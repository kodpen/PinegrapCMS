---
name: main-menu-mail-row
description: Incoming e-mail pinned to a person's own main menu (IM1, #10408): the tab's Pin to main menu, ws_profiles.mail_pinned, row 27 under the Workspace, its gate, badge and icon
---

## What did the owner ask?
- Owner (2026-11-19, poll 154): each person pins Incoming e-mail to their own main menu, as My Notes ("Her kişi kendisi sabitler (Notlarım gibi)"). Not one site-wide switch. Records: `workflow/decisions-made-product-78.md`, the column `workflow/decisions-made-schema-28.md`.

## Where is the pin kept?
- `ws_profiles.mail_pinned` TINYINT(1) NOT NULL DEFAULT 0, as `notes_pinned`; step 11.0.239 of 2026.11.0 (`upgrade_2026_11_0_ws_mail_menu_pin()`, `install_add_column()`, skipped without `ws_profiles`). 0 is not pinned, so no menu changes until a person pins it.
- `includes/workspace/mail_menu.php`: `ws_mail_menu_ready()` probes the column (`pg_schema_has()`), `ws_mail_menu_pinned($user_id)` reads it, `ws_mail_menu_pin($viewer, $pinned)` writes it insert-or-update (a person with no profile row gets one).

## Where is the button?
- "Pin to main menu" on the Incoming e-mail toolbar (`messages_mail.php`), after My e-mail settings: `ws_mail_menu_pin_html($pinned)`, look A's `pg-button pg-button-quiet pg-button-sm`, `bi-pin-angle`. Pinned it reads "Remove from main menu" with the filled pin. The label says what a click does, so no `aria-pressed`; `data-ws-mail-menu-pin` holds the state. Not drawn before the column is there (null).
- `ws_mail_list_filters_html()` takes it as its last parameter, `$pin`; the screen passes `ws_mail_menu_pinned()` once the column is there.

## What does a click do?
- `menuPin()` in `workspace_mail.js` sends `ws_mail_menu_pin` (`pinned` 1 or 0), a `pg_panel_actions()` row and one of `ws_mail_actions_without_workspace()`, so it answers with the Workspace off while mail is on (`workspace/mail-workspace-off.md`).
- The action refuses whoever `ws_mail_list_visible()` refuses, so nobody keeps a pin for a tab they may not open. It answers with the row's drawing (`pg_menu_icon('mail', …)`) and its unread pill (`pg_menu_count_badge()`).
- `menuRow()` adds `#menu_item_27` after the last of My Notes, the Workspace, Automation flows or the Dashboard there, lit with `aria-current="page"` (this page is the tab), and takes `active` from Communication's row; unpinning puts it back. A toast says which.

## How is the row drawn?
- `output_menu()`, in Communication's block: slot 27, the next free one, never reused. Built only when the `mail` tab is among the person's (`pg_messages_tabs_for_user()`, i.e. `pg_messages_mail_visible()`) and they pinned it. `href` `messages_mail.php`, title "Incoming e-mail", `campaigns-color`; one screen, no panel.
- Its badge is Communication's unread count, counted once (`pg_messages_mail_unread()`); both rows carry it.
- In the untitled group after the Workspace and My Notes: `pg_menu_categories()` `''` is `0, 26, 23, 24, 27`; with the Workspace off it follows the Dashboard and Automation flows.
- `messages_mail.php` lights row 27 when it is built, row 9 otherwise. Communication keeps its row, its panel entry and the tab.

## What is its icon?
- `pg_menu_icon('mail')`: an envelope with a letter coming out of it; the letter in the second colour (`--pg-mi-sky`), the pocket's front opaque. On hover the letter comes up 3px (`.pg-mi-sheet`, in the reduced-motion block of `backend.src.css`).

## Tests?
- `tests/mail_menu_pin_test.php`; the untitled slots also in `tests/automation_flows_menu_test.php`. The step was not run on a database in the PR; it is idempotent by `install_add_column()`.

Related: docs/memory/ui/messages-area-mail-tab.md, docs/memory/ui/main-menu-categories.md, docs/memory/ui/main-menu-panels.md
