---
name: shop-starters-narrow-widths
description: Shop starters at tablet width: catalog card buttons wrap, Add to Cart no longer sticks out (#9089); the cart link count badge sits inside the bag button (#9101)
---

## Why does the product card's button row wrap?

The catalog listing starter (`_buildStarterTree()` case `catalog_listing`,
"Load Default Layout") puts View, the favourite heart and Add to Cart in one
`d-flex gap-2 mt-2` row ("Action buttons"). A flex item cannot get narrower
than its longest word, so in a two-column (576-767px) or three-column
(768-991px) card the row needed about 245px of a 168-198px card: Add to Cart
stuck out 22-52px and scrolled the page 16px sideways at 768px. The row is
`d-flex flex-wrap gap-2 mt-2` now and the button carries `text-nowrap`, so
Add to Cart moves under the other two whole (#9089).

## Why is the cart count inside the bag button?

The cart link starter (`_buildStarterTree()` case `cart_link`) had its Count
span as a corner badge (`position-absolute top-0 start-100
translate-middle`), half its height above the button. Templates put the cart
link in the account bar, the first thing on the page with `py-1` above the
button, so the top fifth of the badge was cut off at the page's top edge.
The Count is `badge rounded-pill text-bg-danger` inside the button
(already `d-inline-flex align-items-center gap-2`), beside the bag (#9101).

## What about sites and templates already saved?

Both are starters: a design that already saved the layout, and a design
published from a template before the change, keep the old classes; "Load
Default Layout" or republishing the template gives the new ones. The design
templates' own product cards (`includes/design_templates/pinegrap-*`) were
not checked.
