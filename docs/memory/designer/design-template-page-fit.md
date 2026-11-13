---
name: design-template-page-fit
description: Ferry to photo templates in a browser: header at 1200 px, brand ellipsis, notice line, image size props, tinted-band text colours for every palette, positioned scroll boxes
---

## Which templates does it cover, and how is it tested?
- The sixteen multi-file templates from ferry to photo. `tests/design_template_page_fit_test.php` walks their trees; `_test_tpl_fit_ids()` lists them with their User Styles prefix.

## Why do seven headers open out at 1200 px?
- ferry, gallery, gym, hotel, marina, museum and pharmacy had `navbar-expand-lg`: from 992 px the brand, the Turkish links and the button ran past the container. They are `navbar-expand-xl`, with the menu's own `-lg-` classes moved to `-xl-`. filmstudio, music and photo fit at 992 px and stay `-lg`.

## How does a long site name fit?
- The header's name span is `text-truncate`, and every template's User Styles hold `.navbar-brand{min-width:0;max-width:calc(100% - 5rem)}`: the brand shrinks in the open bar, and on a phone it leaves room for the menu button. A 30-letter name widened no page at 320 to 1400 px.
- Only the header truncates: pharmacy's `$brand()` takes the name class as a parameter, as its footer column is narrow.

## Why is the notice a plain paragraph?
- It was `d-flex flex-wrap`: on a phone the link dropped under the text, alone on its line. It is `p.mb-0` with `me-1` on the icon and `ms-1` on the link, so it wraps as a sentence. pharmacy keeps its flex row (two parts: the on-duty link, the emergency number); launch, law and market never wrapped.

## How are image sizes written?
- As the `width` / `height` props, never in `_attrs`, which the renderer drops ([[image-size-props]]): a 48 px face showed at 96 px.

## Why does the account bar's row wrap?
- With a second language Sign Up ran past the page at 320 to 414 px (hotel by 126 px). As in planetarium to zoo (#9059): the row `flex-wrap justify-content-end column-gap-1 column-gap-sm-2 row-gap-1`; switcher, Log In, Sign Up, Account Button `text-nowrap`.

## Which colour does small text take on a tinted band?
- On `bg-body-tertiary` the palette's text colour (`--pg-primary-fg`, made for the page) fell just under 4.5:1 in light (Coral, Amber, Electric), to 3.9:1 in dark (Midnight, Burgundy); the emphasis tint gave 3.85:1.
- So each template sets `--<prefix>-band-primary` / `-secondary` on the band, `color-mix()` 90% with black (light) or 85% with white (dark), under `@supports`; `text-primary`, `link-primary`, `text-secondary`, `link-secondary` (not hovered or focused), both outline buttons and `.nav` links use them. 4.8:1 or more with all 22 palettes. library adds its hero (`.plb-hero`).
- On `bg-primary-subtle` (call to action bands, the account card's Log out): fg fell to 3.2:1 (Graphite's secondary) in light. There the same classes take `--bs-primary-text-emphasis` / `--bs-secondary-text-emphasis`: 4.56:1 or more with every palette in both themes. These rules come after the band's.
- pharmacy: `.bg-danger-subtle .btn-outline-danger` takes `--bs-danger-text-emphasis` (Bootstrap's red is 3.4:1 on its soft red), `.bg-body-tertiary .link-danger` too.
- Assembly tests refuse hex in User Styles: mix with `black` / `white`.

## Why is every table scroll box positioned?
- `table-responsive position-relative`: a `visually-hidden` caption or heading is absolutely positioned and escapes a scroll box that is not positioned ([[design-template-homeservices-fit]]).

## What else was fixed?
- A paragraph holds no block (a `div` inside a `p` closed it early): the footer's opening hours are a `span`. pharmacy's footer hours are `text-wrap` (its `.prx-price` is nowrap). ferry's wake line is `my-3`.

## What is left for shared files?
- The ferry-crossings block (`style_designer_blocks_ferry.js`) draws the wake without `my-3`.
- The booking block's `.pg-week-slot > time` (`style_designer_blocks_booking.js`) uses `--bs-primary-text-emphasis`: 4.46:1 on the dark page with Midnight or Burgundy.
- A rule for tinted bands in `themes/bridge.css` would cover every template at once.
