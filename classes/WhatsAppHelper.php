<?php
namespace Stanford\WhatsAppAlerts;

use \REDCapEntity\Entity;
use \REDCapEntity\EntityFactory;
use \Exception;

/**
 * Class WhatsAppMessage
 * @property WhatsAppAlerts $module
 * @property Entity $entity
 */
class WhatsAppHelper
{

    const ICEBREAKER_ERROR = 63016;

    private $module;        // The parent EM

    private $template_id;
    private $template;
    private $message;       // Finished message formed from variables

    private $messageContext;
    // private $source;
    // private $source_id;
    // private $record_id;
    // private $event_id;
    // private $event_name;
    // private $instance;

    private $message_sid;   // Message SID
    private $to_number;
    private $from_number;
    private $date_sent;
    private $date_delivered;
    private $date_received;
    private $date_read;
    private $date_error;
    private $error;
    private $status;
    private $raw;

    private $created;

    public $entity;
    protected $config;      // message configuration parsed from email body
    public $trans;

    // CONTEXT PARAMETERS
    private $project_id;

    private $icebreaker_needed; // The record data where an icebreaker should be sent

    private $account_sid;
    private $token;
    private $callback_url;

    private $errors = [];


    public function __construct($module) {
        // Link the parent EM so we can use helper methods
        $this->module    = $module;
    }

    // TODO: move into constructor as optional parameter

    /**
     * @param $config
     * @throws Exception
     */
    public function createOutboundMessage($config) {
        // Load the message configuration - which should look something like the example below:
        /*
             [
                "type" => "whatsapp",
                "template_id" => "ACe3ab1c8d7222aedd52dc8fff05d1feb9_en",
                "template" => "messages_awaiting",
                // "language" => "en",
                "variables" => [ "[event_1_arm_1][baseline]", "Hello" ],
                "body" => "blank if free text, otherwise will be calculated based on template and variables",
                "number" => "[event_1_arm_1][whats_app_number]",
                // "log_field" => "field_name",
                // "log_event_name" => "log_event_name",
                "context" => [
                    "project_id" => "[project-id]",
                    "event_name" => "[event-name]",
                    "record_id" => "[record-id]",
                    "instance" => "[current-instance]"
                ]
            ]
        */

        // Set context
        $context              = empty($config['context']) ? [] : $config['context'];
        $this->messageContext = new MessageContext($context);

        // $this->source     = $mc->getSource();
        // $this->source_id  = $mc->getSourceId();
        // $this->project_id = $mc->getProjectId();
        // $this->record_id  = $mc->getRecordId();
        // $this->event_id   = $mc->getEventId();
        // $this->event_name = $mc->getEventName();
        // $this->instance   = $mc->getInstance();

        // $this->setContext($context);

        // Parse Message Config



        // $this->setTemplate($config['template'] ?? '');
        // $this->setTemplateId($config['template_id'] ?? '');

        $variables = $config['variables'] ?? [];

        // Determine if message uses a template or is free text
        // $template = $config['template'];
        if ($template_id = $config['template_id'] ?? false) {

        // if (!empty($this->getTemplateId())) {
            // We are using a template -- lets load the template
            $t = new Template($this->module, $template_id);

            // Let's create the message
            $message = $t->getMessageFromVariables($variables);
            $template_name = $t->getTemplateName();
            // $this->setMessage($t->getMessageFromVariables($variables));

            // ??
            // $this->template = $t->getTemplateName();
        } else {
            // No template - lets build message from raw body
            $body = html_entity_decode($config['body']);
            $message = strip_tags($body, 'a');
            // $this->message = strip_tags($body,'');
            $this->module->emDebug("Decoding body", $config['body'], $body, $message);
        }

        $this->setMessage($message);
        $this->setToNumber($config['number']);
        $this->appendRaw($config);
    }



    // TODO: Seems like the wrong place
    public function loadFromEntity($entity) {
        $this->entity = $entity;
        $data = $entity->getData();
        foreach ($data as $k => $v) {
            if (property_exists($this, $k)) {
                $this->{$k} = $v;
                // $this->module->emDebug("Loading $k...");
            } else {
                // $this->module->emDebug("Skipping $k...");
            }
        }
    }


    /**
     * @returns bool
     * @throws \Twilio\Exceptions\ConfigurationException
     * @throws \Twilio\Exceptions\TwilioException
     */
    public function sendMessage() {
        # Send message
        $account_sid = $this->account_sid;
        $token = $this->token;
        $client = new \Twilio\Rest\Client($account_sid, $token);

        $this->trans = $client->messages->create("whatsapp:" . $this->to_number,
            [
                "from" => "whatsapp:" . $this->getFromNumber(),
                "body" => $this->getMessage(),
                "statusCallback" => $this->callback_url
            ]
        );

        if (!empty($this->trans->errors)) {
            $this->module->emError("Error sending message: ",
                $this->trans->errors,
                $this->trans->errorCode,
                $this->trans->errorMessage
            );
        } else {
            # Record results
            $this->status = $this->trans->status;
            $this->message_sid = $this->trans->sid;
            $this->module->emDebug("Message $this->status with sid $this->message_sid");
        }
        return empty($this->trans->errors);
    }


    public function getMessageAge() {
        $created_date = $this->entity->getCreationTimestamp();
        $age = strtotime('now') - $created_date;
        // $this->module->emDebug(strtotime('now'), $created_date, $age);

        $mins = rounddown($age/60);
        $hours = rounddown($mins/60);
        if ($hours > 3) {
            $s = "~$hours hours";
        } elseif ($hours > 0) {
            $remainder = $mins - ($hours * 60);
            $s = "$hours hour" . ( $hours > 1 ? 's' : '') .
                "$remainder min" . ( $mins > 1 ? 's' : '');
        } else {
            $s = "$mins min" . ($mins > 1 ? 's' : '');
        }
        return $s;
    }


    /**
     * @param array $newRaw
     * @return void
     */
    public function appendRaw(array $newRaw): void {
        $i = strval(microtime(true));
        $raw = $this->getRaw();
        $raw[$i] = $newRaw;
        $this->setRaw($raw);
    }


    /**
     * Log a NEW message
     * @param array $raw_config
     * @return mixed
     */
    public function logNewMessage() {
        $factory = new EntityFactory();
        $payload = $this->getPayload();
        $entity = $factory->create('whats_app_message', $payload);
        if ($entity == false) {
            // There was an error creating the entity:
            $this->module->emError("Error creating entity with payload", $payload);
            return false;
        } else {
            $id = $entity->getId();
            $this->module->emDebug("Message #$id - logged as $this->message_sid");
            return $id;
        }
    }





    // public function sendIcebreakerMessage($record_id) {
    //
    // }

    // /**
    //  * Twilio calls a callback URL.  This function takes the results of that callback and uses them to update entity
    //  * @return bool
    //  * @throws Exception
    //  */
    // public function updateLogStatusCallback() {
    //     $this->module->emDebug("Callback", json_encode($_POST));
    //     // REMOVE DEPRECATED TAGS: https://www.twilio.com/blog/programmable-messaging-sids
    //     unset($_POST['SmsMessageSid']);
    //     unset($_POST['SmsSid']);
    //     /*
    //         [SmsSid] => SMeb941545a1eb46cab32c67df2f8bef62
    //         [SmsStatus] => sent
    //         [Body] => This is *bold* and _underlined_...
    //         [MessageStatus] => sent
    //         [ChannelToAddress] => +1650380XXXX
    //         [To] => whatsapp:+16503803405
    //         [ChannelPrefix] => whatsapp
    //         [MessageSid] => SMeb941545a1eb46cab32c67df2f8bef62
    //         [AccountSid] => AC4c78ad3161bed65c08e36f77847f914a
    //         [StructuredMessage] => false
    //         [From] => whatsapp:+14155238886
    //         [ApiVersion] => 2010-04-01
    //         [ChannelInstallSid] => XEcc20d939f803ee381f2442185d0d5dc5
    //         (optional)
    //         [ErrorCode] => 63016,
    //         [EventType] => "UNDELIVERED"
    //      */
    //
    //
    //     $this->message_sid  = $_POST['MessageSid'] ?? null;
    //     $this->status       = $_POST['SmsStatus'] ?? '';
    //     $this->error_code   = $_POST['ErrorCode'] ?? '';
    //
    //     // Ignore misc posts to endpoint
    //     if (empty($this->message_sid)) {
    //         $this->module->emError("Unable to parse inbound message sid:", $_POST);
    //         return false;
    //     }
    //
    //     // See if message SID exists
    //     $factory = new EntityFactory();
    //     $results = $factory->query('whats_app_message')
    //         ->condition('sid', $this->message_sid)
    //         ->execute();
    //
    //     if (empty($results)) {
    //         throw new Exception ("Unable to find record for SID $sid");
    //     }
    //
    //     if (count($results) > 1 ) {
    //         throw new Exception ("More than one record found for SID $sid");
    //     }
    //
    //     $entity = array_pop($results);
    //     /** @var Entity $entity */
    //     $data = $entity->getData();
    //     //$this->emDebug("Entity Data: ", $data);
    //
    //     // Add callback to raw history
    //     $raw = empty($data['raw']) ? [] : json_decode(json_encode($data['raw']),true);
    //     // Get a timestamp with microseconds
    //     $i = strval(microtime(true));
    //     $raw[$i] = $_POST;
    //     ksort($raw);
    //
    //     $payload = [
    //         'error'  => $error_code,
    //         'raw'    => json_encode($raw),
    //         'status' => $status
    //     ];
    //
    //     switch ($status) {
    //         case "sent":
    //             $payload['date_sent'] = strtotime('now');
    //             break;
    //         case "delivered":
    //             $payload['date_delivered'] = strtotime('now');
    //             break;
    //         case "read":
    //             $payload['date_read'] = strtotime('now');
    //             break;
    //         default:
    //             $this->module->emError("Unhandled callback status: $status");
    //     }
    //
    //     if ($entity->setData($payload)) {
    //         $id = $entity->save();
    //         $this->module->emDebug("Save", $id);
    //     } else {
    //         $this->module->emDebug("Error setting payload", $payload);
    //     }
    //     return true;
    // }


    /**
     * HANDLER FOR INBOUND POST FROM TWILIO
     * Can either create a new reply or record a status update callback
     * @return bool|mixed
     * @throws Exception
     */
    public function processInboundMessage() {
        // REMOVE DEPRECATED TAGS: https://www.twilio.com/blog/programmable-messaging-sids
        unset($_POST['SmsMessageSid']);
        unset($_POST['SmsSid']);
        unset($_POST['SmsStatus']); // Using MessageStatus instead

        /* EXAMPLE CALLBACK
            [SmsStatus] => sent
            [Body] => This is *bold* and _underlined_...
            [MessageStatus] => sent
            [ChannelToAddress] => +1650380XXXX
            [To] => whatsapp:+16503803405
            [ChannelPrefix] => whatsapp
            [MessageSid] => SMeb941545a1eb46cab32c67df2f8bef62
            [AccountSid] => AC4c78ad3161bed65c08e36f77847f914a
            [StructuredMessage] => false
            [From] => whatsapp:+14155238886
            [ApiVersion] => 2010-04-01
            [ChannelInstallSid] => XEcc20d939f803ee381f2442185d0d5dc5
                (optional)
            [ErrorCode] => 63016,
            [EventType] => "UNDELIVERED"
         */

        /* EXAMPLE REPLY:
            [NumMedia] => 0
            [ProfileName] => Andy Martin
            [WaId] => 16503803405
            [SmsStatus] => received
            [Body] => two
            [To] => whatsapp:+16502755484
            [NumSegments] => 1
            [MessageSid] => SM6bc26f968756025fd01db0ffd941ce83
            [AccountSid] => ACe3ab1c8d7222aedd52dc8fff05d1feb9
            [From] => whatsapp:+16503803405
            [ApiVersion] => 2010-04-01
         */

        $this->message_sid = $_POST['MessageSid']       ?? null;
        $this->status      = $_POST['MessageStatus']    ?? '';
        $this->error       = $_POST['ErrorCode']        ?? '';

        if (empty($this->message_sid)) {
            $this->module->emError("Unable to parse inbound message SID:", $_POST);
            return false;
        }

        usleep(100);    // Added a slight delay to ensure original outgoing message is logged

        // See if there is an existing SID for this message
        $factory = new EntityFactory();
        $results = $factory->query('whats_app_message')
            ->condition('message_sid', $this->message_sid)
            ->orderBy('id', true)
            ->execute();

        if (empty($results)) {
            // There isn't an existing message SID - probably a newly received message
            return $this->parseNewMessage();
        } else {
            // The factory query returns an array
            // There should only be one entry for a SID - lets take the first just in case
            /** @var Entity $entity */
            $this->entity = array_shift($results);
            return $this->parseCallback();
        }
    }


    private function parseNewMessage() {
        $this->module->emDebug("Parsing inbound message with new SID " . $this->message_sid);

        $this->setToNumber($_POST['To']);
        $this->setMessage($_POST['Body']);
        $this->setFromNumber($_POST['From']);

        // To try and determine record context for the inbound message
        // We will check outbound messages for the sender's phone
        $this->module->emDebug("Trying to determine record number for " . $this->getFromNumber());
        $q = $this->module->query('select distinct record_id from redcap_entity_whats_app_message where ' .
            'record_id is not null and to_number = ?',
            [ $this->getFromNumber() ]
        );
        $records = [];
        while ($row = db_fetch_assoc($q)) $records[] = $row['record_id'];
        $record_count = count($records);
        // $this->module->emDebug("QUERY", $records, $record_count);

        // Store our search results in the raw log by adding them to the POST object
        if ($record_count == 1) {
            $record_id = current($records);
            $_POST['REDCap Warning'] = "Assuming sender is record $record_id based on unique number match";
            $this->setRecordId( $record_id );
            $this->module->emDebug("Assigned inbound message to record $record_id");
        } else {
            if ($record_count == 0) {
                $_POST['REDCap Warning'] = "Unable to identify sender - no match from previous outgoing messages";
            } elseif($record_count > 1) {
                $_POST['REDCap Warning'] = "Unable to identify sender - multiple messages match the sender phone number ("
                    . $this->getFromNumber() . ")";
            }
        }

        # Set the context for the new log entry
        // $this->setContext();
        $this->messageContext = new MessageContext();
        // $this->module->emDebug("Set context!");

        # Log message
        $this->appendRaw($_POST);
        $this->setDateReceived(strtotime('now'));
        $this->status = "received";

        $id = $this->logNewMessage();
        if ($id === false) {
            // Error occurred
            $this->module->emError("Unable to logNewMessage");
        }

        // Depending on matches, we might send some error messages...
        if ($record_count == 1) {
            \REDCap::logEvent(
                "[WhatsApp]<br>Incoming message received",
                "New message from sender at " . $this->getFromNumber() . " recorded as message #$id",
                "", $this->getRecordId(), "", $this->module->getProjectId()
            );

            // Since we received a reply from a person, we must assume we have an open 24 hour window
            // Let's see if we have any undelivered messages
            // TODO: Optionally, we could ONLY check if a response is from an ice breaker, but I dont know
            // that is really matters at this point...
            $this->checkForUndeliveredMessages();
        } else {
            if ($record_count == 0) {
                \REDCap::logEvent(
                    "[WhatsApp]<br>Unable to assign incoming message to record",
                    "The sender's number, " . $this->getFromNumber() . ", was not found.  See message #$id",
                    "", "", "", $this->module->getProjectId()
                );
            } else {
                \REDCap::logEvent(
                    "[WhatsApp]<br>Unable to assign incoming message to record",
                    "The sender's number, " . $this->getFromNumber() . ", is used by " . count($records) . " records.  See message #$id",
                    "", "", "", $this->module->getProjectId()
                );
            }
        }
        return $id;
    }


    /**
     * @return bool
     */
    private function parseCallback() {
        $this->module->emDebug("Message #" . $this->entity->getId() . " - parsing callback for " . $this->message_sid);
        $data = $this->entity->getData();

        // Do a selective update of the entity
        // Sometimes the 'sent' callback comes in AFTER the error callback
        // In this case, we don't really want to update the status to 'sent'
        if ($data['status'] == "undelivered" && $data['error'] == 63016 && $this->status == "sent") {
            $this->module->emDebug("Going to ignore a sent after undelivered update");
            $_POST['REDCap Warning'] = "Ignoring this sent callback because I think it is a timestamp collision with an undeliverable update";
        } else {
            $payload['status'] = $this->status;
            $payload['error'] = $this->error;
        }

        // Currently, this object does not have all the data variables set from the entity
        // To enable more consistent object behavior, we will transfer entity attributes to this object
        // ... add others as necessary...
        $this->setToNumber($data['to_number']);
        $this->setRecordId($data['record_id']);

        // Append the Raw and get a summary of changes
        $this->setRaw($data['raw']);
        $diff = $this->diffLastRaw($_POST);
        // $this->appendRaw($diff);
        $this->appendRaw($_POST);
        $diff_summary = [];
        foreach ($diff as $k=>$v) $diff_summary[] = "$k = '$v'";
        $payload['raw'] = $this->getRaw();

        switch ($this->status) {
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
                if ($this->error == self::ICEBREAKER_ERROR) {
                    $template_id = $this->getTemplateId();
                    $icebreaker_template_id = $this->module->getProjectSetting('icebreaker-template-id');
                    if (!empty($template_id) && $template_id == $icebreaker_template_id) {
                        // We don't trigger an icebreaker on a failed icebreaker to prevent a loop
                        $this->module->emError("Detected rejected icebreaker message #". $this->entity->getId());
                    } else {
                        $this->icebreaker_needed = true;
                    }
                }
                break;
            case "failed":
                $this->module->emDebug("Failed Delivery", $_POST);
                break;
            default:
                $this->module->emError("Unhandled callback status: $this->status", $_POST);
        }
        if (!empty($this->error)) $payload['date_error'] = strtotime('now');

        // Save the update
        if ($this->entity->setData($payload)) {
            if ($result = $this->entity->save()) {
                $this->module->emDebug("Updated #" . $this->entity->getId() . " - $this->message_sid => $this->status");
                \REDCap::logEvent(
                    "[WhatsApp]<br>Message #" . $this->entity->getId() . " Status Update",
                    implode(",\n", $diff_summary),
                    "", $data['record_id'], $data['event_id'], $data['project_id']
                );

                return true;
            } else {
                $this->module->emError("Error saving update", $payload, $_POST, $this->entity->getErrors());
            }
        } else {
            $this->module->emDebug("Error setting payload", $payload, $this->entity->getErrors());
        }

        # something went wrong
        return false;
    }

    /**
     * @return mixed
     */
    public function getIcebreakerNeeded()
    {
        if (
            // INCORRECT
            $this->error  == self::ICEBREAKER_ERROR &&
            $this->status == "undelivered"
        ) {
            return $this->icebreaker_needed;
        } else {
            return false;
        }
    }


    /**
     * Makes an array of differences from the last entry...
     * @param $newRaw
     * @return array
     */
    private function diffLastRaw($newRaw) {
        $raw = $this->getRaw();
        $last_raw_entry = end($raw);
        $diff = [];
        foreach ($newRaw as $k => $v) {
            if (!isset($last_raw_entry[$k]) || $last_raw_entry[$k] !== $v) {
                $diff[$k] = $v;
            }
        }
        // $this->module->emDebug($last_raw_entry,$newRaw,$diff);
        return $diff;
    }


    /**
     * @return array
     */
    private function getPayload(): array {
        $payload = [
            'message_sid'    => $this->message_sid,
            'message'        => $this->getMessage(),
            'template'       => $this->getTemplate(),
            'template_id'    => $this->getTemplateId(),
            'source'         => $this->getSource(),
            'source_id'      => $this->getSourceId(),
            'record_id'      => $this->getRecordId(),
            'instance'       => $this->getInstance(),
            'event_id'       => $this->getEventId(),
            'event_name'     => $this->getEventName(),
            'to_number'      => $this->getToNumber(),
            'from_number'    => $this->getFromNumber(),
            'date_sent'      => $this->date_sent,
            'date_delivered' => $this->date_delivered,
            'date_received'  => $this->date_received,
            'date_read'      => $this->date_read,
            'date_error'     => $this->date_error,
            'error'          => $this->error,
            'status'         => $this->status,
            'raw'            => $this->getRaw(),
            'project_id'     => $this->getProjectId()
        ];

        return $payload;
    }


    /**
     * @param mixed $record_id
     */
    public function setRecordId($record_id): void
    {
        $this->record_id = $record_id;
    }

    /**
     * When a recipient responds to a SMS, it may open up a window to send any queued messages that errored
     * out because they were outside of the window.
     * @param $record_id
     * @return void
     */
    private function checkForUndeliveredMessages() {
        // Step 1 - get all messages that are undelivered
        $factory = new EntityFactory();
        $q = $factory->query('whats_app_message')
            ->condition('record_id', $this->getRecordId())
            ->condition('status', "undelivered")
            ->condition('template_id', NULL)
            ->condition('error', 63016)
            ->orderBy('created')
            ->execute();

        /** @var Entity $entity */
        if (count($q) > 0) {
            $this->module->emDebug("Found " . count($q) . " undelivered messages for record " . $this->getRecordId() .
                ".  Attempting redelivery");
            $this->module->sendUndeliveredMessages($q);
        }
    }



    # format number for What's App E164 format.  Consider adding better validation here
    private static function formatNumber($number) {
        // Strip anything but numbers and add a plus
        $clean_number = preg_replace('/[^\d]/', '', $number);
        return '+' . $clean_number;
    }

    /**
     * Determine if the configuration is valid
     * @return false
     */
    public function configurationValid() {
        //TODO
        if (empty($this->account_sid))  $this->errors['sid'] = "Missing SID";
        if (empty($this->token))        $this->errors['token'] = "Missing token";
        if (empty($this->from_number))  $this->errors['from_number'] = "Missing from number";
        if (empty($this->callback_url)) $this->errors['callback'] = "Missing callback url";

        if (count($this->errors) == 0) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Because the redcap_email hook doesn't have any context (e.g. record, project, etc) it can sometimes be hard
     * to know if any context applies.  Generally you can provide details via piping in the subject, but only to a
     * certain extent.  For example, a scheduled ASI email will have a record, but no event / project context
     *
     * This module sets:
     * source, source_id, project_id, record_id, event_id, instance, and event_name
     *
     * @param $context array
     * @throws Exception
     */
    // private function setContext($context = []) {
    //     /*
    //         [file] => /var/www/html/redcap_v10.8.2/DataEntry/index.php
    //         [line] => 345
    //         [function] => saveRecord
    //         [class] => DataEntry
    //         [type] => ::
    //         [args] => Array
    //             (
    //                 [0] => 21
    //                 [1] => 1
    //                 [2] =>
    //                 [3] =>
    //                 [4] =>
    //                 [5] => 1
    //                 [6] =>
    //             )
    //
    //
    //     // From an immediate ASI
    //     scheduleParticipantInvitation($survey_id, $event_id, $record)
    //
    //         [file] => /var/www/html/redcap_v10.8.2/Classes/SurveyScheduler.php
    //         [line] => 1914
    //         [function] => scheduleParticipantInvitation
    //         [class] => SurveyScheduler
    //         [type] => ->
    //         [args] => Array
    //             (
    //                 [0] => 11
    //                 [1] => 53
    //                 [2] => 21
    //             )
    //
    //      */
    //
    //     # Get Context From Backtrace
    //     $bt = debug_backtrace(0);
    //     // $this->emDebug($bt);
    //     foreach ($bt as $t) {
    //         $function = $t['function'] ?? FALSE;
    //         $class = $t['class'] ?? FALSE;
    //         $args = preg_replace(['/\'/', '/"/', '/\s+/', '/_{6}/'], ['','','',''], $t['args']);
    //
    //         // If email is being sent from an Alert - get context from debug_backtrace using function:
    //         // sendNotification($alert_id, $project_id, $record, $event_id, $instrument, $instance=1, $data=array())
    //         if ($function == 'sendNotification' && $class == 'Alerts') {
    //             $this->source     = "Alert";
    //             $this->source_id  = $args[0];
    //             $this->project_id = $args[1];
    //             $this->record_id  = $args[2];
    //             $this->event_id   = $args[3];
    //             $this->instance   = $args[5];
    //             break;
    //         }
    //
    //         if ($function == 'scheduleParticipantInvitation' && $class == 'SurveyScheduler') {
    //             // scheduleParticipantInvitation($survey_id, $event_id, $record)
    //             $this->source     = "ASI (Immediate)";
    //             $this->source_id  = $args[0];
    //             $this->event_id   = $args[1];
    //             $this->record_id  = $args[2];
    //             break;
    //         }
    //
    //         if ($function == 'SurveyInvitationEmailer' && $class == 'Jobs') {
    //             $this->source    = "ASI (Delayed)";
    //             $this->source_id = "";
    //             // Unable to get project_id in this case
    //             break;
    //         }
    //     }
    //
    //     # Try to get project_id from EM if not already set
    //     if (empty($this->project_id)) {
    //         // $this->module->emDebug("Getting project_id context from EM");
    //         $this->project_id = $this->module->getProjectId();
    //     }
    //
    //     # OVERRIDE DEFAULT VALUES WITH SPECIFIED CONTEXT IF PRESENT
    //     if (!empty($context['source'])) $this->source = $context['source'];
    //     if (!empty($context['source_id'])) $this->source_id = $context['source_id'];
    //
    //     if (!empty($context['event_name'])) {
    //         // $this->module->emDebug("Setting event_name from context", $this->event_name, $context['event_name']);
    //         $this->event_name = $context['event_name'];
    //     }
    //
    //     if (!empty($context['event_id'])) {
    //         // $this->module->emDebug("Setting event_id from context", $this->event_id, $context['event_id']);
    //         $this->event_id = $context['event_id'];
    //     }
    //
    //     if (!empty($context['record_id'])) {
    //         // $this->module->emDebug("Setting record_id from context", $this->record_id, $context['record_id']);
    //         $this->record_id = $context['record_id'];
    //     }
    //
    //     if (!empty($context['project_id'])) {
    //         // $this->module->emDebug("Setting project_id from context", $this->project_id, $context['project_id']);
    //         $this->project_id = $context['project_id'];
    //     }
    //
    //     if (!empty($context['instance'])) {
    //         // $this->module->emDebug("Setting instance from context", $this->instance, $context['instance']);
    //         $this->instance = $context['instance'];
    //     }
    //
    //     # Set event_name from event_id and visa vera
    //     if (!empty($this->project_id)) {
    //
    //         if (!empty($this->event_id) && empty($this->event_name)) {
    //             // $this->module->emDebug("Setting event_name from event_id " . $this->event_id);
    //             $this->event_name = \REDCap::getEventNames(true, false, $this->event_id);
    //         }
    //
    //         // This method got complicated to make it work when not in project context from cron
    //         if (!empty($this->event_name) && empty($this->event_id)) {
    //             global $Proj;
    //             $thisProj = (!empty($Proj->project_id) && $this->project_id == $Proj->project_id) ? $Proj : new \Project($this->project_id);
    //             // $this->module->emDebug("Setting event_id from event_name " . $this->event_name);
    //             //$this->event_id = \REDCap::getEventIdFromUniqueEvent($this->event_name);
    //             $this->event_id = $thisProj->getEventIdUsingUniqueEventName($this->event_name);
    //         }
    //     }
    // }


    /** GETTERS AND SETTERS */

    /**
     * @param string $template
     */
    public function setTemplate($template): void
    {
        $this->template = $template;
    }

    /**
     * @param string $template_id
     */
    public function setTemplateId($template_id): void
    {
        $this->template_id = $template_id;
    }

    /**
     * @return mixed
     */
    public function getVariables()
    {
        return $this->variables;
    }

    /**
     * @param mixed $variables
     */
    public function setVariables($variables): void
    {
        $this->variables = $variables;
    }

    /**
     * @param mixed $message
     */
    public function setMessage($message): void
    {
        $this->message = $message;
    }

    /**
     * @return array
     */
    public function getRaw()
    {
        if (empty($this->raw)) {
            return [];
        } else {
            // Because we sometimes set raw without the setter, I need to clean
            // it here
            if (is_object($this->raw)) {
                $this->raw = json_decode(json_encode($this->raw), true);
            }
            ksort($this->raw, SORT_NUMERIC);
            return $this->raw;
        }
    }

    /**
     * @param mixed $raw
     */
    public function setRaw($raw): void
    {
        // Convert the object raw from entity into an array
        if (is_object($raw)) {
            $this->raw = json_decode(json_encode($raw),true);
        } elseif (empty($raw)) {
            $this->raw = [];
        } else {
            $this->raw = $raw;
        }
    }


    public function setAccountSid($sid) {
        $this->account_sid = $sid;
    }

    public function setToken($token) {
        $this->token = $token;
    }

    public function setFromNumber($number) {
        $this->from_number = self::formatNumber($number);
    }

    public function setToNumber($number) {
        $this->to_number = self::formatNumber($number);
    }

    public function setCallbackUrl($url) {
        $this->callback_url = $url;
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return mixed
     */
    public function getToNumber() {
        return $this->to_number;
    }

    /**
     * @return mixed
     */
    public function getFromNumber() {
        return $this->from_number;
    }

    /**
     * @return mixed
     */
    public function getMessage() {
        return $this->message;
    }

    /**
     * @return mixed
     */
    public function getProjectId()
    {
        return $this->project_id;
    }

    /**
     * @return string
     */
    public function getRecordId()
    {
        return (string) $this->record_id;
    }

    /**
     * @return mixed
     */
    public function getEventName()
    {
        return $this->event_name;
    }

    /**
     * @return mixed
     */
    public function getEventId()
    {
        return $this->event_id;
    }

    /**
     * @return mixed
     */
    public function getInstance()
    {
        return $this->instance;
    }

    /**
     * @return mixed
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * @return mixed
     */
    public function getTemplateId()
    {
        return $this->template_id;
    }

    /**
     * @return mixed
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * @return mixed
     */
    public function getSourceId()
    {
        return $this->source_id;
    }

    /**
     * @return mixed
     */
    public function getLogField(): string
    {
        return $this->log_field;
    }

    /**
     * @return mixed
     */
    public function getLogEventId(): string
    {
        return $this->log_event_id;
    }

    /**
     * @param mixed $date_sent
     */
    public function setDateSent($date_sent): void
    {
        $this->date_sent = $date_sent;
    }

    /**
     * @return mixed
     */
    public function getCreated()
    {
        return $this->entity->getCreationTimestamp();
    }

    /**
     * @param mixed $created
     */
    public function setCreated($created): void
    {
        $this->created = $created;
    }

    /**
     * @return mixed
     */
    public function getMessageSid()
    {
        return $this->message_sid;
    }

    /**
     * @param mixed $date_received
     */
    public function setDateReceived($date_received): void
    {
        $this->date_received = $date_received;
    }


}

