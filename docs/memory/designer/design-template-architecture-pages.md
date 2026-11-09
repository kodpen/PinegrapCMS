---
name: design-template-architecture-pages
description: Architecture template 2.0.0: home, projects and the project page on the Architecture blocks: the builders, each page's composition, motion, what left, the test
---

## Which builders draw the blocks?
- Version 2.0.0 (#8264 family, then its pages, #8280): home, `projects` and the `project_detail` widget place the Architecture blocks ([[designer-blocks-architecture]]); their rules ship through `pg_design_template_user_styles(array('architecture-hero', 'architecture-sheet', 'architecture-materials'), $architecture_css)`.
- `parts.php` (projects.php is read before pages_public.php in the tests, so the builders live here): `$fx`, `$rise` (slide-up on view, stagger 80), `$materials` (six samples), `$plate_hero($eyebrow, $title, $lead, $buttons, $src, $alt, $plate)` (the page's only `h1`; `$plate` = number, project name, its setting, the link; words rise and plate fades on load), `$material_board($id, $class)`. `$project_facts()` draws `dl.pg-arch-facts`; `$gallery($key, true)` leaves `photo_1` out for the sheet.

## What does each page hold?
- Home: plate hero (plate 01, the Courtyard House, linking `projects?location=`), featured projects, services rising, material board (`ai-home-materials`), how we work (stages rise, picture fades), testimonials with `google_reviews`, awards rising, `home_posts`. The figures band and the principles left the home; `studio` keeps both.
- Projects: plate hero (`h1` Projects, plate 02, the Print Works, linking `projects?type=`) in place of the page header, then the filter bar rising and `projects_list`.
- Project page: the project sheet (photo 1 fading beside type and location, `h1` name, summary, the facts rising, credits), photos 2-4, then brief and client's words beside the enquiry aside.
- Services, studio, team, process, awards and FAQ are unchanged.

## How is it tested?
- `test_architecture_assembly_pages_on_architecture_blocks` (`tests/design_template_architecture_assembly_test.php`): every `pg-arch-*` class's rule ships, interactions kept by `pg_fx_normalize()`, something moves and one `h1` on each of the three, no Figures or Our Principles band, the load entrance, the board's search id and six samples, both plate links, `photo_1` once on the project page.

Related: docs/memory/designer/design-template-architecture.md, docs/memory/designer/design-template-own-look.md
