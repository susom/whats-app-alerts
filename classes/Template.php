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
 * What's App Template Class
 * This is not to be confused with a message config that is used in ASI's and Alerts
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

    protected $body;     // Constructed message with variables substituted

    /**
     * @param WhatsAppAlerts $module
     * @param null $template_id         // Example is HT93dc8d8d8e768c8851380e65b8635d20_en
     * @return bool
     * @throws
     */
    public function __construct($module, $template_id = null) {
        // Passing in module for logging/etc
        $this->module    = $module;

        // Load the template cache
        if (!empty($template_id)) {
            $this->getTemplate($template_id);
        }
    }

    /**
     * Load all templates from the external module setting or from Twilio if empty or forced
     * @throws \Twilio\Exceptions\ConfigurationException
     */
    private function loadTemplates($force_api_reload = false) {
        if ($force_api_reload) {
            $this->templates = $this->refreshTemplates();
        } else {
            // Try to load from project settings
            $this->templates = $this->module->getProjectSetting('templates');

            if (empty($this->templates)) {
                // project settings are empty, lets try to refresh via api
                $this->templates = $this->refreshTemplates();
            }
        }
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
        $account_sid = $this->module->getProjectSetting('account-sid');
        $token = $this->module->getProjectSetting('token');
        $client = new Client($account_sid, $token);
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
                    // Since we are pivoting on languages we can omit them from the final template
                    unset($templates[$key]['languages']);
                }
            }

            if (!empty($content['META']['next_page_url'])) {
                // TODO: IMPLEMENT SUPPORT FOR PAGING WHEN THERE ARE MORE THAN ONE PAGE OF TEMPLATES
                $this->module->emError("Looks like we need to implement paging for the templates");
            }
        } else {
            $this->module->emError("Unable to fetch templates", $response->getStatusCode(), $response->__toString());
            throw new Exception("Unable to fetch Twilio templates: " . $response->getStatusCode() . " - " . $response->__toString());
        }

        $this->module->setProjectSetting('templates', $templates);
        return $templates;
    }

    /**
     * Find the requested template and populate the object with it
     * @param $template_id
     * @return bool
     * @throws Exception
     */
    public function getTemplate($template_id) {
        // Make sure we have the templates in this object
        $this->loadTemplates(false);

        if (empty($this->templates[$template_id])) {
            // Template does not exist
            throw new Exception("Specified template $template_id does not exist");
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

        return true;
    }

    /**
     * Construct the actual message from the content and variables
     * @param $variables
     * @return array|string|string[]
     * @throws Exception
     */
    public function getBody($variables = []) {
        // Parse the template or raw message for {x} variables
        $variable_placeholders = self::parseContentVariables($this->content);

        if (count($variable_placeholders) != count($variables)) {
            throw new Exception ("Template $this->template_id calls for " .
                count($variable_placeholders) . " variables but the message only contained " .
                count($variables));
        }

        // Substitute variables into placeholders
        $body = $this->content;
        foreach ($variable_placeholders as $match) {
            $index = $match['index'];
            $token = $match['token'];
            $value = $variables[$index-1];
            // $this->module->emDebug("Replacing index $index token $token with $value");
            $body = str_replace($token, $value, $body);
        }
        // $this->body = $body;
        return $body;
    }





    /**
     * @param $record_id
     * @return array|false|string|string[]
     * @throws \Twilio\Exceptions\ConfigurationException
     */
    public function getIcebreakerMessage($record_id) {
        $icebreaker_template = $this->module->getProjectSetting('icebreaker-template-id');
        if (empty($icebreaker_template)) {
            // Nothing to do
            $this->module->emDebug("Icebreaker required but not configured");
            return false;
        }

        // Load the template
        $this->getTemplate($icebreaker_template);

        // Load any variables
        $icebreaker_variables = $this->module->getProjectSetting('icebreaker-variables');
        $this->module->emDebug("Loaded variables for icebreaker", $icebreaker_variables);

        // Substitute variables with context
        $piped_vars = \Piping::replaceVariablesInLabel($icebreaker_variables, $record_id,
            null, 1, null, false,
            $this->module->getProjectId(), false
        );
        $this->module->emDebug("After Piping", $piped_vars);
        $variables = empty($icebreaker_variables) ? [] : json_decode($piped_vars,true);
        $this->module->emDebug("As an array", $variables);

        // Build the actual message body and return
        return $this->getBody($variables);
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
     * @return mixed
     */
    public function getTemplateName()
    {
        return $this->template_name;
    }

    /**
     * @return mixed
     */
    public function getTemplateId()
    {
        return $this->template_id;
    }


}
