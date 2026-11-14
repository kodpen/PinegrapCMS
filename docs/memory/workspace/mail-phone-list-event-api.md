---
name: mail-phone-list-event-api
description: WhatsApp and SMS conversations in the E-mails list (Kind filter), the workspace.mail.received event (transport, number, How it came) and the API (#9420)
---

## How does the list show a phone conversation?
- In the same list as e-mail, no second screen (plan rule). `ws_mail_transport_kind()` names a box's transport: whatsapp, sms, or email for every mailbox (imap, graph, gmail, push); `ws_mail_transport_labels()` labels them.
- Address word `transport` (email | whatsapp | sms, '' for all) in `ws_mail_list_filters()`; `ws_mail_list_where()` adds `box_id IN (SELECT … transport = 'whatsapp')`, or `NOT IN (… 'whatsapp', 'sms')` for e-mail.
- The toolbar's Kind select ("All kinds") only when the reader sees conversations of more than one transport (`ws_mail_list_transports()`); the heading's description then names WhatsApp and SMS.
- A row of a phone conversation: the customer's name or `+number`, no "(no subject)" line, the WhatsApp or SMS badge (`ws_mail_list_transport_badge_html()`). Search already finds a number: an incoming item's `from_email` is `+digits`.

## What does the event carry?
- `ws_mail_phone_ingest()` calls `ws_mail_phone_received_event()` for every stored message: `workspace.mail.received` with the e-mail payload, `customer_email` and `subject` empty, `customer_phone` (+E.164), `transport`, `verified` true; never the text. The webhook twin adds `transport` to its ids.
- An e-mail's payload says `transport` email; an event kept before has none and counts as email (`ws_events_filter_value()`).
- The catalogue entry is "An e-mail or message is received", filter `transport` ("How it came", "Any way"); an existing rule keeps "any", so a flow on received mail also starts on a WhatsApp message in its channel. Flow outputs add transport and customer_phone.

## What does the API give?
- `WorkspaceMailConversation` has `transport` and `customer_phone` (null for e-mail); the list takes `transport` (all | email | whatsapp | sms). Each handler adds the box's transport to the row (`ws_api_mail_box_transports()`).
- Answering a phone conversation over the API is not open yet: `POST …/{id}/reply` refuses it with 409 cannot_send (no address). It goes through `whatsapp_outbox` once WA14a (#9405) has landed: step WA15b.

## Tests?
- `tests/workspace_mail_transport_test.php`; parity lines for the filter and the emitter in `tests/workspace_flows_parity_test.php`. Nothing was run against a database in the cloud session.

Related: docs/memory/workspace/mail-phone-conversations.md, docs/memory/workspace/mail-list.md, docs/memory/workspace/mail-api.md
