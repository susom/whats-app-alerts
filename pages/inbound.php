<?php
namespace Stanford\WhatsAppAlerts;
/** @var WhatsAppAlerts $module */

$module->emDebug("INBOUND POST: " . json_encode($_POST));

$module->processInboundMessage();
