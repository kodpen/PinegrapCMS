---
name: image-editor-starts-designs
description: The Artifact Canvas's ready designs for "Start from a design": the index and design files, what a design may hold, the thirty shipped (AC7, AC8), their licence and test
module: image_editor
---

## Where are the designs kept?
- `assets/canvas/designs.json`: `{ designs: [{ id, purpose, label }] }`, the tiles' order; each design `assets/canvas/designs/<id>.json`: `{ id, width, height, background, layers }`, bottom layer first. Read once a page when the row first opens (config `starts`: `pg_image_editor_starts_urls()` with the index's time).
- `M.startIndex()` keeps an id `[a-z0-9-]` once, a purpose of `M.START_PURPOSES` but all, a label `start…`; 60 at most. `M.startDesign()` refuses a whole design for a wrong id, a size outside 16-8000, more than 40 layers or one bad layer.
- Layers a design may hold: `text` (label key `start…`, font sans, serif or a catalogue `g:` family, x, y, maxW), Draw's shapes `rect`, `ellipse`, `triangle`, `polygon`, `star`, and `slot` (box, radius); fields as `startLayer()` in the model.
- Thirty designs (six from AC7, twenty-four from AC8, #11955): every purpose three or more, each in a size that fits it (a story tall, a banner wide or the share size), the five sizes all used. Texts used by several designs share a key (`startQuoteAuthor`, `startShopSale`, `startSignUp`).
- Licence: designs and words are own; faces Google Fonts (SIL OFL); no photo shipped.

Related: docs/memory/ui/image-editor-starts.md
