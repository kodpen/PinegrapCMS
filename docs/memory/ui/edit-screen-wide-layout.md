---
name: edit-screen-wide-layout
description: Owner decision for panel edit screens: a wide screen is not spent on one column of stacked cards; form column, sticky status column, sticky save bar, modeled on product_builder.php.
---

## What did the owner decide?
- Full-width cards stacked in a single column look sprawling on a wide screen; edit screens must not keep repeating that. The design language (Bootstrap 5, cards, the toolbar) stays. Reference implementation: `product_builder.php` (adding and editing a product). ERP screens apply it by default (`erp/erp-screen-status-column-pattern.md`).

## Which columns and classes?
| Part | Classes | Holds |
|---|---|---|
| Form column | `col-12 col-lg-8 col-xxl-9 order-1` | the cards the operator fills |
| Status column | `col-12 col-lg-4 col-xxl-3 order-2`, with a `position-sticky` box inside | what is READ: status badges, numbers, summary, preview |
| Save bar | `col-12 order-3 position-sticky` | the primary button |

## What are the placement rules?
- Below `lg` the columns stack in source order: write the status column AFTER the form column, so on a narrow screen the summary does not push the first field off screen.
- Stickiness goes on the box inside the status column, not on the column (the column is as tall as the row); the save bar is sticky on its own column.
- Short cards are paired in one row or moved into the status column.
- A screen that departs from the pattern says why in a code comment.

Related: docs/memory/ui/admin-screen-toolbar.md
