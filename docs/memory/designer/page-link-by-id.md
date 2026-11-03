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

## What is left?
- Editor side (canvas name, `pg_link_resolve()`/twin and the Prototype map reading the id, the arrow and Bind Data writing it): plans/designer-prototype-links.md.

Tests: `tests/page_link_by_id_test.php`.
