---
name: agent-risk-tiers
description: Agent risk tiers (agent_tiers.php, A7): the tier of a change, when an application agent's proposal is applied at once, the undo journal in ws_ai_changes (step 11.0.174), who may undo and when
---

## What are the tiers?
- 1 reading and messages, 2 a reversible record change, 3 an irreversible one. `ws_change_tier()` reads the catalogue (`ws_change_types()`): a deletion, an invoice, any `money` field, an order's `status` / `cancellation_reason`, every change of `ws_change_tier3_types()` (erp_account, erp_quote, user), an unknown kind, action or field (also `create`-only and `members` fields: group membership, a parent group) are 3; the rest of a known kind is 2.
- The channel-agent row's `tier` (`ws_channel_agents`, default 1) is set only by a channel manager (`agent-registry.md`). `ws_agent_tier_allows()` counts a value out of range as 1.

## When is a change applied at once?
- Only on `workspace.changes.propose` in a channel, called with an application key whose application is a `kind = 'app'` agent with an allowed row in that channel (`ws_channel_agent_tier_of()`: the highest such row). OAuth connections, the assistants and task proposals stay proposals.
- An active app agent's application is in `ws_assistants()`, so `ws_api_is_assistant()` holds for its key; on this route only, `ws_api_agent_key_proposes()` lets it through when every assistant of that application is an `agent:` one (not Claude, Pinegrap AI or a model agent). The task route and handing a task over still refuse it. Why: on dev the key got 403 and tiers 2 and 3 were never reached (#8452).
- `ws_agent_apply_within_tier()` runs after `ws_proposal_store()`: when every change of the call is within the tier (`ws_agent_applies_at_once()`, tier at least 2), each goes through `ws_change_apply($owner, $id, true)`, in the name of the person the key acts for, with all its checks. One change above the tier keeps the whole call a proposal. The decision says it was applied at once and may be undone; the row gets `applied_by_kind = 'app'`; the API answer gives the changes as applied.

## How is it undone?
- Journal: the `ws_ai_changes` row itself (decision #8243, `workflow/decisions-made-schema-13.md`): `status` gains `undone`, `applied_by_kind`, `undone_by`, `undone_at` (`upgrade_2026_11_0_ws_agent_undo()`, enum widened from what is there). No new table: the before values are already in `fields`.
- `ws_agent_change_undo()` (panel action `ws_agent_change_undo`, the card's Undo button): a member who may post in the channel and `ws_change_allowed()` the kind, within 7 days of `decided_at` (`WS_AGENT_UNDO_SECONDS`, `ws_agent_undo_open()`). `ws_agent_undo_reverse()`: an update is written back from `from`, checked against `to`; a create is deleted (only kinds with `delete`); a tier 3 change or a field with no column cannot be undone. The swapped row goes through `ws_change_apply_record_check()`, so a record changed since refuses ("The record changed after this change..."). Claimed by a conditional UPDATE to `undone`, given back to `applied` when the write fails; a locked decision under the answer says it was undone.
- Not an API route or MCP tool: undoing is a person's act on the card. `GET /workspace/changes` shows status `undone`.

## And in flows?
- The Ask an agent box and runs an agent starts within its tier: `agent-flows.md`.
