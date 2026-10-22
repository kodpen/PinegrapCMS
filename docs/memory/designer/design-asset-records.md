---
name: design-asset-records
description: The design's CSS/JS asset rows (style_custom_css / style_custom_js JSON): module scripts, the per-page `pages` list and its tab:/key: references, where they are resolved, the Bootstrap Icons sheet.
---

## What does a JS row's `module` flag do?
- `module: true` loads the file as `<script type="module">` (inline code gets a block of its own). Four places read it: `get_page_content.php`, the canvas (`applyAssetsToIframe()`), the HTML tree, the preview.
- Every place that copies rows carries the field: `parseFiles()` (the assets panel's reader), `window.sdImportAssets()` (import) and the panel's Duplicate. A copy without it turns a module into a classic script.

## Which pages load a row?
- `pages` lists the pages a CSS/JS row is limited to: page ids, `tab:<key>` for a tab not saved yet, `key:<template page key>` inside a template made from a design. Absent or empty = every page of the design.
- Filters: server `pg_design_asset_on_page($entry, $page_id)`, editor `_sdAssetsInPlay()` / `_sdAssetOnPage()`.
- A path that creates or changes page ids rewrites the references with `pg_designer_resolve_asset_page_refs($json, $prefix, $ids)`: the editor's save (`tab:`), a design installed from a template (`key:`), a duplicated design (old id → copy's id). In the editor the same through `window.sdAssetsRemapPages()`. A reference that matches nothing stays as it is.
- The importer marks a file only some pages use with `page_names`: the in-editor import turns them into `tab:<key>` (`_pgImportApplyPages()`), the File Manager ZIP → design path into ids (`pg_di_asset_pages_by_id()`).
- A page detached into its own design keeps only the rows it loaded, unrestricted (`pg_design_assets_for_page()`).

## Where is the Bootstrap Icons address?
- One place: `pg_design_icons_url()` (1.13.1), passed to the editor as `sdDesign.iconsUrl` (`_sdIconsUrl()`). It stays the version the icon picker lists (its copy under `assets/fonts/bootstrap-icons/`), or newer icons draw as empty boxes.
- A design that links its own icons sheet (an import keeps the project's) does not get the stock one as well (`pg_design_is_icons_url()`).
