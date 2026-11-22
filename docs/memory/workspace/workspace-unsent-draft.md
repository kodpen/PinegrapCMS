---
name: workspace-unsent-draft
description: The Workspace composer's unsent text kept on the browser (localStorage) per site, viewer, channel and discussion: key, when saved, restored, cleared, limits, what is never kept
---

## Where is an unsent text kept?
- In `localStorage`, one slot per site, viewer, channel and discussion: `draftKey(CFG.api_url, CFG.me, channel, thread)` gives `ws-draft:<api url>|<viewer>:c<channel>[:t<discussion>]`. A person's direct channel is a channel too. Two people on one browser, or two sites on one host, never share a slot.
- The value is `{ raw, labels, at }`: the box's stored markup (as `send()` would post it, tags as tokens) and the chips' labels, so `setMarkup()` draws the chips back with their names.
- `draftKey()` and `draftFresh()` live in `assets/js/workspace_side.js` as `window.PGWsDraft` (`prefix`, `days`, `key`, `fresh`), set before the list looks for its box; workspace.js only calls them (#11079). Without the list's script on the page no draft is kept.
- The list marks a channel or discussion row that holds a draft with a pencil (`workspace-menu-rows.md`).
- No server side, no schema; another browser or device does not see it.

## When is it saved, restored, cleared?
- Saved 400 ms after typing pauses (`keepDraft()`, from the box's `input` event) and at once on blur or when another composer replaces the box (`flushDraft()`). The slot is taken when typing, so a save after a channel switch still writes the old channel's.
- Restored when a channel or discussion opens with an empty box (`restoreDraft()`, at the end of `composer()`).
- Cleared when the box is emptied by typing and in `clearInput()` (after a send, a scheduled send, a file from the file manager with its text, a poll from the text) through `dropDraft()`.

## What is never kept?
- The box while it changes a sent or scheduled message (`editing`, `editingScheduled`): it neither saves nor drops the slot.
- Attachments, files, voice, polls in their form; notes (they save themselves); boxes where nothing can be posted.

## What are the limits?
- `DRAFT_DAYS` 14 (workspace_side.js): an older value is not restored and is removed. `DRAFT_KEEP` 50 slots (workspace.js): `draftPrune()` removes the expired, then the oldest, before every write.
- `draftWrite()` and `draftRead()` call `PGWsSide.redraw()` when a slot starts or stops holding a draft; another tab hears the `storage` event.
- Every read and write is in `try`/`catch`: a private window, blocked storage or a full quota keeps nothing and the box works the same.

## How is it tested?
- tests/workspace_unsent_draft_test.php runs `draftKey()`, `draftFresh()` (from workspace_side.js) and `draftPrune()` under Node and holds the wiring. Restore after a reload and two tabs on one channel (the last save wins) were not run in a browser.
