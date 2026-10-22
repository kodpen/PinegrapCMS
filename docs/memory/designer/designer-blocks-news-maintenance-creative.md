---
name: designer-blocks-news-maintenance-creative
description: Maintenance, news and creative blocks (2026.11.0): palette groups, the family branch and helpers, maintenance blocks without site links, attribute-dropping nodes, block details, the palette test.
---

## Which blocks are there and where do they show?
- Three palette groups by `componentType` prefix (`_sdBlockPaletteGroups()`): **Maintenance & Launch** (`maintenance-`, `coming-soon-`: centered, split, status board, dark; launch date, split), **News & Magazine** (`news-`: ticker, rotator, trending, weather card / strip / forecast, markets, lead grid, latest + most read, category columns, live blog, article header, author box), **Creative** (`bento-`, `marquee-`, `showcase-`, `quote-`, `gallery-`, `scroller-`). Each variant has its own palette entry.
- Each brings its own `section`/band and `container`, so they are in `_noWrapCtypes`.

## Where is their code?
- Inside the family branch of `createFromPalette()` (`if (/^(services|…|scroller)-/.test(ctype) …)`), with the `_bk*` helpers; a new family adds its prefix to that regex.
- The branch's helpers share one function scope (`var` hoisting): a family's own helpers carry a prefix no other block in `createFromPalette()` uses (grep `var _xx` first): maintenance `_mn` (`_mt` belongs to `menu-tabs`), news bands `_nb`, news stories `_ns`, creative `_cr`. Pieces every family needs are shared `_bk*` helpers: `_bkTime` (semantic `time` with the `text` prop and `datetime`), `_bkRegion` (role/label/tabindex of a scrollable strip; the older `pricing-comparison` and `compare-table` write the same on their `table-responsive`), `_bkIconLink` (icon-only link with `aria-label`), `_bkMarqueePause` (the marquee pause control, `designer-block-motion.md`).

## Which node types drop attributes?
- `row`, `col` and `container` nodes do not write `_attrs` (editor and PHP mirror alike). A row that needs `role`, `tabindex` or `aria-label` (a horizontal scroller) is a semantic `div` with `row …` / `col-*` classes (`loyalty-stamps`, `scroller-cards`). `_bkCol()` always writes `xs: '12'`.
- An image's whole-number `width`/`height` props are its attributes, a value with a unit a style (`image-size-props.md`). A round avatar or a fixed-shape thumbnail still takes a block rule (`pg-circle-40`, `pg-thumb-100`, `pg-circle-160`).
- `visually-hidden` text is `position: absolute`: inside an `overflow-x-auto` strip the strip needs `position-relative`, or the hidden text escapes and phones scroll sideways.

## What is special about the maintenance blocks?
- In maintenance mode every address answers with the error page's maintenance screen (`error-page-widget.md`), so these blocks carry no menu, no Home button and no other link into the site: only `mailto:`, `tel:` and social links. They go into the Error Page widget's maintenance block or onto a page of their own. Their headings are `h1` (they are the page); other blocks start at `h2`.
- Notify forms are samples like the newsletter blocks: they have no action until the operator wires them. Their submit is a semantic `<button type="submit">` (form rules: `designer-block-forms.md`).

## Which block details are deliberate?
- `scroller-cards`' scroll hint is the two-headed `bi-arrow-left-right`: a one-way arrow points wrong on RTL pages.
- `news-weather-strip`'s cities are outlined chips (`bg-body border rounded`), so corners and border width follow the look; the band stays on `bg-body` (small primary text on `bg-body-tertiary` fails AA).
- `showcase-floating`'s cards float over the picture from 992 px (`pg-float`), below that they stack; the canvas places them as the page does (`designer-canvas-style-designer.md`).

## What guards the palette?
- `tests/designer_blocks_test.php` reads `style_designer.js`: every palette block has a reachable builder in `createFromPalette()` and a group, every `_SD_BLOCK_CSS_NEEDS` name is a rule, every rule's key appears as a class in its own rule (otherwise `_sdEnsureBlockStyles()` never finds it and appends it again on every drop), and no rule uses `!important`.
