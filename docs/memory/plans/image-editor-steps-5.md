---
name: image-editor-steps-5
description: Steps E23b, E30-E32, E31b of plans/image-editor.md: Fotoğraf and Vektör rail groups, the picture as bottom layer, Import, SVG rules for use, a context menu; claim as [plan:image-editor] E<n>
---

## Which steps are there?
- Owner decisions in `workflow/decisions-made-product-24.md` and `-27.md`. Sizes, files and review as in `image-editor-workers.md`.

- E23b (M) Rail groups Fotoğraf and Vektör (owner 2026-11-03 and -04). `free`.
  - Two rail items, each with a mode switch at the top of its panel: Fotoğraf (Crop, Adjust, Redact, Frame; E27 and E28 join it) and Vektör (pen, scissors, shape builder, warp; E26 joins it). The item is highlighted in each mode; a mode keeps its hint, letter and pointer handling; Fotoğraf opens the mode used last, Crop the first time. Photo rail: Select, Fotoğraf, Canvas, Draw, Text, Sticker, Vektör, Animate, Layers at the foot; a group with one mode on a profile is that mode's item. Labels at 1280×800; the phone's bar the same.
  - Tests: the profiles list one item per group; a mode switch keeps the selection; the last mode reopens. Browser: the rail at 1280×800 with labels, the phone's bar.

- E30 (M) The picture as the bottom layer (owner 2026-11-04). Done in #6470 (`ui/image-editor-picture-layer.md`).

- E31 (M) Import from the top bar (owner 2026-11-04). Done in #6653 (`ui/image-editor-import.md`).
  - An Import button in the top bar (not on the annotate bar): From this computer (a file picker; drop and paste stay) and From the site (the Files list of the Sticker part, searchable). A picture becomes a picture layer as the Image tool makes it; the Image item leaves the canvas rail (kept for a caller's list). An SVG becomes one `svg` layer, fitted, its ids made unique; Break apart in its panel makes a layer per element as an opened SVG gets (`svgLayers()`). Video files join the menu with V2.
  - Tests: unique ids and the fit (model); browser: a PNG, a JPEG and an SVG from the computer and from Files on a photo and a canvas, Break apart, undo, a copy saved; the phone.

- E31b (S) Style rules that reach `<use>` copies (found in the review of #6653). Taken in #PR (`ui/image-editor-svg-sanitize.md`).
  - `pg_svg_sanitize()` scopes every kept rule under the root's id (`#root .a`), and Chrome matches the selectors of what a `<use>` draws inside the use's own tree, so rules for symbol or definition content stop there: a file exported like Illustrator's (`<symbol>`, `.cls-1{fill:…}`, `<use>`) shows black symbols when opened or imported, and `#star{stroke:…}` loses its outline on a `<use>` of `#star`. Give ids and classes a prefix of the file (renamed in the elements, in href and url(#…) and in the rules, as `M.svgIdMap()` does for an import) and leave the root's scope off a rule whose every compound names an id or a class; keep it on the rest.
  - Tests: the sanitizer's output for such a file (unit); browser: the file opened and imported shows its colours and outline; an upload is still sanitized.

- E32 (M) Context menu (owner 2026-11-04). `free` once E23b is merged.
  - A right click on the stage, a rail item or a scene (a long press on touch, Shift+F10, the menu key) opens a menu built from the tool and the selection: on layers cut, copy, paste, duplicate, delete, order, lock, hide, align and the type's commands (Combine shapes with two areas or more, Convert to outlines, Expand a warp, Break apart an SVG); on the empty canvas paste, select all, Import, canvas size, fit; on a group item its modes; on a scene duplicate, delete, earlier, later. Text fields keep the browser's menu.
  - Tests: the items for a tool and a selection (model); browser on desktop and phone, keys and focus (role menu).
