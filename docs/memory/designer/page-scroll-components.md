---
name: page-scroll-components
description: The "Back to top" and "Scroll progress" palette components (DO21, #11564): markup, markers, pg_page_scroll.js and when a page gets it, rules, canvas and Preview, bytes
---

## Where are they and what do they draw?
- Components tab › Bootstrap, after Vertical Stack: "Back to top" (`componentType: 'back-to-top'`) and "Scroll progress" (`'scroll-progress'`); builders in `createFromPalette()` of `style_designer.src.js`, hover cards `c:back-to-top` / `c:scroll-progress` with drawings `to-top` / `scroll-progress`.
- Back to top: `<a href="#top" data-pg-to-top aria-label="Back to top">` with `btn btn-primary rounded-circle shadow position-fixed bottom-0 end-0 m-4 … pg-to-top` and a `bi-arrow-up` icon.
- Scroll progress: `div.progress.position-fixed.top-0.start-0.w-100.rounded-0.bg-transparent.pg-scroll-progress`, `aria-hidden="true"` (decoration) and `data-pg-scroll-progress`, holding a `progress-bar` whose `width:0%` is the one inline value (Bootstrap's own data).

## Which script runs them, and on which page?
- `assets/js/pg_page_scroll.js`, plain ES5, deferred: added by `pg_page_scroll_inject()` (`includes/fn/designer.php`, expression `pg_page_scroll_pattern()`) in `get_page_content.php` after shared components and widgets are drawn in, only when the HTML holds `data-pg-to-top` or `data-pg-scroll-progress`; never in an e-mail. A page without either gets no byte more.
- The Preview's `_SD_PAGE_SCRIPTS` row uses the same expression (`tests/designer_page_scroll_test.php` compares them).
- The script hides the button (Bootstrap `invisible opacity-0`) until the page is scrolled half a window (at least 200 px), scrolls to the top on a press (instantly under reduced motion), and sets the bar's width to the scrolled share. One passive scroll listener, work once a frame.
- Without the script the button is always shown and `#top` still leads to the top.

## Why does the canvas not load it?
- So both stay in view to be selected and edited: the button would be hidden on an unscrolled canvas.

## Which rules do they bring?
- On drop, to User Styles (`_SD_BLOCK_CSS`): `pg-to-top` (3 rem square, `z-index: 1035`, opacity fade, none under reduced motion) and `pg-scroll-progress` (`--bs-progress-height: .25rem`, `z-index: 1035`, no width transition). 1035 is above a fixed navbar (1030), below offcanvas and dialogs.

## How many bytes?
- Page without them: 0. With both: 249 + 209 bytes of markup, 81 bytes of script tag, `pg_page_scroll.js` 4,085 bytes (1,681 gzipped), 338 bytes of rules in the design's stylesheet.
