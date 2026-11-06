---
name: workspace-flows-mention-start
description: Flow start "when the flow is mentioned in a channel" (flows-next C4, decision B): @ and the flow's name in the box's channel, composer only, one start per person a minute, outputs
---

## What is it?
- Start box `mention` ("When the flow is mentioned in a channel"), an anchor kind (`ws_flow_anchor_kinds()`, rule `mention`). Setting `channel_id`, required (`ws_flow_anchor_problems()`); a public or private channel, not archived, that the owner reads (`ws_flow_mention_refusal()`), checked at publish and again as each mention starts the run (`ws_flow_anchor_start()`). In the start filter it counts as `event`. Code `includes/workspace/flow_mention.php`; tests `tests/workspace_flow_mention_test.php`. No schema.
- Decision (`workflow/decisions-made-product-33.md`, C4: B): only a mention of the flow by name in the named channel; never a message of a flow or an agent; one start per person a minute. A keyword start is not added until asked.

## How does a message reach the flow?
- Only the panel composer's send (`case 'ws_send'` in actions.php) calls `ws_flow_mention_after_send()`, after `ws_message_ask_assistants()`. `ws_message_send()` keeps nothing: a flow's post box, applications, Claude, Pinegrap AI, MCP agents and the external API write through it or other paths and never start a flow this way. A / command posted as a message (commands.php) does not either.
- It does nothing unless some anchor waits for `workspace.flow.mentioned` (`ws_events_awaited()`), the writer is a team member and the text holds `@`. `ws_flow_mention_waiting()` reads the live flows whose active anchor keeps a mention rule of this channel; `ws_flow_mention_pick()` takes the first `@name` in the plain text (`ws_flow_mention_at()`: any case, not inside a word or an address, no word character after it; at the same place the longer name). One message starts at most one flow.
- One start per person a minute: `ws_flow_mention_recent()` looks for a `workspace.flow.mentioned` row in `ws_events_in` of the last `WS_FLOW_MENTION_GAP` seconds whose JSON starts with `{"author_id":N,` (`ws_flow_mention_payload()` puts the author first). A mention within the minute is dropped quietly; the message itself is posted as usual.
- The rule `{type: mention, event, channel_id}` is read by `ws_events_awaited()`, `ws_events_start()` and `ws_events_rule_matches()` beside event and row rules; `ws_events_start()` skips a rule whose flow is not the one named (`ws_flow_mention_other_flow()`). The event is in `ws_events_own()`, not in `ws_events_catalog()` (the event box does not offer it); record `channel` (`ws_events_record_of()`); `ws_events_creator_sees()` asks the owner reads the channel. The flow's hourly and open-run limits still apply.

## What does a box read? ({{trigger.*}})
- `ws_flow_mention_outputs()`: `message_id`, `text` (plain text, at most 2000 characters), `author_id`, `author_name`, `channel_id`, `channel_name`. `ws_step_trigger_values()` fills them only while the owner still reads the channel (`ws_step_trigger_values_for()`).

## Canvas and lists
- workspace_flow.js `KINDS.mention`, palette after `no_order`, `mentionFields()` (channel picker with help `fl_mention_help`), meta "In #channel"; icon `FLOW_ICONS.mention` (workspace.js). Not in `ws_flow_member_kinds()`: staff build it. Lists say `ws_flow_mention_said()`, naming the channel only to a reader of it. Pinegrap AI's catalogue hint in `ws_flow_ai_catalogue_hints()`.
