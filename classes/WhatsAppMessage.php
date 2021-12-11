<?php
namespace Stanford\WhatsAppAlerts;

use \REDCapEntity\EntityFactory;
use \REDCapEntity\Entity;
use \Exception;

/**
 * Class WhatsAppMessage
 * @property WhatsAppAlerts $module
 * @property \Project $Proj
 */
class WhatsAppMessage
{
    private $module;

    // VARIABLES FOR MESSAGE BODY TO CONTEXT
    protected $config;      // message configuration parsed from email body
    private $valid_config_keys = [
        "type", "template_id", "template", "language",
        "variables", "body", "number", "log_field", "log_event_name", "context"
    ];
    private $template_id;
    private $template;
    private $language;
    private $variables;
    private $body;
    private $number;
    private $log_field;
    private $log_event_name;
    private $context;


    private $sid;
    private $token;
    private $from_number;
    private $callback_url;

    // CONTEXT PARAMETERS
    private $Proj;
    private $source;
    private $source_id;
    private $project_id;
    private $record_id;
    private $event_name;
    private $event_id;
    private $instance;

    private $message;       // Finished message formed from variables

    private $errors = [];



    public function __construct($module, $config = null) {
        // Link the parent EM so we can use helper methods
        $this->module    = $module;

        // Optionally parse the config if presented during the constructor
        if (!empty($config)) return $this->parseEmail($config);
    }

    /**
     * Parse email message message format for What's App config
     * @param $message
     * @return bool     false if not a what's app message
     */
    public function parseEmail($message)
    {
        /*
             {
                "type": "whatsapp",
                "template_id": "ACe3ab1c8d7222aedd52dc8fff05d1feb9_en",
                "template": "messages_awaiting",
                "language": "en",
                "variables": [ "[event_1_arm_1][baseline]", "Hello" ],
                "body": "blank if free text, otherwise will be calculated based on template and variables",
                "number": "[event_1_arm_1][whats_app_number]",
                "log_field": "field_name",
                "log_event_name": "log_event_name",
                "context": {
                    "project_id": "[project-id]",
                    "event_name": "[event-name]",
                    "record_id": "[record-id]",
                    "instance": "[current-instance]"
                }
            }
        */

        // Try to parse a WhatsApp message from the Email body
        $config = self::parseHtmlishJson($message);

        // Stop processing - this is not a valid what's app message
        if ($config === FALSE || !isset($config['type']) || $config['type'] != "whatsapp") {
            return false;
        }

        // Transfer message config params to this object
        $this->config = $config;
        foreach ($this->valid_config_keys as $k) {
            if (!empty($config[$k]) && property_exists($this,$k)) $this->$k = $config[$k];
        }

        // Check for context by merging backtrace with message config
        $this->setContext($config['context']);

        // Format the TO number correctly
        $this->number = self::formatNumber($config['number']);

        // Determine if message uses a template or is free text and set the message body
        if (!empty($config['template_id'])) {
            // We are using a template -- lets load the template
            $t = new Template($this->module, $config['template_id']);
            $variables = $config['variables'];
            $this->message = $t->buildMessage($variables);
        } else {
            // This is a free-text message
            $this->message = strip_tags($config['body'], "");
        }


        // Transfer config params to object
        if (isset($this->config['template_id'])) $this->template_id = $this->config['template_id'];
        // if (isset($this->config['template']))       $this->template = $this->config['template'];
        if (isset($this->config['language'])) $this->language = $this->config['language'];
        if (isset($this->config['variables'])) $this->variables = $this->config['variables'];
        if (isset($this->config['body'])) $this->body = $this->config['body'];
        if (isset($this->config['number'])) $this->number = $this->config['number'];
        if (isset($this->config['context'])) $this->context = $this->config['context'];
        if (isset($this->config['log_field'])) $this->log_field = $this->config['log_field'];
        // TODO - handle log_event_name instead
        if (isset($this->config['log_event_id'])) $this->log_event_id = $this->config['log_event_id'];


        // // Make sure the email body template_id is valid
        // $this->validateTemplate();

        // // Parse the raw message and substitute values
        // $this->setMessage();

        return true;
    }


    /**
     * @throws \Twilio\Exceptions\ConfigurationException
     * @throws \Twilio\Exceptions\TwilioException
     */
    public function sendMessage() {
        # Send message
        $client = new \Twilio\Rest\Client($this->sid, $this->token);

        $this->module->emDebug("About to send message to " . $this->getNumber());

        $trans = $client->messages->create(
            "whatsapp:" . $this->getNumber(), [
            "from" => "whatsapp:" . $this->from_number,
            "body" => $this->getMessage(),
            "statusCallback" => $this->callback_url
            ]);

        $status = $trans->status . (empty($trans->errors) ? "" : " - " . $trans->errors);
        $this->module->emDebug("Transmission complete: $status with sid $trans->sid");

        $this->logMessage($trans->sid, $status);
    }

    /**
     * Log a new message
     * @param $sid
     * @param $status
     * @return mixed
     */
    private function logMessage($sid, $status) {
        $factory = new EntityFactory();

        $i = strval(microtime(true));
        $raw[$i] = $this->config;

        $payload = [
            'sid'            => $sid,
            'message'        => $this->getMessage(),
            'source'         => $this->getSource(),
            'source_id'      => $this->getSourceId(),
            'record'         => $this->getRecordId(),
            'instance'       => $this->getInstance(),
            'event_id'       => $this->getEventId(),
            'event_name'     => $this->getEventName(),
            'to_number'      => $this->getNumber(),
            'from_number'    => $this->from_number,
            'date_sent'      => strtotime('now'),
            'project_id'     => $this->getProjectId(),
            // 'log_field'      => $this->getLogField(),
            // 'log_event_id'   => $this->getLogEventId(),
            'status'         => $status,
            // 'raw'            => $raw
        ];

        $entity = $factory->create('whats_app_message', $payload);
        $id = $entity->getId();
        $this->module->emDebug("Logged Message #$id - $sid");

        return $id;
    }


    /**
     * Twilio calls a callback URL.  This function takes the results of that callback and uses them to update entity
     * @return bool
     * @throws Exception
     */
    public function updateLogStatusCallback() {
        $this->module->emDebug("Callback", json_encode($_POST));
        /*
            [SmsSid] => SMeb941545a1eb46cab32c67df2f8bef62
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

        $sid    = $_POST['SmsSid'] ?? null;
        $status = $_POST['SmsStatus'] ?? '';
        $error_code = $_POST['ErrorCode'] ?? '';
        $update = $status . (empty($error_code) ? '' : " Error#" . $error_code);

        if (empty($sid) || empty($update)) {
            $this->module->emError("Unable to parse statusCallback:", $_POST);
            return false;
        }

        // Get the em log entry for this sid
        $factory = new EntityFactory();
        $results = $factory->query('whats_app_message')
            ->condition('sid', $sid)
            ->execute();

        if (empty($results)) {
            throw new Exception ("Unable to find record for SID $sid");
        }

        if (count($results) > 1 ) {
            throw new Exception ("More than one record found for SID $sid");
        }

        $entity = array_pop($results);
        /** @var Entity $entity */
        $data = $entity->getData();
        //$this->emDebug("Entity Data: ", $data);

        // Add callback to raw history
        $raw = empty($data['raw']) ? [] : json_decode(json_encode($data['raw']),true);
        // Get a timestamp with microseconds
        $i = strval(microtime(true));
        $raw[$i] = $_POST;

        $payload = [
            'error'  => $error_code,
            'raw'    => json_encode($raw),
            'status' => $status
        ];

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
            default:
                $this->module->emError("Unhandled callback status: $status");
        }

        if ($entity->setData($payload)) {
            $this->module->emDebug("Saving!", $payload, $entity->save());
        } else {
            $this->module->emDebug("Error setting payload", $payload);
        }
        return true;
    }




    /** UTILITY FUNCTIONS */

    private static function parseHtmlishJson($input) {
        $string = self::replaceNbsp($input);
        $junk = [
            "<p>",
            "<br />",
            "</p>"
        ];
        // Remove HTML tags inserted by UI
        $string = str_replace($junk, '', $string);

        // See if it is valid json
        list($success, $result) = self::jsonToObject($string);

        if ($success) {
            return $result;
        } else {
            return false;
        }
    }

    private static function jsonToObject($string, $assoc = true)
    {
        // decode the JSON data
        $result = json_decode($string, $assoc);

        // switch and check possible JSON errors
        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                $error = ''; // JSON is valid // No error has occurred
                break;
            case JSON_ERROR_DEPTH:
                $error = 'The maximum stack depth has been exceeded.';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $error = 'Invalid or malformed JSON.';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $error = 'Control character error, possibly incorrectly encoded.';
                break;
            case JSON_ERROR_SYNTAX:
                $error = 'Syntax error, malformed JSON.';
                break;
            // PHP >= 5.3.3
            case JSON_ERROR_UTF8:
                $error = 'Malformed UTF-8 characters, possibly incorrectly encoded.';
                break;
            // PHP >= 5.5.0
            case JSON_ERROR_RECURSION:
                $error = 'One or more recursive references in the value to be encoded.';
                break;
            // PHP >= 5.5.0
            case JSON_ERROR_INF_OR_NAN:
                $error = 'One or more NAN or INF values in the value to be encoded.';
                break;
            case JSON_ERROR_UNSUPPORTED_TYPE:
                $error = 'A value of a type that cannot be encoded was given.';
                break;
            default:
                $error = 'Unknown JSON error occured.';
                break;
        }

        if ($error !== '') {
            return array(false, $error);
        } else {
            return array(true, $result);
        }
    }

    private static function replaceNbsp ($string, $replacement = '') {
        $entities = htmlentities($string, null, 'UTF-8');
        $clean = str_replace("&nbsp; ", $replacement, $entities);
        $result = html_entity_decode($clean);

        return $result;
    }

    # format number for What's App E164 format.  Consider adding better validation here
    private static function formatNumber($number) {
        // Strip anything but numbers and add a plus
        $number = preg_replace('/[^\d]/', '', $number);
        return '+' . $number;
    }



    // /**
    //  * Make sure the template is okay
    //  */
    // private function validateTemplate() {
    //     // Check if the template has been updated and is approved
    //     if (!empty($this->template_id)) {
    //         $all_templates = $this->module->getTemplates();
    //         if (!isset($all_templates[$this->template_id])) {
    //             $this->errors[] = "Template $this->template_id does not appear to be valid in this project";
    //         }
    //
    //         // Load template
    //         $this->template = $all_templates[$this->template_id];
    //         if ($this->template['status'] != 'approved') {
    //             $this->errors[] = "Template has not been approved, status is: " . $this->template['status'];
    //         }
    //
    //         // body contains nbsps...
    //         // if ($this->template['content'] != $this->body) {
    //         //     $this->errors[] = "Message body is different than template content -- will use template content!";
    //         //     $this->module->emError("Message body differs from template content", $this->body, $this->template);
    //         // }
    //     } else {
    //         // Doing a free-text / no template message
    //     }
    // }

    /**
     * Determine if the configuration is valid
     * @return false
     */
    public function configurationValid() {
        //TODO
        if (empty($this->sid)) $this->errors['sid'] = "Missing SID";
        if (empty($this->token)) $this->errors['token'] = "Missing token";
        if (empty($this->from_number)) $this->errors['from_number'] = "Missing from number";
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
     * Replies on the following being preset:
     *
     * @param $context array
     */
    private function setContext($context = []) {
        /*
            [file] => /var/www/html/redcap_v10.8.2/DataEntry/index.php
            [line] => 345
            [function] => saveRecord
            [class] => DataEntry
            [type] => ::
            [args] => Array
                (
                    [0] => 21
                    [1] => 1
                    [2] =>
                    [3] =>
                    [4] =>
                    [5] => 1
                    [6] =>
                )


        // From an immediate ASI
        scheduleParticipantInvitation($survey_id, $event_id, $record)

            [file] => /var/www/html/redcap_v10.8.2/Classes/SurveyScheduler.php
            [line] => 1914
            [function] => scheduleParticipantInvitation
            [class] => SurveyScheduler
            [type] => ->
            [args] => Array
                (
                    [0] => 11
                    [1] => 53
                    [2] => 21
                )

         */

        # Get Context From Backtrace
        $bt = debug_backtrace(0);
        // $this->emDebug($bt);
        foreach ($bt as $t) {
            $function = $t['function'] ?? FALSE;
            $class = $t['class'] ?? FALSE;
            $args = preg_replace(['/\'/', '/"/', '/\s+/', '/_{6}/'], ['','','',''], $t['args']);

            // If email is being sent from an Alert - get context from debug_backtrace using function:
            // sendNotification($alert_id, $project_id, $record, $event_id, $instrument, $instance=1, $data=array())
            if ($function == 'sendNotification' && $class == 'Alerts') {
                $this->source    = "Alert";
                $this->source_id = $args[0];
                $this->project_id = $args[1];
                $this->record_id  = $args[2];
                $this->event_id   = $args[3];
                $this->instance   = $args[5];
                break;
            }

            if ($function == 'scheduleParticipantInvitation' && $class == 'SurveyScheduler') {
                // scheduleParticipantInvitation($survey_id, $event_id, $record)
                $this->source    = "ASI (Immediate)";
                $this->source_id = $args[0];
                $this->event_id   = $args[1];
                $this->record_id  = $args[2];
                break;
            }

            if ($function == 'SurveyInvitationEmailer' && $class == 'Jobs') {
                $this->source    = "ASI (Delayed)";
                $this->source_id = "";
                // Unable to get project_id in this case
                break;
            }
        }

        # Try to get project_id from EM if not already set
        if (empty($this->project_id)) {
            $this->module->emDebug("Getting project_id context from EM");
            $this->project_id = $this->module->getProjectId();
            // if (empty($project_id) && !empty($_GET['pid'])) $project_id = $_GET['pid'];
        }

        # OVERRIDE DEFAULT VALUES WITH SPECIFIED CONTEXT IF PRESENT
        if (!empty($context['event_name'])) {
            $this->module->emDebug("Setting event_name from context", $this->event_name, $context['event_name']);
            $this->event_name = $context['event_name'];
        }

        if (!empty($context['event_id'])) {
            $this->module->emDebug("Setting event_id from context", $this->event_id, $context['event_id']);
            $this->event_id = $context['event_id'];
        }

        if (!empty($context['record_id'])) {
            $this->module->emDebug("Setting record_id from context", $this->record_id, $context['record_id']);
            $this->record_id = $context['record_id'];
        }

        if (!empty($context['project_id'])) {
            $this->module->emDebug("Setting project_id from context", $this->project_id, $context['project_id']);
            $this->project_id = $context['project_id'];
        }

        if (!empty($context['instance'])) {
            $this->module->emDebug("Setting instance from context", $this->instance, $context['instance']);
            $this->instance = $context['instance'];
        }

        # Set event_name from event_id and visa vera
        if (!empty($this->project_id)) {

            if (!empty($this->event_id) && empty($this->event_name)) {
                $this->module->emDebug("Setting event_name from event_id " . $this->event_id);
                $this->event_name = \REDCap::getEventNames(true, false, $this->event_id);
            }

            // This method got complicated to make it work when not in project context from cron
            if (!empty($this->event_name) && empty($this->event_id)) {
                global $Proj;
                $this->Proj = (!empty($Proj->project_id) && $this->project_id == $Proj->project_id) ? $Proj : new \Project($this->project_id);
                $this->module->emDebug("Setting event_id from event_name " . $this->event_name);
                //$this->event_id = \REDCap::getEventIdFromUniqueEvent($this->event_name);
                $this->event_id = $this->Proj->getEventIdUsingUniqueEventName($this->event_name);
            }
        }
    }



    /**
     * Build the message from the template or as free-text
     */
    public function setMessage() {
        if (empty($this->template_id)) {
            // If the template id is empty, this means free-text message
            if (empty($this->config['body'])) {
                $this->module->emError("Missing message BODY for freetext entry");
                $this->errors[] = "Missing BODY for freetext message";
                return false;
            } else {
                $this->message = strip_tags($this->body, "");
            }
        } else {
            // Message comes from template
            if (empty($this->template)) {
                $this->module->emError("Missing template");
                $this->errors[] = "Missing template for email with template id of " . $this->template_id;
            }
            $content = $this->template['content'];
            $contentVariables = $this->parseContentVariables($content);

            if (count($contentVariables) != count($this->variables)) {
                $this->module->emError("Variable difference:", $contentVariables, $this->variables);
                $this->errors[] = "Template variable count differs!  Template content specifies " . count($contentVariables) . " while message contains " . count($this->variables);
            }

            // Substitute variables in content
            $message = $content;
            foreach ($contentVariables as $match) {
                $index = $match['index'];
                $token = $match['token'];
                $value = $this->variables[$index-1];
                $this->module->emDebug("Replacing $token with $value");
                $message = str_replace($token, $value, $message);
            }
            $this->module->emDebug("Substitution Complete", $message);
            $this->message = $message;
        }
    }

    /**
     * Use to pull out {{x}} substitutions from templates
     * @param $content
     * @return mixed
     */
    private function parseContentVariables($content) {
        // https://regex101.com/r/VvU0i3/1
        $re = '/(?\'token\'\{{2}(?\'index\'\d+)\}{2})/m';
        //$str = 'Dear {{1}}, you have one or more messages waiting for you regarding the {{2}}{{32}} study.  Please press the button or respond with a \'y\' to receive them.';
        preg_match_all($re, $content, $matches, PREG_SET_ORDER, 0);
        return $matches;
    }



    /** GETTERS AND SETTERS */

    public function setSid($sid) {
        $this->sid = $sid;
    }

    public function setToken($token) {
        $this->token = $token;
    }

    public function setFromNumber($number) {
        $this->from_number = $number;
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
    public function getNumber() {
        return $this->number;
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
     * @return mixed
     */
    public function getRecordId()
    {
        return $this->record_id;
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




}

