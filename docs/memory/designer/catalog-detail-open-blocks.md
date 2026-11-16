---
name: catalog-detail-open-blocks
description: Catalog detail closed blocks opened (CB6a1, #9849): the Breadcrumb node drawn as its crumbs part and converted into it, the product carousel and the Checkout Options rows drawn true on the canvas
---

## What did CB6a1 open?
- Step CB6a of `plans/designer-closed-blocks-steps.md`, cut by size; this part is #9849. The census: [[canvas-closed-blocks-content]], [[canvas-closed-blocks-widgets]].
- Breadcrumb (content node): a tree, the crumbs part ([[widget-default-parts-breadcrumb]]).
- Carousel (content node) and Checkout Options (`payment_methods` section): settings only, drawn true on the canvas; their output depends on the product and the store's settings.
- Left for CB6a2: the price block (`__price_block_html`) and the Variant Attribute node, both redrawn by a variant switch (`pg_civ_variants.js`).

## What does the Breadcrumb node do now?
- The site never changed: a catalog widget prints the crumbs part in its place (`content:breadcrumb`), and an old tree already opened as that part on load (W16).
- In a catalog listing or catalog detail widget the canvas draws the part with the crumbs list's sample rows (`_sdBreadcrumbSample()`), no dashed box.
- Its settings panel has "Convert to design elements" (`data-sd-crumbs-open`, `_sdBreadcrumbToPart()`): `_sdDefaultParts()` turns the node into the nav, list and crumbs list in place, so a node just dropped opens before the page is reopened.
- Outside a catalog widget the site prints nothing for it; the canvas box says so.

## What does the carousel draw?
- `_sdCarouselSampleHtml(p)`: the markup `_render_content_html()` prints (case `carousel`) with three sample photos: the strip where Thumbnails puts it, Size and Active border, arrows, dots, the lightbox's links. Under it the settings that are on (`_sdCarouselSampleNote()`).
- Inert, no `data-bs-ride` or `data-fancybox`; marked `sd-editor-ph`, out of the canvas audit. `pg_carousel_layout.css` is not on the canvas, so its layout rules are inline in the sample.
- Thumbnails Right follows the site: the wrapper's row-reverse puts the strip beside the slides on the left side.

## What do the Checkout Options rows draw?
- `designer_screen.php` sends `sdDesign.siteWords.payment_method_rows`: `pg_civ_payment_method_rows(_pg_civ_payment_settings(false))`.
- A `payment_methods` body in a catalog detail widget (table parts are built by `buildTableNodeEl()`) draws its first row with the first method's words and hatched, inert copies for the others (`_sdPaymentMethodsDraw()`), as `_pg_civ_fill_payment_methods()` prints them. Other rows of the body fade: the site drops them.
- No method on: the body fades as before (`_sdCatalogBindingDisabled()`).

## Does a running site change?
- No: no PHP output changed; only the editor draws and converts.

## How was it checked?
- `tests/catalog_detail_closed_blocks_test.php`: the carousel sample's carousel classes equal the site's for each strip place and setting, size and colour bounds, the breadcrumb sample against `pg_sw_part_catalog_breadcrumb_item()`, the editor wiring.
- Sandbox: the Online Store template's product page, before and after on the canvas at 1440 and 390, dark, and the site page.
