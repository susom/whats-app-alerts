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
require_once("classes/Template.php");

class WhatsAppAlerts extends \ExternalModules\AbstractExternalModule {

    use emLoggerTrait;

    private $account_sid;
    private $token;
    private $from_number;
    private $settings;
    private $settings_loaded;

    function redcap_module_system_enable($version) {
        // Create the Entity when the module is enabled if it doesn't already exist
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

        // Insert JS for control center page
        if (strpos(PAGE, 'ExternalModules/manager/control_center.php') !== false) {
            $this->includeJs('js/config.js');
            $this->setJsSettings(['modulePrefix' => $this->PREFIX]);
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
                'name' => 'Stanford\WhatsAppAlerts\WAM',
                'path' => 'classes/WAM.php',
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


    /**
     * Given a message/template configuration, send it!
     * @param $message_config
     * @return mixed false or log_id
     * @throws \Twilio\Exceptions\ConfigurationException
     * @throws \Twilio\Exceptions\TwilioException
     */
    public function sendMessage($message_config) {
        # It's What's App Time - we will first parse out the template and context
        $wah = new WhatsAppHelper($this);

        # Try to populate the wah from the parsed what's app message config
        $wah->createOutboundMessage($message_config);

        # Set WAH Twilio settings
        $wah->setAccountSid($this->getProjectSetting('account-sid'));
        $wah->setToken($this->getProjectSetting('token'));
        $wah->setFromNumber($this->getProjectSetting('from-number'));
        $wah->setCallbackUrl($this->getInboundUrl());

        # Ensure we have a valid configuration
        if (! $wah->configurationValid()) {
            $this->emError("configurationValid failed due to errors", $wah->getErrors());
            return false;
        }

        # Send message
        if ($wah->sendMessage()) {
            $wah->setDateSent(strtotime('now'));
            $log_id = $wah->logNewMessage();
            return $log_id;
        } else {
            $this->emError("Something went wrong sending message");
            return false;
        }
    }


    /**
     * Generic REDCap Email Hook
     */
	public function redcap_email( $to, $from, $subject, $message, $cc, $bcc, $fromName, $attachments ) {
        # Determine if this outbound email is intended to create a WhatsApp Message
        if (!$message_config = $this->parseEmailForWhatsAppMessage($message)) return true;

        try {
            # It's What's App Time
            $this->sendMessage($message_config);
        } catch (Exception $e) {
            $this->emError("Caught Exception: " . $e->getMessage());
        }

        // Prevent actual email since this was a What's App attempt
        return false;
	}



    public function sendIcebreakerMessage($wah) {
        try {

            # Get context from previous message
            $message_id = $wah->entity->getId();
            $record_id = $wah->getRecordId();
            $to_number = $wah->getToNumber();

            $icebreaker_template       = $this->getProjectSetting('icebreaker-template-id');
            $icebreaker_variables_json = $this->getProjectSetting('icebreaker-variables');
            $this->emDebug("Sending Icebreaker template $icebreaker_template for $message_id / #$record_id");

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
                ],
                "REDCap Message" => "Triggered by failed message " . $message_id
            ];
            $this->emDebug("Config",$message_config);
            $this->sendMessage($message_config);

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
            $this->emDebug("Retrying entity " . $id);

            // Original message to be redelivered
            $wam = new WhatsAppHelper($this);
            $wam->loadFromEntity($entity);

            // Determine message age
            $original_message = $wam->getMessage();
            $age = $wam->getMessageAge();
            $new_message = "_Originally scheduled $age ago (#$id)_ \n\n" . $original_message;

            $config = [
                "number" => $wam->getToNumber(),
                "body" => $new_message,
                "context" => [
                    "source"    => "Undelivered Message",
                    "source_id" => $id,
                    "record_id" => $wam->getRecordId()
                ],
                "REDCap Message" => "This is a redelivery of message #$id - " . $wam->getMessageSid()
            ];
            $this->emDebug("Config",$config);

            // New message that will be used for redelivery
            if ($log_id = $this->sendMessage($config)) {
                // Update original message
                $update = [
                    "date_redelivered" => strtotime('now'),
                    "status" => "redelivered"
                ];
                $wam->appendRaw(array_merge($update,
                        [
                            "REDCap Message" => "Redelivered as message #$id2 after delay of $age",
                        ])
                );
                $update['raw'] = $wam->getRaw();
                $this->emDebug("About to set data to ", $update);
                if (!$entity->setData($update)) {
                    $this->emError("An error occurred setting the data:", $update);
                };
                $id = $entity->save();
                if ($id === false) {
                    $this->emError("An error occurred saving the data:", $update);
                } else {
                    $this->emDebug("Message #$id - Resent and updated");
                }
            } else {
                $this->emError("Something went wrong sending message");
            }
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

    public function getFromNumber()
    {
        if (! $this->settings_loaded) $this->loadSettings();
        return $this->from_number;
    }

    public function getInboundUrl() {
        $url = $this->getUrl('pages/inbound.php', true, true);
        return $this->checkDevUrl($url);
    }


    /**
     * Fix callback/internal urls for dev purposes, e.g. NGROK.
     * @return string
     */
    public function checkDevUrl($url) {
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
     * Parse message from email body for What's App config
     * @param $message
     * @return bool     false if not a what's app message
     */
    private function parseEmailForWhatsAppMessage($message)
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

        return $config;
    }






    /** UTILITY FUNCTIONS */

    private static function parseHtmlishJson($input) {
        $string = self::replaceNbsp($input, "  ");
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


    /**
     * @param $string
     * @param $replacement
     * @return array|string|string[]
     */
    private static function replaceNbsp ($string, $replacement = '  ') {
        $funky="|!|";
        $entities = htmlentities($string, ENT_NOQUOTES, 'UTF-8');
        $subbed = str_replace("&nbsp; ", $funky, $entities);
        $decoded = html_entity_decode($subbed);
        return str_replace($funky, $replacement, $decoded);
    }

}
