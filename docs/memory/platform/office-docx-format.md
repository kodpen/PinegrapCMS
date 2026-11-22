---
name: office-docx-format
description: Bold, italic and underline on selected text of a .docx paragraph (office_docx_format.php, OD1, PR #11152): the bar, the save entry, runs split and joined, what is kept
module: office_docx_format
---

## How is text made bold, italic or underlined?
- The bar above an editable document (`.office-format`, look A: `pg-icon-button` Bold, Italic, Underline with aria-pressed), and Ctrl/Cmd+B, I, U. It acts on the text selected in the open paragraph box, or, the box closed with Enter or Tab, on what was selected in it while that paragraph stays picked. With nothing selected the status line says "Select text in a paragraph to format it."
- As in a word processor: a button takes the format off when all the selection has it, else puts it on. A button is pressed when all the selection has it.
- The change waits with the text changes; the paragraph is drawn plain with its bold, italic and underline (other formatting comes back on save). Undo of the window drops both.

## What does the preview send and the save take?
- `pg_office_docx_read()` with edit sends `edit.marks`: per editable paragraph `[[characters, bits], ...]` (bold 1, italic 2, underline 4) from the runs' own properties, as the drawing reads them (`pg_office_docx_paragraph_marks()`).
- A save's `paragraphs` entry may hold `format`: `[{start, end, set: {bold|italic|underline: true|false}}]`, in characters (code points) of the paragraph's text, beside or without `text`. Text is written first, then formats, counted in the new text. At most 200 stretches a paragraph (`PG_OFFICE_DOCX_FORMAT_MAX_RANGES`). Same endpoints, lock, version check and refusals as text (`office-paragraph-edits.md`); a stretch past the text: `format_range`.
- The editor sends only stretches that differ from the file's look. Its look of typed text is a guess (the character before it); the server writes the asked value there anyway.

## How are the runs written?
- `pg_office_docx_paragraph_format()`: a run a stretch starts or ends inside is split (`pg_office_docx_run_split()`, a text element cut in two, the copy keeps attributes and rPr). Runs inside get the property in rPr, other properties kept, new ones placed in CT_RPr order (`pg_office_docx_rpr_order()`).
- Bold writes `b` and `bCs`, italic `i` and `iCs` (as Word), underline `u val=single`; an underline already on keeps its kind. Off: the elements removed, or written off (`val=0`, `u val=none`) when the run has `rStyle` or the paragraph `pStyle`, as a style may give it back.
- Afterwards neighbouring runs of one parent with equal rPr (C14N) are joined when the change reached one of them; their text elements become one. Runs not reached keep their XML; a link's runs stay in the link.

## What was checked?
- `tests/office_docx_format_test.php`: the request, the marks, split, join, order, styled paragraphs, links, tabs and breaks, a save with text and format read back with an untouched paragraph byte for byte, and the editor's look functions in Node. Not opened in Word or LibreOffice; the bar not tried in a browser.
