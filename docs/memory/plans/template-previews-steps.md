---
name: template-previews-steps
description: Steps TP1-TP3 of plans/template-previews.md: the live picture and tour, the installer's design step, the panel's preview address; claim as [plan:template-previews] TP<n>
---

## How are the steps taken?
- Claim with a draft `[plan:template-previews] TP<n> — <topic>`; S under 400 product lines, M about 1,000. Texts through `tr_add.php`; `php tools/build_js.php` and `build_css.php` after a bundled script or `backend.src.css`.
- No unit test reaches a frame: a screen step is checked in a browser at 1280, 1920 and 390 px against `template-previews-visuals.md`, as its PR says. TP4-TP5: `-steps-2.md`.

## Which steps are there?

- TP1 (M) The live picture and the tour page. done (#7258).
  - New `includes/fn/design_previews.php` (module `design_previews`): `pg_design_live_picture($src, $opts)` (the box, an optional fallback), `pg_design_preview_address()` (the installer's or the panel's address of a template page or tour; look and palette only from the known lists) and `pg_design_tour_html()` (bar: name and theme, a page list with an E-mails group, previous and next, "n / total", desktop or phone, the continue button; the frame follows `pgDesignPreview`).
  - `assets/js/pg_live_picture.js`, bundle `pg_live_picture.min.js` (`window.PGLivePicture`): loads on view (IntersectionObserver), scales the 1280×800 frame to the box (ResizeObserver), `sandbox=""`, `tabindex="-1"`, `aria-hidden`, a quiet wait state, the fallback after 12 s, `set(el, src)` to swap.
  - `.pg-live-picture` in `backend.src.css` (16:10, both themes). Tests `tests/design_previews_test.php`; memory `designer/design-live-pictures.md`.

- TP2 (M) The installer's design step and width. done (#7409).
  - `install/index.php`: one lead line with the three shared points; the chosen template's block (its home page in `.pg-design-frame`, a click on it or on "Tüm sayfaları yeni sekmede gör" opens the tour; name, pages, description, highlights in a `<details>`, look, palette, desktop or phone); a grid of tiles with live pictures (the radios and data attributes as now, Önerilen on the first, the chosen one checked); Standard installation as a slim row at the end. The page and e-mail rows go.
  - Picking a template sets the look and palette to its own (`pg_design_template_theme()`), both still changeable.
  - `install_action=design_tour` beside `design_preview`, printing `pg_design_tour_html()`; "Bu şablonla devam et" closes the tab (opened with `window.open`), else says it can be closed.
  - Width: `main#content.pg-install` 1440 px; from 1280 px, while step 3 is open, the side column is hidden and the step takes its place (block in two columns, four tiles a row, five from 1536); phones two a row.
  - The designer script's addresses loaded in order (the bug, `template-previews-code.md`).
  - Memory: `platform/design-template-install-at-setup.md` updated.

- TP3 (M) The panel's preview address. done (#7412).
  - New root file `design_preview.php`: GET only, `noindex`, `X-Frame-Options: SAMEORIGIN`, private cache 5 min. `PG_ANONYMOUS_RENDER` before `init.php`; the operator read from the session as `audit_run.php` does, admitted when `pg_designer_access()` is not none.
  - `?template=&page=&look=&palette=`: `pg_design_template_preview_html()` (custom templates too), its links back to this address; nothing to draw gives a plain placeholder page. `&tour=1`: the tour, whose button "Bu Şablonu Kullan" opens `add_system_style.php?start=template` with the look and palette.
  - `?page_id=`: a visual design page the operator may see (`pg_designer_page_access()` not hidden; drafts too) drawn through `pg_background_render()` as a first-time visitor: scripts removed, no consent banner, chat bubble or staff bar, no visit counted, the page cache neither read nor written; an ETag from the page's and the design's last change.
  - Tests on the pure helpers; memory `designer/design-preview-address.md`.
