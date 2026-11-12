---
name: agent-canvas-templates
description: Canvas templates (CV5): Save as template, the config_text list, the settings card, GET workspace/canvas/templates, a canvas made from template and fill, refusals
module: canvas_templates
---

## How does a canvas become a template?
- A person opens a canvas from Files in the image editor and chooses "Save as template" in the save menu ("Remove from templates" when it is one). Only for a canvas opened with its document (`pg_image_editor_canvas_open_doc()`) in replace mode; the open answer carries `template` (bool). Built in CV5.
- Action `template` {file_id, on} (`pg_image_editor_action_template()`): `pg_image_editor_file()` checks the person may edit the file's folder; a Site Log line says which file.
- The list is one `config_text` row, `canvas_templates`, a JSON list of file ids (`includes/fn/canvas_templates.php`), no column. At most 50 (`PG_CANVAS_TEMPLATES_MAX`). Marking reads and writes it as one `pg_config_text_claim()`, retried three times, so two people marking at once do not drop each other's template.

## Where is the list seen and changed?
- Site Settings › Assistants and agents › Canvas templates (`pgset-ws-canvas-templates`, `canvas_templates_card.php`): each template's file name, size and named layers. An administrator unticks one and saves to take it off; a template whose file is gone or changed outside the editor is not shown and goes on that save (`ws_canvas_templates_settings_keep()`). Marking is only done in the editor.

## What does an agent read and make?
- `GET /workspace/canvas/templates` (workspace:read): id (the file id), name, width, height, background, and the named layers bottom up: name, kind (`pg_canvas_template_kind()`: text, picture, shape, icon, else the editor's type) and a text layer's words. A layer with no name is not listed and cannot be filled.
- `POST /workspace/channels/{id}/canvases` takes `template` instead of `svg` (one of them: 422 on svg otherwise), with `fill`: name => words for a text layer (at most 2000 letters), or a file for a picture layer (its id, its address, or {file_id} / {file}; a file of the channel, or with files:read a public file, as layer writes resolve it). `background` and `layers` are ignored then.
- `ws_canvas_template_fill()` (`canvas_template_fill.php`) copies the template's document, drops its `picture` and `by` marks, and sets the words or the picture's `src` and `fileId` (box kept). A locked named layer is still filled: the lock keeps people from moving it, not the template from being used. The new canvas is pending until a browser draws it, as an SVG one is.

## What is refused?
- On `template` (422): a file not on the list or no longer a canvas. On `fill` (422): not an object of names, more than 50 names, a name the template does not have (or two layers share), a layer neither text nor picture, words that are no string or too long, a picture that does not resolve. Every refusal ends with the template's named layers as "name (kind)".

## Tests?
- `tests/workspace_canvas_templates_test.php`: the list and its toggle, the summary, the fill and its refusals, the card's keep rule, the route rows. The editor's menu item, the card and the claim need a browser or a database and were not run.
