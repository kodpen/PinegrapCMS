---
name: designer-block-theme-colours
description: Which classes keep a ready-made designer block in step with the design's look and colour palette, which ones break it, and the contrast traps measured on the ready-made blocks.
---

## Why does a block need theme-driven classes?
- A design wears a **look** (`--pg-*` tokens: radius, shadow, type) and a **colour palette** (`--bs-primary`, `--pg-on-primary`, `--pg-primary-fg`), bridged into Bootstrap by `assets/css/themes/bridge.css` and `base.css` (see `docs/memory/designer/designer-import-validation-theme.md` and the theme section of the designer skill). A block restyles with them only through classes that read those variables.

## Which classes follow the palette and the look?
- Colour: `text-bg-primary` / `text-bg-secondary` (text colour is `--pg-on-primary`), `bg-primary-subtle text-primary-emphasis`, `text-primary`, `link-primary`, `btn-primary`, `btn-outline-primary`, `btn-outline-secondary`, `bg-body`, `bg-body-secondary`, `bg-body-tertiary`, `text-body`, `text-body-secondary`, `text-body-emphasis`, `text-reset`, `border`, `border-primary`.
- Shape and depth: `rounded*`, `shadow-sm|shadow|shadow-lg`, `card`, `badge`, `.blockquote`, `display-*`, `lead` (base.css maps them to the look's tokens). Never set a font family.
- **Never**: `bg-white`, `bg-light`, `bg-dark`, `text-white`, `text-white-50`, `text-light`, `text-dark`, `text-muted`, `text-bg-light`, `text-bg-dark`, `btn-light`, `btn-dark`, `btn-outline-light`, `link-light`, `navbar-dark`, `bg-primary text-white` (write `text-bg-primary`; an amber palette has dark text on primary), hex colours.
- Every palette block and Components-tab item follows this, and `tests/designer_blocks_test.php` fails on a fixed colour class in `createFromPalette()` or `makeCarouselSlide()`. Recipes for dark navbars, photo heroes, buttons on tinted surfaces and carousels: `docs/memory/designer/designer-block-theme-surfaces.md`.

## How is a dark band or text on a photo written?
- Dark band: `_attrs` `data-bs-theme="dark"` **and** `bg-body text-body`; palettes redefine their dark colours under `[data-bs-theme=dark]`, and without `text-body` the text colour is inherited from the light page.
- Text on a photo: an overlay with the shared `pg-shade-bottom` scrim (`_SD_BLOCK_CSS`) plus `data-bs-theme="dark"` on the overlay, so `text-body` and links turn light whatever the photo is.
- Links and buttons on a `text-bg-primary` surface inherit the on-primary colour with `text-reset`; a focus ring there uses `currentColor`.

## Which contrast traps were measured (WCAG 1.4.3, 5 looks x 5 palettes, light and dark)?
- The palette builder makes `--pg-primary-fg` readable against `bg-body` only: small `text-primary` / `link-primary` / `btn-outline-primary` text on `bg-body-tertiary` or `bg-body-secondary` fails in dark mode and with light palettes (amber). Put such text on `bg-body`, or use `text-body-emphasis`.
- Status text: `text-success` / `text-danger` fail on tinted surfaces; use `text-success-emphasis` / `text-danger-emphasis`, always with an icon or sign as well (colour is never the only cue).
- `.blockquote-footer` is a literal grey in Bootstrap 5.3.8 and does not follow dark mode: add `text-body-secondary`.
- `btn-outline-primary` on a dark band without a palette is 3.4:1; use `btn-primary` there.
- `.badge` is `white-space: nowrap`: a pill longer than two words needs `text-wrap`, or a Turkish translation pushes the page sideways at 360 px. A joined row (`pagination`) is 373 px in looks with wide buttons (bold): `flex-wrap`, as the site's own pagers.
- A block may have no palette accent when it is photos, logos, plain lists or Bootstrap status colours (`image-grid`, `logos-*`, `alert-*`, tables): it still follows the look and dark mode. A navbar shows only its brand at 360/768 px, so the brand is `text-primary`.
- The editor's audit measures the same thing on the canvas: `docs/memory/designer/designer-audit-contrast.md`.
