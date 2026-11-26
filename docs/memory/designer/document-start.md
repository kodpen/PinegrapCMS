---
name: document-start
description: The "From a document" start as built (FD3): where it opens, the form, the rows and their kind list, Build the page, designer_document.php, the pictures, the files, the cost
module: document_start
---

## Where does it open?
- The New design window's row "From a document" ("A text or a Word file"), after Canva, before ZIP file (`pg_designer_start_kinds()`); its pane's `data-part="document"` makes `designer_home.js` load `designer_document.js` on first choice, which loads the reader and the rows.
- In the designer: the command palette's "Page from a document", listed only on an empty page of a full-access editor (`_sdDocumentReady()`), opens the same form in the designer's own window (part `document`). No bar button.
- The empty canvas's third card "From a document" (`designer-first-tour-help.md`) shows while `_sdDocumentReady()` and calls `_sdDocumentOpen()`.

## How are the form and the rows put together?
- `PGDocumentRows.mount({box, foot, texts, reader, readFile, pickFile, build, cancel, head, dropArea, css})` draws both, in the home pane and the editor window alike; the texts come from `pg_document_start_texts()` (the pane's `data-texts`, or `designer_document.php?texts=1` for the editor), so one list is translated.
- The form: the box "The text", the drop place with "Choose a file" and "From the file manager" (picker kind `document`: docx, md, txt, role 2), one source at a time, "Read" grey until there is text or a file. A paste is read in the browser, a file by `designer_document.php`; a file id only when the picker offers it (`pg_document_start_file_condition()`, PR #11769).
- The rows: kind list, heading over the words' beginning, the count, an arrow opening the words as fields, a cross ("Put back" once left out), a handle (drag or arrow keys, read out). An unsure row shows the amber "?" and the reason until a kind is chosen; "Build the page" never waits for it.
- The kind list offers only `PGDocumentReader.fits(paragraphs, section)`: the row's kind, the other readings of its words, then text. An unsure row lists the reader's options under "It could be", the rest under "Something else".
- "Back" keeps the text or file and the rows; "Read" after a change first warns that the changes go, and reads on the second press.

## What does "Build the page" do?
- In the New design window: the rows go to this tab's `sessionStorage` (`pg_document_build`), the start questions are asked (`window.sdStartAsk`), and `add_system_style.php?framework=bootstrap5&start=document` opens; the editor (`sdDesign.startDocument`) runs the part's `auto()`, which builds once into the empty first page.
- In the editor window: the sections are put on the page at once, one undo step (`_sdDocumentHost().insert`).
- `PGDocumentPage.build(rows, {make, node, src})` (builder from part `sketchbuild`): intro, text, cards, picture and gallery through `PGSketchBuilder` (`sketch-builder.md`); figures `stats-counter`, questions `faq-twocol`; testimonial, table, prices and contact plain Bootstrap 5 sections. The words fill the starters' columns, samples out; a headless starter gets the heading over its row; a Text's pictures stand among its paragraphs. Every section wears `var(--pg-var-space-section, 3rem)`.

## Where do a Word document's pictures go?
- `designer_document.php` keeps a document with pictures in `data/temp/documents/<token>.docx` (per session, 3 at most, swept after 6 h), answering the pictures without their package part. At build time the editor posts `keep` with the numbers the page places (`PGDocumentPage.pictures()`, no intro picture); `pg_document_start_store()` writes each to the file manager's top folder and answers `n => address`. A pasted text's own picture address is used only when it is `http(s)://` or `/…`.

## What did it cost, and where is it tested?
- Core: the palette row, the stub, the host and the start hook; the home loads nothing until then.
- `tests/document_start_test.php` (refusals, file condition, answer, texts, rows, page in Node), `tests/document_reader_test.php` (`fits`).
