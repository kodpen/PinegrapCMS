---
name: wysiwyg-text-binding
description: Rich text in widgets: a short block-wrapped field is reduced to inline (_pg_rich_text_to_inline()), a long description binds to a div, img-responsive gets img-fluid at render; no extra .alert box.
---

## What goes wrong when a WYSIWYG field is bound via `text`?
- Rich-text-edited fields are stored block-wrapped: `products.out_of_stock_message` → `<p>Sorry, this item…</p>` (the default `add_product.php` writes). Binding it with `text` to a `content/paragraph` node puts `<p>` inside `<p>` (invalid HTML).
- The browser does not nest: it closes the outer `<p>`, so one bound element yields three DOM nodes: an EMPTY shell carrying the designer's classes, the orphaned message, and a trailing empty paragraph.
- Plain product: the shell is hidden by `[data-pg-bind]:empty`, only the orphan shows. Variant product: `pg_civ_variants.js` also writes the same message into the shell → the sentence appears twice (one boxed, one plain). That is why two products behave differently.

## What is the rule?
- Before publishing a WYSIWYG field as a token, reduce it to inline with `_pg_rich_text_to_inline()` (block closings become `<br>`; `<a><strong><em><span>…` preserved).
- Applied in ALL THREE places: catalog_listing loop, catalog_item_view token map, variant JS payload.

## Should the message be wrapped in an `.alert` box?
- No. Do not wrap the operator's own sentence in `.alert`: the text already arrives formatted (e.g. `<strong>Stok Tükendi</strong>`); a second visual container shows one fact as two notices.
- The template uses plain emphasized text; a designer who wants a box adds it via the CSS class field.

## Where does a long rich text go?
- A product's long description (`__description`) keeps its paragraphs, lists and pictures, so it is bound to a `div`, never a `p`: the catalog detail starter writes a semantic `div` named "Description". A layout saved earlier keeps its `p` (the browser closes it before the first inner `<p>`, the classes miss the text) until the designer swaps the element.

## Why do classic-theme pictures stay in their column?
- Rich text written for the classic theme scales pictures with `img-responsive`, which only the classic stylesheet defines; on a Bootstrap 5 page the picture kept its natural width and the page scrolled sideways. Every widget's finished output passes `pg_img_fluid_alias()` (before its srcset pass, not in an e-mail): an `<img>` with `img-responsive` gets `img-fluid` too. Stored text and the field hints are not changed; no CSS is added.

Related: docs/memory/designer/data-binding-to-content.md
