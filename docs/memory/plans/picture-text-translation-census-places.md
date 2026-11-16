---
name: picture-text-translation-census-places
description: Picture text translation PT0 census: every path writing a picture address into a page (nodes, HTML, products, srcset, backgrounds, og:image), what the page pass sees
---

## Where is the finished-page pass, and what does it see?
- `get_page.php` ends with `pg_tr_finalize()` then `pg_version_assets()` (the `&v=<mtime>` stamp), then the page cache keeps the output. In another language `pg_tr_render_finalize()` runs `pg_tr_render_db_texts()` and `pg_tr_rewrite_links()` over the whole page, the head included; a file address gets no language prefix (`pg_tr_path_is_file()`).
- `pg_tr_render_db_pass()` splits out `<script>`, `<style>`, `<textarea>`, `<pre>`, `<code>` and leaves their content alone, but the variant data script (`pgCivVariants`), whose strings it draws.
- So a picture swap beside it sees every attribute in the HTML and nothing inside a script or style block.

## Which paths write a picture's address, and are they seen?
- Image node: `_render_content_html()` writes `src`, `srcset` (`?w=` widths) and AVIF/WebP `<source srcset>` (`designer/responsive-images.md`). Seen.
- Stored HTML (custom HTML, texts, carousel slides, product descriptions, record rich text): `<img src>` plus `srcset` from `pg_img_responsive_html()`. Seen.
- System widget output (catalog cards, product photos, cart lines, `^^token^^` srcs): `srcset` added per widget. Seen.
- Gallery photos of products (`products_images_xref.file_name`): printed as `<img>`. Seen. A variant's photos are in the `pgCivVariants` JSON and swapped in by `pg_civ_variants.js`: the pass draws its strings only, not addresses.
- Backgrounds: a node's `inlineStyle` or a `style` attribute with `url()` is seen; a `<style>` block in the page (`get_page.php` puts the session's CSS in the head) is not; the design's custom CSS is a file of its own and never passes.
- Share picture: `<meta property="og:image">` in the head (`get_page_content.php`) is seen. JSON-LD `image` (inside a script) is not; `pg_tr_render_finalize()` rewrites only its `url`, `@id`, `item`.
- Lightbox (`<a href>` to the picture): seen. RSS enclosures: `pg_tr_rss_localize()`, another pass.
- E-mails (left out of the plan): `pg_image_mail_original_urls()`; never through `pg_tr_finalize()`.

## Why not swap in the cached body?
- A page body in a language is drawn once and kept in `page_translations`, its fingerprint the render rules plus the source body (`pg_tr_render_page_body()`). A language picture drawn later changes neither, so a swap there stays stale until the rules change.
- In the finalize pass it runs on each uncached request; a newly drawn picture still has to mark the page cache dirty (`platform/page-cache-background-writers.md`).

## What would the swap match?
- This site's addresses in `src`, `srcset`, `content` of an `og:image` meta, `href` to a picture and `url()` in a `style` attribute: plain, `?w=`, `?f=`; the derivatives are per file id (`platform/image-derivatives.md`), so a language picture that is its own `files` row gets its widths and formats for free.
