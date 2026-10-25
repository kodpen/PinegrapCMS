---
name: workspace-growth-2026-10
description: Umbrella of the Workspace growth plans of 2026-10-23 (CRM seams, MCP, quotes, catalogue, Workspace canvas, text tools, commands, voice, icons, MCP connectors, assistant keys): index, waves, rules
---

## What was asked? (owner, 2026-10-23)
- Get the Workspace ready for a later CRM; let an AI connected over MCP do more; from the Workspace create frequently used quotes, site and ERP kept apart; create product groups and products; Workspace canvases holding workflow tables that become workflows, also purely personal with no forced link; find more.
- Then: richer text tools in notes and the composer; more and clearer `/` commands with Tab suggestions ("I never managed to start a task"); voice messages with a quick voice reply, usable by the assistants; a chosen channel icon; a simpler MCP servers screen with ready-made connectors ("does it work?"); one-click Claude and Pinegrap AI setup that issues a linked application key ("Pinegrap AI was inefficient, setup is tiring").

## Which plan holds what?
- plans/workspace-crm-seams.md: record-kind registry, customer identity and summary, merge, history, follow-ups, lead intake.
- plans/workspace-mcp-capabilities.md: the site's MCP server: dead tools, one proposal route, resources, prompts, API gaps.
- plans/workspace-quotes.md: sales quote (`erp_quote`) and cart quote (`cart_quote`).
- plans/workspace-catalog-create.md: products and product groups from the Workspace, bulk drafts, variant sets.
- plans/workspace-canvas-workflow-tables.md: workflow tables, board view, scheduled-actions API.
- plans/workspace-flows.md, plans/workspace-flows-engine.md: Flows (Workspace canvas, rules, process templates), the main use (2026-10-24).
- plans/workspace-flows-scheduled-merge.md: scheduled actions into Flows, no feature lost; plans/graph-canvas-core.md: one canvas core for Flows and the designer.
- plans/workspace-composer-tools.md, plans/workspace-slash-commands.md, plans/workspace-voice-messages.md, plans/workspace-channel-icons.md, plans/workspace-mcp-connectors.md, plans/workspace-assistant-key-setup.md.
- Shared names and single owners: plans/workspace-growth-terms.md. Neighbours: plans/workspace-agent-handles.md, ai-agent-prompts.md, workspace-phase3-leftovers.md, privacy-data-pack-audit-log.md, workspace-friendly-ui.md.

## In which order? (waves)
1. Fixes, no schema, no decision: `/task` Enter and Turkish names (slash 1, #1440); dead MCP tools (MCP 1) and missing `dry_run` (MCP 8); voice record, send, play (voice V1, #1457); MCP connector error texts (1, 3-6) and card fixes (9); Discussions caret (#1433); API colour on channels (icons 1); composer shared toolbar (composer 1); catalogue 1a.
2. Foundations, no schema: record-kind drift test (CRM 1), then the `erp_quote` tag (quotes 1); command registry and argument popup (slash 2-3); resources and consent presets (MCP 4-5); scheduled-actions read tools after PR #1352 (canvas S1); assistant key setup slice 1 if it needs no schema.
3. After owner answers, no schema: no MCP approve/apply (MCP 2), then the propose route (MCP 3); quotes 2-3; catalogue 2-4; CRM 2-5; canvas S2.
4. Schema, ask first, one 2026.11.0 sub-step each (labels from the call list on `origin/development` and open PRs): `ws_refs.source_type` (CRM), flows (F1), workflow tables (canvas T1), flow shares (F7), `cart_quotes` (quotes), `erp_quote_templates` (ERP label), `ws_voice` (voice V3), `ws_channels.icon`, saved replies.

## Which rules bind every slice?
- One slice, one PR, a draft PR as the claim first (CLAUDE.md, Claim work); a PR marks its step done in its plan.
- Every feature ships its API with `dry_run`; MCP tools follow from routes; no ws_ screen, tool or file reaches a site member.
- Personal things (workflow tables, canvases, notes) are owner-only by default; shares are explicit; nothing forces a link to a channel, task or record.
- Owner questions are collected per plan; ask them together, Turkish, with the recommendation first.

## Where to start?
- Wave 1. Each plan's "Where to start?" names its first slice.
