---
name: erp-invoice-public-link
description: Public address of an invoice PDF (#3743, step 11.0.65, PR #3780): erp_invoices.public_token_hash, erp_invoice.php, token rules, revoke, 404s, WAF; what n11 and Trendyol require of the link
---

## What is it?
- `erp_invoice.php?i=<invoice id>&t=<token>.pdf` (root, URL-called) streams the PDF the invoice was issued with (`erp_archive_invoice()`: the kept copy, or kept on first opening) through `erp_archive_send()`. Nothing else is on the page. Code: `includes/erp/invoice_public_link.php` (loaded by the ERP bootstrap).
- The `.pdf` at the end is there because n11 refuses a link whose file is not named `.pdf`, `.png` or `.jpeg`; `erp_invoice_public_token_clean()` takes it off.

## How is the token kept?
- `erp_invoices.public_token_hash` CHAR(64) DEFAULT '' (2026.11.0 step 11.0.65, `upgrade_2026_11_0_erp_invoice_public_link()`). Empty = no public address.
- Token: `bin2hex(random_bytes(32))`, only its SHA-256 stored. Never an HMAC of a site secret: a rotated secret would kill every link, and Trendyol asks for ten years.
- `erp_invoice_public_link_issue($id)` makes a new token on each need (the automatic push, a "Send invoice link" with the box ticked) and answers the address; only an issued, not cancelled sales invoice gets one. The earlier token stops working (its address cannot be rebuilt from a hash). A send that reached the marketplace but read as failed (timeout) and is sent again leaves the first address dead.
- Revoke: `erp_invoice_public_link_revoke()` clears the hash; button "Take the address back" on the invoice screen (not for read-only ERP users).
- The address is never written to the activity log (it carries the token).

## When is it a 404?
- `erp_invoice_public_refusal()` (pure): missing (deleted), not a sales invoice, draft, cancelled, empty hash, token that does not match (`hash_equals`). All the same bare 404.

## What protects it?
- `waf_sensitive_scripts()` lists `erp_invoice.php` (token guessing allowance, as `cart_quote.php`, `erp_quote.php`). `X-Robots-Tag: noindex`, `no-store`, `no-referrer`, `DENY` framing.

## What do the marketplaces require of the link?
- Trendyol: http(s), at most 1000 bytes (our cap): `commerce/trendyol-invoice-link.md`.
- n11 (developer.n11.com "Fatura Linki Gönderme", WSDL read 2026-10-30): SOAP `SaveLinkSellerInvoice` at `https://api.n11.com/ws/sellerInvoiceService/` (`mp_n11_send_invoice_link()`), keyed by their order number; https only, at most 2048, file named pdf/png/jpeg; one active link per order, a new send replaces it. `invoiceNumber` (3 alphanumerics, year 2020-2099, 9 digits) and `invoiceDateTime` (ms, GMT+3) are optional for domestic orders and sent only when the number has that shape. A site on plain http cannot send n11 a link.

## What is unverified?
- No live call to either marketplace (fixtures only); whether n11 reads the extension from the path or the whole address.

Related: docs/memory/erp/erp-auto-invoice-marketplace.md, docs/memory/erp/erp-document-archive.md
