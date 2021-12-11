<?php
namespace Stanford\WhatsAppAlerts;

/* EXAMPLE MESSAGE CONFIG
{
    "type": "whatsapp",
    "template_id": "HT93dc8d8d8e768c8851380e65b8635d20_en",
    "template": "messages_awaiting",
    "language": "en",
    "variables": [ "[event_1_arm_1][first_name]", "TEST" ],
    "body": "Dear {{1}}, you have one or more messages waiting for you regarding the {{2}} study.  Please press the button or respond with a 'y' to receive them.  ",
    "number": "[event_1_arm_1][phone_field]",
    "context": {
        "project_id": "[project-id]",
        "event_name": "[event-name]",
        "record_id": "[record-name]",
        "instance": "[current-instance]"
    },
    "log_field": "alert_3_status_field",
    "log_event_id": "67"
}
 */


/* EXAMPLE WHATS APP TWILIO TEMPLATE API CALL
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

/* EXAMPLE OF STORED TEMPLATES AS PARAMETERS
{
	"HT93dc8d8d8e768c8851380e65b8635d20_en": {
		"category": "ACCOUNT_UPDATE",
		"url": "https://messaging.twilio.com/v1/Channels/WhatsApp/Templates/HT93dc8d8d8e768c8851380e65b8635d20",
		"template_name": "messages_awaiting",
		"account_sid": "ACe3ab1c8d7222aedd52dc8fff05d1feb9",
		"languages": [
			{
				"status": "approved",
				"language": "en",
				"date_updated": "2021-11-17 12:20:36.0",
				"content": "Dear {{1}}, you have one or more messages waiting for you regarding the {{2}} study.  Please press the button or respond with a 'y' to receive them.  ",
				"components": [
					{
						"buttons": [
							{
								"text": "Okay - I'm Ready",
								"type": "QUICK_REPLY",
								"index": 0
							}
						],
						"type": "BUTTONS"
					}
				],
				"date_created": "2021-11-17 11:50:16.0",
				"rejection_reason": null
			}
		],
		"namespace_override": null,
		"sid": "HT93dc8d8d8e768c8851380e65b8635d20",
		"status": "approved",
		"language": "en",
		"date_updated": "2021-11-17 12:20:36.0",
		"content": "Dear {{1}}, you have one or more messages waiting for you regarding the {{2}} study.  Please press the button or respond with a 'y' to receive them.  ",
		"components": [
			{
				"buttons": [
					{
						"text": "Okay - I'm Ready",
						"type": "QUICK_REPLY",
						"index": 0
					}
				],
				"type": "BUTTONS"
			}
		],
		"date_created": "2021-11-17 11:50:16.0",
		"rejection_reason": null
	},
	"HTd10b1c663c79dd33a5de95960a046a6e_en": {
		"category": "ACCOUNT_UPDATE",
		"url": "https://messaging.twilio.com/v1/Channels/WhatsApp/Templates/HTd10b1c663c79dd33a5de95960a046a6e",
		"template_name": "pace_welcome_message",
		"account_sid": "ACe3ab1c8d7222aedd52dc8fff05d1feb9",
		"languages": [
			{
				"status": "approved",
				"language": "en",
				"date_updated": "2021-03-30 21:50:59.0",
				"content": "Jambo!  Welcome to the PACE Study.  Thank you for providing your What's App number for notifications and reminders.",
				"components": null,
				"date_created": "2021-03-30 13:52:12.0",
				"rejection_reason": null
			}
		],
		"namespace_override": null,
		"sid": "HTd10b1c663c79dd33a5de95960a046a6e",
		"status": "approved",
		"language": "en",
		"date_updated": "2021-03-30 21:50:59.0",
		"content": "Jambo!  Welcome to the PACE Study.  Thank you for providing your What's App number for notifications and reminders.",
		"components": null,
		"date_created": "2021-03-30 13:52:12.0",
		"rejection_reason": null
	},
	"HT3dae3777624fbc2ecf70d1c25dd8496b_en": {
		"category": "APPOINTMENT_UPDATE",
		"url": "https://messaging.twilio.com/v1/Channels/WhatsApp/Templates/HT3dae3777624fbc2ecf70d1c25dd8496b",
		"template_name": "survey_reminder",
		"account_sid": "ACe3ab1c8d7222aedd52dc8fff05d1feb9",
		"languages": [
			{
				"status": "rejected",
				"language": "en",
				"date_updated": "2021-03-30 22:51:00.0",
				"content": "Jambo! Please complete your {{1}} survey!  Click {{2}}",
				"components": null,
				"date_created": "2021-03-30 13:50:49.0",
				"rejection_reason": "PROMOTIONAL"
			}
		],
		"namespace_override": null,
		"sid": "HT3dae3777624fbc2ecf70d1c25dd8496b",
		"status": "rejected",
		"language": "en",
		"date_updated": "2021-03-30 22:51:00.0",
		"content": "Jambo! Please complete your {{1}} survey!  Click {{2}}",
		"components": null,
		"date_created": "2021-03-30 13:50:49.0",
		"rejection_reason": "PROMOTIONAL"
	}
}
 */

use Twilio\Rest\Client;
use Exception;

/**
 * Class What's App Template Class
 * @property WhatsAppAlerts $module
 */
class Template
{
    private $module;

    protected $config;      // The template configuration from an alert/asi/email message

    protected $templates;   // A cache of the processed Twilio templates

    // SPECIFIC TEMPLATE FIELDS
    protected $template_id;
    protected $template_name;
    protected $status;
    protected $language;
    protected $content;
    protected $components;

    // protected $account_sid;

    protected $message;     // Constructed message with variables substituted

    /**
     * @param WhatsAppAlerts $module
     * @param null $template_id         // Example is HT93dc8d8d8e768c8851380e65b8635d20_en
     */
    public function __construct($module, $template_id = null) {
        $this->module    = $module;

        // Load the template cache
        $this->templates = $module->getProjectSetting('templates');
        if (!empty($template_id)) {
            $this->loadTemplate($template_id);
        }
    }


    /**
     * Load from the external module setting or from Twilio if empty
     * @throws \Twilio\Exceptions\ConfigurationException
     */
    private function loadTemplates($force_api_reload = false) {
        if ($force_api_reload) {
            $this->templates = $this->refreshTemplates();
        } else {
            // Try to load from project settings
            $templates = $this->module->getProjectSetting('templates');

            if (empty($templates)) {
                // project settings are empty, lets try to refresh via api
                $this->templates = $this->refreshTemplates();
            }
        }
    }


    public function loadTemplate($template_id) {
        // Make sure we have the templates in this object
        $this->loadTemplates();

        if (empty($this->templates[$template_id])) {
            // Template does not exist
            throw new Exception("Template $template_id does not exist");
        }

        $template            = $this->templates[$template_id];
        $this->template_id   = $template_id;
        $this->template_name = $template['template_name'];
        $this->status        = $template['status'];
        $this->language      = $template['language'];
        $this->content       = $template['content'];
        $this->components    = $template['components'];

        if ($this->status != 'approved') {
            throw new Exception ("Template $template_id is not approved - current status $this->status");
        }

        if (empty($this->content)) {
            throw new Exception ("Template $template_id does not have any content!");
        }
    }


    /**
     * Construct the actual message from the content and variables
     * @param $variables
     * @return array|string|string[]
     * @throws Exception
     */
    public function buildMessage($variables) {
        $variableCount = count($variables);

        $message = $this->content;
        $contentVariables = self::parseContentVariables($message);
        $templateVariableCount = count($contentVariables);

        if ($templateVariableCount != $variableCount) {
            throw new Exception ("Template $this->template_id calls for " .
                $templateVariableCount . " but the message contained " . $variableCount);
        }

        foreach ($contentVariables as $match) {
            $index = $match['index'];
            $token = $match['token'];
            $value = $variables[$index-1];
            $this->module->emDebug("Replacing index $index token $token with $value");
            $message = str_replace($token, $value, $message);
        }
        $this->module->emDebug("Substitution Complete:", $this->content, $message);
        $this->message = $message;
        return $message;
    }




    /**
     * Use to pull out {{x}} substitutions from templates
     * @param $content
     * @return mixed
     */
    private static function parseContentVariables($content) {
        // https://regex101.com/r/VvU0i3/1
        $re = '/(?\'token\'\{{2}(?\'index\'\d+)\}{2})/m';
        //$str = 'Dear {{1}}, you have one or more messages waiting for you regarding the {{2}}{{32}} study.  Please press the button or respond with a \'y\' to receive them.';
        preg_match_all($re, $content, $matches, PREG_SET_ORDER, 0);
        return $matches;
    }



    /**
     * Take a potentially 'dirty' string that should be a json encoded template definition
     * and convert it into a valid config object
     * @param $config
     * @return bool
     */
    public function parseConfig($config)
    {
        $config = self::parseHtmlishJson($config);

        // Stop processing if config is not valid for what's app
        if ($config === FALSE || !isset($config['type']) || $config['type'] != "whatsapp") {
            return false;
        }

        $this->config = $config;

        // Transfer config params to this object
        foreach ($this->config as $k => $v) {
            $this->module->emDebug("Checking $k to $v", property_exists($this,$k));
            if (property_exists($this,$k)) $this->$k = $v;
        }
        // if (isset($this->config['template_id'])) $this->template_id = $this->config['template_id'];
        // // if (isset($this->config['template']))       $this->template = $this->config['template'];
        // if (isset($this->config['language'])) $this->language = $this->config['language'];
        // if (isset($this->config['variables'])) $this->variables = $this->config['variables'];
        // if (isset($this->config['body'])) $this->body = $this->config['body'];
        // if (isset($this->config['number'])) $this->number = $this->config['number'];
        // if (isset($this->config['context'])) $this->context = $this->config['context'];
        // if (isset($this->config['log_field'])) $this->log_field = $this->config['log_field'];
        // // TODO - handle log_event_name instead
        // if (isset($this->config['log_event_id'])) $this->log_event_id = $this->config['log_event_id'];
        //
        //     // Check for context by merging backtrace with template
        //     $this->setContext();
        //
        //     // Format the TO number correctly
        //     $this->number = self::formatNumber($this->getNumber());
        //
        //     // Make sure the email body template_id is valid
        //     $this->validateTemplate();
        //
        //     // Parse the raw message and substitute values
        //     $this->setMessage();

        return true;
    }



    /**
     * Pull down the Twilio What's App templates for the project and store them in
     * an em setting variable.  We also expand the templates to include one per language
     * so as to simplify selection.  The key for a cached template is:
     * template_id + '_' + language
     * @return array
     * @throws \Twilio\Exceptions\ConfigurationException
     */
    public function refreshTemplates() {
        $sid = $this->module->getProjectSetting('sid');
        $token = $this->module->getProjectSetting('token');

        $client = new Client($sid, $token);
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
            $this->module->emError("Unable to fetch templates", $response->getStatusCode(), $response->__toString());
            throw new Exception("Unable to fetch Twilio templates: " . $response->getStatusCode() . " - " . $response->__toString());
        }
        // $this->module->setProjectSetting('templates', $templates);
        // $this->templates = $templates;

        return $templates;
    }



    // /* HELPER FUNCTIONS */
    //
    // private static function parseHtmlishJson($input) {
    //     $string = self::replaceNbsp($input);
    //
    //     // Remove HTML tags inserted by UI
    //     $junk = [
    //         "<p>",
    //         "<br />",
    //         "</p>"
    //     ];
    //     $string = str_replace($junk, '', $string);
    //
    //     // See if it is valid json
    //     list($success, $result) = self::jsonToObject($string);
    //
    //     if ($success) {
    //         return $result;
    //     } else {
    //         return false;
    //     }
    // }
    //
    // /**
    //  * @param $string
    //  * @param bool $assoc
    //  * @param bool $return_object
    //  * @return array
    //  */
    // private static function jsonToObject($string, $assoc = true)
    // {
    //     // decode the JSON data
    //     $result = json_decode($string, $assoc);
    //
    //     // switch and check possible JSON errors
    //     switch (json_last_error()) {
    //         case JSON_ERROR_NONE:
    //             $error = ''; // JSON is valid // No error has occurred
    //             break;
    //         case JSON_ERROR_DEPTH:
    //             $error = 'The maximum stack depth has been exceeded.';
    //             break;
    //         case JSON_ERROR_STATE_MISMATCH:
    //             $error = 'Invalid or malformed JSON.';
    //             break;
    //         case JSON_ERROR_CTRL_CHAR:
    //             $error = 'Control character error, possibly incorrectly encoded.';
    //             break;
    //         case JSON_ERROR_SYNTAX:
    //             $error = 'Syntax error, malformed JSON.';
    //             break;
    //         // PHP >= 5.3.3
    //         case JSON_ERROR_UTF8:
    //             $error = 'Malformed UTF-8 characters, possibly incorrectly encoded.';
    //             break;
    //         // PHP >= 5.5.0
    //         case JSON_ERROR_RECURSION:
    //             $error = 'One or more recursive references in the value to be encoded.';
    //             break;
    //         // PHP >= 5.5.0
    //         case JSON_ERROR_INF_OR_NAN:
    //             $error = 'One or more NAN or INF values in the value to be encoded.';
    //             break;
    //         case JSON_ERROR_UNSUPPORTED_TYPE:
    //             $error = 'A value of a type that cannot be encoded was given.';
    //             break;
    //         default:
    //             $error = 'Unknown JSON error occured.';
    //             break;
    //     }
    //
    //     if ($error !== '') {
    //         return array(false, $error);
    //     } else {
    //         return array(true, $result);
    //     }
    // }
    //
    // private static function replaceNbsp ($string, $replacement = '') {
    //     $entities = htmlentities($string, null, 'UTF-8');
    //     $clean = str_replace("&nbsp; ", $replacement, $entities);
    //     $result = html_entity_decode($clean);
    //
    //     return $result;
    // }
    //



    /**
     * loop through all language variants for template
     * @return array
     */
    public function getAllVariants() {
        $entries = [];

        foreach ($this->languages as $l) {
            $key = $this->sid . "/" . $l['language'];

            $entries[$key] = [
                "template_name"     => $this->name,
                "sid"               => $this->sid,
                "status"            => $l['status'] . ($l['rejection_reason'] ? " / " . $l['rejection_reason'] : ''),
                "language"          => $l['language'],
                "date_updated"      => $l['date_updated'],
                "content"           => $l['content'],
                "variables"         => count($this->getVariables($l['content'])),
                "components"        => implode(", ", $this->getComponentsSummary($l['components']))
            ];
        }
        return $entries;
    }


    /**
     * Convert the components object into a summary for visualization
     * @param $components
     * @return array
     */
    private function getComponentsSummary($components) {
        $result =  [];
        foreach ($components as $component) {
            if ($component['type'] = 'BUTTONS') {
                foreach ($component['buttons'] as $button) {
                    $result[] = "[" . $button['index'] . ":" . $button['type'] . "] " . $button['text'];
                }
            } else {
                $this->module->emDebug("New Component Type", $component);
            }
        }
        return $result;
    }


    private function getVariables($content) {
        // https://regex101.com/r/VvU0i3/1
        $re = '/(?\'token\'\{{2}(?\'index\'\d+)\}{2})/m';
        //$str = 'Dear {{1}}, you have one or more messages waiting for you regarding the {{2}}{{32}} study.  Please press the button or respond with a \'y\' to receive them.';
        preg_match_all($re, $content, $matches, PREG_SET_ORDER, 0);
        return $matches;
    }


}


/* EXAMPLE OF CONFIG OBJECT

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
