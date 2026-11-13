---
name: canvas-sample-gaps-orders
description: CS0 census, orders: what the order view and order receipt e-mail card still draw without a sample after CS2 (#9214)
---

## What did CS2 settle?
- Every value of `SW_ORDER_VIEW_TOKEN_GROUPS`, the order entity tokens and the `order.*` lists draws the sample order (`canvas-sample-order.md`): the shipping address is no longer money, counts and ids are their own, the product form shows three answers and an information text, returns draw one request with its lines and actions.

## What is left?
- `__item_image` is the grey `[token]` box (picture work: CF4 of `plans/designer-canvas-faults.md`).
- Flags meant to be empty in a shipped order (`__order_is_tax_exempt`, `__item_added_by_offer`, `__item_discounted_by_offer`, `__has_shipping` …) stay empty, as the plan allows.

## Which part belongs to the cart and checkout?
- `canvas-sample-gaps-checkout.md`.
