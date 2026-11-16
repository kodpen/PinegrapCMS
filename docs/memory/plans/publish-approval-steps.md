---
name: publish-approval-steps
description: Plan publish-approval, steps PA1 to PA6 (PA0, the owner's answer, is done): the census, the switch and the held save, the editor's side, the designer's side, the Workspace request, close-out
---

## Which steps are there?
- PA0 Done (2026-11-16, agent channel poll 64, message 5749): the owner's answer that a site may choose that a content editor's change waits for a designer, "Evet, anahtarla seçilebilsin" (`plans/publish-approval.md`).

- PA1 Done (#9882): the census, `plans/publish-approval-census.md` and its parts `publish-approval-census-gate.md`, `-table.md`, `-places.md`, `-features.md`; the architect's decisions on it: agent channel message 5857.

- PA2 Done (#9892): the switch and the held save (`designer/content-proposals.md`).
  - Existing files: `includes/fn/designer.php` (in `pg_designer_save_page()`, the content-level save of a live page hands the gate's result to the module and writes no page), `includes/settings/features.php` and `features.save.php` (the card).
  - Also: the assistant's readers taking the one condition `agent <> 'editor'` (`includes/designer_ai.php`: the panel's requests, `pg_design_ai_proposals_map()`, the dry run; `includes/api/resources/design.php`: the proposal routes and `pending_proposals`; `includes/workspace/ai.php`; `includes/workspace/threads.php`), with a test that none returns an editor row.
  - New: `includes/fn/content_proposals.php`, `tests/content_proposals_test.php`.
  - Tests, red first: the switch off changing nothing, each save A2 leaves alone, one waiting change per page and editor, a second save replacing it, an unchanged save withdrawing it.
  - Features: A1 to A4, A16.

- PA3 (M) after PA2: the editor's side. Existing files: `assets/js/style_designer.src.js` (the tab's state, the waiting change laid on the canvas, "Withdraw"), the page load of the designer's panel actions. Pictures to the owner. Features: A5, A6.

- PA4a Done (#9930): the designer's side, first part: the module's own list (`content_changes.php`), Compare, apply, stale, dismiss with its reason, take back, History naming both. Features: A7 (the list), A8 to A11.
- Architect (2026-11-16, agent channel message 6369): PA4a takes `ready` after its fixes and the architect's second look, without the owner's look (nothing links to its screen and the switch is off on every site). The owner gets one question with PA4b's tab count and bell lines on the same page; PA4b is not `ready` before his look, and a change he asks for goes into PA4b.
- PA4b (S) after PA4a: the bell lines (`includes/notifications.php`, a third set in `pg_notification_off_kinds()` and `pg_notification_off_lines_on()`) and the count on the page's tab. Features: A7 (the count), A9 (the editor told), A12.
  - Architect (2026-11-16, agent channel message 6401): its pictures for the owner use sample data with one waiting change per page and editor (A3) and at least two editors: 1440 light list, Compare open, Dismiss open, 390 list and Compare, 1440 dark, beside the tab's count and the bell lines. The bell line says "Sebep" like the screen's Dismiss field.

- PA5 (S) after PA4: the Workspace request. Existing files: `includes/workspace/approvals.php` (one call in `ws_approval_settle()` to a function of the module, `workspace/workspace-approval-decide-checks.md`). Tests: a designer's yes applying, another member's yes only noted, a no dismissing, a withdrawn change taking its request back, a named channel that is archived or gone asking nowhere. Features: A13, A14, A15.

- PA6 (S) close-out: every A number checked against the code with its PR; `designer/content-proposals.md`; this plan's files and the census files (`publish-approval-census*.md`) deleted and its line in `plans/website-features.md` removed.

## Which steps run side by side?
- PA3 and PA4 run beside each other once PA2 has landed.
