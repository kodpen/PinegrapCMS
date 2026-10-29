---
name: ai-write-note
description: The site note ^^__ai_generated^^ (product detail widget): when it prints, which columns it speaks for, pg_ai_fields_note(), a page's own marks still open
---

## What prints the note?
- `^^__ai_generated^^`, the product detail widget (`catalog_item_view`, binding "AI note" in the designer's Basic Details). `pg_ai_fields_note('product', $p)` gives "Written with the help of AI." (`lang()`, Turkish "Yapay zekâ yardımıyla yazıldı.") while a shown text holds its mark (`pg_ai_fields_marked()`), else ''. An empty bound element is hidden by the site's `[data-pg-bind]:empty` rule, so no visibility flag.
- Nothing prints by default: a design that must disclose binds the token where it wants the note. Starter trees do not carry it.

## Which texts does it speak for?
- `pg_ai_fields_shown()`: a product's short description (its title on the page) and long description. The SEO title and meta description are read by search engines, not printed in the body, so their marks print nothing.
- The renderer reads `products.ai_fields` only where `pg_ai_fields_ready('product')`; a database without the column prints nothing.

## And a page's own texts?
- Open: no widget prints a note for `page.ai_fields` yet (`pg_ai_fields_shown('page')` is empty). Page title and meta description are not in the page body either, so nothing needs one today.

## How was it checked?
- `tests/ai_fields_test.php` (`test_ai_fields_note`), `php tools/check_bindings.php`. Not run on a site.
