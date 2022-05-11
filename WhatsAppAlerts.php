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
        $this->wah = new WhatsAppHelper($this);

        if (!$wamd = $this->wah->parseEmailForWhatsAppMessageDefinition($message)) {
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
        $callback_url   = $this->getInboundUrl();
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
                "raw"           => $this->wah->appendRaw($wamd->getConfig())
            ],
            // Merge in context
            $wamd->getMessageContext()->getContextAsArray()
        );

        $logEntryId = $this->wah->logNewMessage($payload);

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
     * Handle an inbound message (both status callback and reply)
     * @return void
     * @throws Exception
     */
    public function processInboundMessage() {
        try {
            //xdebug_break();
            if ($IM = new InboundMessage()) {
                // We have a valid inbound message (by virtual of a sid)
                usleep(500);   // Give a little time for previous message to be logged

                // Use a lock to keep things under control w/ races and a lot of inbound messages
                $lock = $this->query("SELECT GET_LOCK(?,30)", $this->PREFIX)->fetch_array()[0];
                if ($lock == 0) {
                    $this->emError("Unable to obtain a lock for inbound message", $IM);
                    exit();
                }

                $this->wah = new WhatsAppHelper($this);

                $msid = $IM->getMessageSid();

                if ($entity = $this->wah->getLogByMessageId($msid)) {
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
                    $diff = $this->wah->diffLastRaw($existing_raw, $new_raw);
                    $payload['raw'] = $this->wah->appendRaw($diff, $existing_raw);

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
                    $records = $this->wah->getRecordsByToNumber($from_number);
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
                    $logEntryId = $this->wah->logNewMessage($payload);

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
                            if ($entities = $this->wah->getUndeliveredMessages($record_id)) {
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
                    "source"    => "Undelivered Message",
                    "source_id" => $id,
                    "record_id" => $record_id
                ],
                "REDCap Message" => "This is a redelivery of message #$id - " . $data['message_sid']
            ];

            $this->emDebug("Redelivery Config",$config);
            $wamd = $this->wah->getMessageDefinitionFromConfig($config);

            // New message that will be used for redelivery
            if ($new_id = $this->sendMessage($wamd)) {

                // Update original message
                $update = [
                    "date_redelivered" => strtotime('now'),
                    "status" => "redelivered"
                ];

                // Update raw
                $raw = $this->wah->appendRaw(
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
        $this->wah = new WhatsAppHelper($this);

        $record_id = $_GET['record_id'];
        if ($record_id) {
            $entities = $this->wah->getUndeliveredMessages($record_id);
            echo "<pre>". print_r($entities,true) . "</pre>";
        }

    }

    private function loadSettings() {
        $this->account_sid = $this->getProjectSetting('account-sid');
        $this->token = $this->getProjectSetting('token');
        $this->from_number = $this->getProjectSetting('from-number');
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

    public function getFromNumber()
    {
        if (! $this->settings_loaded) $this->loadSettings();
        return $this->from_number;
    }

    public function getInboundUrl(): string
    {
        $url = $this->getUrl('pages/inbound.php', true, true);
        return $this->checkForOverrideUrl($url);
    }


    /**
     * Fix callback/internal urls for dev purposes, e.g. NGROK.
     * @param $url
     * @return string
     */
    public function checkForOverrideUrl($url): string
    {
        $overrideUrl = $this->getProjectSetting('override-url');
        if (!empty($overrideUrl)) {
            // Make sure callback_override ends with a slash
            if (! str_ends_with($overrideUrl,'/')) $overrideUrl .= "/";
            // Substitute
            $new_url = str_replace(APP_PATH_WEBROOT_FULL, $overrideUrl, $url);
            // $this->emDebug("Overriding url $url with $new_url");
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

        // Append a 1 to some numbers
        if (strlen($clean_number) === 10 && left($clean_number,1) != "1") {
            $clean_number = "1".$clean_number;
        }
        return $prefix . $clean_number;
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
