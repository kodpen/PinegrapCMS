---
name: canvas-closed-blocks-content
description: CB0 list, content nodes the server draws (Messages, Breadcrumb, Variant Attribute, Applied Offers, Carousel, own code, Language Switcher): output, settings, use, opening
---

## Which content nodes are one closed box on the canvas?
- Each has its own `render()` / `toHTML()` in the content types of `style_designer.src.js` and prints a marker the server fills (`_render_content_html()`).

## Messages (PHP)?
- Site: the liveform's errors and notices of the widget it sits in (`_pg_inject_messages_node()`), or what the widgets left at page level.
- Opened by settings (CB5, #9723): Alert look, Dismiss button and a true sample error on the canvas (`messages-node-settings.md`); the messages depend on the request.
- Use: the server names one in each form widget (`_pg_inject_messages_node()`); 57 templates place it on their pages.

## Breadcrumb?
- Site: catalog, group, subgroup, product inside a catalog or catalog detail widget; elsewhere nothing.
- Use: offered in those two widgets; no template places it.
- Opened as a tree (CB6a1, #9849): drawn as its crumbs part, "Convert to design elements" makes it that part (`catalog-detail-open-blocks.md`).

## Variant Attribute?
- Site: one copy per attribute of a select-type product (Colour, Size) as a select, radios, button group or button list. Canvas: a box naming the style.
- Change today: the style and three CSS class fields (label, control, item).
- Use: the catalog detail starter, 32 shop templates (`__variant_picker` is the older HTML value).
- Opened by settings (CB6a2): drawn on the canvas as the site expands it; not a tree, the variant script finds its controls (`catalog-detail-variant-price-blocks.md`).

## Applied Offers?
- Site: the cart's alert listing the offers that apply. Canvas: a box. Change today: CSS class.
- Use: the shopping cart starter, 32 shop templates (the cart's `applied_offers` section does the same).
- Opening: a tree on a loop of offers.

## Carousel (content)?
- Site: inside a catalog detail widget, the product's pictures with thumbnails and a lightbox, filled by the server.
- Change today: thumbs position and size, autoplay, interval, controls, indicators, lightbox.
- Opened by settings (CB6a1, #9849): the canvas draws the gallery the site builds with sample photos (`catalog-detail-open-blocks.md`); the picture list depends on the product.

## Custom HTML, SVG, Custom PHP?
- The user's own code, edited in a code box; Custom PHP draws "Custom PHP — runs at render time" on the canvas.
- No template places them; classic regions made shared components carry Custom PHP (`designer-region-to-shared-component.md`).
- Opening: none proposed; Custom HTML could get "Convert to design elements" through the HTML importer (`classic-style-import.md`), a question for CB1.

## Language Switcher Button?
- A component drawn per request (`language-switcher-component.md`); the canvas draws its true markup. 57 templates.
- Opening: none; it is the pattern for request-bound output.
