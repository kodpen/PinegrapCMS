---
name: design-template-fit
description: Templates App Studio to Events (#8997): header menu opens at 1200 px and wraps, image sizes in props not _attrs, dark grey sections lift primary and second text
---

## Why does the header menu open out at 1200 px?
- With `navbar-expand-lg` the name, six or seven links and one or two buttons were laid out in the 960 px container from 992 px on. Eleven templates (appstudio, architecture, bakery, brewery, campsite, carrental, cityhall, construction, coworking, dealership, drivingschool) ran past it; the page scrolled sideways up to 1,366 px (bakery, Turkish), and at 1,280 px, the picture "Choose a Template" draws, the header button was cut off.
- Now `navbar-expand-xl` with the `-xl-` utilities; the navbar's container has `flex-xl-wrap`, so a long site name moves the row under it instead of overflowing (charity, courses, events too).
- Bakery, campsite, carrental, dealership still did not fit at 1,200 px in Turkish: their menu has `p??-nav`, which from 1,200 to 1,399 px sets the links a size smaller (`--bs-nav-link-font-size:.875rem`, padding .375rem) and the header button `px-xl-3`. Leaving the button out there (Home Services, [[design-template-homeservices-fit]]) was the other way; the header keeps the main action.

## Why is an image's size a prop?
- The page writes `width` / `height` from the image's props only: `_render_extra_attrs()` leaves them out of `_attrs` ([[image-size-props]]). Twenty images that had them in `_attrs` showed at the file's size: testimonial faces at 96 px instead of 48, City Hall's council portraits at 240 px instead of 112 (the Council and Mayor pages scrolled sideways at 390 px), team cards, app icons, phone screens.
- On 2026-11-12 the same pattern was still in about thirty files of other templates (ferry to zoo): `grep -rn "\$attr('width'" includes/design_templates`.

## Which text is lifted on grey sections in the dark theme?
- A palette's primary text colour is made readable on the page background; on `bg-body-tertiary` (account bar, page headers, grey bands) it measured 3.9 to 4.4:1 in the dark theme in all fourteen templates, and so did `link-secondary` (the breadcrumbs' Home link).
- Each template's User Styles: `text-primary`, `link-primary` and `btn-outline-primary` there take `--bs-primary-text-emphasis`, mixed 80% with `--bs-emphasis-color` behind `@supports (color-mix)` (Cobalt's emphasis tint is as dark as its primary: 4.12:1 without the mix); `link-secondary` and `btn-outline-secondary` take `--bs-secondary-color`. Light was measured with no failure and is unchanged.
- Blocks: `pg-campsite-marker` and the construction progress dot write `var(--pg-on-primary, var(--bs-body-bg))` on the primary (2.18:1 in dark before).
- Charity's Our Work jump links on the page header are `nav-link link-primary`, so the rule reaches them.

## How was it measured, and what tests it?
- Every public page and e-mail page drawn from the template file (`pg_design_template_preview_html()`, the template's look and palette) in headless Chromium at 320 to 1,440 px, Turkish and English, light and dark (`data-bs-theme` set with transitions off); contrast from computed colours.
- `tests/design_template_fit_test.php`: header navbar `navbar-expand-xl` with no `-lg-` class, container `flex-xl-wrap`, no image size in `_attrs`, the dark rules in the User Styles.

Related: [[design-template-text-contrast]], [[designer-block-theme-colours]]
