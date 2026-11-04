---
name: cloudflare-image-vary-rule
description: WebP at the own address through a CDN: Cloudflare Tools Cache Rule (Vary Accept), the token-free self-test (job and button) that sets bit 8 or picks private or original (#5536, #6144)
module: cloudflare_tools, image_vary_check
---

## What rule is written?
- Phase `http_request_cache_settings`, ref `pinegrap_webp_same_address` (`pg_cloudflare_image_vary_rule()`): expression `http.request.uri.path.extension in {"jpg" "jpeg" "jpe" "png"}` (the extensions `get_file.php` negotiates), `set_cache_settings` with `cache: true` and `vary`: `headers.accept` normalize, `media_types` `["image/webp"]`; `default` passthrough (Cloudflare requires `default` once `vary` is there). Normalised Accept is `image/webp` or empty, so each URL has two copies.
- Cloudflare's Vary in Cache Rules works on every plan (2026); before it only Vary for Images (Pro+) existed.

## How is it written?
- `pg_cloudflare_image_vary_plan()` reads the entrypoint GET: 404 → `create` (PUT the entrypoint with the rule), our ref missing → `add` (POST `rulesets/<id>/rules`, appended last, so it wins over earlier rules), ref found but not current (`pg_cloudflare_image_vary_rule_current()`: expression, action, enabled, Accept normalize with image/webp) → `update` (PATCH the rule), current → `none`, read failed → `failed`. Ids are taken only as 32 hex.
- A failed read or write shows Cloudflare's reason; the card's "Add the rule by hand" steps open by themselves when the read failed (token without Zone › Cache Rules › Edit).

## What does the self-test do?
- `includes/fn/image_vary_check.php`, needs no Cloudflare token. `pg_image_vary_check()`: the newest public (`get_access_control_type()` public, not attachment, not withheld) jpg/png whose WebP derivative is made here first, fetched three times at `https://HOSTNAME_SETTING/<name>?pg_vary_check=<16 hex>` (answered public), Accept with WebP, without, with. `pg_image_vary_verdict()`: `ok` (WebP, original, WebP), `shared` (one copy for both), `no_webp`, `not_proxied` (no `CF-Ray`, `X-Amz-Cf-Id`, `Via` or `X-Cache` came back: `pg_image_vary_answer_proxied()`), `unreachable` (not 200 or not `image/*`).
- After `shared`, a second fresh address with `&pg_vary_cache=private` (answered private) is asked with WebP, then without: `pg_image_vary_private_verdict()` `kept` (the second still got WebP) or `honoured`.
- `pg_image_vary_outcome()`: `ok` sets bit 8 (pictures public again); `shared` takes bit 8 back if set, mode `private` (honoured) or `original` (kept); `not_proxied` mode `private`; anything else keeps the last mode. `pg_image_vary_test_run()` applies it, writes `data/cache/img/same_address.json` (mode, verdict, private, bits, time), drops the System Status cache on a change and writes the Site Log.
- When: the general job (`pg_image_vary_test_daily()`, `pg_image_vary_test_due()`) while bits 1+4 are on and 8 off: once when the stored bits differ (switch just turned on, bit 8 taken back), then daily (weekly after `not_proxied`). The card's check button runs the same `pg_image_vary_test_run()`; it is disabled while bit 4 is off. The rule writer stays the one-click way back to edge caching.
- Each test address is fresh, so a CDN ignoring Vary or `private` hands that copy to nobody else.

Pinned by `tests/cloudflare_image_vary_test.php` and `tests/image_negotiation_test.php`.

Related: image-same-address.md, image-same-address-switches.md, cloudflare-tools-failed-reads.md
