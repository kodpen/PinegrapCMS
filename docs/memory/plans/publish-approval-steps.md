---
name: publish-approval-steps
description: Plan publish-approval, steps PA0 to PA6: the owner's answer, the census, the switch and the held save, the editor's side, the designer's side, the Workspace request, close-out
---

## Which steps are there?
- PA0 (the owner's answer, asked by the architect) Whether a site may choose that a content editor's change waits for a designer (`plans/publish-approval.md`). Until it is recorded the plan's description keeps "not to be started".

- PA1 (S, no code) after PA0: census. One memory file, `plans/publish-approval-census.md`:
  - how the result of `pg_designer_merge_restricted_tree()` is written as operations a proposal takes (a text, a picture's address, per node), and what is lost if it cannot be;
  - what `design_proposals` and its apply, take-back and stale rules need for a proposal a person made;
  - every place that lists or draws a proposal: the Assistant panel, the Workspace message card, the API;
  - what a content-level editor sees after a save today, and what a change inside a shared component would take.
  - The architect confirms the shape on it before PA2; a feature the census finds cannot be built as written comes back as a question.

- PA2 (M) after PA1: the switch and the held save.
  - Existing files: `includes/fn/designer.php` (in `pg_designer_save_page()`, the content-level save of a live page hands the gate's result to the module and writes no page), `includes/settings/features.php` and `features.save.php` (the card).
  - New: `includes/fn/content_proposals.php`, `tests/content_proposals_test.php`.
  - Tests, red first: the switch off changing nothing, each save A2 leaves alone, one waiting change per page and editor, a second save replacing it, an unchanged save withdrawing it.
  - Features: A1 to A4, A16.

- PA3 (M) after PA2: the editor's side. Existing files: `assets/js/style_designer.src.js` (the tab's state, the waiting change laid on the canvas, "Withdraw"), the page load of the designer's panel actions. Pictures to the owner. Features: A5, A6.

- PA4 (M) after PA2: the designer's side. Existing files: the Assistant panel's proposal list (`assets/js/designer_ai.js`), the tab's count. New: the bell lines. Tests: apply writing one revision with both names, a stale change refused, dismiss with its reason, take back refused after a later save. Pictures to the owner. Features: A7 to A12.

- PA5 (S) after PA4: the Workspace request. Existing files: `includes/workspace/approvals.php` (one call in `ws_approval_settle()` to a function of the module, `workspace/workspace-approval-decide-checks.md`). Tests: a designer's yes applying, another member's yes only noted, a no dismissing, a withdrawn change taking its request back. Features: A13, A14, A15.

- PA6 (S) close-out: every A number checked against the code with its PR; `designer/content-proposals.md`; this plan's files and the census deleted and its line in `plans/website-features.md` removed.

## Which steps run side by side?
- PA3 and PA4 run beside each other once PA2 has landed.
