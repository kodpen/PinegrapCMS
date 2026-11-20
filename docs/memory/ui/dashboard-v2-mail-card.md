---
name: dashboard-v2-mail-card
description: New dashboard Incoming e-mail card (cards/mail.php): who is offered it, the unread head and tab link, the eight newest Incoming conversations as the screen lists them, refresh
---

## What did the owner ask?
- Owner (2026-11-19): a card of the inbox "yetki dahilinde", the message titles enough, a click leading to the inbox, matching the card looks (`workflow/decisions-made-product-78.md`).
- Architect (2026-11-19): on the new dashboard only, since the old one stays as it is (owner 2026-11-01, `dashboard-v2-overview.md`); eight rows, the reading of "message titles enough".
- Built in #10429. The same request pinned the tab to a person's menu (`main-menu-mail-row.md`, #10408) and put the channel in the open mail's head (`workspace/mail-pane-channel-pill.md`, #10435).

## Where is it?
- Card id `mail`, title "Incoming e-mail", `bi-envelope` (the tab's icon), category General after My notes, width S, no period. Gallery line "The newest incoming e-mail, unread ones in bold, one click from the inbox", sketch `rows`, marked NEW. In no preset.
- `includes/dashboard/v2/cards/mail.php` (`pg_dashboard_v2_card_mail()`, `pg_dashboard_v2_mail_rows()`), renderer `assets/js/pg_dash_cards_mail.js` (last in the `pg_dash.min.js` bundle), styles "Incoming e-mail card" in `assets/css/pg_dash.src.css`, only `--pgd-*` tokens, so the four card faces and the backdrops draw it.

## Who is offered it?
- Gate `mail`, `pg_dashboard_v2_mail_gate()` in `cards.php`: whoever may open the tab, `pg_messages_mail_visible()` (mail on, a member, a mailbox writing where they read: `ws_mail_list_visible()`), while `ws_mail_reads_ready()`, without which the screen refuses. The gallery offers only cards whose gate is open, and the loader answers `denied` otherwise, so the card leaves the grid.

## What does it show?
- The head: the unread count of the menu's Communication row (`pg_messages_mail_unread()`), "3 unread" lit with the New badge's dot, or "Nothing unread"; at the other end "Open Incoming e-mail" to `messages_mail.php`.
- Then the newest eight (`PG_DASHBOARD_V2_MAIL_SHOWN`) conversations from `ws_mail_list()` with `ws_mail_list_filters(array())`, the Incoming view as the tab opens (open conversations of every box and kind), so the card never shows what the screen hides. `ws_mail_list()` takes a page size (`$page`, at most `WS_MAIL_LIST_PAGE`): same query and order, fewer rows.
- A row: the sender and the time, the subject under it, named by `ws_mail_list_row_names()`, which the screen's rows use too (customer name, else address, else number; "(no subject)"; a WhatsApp or SMS row shows its kind instead). Bold while unread, with a hidden "Unread". The row opens `messages_mail.php?conversation=<id>` (`ws_mail_list_page_url()`), where the pane marks it read.
- No conversation: the head stays and "No e-mail waiting" stands under it.

## How is mail text kept safe?
- The card answers plain text in JSON, never HTML (`dashboard-v2-storage.md`); the renderer writes the sender and subject with `textContent` only. The test refuses `innerHTML` in the script.

## When is it asked again?
- In the refresh list of `pg_dashboard_v2_card_timing()`, at the session's refresh time while the tab is visible. Reading marks nothing read.

## Tests?
- `tests/dashboard_v2_mail_card_test.php` (list entry, gate, row names, rows, wiring), the ids in `tests/dashboard_v2_cards_test.php`. Not run against a database or in a browser in the PR.

Related: docs/memory/ui/dashboard-v2-notes-card.md, docs/memory/workspace/mail-list.md
