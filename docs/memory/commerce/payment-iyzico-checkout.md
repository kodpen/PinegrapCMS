---
name: payment-iyzico-checkout
description: How submit_order.php pays with iyzico: the driver branch for Iyzipay and Pay With Iyzico, return modes, state, the removed inline code (PR #2770) and the instalment surcharge skip
---

## How does submit_order.php pay with iyzico?
- One branch before the non-recurring charge switch: `pg_payment_checkout_driver(method, ECOMMERCE_PAYMENT_GATEWAY, mode)` non-empty → `authorize`, or `complete` on the driver's return mode (`$payment_return`), through `pg_payment_call()`; else the switch runs. The answer fills the gateway code's variables (`pg_payment_checkout_totals()`: amount above the total = instalment charge; `card_brand` → `card_type`).
- Return modes keep the old addresses (`pg_payment_return_mode()`): `iyzipay_threedsecure_return`, `pay_with_iyzico_return`; the checks atop submit_order.php and order_preview.php still apply.
- The 3-D Secure return: `commerce/payment-iyzico-threeds-return.md`. A card return gets an empty card number and expiry (the bank posts no card), as the Iyzipay block gave it.
- The card's instalment charge is lowered where a rate's total would be out of reach (`pg_order_installment_charge()`, #1269, erp/erp-order-charges-vat.md); the 3-D Secure row and the order keep that figure.
- PWI state (token, conversation id, order id, base total): `ecommerce.payment_state.pay_with_iyzico`; card 3-D Secure state (the visitor's instalment choice): `…payment_state.iyzico`. A failure before iyzico is asked keeps it, any iyzico answer ends it.
- A registered driver that cannot load refuses the payment, never falls back to the gateway code. Errors go through `h()` before `mark_error()`.
- The inline Iyzipay and Pay With Iyzico charge code was removed (PR #2770, decision #2438); `tests/payment_iyzico_routing_test.php` pins both values to the driver in every mode and keeps the iyzico SDK out of submit_order.php. The card's `case 'Iyzipay':` left in the gateway switch refuses, and Pay With Iyzico falls to the refusing `default`, so a request that ever skipped the driver cannot complete an order uncharged. The recurring-profile `case 'Iyzipay':` further down is live and stays. `iyzipay_cancel_payment()` (`includes/fn/ecommerce.php`) has no caller since.
- The installment-surcharge skip of the "total changed" check (gateway Iyzipay, instalment ≥ 2) stays: the driver works the fee out from the server total and iyzico's installmentPrice, as the removed block did.

Related: docs/memory/commerce/payment-driver-iyzico.md, docs/memory/commerce/payment-iyzico-threeds-return.md
