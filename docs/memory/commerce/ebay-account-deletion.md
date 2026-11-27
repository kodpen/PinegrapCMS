---
name: ebay-account-deletion
description: ebay_account_deletion.php, the deletion address eBay needs before production keys: challenge, signature and key cache, status codes, WAF entry, the privacy request it records
module: ebay
---

## Why does it exist?
- eBay issues a production keyset only once the application names an address for Marketplace account deletion notices (Alerts & Notifications) and a verification token. Connections › eBay shows both with Copy buttons (`copy_values`, `ebay-connection.md`).
- Token: `pg_ebay_deletion_token()`, HMAC-SHA256 of a fixed label with `ENCRYPTION_KEY`, 64 hex characters; stored nowhere. Address: `pg_ebay_deletion_address()`.

## What does the address answer?
- `pg_ebay_deletion_receive()` (`includes/fn/ebay.php`), called by the root script `ebay_account_deletion.php` (no session).
- 404 while no eBay application is saved for either environment or the site has no encryption key (`pg_ebay_deletion_open()`).
- GET `challenge_code` (1 to 512 characters): 200 JSON `challengeResponse`, the hex SHA-256 of code, token and address in that order (`pg_ebay_challenge_response()`); no code is 400.
- Other than GET and POST: 405. Body over 16 KB: 413.
- POST: `X-EBAY-SIGNATURE` is base64 JSON with `alg`, `kid`, `signature`, `digest` (`pg_ebay_signature_header()`). The key comes from `GET /commerce/notification/v1/public_key/{kid}` with an application token (client credentials, scope `api_scope`), production first then sandbox, cached an hour in the service row `ebay_notification_keys` (10 keys, a day at most).
- A bad header, a key eBay says it does not know (404) or a signature that fails: 412, as eBay's guide asks, and a WAF offence (`waf_register_offence()`, blocks in block mode). eBay could not be asked for the key: 500, so eBay sends the notice again. A valid notice: 204, whatever it is about.
- `pg_ebay_signature_valid()` checks the raw body first, then the body re-encoded without escaped slashes; SHA1 by default, SHA256 when the key says so.

## What is recorded?
- eBay sends every closed account to every application, so only a buyer of this shop is kept. `pg_ebay_buyer_orders()` finds orders imported by an `ebay` marketplace account whose contact's `nickname` is the eBay username (first 50 characters); the order import of a later step writes it there.
- With orders and an address: a privacy request (`core/privacy-requests.md`), kind erasure, source `marketplace`, the address of the latest order, name the username, message naming the order numbers; once per username. A bell line `privacy_request` with `order_total = 'marketplace'` reads "An eBay buyer closed their eBay account" (managers and up).
- Nothing else is kept: a notice about someone who never bought here is answered 204 and dropped. Erasing stays the shop's job with the request's Answer tab and the erase tool.

## Firewall?
- `waf.php`: `ebay_account_deletion.php` is in `waf_is_api_request()` and `waf_country_api_scripts()`: eBay calls from its own servers, so the bot policy and country restriction step aside; `tests/waf_subjects_test.php` holds both lists.

## Tests?
- `tests/ebay_test.php`: challenge hash, header parsing, PEM, both digests against a test key (`tests/fixtures/ebay/public-key.json`), payload, request text. Not tried against eBay's own servers.
