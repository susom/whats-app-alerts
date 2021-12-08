<?php
namespace Stanford\WhatsAppAlerts;

require_once "emLoggerTrait.php";

require_once "vendor/autoload.php";

use mysql_xdevapi\Exception;
use \Project;
use \REDCap;

require_once("classes/Template.php");
require_once("classes/WhatsAppMessage.php");
require_once("classes/MessageLogger.php");


class WhatsAppAlerts extends \ExternalModules\AbstractExternalModule {

    use emLoggerTrait;

    public function __construct() {
		parent::__construct();
		// Other code to run when object is instantiated
	}

    public function redcap_every_page_top($project_id=null){
        if (PAGE == 'AlertsController:setup')
        {
            $this->override_alerts();
        }
    }

    public function override_alerts() {
        $jsFilePath = $this->getUrl('js/override.js'); //Override js file
        print "<script type='module' src=$jsFilePath></script>"; //inject both js and css style
        print "<style> .inv {position: absolute !important; top: -9999px !important; left: -9999px !important; } </style>";
    }

    public function old_redcap_email( $to, $from, $subject, $message, $cc, $bcc, $fromName, $attachments ) {

        # Check Subject for '@WHATSAPP' action tag
        # Function takes (phone_number, project_id (context), record_id = context if available, event_name = '', instance_id = 1, log_field, log_event_name = context if available)
        preg_match('/@WHATSAPP\((.*)\)/', $subject, $matches);

        # Abort this hook and send the normal email if there is no match for WHATS APP
        if (empty($matches[1])) return true;

        # Obtain context to fill in gaps
        $context = $this->getContext();
        if (empty($context)) {
            $this->emError("Unable to parse context for this email", debug_backtrace(0));
            return false;
        }
        $this->emDebug("EMAIL CONTEXT: " . json_encode($context));

        // Remove apostrophes and spaces from the function arguments
        $args = preg_replace(['/\'/', '/"/', '/\s+/', '/_{6}/'], ['','','',''], $matches[1]);
        $parts = explode(",",$args);
        $this->emDebug("PARTS: " . json_encode($parts));

        # Required Parameters
        $number       = $parts[0];
        $project_id   = empty($parts[1])   ? ($context['project_id'] ?? '' ) : $parts[1];
        $this->emDebug("project_id", $_GET['pid'], $project_id, PROJECT_ID);

        # Validate and Define Project ID
        if (!empty($project_id)) {
            if (empty($_GET['pid'])) $_GET['pid']=$project_id;
            if (!defined("PROJECT_ID")) define('PROJECT_ID', $project_id);
        }
        if (empty($_GET['pid'])) {
            $this->emDebug("Missing required Project Context", $parts, $context);
            return false;
        }
        global $Proj;
        $thisProj = ($Proj->project_id ?? NULL == $project_id) ? $Proj : new Project($project_id);


        $record_id    = empty($parts[2])   ? ($context['record_id'] ?? NULL) : $parts[2];
        $event_name   = empty($parts[3])   ? ($context['event_name'] ?? '')  : $parts[3];
        $event_id     = empty($event_name) ? ($context['event_id'] ?? NULL)  : $thisProj->getEventIdUsingUniqueEventName($event_name);
        $instance     = empty($parts[4])   ? ($context['instance'] ?? 1)     : $parts[4];
        $log_field    = $parts[5] ?? '';
        $log_event_id = empty($parts[6])   ? ($event_id ?? '')               : $thisProj->getEventIdUsingUniqueEventName($parts[6]);

        # Validate Number
        if (empty($number)) {
            $this->emDebug("Number missing: ", $parts, $context);
            return false;
        }

        # Load settings
        $sid = $this->getProjectSetting('sid');
        $token = $this->getProjectSetting('token');
        $fromNumber = $this->getProjectSetting('from-number');
        if (empty($sid) || empty($token) || empty($fromNumber) || empty($project_id)) {
            REDCap::logEvent(
                "What's App Configuration Error",
                "Missing required sid, token, or from number in What's App Alerts configuration",
                "",$record_id,$event_id,$project_id
            );
            $this->emDebug("Missing sid or token or number");
            return false;
        }

        # Callback URL for delivery updates
        $callbackUrl = $this->getUrl('statusCallback.php', true, true);
        $callbackUrl = str_replace("redcap.local","b2092c2da177.ngrok.io",$callbackUrl);
        $this->emDebug($callbackUrl);

        # strip tags from message
        $body = strip_tags($message, "");

        # format number for What's App E164 format.  Consider adding better validation here
        $toNumber = preg_replace( '/[^\d]/', '', $number);
        $toNumber = '+' . $toNumber;
        $this->emDebug("Record: $record_id, Original: $number, To: $toNumber, From: $fromNumber, Body: $body");

        # Send message
        $client = new \Twilio\Rest\Client($sid, $token);
        $trans = $client->messages->create(
            "whatsapp:" . $toNumber, [
            "from" => "whatsapp:" . $fromNumber,
            "body" => $body,
            "statusCallback" => $callbackUrl
        ]);

        # Log the message to the external message logs by SID so we can find it
        $status = $trans->status . (empty($trans->errors) ? "" : " - " . $trans->errors);
        $payload = [
            'record'         =>$record_id,
            'number'         =>$toNumber,
            'project_id'     =>$project_id,
            'event_id'       =>$event_id,
            'event_name'     =>$event_name,
            'instance'       =>$instance,
            'trigger'        =>$context['trigger']    ?? '',
            'trigger_id'     =>$context['trigger_id'] ?? '',
            'log_field'      =>$log_field,
            'log_event_id'   =>$log_event_id,
            'sid'            =>$trans->sid,
            'status'         =>$status,
        ];
        $r = $this->log($body, $payload);
        $this->emDebug("Just logged payload to log_id $r with $status");

        # See if logging message status has been requested
        if (!empty($log_field) && !empty($log_event_id)) {
            // Default an empty log_event_name to the current event of the alert
            $this->updateAlertLogField($project_id,$record_id,$log_field,$log_event_id,$status);
        }


        // Debug issues...
        if ($trans->status !== "queued") {
            REDCap::logEvent("Error sending What's App message", $status,"",$record_id, null, PROJECT_ID);
            $msg = "Error with transmission: " . $status;
            $this->emDebug($msg);
        }

        // Prevent actual email
        return FALSE;
    }


    public function json_validate($string, $assoc = true)
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

    public function parseHtmlishJson($input) {

        $this->emDebug("STRING1: " . $input);
        $string = $this->replaceNbsp($input);

        $this->emDebug("STRING2: " . $string);

        $junk = [
            "<p>",
            "<br />",
            "</p>"
        ];

        // Remove HTML tags inserted by UI
        $string = str_replace($junk, '', $string);

        $this->emDebug("STRING3: " . $string);

        // See if it is valid json
        list($success, $result) = $this->json_validate($string);

        $this->emDebug($success,$result);

        if ($success) {
            return $result;
        } else {
            $this->emDebug($result, $input, $string);
            return false;
        }
    }

	public function redcap_email( $to, $from, $subject, $message, $cc, $bcc, $fromName, $attachments ) {

        # Determine if this email is a What's App encoded message
        $json = $this->parseHtmlishJson($message);

        # Skip hook for non-what's app messages
        if ($json === FALSE || !isset($json['type']) || $json['type'] != "whatsapp") {
            $this->emDebug("Skipping Email", $message);
            return true;
        }

        # Check to parse json from email body
        /*
             {
                "type": "whatsapp",
                "template_id": "ACe3ab1c8d7222aedd52dc8fff05d1feb9_en",
                "template": "messages_awaiting",
                "language": "en",
                "variables": [ "[event_1_arm_1][baseline]", "Hello" ],
                "body": "blank if free text, otherwise will be calculated based on template and variables",
                "phone": "[event_1_arm_1][whats_app_number]",
                "context": {
                    "project_id": "[project-id]",
                    "event_name": "[event-name]",
                    "record_id": "[record-id]",
                    "instance": "[current-instance]"
                }
            }
        */


        // $this->emDebug("EMAIL CONTEXT: " . json_encode($context));
        // Remove apostrophes and spaces from the function arguments
        // $args = preg_replace(['/\'/', '/"/', '/\s+/', '/_{6}/'], ['','','',''], $matches[1]);
        // $parts = explode(",",$args);
        // $this->emDebug("PARTS: " . json_encode($parts));
        # Required Parameters
        // $number     = $m->getNumber();
        // # Validate and Define Project ID
        // if (!empty($project_id)) {
        //     if (empty($_GET['pid'])) $_GET['pid']=$project_id;
        //     if (!defined("PROJECT_ID")) define('PROJECT_ID', $project_id);
        // }
        // if (empty($_GET['pid'])) {
        //     $this->emDebug("Missing required Project Context", $parts, $context);
        //     return false;
        // }
        //
        // global $Proj;
        // $thisProj = ($Proj->project_id ?? NULL == $project_id) ? $Proj : new Project($project_id);
        //
        //
        // $record_id    = empty($parts[2])   ? ($context['record_id'] ?? NULL) : $parts[2];
        // $event_name   = empty($parts[3])   ? ($context['event_name'] ?? '')  : $parts[3];
        // $event_id     = empty($event_name) ? ($context['event_id'] ?? NULL)  : $thisProj->getEventIdUsingUniqueEventName($event_name);
        // $instance     = empty($parts[4])   ? ($context['instance'] ?? 1)     : $parts[4];
        // $log_field    = $parts[5] ?? '';
        // $log_event_id = empty($parts[6])   ? ($event_id ?? '')               : $thisProj->getEventIdUsingUniqueEventName($parts[6]);
        //
        // # Validate Number
        // if (empty($number)) {
        //     $this->emDebug("Number missing: ", $parts, $context);
        //     return false;
        // }


        # Create WhatsAppMessage Object
        $m = new WhatsAppMessage($this, $json);
        if (! $m->configurationValid()) return false;

        # Load settings
        $sid = $this->getProjectSetting('sid');
        $token = $this->getProjectSetting('token');
        $fromNumber = $this->getProjectSetting('from-number');
        $callbackUrl = $this->getCallbackUrl();
        if (empty($sid) || empty($token) || empty($fromNumber) || empty($m->getProjectId())) {
            REDCap::logEvent(
                "What's App Configuration Error",
                "Missing required sid, token, or from number in What's App Alerts configuration",
                "",$m->getRecordId(),$m->getEventId(),$m->getProjectId()
            );
            $this->emDebug("Missing sid or token or number");
            return false;
        }

        # Send message
        $client = new \Twilio\Rest\Client($sid, $token);
        $this->emDebug("About to send message to " . $m->getNumber());
        try {
            $trans = $client->messages->create(
                "whatsapp:" . $m->getNumber(), [
                "from" => "whatsapp:" . $fromNumber,
                "body" => $m->getMessage(),
                "statusCallback" => $callbackUrl
            ]);
        } catch (\Exception $e) {
            $this->emError("Caught exception: " . $e->getMessage());
        }

        # Log the message to the external message logs by SID so we can find it
        $status = $trans->status . (empty($trans->errors) ? "" : " - " . $trans->errors);
        $this->logTransmission($trans->sid, $status, $m);

        # See if logging message status has been requested
        $log_field = $m->getLogField();
        $log_event_id = $m->getLogEventId();
        if (!empty($log_field) && !empty($log_event_id)) {
            // Default an empty log_event_name to the current event of the alert
            $this->updateAlertLogField($m->getProjectId(),$m->getRecordId(),$log_field,$log_event_id,$status);
        }

        // Debug issues...
        if ($trans->status !== "queued") {
            REDCap::logEvent("Error sending What's App message", $status,"",$m->getRecordId(), null, $m->getProjectId());
            $msg = "Error with transmission: " . $status;
            $this->emDebug($msg);
        }

        // Prevent actual email
        return FALSE;
	}

    /**
     * Log initial transmission
     * @param $sid
     * @param $status
     * @param WhatsAppMessage $m
     */
    public function logTransmission($sid, $status, WhatsAppMessage $m) {
        $payload = [
            'record'         => $m->getRecordId(),
            'number'         => $m->getNumber(),
            'project_id'     => $m->getProjectId(),
            'event_id'       => $m->getEventId(),
            'event_name'     => $m->getEventName(),
            'instance'       => $m->getInstance(),
            'trigger'        => $m->getTrigger(),
            'trigger_id'     => $m->getTriggerId(),
            'log_field'      => $m->getLogField(),
            'log_event_id'   => $m->getLogEventId(),
            'sid'            => $sid,
            'status'         => $status,
        ];
        $r = $this->log($m->getMessage(), $payload);
        $this->emDebug("Just logged payload to log_id $r with $status");
    }





    /**
     * Twilio calls a callback URL.  This function takes the results of that callback and uses them to update
     * both the AlertLogField and the external_module_log_settings entries
     * @param $sid
     * @param $status
     * @throws \Exception
     */
	public function updateLogStatusCallback() {
        $this->emDebug("Callback", json_encode($_POST));
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
        $error  = empty($_POST['ErrorCode']) ? '' : " Error#" . $_POST['ErrorCode'];
        // $event = empty($_POST['EventType']) ? '' : " Event:" . $_POST['EventType'];
        $update = $status.$error;

        if (empty($sid) || empty($update)) {
            $this->emDebug("Unable to parse statusCallback:", $_POST);
            return false;
        }

        # Get the em log entry for this sid
        $q = $this->queryLogs("select log_id, sid, status, project_id, record, log_field, log_event_id where sid = ?", [$sid]);
        if ($row = $q->fetch_assoc()) {
            $log_id = $row['log_id'];
            $project_id = $row['project_id'];
            $record_id = $row['record'];
            $log_field = $row['log_field'];
            $log_event_id = $row['log_event_id'];
            $old_status = $row['status'];
            $this->updateAlertLogField($project_id, $record_id, $log_field, $log_event_id, $status);

            # Update the external module log table as well
            $query = $this->createQuery();
            $query->add('replace into redcap_external_modules_log_parameters (log_id, name, value) values (?, ?, ?)',
                [
                    $log_id,
                    'status_update',
                    $status
                ]
            );
            $query->execute();
            $this->emDebug("Updating $log_id status from $old_status to $status");

            $query = $this->createQuery();
            $query->add('replace into redcap_external_modules_log_parameters (log_id, name, value) values (?, ?, ?)',
                [
                    $log_id,
                    'status_modified',
                    date('Y-m-d H:i:s')
                ]
            );
            $query->execute();
        }
    }

    /**
     * The EM supports writing the current message status to a field in the project defined with an field_name and
     * event_name.  This is not always possible depending on the context and inputs provided to the @WHATSAPP tag
     * @param $project_id
     * @param $record_id
     * @param $log_field
     * @param $log_event_id
     * @param $status
     * @return bool
     * @throws \Exception
     */
	public function updateAlertLogField($project_id, $record_id, $log_field, $log_event_id, $status) {

	    # Verify required inputs
	    if (empty($project_id) || empty($record_id) || empty($log_field) || empty($log_event_id)) {
	        // Missing required fields - abort
            $this->emDebug("Unable to log to alert field: " . func_get_args());
            return false;
        }

        # Verify valid inputs
        global $Proj;
        $logProj = ($Proj->project_id ?? NULL == $project_id) ? $Proj : new Project($project_id);
        $error = "";
        $field_metadata = $logProj->metadata[$log_field] ?? null;
        $form_name = $field_metadata['form_name'] ?? null;
        if (empty($field_metadata)) {
            $error = "log_field $log_field is not present";
        } elseif ($field_metadata['element_type'] !== "text") {
            $error = "log field $log_field must be of type text";
        } elseif (empty($logProj->eventsForms[$log_event_id])) {
            $error = "log_event_id $log_event_id is not valid in project $project_id";
        } elseif (!in_array($form_name, $logProj->eventsForms[$log_event_id])) {
            $error = "form $form_name is not enabled in event $log_event_id";
        }
        if (!empty($error)) {
            REDCap::logEvent("What's App Unable to update Log Field: $", $error, "", $record_id, null, $project_id);
            $this->emDebug($error);
            return false;
        }

        # save update
        $payload = [
            'project_id' => $project_id,
            'data' => [
                $record_id => [
                    $log_event_id => [
                        $log_field => $status
                    ]
                ]
            ]
        ];
        $result = REDCap::saveData($payload);
        if (!empty($result['errors'])) {
            $this->emError("Errors saving", $payload, $result);
            return false;
        }
        $this->emDebug("Just updated $log_field in event $log_event_id with $status");
        return true;
    }






    /**
     * Pull down the templates for the project and store them in an em setting
     * @return array
     * @throws \Twilio\Exceptions\ConfigurationException
     */
    public function cacheWhatsAppTemplates() {
        $sid = $this->getProjectSetting('sid');
        $token = $this->getProjectSetting('token');

        $client = new \Twilio\Rest\Client($sid, $token);
        $response = $client->request(
            'GET',
            'https://messaging.twilio.com/v1/Channels/WhatsApp/Templates'
        );

        $templates = [];
        if ($response->ok()) {
            // Templates is an array with key 'whatsapp_templates'
            $content = $response->getContent();

            foreach ($content['whatsapp_templates'] as $t) {
                $sid = $t['sid'];
                foreach ($t['languages'] as $l) {
                    $language = $l['language'];
                    $key = $sid . "_" . $language;
                    $templates[$key] = array_merge($t, $l);
                }
            }
        } else {
            $this->emError("Unable to fetch templates", $response->getStatusCode(), $response->__toString());
        }
        $this->setProjectSetting('templates', $templates);
        return $templates;
    }


    /**
     * Get the cached templates and fetch if empty
     * @return array
     */
    public function getTemplates() {
        $templates = $this->getProjectSetting('templates');
        if (empty($templates)) {
            // Refresh local template store
            $templates = $this->cacheWhatsAppTemplates();
        }
        return $templates;
    }

    /**
     *
     */
    public function getCallbackUrl() {
        # Callback URL for delivery updates
        $callbackUrl = $this->getUrl('pages/statusCallback.php', true, true);
        $callback_override = $this->getProjectSetting('callback-override');
        if (!empty($callback_override)) $callbackUrl = str_replace(APP_PATH_WEBROOT_FULL, $callback_override, $callbackUrl);
        $this->emDebug("Callback url: " . $callbackUrl);
        return $callbackUrl;
    }

    private function replaceNbsp ($string, $replacement = '') {
        $entities = htmlentities($string, null, 'UTF-8');
        $clean = str_replace("&nbsp; ", $replacement, $entities);
        $string = html_entity_decode($clean);
        return $string;
    }

}
