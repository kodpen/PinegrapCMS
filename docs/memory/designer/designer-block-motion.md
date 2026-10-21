---
name: designer-block-motion
description: Motion in designer blocks without JS: the shared pg-marquee contract (loop, pause by hover, focus and a CSS-only button, reduced motion, RTL), no rotator autoplay, no countdowns.
---

## How does a block move without JavaScript?
- Only CSS from `_SD_BLOCK_CSS` and Bootstrap's `data-bs-*`. The moving bands (`news-ticker`, `marquee-words`, `marquee-logos`) share one entry, `'pg-marquee'`: a `@keyframes` translate of half the track's width.

## What markup does the marquee rule expect?
- `pg-marquee` on the band → `pg-marquee-viewport` (clips; `role="region"`, `aria-label`, `tabindex="0"`) → `pg-marquee-track` → two identical `pg-marquee-group` children; the second also `pg-marquee-copy`, `aria-hidden="true"`, its links `tabindex="-1"`, its images `alt=""`. A group's end padding equals its gap, so the jump at -50% is invisible; it must be wider than the screen.
- Group layout comes from the rule (`.pg-marquee-group { display: flex }`), **not** from `d-flex`: Bootstrap utilities are `!important`, so a `d-flex` copy could not be hidden under reduced motion. Same reason: no `overflow-hidden` on the viewport.
- Pause control: a wrapper `pg-marquee-toggle` holding `input.btn-check.pg-marquee-pause` + its `label.btn` (`bi-pause-fill`, visually hidden text). The rule pauses the track on `:hover`, `:focus-within` and `.pg-marquee:has(.pg-marquee-pause:checked)`; the label's focus ring and checked state are drawn with `currentColor`, so they show on any surface, `text-bg-primary` included.
- `prefers-reduced-motion: reduce`: no animation, the copy group and the toggle are hidden, the viewport scrolls sideways instead. `[dir="rtl"]` runs a mirrored keyframe.
- In the canvas every node sits in a `.sd-wrap`: block rules use descendant selectors only (no `>`, `+`, `~` between nodes); hover pauses the band while text is edited.

## Why does the headline rotator not autoplay?
- Bootstrap 5.3.8's carousel pauses only on mouse hover: it keeps cycling with keyboard focus inside (focus falls to `body` when the slide hides) and under reduced motion, and the data API has no pause button. That fails WCAG 2.2.2, so `news-ticker-rotator` moves with its previous/next buttons only. An operator who wants autoplay adds `data-bs-ride="carousel"` in Attributes. `image-carousel` and `testimonial-carousel` (older) still autoplay.

## Why is there no countdown or live value?
- A countdown needs JavaScript; a block shows a fixed date in `<time datetime>` the operator edits (`coming-soon-launch`). Weather and market values in the news blocks are sample values too: a live feed would be a system widget or a request-time component (like the language switcher placeholder), not a palette block.

## Which other motion rules exist?
- `'pg-pulse-still'`: `spinner-grow` keeps animating under reduced motion in Bootstrap (only slower) and rests at opacity 0; the rule stops it and keeps it visible (`maintenance-dark`, `news-live-blog`).
- `'pg-tilt'` (polaroid cards): rotation from 768 px up, straightens on hover, no transition under reduced motion.
