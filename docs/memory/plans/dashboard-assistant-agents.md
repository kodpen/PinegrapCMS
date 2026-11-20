---
name: dashboard-assistant-agents
description: Plan (owner 2026-11-21): the dashboard's Assistant card with Claude off: a notice at the top, the agent chat, questions and proposals kept, a short note when no agent is connected; one step, DA1
priority: 7
---

## What is asked?
- The owner's words and the decision: `workflow/decisions-made-panel-13.md`.

## What is there today?
- While Claude is not ready `pg_dashboard_v2_card_ai()` (`includes/dashboard/v2/cards/ai.php`) returns `pg_dashboard_v2_ai_offline()` alone and the renderer (`assets/js/pg_dash_cards_assistant.js`) draws only that (`ui/dashboard-v2-ai-card.md`).
- The chat's options leave Claude out while it is not ready (`pg_dashboard_v2_ai_chat_askable()`); questions and proposals count any agent's.

## Where does each part of today's card go?
- Head chip: "Claude · connected" stays; "Not connected" becomes "Claude · not connected".
- The pitch (tile, "Connect Claude", text, buttons, administrator line, MCP note): whole only when the card holds nothing else (no chat option, question or proposal); otherwise the notice takes its place.
- Daily summary and the job box with its "no channel" line: only while Claude is ready, as today.
- Agent questions and proposals: drawn whenever they hold a chip, Claude ready or not. "Nothing is waiting for your approval." only while Claude is ready.
- The chat with all its parts: drawn whenever it has an option, Claude ready or not; nothing in it changes.

## What is new?
- The notice, first in the body while Claude is not ready and the pitch is not drawn: one row, the plug tile small, "Claude is not connected" strong, under it "No daily summary and no jobs for Claude until it is connected." At its right a quiet small "Connect Claude" for who may open the Workspace settings; else the administrator line under the text.
- The agent note, last in the body while no application agent is active (`ws_agents_list()`), Claude ready or not: a grey block, title "Workspace agents", two lines and one quiet small button.
  - "An agent is an application with its own key. It answers under its own name in the channels it is added to."
  - "A channel takes an agent from its menu, Agents in this channel. A channel kept for agents shows what each is doing."
  - The button "Connect an agent" opens Site Settings at the Workspace agents row (`pg_settings_workspace_link('ws-agents')`) for who may open the Workspace settings; else the line "An administrator connects agents in the Workspace settings."
- Order: notice or pitch, daily summary, questions, proposals, job box, chat, agent note.

## What must be true? (each pinned by a test)
- Claude not ready, one assistant askable: no pitch title; one notice; the chat's form is drawn; no job box, no summary.
- Claude not ready, nothing askable, no chip: the pitch as today, then the agent note when no agent is active.
- Claude not ready with a question or proposal chip: it is drawn under the notice.
- Claude ready with an active agent: data and markup as today.
- The agent note is drawn exactly when no application agent is active, with one button or the administrator line, never both.
- At most one filled button on the card; the notice's and the note's are quiet.
- No line of the notice or the note is over 110 characters.

## What does not change?
- The gate, posting only through `ws_send`, the chat's keep rule (`ui/dashboard-v2-ai-chat-keep.md`), the daily summary's setting, the proposals' counting.

## Which step is there?
- DA1 (S), one PR: the card's data (a pure builder for each state), the renderer, the "Assistant card" block of `pg_dash.src.css`, `tests/dashboard_v2_ai_test.php` red first; the new texts through `php tools/tr_add.php`.
- The worker opens the dashboard itself at 1440 and 390 px (Claude off with an agent, off with none, on with none) and writes "Screen check" in the PR.
- The same PR corrects `ui/dashboard-v2-ai-card.md` (its "Not done: the chat when Claude is offline" line goes) and deletes this plan. Claim as `[plan:dashboard-assistant-agents] DA1 — the card with Claude off`.
