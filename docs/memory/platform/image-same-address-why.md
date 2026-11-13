---
name: image-same-address-why
description: Why a JPEG/PNG keeps its address and file and WebP is negotiated there: options weighed, outside facts, owner decisions, the bytes-match-extension rule
---

## What rule came out of it?
- A file's bytes match its name's extension; a stored format change is a new file. No door renames a picture to change its format (File Manager "Prepare WebP" and the Design Canvas make copies, Replace keeps the format).
- The upload is the master, never its WebP copy. Every other copy of a picture (WebP at the own address, panel thumbnails at 320 px, Workspace previews) is a derivative in `data/cache/img`, never a file row (`image-derivatives.md`).

## Which options were weighed? (2026-11-01)
- Rename everywhere (`photo.jpg` → `photo.webp`, references rewritten): reaches only what the site stores, never sent mails, feeds, marketplace listings, other sites or image search; a name sits inside longer ones; a 301 to WebP still fails readers without WebP.
- WebP bytes under the old name for everyone: readers without WebP break, and code trusting the extension goes wrong (`get_file.php`'s type map, `files.type`, feed filters); the original is lost.
- Negotiate at the same address (`Accept`, `Vary: Accept`): chosen. Reaches texts, CSS, scripts and other sites; every other reader keeps the original; one switch turns it off.
- `<picture>` everywhere: done for the designer's Image element only; cannot reach CSS or outside links.
- CDN conversion (Cloudflare Polish, Pro+): outside the product, works beside it. A WebP master: later, only if disk becomes the problem.

## What outside facts does it rest on?
- Browsers name `image/webp` in an `<img>` Accept (Chrome, Edge, Firefox, Safari since Big Sur); Chrome's page loads do too, so a download by the plain address would get WebP: hence `f=original` in downloads and mails.
- Outlook for Windows shows WebP broken; Gmail turns it into JPEG and loses transparency. Social previews read WebP, not AVIF. Trendyol prefers JPEG; the OpenAI product feed names JPEG or PNG. These readers send `*/*` or no Accept, so they keep the original.
- Caches: nginx and Varnish keep a copy per `Vary` value; Cloudflare ignores Vary values other than Accept-Encoding unless a Cache Rule varies on Accept (every plan since 2026), so the CDN guard and the Cloudflare check exist (`cloudflare-image-vary-rule.md`).

## Which decisions did the owner make? (#4676, #4677; `workflow/decisions-made-product-17.md`)
- On for a new install, off on a running site; switches are bits 4 and 8 of `config.image_derivative_formats`, no schema (`image-same-address-switches.md`).
- No door renames; AVIF at the plain address not now (`?f=avif` stays); Cloudflare Tools writes and checks the Cache Rule.
- A 320 px rung for panel lists (`image-panel-thumbnails.md`); Workspace previews from the derivative cache (`api/api-file-endpoints.md`); shrink on upload above N px, 2560 on a new install, off on a running site (`image-upload-cap.md`).

Related: image-same-address.md, image-derivatives.md
