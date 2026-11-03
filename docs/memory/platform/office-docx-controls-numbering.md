---
name: office-docx-controls-numbering
description: Word preview: table rows and cells inside content controls and custom XML, and numId 0 taking a paragraph's (or its style's) numbering off (PR #1119), run properties turned off by w:val
---

## Which rows and cells does the Word preview find?
- `pg_office_docx_blocks()` draws a table from `pg_office_docx_wrapped($table, 'tr')` and each row from `pg_office_docx_wrapped($row, 'tc')`: the direct children with that name and those inside `sdt` / `sdtContent` / `customXml`, at any depth.
- A repeating section content control (Word's Developer tab, forms and templates) wraps whole rows in `w:sdt`; a plain text control or a custom XML element can wrap one cell. Before, only direct `tr` and `tc` children were read, so such rows vanished: a table showed its header and nothing else.
- In the body and in cells, `sdt`, `sdtContent` and `customXml` were already walked into; `smartTag` and the others inside a paragraph by `pg_office_docx_inline()`.

## What does numId 0 do?
- `<w:numId w:val="0"/>` is "no numbering" (ECMA-376 17.9.18). On a paragraph it also takes off the numbering its style gives (a numbered Heading1 with numId 0 is a plain `<h1>`); on a style it takes off what the style would give. Before, "0" was read as a list id and the paragraph became a bullet (the unknown list's default).
- The style's own numbering is still followed when the paragraph has none (List Bullet, a numbered heading), and a numbered paragraph still becomes a list item rather than a heading.
- Not followed: numbering a style inherits through `basedOn`, `lvlOverride`/`startOverride`, and numbering in a table style.

## Which values turn a run's formatting off?
- `pg_office_docx_run()` reads bold, italic, underline and strike as off for `w:val` `0`, `false`, `off` (ST_OnOff) and `none` (underline); any other value or none at all is on, as in `office_edit.php`'s on/off check. Before, `off` was missing, so `<w:b w:val="off"/>` showed bold.

## Tests
- `tests/office_docx_reader_test.php`: rows in a repeating section, a row and cells in custom XML and a cell control; numId 0 on a heading, on a plain paragraph and on a style; run properties turned off with each off word.
