---
name: mail-one-account
description: One Google or Microsoft account sends the site's e-mail and reads mailboxes (EM4): "Connected for a mailbox", Allow reading mail, Disconnect naming both uses
module: mail_accounts
---

## What was decided?
- Architect (2026-11-21, agent channel message 11313, plan mail-settings-one C2): one `connect_accounts` row per provider account, with the purposes it was granted. A box's account is offered for sending as it is, no new consent; a sending account chosen for a box asks the wider consent on the same row; removing a box never disconnects an account sending uses; Disconnect says both uses stop.
- Before (#11045 and earlier) System's select listed only purpose `mail`, so a Gmail box's account had to be connected a second time for sending.

## Which accounts may send the site's e-mail?
- A site account (user 0) holding `mail` or `mail_read` (`pg_mail_account_sends()`, `includes/fn/mail_accounts.php`): `mail_read` carries the send permission (Gmail's `https://mail.google.com/`, Microsoft Graph's `Mail.Send`, `workspace/mail-microsoft.md`).
- How e-mail leaves' account selects list `pg_mail_sending_accounts()`; one that only reads is "<address> · Connected for a mailbox" (`pg_mail_account_mark()`). The save (`pg_mail_settings_save()`), the way's check (`mail_sending.save.php`) and `pg_mail_connection()` in `email()` take both.

## How does a sending account read a mailbox?
- A box's "Connected account" select (Reading tab) lists the `mail_read` accounts, then the site's Google and Microsoft accounts that only send, "(sends the site's e-mail)" (`ws_mail_box_accounts()`, `ws_mail_box_account_option()`).
- Chosen, it shows under the select "… asks once more for the same account; it is not connected twice." and "Allow reading mail" (`ws_mail_box_account_ask_html()`, `pg_mail_box_account_asks()`); below manager a line says a manager allows it, as connect.php is the manager's.
- The button submits `pgset_mail_read_<id>`, printed outside the settings form by the Mailboxes tab (`ws_mail_box_account_ask_forms()`): connect.php, purpose `mail_read`, `connection_id` the account. `pg_connect_start()` hints its address and asks the purposes it holds again; `pg_connect_finish()` writes the same row (`api-connected-accounts.md`). It returns to `settings_mailboxes.php#pgset-ws-mail`.
- Saving a box with such an account before that is refused: "… does not read mail yet: press Allow reading mail under it, then save."

## What do Disconnect and Remove mailbox say?
- Disconnect of an account (provider drawer and service page of Connections): `pg_mail_account_disconnect_confirm()` with `pg_mail_account_uses()` (system e-mail, campaigns, the boxes read with it): "The site will no longer use …: system e-mail stops leaving through it; the mailbox Support stops being read until …". No mail use: the sentence it had.
- Remove mailbox deletes no account (`workspace/mail-box-remove.md`); when the box's account sends the site's e-mail its confirm adds "… stays connected: the site's e-mail is still sent through it." (`pg_mail_box_remove_kept()`).

## Which helper draws the Connect buttons?
- `pg_mail_connect_button()` and `pg_mail_connect_form()`: E-mail sending's "Connect a … account" (`pgset_mail_connect_<provider>`, purpose `mail`) and the Mailboxes tab's Allow reading mail.

## What is not changed?
- Connections' Gmail and Microsoft 365 mail rows still read purpose `mail` (`connections-registry-rows.md`); the Mailboxes tab's account rows still read `mail_read` (`ui/connections-mailboxes-accounts.md`). No table or column.

Tests: `tests/mail_accounts_test.php`.
