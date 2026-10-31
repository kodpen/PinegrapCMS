---
name: order-preview-step-links-and-installments
description: Order Preview without earlier checkout step pages in the session (pg_op_step_page_id/url, no Change link) and the Iyzipay installment table shared with Express Order (#4019)
module: widgets_order_preview
---

## What if the visitor skipped the shipping or billing step?
- The cart, shipping and billing steps write `$_SESSION['ecommerce']['shipping_address_and_arrival_page_id']` / `['billing_information_page_id']`; a visitor (or the operator in the designer) can reach the Order Preview without them.
- Read them only with `pg_op_step_page_id($_SESSION['ecommerce'] ?? null, $key)` (0 when absent) and build the link with `pg_op_step_url($key, $query)` ('' when no page). Classic markup uses `pg_op_change_link_html()`.
- No page: the "Change" link is left out (widget recipients, `__billing_update_url` dropped as an empty link, `templates/order_preview.php`, the classic branch), the billing custom field label query is skipped; totals are untouched.
- Before #4019 (#3989): five warnings per view, links to `/?ship_to_id=…` and `/`.

## Why did the designer save say it failed?
- After a save `pg_seo_analyze_record()` renders the page with the operator's session; the widget's warnings were printed before `header()` in `designer_screen.php`. Now the widget returns '' while `pg_seo_rendering()` or `UPDATE_SEARCH_INDEX` is on, as the cart widget does; no output buffer was added.

## How is the Iyzipay installment table built?
- One function for both pages: `pg_payment_iyzico_installment_table($amount, pg_payment_iyzico_settings(), $_SESSION['ecommerce']['iyzipay_installments'])` in `includes/payments/iyzico.php` (load it with `require_once`; driver files are loaded lazily). Returns `html` and `rows`; `rows > 0` sets `$installment`.
- iyzico is asked only when the installment setting is 2+, both keys are set and the amount is above 0. Before #4019 (#3990) 7 `RetrieveInstallmentInfo` calls ran on every render (~650 ms) even with installments off, and a failed reply printed 13 warnings.
- `pg_payment_iyzico_installment_detail()` checks `status === 'success'` and `installmentDetails[0].installmentPrices`; a failed family is left out and not cached. Prices are keyed by `installmentNumber` (position 1,2,3,6,9,12 when missing); a row needs a 1-installment price; columns 2,3,6,9,12 up to the setting.
- A family's answer is cached ten minutes in the session slot by base URL, key, locale, amount and bin.
- The family name is escaped with `h()`; amounts are printed as iyzico sends them (`pg_format_money`, base currency symbol).
- No table at all when no family answers (before: an empty table).
- Tests: `tests/iyzipay_installment_table_test.php` (stand-in HTTP client), `tests/order_preview_session_steps_test.php`. No sandbox call in CI.
