---
name: agent-canvas-save
description: An agent saves a canvas's picture into Files (POST messages/{id}/canvas/save, canvas_save.php): a file create of the change catalogue, tier 2 at once, tier 1 a card, the formats, the pending refusal
---

## What does a save do?
- `POST /workspace/messages/{id}/canvas/save` (`workspace.messages.canvas.save`, `api_canvas.php`): folder_id, name, format, description, text. The canvas's drawn picture becomes a new `files` row in the folder, in the name of the person the key acts for. Built in CV7.
- Formats: the image editor's export formats on this server (`pg_image_editor_export_formats()`: jpeg and png, webp and avif where a library writes them); png when left out. A PNG is copied as drawn; another format goes through the engine at the save's quality (`pg_image_editor_canvas_export()` in `includes/fn/image_editor.php`).
- Name: the given one, else the canvas file's, with the format's extension (`ws_canvas_save_name()`), made unique by `get_unique_name()`; a blocked upload name is refused.

## How does it follow the risk tiers?
- A save is a change of the `file` kind, action `create` (create-only fields `canvas`, `name`, `format`; with `folder_id`, `description`), so it takes the one proposal route: the route builds the change (`ws_canvas_save_change()`), checks it with `ws_changes_input()`, stores it with `ws_proposal_store()` and runs `ws_agent_apply_within_tier()`.
- The kind carries `create_reversible`: `ws_change_tier()` counts its create-only fields as fields, so a save is tier 2 (a file is binned like any other). Any other kind's create-only field is still tier 3.
- Tier 2 or above: applied at once, the decision under the message names the file (`<#file:id>`); the answer gives `saved: true`, file_id, file_name, file_url. Tier 1: a card the person applies (`saved: false`).
- No Undo on the card: `ws_agent_undo_reverse()` needs a column for every field, and `canvas` and `format` have none. The decision then says "Applied at once within the tier this channel allows …" without the 7 days (`ws_change_apply()`).
- The same change can be proposed through `workspace.changes.propose` ({type: file, action: create, fields: {canvas, folder_id, format, …}}) and by the assistants in their answers.

## What is refused?
- A canvas whose newest version is still pending (`ws_canvas_message_state()`): 409 `conflict` on the route; the check again on apply (`ws_canvas_save_check()`), so a card applied before the picture is drawn fails.
- On `changes` (422): a message that is no canvas or out of the person's reach, no folder, a folder in the recycle bin, a folder the person may not edit (`pg_folder_edit_access()`), a format the server does not write, `content` on a create.
- Who: an application's agent of the channel (`ws_api_canvas_agent()`, then `ws_api_proposal_agent(true)`); Claude's key gets 403, Claude proposes in its answer. Scope workspace:write and files:read (`ws_api_proposal_scopes()`). The hourly proposal limit applies.

## Tests?
- `tests/workspace_canvas_save_test.php`: name, change, refusals, tier, route row. The write (engine encode, files row, decision) needs a database and was not run in a unit test.
