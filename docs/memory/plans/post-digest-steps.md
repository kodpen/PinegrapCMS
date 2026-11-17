---
name: post-digest-steps
description: Plan post-digest, steps DG1 to DG5: the records box on the server, the contact group as a campaign, the canvas, the ready flow, close-out
---

## Which steps are there?
- DG1 (M) The records box, server only: done in #9978 (kind `list_records`, `includes/workspace/flow_records.php`, `pg_sw_form_feed_pick()`; `workspace/workspace-flows-records-window.md`). Features D1 to D6, D13; the canvas form waits for DG3.

- DG2 (M) after DG1: the contact group as a campaign: done in #10026 (e-mail recipient `group`, `records` box; `workspace/workspace-flows-email-group.md`). Features D7, D8, D9, D11, D12. Pictures of the mail to the owner through the architect.

- DG3 (M) after DG2: the canvas: done in #10176 (palette, records box form, e-mail box's contact group; beside #10077 by the architect's leave, agent channel 8001). Pictures to the owner before ready.

- DG4 (S) after DG3: the ready flow. Existing files: `includes/workspace/flow_templates.php`. Tests: `tests/workspace_flow_templates_test.php`. Features: D10.

- DG5 (S) close-out: every D number checked against the code with its PR; `workspace/workspace-flows-records-box.md`; this plan's three files deleted and its line in `plans/more-features.md` removed.

## Which steps wait for each other?
- Each waits for the one before it.
