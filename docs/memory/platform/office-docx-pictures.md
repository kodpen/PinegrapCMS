---
name: office-docx-pictures
description: A PNG, JPEG or GIF put into a .docx after a paragraph (office_docx_picture.php, DA6): save entry, parts written, size drawn, refusals, picture paragraphs deleted
module: office_docx_picture
---

## Which save entry puts a picture in?
- In a save's `paragraphs` list (`office-docx-add-delete.md`): `{after: p, picture: {data, name?}}`. `data` is the picture's bytes in base64 (white space allowed), `name` its description (alt text, one line, at most 255 characters).
- It reads after paragraph p in the order listed beside added paragraphs and tables after the same p. The bar's Picture button that sends it: `office-docx-picture-bar.md`.

## What is taken, and what is refused?
- PNG, JPEG or GIF, known by its first bytes and read by `getimagesizefromstring()` as the same kind, 1 to 20000 pixels a side; anything else is `picture_type`.
- The preview's picture limits (`office-files.md`): one picture over 2 MB (`PG_OFFICE_MAX_IMAGE`) is `picture_large`; a save's pictures together, or the document's `word/media` pictures with the new ones, over 8 MB (`PG_OFFICE_MAX_IMAGES`) is `picture_room`, as the preview would no longer show them.
- After a paragraph where none may be added: `add_locked`. Nothing is written when any entry is refused.

## What is written?
- The bytes as `word/media/imageN.png|jpeg|gif`, N the first no entry has (case ignored); the same bytes twice in a save are one part.
- An image relationship of the main part (next free `rIdN`, strict type in a strict document), kept in the links' context so the rels part is written once (`office-docx-links.md`), with a numbering a kind adds in the same save (`pg_office_docx_rel_add()`). The content types build on the ones that kind changed.
- A `Default` content type for the extension, before the overrides, only when `[Content_Types].xml` has none (case ignored); an extension already given another type gets an `Override` for the part. A content types part that cannot be read refuses the save as `unusual`.
- A new paragraph after the anchor holding one `w:drawing` / `wp:inline` / `pic:pic` run, the namespaces declared on that paragraph. It takes only the anchor's alignment (`jc`), not its style or list: a picture is not a heading.
- `docPr` id: one past the highest in the main part; name `Picture N`, `descr` the description.

## What size is it drawn at?
- Its pixels at 96 per inch (9525 EMU a pixel); wider than the text, it is made as wide as the text keeping its shape. The text's width is the table step's (`pg_office_docx_table_text_width()`): the section's page less its margins and gutter, in a cell the cell's width less 216 twips.

## Which picture paragraphs can be deleted?
- A paragraph whose drawings are all plain pictures (`w:drawing` in a run, one `pic:pic` in its graphic, no text box) is deleted like a paragraph of text, if nothing else keeps it (a field, a comment, the last of a cell, a section break...). Its part and relationship stay in the package.
- `delete_picture` / `keep_picture` now mean a drawing of another kind (text box, chart, shape), a legacy VML picture (`pict`) or an embedded object: "holds a drawing, a text box or an embedded object".
- Deleting a table holding a picture is still `table_delete` (`office-docx-tables.md`).

## What was checked?
- `tests/office_docx_picture_test.php`: the entry's checks, the drawn size, a save with pictures after a heading, in a cell and at the end, the parts, relationships and content types, read back by `pg_office_read()` with the pictures drawn, a picture paragraph deleted, an existing `image1.png` and an upper-case PNG default, a strict document, and each refusal leaving the file as it was. Not opened in Word or LibreOffice.

Related: docs/memory/platform/office-docx-add-delete.md, docs/memory/platform/office-files.md
