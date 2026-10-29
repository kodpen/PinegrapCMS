---
name: order-completion-context
description: pg_order_complete()'s $context (#2972): what the caller passes besides the columns, and what stays in submit_order() after the call
module: order_completion
---

## What is the context?
- What the caller knows besides the columns; a missing key means a request without a visitor: `lines` (the order lines joined to their products, read by `submit_order()` before payment), `order` (billing columns, custom fields, PO number), `contact_id`, `reference_code`, `receipt` (the checkout page's settings; none: no receipt), `new_account`, `auto_registration`, `key_code`, `affiliate_rate`, `recurring_products`, `created_user_id`, `actor`, `visitor_id`. Total, date, user id, commission, gift card figure and affiliate code come from `$values`.
- A3 builds it from the order row and the same line query (the receipt settings are its open question).

## What stays in submit_order()?
- Auto-registration (`order-completion-before-lock.md`), then the call; after it the frozen legacy gateways' recurring profiles (the typed card), the session values the receipt page reads, the post-save hook, `pg_erp_auto_invoice()`, the redirect.
- Since #2972 the recurring profiles are created after the receipt and line writes instead of before them; the hook still runs after the new_order notification and before the invoice, but no longer sees the loop's locals (`$reward_points`, `$contact_groups`, `$forms`): only the request's own variables.

Related: commerce/order-completion.md, plans/order-completion-function.md
