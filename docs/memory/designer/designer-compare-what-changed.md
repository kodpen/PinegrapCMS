---
name: designer-compare-what-changed
description: Compare's "What changed" column joined to the pictures (CL3, #11707), and the three rows that open Compare with the live page: tab menu, command palette, Publish sheet icon
---

## What opens "Compare with the live page"?
- The page's tab menu, after "Preview as prototype", for a page saved here (`page_id > 0`, not picked from another design); the command palette's row after "Preview" for the open page. No bar button, no key.
- The Publish sheet: an icon button after "Changed" on a row with `compare` (`_pgPublishRows()`: on the site, changed, saved); `bridge.compare(key)` opens the window. A draft's row has none.
- All open `_sdCompareOpen({ pageId })`: the live page against the draft. Nothing is written, so view mode has it.

## How does it open over the Publish sheet?
- A modal dialog makes the rest of the page inert, so the window is placed inside the open modal dialog (`modalHost()`), which gets `sd-cmp-host` (no transform, so the fixed overlay covers the screen). Escape is caught in the capture phase and the dialog's `cancel` is prevented: it closes the window only. Focus goes back to the button that opened it; the sheet keeps its checks.

## What is the list?
- A 300 px column right of the pictures: "What changed", the count of rows, then `PgCompareChanges.rows()` with the window's `_sdT()` texts. A row: icon by kind (`PgCompareLook.icon()`), the sentence, in grey "in {place}". `new`, `same` and `unreadable` show their sentence in its place; it is drawn once the two trees are read, before the pictures.
- A chosen row: the handle goes to 0 for an added element, 100 for a removed one (`handleFor(sideOf(row))`); the pictures carrying its place get a blue outline with the sentence as its tag; the two pictures are shifted (`translate`) so the element stands at the same height (`align()`), and the scroll box goes there.
- A text row opens into "Before" / "Now" (six lines at most). A row of several elements opens into their names (`nameOf()`: "Text “Full”"); a name outlines its element only.
- Elements are found by `data-pg-node` (the compare render), else by id. One not found outlines nothing; the row stays. Outlines are redrawn on every layout, scrolled only when chosen.

## Why does a picture carry the element ids?
- `compare_render` draws through `pg_seo_check_draft_render()`, which loads `seo.php` itself: without it a standard page was not renderable, every picture fell back to the editor's markup and a chosen row outlined nothing (#11855).

## How is it used by the keyboard?
- The window is `aria-modal`: it takes the focus on its open tab, Tab and Shift+Tab go round its visible controls, Escape closes it and gives the focus back (#11855).

## And a narrow window?
- The line holds the two names only (no grey detail); the foot's sentence takes a line of its own over the buttons.
- Under 768 px the list is hidden; the foot's "What changed · 7" swaps the pictures for it and "The pictures" swaps back; choosing a row swaps back and shows its place.

## What differs from the drawing?
- One look or other change is the reader's own sentence ("Button changed"), not "Other changes (1)": the list's sentences are the reader's. A move into another parent uses `bi-arrow-return-right` (the drawing has only up and down).
