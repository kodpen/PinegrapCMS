---
name: office-docx-paragraph-kind
description: A .docx paragraph's kind (normal, heading 1-3, bulleted or numbered list) set from the format bar (office_docx_kind.php, OD2): save entry, styles and numbering used or added, refusals
module: office_docx_kind
---

## How is a paragraph's kind changed?
- The "Paragraph style" select in the document's format bar (`.office-format`, look A `pg-input pg-input-sm`), beside Bold, Italic, Underline: Normal text, Heading 1, Heading 2, Heading 3, Bulleted list, Numbered list. It speaks for the paragraph picked or open; disabled with none. A paragraph of a style the choice does not hold (Title, a quote, heading 4 and below) shows "Other style", which cannot be chosen.
- The change waits with the text and format changes; the paragraph shows the new kind as a look (`data-kind`, heading size or a list mark) until saved. Undo of the window drops it.

## What does the preview send and the save take?
- `pg_office_docx_read()` with edit sends `edit.kinds`: per editable paragraph `normal`, `heading1`-`heading3`, `bullet`, `number` or `other` (`pg_office_docx_kind_of()`): its numbering (own or its style's, numId 0 none) first, then its style.
- A save's `paragraphs` entry may hold `kind` beside or without `text` and `format` (`office-paragraph-edits.md`); another word: `none`. Kinds are written after text and format, in document order. A paragraph that already has the kind is not written.

## Which styles and numbering are used?
- Heading N: the document's style whose built-in name is "heading N" (Word writes it in English whatever the id or the shown name: "Balk1", "a2"), else one whose id reads as the heading; else the product adds `HeadingN` (or `HeadingNPgM` when the id is taken), based on the default paragraph style, bold, 16/14/12 pt, outline level.
- The preview also draws a paragraph as `<hN>` by that built-in name now (`pg_office_docx_state()` `heads`), not only by an id that reads as a heading.
- List: the numbering of the paragraph just before (else just after) when it is the same kind, so neighbours stay one list; a bullet else takes the document's first bulleted num; a numbered list else gets a new num on the document's own decimal numbering with `startOverride` 1, so it counts from 1; else the product adds a nine-level numbering. Style: List Paragraph when the document has it, a plain style of the document's own kept.
- Normal and headings take the numbering off; a heading, title, quote or list style gives way. A heading style that numbers itself keeps doing so, and reads back as a numbered list.

## How are the parts written?
- `word/document.xml` as for text (DOM); pPr children put in CT_PPr order (`pg_office_docx_ppr_put()`).
- Styles and numbering are written only when something is added, as text inserted before the root's end (abstract numberings before the first `num`): the bytes before stay as they were. A part the document lacked (numbering.xml often) is written new and named in `word/_rels/document.xml.rels` and `[Content_Types].xml`, with the standard relationship type (never a Microsoft extension's), among the links' and pictures' (`pg_office_docx_rel_add()`).

## What is refused?
- `kind_unusual` ("The styles or the list numbering of this document cannot be changed here..."): the styles or numbering part elsewhere than the relationships name, or present without a relationship, too large, not UTF-8, a DOCTYPE, a root without the main namespace's prefix, no relationship type to name an added part with. Only saves with a kind read these parts; a text or format save is not refused by them.

## What was checked?
- `tests/office_docx_kind_test.php`: kinds read, a save on a document with its own headings and numbering (styles byte for byte, numbering kept up to the new num), one that adds a heading, both numberings, relationship and content type, untouched paragraphs and parts byte for byte, refusals leaving the file as it was. Not opened in Word or LibreOffice; the select tried in Chromium with OD3 (`office-docx-add-delete.md`).
