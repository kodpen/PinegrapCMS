---
name: workspace-flow-card-embed-server
description: Server side of the flow card's read-only canvas (K2): ws_flow_draft_view at real places with marks and dry runs; ws_flow_embed_config, labels only, for any Workspace reader
---

## Who may read a card's view? (K2)
- `ws_flow_draft_card_source()` (flow_draft.php) holds the rights of the card, shared by the thumbnail (`ws_flow_draft_card_graph_read()`, action `ws_flow_draft_graph`) and the view (`ws_flow_draft_card_view_read()`, action `ws_flow_draft_view`): the asker only; waiting = `ws_can_schedule()` + `ws_can_post_channel()`, a patch made again on its flow with `edit` access; opened = the flow with `view`, its graph read and masked as `ws_flow_detail()` does (`ws_flow_card_opened_graph()`, #7478), tried where `ws_flow_share_manager()`. Nothing written.

## What does `ws_flow_draft_view` give? (`view`)
- `graph`: `ws_flow_graph_normalize()` at the real canvas places with each box's config (never `ws_flow_card_graph()`'s scaled ones), for `PGWsFlow.embed()`.
- `names` (box id => name, `ws_flow_box_names()`), `mark` (list of `{id, mark}` added/changed, `ws_flow_patch_marks()`), `removed` (names, `ws_flow_patch_removed()`, shared with the thumbnail), `tries` (box id => `{said, failed}`, `ws_flow_card_tries()` then `ws_flow_card_view_tries()`; a value box or a refused try has none), `flow_id` (0 while it waits), `can_try`.
- Built by `ws_flow_card_view()` (pure but for the kinds' words).

## What does `ws_flow_embed_config` give? (`config`)
- `ws_flow_embed_config($viewer)` (flows.php), once per page: `ready`, `kinds` (kind => label), `subjects`, `steps`, `outputs`, `trigger_outputs`, `derived`, `decimal`: the keys of `ws_flow_js_config()` the drawing reads, so `FL` can be this object.
- `steps` is `ws_flow_embed_lists()` (pure): metrics `{key, label}`, targets `{id, name}`, repeats and operators (key => label), events `{list: key => {label}}` or null, tables `{id, name, edit, columns {id, name, type}}`, mcp `{id, alias, tools {name}, asks}`, changes and records (kind => `{label, fields: key => {label}}`). No form options, column settings, tool descriptions, templates.
- For any Workspace reader, a home reader too: no `build` gate; each list keeps its own rights (metrics by right, `ws_scheduled_targets()` and `ws_scheduled_js_config()` empty for who may not schedule, tables reached, MCP servers usable, none for a role-3 member).

## Tests
- tests/workspace_flow_draft_test.php: `test_ws_flow_card_view`, `test_ws_flow_patch_removed`, `test_ws_flow_embed_lists`, `test_ws_flow_card_view_wiring`. No browser: the card does not read these until K3.
