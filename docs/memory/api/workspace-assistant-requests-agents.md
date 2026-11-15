---
name: workspace-assistant-requests-agents
description: GET /workspace/assistant-requests agent filter (CA5 #9770): agent:<id> and model:<id>, both row shapes, proposals kept out, the reader's reach first, label on a registry agent's row
---

## What does the agent filter take?
- `claude`, `ai`, `mcp` as before (`ws_ai_agent_where()`), and since CA5 (#9770) `agent:<id>` (a registry app agent's requests) and `model:<id>` (a model assistant's); the id is `agent_id` of `GET /workspace/channels/{id}/agents`. Any other value answers 422 on `agent`.
- The param is a string (max 32), not an enum: the validator has no pattern type, so the handler refuses other shapes.

## Which rows does agent:<id> match?
- `ws_api_assistant_requests_registry_where()`: every key of `ws_agent_activity_keys()`, so `agent:<id>` and the older `app:<id>`; an `app:<id>` row only with `message_id > 0`, since a proposal carries `app:<api app id>` and never sits on a message. With this filter the route skips the general proposal exclusion (`ws_proposal_where()`), which would drop the older rows.
- Without the `agent` column the filter answers an empty list.

## Who sees which rows?
- The same reach as every other request (`ws_api_assistant_requests_reach()`): channels the owner may read (`ws_api_readable_channel_ids()`, Claude's own key also narrowed by `claude_access`), runs on tasks they see, their own channel-less requests. The filter is ANDed to it, so a request of a channel the owner cannot read stays out. No new scope: `workspace:read`.

## What does a row carry?
- `agent` is the queue's value for a registry or model row (`agent:<id>` also for an old `app:<id>` row, `model:<id>`); before CA5 such rows of the unfiltered list said `claude`.
- `label` (string or null): the line the panel shows under the waking message, from `ws_agent_request_state()` (e.g. "Waiting for @x", "@x answered", "@x saw it · no answer needed"); null for Claude, Pinegrap AI and MCP rows. `ai_state` is not read here, so an approval agent's answered request without a message reads "saw it · no answer needed".
