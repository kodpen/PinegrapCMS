---
name: workspace-flows-template-format
description: Flow templates (G1 #2161, flow_templates.php): format, the scheduled templates that came over or wait, the gallery
---

## Where is it?
- `includes/workspace/flow_templates.php`: `ws_flow_templates($fill)` (pure), `ws_flow_template_build()`, `ws_flow_template_categories()` (team, site, sales), `ws_flow_template_setting_types()`. Tests `tests/workspace_flow_templates_test.php`.
- Served in `ws_flow_js_config()` (mode flows, builders only) as `templates` and `template_categories`, filled for the viewer: me, channel 0, site and e-mail of `ws_scheduled_js_config()`, the counts they may read, recap_ai, join, events (`ws_flow_templates_for()`); the API lists them too (`workspace-flows-api-templates.md`).

## What is a template?
- key (the scheduled template's, or its own), category, title, help (one line), icon, start (kind of the first box), graph (`ws_flow_graph_normalize()` shape, boxes in columns of 256, follow-ups stacked 112 apart), boxes, threads (count of `{{node.ID.FIELD}}` in its configs), settings `[{type, targets: [[node, key], …]}]` (a box spec gives `setting` [type, key] or `settings`, a list, as the approval box: channel and people).
- Setting types: channel (id), person (id list), time (HH:MM), url, address; the setup panel asks them (`workspace-flows-template-setup.md`). Each template also carries `motion`.

## Which came over, and how?
- From `saTemplates()`: task_mail, morning, digest, uptime, ssl, overdue, stock, invoices, monthly. Titles and texts are the same `sa_tpl_*` sentences (a test compares them with `ws_scheduled_js_strings()`).
- "when" → a schedule box, each condition → a condition box (yes arrow), the step → its step box (task → `task_open`), a follow-up → a box on the failed arrow. Text sent to the channel open, else a notify to the person (the gallery has no channel: notify).
- A follow-up's `{{result}}` becomes `{{node.check.text}}`: a flow fills no `{{result}}`, the box's sentence is the same text.
- A template needing a count the person cannot read is left out (digest needs one of its counts, overdue, stock, invoices theirs).
- welcome and the event ones of workspace_events.js (merge plan R1): a `join` start (0: any public channel) posting in the channel joined (-1), only with `join`; order_event, form_event, stock_event, overdue_event, approval_event an `event` start, each only when its event is in `events` (`ws_events_js_config()` list); titles as `ws_events_js_strings()`.

## Which day does a start box get?
- A day of the site's calendar, as the scheduler reads date + time: from `today` (`date('Y-m-d')`) at noon in UTC (`DateTimeImmutable`). `strtotime('next monday')` in PHP's zone gave the Sunday before east of UTC (PR #2230); tested in five zones.

## Which are a flow's own?
- new_customer, quote_approval, text_review (G3): `workspace-flows-template-flow-only.md`.

## Which wait, and why?
- forms, orders (counts "since the last run", a flow reads the last day) and chain (started by another action): #2753, merge plan R1b.

## How does the canvas use it?
- "New flow" with templates opens `showGallery(null)` in place of the canvas: Empty canvas, then the cards by category; Use creates a flow named after the template with its graph.
- The box list shows "Start from a template" while the flow has no boxes; Use opens that flow again and places the graph (a flow that got boxes meanwhile gets a new flow instead), saved as any change; then the motion and the setup panel (`workspace-flows-template-setup.md`).
