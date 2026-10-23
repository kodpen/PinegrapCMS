---
name: form-double-opt-in
description: Double opt-in for custom forms that add to a contact group (11.0.27): the switch, the signed stateless link, confirm_subscription.php, consent_log pending/granted
---

## What does the switch do?
- `custom_form_pages.double_opt_in` (step `upgrade_2026_11_0_form_double_opt_in()`, label 11.0.27, default 0). On: a submission keeps the contact but not the form's `contact_group_id`; the contact's own address gets a link; the group is joined only after the button on `confirm_subscription.php` is pressed. Off: unchanged behaviour.
- Not held when the contact is already in the group, the group does not exist, or the registration/membership group is the same group.
- Set in the legacy page form (inside the "Add to Contact Group" row, `includes/forms/page_form.php`) and in the designer form settings (`sd-cf-sb` `data-s="double_opt_in"` under Contact group; `pg_cf_load_page_form_settings()` / `pg_cf_write_form_settings()`).
- Every read and write waits for the column: `pg_fs_ready()` (upgrade bridge: `create_or_update_page_type_record()` writes every key as a column, `custom_form.php` lists columns).

## How is the link made and checked?
- `includes/fn/form_subscription.php`. `pg_fs_token(c, g, t, email, key)` = HMAC-SHA256 of `subscribe-confirm:c:g:t:<normalised email>` under `ENCRYPTION_KEY`. Query `c`, `g`, `t`, `s`; nothing stored. TTL 7 days (`pg_fs_ttl()`), 5 minutes of clock skew.
- `pg_fs_parse()` takes only plain positive integers that fit the PHP int and 64 lower-case hex. `pg_fs_link_state()`: invalid, member, expired, confirm (member before expired: a second click on an old link says "already subscribed").
- A changed contact address or key invalidates open links. No key or no contact address: the group stays out and `log_activity()` says why (refuse rather than add without confirmation).

## Why GET shows a button and POST confirms?
- Mail scanners and link previews open links. GET never writes; POST checks `validate_token_field()` and the signature again. Headers: `Cache-Control: no-store, private`, `X-Robots-Tag: noindex, nofollow`, `Referrer-Policy: no-referrer`. Frame: `output_header_secure()`; a designed page (system widget) is a later step.

## What does confirming write?
- `pg_fs_confirm()`: `contacts_contact_groups_xref` row, `opt_in` row = 1, `contacts.opt_in = 1` (a subscription group mails only contacts opted in to both, `add_email_campaign.php`), `consent_log` granted, `log_activity()`.

## What goes into consent_log?
- channel `email`, purpose `group:<id>` (`pg_consent_log_purpose_valid()`), source `form`. `pending` when a confirmation mail is sent, `granted` on confirmation; ip and user agent from the request.
- Mail at most once a day per address and group (`pg_fs_resend_allowed()` on the newest pending row by person token); later submissions inside the day send nothing.
- Pending is never consent: the privacy export prints it as "Waiting for confirmation".

## What is left for other steps?
- `pg_fs_pending_url($form_id)` holds this request's link for `^^confirm_subscription_url^^` (step b). When that variable is in the submitter mail, the built-in mail may be skipped there.
- Per-recipient throttling of the mail is step a.
- "Alternative confirmation page if contact group" checks membership right after submission, so a held group does not match.

Related: docs/memory/core/consent-log-and-audit-trail.md, docs/memory/plans/forms-subscription-double-opt-in.md
