<?php
namespace Stanford\WhatsAppAlerts;

require_once "emLoggerTrait.php";
require_once "vendor/autoload.php";

use \Exception;
use REDCapEntity\Entity;
use REDCapEntity\EntityDB;
use REDCapEntity\EntityFactory;

require_once("classes/WhatsAppHelper.php");
require_once("classes/MessageContext.php");
require_once("classes/InboundMessage.php");
require_once("classes/WhatsAppMessageDefinition.php");
require_once("classes/Template.php");

class WhatsAppAlerts extends \ExternalModules\AbstractExternalModule {

    use emLoggerTrait;

    private $account_sid;
    private $token;
    private $Client;
    private $from_number;
    private $settings;
    private $settings_loaded;

    private $last_error;

    private $wah;

    /**
     * Generic REDCap Email Hook
     */
    public function redcap_email( $to, $from, $subject, $message, $cc, $bcc, $fromName, $attachments ) {
        // Determine if this outbound email is intended to create a WhatsApp Message
        if (!$wamd = $this->getWAH()->parseEmailForWhatsAppMessageDefinition($message)) {
            // Just send email if not a what's app message
            return true;
        } else {
            // We have a message definition object -- let's try to send it
            try {
                $this->sendMessage($wamd);
            } catch (Exception $e) {
                $this->emError("Caught Exception: " . $e->getMessage());
            }

            // Prevent actual email since this was a What's App attempt
            return false;
        }
    }




    /**
     * Given a WhatsAppMessageDefinition, send the message
     * @param WhatsAppMessageDefinition $wamd
     * @return mixed false or log_id
     * @throws \Twilio\Exceptions\ConfigurationException
     * @throws \Twilio\Exceptions\TwilioException
     */
    public function sendMessage($wamd) {

        $to_number  = $this->formatNumber($wamd->getToNumber());
        $body       = $wamd->getBody();

        $from_number    = $this->formatNumber($this->getFromNumber());
        $callback_url   = $this->getCallbackUrl();
        $client         = $this->getClient();

        $trans = $client->messages->create($to_number,
            [
                "from" => $from_number,
                "body" => $body,
                "statusCallback" => $callback_url
            ]
        );

        if (!empty($trans->errors)) {
            $this->emError("Error sending message: ",
                $trans->errors,
                $trans->errorCode,
                $trans->errorMessage
            );
        }

        # Record results
        $status = $trans->status   ?? "";
        $error = $trans->errorCode ?? "";
        $message_sid = $trans->sid ?? "";
        $this->emDebug("Message $status with sid $message_sid sent");

        // Log the new message
        $payload = array_merge(
            // Message details
            [
                "message_sid"   => $message_sid,
                "template"      => $wamd->getTemplate(),
                "template_id"   => $wamd->getTemplateId(),
                "body"          => $body,
                "to_number"     => $to_number,
                "from_number"   => $from_number,
                "error"         => $error,
                "date_sent"     => strtotime('now'),
                "project_id"    => $this->getProjectId(),
                "raw"           => $this->getWAH()->appendRaw($wamd->getConfig())
            ],
            // Merge in context
            $wamd->getMessageContext()->getContextAsArray()
        );

        $logEntryId = $this->getWAH()->logNewMessage($payload);

        $this->emDebug("Created new Log Entry as " . $logEntryId);

        \REDCap::logEvent(
            "[WhatsApp]<br>Message #" . $logEntryId . " Created",
            "sid: $message_sid, status $status",
            "",
            $wamd->getMessageContext()->getRecordId(),
            $wamd->getMessageContext()->getEventId(),
            $wamd->getMessageContext()->getProjectId()
        );

        return $logEntryId; // empty($error);
    }


    /**
     * Try to determine the valid project id based on the configured what's app number
     * @param $whats_app_number
     * @return array $projects
     */
    public function getProjectsByWhatsAppNumber($whats_app_number)
    {
        // Format number into just numerical portion
        $to_digits = $this->formatNumber($whats_app_number, "");

        // Get all projects configured
        $projects = [];
        foreach ($this->getProjectsWithModuleEnabled() as $local_pid) {
            $pid_from_number = $this->getProjectSetting('from-number', $local_pid);
            $pid_from_digits = $this->formatNumber($pid_from_number, "");
            if ($to_digits == $pid_from_digits) {
                $projects[] = $local_pid;
            }
        }
        return $projects;
    }


    /**
     * Look at each project and determine if any records contain the matching from_number
     * @param $projects
     * @param $from_number
     * @return array    [ $project_id, $record, $event ]
     */
    public function filterProjectsBySender($projects, $from_number)
    {
        $from_digits = $this->formatNumber($from_number, '');
        $matches=[];
        foreach ($projects as $local_pid) {
            $pid_phone_field = $this->getProjectSetting('inbound-phone-field', $local_pid);
            $this->emDebug("Checking project $local_pid for records with $pid_phone_field that is similar to $from_number");
            $result = $this->query(
                "SELECT record, event_id, value from redcap_data where project_id = ? and field_name = ?",
                [$local_pid, $pid_phone_field]
            );
            while ($row = $result->fetch_assoc()) {
                $record_phone_digits = $this->formatNumber($row['value'], '');
                if (!empty($record_phone_digits) && $from_digits == $record_phone_digits) {
                    $this->emDebug("Found an inbound match: " . json_encode($row));
                    // We have a match
                    $matches[] = [
                        "project_id" => $local_pid,
                        "record_id"  => $row['record'],
                        "event_id"   => $row['event_id']
                    ];
                }
            }
        }
        return $matches;
    }



    /**
     * Handle an inbound message (both status callback and reply)
     * @return void
     * @throws Exception
     */
    public function processInboundMessage($type) {
        try {
            //xdebug_break();
            if ($IM = new InboundMessage()) {

                // Use a lock to keep things under control w/ races and a lot of inbound messages
                $lock = $this->query("SELECT GET_LOCK(?,30)", $this->PREFIX)->fetch_array()[0];
                if ($lock == 0) {
                    $this->emError("Unable to obtain a lock for inbound message", $IM);
                    exit("Unable to obtain lock for inbound message -- see logs");
                }

                if ($type == "inbound") { // User has responded to a message
                    $this->processInboundReply($IM);
                } elseif ($type == "callback") {
                    // Process callback update :: read/
                    $this->emDebug("Callback from project: ",$_GET['pid'], $_GET['project_id']);
                    $this->processInboundCallback($IM);
                }
            } else {
                $this->emDebug("Invalid inbound message");
            }
        } catch (Exception $e) {
            $this->emError("Exception on inbound message", $e->getMessage());
        }
    }



    private function processInboundCallback($IM) {
        $msid = $IM->getMessageSid();
        $notes = [];

        if ($entity = $this->getWAH()->getLogByMessageId($msid)) {
            $id = $entity->getId();
            $data = $entity->getData();
            $status = $IM->getStatus();
            $error = $IM->getErrorCode();
            $icebreaker_needed = false;


            $payload = [];
            if ($data['status'] == "undelivered" && $data['error'] == 63016 &&  // previous logged message values
                $status == "sent"                                               // current message update
            ) {
                // You would think the order should be queued -> sent -> error/delivered -> read
                // but in some cases, the error message arrives before the sent message.  So, if we already have an
                // error and get a 'sent' status update, we are going to mostly ignore this.
                // TODO: Log in raw that we ignored a sent update... add look at last raw ts to see if has been very little
                // time to verify collision

                $this->emDebug("Going to ignore a sent after undelivered update");
                $notes[] = "Ignoring a status update from " . $data['status'] . " to $status - assuming incorrect order of callback messages";
            } else {
                // Update status and error messages
                $payload['status'] = $status;
                $payload['error'] = $error;
            }

            // Convert previous raw value to array
            // TODO: Fix and add notes -- this is way too confusing/much data...
            $existing_raw = json_decode(json_encode($data['raw']), true);
            $new_raw = $IM->getRaw();
            $diff = $this->getWAH()->diffLastRaw($existing_raw, $new_raw);
            $payload['raw'] = $this->getWAH()->appendRaw($diff, $existing_raw);
            $payload['project_id'] = $_GET['pid'];

            $diff_summary = [];
            foreach ($diff as $k => $v) $diff_summary[] = "$k = '$v'";

            switch ($status) {
                case "sent":
                    $payload['date_sent'] = strtotime('now');
                    break;
                case "delivered":
                    $payload['date_delivered'] = strtotime('now');
                    break;
                case "read":
                    $payload['date_read'] = strtotime('now');
                    break;
                case "undelivered":
                    // Message was undelivered
                    if ($IM->isIceBreakerError()) {
                        $template_id = $data['template_id'];
                        $icebreaker_template_id = $this->getProjectSetting('icebreaker-template-id', $data['project_id']);
                        // $this->emDebug($template_id, $icebreaker_template_id);
                        if (!empty($template_id) && $template_id == $icebreaker_template_id) {
                            // We don't trigger an icebreaker on a failed icebreaker to prevent a loop
                            $this->emError("Detected rejected icebreaker message #" . $id);
                        } else {
                            $icebreaker_needed = true;
                        }
                    }
                    break;
                case "failed":
                    $this->emDebug("Failed Delivery", $_POST);
                    break;
                default:
                    $this->emError("Unhandled callback status: $status", $_POST);
            }

            if (!empty($error)) $payload['date_error'] = strtotime('now');

            // Save the update
            if ($entity->setData($payload)) {
                if ($result = $entity->save()) {
                    $this->emDebug("Updated Entity #" . $id . " - $status $error");
                    \REDCap::logEvent(
                        "[WhatsApp]<br>Message #" . $entity->getId() . " Status Update",
                        implode(",\n", $diff_summary),
                        "", $data['record_id'], $data['event_id'], $data['project_id']
                    );

                    if ($icebreaker_needed) {
                        $record_id = $data['record_id'];
                        $to_number = $data['to_number'];
                        $this->sendIcebreakerMessage($record_id, $to_number);
                    }

                    return true;
                } else {
                    $this->emError("Error saving update", $payload, $_POST, $entity->getErrors());
                }
            } else {
                $this->emDebug("Error setting payload", $payload, $entity->getErrors());
            }
        }
        # something went wrong
        return false;



    }


    /**
     * Called from processInboundMessage - this method handles reply messages only
     * @param $IM
     * @return false|void
     */
    private function processInboundReply($IM) {

        // We have a valid inbound message - see if the to number (e.g. What's App Project Number) is registered
        // to any active projects on our server
        $to_number = $IM->getToNumber();
        $possible_projects = $this->getProjectsByWhatsAppNumber($to_number);
        $this->emDebug($to_number . " is registered to the following projects: " . json_encode($possible_projects));

        // Given a set of matching projects, lets try to identify participant records that match the sender's number
        $from_number = $IM->getFromNumber();
        $matches = $this->filterProjectsBySender($possible_projects, $from_number);

        $payload = [
            "message_sid"   => $IM->getMessageSid(),
            "body"          => $IM->getBody(),
            "to_number"     => $IM->getToNumber(),
            "from_number"   => $from_number,
            "date_received" => strtotime('now'),
            "status"        => "received",
            "source"        => "Inbound message",
            "raw"           => $this->getWAH()->appendRaw($IM->getRaw(), [])
        ];

        if (empty($matches)) {
            $this->emLog("Unable to identify a matching record for the reply from $from_number in projects " . implode(",", $possible_projects));
            // TODO: We should still probably log the message to the table even if it isn't associated with a project or record
            $logEntryId = $this->getWAH()->logNewMessage($payload);
            foreach ($possible_projects as $project_id) {
                \REDCap::logEvent(
                    "[WhatsApp]<br>Unable to assign incoming message to record / project",
                    "The sender's number, " . $from_number . ", was not found in this project.  See message #$logEntryId",
                    "", "", "", $project_id
                );
            }
            return false;
        } else if(count($matches) > 1) { // multiple projects exist with the same
            $this->emLog("Found multiple potential records that match the inbound message from $from_number", $matches);
        } else {
            // Found just one match
            $this->emDebug("Matched reply from $from_number to " . json_encode($matches));
        }

        $payload['raw']['matches'] = $matches;
        // Log these messages (only log if we know the record/project)
        foreach ($matches as $match) {
            $record_id = $match['record_id'];
            $project_id = $match['project_id'];
            $this_payload = array_merge($payload, [
                "record_id"     => $record_id,
                "project_id"    => $project_id,
            ]);
            $this->settings_loaded=false;
            $_GET['pid'] = $project_id; //For settings, get From number

            $this->emDebug("About to process ", $this_payload);

            if ($logEntryId = $this->getWAH()->logNewMessage($this_payload)) {
                $this->emDebug("Log Entry ID:", $logEntryId);

                \REDCap::logEvent(
                    "[WhatsApp]<br>Incoming message received",
                    "New message from sender at " . $IM->getFromNumber() . " recorded as message #$logEntryId\n" . $IM->getBody(),
                    "", $record_id, "", $project_id
                );

                // Since we received a reply from a person, we must assume we have an open 24 hour window
                // Let's see if we have any undelivered messages
                if ($entities = $this->getWAH()->getUndeliveredMessages($record_id, $project_id)) {
                    $this->emDebug("Log ID $logEntryId: Found " . count($entities) . " undelivered messages for record $record_id in project $project_id");
                    $this->sendUndeliveredMessages($entities);
                } else {
                    $this->emDebug("Log ID $logEntryId: No undelivered messages for record $record_id in project $project_id");
                };
            } else {
                // Error occurred
                $this->emError("Unable to save new IM", $this_payload);
            }
        }
    }


/*
    public function processInboundMessageOld() {
        try {
            //xdebug_break();
            if ($IM = new InboundMessage()) {
                // We have a valid inbound message (by virtual of a sid)
                usleep(500);   // Give a little time for previous message to be logged

                // Use a lock to keep things under control w/ races and a lot of inbound messages
                $lock = $this->query("SELECT GET_LOCK(?,30)", $this->PREFIX)->fetch_array()[0];
                if ($lock == 0) {
                    $this->emError("Unable to obtain a lock for inbound message", $IM);
                    exit("Unable to obtain lock for inbound message -- see logs");
                }

                $msid = $IM->getMessageSid();

                if ($entity = $this->getWAH()->getLogByMessageId($msid)) {
                    // This request is related to an existing sid -- likely a callback
                    $id = $entity->getId();
                    $data = $entity->getData();
                    $status = $IM->getStatus();
                    $error = $IM->getErrorCode();
                    $icebreaker_needed = false;

                    $payload = [];
                    if ($data['status'] == "undelivered" && $data['error'] == 63016 && $$status == "sent") {
                        $this->emDebug("Going to ignore a sent after undelivered update");
                        // $_POST['REDCap Warning'] = "Ignoring this sent callback because I think it is a timestamp collision with an undeliverable update";
                    } else {
                        $payload['status'] = $status;
                        $payload['error'] = $error;
                    }

                    // Convert previous value to array
                    $existing_raw = json_decode(json_encode($data['raw']),true);
                    $new_raw = $IM->getRaw();
                    $diff = $this->getWAH()->diffLastRaw($existing_raw, $new_raw);
                    $payload['raw'] = $this->getWAH()->appendRaw($diff, $existing_raw);

                    $diff_summary = [];
                    foreach ($diff as $k=>$v) $diff_summary[] = "$k = '$v'";

                    switch ($status) {
                        case "sent":
                            $payload['date_sent'] = strtotime('now');
                            break;
                        case "delivered":
                            $payload['date_delivered'] = strtotime('now');
                            break;
                        case "read":
                            $payload['date_read'] = strtotime('now');
                            break;
                        case "undelivered":
                            // Message was undelivered
                            if ($IM->isIceBreakerError()) {
                                $template_id = $data['template_id'];
                                $icebreaker_template_id = $this->getProjectSetting('icebreaker-template-id');
                                // $this->emDebug($template_id, $icebreaker_template_id);
                                if (!empty($template_id) && $template_id == $icebreaker_template_id) {
                                    // We don't trigger an icebreaker on a failed icebreaker to prevent a loop
                                    $this->emError("Detected rejected icebreaker message #". $id);
                                } else {
                                    $icebreaker_needed = true;
                                }
                            }
                            break;
                        case "failed":
                            $this->emDebug("Failed Delivery", $_POST);
                            break;
                        default:
                            $this->emError("Unhandled callback status: $status", $_POST);
                    }

                    if (!empty($error)) $payload['date_error'] = strtotime('now');

                    // Save the update
                    if ($entity->setData($payload)) {
                        if ($result = $entity->save()) {
                            $this->emDebug("Updated Entity #" . $id . " - $status $error");
                            \REDCap::logEvent(
                                "[WhatsApp]<br>Message #" . $entity->getId() . " Status Update",
                                implode(",\n", $diff_summary),
                                "", $data['record_id'], $data['event_id'], $data['project_id']
                            );

                            if ($icebreaker_needed) {
                                $record_id = $data['record_id'];
                                $to_number = $data['to_number'];
                                $this->sendIcebreakerMessage($record_id, $to_number);
                            }

                            return true;
                        } else {
                            $this->emError("Error saving update", $payload, $_POST, $entity->getErrors());
                        }
                    } else {
                        $this->emDebug("Error setting payload", $payload, $entity->getErrors());
                    }

                    # something went wrong
                    return false;

                } else {
                    // This is a new message sid - likely a user-entered reply
                    // xdebug_break();
                    // Have we sent messages to this number before
                    $from_number = $IM->getFromNumber();
                    $records = $this->getWAH()->getRecordsByToNumber($from_number);
                    $record_count = count($records);

                    if ($record_count == 1) {
                        $record_id = current($records);
                        $this->emDebug("Assigned inbound message to record $record_id based on from number: $from_number");
                    } else {
                        if ($record_count == 0) {
                            $this->emDebug("Unable to identify sender by from number: $from_number");
                            // $_POST['REDCap Warning'] = "Unable to identify sender - no match from previous outgoing messages";
                        } elseif($record_count > 1) {
                            $this->emDebug("More than one record has the same number: $from_number");
                            // $_POST['REDCap Warning'] = "Unable to identify sender - multiple messages match the sender phone number ("
                            //     . $IM->getFromNumber() . ")";
                        }
                        $record_id = "";
                    }

                    // Save inbound message to logs
                    $payload = [
                        "message_sid"   => $IM->getMessageSid(),
                        "body"          => $IM->getBody(),
                        "to_number"     => $IM->getToNumber(),
                        "from_number"   => $from_number,
                        "date_received" => strtotime('now'),
                        // "raw"           => $this->appendRaw([], $IM->getRaw()),
                        "status"        => "received",
                        "record_id"     => $record_id
                    ];
                    $logEntryId = $this->getWAH()->logNewMessage($payload);

                    if ($logEntryId === false) {
                        // Error occurred
                        $this->emError("Unable to save new IM");
                    } else {
                        // If we were able to match the incoming message to a record, we need to check a few things
                        if ($record_count == 1) {
                            \REDCap::logEvent(
                                "[WhatsApp]<br>Incoming message received",
                                "New message from sender at " . $IM->getFromNumber() . " recorded as message #$logEntryId\n" . $IM->getBody(),
                                "", $record_id, "", $this->getProjectId()
                            );

                            // Since we received a reply from a person, we must assume we have an open 24 hour window
                            // Let's see if we have any undelivered messages
                            if ($entities = $this->getWAH()->getUndeliveredMessages($record_id)) {
                                $this->emDebug("Found " . count($entities) . " undelivered messages for record " . $record_id);
                                $this->sendUndeliveredMessages($entities);
                            } else {
                                $this->emDebug("No undelivered messages for record $record_id");
                            };
                        } else {
                            if ($record_count == 0) {
                                \REDCap::logEvent(
                                    "[WhatsApp]<br>Unable to assign incoming message to record",
                                    "The sender's number, " . $IM->getFromNumber() . ", was not found.  See message #$logEntryId",
                                    "", "", "", $this->getProjectId()
                                );
                            } else {
                                \REDCap::logEvent(
                                    "[WhatsApp]<br>Unable to assign incoming message to record",
                                    "The sender's number, " . $IM->getFromNumber() . ", is used by " . $record_count . " records.  See message #$logEntryId",
                                    "", "", "", $this->getProjectId()
                                );
                            }
                        }
                    }
                }
            } else {
                $this->emDebug("Invalid inbound message");
            }
        } catch (Exception $e) {
            $this->emError("Exception on inbound message", $e->getMessage());
        }
    }
*/

    public function sendIcebreakerMessage($record_id, $to_number) {
        try {
            $icebreaker_template       = $this->getProjectSetting('icebreaker-template-id');
            $icebreaker_variables_json = $this->getProjectSetting('icebreaker-variables');

            // Substitute variables with context
            if (!empty($icebreaker_variables_json)) {
                $this->emDebug("Record $record_id in project " . $this->getProjectId());
                $piped_vars = \Piping::replaceVariablesInLabel($icebreaker_variables_json, $record_id,
                    null, 1, null, false,
                    $this->getProjectId(), false
                );
                $this->emDebug("Setting Icebreaker Variables Json to: " . $piped_vars);
                $variables = json_decode($piped_vars,true);
                // $this->emDebug("As an array", $variables);
            } else {
                $variables = [];
            }

            // Create a config object which will tell the WAH class to send a message
            $message_config = [
                "type" => "whatsapp",
                "template_id" => $icebreaker_template,
                "variables" => $variables,
                "number" => $to_number,
                "context" => [
                    "record_id" => $record_id
                ]
                // "REDCap Message" => "Triggered by failed message " . $message_id
            ];

            $this->emDebug("Config",$message_config);

            $wamd = new WhatsAppMessageDefinition($this);
            $wamd->parseDefinition($message_config);
            $this->sendMessage($wamd);

            // if($wam2->createOutboundMessage($config)) {
            //     # Load Twilio settings
            //     $wam2->setAccountSid($this->getProjectSetting('account-sid'));
            //     $wam2->setToken($this->getProjectSetting('token'));
            //     $wam2->setFromNumber($this->getProjectSetting('from-number'));
            //     $wam2->setCallbackUrl($this->getInboundUrl());
            //
            //     # Ensure we have a valid configuration
            //     if (! $wam2->configurationValid()) {
            //         $this->emError("configurationValid failed due to errors", $wam2->getErrors());
            //         return false;
            //     }
            //
            //     # Send message
            //     if ($wam2->sendMessage()) {
            //         $wam->setDateSent(strtotime('now'));
            //         $wam->logNewMessage();
            //     } else {
            //         $this->emError("Something went wrong sending message");
            //     };
            // } else {
            //     $this->emError("Unable to loadByConfig");
            // };
        } catch (\Exception $e) {
            $this->emError("Unable to load icebreaker template: " . $e->getMessage());
        }
    }

    public function sendUndeliveredMessages($entities) {

        // Each entity is an undelivered message
        foreach ($entities as $entity) {
            /** @var Entity $entity */
            // Retry Delivery
            $id = $entity->getId();
            $data = $entity->getData();

            $this->emDebug("Retrying entity " . $id);
            $record_id = $data['record_id'];
            $original_body = $data['body'];
            $to_number = $data['to_number'];
            $project_id = $data['project_id'];

            // Get Age
            $created_date = $entity->getCreationTimestamp();
            $age = strtotime('now') - $created_date;
            $mins = rounddown($age/60);
            $hours = rounddown($mins/60);
            if ($hours > 3) {
                $age_nice = "~$hours hours";
            } elseif ($hours > 0) {
                $remainder = $mins - ($hours * 60);
                $age_nice = "$hours hour" . ( $hours > 1 ? 's' : '') .
                    " $remainder min" . ( $mins > 1 ? 's' : '');
            } else {
                $age_nice = "$mins min" . ($mins > 1 ? 's' : '');
            }
            $new_body = "_Originally sent $age_nice ago (#$id)_ \n\n" . $original_body;

            // Generate a new message
            $config = [
                "number" => $to_number,
                "body" => $new_body,
                "context" => [
                    "source"    => "Redelivered Message",
                    "source_id" => $id,
                    "record_id" => $record_id,
                    "project_id" => $project_id
                ],
                "REDCap Message" => "This is a redelivery of message #$id - " . $data['message_sid']
            ];

            $this->emDebug("Redelivery Config",$config);
            $wamd = $this->getWAH()->getMessageDefinitionFromConfig($config);

            // New message that will be used for redelivery
            if ($new_id = $this->sendMessage($wamd)) {

                // Update original message
                $update = [
                    "date_redelivered" => strtotime('now'),
                    "status" => "redelivered"
                ];

                // Update raw
                $raw = $this->getWAH()->appendRaw(
                    array_merge(
                        $update,
                        [
                            "REDCap Message" => "Redelivered as message #$new_id after delay of $age_nice",
                        ]),
                    $data['raw']
                );
                $update['raw'] = $raw;

                $this->emDebug("About to set data to ", $update);
                if (!$entity->setData($update)) {
                    $this->emError("An error occurred setting the data:", $update);
                };

                $id = $entity->save();
                if ($id === false) {
                    $this->emError("An error occurred saving the data:", $update);
                } else {
                    $this->emDebug("Message #$id - resent as #$new_id");
                }
            } else {
                $this->emError("Something went wrong sending message");
            }
        }
    }


    // TO BE DELETED
    public function testUndelivered() {
        $record_id = $_GET['record_id'];
        if ($record_id) {
            $entities = $this->getWAH()->getUndeliveredMessages($record_id);
            echo "<pre>". print_r($entities,true) . "</pre>";
        }

    }

    private function loadSettings($project_id = "") {
        if(empty($project_id)) $project_id = $this->getProjectId();
        $this->account_sid = $this->getProjectSetting('account-sid', $project_id);
        $this->token = $this->getProjectSetting('token', $project_id);
        $this->from_number = $this->getProjectSetting('from-number', $project_id);
        $this->settings_loaded = true;
    }

    public function getAccountSid() {
        if (! $this->settings_loaded) $this->loadSettings();
        return $this->account_sid;
    }

    public function getToken() {
        if (! $this->settings_loaded) $this->loadSettings();
        return $this->token;
    }

    public function getClient() {
        if (empty($this->Client)) {
            $account_sid    = $this->getAccountSid();
            $token          = $this->getToken();
            $this->Client = new \Twilio\Rest\Client($account_sid, $token);
        }
        return $this->Client;
    }

    /**
     * Get the Whats App Helper Object
     * @return WhatsAppHelper
     */
    private function getWAH() {
        if (empty($this->wah)) {
            $this->wah = new WhatsAppHelper($this);
        }
        return $this->wah;
    }

    public function getFromNumber()
    {
        if (! $this->settings_loaded) $this->loadSettings();
        return $this->from_number;
    }

    public function getCallbackUrl(): string
    {
        $url = $this->getUrl('pages/callback.php', true, true);
        return $this->checkForOverrideUrl($url);
    }


    /**
     * Fix callback/internal urls for dev purposes, e.g. NGROK.
     * @param $url
     * @return string
     */
    public function checkForOverrideUrl($url): string
    {
        $overrideUrl = trim($this->getSystemSetting('override-url'));
        if (!empty($overrideUrl)) {
            // Make sure callback_override ends with a slash
            if (! str_ends_with($overrideUrl,'/')) $overrideUrl .= "/";
            // Substitute
            $new_url = str_replace(APP_PATH_WEBROOT_FULL, $overrideUrl, $url);
            $this->emDebug("Overriding url $url with $new_url");
        } else {
            $new_url = $url;
        }
        return strval($new_url);
    }

    /**
     * Build a number for What'sApp
     * @param $number string
     * @param $prefix string (whatsapp:+)
     * @return string
     */
    public function formatNumber($number, $prefix = "whatsapp:+") {
        // Strip anything but numbers and add a plus
        $clean_number = preg_replace('/[^\d]/', '', $number);

        // Append a 1 to 10 digit numbers
        if (strlen($clean_number) === 10 && left($clean_number,1) != "1") {
            $clean_number = "1".$clean_number;
        }
        return $prefix . $clean_number;
    }

    /**
     * Injects javascript files and any necessary data necessary before page load
     * @return void
     */
    public function injectJavascript()
    {
        try {

            $jsFilePath = $this->getUrl('scripts/inject.js');
            $ajaxFilePath = json_encode($this->getUrl('pages/ajax_handler.php'));
            $csrfToken = json_encode($this->getCSRFToken());
            print "<script type='module' src=$jsFilePath></script>";
            print "<script type='text/javascript'>var ajaxUrl = $ajaxFilePath; var csrfToken = $csrfToken</script>";


        } catch (\Exception $e) {
            \REDCap::logEvent("Error injecting js: $e");
            $this->emError($e);
        }
    }
    public function validatePhoneNumber($phoneNumber)
    {
        $possiblePhoneNumbers = json_decode(\REDCap::getData('json', NULL, array('phone')));
        foreach($possiblePhoneNumbers as $number){
            if($number->phone === $phoneNumber)
                return $number->phone;
        }
        return NULL;
    }
    public function getMessagesByPhoneNumber($phoneNumber)
    {
        $searchPhone = $this->validatePhoneNumber($phoneNumber);
        $formatted = $this->formatNumber($searchPhone);
        if($searchPhone) {
            $factory = new EntityFactory();
            $resultsFrom = $factory->query('whats_app_message')
                ->condition('project_id', PROJECT_ID)
                ->condition('from_number', $formatted)
                ->orderBy('updated')
                ->execute();
            $resultsTo = $factory->query('whats_app_message')
                ->condition('project_id', PROJECT_ID)
                ->condition('to_number', $formatted)
                ->orderBy('updated')
                ->execute();

            $enc = array_merge($this->parseFactoryData($resultsFrom), $this->parseFactoryData($resultsTo));

            return json_encode($enc);
        } else {
            \REDCap::logEvent("Phone number passed via ajax does not match entry in database");
            $this->emError('Phone number passed via ajax does not match entry in database');
        }

    }

    public function parseFactoryData($results)
    {
        $enc = [];

        foreach($results as $index => $message) {
            $obj = [];
            $obj['body'] = $message->getDataValue('body');
            $obj['date_sent'] = $message->getDataValue('date_sent');
            $obj['date_received'] = $message->getDataValue('date_received');
            $obj['to_number'] = $message->getDataValue('to_number');
            $obj['from_number'] = $message->getDataValue('from_number');
            $obj['updated'] = $message->getDataValue('updated');
            $obj['source'] = $message->getDataValue('source');
            $enc[] = $obj;
        }
        return $enc;
    }
    public function fetchPhoneNumbers() {
        return json_decode(\REDCap::getData('json', NULL, array('phone')));
    }

    /************ REDCAP ENTTIY SECTION ********************/
    function redcap_module_system_enable($version) {
        // Create the Entity table(s) when the module is enabled if it doesn't already exist
        \REDCapEntity\EntityDB::buildSchema($this->PREFIX);
    }

    function redcap_every_page_top($project_id)
    {
        // The following code was recommended by the redcap_entity module
        if (!defined('REDCAP_ENTITY_PREFIX')) {
            $this->emDebug("Delaying execution...");
            $this->delayModuleExecution();
            // Exits gracefully when REDCap Entity is not enabled.
            return;
        }

//        if($_GET['page'] === 'pages/conversation')
//            $this->injectJavascript();

        // ABM: I DONT THINK THIS IS NEEDED AS THIS ONLY WILL BE RUN IN PROJECT CONTEXT???
        // // Insert JS for control center page
        // if (strpos(PAGE, 'ExternalModules/manager/control_center.php') !== false) {
        //     $this->includeJs('js/config.js');
        //     $this->setJsSettings(['modulePrefix' => $this->PREFIX]);
        // }
    }

    function redcap_entity_types() {
        $types = [];

        $types['whats_app_message'] = [
            'label' => 'WhatsApp Message',
            'label_plural' => 'WhatsApp Messages',
            'icon' => 'email',
            'class' => [
                'name' => 'Stanford\WhatsAppAlerts\MessageLogs',
                'path' => 'classes/MessageLogs.php',
            ],
            'properties' => [
                'message_sid' => [
                    'name' => 'Message SID',
                    'type' => 'text',
                ],
                'template_id' => [
                    'name' => 'Template ID',
                    'type' => 'text',
                ],
                'template' => [
                    'name' => 'Template',
                    'type' => 'text',
                ],
                'body' => [
                    'name' => 'Body',
                    'type' => 'text',
                ],
                'source' => [
                    'name' => 'Source', // Inbound or ASI or Email or Alert, etc...
                    'type' => 'text',
                ],
                'source_id' => [
                    'name' => 'Source ID',
                    'type' => 'text',
                ],
                'record_id' => [
                    'name' => 'Record Id',
                    'type' => 'text',
                ],
                'event_id' => [
                    'name' => 'Event ID',
                    'type' => 'text',
                ],
                'event_name' => [
                    'name' => 'Event Name',
                    'type' => 'text',
                ],
                'instance' => [
                    'name' => 'Instance',
                    'type' => 'text',
                ],
                'to_number' => [
                    'name' => 'To Number',
                    'type' => 'text',
                ],
                'from_number' => [
                    'name' => 'From Number',
                    'type' => 'text',
                ],
                'date_sent' => [
                    'name' => 'Sent',
                    'type' => 'date',
                    // 'default' => 'NULL',
                ],
                'date_delivered' => [
                    'name' => 'Delivered',
                    'type' => 'date',
                    // 'default' => 'NULL',
                ],
                'date_received' => [
                    'name' => 'Received',
                    'type' => 'date',
                    // 'default' => 'NULL',
                ],
                'date_read' => [
                    'name' => 'Read',
                    'type' => 'date',
                    // 'default' => 'NULL',
                ],
                'date_error' => [
                    'name' => 'Error',
                    'type' => 'date',
                    // 'default' => 'NULL',
                ],
                'date_redelivered' => [
                    'name' => 'Redelivered',
                    'type' => 'date',
                ],
                'error' => [
                    'name' => 'Error#',
                    'type' => 'text',
                ],
                'status' => [
                    'name' => 'Status',
                    'type' => 'text',
                ],
                'raw' => [
                    'name' => 'Raw',
                    'type' => 'json',
                ],
                'project_id' => [
                    'name' => 'Project ID',
                    'type' => 'project',
                ],
                'created_by' => [
                    'name' => 'Created By',
                    'type' => 'user'
                ]

            ],
            'special_keys' => [
                // 'project' => 'project_id',
                'author' => 'created_by'
            ]
        ];

        return $types;
    }

}
