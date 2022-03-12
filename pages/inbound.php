<?php
namespace Stanford\WhatsAppAlerts;
/** @var WhatsAppAlerts $module */

// $module->emDebug($_POST);

$wah = new WhatsAppHelper($module);
$result = $wah->processInboundMessage();


if ($wah->getIcebreakerNeeded()) {
    $module->sendIcebreakerMessage($wah);
}

