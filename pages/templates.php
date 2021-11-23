<?php

namespace Stanford\WhatsAppAlerts;
/** @var \Stanford\WhatsAppAlerts\WhatsAppAlerts $module */

redcap_info();
exit();

// $ex = '<p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;{<br />&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; "type": "whatsapp",<br />&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; "template_id": "ACe3ab1c8d7222aedd52dc8fff05d1feb9_en",<br />&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; "template": "messages_awaiting",<br />&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; "language": "en",<br />&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; "variables": [ "[event_1_arm_1][baseline]", "Hello" ],<br />&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; "body": "blank if free text, otherwise will be calculated based on template and variables",<br />&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; "phone": "[event_1_arm_1][whats_app_number]",<br />&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; "context": {<br />&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; "project_id": "[project-id]",<br />&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; "event_name": "[event-name]",<br />&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; "record_id": "[record-id]",<br />&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; "instance": "[current-instance]"<br />&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; }<br />&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; }</p>';
// var_dump($ex);
//
// $o = $module->parseWhatsAppJson($ex);
// var_dump($o);
//
// exit();

$templates = $module->cacheWhatsAppTemplates();

//foreach ($templates['whatsapp_templates'] as $t) {
//    $wat = new Template($module, $t);
//
    echo "<pre>";
    echo print_r($templates, true);
    echo "</pre>";


//}

