---
name: agent-canvas-layer-writes
description: An agent writes a canvas's layers one by one (canvas_layer_write.php): add a text, picture, shape or icon, style, order, name, lock, a layer by its name, GET workspace/canvas/fonts
---

## Which layers can an agent add?
- `add` with `type` (`svg` when left out, as before): `text`, `picture`, `shape`, `icon` (`ws_canvas_write_layer()`), each the layer the editor's own tool makes, with its defaults for what is left out; a layer without `x` and `y` is centred. Any add takes `name` and `lock`. Built in #8834.
- text: `type: 'text'`, the fields of `M.textDefaults()` but colour `#000000` (the editor's white is lost on a white board) and size a twelfth of the board's shorter side (at least 12, as `textSize()`); w and h by the estimated layout (half the size a letter, `ws_canvas_write_text_box()`), refitted by the editor to the font.
- picture: `file_id` or `file` (the address), a file of the channel or with `files:read` a public one (`ws_canvas_split_file_row()`, `agent-canvas-split-parts.md`); 40 % of the board at most, centred, as the Sticker tool's "Picture from Files".
- shape: `rectangle` (type `rect`), `ellipse` (x, y, w, h; a third of the board by default) or `line` (`x1, y1, x2, y2`, the box along it as `M.lineBox()`); colour `#000000`, fill `solid`, the Draw tool's medium stroke (`M.strokeFor()`), dash `solid`, a radius cut to half the shorter side.
- icon: a Bootstrap Icons name (with or without `bi-`) read from `assets/fonts/bootstrap-icons/bootstrap-icons.json`; a glyph layer (`set: 'bi'`, `code`, colour `#212529`), a fifth of the shorter side, square (`ui/image-editor-glyph.md`).

## What do style, order, name and lock change?
- `style`: only the fields of the layer's type (`ws_canvas_write_style_fields()`): text font, size 2-4000, weight (snapped to 300, 400, 600, 700, 900), italic, align, color, bg, lineHeight, spacing; drawn kinds color, stroke, dash, fill (closed kinds), radius (rect, bubble); a glyph its color; any layer opacity. Another field is refused. A styled text is refitted, its aligned side kept (`M.textFit()`).
- `order`: `to` front, back, above or below a `target` layer.
- `name`: 1-80 letters no other layer has as its name or id. `lock`: `locked` true or false. A locked layer takes only these two.
- The editor's layers list shows `l.name` first for any layer (`Editor.prototype.layerName`).

## How is a layer named in an operation?
- `layer` (and `target`) is an id, else a name one layer alone has (`ws_canvas_layer_index()`); a name two layers share names neither (422).

## Which fonts may an agent give?
- `GET /workspace/canvas/fonts` (`workspace:read`, `ws_canvas_write_fonts()`): the stacks sans, serif, hand, display, mono, then the site's style fonts and the Google catalogue as `g:<family>`; a font is also accepted as the bare family (`ws_canvas_write_font()`).
- The assistants' `edit_canvas` names the same operations; their pictures are the channel's files only.
