---
name: workspace-flows-template-flow-only
description: The flow templates a scheduled action never had (G3): new_customer, quote_approval, text_review; their boxes, the one person setting, how they are tested
---

## Which are they?
- Started by hand, offered to everyone: `new_customer` (sales: welcome task → done → 3-day delay → follow-up task with a 48 h timeout → notify on timeout), `quote_approval` (sales: approval, 48 h, arrows on approved/rejected/expired/withdrawn to two task_open, a notify and an end box), `text_review` (team: writing task → done → approval reading `{{node.write.task_id}}` → task_open on approved, notify on rejected).
- One person setting fills every assignee, approver and person told; the approval asks for a channel (0 in the gallery, so the setup panel requires one).
- Manual subject is '' (`ws_flow_problems()` needs the database for record types; the tests use `ws_flow_graph_check()`). Tested in `test_workspace_flow_templates_flow_only_*`.

Related: workspace-flows-template-format.md
