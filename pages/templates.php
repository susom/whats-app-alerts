<?php

namespace Stanford\WhatsAppAlerts;
/** @var \Stanford\WhatsAppAlerts\WhatsAppAlerts $module */






$templates = $module->getWhatsAppTemplates();

foreach ($templates['whatsapp_templates'] as $t) {
    $wat = new Template($module, $t);

    echo "<pre>";
    echo print_r($wat->getAllVariants(), true);
    echo "</pre>";


}

