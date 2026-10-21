---
name: import-lazy-images
description: Designer import/paste of lazy-loaded images: which source an image node keeps (data-src, srcset, picture, noscript) instead of the placeholder GIF in src.
---

## What was the symptom?
- An imported or pasted design showed an empty image: its `src` was `data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7` (a transparent 1×1 GIF) although the page's own image was a long base64 picture.
- Not a truncation: data: URIs of any size survive the parser. The page was built for lazy loading; `src` held the placeholder and the picture waited in `data-src`, `srcset`, a `<picture>` `<source>` or a `<noscript>` copy. The image node keeps one source and drops the rest, so the placeholder won.

## Where is it fixed (PR #1034)?
- `pg_di_settle_img_sources($doc)` in `includes/designer_import.php` runs on the parsed document before it becomes nodes, in `pg_designer_import_html()` (file, ZIP, pasted page) and `pg_designer_import_fragment()` (canvas paste). It works on the DOM, so an `<img>` inside heading or paragraph text (kept as markup) gets the same source.
- Tests: `tests/designer_import_images_test.php`.

## In which order is the source chosen?
1. A lazy attribute wins over any `src`: `data-src`, `data-lazy-src`, `data-original` (`pg_di_lazy_src_attrs()`), then the best candidate of `data-srcset` / `data-lazy-srcset`. The lazy attributes are removed, or the project's script would put them back over a picture picked in the designer later. A value that is no address (`data-src="true"`) is ignored and kept.
2. Only when `src` is blank (`pg_di_is_blank_src()`: empty, `#`, `about:blank`, or a data: URI of at most 512 characters): the best `srcset` candidate, then the `<picture>` sources (first without `media`, else the first), then the `<noscript>` image right after it (which is then removed). A real `src` is never replaced by these.
- "Best" (`pg_di_srcset_best()`): widest `w`, else densest `x`; placeholders skipped.

## How is a srcset split?
- `pg_di_srcset_candidates()` splits like a browser: a URL runs to white space, so the comma of a `data:` URI does not cut it (the old `explode(',')` broke every data: URI in a srcset). `pg_di_rewrite_srcset()` uses it.
- libxml 2.9 nests what follows `<source>` inside it and parses `<noscript>` content as elements; the noscript reader also reads an `<img>` tag kept as text.

Related: docs/memory/designer/html-import-and-paste.md
