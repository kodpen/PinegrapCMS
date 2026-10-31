---
name: erp-quote-guest-link
description: Sales quote guest link (#3761, 2026.11.0 step 11.0.64): erp_quote_links, erp_quote.php, one live link, expiry, acceptance through erp_signatures and erp_quote_set_status(), WAF, e-mail
---

## What is it? (decision #3671, built #3761)
- A customer without an account opens a sales quote by link (`erp_quote.php?t=`), reads it and accepts it with a drawn signature. No payment or collection on the page (erp-not-built-on-purpose.md).
- Code: `includes/erp/quote_links.php` (loaded by the ERP bootstrap), page `erp_quote.php` (root, URL-called), buttons on `edit_erp_quote.php`.

## What does the table keep? (`erp_quote_links`, `upgrade_2026_11_0_erp_quote_links()`, in `get_tables()`)
- `quote_id`, `token_hash` CHAR(64) UNIQUE (SHA-256 of the token), `token_hint` (first 6 characters), `expires_at`, `used_at`, `revoked_at`, `created_at`, `created_by`; unix seconds, 0 = not yet. Rows are never deleted.
- Token from `pg_short_link_new_token()`, shown once (screen notice or the e-mail), never stored.

## Which rules hold?
- One live link: `erp_quote_link_issue()` calls `erp_quote_link_revoke()` (sets `revoked_at` on live rows) before the new row. Only an open quote gets a link.
- `expires_at` = end of the quote's `valid_until` day at issue (`erp_quote_link_expires_at()`).
- `erp_quote_link_state()` (pure but for today): revoked, used, expired, accepted (quote accepted another way), closed (`erp_quote_state()` not open or `erp_quote_status_refusal($quote, 'accepted')` refuses: expired, invoiced, cancelled, rejected), open. Asked on every open of the page.
- Lookup `erp_quote_link_for_token()`: shape check, then by hash with `hash_equals`. Unknown, wrong and revoked tokens get the same neutral 404 page.

## How is a quote accepted?
- `erp_quote_link_accept()`: state must be open, the refusal function asked, the link claimed (`used_at`, `WHERE used_at = 0 AND revoked_at = 0`, one affected row) so a double submit does nothing; `erp_signature_store('quote', …, user_id 0)` with `erp_signature_document_hash_quote()`; a refused store releases the claim. Then `erp_quote_set_status(…, 'accepted', 0)` (panel writer, `erp.quote.accepted`) and `erp.quote.signed`. Never a bare status write.
- The panel's signature card says "through the guest link" for a signature with `user_id` 0.

## What does the page show on each line? (#3942, PR #3985)
- Description, quantity, unit price, discount, VAT % and the line total after the discount with VAT, from `erp_quote_line_amounts()` (`includes/erp/quotes.php`), which the panel's read-only quote uses too; a note under the table says so. The line totals add up to subtotal − discount + VAT printed below.
- The signing note reads "adi elektronik imza" in Turkish, as on the panel's signature card and the signature page.

## What protects the page?
- `waf_sensitive_scripts()` lists `erp_quote.php` beside `cart_quote.php` (30/min allowance, token guessing). `noindex`, `no-store`, `no-referrer`, `DENY` framing; GET changes nothing (mail scanners); POST needs the form token (`validate_token_field()`). Styled by `assets/css/cart_quote.css`.

## How does the link reach the customer?
- Edit Sales Quote card "Guest link": Create guest link (URL shown once with Copy, then the hint and end date), Revoke.
- The e-mail form's optional switch "Add a guest link…" issues a new link and puts it in the quote e-mail (`erp_quote_mail_send(…, $link_url)`); the address is the quote account's invoice/account e-mail as before.

## Not built
- No API route for links; no list of past links on the screen (the rows are there).
