---
name: workspace-flows-quick-setup-rules
description: Flows quick setup's lasting rules (owner 2026-11-12, #8950; built QS0-QS7, #9082-#9333): only creates, only existing boxes, the drawing, the architect's decisions, where it opens
---

## What is it and what was asked?
- A flow built by choices in a dialog: a start, steps one after the other, a summary, over every area and action of the site (`workflow/decisions-made-workspace-20.md`). The drawing the owner approved: `flows-quick-setup-look/quick-setup.html` with pictures in both themes; its names are examples, the dialog shows the boxes' own labels.
- Code: server `workspace-flows-quick-setup.md`, dialog `workspace-flows-quick-setup-dialog.md`, summary `workspace-flows-quick-setup-summary.md`, step settings `workspace-flows-box-form.md`, drawing against the boxes `workspace-flows-quick-setup-map.md`.

## Which rules does it keep?
- It only creates: a normal draft through the store (`ws_flow_save()`, `ws_flow_publish()`), same graph check and problems; nothing at run time knows the flow came from it, and it is edited on the canvas afterwards.
- Every start and step shown is a box kind or start event that exists. A drawn step with no box stays hidden; a new box is general (`workspace-flows-adding.md`), never for the quick setup alone, and gets its line in `ws_flow_quick_groups()`.
- An area groups the event catalogue in one server list; "Only when" becomes condition boxes after the start; a recipe is data (`ws_flow_quick_recipes()`), not code.
- A step's setting is drawn by the canvas's own field code (`PGWsFlow.boxForm()`), so a field is described once. Rights as for making a flow on the canvas. No schema.

## What did the architect decide? (2026-11-13, #9082)
- "Only when" on a field of the start's record: one Read a record box before the condition boxes, shared by them; the Summary shows it as the "Only when" line, not as a step.
- The rail as built: the web address start under By hand, ERP sales quotes under Cart and quotes, store credit and user accounts under Customers, bounces under Content and campaigns, `join`, `mention`, `row` under Workspace.
- Of the drawn steps with no box: Send an SMS (`workspace-flows-sms-box.md`, #9290) and Add to or take out of a contact group (`workspace-flows-contact-group-box.md`, #9291) were added; the order's status is the change box on the run's record (`change.id` `{{trigger.record_id}}`, #9223). Give an offer code waits for an owner question (offers have no single-use code per person); its step stays hidden.

## Where does it open?
- The Flows list: "Quick setup" beside "New flow" in the bar and under the empty list's words, for whoever builds flows (`FL.quick` served, not a single-flow layer): `quickOffered()` in `workspace_flow.js` (#9333).
- The made flow opens on the canvas (`quickMade()`: `keepEntry()`, `show()`), with a toast that it works or why it stayed a draft (`publish_error`).
- The address `workspace_flows.php?quick=1` opens it over the list, `?quick=<recipe key>` on that recipe's steps (`recipe` option of `PGWsFlowQuick.open()`); Communication › Automatic sends links there (`ui/messages-area-automatic-tab.md`, MA8).
