---
name: design-template-filmstudio-pages
description: Film Studio template 2.0.0: home, services and project page on the Film Studio blocks, motion elsewhere, names in their own case; builders, pages, limits, test
---

## Which builders draw the blocks?
- Version 2.0.0 (`filmstudio-1` #8689 family, then its pages, #8739): the home, the services page and the `project_detail` widget place the Film Studio blocks ([[designer-blocks-filmstudio]]) and the general `timeline-horizontal`; their rules ship through `pg_design_template_user_styles(array('filmstudio-reel', 'filmstudio-credits', 'filmstudio-formats', 'timeline-horizontal'), $filmstudio_css)`.
- `parts.php`: `$fx($node, $effect)`, `$rise($node, $stagger = 90)` (slide-up on view, 700 ms), `$fade_in($node, $delay = 0)` (fade on view, 800 ms). `$showreel($seed, $title, $frame)` takes the frame's class: the home passes `ratio ratio-16x9` inside `pg-filmstudio-screen`.
- `pages_public.php`: `$home_reel` (Showreel Hero), `$home_featured` (Title Card and Credits, Night Drive), `$home_timeline` (Timeline: Horizontal over `$process`), `$format_card()` and `$services_formats` (Cuts for Every Screen).

## What does each page hold?
- Home: the dark reel band (the only `h1`; words rise on load, the screen grows, the five films rise after 700 ms), figures, the featured film (`fm-featured-film`), services, `home_projects`, How We Work as the horizontal timeline, testimonials with `google_reviews`, `home_posts`. Why Work with Us left the home; `about` keeps it (`fm-about-why`).
- The featured film is page content: Night Drive's still, the five credits from `$crew` (Directed by, Director of Photography, Producer, Editor, Colourist) and its client's words. Its play mark links `{{page:projects}}?service=` the film's service, with "See the work" hidden for a screen reader.
- Services: the cards, then the formats (`fm-formats`, between the cards and Industries), the industry tiles, the ask band.
- Project page: a centred title card (badges, `h1`, summary, facts; fades on load), the first still at 2.39:1 (`pg-filmstudio-scope pg-hide-if-empty`) with the round play mark as the record's one `film_url` link (`target="_blank"`, `rel="noopener"`), the other stills rising, credits, words and the aside. The still and the mark leave on their own when empty; the frame goes when both do.
- Process, studio, crew, about, a service's steps: rows and lists rise on view; section headings fade in.

## Why do names keep their own case?
- `pfm-display` and `text-uppercase` set capitals, and a Turkish page uppercases "i" to "İ" ([[designer-inline-text-editing]]): an English film name read "NİGHT DRİVE". So a name typed into a record or a person's name (project, crew, crew call, post, enquiry, account) carries no capitals class; a translated title or eyebrow may.

## What could the editor not do?
- A record's credits are one long text ("Role: Name" lines), so the project page cannot draw them as `dl.pg-filmstudio-roll`: only the home's featured film rolls.
- The reel's chapter timecodes are fixed text: a player cannot yet seek to a chapter.

## How is it tested?
- `test_filmstudio_assembly_home_services_project_on_blocks` (`tests/design_template_filmstudio_assembly_test.php`): version 2.0.0, each page places its block classes and ships their rules, every `_fx` kept by `pg_fx_normalize()`, the effects per page, no `autoplay`, the play mark bound to `film_url`. The projects test still wants one film link and no embed.

Related: docs/memory/designer/design-template-filmstudio.md, docs/memory/designer/design-template-own-look.md
