---
name: mail-google
description: A Gmail or Google Workspace mailbox (MG1): a connected Google account (mail_read, XOAUTH2, Gmail API answers) or an app password, the form's Gmail part, Google pages checked
---

## What was decided?
- Owner (2026-11-19): a Gmail mailbox is read with a connected Google account; decided in channel poll 155 ("Evet, eklensin"), architect message 9698. The app-password path stays as the second way; a box working today keeps working unchanged. No table or column: `settings.connection_id` already existed.

## What signs in?
- Purpose `includes/connect/purposes/mail_read.php`: `scopes` and `imap_scopes` for `google` are `https://mail.google.com/`, the one scope Gmail's IMAP takes with XOAUTH2; it also covers the Gmail API's send call.
- The rest is the Microsoft path (`mail-microsoft.md`): `ws_mail_box_credentials()` and `ws_mail_box_login()` (XOAUTH2 to imap.gmail.com:993); answers with `reply_via = 'connection'` go through `email()`'s `connection` property and `pg_mail_connection_deliver()`, purpose `mail`'s `send.google` (Gmail API upload, 35 MB). No sent copy is added: Gmail files what it sends.
- Registry (`connections_registry.php`): the `gmail` entry covers purposes `mail` and `mail_read`.

## What does the box form show with Gmail?
- `ws_mail_google_steps()` between Mail service and User name, while Gmail is chosen: "Sign in with" (`google_way`, not stored) with "A connected Google account" first and "An app password". `ws_mail_google_way()` opens a box with an account or no password (a new one) on the account, a box keeping a password on the app password.
- The account way: four steps, the "Connect a Google account for mailboxes" button (`ws_mail_connect_form(..., 'google')`, hidden form `pgset_mail_connect_google` in `includes/settings/mailboxes.php`), and "Setting up the Google application": the two redirect addresses (`pg_signin_redirect_uri('google')`, `pg_connect_redirect_uri()`) each with Copy, the Gmail API's enable link, Google's verification warning.
- The button rule is Microsoft's (architect message 9695): while the Google application is not set up, no button, the reason and a link to `connections.php#connections-google`; a refusal from connect.php is shown on Mailboxes (`pg_connect_flash_take()`). A user below manager is told who connects it.
- The app password way: six steps (2-Step Verification, App passwords, full address as user, Connected account None, Test connection) and three notes.

## Which Google pages were checked (2026-11-19)?
- developers.google.com/workspace/gmail/imap/xoauth2-protocol: the IMAP/SMTP scope is `https://mail.google.com/` (no Gmail API needed; only sending uses it).
- support.google.com/cloud/answer/15549945: Testing limits use to the listed test users; a warning is shown before a test user grants the scopes; a test user's authorisation ends seven days after consent; Internal limits it to the organisation's members.
- developers.google.com/identity/protocols/oauth2/production-readiness/restricted-scope-verification: no verification for Internal or Testing; restricted scopes with server access need a security assessment.
- support.google.com/accounts/answer/185833, mail/answer/7126229: app passwords; IMAP always on since January 2025.
- Not written: the "Advanced" › "Go to … (unsafe)" button names; no Google page found states them, so the form says the test user passes the warning.

## What stays unverified?
- No Google account in the cloud session: the real consent, the XOAUTH2 exchange with imap.gmail.com and sending through the Gmail API were never run; the owner tries them on dev.

## Tests?
- `tests/workspace_mail_google_steps_test.php` (ways, button, addresses, warning), `tests/workspace_mail_connection_test.php` (purpose scopes, registry covers).

Related: docs/memory/workspace/mail-microsoft.md, docs/memory/ui/look-settings-mailboxes.md
