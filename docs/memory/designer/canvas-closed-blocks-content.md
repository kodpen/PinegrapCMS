---
name: canvas-closed-blocks-content
description: Content nodes the server draws (Messages, Breadcrumb, Variant Attribute, Applied Offers, Carousel, own code, Language Switcher): what each opens to today and what stays closed
---

## Which content nodes does the server draw?
- Each has its own `render()` / `toHTML()` in the content types of `style_designer.src.js` and prints a marker the server fills (`_render_content_html()`). The rules of opening: [[canvas-closed-blocks]].

## Messages?
- Settings only (CB5, #9723): Alert look, Dismiss button, and a true sample error on the canvas (`messages-node-settings.md`). The messages depend on the request: the saved body keeps the placeholder.
- Stays closed: the messages themselves and the heading text.

## Breadcrumb?
- A tree (CB6a, #9849): drawn as its crumbs part; "Convert to design elements" makes it that part (`catalog-detail-open-blocks.md`). Prints only inside a catalog or catalog detail widget.

## Variant Attribute?
- Settings only (CB6a, #9871): inside a catalog widget drawn as the site expands it (Colour, Size samples) in the node's style and classes; outside one the box naming the style stays (`catalog-detail-variant-price-blocks.md`).
- Not a tree: the variant script finds its controls in the server's markup.

## Applied Offers?
- Settings only (CB6c, #9901): drawn as the cart's offers alert from the twin of `pg_cart_applied_offers_html()`, inert, with a line saying the site fills it per cart (`cart-open-blocks.md`).

## Carousel (content)?
- Settings only (CB6a, #9849): the canvas draws the product gallery the site builds, with sample photos, in the node's thumbs, autoplay, controls and lightbox settings (`catalog-detail-open-blocks.md`). The picture list depends on the product.

## Custom HTML, SVG, Custom PHP?
- Stay closed: the user's own code, edited in its code box; Custom PHP draws "Custom PHP — runs at render time" on the canvas.
- Owner (2026-11-15, channel message 4146): they stay closed (`workflow/decisions-made-product-54.md`); no "Convert to design elements" through the HTML importer.
- Classic regions made shared components carry Custom PHP (`designer-region-to-shared-component.md`).

## Language Switcher Button?
- Drawn per request (`language-switcher-component.md`): the canvas draws its true markup from settings. It was already open in this sense and is the pattern for request-bound output.
