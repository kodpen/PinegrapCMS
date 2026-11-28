---
name: office-pptx-slides-measure
description: DF1 measure of a .pptx drawn as slides: server's Imagick draw against the editor's painter in a browser, seconds to the first and all slides, what each leaves out; DF2 takes the painter
module: office_pptx_canvas
---

## How was it measured?
- dev.pinegrap.com answered 403 to the container and the owner's decks are not in the repo, so locally with the same code (2026-11-28): two decks written by python-pptx, a Canva-shaped one (24 slides, 1920 x 1080, 20 MB, a full-bleed photo and 1-3 cropped photos a slide, Google fonts plus Aileron and Canva Sans) and a corporate one (30 slides, 16:9, 3 MB, title and bullet layouts, charts, tables, groups, a logo, notes).
- Server: `pg_pptx_design_read()`, `pg_pptx_canvas_slide()`, pictures cut by `pg_image_editor_design_picture_bytes()`, `pg_image_draw_document()`; PHP 8.3, ImageMagick 6.9.12, 4 cores. Browser: headless Chromium on the same machine, `pg_image_editor.min.js` loaded from localhost, each slide drawn as `workspace_canvas_picture.js` draws (`canvasOpenDoc()`, `layersReady()`, `drawArtboard()`, PNG included).

## What are the seconds?
- Server, Canva deck, its fonts in `data/fonts`: first slide 3.2 s, all 68-72 s, the same on a second opening. Pictures are the cost (one slide: 3.4 s for its three photos, 0.4 s its texts, 0.1 s shapes). Reading the whole package and mapping: 0.03 s.
- Server, Canva deck on a site without those font files: 0 of 24 slides drawn (error `font`), so no time.
- Server, corporate deck: first slide 0.2 s (0.5 s with the once-per-build probe), all 7.4-8.2 s.
- Browser, Canva deck: editor loaded 0.07 s, first slide 0.74 s, all 6.0 s; second opening 0.19 s and 4.0 s.
- Browser, corporate deck: loaded 0.09 s, first 0.58 s, all 1.4 s; second opening 0.07 s and 0.63 s.
- Not counted: the bundle's download from a real site (783 KB, 240 KB gzipped) and a GPU; the browser numbers include fetching Google faces.

## What does each leave out?
- Both draw the same mapped document, so both lose what the mapper's report counts: corporate charts (5) and tables (5) as empty space, arrows and other presets (27 `shape`), gradients as their first colour (9).
- Not counted by the report: a placeholder's size, colour, alignment and bullets from the layout's and master's text styles (corporate titles come out small, black, left), a shape's text colour from its style (white drawn black), a theme font (Calibri) replaced by Roboto without naming it; Canva body text broken too late in wide fonts (Montserrat, Poppins) runs past its box.
- Server only: a whole slide waits when one text's face has no file in `data/fonts` (a site only gets one when the editor fetched it), an svg layer without RSVG, a script other than Latin, Greek or Cyrillic, an emoji.
- Kept output: 1.9 MB a Canva slide as PNG, 46 MB for the deck; 2.7 MB for the corporate deck.

## Which way does DF2 take?
- The browser's painter, under 3 s to the first slide both openings, so the architect is not asked. The server answers the mapped documents (milliseconds), the browser loads the editor when the Slides tab shows, draws the first slide, then the strip one by one. Nothing drawn is kept on the server; the server's draw is not used.
- DF2 gives the picture parts to the painter through the preview's own door, one slide at a time as data: addresses (`office-pptx-slides-preview.md`).
- The reader's master text styles, shape-style text colour and the report naming a theme font are DF2's or a later step's fixes, not this measure's.
