---
name: suggestion-lists
description: Search-as-you-type lists the screens build themselves (ERP account contact, product bundle, Create Order, ERP lines): keyboard, focus after a pick, how an empty or failed answer is said
---

## Which lists are these?
Lists drawn by a screen's own script, not `pg_fields.js`: the ERP account Linked Contact search (`includes/erp/account_form.php`), the Edit Product bundle search (`assets/js/product_bundle.js`), Create Order's product and customer boxes (`assets/js/add_order.js`) and the ERP invoice/waybill line product search (`assets/js/erp_invoice_editor.js`).

## How do they work from the keyboard?
- The box is `role=combobox` with `aria-autocomplete=list`, `aria-controls` (the list's id) and `aria-expanded` kept in step with the list.
- ERP contact, bundle and ERP lines: results are real buttons. ArrowDown in the box moves into them, the arrows move through them, ArrowUp from the first and Escape go back to the box (Escape also closes the list). Create Order keeps focus in the box (`aria-activedescendant`).
- After a pick focus returns to the box; the emptied list would otherwise drop it to the page (`focus-after-removal.md`).

## How is "nothing found" said?
- Every list shows a line and reads it through a polite status line (`role=status`, emptied and filled 150 ms later so a repeat is read again). A plain line inside a list is never read on its own.
- ERP contact: a status div in the card; the pick reads the name and "Linked when the account is saved."
- Bundle: the script creates the status div and the ARIA, so `product_builder.php` stays as it is. Its empty or failed answer shows "No results found" read from `script[data-pg-no-results-label]` (the `pg_fields_script()` tag, printed further down the screen, so read when needed). Adding a row or raising a quantity reads "<product>, Quantity: n", the word taken from the table head.
- Create Order: the existing `announce()` / `#pg_sale_live`.
- ERP lines: a status div the script appends to `[data-erp-editor]`; the popup itself still moves to `pgUI.overlayHost()` (#2968).

## When was it done?
PR "[iş] Own suggestion lists: ERP contact search from the keyboard…" (October 2026), pinned by `tests/suggestion_lists_test.php`.
