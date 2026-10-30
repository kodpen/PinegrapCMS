---
name: subscriptions-iyzico
description: iyzico store-run subscriptions (PR #3249, decided in #3183): the stored-card renewals switch, card_ref, charge_renewal, update_card, subscription_cancel, the checkout row
module: subscriptions
---

## When does iyzico run subscriptions?
- Only while the card driver's one field `stored_card_renewals` (Ecommerce card, Iyzipay box: "Stored-card renewals allowed by iyzico") is on: then `pg_payment_iyzico_capabilities()` adds `'recurring' => 'store'`. iyzico charges a stored card without 3-D Secure only for a merchant it approved; off by default (#3183). Pay with iyzico never does.
- The switch lives in `connect_apps` row `payment_iyzico` (options), though the driver's keys stay in config columns: `pg_payment_settings_card_rows()` draws fields of every card driver, `settings` config or connect.

## What is card_ref?
- `"<cardUserKey>|<cardToken>"` (`pg_payment_iyzico_card_ref()`, ≤150 chars, neither half holds `|`). The first payment sends `registerCard` 1 whenever the cart has a recurring line (as before) and both `authorize` (direct) and `complete` (3-D Secure return) answer `card_ref` and `card_last4`. Never logged; the checkout seals it (`pg_subscription_card_ref_seal()`).

## What do the operations do?
- `charge_renewal($row)`: needs `$row['order']` (the first order's orders row: iyzico wants buyer and billing address) besides the row from `pg_subscription_driver_row()`. Reads the wallet (`/cardstorage/cards`); stored token there → charge it; gone and exactly one card left (the customer replaced it on iyzico's page) → charge that one and answer the new `card_ref` (the caller re-seals it); else a plain failure. Then CreatePaymentRequest with cardUserKey + cardToken, no 3DS, installment 1, group SUBSCRIPTION, basket `subscription-<id>`, the row's amount and currency (protected currency: converted at today's rate as the checkout does; answer `amount` is the row's).
- Answers: paid; iyzico's refusal in its own words (`raw.code`) = clear failure; empty body or a cURL error after sending = unclear (`pg_payment_iyzico_refusal()`; `pg_payment_iyzico_curl_unsent_codes()` are plain failures); fraud -1 refused, 0 paid and logged.
- `update_card($row)`: iyzico's card management page (`POST /v1/card-management/pages`, cardUserKey, `callbackUrl` = `$row['return_url']` or the site root), signed IYZWSv2 by hand since SDK 2.0.59 has no class for it; answers redirect to `cardPageUrl`.
- `subscription_cancel($row)`: `DeleteCardRequest` (cardUserKey + cardToken). Lines of one order share the card: the cancel slice calls it only when no other live subscription of that order uses it.
- The SDK's `Subscription` mappers stay unloaded (no PHP 7.1 patch).

## How does the checkout make the row?
- `submit_order.php`, right after `pg_order_complete()` and before the legacy profiles: when a driver took the payment and the cart has a recurring line, `pg_subscription_checkout_create($order_id, $driver, $payment_answer, $products)`. Pure `pg_subscription_checkout_lines()` picks recurring, priced lines, once per line, only for mode `store` with a card_ref; later lines too (`subscriptions-later-start.md`). `pg_subscription_create_from_order_line()` returns the existing row of the same order line (retried return). A line that got none is logged ("A subscription could not be created for an order line.", e.g. no `ENCRYPTION_KEY`). Currency: `BASE_CURRENCY_CODE`.
- The legacy `case 'Iyzipay':` that sets `recurring_profile_enabled` still runs. With the switch off iyzico has no recurring mode: later lines get no row and the order is not refused, as before.

## How was it verified?
- `tests/payment_iyzico_test.php` (stand-in HTTP client), `tests/subscriptions_test.php`. No iyzico sandbox run: the card management page's request and answer follow iyzico's documentation, not a recorded exchange.

Related: commerce/subscriptions.md, commerce/payment-driver-iyzico.md, plans/subscriptions-iyzico-stripe.md
