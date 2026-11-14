---
name: messages-area-automatic-tab
description: Communication › Automatic sends (MA8): the flows that send a message on the table driver, its gate, runs today, the ready recipes and ?quick= into the quick setup, the profiles note
module: messages_automatic
---

## What does the tab list?
- `messages_automatic.php`: the flows the person reaches (`ws_flows_list()` with `ws_flows_with_converted()`, as the Flows list) whose graph holds a Send an e-mail, Send an SMS or Send a WhatsApp message box (`pg_messages_send_kinds()`, `pg_messages_graph_sends()`). The graph read is the live version's, a never-published flow's draft (`ws_flows_start_graphs()`).
- Printed whole on the list table driver, list `messages_automatic`: Name (opens `workspace_flows.php?flow=<id>`), Starts (the Flows list's sentence), Channel (tiles and "3 e-mails" / "E-mail, WhatsApp"), Status pill (Live teal, Paused amber, Draft gray), Runs today, Last Modified. Live first, then paused, then drafts.
- Runs today: per live or paused flow, the newest 1,000 runs on the runs' flow index (`PG_MESSAGES_AUTOMATIC_TODAY_CAP`), "999+" at the cap; 200 flows at most are counted, a draft shows "—".

## Who opens it?
- Gate `email_or_flows` in `pg_messages_area_tabs()`: the Campaign Manager rule or the Flows screen's (`pg_flows_user_may()`). `pg_messages_tabs_for_user()` asks the flows gate only of a role 3 user without the campaign right.
- Without the flows gate (a campaign manager outside the team, or a site whose flows are not upgraded) the tab shows a note only. The flows a person sees are those the Flows screen shows them.

## What leads into the quick setup?
- "Quick setup" in the toolbar (whoever builds flows: `ws_flow_quick_for()` not null) and the Ready recipes card: the catalogue's recipes holding a send step whose start the person may use (`pg_messages_send_recipes()`), each a chain of tiles (`pg_messages_recipe_chain()`).
- Each is a link `workspace_flows.php?quick=1|<recipe key>` (`pg_messages_quick_url()`). The Flows screen reads `quick` in `addressOptions()`, draws the list, forgets the address and calls `openQuick(null, key)`; `PGWsFlowQuick.open()` takes `recipe`: its first start the catalogue offers is chosen and its steps added, on the Steps stage (`recipeOpening()`). A recipe for any start about a record is not linked.
- Why a link and not the dialog on the tab: the dialog needs the Workspace kit and the flow config the Flows screen loads; the made flow opens on the canvas there.

## What about the campaign profiles?
- For who passes the e-mail rule: a note "N campaign profile(s) still send as before, beside the flows." (enabled profiles, a role 3 user's own) leading to My Campaign Profiles, until MA9 turns them into flows.

Test: `tests/messages_automatic_test.php`.
