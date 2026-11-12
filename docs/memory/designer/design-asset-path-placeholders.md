---
name: design-asset-path-placeholders
description: {path} and {software_directory} in a design: filled in a linked CSS/JS row before the page's address check and on the canvas; what is saved keeps them
module: designer
---

## What did it fix?
- A design's linked stylesheet or script row written `{path}name` (as the HTML importer keeps a classic style's head files, `classic-style-import.md`) printed nothing on the site: `get_page_content.php` checked the address (an http(s) URL or root-relative) before the page filled the placeholders, and `{path}name` is neither. On the canvas the browser asked for `%7Bpath%7Dname`. Fixed in #8732 (LG2, `legacy-overview.md`), for every design, not only moved ones.

## Where does the site fill them?
- `pg_design_asset_address($content, $path, $software_directory)` (`includes/fn/designer.php`) gives the address an `external-css` / `external-js` row loads: it fills both placeholders without case (`pg_designer_resolve_output_placeholders()`), then keeps an http(s) URL or a root-relative path, else ''. The CSS and JS rows of `get_page_content.php` pass the raw `PATH` and `SOFTWARE_DIRECTORY`; the address is escaped once when printed.
- Still refused: a relative address (`style.css`) and `{software_directory}/x.js` without `{path}` (it fills to `pinegrap/x.js`, relative).
- Inline CSS and JS rows and the page's markup needed nothing: `get_page_content.php` fills `{path}` (`OUTPUT_PATH`) and `{software_directory}` on the assembled page last.

## Where does the canvas fill them?
- `_sdPathFill(s, path, dir)` (`style_designer.src.js`) is the twin of the page's fill: case-insensitive, the value copied literally (`$` and `\` safe), null to ''. `_sdSitePath(s)` calls it with `window.OUTPUT_PATH` (else `/`) and the `software_directory` global (else `pinegrap`).
- Asset links: `_sdAssetHref()` fills the row; a managed file's stamp is still looked up by the row's own text. `applyAssetsToIframe()` fills the user styles, the inline CSS (`url({path}bg.jpg)`) and the inline JS.
- A node: `_propsWithBindings()` returns `_sdPathProps()`, which fills the string props `src`, `href`, `poster` and `html` (Custom HTML) in a copy; nothing to fill returns the same object. `_sdApplyNodeAttrs()` fills custom attribute values and the `style` attribute (`background: url({path}…)`). `_sdBustImageCache()` fills the address before it compares it with the drawn image.

## What keeps the placeholders?
- What is saved: the asset rows, the tree's props and attributes; `tests/classic_style_design_test.php` stays green.
- A node's `text` (and the other rich-text props): the inline edit reads the node's HTML back into the prop, so a filled address would be saved. A `{path}` link inside rich text stays unfilled on the canvas.
- The code view (`_sdPageFrameRows()`) shows the rows as written.

## What is not covered?
- The editor's Preview (`_sdPreviewDocHtml()`) and the template preview (`_pg_tpl_preview_assets()` in `includes/fn/designer.php`) were left as they were.

## Tests?
- `tests/design_asset_path_placeholders_test.php`: the page's address (filled, kept, refused, a literal `$1\b` path, both rows wired) and, under Node, the canvas twin (`_sdPathFill`, `_sdSitePath`, `_sdPathProps`, `_sdAssetHref`) and where it is wired; the Node tests skip without Node.
