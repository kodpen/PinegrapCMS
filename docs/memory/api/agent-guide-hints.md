---
name: agent-guide-hints
description: Hints on API refusals: hint and guide on the error object from pg_agent_guide_hints(), the designer's own refusal codes on design.proposals.create, the hash check (design_page_changed)
module: agent_guide_hints
---

## What does a refusal carry besides code and message?
- `hint`: one English sentence on what to do next, at most 200 characters, on every site's language (owner decision in `workflow/decisions-made-product-85.md`); `message` stays the translated sentence.
- `guide`: the guide section that explains it, `topic#section`, read with `guide_read` / `GET /guide?topic=…&section=…` (`agent-guide-engine.md`).
- Built in #10766 (plan ai-guide, AG13a). Only codes in the registry get them; `not_found` and the like get none.

## Where do the hints live?
- `pg_agent_guide_hints()` in `includes/fn/agent_guide_hints.php`: code => hint, guide. Tools in a hint are written `{tool:route.id}`, as in a topic line; `pg_agent_guide_hint()` writes their names.
- `api_fail()` adds them through `api_error_with_hint()` (`includes/api/response.php`); a handler passes none. Adding a hint is a registry line, no handler is touched.
- `tests/agent_guide_hints_test.php` fails on a guide section its topic does not declare, a hint over 200 characters, a named tool that is no route, and a 422 code missing from `api_error_catalogue()`.

## Which codes have a hint?
- The designer's: `design_page_changed`, `design_node_unknown`, `design_keep_inside` (guide `designer#page` / `designer#mistakes`), `design_op_invalid`, `design_page_invalid` (`designer#ops`).
- Every route's: `validation_failed`, `invalid_cursor` (`start#calls`), `invalid_dry_run`, `dry_run_unsupported` (`start#writes`), `insufficient_scope` (`start#jobs`).

## Which codes does design.proposals.create answer?
- Before AG13a every refused operation was 422 `validation_failed`. Now, field still `ops[N]`:
  - `design_node_unknown`: node, parent, before or after names an id the page lacks.
  - `design_keep_inside`: text of a `<pg-keep>` part, a bound text or attribute, or something inserted inside a part.
  - `design_op_invalid`: any other wrong operation; `design_page_invalid`: the operations together make no valid page.
  - `validation_failed` stays for an empty list or more than 40 operations.
- The code comes from `pg_design_ai_apply_ops()` (`code` beside `error`, through `_pg_dai_op()` and `_pg_dai_position()`), passed on by `pg_design_ai_proposal_create()`; `api_design_proposal_fail()` writes each code out so `check_api_schema.php` finds it answered. The built-in assistant reads only `error`.

## How is a page changed since it was read caught?
- Optional body field `hash` on `design.proposals.create`: the `hash` `design.pages.get` gave. When the page's base tree (`pg_design_ai_base_tree()`, the request's copy with `request_id`) hashes otherwise: 409 `design_page_changed`, field `hash`, nothing kept. Without `hash` nothing is compared, as before.

## What about a class that draws nothing?
- Not a refusal of the API: the proposal is kept and only the built-in assistant is warned (`pg_design_ai_bare_classes()`). Refusing it would change what running integrations get, so it has no code.
