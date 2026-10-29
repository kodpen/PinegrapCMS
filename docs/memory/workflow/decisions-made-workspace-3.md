---
name: decisions-made-workspace-3
description: Decisions list, workspace part 3 (2026-10-29 afternoon on): the Flows list after the Scheduled actions section goes: refused rows, cancelled rows, a converted row's Run now and History
---

## Where do scheduled-action rows go once the Scheduled actions section is removed? (#3173)
- Decided (architect, 2026-10-29; plan `workspace-flows-look.md` V5, under the owner's "no feature lost" rule of 2026-10-24): refused rows (`kind = 'action'`, `flow_id = 0`, `anchor = ''`, not cancelled: the conversion `ws_flow_problems()` turned away, still run by `ws_scheduled_execute()`) form a second last group of the flow list, "Not converted · N", each drawn by `scheduledCard()` with its buttons as today (Edit retries the conversion, #2753). Cancelled unconverted rows are not listed in Flows: nothing can be done with them there, and their channel or note card stays (answer 4 of the merge). A converted row keeps Run now (`ws_scheduled_run_now`, the only hand start of a time- or event-started flow) and History (`ws_scheduled_get`: the anchor's `ws_scheduled_runs` lines, before and after the conversion, not swept) beside Open flow. A Run on the canvas for a non-manual flow (2b) is not built: a product change for a step of its own if staff ask. No schema.

Related: docs/memory/workflow/decisions-made-workspace-2.md, docs/memory/workflow/decisions-made.md
