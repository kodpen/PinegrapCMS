---
name: translations-screen-frame
description: Translations screen frame (TS1): head with language shares, tabs Texts/Activity/Glossary, Scan for new texts and Translate pending, the rail, the filter tiles, the tool line
---

## What does the head hold?
- The language button (fill) whose menu lists each target language, "Served" or not (`translations-screen-not-served.md`), with its code and the share of the site's texts it has a translation for (`pg_tr_screen_share()`: never 100 while a text is left, never 0 once one is translated).
- The tabs "Texts", "Activity", "Glossary" (`pg-seg`, Bootstrap tabs on the screen). `tab=activity|glossary` in the address opens one; the script rewrites the address when a tab is chosen; a glossary form posts `tab=glossary`, so `pg_tr_screen_view_query()` brings its answer back there.
- "Activity" counts the language's updates still open and those that left failed texts; "Glossary" counts its terms.
- "Translate pending" (fill, `#pg_tr_update`) with the scope's pending count, then "Scan for new texts" (quiet, `#pg_tr_scan`), then "More" (`bi-three-dots`) with today's items, "Clear translations" in danger ink.

## What do the two jobs do?
- "Scan for new texts" calls the `update` action with the engine `manual`, in turns: `pg_tr_update()` opens no job for an engine of kind `panel`, so it scans and lists only, and the Site Log says "were scanned". The toast says "Scanned: {var} text(s) are pending." and the screen reloads.
- "Translate pending" is today's update: scan, then the language's engine. With the engine "By hand" it is not drawn and "Scan for new texts" is the fill.
- Before TS1 one button, "Update translations" / "Update this page" / "Update these texts", did both under one name.

## Where are the engine and its notes?
- On the automation line under the head, changed in the dialog "Automation for <language>"; the progress card takes the line's place while an update runs (`translations-screen-automation.md`).

## What is the rail?
- One `pg-card` (`#pg_tr_rail`), 300 px (240 px under 1200 px, above the list under 992 px), sticky and scrolling on its own. "Find a page" narrows the page rows as typed (`data-pg-tr-page`, client side; "No page matches the search."). Then "All texts" (was "All pages"), "Pages" with the reviewed count, the pages with count, verdict icon and a 3 px bar, "Other texts" with the groups.
- Every row is drawn by the closure `$rail_row`; the current one is `.active` with `aria-current="page"` and a 3 px accent edge.

## What are the filter tiles?
- Links as `pg-card pg-figure` in `.pg-figures`, six of equal width in one row down to 768 px (`translations-screen-narrow.md`): All, Pending, Machine, Reviewed, Suspicious, and "Keeping pages out of search" while the language's index policy is "reviewed". Each shows the scope's count; Suspicious and the blocking count are queried for the scope (the blocking one only while its tile shows).

## What is the tool line?
- "Search in the texts" (the key "/" puts the cursor there outside a field), "Draw the waiting pictures" in the Images group, "Approve all" quiet with its count. Under it the page's index note, the group notes, the bulk line and the rows (TS2 redraws the row).

## What do the other two tabs hold?
- "Activity": the table of the language's updates and an update's drawer (`translations-screen-activity.md`). "Glossary": the table of terms with its search (`translations-screen-glossary.md`).

Related: docs/memory/ui/translations-screen-drawing.md, docs/memory/ui/translations-screen-today.md, docs/memory/ui/look-translations-screen.md
