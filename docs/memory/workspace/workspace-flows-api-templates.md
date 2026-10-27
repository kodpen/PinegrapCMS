---
name: workspace-flows-api-templates
description: Flow templates over the external API (A6, #2298): GET /workspace/flow-templates, POST /workspace/flows with template and answers, what is checked, what never happens (publish)
---

## What does the listing give?
- `GET /workspace/flow-templates` (`ws_api_flow_templates_list()`, a read MCP tool): `ws_flow_templates_for($viewer)`, the canvas gallery's list filled for the owner of the application (their e-mail, site, counts). Nobody who may not schedule (`ws_can_schedule()`) gets any: the fill is null for them.
- Per template (`ws_api_flow_template_present()`): key, category, title, help, start, boxes, threads, settings (the types only: channel, person, time, url, address), graph (`ws_flow_graph_present(…, true)`). Icon and motion stay canvas-only.

## How is a draft made from one?
- `POST /workspace/flows` takes `template` (a key) and `answers` (object, setting type => value). `ws_api_flow_from_template()` (pure) builds the input before `ws_flow_save_check()`, so a dry run meets the same refusals.
- Without `answers` the boxes keep the template's own values (as Close on the setup panel). With them every setting must pass `ws_flow_template_answers_check()`; a refusal is 422 on `answers.<type>`. Values go in through `ws_flow_template_apply()`.
- `name` is optional with a template (its title); still required without one (`ws_flow_save_check()`: "A flow needs a name.").
- A key not offered, or `nodes`/`edges`/`notes` beside `template`: 422 on `template`. A caller who may not build flows skips the template step and meets the save check's 403.

## What does it never do?
- Publish: the setup panel's "Publish now" stays the person's own session (#1708 rule, `workflow/decisions-made-product.md`). Rights on the channel and people are checked at publish.

## How is it tested?
- `tests/workspace_flow_api_templates_test.php`.
