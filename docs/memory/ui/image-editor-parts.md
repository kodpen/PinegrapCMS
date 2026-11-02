---
name: image-editor-parts
description: The image editor in parts: core, model, one file per tool; the hooks a tool hangs on the editor (panels, pointers, layer types), its model section, labels, API actions, styles and tests
module: image_editor
---

## Which files make up a tool?
- `assets/js/pg_image_editor_<tool>.js`: crop, adjust, layers, draw, text, sticker, redact, versions, artboard, export, vector, svg, motion, access, bundled in that order after the model and the core (`pg_js_bundles()`). Each is a function over `window.PgImageEditor.parts` (`Editor`, `M` the model, `SVGNS`, `icons`, `config`, `find`, `t`, `el`, `icon`, `section`, `tile`, `decimal`, `shapeRadius`, `button`) that puts methods on `Editor.prototype` and registers its hooks.
- Its pure maths in its section at the end of `pg_image_editor_model.js`, listed in the section's object (`DRAW`, `TEXT`, …); the objects join `PgImageEditorModel`, and a name taken twice stops the model loading. Tested under Node in `tests/image_editor_<tool>_test.php`.
- Its labels in `pg_image_editor_strings_<tool>()` (`pg_image_editor_parts()` lists the parts; a key belongs to one part, `tests/image_editor_parts_test.php`) and its API actions as `pg_image_editor_action_<name>($request, $user)` (lower case; open and save stay the core's), under its line at the end of `includes/fn/image_editor.php`. Its rules under its line in `pg_image_editor.src.css`, before the small-screen rules.
- Shared panel controls live in the core: the colour palette is `X.colors` and `X.swatches(label, value, pick(color, live), opts)` (`opts.custom` false drops the colour input, `opts.transparent` puts a transparent swatch first), with its labels (`customColor`, `colorWhite` … `colorPurple`, `colorTransparent`) in `pg_image_editor_strings_core()` and its rules in the stylesheet's Panel controls. A tool never defines its own palette: two parts defining a key fail `tests/image_editor_parts_test.php`.
- So tools built side by side change different lines; the merge train rebuilds `.min.js` and `.min.css` (`workflow/merge-train-tool.md`).

## What can a tool hang on the editor?
- The comment above `Editor.inits` in `pg_image_editor.js` lists them: `inits` (the tool's own fields), `panels`, `headers`, `hints`, `pointers` (down, move, up; `double` true when it used a double click, else the view zooms), `selectTools`, `layerTypes`, `keys`, `escapes` (true when it used Escape, before the selection clears), `views`, `saveHooks`, `topItems`, `menuItems`, `footItems`.
- A layer type draws itself twice from the same numbers: `node(l)` for the stage, `paint(g, l)` for the save's canvas, both in rotated-space units. As needed: `ratio` (a corner keeps the shape), `ready(l)` (a promise of pictures or fonts; `layersReady()` waits before a save), `name(l)`, `hit(l, p, slack)` (passed to `M.hitLayer()`: a line is thinner than its box), `edit(l)` (a double click with Select), `panel(body, layers)` (shown first in Select's panel when every selected layer has the type), `handles(l)` with `drag(start, h, x, y, e)` (more handles in the layer's frame; `box: false` drops the box handles), `resize(start, box, h)` (the fields a box handle changes, from `M.resizeBox()`'s box: a text scales its letters). The picture type in `pg_image_editor_layers.js` is the example.
- A tool that also selects and moves layers lists itself in `Editor.selectTools` (the handles show and the layer keys work) and gives a press on a handle or a layer to `this.startDrag(e)`. Every change goes through `this.change(mutate, key)` (`image-editor-modal.md`).

## Where do tools still meet?
- A hook a tool needs in the core or the layers file is a small edit of its own, named in the PR body. The save path in `includes/fn/image_editor.php` and `includes/image_engine.php` is shared: steps that change it go one at a time (`plans/image-editor-workers.md` while the plan runs).
