---
name: cloudflare-image-vary-rule
description: Cloudflare Tools card for WebP at the own address: the Cache Rule it writes (Vary Accept normalised to image/webp), the steps, the edge check that sets or takes back bit 8 (#5536)
module: cloudflare_tools
---

## What rule is written?
- Phase `http_request_cache_settings`, ref `pinegrap_webp_same_address` (`pg_cloudflare_image_vary_rule()`): expression `http.request.uri.path.extension in {"jpg" "jpeg" "jpe" "png"}` (the extensions `get_file.php` negotiates), `set_cache_settings` with `cache: true` and `vary`: `headers.accept` normalize, `media_types` `["image/webp"]`; `default` passthrough (Cloudflare requires `default` once `vary` is there). Normalised Accept is `image/webp` or empty, so each URL has two copies.
- Cloudflare's Vary in Cache Rules works on every plan (2026); before it only Vary for Images (Pro+) existed.

## How is it written?
- `pg_cloudflare_image_vary_plan()` reads the entrypoint GET: 404 → `create` (PUT the entrypoint with the rule), our ref missing → `add` (POST `rulesets/<id>/rules`, appended last, so it wins over earlier rules), ref found but not current (`pg_cloudflare_image_vary_rule_current()`: expression, action, enabled, Accept normalize with image/webp) → `update` (PATCH the rule), current → `none`, read failed → `failed`. Ids are taken only as 32 hex.
- A failed read or write shows Cloudflare's reason; the card's "Add the rule by hand" steps open by themselves when the read failed (token without Zone › Cache Rules › Edit).

## What does the check do?
- `pg_cloudflare_image_vary_check()`: the newest public (`get_access_control_type()` public, not attachment, not withheld) jpg/png whose WebP derivative is made here first, fetched three times at `https://HOSTNAME_SETTING/<name>?pg_vary_check=<16 hex>`, Accept with WebP, without, with. `pg_cloudflare_image_vary_verdict()`: `ok` (WebP, original, WebP), `shared` (one copy for both), `no_webp`, `not_proxied` (no `CF-Ray` came back), `unreachable` (not 200 or not `image/*`).
- `get_file.php` negotiates the check address before bit 8 is set (`pg_image_vary_check_asked()` skips the CDN guard); the fresh query is its own cache entry, so a CDN ignoring Vary hands that copy to nobody else.
- `ok` sets bit 8 of `config.image_derivative_formats`; `shared` with bit 8 set takes it back (visitors would get the wrong copy). Both drop the System Status cache and write the Site Log. The check button is disabled while the own-address switch (bit 4) is off.

Pinned by `tests/cloudflare_image_vary_test.php` and `tests/image_negotiation_test.php`.

Related: image-same-address.md, image-same-address-switches.md, cloudflare-tools-failed-reads.md
