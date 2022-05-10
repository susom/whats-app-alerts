<?php
namespace Stanford\WhatsAppAlerts;

use \REDCap;


/**
 * Class MessageContext
 *
 * Because the redcap_email hook doesn't have any context (e.g. record, project, etc) it can sometimes be hard
 * to know if any context applies.  Generally you can provide details via piping in the subject, but only to a
 * certain extent.  For example, a scheduled ASI email will have a record, but no event / project context
 *
 * This module sets:
 * source, source_id, project_id, record_id, event_id, instance, and event_name
 *
 */
class MessageContext {
    private $source;
    private $source_id;
    private $project_id;
    private $record_id;
    private $event_id;
    private $instance;
    private $event_name;

    public function __construct($context = []) {
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
            // \Plugin::log($t);
            $function = $t['function'] ?? FALSE;
            $class = $t['class'] ?? FALSE;
            $args = $t['args'] ?? FALSE;
            // remove whitespace and misc chars
            // if(is_array($args)) $args = preg_replace(['/\'/', '/"/', '/\s+/', '/_{6}/'], ['','','',''], $args);

            // If email is being sent from an Alert - get context from debug_backtrace using function:
            // sendNotification($alert_id, $project_id, $record, $event_id, $instrument, $instance=1, $data=array())
            if ($function == 'sendNotification' && $class == 'Alerts') {
                // \Plugin::log($args);
                $this->source     = "Alert";
                $this->source_id  = $args[0] ?? null;
                $this->project_id = $args[1] ?? null;
                $this->record_id  = $args[2] ?? null;
                $this->event_id   = $args[3] ?? null;
                $this->instance   = $args[5] ?? 1;
                break;
            }

            if ($function == 'scheduleParticipantInvitation' && $class == 'SurveyScheduler') {
                // \Plugin::log($args);
                // scheduleParticipantInvitation($survey_id, $event_id, $record)
                $this->source     = "ASI (Immediate)";
                $this->source_id  = $args[0] ?? null;
                $this->event_id   = $args[1] ?? null;
                $this->record_id  = $args[2] ?? null;
                break;
            }

            if ($function == 'SurveyInvitationEmailer' && $class == 'Jobs') {
                \Plugin::log($args);
                $this->source    = "ASI (Delayed)";
                $this->source_id = "";
                // Unable to get project_id in this case
                break;
            }
        }

        # Try to get project_id from url if not already set
        if (empty($this->project_id)) {
            $pid = $_GET['pid'] ?? null;
            // Require an integer to prevent any kind of injection (and make Psalm happy)
            $pid = filter_var($pid, FILTER_VALIDATE_INT);
            if($pid !== false) $this->project_id = (string)$pid;
        }

        # OVERRIDE BACKTRACE VALUES WITH SPECIFIED INPUT CONTEXT IF PRESENT
        if (!empty($context['source']))     $this->source     = $context['source'];
        if (!empty($context['source_id']))  $this->source_id  = $context['source_id'];
        if (!empty($context['event_name'])) $this->event_name = $context['event_name'];
        if (!empty($context['event_id']))   $this->event_id   = $context['event_id'];
        if (!empty($context['record_id']))  $this->record_id  = $context['record_id'];
        if (!empty($context['project_id'])) $this->project_id = $context['project_id'];
        if (!empty($context['instance']))   $this->instance   = $context['instance'];

        # Set event_name from event_id and visa vera
        if (!empty($this->project_id)) {
            if (!empty($this->event_id) && empty($this->event_name)) {
                $this->event_name = REDCap::getEventNames(true, false, $this->event_id);
            }

            // This method got complicated to make it work when not in project context from cron
            if (!empty($this->event_name) && empty($this->event_id)) {
                global $Proj;
                $thisProj = (
                    !empty($Proj->project_id) && $this->project_id == $Proj->project_id) ?
                    $Proj :
                    new \Project($this->project_id);
                $this->event_id = $thisProj->getEventIdUsingUniqueEventName($this->event_name);
            }
        }
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
    public function getEventName()
    {
        return $this->event_name;
    }

}
