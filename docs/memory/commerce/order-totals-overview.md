---
name: order-totals-overview
description: Order totals (a coupon's 1 kurus gap) across checkout, order pages and the ERP invoice: who works them out, who reads the header, rounding, where a kurus may differ and what is decided
---

## What makes an order's total?
- `submit_order()` charges and writes the header: the lines charged today (`pg_order_line_charged_today()`), less `orders.discount`, plus `orders.tax`, the recipients' shipping, the surcharge and the instalment charge, less the gift cards; `orders.total` holds the instalment charge and has the gift card taken off (`erp/erp-order-invoice-header.md`).
- The header is the record: the sum of `order_items` is not the total, and `order_items.tax_total` is the undiscounted line tax (`erp/erp-discounted-order-vat.md`).

## Which decisions bind it?
- Owner (2026-10-21): VAT per group of lines with one rate (and one withholding rate), on the group's sum, as Paraşüt takes it (`erp/erp-vat-per-rate-group.md`).
- Owner (2026-10-21): the customer never pays more than the page showed; a rate total no amount reaches takes the shipping, then the surcharge, a kurus lower (9.99 shows 9.98). Owner (2026-10-22): the same for the instalment charge and for orders placed before the rule (`erp/erp-order-charges-vat.md`).
- Not decided: whether an order discount on a cart with a later-starting subscription is worked out over today's lines or capped at them; today it is capped (`order-discount-charged-today.md`).

## How is money rounded?
- In minor units, half away from zero, on the exact product: a percentage with `pg_percent_of()` / `pg_percent_off()` (`offer-percent-rounding.md`, `card-surcharge-rounding.md`), a tax rate with `pg_tax_apply_rate()` and in the ERP `erp_apply_rate()` (`erp/erp-line-total-and-rate-rounding.md`). A float `round(P / 100 * x)` moved a kurus on PHP 8.4+; never an `(int)` cut.

## Which surfaces work totals out, and which read the header?
- Work them out from the cart, as `submit_order()` will: the cart, the express order widget (`pg_express_order_charged_totals()`, `express-order-widget-totals.md`), the classic express order and order preview pages (own copies), the legal documents.
- Read `orders.discount`, `tax`, `total`: My Account's order view, the receipt page and e-mail (`pg_order_page_figures()`, `order-page-stored-figures.md`), the designer order view widget, the panel's order screen.
- The ERP invoice rebuilds lines with `erp_order_lines()`, ties them to `orders.tax` and refuses a `grand_total` other than `orders.total` + gift card (`erp/erp-order-invoice-header.md`). The order screen's legacy Paraşüt Create Invoice sends the same lines (`erp/erp-parasut-legacy-invoice.md`).
- Marketplace imports build their own header (`mp_order_totals()`, `marketplace-order-money.md`).

## Where may a kurus differ?
- A line's VAT against its own rate x amount: decided (per rate group; lines share the group's VAT by largest remainder, `pg_tax_spread()` = `erp_allocate()`).
- A line partly discounted by an offer: the spread unit price rounds down, the customer keeps at most quantity − 1 minor units (`offer-discounted-units.md`).
- Orders taxed per line before #1168: a gap of up to 1 kurus per line is spread on the invoice; wider is refused (`erp/erp-discounted-order-vat.md`).
- Marketplace and API orders: settled on the invoice only, a kurus below the payment (`erp/erp-order-charges-vat.md`).
- Issued ERP documents keep their figures; the Paraşüt driver's per-rate shift stays for them (`erp/erp-parasut-invoice-lines.md`).

## What is checked before a total is accepted?
- `submit_order()` refuses a total higher than the one the page posted ("the total has changed"), so any page that shows a total must follow its arithmetic.
- Tests: `order_charges_test.php`, `express_order_totals_test.php`, `order_page_figures_test.php`, `offer_percent_test.php`, `surcharge_percent_test.php`, `tax_apply_rate_test.php`, `erp_order_lines_test.php`.
