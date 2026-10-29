---
name: mail-notices-and-event
description: Who hears of a Workspace e-mail conversation (inbox kinds mail_assigned, mail_customer) and the workspace.mail.received event for scheduled actions and flows (G5b)
---

## Who is told?
- Given to somebody else: inbox kind `mail_assigned` (message = the card, actor = who gave it). Taking it oneself tells nobody.
- The customer writes again on a held conversation (`ws_mail_ingest()`): `mail_customer` to the holder (message = the new mail); read with the channel like a mention (`ws_channel_mark_read()`). Lines drawn by `ws_mail_inbox_describe()` (subject as body); the API inbox route names both kinds. Nobody holds it: the channel's unread count is the notice.

## Which event does mail start?
- `workspace.mail.received` (`ws_event()` in `ws_mail_ingest()`, every stored incoming mail, new or not; not a bounce, loop or held mail). Payload: id and conversation_id, channel_id, message_id, box_id, new, customer, customer_email, subject, verified; never the mail's text (untrusted, the card carries it). In the catalogue only after the tables exist; record = its channel; placeholders {{record_title}} = subject, {{customer}}; flow outputs in `ws_flow_event_outputs()`; its parity lines in tests/workspace_flows_parity_test.php. The webhook twin with ids only is `workspace.mail.received` in `mail-api.md`.

## Tests?
- `tests/workspace_mail_status_test.php` (wiring), `tests/watch_test.php` (record of the event). The notices and the event's run were not run against a database in the cloud session.
