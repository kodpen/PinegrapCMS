---
name: place-map-steps
description: Plan place-map, steps MP1 to MP6: the census of providers and consent, "Find from the address" on a record, the endpoint and the block, the map and the switch, near me and a typed address, close-out
---

## Which steps are there?
- MP1 (S, no code) Census, done (PR #9980): `plans/place-map-census.md`, `-census-2.md`, `-census-3.md`.

- MP2 (M) after MP1: coordinates on a record, done (PR #10000; `platform/place-coordinates.md`). No schema.
  - Existing files: the record's form and the collection's record list in the panel (`edit_submitted_form.php`, `view_submitted_forms.php`: the action and the bulk action). Other plans' steps hold them: the step names the clash.
  - New: `includes/fn/place_map.php` (the fields a block names, the lookup, its bound and its answers), the coordinates calls under `includes/geo/geocode/`, `tests/place_map_test.php`.
  - Tests, red first: a found address filling both fields; an address not found saying so and writing nothing; the provider's "wait" passed on; the bulk bound and the count left; a user without the forms right refused; no call without a press.
  - Pictures: the action on the form and its answers, the bulk action. To the owner.
  - Features: X4 to X7.

- MP3 (M) after MP2: the endpoint, the block and its list.
  - Existing files: `get_page_content.php` (the script for a page whose markup carries `data-pg-map`), `assets/js/style_designer.src.js` (one row in `_SD_PAGE_SCRIPTS`; held by other open PRs, the step names the clash).
  - New: the root file `places.php`, `assets/js/pg_map.js` (the list, its search, the cards; no map yet), the block family's file with its test and its canvas picture.
  - Tests: the endpoint giving only what the list page shows a visitor (a draft, an unfinished record and a private folder left out); the cut at 500, with "Near me" not drawn and the designer told both; its origin and rate checks; the script's distance and ordering under Node.
  - Pictures: the block as a list, the search, a card, a phone. To the owner.
  - Features: X1, X2, X3, X8, X10, X15, X16.

- MP3b (S) after MP3: the block's field pickers, done (PR #10535; `platform/place-list-fields.md`). Features: X2's pickers.

- MP4 (M) after MP3b: the map and the switch, PR #MP4PR (`designer/designer-blocks-map.md`). Features: X9, X13, X14. Pictures to the owner; not marked ready before his look.

- MP5 (S) after MP4: near me and a typed address. Existing files: none. In the script and the endpoint. Tests: the lookup's rate limit; nothing typed kept. Features: X11, X12.

- MP6 (S) close-out: every X number checked against the code with its PR; `designer/designer-blocks-map.md` and `platform/place-coordinates.md`; this plan's files and the census deleted and its line in `plans/more-features.md` removed.

## Which steps wait for each other?
- Each waits for the one before it.
