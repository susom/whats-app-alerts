# What's App Alerts
A module for sending email messages via What's App using Twilio's What's App APIs.

This module supports:
* Alerts and Notifications
* Automated Survey Invitations

## Prerequisites
You must have a Twilio account and number configured for What's App.  This typically requires registering you phone
number from Twilio with your Facebook Business identity.  More information can be found
[here](https://www.twilio.com/docs/whatsapp/tutorial/connect-number-business-profile):

For development you can set up a temporary number but must respond from the destination number (your what's app)
every 24 hours in order to enable additional messages.

## How to Configure
To use the module you replace the subject of the email with an action-tag like function.
The function can be as simple as

`@WHATSAPP(+16508675309)`
or
`@WHATSAPP([phone_field])`

This module uses REDCap piping to supply the necessary context for sending the message
as the redcap_email hook does not otherwise provide any information about the project/record/etc...

In addition to the basic syntax above, it also supports additional arguments to provide context and additional features.
The full context is:

`
@WHATSAPP(
  PHONE_NUMBER,
  PROJECT_ID (optional),
  RECORD_ID (optional),
  EVENT_NAME (optional),
  INSTANCE (optional=1),
  LOG_FIELD_NAME (optional),
  LOG_EVENT_NAME (optional)
)
`

In some cases, the module is able to determine the 'context' of the email message.  For example,
in an Alert and Notification that is triggered IMMEDIATELY after a save event, the record_id,
event_name, and instance will all be automatically discovered, so you only need provide the
PHONE_NUMBER parameter via piping.  An example from a classical project would be:

`
@WHATSAPP([event_1_arm_1][phone_field])
`

However, if you configure your alert to fire after a delay, it is better to provide the
additional context parameters as part of the subject.  They will then be converted by
piping and stored in the email queue.  When the email is sent, this module will be able to
completely log the interaction.  An example of an Alert sent the next day at 8AM might look like:

`
@WHATSAPP([event_1_arm_1][phone_field], [project-id], [event_1_arm_1][record_id], [event-name], [current-instance])
`

## Automate Survey Invitations
This module also supports intercepting ASI emails and sending them via What's App.

## Best Practices
If your study supports both EMAIL and What's App messaging, you will want to configure two Alerts for each
message.  If the participant says they have What's App - then have the logic controlling the alerts decide
if the subject will use the @WHATSAPP tag or not.  Any email that does not include the @WHATSAPP tag will
be sent via the normal email method.

## Saving the Message Status
All What's App messages are saved to the External Module logs and can be viewed by users with project-design rights
via the External Modules link on the left sidebar.

Additionally, it is possible to configure a field in the project that will store the status of the message.
This might be useful for cases where future logic is dependent on a successful delivery of a What's App message.

To do this, you must supply the `field_name` and `event_name` of the location in the record where you want to store the
message status.  An example is:

`
@WHATSAPP([event_1_arm_1][phone_field], [project-id], [event_1_arm_1][record_id], [event-name], [current-instance], msg_status_field, [event-name])
`

NOTICE: in this case, we **do not** include brackets around the msg_status_field.  This parameter is the NAME of the
field, not its current value.

So, imagine you have a project where each month there is a reminder to complete a survey.  You configure an
Alert to trigger once per record/event.  In a form enabled for each event, you include a @HIDDEN-SURVEY field
called "alert_status".  You could then set the subject of the Alert to be:

`
@WHATSAPP([baseline_arm_1][phone], '', '', '', '', alert_status)
`

or, the syntax also supports quotes or no quotes

`
@WHATSAPP([baseline_arm_1][phone],,,,,"alert_status")
`

or, if you were using any 'delay' after the logic was true, you would want to include all parameters:

`
@WHATSAPP([baseline_arm_1][phone], [project-id], [baseline_arm_1][record_id],[event-name], [current-instance], alert_status)
`

You can also optionally include a final log_event_id argument if you wish to store the `alert_status`
in a field from a different event than the actual Alert.

## TODO:

WHATS APP CONFIGURATION LOGIC:

Template Config Page:
1) Get the current templates
2) Specify a template for 'break the ice'
3) request a new template
4) delete a template

In Alert Config:
1. Select What's App icon
2. Hide rest of normal form and display new form
3. dropdown of the template to use OR free-text
   1. if the template has variables, you need to define the redcap fields or text to go into the variable location
      1. VAR 1: "text or redcap_field"
4. variable for phone number
5. variable for project_id (hidden)
6. variable for record_id (hidden)
7.


On Project-level, define 'break-the-ice' message temaplte.  This is the
template that will be used if a custom message is sent outside of
an open 24 window.







- Build a page to download What's App Templates and store them in the EM Config
  - should support ability to refresh templates
- Define your 'ice breaker' template that is sent when something runs over 24 hours
- Store messages and determine if ice breaker needs to be sent
- build ui to configure outgoing messages


- Edit UI of Alerts and Notifications so that subject has hint about how to set up and send with What's App
```help
<a href="javascript:;" class="help ml-1" onclick="simpleDialog('If this checkbox is checked, any field variables (e.g., [date_of_birth]) that exist in the alert\'s subject or message    will not have their value piped if the field has been tagged as an \'Identifier\' field. In this case, it will simply replace    the field variable with [*DATA REMOVED*] rather than piping the actual data into the message.','Prevent piping of data for Identifier fields');">?</a>
```
- View EM Logs from plugin page


# The Ice Breaker Template
What's App does not permit non-template messages unless the recipient has responded in the past 24 hours.
If a message is rejected with a Twilio error code 63016 (window not open) - this module can automatically
send an ice breaker message based on an approved template.  When the participant responds to the ice breaker,
we can then automatically trigger the previously rejected messages.  The ice breaker template should be in the
format of: `HT93dc8d8d8e768c8851380e65b8635d20_en` and is typically set from the What's App configuration page.

# Alert Format
This What's app module works by intercepting the redcap_email hook and parsing json in the content body set in the alerts and notifications page.
1. Using freeform messages
2. Using templates
   - Example body
    ```json
   {
         "type": "whatsapp",
         "template_id": "HT59a6bd0962fb1555dc68ddfae0352ad9_en",
         "number": "[week_0_arm_1][screen_phone]",
         "variables": ["Hi", "second", "third"],
         "context": {
           "project_id": "40",
           "event_name": "week_2_arm_1",
           "record_id": "1",
           "instance": ""
         }
     }
    ```
   -

