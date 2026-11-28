---
name: office-docx-format-look
description: Text colour, highlight and font size on selected .docx text and a paragraph's alignment (office_docx_format.php, DA1): save entry, reader marks and aligns, how each is written
module: office_docx_format
---

## What does a save's paragraph entry take beside bold, italic and underline?
- A `format` stretch's `set` may also hold `color` (six hex digits, `RRGGBB`, no `#`), `highlight` (one of Word's own names: yellow, green, cyan, magenta, blue, red, darkBlue, darkCyan, darkGreen, darkMagenta, darkRed, darkYellow, darkGray, lightGray, black, white; any case) and `size` (points, 8 to 72, whole or half). `false` takes each off; `true`, `none`, `#RRGGBB`, a colour name or a size outside the range: `none` refusal.
- Split and joined as bold (`office-docx-format.md`): the same stretches, counting, limit of 200 and `format_range` refusal.
- An entry (changed or added with `after`) may hold `align`: `start`, `center`, `end` or `justify`; anything else (also `left`) is refused as `none`. With only `align` the text is not touched. Alignments are written after the formats, before paragraphs are added.

## How is each written?
- Colour: `w:color w:val="RRGGBB"`; a `themeColor`, `themeTint` or `themeShade` on it is removed, as the theme would win. Highlight: `w:highlight w:val="name"`. Size: `w:sz` and `w:szCs` in half points (12 pt is 24). Each in CT_RPr order (`pg_office_docx_rpr_place()`), every other property kept.
- Taken off: the element goes; where a style may give it back (the run's `rStyle` or the paragraph's `pStyle`) colour is written `auto` and highlight `none`. A size has no "none" value, so it always goes and the style's size holds.
- Alignment: `w:jc` in pPr in CT_PPr order (`pg_office_docx_ppr_put()` of `office_docx_kind.php`), written `left`, `center`, `right`, `both`: Word reads left and right as the start and the end of a right-to-left paragraph, and older readers do not know `start`/`end`.
- Start writes `left` where the paragraph has a `jc` or a `pStyle`, never removes `jc`: a paragraph without a style takes the default paragraph style (Normal), which is often justified. A paragraph with neither is left as it is. A paragraph already so aligned (also by `start`/`end` values) is left as it is.

## What does the reader send the editor?
- `edit.marks` stretches stay `[characters, bits]`; a stretch whose runs have a colour, highlight or size of their own carries a third item `{c: 'RRGGBB', h: name, s: points}`, each key only when present (`pg_office_docx_run_look()`); a run in a link also `l`, its address (`office-docx-links.md`). `auto`, `none` and highlight names Word does not have are not counted. Neighbours join only when bits and look are equal.
- `edit.aligns`: paragraph position => `center`, `end` or `justify`, only for paragraphs not at the start (`pg_office_docx_paragraph_align()`, from the paragraph's own `jc`; `distribute` and `thai` read as start).
- The preview's HTML draws them (`pg_office_docx_look_style()`), and the document bar sets them: `office-docx-bar-look.md`.

## What was checked?
- `tests/office_docx_look_test.php`: the request, the marks, each property written and taken off (plain and styled), split and join, alignment written, replaced and removed, a save with format, align and an added aligned paragraph read back with an untouched paragraph byte for byte. Not opened in Word or LibreOffice.
