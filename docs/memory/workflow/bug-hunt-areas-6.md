---
name: bug-hunt-areas-6
description: The one round of bug hunts of the night of 2026-11-27 (owner): up to 15 workers a coordinator, work first, the ten designer parts given to k3 and the eight areas given to k2, how the night ends
---

## What did the owner ask?
- Owner (2026-11-27, agent channel message 14700): "yapacak iş biterse bir miktar bug hunt başlat, koordinatör başı 15 er işçi kadar. sonrasında koordinatörleri uyut".
- Owner (2026-11-27, message 14703): "hata avı son seçenek önce geliştirecek iş varsa onlara tamamlansın."
- Architect's reading (message 14704): for that night a coordinator runs up to 15 workers in place of 8; free `bug` issues and plan steps go out first; the empty places take a hunt, one worker an area, from the lists below; a hunt that ends is not replaced.
- The limits of `bug-hunt.md` (three hunts a coordinator, 8 places) come back when the round has ended: the 15 was for one night.

## How does the night end?
- A coordinator whose workers have all ended removes its timers and waiters, says so in the channel naming the merging session and the architect, and ends.
- The merging session lands until both have said so and no `ready` PR is left, then removes its timers and says so. The architect closes its request waiter last.
- Issues and steps that did not fit stay for the morning. What only the owner decides waits as a poll, its PR back in draft.

## Which areas did k3 get? (message 14706; scripts in `assets/js/`)
- Sketch tool: `style_designer_sketchai.js`, `_sketchbuild.js`, `_sketchdetails.js`, `_sketchread.js`, `includes/fn/sketch_ai.php`.
- Preview as: `style_designer_previewas.js`, `includes/fn/designer_preview_as.php`, the widget readers.
- Can change per place: `style_designer_overrides.js`, `includes/fn/component_overrides.php`.
- Spacing handles, bands and canvas guides: `style_designer_spacing.js`, `_bands.js`, `_guides.js` (the owner met three faults there on 2026-11-26).
- Code view: `style_designer_code.js`.
- Accessibility view: `style_designer_accessview.js`, `includes/fn/a11y.php`.
- Style guide and theme: `style_designer_styleguide.js`, `_theme.js`, `includes/fn/design_style_guide.php`.
- Clean up: `style_designer_cleanup.js`, `includes/fn/design_cleanup*.php`.
- Select similar, Find and replace, the two searches: `style_designer_similar.js`, `_findreplace.js`, `_optsearch.js`, `_treesearch.js`.
- Image focus, shapes, background layers: `style_designer_focus.js`, `includes/fn/image_focus.php`, `_shape.js`, `_curve.js`, `_bglayers.js`.

## Which areas did k2 get? (message 14707)
- Page Design Studio home and the New design window: `designer_home.js`, `view_system_styles.php`, what plan `designer-home-tidy` landed.
- Designer grid, move and mirror: `style_designer_grid.js`, `_move.js`, `_mirror.js`.
- Designer states, interactions, collections and fonts: `style_designer_states.js`, `_interactions.js`, `_collection.js`, `_fonts.js`.
- The designer's first tour: `style_designer_tour.js`, `pg_designer_tour.js`, `includes/fn/tour.php`.
- A classic page made editable (LE1 to LE12): `includes/fn/legacy_to_designer*.php`, `legacy_to_classic.php`.
- Site Settings after the tidy rounds, in two areas: the e-commerce and accounting panes; the general and maintenance panes.
- Workspace agents since 2026-11-20: memory search, a channel info's agents, the brief's who-wakes block.

## What is left alone in every area?
- `style_designer.src.js` (held that night by #11973, #11987 and the plan steps) and the files of open PRs: a fault there is a `bug` issue.
- Designer parts hunted before: activity, variables, record edit, document, phone, comments, clicks, my library, import, publish check.
- What each hunt found goes into its own `bug-hunt-areas-<area>.md`.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workflow/bug-hunt-areas-5.md
