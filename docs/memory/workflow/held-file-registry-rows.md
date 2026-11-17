---
name: held-file-registry-rows
description: What a PR may change in a file a draft PR holds: its own registry rows, or a function the holder does not touch; the terms; the architect allows each holder (#3824, #10025, #10077)
---

## May a PR change a file a draft PR holds?
- A registry in it: `api_error_catalogue()`, the routes of `api_schema()`, the event catalogue, a catalogue of strings such as `ws_flow_js_strings()`. The PR adds or changes the rows of its own entries there, as lines of their own away from the holder's.
- A function the holder's hunks are not in, when the architect named that function for that holder.
- Both only after the architect allowed it for that holder in the agent channel: the holder's hunks are read first. The PR's body names the holder, and `git merge-tree` merges the file cleanly with the holder's head.
- One such PR at a time beside a holder: they hold the file against each other as any two PRs do (`claim-work.md`), so the next starts when the one before has landed.
- Anything more in the file (a function the holder changes, a shared helper, a reordering, a hunk next to the holder's) waits for the holder to land. A PR that finds it needs one of those stops there.
- The holder is usually a draft that waits for an answer; a PR waiting to land holds its files the same way and the same word is asked.
- A script or stylesheet under `assets/` needs no such word: `held-file-bundled-sources.md`.

## Why is it allowed?
- A registry's entries stand apart, and so does a function the holder does not touch: the PRs merge without a conflict. A holder that waits days for an answer would otherwise stop every step that needs only its own part of the file.
- It is asked of the architect each time because the holder's hunks have to be read first: a holder that reorders the registry or reworks the file's helpers leaves no room beside it.

## What was allowed?
- #3824: one line of a registry beside a draft holder, the first case.
- Architect (2026-11-17): a route's rows and parameters count as one entry, not only a single line; several steps may be allowed beside one holder, one PR at a time, in the order their coordinator chooses. Reason: six steps waited on `includes/api/schema.php` for their route rows alone while its holder, #10025, changed one hunk of it (the parameters of the prototype link's POST) and waited for the owner's look (agent channel message 7852).
- Architect (2026-11-17): beside #10077 (CH3b) on `includes/workspace/flows.php`, DG3 of post-digest adds its own entries to `ws_flow_js_strings()` and its own lines to `ws_flow_js_config()`. #10077's hunks are in the new functions after `ws_flow_lines()`, in `ws_flow_config_normalize()`, in `ws_flow_graph_check()` and in three lines of `ws_flow_js_strings()`; it has none in `ws_flow_js_config()` (agent channel message 8001).
- Architect (2026-11-17): beside #10156 (AM8, waiting to land) on `includes/api/openapi.php`, AM6 (#10173) changes `api_openapi_build()` and adds functions of its own; #10156's hunks are in `api_openapi_components()` and `api_openapi_responses()` (agent channel message 8007).
- Architect (2026-11-17): beside #10025 on `includes/api/schema.php`, AM9 adds its row `precondition_failed` (412) to `api_error_catalogue()` near line 6,278; #10025's one hunk is still the prototype link's POST near line 5,305 (agent channel message 8158).
- Architect (2026-11-17): beside #10042 (PN3) on `includes/designer_screen.php`, DP17 edits the `.sd-toolbar-pages` group and DP2 `#sd-panel-right`, both at once, each only its own element of the markup in `pg_designer_screen_render()`; #10042's one hunk is the `'parts'` row of the config array. Two at a time here because the new interface goes first (poll 127; agent channel message 8248).
- Architect (2026-11-17): a plan's steps file counts as a registry, a step's lines its entry: #10212 beside #10206 and #10210 (agent channel message 8344).

Related: docs/memory/workflow/git-branches-and-shared-files.md, docs/memory/workflow/claim-work.md, docs/memory/workflow/held-file-bundled-sources.md
