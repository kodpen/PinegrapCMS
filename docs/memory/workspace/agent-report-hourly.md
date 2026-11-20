---
name: agent-report-hourly
description: An application agent tells its model at least once an hour in an agent channel (#10546): the model field on the answer route and the brief's line that asks for it
---

## Can an answer carry the model?
- Yes (#10546): `POST /workspace/agent-requests/{id}/answer` takes an optional `model` (the same rule, `ws_agent_report_model_check()`), checked before the dry run, 422 on a breach. Written after the answer (a task run's too) by `ws_agent_report_model_save()`: `report_model` and `report_at` only, the usage windows kept. Before step 11.0.195 the field is ignored.

## When is the agent asked for it?
- Requested in an agent channel (a discussion: its channel's flag), the brief (`ws_agent_brief()`) ends with `ws_agent_report_brief_line()` when the app agent's `report_at` is 0 or at least `WS_AGENT_REPORT_ASK_AFTER` (3600 s) old: add `"model": "<the model you run on>"` to this answer, or POST /workspace/agent-report. No line when fresh, outside an agent channel, or for a model assistant.
- The instructions' request steps say it in one sentence (`ws_agent_instructions_request_lines()`); its version changed with it, so every agent read them again once.
