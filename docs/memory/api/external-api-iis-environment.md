---
name: external-api-iis-environment
description: What IIS and the WAF impose on the external API: path-based API detection, rate buckets outside the sensitive limit, no PATCH/DELETE, POST twins, the webhooks exception, first-match routing
---

## How does waf_is_api_request() detect API requests?
- It asks the running script first, then the request path (`waf_request_names_script()`). `web.config` routes every request that does not match a file on disk to `router.php`; IIS counts `/integration.php/meta` as a file but not `/integration.php/products/138`. So the most-used endpoint shape arrives with `SCRIPT_NAME` = `router.php` and is recognised by its path.
- The FIRST `.php` segment in the path is taken: `/index.php/integration.php/x` answers `index.php` here too; the exemption cannot be forged. Scripts counted as API: `integration.php`, `api.php`, `mcp.php`, `oauth.php`, `workspace_ical.php`, `feed.php`, `marketplace_webhook.php`.

## Which rate limits apply?
- These are outside the WAF's sensitive counter (`waf_handle_rate_sensitive()`): its per-IP 30/min plus auto-ban made the API unusable for an integration doing what it was allowed to.
- `integration.php` (and `mcp.php`) count per application: `api_apps.rate_limit_per_min`, default 120/min, one atomic `INSERT ... ON DUPLICATE KEY UPDATE` per call (`includes/api/ratelimit.php`). A device or an OAuth connection has its own bucket at its application's ceiling (`api_device_rate_hits()`), so one busy phone does not use up everybody's allowance. `api.php`, the site's own endpoint, counts per address (`waf_rate_limit_api`, default 180/min).
- What the sensitive counter looked for is still caught: a failed key or secret writes an offence (`api_offence()`).

## Which HTTP methods fail on IIS?
- PATCH and DELETE are not delivered: WebDAV answers both with 405 before PHP, and removing WebDAV from `web.config` takes the site down with a 500. PATCH has a lasting side effect: IIS then treats that address as a no-longer-existing file, the next POST falls into site routing and the store's 404 page comes back.

## How are routes written for it?
- An update: `'method' => 'POST', 'also_accepts' => array('PATCH')` (other servers may still send PATCH).
- A delete: the `'method' => 'DELETE'` route plus a twin route `POST /x/{id}/delete` whose id ends in `_post` (`products.delete_post`); the MCP server skips `_post` ids, so the action stays one tool.
- Exception: `POST /webhooks/{id}` has been the delete since the first version (`webhooks.delete`, DELETE with `also_accepts` POST). A webhook is updated with `PATCH /webhooks/{id}` or `POST /webhooks/{id}/update`.
- The router takes the FIRST route whose path and method match (`api_route_match()`), so a new route's path must not collide with an older route's `also_accepts`. Path placeholders are typed (an int `{id}` matches digits only), which keeps `/products/inventory` off `/products/{id}`. A path that exists under another verb answers 405 with `Allow`.

Related: `external-api-authorization.md`, `api-endpoint-rules.md`.
