<?php
namespace Stanford\WhatsAppAlerts;

require_once "emLoggerTrait.php";
require_once "vendor/autoload.php";

use \Exception;
use \Project;
use \REDCap;
use REDCapEntity\Entity;
use REDCapEntity\EntityDB;
use REDCapEntity\EntityFactory;

require_once("classes/Template.php");
require_once("classes/WhatsAppMessage.php");
require_once("classes/MessageLogger.php");

class WhatsAppAlerts extends \ExternalModules\AbstractExternalModule {

    use emLoggerTrait;

    function redcap_module_system_enable($version) {
        // Create the Entity when the module is activated if it doesn't already exist
        \REDCapEntity\EntityDB::buildSchema($this->PREFIX);
    }

    function redcap_every_page_top($project_id)
    {
        if (!defined('REDCAP_ENTITY_PREFIX')) {
            $this->emDebug("Delaying execution...");
            $this->delayModuleExecution();

            // Exits gracefully when REDCap Entity is not enabled.
            return;
        }

        // Insert JS for control center page
        if (strpos(PAGE, 'ExternalModules/manager/control_center.php') !== false) {
            $this->includeJs('js/config.js');
            $this->setJsSettings(['modulePrefix' => $this->PREFIX]);

            return;
        }
    }

    /**
     * Integrated REDCap Entity to manage messages log
     * @return array
     */
    function redcap_entity_types() {
        $types = [];

        $types['whats_app_message'] = [
            'label' => 'WhatsApp Message',
            'label_plural' => 'WhatsApp Messages',
            'icon' => 'email',
            'class' => [
                'name' => 'Stanford\WhatsAppAlerts\Entity\Message',
                'path' => 'classes/entity/Message.php',
            ],
            'properties' => [
                'sid' => [
                    'name' => 'SID',
                    'type' => 'text',
                ],
                'template' => [
                    'name' => 'Template',
                    'type' => 'text',
                ],
                'message' => [
                    'name' => 'Message',
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
                'record' => [
                    'name' => 'Record',
                    'type' => 'text',
                ],
                'instance' => [
                    'name' => 'Instance',
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
                'error' => [
                    'name' => 'Error#',
                    'type' => 'text',
                ],
                'status' => [
                    'name' => 'Status',
                    'type' => 'text',
                    // 'choices' => [
                    //     'queued' => 'Queued',
                    //     'sent' => 'Sent',
                    //     'delivered' => 'Delivered',
                    //     'read'  => 'Read',
                    //     'error' => 'Error',
                    // ]
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


	public function redcap_email( $to, $from, $subject, $message, $cc, $bcc, $fromName, $attachments ) {

        try {
            # Determine if this email is intended for WhatsApp
            $wam = new WhatsAppMessage($this);

            # Stop processing - this is not a valid what's app message
            if (! $wam->parseEmail($message)) {
                $this->emDebug("Email message is not a what's app template - deliver email normally");
                return true;
            }

            # Load Twilio settings
            $wam->setSid($this->getProjectSetting('sid'));
            $wam->setToken($this->getProjectSetting('token'));
            $wam->setFromNumber($this->getProjectSetting('from-number'));
            $wam->setCallbackUrl($this->getCallbackUrl());

            # Ensure we have a valid configuration
            if (! $wam->configurationValid()) {
                $this->emError("configurationValid failed due to errors", $wam->getErrors());
                return false;
            }

            # Send message
            $wam->sendMessage();


            # See if logging message status has been requested
            // $log_field = $wam->getLogField();
            // $log_event_id = $wam->getLogEventId();
            // if (!empty($log_field) && !empty($log_event_id)) {
            //     // Default an empty log_event_name to the current event of the alert
            //     $this->updateAlertLogField($wam->getProjectId(),$wam->getRecordId(),$log_field,$log_event_id,$status);
            // }

            // Debug issues...
            // if ($trans->status !== "queued") {
            //     REDCap::logEvent("Error sending What's App message", $status,"",$wam->getRecordId(), null, $wam->getProjectId());
            //     $msg = "Error with transmission: " . $status;
            //     $this->emDebug($msg);
            // }
        } catch (Exception $e) {
            $this->emDebug("Caught Exception: " . $e->getMessage());
        }

        // Prevent actual email
        return false;
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





    //
    // /**
    //  * Pull down the Twilio What's App templates for the project and store them in
    //  * an em setting variable.  We also expand the templates to include one per language
    //  * so as to simplify selection.  The key for a cached template is:
    //  * template_id + '_' + language
    //  * @return array
    //  * @throws \Twilio\Exceptions\ConfigurationException
    //  */
    // public function cacheWhatsAppTemplates() {
    //     $sid = $this->getProjectSetting('sid');
    //     $token = $this->getProjectSetting('token');
    //
    //     $client = new \Twilio\Rest\Client($sid, $token);
    //     $response = $client->request(
    //         'GET',
    //         'https://messaging.twilio.com/v1/Channels/WhatsApp/Templates'
    //     );
    //
    //     $templates = [];
    //     if ($response->ok()) {
    //         // Templates is an array with key 'whatsapp_templates'
    //         $content = $response->getContent();
    //
    //         foreach ($content['whatsapp_templates'] as $t) {
    //             $sid = $t['sid'];
    //             foreach ($t['languages'] as $l) {
    //                 $language = $l['language'];
    //                 $key = $sid . "_" . $language;
    //                 $templates[$key] = array_merge($t, $l);
    //             }
    //         }
    //     } else {
    //         $this->emError("Unable to fetch templates", $response->getStatusCode(), $response->__toString());
    //     }
    //     $this->setProjectSetting('templates', $templates);
    //     return $templates;
    // }


    // /**
    //  * Get the cached templates and fetch if empty
    //  * @return array
    //  */
    // public function getTemplates() {
    //     $templates = $this->getProjectSetting('templates');
    //     if (empty($templates)) {
    //         // Refresh local template store
    //         $templates = $this->cacheWhatsAppTemplates();
    //     }
    //     return $templates;
    // }

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


}
