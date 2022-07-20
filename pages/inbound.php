<?php
namespace Stanford\WhatsAppAlerts;
/** @var WhatsAppAlerts $module */

/**
 * This is a system-level page as inbound messages could be intended for different projects
 */

$module->emDebug("INBOUND POST: " . json_encode($_POST));

$module->processInboundMessage();
