---
name: designer-home-script-rules
description: The Page Design Studio home script's pure rules (window.PgDesignerHome): chips after Put on the site, a refused rename, the focus after a delete under a search, ?start= read once
---

## Which rules does `assets/js/designer_home.js` keep apart, and where are they tested?
- `window.PgDesignerHome` (top of the script, before it stops on a page without `#sdh-config`): `chipDraft()`, `renameStep()`, `nextShown()`, `withoutStart()`. Tests: `tests/designer_home_js_test.php`, run in Node.

## How are the page chips painted after "Put on the site" or "Take off the site"?
- `designer/design_publish` answers `draft_ids`, the design's pages still drafts after the move; a chip is a draft when its page is listed. A draft a prototype link holds stays a draft, so its chip keeps the dashed outline. An answer without the list paints every chip the way the move went.

## What happens to a refused name in the rename box?
- Enter tries again; leaving the box with the name just refused puts the old name back instead of asking again and taking the focus back (a phone has no Escape).

## Where does the focus go after a design is deleted under a search?
- To the next design the search shows, else the one before it; the search runs again, so "No design matches" comes when the last match is gone.

## Does `?start=` open the New design window again on a reload?
- No: the script reads it once and drops it from the address (`history.replaceState`), keeping `from=pages` and the rest; the reload after deleting the large design no longer opens the window.

Related: docs/memory/designer/designer-home-screen.md, docs/memory/designer/new-design-window.md, docs/memory/designer/designer-home-menu.md
