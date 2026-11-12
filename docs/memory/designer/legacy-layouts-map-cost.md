---
name: legacy-layouts-map-cost
description: Mapping a page type's output per request against converted layout copies (LL1 census, #8968): measured cost, what each reaches, the proposal sent to the architect
---

## What does mapping at output cost?
- `pg_lbs_html()` over rendered markup: 0.8 ms for 100 KB, 2.2 ms for 300 KB (PHP 8.3, the largest starting layout with its PHP taken out, 20 runs). The largest layout file is 145 KB of source; a cart's output is smaller.
- Paid only on a page of a `bootstrap5` visual design whose system region draws a page type; classic styles and Legacy designs skip it and stay byte for byte.

## What does each way reach?
- Map at output: the template, the page's file, a hosted site's generated layout, the database layouts of form views, the 2189 tags holding PHP, the canvas preview. Nothing written, so a page moved back to Legacy (LB5) gets its old output at once. It also renames visitor and designer content inside the region.
- Converted copies (a second file per page, e.g. beside `data/layouts/<id>.php`, chosen when the page's design is `bootstrap5`): no cost per request and a designer sees 5.3.8 names in the layout code tab. But a text pass misses the tags holding PHP, the database layouts need a column (no schema in this plan), two files of one page drift when either is edited, and the templates need a second set in the product.
- Both: map at output for everything, plus a converted copy written when a designer edits the layout on a Bootstrap 5.3.8 design, so the code tab shows 5.3.8 names; the output map then finds nothing to rename in it (a second pass changes nothing).

## What was proposed?
- Map at output first (LL3 on the join of [[legacy-layouts-output-join]]), with a layout variant of LB2's map: no theme-name rule, the ambiguous names left to LL2's tests. Converted copies only later, if the code tab's names matter. Asked on #8969; LL2 waits for the answer.
