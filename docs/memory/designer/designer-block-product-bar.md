---
name: designer-block-product-bar
description: The Product Bar blocks (spotlight-bar, spotlight-bar-dark) in Marketing: a sticky bar under a sticky site header with the product name, section links and one button; offset 56, light and dark
---

## What does Product Bar draw?
- `spotlight-bar` (palette "Product Bar") and `spotlight-bar-dark` ("Product Bar: Dark"), one builder in `createFromPalette()` (PR #8804). A `section.bg-body.text-body.border-bottom.py-2` with the Sticky interaction, inside a container one flex line: the product name (`h2` drawn `fs-5 fw-semibold text-truncate me-auto`), a `nav` (`aria-label` "Product sections", `d-none d-md-block`) with a `ul.nav.small` of anchor links (`#overview`, `#features`, `#specs`, `nav-link px-2 link-body-emphasis`), and one `btn btn-primary btn-sm` link.
- The dark entry adds `data-bs-theme="dark"` to the section; both follow the palette ([[designer-block-theme-colours]]). No block CSS rule: the bar is opaque `bg-body`, unlike CTA: Sticky Bar's blurred `pg-sticky-bar` ([[designer-blocks-cta]]), which lives in a family file a core block cannot name.

## Why offset 56?
- `_fx {effect: 'sticky', offset: 56}`: 56 px is a Bootstrap navbar's height (a `navbar` with brand and `py-2`, as the templates' `sticky-top` Site Header), so the bar stops right under a sticky header instead of sliding under it. When the site header scrolls away, set the offset to 0 in the Interactions panel.
- Sticky holds only inside the element the bar sits in: place it at the top of a wrapper that holds the product's sections, and give those sections the ids the links name.

## Why are the links hidden on a phone?
- At 360 px name, three links and a button do not fit one line; the phone keeps the name (truncated) and the button. The sections stay reachable by scrolling.

## Why the spotlight- prefix?
- The palette buckets blocks by prefix; `spotlight-` is already in Marketing's match list (from Hero: Full-Screen Media, [[designer-block-full-bleed-giant-type]]), and `product-` would risk the catalog's names.

## How is it tested?
- `tests/designer_blocks_test.php` `test_designer_blocks_product_bar`: both entries in the palette, Marketing, no-wrap list, band and link classes, aria-label, the dark attribute only on the dark entry, the sticky offset 56 kept by `pg_fx_normalize()`.
