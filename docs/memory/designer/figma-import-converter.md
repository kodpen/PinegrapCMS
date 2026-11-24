---
name: figma-import-converter
description: The Figma converter (#11509): a frame of a Figma nodes answer to a designer tree, with the lists of pictures, instances, colours, variables and the report left for the next step
module: figma_import
---

## What does it take and give?
- `pg_figma_answer_frame($answer, $id)` picks a frame from a stored `GET /v1/files/:key/nodes` answer (`12-3` or `12:3`) with its `styles` and `components` maps; null when missing.
- `pg_figma_convert_frame($frame, $styles, $components, $opts)` returns `name`, `tree` (root, every node with an `_id` from `sd_1`), and the lists `pictures`, `instances`, `colors`, `text_styles`, `variables`, `fonts`, `report`. Pure: no network, database or file; the read and the frame list are ST3's.
- Tests: `tests/figma_import_test.php` on the stored answers in `tests/fixtures/figma/`.

## How is the layout carried?
- The frame is a `section` at `width:100%;max-width:<frame width>;margin:0 auto`; inner frames, groups and instances are `div` semantic nodes; all look is `inlineStyle`, the Figma layer name `customName`.
- Auto layout HORIZONTAL/VERTICAL is flex (gap, wrap with the counter-axis gap, padding, justify/align); GRID is CSS grid (`repeat(n,minmax(0,1fr))` unless Figma gives track text), with column/row spans.
- A child's FILL is `flex:1 1 0` along the parent and `align-self:stretch` across; FIXED writes px and `flex-shrink:0`; HUG writes nothing.
- A frame without auto layout and a group place their children with `position:absolute` offsets from the bounding boxes; so does an ABSOLUTE child of an auto layout frame.
- Top visible fill only: solid, linear gradient, or an image (listed as a `background` picture); shadows become `box-shadow`, strokes a solid `border`.

## How is text carried?
- Heading when the text style or layer is named H1-H6 / "Heading n", else by size (40px h1, 30px h2, 24px h3), else paragraph; a whole-text link to http(s)/mailto/tel is a link node. Other link kinds are dropped.
- Family in `fontFamily` (cleaned to letters, digits, space, `._-`); size, weight, line height, spacing, align, case, decoration and colour in `inlineStyle` with `margin:0`.
- Characters are escaped, line breaks `<br>`; style-override runs become `<span style>` or `<a href>`. Override positions count UTF-16 units, as Figma does.

## What is left for ST5?
- `pictures`: image nodes with `src: ''` for image fills, vectors and vector-only groups (icons), plus `background` boxes; each with `node` (`_id`), `figma` id, `kind`, `image_ref`.
- `instances`: `componentId` per INSTANCE node; one component on several frames is a shared component.
- `colors` (value, first style name, count; most used first) and `text_styles` (name or values) fill the variables; `variables` lists `boundVariables` ids, resolvable only where the Variables API answers (Enterprise only, owner 2026-11-24: colours and text styles come from the nodes on every plan).
- `report`: `{figma, name, code}`; codes `type` (sticky, connector, slice, widget, table…), `rotation`, `effect` (blur), `fill` (radial/angular/diamond gradient, video, stacked fills), `limit`. Hidden layers are left out silently. ST5 words the codes with `lang()`.

## What bounds the work?
- `max_nodes` 4000 and `max_depth` 40 by default; the node that meets one is reported `limit` once and the rest of the frame is left out.
