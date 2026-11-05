---
name: erp-screen-status-column-pattern
description: Owner decision on ERP screen width: form column plus sticky status column and sticky save bar, modeled on product_builder.php.
---

## What did the owner decide about wide ERP screens?
- Owner decision: fix the sprawling layouts on these screens; the software design language (Bootstrap 5, card structure, toolbar pattern) stays, but badly laid-out screens must not keep being repeated. Full-width cards stacked in a single column are out.
- Applies to ERP screens; a new ERP screen assumes this pattern by default.

## What is the reference layout?
Reference implementation: `product_builder.php` (draws `add_product.php` and product edit).

| Part | Classes | Content |
| --- | --- | --- |
| Form column | `col-12 col-lg-8 col-xxl-9 order-1` | Cards the operator fills |
| Status column | `col-12 col-lg-4 col-xxl-3 order-2`, inside it a `position-sticky` box (`top:3.3rem`) | Read-only info: badges, numbers, summary, preview |
| Save bar | `col-12 order-3 position-sticky` (`bottom:0`) | The single primary button |

## What are the rules?
- Below `lg` columns stack in source order, so write the status column AFTER the form column; on a narrow screen the summary must not push the first field off screen.
- Stickiness goes on the box inside the status column, not the column (the column is as tall as the row). The save bar is the opposite: stickiness goes on its column.
- The status column holds what is read, not what is filled. The one box the operator works on (barcode in the product editor) goes on top.
- Short cards may pair up (`col-12 col-xl-6`); order of preference: first move status info to the column, then pair the rest.
- A screen that departs from the pattern says WHY in a comment.

## How do the account create and edit screens apply it?
- `edit_erp_account.php`: the cards and the sticky save bar in the form column; the Balance card (balance, credit limit line, late fee panel) in the status column, outside the account form, because `erp_late_fee_panel()` carries its own form; the statement full width below (`order-3`). Before (issue #6551) all six cards and the balance strip were stacked in one `col-12`.
- `add_erp_account.php`: the form wraps both columns; the status column holds the Notes card (`erp_account_form_cards(..., $with_notes = false)` + `erp_account_form_notes_card()`).

## How do the till screens apply it?
- `add_erp_till.php` (Open a Till, #6834): the till card in the form column, a Status card (Active/Passive and what passive means) in the sticky status column, `erp_till_form_cards($liveform, false, false)` leaving Status out of the card; before, one full-width card. `edit_erp_till.php` keeps Status in the card and reads balance, type and movements in its status column, the cash book full width below.
- `add_erp_transfer.php` (Transfer, #6875): From/To, Amount, Date and Description in one card in the form column; the sticky status column lists every active till with its balance (Till Balances), read from the same query as the till options.

## How does the one-receipt screen apply it?
- `erp_receipt.php` (#6893): Allocations, Allocate to open invoices and the cheque note in the work column; the movement card (number, badge, date, amount, account, till, method, description, cancellation line) in the sticky status column; Cancel the Receipt/Payment in the sticky save bar column. Before, the movement card ran full width above the allocations.
