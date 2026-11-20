---
name: integrations-census-today
description: Census (2026-11-17) of the outside services the product connects to today, the selling side: how they were counted, payments, shipping, marketplaces, e-documents, how a new one is added
---

## How was it counted?
- On `development` at f1586738: one per outside product or API that is in the code with its screen. Not counted: generic protocols (SMTP, IMAP, FTP, S3-compatible storage, iCal, RSS, Web Push, time stamps), carriers that are a tracking link only, anything partly built or planned.
- Total 79, and 12 ready-made MCP connectors beside them. Memory says many were built from documentation and never run against the live service: carrier labels, Trendyol, the mail transports, SMS, calendar sync, model assistants, Microsoft mailboxes, İYS, İşbaşı live.
- By group: payments 9, shipping 10, marketplaces 2, e-document and tax 3, e-mail 8, SMS and consent 4, storage 3, sign-in 3, analytics and SEO 3, maps and geo 4, AI 10, chat 4, calendar 2, currency 2, CDN, captcha and spam 4, fediverse 1, GitHub 1, feeds 2, vendor 1, other 3. The groups after e-documents: `integrations-census-today-2.md`.

## Which payment services? (Site Settings › Ecommerce › Payment Methods)
- Drivers in `includes/payments/`: iyzico, Stripe, PayPal, PayTR. Older gateways kept inline in `submit_order.php`: Authorize.Net, ClearCommerce, First Data, Sage. Givex gift cards on a card of their own.
- Named as candidates in `commerce/payment-driver-layer-decisions.md`, with no plan: Sipay, Param.

## Which shipping services? (Site Settings › Ecommerce › Shipping)
- Label drivers in `includes/shipping/`: Geliver, Basit Kargo, Kargonomi (aggregators), Aras Kargo (direct), Shippo and Sendcloud (abroad). Live checkout rates: UPS, FedEx, USPS. ShipWorks reads orders through `shipworks.php`.
- Yurtiçi, MNG, PTT, Sürat, DHL and eight more carriers are a tracking link only; their labels come through an aggregator.

## Which marketplaces? (System tools › Marketplaces)
- n11 and Trendyol on the outbound layer (`includes/api/outbound/connectors/`), and Google Merchant Center as a feed and a per-product push. Hepsiburada is planned and waits for its API facts (`marketplace-trendyol-hepsiburada.md`).

## Which e-document and accounting services? (Site Settings › Accounting › E-Invoice)
- Paraşüt and Logo İşbaşı for e-Fatura and e-Arşiv (İşbaşı on its test environment only); VIES for EU VAT numbers.

## How is a new one added?
- A file and a row of a list; no add-on loader exists yet (`addon-hook-system.md`).
- Payment: `includes/payments/<key>.php` and `pg_payment_builtin_drivers()`. Carrier: `includes/shipping/<key>.php` and `pg_label_builtin_drivers()`. Marketplace: `connectors/<key>.php` and `mp_providers()`. SMS: `includes/sms/<key>.php` and `pg_sms_providers()`.
- Connected account: `includes/connect/providers/<key>.php` with a purpose file, found by `pg_connect_definitions()`. Model provider: a row of `ws_model_providers()`. MCP connector: a row of `ws_mcp_catalogue()`.
