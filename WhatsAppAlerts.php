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

class WhatsAppAlerts extends \ExternalModules\AbstractExternalModule {

    use emLoggerTrait;

    private $account_sid;
    private $token;
    private $from_number;
    private $settings;
    private $settings_loaded;

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


    /**
     * Generic REDCap Email Hook
     */
	public function redcap_email( $to, $from, $subject, $message, $cc, $bcc, $fromName, $attachments ) {
        # Determine if this outbound email is intended to create a WhatsApp Message
        if (!$config = $this->parseEmailForWhatsAppMessage($message)) return true;

        try {
            # It's Whats App Time
            $wam = new WhatsAppMessage($this);

            # Try to populate the wam from the message config
            $wam->loadByConfig($config);

            # Load Twilio settings
            $wam->setAccountSid($this->getProjectSetting('account-sid'));
            $wam->setToken($this->getProjectSetting('token'));
            $wam->setFromNumber($this->getProjectSetting('from-number'));
            $wam->setCallbackUrl($this->getInboundUrl());

            # Ensure we have a valid configuration
            if (! $wam->configurationValid()) {
                $this->emError("configurationValid failed due to errors", $wam->getErrors());
                return false;
            }

            # Send message
            if ($wam->sendMessage()) {
                $wam->setDateSent(strtotime('now'));
                $wam->logNewMessage();
            } else {
                $this->emError("Something went wrong sending message");
            }
        } catch (Exception $e) {
            $this->emError("Caught Exception: " . $e->getMessage());
        }

        // Prevent actual email since this was a What's App attempt
        return false;
	}

    // TODO: Cleanup arguments ofr this function to be more elegant...
    public function sendIcebreakerMessage($wam) {
        try {
            $message_id = $wam->entity->getId();
            $record_id = $wam->getRecordId();
            $to_number = $wam->getToNumber();
            // $this->emDebug($wam);

            $this->emDebug("Sending Icebreaker based on failed callback from $record_id");
            $icebreaker_template  = $this->getProjectSetting('icebreaker-template-id');
            $icebreaker_variables = $this->getProjectSetting('icebreaker-variables');
            $this->emDebug("Loaded variables for icebreaker", $icebreaker_variables);

            // Substitute variables with context
            if (!empty($icebreaker_variables)) {
                $piped_vars = \Piping::replaceVariablesInLabel($icebreaker_variables, $record_id,
                    null, 1, null, false,
                    $this->getProjectId(), false
                );
                $this->emDebug("After Piping", $piped_vars);
                $variables = empty($icebreaker_variables) ? [] : json_decode($piped_vars, true);
                // $this->emDebug("As an array", $variables);
            } else {
                $variables = [];
            }

            $wam2 = new WhatsAppMessage($this);
            // Create a config object which will tell the WAM class to send a message
            $config = [
                "type" => "whatsapp",
                "template_id" => $icebreaker_template,
                "variables" => $variables,
                "number" => $to_number,
                "context" => [
                    "record_id" => $record_id
                ],
                "REDCap Message" => "Triggered by failed message " . $message_id
            ];

            $this->emDebug("Config",$config);
            if($wam2->loadByConfig($config)) {
                # Load Twilio settings
                $wam2->setAccountSid($this->getProjectSetting('account-sid'));
                $wam2->setToken($this->getProjectSetting('token'));
                $wam2->setFromNumber($this->getProjectSetting('from-number'));
                $wam2->setCallbackUrl($this->getInboundUrl());

                # Ensure we have a valid configuration
                if (! $wam2->configurationValid()) {
                    $this->emError("configurationValid failed due to errors", $wam2->getErrors());
                    return false;
                }

                # Send message
                if ($wam2->sendMessage()) {
                    $wam->setDateSent(strtotime('now'));
                    $wam->logNewMessage();
                } else {
                    $this->emError("Something went wrong sending message");
                };
            } else {
                $this->emError("Unable to loadByConfig");
            };
        } catch (\Exception $e) {
            $this->emError("Unable to load icebreaker template: " . $e->getMessage());
        }
    }


    public function sendUndeliveredMessages($entities) {
        $account_sid = $this->getProjectSetting('account-sid');
        $token = $this->getProjectSetting('token');
        $callbackUrl = $this->getInboundUrl();

        // Each entity is an undelivered message
        foreach ($entities as $entity) {
            /** @var Entity $entity */

            // Retry Delivery
            $id = $entity->getId();
            $this->emDebug("Retrying entity " . $id);

            // Original message to be redelivered
            $wam = new WhatsAppMessage($this);
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
            $wam2 = new WhatsAppMessage($this);
            if($wam2->loadByConfig($config)) {
                # Load Twilio settings
                $wam2->setAccountSid($this->getAccountSid());
                $wam2->setToken($this->getToken());
                $wam2->setFromNumber($this->getFromNumber());
                $wam2->setCallbackUrl($this->getInboundUrl());

                # Ensure we have a valid configuration
                if (! $wam2->configurationValid()) {
                    $this->emError("configurationValid failed due to errors", $wam2->getErrors());
                    return false;
                }

                # Send message
                if ($wam2->sendMessage()) {

                    // Save new message to log
                    $wam2->setDateSent(strtotime('now'));
                    $id2 = $wam2->logNewMessage();

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
                };
            } else {
                $this->emError("Unable to loadByConfig");
            };

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
