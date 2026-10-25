---
name: consent-log-writers
description: Which screens write a person's own e-mail marketing answer to consent_log (sign-up, preferences, one-click, checkout, custom form), when a row is written, what stays unrecorded (PR #1581)
---

## What is recorded?
- A person's own answer to a marketing question they were shown (PR #1581). Staff, API, import, Workspace, job and marketplace writers add none. Channel `email` only (no SMS consent field exists): purpose `marketing` for `contacts.opt_in`, `group:<id>` for a list's `opt_in` row. The opt-in writes themselves are unchanged.

## When is a row written?
- `pg_consent_log_answers()` (`includes/fn/privacy.php`) reads the person's rows once and writes only the answers that differ from the last recorded one (`pg_consent_log_changes()`; none or `pending` counts as no answer). A re-sent form, a second one-click POST or a save that only changed the address writes nothing. The baseline is the log, never `contacts.opt_in`.
- Person: `email` after the save (the token), `user_id`, `contact_id`; activation and the preferences form add `previous_email`, the address before the save, read with them but never stored. ip and user agent are the request's.

## Which writers?
- `signup`: `pg_member_register()`, `pg_member_activate()` (`auth.php`) when `$opts['opt_in_asked']`. The entrance scripts set it: legacy screen always, a `pg_widget_id` post only when `pg_member_widget_asks_opt_in()` finds a non-hidden control named `opt_in` in that widget's tree of that type.
- `email_preferences.php` form: `account` when signed in, else `unsubscribe` (signed link). `marketing` when `opt_in` was drawn (`pg_sw_posted_control()`); a list per drawn box, none when the global answer is no (hidden boxes still post 1), only open lists when the post created the contact.
- One-click POST: `marketing` refused, `unsubscribe`, with the account using the address and its contact, else the first contact with it.
- `checkout`: billing_information.php, express_order.php and submit_order.php keep `$_SESSION['ecommerce']['opt_in_asked']` (order id, `opt_in_displayed === 'true'`). submit_order() records once, after `pg_legal_documents_store()`, when the flag names the order and says asked, from the `orders` row. The designed express order posts `opt_in_displayed=true` only when `_eo_tree_asks_opt_in()` finds the box after the visibility and section bindings.
- `custom_form.php`, `form`: a choice field (`pg_cf_type_has_options()`) bound to `opt_in`, not office use only, not hidden by `?show_<id>=false` in `send_to`, not a contact filled in for somebody else; answer = the stored `contacts.opt_in`.

## What writes no row?
- Question not shown: social sign-up (0), contacts created with DEFAULT 1 (forms, profile, `pg_contact_quick_save()`, affiliate), retrieve-order resets, a checkout that hid the box, a widget without the control. POS sales are staff writes.

## Was it run?
- Tests: `tests/privacy_test.php`, `member_opt_in_test.php`, `express_order_opt_in_test.php`.
- Sandbox (MariaDB, legacy screens, hand-built widget posts): all 29 cases passed: each writer's legacy path, repeated answers, rejected checkout submits, the no-row paths.
- One-click (sandbox): the refusal carries the account and its contact, or the contact with user 0; a repeated POST adds none; a later unticked address change, signed in or at membership activation, adds none; re-ticking adds one `granted` `account` row.
- Not run: social sign-up, rendered designed widgets, gateway returns, order_history_retrieve_order.php, profile, quick save, affiliate, POS, double opt-in. The legacy screen's first save writes a refused row per open list shown.

## Open for the owner?
- Boxes start ticked (registration, membership, widgets; checkout from `orders.opt_in` DEFAULT 1): untick?
- A designed express order without the box writes `opt_in = 0` for every buyer; social sign-up writes 0 unasked.
- DEFAULT-1 contacts have no row: step 5 must not push them to İYS as consent.

Related: docs/memory/core/consent-log-and-audit-trail.md, docs/memory/plans/sms-and-iys.md
