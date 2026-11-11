---
name: designer-blocks-cta
description: General CTA blocks in a family file (cta- prefix, core Marketing group): the sticky bar; where it sticks, its rule, who uses it
---

## Where does it live?
- `assets/js/style_designer_blocks_cta.js`, a general family ([[designer-block-family-files]]): prefix `cta-` and no group, so its blocks join the core Marketing group beside CTA: Banner, Inline and Gradient. Added with step `dealership-1` (PR #8640); the App Studio and other own-look steps reuse it rather than adding their own bar.

## What does CTA: Sticky Bar draw?
- `cta-sticky`: `section.pg-sticky-bar border-bottom py-2` with the Sticky interaction (`_fx {effect: 'sticky'}`, pg_fx's `position: sticky; top: 0`). Inside a container: `h2` title drawn as `h6` (`me-auto`), a small line shown from md up, two small buttons (btn-primary, btn-outline-secondary).
- Sticky holds only inside the element the bar sits in: put it at the top of a wrapper that holds the rest of the page. The Car Dealership home wraps everything after the first panel in a "Showroom" div for that.
- A header that is sticky too would cover it; the bar has no offset of its own (the interaction's offset field gives one).

## What does its rule do?
- `pg-sticky-bar`: the page colour (`var(--bs-body-bg)`); where the browser can blur behind (`@supports backdrop-filter`), `rgba(var(--bs-body-bg-rgb), .86)` with a blur, so the page shows faintly through. No fixed colour, so it follows the palette in both themes.

## How is it tested and placed?
- `tests/designer_blocks_dealership_test.php` runs the file: no group, one block, a section with one `h2`, one interaction kept by the normalizer, the rule read by `_pg_designer_block_css_parse()`.
- Templates place it with their own words and links (the dealership's `$sticky_bar()` in `parts.php`) and name `cta-sticky` in `pg_design_template_user_styles()`.

Related: docs/memory/designer/designer-blocks-general.md, docs/memory/designer/designer-blocks-dealership.md
