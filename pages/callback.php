<?php
namespace Stanford\WhatsAppAlerts;
/** @var WhatsAppAlerts $module */

$module->emDebug("In Status Callback");

//$wam = new WhatsAppHelper($module);
// $wam->updateLogStatusCallback();
$module->emDebug(__FILE__);
$module->processInboundMessage("callback");
