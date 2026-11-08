---
name: design-template-construction-pages
description: Construction template 2.0.0: home, projects and safety on the Construction blocks: the builders, each page's composition, motion, what left, the test
---

## Which builders draw the blocks?
- Version 2.0.0 (#8292 family, then its pages, construction-2): home, `projects` and `safety` place the Construction blocks ([[designer-blocks-construction]]); their rules ship through `pg_design_template_user_styles(array('construction-hero', 'construction-progress', 'construction-notice'), $construction_css)`.
- `parts.php` (after `$figure_row`): `$fx`, `$rise` (slide-up on view, stagger 80), `$cn_stripe`, `$board_hero($eyebrow, $title, $lead, $buttons, $src, $alt, $facts)` (the page's only `h1`, keeps `pcn-hero`; four `$facts` label => value; photograph fades, board rises, on load), `$site_progress($key, $id, $class)` (a project of `$projects` with its stages in `$site_stages`: the share done is 25, 50 or 75, drawn by a `w-` class, never an inline style), `$street_notice($id, $class)` (calls `$firm['phone']`).
- A stage to come has no class: the family has rules for `pg-cn-done` and `pg-cn-now` only, and the test wants every placed `pg-cn-` class to have its rule.

## What does each page hold?
- Home: board hero, services rising, featured projects, the Eastgate Logistics Hub on site (`cn-home-on-site`), why build with us rising, testimonials rising with `google_reviews`, `home_posts`. The figures band and the process band left the home; `about`, `sectors`, `safety` keep the figures, `process` the steps.
- Projects: board hero (`h1` Our Projects) in place of the page header, the filter bar rising, `projects_list`, then the Valley Road and Bridge on site (`cn-projects-on-site`).
- Safety: safety points rising, the neighbour notice (`cn-safety-neighbours`), certificates rising, figures.
- Services, the service pages, sectors, process, about, team, FAQ and the project page are unchanged.

## How is it tested?
- `test_construction_assembly_pages_on_construction_blocks` (`tests/design_template_construction_assembly_test.php`): every placed `pg-cn-*` class's rule ships, interactions kept by `pg_fx_normalize()`, something moves and one `h1` on each of the three, no Figures band on the home, the load entrance, the quote link, the progress bar's role, five stages with one on site and four board facts on home and projects, four notice facts and the call on safety.

Related: docs/memory/designer/design-template-construction.md, docs/memory/designer/design-template-own-look.md
