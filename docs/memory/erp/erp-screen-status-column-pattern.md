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
