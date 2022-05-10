<?php
namespace Stanford\WhatsAppAlerts;
/** @var WhatsAppAlerts $module */

$wam = new WhatsAppHelper($module);
// $wam->updateLogStatusCallback();
$module->emDebug(__FILE__);
$wam->processInboundMessage();
