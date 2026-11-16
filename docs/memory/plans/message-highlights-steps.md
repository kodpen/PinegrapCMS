---
name: message-highlights-steps
description: Plan message-highlights, steps HL1 to HL5: the table and the pure part, the server's actions and cleanup, the message side in the browser, My highlights, close-out
---

## Which steps are there?
- HL1 (S) The table and the pure part. Done: #9867 (step 11.0.222).
  - A migration step of the open version with the idempotent helpers: `ws_highlights` as `workflow/decisions-made-schema-23.md` names it; the PR body repeats the columns. Rerun twice on a dev database.
  - `includes/workspace/highlights.php`: `ws_highlights_ready()` (the feature is off until the step ran), `ws_highlight_plain()`, `ws_highlight_find($plain, $quote, $occurrence)`, `ws_highlight_remap_one($row, $old_plain, $new_plain)`.
  - Tests: folding of white space, a quote found by its occurrence, a quote across a line break, a list and a mention, the remap's three outcomes. Features: H2, the pure part of H9.
- HL2 (M) The server's actions. After HL1. Done: #9879.
  - `ws_highlight_set()`, `ws_highlight_note()`, `ws_highlight_remove()`, `ws_highlights_remove_many()`, `ws_highlights_map($message_ids, $viewer)` (a message's `highlights`: id, quote, occurrence, scope, note, the maker's id, name and colour, `mine`, `can_remove`), `ws_highlights_mine($viewer, $scope, $after)` with the counts.
  - The actions beside `ws_reaction_set`, with the same CSRF and membership checks. The cleanup calls of H9 in `ws_message_delete()`, `ws_threads_purge()`, the edit path, the leave path and where a person's reactions are deleted.
  - Tests, red first: who may make each scope, who may remove, the three limits, the same place twice (H5), a `me` row never in another viewer's map, no inbox row and no stamp for `me`, the stamp for `all`.
  - Features: H1 (the server's check), H3 to H9.
- HL3 (M) The message side. After HL2. Done: #9889 (pictures: agent channel 5940-5945).
  - A new `assets/js/workspace_highlights.js` in the Workspace bundle list (`pg_js_bundles()`): the twin of plain and find, the toolbar, drawing into the body after `messageNode()` drew it, the card. Texts with `php tools/tr_add.php`.
  - Tests: the twin on HL1's fixtures. Pictures, light and dark, 1280 and 390: the toolbar on a selection in another's and in his own message; an `all` and a `me` highlight; two overlapping; the card with and without a note; the long selection.
  - Its sheet goes to the owner. Features: H10 to H16.
- HL4 (M) My highlights. After HL3. Done: #9935.
  - The button in My Notes' bar (`startNotes()`), the list, the chips, the jump, bulk removal.
  - Pictures, light and dark, 1280 and 390: the list with both scopes and a note, rows ticked with "Remove n", the confirmation, the empty state. Its sheet goes to the owner. Features: H17 to H22.
- HL5 (S) Close-out: a new `workspace/workspace-message-highlights.md` (`module:` naming the new file), one line in `workspace-text-highlight.md` telling the two apart, every H number checked against the code with its PR, this plan's files deleted.

## What must no step do?
- Change a message's `body`, send anything to anybody, or draw a highlight from a text the server did not check against the message (H2).
