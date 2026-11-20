---
name: page-tab-link-counts
description: A page tab's tip on the Page Design Studio's page strip lists its outgoing, incoming and broken link counts, counted from the Prototype view's own data (tabCounts()); when, words, what shows none
---

## What does the operator see?
- Resting the pointer or the focus on a page tab, its tip (the tab's `title`) gets three lines under the name and marks: `3 outgoing links` / `1 incoming link` / `No broken links` (`3 giden bağlantı`, `1 gelen bağlantı`, `Kırık bağlantı yok`). Each count has three keys: none, one, many.
- No counts: before the read answers, after a failed read, in an e-mail design (no Prototype view), in the component editor, on a page without content rights (`sd-tab-locked`). A link is never called broken without the read.

## What is counted?
- As the Prototype view's Links tab counts the page's frame (`listOf()`, `brokenOf()`, designer/prototype-list.md), tested equal: `out` every link of the page, a classic menu's or region's items each, links without a target and outside addresses too; `in` the links of other pages and of shared components that open the tab, its link to itself not counted; `broken` its own broken links.
- A shared component's links are its own frame's: a header placed on every page adds nothing to a page's `out`, and its link to Home counts once in Home's `in`.

## Where does the data come from?
- `PGProtoHotspots.tabCounts(marked)` (pure, `assets/js/pg_proto_hotspots.js`) over `mark()` of `design()` of every tab (`_sdProtoTabs()`), shared components fetched as the view fetches them (`prefetchShared()`), the full `prototype_map` read (menus and regions too). No second walk, no endpoint, no schema.

## When is it counted?
- `_sdTabLinksSoon()` on `mouseover` / `focusin` of a tab in `#sd-tabs-list`, run 250 ms later (`SD_TAB_LINKS`). It counts again only after `render()` or `_pgTabsRender()` marked it stale (`_sdTabLinksStale()`), or once the answer is older than 60 s; one run at a time, a later run drops an earlier answer.
- The tip without counts is kept on the tab as `data-title`; an answer sets every tab's `title` in place (`_sdTabLinksPaint()`), the strip is not drawn again. A strip drawn later carries the last counts (`_sdTabLinksTitle()` in `_pgTabHtml()`).
- A native tip already shown keeps its text until the pointer comes back.

## Which tests cover it?
- `tests/proto_hotspots_test.php`: `test_proto_hotspots_tab_counts` (the counts, equal to the links panel's, from trees and the read), `test_proto_hotspots_view_tab_counts` (the wiring, the words).

## Which PR, and what was asked?
- Architect (2026-11-19): the Visual Page Editor's links in three steps without picture approval: canvas marks (designer/canvas-link-marks.md), the P key back to the Prototype view (designer/prototype-back-key.md), link counts on a page tab; any need for a schema change or an endpoint goes to the architect first.
- DX3, PR #10640 (2026-11-20). Before it the counts showed only in the Prototype view's Links tab.
