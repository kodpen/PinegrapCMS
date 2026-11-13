---
name: designer-autosave
description: Visual Page Editor autosave: one browser entry per design and page, kept until the server has it, swept on start, oldest removed for room, one notice when a write fails.
---

## Where does the editor autosave?
- In `localStorage`, key `pinegrap_designer_autosave_v2:<design id or new>:<tab key>` (`_autosaveKey()`, `assets/js/style_designer.src.js`), written 2.5 s after an edit (`scheduleAutosave()`, `_doAutosave()`): the tree, the design's CSS, JS and fonts (`_snapshotAssetFields()`) and the shared-component cache.
- A pending write is flushed before a save and before a tab switch (`_sdAutosaveFlush()`); the tab switch used to drop it.

## Who offers it back?
- The "Unsaved work" prompt (`checkAutosaveRestore()`) only for a page never saved (the blank first tab of a new design, key `new:new1`), refusing entries older than 7 days.
- For a saved page the History panel's Autosave row (`_readAutosaveAsSnapshot()` in `openSnapshotsPanel()`).

## When is an entry removed?
- After the server answered a save with success: the entries of the pages it sent, under the keys they had when the save started, if no newer than the save (`_sdAutosaveForget()`). An edit made during the request keeps its entry; pages held back by the Publish sheet keep theirs.
- Never before the reply. `prepareFormData()` used to remove the entry before sending, so a refused save ("Page name is required.", a session signed in as another user) lost the work on reload (#8999, fixed in #PRNUM).
- On editor start (`_sdAutosaveSweep()`): unreadable entries, entries older than 7 days, and after one day those of tabs no reload brings back (any tab key but `p<page id>`, `new1` and `p0`: new tabs `new<time>_<n>`, template tabs). Entries of other designs and deleted designs go with the 7 days.
- When a write does not fit (`_sdAutosaveWrite()`): the oldest other entries one by one, retrying after each.

## What does the user see when it fails?
- One warning per editor session that stays until closed: "Autosave is not working: this browser did not keep the changes (its storage for this site may be full). Save to keep them." Before, `_doAutosave()` swallowed the error; one browser held 68 entries filling 5.2 MB of its 5 MB, and no autosave fitted (#8998).

## Why does a new design keep one shared slot?
- The prompt reads only `new:new1`, so it is the one way back to a design closed before its first save. A unique key per new design (#8998's hint) would make that work unreachable; the prompt names the time and design name and offers "No, Delete". Not decided: offering a list of abandoned new designs instead.

## What does a save reply that is not JSON say?
- A 200 that is not the editor's JSON is the token check's page or the sign-in page: the toast says the session may have ended or another user signed in, and to sign in again in another tab and save again, or reload and load the Autosave from History. "Publishing failed." stays for a JSON reply without a message.

Tests: `tests/designer_autosave_test.php`.
