---
name: design-template-library-pages
description: Public Library 2.0.0 (#8846): home, visit, Central Library and catalogue drawn on the Library blocks and the general hours and floor blocks; the data behind them, motion, browser fixes, tests
---

## What does 2.0.0 draw?
- Home: Shelf Hero (eleven spines, weekday hours as two `<time>`s, six format links to `{{page:catalogue}}?format=`), New Arrivals, Card and Loans band, Branches, Coming Up, Testimonials, Latest News. The figures and services rows left the home.
- Visit: page header, the week table (Hours (Seasons): a row per day, a column per branch), Branches (`#lib-branches`), Getting Here, the ask band.
- `branch_central`: after the rooms, the Floor Directory (Locations (Floor Directory)), top floor first. Only branches with a `$floors` entry get one.
- Catalogue: the Shelf Mark Guide band after the list.
- Built in `pages_public.php` (`$shelf_hero`, `$card_band`, `$week_table`, `$floor_directory`, `$table_box`) and `catalogue.php` (`$shelfmark_band`). Each section's `_label` is the palette block's label.

## Where does the data come from?
- `parts.php`: each `$branches[*]['hours']` row has `on` (day indexes, 0 = Monday), so the week table fills every day once; `$week_days`; `$floors['central']` (level, name, text, items, access); `$loan_rules` (format, items, period, renewals, icon).
- The hero's hours are the Central Library's first row; the card's holder is the first testimonial's name; the shelf mark sample is `$catalogue_items['garden']` (635 REE).
- No block was added: `library-hero`, `library-card`, `library-shelfmark`, `hours-seasons`, `locations-floors` ship their rules through `pg_design_template_user_styles()`.

## How does it move?
- `$fx` adds `props._fx`; `$rise` is slide-up on view with a stagger; `$header_in` fades a section header in. The spines are set down on load (slide-down, stagger 60), the card slides in from the left, the shelf mark spine scales in. Every page carries at least three effects.

## What did the browser show?
- 1366 px: the open header (brand, seven links, Join the Library) was 100 px wider than the 1140 px container. Join the Library is `d-xl-none d-xxl-inline-flex` (collapsed menu below 1200 px, open bar from 1400 px), and the name is `text-truncate` with `.navbar-brand{min-width:0;max-width:calc(100% - 5rem)}`: a 33-letter name ends in an ellipsis at 1200 px, and on a phone before the menu button.
- Dark with Forest: `text-primary` and `link-primary` on `bg-body-tertiary` gave 4.12:1, the hero's tinted corner 4.48:1. On both the template mixes small coloured text 10% darker (light) or 15% lighter (dark), secondary links and buttons too: Forest's brown reads at 5.1:1 ([[design-template-page-fit]]). The first fix (the emphasis tint, the theme's grey for secondary) gave 3.85:1 with Midnight.
- The loans and week tables sit in a positioned, focusable `table-responsive` region, so the hidden Day heading stays inside the scroll box. No sideways scroll at 375 and 320 px.
- A template opened from `add_system_style.php` without `?look=&palette=` wears the default look and no palette; measure colours with the template's own.

## How is it tested?
- `test_library_assembly_pages_on_library_blocks` (`tests/design_template_library_assembly_test.php`): version, shipped rules, `_fx` kept by `pg_fx_normalize()`, one `h1`, labels and search ids, the hero's spines, times and formats, the loans table, the week table's cells, the floors' order and rooms, the shelf mark's parts and its single example, the dark band and brand rules.
- `test_library_shared_parts_have_no_shop` checks the header's button classes.

Related: [[design-template-library]], [[designer-blocks-library]], [[design-template-homeservices-fit]]
