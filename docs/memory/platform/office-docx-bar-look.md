---
name: office-docx-bar-look
description: The document bar's Size, Text color, Highlight color and four alignments (DA2, PR #12084): controls, shown state, a character's look, page and preview drawing
module: office_docx_format
---

## Which controls are they?
- After Bold, Italic, Underline of the document bar (`.office-format.office-doc-format`): Size, "Text color", "Highlight color", a `pg-seg` of Align left, Align center, Align right, Justify; then Paragraph style and Delete, as before.
- Size: the workbook bar's select (`FONT_SIZES`, 8 to 72); "Default" takes the text's own size off (the style's holds). A size outside the list shows its number; a selection of mixed sizes shows none.
- Text color: the workbook bar's `TEXT_COLOURS`. Highlight color: Word's highlight names in Word's menu order (`DOC_HIGHLIGHTS`, white left out as Word does), named Yellow, Bright green, Turquoise, Pink, Blue, Red, Dark blue, Teal, Green, Violet, Dark red, Dark yellow, Dark grey, Light grey, Black. Each opens a menu: "None" first, then the swatches, the chosen one ringed.
- The colours, the size and Bold act on the selected text (`selectedParts()`); the alignment on every paragraph the selection reaches, else the one open or picked. Each press is one change of Undo. Choosing the alignment the file has takes the change off.

## What state do they show?
- The colour bar under each glyph and the size: what all the selected text has; with only a caret, the character before it (what typed text takes). The alignment pressed: the one all the paragraphs share.

## How does a character's look hold a colour?
- A look stays one number a character: the bits (bold 1, italic 2, underline 4) plus 8 times the number of its set of colour, highlight and size, each set kept once (`DOC_LOOK_SETS`, `lookSet()`), so looks still compare with `===` and `carryMarks()`, `cutLook()` are unchanged. Set 1 is "unknown": `exactBase()` gives it, so text moved into a new run sends every part, a missing one as `false`.
- `formatRanges()` sends `color`, `highlight`, `size` stretches after bold, italic, underline (`office-docx-format-look.md` for what the save takes). An alignment is sent as the entry's `align` only when it differs from the file's (`edit.aligns`); an added paragraph's file alignment is the one of the file's paragraph it follows, and Enter carries an asked alignment on.

## How are they drawn?
- The page: `drawText()` wraps a stretch in a span styled by `lookStyle()`; an asked alignment is the paragraph's `text-align`, the server's put back when taken off.
- The preview's HTML (`pg_office_docx_run()`): a run's own colour, highlight and size as a span styled by `pg_office_docx_look_style()`, the twin of `lookStyle()`; the alignment was drawn already. File content: the same in both themes.

## What was checked?
- `tests/office_docx_bar_test.php`: the look in the marks, the stretches and alignments sent, the preview's style. On a sandbox in Chromium, in the File Manager's file window and a channel's preview, at 1440 and 390 px: colour, size, highlight, centre, Undo, save, read back drawn and shown on the bar. Not opened in Word.
