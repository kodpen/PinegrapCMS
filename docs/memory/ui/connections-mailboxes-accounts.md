---
name: connections-mailboxes-accounts
description: Accounts that read mailboxes (CX11e): Connect an account for mailboxes on Google's and Microsoft's pages, the return to Site Settings, the card without a Connect button
module: connections_screen
---

## Where is an account for mailboxes connected?
- Architect (2026-11-20, agent channel message 10563): on Google's and Microsoft's pages of Connections, option 2 of #10720. Step CX11e of `plans/connections-merges-build.md`.
- In "Status", a "Mailboxes" part (`#connections-mail`, `connections_mail_part()` in `connections.php`): how many of the site's accounts serve `mail_read`, "Connect an account for mailboxes" (connect.php, purpose `mail_read`) and a link to Site Settings › Mailboxes.
- Gate: card `mailboxes` of `pg_connections_screen_page_cards()`: a manager (connect.php's right for the site's accounts) who may open the Mailboxes category (`connections_mailboxes_may_manage()`, `pg_settings_may_open('mailboxes')`). Nobody gains or loses a right.

## Where does the connection return to?
- The page reads `mail_return`; `pg_connections_screen_mail_return()` (pure, tested) takes only a `settings_<key>.php` address with an optional anchor that `pg_connect_return_path()` and `pg_safe_redirect_path()` both keep unchanged, else the provider's page.
- The button's `data-return` fills the Connect form's `return`; other Connect buttons use the form's own `data-return`. connect.php checks the address again. Mailboxes shows connect.php's message (`pg_connect_flash_take()`).

## What do the Mailboxes card and a box's form show now?
- No Connect button and no hidden Connect form. `ws_mail_connection_html()`: "Microsoft (Google) account for mailboxes", Connected / Not connected (`ws_mail_connection_count()`: accounts serving `mail_read`), "Manage in Connections" / "Connect in Connections" to `ws_mail_connections_url()`, and "The … application is not set up yet." while it is not.
- The card shows both providers (`ws_mail_connections_html()`). A Gmail box's form shows Google's in its connected-account way and opens the page in a new tab, so a half-filled form being added stays as typed.
- A user below manager cannot open Connections: they read who connects it, with no link.
- Stays in the box form: server, user, password and the "Connected account" select (which account a box uses is the box's setting), the redirect addresses, the Gmail API line and Google's warning.

## What did not change?
- No registry row: `gmail` and `microsoft_mail` stay on E-mail sending (poll 148); `pg_connections_count()` stays 78. No data step, table or column.

Tests: `tests/connections_mailboxes_test.php`, `tests/workspace_mail_google_steps_test.php`, `tests/workspace_mail_connection_test.php`.
