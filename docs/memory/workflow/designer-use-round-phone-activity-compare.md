---
name: designer-use-round-phone-activity-compare
description: Designer use round UR6 (#11855): "Preview on a phone", History's Activity tab and "Compare with the live page" used in a browser as a first-time person; faults found, fixed and left
---

## What was used, and how?
- On a `setup_sandbox.sh` instance with Playwright: the "Pinegrap'a merhaba deyin" template saved (37 pages), all published, the home page edited and published twice more, so the Activity tab had 97 rows over two pages of fifty.
- Phone preview: the Preview menu row by keyboard, Copy link, Escape, the window opened again (the same link), the "Phone preview" chip in Share prototype, the phone link at 390 px with touch, its page list.
- Activity: both tabs by arrows, the three choosers, a kind narrowed to one row, the page search, paging to "This is where the design's activity begins.", a version row opening Compare and closing back.
- Compare: from an Activity row, the command palette (Ctrl+Shift+P), the Publish sheet's icon; slider, Side by side, Phone width, a chosen row, the narrow list swap.
- 1440 and 390 px, the panel light and dark, keyboard alone for the three windows.

## What was found and fixed in #11855?
- Phone window, light panel: its title line and "End now" were dark on dark and "Copy link" white (no `data-bs-theme="dark"`); below 768 px it did not fill the screen and its body had no padding, the info line touching the edges.
- Phone window: closing left the focus on the page body; it now goes back to the Preview button.
- Phone view: no focus ring on the dark line's buttons and list rows.
- Activity, page chooser: Enter in its search chose nothing; after any choice the focus fell to the body (the chooser is disabled while the list is read); the search field was 4 px corners and a 3 px ring, now 30 px high, corner 9, the chrome's focus.
- Compare took no focus: Tab went on through the panels under it. Now `aria-modal`, focus on the open tab, Tab kept inside.
- Compare at 390 px: the side line ran off the window with its grey detail; the foot's sentence and three buttons wrapped into three lines.
- Compare's server pictures were never drawn: `compare_render` answered `html: ''` for every page, as `seo.php` was not loaded and a standard page was "not renderable"; the fallback picture has no `data-pg-node`, so a chosen row outlined nothing. Test first (`tests/seo_draft_test.php`).

## What was left, and why?
- The page bar at 1440 px: "Önerilen gruplar" overlaps the presence avatars and the "Tıklama" chip; and the signed-in person's own avatar shows twice. Not these features' parts.
- The cleanup, move and style guide windows build their dialog the way the phone window did (no `data-bs-theme`): likely the same light-panel fault; seen in code only, their features' rounds.
- Activity sentences name pages by their address (`anasayfa`), Compare's head too; the page list on the phone by title. A wording choice for the reader.
- Compare's side choosers on a phone cut the date ("26.11.2026 1"): native selects.
- An empty design never shows the Activity empty state once saved: "created the design" is its first row.

## What could not be run?
- The phone link on a real phone and its redraw after a save over time (the hour's stop, "No connection"); the team link state (no page for signed-in visitors on a public start); "End now" and a link run out while open; the amber unsaved line (typing into the canvas did not stick in the script).
- Compare on a page never on the site (`new`): every template page was published first.
