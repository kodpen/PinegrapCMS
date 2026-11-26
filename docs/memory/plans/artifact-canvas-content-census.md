---
name: artifact-canvas-content-census
description: Plan artifact-canvas-content, step AC1: what a person meets on an empty Artifact Canvas board, tool by tool, and which census file holds the rest
---

## Which files make the census?
- This file: the empty board today, tool by tool.
- `artifact-canvas-content-census-tools.md`: what Canva, Adobe Express, VistaCreate and Polotno Studio give at the same point, with the pages read.
- `artifact-canvas-content-census-gaps.md`: each gap, what it takes in own code, where its content comes from and under which licence, a size.
- `artifact-canvas-content-census-proposals.md`: ten proposals, the most use for the least work first.

## What does the New image dialog offer?
- Five sizes and Custom (`M.BOARD_PRESETS`: square 1080, portrait 1080×1350, story 1080×1920, share 1200×630, banner 1920×600) and four backgrounds: white, transparent, black, light blue (`ui/image-editor-artboard.md`). No design to start from, no purpose named (post, poster, invitation), no recent size.

## What does each rail tool offer on the empty board?
- Select: the board's size and background, titled Canvas. Nothing to pick.
- Canvas: the board's handles, the same five preset chips, Fit to content. A preset moves the board, never the artwork: no layout follows a new size (`ui/image-editor-artboard.md`).
- Draw: nine kinds (arrow, line, box, ellipse, triangle, polygon, star, bubble, pen), fill none, solid or soft, dashes, corners (`ui/image-editor-draw.md`). No gradient.
- Text: an empty board to click on; five system stacks, the site's own fonts (`style.style_custom_fonts`) and "Choose from Google Fonts", about two hundred families shipped in code (`ui/image-editor-fonts.md`). Bold, italic, align, then under Details weight, line height, spacing, padding, outline and shadow (`ui/image-editor-text.md`). No text style to add (heading, body) and no font pair.
- Sticker: eight stickers (burst, pill, tag, stamp, speech bubble, check, star, heart), "Picture from Files" (the 60 newest), Icons (Bootstrap Icons, about two thousand, searched by English name) and Emoji (eight categories, the system's drawing) (`ui/image-editor-sticker.md`, `ui/image-editor-glyph.md`).
- Frame (the canvas profile's one tool of the Photo group, `ui/image-editor-rail.md`): four frames and the board's background swatches.
- Vector group: Pen, Scissors, Shape builder, Warp, Free deformation: tools, no content.
- Animate: an empty timeline (`ui/image-editor-motion.md`). Layers: an empty list.
- The top bar's Import: this computer, or the site's files (pictures, SVG, video); Data: a Workspace table as a chart (`ui/image-editor-import.md`, `ui/image-editor-data-menu.md`).

## Which colours does it offer?
- Every colour field shows the core's eight swatches (`COLORS` in `pg_image_editor.js`: white, black, red, orange, yellow, green, blue, purple) and a picker. The site's design palette (`style.style_palette`, `designer/design-theme-look-and-palette.md`) is not offered: the brand's colours are typed each time.

## What does the product already hold that the canvas does not use?
- An Unsplash dialog (`assets/js/pg_unsplash.js`, `window.pgUnsplash`) used by the rich text editor's Image dialog when the site set `UNSPLASH_ACCESS_KEY` (`ui/picker-editor-bridge.md`); the canvas does not offer it.
- The design palette and design variables of every visual design, and the site fonts (already offered in Text).
- Scenes (several pages of one canvas, `ui/image-editor-scenes.md`): the place where one design in other sizes could live.

## What does a person feel?
- The owner wrote on 2026-11-26 (agent channel message 14119): "şu an için biraz boş gibi geliyor". Every tool is there, but nothing to start from: a person who is not a designer meets a white square.
