---
name: system-status-payment-mode
description: The "Payment Mode" System Status row - a switched-on payment way in test on a live shop, by the set-up card's own rule; when it is drawn, what it names, which saves drop the cache
module: launch_journey
---

## When is the row drawn?
- Only with the shop on (`ECOMMERCE === true`) and the site gone live: the `launch_journey` row's `live_at` above 0, set by the first "Go live" on the "Set up your site" card. Shop off or never gone live: no row; the card says it.
- Owner (2026-11-26): a warning row on System Status, no operations alert, no schema change (agent channel poll 225, `workflow/decisions-made-product-112.md`). Architect (2026-11-26, request 3108): "gone live" is the journey's own go-live time.

## What decides "in test"?
- `pg_launch_journey_payment_test_ways($shop_on, $live_at, $payments, $gateway_mode)` (`includes/fn/launch_journey.php`, pure): null for no row, else the drivers in test by `pg_launch_journey_payment_live()` over `pg_launch_journey_payment_facts()`, the card's step "Payment live" rule. Never a second rule: the row and the card must not disagree.
- A way whose mode cannot be read (no key, no mode) is not a warning. No way in test, or no online way at all: the row passes ("Live").

## What does the row show?
- In `get_system_status_checks()` (`includes/fn/system_status.php`), title "Payment Mode", short label "Payments", glyph `bi-credit-card`, a job row; reported, not scored (no weight), like Product Feeds.
- Warning: "{var:1} in test", a detail row per way in test (the driver's label, or `pg_payment_method_label()` for a classic way) and the link to Site Settings › Ecommerce › Payment Methods (`pgset-payments`), where every way's mode and keys are set.
- Tests: `tests/system_status_payment_test_test.php`.

## Which saves drop the cache?
- The Payment Methods card saves through Site Settings, which already calls `pg_system_status_forget()`.
- `connections.php` `gateway_save` / `gateway_remove` (classic gateway keys, the classic Stripe key) and `driver_save` / `driver_remove` (Stripe, PayPal, PayTR keys) call it after a write.
- `pg_launch_journey_go_live()` calls it on the first "Go live" too, since the row starts there. `tests/system_status_forget_test.php` pins these.
