---
name: guest-shipment-lookup
description: Guest parcel lookup (track_shipment.php, PR #2116): order number + billing e-mail, neutral not-found answer, shipping fields only, 10 lookups/hour per address in waf_rate, how operators place it
module: shipment_lookup
---

## What does it do?
- `track_shipment.php` (URL-called, standalone page like `legal_document.php`): GET shows the form, POST with the session token looks the order up and shows its parcels above the form. Shop off (`ECOMMERCE`) → "This page is not available." 404.
- Pure parts in `includes/fn/shipment_lookup.php`, tested in `tests/shipment_lookup_test.php`.

## How is the order matched?
- `pg_shipment_lookup_order_number()`: spaces and a leading `#` dropped, digits only, at most 20 (BIGINT), leading zeros dropped; anything else is ''. `pg_shipment_lookup_email()`: trimmed, lower-cased, needs a name and a domain, at most 100 characters.
- `pg_shipment_lookup()` reads up to 20 placed orders (`status != 'incomplete'`) with that number and takes the one whose `billing_email_address` matches (`pg_shipment_lookup_matches()`, `hash_equals` on both normalised). A marketplace import may repeat a number, hence several rows.
- A wrong number and a wrong address give the same sentence ("No order was found with this order number and e-mail address."), so the page says nothing about which orders exist. A matched order without parcels says so ("No parcel has been shipped…"): both fields were right then.

## What does the answer show?
- `pg_shipment_lookup_parcels()` keeps only `number`, `carrier_name`, `url`, `status`, `shipped_at`, `delivered_at` per parcel, from `pg_shipment_rows()` through `pg_shipment_view()` (the row's carrier and link first, as on the order screens; `order-view-tracking-link.md`). No prices, items, addresses, names or recipient labels; rows of an address the order does not list are dropped.
- Status text: `pg_shipment_status_label()`; dates in the site's date order (`DATE_FORMAT`).

## How is it limited?
- Every POST that passed the token check is counted with `waf_rate_record()` under scope `shiplookup` per `waf_ip_subject()`, one-hour fixed window, `PG_SHIPMENT_LOOKUP_LIMIT` 10: the 11th is refused with 429 + `Retry-After`, `waf_log_event('rate', 'rate-shipment-lookup', …)` and a `DENY rule=rate-shipment-lookup` line. No offence registered.
- As with the form limits (`waf/form-submission-limits.md`), private/reserved addresses and Trusted Proxies are not counted (`pg_shipment_lookup_address_counted()`): behind an unlisted proxy every visitor would share one bucket. Fails open; works with the firewall off; `waf.php` unchanged.
- The page answers `Cache-Control: private, no-store`; its POST does not mark the page cache dirty (`pg_page_cache_request_dirties()` list).

## How do operators place it?
- Nothing links to it by itself. Step 11.0.45 (no schema change) leaves an `install_note()` naming the address and suggesting a menu item, the footer or the shipping e-mail. No designer token or palette entry was added (that would need `style_designer.js`).
- The Turkish starter's "Kargo Yola Çıktı" campaign profile is left as it is.
