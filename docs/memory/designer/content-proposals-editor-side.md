---
name: content-proposals-editor-side
description: Waiting content changes, the editor's side in the Page Design Studio: the tab's mark, Withdraw, the waiting change laid on the canvas, the answer shown to the editor, the designers' tab count
module: content_proposals
---

## What does the page's tab show?
- (PA3, #10188) A mark after the page's name, from `pg_content_proposals_studio_rows()` (pure): the person's own waiting change ("Waiting for approval", amber pill); else the newest answer to one of their changes not closed, for 30 days (`PG_CONTENT_PROPOSALS_KEEP_DAYS`): applied, dismissed by a designer, or stale (an icon); else, for roles 0 and 1 only, how many changes wait on the page (a number).
- A change the editor withdrew, or one taken back (`reverted`), is no answer. Nobody else's change or answer reaches an editor; a Manager gets no count.
- The mark opens a note under it (`#sd-cc-pop`); clicking it never switches the tab. The count opens `content_changes.php` (PA4a's list) in a new browser tab.
- The list of every page (the strip's button, the only way on a phone) shows the same mark on the page's row; its note then sits under that button.

## How does the Studio read it?
- One `designer` sub-action, `content_change_state` (`pg_content_proposals_studio()`), answered before the designers-only allow-list, so every role that opens the Studio reads it; it returns only the person's own rows and, for roles 0/1, the counts. Read once when the editor opens (after `_collabStart()`) and again after a content level's save.
- `designer_screen.php` is not changed: the page payload stays as it was.

## How is the waiting change laid on the canvas?
- (A6) The server puts the change's operations on the page as stored now (`pg_content_proposals_laid()`, through `pg_content_proposals_apply_ops()`) and hands the tree and the content-level settings. The Studio lays them on the tab as its saved state (`_pgContentLay()`): the tab stays clean, so nothing writes them but a new save.
- A tab the editor already changed is left alone. A change that no longer fits (a designer changed the page) is not laid; its note says so and offers Withdraw.

## What does Withdraw do?
- `content_change_withdraw` → `pg_content_proposals_withdraw()`: only the editor who made it, only while it waits (`pg_content_proposals_withdraw_check()`); the row becomes `dismissed` with `decided_by = requested_by`, as a save of the page as it is does, and its Workspace request is taken back. Tells nobody.
- The canvas then gets the page as stored (`page_load`), unless the editor changed it since: their edits stay.

## How is an answer closed?
- The note's Close: `content_change_seen` → `pg_content_proposals_answer_seen()` sets `steps.seen` on the editor's own closed row; no column. The open page's answer opens by itself once per load.
- The words are the bell line's sentence (`pg_content_proposals_answer_text()` → `pg_content_proposals_bell_words()`), the dismissal's reason whole. Role 3 has no bell, so this is where it is told (architect, agent channel message 6485).

## Which test guards it?
- `tests/content_proposals_editor_test.php`: the own waiting change, nobody else's, the count by role, each answer and its reason, closed and old answers, withdraw's check, the wiring.
