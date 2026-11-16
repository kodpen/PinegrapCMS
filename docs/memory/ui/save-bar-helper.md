---
name: save-bar-helper
description: pg_save_bar() (SH6a): the framed bar's markup, options, .pg-savebar frame, screens that took it; the buttons' order ghost, quiet, filled and the hidden Return button (SH6b)
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
- SH6a (#9746): Add Submitted Form, Edit Purchase Order, Edit Quote, account sync item, ERP accounts import (three steps), expense categories, e-invoice inbox document, stock counts, import, Import My Site, Import Short Links, import ZIP, migration, Send E-mail Campaign (both windows and the refusal), Menu Items' reorder bar, WhatsApp template.
- The account sync item and inbox document bars moved from inside the form column to the row's `order-3` column.
- The bars in `includes/forms/*.php` already draw the same framed column by hand; they are not moved yet.

## What stays out?
- `includes/templates/edit_offer.php`: an owner's kept screen (`decisions-made-panel-3.md`).
- `includes/settings/screen.php`: the settings page frames its own bar (`#pg_settings_page_form > nav`).

## In which order are the buttons? (SH6b)
- Ghost, quiet, filled, in the markup, never by CSS order: the danger delete first, the save last. The roles are the stylesheet's (`look-edit-screens.md`, "What is the save bar?"). `tests/save_bar_order_test.php` checks every inline bar and the screens that hand their buttons to a form layout.
- Left in the old order: `product_builder.php`, `edit_offer` (kept screens), `edit_comment.php` and `edit_erp_invoice.php` (an open step changed them at the time), and Edit Product Group (`product_group_form.php`).

## What keeps Return on Save?
- Return presses the form's first submit button, so a delete first would be pressed. A reordered bar starts with `pg_save_bar_return_button()`: a nameless, visually hidden submit (`form=` when the bar sits outside its form). `backend.src.js` hands its press to the bar's `[data-pg-save-shortcut]` button, else its first filled button, else `pgSaveShortcutButton()`; that button's handlers and question run.
- A bar whose buttons each sit on a form of their own (account sync item, inbox document), or whose form has no field (delivery note), needs none.
- Add Expense's Save carries `data-pg-save-shortcut`: "Save and add another" (same `submit_save` name) now comes first and Ctrl+S would take it.


- The phone bar: `save-bar-phone.md`.

Related: docs/memory/ui/look-edit-screens.md, docs/memory/ui/save-bar-phone.md, docs/memory/ui/save-bar-chat-launcher.md
