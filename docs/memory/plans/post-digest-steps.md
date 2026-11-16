---
name: post-digest-steps
description: Plan post-digest, steps DG1 to DG5: the records box on the server, the contact group as a campaign, the canvas, the ready flow, close-out
---

## Which steps are there?
- DG1 (M) The records box, server only. No schema.
  - The draft PR's body says where a run's start moment is read (the start's runs keep one, `ws_scheduled_runs.started_at`) and how a drawn flow's step is handed it (`ws_step_action_row()` gives 0 today); the architect confirms it there before the rest is written.
  - It also says which column is the date the feed gives a record, and whether the moment a record was set live is kept anywhere. If it is, the window reads that moment; if not, it goes back to the architect: no column is added by itself.
  - Existing files: `includes/fn/widgets.php` (the feed's pick split out of `pg_sw_form_feed_parts()` into a function that gives rows, which the feed then calls), `includes/workspace/flows.php` (`ws_flow_kinds()`, the normalize case), `includes/workspace/steps.php` and `flow_runs.php` (the run case, the window, the Try answer), `includes/workspace/flow_agent_box.php` (the tiers).
  - New: `includes/workspace/flow_records.php`, `tests/workspace_flow_records_test.php`.
  - Tests, red first: the feed's output unchanged after the split; a private page refused; a record at the earlier start in and at this start out; a record published while a run works given by the next run; a record dated before the window counted as left out; the first run from the switch-on; a start that fires twice.
  - Features: D1 to D6, D13.

- DG2 (M) after DG1: the contact group as a campaign.
  - Existing files: the e-mail box's run (`includes/workspace/flow_mail_campaign.php`), the normalize case of `flows.php`.
  - New: the records block of the body, in the module.
  - Tests: a group send making one campaign row with its recipients; opt-in and a bounced address left out; no mail at count 0; Try mailing only the person and moving no window.
  - Pictures: the mail on a phone and a desktop, with and without pictures. To the owner.
  - Features: D7, D8, D9, D11, D12.

- DG3 (M) after DG2: the canvas.
  - Existing files: `assets/js/workspace_flow.js` (the box in the palette, its form, the e-mail box's recipient), `assets/js/workspace.js` (the box's icon). Both are held by other open PRs: the step names the clash and opens when they have landed.
  - Pictures: the records box's form, the e-mail box with a group. To the owner; not marked ready before his look.
  - Features: the canvas side of D1, D3, D7.

- DG4 (S) after DG3: the ready flow. Existing files: `includes/workspace/flow_templates.php`. Tests: `tests/workspace_flow_templates_test.php`. Features: D10.

- DG5 (S) close-out: every D number checked against the code with its PR; `workspace/workspace-flows-records-box.md`; this plan's three files deleted and its line in `plans/more-features.md` removed.

## Which steps wait for each other?
- Each waits for the one before it.
