---
name: designer-home-screen
description: Page Design Studio home (view_system_styles.php): the head row, the first visit's three starts, the site's design shown large, tiles, the grouped menu, rename (designer/design_rename)
---

## Which colours may the screen use?
- Greys plus the panel's primary as the one accent; previews keep theirs (`designer-home-colours.md`, tooltips there too).

## What does the home show?
- One head row (`pg_designer_home_head()`): "Designs" with the count and the sentence of what a design is, "Find a design" from seven designs (accent and dotless-i folding; it filters the tiles, the large card stays; nothing found: "No design matches “…”." and "Clear the search"), "Shared components and system widgets" (ghost), "New design" (filled, the window on Blank). Below designer: no "New design", one grey line saying designers create designs.
- No design yet: the first visit (`pg_designer_home_first_visit()`): "Start your first design", the sentence, three cards Blank, Template, "Bring your own" (`pg_designer_home_first_starts()`), each opening the New design window on its start (`new-design-window.md`); no templates: Template at 50% with "There are no templates."; the three steps as one grey line.
- `pg_designer_home_featured_id()` picks the design shown large: the only one, or the single design holding the home page; otherwise all are tiles.
- The large card: preview in a browser frame, name and ⋯, the state line, the home-page line, four facts in one row (Framework, Template, Theme, Last modified; its tooltip says who), one row of page chips then "+n more" (the script keeps as many as fit, `fitChips()`; opens in place), the buttons Edit (filled), Add page, View on site (ghost, arrow after), Put on the site while drafts exist.
- Tiles (`.sdh-tiles` grid: four a row from 1400 px, three from 1000, two from 560, below 560 px a list, see `designer-home-narrow.md`): preview, name and ⋯, one line of state mark and words, page count and time at the right; a design with no page shows "No pages yet" in the picture's place.
- No hint line: right click and long press still open the menu; the ⋯ button is what a person sees.

- Architect (2026-11-26): the start cards leave a home that has a design; "New design" is the one way in. No `workflow/DECISIONS.md` entry binds them (#11920).

## What do the pictures show?
- Real pages, drawings as fallback: `designer-home-pictures.md`.

## Where is the data from?
- `pg_designer_list_designs()`; `pg_designer_list_pages()` (home first; `pg_designer_page_access()` hides 'hidden' pages, marks 'locked' ones for content roles); `pg_designer_list_folder_counts()` (folders using it as default, for the delete warning).
- `pg_designer_list_status_cell($d)` is the status block (badge + one line + data-pages/drafts/home/offable); `designer/design_publish` answers it as `cell`. Counts from `pg_designer_design_draft_counts()`, state from `pg_designer_design_state()` (`empty` / `live` / `draft` / `mixed`; `offable` = pages that can go off: not drafts, not home).
- Put on / take off the site: `designer/design_publish` → `pg_designer_design_set_draft()` moves every page (the home page stays); refused while the design is open in the editor, whose next save would put its tabs' state back (`page-drafts.md`). The shared components panel above the list: `shared-components-panel.md`.

## How does the menu work?
- `designer-home-menu.md`.

## How does rename work?
- `api.php designer/design_rename` -> `pg_designer_rename_design()` (includes/fn/designer.php): designers only, visual designs only, name cleaned by `pg_designer_design_name_clean()` (one line, 100 chars), a name another style has is refused, refused while the design is open in the editor (its save would write the old name back). Logged as "{var:1} was renamed to {var:2}".

## Files and tests?
- `includes/designer_screen.php` (home functions), `assets/js/designer_home.js`, `assets/css/designer_home.css` (also the template gallery CSS), the page loads `pg_live_picture.min.js`. Tests: `tests/designer_home_test.php`.
