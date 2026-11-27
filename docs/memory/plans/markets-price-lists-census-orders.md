---
name: markets-price-lists-census-orders
description: Markets and price lists MK1 census, orders: where an order keeps its currency and rate, and what refunds, reports, the API, the ERP entry, marketplaces and feeds assume about its totals
---

## Where does an order keep its currency and rate?
- `orders.currency_code` varchar(3) (`data/backups/english_default/sql.sql:753`): `VISITOR_CURRENCY_CODE` when the order is made (`initialize_order()` `includes/fn/ecommerce.php:8148`, bound at 8244), rewritten when the visitor switches (`update_currency.php:55-56`). It is what was shown, not what was charged.
- No column keeps a rate. `subtotal`, `discount`, `tax`, `shipping`, `total` and `refunded_amount` (`2026.1.14.php:31`) are base minor units.
- A marketplace order keeps the marketplace's totals and `currency_code` from its package, not converted (`includes/api/outbound/orders.php:734-740`).
- A renewal hands `orders.currency_code` to the driver (`includes/fn/subscription_renewal.php:617`).

## What do the readers of an order's totals assume?
- Refund: base (`view_order.php:2039-2041`); the driver refund at 2871 and `includes/fn/returns.php:4221` send base amounts.
- API: an order has no currency field (`includes/api/resources/orders.php`, totals 105-108); `GET /meta` gives the base currency for every money field (`includes/api/resources/meta.php:10-13, 29, 53-56`).
- ERP entry: the invoice is in `erp_base_currency()`, rate 1, source `base` (`includes/erp/order_bridge.php:1032-1035`); a marketplace order goes to its own screen unless the store invoices those too (1252-1255). Paraşüt gets base (`includes/fn/parasut.php:4589-4591`).
- Order profit: a supplier cost in another currency is converted at today's `currencies` rate (`includes/erp/order_profit.php:96-105, 693-712`).
- Sums of `orders.total` with no currency split: `includes/dashboard/v2/cards/shop.php:82`, `includes/dashboard/widgets/widget_3.php:46-53`, `includes/sales_map.php:559, 602, 669`, `includes/erp/profit.php:735`, `includes/fn/audiences.php:639`. A marketplace order in another currency is added as if it were base.
- `view_order_report.php` filters and groups by currency (205-207, 379-404) but prints every figure with the base symbol (1028-1044).
- Split by currency already: `includes/fn/product_insights.php:385-393`, `includes/fn/customer_values.php:92-103`, the API (`includes/api/resources/reports.php:523`).
- Marketplaces: the settings screen shows a total with the order's own code (`marketplace_settings.php:1725-1732`).
- Feeds: price and currency are base (`includes/fn/product_feeds.php:123-130, 572-576, 1032`).
- iyzico's protected currency charges another currency, but the order does not keep it, so its refund is refused (`includes/payments/iyzico.php:1444-1467`).
