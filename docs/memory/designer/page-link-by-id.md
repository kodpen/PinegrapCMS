---
name: page-link-by-id
description: A link outside a widget kept by page id (__page_link, #6233): fallback href plus data-pg-page-link in the saved body, the current address put in by get_page_content(), '#' inside a widget
---

## What is it?
- A content link, a btn drawn as `<a>` or a semantic `a`, in a page tree or a shared component, with `_bindings.href = '__page_link'` and `_bindParams.href = {page_id, params: []}`: the shape widgets use (designer/record-value-page-links.md), no new prop. `props.href` (or the `_attrs` href) stays as the fallback. Decided in #6233 (`workflow/decisions-made-product-23.md`).

## How is it drawn on the site?
- A page body is saved drawn (`page.page_tree_code`), so a name drawn at Save would miss a rename. `_apply_bindings()` outside a widget (`!pg_sw_finish_active()`) keeps the typed href and adds `data-pg-page-link="<id>"` (`pg_page_link_bound_id()`: an int or digits above 0; `tab:<key>`, 0 or junk add nothing).
- `get_page_content()`, after shared components and widgets are drawn and before the language pass, runs `pg_page_link_expand()`: the ids (`pg_page_link_ids()`, `<a>` tags only), one page query (`pg_sw_page_link_resolve()`), then `pg_page_link_expand_html()` sets the href (escaped once; inserted when the tag has none) and drops the marker. A missing or binned page keeps the fallback; the marker on another tag is only dropped.
- The site, Preview, prototype.php, e-mails and the SEO crawl all pass `get_page_content()`, so `seo_link` stores the current address.
- Inside a widget a `__page_link` the renderer did not expand still prints `#`. A shared component placed in a widget's layout is drawn after the widget closes, so it counts as outside.

## How does the Prototype view read and write it?
- `nodeLinks()` gives such a link (not one in text) `bound` (`boundPage()`: digits, `tab:<key>`, '' for none: then its typed href) and `pageLink`; `design()` asks digits in `request.page_ids` (designer/prototype-map-read.md). `markBound()`: the tab of that id or key, else `markHref()` of the page's current address from the maps (binned, draft), `hidden` for `hidden_ids`, else broken; the row's href is the current address.
- An arrow of a link of its own to a tab frame: `hrefPatch(node, row, href, target)` adds `bind: {page_id}` (`boundTo()`: the page id, `tab:<key>` unsaved, made an id on Save); to a chip, a bound link gets `bind: false`. `applyPatch()` writes the address (fallback), then `_bindings.href` / `_bindParams.href` (params kept). Links in text stay by name. A row matches by `bound` when it has one.
- A link in a widget tree with page id 0 shows as its typed href, though the site prints `#` there.

## How does the panel show it?
- `_sdBoundHrefNotice()` outside a widget: `_sdPageLinkNotice()` names the page (`_sdPageLinkName()`: tabs, else the loaded page list, never fetched for it) and a "Type an address instead" button (`data-sd-page-unlink`) that drops the binding and keeps the address.

## What is left?
- Bind Data offering a page outside widgets; `pg_link_resolve()` itself stays href-only (bound links are marked before it).

Tests: `tests/page_link_by_id_test.php`, `proto_hotspots_test.php` (`bound`), `prototype_map_test.php`.
