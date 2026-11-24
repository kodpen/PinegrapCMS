---
name: agent-guide-overview
description: The AI agent guide as a whole (2026.11.0, #10709 to #11236): decisions, its parts, which file holds what, how a part is added, what was left out
---

## What is it?
- A guide generated from code that tells an AI agent what the site is made of and which tool does which job, read over MCP, the API and the panel session; a cheaper tool list; refusals that say what to do next.
- Asked by the owner (2026-11-20, agent channel message 10376, poll 170): agents acted on guesses, in the designer above all. Decisions: `workflow/decisions-made-product-85.md`.

## Which decisions bind it?
- One guide, built from code, in English; no public address; nothing written for agents the person cannot see; hints in English, `message` stays translated.
- Tools stay the API's routes; no tool groups per connection: the consent presets are the profiles (`api-oauth-consent-presets.md`), ticking no record write (owner, 2026-10-24).
- The full tool list is unchanged; the compact list is a second address the person picks. A tool that moves money is always called by its own name.
- Limits (tested): topic list 1,536 bytes, a topic 4,096, the opening instructions 3,072, a tool's description 600 characters, a whole tool 4,096 bytes. No table and no column.
- The canvas guide stays as it is (`workspace/agent-canvas-guide.md`); the Claude plugin's skills call `guide_read` and copy no topic (`plans/claude-plugin-bundle-steps.md`, CB3).

## Which parts are there?
- Engine, `GET /guide`, `guide_read`, `pinegrap://guide`, "Guide: <topic>." on tools: `agent-guide-engine.md` (#10710).
- Topics: start (engine), designer and the `design_change` prompt (`agent-guide-designer.md`, #10737), content, forms, store (`agent-guide-areas.md`, #10722), workspace, erp (`agent-guide-workspace-erp.md`, #10718), panel (`agent-guide-panel.md`, #10725).
- The panel's door: "AI agent guide" in the account menu, `agent_guide.php`, `llms.txt` in the software folder, `rel="help"` (`agent-guide-panel-door.md`, #10786).
- The compact list, `mcp_compact.php` (`mcp-compact-list.md`, #10744). Consent presets Design and Content (#10709).
- The tool budget and the route key `details` (`mcp-tool-budget.md`, #10822; the diet #10915, #10920, #11129, #11155).
- `hint` and `guide` on refusals, the designer's own refusal codes (`agent-guide-hints.md`, #10766). The Refused calls card on Application Access and `GET /reports/api-refusals` (`api-refusals-report.md`, #11236).

## Which file holds what?
- `includes/fn/agent_guide.php` (registry, text, digest, sizes), `agent_guide_<topic>.php` (one per topic), `agent_guide_hints.php`, `api_refusals.php`; `includes/api/resources/guide.php`; `includes/mcp/tools.php` (guide line, compact list, money rule, budgets), `protocol.php` (instructions), `resources.php`, `prompts.php`; `includes/oauth/rules.php` (presets).

## How is a part added?
- A topic: its file `includes/fn/agent_guide_<id>.php` and its registry row; the engine is not touched. A tool name in a line or hint is written `{tool:route.id}` (tested).
- A hint: a line in `pg_agent_guide_hints()` naming a section its topic declares.
- A long tool text: reference detail into the route's `details` key; the budget test has no exceptions list.

## How much did it save?
- From 853,922 to 766,202 bytes for the full tool list with every module and scope, 615 tools all within the budget: `agent-guide-sizes.md`.

## What was left out?
- The compact list as the default address, a guide in other languages, a guide the site's owner writes, per-connection tool groups, training or scoring of agents: each a later yes.
- A class that draws nothing is no API refusal: the proposal is kept and only the built-in assistant is warned (`agent-guide-hints.md`).
