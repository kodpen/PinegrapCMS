---
name: post-digest-steps
description: Plan post-digest, steps DG1 to DG5: the records box on the server, the contact group as a campaign, the canvas, the ready flow, close-out
---

## Which steps are there?
- DG1 (M) The records box, server only: done in #9978 (kind `list_records`, `includes/workspace/flow_records.php`, `pg_sw_form_feed_pick()`; `workspace/workspace-flows-records-window.md`). Features D1 to D6, D13; the canvas form waits for DG3.

- DG2 (M) after DG1: the contact group as a campaign: done in #10026 (e-mail recipient `group`, `records` box; `workspace/workspace-flows-email-group.md`). Features D7, D8, D9, D11, D12. Pictures of the mail to the owner through the architect.

- DG3 (M) after DG2: the canvas.
  - Existing files: `assets/js/workspace_flow.js` (the box in the palette, its form, the e-mail box's recipient), `assets/js/workspace.js` (the box's icon). Both are held by other open PRs: the step names the clash and opens when they have landed.
  - Pictures: the records box's form, the e-mail box with a group. To the owner; not marked ready before his look.
  - Features: the canvas side of D1, D3, D7.

- DG4 (S) after DG3: the ready flow. Existing files: `includes/workspace/flow_templates.php`. Tests: `tests/workspace_flow_templates_test.php`. Features: D10.

- DG5 (S) close-out: every D number checked against the code with its PR; `workspace/workspace-flows-records-box.md`; this plan's three files deleted and its line in `plans/more-features.md` removed.

## Which steps wait for each other?
- Each waits for the one before it.
