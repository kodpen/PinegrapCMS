---
name: account-email-pages
description: Designable welcome and password reset e-mails: Account E-mail widget, *_email_page_id settings, resolver, stored recipient, 'secret' mail (#1192).
---

## Which account e-mails can be designed, and where is the page chosen?
- Welcome (new): `registration` / `membership` cfg `welcome_email_page_id` + `welcome_email_subject`. Reset: `forgot_password` cfg `reset_email_page_id` + `reset_email_subject`. Pickers `_swAccountEmailRow()`.
- Senders `pg_account_email_send_welcome()` / `_send_reset()` (`widgets_account.php`) from `registration_entrance.php`, `membership_entrance.php`, `signin.php` (social sign-up), `forgot_password.php`. Queued, HTML, `pg_tr_email()`; `pg_account_email_subject()` strips `^^tokens^^` and line breaks.
- No site setting (needs a schema step); `{{tab:}}` + the `_email_page_id` suffix give the installer's "E-postalar" row. Built-in templates ship `email_account_welcome` / `email_password_reset` (reset href bound).

## Which widget answers when the form posts none?
- `pg_account_email_setting()`: the posted `pg_widget_id` (only when `return_to` validated; type-checked) decides, even with no page. Else the first widget of that type on a non-draft, non-binned page that names a page (`pg_sw_widget_pages()` with that test: a site naming none pays one query). A widget only inside a shared component is not found.
- The page must exist, not binned or a draft (`pg_account_email_page_usable()`). Welcome: else none (chosen page gone = activity line). Reset: built-in text.

## What does the Account E-mail widget draw?
- Tokens `__site_name`, `__user_name`, `__user_first_name`, `__user_email`, `__username`, `__login_url`, `__my_account_url`, `__set_password_url` (reset); flags `is_welcome`, `is_password_reset`.
- Filled only when `pg_sw_render_context()` has `email` and `account_email` (`kind`, `user_id`, `set_password_url`); elsewhere tokens are '' and bound links drop; editor preview: samples. Links on `HOSTNAME_SETTING`.
- Name in an element of its own: `pg_tr_render_db_html()` matches whole texts. Inline styles (clients drop stylesheets); colour fallback inside `var(--x,#hex)`: a client reading var() but blocking the CSS link drops a separate fallback.

## When is the designed reset e-mail used?
- Only when the body (after `pg_tr_email()`) contains `k=<token>` (`pg_account_email_has_link()`); else activity line + built-in text (unchanged). Visitors kept to the panel get the built-in.

## Why does the reset e-mail go to the stored address?
- `user_email`'s collation folds accents: `jane@exámple.com` found `jane@example.com`'s account; the link went to the look-alike. Now `pg_password_reset_address_matches()` (case only) else the unknown-address branch (same notice, no token, no mail); both sends use the stored address.
- Rule `pg_email_address_matches()` (`auth.php`; the reset name wraps it); social sign-in uses it too ([[social-sign-in-address-match]]).

## Does the designed reset e-mail tell which addresses have accounts?
- No added timing gap: `forgot_password.php` resolves the setting before the lookup; the unknown answer draws the page unsent (`pg_account_email_reset_unsent()`); the widget looks up its links even with no member (most of the render). Sandbox: known 49-52 ms / unknown 44-46 (before: 50-63 / 15-17); ~5 ms is the token write and send, as on the legacy screen.

## How is the reset link kept out of logs?
- `'secret' => true` on both reset sends: `pg_mail_failure_body_shown()` keeps the body out of the failure log and notice.
- Residual: `mail_outbox.properties` keeps the body 7/30 days (follow-up). A future page cache must skip `$email = true` renders.

## What exposure is accepted?
- The welcome goes to an unverified address with names the visitor typed (like a form's confirmation copy); one account per address and the WAF limit it. Social sign-up has no `pg_lang`: source language. Default subject is `lang()`: templates set `*_subject`.

Related: docs/memory/designer/member-widgets-handlers-and-forms.md, docs/memory/platform/hostname-in-mail-links.md
