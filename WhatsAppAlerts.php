<?php
namespace Stanford\WhatsAppAlerts;

require_once "emLoggerTrait.php";

require_once "vendor/autoload.php";

use \Project;
use \REDCap;

require_once("classes/Template.php");


class WhatsAppAlerts extends \ExternalModules\AbstractExternalModule {

    use emLoggerTrait;


    public function __construct() {
		parent::__construct();
		// Other code to run when object is instantiated
	}

    /**
     * Because the redcap_email hook doesn't have any context (e.g. record, project, etc) it can sometimes be hard
     * to know if any context applies.  Generally you can provide details via piping in the subject, but only to a
     * certain extent.  For example, a scheduled ASI email will have a record, but no event / project context
     */
	public function getContext() {
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

        # Get Context
        $bt = debug_backtrace(0);
        // $this->emDebug($bt);

        $context = [];
        foreach ($bt as $t) {
            $function = $t['function'] ?? FALSE;
            $class = $t['class'] ?? FALSE;
            $args = preg_replace(['/\'/', '/"/', '/\s+/', '/_{6}/'], ['','','',''], $t['args']);

            // If email is being sent from an Alert - get context from debug_backtrace using function:
            // sendNotification($alert_id, $project_id, $record, $event_id, $instrument, $instance=1, $data=array())
            if ($function == 'sendNotification' && $class == 'Alerts') {
                $context['trigger']    = "Alert";
                $context['trigger_id'] = $args[0];
                $context['project_id'] = $args[1];
                $context['record_id']  = $args[2];
                $context['event_id']   = $args[3];
                $context['instance']   = $args[5];
                break;
            }

            if ($function == 'scheduleParticipantInvitation' && $class == 'SurveyScheduler') {
                // scheduleParticipantInvitation($survey_id, $event_id, $record)
                $context['trigger']    = "ASI (Immediate)";
                $context['trigger_id'] = $args[0];
                $context['event_id']   = $args[1];
                $context['record_id']  = $args[2];
                break;
            }

            if ($function == 'SurveyInvitationEmailer' && $class == 'Jobs') {
                $context['trigger']    = "ASI (Delayed)";
                $context['trigger_id'] = "";
                // Unable to get project_id in this case
                break;
            }

        }

        // Set event_name from event_id
        if (isset($context['event_id']) && !isset($context['event_name'])) {
            $context['event_name'] = REDCap::getEventNames(true, false, $context['event_id']);
        }
        if (isset($context['event_name']) && !isset($context['event_id'])) {
            $context['event_id'] = REDCap::getEventIdFromUniqueEvent($context['event_name']);
        }
        return $context;
    }



	public function redcap_email( $to, $from, $subject, $message, $cc, $bcc, $fromName, $attachments ) {

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



    public function getWhatsAppTemplates() {
        $sid = $this->getProjectSetting('sid');
        $token = $this->getProjectSetting('token');

        $client = new \Twilio\Rest\Client($sid, $token);
        $response = $client->request(
            'GET',
            'https://messaging.twilio.com/v1/Channels/WhatsApp/Templates'
        );

        if ($response->ok()) {
            // Templates is an array with key 'whatsapp_templates'
            $content = $response->getContent();

            $templates = [];

            foreach ($content['whatsapp_templates'] as $t) {
                $sid = $t['sid'];
                foreach ($t['languages'] as $l) {
                    $language = $l['language'];
                    $key = $sid . "_" . $language;

//                    $templates[$key] =
                }
            }


            return $response->getContent();
        } else {
            $this->emError("Unable to fetch templates", $response->getStatusCode(), $response->__toString());
            return false;
        }
    }



}
