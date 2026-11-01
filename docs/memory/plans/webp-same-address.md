---
name: webp-same-address
description: Plan (owner request 2026-11-01): a JPEG or PNG keeps its address and its file; a browser that asks for WebP gets a WebP copy there, every other reader the original; steps W1-W6
---

## What is wrong now?
- Outside the designer's Image element (`<picture>`, `designer/responsive-images.md`) a picture is sent as uploaded: texts, widgets and carousels get a `srcset` in its own format only; CSS backgrounds, classic pages, theme CSS, script-set images and other sites get the original.
- Both convert doors change the address: the File Manager's "Convert to WebP" (`pg_explorer_handle_webp()`) and Edit File's replace mode rename the row to `.webp` and delete the original, and a rename rewrites no reference (`platform/file-manager-where-used.md`). Pages, CSS, sent mails, feeds and other sites asking for `photo.jpg` get a 404, so operators do not convert.

## What is proposed?
- The address never changes and the uploaded file stays the master. `get_file.php` answers a JPEG or PNG asked for without `f=` (plain or `?w=`) with its WebP derivative when the request's `Accept` names `image/webp` with q > 0, else with the original as today; the `Content-Type` of what is sent, `Vary: Accept`, download name `<stem>.webp`.
- The copy is an ordinary derivative (`platform/image-derivatives.md`): never heavier than the original, never another pixel size, made once per signature, swept, purged and counted as now. `?f=webp` and `?f=avif` stay; a new `?f=original` always sends the uploaded bytes (File Manager download, mails).
- A reader that does not name `image/webp` keeps the original: mail programs, marketplaces and feeds fetching by URL (`*/*` or no `Accept`); PDF and office code reads the file from disk anyway.
- A cache that ignores `Vary` would give its first copy to everyone. Cloudflare does unless a Cache Rule varies on `Accept`, and dev.pinegrap.com image answers come from its cache (`cf-cache-status: HIT`). A request that came through a CDN or proxy (`CF-Ray`, `CDN-Loop`, `Via`, `X-Amz-Cf-Id`) gets the original until the operator confirms, or Cloudflare Tools checks, that the edge keeps a copy per format.
- One rule for every door: a file's bytes match its name's extension. WebP at the same address is the server's answer, never a stored file; a stored format change is a new file (question 6 for the image editor).

## Which files hold the rest?
- `webp-same-address-steps.md`: W1-W6, their files, tests and order.
- `webp-same-address-options.md`: the options weighed and the outside facts behind the choice.

## Which questions are decided? (owner 2026-11-01, delegated to the architect; `workflow/decisions-made-product-16.md`)
1. Default: on for a new install; a running site stays off until switched on, and the upgrade note and the System Status line say so (an upgrade starts no encodes on a shared host).
2. Convert doors: the batch becomes "Prepare WebP" (makes the copies, renames nothing) and Edit File keeps only "Create a .webp copy". No door renames a file.
3. Switches: bits 4 (WebP at the own address) and 8 (the CDN keeps a copy per format) of `config.image_derivative_formats` (TINYINT UNSIGNED; bits 1 and 2 taken), no schema step; bit 4 counts only while bit 1 is on (`pg_image_delivery_settings()`).
4. AVIF at the plain address: not now (slow full-size encodes, Safari does not ask for it, social sites do not read it); `?f=avif` stays.
5. Cloudflare Tools writes the Cache Rule when its token may and checks it; otherwise it shows the steps. Bit 8 is set only by a passed check or by the operator.
6. The image editor's export format (E13, `image-editor-steps-2.md`): a format other than the file's own is saved as a copy; Replace never changes a file's format.

## Where does it start?
- W1. Read `platform/image-derivatives.md`, `image-delivery-settings.md`, `get-file-byte-ranges.md` and `get_file.php` from the access checks to the send.
