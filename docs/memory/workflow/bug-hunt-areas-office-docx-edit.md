---
name: bug-hunt-areas-office-docx-edit
description: Bug hunt of Word documents edited in place, server side (DA1-DA7, 2026-11-28): what was fixed, what read clean, what was left
---

## Which area and files?
- Word documents edited in place, server side, named by the architect on 2026-11-28: `includes/fn/office_docx_*.php`, `includes/fn/office_edit.php`, `includes/fn/document_paragraphs.php`, and their callers `includes/explorer/handle_file_window.php` and `includes/workspace/file_edits.php`. `assets/js/pg_office_view.js` and `includes/fn/office.php` were left out (held by PR #12272). Hunted in the PR `[iş] Bug hunt — Word documents edited in place`.

## What was fixed?
- A save that made the first list of a document without a numbering part (every New document) and also added a link or a picture lost the list: the kind writer's relationships and content types were written over by the links' and the pictures', and both could take the same `rId`. Now every added relationship goes through `pg_office_docx_rel_add()` and the pictures' content types build on the kind's (`tests/office_docx_save_mix_test.php`).
- An added numbering or styles part took its relationship type base from the first relationship listed; Word 2010 files list `stylesWithEffects` under Microsoft's address, so the numbering was named where Word does not look and the lists showed as plain text in Word. The base is now the standard transitional or strict one.
- Align start on a centred paragraph without a style removed `jc`, so the paragraph took the default paragraph style's alignment (often justified) in Word while the editor showed it at the start. It is written `left` now (`office-docx-format-look.md`).
- The designer's document reader reused its share variable for a table cell's paragraphs: a document cut by the limits at a table raised a PHP notice ("Object of class DOMElement could not be converted to float").

## What read clean?
- Locks and refusals of text, format, kind, add and delete (tracked changes, fields, comments, notes, controls, text boxes, merged cells, bookmarks crossing paragraphs, section breaks), the document-wide refusals (signed, protected, tracking, size, DOCTYPE), the version check and lock, the copy-build-verify-rename package write.
- Table rows, columns and deletes (merged and uneven tables refused, bookmark ends kept whole), picture checks (type sniffed and confirmed, side and byte limits), link addresses.
- Callers: the file window checks edit access, the bin and the kind before saving; a channel saves into a copy and never writes the message's file.

## What was left?
- Deleting the only paragraph between two tables joins them into one in Word: #12281 (needs a `keep_` string in `office.php`, held then).
