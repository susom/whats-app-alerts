# Developer Setup

The following notes should help set up REDCap with What's App by Twilio


## Twilio Setup

1. Set up your Twilio Account
2. Purchase a Phone Number
3. Create a message service and associate it to your number
4. Create a What's App enabled sender
   1.
5. Register a What's App Sender (requires approval)
6. Create a Message Service to handle these messages and associate it to your number
7. You must register approved What's App Templates.  What's App and Twilio have complex rules about what content you can sent to a What's App number.  The content depends on whether or not your are in an 'open session' or not.  An open session lasts 24 hours and begins when a participant replies to your phone number.  In most cases you will NOT have an open session with a participant and will have to use a 'template' to message them.  We suggest creaing an 'ice breaker' template that can be used to open a new open session.

TODO: Document better






## REDCap Setup
1. Install the external module and goto configure
2. Apply the 'default' project for testing (see xml file in 'documentation' folder)
3. Enter in your Twilio account SID and token, the from number (in correct format), and then you probably need to set an 'override url' if you are developing on your own LAN.
   1. Twilio needs to be able to call your server.  When developing locally, I use ngrok for this, e.g.:  `ngrok http 80` which gives me a url like: `http://9d67-98-42-7-123.ngrok.io`
   2. Once you've done this, you need to tell Twilio to send callbacks to the correct EM address.  To get the address, goto your project and click on the `What's App Info` em page.  You should see an Inbound Url.  Copy and paste this value.
   3. Goto Twilio and place this url as the inbound web services for your messages.  Depending on how your configure your Twilio Message Service you can either place this url on your incoming phone number OR you can place it on the incoming service.  For now, I'll use the phone number.









1. Setup local ngrok
- ngrok -> ngrok  http:80 ;

2. Navigate to REDCap project external module settings for What's App
3. Set override URL to ngrok output (will override callback to your localhost)
4. Gather inbound url from REDCap and paste into Request URL
- Messaging service -> modify Integration 'send a webhook'
-
