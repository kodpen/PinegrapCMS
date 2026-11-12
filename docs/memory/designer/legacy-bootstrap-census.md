---
name: legacy-bootstrap-census
description: Census of the starting sites' Legacy designs for the Bootstrap 5.3.8 helper (LB1, #8940): sets, Bootstrap 3 and theme classes, scripts, theme parts, map coverage and gaps
---

## Which Legacy designs did the sandbox move make?
- Every set of both starting sites, moved by `pg_ltd_run_start()` and `pg_ltd_run()`, no failure. english_default: "home-1 set" (42 styles, 148 pages, 207 parts) and "auto-dialog set" (2, 2, none). turkish_default: "home-2 seti" (40, 146, 194), "home-1 seti" (2, 2, 20), "auto-dialog seti" (2, 2, none), "emailer-inline seti" (2, 27, 1).
- Counted: the `cssClass` of every node in the pages' trees and their parts (refs followed), classes and `data-` attributes inside HTML props, and `data-` names in `_attrs`. Then about 90 pages per big design fetched as a visitor (the rest redirect to sign-in or answer 404).

## What do the trees use?
- Class uses (distinct names). home-1 set: Bootstrap 3 names the map renames 324 (23), Bootstrap 3 names 5.3.8 keeps 2343 (33), theme 3011 (230), Themify icons 96 (44), Pinegrap and stray 63 (13). home-2 seti: 308, 2221, 2749, 82, 64. home-1 seti: 29, 175, 372, 27, 6.
- Map work by name: `text-right` 136, `col-*-offset-*` 105, `btn-lg` 12, `pull-left` 12, `hidden-*` 13, `col-xs-*` 16, `list-inline` 7, push/pull 12, `text-left` 4, `visible-*` 4. Kept names: `row`, `container`, `col-sm/md-*`, `text-center`, `breadcrumb` 120, `lead` 104, `bg-primary` 22, `progress` 8.
- No Bootstrap 3 component in the trees: no `navbar`, `panel`, `label`, `glyphicon`, `modal`, `data-toggle`. The menu is the theme's (`nav-container`, `module`), dialogs are the theme's `modal-container`. One `embed-responsive` (turkish home-2).
- auto-dialog sets: one kept Bootstrap class, nothing to convert. emailer-inline: e-mail tables with its own inline sheet (`es-*`), no Bootstrap sheet row: not a helper case.

## What does the map not reach?
- Theme names that 5.3.8 defines as `!important` utilities: `bg-secondary` 92, `bg-dark` 71, `overflow-hidden` 13, `align-bottom` 6, `accordion` 4, `pt-1`/`pt-2` 4, `bg-light` 2 (home-1 set; home-2 seti alike). `bg-secondary` turns the theme's light grey sections dark (`legacy-bootstrap-trial.md`).
- Push/pull on a column outside a `.row` (the image-edge parts): 5.3.8 has no twin. Two `visible-*` on one element need one merged display set.
- Page types' custom layouts: 79 of the 88 `data/layouts` files print Bootstrap 3 (`form-group` 629, `text-right` 345, `input-group-btn` 122, `visible-xs-inline` 100, `btn-default` 70, `checkbox` 62, `glyphicon` 53, `input-group-addon` 44, `img-responsive` 41, `help-block` 40), and so do `includes/templates/` (cart, order form, shipping). A layout belongs to its page type, not to a design, so the trees' map does not touch it.

## Which scripts and theme parts?
- Rows of the home sets: jQuery, Bootstrap 3 JS, flickr, flexslider 2.3.0 (cdnjs), lightbox, masonry, twitterfetcher, spectragram, ytplayer, countdown, smooth-scroll, parallax, the theme's `scripts.js`. auto-dialog: jQuery, Bootstrap 3 JS, `scripts.js`.
- `scripts.js` calls only one Bootstrap plugin, `$('[data-toggle="tooltip"]').tooltip()`, and starts flexslider (8 calls), masonry, ytplayer, countdown, spectragram, smoothScroll, Twitter and Flickr feeds, the theme's tabs and nav.
- Theme parts in home-1 set: `background-image-holder` 88, `parallax` 22, sliders (`slides`) 10, `masonry-item` 19, `modal-container` 12, `tabbed-content` 6, `accordion` 4, `data-lightbox` 8, `countdown` 2.
- Theme sheets: english `theme-foundrygreen.css` activated (four `theme-*.css`), turkish `theme_modern.css` (four `theme_*.css`); 40 colour sheets and four font sheets beside them.
