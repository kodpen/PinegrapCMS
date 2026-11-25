---
name: document-to-page
description: A page from a document ("From a document" start, "Page from a document"): what it is, what binds it (words never changed, nothing sent before "Sort it for me"), its parts and which file holds each
---

## What is it?
- Owner (2026-11-24): a customer sends the words in a document, so a page starts from the words; one of ten designer features, level 3, kept simple and quick (`workflow/decisions-made-product-108.md`, `designer-light-and-simple.md`).
- A pasted text, or a .docx, .md or .txt from the computer or the file manager, is read into sections, shown as rows to check, then built into a page from the sketch builder's starters with the design's variables. Built in PRs #11533, #11601, #11664 and #11704; the `[NEW]` line came with the close-out.

## Where does it open?
- The New design window's row "From a document"; in the designer, the command palette's "Page from a document" and the empty canvas's third card "From a document", only on an empty page. No bar button (`document-start.md`).

## What binds it?
- The words go in as they were written; they change only when the person accepts a proposal of "Sort it for me". A card's title is its lead, nothing is rewritten by the reader or the builder.
- Nothing leaves the site unless "Sort it for me" is pressed, and that button is there only while Pinegrap AI writes on the site (`document-sort.md`). The form says "The text is read on this site. Nothing is sent anywhere."
- Reading happens when "Read" is pressed: a paste in the browser, a file on the server; nothing runs while typing.
- A document is read within the own Word reader's limits (`platform/office-dom-ceiling.md`); past them the form says how much was read. Pasted markup is cleaned as the pasted-markup start cleans it.
- The reader's best reading is already chosen: "Build the page" never waits for an unsure mark.
- No table or column: pictures go to the file manager when the page is built, the rows live in the browser until then.
- Parts load on first use; the home and the editor pay nothing until the start is chosen.

## Which file holds what?
- The reader (kinds, items, unsure marks, pasted text to paragraphs): `document-reader.md`.
- A file to paragraphs and pictures, limits, how much was read: `document-paragraphs.md`.
- The form, the rows, "Build the page", the stored pictures: `document-start.md`.
- "Sort it for me", its proposals and limits: `document-sort.md`.
- The look: `designer-document-to-page-drawing.md` (F1 to F3) and `designer-document-to-page-rows-drawing.md` (F4 to F6); where they and the text differ, the drawing decides (`workflow/screen-follows-drawing.md`).

## How is a new kind added?
- In the reader's `KINDS` with its rule, `fits()` and its heading words (`WORDS`), then its label in the rows (`_sdT()`, `php tools/tr_add.php`) and its section in `PGDocumentPage.build()`; tests in `tests/document_reader_test.php` and `tests/document_start_test.php`.
