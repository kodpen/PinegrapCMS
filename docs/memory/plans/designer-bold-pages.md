---
name: designer-bold-pages
description: Plan: what the Visual Page Editor needs for bold product pages (scroll scenes, edge-to-edge media, big type, dark and light chapters), from an inspection of the designer
---

## What is it?
- The features the Visual Page Editor lacks for bold product pages with motion, found by inspecting the designer and the shipped templates on 2026-11-11, cut into steps any session can build: [[designer-bold-pages-steps]].
- Started by the owner's order of 2026-11-11 (the template line, recorded by PR #8641 in workflow's design-template-owner-line file). An ordinary feature plan, not a template plan: any coordinator's workers take its free steps. Claim as `[plan:designer-bold-pages] <step> — <topic>`.

## What can a page do today?
- Entrance on load or view, hover, click, `sticky` and `parallax` (a move only), from `props._fx` ([[designer-interactions]]). Nothing is tied to the scroll position beyond parallax: no scale or fade while scrolling, no pinned scene with steps.
- Edge to edge: a photo hero (`hero-image-bg`, `pg-hero-tall`). No video item in the palette: a `<video>` exists only as a raw element (code view), and no block plays one.
- Horizontal gallery with snap: `scroller-cards` (snap and previous/next buttons, [[designer-scroller-controls]]); cards only, no wide media slides.
- Colour switch: `launch-colours` (radios and `:has()`, [[designer-blocks-launch]]), but only in the Product Launch family.
- Sticky bar: any node with `sticky`; no ready product bar. Numbers that count: none (figures are static text).
- Very large type: `display-1` stops at 80 px; templates' headings measured 56 to 64 px. A bigger size is a fixed pixel value in the Style panel, not one that follows the window.
- Dark and light chapters: a band can be dark (`data-bs-theme="dark"`, [[designer-block-theme-colours]]); the change is a hard cut, and a design cannot be dark as a whole: only a visitor's theme switcher sets `<html>` dark.

## What do looks and palettes give?
- 12 looks (only `--pg-*` tokens: corners, shadows, lines, type) and 19 palettes (a primary and a secondary; surfaces stay Bootstrap's white and greys). None is made for a dark showroom or for very large, light display type ([[design-theme-look-and-palette]]).
- Templates pick few: `minimal` 10, `modern-soft` 9, `bold` 8 of the theme lines in the template files.

## Where does the sameness come from?
- Beyond [[design-template-own-look]]: every home measured on dev (appstudio, dealership, hotel, restaurant, even launch) is the same sequence of `py-5` container bands, alternating with `bg-body-tertiary`: hero, figures strip (`py-4 border-top border-bottom`), lists, testimonials, news, a call-to-action band, a newsletter.
- The palette has almost no full-height or full-bleed block and no giant type class, so a page built from it stays a stack of container bands.
- The call-to-action band is `bg-primary text-white` in 39 template folders, a pair the block rules forbid (amber palettes put dark text on primary).
- Motion: about 20 templates use `_fx`, mostly view entrances; none moves with the scroll.

## Which questions wait for the owner?
- B9: may a muted, looping hero video play by itself, with a pause button and stopped under reduced motion?
- B11: a design-wide colour mode (light, dark, follow the system) in the Theme pane: a new `style` column.
