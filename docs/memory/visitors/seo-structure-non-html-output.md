---
name: seo-structure-non-html-output
description: Pages may output JSON/XML/CSV, so SEO/structure rules must not assume an HTML document; what the structure check does with such output and the robots splice risk.
---

## Is every Pinegrap page an HTML document?
- No. A page is whatever its style and regions produce. Operators deliberately write pages returning JSON, XML, CSV or bare fragments (dynamic-region API endpoints such as `kodpen.com/api2` are the live example).
- Never add a rule that assumes the output is a document: forcing a tag into `</head>`, expecting `<title>`, looking for a canonical. On those pages it yields meaningless findings or corrupts the output.

## What does the structure check do with such output?
- `pg_seo_analyze_page()` (`seo_structure.php`) renders the page and runs `pg_seo_analyze_html($html, 'document', …)`. Output without any `<` gives no DOM (`pg_seo_html_xpath()` returns null), so plain JSON or CSV gets no findings. There is no separate "is this a document" test: output holding `<` anywhere (JSON carrying HTML in a string) is parsed and judged as a document.
- Product and product group texts are analysed as `fragment`: the document-only rules (`pg_seo_document_only_codes()`) are skipped for them.
- `page_type` does NOT answer the question: two pages of the same type can be one a document and one JSON depending on what is put in them.

## What is the known risk?
- The robots splice in `get_page_content.php` (`preg_replace('/<\/head>/i', ...)` when the `<meta_tags>` placeholder is gone, 2026.4.3) carries the same assumption. If a `noindex` JSON page body contains `</head>`, the tag lands inside the JSON.
- A new splice or document-level rule needs its own check that the output is a document; there is no helper for page output to reuse.

Related: docs/memory/visitors/page-noindex-switch.md
