---
name: connections-mailboxes-accounts
description: Accounts that read mailboxes (CX11e): Connect an account for mailboxes on Google's and Microsoft's pages, the return to Site Settings, the card without a Connect button
module: connections_screen
---

## Where is an account for mailboxes connected?
- Architect (2026-11-20, agent channel message 10563): on Google's and Microsoft's pages of Connections, option 2 of #10720. Step CX11e of `connections-one-place.md`.
- In "Status", a "Mailboxes" part (`#connections-mail`, `connections_mail_part()` in `connections.php`): how many of the site's accounts serve `mail_read`, "Connect an account for mailboxes" (connect.php, purpose `mail_read`) and a link to Site Settings › Mailboxes.
- Gate: card `mailboxes` of `pg_connections_screen_page_cards()`: a manager (connect.php's right for the site's accounts) who may open the Mailboxes category (`connections_mailboxes_may_manage()`, `pg_settings_may_open('mailboxes')`). Nobody gains or loses a right.

## Where does the connection return to?
- `ws_mail_connections_url()` leads with `from=email/pgset-ws-mail` (`connections-return-to-card.md`, #11862; `mail_return=` is still read from an old link). The part's Connect returns to the page, which goes on to the card once the account is connected; saving the application alone stays on the page, as the account is still to connect.
- The button's `data-return` fills the Connect form's `return`; other Connect buttons use the form's own `data-return`. connect.php checks the address again. Mailboxes shows connect.php's message (`pg_connect_flash_take()`), put back by the page on the way.

## What do the Mailboxes card and a box's form show now?
- No Connect button and no hidden Connect form. `ws_mail_connection_html()`: "Microsoft (Google) account for mailboxes", its state, "Manage in Connections" / "Connect in Connections" to `ws_mail_connections_url()`, and "The … application is not set up yet." while it is not.
- State (`ws_mail_connection_state()`, pure, tested; read by `ws_mail_connection_read()`): Connected when an account of the provider serves `mail_read`; "Connected for another purpose" when its accounts hold only other purposes, with "The account is connected for Sending e-mail only; reading mail needs one more sign-in, for Reading mailboxes." and "Sign in for mailboxes in Connections"; else Not connected.
- Why (#10825): Connections' Gmail and Microsoft 365 mail rows read purpose `mail` (sending), so an account connected there showed Connected on Connections and Not connected on Mailboxes. Connecting one purpose never adds another; `mail_read` needs its own consent (`https://mail.google.com/`).
- Connected, on the card only (not in a box's drawer): "Next, add the mailbox with Add a mailbox and choose Mail service: Gmail or Google Workspace." (Microsoft 365 or Outlook.com for Microsoft). A connected account adds no mailbox by itself.
- The card shows both providers (`ws_mail_connections_html()`). A box's form (Edit) shows Google's in its connected-account way and opens the page in a new tab, so a half-filled form stays as typed.
- Since EM5 (#11175) a new box signs in on Add a mailbox's Sign in step while the application is set up (`workspace/mail-add-sheet.md`).
- A user below manager cannot open Connections: they read who connects it, with no link.
- Stays in the box form: server, user, password and the "Connected account" select (which account a box uses is the box's setting), the redirect addresses, the Gmail API line and Google's warning.
- Since EM4 (#11103) the select also lists the site's sending accounts, each with "Allow reading mail", which asks the provider again for the same account, one row (`api/mail-one-account.md`);.

## What did not change?
- No registry row: `gmail` and `microsoft_mail` stay on E-mail sending (poll 148); no service was added (`pg_connections_count()`: `api/connections-registry.md`). No data step, table or column.

Tests: `tests/connections_mailboxes_test.php`, `tests/workspace_mail_google_steps_test.php`, `tests/workspace_mail_connection_test.php`.
