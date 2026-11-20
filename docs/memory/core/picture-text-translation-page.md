---
name: picture-text-translation-page
description: Picture text translation's page pass: a page in another language shows a Design Canvas picture's drawn language version in src, srcset, og:image, links and style url(); waiting rows, one query
module: picture_translation
---

## Where does the swap run?
- `pg_picture_tr_page_pass()` (`includes/fn/picture_translation.php`, the page pass block) from `pg_tr_render_finalize()`, right after `pg_tr_rewrite_links()`, on every page drawn in another language. Not in the cached body (`page_translations`): a picture drawn later changes neither its fingerprint nor the rules.
- The store, the states and the hooks: `picture-text-translation-store.md`.

## Which places are swapped, and which are not?
- Swapped: an `img`'s or a `source`'s `src` and each `srcset` candidate, an `a`'s or a `link`'s `href`, the `content` of `<meta property="og:image">` (also `:url`, `:secure_url`), `url()` in a `style` attribute (plain, quoted or `&quot;`). Root-relative or on the site's hosts (`pg_picture_tr_hosts()`), right below `PATH`, with a picture extension (`pg_picture_tr_extensions()`); `?w=`, `?f=` and the quote stay.
- Left alone: `<script>`, `<style>`, `<textarea>`, `<pre>`, `<code>` blocks (the JSON-LD `image`, the variant data's photos), `data-src` and other attributes, other metas, other hosts. The first version leaves them out (`picture-text-overview.md`).

## What does it cost?
- One `SELECT` a page (`pg_picture_tr_page_sql()`): the named files, their row in the language, the row's picture and whether the file has `file` uses. No document decoded. A page with no picture of the site asks nothing.
- An `INSERT IGNORE` only on a first visit of a picture with words in that language (a waiting row, state 0), which also sets the waiting mark. None for a right-to-left language (`pg_tr_known_languages()` index 5): those are not drawn.
- A page save in the visual editor writes the rows of the pictures in the page's own code beforehand, by the same address rule (`picture-text-auto-rows.md`); a visit still writes those of shared parts, widgets and menus.

## What is shown?
- Only a drawn row (state 1) whose picture is found; waiting and out of date show the source (`pg_picture_tr_page_plan()`).
- The picture is joined only in the source's folder (`p.folder = f.folder`), so `get_file.php` serves it with the source's rights: a private folder's picture stays private.

## Where is it tested?
- `tests/picture_translation_page_test.php`: the address rule, each place swapped, each left-out place untouched, the names, the plan (drawn only, waiting rows, right-to-left), the folder in the query, one read and one insert, the hook's place. Tried on MariaDB with a drawn, a new and an other-folder picture.
