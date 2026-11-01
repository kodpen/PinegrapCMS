---
name: webp-same-address-options
description: Research of 2026-11-01 behind plans/webp-same-address.md: the options weighed for WebP without breaking addresses, and the outside facts (browsers, mail, social sites, marketplaces, Cloudflare)
---

## Which options were weighed?
- A. Rename everywhere (`photo.jpg` to `photo.webp`, references rewritten): only what the site stores can be rewritten (Where used reads about a dozen sources by name, 40 hits each; a name sits inside longer ones, `photo.jpg` in `old-photo.jpg`), never sent mails, fetched feeds, marketplace listings, other sites or image search. Each old name needs a 301, and a 301 to WebP still fails readers without WebP; an editor left open saves the old text back. At most a later tidy-up tool.
- B. WebP bytes under the old name, for everyone: browsers cope (they read the bytes and `Content-Type`; a file short link named `.exe` serves a picture), readers without WebP break, and code trusting the extension goes wrong: `get_file.php`'s type map, `pg_img_file_name()`, `pg_image_derivative_source_ok()`, the feeds' filters (`product_feeds.php`: OpenAI JPEG or PNG), `files.type`, the colour-profile plan by extension. The original is lost (lossy).
- C. Keep the file, send WebP to readers that ask (`Accept`, `Vary: Accept`): chosen. Reaches every address (texts, CSS, scripts, other sites), leaves every other reader on the original, one switch turns it off.
- D. `<picture>` everywhere: done for the Image element; elsewhere left out on purpose (scripts swap `src`, CSS targets `> img`); cannot reach CSS or outside links.
- E. Conversion at the CDN (Cloudflare Polish, Pro and up): outside the product; works beside C.
- F. A WebP master, readers without WebP sent a JPEG or PNG made from it: saves disk, which resize and optimize already do safely; later, if disk becomes the problem.

## What do browsers send?
- `<img>` Accept (MDN): Chrome and Edge `image/avif,image/webp,image/apng,image/*,*/*;q=0.8`; Firefox 128+ `image/avif,image/webp,...`; Safari since Big Sur `image/webp,...`, no AVIF. Chrome's page loads name both too, so a download by the plain address would get WebP: hence `f=original`.

## Who cannot show WebP?
- Mail: Outlook for Windows shows a broken image; Gmail turns WebP into JPEG and loses transparency (black behind a logo) (caniemail, Ghost forum 42334, james.cridland.net 2024). Gmail's image proxy sent no `Accept` in a 2013 capture.
- Social previews read WebP, not AVIF (joost.blog, updated 2026-05).
- Trendyol prefers JPEG; the OpenAI product feed names JPEG or PNG.

## What do caches do?
- Cloudflare: Vary values other than `Accept-Encoding` were not considered, except Vary for Images (Pro and up); since 2026-07-02 a Cache Rule varies on `Accept` on every plan (normalize, media types). Without one the first copy goes to everyone.
- dev.pinegrap.com, 2026-11-01: a PNG answered `cf-cache-status: HIT`, age two days, `public, max-age=604800`, no `Vary`; `?f=webp` of that 6 KB PNG sent the PNG itself.
- nginx and Varnish keep a copy per `Vary` value.
