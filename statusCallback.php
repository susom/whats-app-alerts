<?php
namespace Stanford\WhatsAppAlerts;
/** @var WhatsAppAlerts $module */

$module->emDebug("Callback", json_encode($_POST));
/*
    [SmsSid] => SMeb941545a1eb46cab32c67df2f8bef62
    [SmsStatus] => sent
    [Body] => This is *bold* and _underlined_...
    [MessageStatus] => sent
    [ChannelToAddress] => +1650380XXXX
    [To] => whatsapp:+16503803405
    [ChannelPrefix] => whatsapp
    [MessageSid] => SMeb941545a1eb46cab32c67df2f8bef62
    [AccountSid] => AC4c78ad3161bed65c08e36f77847f914a
    [StructuredMessage] => false
    [From] => whatsapp:+14155238886
    [ApiVersion] => 2010-04-01
    [ChannelInstallSid] => XEcc20d939f803ee381f2442185d0d5dc5
 */

$sid = $_POST['SmsSid'] ?? null;
$status = $_POST['SmsStatus'] ?? null;

$module->updateLogStatusCallback($sid, $status);
