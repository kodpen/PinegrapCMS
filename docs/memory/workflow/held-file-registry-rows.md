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
- A bundled script or stylesheet needs no such word: `held-file-bundled-sources.md`.

## Why is it allowed?
- A registry's entries stand apart, and so does a function the holder does not touch: the PRs merge without a conflict. A holder that waits days for an answer would otherwise stop every step that needs only its own part of the file.
- It is asked of the architect each time because the holder's hunks have to be read first: a holder that reorders the registry or reworks the file's helpers leaves no room beside it.

## What was allowed?
- #3824: one line of a registry beside a draft holder, the first case.
- Architect (2026-11-17): a route's rows and parameters count as one entry, not only a single line; several steps may be allowed beside one holder, one PR at a time, in the order their coordinator chooses. Reason: six steps waited on `includes/api/schema.php` for their route rows alone while its holder, #10025, changed one hunk of it (the parameters of the prototype link's POST) and waited for the owner's look (agent channel message 7852).
- Architect (2026-11-17): beside #10077 (CH3b) on `includes/workspace/flows.php`, DG3 of post-digest adds its own entries to `ws_flow_js_strings()` and its own lines to `ws_flow_js_config()`. #10077's hunks are in the new functions after `ws_flow_lines()`, in `ws_flow_config_normalize()`, in `ws_flow_graph_check()` and in three lines of `ws_flow_js_strings()`; it has none in `ws_flow_js_config()` (agent channel message 8001).

Related: docs/memory/workflow/git-branches-and-shared-files.md, docs/memory/workflow/claim-work.md, docs/memory/workflow/held-file-bundled-sources.md
