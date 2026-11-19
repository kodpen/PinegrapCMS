---
name: picture-text-overview
description: Picture text translation as a whole (owner 2026-11-16, built in #10028 to #10269): what it does, its binding decisions, what the first version leaves out and why, where each part is
module: picture_translation
---

## What does it do?
- A Design Canvas picture whose words stand as text layers (or a sticker's label) has them translated with the site; a page opened in another language shows that language's drawn picture in place of the source. Before it, only its alt and title were (`translation-attribute-texts.md`).

## Which decisions bind it?
- Owner (2026-11-17, poll 143, `workflow/decisions-made-product-75.md`): drawn by itself: an open panel page draws (`picture-text-auto-panel.md`), the server with nobody there is still to come (`plans/picture-text-auto-draw.md`).
- Owner (2026-11-16): Design Canvas pictures shown by a page in another language; the source picture shown while a language's is not drawn or out of date; a table of its own (`workflow/decisions-made-product-64.md`, columns `workflow/decisions-made-schema-25.md`).
- Owner (2026-11-17): drawn only once every counted text is translated (`workflow/decisions-made-product-71.md`, poll 131). Looks accepted: the Pictures group (poll 118, `decisions-made-product-70.md`), the Languages list (poll 130), the queue (poll 140, `decisions-made-product-74.md`).
- Architect (2026-11-16, message 6748): no right-to-left language at first; no box or size per language, a text shrinks to the source box down to 70% and is marked "Look by hand" below it (`picture-text-translation-fit-letters.md`); texts as owner type `file`, so a page's same words are one string; only a picture a page has shown is counted, sent and drawn.
- Architect (2026-11-16): a language's picture is a `files` row in the source's folder, found only through `file_translation`, never by name, in no file list.

## Why these ways?
- The server's drawing (`platform/image-server-draw.md`) knows only Latin, Greek and Cyrillic, only fonts it has a file of, no stickers or effects, and no photo document: the browser's renderer draws everything the editor draws, so a browser draws.
- A list in the source's document would cost a JSON decode per picture per page and vanish on a restore or an outside change; a file named beside the source breaks on a rename, move or bin; the table is keyed by file id.
- Right to left: the editor has no text direction and its stage gives each letter its own x, which cuts Arabic joining. Such a language is listed with "Right to left: the source picture is shown." and gets no waiting row.
- A banner nobody shows in another language would cost engine calls: hence only pictures with a `file_translation` row.

## What does the first version leave out?
- A flat picture whose letters are pixels (a photo with no text layer, a scan, an uploaded PNG); the words inside an svg layer; saved animations and videos; pictures in e-mails and RSS; a Workspace canvas (its saves keep no texts).
- Places the page pass does not read: a `<style>` block, the design's custom CSS file, JSON-LD `image`, a variant's photos in the page's script data.
- Right-to-left languages; drawing with nobody there; correcting one language's picture by hand (a later step, the owner's look).

## Which file holds each part?
- The store's texts, the table's states, the hooks, the prune: `picture-text-translation-store.md`.
- The page pass (the swap, waiting rows, one query): `picture-text-translation-page.md`; save-time rows, text jobs, the mark, the keep: `picture-text-auto-rows.md`.
- The Pictures group of the Translations screen: `picture-text-translation-group.md`.
- The Design Canvas Languages list and the fit: `picture-text-translation-draw.md`; its server side, the hidden lists: `picture-text-translation-draw-server.md`; a photo with text layers: `picture-text-translation-draw-photo.md`.
- "Draw the waiting pictures": `picture-text-translation-queue.md`. Moves, deletes, the bin, Disk Usage, the sweep: `picture-text-translation-doors.md`.
- The `[NEW]` changelog line was named by #10208.
