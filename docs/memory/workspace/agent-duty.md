---
name: agent-duty
description: An agent's duty text (AF1, #9785): the "Duty" box on the agent's form, ws_agents.duty TEXT NULL, the "Your duty" part of its instructions, the duty on the agent card
---

## What is the duty?
- What an agent is there for, in the words of the person who set it up. Owner (2026-11-15, agent channel poll 49): yes to the duty text and its column (`workflow/decisions-made-workspace-33.md`).
- Stored in `ws_agents.duty`, `TEXT NULL`, added by `upgrade_2026_11_0_ws_agent_duty()` (11.0.217). NULL is no duty: no backfill, an agent without one works as before.
- At most `WS_AGENT_DUTY_MAX` (2,000) characters, counted with `mb_strlen()`; `ws_agent_save_check()` refuses a longer one on the field `duty`. `ws_agent_duty_clean()` turns line ends into `\n` and trims; '' is saved as NULL. `ws_agent_shape()` carries it as `duty`.
- Written only once the column exists (`pg_schema_has('ws_agents', 'duty')`), like the role: files can land before the upgrade.

## Where is it set?
- Site Settings › "Assistants and agents" › Edit (and the Workspace Settings card): a "Duty" box under Role (Turkish "Görev tanımı"), named `ws_agent[<id>][duty]`, for an application or a model agent. Claude's row has no box: its routine has instructions of its own.

## Where does the agent read it?
- `ws_agent_instructions_who_lines()` puts "Your duty, as the person who set you up wrote it:" and the text right after "You are …", before the Site API line, only when there is one. The instructions' version is a hash of the text, so it changes with the duty and the next brief tells the agent to read them again (`agent-instructions.md`).
- A model agent's prompt does not carry it in this step.

## Who sees it?
- The agent card in a channel (`ws_agent_card()`, `duty`; `agentCardShow()` in workspace.js): under the name and handle, labelled "Duty", four lines at most (`.ws-agent-card-duty-text`), with the whole text as its title. The card is for team members who may read the channel (`agent-card-look.md`); the guest page carries none.
- Tests: `tests/workspace_agent_duty_test.php`.
