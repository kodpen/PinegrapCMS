---
name: jsonld-script-escaping
description: How JSON-LD is printed inside <script type="application/ld+json"> so no value can break out of the element or swallow the page.
module: seo
---

## How is JSON-LD printed into a page?
- Through `pg_jsonld_script_json()` (`includes/fn/seo.php`): `json_encode()` with `JSON_HEX_TAG | JSON_HEX_AMP`, unicode and slashes unescaped. Every `application/ld+json` block in `get_page_content.php` (product, catalog listing, BlogPosting, breadcrumb, organization) and the page's own block (`pg_custom_jsonld_block()`) use it; the search sections block has its twin `pg_search_sections_jsonld_json()` (`includes/fn/widgets.php`).
- Never `json_encode($data, JSON_UNESCAPED_UNICODE)` alone into a `<script>`: a default `json_encode()` escapes `/`, so `</script>` cannot appear, but `<!--<script>` can.

## What did a bare json_encode() break?
- A value holding `<!--<script>` (a visitor's published product question, a record title or author on a form item view, a product name) put the HTML tokenizer in the "script data double escaped" state: the block's own `</script>` no longer closed it, and the page up to the next `</script>` was read as script text, so the page lost its content and the JSON-LD did not parse. Fixed in 2026.11.0 (`tests/seo_jsonld_script_test.php`).

Related: output-escaping.md
