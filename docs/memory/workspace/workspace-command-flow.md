---
name: workspace-command-flow
description: The channel's flows in the composer's "/" list and /flow (/akış) (CC5 of channel-commands, #10301): which flows, read when, what a pick does, a typed name, the customer of a channel about one
---

## Which flows does the "/" list show?
- The flows Add › Start a flow lists in the channel: `ws_flows_here()` (`includes/workspace/flow_run_cards.php`) = `ws_flows_runnable_in()`, for whoever the Workspace shows Flows to (`ws_flows_nav_visible()`), in a public or private channel only. Entries: id, name, live version number (`ws_flows_here_present()`, pure).
- Read once, as the channel opens: `ws_channel_open` answers `flows_here` (bool) and `flows` (the entries); the screen keeps `self.flowsHere` and `self.flowsList` until the channel opens again. "/" reads no server.
- None in a discussion, an earlier era, a direct message, a mailbox room or a guest channel (`flowsOffered()` in workspace.js).
- In the list: after the built-in commands, under the header "Flows" (`cmd_flows`), each row "Start this flow" (`cmd_flow_meta`), icon `bi-play-circle`. Filtered by a folded prefix of the name or of a word in it (`flowPickerRows()`, pure); the header goes with the last row (`commandPickerAddFlows()`, pure).

## What does a pick or Enter do?
- What Start a flow does: the typed "/…" goes, the flow is asked (`fl_run_ask`) and started by `startFlowHere()` → panel action `ws_flow_run_start` with the channel and a start token. The rights and refusals are `ws_flow_run_start()`'s. No new action or route.
- `startFlowHere()` without a button keeps a second pick away by a flag on the flow itself.

## What does /flow (/akış, /akis) do?
- Entry in `ws_command_registry_agents()`: group agents, `where` channel, gate `ws_command_gate_flow()` (Flows shown, a public or private channel), one optional argument of kind `flow` whose list is the channel's flows (`suggest` = `flows`).
- Runner `ws_command_run_flow()` writes nothing: a whole flow name (`ws_command_flow_named()`, folded, spaces collapsed) answers `flow_start` with the flow, and the screen asks and starts it as a pick does; none answers `flow_form`, which opens Start a flow's drawer. No flow here: "No flow can be started in this channel.".
- Over the API both answers are `preview` types (`ws_api_command_preview_types()`): nothing is started from a typed line. No live line.

## Can a typed sentence start a flow?
- No. `/Weekly report` without a pick is an unknown command, as decision #7110 has it (no keyword start). Only a pick, Enter on a picked row, or /flow with the name starts one.

## Which customer is a run started in a channel about?
- The channel's (`ws_channel_customer_of()` columns): `ws_flow_channel_contact_id()` (pure) takes `customer_id` of a contact, else `contact_id` (kept for a company or an account). `ws_flow_run_channel_contact()` passes it only when the starter reads the contact (`ws_flow_run_contact_check()`).
- The panel action `ws_flow_run_start` adds it as `contact_id` when a `channel_id` comes without one, so the drawer, the "/" list and /flow share the contact step of `ws_flow_run_start_check()`: one open run per flow and customer, an open one the starter reads is answered, otherwise refused as busy. A customer picked on the canvas stays.
- Before CC5 a run started from a customer channel's drawer was about nobody.

## Tests
- `tests/workspace_command_flow_test.php` (the entry, the gate, the named flow, the entries, the runner, a typed name, the picker under Node, the contact); `tests/workspace_flow_run_cards_test.php` (offered only with a flow).
