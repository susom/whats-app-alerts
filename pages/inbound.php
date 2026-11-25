<?php
namespace Stanford\WhatsAppAlerts;
/** @var WhatsAppAlerts $module */

/**
 * This is a system-level page as inbound messages could be intended for different projects
 */

// Not $_POST: it holds the sender number and message body.
$module->emDebug("INBOUND POST, MessageSid " . ($_POST['MessageSid'] ?? '-'));

$module->processInboundMessage("inbound");
