---
name: wysiwyg-text-binding
description: Never give a WYSIWYG (block-wrapped) field to a text binding: nested p breaks the DOM; use _pg_rich_text_to_inline() in all three places; no extra .alert box.
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

Related: docs/memory/designer/data-binding-to-content.md
