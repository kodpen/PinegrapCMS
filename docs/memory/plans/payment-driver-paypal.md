---
name: payment-driver-paypal
description: Step 4b (PayPal Checkout v2) hand-over: branch feature/payment-4b-paypal-v2 (closed PR #1827), what is missing before it lands, the open PENDING-capture question
---

## What is built?
- Branch `feature/payment-4b-paypal-v2` (PR #1827, closed unmerged; CI green on e21f842):
  - `includes/payments/paypal.php`: key `paypal`, redirect method `PayPal Checkout`; connect_apps client ID, secret, mode. Orders v2 create → approve redirect → read and capture in `complete()`; refund and cancel by capture id; no webhook.
  - The built-in entry in `includes/fn/payments.php`.
  - Step `upgrade_2026_11_0_paypal_checkout()` appending the method to `orders.payment_method` (label 11.0.39 then).
  - view_order_report.php options from the drivers' methods.
  - `tests/payment_paypal_test.php` with `tests/fixtures/paypal/`, the memory file `commerce/payment-driver-paypal.md` and the TR keys.
- Both merge-train findings are fixed in e21f842:
  - An unclear capture answer (5xx, not JSON, no capture in a 2xx) keeps the return state.
  - A PENDING capture's log line names PayPal's reason and asks for a check before shipping.

## What is missing before it lands?
- Decided (#2408): a PENDING capture places the order awaiting payment, as the 4b PR does; the denial reaches the site by step 4b2 below.
- Bring the branch up to date (merge `development`, or cherry-pick its two commits onto a fresh branch). Re-check that the step label is free (`release/version-numbering.md`) and re-check the TR keys (`php tools/check_lang.php`). Open a new PR titled `[plan:payment-driver-layer] 4b — …`.
- Never verified: the fixtures follow PayPal's Orders v2 / Payments v2 docs, but no live or sandbox account was called, and the browser round trip (checkout → PayPal → `paypal_return` → receipt) never ran.
- Known limits: PayPal takes no TRY (refused at authorize, the method is not hidden). Payments are always captured, whatever the Transaction Type.

## What was decided for a PENDING capture? (#2408)
- The order is placed awaiting payment (a). Step 4b2, after 3b's callback endpoint, takes PayPal's `PAYMENT.CAPTURE.COMPLETED` / `DENIED` webhooks on `payment_callback.php?driver=paypal`: completion marks the order paid, a denial alerts.

## Where to start?
- `git log origin/feature/payment-4b-paypal-v2`; the branch's `commerce/payment-driver-paypal.md`.

Related: docs/memory/plans/payment-driver-layer.md, docs/memory/commerce/payment-drivers.md
