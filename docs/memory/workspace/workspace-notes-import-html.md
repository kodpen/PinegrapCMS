---
name: workspace-notes-import-html
description: One web page (.html/.htm) as a note (notes_html.php, PR #1776): laid out in the stage like the Evernote reader, HTML parser only, what is dropped, embedded data: pictures as attachments
---

## How does a web page come in?
- The notes import takes one `.html` or `.htm` file, alone (with other files: "Upload one web page (.html) on its own…"); 16 MB at most (`WS_NOTE_HTML_MAX`, the parser holds the page and its pictures at once). `ws_note_import_stage()` moves it to `source.html` and `ws_note_html_stage()` writes `files/0.txt` named `<title>.md` with title, created and updated in the front matter (`ws_note_enex_file()`), its pictures as `attachments/1/<name>` rows with the fifth value `1`. Preview, batches and finish are the Markdown import's (workspace-notes-import.md).
- Title: `og:title`, else `<title>`, else the first `<h1>`, else the uploaded file's name. Dates: `article:published_time`, `date`, `dcterms.created`… and `article:modified_time`, `dcterms.modified`… through `ws_note_import_time()`. The canonical link (else `og:url`), http(s) only, is a link at the end.

## How is the page read, and what never survives?
- Character set: BOM, then `<meta charset>` / http-equiv (Latin-1 read as Windows-1252), else `ws_note_import_text()`; the declaration is rewritten to UTF-8 so libxml does not decode twice.
- `loadHTML()` with `LIBXML_NONET` only, never `loadXML()`: a document type's entities are not expanded and no external entity is read (tested).
- Taken out before reading: head, script, style, noscript, template, frames, object/embed, svg, math, canvas, media, nav, form controls (text inputs, button, select, textarea), `hidden`, `aria-hidden="true"`, `display:none`, `visibility:hidden`, and every `en-*` element of the page. An older libxml nests the rest of a parent inside an unknown void element (`<embed>`, `<source>`): those are unwrapped, not removed with their "children".
- `<input type=checkbox>` becomes `en-todo`, so an exported checklist is `- [ ]`/`- [x]`. Content root: `<main>`, else a single `<article>`, else body.
- The words go through `ws_note_enex_markdown_node()` (the ENML walker): same marks, links (http, https, mailto only), web pictures as links, headings and lists left for `ws_note_import_convert()`. No attribute is ever written.

## What happens to pictures inside the page?
- `data:image/png|jpeg|gif|webp;base64` whose decoded bytes carry that type's signature become `image-N.<ext>` attachments, shown where the page shows them (replaced by an `en-media` with the MD5). SVG, other types, broken base64 or bytes of another kind are dropped. One past `ws_upload_max_bytes()`, or past `WS_NOTE_IMPORT_FILES_BYTES` in all, is dropped; the same picture twice is one file. Relative `src` (an export folder's files) cannot be reached from one file and is dropped.

## Where are the tests?
- tests/workspace_notes_html_test.php: charset, a clip, an export, hostile markup, refusals, embedded pictures, the stage read back. The hook in `ws_note_import_stage()` needs a real upload and is not unit-tested.
