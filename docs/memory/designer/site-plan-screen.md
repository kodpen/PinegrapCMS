---
name: site-plan-screen
description: The plan screen of "Describe it" (SB4): the page rows, the theme's five answers, the drafts line that stops "Make my site", what "Make my site" keeps and opens
module: site_plan
---

## Where is it?
- In the "Describe it" pane of the New design window, after "Plan my site" (`describe-start.md`). The server draws only its empty place `section.sd-sp` with its words, the theme's choices, the limits and the address (`pg_site_plan_screen()`, `includes/fn/site_plan.php`); `assets/js/designer_describe.js` draws the rest. Rules: `site-from-brief.md`.
- "Plan my site" sends the checked brief to `designer/site_plan` (`site-plan-request.md`); the foot's line reads "Planning your pages…" and the button waits. A refusal's words, or "The connection was lost. Please try again.", stand in the foot's line and the brief stays.
- Back shows the brief again; "Plan my site" on the same brief shows the rows as they were left, with no second request (kept for the open window only).

## What does a page row hold?
- Its number, the name as a field (40 characters), a line with `/address`, the count of sections (their words in the hover title) and the purpose; the place in the menu (Main menu, Footer only, Not in a menu); Move up, Move down, Remove.
- A renamed row's address follows its name; "Add a page" (up to 12) adds "New page" with no sections, its line "Its sections are described from its name.".
- "Make my site" checks the rows (`checkPlan()`): an empty name is marked under its field, no page left says "Keep one page at least."; addresses are made unique with -2, -3 as the server does.

## How is the theme shown?
- Under "Theme", the five questions' labels as value rows: Brand colour (a colour picker), Second colour ("Choose for me" or "I will pick" with a picker), Feel, Fonts, Space (selects, "Not chosen" for a skipped answer) from `pg_site_plan_theme_choices()`.

## What does the drafts line say?
- In the foot: "Making these pages takes about N of the M drafts left today."; N is `cost()`, the twin of `pg_site_plan_cost()` (one a page plus one a section; a page with no sections counts `PG_SITE_PLAN_GUESS` = 6), M the `drafts.left` of the plan's answer. When N is larger it turns rose with "Remove a page, or make the site tomorrow." and "Make my site" is disabled.

## What does "Make my site" do?
- Keeps in `localStorage` `pg_site_plan` (`{brief, pages, theme}`, the approved plan the editor's run reads) and `pg_proto_sketch_new` (one empty frame a page, the Canva start's places, `details` with the address, typed), then opens `add_system_style.php?framework=bootstrap5&start=describe` with `&answers=` (the given answers only).
- `?start=describe` sets `auto_sketch` as `?start=canva` does: the editor opens the Prototype view on the sketch. The first save moves the sketch to the design's id (`canva-start.md`). Nothing is stored on the server; the editor then makes the pages (`site-run.md`).

## Tests?
- `tests/site_plan_screen_test.php`: the place and its words, the address, and in Node the twins of the address and the drafts, the rows' check, the sketch and the answers.

Related: docs/memory/designer/describe-start.md, docs/memory/designer/site-plan-request.md, docs/memory/designer/canva-start.md
