---
name: legacy-editable-layouts
description: Plan legacy-editable, layouts: a System region becomes its widget in the run; a stock layout by carried settings, a layout file by a piece map keeping the theme's markup; what stays code
---

## Why are layouts the hard part?
- 26 page types draw their System region through `render_layout()`: the stock template `includes/templates/<type>.php`, or the page's own PHP file `data/layouts/<page id>.php` (`designer/layout-widget-offer.md`). A view template over a fixed data contract, no queries.
- Starting site: 88 of 180 pages have a file, 106 distinct texts over both sites, each differing from its template (`designer/legacy-layouts-census.md`). Form list view 52 pages (3 texts), custom form 26 (21), order form 20 (11), photo gallery 12, catalog 10, the rest 2 to 6 each.
- Today a file page gets the stock widget by a button and loses the theme's markup; only custom form converts its file into design elements (`designer/layout-form-converter.md`).

## What happens to a page that draws the stock template?
- A page type a widget stands in for (`pg_classic_widget_kinds()`), no file (`layout_modified = 0`), and a hosted site's every page: the run's new step `types` puts the widget in the System region's place by a server twin of the editor's "Convert to design elements" (`_sdClassicConvert()`), settings carried by `pg_layout_widget_seed()`.
- The seed first learns what it leaves out today: columns, image sizes, featured and new items, the advanced search, sort 3, header and footer. A setting the widget cannot hold keeps the page a System region; the dry run names the setting.

## What happens to a page with its own layout file?
- `pg_layout_convert($type, $code, $ctx)` in `includes/fn/layout_convert.php`: the custom form converter made general. Parts by `pg_di_php_parts()`, markup by the HTML converter, so the theme's wrappers and classes stay.
- A piece map a type, `pg_layout_piece_map($type)`: each piece of the type's data contract and what it becomes in the widget's tree: a value a token of the dictionary (`designer/token-dictionary.md`), a loop the widget's list with its row template, a condition the widget's own state where it has one, the form tags and messages nothing (the widget draws them). No second name for a system field; `php tools/check_bindings.php` stays green.
- It answers `ok`, `tree`, `dropped` (the piece, its line, the reason) as the form converter does. A `<script>` or `<style>` of the file stays a raw block.
- In a run a page converts only when nothing is dropped. Otherwise it stays a System region, drawn true on the canvas; its Region panel lists the dropped pieces and "Convert to design elements" converts after the designer's confirm (`legacy-editable-screens.md`).
- The file, the page type and its type row stay; undo is the editor's undo, a page revision or Back to classic.

## In which order are the types mapped?
- By pages on the starting sites, one family a step: form list view with form item view; order form (the custom form's field handling); photo gallery, catalog, catalog detail; shopping cart, express order, billing information, shipping address and arrival, shipping method, order preview, view order; login, registration and membership entrance, the three password pages, my account, profile, address book, e-mail preferences, search results.
- Each step's test runs every starting-site text of its types through the converter and holds the dropped list it expects; its sandbox shows one page a distinct text, classic and converted, wide and phone.

## What stays code?
- Order receipt and the other eight types with no widget (`pg_classic_no_widget_types()`); a file whose pieces drop until a designer converts it; a Dynamic region.
- Bootstrap names are not touched here: a Legacy design keeps Bootstrap 3, its trial design maps at output (`designer/legacy-layouts-output-map.md`).

Related: docs/memory/plans/legacy-editable.md, docs/memory/designer/layout-code-tab.md, docs/memory/designer/system-widget-change-checklist.md
