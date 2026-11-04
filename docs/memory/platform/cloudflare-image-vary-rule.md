---
name: cloudflare-image-vary-rule
description: WebP at the own address through a CDN: Cloudflare Tools Cache Rule (Vary Accept), the token-free self-test that sets bit 8 only on cache hits, or picks private or original (#5536, #6144, #6667)
module: cloudflare_tools, image_vary_check
---

## What rule is written?
- Phase `http_request_cache_settings`, ref `pinegrap_webp_same_address` (`pg_cloudflare_image_vary_rule()`): expression `http.request.uri.path.extension in {"jpg" "jpeg" "jpe" "png"}` (the extensions `get_file.php` negotiates), `set_cache_settings` with `cache: true` and `vary`: `headers.accept` normalize, `media_types` `["image/webp"]`; `default` passthrough (Cloudflare requires `default` once `vary` is there). Normalised Accept is `image/webp` or empty, so each URL has two copies.
- Cloudflare's Vary in Cache Rules works on every plan (2026); before it only Vary for Images (Pro+) existed.

## How is it written?
- `pg_cloudflare_image_vary_plan()` reads the entrypoint GET: 404 → `create` (PUT the entrypoint with the rule), our ref missing → `add` (POST `rulesets/<id>/rules`, appended last, so it wins over earlier rules), ref found but not current (`pg_cloudflare_image_vary_rule_current()`: expression, action, enabled, Accept normalize with image/webp) → `update` (PATCH the rule), current → `none`, read failed → `failed`. Ids are taken only as 32 hex.
- A failed read or write shows Cloudflare's reason; the card's "Add the rule by hand" steps open by themselves when the read failed (token without Zone › Cache Rules › Edit).

## What does the self-test do?
- `includes/fn/image_vary_check.php`, needs no Cloudflare token. `pg_image_vary_check()`: the newest public (public folder, not attachment, not withheld) jpg/png whose WebP derivative is made here first, fetched four times at `https://HOSTNAME_SETTING/<name>?pg_vary_check=<16 hex>` (answered public): Accept with WebP, without, with, without.
- `pg_image_vary_verdict()`: `ok` only when the types are right (WebP, original, WebP, original) and the last two came out of the edge's cache (`pg_image_vary_answer_hit()`: `CF-Cache-Status` HIT, STALE, UPDATING or REVALIDATED, an `X-Cache` naming a hit, a numeric `Age`); `not_cached` when the types are right without that proof; `shared` (a reader got the other's copy); `no_webp`; `not_proxied` (no `CF-Ray`, `X-Amz-Cf-Id`, `Via` or `X-Cache`: `pg_image_vary_answer_proxied()`); `unreachable` (not 200 or not `image/*`).
- After `shared`, a fresh `&pg_vary_cache=private` address (answered private), WebP then without: `kept` or `honoured` (`pg_image_vary_private_verdict()`).
- `pg_image_vary_outcome()`: `ok` sets bit 8 (public again); `shared` and `not_cached` take bit 8 back if set (`shared` picks mode `private` or `original`, `not_cached` keeps the last mode); `not_proxied` leaves bit 8 as the operator set it, mode `private`. `pg_image_vary_test_run()` applies it, writes `data/cache/img/same_address.json` (mode, verdict, private, bits, time), drops the System Status cache on a change and writes the Site Log.
- When: the general job while bits 1+4 are on, confirmed or not: once when the stored bits differ (the switch or the confirmation changed, by hand too), then daily (weekly after `not_proxied`). The card's check button runs the same test; the rule writer stays the one-click way back to edge caching. Each test address is fresh, so a copy it leaves at the edge reaches nobody else.

## Why must the cache prove it? (#6667)
- On dev.pinegrap.com the server's own requests were answered in the right format without coming out of Cloudflare's cache, so the old three-fetch test said `ok` and set bit 8 while Cloudflare gave its first copy (WebP) to every reader; a confirmation was never tested again, and ticking it off was undone by the next job tick.

Pinned by `tests/cloudflare_image_vary_test.php` and `tests/image_negotiation_test.php`.

Related: image-same-address.md, image-same-address-switches.md, cloudflare-tools-failed-reads.md
