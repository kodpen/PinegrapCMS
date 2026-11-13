---
name: server-config-iis-webp
description: IIS web-root rule `webp` (mimeMap image/webp): why the panel logo's poster answered 404.3 on IIS, where the block goes, how existing sites get it
---

## Why does IIS need a `.webp` rule?
- Many IIS servers have no `.webp` MIME map, so the static file handler answers every `.webp` with 404.3. Since the animated logo (2026-11-04) `pg_logo_markup()` uses `assets/images/pinegrap-mascot-still.webp` as the video poster and the fallback `<img>` (reduced motion, the live site's image edit button), so every panel page showed a 404 in the console (issue #8623).

## Where is it written?
- Block `webp` in the IIS list of `pg_server_config_blocks()`, `recommended`, anchor `static-content`: inside the one `<staticContent>`, after the `charset` block whose detect is `<staticContent>`. Detect: a `<mimeMap fileExtension=".webp"`.
- Snippet: `<remove fileExtension=".webp" />` then `<mimeMap … mimeType="image/webp" />`; the `<remove>` comes first because a duplicate mimeMap on an IIS that already maps it is a 500.
- The web-root `web.config` in the repo carries the same two lines; `pg_server_config_default('iis')` writes them before `<clientCache>`.

## How does an existing IIS site get it?
- System Status lists "WebP pictures" as missing (recommended) until Fix is pressed, or the installer's repair adds it. Apache and nginx need nothing: their standard MIME lists map `.webp`.
- Tests: `tests/server_config_blocks_test.php` (`test_server_config_blocks_iis_webp_mime_map`).

Related: docs/memory/platform/server-config-web-root-rules.md, docs/memory/platform/server-config-compression-cache.md
