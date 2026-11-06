---
name: workspace-flows-ai-box-canvas
description: The Pinegrap AI box on the flow canvas (B2's second PR): KINDS.ai, a classifying box's named arrows through kindOf(), the palette gate, aiFields(), extract's values
---

## How does the canvas draw it? (workspace_flow.js)
- `KINDS.ai` (group system, icon `FLOW_ICONS.ai` in workspace.js) holds a writing box's outcomes; `kindOf()` gives a classifying box `class_1…`, `other`, `failed`, so ports, the arrow picker and the walk follow the settings. `outcomeText()` names `class_N` by its outcome ("Outcome N" when unnamed), `other` "None of these"; both are drawn on the arrow.
- Palette after Read a record, offered while `FL.steps.ai` (`ws_ai_ready()`). `aiFields()`: mode (`AI_MODES`), prompt and text as value fields (`VALUE_FIELDS.ai`), outcomes (up to 8; removing one drops its arrow and moves later arrows up), hours. `aiMeta()` shows the mode and the outcomes. `boxValues()` adds `number` and `found` for extract.

## What is it built on?
- The box itself, its settings and its wait: `workspace-flows-ai-box.md`. Tests `tests/workspace_flow_ai_box_test.php` (canvas source), `tests/workspace_flows_canvas_test.php` (KINDS against `ws_step_ai_arrow_outcomes()`).
