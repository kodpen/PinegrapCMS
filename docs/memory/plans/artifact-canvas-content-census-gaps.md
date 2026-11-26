---
name: artifact-canvas-content-census-gaps
description: Plan artifact-canvas-content, step AC1: each content gap of the Artifact Canvas against Canva and the others: what it takes in own code, where its content comes from, the licence, a size (S, M, L)
---

## How is a gap sized?
- S: one PR well under 1,000 product lines; M: one PR of about 1,000; L: two or more. Content a session makes is the product's own (MIT) and needs no outside licence. Ready content is loaded when its panel opens, never on the first load (`artifact-canvas-content.md`).

## Ready designs by purpose?
- Code: a "Start from a design" row in the New image dialog, a tile per design drawn small by the canvas's own renderer, filtered by purpose (post, story, banner, announcement, sale, event, quote, invitation); Create opens the design's document instead of a blank board. M.
- Content: canvas documents (JSON) a session makes from texts, drawn layers, glyphs and Google Fonts, kept as files under `assets/` with an index; about 5 KB each, 30 designs about 150 KB, read when the row opens. No photo inside: a picture spot is a box saying "Your picture", so no photo licence enters.
- Licence: own. Google Fonts faces are under the SIL Open Font License, already used by the canvas.
- A design's texts are `lang()` words in the panel's language; saved, they are canvas texts (`core/picture-text-overview.md`).

## Text styles and font pairs?
- Code: three buttons at the top of the Text panel (Add a heading, Add a subheading, Add body text), each a text layer with set size, weight and line height; under them about twelve pairs (a heading and a line in two families), each a group of two text layers. S.
- Content: own; families from the shipped Google Fonts list. Licence: OFL.

## Sets of elements?
- Lines and dividers, badges, ribbons, blobs, arrows, speech shapes, corner ornaments: drawn as svg layers by own code, about 40 shapes as paths in 30 KB. S to M. Licence: own.
- More icons: Tabler Icons (MIT, about 5,000, outline, one stroke width, so a colour and a width change them) beside Bootstrap Icons, as svg layers from a path map fetched when opened (about 2 MB unpacked, a few hundred KB compressed). S to M.
- Illustrations: none chosen. Sets under CC0 exist (Open Peeps); each set's licence is read again before it ships, and a set whose licence forbids redistribution in a product or a competing service is never shipped (the plan's rule).

## Photo library?
- Unsplash: the product already has the dialog and the site's key (`ui/picker-editor-bridge.md`). Code: "From Unsplash" in Import's menu, the pick a picture layer. S.
- Licence: the Unsplash License allows use and change, attribution not required; the API guidelines ask to hotlink the returned address, call `download_location` when a photo is chosen, and credit the photographer (help.unsplash.com/api-guidelines). Open: the canvas keeps a placed picture as a files row (`ui/image-editor-canvas-save.md`), not a hotlink; whether a chosen photo may be kept so is a question for the architect before the step.
- Openverse (no key, CC0 and public domain filter, license and attribution in every record): a second source for sites without a key. M. Licence: per photo; only CC0 and PDM shown.

## A brand's colours and fonts?
- Colours: a "Site colours" swatch row before the eight core swatches, read from the site's designs' palettes and variables (`designer/design-theme-look-and-palette.md`, `designer/design-variables-tab.md`) by a new action. S. No schema change.
- Fonts: the site's fonts are already the first tiles of Text. A logo has no known setting to read: a person keeps it in Files and uses Picture from Files.

## One design in other sizes?
- Code: "Resize" in the Canvas tool: pick sizes (the presets, custom), each made as a new scene or a new canvas; every layer is placed by its relative place in the old board and scaled by the smaller ratio, texts by the same ratio, the background stretched. Pure model function with tests, then the panel. M.
- Content: none. Licence: none.

