---
name: share-card-address
description: The share card (og:image drawn from the title, logo and site name): its address and signature, the order file, the draw under a lock, the 404 cases, the sweep
module: share_card
---

## What is it?
- A 1200 x 630 PNG drawn by the server for a page or item with no share picture of its own: the background colour, the Organization Logo when one is set and readable, the title in at most three lines, the site's name (`TITLE`). Plan: `plans/share-card.md`.
- `includes/fn/share_card.php`, loaded only by `get_share_card.php` and `job.php` (later by the page that prints the address), not by `functions.php`. It draws through `pg_image_draw_document()` (`includes/fn/image_draw.php`).

## How is the title laid out?
- `pg_share_card_wrap()` (pure): words onto a line while the measured width fits, a word wider than a line cut by letters onto its own lines, more than three lines cut with the last ending in `…`. The measure is the bundled Roboto's advances (`pg_share_card_measure()`), the same sums the renderer places letters by.

## What is the address and what does it sign?
- `get_share_card.php?k=<page|record|product>&i=<id>&s=<32 hex>`, a root file so `router.php` does not change. No text travels in it.
- The signature is an HMAC with `ENCRYPTION_KEY` over the kind, the id, the title, the logo's stamp (name, mtime, size), both colours and the site's name: any change of what the card shows is a new address (`pg_share_card_signature()`).
- `pg_share_card_address()` gives a page the address and keeps the title it printed in `cache/share/<kind>-<id>.json`, one entry per language (`pg_share_card_order_put()`, rewritten at most daily for the same title).

## When is the answer 404?
- A bad shape, no order file, no entry with that signature, a signature that no longer matches the site's state (title, logo, colours or name changed: a stale address), or an item not every visitor may open (`pg_share_card_public()`: the page's folder public and not in the Recycle Bin; a record or product also needs the public page that printed it and, for a product, `enabled = 1`).
- A host that cannot draw (`pg_share_card_unable()`: `server` without text drawing, `letters` for letters that need shaping or the bundled font lacks) and a drawing that failed within the hour (`<name>.fail`).

## How is it drawn and kept?
- Once, into `dirname(FILE_DIRECTORY_PATH)/cache/share/<kind>-<id>-<signature>.png`, under a non-blocking lock on `<file>.lock` and one of the image engine's encode slots (`platform/image-derivatives.md`); a held lock or no free slot answers 503 with `Retry-After: 5`. Then served with `Cache-Control: public, max-age=31536000, immutable`; the file's time is touched at most daily when asked for.
- The colours are the settings texts `share_card_background` and `share_card_color` (default `#1f2937` and `#ffffff`).

## How is it swept?
- The general job, once a day (`cache/share/sweep.txt`): cards and order files untouched for 30 days, failure marks, locks and temporary files older than an hour (`pg_share_card_sweep()`).

## How is it tested?
- `tests/share_card_test.php`: the wrap (short, three full lines, a long word, the bundled font), the signature, the address and request shapes, stale and private answers, the document, a host that cannot draw, the paths and the sweep.
