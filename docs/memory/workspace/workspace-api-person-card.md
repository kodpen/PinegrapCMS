---
name: workspace-api-person-card
description: GET /workspace/channels/{id}/members/{user_id}, the person card of a channel in the API and MCP (api_person_card.php): what it returns, who may call it, seen only for a manager
---

## What does the route return?
- `workspace.channels.members.card` (`workspace:read`, an MCP tool `workspace_channels_members_card`): `WorkspacePersonCard`, the panel's `ws_person_card()` (`person-card-facts.md`) through `ws_api_person_card_present()`.
- Keys: user_id, name, username, role, role_label, title, avatar, presence, departments (id, name, color, lead, leads as {user_id, name}), workday (minutes, hours, days ISO), away (start, end, now, source plan or mail, title; no auto-reply text), clock (zone, time, other), seen.
- The person need not be in the channel, only on the team, as the panel's card.

## Who may call it?
- An application whose owner is a member of the workspace (`ws_api_viewer()`, else 403) and reads the channel (`ws_api_channel_or_404()`, else 404).
- A person not on the team (a guest, a former member, an application) has no card: 404. A guest never holds a key, as a guest has no panel session.
- `seen` is null unless the owner manages the channel (`ws_can_manage_channel()`), as the panel sends it (owner 2026-11-12, `channel-members-agents.md`); the presenter drops it again whatever the card holds.

## Where is it?
- Route and presenter: `includes/workspace/api_person_card.php`, merged into `ws_api_routes()`; objects in `ws_openapi_objects()`.
- Tests: `tests/workspace_api_person_card_test.php`.
- Added in PR #9771 (channel-api-gaps G3).
