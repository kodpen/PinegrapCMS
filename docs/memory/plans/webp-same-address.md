---
name: webp-same-address
description: Plan (owner request 2026-11-01): a JPEG or PNG keeps its address and its file; a browser that asks for WebP gets a WebP copy there, every other reader the original; steps W1-W10
---

## What is wrong now?
- Outside the designer's Image element (`<picture>`, `designer/responsive-images.md`) a picture is sent as uploaded: texts, widgets and carousels get a `srcset` in its own format only; CSS backgrounds, classic pages, theme CSS, script-set images and other sites get the original.
- Both convert doors change the address: the File Manager's "Convert to WebP" (`pg_explorer_handle_webp()`) and Edit File's replace mode rename the row to `.webp` and delete the original, and a rename rewrites no reference (`platform/file-manager-where-used.md`). Pages, CSS, sent mails, feeds and other sites asking for `photo.jpg` get a 404, so operators do not convert.
- Panel lists (File Manager tiles, the product table) load each original to draw a thumbnail.

## What is proposed?
- The address never changes and the uploaded file stays the master. `get_file.php` answers a JPEG or PNG asked for without `f=` (plain or `?w=`) with its WebP derivative when the request's `Accept` names `image/webp` with q > 0, else with the original as today; the `Content-Type` of what is sent, `Vary: Accept`, download name `<stem>.webp`.
- The copy is an ordinary derivative (`platform/image-derivatives.md`): never heavier than the original, never another pixel size, made once per signature, swept, purged and counted as now. `?f=webp` and `?f=avif` stay; a new `?f=original` always sends the uploaded bytes (File Manager download, mails).
- A reader that does not name `image/webp` keeps the original: mail programs, marketplaces and feeds fetching by URL (`*/*` or no `Accept`); PDF and office code reads the file from disk anyway.
- A cache that ignores `Vary` would give its first copy to everyone. Cloudflare does unless a Cache Rule varies on `Accept`, and dev.pinegrap.com image answers come from its cache (`cf-cache-status: HIT`). A request that came through a CDN or proxy (`CF-Ray`, `CDN-Loop`, `Via`, `X-Amz-Cf-Id`) gets the original until the operator confirms, or Cloudflare Tools checks, that the edge keeps a copy per format.
- One rule for every door: a file's bytes match its name's extension; a stored format change is a new file.
- Every other copy of a picture is a derivative too, never a file row: a 320 px copy for panel lists, Workspace previews in the same cache (W7, W8).

## Which files hold the rest?
- `webp-same-address-steps.md`: W1-W10, their files, tests and order.
- `webp-same-address-options.md`: the options weighed and the outside facts behind the choice.

## Which questions are decided? (owner 2026-11-01, delegated to the architect; #4676 1-6, #4677 7-9; `workflow/decisions-made-product-17.md`)
1. Default: on for a new install; off on a running site until switched on (the upgrade note and System Status say so).
2. Convert doors: the batch becomes "Prepare WebP" (copies, no rename); Edit File keeps only "Create a .webp copy". No door renames a file.
3. Switches: bits 4 (own address) and 8 (CDN confirmed) of `config.image_derivative_formats`, no schema; bit 4 needs bit 1.
4. AVIF at the plain address: not now; `?f=avif` stays.
5. Cloudflare Tools writes and checks the Cache Rule when its token may, else shows the steps; bit 8 is set by a passed check or the operator.
6. Image editor export (E13): another format is saved as a copy; Replace keeps the format.
7. A 320 px rung appended to the derivative widths, used by panel lists (W7).
8. Workspace previews served from the derivative cache with the file's `files` id (W8).
9. The upload stays the master, never its WebP copy; shrink on upload above N px (`config.image_upload_max_dimension`, step `11.0.142`): 2560 on a new install, off on a running site (W9).

## Where does it start?
- W1. Read `platform/image-derivatives.md`, `image-delivery-settings.md`, `get-file-byte-ranges.md` and `get_file.php` from the access checks to the send.
