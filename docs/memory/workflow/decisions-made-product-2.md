---
name: decisions-made-product-2
description: Decisions list, product part 2 (2026-10-29 on): a guest order the payment service's message completes links or opens the account the checkout page's switch asks for, and gets that page's receipt
---

## What happens to a guest order the payment service's message completes? (#2984)
- Decided (architect, 2026-10-29; `commerce/order-completion-from-payment.md`): the checkout page's auto-registration switch holds whichever request completes the order. A3 links the order to the account whose e-mail is the billing e-mail (`decisions-made-access.md`, #67 item 8: no verification) and opens none; A3b (#3035, `commerce/order-completion-account.md`) opens the account without signing anyone in, and the customer's receipt copy carries the set-password link as on the visitor's path. A blocked address gets no account; the order still completes. Reason: the owner's decision (#2382) completes the order the visitor left, and the switch is the site's setting.
- The receipt is the one sent by the checkout page `orders.checkout_page_id` names (`decisions-made-schema-2.md`); an order that names no page (started before 2026.11.0's step 11.0.120) or whose page is gone gets a plain-text receipt with a fixed subject and the order number, always sent, and no account link.

Related: docs/memory/workflow/decisions-made-product.md (part 1), docs/memory/workflow/decisions-made.md
