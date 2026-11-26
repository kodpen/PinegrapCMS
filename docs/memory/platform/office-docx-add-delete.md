---
name: office-docx-add-delete
description: Adding a paragraph after another and deleting one in a .docx (office_docx_paragraphs.php, OD3, PR #11811): the bar's buttons, the save entries, the kind a new paragraph takes, the refusals
module: office_docx_paragraphs
---

## How is a paragraph added or deleted?
- The document's format bar (`.office-format`, look A `pg-icon-button`), after the "Paragraph style" select: "Add a paragraph after this one" (`bi-plus-lg`) and "Delete the paragraph" (`bi-trash3`). Both act on the paragraph open or picked; the same controls serve the file window and a channel's preview (`renderDocument()` in `pg_office_view.js`).
- Add puts an empty paragraph after the picked one (same tag: `li` in a list) and opens it to type in. Delete strikes a paragraph of the file through until the save; pressed again ("Keep the paragraph") it stays. An added paragraph that is deleted simply goes. The window's undo drops both.
- A paragraph that may not be deleted says why in the status line (`keep_*` strings); a cell's or the document's last paragraph may go once a paragraph was added after it.

## What does the preview send and the save take?
- `pg_office_docx_read()` with edit sends `edit.keeps` (position => `picture`, `section`, `cell`, `last`, `bookmark`, `locked`) and `edit.shut` (positions nothing may be added after), beside texts, marks and kinds; the HTML is unchanged.
- In the save's `paragraphs` list: `{p, delete: true}` deletes; `{after: p, text, format?, kind?}` adds after paragraph p of the file (text '' when left out). Several after the same p are written in the order listed. Positions are always the file's as opened.
- Order of the save: texts, formats, then added paragraphs (with their own text, format), then deletes from the last up, each checked as the document then stands, then kinds in the order paragraphs now read. Nothing is written when any is refused.

## Which kind does a new paragraph take?
- The paragraph properties of the file's paragraph it follows are copied (`pg_office_docx_paragraph_add()`): its style, numbering, alignment and mark formatting, so a list item continues its list and its text takes the mark's look. Not copied: `sectPr`, `pageBreakBefore`, `pPrChange`, and tracked marks in the mark's rPr. A kind the anchor is being given in the same save is sent for the new one too.

## What is not deleted, and why?
- `pg_office_docx_delete_refusal()`: a section break (`sectPr` in its pPr: the section's page layout), a picture (`drawing`, `pict`, `object`), anything that keeps the text from changing (fields, comments, notes, controls, tracked changes: `office-paragraph-edits.md`), the last paragraph of a table cell (the cell's end mark; no p, tbl or sdt after it) or of the body, and one end of a bookmark or permission range whose other end is in another paragraph.
- Their codes in a save: `delete_picture`, `delete_section`, `delete_cell`, `delete_last`, `delete_bookmark`, `locked`.
- Nothing is added after a paragraph in a content control or custom XML, a text box, a merged-hidden cell, or whose mark is a tracked change (`add_locked`).

## What was checked?
- `tests/office_docx_paragraphs_test.php`: keeps read, the refusal rules, a save adding and deleting read back by the product's reader with untouched paragraphs and parts byte for byte, each refusal leaving the file as it was, and the editor's source. The buttons were tried in Chromium on a built fixture (add, type, refusals, delete, keep, reset, save read back). Not opened in Word or LibreOffice.
- That run found the style select held in `var select`, which, hoisted, replaced `renderDocument()`'s `select()`: a click on a paragraph threw once the bar was drawn (OD2). It is `choice` now; the test checks no `var select` comes back.
