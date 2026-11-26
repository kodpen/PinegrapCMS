---
name: artifact-canvas-content-screens
description: Plan artifact-canvas-content: how each part of the Artifact Canvas's ready content is put together on screen, step by step (AC4 to AC13)
---

## Which look do they follow?
- The canvas's own panels: `section`, `tile`, `swatches` and `button` of the core (`ui/image-editor-parts.md`), the rail and panel measures of `ui/image-editor-rail.md`. Nothing here opens a second dialog over the canvas but the New image dialog that is already one.

## AC4 Site colours?
- In every colour control (`X.swatches`), a first row labelled "Site colours" before the eight core swatches: the site's palette colours, then its colour variables, at most twelve, each swatch's title its name. No row on a site with no design.

## AC5 Text styles and font pairs?
- At the top of the Text panel three full-width buttons, each written in its own style: "Add a heading", "Add a subheading", "Add body text". A press puts a text layer in the board's middle, sized to the board.
- Under them the section "Font pairs": twelve tiles, two a row, each showing a heading and a line in the pair's two faces. A press puts both as one group.

## AC6 Element sets?
- In the Sticker tool a section "Elements" above the stickers, with a filter of chips: Lines, Badges, Ribbons, Blobs, Arrows, Speech, Ornaments. Tiles four a row; a press puts the shape as an svg layer that takes a colour and, where it has a stroke, a width.

## AC7, AC8 Start from a design?
- The New image dialog gains, above its sizes, the row "Start from a design" with "Blank" as its first tile, so a blank board stays one press away.
- Opened, it shows purpose chips (All, Post, Story, Banner, Announcement, Sale, Event, Quote, Invitation) and the designs as tiles drawn small by the canvas's own renderer, three a row, each with its size under it. Picking a tile and Create opens that design as the new canvas.
- A place for a photo is a dashed box saying "Your picture"; a press opens Picture from Files and the picked picture fills the box, cut to it.
- On a phone the tiles go two a row and the chips scroll sideways.

## AC9 Resize?
- In the Canvas tool, under the size fields, the button "Resize to other sizes". Its panel lists the five presets with tick boxes and a row for a custom size, then the choice "As new scenes" or "As new canvases", then "Make {n} sizes".
- After it a line says what was made and names any text that had to shrink under the floor, with "Show" selecting it.

## AC10 Photo frames?
- In the Sticker tool's "Elements" a chip "Frames": circle, rounded square, arch, blob and the set's badge shapes. A frame on the board is an empty shape saying "Your picture"; a picture dropped on it, or picked through it, fills it.
- A double press on a filled frame moves and scales the photo inside it; Escape or a press outside ends that.

## AC11 From Unsplash, AC13 Openverse?
- Import's menu gains "From Unsplash" (with a key) and "Free photos (Openverse)". Each opens the photo search the rich text editor already has: a search field, tiles, the photographer's name under each. A press places the photo as a picture layer.
- The layer's panel shows a line "Photo by {name} on Unsplash", or the Openverse record's credit and licence.

## AC12 A second icon set?
- The Sticker tool's icon search gains the choice "Bootstrap" / "Tabler" as a segmented control; Tabler's tiles load on its first pick. A Tabler icon's panel has Colour and Stroke width.

Related: docs/memory/plans/artifact-canvas-content-steps.md, docs/memory/ui/screen-composition-rules.md
