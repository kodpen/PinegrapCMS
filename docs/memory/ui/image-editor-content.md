---
name: image-editor-content
description: The Artifact Canvas's ready content (AC4 to AC13): Site colours, text styles, Elements, Start from a design, Resize, frames, Unsplash, Tabler, Openverse; the rules and files
module: image_editor
---

## Why does the canvas have ready content?
- Owner (2026-11-26): an empty board felt "biraz boş", and with Canva beside it the canvas needs better content to start from; all ten kinds were chosen, the basics first (`workflow/decisions-made-product-114.md`, agent channel poll 231).
- Before it a person who is not a designer met a white square: no design to start from, no text style, no element set, no photo source, no brand colour, no way to make one design in other sizes.

## Which parts are there, by the label a person sees?
- "Site colours": the first row of every colour control (`image-editor-site-colors.md`, #11854).
- "Add a heading", "Add a subheading", "Add body text" and "Font pairs" at the top of the Text panel (`image-editor-text-styles.md`, #11881).
- "Elements" with its chips in the Sticker tool (`image-editor-elements.md`, #11897); its "Frames" chip puts a photo frame (`image-editor-frames.md`, #12012).
- "Start from a design" in the New image dialog, the "Your picture" box (`image-editor-starts.md`, #11932); the thirty designs and their files (`image-editor-starts-designs.md`, #11955).
- "Resize to other sizes" in the Canvas tool (`image-editor-resize.md`, #11975).
- "From Unsplash" and "Free photos (Openverse)" in Import (`image-editor-unsplash.md`, #12033; `image-editor-openverse.md`, #12108).
- "Bootstrap" / "Tabler" switch in the Sticker tool's icon search (`image-editor-tabler.md`, #12067).
- Where each source and licence comes from, and what was left out: `image-editor-content-sources.md`.

## What does every part keep?
- A part is a tool file of its own, its pure maths in the model with a test (`image-editor-parts.md`); a hook in the core is named in its memory file.
- Ready content is a file under `assets/` asked for when its panel, row or chip opens; nothing joins the canvas's first load, and no library comes in (the canvas is own code, `image-editor-overview.md`).
- A thing put on the board is an ordinary layer (text, svg, picture, group): it saves, translates, animates and exports as one made by hand. The photo frame is the one layer type the content added.
- Every word a person reads is `lang()`; a ready design's texts are `lang()` words in the panel's language that become canvas texts when placed, so they are translated as any canvas text (`core/picture-text-overview.md`).
- No schema change: colours are read from the designs, designs and shapes are files, photos are `files` rows or (Unsplash) the returned address.
- Phone and dark mode as the canvas does them (`image-editor-touch.md`); the panels use the core's `section`, `tile`, `swatches` and `button`, and open no second dialog over the canvas.

## How is new ready content added?
- Content a session makes is the product's own and needs no licence line. Content from outside enters only with its licence named in its memory file, and only a licence that allows it on every site and in a product (`image-editor-content-sources.md`); never anything from Canva (`api/canva-api-terms.md`).
- A new design is a design file plus an index entry (`image-editor-starts-designs.md`), opened and saved once in its test; a new shape is a path in the elements file (`image-editor-elements.md`).
- A new photo source follows the Unsplash or Openverse file: credit kept on the layer and shown in its panel, offered only where its key or policy allows.

Related: docs/memory/ui/image-editor-overview.md, docs/memory/ui/image-editor-design-file.md
