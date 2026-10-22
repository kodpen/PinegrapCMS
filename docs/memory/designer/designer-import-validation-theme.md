---
name: designer-import-validation-theme
description: Designer HTML/ZIP import rules, SEO validation badges (client twin), theme as files row, canvas direct-child CSS rules, image placeholder and loop band.
---

## How does HTML / ZIP import work?
- File Manager ZIP -> design: `import_design_zip.php` -> `pg_designer_create_design_from_import()` (style + pages into the `Import/<project>` folder, no sentinel written) -> editor. In-editor import also puts pages in `folder_id` and adds an option to the folder select.
- Core `includes/designer_import.php`, endpoint `designer_import.php` (multipart; `api.php` reads JSON only). Pages are unsaved tabs; files are written at once under `Import/<project>/...`.
- A ZIP is measured before it is read (`pg_zip_import_open()`); every entry is read by its own index through `pg_zip_entry_contents()`, a page at most 8 MB: docs/memory/platform/zip-entry-declared-size.md.
- Inline `<svg>` is taken raw BEFORE parsing (`pg_di_lift_svgs()`; libxml nests `<path/>`): a `bi-*` class -> icon node, otherwise an `svg` content node holding the drawing (no file, never custom_html; cleaned by `pg_designer_svg_markup()` when drawn): `html-import-and-paste.md`.
- `srcset`/`sizes` are not carried to the image (it would override src).
- An `<img>` without an alt attribute gets no `alt` prop (the audit asks for one); `alt=""` is kept as the decorative statement.
- Page names are fixed before translation (`$ctx->page_names`); cross-page links resolve from there.

## How is validation done?
- Client `_validationRules` (per node; the `seo` field = server code) -> canvas badge `applyIssueBadges()`.
- `api.php designer/seo_check` -> `pg_seo_evaluate_meta` + `pg_seo_analyze_html('fragment')` + `pg_seo_compose`; writes nothing. Save runs `pg_seo_analyze_record` + `pg_seo_recalculate`.
- When adding a node-level SEO check, write its client twin too: a server finding cannot be mapped to a node.
- Text contrast is measured on the canvas, not from the tree: docs/memory/designer/designer-audit-contrast.md.
- Empty link/button, image alt, form submit and empty heading rules (what counts as a name, an alt decision, a submit, a heading): docs/memory/designer/designer-audit-tree-rules.md.

## How is the theme stored?
- A theme is a `files` row (`design=1`). The "theme" row in the Styles list does not enter `cssFiles`; it is read from `select[name=theme_id]` (`_sdActiveTheme`).
- "Save as theme" -> `api.php designer/theme_save`.

## Canvas CSS rules
- Direct-child rules need `X > .sd-wrap { display:contents }` plus re-applying the rule to the real child, for: `.row`, `.d-grid`, ALL `.d-*-flex`, `.ratio` (`.ratio > .sd-wrap > *` is absolutely positioned).
- Adding a new Bootstrap container: see docs/memory/designer/bootstrap-direct-child-canvas.md. `content/span` / `text` wrapper is `display:inline`.
- Image binding hint (2026.1.28): a bound `<img>` with empty src shows the token name in an SVG placeholder (`[item_image]`).
- `sd-loop-area` carries a real `<div class="sd-loop-band">` (was `::before`) with `bi-arrow-repeat` + "Loop alanı — 1 kayıt gösteriliyor (runtime'da her kayıt için tekrarlanır)". Canvas only; frontend unaffected.

Related: docs/memory/designer/multi-page-designer-editor.md
