---
name: design-template-theme-colours
description: Design template files carry no fixed colour class (PR #8976): the test that fails on one, what each class became (primary band, dark band, ink buttons, light chips), the Bike Shop's list
---

## What may a template file not carry?
- The fixed colour classes of [[designer-block-theme-colours]]: `bg-white`, `bg-light`, `bg-dark`, `bg-black`, `text-white`, `text-white-50`, `text-dark`, `text-black`, `text-black-50`, `text-muted`, `text-light`, `text-bg-light`, `text-bg-dark`, `btn-light`, `btn-dark`, `btn-outline-light`, `btn-outline-dark`, `link-light`, `link-dark`, `navbar-dark`, `navbar-light`, `bg-primary`, `bg-secondary`.
- Why: such a class keeps its colour whatever palette or colour mode the design wears. `bg-primary text-white` was white on amber; `btn-dark` and `text-bg-dark` vanish in a dark design; `text-bg-light` stays a light patch there.
- `tests/design_template_theme_colours_test.php` fails on one. It reads every string literal of `includes/design_templates/*.php` and `*/*.php` (a class in a builder nobody calls is found too), then walks the trees of each main file for a class joined at run time (`'bg-' . $tone`) and for a component's `variant` `light` or `dark` (a Button or wishlist heart draws `btn-light` / `btn-dark` from it).

## What did each class become?
- Primary band or box: `text-bg-primary`, never `bg-primary` alone (a `.badge` on it is white). Its buttons: `btn btn-secondary` and `btn btn-link text-reset` (were `btn-light`, `btn-outline-light`); a badge on it `text-bg-secondary`; no `opacity-*` on its text and no `data-bs-theme` on it.
- Dark band or card: `bg-body text-body` with `_attrs` `data-bs-theme="dark"` (was `bg-dark text-white`, `text-bg-dark`); its buttons `btn-primary` and a solid `btn-secondary`. A video frame carries the attribute itself, as does a play icon over a photo (`text-body-emphasis`).
- Ink buttons and badges (`btn-dark`, `btn-outline-dark`, `text-bg-dark`): the primary classes where the template's palette has an ink primary (Architecture, `graphite`), the secondary classes where ink stood beside a coloured primary (Boutique, `burgundy`; Playground, `fuchsia`). An outline button on `bg-body-tertiary` became a solid `btn-secondary` ([[design-template-text-contrast]]). App Studio's store buttons are `btn-primary`.
- Light chips (`text-bg-light`, `btn-light`): `bg-body text-body-emphasis` for a badge (with `border` where it had one), `btn-secondary` for a button. `link-dark` is `link-body-emphasis`; `link-light` in a dark strip is `text-reset`.
- A builder that draws a tone by name writes `text-bg-<tone>`, and its light tone is `bg-body text-body-emphasis` (Playground's `$sticker`, its progress bar).
- 48 templates got a patch version with it; the tests that pinned a version now ask for "at least".

## Why is the Bike Shop on the test's list?
- `_test_tpl_colours_still_fixed()` names the classes a template still uses: only `pinegrap-bikeshop` (`bg-dark`, `btn-dark`, `btn-outline-dark`, `link-light`, `text-bg-dark`, `text-white`, 35 places). Its folder has no main file, so the installer does not offer it and its palette (graphite and signal orange) is not settled: which colour is primary decides between the two ink recipes above.
- A listed template may have no main file: the step that assembles the Bike Shop replaces those classes and deletes its entry, or the test fails. The list only shrinks (a listed class no longer used fails too); nothing is added.

## What is not covered?
- The block family files (`assets/js/style_designer_blocks_<family>.js`): `tests/designer_blocks_test.php` holds only the core's `createFromPalette()` and `makeCarouselSlide()` to the list. The Photo family still writes `badge bg-primary` for its "Picked" badge (white text on any primary); the Photo template's own node is `badge text-bg-primary`.
- Not looked at in a browser: the classes follow the measured recipes of [[designer-block-theme-surfaces]].
