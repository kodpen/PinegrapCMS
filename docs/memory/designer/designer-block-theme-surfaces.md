---
name: designer-block-theme-surfaces
description: How a ready-made designer block writes a dark navbar, a hero on a photo, buttons and links on tinted or primary surfaces, and carousel slides so they pass contrast in every look, palette and mode.
---

## Where do these recipes come from?
- The older palette blocks and Components-tab items were moved to the classes of `designer-block-theme-colours.md` and measured with the theme/a11y harness (5 looks x 5 palettes x light/dark x 360/768/1280 x en/tr). What failed is written down here.

## How is a dark navbar written?
- `navbar navbar-expand-* bg-body border-bottom` with `_attrs` `data-bs-theme="dark"` (`navbar`, `navbar-search`, `navbar-dark-center`). `navbar-dark` is deprecated in Bootstrap 5.3 and `bg-dark` ignores dark mode. Give it one palette accent: brand `text-primary`, a CTA `link-primary` or a `btn-primary` (a dark bar has nothing else that follows the palette).
- A light bar is `bg-body`, never `bg-white`: in dark mode the links turn white on white (1:1 measured on `navbar-light-cta`).

## How does a section with a background photo carry its text?
- The scrim is the section's only, in-flow child: section `d-flex … pg-hero-tall` with the photo in its style, overlay `d-flex flex-column justify-content-end w-100 py-5 text-body pg-shade-bottom` + `data-bs-theme="dark"`, the container inside the overlay (`hero-image-bg`). An absolute overlay beside the text leaves the text over the bare photo, and an absolute content box is cut by `overflow-hidden` when a translation runs long.

## Which buttons and links stay readable on a tinted or primary surface?
- Outline buttons (`btn-outline-primary`, `btn-outline-secondary`) and link text use the palette's readable shade, which is checked against `bg-body` only: on `bg-body-tertiary`, `bg-primary-subtle`, an alert or a scrim they measured 3.8–4.4:1. There a second action is a solid `btn-secondary` (`cta-simple`, `booking-cta`, `hero-image-bg`), or a `btn-link` with `text-reset` on an alert or a `text-bg-primary` band (`alert-warning-actions`, `cta-gradient`). A row of links moves to `bg-body` (`error-404-search`, `breadcrumbs-with-header`).
- Small primary text sits on `bg-body` (`team-leadership` card): on `bg-body-tertiary` even `text-primary-emphasis` measured 3.85:1 in dark mode (midnight, crimson).
- Status headers: `bg-danger-subtle text-danger-emphasis` with `border-danger-subtle` (`compare-vs`); `bg-danger bg-opacity-10 text-danger` measured 3.1:1.
- Never `opacity-*` on text: `opacity-75` on a primary band measured 2.4:1. No `progress-bar-striped` under a label: the stripes took white text to 3.97:1 (Components-tab progress).
- A badge on a primary band: `text-bg-secondary`; `bg-white bg-opacity-25 text-white` had no measurable contrast.
- A fixed gradient image ignores the palette and makes the text contrast unmeasurable: `cta-gradient` is a flat `text-bg-primary` band; an operator can add a gradient in Background.

## How are carousel slides and controls coloured?
- Bootstrap paints the controls, indicators and caption white, and black under `data-bs-theme="dark"` or `.carousel-dark` (the variables come from the nearest theme). So the Components-tab carousel ships `carousel-dark` with light slides: `makeCarouselSlide()` rotates `bg-primary-subtle`, `bg-body-tertiary`, `bg-secondary-subtle` with `text-body` and `data-bs-theme="light"` on the slide.
- On a plain surface the indicators take the text colour: `pg-carousel-ink` (`testimonial-carousel`). Photo captions sit on `pg-shade-bottom` with `data-bs-theme="dark"` and `text-body` across the slide's full width (`image-carousel`); Bootstrap's dark-mode caption colour is black.

## What keeps this from coming back?
- `tests/designer_blocks_test.php` fails when a string literal of `createFromPalette()` or `makeCarouselSlide()` holds a fixed colour class (`bg-dark`, `text-white`, `text-muted`, `btn-light`, `navbar-dark`, plain `bg-primary`, …) or a `light`/`dark` button variant.
