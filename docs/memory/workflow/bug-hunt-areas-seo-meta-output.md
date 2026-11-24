---
name: bug-hunt-areas-seo-meta-output
description: Bug hunt of what a page puts in its head (area 47, 2026-11-24): six faults fixed in the hunt's PR (#11459), what was read clean, what is left as it is
module: seo
---

## What was the area?
- The head `get_page_content()` writes: `<title>`, description, canonical link and og:url, Open Graph and Twitter tags, robots tag, the canonical of numbered and filtered lists, Product and BlogPosting JSON-LD (`includes/fn/seo.php`, `includes/fn/widgets_catalog.php`).
- Asked: one title, description and canonical each; canonical and og:url absolute on one host and scheme; noindex in tag and header; paging and filter addresses; Product price, stock, currency; article data; no draft or private value in a preview card.

## What did it fix? ("[iş] Bug hunt — SEO meta output", #11459)
- Every `<title>…</title>` of the page was replaced by the page title, so each inline drawing's `<title>` (an icon's accessible name) read as the page title, and a title the design wrote over several lines was not replaced. `pg_head_title_replace()` / `pg_head_title_text()` touch only the head's title (head ends at `</head>`, else `<body`; no head: left alone) and add one when the head has none.
- noindex was only a meta tag, so a page's `?pdf=true` copy and a page answering JSON or XML stayed indexable. `pg_page_robots_header()` sends `X-Robots-Tag: noindex, follow|nofollow` beside the tag (not during the SEO pass); the page cache keeps the header.
- Product JSON-LD named the full price while the page showed the offer price everybody gets: a merchant price mismatch. Both builders take `pg_product_jsonld_price_cents()` with `get_discounted_product_prices(false)`, the rule the product feeds keep.
- Availability: the classic builder said InStock for an oversold product (quantity below 0) and PreOrder for a back order; the widget's said InStock for a back order. Both use `pg_product_jsonld_availability()`: InStock, OutOfStock, BackOrder.
- og:locale followed the panel language, not the language the pages are written in (`TRANSLATION_SOURCE_LANGUAGE`): `pg_og_locale_language()`. Only tr and en have a locale; others stay en_US.
- A classic form list's canonical took `?page_number=2abc` as typed: `pg_canonical_page_number()` keeps a whole number above 1.

## What was read clean?
- One description (`pg_meta_tags_drop_overridden()`), one canonical (none for a missing item), canonical and og:url built from `URL_SCHEME . HOSTNAME_SETTING`; og:image and JSON-LD addresses too.
- Designed list paging and URL filters (`widget-list-paging.md`, `form-list-url-filters.md`), the canonical override rules (`page-canonical-robots-directives.md`), JSON-LD escaping (`jsonld-script-escaping.md`).
- The classic form item view: a record of another form or one the visitor may not see ends in `output_error()` before the head is written, so no title or description of it reaches the card.

## What is left as it is?
- A search results page's canonical keeps `?query=`; whether such pages carry noindex is product behaviour.

Tests: `tests/seo_head_output_test.php`.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/platform/structured-data-open-graph.md, docs/memory/visitors/page-noindex-switch.md
