---
name: trendyol-api-facts
description: Trendyol seller API (V2) facts for the connector: hosts, Basic auth + mandatory User-Agent, stage IP whitelist (503), category tree, V2 attributes with separately paged values, limits, test call.
---

## Where were these facts read, and how sure are they?
- developers.trendyol.com (TR and EN Türkiye pages, last modified 2026-04..10), read 2026-10-20. Not one call has been made with real credentials: tests use fixtures shaped after the docs (`tests/fixtures/trendyol/`). The `/v3.0/` pages are Trendyol International (`storeFrontCode` header), not Türkiye.

## Which hosts and paths?
- Live `https://apigw.trendyol.com`, test `https://stageapigw.trendyol.com` (panel `stagepartner.trendyol.com`); stage credentials are separate. Paths start `/integration/`. The old base URL closed from 26 May 2025 (only third-party sources name it `api.trendyol.com/sapigw`). Product V1 was supported until 15 Sep 2026 and order V1 until 15 Oct 2026 (changelog entries of 21.07 and 30.07.2026): use only V2 paths or ones marked "V1-V2".

## How is a request authenticated?
- `Authorization: Basic base64(apiKey:apiSecret)` and `User-Agent: <sellerId> - SelfIntegration` (integrator name alphanumeric, max 30; registered integrators use their own name). No User-Agent → 403. curl takes the header from the list over `CURLOPT_USERAGENT`.
- Seller id is in the path of seller-scoped calls only; categories, attributes and brands are not seller-scoped.
- 401 (`ClientApiAuthenticationException`) = wrong key/secret or wrong environment. 403 = missing User-Agent or wrong endpoint/parameter. Stage answers 503 to a server whose IP is not whitelisted (`/integration/product/sellers/{id}/ip-whitelists`, IPv4, max 7).

## How do ids go into a path or a body?
- Seller id: 1–20 digits (`mp_trendyol_seller_id()`). Package and line ids of the accept and ship calls: `mp_trendyol_id_fits()`, 1–18 digits, no leading zero; a line id is a JSON number, where (int) drops a leading zero and saturates past 18 digits (PR #1675).

## Which call tests the connection?
- `GET /integration/product/sellers/{sellerId}/products/approved?page=0&size=1` (`totalElements`, `content`): seller-scoped, read group, writes nothing. Not the addresses endpoint (was 1 call/hour).

## What do category calls answer?
- Tree: `GET /integration/product/product-categories` → `categories[{id,name,parentId?,subCategories[]}]`; leaf = empty/absent `subCategories`; only leaves take products; refresh about weekly.
- Attributes V2: `GET /integration/product/categories/{id}/attributes` → `categoryAttributes[{attribute{id,name}, required, allowCustom, varianter, slicer, allowMultipleAttributeValues}]`, **without values**.
- Values: `GET .../categories/{id}/attributes/{attrId}/values?page=&size=` (size max 1000) → `{totalPages, content[{attributeValueId, attributeValue}]}`. The page count ends the loop; a short page only when no count.
- `varianter` or `slicer` is stored as `is_variant` (`marketplace-variant-attributes.md`). `allowMultipleAttributeValues` has no column; the V2 create body carries one value per attribute.

## What are the limits?
- 50 requests to one endpoint per 10 s → 429 `too.many.requests`. From 14 Sep 2026 per-minute group limits scale with the listing limit (product read 1000–2000, product write 200–600, inventory/price write 350–2000; 30 price updates/min per barcode).
- The connector spends at most 45 value calls per category (`mp_trendyol_value_call_budget()`); over budget or a 429 midway is an error, nothing half-stored.

## What is the error body?
- Not fully documented. Read `errors[].message`, then `errors[].key`, then `message`, then `exception` (`mp_trendyol_error_message()`).

## What is still unknown?
- Status for a valid key with a wrong seller id (401 or 403). Whether stage has the same category ids as live (the cache is per provider, not per environment).

Related: docs/memory/commerce/trendyol-listings.md, docs/memory/commerce/marketplace-outbound-layer.md
