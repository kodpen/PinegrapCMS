---
name: markets-price-lists-census-payments
description: Markets and price lists MK1 census, payment drivers: in which currency each shipped and legacy driver charges today and which currencies each can take, from its own code and notes
---

## In which currency does each driver charge?
- Stripe (`includes/payments/stripe.php`): base, lowercased (158); minor units by currency (173-181); amount 196; intents 519, 529, 578, 586; the completion check compares amount and currency (715-721); the fee is read in the settlement currency (795-811). No list of accepted currencies in the code. Notes: `commerce/payment-driver-stripe.md`.
- PayPal (`includes/payments/paypal.php`): base (117). `pg_payment_paypal_currencies()` (139): AUD BRL CAD CNY CZK DKK EUR HKD HUF ILS JPY MYR MXN TWD NZD NOK PHP PLN GBP SGD SEK CHF THB USD, no TRY; whole-unit currencies 157; a base outside the list is refused (434-435); refund in base (739). Notes: `commerce/payment-driver-paypal.md`.
- PayTR (`includes/payments/paytr.php`): base (134), named by `pg_payment_paytr_currency()` (191) as TL, EUR, USD, GBP or RUB; any other is refused (482-483). Notes: `commerce/payment-driver-paytr.md`.
- iyzico card (`includes/payments/iyzico.php`): base (160), or the protected currency when every switch says so (165-179), converted at today's `currencies` rate (179; card request 715-739). Setting: `includes/settings/commerce.php:520`. Pay with iyzico: base (1191). Renewal: the subscription's currency (1741), protected conversion 1752-1760. A refund under a protected currency is refused (1444-1467). Notes: `commerce/payment-driver-iyzico.md`.
- Legacy gateways (`pg_payment_legacy_gateways()` `includes/fn/payments.php:56`, code in `submit_order.php`): all send `BASE_CURRENCY_CODE`. ClearCommerce only USD, AUD, CAD, EUR, GBP (1686-1705); Authorize.Net 2161; Payflow 2534; Payments Pro 2778; Stripe (old) 3071; PayPal Express 3282, 3312. First Data (2393) and Sage (2934) send no currency.
- Offline payment, store credit and "On Account": base; the account limit is base minor units (`includes/erp/storefront.php`, `erp_storefront_on_account()`).

## What would a charge in the buyer's currency ask of each?
- Stripe and PayPal already send a currency code: they would send the order's charged currency, and PayPal's list says where it cannot.
- PayTR takes its five currencies the same way.
- iyzico's protected currency shows the path, and its refused refund shows what is missing: the charged currency and rate kept on the order.
- The legacy gateways stay in base.
