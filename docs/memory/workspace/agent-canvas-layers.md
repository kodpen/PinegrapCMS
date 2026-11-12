---
name: agent-canvas-layers
description: An agent's SVG taken apart into a layer per element on the server (layers "split", canvas_split.php): placement, names, definitions, the bounding boxes, the browser fixture both sides read
---

## What does layers "split" do?
- `POST channels/{id}/canvases` with `layers: "split"`, and an edit's `add` or `svg` operation with it, give a layer per top-level element instead of one imported layer, as the editor's Import then Break apart would (`ui/image-editor-import.md`). Without the word (or `one`) it stays one layer; any other word is refused (422 on `layers`, or "Operation n:" on `operations`). Built in #8799.
- Each element is placed by the root's matrix times its own (`ws_canvas_split_place()`, `M.svgPlace()`); one that would skew keeps its transform inside a `g` and is placed by the root alone. A group stays one layer. Any element that is not in `ws_canvas_split_editable()` (a `use`) is kind `kept`, locked.
- An `add` puts the layers where the one layer would have gone: `ws_canvas_fit_box()` of the SVG's own size, or the op's x, y, w, h, rot, as a frame (`ws_canvas_split_into()`, Break apart's `M.svgMatrix(l)` times each part).
- The assistants' `draw_canvas` takes `layers`; `edit_canvas` names it for add and svg (`canvas_ai.php`).

## Where do ids, definitions and style rules go?
- Ids are renamed with `i<n>-` against the document's ids, as Import does (`ws_canvas_taken_ids()`), so a second add gets `i2-`.
- Definitions (defs' children, style, gradients, patterns, clip paths, masks, markers, filters, symbols) go into the document's `svgDefs`, one `<defs>`; the SVG's renamed root id becomes `svgRootId`, the scope of its sanitized style rules. When the document already has a root id, the new scope is renamed to it in the definitions (Break apart's `renameIds`). An `svg` operation starts a fresh document, so the old definitions go.
- A `use` pointing at an element of another layer draws on the stage but not in that layer's own picture: the same as Break apart.

## How is a layer named, and what does the read give?
- A layer whose element had an id gets `name`: the id as written, without the `i<n>-` and the sanitizer's `pgsvg-<hash>-` prefixes. The editor's layers list shows `l.name` first for an svg layer (`Editor.layerTypes.svg.name`).
- `GET messages/{id}/canvas`, the create's and edit's answers list each layer's id, type, kind, `name` (null without one) and box (`ws_canvas_layer_read()`).

## How is a box measured without a browser?
- The editor measures with `getBBox()`; `ws_canvas_split_bbox()` works it out from the geometry (fill, no stroke, own transform not applied): rect, image, circle, ellipse, line, points, a path exactly (curves to their turning points, arcs to their extremes, flags written together), a group through its children's transforms, a `use` as the element it names plus x, y; `display:none` draws nothing.
- Text is an estimate: half its size a letter, a sans serif's ascent 0.905 and descent 0.212 of the size, moved by `text-anchor`, a `tspan` with its own x or y a run of its own. The browser's box differs by some pixels; it matters only for a text that stays an svg layer (`agent-canvas-split-parts.md`).

## How do the two sides stay together?
- `tests/fixtures/canvas_split/chart.svg` and `chart.json`: the boxes the editor's `svgLayers()` measured in Chromium for the sanitized file. `tests/workspace_agent_canvas_split_test.php` checks the PHP split against them and runs the model under Node (`M.svgTransform()`, `M.svgPlace()`, `M.svgMatrix()`, `M.svgOwnBox()`, the fixture's places) against the ports.
- A change to either side's measure or placement redoes the fixture from the browser: load `pg_image_editor.min.js` in a page and call `PgImageEditor.parts.Editor.prototype.svgLayers.call({}, sanitizedSvg)`.
