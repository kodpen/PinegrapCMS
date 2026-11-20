---
name: agent-guide-designer
description: The guide's designer topic: lines shared with the built-in assistant, <pg-keep> kinds, worked lists the test applies, colours and fonts on design_designs_list, the design_change prompt
module: agent_guide_designer
---

## What does the designer topic say, and where is each line from?
- `includes/fn/agent_guide_designer.php` (plan ai-guide, AG2, #PRNUM): sections page, ops, classes, widgets, examples, mistakes. Engine and limits: `agent-guide-engine.md`.
- The page lines come from `pg_design_ai_page_lines()` (html, bound, css), the same lines the built-in assistant's instructions read (`pg_design_ai_system_prompt()`); the topic uses html and bound.
- The operation lines come from `pg_design_ai_ops_guide()`: one line per name of `pg_design_ai_op_names()`, in that order, then `html` (what html keeps). Before AG2 that function was read by nothing.
- The `<pg-keep>` kinds come from `pg_design_ai_keep_kinds()`: region, shared_ref, loop_area, recipient_loop_area, component, locked, bound; any other kind is a content part's type.
- The framework line for bootstrap5 is `pg_design_ai_framework_line()`'s; the widget kinds by job (`pg_agent_guide_designer_widgets()`) must be keys of `pg_sw_type_labels()`.

## Does the assistant's prompt still read the same?
- Yes, word for word: `tests/agent_guide_designer_test.php` keeps a sha256 of the prompt up to "Rules:" for a selected element on a Bootstrap page and for a whole custom page. A change of a shared line changes the topic and the prompt together and fails that test: update the hash on purpose.
- The prompt reads `HOSTNAME_SETTING` only when defined, so the test calls it without defining it. Other tests need the constant undefined.

## How are the worked lists checked?
- `pg_agent_guide_designer_examples()`: five lists (words and a class, an own class with css, insert a link after, move a region, replace keeping a `<pg-keep>`), written against `pg_agent_guide_designer_example_tree()`. The test runs each through `pg_design_ai_apply_ops()` and finds its JSON in the text.
- The sample page's kept part is a menu region, not a shared_ref: validating a shared_ref reads `shared_components` (a database query) to look for a loop.
- No Bootstrap button class in a worked list: the push checks (`check_bs6.php`, `check_look.php`) read `includes/fn/` as panel markup.

## How big is it?
- 4,046 bytes with every module on, under the 4,096 limit; a new line needs another one shortened.
- Measured at AG2 (every module and scope): the tool list went from 853,438 to 854,288 bytes, as the 36 design tools now end with "Guide: designer." and `design_designs_list`'s description grew. Opening instructions unchanged (2,883).

## What does design_designs_list give for an agent?
- Besides framework (bootstrap5, custom, legacy): `colors` {primary, secondary} from the colour palette (`pg_design_palette_colors()`, Bootstrap's own without one) and `fonts` {body, heading}, CSS font-family lists read from the look's stylesheet by `api_design_fonts_of_css()` (a heading of `var(--pg-font-body)` becomes the body's).
- Both null for a design without Bootstrap; fonts null without a look. A look made in the editor (`file-<id>`) is read from its file. `api_design_values()` in `includes/api/resources/design.php`.

## What does the design_change prompt do?
- Arguments page_id and wish (text). Offered with `design_pages_get` and `design_proposals_create` (design:read and design:write). Embeds `pinegrap://guide/designer` through `embed.fixed` (`pg_mcp_prompt_get()`); without it, names the resource and `guide_read`.
- Steps: read the page (outline first when it is long), the smallest operations, `dry_run` true first, then for real with a summary; report that a designer applies it.
