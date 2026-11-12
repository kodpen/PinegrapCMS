---
name: designer-blocks-library
description: The Library block family (library- prefix): shelf hero with today's hours and formats, shelf mark guide with the ten subject classes, library card with its loans table
---

## Where does it live?
- `assets/js/style_designer_blocks_library.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `library-`, palette group "Library" (`bi-book`). Built in step `library-1` (PR #8794) for the Public Library template ([[design-template-library]]); `library-2` places it on the pages. Each item's `desc` is its hover-card line ([[palette-block-info]]).

## Which blocks does it hold?
- `library-hero` (Library: Shelf Hero): the page's `h1`, a line, two buttons. Beside it a bookcase: eleven spines on a shelf (`pg-library-shelf`, `aria-hidden`), titles read top down, three heights, theme colours; under it today's hours (two `<time>`s) and the branch.
- Under the hero's row an `h2` and six formats as pill links. On load: words rise, books are set down one by one, hours fade in, formats follow.
- `library-shelfmark` (Library: Shelf Mark Guide): a spine with its label drawn large (`pg-library-label`, `aria-hidden`), then an `h3` and a `dl` reading it: subject number, the author's letters, fiction A to Z. Beside it an `h3` and the ten subject classes as an `ol`; the example's class is marked "Example" as well as in colour.
- `library-card` (Library: Card and Loans): the card at a bank card's shape (`pg-library-card`, `aria-hidden`) with its barcode (`pg-library-barcode`, bars in `currentColor`), sliding in on view. Beside it an `h2`, a loans table (caption, a `th scope="col"` per column, a `th scope="row"` per format, in a named `table-responsive` region), three facts and two buttons.
- On the card band's `bg-body-tertiary` the eyebrow is `text-body-secondary` and Renew Your Loans a solid `btn-secondary` (outline: 4.12:1 in dark; [[designer-block-theme-surfaces]]).

## Why a loans table when the template lends nothing online?
- The table and the facts are the rules a library publishes (how many, how long, how often renewed), content the operator edits. Search the Catalogue, Renew Your Loans and the formats are links the operator points at the catalogue pages or the library system; no block implies a loan the site keeps ([[design-template-library]]).

## Why fixed heights on the shelf?
- On the canvas `.sd-wrap` sits between the shelf and each spine, so a height in per cent has nothing to resolve against ([[bootstrap-direct-child-canvas]]). The shelf is a grid (`grid-auto-flow: column`, `minmax(0, 1fr)`): any number of books fit a phone; heights are rem (`pg-library-mid`, `pg-library-low`). The shelf sets no height (one equal to the tallest book hid the board).

## Why are these not reused blocks?
- No block draws a shelf, reads a shelf mark or lists loan rules per format. `loyalty-stamps` is a stamp grid and `gym-hero` a pass; `hours-seasons` is a table of times, not of loans.

## Which general blocks does a library site place instead?
- Branches: `locations-cards`; hours: `hours-card`, `hours-seasons`; rooms: `locations-spaces`; floors: `locations-floors`; weekly story times and clubs: `booking-week`.
- Events: `events-cards`, `events-list`. Services and the digital library: `services-icon-cards`, `features-alt`. Joining: `steps-numbered`. Figures: `stats-icons`. Questions: `faq-categorized`. New arrivals stay the catalogue widget.

## Translation choices
- "Book", "At a time" and "Bars" mean something else in the shards, so the layers are Book Spine, Items at once, Barcode Lines. No apostrophe in a sample.

## How is it tested?
- `tests/designer_blocks_library_test.php` (Node runner `tests/helpers/designer_family_run.php`): the shared family checks; eleven spines, six formats, today's times; ten classes in order, one example; the table's heads; three drawings hidden; rules with no colour, motion, child selector or per cent height.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/designer-blocks-overview.md
