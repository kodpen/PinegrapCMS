---
name: image-same-address
description: A JPEG or PNG answered with its WebP derivative at its own address (get_file.php, includes/image_negotiation.php): the switch bits, who gets WebP, Vary, CDN guard, f=original, download name
---

## What does get_file.php do at a picture's own address?
- A jpg/jpeg/jpe/png asked for without `f=` (plain or `?w=`) is answered with its WebP derivative when the switch is on and the request's `Accept` names `image/webp` with q > 0; at `?w=` the WebP is made at that width, plain at the picture's own width. Every other reader gets what it got before. The bytes come from `pg_image_derivative()` (`image-derivatives.md`): same cache, signature, sweep and refusals; a refused or no-gain copy sends the original.
- `Content-Type` follows what is sent; the download name is `<stem>.webp` (`pg_image_negotiated_download_name()`), at `?w=` the derivative's `<stem>-<width>.webp` as before.
- Never for ERP documents, workspace files or attachments (they get no derivatives), nor when `f=` is set: `f=webp`/`f=avif` name their own answer, `f=original` skips every derivative (`?w=` included) and sends the uploaded bytes.

## Which function decides?
- `includes/image_negotiation.php`, gate `PG_FUNCTIONS_DIR` or `PG_GET_FILE`, pure, tested in `tests/image_negotiation_test.php`: `pg_image_accepts($accept, $type)` (first entry for the type decides; `q=0` and a non-numeric q refuse; `image/*` and `*/*` never count, every mail program and feed reader sends them), `pg_image_behind_proxy($server)`, `pg_image_same_address_settings($row)`, `pg_image_original_asked($query)`, `pg_image_negotiated_format($row, $extension, $query, $server)` → `format` (`webp` or ''), `vary`.
- It reads `$router_config_row` (the router's `SELECT * FROM config`): no extra query.

## Which switches?
- Bits of `config.image_derivative_formats` (no schema): 4 = answer the own address with WebP, needs 1 (WebP offered), so the switch is on only when `bits & 5 == 5`; 8 = the CDN in front keeps a copy per format. Nothing sets them before the settings step: a site sends exactly what it sent before, no `Vary` either.

## When does Vary: Accept go out?
- On every answer while the switch is on for a negotiable file, the original and 304 included, appended (`header(..., false)`) so another layer's `Vary` stays.

## Why the original through a CDN?
- A cache that ignores `Vary` gives its first copy to everyone; Cloudflare does unless a Cache Rule varies on `Accept`. A request carrying `CF-Ray`, `CDN-Loop`, `Via` or `X-Amz-Cf-Id` gets the original (still with `Vary`) until bit 8 is set (`page-cache-proxy-rules.md`).
