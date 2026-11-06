---
name: install-design-step
description: The installer's Site Design screen: live-picture tiles, the chosen template's block and tour, pickers set to the template's theme, 1440 px width, designer scripts loaded in order
---

## What does the design step show? (#7409)
- One lead line with the shared points; above the grid the chosen template's block: its home page large in `.pg-design-frame` (desktop scaled, or the phone switch), name, page count, description, highlights in a `<details>`, the look and palette pickers. A click on the picture or on "See all pages in a new tab" opens the tour (`install_action=design_tour`, `pg_design_tour_html()`) with `window.open`, so its continue button can close the tab.
- The grid: one tile per template, a live picture of its home page (`pg_design_live_picture()`, `pg_live_picture.min.js`) in the template's own look and palette, the drawing as fallback; Recommended on the first; Standard installation a slim row after it. The radios and `data-design`/`data-kinds` stay; the tiles' `local` follows the chosen starter site's language (`PGLivePicture.set()`).
- Picking a template (by hand or the one offered first) sets the pickers to its own look and palette (`pg_design_template_theme()`; none named: the default look, `pg_design_default_look()`, on the tile too); both stay changeable. Before the form is sent the server prints the selected template's theme as checked.
- Width: `main#content.pg-install` 1440 px; from 1280 px, while step 3 is open (`.pg-wide-step` set by `pg_install_set_step()`), the side column is hidden and the step takes its room: tiles 2 a row, 3 from 768, 4 from 1280, 5 from 1536.

## Why are the designer's script files loaded one by one?
- In development mode (or with the .min.js missing) `pg_js_bundle_urls('assets/js/style_designer.min.js')` gives several sources. They were glued into one address, the script never loaded and a template install stopped with "The design template could not be prepared in this browser". The step now gets the list as JSON and loads each after the one before (#7409).

Related: docs/memory/platform/design-template-install-at-setup.md, docs/memory/designer/design-live-pictures.md
