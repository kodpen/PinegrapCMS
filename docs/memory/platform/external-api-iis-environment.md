---
name: external-api-iis-environment
description: Rules imposed by IIS/WAF on the external API: waf_is_api_request() path logic, WAF exemption, no PATCH/DELETE, 422 body keys, POST /products.
---

## How does waf_is_api_request() detect API requests?
- It looks at the request PATH, not the running file. `web.config` routes every request that does not match a file on disk to `router.php`; IIS counts `/integration.php/meta` as a file but not `/integration.php/products/138`. So the most-used endpoint shape arrives with `SCRIPT_NAME` = `router.php`.
- The FIRST `.php` segment in the path is taken: `/index.php/integration.php/x` answers `index.php` here too; the exemption cannot be forged.

## Is integration.php exempt from the WAF?
- Yes, from the WAF's sensitive counter. It has its own limit (per app, default 120/min); per-IP 30/min + auto-ban made it unusable.
- The signal the counter looks for is preserved by `api_offence()`: a request whose AUTHENTICATION failed writes an offence to the WAF.

## Which HTTP methods fail on IIS?
- PATCH and DELETE are not delivered: WebDAV answers both with 405 before PHP. PATCH has a lasting side effect: IIS then treats that address as a no-longer-existing file, the next POST falls into site routing and the store's 404 page comes back. `also_accepts` stays for other servers.

## Other rules
- `api_query()` catches the mysqli exception. Since `init.php` turns reporting off the query returns `false` instead of throwing; the try/catch is a second safety, not the only one. The error-reading path handles both cases.
- An unrecognised field in the body is rejected with 422 (`api_request_body_keys()`). The query string is NOT checked: cache busters, analytics tags and proxy marks land there.
- `POST /products` writes where the screen writes: `pg_pb_create_product()`; there is no second INSERT. `pg_pb_save_submit_form_fields()` takes its source as a parameter (`$_POST` default); `$product['user']` can be given by the caller. A taken name returns 409 (`get_unique_name()` would silently make it `AD[1]`).

Related: docs/memory/core/external-api-authorization.md
