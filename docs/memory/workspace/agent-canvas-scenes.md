---
name: agent-canvas-scenes
description: An agent sizes a canvas and makes scenes (canvas_board.php): size by preset or pixels around the middle, background, scenes added, chosen, removed, timed; at most 30
---

## Which board operations does an edit take?
- `size`: `preset` square 1080 x 1080, portrait 1080 x 1350, story 1080 x 1920, share 1200 x 630, banner 1920 x 600 (`ws_canvas_board_presets()`, the New image dialog's `M.BOARD_PRESETS`), or `width` and `height`, whole numbers 16 to 8000. Both, neither or another value: 422 (`size`).
- The board keeps its middle (the Canvas tool's centre anchor, `M.boardAnchored()` with 0.5, 0.5): the shift is rounded as JavaScript rounds (`ws_canvas_board_round()`, a half goes up, also below zero) and every layer of every scene moves the other way (`ws_canvas_board_shift()`, the twin of `M.mapLayer()` for a move: a line's ends, `pts`, `nodes`, a box around its centre, a turned box's `rot` written in -180 to 180).
- The editor's own Canvas tool (`Editor.prototype.setBoard()`) moves only the open scene's layers; the server moves all of them, so a presentation stays centred in every scene.
- `background`: the open scene's, `#rrggbb` (written lower case) or `transparent`. Built in #NNNN.

## How does an agent make scenes?
- `scene` with `action` (`ws_canvas_board_scene()`): `add` (`copy` true for a copy of the open scene, `name` up to 80 letters no scene has, `duration`): a new scene after the open one, opened; `choose` (`scene`); `remove` (`scene`), never the only one; `transition` (`scene` optional, the open one; `kind` none, fade, slide, zoom or wipe; `ms` 100 to 2000, kept in steps of 100); `duration` (`scene` optional; `duration` 1000 to 60000 ms).
- A scene is named by its id (`s1`, `s2` …) or by a name one scene alone has. A canvas of one scene has nothing to choose, remove or give a transition (422 `scene`); its duration is the document's.
- The steps are the PHP twins of `M.sceneStart()`, `sceneAdd()`, `sceneOpen()`, `sceneDelete()` and `sceneSet()` (`ui/image-editor-scenes.md`): the open scene's layers, background and duration are the document's own, a closed one keeps them in its entry; down to one scene `scenes` and `scene` go.
- The layer operations (add, text, box, style …) work on the open scene, so an agent chooses a scene before changing it.
- At most 30 scenes (`ws_canvas_board_scene_max()`; the editor holds 50): a thirty-first is refused (`scenes`), which keeps a presentation well under the 1 MB document.

## What does a read give?
- `GET /workspace/messages/{id}/canvas` and `read_canvas` add `scenes` (`ws_canvas_board_scenes_read()`): id, name (null when none), open, number of layers, background, duration, transition `{kind, ms}`, in playing order; an empty list for one scene. `layers` are the open scene's.

## Where is it tested?
- `tests/workspace_agent_canvas_scenes_test.php`: presets, the rounding, every layer kind moved, the refusals, the scene steps, the cap, the read, the rows of the route and the tools, and the same steps run through `pg_image_editor_model.js` in Node giving the same document.
- The route's `operations` `of` list names the new fields, so `check_api_schema.php` covers them; the MCP tool is the same route, the assistants' `edit_canvas` describes the same operations.
