---
name: design-template-fit
description: Templates App Studio to Events (#8997): header menu opens at 1200 px and wraps, image sizes in props not _attrs, grey and primary-tinted surfaces tint primary and second text
---

## Why does the header menu open out at 1200 px?
- With `navbar-expand-lg` the name, six or seven links and one or two buttons were laid out in the 960 px container from 992 px on. Eleven templates (appstudio, architecture, bakery, brewery, campsite, carrental, cityhall, construction, coworking, dealership, drivingschool) ran past it; the page scrolled sideways up to 1,366 px (bakery, Turkish), and at 1,280 px, the picture "Choose a Template" draws, the header button was cut off.
- Now `navbar-expand-xl` with the `-xl-` utilities; the navbar's container has `flex-xl-wrap`, so a long site name moves the row under it instead of overflowing (charity, courses, events too).
- Bakery, campsite, carrental, dealership still did not fit at 1,200 px in Turkish: their menu has `p??-nav`, which from 1,200 to 1,399 px sets the links a size smaller (`--bs-nav-link-font-size:.875rem`, padding .375rem) and the header button `px-xl-3`. Leaving the button out there (Home Services, [[design-template-homeservices-fit]]) was the other way; the header keeps the main action.

## Why is an image's size a prop?
- The page writes `width` / `height` from the image's props only: `_render_extra_attrs()` leaves them out of `_attrs` ([[image-size-props]]). Twenty images that had them in `_attrs` showed at the file's size: testimonial faces at 96 px instead of 48, City Hall's council portraits at 240 px instead of 112 (the Council and Mayor pages scrolled sideways at 390 px), team cards, app icons, phone screens.
- On 2026-11-12 the same pattern was still in about thirty files of other templates (ferry to zoo): `grep -rn "\$attr('width'" includes/design_templates`.

## Which text is tinted on grey sections and primary-tinted cards?
- A palette's primary and second text colours are made readable on the page background only. Across all 22 palettes they fell to 3.9:1 on `bg-body-tertiary` (account bar, page headers, grey bands) in the dark theme (Midnight, Ocean), to 4.4:1 there in the light one (Coral, Electric) and to 3.2:1 on `bg-primary-subtle` (the account card's Log out, Graphite).
- The User Styles of the 14 templates and of Bookshop and Boutique (which had none before) set `--tpl-tinted-fg` on those surfaces: `--bs-primary-text-emphasis`; on grey, behind `@supports (color-mix)`, `--pg-primary-fg` 85% with `--bs-emphasis-color`, and in the dark theme the emphasis tint 80% with it.
- `text-primary`, `link-primary`, `btn-outline-primary` and `.nav` links there take it; `text-secondary`, `link-secondary`, `btn-outline-secondary` take `--bs-secondary-color`. Minimum after: 5.1:1 dark, 5.2:1 light.
- Blocks: `pg-campsite-marker` and the construction progress dot write `var(--pg-on-primary, var(--bs-body-bg))` on the primary (2.18:1 in dark before).
- Charity's Our Work jump links on the page header are `nav-link link-primary`, so the rule reaches them.

## How was it measured, and what tests it?
- Every public page and e-mail page drawn from the template file (`pg_design_template_preview_html()`, the template's look and palette) in headless Chromium at 320 to 1,440 px, Turkish and English, light and dark (`data-bs-theme` set with transitions off); contrast from computed colours.
- `tests/design_template_fit_test.php`: header navbar `navbar-expand-xl` with no `-lg-` class, container `flex-xl-wrap`, no image size in `_attrs`, the tint rules in the User Styles.
- Each surface rule was measured on a probe page with every palette in both themes, then the 14 templates' pages with Midnight (dark), Coral and Graphite (light), and all 16 with their own palettes (Bookshop's dark eyebrows on grey were 4.12:1).

Related: [[design-template-text-contrast]], [[designer-block-theme-colours]]
