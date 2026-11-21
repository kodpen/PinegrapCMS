---
name: privacy-request-form
description: A person's own privacy request (P10, P10b, #10921): privacy_request.php, off for visitors by default, always from My Account; 48-hour proof link; 5 an address an hour; the switch
module: privacy_request_form
---

## Who can send a request?
- `privacy_request.php` (root, URL-called). Signed in (`pg_credit_checkout_user()`, a ghost session counts as a visitor): always, source `account`, open at once, the 30 days from now, the address is the account's (`user.user_email`), never one typed.
- A visitor: only while the Privacy centre's "Public request form" is on; off, the page and its proof links answer 404 (`pg_privacy_request_form_access()`). Source `form`, status `unproven`, `received_at` 0, `due_at` its 7-day sweep moment, as `core/privacy-requests.md` says.
- Fields: kind (the five of `pg_privacy_request_kinds()`), a message (`request_message`, 10,000 characters; `body` is the spam honeypot's name); a visitor also e-mail and name and the site's spam check (`get_captcha_info()`, `validate_captcha_answer()`; staff skip it).
- The answer after a send is one sentence whether the address is known; the page never looks it up itself (`pg_privacy_request_submit()` links contact and user quietly).

## Where is the switch?
- Contacts › Privacy centre › Requests: the inset row "Public request form" above the list, a switch that saves when flipped. Stored as `config_text` `privacy_request_form` = `1`; off deletes the row, so off is the default. One audit line (`privacy_request`, id 0, `public_form` 0/1).
- Who: `pg_privacy_rules_may_change()` (role <= 2), as the retention rules.

## How does the proof link work?
- Mailed to the address typed (plain text, queued, `secret`). Stateless, as `confirm_subscription.php`: `r` (request), `t` (issued), `s` = HMAC-SHA256 of `privacy-request-proof:<id>:<t>:<email>` under `ENCRYPTION_KEY` (`pg_fs_key()`), its own prefix. 48 hours; a `t` more than 5 minutes ahead is invalid.
- GET shows a button; only the POST with the form token proves: `status` open, `received_at` now, `due_at` +30 days, only where the row is still unproven (two presses prove once), one audit `update` line.

## How is it limited?
- `pg_privacy_request_throttled()`: `waf_rate_exceeded()` scope `privacy_request`, 5 an hour per address asked about and 20 per sender network address (`waf_ip_subject()`); counted after the form and spam check pass, before the row. Works with the firewall off; fails open where the store is not loaded. Past it: 429, `Retry-After`, `rate-privacy-request` in the firewall log.
- `privacy_request.php` is in `waf_sensitive_scripts()` and in `pg_page_cache_request_dirties()`'s list of posts that leave stored pages alone.

## Where does My Account link to it?
- Built-in `my_account.php` and `my_account_system.php`: a last part "Your personal data" with "Make a request" (`pg_privacy_request_account_view()`, shown once the table is there).
- A classic custom layout gets `$privacy_request` (shown, url) and prints it only if written to, as `$store_credit`; the Requests view itself does not depend on it (the page is reachable signed in at any time).
- Designer my account widget: the link token `__privacy_request_url` (Account Pages). No section of its own and no starter node yet.

## Tests?
- `tests/privacy_request_form_test.php`: off by default, access (signed in vs visitor, no table), the switch refused below manager, the row of each source, the rate buckets and where the page counts, the proof signature, 48 hours, parse.
- Seen in a sandbox (2026-11-21): the switch, a signed-in send (open, 30 days), visitor sends (unproven) with the sixth refused 429, the proof link's button, proof, "sent already", expired and a wrong signature.

## What else did this step fix?
- The Requests view fataled (`pg_list_table_printed()` undefined): `includes/privacy_centre_requests.php` now loads `includes/fn/list_table.php` itself.

Related: [[privacy-requests]], [[form-submission-limits]]
