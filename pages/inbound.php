<?php
namespace Stanford\WhatsAppAlerts;
/** @var WhatsAppAlerts $module */

// $module->emDebug($_POST);

$wam = new WhatsAppMessage($module);
$result = $wam->processInboundMessage();


if ($wam->getIcebreakerNeeded()) {
    $module->sendIcebreakerMessage($wam);
}

