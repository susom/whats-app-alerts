<?php
namespace Stanford\WhatsAppAlerts;


/**
 * Class Template
 * @property WhatsAppAlerts $module
 */
class Template
{
    protected $config;
    protected $name;
    protected $sid;
    protected $languages;

    private $module;

//    private $status;
//    private $content;
//    private $components;

    public function __construct($module, $config) {
        $this->module    = $module;
        $this->config    = $config;

        $this->name      = $config['template_name'];
        $this->sid       = $config['sid'];
        $this->languages = $config['languages'];
    }

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
