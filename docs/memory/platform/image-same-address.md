---
name: image-same-address
description: JPEG/PNG answered with its WebP derivative at its own address (get_file.php, image_negotiation.php): switch bits, Vary, CDN guard, f=original in downloads and mails, no door renames
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

## Which doors make WebP, and does any rename a picture?
- None renames (owner, question 2 of the plan). File Manager "Prepare WebP" (`pg_explorer_handle_webp()`, JPEG/PNG only, `pg_explorer_webp_source_types()`) makes the cached copy with `pg_image_derivative()` (width 0, webp): no row added, renamed or removed; `busy` says it is made on the first visit. Edit File keeps only "Create a .webp copy", a new `.webp` row beside the picture.
- The file window shows the made copy's size and saving: `pg_image_derivative_cached()` (`image_engine.php`, through `pg_image_derivative_target()`, the plan `pg_image_derivative()` makes from) finds it without making or writing anything.

## Who asks for f=original?
- File Manager downloads (`originalUrl()` in `view_folders.php`): a download is the uploaded file.
- Every mail body: `get_page_content()` with `$email` runs `pg_image_mail_original_urls()` over `src`, `background` and `url()` in `style` attributes, for this site's (`HOSTNAME_SETTING`, with or without `www.`) jpg/jpeg/jpe/png addresses without an `f=`; `<style>` blocks and links stay. Mail programs and image proxies may name WebP and then show or keep the copy.

