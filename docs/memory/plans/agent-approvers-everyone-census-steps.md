---
name: agent-approvers-everyone-census-steps
description: AE1 census (2026-11-14): the exact scope of AE2 to AE4 (agent approvers on the server, the form and card, the API) and the store an agent approver needs (a table of its own, decided)
---

## What does each step change?
- Readers and routes: `agent-approvers-everyone-census.md`.
- AE2 (schema): the store decided below;
- AE2 (bound): `ws_approval_input()` takes `agents` only with an explicit by-hand switch, passed by the panel action and by the API when `ws_api_writer_app_id()` is 0; the asked agent woken once through `ws_agent_after_send()` in followed mode with the request's brief (`ai_state`);
- AE2 (answer): `ws_approval_agent_decide()` sharing the checks; evaluate, settle, the card's payload and the discussion delete read the agents.
- AE2 tests (red first): the by-hand switch refuses agents for a flow's call and an application's, an agent never answers a request its application wrote, `ws_approval_outcome()` counts an agent as a person.
- AE3: the Approvers field lists the channel's agents under the people (paused ones left out), the card draws them with the agent badge; new texts by `lang()`.
- AE4: `approvers` (or `agents`) on create; the decide route opens to the asked agent's own key (`ws_api_agent_caller()`), stays off MCP, keeps the dry run; `WorkspaceApproval` carries the agents; `php tools/check_api_schema.php`.
- AE4b: done (#9608), `workspace/approval-assistants.md`.
- AE4c: done (#9622), `workspace/approval-assistants.md`.

## Which store does an agent approver need?
- Decided (owner, channel 3501; shape 3506): a table of its own, `ws_approval_agents` beside `ws_approval_guests`: `approval_id`, `agent` VARCHAR(32) (the `ws_ai_requests.agent` value), `request_id`, `decision`, `note`, `decided_at`; key (approval_id, agent). `ws_approval_people` and its readers stay untouched. Added by AE2 (11.0.210).
