---
name: erp-negative-price-lines
description: An order line of a product priced below zero: how the checkout, the ERP invoice, ERP returns and Paraşüt treat it (Paraşüt refuses it, #1297), and where such a line can still come from
---

## Where can a line below zero come from?
- Only from a product priced below zero: one stored before, or one written by the bulk edit's increase methods until #1296 (commerce/product-bulk-price-edit.md). Every other writer refuses or strips the sign, offers and gift lines floor at zero, and ERP typed lines refuse a negative price (`invoice_manual.php`).

## How is it taxed?
- Checkout: alone (`pg_tax_by_rate()`), so a group's shares never run backwards. `orders.tax` holds that figure.
- ERP invoice (`erp_order_lines()`): without an order discount it keeps the checkout's line figures. With one, the credit gets a negative share of it (`erp_allocate()`) and the VAT is taken again per rate with the credit in its rate's group (`erp_rate_vat()`); a group whose sum is zero or less puts its whole VAT on its first line (1.00 and −3.00 at 20%: −0.40 and 0.00).
- The tie to `orders.tax` moves only lines with VAT above zero, by at most a kurus a taxed line and one. Where the checkout held the order's VAT at zero, a wider gap stays and the invoice is refused as a total mismatch before a number is taken; a narrower one is absorbed, the invoice is numbered, and the driver refuses the credit line at send. Create Invoice names the credit line first either way (#1297).
- Not grouped at the checkout as well (weighed 2026-10-23): Paraşüt takes no such line anyway, and a group share can move a line's VAT by about a kurus per credit line of its rate, which a subscription then bills every period.

## Does it go to Paraşüt?
- No. Live (demo company, 2026-10-23): `POST sales_invoices` with a −0.52, −0.02 or −0.05 line, or a −3.32 discount, was answered 422 ("Birim fiyatı 0 sayısına eşit veya büyük olmalı"; VAT and discount the same). Nothing was created.
- The ERP driver (`erp_edoc_parasut_send_invoice()`) and the order screen's Create Invoice (`parasut_order_invoice_refusal()`) refuse such an invoice before any request and name the line (`erp_edoc_parasut_negative_line_refusal()`, #1297). İşbaşı was not tried.

## How does a return give it back?
- Like a discount, with the goods (#1306): left out (empty or 0), the credit comes back in the share the goods given back are of the goods' value left (not shipping) (`erp_return_credit_quantities()`; four places, all of it with the last goods). Weighed by its VAT rate's goods only when what is left of them covers the credit's rest, else by all goods. 100.00 and −30.00 at 20% (84.00): the goods give back 84.00, one of two units 42.00 (before: 120.00, 60.00).
- Figures stay below zero, each piece held between zero and what is left (`erp_return_within()`). `erp_return_credit_refusal()`: a return's total is held to 0..max(0, left of the sale); a sale already below zero is refused with its own message (it failed in `erp_account_post()` with no message). Per-rate net/VAT bounds apply only to a credit typed by hand: on the worked-out share they refused every partial return of a credit at a rate with only shipping left, or larger than its rate's goods.
- The return screen leaves the credit empty. The card dialog's Everything leaves credit rows at 0 (a whole count was out of step, refused). A request (goods only) gets it too. Without the ERP, `pg_return_refund_estimate()` brings it back by goods price, its discount share below zero.

## Which tests cover it?
- `tests/erp_edoc_parasut_test.php` (`..._negative_line_refusal`, `..._send_invoice_refuses_a_line_below_zero`), `tests/parasut_order_invoice_test.php` (`..._names_a_line_below_zero`, `..._before_the_totals`), `tests/order_tax_by_rate_test.php` (a credit taxed alone), `tests/erp_return_test.php` (`..._credit*`, `..._below_zero_is_refused`), `tests/erp_order_refund_test.php` (`..._credit_line`, `..._credit_row_to_the_goods`), `tests/returns_test.php` (`test_returns_refund_estimate`).
