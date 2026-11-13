---
name: workspace-flows-sms-box
description: Flows' Send an SMS box (kind sms, #9290): the record's person's mobile or a typed number, info (SMS kind flow) or marketing with consent, queued by pg_sms_enqueue()
---

## What is it?
- Step kind `sms` ("Send an SMS") in `ws_step_kinds()`, functions in `includes/workspace/flow_sms_box.php` (prefix `ws_step_sms_`). Plan step QS6 (`workspace-flows-quick-setup-rules.md`), PR #9290. Tests `tests/workspace_flow_sms_box_test.php`.
- Settings (`ws_step_sms_normalize()`): `to` (one typed number, 200), `recipient` typed | record_person, `purpose` info | marketing, `text` (900, takes values and the scheduled words such as `{{customer}}`). The number never takes a value, as no recipient does.
- Problems: a typed number empty or not one `pg_sms_number()` reads (a landline, a value), an empty text. `ws_step_sms_check()` also refuses while SMS is not set up (`pg_sms_ready()`) and a marketing box without the sender identity.

## Whom does it write to?
- record_person: `ws_step_sms_person()` reads the record as the e-mail box does (`ws_flow_mail_person_source()`, the owner's rights): an order's billing phone (none for a marketplace order), a booking's phone, a contact's mobile, the contact's mobile of a submitted form or of a received e-mail. No number: done, sent 0, the reason in the run.

## How does it send?
- Only queued: `pg_sms_enqueue()` (`platform/sms-outbox-queue.md`), never `pg_sms_send()`. Reference `flow:<run>:<pass>:<box>` so a box run again does not queue twice.
- info goes as SMS kind `flow` (transactional, no İYS check, label Automation flow on the queue tab). marketing goes as kind `marketing`: `pg_sms_marketing_permission()` first (the campaigns' rule, `platform/sms-marketing-kind.md`); a refusal leaves the box done with sent 0. It expires 6 hours after queueing, as a campaign's message.
- Output `sent` (1 queued, 0 not). The run's sentence shows only the number's last four digits.

## Where is it offered?
- Canvas palette (Message group) and the quick setup's Message group only while `pg_sms_ready()` (`FL.steps.sms`, `ws_flow_quick_fill()`). Not a role-3 member's kind (as the e-mail box). Tier irreversible; an outbound kind on the flow card; runs with the Workspace off.
