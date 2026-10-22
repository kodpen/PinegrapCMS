---
name: content-translation-html-pieces
description: Translating stored HTML (custom HTML block, product description, form message): how a block is cut into texts so images, form controls, buttons and data-* attributes stay on the translated page
---

## Which HTML goes through this?
Everything `pg_tr_html_segments()` extracts and the finished-page pass `pg_tr_render_db_texts()` draws (`includes/translate/content.php`): custom HTML blocks of a visual page, product and group descriptions and details, form confirmation messages and field texts, a record's rich-text field, the texts of a widget's settings. A content node's own text (heading, paragraph) is the tree renderer's and already holds only the editor's inline tags.

## What is one text?
A leaf block (no block inside it) is one text with its inline tags (`pg_tr_inline_tags()`, the editor's list). An element outside that list cuts it (`pg_tr_inline_pieces()`): an `<img>`, `<input>`, `<button>`, `<iframe>`, `<video>`, any other non-inline tag, and a comment. The stretches between cuts are the texts; the cuts are drawn as they are. An element whose content is not words (`pg_tr_opaque_tags()`: select, textarea, svg, iframe, video, audio, canvas, script, style, pre, ...) is one cut with its content. A stretch whose inline tags do not close inside it (a `<b>` around a button) is cut at those tags too. A stretch with no tag left is a plain `text` segment, otherwise `inline`.

## Why cut instead of storing the block whole?
The inline cleaner (`pg_tr_clean_inline()`) keeps only inline tags and a few attributes. A block stored whole lost every other element from the source text ("Bize yazın Gönder"), and the render pass swapped the block's whole inner HTML for the translation: the translated page had no product image, no required KVKK check box, no submit button (#842, PR #1009). Extraction and drawing call the same `pg_tr_inline_pieces()`, so the hash looked up is the hash stored.

## What about the attributes the cleaner drops?
The store keeps the cleaned tag (`<a href="#x">`); `pg_tr_inline_restore_attrs()` puts the page's own tag back for every tag that lost an attribute (id, data-bs-*, name, role ...), the k-th cleaned tag of the translation getting the k-th raw tag of that form. A collapse link or tooltip keeps working. The kept list is `pg_tr_inline_attrs()` (same as `pg_design_ai_clean_inline()`).
The worded attributes (alt, title, aria-label ...) are texts of their own after the block's texts: `translation-attribute-texts.md`.

## What happened to translations made before?
A block that had no cut keeps its hash. A block that had one is new texts now (the plain parts change format to `text`); the old string loses its use on the next extraction and needs translating again.

## Where is it tested?
`tests/translate_inline_pieces_test.php`: segments, cuts, attribute restore and the render with a stub lookup.
