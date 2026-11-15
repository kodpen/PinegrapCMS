---
name: agent-approvers-everyone
description: Plan (owner 2026-11-14): an approval request can ask a channel's agents and assistants, and the tag @herkes mentions everyone in the room but the writer; steps AE1 to AE6 (AE4b split from AE4)
---

## What is asked?
- Owner (2026-11-14, in the architect's window): "Ask for approval" also offers the channel's agents and assistants as approvers, with API routes where they are missing; and a tag `@herkes` that mentions everyone in the room but the writer, in the agent channel every agent, assistants left out. His words: `workflow/decisions-made-workspace-30.md`.
- Before: approvers were team members who may write in the channel (`ws_approval_eligible()`); a roll call is a checklist that wakes nobody; `@hepsi` a habit caught by text waiters (`workflow/agent-channel-wake.md`).

## What changes for approvers?
- The Approvers field lists, under the people, the channel's active agents and the assistants that can be asked there. A paused agent is not offered.
- An agent asked is woken at once: one request row and the knock, its brief carrying the request. It answers Approve or Reject with an optional note through its own key; an assistant answers when the site carries it on. The card names it with its badge.
- "Any one of them", "All of them" and the deadline count an agent as they count a person.
- The bound: only a request a person makes by hand may ask an agent or an assistant. A flow's request (its approval box, a write that asks first, a member's change) and an agent's request ask people only, and no agent answers a request it made.

## What is @herkes?
- "Everyone" on top of the mention menu; typed `@herkes` or `@everyone` does the same. No agent takes `herkes`, `everyone` or `hepsi` as its handle.
- People: every member of the channel but the writer gets a mention line, whatever their notify level. A guest of a customer room is never counted and gets no mail from it.
- Agents: every active application agent of the channel is woken, under today's brakes. Claude, Pinegrap AI and a model assistant are left out.
- Only a person's `@herkes` wakes agents; an agent's or an application's rings the people and wakes no agent.

## Which steps?
- AE1 (S, no product code) Census of approvals, mentions and the wake; the store asked of the owner (a table of its own, channel 3501). Done (#9555): `agent-approvers-everyone-census*.md`.
- AE2 (M, schema) Agent approvers on the server: who may be asked, the wake, the answer of an asked agent, the bound with its tests (a flow's request refuses an agent, an agent's own request refuses it). Exact scope: `-census-steps.md`. Done (#9567): `workspace/approval-agents.md`.
- AE3 (S) The form and the card: agents under the people, the badge on the card (workspace_approvals.js, `ws_approvals_map()`). Done (#9586). Look A, the pictures in the agent channel.
- AE4 (S) API for application agents: `agents` on create, the decide route for the asked agent's own key (off MCP), `WorkspaceApproval.agents`, the dry run. Done (#9584): `workspace/approval-agents-api.md`.
- AE4b (S) The assistants as approvers: model agents, Claude and Pinegrap AI offered and answering through the site that carries them on. Scope: `-census-steps.md`.
- AE5 (M) `@herkes` on the server: tag, lines, wake, reserved handles. Done (#9571): `workspace/workspace-everyone-mention.md`.
- AE5b (S) The @ menu's "Everyone" entry. Done (#9578), look request channel 3659.
- AE6 (S) Close-out: the memory files; `workflow/agent-channel-wake.md` drops `@hepsi` and its text waiters; this plan deleted.

## What does every step keep?
- A request made before, and a request that asks people only, works as today. A poll's votes and read receipts stay people's.
- No table or column without asking the architect. Texts through `lang()`, Turkish by `tools/tr_add.php`.
- AE1 first; AE2 after the owner's answer on the column, AE3 and AE4 after AE2, AE4b after AE4; AE5 after AE1, beside the rest; AE5b after AE5. Claim as `[plan:agent-approvers-everyone] <step> — <topic>`.
