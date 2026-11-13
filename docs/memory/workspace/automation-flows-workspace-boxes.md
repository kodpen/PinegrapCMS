---
name: automation-flows-workspace-boxes
description: Which flow boxes and starts need the Workspace (ws_flow_kinds_workspace()): the list, offered only with it, the problem on the canvas, the refusal at run time and in Try
---

## Where is the list?
- `ws_flow_kinds_workspace()` beside `ws_flow_kinds()` (`includes/workspace/flows.php`), with the pure `ws_flow_node_needs_workspace($node)` for a box whose settings decide. One place: the runner's refusal, Try, the palette, the problem line, templates, the quick setup and Pinegrap AI's catalogue read it.
- The plan's rule (`plans/automation-flows-menu.md`, after the owner's M1a, #8961): a box or start that needs the Workspace is offered only while it is on; on a site without it a flow holding one shows a problem and that box does not run. It names them: a channel message, the task boxes, check-in, a customer brought into a channel, the mention and join starts, an agent.

## Which kinds are on it?
- Starts: When somebody joins a channel `join`, When the flow is mentioned in a channel `mention`.
- A message in a channel: Post a message `post`, Ask for a check-in `checkin`, Ask for approval `approval` (its request is a message in a channel, and approval deadlines are not run with the Workspace off, so a run would wait for good). Counting the approval box as a channel message is the implementing session's reading.
- Bring a customer into a channel `lead`; the task boxes `task`, `task_open`, `task_digest`; Ask an agent `agent`.
- Send a summary of counts `report` when it posts into a channel (`channel_id` > 0); one sent only by e-mail runs.

## What is not on it yet?
- The census found more that acts in the Workspace (Let people know, the table boxes and the row start, Pinegrap AI, MCP, Workspace records in Change or Read a record, starts on the Workspace's own events). They run with the Workspace off until the architect answers the proposal in `plans/automation-flows-menu-proposals.md`.

## What runs without the Workspace?
- Started by hand `manual`, At a time `schedule`, a site event, When a customer stops ordering `no_order`, When a web address is called `web`; Send an e-mail, Call a webhook, Check a web address, Start a flow, Change or Read a site record, Condition, Wait, For each, End, Constants, Derived value.
- A run still opens in its flow's home channel and writes its owner's flow_finished line in the Workspace inbox; with the Workspace off nobody reads them (open in the census).

## Where is such a box not offered with the Workspace off? (AF3)
- The canvas palette and adding a box (`memberKind()` reads `FL.workspace_kinds`), the templates (`ws_flow_templates_for()` drops one holding such a box, `ws_flow_graph_needs_workspace()`), the quick setup (`ws_flow_quick_fill()` takes the kinds out), Pinegrap AI's catalogue (`ws_flow_ai_catalogue()`). With it on, all as before.
- The problem line: `ws_flow_workspace_problems(ws_enabled(), $graph)` (pure, code `workspace_off`, "The box …: It needs the Workspace, which is switched off on this site.") joins the graph's problems in the save answer, the opened flow and the publish check: like every problem it stops Publish, so a flow kept from Workspace days is published again only without it. A live one keeps running; that box fails (below).
- Try refuses it with the run's words (`ws_flow_box_try()`, after the member's refusal).

## What happens at run time?
- `ws_flow_step_run()` asks `ws_flow_workspace_box_refusal(ws_enabled(), $node)` after the role-3 member's refusal: the box fails with "This box needs the Workspace, which is switched off on this site, so it did not run." the way a refused member box does, without its retries. With the Workspace on nothing changes.
- Pinned by `tests/automation_flows_gate_test.php` (every listed kind is a real kind; the site-only kinds are off the list).
