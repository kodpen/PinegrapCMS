---
name: designer-document-to-page
description: Plan (owner 2026-11-24, level 3): a page built from a pasted text or a document: headings, lists, questions, figures and quotes read into sections, checked in a list, then built; steps FD1 to FD5
priority: 5
---

## What is asked?
- Owner (2026-11-24): ten more features, in levels, the designer kept simple and quick (the decision part 108; `designer/designer-light-and-simple.md`). This one: a customer sends the words in a document; the page starts from the words.

## What is there today?
- The words are copied into a page one text at a time. The product reads a .docx with its own reader (`platform/office-files.md`), and the sketch plan builds a page from a list of sections (`designer-sketch.md`, SK3).

## What is built?
- "From a document" as a start of the New design window, and for an empty page from its canvas: a box to paste text into, or a .docx, .md or .txt picked from the computer or the file manager.
- The reader, by rules: a heading opens a section; the first heading with its lead becomes the intro; short items led by a few bold words become cards; a question followed by its answer, repeated, a list of questions; lines of a number and a label, figures; a quotation with a name, a testimonial; a table, a table or price columns; an e-mail, a phone number and an address, the contact section. Pictures in a document go to the file manager and to their place.
- Before anything is built, the sections are shown as rows: the kind (a list to change it), its words (to correct), a mark where the reader was unsure, a handle to reorder, a cross to leave one out.
- "Build the page" hands the rows to the sketch plan's builder: sections from the starters, with the design's variables. The words go in as they were written.
- With Pinegrap AI on, "Sort it for me" proposes the kinds and a shorter text for a long passage; each proposal is accepted or left.

## What must it keep?
- The words are never changed unless the person accepts a proposal; nothing leaves the site unless the AI action is pressed.
- A document is read within the own reader's limits (`platform/office-dom-ceiling.md`); beyond them the form says how much was read. Pasted markup is cleaned as the pasted-markup start cleans it.
- The reading is worked out when "Read" is pressed, on the server for a file and in the browser for a paste; nothing runs while typing.

## Which steps? (S under 400, M about 1,000 product lines; tests red first)
- FD1 (M) The reader, pure: paragraphs with their styles in, sections with kinds and unsure marks out. Done (#11533, `designer/document-reader.md`; kinds intro, text, cards, questions, figures, testimonial, table, prices, contact, picture, gallery: FD3 maps them to SK3 starters).
- FD2 (S) A .docx, .md or .txt to paragraphs through the own reader; pictures taken out. Done (#11601, `designer/document-paragraphs.md`; a .md or .txt comes back as text for `PGDocumentReader.paragraphs()`, pictures as a list, `src` `picture:<n>` until FD3 stores them).
- FD3 (M) after FD1, FD2, SK3 and ST2: the start's form, the rows, "Build the page"; boards F1 to F4 and F6 of the drawing. The PR body carries a table of the boards: what was built for each, what differs and why.
- FD4 (S) after FD3: "Sort it for me"; board F5. The PR body carries a table of the boards: what was built for each, what differs and why.
- FD5 (S) Close-out: memory in `designer/`, this file deleted, the `[NEW]` line named.

## What does every step keep?
- No table or column. The drawing: `designer/designer-document-to-page-drawing.md` and `designer-document-to-page-rows-drawing.md`; it decides where the two differ (`workflow/screen-follows-drawing.md`). Claim as `[plan:designer-document-to-page] <step> — <topic>`.
