---
name: bug-hunt-areas-document-to-page
description: Bug hunt "Page from a document" (2026-11-26, PR #11769): seven faults fixed (file pick, pictures, words, how much was read), what read clean, two faults left as one task
---

## What was the area?
- "Page from a document" ("From a document" start), named by the architect. PR #11769.
- Files read: `designer_document.php`, `includes/fn/document_start.php`, `document_paragraphs.php`, `document_sort.php`, `includes/designer_parts/document.php`, `assets/js/pg_document_reader.js`, `pg_document_rows.js`, `pg_document_page.js`, `style_designer_document.js`, `designer_document.js`, the Word reader's open and part checks (`includes/fn/office.php`).

## What did it fix?
- A file id posted to `designer_document.php` read any `files` row: a designer read a team chat attachment, a form upload or a binned .docx/.txt. Now only a file the picker's kind `document` offers (`pg_document_start_file_condition()`).
- A picture among a Text section's paragraphs was stored in the file manager but left off the page. Now placed where it stands.
- Pictures of a left-out row, and the intro's picture, were stored though the page shows none. Now only what the page places (`PGDocumentPage.pictures()`).
- Figures under a heading lost the heading: the stats-counter starter has none, and the heading went into the first figure. Now a heading is put over the row.
- A document held in one content control, cut by the paragraph limit, said "about 100% were read". Now the share read.
- An address line holding a phone or e-mail was given twice (the line, and its phone or e-mail again). Now one line.
- The lost pictures note said "each larger than 2 MB" for EMF and WMF drawings too. Now both reasons.

## What was read and found clean?
- Server read: a .docx that is not a zip, or has no `[Content_Types].xml`, is refused; the package's declared sizes are checked before any part is read and parts are read within their limit; a relationship target is folded inside the package; a picture's bytes come only from `word/media/`, under 2 MB, and only when `getimagesizefromstring()` reads them.
- Rights and CSRF: every POST checks the session token with `hash_equals()`; roles above Designer are refused; the kept document's token is per session and checked by shape.
- Escaping: every text into the page is escaped; `alt` and `href` are escaped by the designer's renderer; a picture address is used only when `http(s)://` or `/…`.
- "Sort it for me": nothing posts before it is pressed; a proposal for a row not sent, or a kind outside the row's fits, is dropped on the server.
- Stored pictures go to folder 0, public like the designer's other assets; the token is let go after one store.

## What was seen and left?
- The reader drops words around a reading (a table section's paragraphs, the lines of a questions, figures, cards or contact section that are no item, a card past its word limit), and the intro's picture is never shown. Too large for the hunt's PR; given out as one task after #11769 lands.
- Architect (2026-11-26, agent channel request 3035): both are faults. Nothing of the document is lost between the rows and the built page unless the person leaves a row out. A special section keeps its look: paragraphs before the first item go above the items, the rest under them, in order; an item past a limit goes under them as a paragraph with its lead bold.
- The rows show these words when a row is opened, with no new control or mark. The intro's picture goes in a hero starter with a picture place, else in a picture section right under the intro, and is stored like the others.
- Test that holds it, red first: for each kind, every paragraph index a section read is on the built page exactly once, pictures counted. The PR adds the rule to "What binds it?" in `designer/document-to-page.md` and corrects `document-reader.md` and `document-start.md`.

Related: docs/memory/designer/document-to-page.md, docs/memory/workflow/bug-hunt.md
