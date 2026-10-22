---
name: designer-block-markup
description: Heading levels and inline content in ready-made designer blocks: no skipped level, size from an h-class, no paragraph or block inside a button, label, span or heading.
---

## Which heading levels does a block write?
- The block's own title is an `h2`; the titles of its items (cards, plans, people, timeline steps, posts, questions, menu lines) are the next level, `h3`, even when they look small. A screen reader lists headings by level, and the editor's audit (`heading_skip`) and WCAG 1.3.1 report a jump such as `h2` -> `h5`.
- The size comes from Bootstrap's heading class, not from the tag: `tag: 'h3'` with `cssClass: 'h5 fw-semibold mb-2'`. `.h1`–`.h6` match the tag rules in Bootstrap and in the theme (`assets/css/themes/base.css` styles `h1…h6` and `.h1….h6` together), so the block looks the same as with an `h5` tag.
- `contact-info` was the only older block whose title was an `h3`; it is an `h2` with `h3` now, its items `h3` with `h6`.
- `_bkMenuItem(name, desc, price, tags, tag)` writes the item name as `h4` under a category `h3` (`menu-list`); `menu-tabs` (through `_mtItem`) and `menu-specials` have no category heading and pass `'h3'`.
- Measured: before, 18 older blocks jumped a level (`features-list`, `pricing-tiers`, `pricing-toggle`, `stats-progress`, `team-grid`, `team-minimal`, `team-leadership`, `faq-twocol`, the three timelines, `blog-grid`, `blog-list`, `blog-featured`, `compare-side-by-side`, `contact-info`, `menu-tabs`, `menu-specials`).

## What may sit inside a button, label, span or heading?
- Phrasing content only: text, an icon, a `span`. A paragraph content node prints a `<p>`, which is invalid there. Short text inside one of them is a content `heading` node with `tag: 'span'` (it keeps the text editable), as in the carousel control labels (`image-carousel`, the Components-tab carousel) and the `faq-twocol` question.

## What keeps this from coming back?
- `tests/designer_blocks_test.php` fails when a `createNode('semantic', …)` button, label, span, small, strong, em or heading in `createFromPalette()` holds a paragraph, a `div`/`p`/list/section/form/table node or a grid node. Nodes built through `_bkTag()` are not read there.
- Heading order is not in the test (a builder's helpers are written before the title that precedes them on the page): the editor's audit and a render of the block's HTML check it.
