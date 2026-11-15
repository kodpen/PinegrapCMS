---
name: save-bar-helper
description: pg_save_bar() (includes/fn/save_bar.php, SH6a): the framed save bar's markup, its options, the .pg-savebar frame on any screen, which screens took it, what is left to SH6b and SH6c
module: save_bar
---

## What does pg_save_bar() draw?
- `pg_save_bar($buttons, $opts)` returns `div.col-12.order-3.position-sticky.pg-savebar` (bottom 0, z-index 3) around `nav.buttons.navigation.text-center.mb-7` > `.container` > the buttons' box. The column sticks, not the bar; in a `.row` it sits under the form and status columns.
- Options: `label` (translated accessible name, default "data edit buttons"), `id` and `hidden` (inline `display:none`, for a script that calls jQuery `.show()`: Menu Items' reorder bar), `group` (the buttons' box classes, default `btn-group flex-wrap justify-content-center`, `''` none), `after` (a live status line after the buttons).
- The buttons are printed as given: their order and classes are the screen's.

## Why a class and not only the edit width's selector?
- Look A's frame (`.pg-floatbar` surface, 56 px bar from 576 px, buttons by role, chat clearance) was scoped to `body.pg-measure-edit .row > .order-3 > nav`. Imports, tools and lists have no `pg-measure-edit` and some bars sit outside a `.row`, so each of those rules also names `.pg-savebar > nav.buttons.navigation`.
- A bar left as a bare `nav … position-sticky` with `bottom:.5rem` was the old centred pill with no frame; `tests/save_bar_helper_test.php` refuses one in any root screen or `includes/*/` file.

## Which screens take it?
- SH6a (#PR): Add Submitted Form, Edit Purchase Order, Edit Quote, account sync item, ERP accounts import (three steps), expense categories, e-invoice inbox document, stock counts, import, Import My Site, Import Short Links, import ZIP, migration, Send E-mail Campaign (both windows and the refusal), Menu Items' reorder bar, WhatsApp template.
- The account sync item and inbox document bars moved from inside the form column to the row's `order-3` column.
- The bars in `includes/forms/*.php` already draw the same framed column by hand; they are not moved yet.

## What stays out?
- `includes/templates/edit_offer.php`: an owner's kept screen (`decisions-made-panel-3.md`).
- `includes/settings/screen.php`: the settings page frames its own bar (`#pg_settings_page_form > nav`).
- The buttons' order (ghost, quiet, filled) in the markup is SH6b, the phone bar SH6c (`plans/panel-old-screens-shared-steps.md`).

Related: docs/memory/ui/look-edit-screens.md, docs/memory/ui/save-bar-chat-launcher.md
