---
name: designer-meta-tags
description: Design-wide and per-page custom meta tags of the visual designer (style.style_meta_tags, page.page_meta_tags, 11.0.3): storage, precedence over generated tags, who edits them.
---

## Why did the designer's meta tags never reach the site?
- Settings > Design > "Meta Tags" edited `pageSettings.meta`, a JS variable with three defaults (viewport, description, robots). Save never sent it, no column held it, so every load started from the defaults again. The HTML tree, code view and blob preview drew the variable, which made the tags look saved.
- Fixed in 2026.11.0 (step 11.0.3, PR #555): the list is stored and drawn by `get_page_content.php`. `pageSettings` keeps only `jquery`.

## Where are the tags stored?
- `style.style_meta_tags` (the design's, on every page of the design) and `page.page_meta_tags` (the page's own), both TEXT holding a JSON list of `{"name","content"}`; `''`/NULL is none. Readiness: `pg_meta_tags_ready()`.
- One shape for both, from `includes/fn/meta_tags.php`: `pg_meta_tags_normalize()` keeps rows with a valid name (`^[A-Za-z][A-Za-z0-9._:-]*$`, max 100), not reserved, and non-empty one-line content (max 1000); at most 30 rows; `pg_meta_tags_encode()` keeps the JSON under 60000 bytes. Tests: `tests/meta_tags_test.php`.
- Reserved names (`viewport`, `description`, `robots`, `charset`) are refused: other settings write them (viewport in every designer head, the page/site description, the noindex switches the site map also follows).

## How are they written in the head?
- At the `<meta_tags>` step, after marker expansion, so content never reaches the `<!--pg-…-->` eval path; values go through `h()`.
- `pg_meta_tags_merge()`: a page tag replaces every design tag of the same name (case-insensitive). Same names inside one list are kept (several `og:image`).
- `pg_meta_tags_drop_overridden()`: a custom tag replaces the generated tag of the same name and its structured properties (custom `og:image` drops `og:image:width/height/alt`). Custom `og:description` turns the combined description tag into a plain `name="description"`. `google-site-verification` is never dropped (several owners).
- Attribute: `property` for `og: fb: article: book: profile: music: video: product:`, `name` otherwise (`pg_meta_tags_attribute()`).
- Not in an e-mail body. Without the placeholder the custom tags go before `</head>`, like robots.
- The meta block and the `<title>` are passed to `preg_replace()` through `addcslashes(…, '\\$')`; a title or description with `$1` used to lose it.

## Who edits them, and where?
- Design list: Settings > Design, full designers only (`pg_designer_meta_tags_group('design')`, hidden input `style_meta_tags`, `save_system_style()` writes it only when the key is sent). Counted in `_pgStyleJSON()`.
- Page list: Settings > Page, every role that may edit the page title (escaped content, not markup). Page field `page_meta_tags` in `_PG_PAGE_FIELDS`; saved via `$c_extra` in `pg_designer_save_page()` only when sent, so it joins the "carried" comparison.
- JS: `_sdMetaListBind()` / `_sdMetaListRender()` keep rows as typed (incomplete too) in the hidden input; the server normalises. Reserved names get `is-invalid`.
- Copied by duplicate design, duplicate page, page detach; page object literals carry `page_meta_tags` (a missing key would send '' and wipe the stored list).

## What is not covered yet?
- Design templates (export/install) do not carry either list.
- No translation per site language; the external API and the legacy `edit_page.php` do not expose `page_meta_tags`.
