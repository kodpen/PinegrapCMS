---
name: bug-hunt-areas-seo-meta-output
description: Bug hunt of what a page puts in its head (area 47, 2026-11-24): title, description, canonical, og tags, robots tag and header, paging addresses, product and article JSON-LD
---

## What was the area?
- The head `get_page_content()` writes: the `<title>`, the description, the canonical link and og:url, the Open Graph and Twitter tags, the robots meta and header, the canonical of numbered and filtered list pages, and the Product and BlogPosting JSON-LD (`includes/fn/seo.php`, `includes/fn/widgets_catalog.php`).
- Asked: one title, description and canonical each; canonical and og:url absolute, same host and scheme; noindex in the tag and the header; paging and filter addresses; Product price, stock and currency, article data; no draft or private value in a preview card.

Related: docs/memory/workflow/bug-hunt.md
