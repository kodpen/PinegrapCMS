---
name: bell-titles
description: A bell row's short title says what happened and with what ("New order #SP-1044"), the brief text under it; the push banner keeps the sentence (#8897)
---

## What does a bell title say?
- What happened and with what, in a few words, no greeting, no label with a colon (look A, `plans/panel-design-language-look.md`). `pg_notification_display()` sets `short` and `brief`:
  - `new_order` "New order #SP-1044", text the total in bold; `order_return` "Return asked for #…", "Return sent back #…", "Refund due #…", "Refund overdue #…", text the sentence; `out_stock` "Out of stock", text the product; `form_submited` "Form submitted", text "Reference Code **AB12**"; `new_comment` "New comment" / "New product question"; `erp_auto_invoice` "Order #… not invoiced".
  - A Workspace row: `ws_bell_title()` (`includes/workspace/notify.php`): "<sender> mentioned you", "<sender> asked you", "<sender> wrote", "<agent> answered you"…; the channel is the place ("#sales", "A discussion in #sales"). Owner 2026-11-11, #8754.
- The device push banner keeps the whole sentence (`notifications-menu.md`). `tests/bell_row_test.php` holds no short title with a colon.

## Where is it used?
- `pg_panel_get_notifications()` sends `short` as `title` and `brief` as `details` when set; the row is drawn as `bell-row.md` says.

## What of the sheet is not drawn yet?
- A person's round avatar in place of the tile, and the verb in the second text colour ("Ayşe Demir *mentioned you*"): `ws_bell_title()` sends one string.
