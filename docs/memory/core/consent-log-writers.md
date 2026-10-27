---
name: consent-log-writers
description: Who writes consent_log rows: own e-mail marketing answers (sign-up, preferences, one-click, checkout, custom form; PR #1581), accepted checkout documents as contract rows (PR #2000)
---

## What is recorded?
- A person's own answer to a marketing question they were shown (PR #1581). Staff, API, import, Workspace, job and marketplace writers add none. Channel `email` only (no SMS consent field exists): purpose `marketing` for `contacts.opt_in`, `group:<id>` for a list's `opt_in` row. The opt-in writes themselves are unchanged.

## When is a row written?
- `pg_consent_log_answers()` (`includes/fn/privacy.php`) reads the person's rows once and writes only the answers that differ from the last recorded one (`pg_consent_log_changes()`; none or `pending` counts as no answer). A re-sent form, a second one-click POST or a save that only changed the address writes nothing. The baseline is the log, never `contacts.opt_in`.
- Person: `email` after the save (the token), `user_id`, `contact_id`; activation and the preferences form add `previous_email`, the address before the save, read with them but never stored. ip and user agent are the request's.

## Which writers?
- `signup`: `pg_member_register()`, `pg_member_activate()` (`auth.php`) when `$opts['opt_in_asked']`. The entrance scripts set it for every post without `pg_widget_id` (the legacy screen, whatever its layout draws: see the gaps file), for a widget post only when `pg_member_widget_asks_opt_in()` finds a non-hidden control named `opt_in` in that widget's tree of that type.
- `email_preferences.php` form: `account` when signed in, else `unsubscribe` (signed link). `marketing` when `opt_in` was drawn (`pg_sw_posted_control()`); a list per drawn box, none when the global answer is no (hidden boxes still post 1), only open lists when the post created the contact.
- One-click POST: `marketing` refused, `unsubscribe`, with the account using the address and its contact, else the first contact with it.
- `checkout`: `pg_consent_log_checkout()` right after the `UPDATE contacts` that writes `opt_in` (billing_information.php, express_order.php's update-cart post, submit_order()'s express block), with that value, so a submit later refused or never paid keeps its row. Only for `opt_in_displayed === 'true'`, never while ghosting; an address `validate_email_address()` refuses stays out. A signed-in buyer whose card is not updated (`update_contact` off or not posted, as on a designed express order without an `update_contact` control, the default tree included) gets none. A designed express order posts `opt_in_displayed=true` only when `_eo_tree_asks_opt_in()` finds the box after the visibility and section bindings.
- `custom_form.php`, `form`: a choice field (`pg_cf_type_has_options()`) bound to `opt_in`, not office use only, not hidden by `?show_<id>=false` in `send_to`, not a contact filled in for somebody else; answer = the stored `contacts.opt_in`.

## Who writes contract rows?
- `pg_legal_documents_store()` (`includes/fn/legal_documents.php`, PR #2000), after the order's `order_documents` rows: one `granted` row per type both stored and accepted (`preinfo`, `contract`), source `checkout`, purpose = the type. Person from the order (`billing_email_address` as the token, `user_id`, `contact_id`); ip, user agent and `created_at` are the documents' own (`accepted_at`). Built by `pg_legal_documents_consent_events()` (tested in `tests/legal_documents_test.php`).
- An unaccepted type writes nothing (no `refused` row: not ticking a box is no answer), nor does the repeat call the store ignores. `kvkk` waits on the KVKK checkbox (`plans/checkout-legal-documents.md`).

The cookie notice's answers (channel `cookie`, source `banner`): `consent-log-cookie-notice.md`.

Gaps, tests, open questions: docs/memory/core/consent-log-writer-gaps.md. Related: docs/memory/core/consent-log-and-audit-trail.md, docs/memory/plans/sms-and-iys.md
