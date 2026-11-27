---
name: accounting-sync-census-parasut
description: QuickBooks and Xero sync census, Paraşüt part: how the Paraşüt connection signs in, keeps its token, maps customers, products and tax, records a failed send, and what of it is the shared pattern
---

## How does the Paraşüt connection sign in?
- A connected account, model A: the site's owner registers their own client (`api/api-connected-accounts.md`). Provider `includes/connect/providers/parasut.php` 36-63: `client_auth` post, `identity` false, `connect_purpose` parasut, `listed` false, three `setup` lines. Purpose `includes/connect/purposes/parasut.php`: owner site (user_id 0).
- `pg_connect_finish()` (`includes/fn/connect.php` 1748) trades the code with the PKCE verifier and the secret, as for every provider (`pg_connect_authorize_url()` 446). With `identity` false the row's `external_id` is the fixed `account` (1832).
- Back on Connections, `connections.php` 1809 calls `parasut_devhub_after_connect()` (`includes/fn/parasut.php` 1924): it reads `/v4/me?include=companies`, keeps e-mail and company name in the sealed `parasut_devhub` service entry (`pg_connect_service_put()`, `connect.php` 2053) and writes `config.parasut_company_id`.

## How is the token kept?
- Sealed in the `connect_accounts` row. `pg_connect_access_token()` (`connect.php` 1344) renews under a lock; `pg_connect_api()` (1555) renews once on a 401 (`api/api-connected-accounts-renewal.md`).
- `parasut_get_token()` (`parasut.php` 1100) takes the connection first, else the older cache (a password grant or a pasted code). Requests are held to 10 per 10 s (`PARASUT_RATE_LIMIT`, `parasut_throttle()` 87).

## How are customers, products and tax mapped?
- Customer, order path: `parasut_sync_contact()` (`parasut.php` 3387) reuses `orders.parasut_contact_id`, else finds one by tax number (`parasut_order_contact_for()` 3305), else creates one, and writes the id on the order.
- Customer, ERP path: `erp_edoc_parasut_contact_for()` (`includes/erp/edoc/parasut.php` 680): the card linked in `erp_edoc_accounts` (`includes/erp/account_sync.php` 1854), else a `filter[tax_number]` search page by page, else POST `/contacts`.
- Product: `parasut_sync_product()` (`parasut.php` 2789) POSTs `/products` (`parasut_product_attributes()` 2563: name, list price, currency, VAT rate, stock) and keeps the id in `products.parasut_product_id`. Currencies: TRL, USD, EUR, GBP (`parasut_currency_code()` 2513).
- Tax: a plain `vat_rate` number per line, 0, 1, 10 or 20 (`erp_edoc_parasut_vat_rates()` 528, `erp_edoc_parasut_invoice_payload()` 1416); there is no tax code to look up.

## What happens when a send fails?
- `erp_edoc_invoice_send()` (`includes/erp/edoc/service.php` 1215) claims the invoice as `sending`; on failure (1275) it writes `edoc_status` error and `edoc_error` (2000 characters) and keeps a known external id, so a retry does not create a second invoice.

## Which part is the shared pattern?
- Shared: the provider and purpose files, the sealed token and its renewal, a service entry for what the account says about itself, and an after-connect step that reads the company.
- Not shared: one column per service for each pairing (`orders.parasut_*` and `contacts.parasut_contact_id`, 2026.1.17.php 35-47; `products.parasut_product_id`, 2026.1.18.php 23; `erp_invoices.parasut_*`, 2026.4.4.php 2319-2322) and `config.parasut_company_id`. `commerce/marketplace-outbound-layer.md` says this is not repeated.
- The ERP's e-document drivers (`erp_edoc_drivers()`, one active provider, `erp_invoices.edoc_*`) carry the legal e-invoice; a bookkeeping service there would take Paraşüt's place.
