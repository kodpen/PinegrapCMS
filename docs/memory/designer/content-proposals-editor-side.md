---
name: content-proposals-editor-side
description: Waiting content changes, the editor's side in the Page Design Studio: the tab's mark, Withdraw, the waiting change laid on the canvas, the answer shown to the editor, the designers' tab count
module: content_proposals
---

## What does the page's tab show?
- Nothing yet. Architect (2026-11-17, agent channel message 8619): a draft that waits for an area's new shape holds nothing in that area; the page strip and the Pages list are redrawn by designer-panels (DP19), and the marks there are owed after DP19, drawn as owner poll 132 shows.
- Ready for it (PA3, #10188), in the block before META TAGS in `style_designer.src.js`, called by nothing yet: `_pgContentChangeBadge(p, inList)` (the mark) and `_pgContentAnswerAuto()` (an answer's note opening once).
- Their rules: the own waiting change ("Waiting for approval", its note with Withdraw, opened only by a click); else the newest answer not closed, for 30 days (applied, dismissed by a designer with its reason, stale); else, for roles 0 and 1, the count, opening `content_changes.php`. From `pg_content_proposals_studio_rows()` (pure); nobody else's change reaches an editor; a Manager gets no count.
- The wordless marks are named (`_pgContentMarkName()`): "Applied", "Dismissed", "Cannot be applied", "N waiting changes" (architect, message 8183); colours from the `--sd-*` tokens.

## How does the Studio read it?
- One `designer` sub-action, `content_change_state` (`pg_content_proposals_studio()`), answered before the designers-only allow-list, so every role that opens the Studio reads it; it returns only the person's own rows and, for roles 0/1, the counts. Read once when the editor opens (after `_collabStart()`) and again after a content level's save.
- `designer_screen.php` is not changed: the page payload stays as it was.

## How is the waiting change laid on the canvas?
- (A6) The server puts the change's operations on the page as stored now (`pg_content_proposals_laid()`, through `pg_content_proposals_apply_ops()`) and hands the tree and the content-level settings. The Studio lays them on the tab as its saved state (`_pgContentLay()`): the tab stays clean, so nothing writes them but a new save.
- A tab the editor already changed is left alone. A change that no longer fits (a designer changed the page) is not laid; its note says so and offers Withdraw.

## What does Withdraw do?
- The editor confirms first (`_pgContentWithdrawAsk()`, `pgConfirm`): what is withdrawn and that the page goes back to what is on the site; it cannot be undone (architect, message 8183, chose between a confirm and Undo).
- `content_change_withdraw` → `pg_content_proposals_withdraw()`: only the editor who made it, only while it waits (`pg_content_proposals_withdraw_check()`); the row becomes `dismissed` with `decided_by = requested_by`, as a save of the page as it is does, and its Workspace request is taken back. Tells nobody.
- The canvas then gets the page as stored (`page_load`), unless the editor changed it since: their edits stay.

## How is an answer closed?
- The note's Close: `content_change_seen` → `pg_content_proposals_answer_seen()` sets `steps.seen` on the editor's own closed row; no column. Until DP19 no mark draws the notes, so Withdraw and Close are reached only through them then; an answer's note is to open by itself once per load, the waiting note only on a click.
- The words are the bell line's sentence (`pg_content_proposals_answer_text()` → `pg_content_proposals_bell_words()`), the dismissal's reason whole. Role 3 has no bell, so this is where it is told (architect, agent channel message 6485).

## Which test guards it?
- `tests/content_proposals_editor_test.php`: the own waiting change, nobody else's, the count by role, each answer and its reason, closed and old answers, withdraw's check, the wiring.
