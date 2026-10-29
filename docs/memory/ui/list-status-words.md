---
name: list-status-words
description: A word beside a list row's green or red state (pg_list_status_word()): product groups, key codes, arrival dates, gift cards, ERP expenses
module: output
---

## Why a word beside the colour?
- `.status_enabled`/`.status_disabled` and `fg-success`/`fg-danger` carry colour only, so on these lists a colour-blind user, or a screen reader, could not tell which record was off. The colours stay; a short word is added.

## What prints the word?
- `pg_list_status_word($text)` (`includes/fn/output.php`): `<div class="small fg-secondary">` with the escaped text, under the coloured name or code.

## Where, and which word?
- Edit Product Group, products in the group (`edit_product_group.php`) and the rows offered for it, preloaded (`pg_product_group_form_unselected_rows()`) and Ajax-loaded (`includes/panel/commerce.php`): Disabled under a disabled product's name. The three places stay alike.
- All Key Codes: `pg_key_code_list_cells()` now returns `html` for the `code` cell, the escaped code plus the reason it does not work today: Disabled, else Expired (the code's own date), else Offer not running (the offer is off or outside its dates). The template prints that html, so the quick-edit answer repaints the reason through `paint()` with no JS change.
- All Shipping Arrival Dates: Inactive under the name outside the date window; the Status badge still says Enabled there.
- All Gift Cards: Expired, else No balance, under the red code badge.
- ERP Expenses list and Expense screen: an Overdue badge after Unpaid when the due date has passed, as on the cheques list.

## What was left out?
- `view_containers`, `view_auto_dialogs` and the products list already have an Enabled column.

Test: `tests/list_status_words_test.php`.
