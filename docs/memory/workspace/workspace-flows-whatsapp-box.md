---
name: workspace-flows-whatsapp-box
description: Flows' Send a WhatsApp message box (kind whatsapp, WA7 #9372): an approved template, variables v1-v20 with values, the e-mail in its place; the event whatsapp.received
---

## What is the box?
- Step kind `whatsapp` ("Send a WhatsApp message") in `ws_step_kinds()`, functions in `includes/workspace/flow_whatsapp_box.php` (prefix `ws_step_whatsapp_`). Tests `tests/workspace_flow_whatsapp_box_test.php`.
- Settings (`ws_step_whatsapp_normalize()`): `to` (typed number), `recipient` typed | record_person (as the SMS box, `workspace-flows-sms-box.md`), `template` (`name|language`), `v1`-`v20` (the template's variables in `pg_whatsapp_message_slots()` order: header, message, a button's link; one line each, values and the scheduled words allowed), `fallback` 0|1, `fallback_to`, `fallback_subject`, `fallback_body`.
- No purpose setting: the template's category is Meta's, read at the send; a marketing template goes only with SMS marketing consent (`pg_whatsapp_enqueue()`, owner #9087).

## Why is the recipient never a value?
- Owner rule of every recipient (`workflow/decisions-made-workspace-4.md`): addresses and numbers take no thread. The step's "recipient from a value" is the record's person, read when the box runs.

## How does it send?
- Only queued: `pg_whatsapp_enqueue()` with kind `flow`, reference `flow:<run>:<pass>:<box>`; a marketing template expires 6 hours after queueing. Variables filled by `pg_whatsapp_message_components()` (WA5's, not copied).
- Outputs `sent` (1 queued, 0 not) and `emailed` (the e-mails sent in its place).
- Check: a template gone, not approved, with a media header or copy-code button, over 20 variables, or a variable left empty stops the box from being published.

## When does the e-mail go in its place?
- With `fallback` on: no number, or the message could not be queued (WhatsApp off, template not approved, consent refused, queue refusal). Check lets such a box through so the e-mail can go.
- To the record's person's address (`ws_flow_mail_person()`), or the typed `fallback_to`. For a marketing template the record's person needs e-mail marketing consent (`ws_flow_mail_marketing_refusal()`) and the mail carries the unsubscribe link.
- A delivery WhatsApp reports failed later (WA4's webhook) sends no e-mail: the box has ended.

## Where is it offered?
- Canvas palette and quick setup Message group only while `pg_whatsapp_ready()`; `FL.steps.whatsapp.templates` from `ws_step_whatsapp_offered()` (approved, text-fillable). Tier irreversible; an outbound kind on the flow card; not a role-3 member's kind.
- Each variable is a row "Variable N" with where it sits, never Meta's `{{1}}` (owner, #2222). Rows carry `data-wa-var` / `data-wa-part` for WA8b's chip piece (#9376), which had not landed when the box was built.

## What is the event "A WhatsApp message arrived"?
- `whatsapp.received` in `ws_events_catalog()`: record contact, right contacts. Payload `pg_whatsapp_received_event()` (`includes/fn/events.php`): id (inbox row), from, profile_name, type, body (1000), contact_id; never the media or wamid. Also an API webhook event.
- Announced by `pg_whatsapp_inbox_receive()` (`includes/fn/whatsapp_webhook.php`) for each new customer message kept in `whatsapp_inbox`, after its bell notice; a retried wamid is not announced again.
