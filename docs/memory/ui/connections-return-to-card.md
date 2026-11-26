---
name: connections-return-to-card
description: How a Connections service page returns to the Site Settings card that led there (from=): the lead helper, the key check, Back to {card}, the return after Save and Connect
module: connections_screen
---

## What is it?
- Step TR3a of `plans/settings-tidy-round.md` (#11862), the rule's return part (`plans/settings-crossings-rule.md`; owner poll 232, `workflow/decisions-made-product-114.md`): a lead carries its card, the page shows the way back and returns by itself once the service is done.

## How does a lead name its card?
- `pg_connections_lead_url($service, $category, $card, $anchor, $drawer)`: `connections.php?service=<key>&from=<category>%2F<card>[%2F<drawer>]#<anchor>`, registry keys, never an address. Every lead from a card uses it, in the same tab.
- Mailboxes leads with category `email` (Mailboxes is a tab of E-mail, `pg_settings_moved_categories()`).
- `pg_connections_back($asked, $categories)` (pure, tested) checks it against `pg_settings_categories_for($user)`: the card must be in the category's `sections`; anything else is dropped and the page behaves as from the list. Nobody gains a way to a category they may not open.

## What does the page do with it?
- "Back to {card}" (`lang('Back to {var:1}')`, the card's title) at the right of the breadcrumb strip: `pg_page_shell()`'s `back` key (`output_header()`), its link `pg_settings_link()`, so a click opens the dialog on the card.
- Its script adds a hidden `connections_from` to every POST form of the page; each write's redirect goes through `connections_after_write()` → `pg_connections_back_after_write()`: the page with `from=` kept, plus `saved=1` when no error was added and the action removes nothing (`pg_connections_back_removes()`: remove, disconnect, delete). Disconnect, Remove and a refused Save stay on the page with the way back.
- Connect: the page's connect return (`$connect_return`, the Mailboxes and Backups parts too) is `pg_connections_back_page_url()`, so connect.php keeps the card in the flow's own return. An MCP connector's sign-in returns there too (`ws_mcp_settings_place()`).

## When does it return by itself?
- `pg_connections_back_due($back, $done, $state)`: a Connect that went through (connect.php's notice, or `mcp_connected=` without an error; not while Paraşüt's company read warned), or `saved=1` with the row's state `connected`.
- A provider's page (Google, Microsoft) counts as ready when its application is ready, but for a Mailboxes lead: there only a Connect returns, as the account is connected on that page.
- It goes to `pg_settings_link($category, $card)`, the card's own screen; Backups to `settings_maintenance.php?backup_connect=<type>#pgset-backup` (`pg_connections_back_destination()`), so the card shows the destination chosen.
- The line: `$_SESSION['software']['settings_returned']` (category, text), read once by `includes/settings/screen.php` after the cards: connect.php's message when no card took it (Mailboxes and Backups take it), else "{var:1} is connected." or "The {var:1} application is set up.".

## What happened to backup_return and mail_return?
- Gone from the leads; `pg_connections_back_legacy()` reads them from an old link as `from=`. `pg_connections_screen_mail_return()` was removed.

## Which leads changed place or tab?
- E-mail sending's "Set up the application on Connections first." opens Google's or Microsoft's page (`#connections-details`), not the list.
- Backups' "Connected accounts" link became "Set up the {var:1} application": the drive's own page, which asks for the application (`connections-page-finishes.md`); Dropbox, with no page, its drawer on the list.
- MCP servers' Manage in Connections opens in the same tab; with no connector picked, Open in Connections still opens the list's MCP group.
- A box's set-up drawer on Mailboxes and Claude's set-up step 4 open the page in the same tab too (TR3b); what the pane held and the drawers open come back: `connections-lead-keeps-pane.md`.

Tests: `tests/connections_back_test.php`.
