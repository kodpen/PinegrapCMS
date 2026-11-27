---
name: site-plan-request
description: The plan of pages of the "Describe it" start (SB3): the designer/site_plan action, what it sends, the answer's pages and theme and their checks, the drafts it reports, the limits
module: site_plan
---

## Where is it?
- `designer/site_plan` (`includes/panel/designer.php`, designers only, in the page cache's read-only list) calls `pg_site_plan_request()` (`includes/fn/site_plan.php`). One `pg_ai_chat()` call; nothing is written on the server. Rules: `site-from-brief.md`; the brief: `describe-start.md`.

## What does it take and send?
- `brief` as an array or JSON, checked by `pg_site_plan_input()` with `pg_site_brief_check()`: a brief with a mistake is refused with its first field's words.
- System rules: one page per page of the brief, in its order, none added; name, address, purpose, 3 to 8 sections in words, menu; the theme as the five questions' keys (looks, type feels, rooms listed); the shared writer rules; the JSON shape last. User: the brief as JSON. Temperature 0.5, `max_tokens` 4000.

## What comes back?
- `plan.pages`: `pg_site_plan_pages()`: name (40), address (`pg_site_plan_address()`: a-z, 0-9, hyphens, Turkish letters folded, 60, `page` when empty; a repeat gets `-2`, `-3`), purpose (200), sections (160 each, at most 10), menu `main` / `footer` / `none` (unknown is `main`). A page without a name or a section is dropped; at most 12 pages. No page left: refused, "The plan of pages could not be made. Please try again."
- `plan.theme`: `pg_site_plan_theme()` through `pg_design_variables_answers()`; the brief's brand colour wins over the model's; unknown answers are `''` (skipped).
- `drafts`: `cap`, `left` after this request, `run` = `pg_site_plan_cost()`: one draft a page (its describe) plus one a section (its texts); a page with no sections (added on the plan screen) counts `PG_SITE_PLAN_GUESS` = 6. The plan screen counts again as rows change and refuses a run larger than `left` (`site-plan-screen.md`).

## Which limits apply?
- Those of "Write with AI" and the Sketch tool (`sketch-ai.md`): `pg_ai_write_problem()` (answer `off: true`), `pg_ai_state()`, the request takes one daily draft, and the Sketch tool's pace of 20 a minute a person (same `waf_rate` bucket `sketch_ai`, as the run that follows uses those requests).

## What was not checked?
- Not run with a live Pinegrap AI; the pure parts and the wiring in `tests/site_plan_test.php`.
