---
name: designer-home-screen
description: Visual Page Editor home (view_system_styles.php): drawn start cards, the site's design shown large, tiles, the right-click menu, rename (designer/design_rename)
---

## What does the home show?
- Top: four starts as cards with a drawing each (`pg_designer_home_start_card()`, `pg_designer_home_drawing()`): blank canvas, ZIP into tabs, pasted markup, and the template card fanning three real template thumbnails (`pg_design_thumb_svg()`). Designers only; below that level a one-line alert. Phones: two by two, title only.
- Below: no DataTable (most sites have one design). `pg_designer_home_featured_id()` picks the design shown large: the only one, or the single design holding the home page; otherwise all are tiles. The large card has the preview in a browser frame, status block, facts (framework, template, theme, last modified), page chips (12, then "+n more") and at most four buttons.
- No designs: a dashed box with the three steps (start, build, publish). More than six designs: a name search (accent and dotless-i folding).

## Where is the data from?
- `pg_designer_list_designs()` as before; `pg_designer_list_pages()` (home first, then page_id; `pg_designer_page_access()` hides 'hidden' pages and marks 'locked' ones for content roles); `pg_designer_list_folder_counts()` (folders using it as default, for the delete warning).
- `pg_designer_list_status_cell($d)` is the status block (badge + one line + data-pages/drafts/home/offable); `designer/design_publish` answers it as `cell`.

## How does the menu work? (assets/js/designer_home.js)
- Right click on a card or a page chip, the card's ⋯ button, a 550 ms long press, the menu key or Shift+F10. A focused card: Enter opens, F2 renames, Delete deletes.
- Design: edit, new tab, add page (`&new_page=1`), view on site, put on / take off the site, rename, turn into a template (`designer/template_from_design`, the panel dialog `pg_designer_home_template_dialog()`), delete. Page: edit (`&page=`), new tab, view, copy address.
- "Take off the site" is in the menu only; the large card shows "Put on the site" while there are drafts.
- After a template is made the template card reloads the page with `?start=template` (the gallery is printed with the page).
- Texts come from `pg_designer_home_texts()` in `#sdh-config`; the script has none of its own.
- The menu is a `.dropdown-menu` not opened by Bootstrap: keys are taken on the window's capture phase (docs/memory/ui/own-menu-dropdown-keydown.md).

## How does rename work?
- `api.php designer/design_rename` -> `pg_designer_rename_design()` (includes/fn/designer.php): designers only, visual designs only, name cleaned by `pg_designer_design_name_clean()` (one line, 100 chars), a name another style has is refused, refused while the design is open in the editor (its next save would write the old name back). Logged as "{var:1} was renamed to {var:2}".

## Tooltips?
- Only icon-only controls get one, compact (`no-popover` + `data-bs-toggle="tooltip"`); see docs/memory/ui/panel-hint-tooltip-or-popover.md. The template gallery's labels use the same; its details card is a trimmed popover (`sd-tpl-info`).

## Files and tests?
- `includes/designer_screen.php` (home functions), `assets/js/designer_home.js`, `assets/css/designer_home.css` (also the template gallery CSS). Tests: `tests/designer_home_test.php`.
