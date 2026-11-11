---
name: design-template-overview
description: Start file for the design-template(s)-* files: the Choose a Template catalog, Use This Template, a template file, its own look rule, rows and standard pages, and each template's file
---

## What is a design template?
- A ready site (pages, widgets, shared parts, folders, forms, samples, a catalog with the shop) in `includes/design_templates/<id>.php` (`pinegrap-<name>`, `hello-pinegrap`), often a folder too. "Choose a Template" lists them with a live picture; "Use This Template" opens one as unsaved tabs; Publish saves it.
- "Turn this design into a template" (designs list, or Settings > General) keeps a design as a template here (`custom-<n>`).

## Which decisions bind it?
- Its own look (owner, 2026-11-07, `workflow/decisions-made-product-37.md`): `design-template-own-look.md` (a key rule); the block families: `designer-blocks-overview.md`.
- Template plans are paused but for the owner's lines: `workflow/coordinator-no-plans.md`, `workflow/design-template-owner-line.md`.
- Comment threads, events and testimonials (`decisions-made-product-16.md`): `design-template-threads.md`, `-shop-comments.md`, `-newsroom-comments.md`. Pictures of templates (`decisions-made-product-35.md`): `design-live-pictures.md`.
- News and Magazine (owner, 2026-10-22): `design-templates-newsroom.md`. Per-template ones: `[decision: …]` in `INDEX.md`.
- Site output stays on Bootstrap 5.3.8 (`ui/bootstrap-6.md`).

## Which file holds what?
- The file's keys, placeholders (`_pg_tpl_fill()`), `requires`, starter widgets, shared parts, folders, comments: `design-templates-file-format.md`.
- Catalog, fees, contact groups, the suggested look and the drawing: `design-templates-catalog-and-look.md`; form fees: `design-template-form-fees.md`.
- Rows made on opening, discarded, reused: `design-templates-rows.md`.
- Pages every template has (account, inbox, staff, blog, search, e-mail pages), sample records: `design-templates-standard-pages.md`.
- Made from a design: `design-templates-from-design.md`; at setup: `platform/design-template-install-at-setup.md`.
- For every template: comment labels (`design-template-comment-labels.md`), the blog cover input (`design-template-blog-cover.md`), text colours and shared names (`design-template-text-contrast.md`).

## Which file is each template's?
- `design-template-<name>.md`: appstudio, architecture, bakery, brewery, campsite, carrental, charity, cityhall, construction, courses, coworking, dealership, drivingschool, events, ferry, filmstudio, florist, forum, gallery, gym, hello, homeservices, hotel, launch, law, library;
  magazine, marina, market, museum, music, news, pharmacy, photo, planetarium, podcast, portfolio, realestate, recruitment, restaurant, salon, school, skiresort, social, sportsclub, theatre, travel, veterinary, wedding, zoo.
- Cafe and Clinic: `design-template-cafe.md`; Bookshop events: `-bookshop-events.md`.
- Pages rebuilt on a block family: `design-template-<name>-pages.md` (or `-home`) for architecture, campsite, cityhall, construction, coworking, drivingschool, ferry, gym, hotel, launch, marina, museum, music, pharmacy, photo, school, sportsclub, travel, wedding.
- More topics: forum (`-blog`, `-decisions`, `-layout`, `-moderation`, `-private-threads`, `-replies`), magazine (`-a11y`, `-issues`, `-layout`, `-publishing`), news (`-layout`, `-publishing`), events and salon (`-words`).
- Boutique, Store, Playground: only in the shared files.

## How is a new template added?
1. Its plan starts with the block family, then the pages (`design-template-own-look.md`); while the pause holds, none is started.
2. Write the file to `design-templates-file-format.md`, the standard pages and e-mails to `design-templates-standard-pages.md`, every text through `lang()` (Turkish via `php tools/tr_add.php`).
3. Tests `tests/design_template_<name>_*_test.php` (account, public, standard…) beside the shared ones (comment labels, text contrast, alert links).
4. Write `design-template-<name>.md` and add the name here; the plan growing the cluster keeps it current.
