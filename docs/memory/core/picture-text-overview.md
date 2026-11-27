---
name: picture-text-overview
description: Picture text translation as a whole (owner 2026-11-16, #10028 to #10448): what it does, its binding decisions, what it leaves out and why, where each part is
module: picture_translation
---

## What does it do?
- An Artifact Canvas picture whose words stand as text layers (or a sticker's label) has them translated with the site; a page opened in another language shows that language's drawn picture in place of the source. Its alt and title are translated apart (`translation-attribute-texts.md`).
- Each language's picture is drawn by itself once its texts are translated (`picture-text-auto-draw.md`).

## Which decisions bind it?
- Owner (2026-11-17, poll 143, `workflow/decisions-made-product-75.md`): drawn by itself, by an open panel or, with nobody there, by the server (`picture-text-auto-draw.md`).
- Owner (2026-11-16): Artifact Canvas pictures shown by a page in another language; the source picture shown while a language's is not drawn or out of date; a table of its own (`workflow/decisions-made-product-64.md`, columns `workflow/decisions-made-schema-25.md`).
- Owner (2026-11-17): drawn only once every counted text is translated (`workflow/decisions-made-product-71.md`, poll 131). Looks accepted: the Pictures group (poll 118, `decisions-made-product-70.md`), the Languages list (poll 130), the queue (poll 140, `decisions-made-product-74.md`).
- Owner (2026-11-26, poll 229, `decisions-made-product-113.md`): a longer translation grows into the free room, wraps, then shrinks to its floor, marked "Look by hand" below it; one language's texts are corrected by hand.
- Architect (2026-11-16, message 6748): no right-to-left language at first; texts as owner type `file`, so a page's same words are one string; only a picture a page has shown is counted, sent and drawn.
- Architect (2026-11-16): a language's picture is a `files` row in the source's folder, found only through `file_translation`, never by name, in no file list.

## Why these ways?
- A browser's renderer draws everything the editor draws, so a browser draws first; the server (`platform/image-server-draw.md`) draws only a canvas its verdict passes whole (`picture-text-auto-server.md`).
- A list in the source's document would cost a JSON decode per picture per page and vanish on a restore; a name beside the source breaks on a rename, move or bin; the table is keyed by file id.
- Right to left: the editor has no text direction and its stage places each letter, which cuts Arabic joining; no waiting row.

## What does it leave out?
- A flat picture whose letters are pixels (a photo with no text layer, a scan, a PNG); words in an svg layer; saved animations and videos; pictures in e-mails and RSS; a Workspace canvas (its saves keep no texts).
- Places the page pass does not read: a `<style>` block, the design's custom CSS, JSON-LD `image`, a variant's photos in script data.
- Right-to-left languages; other words, font or colour for one language (`picture-text-fit-correct.md`).

## Which file holds each part?
- The store's texts, the table's states, the hooks, the prune: `picture-text-translation-store.md`.
- The page pass (the swap, waiting rows, one query): `picture-text-translation-page.md`.
- The automatic draw: `picture-text-auto-draw.md`; rows, jobs, mark, keep: `picture-text-auto-rows.md`; the panel's draw: `picture-text-auto-panel.md`; the server's: `picture-text-auto-server.md`.
- The Pictures group of the Translations screen: `picture-text-translation-group.md`.
- The Languages list: `picture-text-translation-draw.md`; the fit: `picture-text-translation-fit-letters.md`, `picture-text-fit-room.md`, `picture-text-fit-wrap.md`, `picture-text-fit-correct.md`, `picture-text-fit-view.md`; its server side, the hidden lists: `picture-text-translation-draw-server.md`; a photo with text layers: `picture-text-translation-draw-photo.md`.
- "Draw the waiting pictures": `picture-text-translation-queue.md`. Moves, deletes, the bin, Disk Usage, the sweep: `picture-text-translation-doors.md`.
- The `[NEW]` changelog line: named by #10208, reworded by #10474.
