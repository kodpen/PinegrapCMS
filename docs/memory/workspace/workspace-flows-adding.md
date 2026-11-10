---
name: workspace-flows-adding
description: Recipe for a new flow box or start event, never one example: is it there already, then every place it is named (server, canvas, Pinegrap AI, bundle, tests)
---

## What may a new box or event be?
- A general mechanism that serves every scenario, never one made for one example (the bakery flow on dev was an example). Ready flows for one need are templates: `workspace-flows-template-adding.md`.
- Why (owner, 2026-11-05): people get the steps other automation tools have, while flows run inside the site with its records, rights and drafts by Claude or Pinegrap AI, at no price per task (`workflow/decisions-made-product-33.md`).

## Is it there already?
- Read the box list by label in `workspace-flows-overview.md` first: a "Wait" box is kind `delay`, "Ask for approval" is `approval`. A setting on an existing box is usually the answer, not a new kind.

## Where is a new step box named? (a `ws_scheduled_do()` action or its own run)
- `ws_step_kinds()` (`includes/workspace/steps.php`): label, type, `texts` (filled with values, which also gives `ws_flow_node_texts()` and the reference check), `lines`, `urls`, `outputs`, outcomes if not done/failed. `ws_flow_kinds()` takes it from there.
- In `steps.php`: `ws_step_config_normalize()` (the case), `ws_step_config_problems()`, `ws_step_check()` (a kind with its own check), the branch in `ws_step_run()` (a kind not run by `ws_scheduled_do()`), `ws_step_fill_target()` when its texts land somewhere new, and its answer in `ws_flow_box_try()` (`workspace-flows-box-try.md`).

## Where is a new box of another kind named?
- `ws_flow_kinds()` (`includes/workspace/flows.php`): label, trigger, outcomes, outputs, `pure` for a value box.
- `flows.php`: its case in `ws_flow_config_normalize()` (the switch `ws_flow_graph_normalize()` calls), `ws_flow_node_texts()` when a setting takes a value, `ws_flow_node_fields()` for outputs set by its settings, its problems in `ws_flow_graph_check()`.
- `flow_runs.php`: its case in the switch of `ws_flow_step_run()`; a box that waits writes `wake_at` / a wait key and is settled by `ws_flow_step_recheck()` (model: `workspace-flows-delay-box.md`). `steps.php`: its case in `ws_flow_box_try()`.

## Where is every new box named?
- `ws_flow_box_tiers()` (`flow_agent_box.php`), held by `tests/workspace_flow_agent_box_test.php`.
- `ws_flow_member_kinds()` (`flow_member.php`) only if role-3 members may use it; that is an owner question (`workflow/decisions-made-workspace-3.md`).
- `ws_flow_kinds_not_offered()` if no new box may be made of it.
- `fl_kind_<kind>` in `ws_flow_js_strings()` (`flows.php`); each new label through `php tools/tr_add.php`.
- Pinegrap AI: `ws_flow_kind_catalogue()` reads the kinds and settings by itself; a setting whose type does not say enough gets a line in `ws_flow_ai_catalogue_hints()` (`flow_draft.php`) or a type override in `ws_flow_kind_settings()` (`flow_catalogue.php`).
- Canvas `assets/js/workspace_flow.js`: `KINDS` (group, into, out), `PALETTE`, its default settings in `addItem()`, its `…Fields(box, node)` form and its call by kind, `VALUE_FIELDS` for value settings, meta text; its line icon in `FLOW_ICONS` of `assets/js/workspace.js`. Then `php tools/build_js.php` (`workspace_flow.min.js`, `workspace.min.js`).
- Memory: its topic file, a line in `workspace-flows-overview.md`.

## Where is a new start event named?
- Its line in `ws_events_catalog()` (`watch.php`) with record, right and filter; its outputs in `ws_flow_event_outputs()` (`flow_anchors.php`); its line in `tests/workspace_flows_parity_test.php` (`workspace-flows-parity-test.md`).
- A new start box (not an event) is an anchor kind in `ws_flow_anchor_kinds()` with its case in `ws_flow_anchor_normalize()` (`workspace-flows-anchors.md`).

## Which tests run?
- `php tools/test.php workspace_flow` (catalogue, outputs, tiers, the box's own test), `php tools/build_js.php --check`, `php tools/check_lang.php`, `node --check` on the changed scripts.
