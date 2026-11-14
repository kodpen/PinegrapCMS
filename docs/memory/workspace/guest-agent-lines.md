---
name: guest-agent-lines
description: The guest page lists the agents' and assistants' messages beside people's (GV1): which senders, how drawn, what a guest may do on them, and that nothing a guest does asks an agent
---

## Which lines does a guest read?
- In a guest's room and in every channel shared with a guest, earlier ones included: people's and guests' lines, and the lines of the registry agents (`ws_agents` app and model, any status), Claude, Pinegrap AI and an MCP agent's answer (kept in its asker's name, found with `ws_mcp_agent_answers_map()`). Owner decision: `workflow/decisions-made-workspace-24.md`.
- Left out: other applications' lines (CI, integrations), system lines, an agent's task card, change cards, buttons, request ("Waiting for") lines. An agent's file is not served; an agent line with no text and no poll is skipped.
- `ws_guest_line_sender()` (pure) says person, guest, agent or ''; `ws_guest_lines_sql()` (pure) is the WHERE part; `ws_guest_agent_apps()` gives each application's name, `@tag` and badge.

## How is an agent's line drawn?
- By the staff's engine (`ws_render_body()`), under the agent's name with a badge: "AI agent", "AI assistant" or "MCP agent" (`pg-guest-badge` in `workspace_guest.css`). An MCP answer loses its first line naming the tool.
- A tag `<@app:N>` reads as `@handle` (`@Claude`, `@ai`), never a link; any other application's tag as "@Application".

## What may a guest do on an agent's line?
- Answer it and leave an emoji. Its poll and checklist are read only: `ws_guest_vote()` and `ws_guest_check()` refuse unless the message is a person's own line (`ws_guest_person_line()`); the poll carries `readonly`.
- An answer's parent must be a line the guest sees (`ws_guest_send()`).

## Does a guest's action ask an agent?
- No. `ws_guest_send()`, `ws_guest_react()`, `ws_guest_vote()` and `ws_guest_check()` call no wake or request function; a guest's tag is taken out by `ws_guest_clean_text()`. `tests/workspace_guest_agent_lines_test.php` holds one test for each.
- A flow started by `workspace.guest.message` runs, but its Ask an agent box settles as failed ("A guest's message asks no agent.") without asking: `ws_step_agent_guest_started()` in `flow_agent_box.php`, called from `steps.php`. Its Pinegrap AI box still runs: it reads the text and answers the flow, never a channel.
