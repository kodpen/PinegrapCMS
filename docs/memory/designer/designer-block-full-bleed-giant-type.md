---
name: designer-block-full-bleed-giant-type
description: Block rules pg-display-xl / pg-display-xxl (giant type), pg-bleed (edge to edge), pg-screen (one screen tall), pg-shade-tint, and the Hero: Full-Screen Media block (spotlight-media) in Marketing
---

## What do the four rules do?
- `pg-display-xl` / `pg-display-xxl` (`_SD_BLOCK_CSS`): heading type that follows the window. `clamp(2.75rem, 1.6rem + 5vw, 6.5rem)` and `clamp(3.25rem, 1.4rem + 8vw, 9rem)`: 44 / 52 px at 360 px, 90 / 125 px at 1280 px; xl reaches 6.5 rem at about 1,570 px, xxl 9 rem at about 1,520 px. `display-1` stops at 80 px.
- Their weight is the look's `--pg-display-weight` (300 in base.css, 900 in Bold), never a fixed weight; font and tracking are the look's heading tokens too. Line height 1 / 1.05, and `overflow-wrap: break-word` so a long Turkish word wraps at 360 px instead of pushing the page sideways.
- `pg-bleed`: the band reaches the window's sides also inside a container (`width: 100vw`, margins `calc(50% - 50vw)`, no radius). 100vw counts a classic desktop scrollbar, so the same entry holds `body:has(.pg-bleed) { overflow-x: clip; }`; `clip`, not `hidden`, keeps `position: sticky` working.
- `pg-screen`: `min-height: 100vh; min-height: 100svh;` (svh leaves room for a phone's address bar; vh for an older browser).

## What is Hero: Full-Screen Media?
- `spotlight-media` (PR #8685), needs `pg-screen`, `pg-bleed`, `pg-shade-bottom`, `pg-shade-tint`, `pg-display-xxl`; in `_noWrapCtypes`. Built like `hero-image-bg` ([[designer-block-theme-surfaces]]): the picture is the section's background (the Background panel's store), the overlay (`pg-shade-bottom pg-shade-tint`, `data-bs-theme="dark"`) is the only in-flow child, the text sits at the bottom, left aligned: eyebrow (`fs-5 fw-bold`), `h1.pg-display-xxl`, lead in `col-lg-7`, `btn-primary` + solid `btn-secondary`.

## Why is the overlay darker than hero-image-bg's?
- Giant type reaches the photo's upper half (the eyebrow at about 37% of a 1280 x 800 screen), where `pg-shade-bottom` alone is 0 to .45 black. Measured on a white photo: eyebrow 1.8:1, headline 2.0:1. `pg-shade-tint` (`background-color: rgba(0, 0, 0, .55)`, under the gradient) brings the worst case to 6.4:1 (eyebrow), 7.1:1 (headline), 9.2:1 (lead) in 5 looks, light and dark, 360/768/1280 px.
- The eyebrow is bold at 20 px, large text (3:1), so a long translation that pushes it to the top of the tint alone still passes.

## Why does it sit in Marketing, not Page Header?
- The plan puts it in Marketing ([[designer-bold-pages-steps]], B1): it is a full-screen product scene placed anywhere on a page, not only at the top. A `hero-` type would land in Page Header (groups match by prefix, first group wins), and `cta-` is about to become its own family file. So the type takes a new prefix, `spotlight-`, added to Marketing's match list; later full-screen scenes of the plan can use it too.

## How was it checked?
- `tests/designer_blocks_test.php`: the four rules (clamp in rem between 2.5 and 9, the display weight variable, no fixed weight, 50vw with the clip, svh with a vh fallback), the block's group, needs, classes, dark scrim and no-wrap entry.
