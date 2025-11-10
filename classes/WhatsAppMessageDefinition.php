<?php
namespace Stanford\WhatsAppAlerts;

use \REDCapEntity\Entity;
use \REDCapEntity\EntityFactory;
use \Exception;

/**
 * Class WhatsAppMessageDefinition
 *
 * This class represents a json array that contains a definition for a template-based or free text message
 * It also supports methods to obtain information needed for logging
 *
 * @property WhatsAppAlerts $module
 * @property Entity $entity
 */
class WhatsAppMessageDefinition
{

    private $config;           // raw json message configuration
    private $to_number;        // To Number
    private $body;             // Message body
    private $MessageContext;   // Context object
    private $variables = [];
    private $template_id;
    private $template_name;
    private WhatsAppAlerts $module;

    public function __construct($module) {
        // Link the parent EM so we can use helper methods
        $this->module    = $module;
    }


    /**
     * Try to parse out the config and prepare for delivery
     * @return boolean
     */
    public function parseDefinition($config) {
        // Parse the message configuration - which should look something like the example below:
        /*
             [
                "type" => "whatsapp",
                "template_id" => "ACe3ab1c8d7222aedd52dc8fff05d1feb9_en",
                "template" => "messages_awaiting",
                // "language" => "en",
                "variables" => [ "[event_1_arm_1][baseline]", "Hello" ],
                "body" => "blank if free text, otherwise will be calculated based on template and variables",
                "number" => "[event_1_arm_1][whats_app_number]",
                // "log_field" => "field_name",
                // "log_event_name" => "log_event_name",
                "context" => [
                    "project_id" => "[project-id]",
                    "event_name" => "[event-name]",
                    "record_id" => "[record-id]",
                    "instance" => "[current-instance]"
                ]
            ]
        */

        // See if template contains context (optional)
        $definition_context = empty($config['context']) ? [] : $config['context'];
        $this->MessageContext = new MessageContext($definition_context);

        // $this->source     = $mc->getSource();
        // $this->source_id  = $mc->getSourceId();
        // $this->project_id = $mc->getProjectId();
        // $this->record_id  = $mc->getRecordId();
        // $this->event_id   = $mc->getEventId();
        // $this->event_name = $mc->getEventName();
        // $this->instance   = $mc->getInstance();

        if(isset($config['variables']) and !empty($config['variables'])){
            // Adjust for 1-based array from REDCap
            array_unshift($config['variables'], "");
            unset($config['variables'][0]);
            $this->variables = $config['variables'];
        }

        $this->template_id = $config['template_id'] ?? null;

        if ($this->template_id) {
            // We are using a template -- lets load the template
            $Template = new Template($this->module, $this->template_id);

            // Let's create the message from the template
            $body = $Template->getBody($this->variables);
            $this->template_name = $Template->getTemplateName();
            $this->template_id = $Template->getTemplateId();
        } else {
            // No template - lets build message from raw body
            $raw_body = html_entity_decode($config['body']);
            $body = strip_tags($raw_body, 'a');
            // $this->message = strip_tags($body,'');
            // $this->module->emDebug("Decoding body", $config['body'], $raw_body, $body);
        }

        $this->setBody($body);
        $this->setToNumber($config['number']);
        $this->config = $config;
    }


    /**
     * Parse message 'config' from email body.  Config can refer to a template or be a free-text message
     * @param $email
     * @return bool     false if not a what's app message
     */
    public function parseEmailForWhatsAppMessageDefinition($email)
    {
        /*
         * HERE IS AN EXAMPLE JSON THAT COULD BE IN A MESSAGE.
         * TODO: I WOULD PREFER TO CHANGE THIS SO DETAILS ARE IN ANOTHER TABLE AND NOT PRONE TO CHANGES BY TIDY RICH TEXT EDITOR
         *
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
        $config = $this->parseHtmlishJson($email);
        $this->module->emLog('output parsed config:', $config);
        // Stop processing - this is not a valid what's app message
        if ($config === FALSE || !isset($config['type']) || $config['type'] != "whatsapp") {
            // Not What's App Message Definition
            $this->module->emError('CONFIG error, original email:', $email);
            return false;
        } else {
            // Is What's App Message Definition
            $this->config = $config;
            $this->parseDefinition($config);
            return true;
        }
    }







    /***************** UTILITY FUNCTIONS *********************/

    /**
     * A kludgy method to parse JSON out of the html that the Tidy editor butchers in message templates...
     * @param $input
     * @return false|mixed
     */
    private function parseHtmlishJson($input) {
        $string = self::replaceNbsp($input, "  ");
        $junk = [
            "<p>",
            "<br />",
            "<br>",
            "</p>",
            "<div>",
            "</div>"
        ];
        // Remove HTML tags inserted by UI
        $string = str_replace($junk, '', $string);
        $string = preg_replace('/\xC2\xA0/', ' ', $string);
        $this->module->emLog('replacement string to be validated', $string);
        // See if it is valid json
        list($success, $result) = $this->jsonToObject($string);
            // false, error , true result
        if ($success) {
            return $result;
        } else {
            return false;
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


    /**
     * Try to parse JSON into a php object and handle errors
     * @param $string
     * @param $assoc
     * @return array
     */
    private function jsonToObject($string, $assoc = true)
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
            $this->module->emError('JSON errors encountered', $error);
            return array(false, $error);
        } else {
            return array(true, $result);
        }
    }


    /**
     * @param mixed $body
     */
    public function setBody($body): void
    {
        $this->body = $body;
    }

    /**
     * @param $number
     * @return void
     */
    public function setToNumber($number) {
        $this->to_number = self::formatNumber($number);
    }

    /**
     * Format number for What's App E164 format.  Consider adding better validation here
     * @param $number
     * @return string
     */
    private static function formatNumber($number) {
        // Strip anything but numbers and add a plus
        $clean_number = preg_replace('/[^\d]/', '', $number);
        return '+' . $clean_number;
    }

    public function getTemplate() {
        return $this->template_name ?? "";
    }

    public function getBody() {
        return $this->body;
    }

    public function getTemplateId() {
        return $this->template_id ?? "";
    }

    public function getMessageContext() {
        return $this->MessageContext;
    }

    public function getToNumber() {
        return $this->to_number;
    }

    public function getConfig() {
        return $this->config;
    }

    public function getVariables() {
        return $this->variables;
    }
}

