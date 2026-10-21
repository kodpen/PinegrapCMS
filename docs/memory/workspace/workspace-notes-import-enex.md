---
name: workspace-notes-import-enex
description: Evernote exports (.enex) as notes (notes_enex.php, PR #644): laid out in the stage as Markdown and attachment files, ENML to Markdown, limits and refusals
---

## How does an .enex come in?
- The notes import takes one or more `.enex` files, on their own (not with .md or a .zip). `ws_note_import_stage()` moves them into the stage and `ws_note_enex_stage()` lays them out as an upload of Markdown files: a note is `files/<n>.txt` named `<title>.md` (`ws_note_export_name()`, " (n)" on a clash, `note-N` without a title) with title, created, updated and tags in the front matter; its attachments are `attachments/<n>/<name>` beside it, linked from where the note shows them. Preview, batches and finish are the Markdown import's (workspace-notes-import.md). The source file is removed once read.
- An attachment's stage row has a fifth value `1`: `ws_note_import_index()` never reads it as a note and `ws_note_import_target()` treats a link to it as a file, so a `readme.txt` attached to a note stays its file.
- An attachment keeps the last segment of its export name (`ws_note_enex_file_name()`), cut at the last `/` or `\` by hand: `basename()` on Windows drops a leading "X:" as a drive.

## How is the file read?
- `xml_parse()` 64 KB at a time; an attachment is base64-decoded into its file while it streams, with its MD5 hashed on the way (the `en-media` hash). Past `ws_upload_max_bytes()` it is only counted, so its link becomes words at import, as for a zip.
- Refused as "This is not an Evernote export (.enex).": no `<en-export>` in the first 8 KB; `<!ENTITY` or an internal subset (`<!DOCTYPE … [`) anywhere before the root element, checked chunk by chunk (a long comment cannot hide one; entity expansion filled data/temp before); a root other than `en-export`. A parse error says the line.
- 50000 notes and attachments at most, checked as each `<resource>` opens, so no file is opened past it (a note of countless empty resources created one file each before, #644). A note of more than 16 MB ENML stays in the preview as "too long".

## What does ENML become?
- `ws_note_enex_markdown()`: the DTD is never loaded; named entities are spelt out first, then `loadXML(LIBXML_NONET)`, the HTML parser when the note is not well-formed, plain text last. A `<div>` is a line, an empty one an empty line; `<b>`/`<i>`/`<s>`/`<code>` and bold, italic, line-through styles keep their marks; http(s) and mailto links stay links (evernote:// keeps its words); web pictures of clips become links, data: pictures are dropped.
- `en-todo` and the newer editor's `--en-todo` checklists are `- [ ]`/`- [x]` items; tables are Markdown tables (`|` escaped, colspan padded, cut at 100 columns so a crafted colspan cannot exhaust memory); `<pre>` and `--en-codeblock` divs are fences with their spaces; headings and lists stay Markdown for `ws_note_import_convert()`; `en-crypt` is "[encrypted text]".
- `en-media` is found by hash (a box without one takes the next file); attachments the text never shows, and `<task>`s of the newer editor (as checklist items), go at the end.

## Where are the tests?
- tests/workspace_notes_enex_test.php: dates, names, ENML, the file read back, a streamed export, and the refusals (padded prolog, attachment limit, wide table).
