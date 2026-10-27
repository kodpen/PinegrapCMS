---
name: workspace-flows-approval-box
description: The approval box of a flow (F5c): asks, waits on approval:<id>, leaves on approved, rejected, expired, withdrawn or failed; deadline in hours, woken by approval.decided or the hourly look
---

## What is it?
- Step kind `approval` in `ws_step_kinds()` (type `approval`, `ws_scheduled_do()` asks it), with its own `outcomes`: approved, rejected, expired, withdrawn, failed (`ws_flow_kinds()` reads a step's `outcomes`, else done/failed). Outputs `approval_id`, `message_id`. Tests `tests/workspace_flow_approval_test.php`.
- Settings `channel_id`, `title` (the box's title on the canvas), `text`, `approvers` (up to 50), `rule` (any, all), `closes_in` ('' or 1 to `WS_STEP_APPROVAL_CLOSES_MAX` = 720 hours). Publish problems: no channel (an approval needs one), no title, nobody asked; `ws_approval_input()` checks the people against the channel with the owner's rights.

## How does it run and wait?
- `ws_step_run()` sets `closes_at` = now + hours, then `ws_scheduled_do()`, whose approval case now returns `approval_id` and passes `closes_at` (parity line `approval.closes_at`; the scheduled form sets none).
- Asked: `ws_flow_approval_wait()` in one conditional UPDATE writes effect and wait_key `approval:<id>`, `wait_event` `workspace.approval.decided` (`WS_FLOW_APPROVAL_WAIT`), `wake_at` +1 h. Not asked: the step's failure settles as any step (failed, or done on a failed arrow).
- Woken by `ws_flow_wake()` (`ws_flow_wake_key()` maps the event's `id`) or the hourly recheck (`ws_flow_step_recheck()` → `ws_flow_approval_recheck()`), which reads the request itself.
- Stopped half way after `began:approval`: fails (never asked twice); with `approval:<id>` written: resumes waiting.

## How does it settle? (`ws_flow_approval_settlement()`)
- approved, rejected: done on that outcome; no arrow on it ends the branch.
- expired, withdrawn (a request gone counts as withdrawn): done where an arrow leaves on it, else the box fails, as a cancelled task fails a task box.
- A run cancelled or ended meanwhile: the box is skipped, the request stays open.

## Try and canvas?
- Try: the generic step path (`ws_scheduled_action_text()`): "Ask … to approve … in #…"; nothing is asked.
- `workspace_flow.js`: KINDS/palette `approval` (`bi-patch-check`), `approvalFields()`, outcome labels `fl_on_approved` …
