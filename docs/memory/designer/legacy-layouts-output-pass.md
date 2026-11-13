---
name: legacy-layouts-output-pass
description: Where a Bootstrap 5.3.8 design's page prints its page type's 5.3.8 names (LL3, #9143): the gate, the part mapped, the code tab's line, the sandbox pictures
module: legacy_bootstrap
---

## Which page gets the pass?
- `get_page_content.php` asks `pg_lbs_layout_output_applies($style_layout, $pg_design_fw['key'], $email)`: the page's style is a visual design (`visual_designer`), its framework key is `bootstrap5`, and the page is not drawn as an e-mail. A classic style (its framework column reads `bootstrap5`), a Legacy design, a custom-framework design and every e-mail keep their markup byte for byte.
- `includes/fn/legacy_bootstrap.php` is loaded only for a visual design's page drawn in a browser. The canvas region preview and the SEO renders run the same `get_page_content()` and see the mapped markup.

## Which part of a system region is mapped?
- `pg_lbs_layout_region($system_output, $pg_lbs_from)`, once per region, after the page type's markup, its comments and the comment e-mail markup, before the footer, the edit mode frame, the canvas marks and the join with `<system>` ([[legacy-layouts-output-join]]).
- A secondary region's header and footer are not mapped: they are the page's own rich text, edited in place in edit mode, and a page can be drawn with another style on a phone (`mobile_style_id`); a mapped header saved from edit mode would write 5.3.8 names for that style too. Content and common regions are not mapped either.
- A pass that cannot finish leaves the whole region as it came. Rules of the pass: [[legacy-layouts-converter]].

## What does the designer see?
- The layout code tab (`_sdLayoutCodeOpen()`, `style_designer_code.js`) shows the file as written; on a `bootstrap5` design one line above the editor (a file tab's `note`, `.sd-code-note`): "Pages of this design print the layout's Bootstrap 3 class names as their Bootstrap 5.3.8 names; the file keeps them as written."

## What did the sandbox show? (2026-11-13, #9143)
- turkish_default starter, the cart, checkout, sign-in, two form and catalog pages moved to a bare `bootstrap5` visual design (`<system>` in a container), before and after, 1280 and 390 px, visitor and signed in; 28 pairs, no script error.
- Better after: the cart hides its phone-only cell labels on wide screens and shows its remove button (`ti-close` gets a twin), totals right, offsets (`col-sm-offset-8`), the catalog search button's icon, `mb-3` spacing on every form.
- Left the same before and after: the cart table overflows a phone (430/446 px), as its `mobile_stacked` stacking comes from the classic sheet; the catalog image is 500 px wide on a phone; sign-in inputs carry no `form-control`, so on a wide screen the `float-start` "Remember me" sits beside them. None is a rename; the `form-check` inputs got `form-check-input` in LL4 ([[legacy-layouts-shapes]]).
- The first round showed a chevron beside every select and a tick beside every box: the theme widget rule of [[legacy-layouts-converter]] came from it.
- Pass time per request (PHP 8.3 built-in server, no opcache): billing information 33 KB 1.1-1.8 ms, cart 11 KB 0.3-0.5 ms, sign-in 1.4 KB 0.14 ms.
