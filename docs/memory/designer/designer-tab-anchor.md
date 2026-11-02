---
name: designer-tab-anchor
description: A link or search result to an element inside a Bootstrap tab not shown opens that tab and scrolls to it: pg_bs_tips.js on load and hashchange, pg_search_highlight.js reveal() (#4823).
---

## Why does a page with tabs get pg_bs_tips.js?
- Bootstrap opens no tab from the address: a link to an element in a pane not shown (the events template's speakers to `programme#session-…` on day 2) opened the page on the first tab at the top (#4823). On load and on `hashchange` the script shows every closed pane around the anchor's element through `bootstrap.Tab` (toggle found by `data-bs-target` or `href`), then `scrollIntoView`. `pg_search_highlight.js` `reveal()` shows the first mark's pane the same way, for a search result on a page without the anchor.

## How was it checked?
- The marker is in `tests/designer_page_scripts_test.php`. The scripts themselves ran in Chromium on a static page with Bootstrap 5.3.8: anchor on load, anchor by `hashchange`, `?highlight=` with and without the anchor, the last without `pg_bs_tips.js`.
