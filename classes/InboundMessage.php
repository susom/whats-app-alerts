<?php
namespace Stanford\WhatsAppAlerts;


/**
 * PARSE AN INBOUND MESSAGE FROM WHAT'S APP
 */

    /* EXAMPLE CALLBACK
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
            (optional)
        [ErrorCode] => 63016,
        [EventType] => "UNDELIVERED"
     */

    /* EXAMPLE REPLY:
        [NumMedia] => 0
        [ProfileName] => Andy Martin
        [WaId] => 16503803405
        [SmsStatus] => received
        [Body] => two
        [To] => whatsapp:+16502755484
        [NumSegments] => 1
        [MessageSid] => SM6bc26f968756025fd01db0ffd941ce83
        [AccountSid] => ACe3ab1c8d7222aedd52dc8fff05d1feb9
        [From] => whatsapp:+16503803405
        [ApiVersion] => 2010-04-01
     */

class InboundMessage
{
    public $post;

    const ICEBREAKER_ERROR = 63016;

    public function __construct()
    {
        // Remove unused properties if set
        unset($_POST['SmsMessageSid']);
        unset($_POST['SmsSid']);
        unset($_POST['MessageStatus']); // Using SmsStatus instead
        // unset($_POST['SmsStatus']); // Using MessageStatus instead

        $this->post = $_POST;

        // Return true if Message SID is present
        return !empty($this->getMessageSid());

        // TODO: I do not know whether or not replies will come here with or without a pid

    }

    // Return reply or callback depending on message type
//    public function getMessageType() {
//        return empty($this->post['EventType']) ? 'reply' : 'callback';
//    }

    public function getMessageSid () {
        return $this->post['MessageSid'] ?? null;
    }

    public function getStatus() {
        return $this->post['SmsStatus'] ?? '';
    }

    public function getErrorCode() {
        return $this->post['ErrorCode'] ?? '';
    }

    public function getToNumber() {
        return $this->post['To'] ?? '';
    }

    public function getBody() {
        return $this->post['Body'] ?? '';
    }

    public function getFromNumber() {
        return $this->post['From'] ?? '';
    }

    public function getRaw() {
        return $this->post;
    }

    public function isIceBreakerError() {
        return $this->getErrorCode() == self::ICEBREAKER_ERROR;
    }

}
