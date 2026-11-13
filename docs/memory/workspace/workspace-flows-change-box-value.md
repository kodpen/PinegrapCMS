---
name: workspace-flows-change-box-value
description: The change box's record named by one value of its run, such as {{trigger.record_id}} (QS1b, PR #<PR>): setting, publish check without the record, the value read as the box runs, try, refusal
---
## How is a record named by a value checked?
- `id` is one of the kind's texts, so a value of a later box is a graph problem; a text that is not one value alone is a draft problem.
- At publish `ws_step_check()` passes `$id_later` to `ws_scheduled_action_input()`: kind, verb, rights and fields are checked, the record is not.
- As the box runs (`ws_step_run()`), the approved write of a role-3 proposal and a box try with a run, `ws_step_change_id()` (pure) fills the value first; then everything is checked on that number as on a typed one, starter included. No whole number from 1 fails the box with `ws_step_change_id_refusal()`, without retries. A try without a run checks the rest and says it needs a run.
- Why: "Change the order's status" on the order that started the run and the quick setup's approval recipe (`workspace-flows-quick-setup.md`), PR #<PR>.
