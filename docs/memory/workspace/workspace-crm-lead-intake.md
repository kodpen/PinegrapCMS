---
name: workspace-crm-lead-intake
description: Lead step and box (CRM seams 6, PR #2092): a submitted form's customer into their channel or a new New customer channel; contact by forms.contact_id or e-mail, hourly cap
---

## What is it?
- Step type `lead` of `ws_scheduled_do()` (`ws_scheduled_lead()`, scheduled.php) and box kind `lead` in `ws_step_kinds()` (steps.php); no new rule kind. It runs on a `form.submitted` event: a scheduled action's event rule, or a flow whose start box is that event. Any other start fails it ("Only a submitted form brings a customer in.").
- Settings: `private` (a channel it opens is private), `user_ids` (up to 50, put in a channel it opens). Nothing is required, so `ws_step_config_problems()` gives none.
- Canvas form `leadFields()` in workspace_flow.js; no field in the scheduled-actions form of workspace.js (the step can still be sent through the API's create route).

## How is the customer found?
- `ws_lead_submission_of()` reads the event payload (`id` = the `forms` row, `contact_id`). `ws_lead_contact_find()`: the form's contact when it exists, else the newest contact whose `email_address` is the submission's address (`ws_lead_email_pick()`: the field tied to the contact's address first, else the first `email address` field with a valid address). None found fails the step.
- Two contacts of one person are not merged here: the contacts screen's duplicates and `merge_contacts()` settle them (slice 7 asks the owner first).

## Which channel?
- Reused: the newest public or private channel `ws_customer_channels('contact', id)` gives that the creator reads (`ws_lead_channel_pick()`); a system line says the form came. A reuse never counts against the cap.
- Else opened as the creator with `ws_channel_create()` (customer = the contact, so `ws_customer_input()` asks the contact right) and `ws_template_apply(..., 'builtin:new_customer', new_channel)`. Named `ws_lead_channel_name()` (name, company, address, "Contact #N"), then " (2)" to " (9)" when taken (`ws_lead_channel_names()`). A template that cannot be applied leaves the channel and says why in the step's sentence.

## What limits it?
- `WS_SCHEDULED_LEAD_HOURLY` (20): channels with a contact the creator made in the last hour, by hand too (no schema to mark the step's own). Over it the step fails; a spam form cannot fill the Workspace.
- Rights: `ws_scheduled_action_input()` asks the contact tag right (`ws_ref_types()`) and the templates tables; a flow box is checked with the owner's rights at publish and run, like every step box.

## What does a flow box add?
- `ws_step_run()` hands the run's `event` and `event_payload` (the anchor's context) to this box only; other boxes' texts stay unfilled from the event. The box keeps `channel_id` for `{{node.ID.channel_id}}`.
- Tests: tests/workspace_lead_intake_test.php; the parity table maps `lead`, its options and the limit to this box (thirteen steps).

## What was not checked live?
- No run on a site (2026-10-27): the queries, the system lines and the template on a made channel are unit-tested by source only.
