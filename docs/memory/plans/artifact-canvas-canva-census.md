---
name: artifact-canvas-canva-census
description: Plan artifact-canvas-content, step AC2: what a design downloaded from Canva keeps as .pptx and as .pdf (read 2026-11-26 on the owner's ten-page file), and why the .pptx is the route
---

## Which files were read?
- The owner's download of one Canva design of ten pages, a website layout, as .pptx (17.8 MB) and its first five pages as .pdf (13 MB); agent channel messages 14149 and 14150, 2026-11-26. The files are his and hold Canva content: they are never committed, and a test writes its own presentation.
- Read by the architect with a throwaway script; no .svg was read, and the .pptx made it unnecessary.

## What does the .pptx keep?
- Slide size 18288000 by 10287000 EMU: 1920 by 1080 px at 9525 EMU a pixel. A slide's background is a solid colour in `p:bg`.
- Texts: 98 text boxes on ten slides, every one a real `p:sp` with a `p:txBody`, none drawn as a picture. Each has its place and size (`a:xfrm`), no insets, `spAutoFit`, top anchored.
- A paragraph is whole (`a:p`), not cut into lines; a box holds one to four paragraphs. Per run: the family (`a:latin typeface`), the size in hundredths of a point (`sz`), bold, italic, the colour (`a:srgbClr`), the letter spacing in hundredths of a point (`spc`). Per paragraph: the alignment (`algn`) and the line spacing in points (`a:lnSpc/a:spcPts`).
- One box of 98 had two runs in a paragraph; none had a line break element.
- Pictures: 22, as 15 JPEG and 2 PNG parts. Twenty are a `p:sp` with a four-point `a:custGeom` rectangle and an `a:blipFill`, the crop written as `a:fillRect` with negative sides, each alone in a `p:grpSp` whose `a:chOff` / `a:chExt` scale it to its place. Two are plain `p:pic`.
- Lines: ten `prstGeom` `line` shapes. No turn on any element (`rot="0"` everywhere), no chart, table, gradient or effect in this design.
- Fonts: the families are named with the style in the name ("Aileron Bold", "Aileron Italics", "Cormorant Garamond Italics") and embedded as `ppt/fonts/*.fntdata`.

## What does the .pdf keep?
- PDF 1.4 by Canva, 1440 by 810 points a page. Text is text: Type0 fonts with `Identity-H`, subset TrueType files and a `ToUnicode` map each; pictures are JPEG streams.
- But a paragraph arrives as its drawn lines, with no box, no paragraph and no alignment; the words must be put together again from glyph runs, and the product has no PDF reader.

## Which route follows?
- Architect (2026-11-26, agent channel request 3136): the .pptx. It gives every text with its box, paragraph and style, every picture with its crop, and the own reader already opens the package (`platform/office-pptx-preview.md`). The .pdf and the .svg are not read.
- Sizes: a point is 12700 EMU, so a text's size in px is `sz` / 100 × 4 / 3 on a 1920 px slide; the line height is `spcPts` over `sz`; the letter spacing in em is `spc` over `sz`.
- Fonts: the style word is cut from the family's name (Bold, Italic, Italics, Light and the like become the layer's weight and italic), and the family is looked up in the font catalogue (`ui/image-editor-fonts.md`). A family it lacks is replaced and named in the import's report. An embedded font file is never taken out of the package: its licence is the design tool's.
- Canva's Content License Agreement lets a design's content be used in the design; nothing here takes an element out as a file of its own for others (`api/canva-api-terms.md`). The picture parts become the site's own files only as parts of the canvas the person opened.
- What one design cannot show: turned and flipped elements, shapes with curves, gradients, tables, charts and effects. The reader counts what it does not bring and the report says it (`plans/artifact-canvas-design-file.md`).
