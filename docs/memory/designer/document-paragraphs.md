---
name: document-paragraphs
description: A .docx, .md or .txt turned into the paragraphs the designer's document reader takes (includes/fn/document_paragraphs.php): styles, bold leads, pictures taken out, limits, how much was read
module: document_paragraphs
---

## What does it give?
- `pg_document_paragraphs($path, $name)` answers `{ok, error, paragraphs, text, pictures, pictures_lost, cut, read}`. A .docx gives `paragraphs` in the shape `PGDocumentReader.read()` takes (`document-reader.md`); a .md, .markdown or .txt gives `text` (UTF-8, byte order mark left out, Windows-1254 converted), which the browser turns into paragraphs with `PGDocumentReader.paragraphs()` as it does a paste, so the Markdown rules stay in one place (PR #11601).
- A paragraph: `{text, style}` with `style` heading (and `level` 1-6), item, quote, table (`rows`, arrays of cell texts), picture or paragraph; `lead` the bold words it starts with, when more follows. Empty paragraphs, deleted tracked text and text boxes inside drawings are left out; breaks and tabs are spaces.

## How is a .docx read?
- Through the product's own Word reader (`platform/office-files.md`): `pg_office_open()`, `word/document.xml` by `pg_office_xml(..., true)` within 8 MB (`platform/office-dom-ceiling.md`), numbering, styles and relationships by `pg_office_docx_state()`; a heading by its style's built-in name (`heading N`, whatever the id), else `pg_office_docx_block_tag()`; a list item by its numbering or its style's. Content controls are read through; table cells give their text.

## Where do the pictures go?
- Taken out: each png, jpg, gif or webp in a paragraph's drawing is listed in `pictures` (`part`, `name`, `mime`, `size`, `alt` from the drawing's description) and becomes a picture paragraph after its text, `src` `picture:<n>`, `picture` n. The step that builds the page stores it and replaces `src`; `pg_document_picture($path, $part)` gives its bytes, only from `word/media/`, under 2 MB, and only when they are a picture.
- Past the preview's picture limits (2 MB one, 8 MB together) or of another kind (emf, wmf, bmp) a picture is counted in `pictures_lost`, not listed. Pictures inside table cells are not taken out.

## What are the limits, and how much was read?
- 2,000 paragraphs (the designer reader's own limit) and 600,000 characters of text; a .md or .txt 1 MB, cut back to a line's end.
- `cut`: '' all read, 'size' (main part past 8 MB, or the text file past 1 MB), 'paragraphs' (a count limit). `read.paragraphs` the paragraphs given, `read.percent` about how much of the document they are: the share of the main part loaded times the share of its blocks walked, a content control counted by its own share (PR #11769: a document in one control said 100%). The form words it ("The first N paragraphs, about P%, were read").

## Where is it loaded and tested?
- Not by `functions.php`: the door that reads the file (`designer_document.php`, `document-start.md`) requires it, so no request pays for it otherwise. `tests/document_paragraphs_test.php` (styles, pictures, limits, text files, refusals, and the paragraphs read by `PGDocumentReader.read()` in Node).
- Timing (PHP 8.3, 4 cores, median): 200 paragraphs (30 KB) 1.7 ms, 1,560 (238 KB) 11 ms, a 9.3 MB main part 205 ms (cut at 8 MB, 2,000 paragraphs, 3%).
