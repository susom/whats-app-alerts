<?php
namespace Stanford\WhatsAppAlerts;

# Check to parse json from email body
/*
     {
        "type": "whatsapp",
        "template_id": "ACe3ab1c8d7222aedd52dc8fff05d1feb9_en",
        // "template": "messages_awaiting",
        "language": "en",
        "variables": [ "[event_1_arm_1][baseline]", "Hello" ],
        "body": "blank if free text, otherwise will be calculated based on template and variables",
        "number": "[event_1_arm_1][whats_app_number]",
        "context": {
            "project_id": "[project-id]",
            "event_name": "[event-name]",
            "record_id": "[record-id]",
            "instance": "[current-instance]"
        }
    }
*/

/**
 * Class WhatsAppMessage
 * @property WhatsAppAlerts $module
 * @property \Project $Proj
 */
class WhatsAppMessage
{
    protected $config;

    private $module;
    private $context = [];

    private $template_id;
    private $template;
    private $language;
    private $variables;
    private $body;
    private $number;
    private $log_field;
    private $log_event_id;


    private $Proj;
    private $trigger;
    private $trigger_id;
    private $project_id;
    private $record_id;
    private $event_name;
    private $event_id;
    private $instance;

    private $message;       // Finished message formed from variables


    private $errors = [];

    public function __construct($module, $config) {
        $this->module    = $module;
        $this->config    = $config;

        // Transfer config params to object
        if (isset($this->config['template_id']))    $this->template_id = $this->config['template_id'];
        // if (isset($this->config['template']))       $this->template = $this->config['template'];
        if (isset($this->config['language']))       $this->language = $this->config['language'];
        if (isset($this->config['variables']))      $this->variables = $this->config['variables'];
        if (isset($this->config['body']))           $this->body = $this->config['body'];
        if (isset($this->config['number']))         $this->number = $this->config['number'];
        if (isset($this->config['context']))        $this->context = $this->config['context'];
        if (isset($this->config['log_field']))      $this->log_field = $this->config['log_field'];
        if (isset($this->config['log_event_id']))   $this->log_event_id = $this->config['log_event_id'];

        // Check for context by merging backtrace with template
        $this->setContext();

        $this->formatNumber();

        // Make sure the email body template_id is valid
        $this->validateTemplate();

        // Create the message
        $this->setMessage();

    }

    # format number for What's App E164 format.  Consider adding better validation here
    private function formatNumber() {
        // Strip anything but numbers
        $number = preg_replace('/[^\d]/', '', $this->number);
        $this->number = '+' . $number;
    }

    /**
     * Make sure the template is okay
     */
    private function validateTemplate() {
        // Check if the template has been updated and is approved
        if (!empty($this->template_id)) {
            $all_templates = $this->module->getTemplates();
            if (!isset($all_templates[$this->template_id])) {
                $this->errors[] = "Template $this->template_id does not appear to be valid in this project";
            }

            // Load template
            $this->template = $all_templates[$this->template_id];
            if ($this->template['status'] != 'approved') {
                $this->errors[] = "Template has not been approved, status is: " . $this->template['status'];
            }

            // body contains nbsps...
            // if ($this->template['content'] != $this->body) {
            //     $this->errors[] = "Message body is different than template content -- will use template content!";
            //     $this->module->emError("Message body differs from template content", $this->body, $this->template);
            // }
        }
    }

    /**
     * Determine if the configuration is valid
     * @return false
     */
    public function configurationValid() {
        if (count($this->errors) == 0) {
            return true;
        } else {
            $this->module->emDebug("configurationValid failed due to errors", $this->errors);
            return false;
        }
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Because the redcap_email hook doesn't have any context (e.g. record, project, etc) it can sometimes be hard
     * to know if any context applies.  Generally you can provide details via piping in the subject, but only to a
     * certain extent.  For example, a scheduled ASI email will have a record, but no event / project context
     */
    private function setContext() {
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
                $this->trigger    = "Alert";
                $this->trigger_id = $args[0];
                $this->project_id = $args[1];
                $this->record_id  = $args[2];
                $this->event_id   = $args[3];
                $this->instance   = $args[5];
                break;
            }

            if ($function == 'scheduleParticipantInvitation' && $class == 'SurveyScheduler') {
                // scheduleParticipantInvitation($survey_id, $event_id, $record)
                $this->trigger    = "ASI (Immediate)";
                $this->trigger_id = $args[0];
                $this->event_id   = $args[1];
                $this->record_id  = $args[2];
                break;
            }

            if ($function == 'SurveyInvitationEmailer' && $class == 'Jobs') {
                $this->trigger    = "ASI (Delayed)";
                $this->trigger_id = "";
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
        $context = empty($this->context) ? [] : $this->context;
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
    public function getTrigger()
    {
        return $this->trigger;
    }

    /**
     * @return mixed
     */
    public function getTriggerId()
    {
        return $this->trigger_id;
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

    // /**
    //  * Log initial transmission
    //  * @param $sid
    //  * @param $status
    //  */
    // public function logTransmission($sid, $status) {
    //     $payload = [
    //         'record'         =>$this->getRecordId(),
    //         'number'         =>$this->getNumber(),
    //         'project_id'     =>$this->getProjectId(),
    //         'event_id'       =>$this->getEventId(),
    //         'event_name'     =>$this->getEventName(),
    //         'instance'       =>$this->getInstance(),
    //         'trigger'        =>$this->getTrigger(),
    //         'trigger_id'     =>$this->getTriggerId(),
    //         'log_field'      =>$this->getLogField(),
    //         'log_event_id'   =>$this->getLogEventId(),
    //         'sid'            =>$sid,
    //         'status'         =>$status,
    //     ];
    //     $r = $this->module->log($this->getMessage(), $payload);
    //     $this->module->emDebug("Just logged payload to log_id $r with $status");
    // }




    //
    // /**
    //  * loop through all language variants for template
    //  * @return array
    //  */
    // public function getAllVariants() {
    //     $entries = [];
    //
    //     foreach ($this->languages as $l) {
    //         $key = $this->sid . "/" . $l['language'];
    //
    //         $entries[$key] = [
    //             "template_name"     => $this->name,
    //             "sid"               => $this->sid,
    //             "status"            => $l['status'] . ($l['rejection_reason'] ? " / " . $l['rejection_reason'] : ''),
    //             "language"          => $l['language'],
    //             "date_updated"      => $l['date_updated'],
    //             "content"           => $l['content'],
    //             "variables"         => count($this->getVariables($l['content'])),
    //             "components"        => implode(", ", $this->getComponentsSummary($l['components']))
    //         ];
    //     }
    //     return $entries;
    // }
    //
    //
    // /**
    //  * Convert the components object into a summary for visualization
    //  * @param $components
    //  * @return array
    //  */
    // private function getComponentsSummary($components) {
    //     $result =  [];
    //     foreach ($components as $component) {
    //         if ($component['type'] = 'BUTTONS') {
    //             foreach ($component['buttons'] as $button) {
    //                 $result[] = "[" . $button['index'] . ":" . $button['type'] . "] " . $button['text'];
    //             }
    //         } else {
    //             $this->module->emDebug("New Component Type", $component);
    //         }
    //     }
    //     return $result;
    // }

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



}


/*

Array
(
    [whatsapp_templates] => Array
        (
            [0] => Array
                (
                    [category] => ACCOUNT_UPDATE
                    [url] => https://messaging.twilio.com/v1/Channels/WhatsApp/Templates/HT93dc8d8d8e768c8851380e65b8635d20
                    [template_name] => messages_awaiting
                    [account_sid] => ACe3ab1c8d7222aedd52dc8fff05d1feb9
                    [languages] => Array
                        (
                            [0] => Array
                                (
                                    [status] => approved
                                    [language] => en
                                    [date_updated] => 2021-11-17 12:20:36.0
                                    [content] => Dear {{1}}, you have one or more messages waiting for you regarding the {{2}} study.  Please press the button or respond with a 'y' to receive them.
                                    [components] => Array
                                        (
                                            [0] => Array
                                                (
                                                    [buttons] => Array
                                                        (
                                                            [0] => Array
                                                                (
                                                                    [text] => Okay - I'm Ready
                                                                    [type] => QUICK_REPLY
                                                                    [index] => 0
                                                                )

                                                        )

                                                    [type] => BUTTONS
                                                )

                                        )

                                    [date_created] => 2021-11-17 11:50:16.0
                                    [rejection_reason] =>
                                )

                        )

                    [namespace_override] =>
                    [sid] => HT93dc8d8d8e768c8851380e65b8635d20
                )

            [1] => Array
                (
                    [category] => ACCOUNT_UPDATE
                    [url] => https://messaging.twilio.com/v1/Channels/WhatsApp/Templates/HTd10b1c663c79dd33a5de95960a046a6e
                    [template_name] => pace_welcome_message
                    [account_sid] => ACe3ab1c8d7222aedd52dc8fff05d1feb9
                    [languages] => Array
                        (
                            [0] => Array
                                (
                                    [status] => approved
                                    [language] => en
                                    [date_updated] => 2021-03-30 21:50:59.0
                                    [content] => Jambo!  Welcome to the PACE Study.  Thank you for providing your What's App number for notifications and reminders.
                                    [components] =>
                                    [date_created] => 2021-03-30 13:52:12.0
                                    [rejection_reason] =>
                                )

                        )

                    [namespace_override] =>
                    [sid] => HTd10b1c663c79dd33a5de95960a046a6e
                )

            [2] => Array
                (
                    [category] => APPOINTMENT_UPDATE
                    [url] => https://messaging.twilio.com/v1/Channels/WhatsApp/Templates/HT3dae3777624fbc2ecf70d1c25dd8496b
                    [template_name] => survey_reminder
                    [account_sid] => ACe3ab1c8d7222aedd52dc8fff05d1feb9
                    [languages] => Array
                        (
                            [0] => Array
                                (
                                    [status] => rejected
                                    [language] => en
                                    [date_updated] => 2021-03-30 22:51:00.0
                                    [content] => Jambo! Please complete your {{1}} survey!  Click {{2}}
                                    [components] =>
                                    [date_created] => 2021-03-30 13:50:49.0
                                    [rejection_reason] => PROMOTIONAL
                                )

                        )

                    [namespace_override] =>
                    [sid] => HT3dae3777624fbc2ecf70d1c25dd8496b
                )

        )

    [meta] => Array
        (
            [page] => 0
            [page_size] => 50
            [first_page_url] => https://messaging.twilio.com/v1/Channels/WhatsApp/Templates?PageSize=50&Page=0
            [previous_page_url] =>
            [url] => https://messaging.twilio.com/v1/Channels/WhatsApp/Templates?PageSize=50&Page=0
            [next_page_url] =>
            [key] => whatsapp_templates
        )

)

 */
