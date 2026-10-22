---
name: designer-block-theme-surfaces
description: How a ready-made designer block writes a dark navbar, a hero on a photo, buttons and links on tinted or primary surfaces, and carousel slides so they pass contrast in every look, palette and mode.
---

## Where do these recipes come from?
- Each block's HTML was rendered with `bridge.css` + `base.css` (5 looks x 5 palettes x light/dark x 360/768/1280 px x en/tr) and its text measured with `pg_contrast.js`. What failed is written here.

## How is a dark navbar written?
- `navbar navbar-expand-* bg-body text-body border-bottom` with `_attrs` `data-bs-theme="dark"` (`navbar`, `navbar-search`, `navbar-dark-center`). `navbar-dark` is deprecated in Bootstrap 5.3 and `bg-dark` ignores dark mode. Give it one palette accent: brand `text-primary`, a CTA `link-primary` or a `btn-primary`.
- A light bar is `bg-body`, never `bg-white`: in dark mode its links went white on white (`navbar-light-cta`).

## How does a section with a background photo carry its text?
- The scrim is the section's only, in-flow child: section `d-flex … pg-hero-tall` with the photo in its style, overlay `d-flex flex-column justify-content-end w-100 py-5 text-body pg-shade-bottom` + `data-bs-theme="dark"`, the container inside the overlay (`hero-image-bg`). An absolute overlay leaves the text over the bare photo, and an absolute content box is cut by `overflow-hidden` when a translation runs long.

## Which buttons and links stay readable on a tinted or primary surface?
- Outline buttons (`btn-outline-primary`, `btn-outline-secondary`) and link text use the palette's readable shade, which is checked against `bg-body` only: on `bg-body-tertiary`, `bg-primary-subtle`, an alert or a scrim they measured 3.8–4.4:1. There a second action is a solid `btn-secondary` (`cta-simple`, `booking-cta`, `hero-image-bg`), or a `btn-link` with `text-reset` on an alert or a `text-bg-primary` band (`alert-warning-actions`, `cta-gradient`). A row of links moves to `bg-body` (`error-404-search`, `breadcrumbs-with-header`).
- Small primary text sits on `bg-body` (`team-leadership` card): on `bg-body-tertiary` even `text-primary-emphasis` measured 3.85:1 in dark mode.
- Status headers: `bg-danger-subtle text-danger-emphasis` (`compare-vs`); `bg-danger bg-opacity-10 text-danger` measured 3.1:1.
- Never `opacity-*` on text: `opacity-75` on a primary band measured 2.4:1. No `progress-bar-striped` under a label (white text 3.97:1). The Components-tab bar is `text-bg-primary`; the Progress panel's Color writes `text-bg-*` and drops a saved `bg-*`.
- A badge on a primary band: `text-bg-secondary`; `bg-white bg-opacity-25 text-white` had no measurable contrast.
- A fixed gradient image ignores the palette and makes the text contrast unmeasurable: `cta-gradient` is a flat `text-bg-primary` band; an operator can add a gradient in Background.

## How are carousel slides and controls coloured?
- Bootstrap paints the controls, indicators and caption white, and black under `data-bs-theme="dark"` or `.carousel-dark`. So the Components-tab carousel ships `carousel-dark` with light slides: `makeCarouselSlide()` rotates `bg-primary-subtle`, `bg-body-tertiary`, `bg-secondary-subtle` with `text-body` and `data-bs-theme="light"` on the slide.
- On a plain surface the indicators take the text colour: `pg-carousel-ink` (`testimonial-carousel`).
- Over photos (`image-carousel`) the caption sits on `pg-shade-bottom` with `data-bs-theme="dark"` and `text-body` across the slide (Bootstrap's dark caption is black). `pg-carousel-photo` keeps the controls and indicators at Bootstrap's light-mode white in a dark design: black indicators on the scrim measured 1.2:1. Its `:not(.carousel-dark)` lets Carousel Options > Dark still switch them.

## What keeps this from coming back?
- `tests/designer_blocks_test.php` fails on a fixed colour class (list in `designer-block-theme-colours.md`) or a `light`/`dark` button variant in `createFromPalette()` or `makeCarouselSlide()`.
