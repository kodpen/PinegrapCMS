---
name: seo-structure-non-html-output
description: Pages may output JSON/XML/CSV, so SEO/structure rules must not assume an HTML document; pg_seo_output_is_document() gate and the robots splice risk.
---

## Is every Pinegrap page an HTML document?
- No. A page is whatever its style and regions produce. Operators deliberately write pages returning JSON, XML, CSV or bare fragments (dynamic-region API endpoints such as `kodpen.com/api2` are the live example).
- Never add a rule that assumes the output is a document: forcing a tag into `</head>`, expecting `<title>`, looking for a canonical. On those pages it yields meaningless findings or corrupts the output.

## What gates the structure check?
- `pg_seo_output_is_document()` (seo_structure.php) inspects the first 2 KB; if not a document the structure check does not run at all.
- Be GENEROUS: a false "yes" only produces findings that would have been produced anyway; a false "no" silently drops a real page from the score.
- The 2 KB limit is deliberate: reading further starts matching markup inside a JSON string or code sample.
- `page_type` does NOT answer this: two pages of the same type can be one a document and one JSON depending on what is put in them.

## Known risk
- The robots splice (`preg_replace('/<\/head>/i', ...)`, 2026.4.3) carries the same assumption. If a `noindex` JSON page body contains `</head>`, the tag lands inside the JSON.
- When writing a new splice, first add a gate like `pg_seo_output_is_document()`.

Related: docs/memory/visitors/page-noindex-switch.md
