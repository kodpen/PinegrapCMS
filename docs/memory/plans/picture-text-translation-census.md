---
name: picture-text-translation-census
description: Picture text translation PT0 census start: where a Design Canvas document with text layers lives, which layer fields hold words, the parts, the questions for the owner
---

## Where are the parts?
- Store, Translations screen, jobs, short words and prices: `picture-text-translation-census-store.md`.
- Every path that writes a picture's address into a page, and what the finished-page pass sees: `-census-places.md`.
- Who can draw a language's picture, a longer text's box, right-to-left: `-census-draw.md`.
- Three ways to keep a language's version: `-census-keep.md`.

## Where does a document with text layers live?
- Always in `file_versions.doc_json` of the file (`ui/image-editor-versions.md`); no other table holds one. The live document is the newest row's, and only while the file is byte for byte that row's pixels (`pg_file_versions_open_state()`); a file changed outside the editor (rotate, Optimize, an upload over it) opens flat, its layers gone from the live picture.
- A photo (File Manager, Page Design Studio button, product picture): a save row over a base row (`source.base`); the server draws the pixels from the base, the browser sends the layers as an overlay (`platform/image-editor-save.md`).
- A canvas in Files ("New image"): a save row with `artboard`, source `new` or `saved` (`ui/image-editor-canvas-save.md`).
- A Workspace canvas: the same row on the message's or note's file (`ws_canvas_keep()`); an agent's has `picture: pending|server` (`workspace/agent-canvas-picture.md`).
- Not kept: a document over 1 MB or with a `data:` picture: the row is a base row, layers flattened. Such a picture has no texts to read.
- Only the original and the last 10 saves stay; restoring an older version makes its document live again.

## Which layer fields hold words?
- Text layer: `text` only (one string, lines split by a new line). `font`, `size`, `align` and the box are style.
- Sticker: `label` (system fonts, `ui/image-editor-sticker.md`).
- svg layer of kind `text`: its words are inside `markup`; the panel edits a text element without `tspan` (`ui/image-editor-svg.md`). An agent's chart's title, labels and note arrive this way (`workspace/agent-canvas-chart.md`).
- Glyph (icon, emoji) and every other layer: none.
- So a field per layer means `text` for a text, `label` for a sticker; svg text needs its own reader (left out at first?).

## What are the questions for the owner?
1. Where is a language's version kept: a table of its own (schema), a list in the source's document, or a file named beside it (`-census-keep.md`)?
2. While a language's picture is out of date (the source saved with other words): does a visitor see the older translated picture or the source picture?
3. Is a picture drawn by a person at the screen (the Design Canvas, or a browser of a signed-in editor) enough at first, or must it be drawn with nobody there (the server: only Latin, Greek, Cyrillic, only fonts it has a file of, no stickers or effects)?
4. Which pictures enter the translation: every picture whose document has text layers, only those a page in another language shows, or only those marked (a switch in the Design Canvas)? Each text sent to an engine costs.
5. Is a right-to-left language (Arabic, Hebrew) in the first version, with the alignment mirrored, or later?
6. May a text get a size or box for one language only (T2), or does a longer text always shrink to the source box?
7. Every new screen's look (the languages list in the Design Canvas, a Pictures group on the Translations screen).
