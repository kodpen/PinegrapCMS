---
name: agent-canvas-design-steps
description: Steps CV1-CV8 of plans/agent-canvas-design.md: an SVG taken apart, text and picture layers, layer writes, sizes and scenes, templates, the server's drawing, saving into Files, close-out
---

## Which steps are there?
- S under 400 product lines, M about 1,000; each with its tests and its topic in `workspace/`. `free` once the steps it names have landed.

- CV1 (M) One SVG taken apart. Done in #8799.
  - `POST channels/{id}/canvases` and the edit's `add` and `svg` take `layers: "split"`: a layer per top-level element, each placed by the root's matrix times its own, ids kept unique, as Break apart does (`ui/image-editor-import.md`, `svgLayers()`, `M.svgPlace()`). Without the word: one layer.
  - The server's port is one module; it reads the fixtures the model's tests read (an SVG and the layers it gives). A group stays one layer; styles and gradients go with the layers that use them.
  - The answer and `GET messages/{id}/canvas` list each layer's id, kind, box and name. Topic `workspace/agent-canvas-layers.md`.
- CV2 (M) Text and pictures as their own layers. `free` once #8799 lands.
  - In a split a `text` element becomes a text layer (words, size, weight, colour, alignment, the font when the catalogue has it; else it stays an svg layer). An `image` naming a file of the channel, or of Files the agent may read, becomes a picture layer; an outside address is refused as today.
- CV3 (M) Layer writes. After CV1.
  - `add` with `type` text, picture, shape (rectangle, ellipse, line; fill, stroke, corner radius) or icon (a Bootstrap Icon by name, `ui/image-editor-glyph.md`); `style`, `order` (front, back, above or below a layer), `name`, `lock`. A layer is named by id or by name.
  - A route lists the fonts an agent may name: the editor's catalogue (`ui/image-editor-fonts.md`).
- CV4 (S) Sizes and scenes. After CV3.
  - `size` by name from the New image dialog's presets (`M.BOARD_PRESETS`), `background`. Scenes: add, choose, remove, a transition (`ui/image-editor-scenes.md`); at most 30.
- CV5 (M) Templates. After CV3.
  - A person marks a canvas in Files as a template ("Save as template" in the editor; a list on a settings card). The list is a site setting holding file ids, no column. An agent reads the templates (name, size, the named layers and their kinds) and makes a canvas from one: `template` and `fill` (a name to words or to a file).
  - A name that is missing or of another kind is refused with the list of names.
- CV6 (M) The server draws where it can. After CV1. Owner: optional, never required.
  - A probe first (`pg_image_draw_can()`): can the host's Imagick read an SVG, through which delegate, and draw text from a font file; shown on System status. The host draws a fixture set (shapes, a gradient, a picture, text in a site font) and the step reports how it compares with the editor's renderer.
  - Where it can, a pending canvas gets its picture from the server at once. A document it cannot draw faithfully (text in a font it has no file for, an effect outside the probe) waits for a browser as today; a browser's picture replaces the server's.
  - Guards: only markup that `pg_svg_sanitize()` passes; pictures put in from the files the document names, nothing fetched; limits on size and time, Imagick's resource limits set; no shell.
  - `ui/image-editor-overview.md` corrected: "the server never rasterises SVG" holds only where the host cannot.
- CV7 (M) Saving into Files. After CV3.
  - `POST messages/{id}/canvas/save` (folder, name, format). A tier 2 agent's save is done at once and named in the channel; a tier 1 agent's is a proposal a person approves (`workspace/agent-risk-tiers.md`, the one proposal route). The picture must be there (CV6 or a browser); the formats are the export formats the server writes.
- CV8 (S) Close-out. An overview if the `agent-canvas-*` cluster has ten files, the lasting rules into the topic files, the PR body names the `[NEW]` changelog line, both plan files deleted.

## Which questions are open?
- None. A step that needs a column asks first.
