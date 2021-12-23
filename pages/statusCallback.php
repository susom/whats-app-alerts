<?php
namespace Stanford\WhatsAppAlerts;
/** @var WhatsAppAlerts $module */

$wam = new WhatsAppMessage($module);
// $wam->updateLogStatusCallback();
$module->emDebug(__FILE__);
$wam->processInboundMessage();
