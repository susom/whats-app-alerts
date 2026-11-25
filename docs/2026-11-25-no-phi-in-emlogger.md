# Keep email bodies, numbers and messages out of em_logger (2026-11-25)

## Problem

- **Every email.** The `redcap_email` hook started with `emLog("Alert triggered", $message)`, so the body of every email REDCap sent from an enabled project went to em_logger at INFO. That covers alerts, invitations and any piped participant data. For each ordinary (non-WhatsApp) email, `WhatsAppMessageDefinition` also logged the whole email at ERROR (`CONFIG error, original email`), plus the parsed config and the cleaned string at INFO.
- **Every send.** `sendMessage()` logged the `$wamd` object (recipient number and body; visible when em_logger writes TSV).
- **Inbound and callbacks** logged:
  - the sender's WhatsApp number (INFO);
  - the Twilio `$_POST`, payloads, `InboundMessage` objects and stack traces (ERROR);
  - exception objects.
- **Debug lines** logged every record's phone value in the project (`"ROW val"` in `filterProjectsBySender()`), sender numbers, inbound POSTs, payloads, piped icebreaker variables and raw message entries.

## Fix

Each line now logs message ids, record / project ids, counts and keys instead of the value.

Behavior is unchanged, with one exception: a non-WhatsApp email is now logged as a DEBUG note instead of an ERROR. It is the normal case, not an error.

The commented-out `processInboundMessageOld()` block was left untouched.

## How it was reproduced and verified (local, REDCap 17.5.9, PID 213)

1. A plain email was sent through `REDCap::email()` in the project's context, so the hook runs as it does for an alert. Local mail goes to a MailHog sink.
2. A Twilio-style reply from a fake `+1 650 555-xxxx` number was POSTed to the no-auth `pages/inbound` page. The `To` number is not registered to any project, so no phone values were read and nothing was sent to Twilio.

| Code | Email body in log | Sender number in log | Email delivered |
| --- | --- | --- | --- |
| `master` | 3 lines | 1 line | yes |
| this branch | 0 | 0 | yes |
| this branch, project debug on | 0 of 5 | – | yes |

The callback, icebreaker and redelivery paths need live Twilio traffic, so they were changed by review. Scripts: `emlogger-phi-review/e2e/whatsapp-email.php` and `twilio-inbound.sh` in the REDCap EM workspace.
