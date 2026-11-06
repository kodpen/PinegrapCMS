---
name: workspace-flows-email-record-person
description: Flow e-mail box to the person of the run's record (B8): recipient record_person, purpose info or marketing, campaign consent, unsubscribe link, nothing sent with its reason
---

## What does it do?
- E-mail box settings `recipient` (`typed` default, `record_person`) and `purpose` (`info` default, `marketing`) in `ws_step_config_normalize()`. Decided by the owner (`workflow/decisions-made-product-33.md`, B8: A): the address is read from the record, never typed; typed addresses work as before.
- Code: `includes/workspace/flow_mail_person.php`; hooks in `ws_step_run()` and `ws_flow_box_try()` (steps.php), `ws_scheduled_mail()` (scheduled.php).

## Which person?
- `ws_flow_mail_person_source()` (pure): a run's `subject_type` contact (started by hand for a contact, or the no-order start); else the event's record by `ws_events_record_of()`: order, form, booking, contact; `workspace.mail.received` its `conversation_id`. Anything else (a schedule, stock.low, a flow started by another) has none.
- `ws_flow_mail_person()`: the record read with the owner's rights (`ws_step_record_get_read()`; a mail by `ws_can_read_channel()` on its channel), then the address: `orders.billing_email_address`, `get_submitter_email_address()`, `bookings.email`, `contacts.email_address`, `ws_mail_conversations.customer_email`.

## Consent and unsubscribe?
- `ws_flow_mail_marketing_refusal()`: as a campaign's commercial mail at send time: a contact with the address and `opt_in = 1`, not refused in İYS (`pg_iys_email_refused()`), not blocked by bounces (`pg_mail_address_blocked()`). `contacts.opt_in` is the e-mail marketing flag (`core/consent-log-writers.md`); unsubscribing turns it off.
- Marketing mail: `ws_scheduled_mail(..., true)` sends with `type campaign`, `purpose commercial` (List-Unsubscribe headers in `email()`) and `ws_flow_mail_unsubscribe_footer()` adds the `pg_email_preferences_url()` link (none without ENCRYPTION_KEY). The action key `email.marketing` has its parity line.

## When it cannot write?
- No record, a record the owner may not read, no valid address, or marketing without consent: the box settles done, outcome done, `sent` 0, the reason as its result; the boxes after it go on (a condition on `sent` can branch). Not failed: a person not to write to is no error.
- Try: without a run it says it writes to the record's person; with a run it names the reason or fills the address as the run would.

## Canvas and AI?
- `emailFields()`: Write to (typed / the record's person); the person shows Why (info / marketing) instead of To. Meta "To the record's person". Catalogue `email.recipient`, `email.purpose`; AI hints in flow_draft.php; API nodes text (api.php).
- Tests: `tests/workspace_flow_mail_person_test.php`.
