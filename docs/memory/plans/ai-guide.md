---
name: ai-guide
description: Plan (owner 2026-11-20, agent channel poll 170): the site explains itself to AI agents cheaply: a guide generated from code, read over MCP, API and the panel; a compact tool list; AG1 to AG14
priority: 6
---

## What is asked?
- Owner (2026-11-20, agent channel message 10376): AI agents use Pinegrap from the browser and over MCP; reading the documentation costs time and tokens, so they act on guesses, in the designer above all. He asked how the software can tell an AI how to use it most easily.
- Owner (poll 170, message 10442, on the research in message 10440): the architect develops and widens the feature and starts its plan (`workflow/decisions-made-product-85.md`).

## What is there today?
- Measured on `development` (2026-11-20, every module and scope): 577 MCP tools, 853,922 bytes (about 210,000 tokens) before any work; descriptions 338 KB, input schemas 375 KB. Workspace 189 tools 339 KB, ERP 82 / 117 KB, store 51 / 55 KB, design 36 / 44 KB. 209 descriptions are over 600 characters; the longest tool is 12 KB. Every tool's name and first sentence together: 31 KB.
- `pg_mcp_instructions()` (2.5 KB) tells money, paging, `dry_run` and the rules; no word on what a page, a design, a region, a system widget or a collection is, or which tool does which job.
- The designer is built for a model (`design_pages_get` gives HTML with `data-pg` ids, `design_proposals_create` proposes operations), but only the built-in assistant is told its rules (`pg_design_ai_system_prompt()`, `pg_design_ai_tools_guide()`); an agent over MCP is told none.
- A browser agent finds no sign in the panel; `llms.txt` is the visitor pages' (`visitors/ai-crawlers-llms-txt.md`).
- The model that works: the canvas guide (`workspace/agent-canvas-guide.md`): values in one place, text built from them, a route, an MCP resource and prompt, a short form on the tools.
- Refusals carry `code`, `message`, `field`, `errors`, `docs`; `api_request_log` keeps each call's `error_code`.

## What binds it?
- No table and no column; a step that needs one stops and asks the architect.
- Tools stay the API's routes (`api/api-mcp-server.md`); no tool groups per connection: the consent presets are the tool profiles (`api/api-oauth-consent-presets.md`), and they tick no record write (owner, 2026-10-24).
- The canvas guide stays as it is; the Workspace topic points at it.
- The Claude plugin's skills (`plans/claude-plugin-bundle-steps.md`, CB3) name the guide and copy none of it.

## What did the architect decide? (2026-11-20)
- The decisions are in `workflow/decisions-made-product-85.md`: one guide built from code, in English; no public address; nothing hidden from the person; the compact tool list as a second address the person chooses, the full list unchanged; a tool that moves money always under its own name; hints in English.
- The limits: topic list 1,536 bytes, a topic 4,096, the opening instructions 3,072, a tool's description 600 characters, a whole tool 4,096 bytes.

## What is built?
- Features: `ai-guide-features.md`, `ai-guide-features-2.md` (G1 to G19). Steps: `ai-guide-steps.md`, `ai-guide-steps-2.md`. Claim as `[plan:ai-guide] <step> — <topic>`.

## What is left out?
- The compact list as the default address, a guide in other languages, a guide a site's owner writes by hand, per-connection tool groups, training or scoring of agents. Each is a later yes.

## Whose look?
- No pictures (`workflow/look-pictures-none.md`): the one thing a person sees is the "AI agent guide" link (AG6) and the compact address on the connection guide (AG7), seen on dev.
