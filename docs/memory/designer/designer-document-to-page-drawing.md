---
name: designer-document-to-page-drawing
description: The drawing of the "From a document" start (`document-to-page.md`): boards F1 what opens it, F2 the form, F3 the sections as rows
---

## Where is the drawing?
- `designer/designer-document-to-page-drawing/document-to-page.html` on `ui/look-sheet/sheet.css`, with `document-to-page-dark.jpg` and `-light.jpg`. FD3 builds boards F1 to F4 and F6, FD4 board F5. Boards F4 to F6: `designer-document-to-page-rows-drawing.md`.
- The kinds and the unsure marks are the reader's (`document-reader.md`), the limits the file reader's (`document-paragraphs.md`); the document on the sheet is an example.

## What opens it? (F1)
- The New design window's row "From a document" (line "A text or a Word file"), after "Canva" and before "ZIP file". A panel screen: it follows the theme.
- On an empty page: the third card of the canvas ("From a document", `designer-first-tour-help-drawing.md` T6) and the command palette's row "Page from a document", listed only while the page is empty.
- From a page the same form and rows open in the designer's own window, head "Page from a document", dark. No button in the page bar, the canvas bar or a panel head (`designer-light-and-simple.md`).

## How is the form put together? (F2)
- Pane head "Start from a document" with one sentence. A box "The text" to paste into (placeholder "Paste the text here"; help: plain text or Markdown, a text copied from Word or a web page keeps its headings and lists, up to 600,000 characters).
- Under it a small drop place: "Or drop a file here", ".docx, .md or .txt · up to 8 MB", "Choose a file" and "From the file manager" (the file manager's picker on documents). A file dropped anywhere on the window is taken.
- One source: a chosen file takes the box's place as a row (name, kind and size, a cross) with "Paste text instead".
- Foot: the line "The text is read on this site. Nothing is sent anywhere.", "Cancel", and "Read", grey until there is text or a file. A paste is read in the browser, a file on the server; nothing is read while typing.
- The form asks for no framework and no style: the page is Bootstrap 5 with the design's variables.

## How are the sections shown? (F3)
- Head: "9 sections", where they came from with the word count ("read from brief.docx · 1,240 words"), an amber pill "2 to check" for the unsure rows, and "Sort it for me" only where Pinegrap AI is on (F5).
- One row a section, in the document's order: the handle, the kind as a list with its icon, the heading in bold over the beginning of its words, how much it holds ("4 cards", "420 words"), an arrow that opens the words, a cross that leaves it out.
- An unsure row has an amber "?" and, in place of its words, the reason in a sentence ("It could be a price list: three of the six lines carry a price."). The reader's best reading is already chosen: "Build the page" never waits for a mark. The pill goes when each such row has had its kind chosen.
- Foot: "Back" (to the form, the text or file kept; reading again throws the rows' changes away and says so first), "Cancel", "Build the page".
- "Build the page" in the New design window leads to the start questions, then the design opens on the page. From an empty page the sections are put on the page at once, as one step of undo.
