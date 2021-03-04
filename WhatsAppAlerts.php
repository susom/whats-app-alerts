<?php
namespace Stanford\WhatsAppAlerts;

require_once "emLoggerTrait.php";

require_once "vendor/autoload.php";

use \Project;
use \REDCap;

class WhatsAppAlerts extends \ExternalModules\AbstractExternalModule {

    use emLoggerTrait;

    public function __construct() {
		parent::__construct();
		// Other code to run when object is instantiated
	}

	public function redcap_email( $to, $from, $subject, $message, $cc, $bcc, $fromName, $attachments ) {

        # Check Subject for '@WHATSAPP' action tag
        # Function takes (record_id, event_name = '', instance_id = 1, log_field, log_event)
        preg_match('/@WHATSAPP\((.*)\)/', $subject, $matches);

        # Abort this hook and send the normal email if there is no match for WHATS APP
        if (empty($matches[1])) return true;

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
        $this->emDebug($bt);
        $context = [];
        foreach ($bt as $t) {
            if (isset($t['function']) && isset($t['class'])) {
                // If email is being sent from an Alert - get context from debug_backtrace using function:
                // sendNotification($alert_id, $project_id, $record, $event_id, $instrument, $instance=1, $data=array())
                if ($t['function'] == 'sendNotification' && $t['class'] == 'Alerts') {
                    $context['trigger']    = "Alert";
                    $context['trigger_id'] = $t['args'][0];
                    $context['record_id']  = $t['args'][2];
                    $context['event_id']   = $t['args'][3];
                    $context['instrument'] = $t['args'][4];
                    $context['instance']   = $t['args'][5];
                    break;
                }

                if ($t['function'] == 'scheduleParticipantInvitation' && $t['class'] == 'SurveyScheduler') {
                    // scheduleParticipantInvitation($survey_id, $event_id, $record)
                    $context['trigger']    = "ASI (Immediate)";
                    $context['trigger_id'] = $t['args'][0];
                    $context['record_id']  = $t['args'][2];
                    $context['event_id']   = $t['args'][1];
                    break;
                }

                if ($t['function'] == 'SurveyInvitationEmailer' && $t['class'] == 'Jobs') {
                    // scheduleParticipantInvitation($survey_id, $event_id, $record)
                    $context['trigger']    = "ASI (Delayed)";
                    break;
                }

            }
        }

        if (empty($context)) {
            // Unable to parse context
            // REDCap::logEvent("Unable to parse context for WHATSAPP email message", )
            $this->emError("Unable to parse context for this email", $bt);
            return false;
        }

        $this->emDebug("EMAIL CONTEXT: " . json_encode($context));
        $record_id = $context['record_id'] ?? "";
        $trigger = $context['trigger'];

        # Parse the contents of the action tag "phone_number, log_field(optional), log_event(optional)"
        $parts = array_map('trim', explode(",",$matches[1]));
        $number    = $parts[0];
        $log_field = !empty($parts[1]) ? $parts[1] : NULL;
        $log_event_name = !empty($parts[2]) ? $parts[2] : NULL;

        // if (empty($record_id)) {
        //     $this->emDebug("Record ID missing");
        //     return false;
        // }

        if (empty($number)) {
            $this->emDebug("Number missing for context: ", $context);
            return false;
        }

        # See if field logging has been requested
        $log_event_id = '';
        if (!empty($log_field)) {
            // Default an empty log_event_name to the current event of the alert
            $log_event_id = empty($log_event_name) ? $context['event_id'] : REDCap::getEventIdFromUniqueEvent($log_event_name);
        }

        // if (!empty($log_field) && empty($log_event) && \REDCap::isLongitudinal()) $log_event = current(\REDCap::getEventNames(true));
        // $field_name = trim($parts[1]);
        // $eventNames = \REDCap::getEventNames(true);
        // $event_name = ( !empty($parts[2]) && in_array(trim($parts[2]),$eventNames) ) ? trim($parts[2]) : current($eventNames);
        // Get the value for the what's app number
        // $params = [
        //     "records" => [$record_id],
        //     "fields" => [$field_name],
        //     "return_format" => "json",
        //     "events" => [$event_name]
        // ];
        // $q = \REDCap::getData($params);
        // $results = json_decode($q,true);
        // if (!empty($results['errors'])) {
        //     \REDCap::logEvent("Error obtaining What's App number for record $record_id in field $field_name");
        //     $this->emError("Error querying What's App Number: ", $params, $results);
        //     return false;
        // }
        // if (empty($results[0][$field_name])) {
        //     \REDCap::logEvent("Unable to find a number for record $record_id in field $field_name");
        //     $this->emDebug("Unable to find a number in the $field_name field for record $record_id");
        //     return false;
        // }
        //
        // $number = trim($results[0][$field_name]);
        // $this->emDebug($results, $number);

        $sid = $this->getProjectSetting('sid');
        $token = $this->getProjectSetting('token');
        $fromNumber = $this->getProjectSetting('from-number');
        $callbackUrl = $this->getUrl('statusCallback.php', true, true);

        $callbackUrl = str_replace("redcap.local","c4054076e31e.ngrok.io",$callbackUrl);
        $this->emDebug($callbackUrl);

        if (empty($sid) || empty($token) || empty($fromNumber)) {
            REDCap::logEvent("Missing required sid, token, or from number in What's App Alerts configuration");
            $this->emDebug("Missing sid or token or number");
            return false;
        }

        # strip tags from message and format number
        $body = strip_tags($message, "");
        $this->emDebug("Number before: " . $number);
        if (strpos($number, "+",0) === false) $number = "+" . $number;
        $this->emDebug("Record: $record_id, Number: $number, From: $fromNumber, Body: $body");

        # Send message
        $client = new \Twilio\Rest\Client($sid, $token);
        $trans = $client->messages->create(
            "whatsapp:" . $number, [
            "from" => "whatsapp:" . $fromNumber,
            "body" => $body,
            "statusCallback" => $callbackUrl
        ]);

        // Log the message
        $status = $trans->status . (empty($trans->errors) ? "" : " - " . $trans->errors);
        $payload = [
            'record'       =>$record_id,
            'number'       =>$number,
            'project_id'   =>PROJECT_ID,
            'event_id'     =>$context['event_id'] ?? '',
            'instance'     =>$context['instance'] ?? '',
            'instrument'   =>$context['instrument'] ?? '',
            'alert_id'     =>$context['alert_id'] ?? '',
            'sid'          =>$trans->sid,
            'status'       =>$status,
            'log_field'    =>$log_field,
            'log_event_id' =>$log_event_id
        ];

        $r = $this->log($body, $payload);
        $this->emDebug("Just logged payload to log_id $r");
        // $entry = $this->queryLogs("select record, number, project_id, event_id, instance, instrument, alert_id, sid, status, log_field, log_event_id  where log_id = $r");

        # If the configuration calls for logging the status to a field, do it.
        if (!empty($log_field)) {
            $this->updateAlertLogField(PROJECT_ID,$record_id,$log_field,$log_event_id,$status);
        }

        if ($trans->status !== "queued") {
            REDCap::logEvent("Error sending What's App message", $status,"",$record_id, null, PROJECT_ID);
            $msg = "Error with transmission: " . $status;
            $this->emDebug($msg);
        }

        // Prevent actual email
        return FALSE;
	}



	public function updateLogStatusCallback($sid, $status) {
        # Get the em log entry for this sid
        $q = $this->queryLogs("select log_id, project_id, record, instance, sid, log_field, log_event_id where sid = ?", [$sid]);
        if ($row = $q->fetch_assoc()) {
            // $this->emDebug($row);
            $log_id = $row['log_id'];
            $project_id = $row['project_id'];
            $record_id = $row['record'];
            $log_field = $row['log_field'];
            $log_event_id = $row['log_event_id'];
            if(!empty($log_field)) $this->updateAlertLogField($project_id, $record_id, $log_field, $log_event_id, $status);

            # Update the external module log table as well
            $query = $this->createQuery();
            $query->add('replace into redcap_external_modules_log_parameters (log_id, name, value) values (?, ?, ?)', [
                $log_id,
                'status_update',
                $status
            ]);
            $query->execute();
            $this->emDebug("Setting $log_id last_status to $status: " . $query->affected_rows);
            $query = $this->createQuery();
            $query->add('replace into redcap_external_modules_log_parameters (log_id, name, value) values (?, ?, ?)', [
                $log_id,
                'status_modified',
                date('Y-m-d H:i:s')
            ]);
            $query->execute();
            $this->emDebug($query->affected_rows);
        }
    }


	public function updateAlertLogField($project_id, $record_id, $log_field, $log_event_id, $status) {

        global $Proj;
        $logProj = ($Proj->project_id ?? 0 == $project_id) ? $Proj : new Project($project_id);

        // Verify log field / event are valid
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
            REDCap::logEvent("What's App Error", $error, "", $record_id, null, $project_id);
            $this->emDebug($error);
            return false;
        }

        # save update
        $payload = [
            'project_id' => $project_id,
            'dataFormat' => 'json',
            'data' => json_encode([
                [
                    $logProj->table_pk => $record_id,
                    'redcap_event_name' => REDCap::getEventNames(true,false, $log_event_id),
                    $log_field => $status
                ]
            ])
        ];
        $result = REDCap::saveData($payload);
        if (!empty($result['errors'])) {
            $this->emError("Errors saving", $payload, $result);
            return false;
        }
        $this->emDebug("Just updated $log_field in event $log_event_id with $status");
        return true;
    }
}
