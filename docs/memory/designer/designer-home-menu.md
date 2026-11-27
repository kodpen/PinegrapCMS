---
name: designer-home-menu
description: The Page Design Studio home's menu on a design or a page chip: how it opens, its rows in groups without icons, its texts
---

## How does the menu open (`assets/js/designer_home.js`)?
- Right click on a card or a page chip, its ⋯ button, a 550 ms long press, the menu key or Shift+F10. A focused card: Enter opens, F2 renames, Delete deletes. The card or chip whose menu is open wears the focus ring.
- Words only, in groups with dividers; a row that does not apply is left out. Design: Edit, Open in a new tab, Add page | View on site, Put on the site, Take off the site | Rename, Turn into a template (`designer/template_from_design`, the dialog `pg_designer_home_template_dialog()`) | Delete in rose. Page: Edit (`&page=`), Open in a new tab | View on site, Copy address.
- "Take off the site" is in the menu only; the large card shows "Put on the site" while drafts exist.
- After a template is made, choosing the Template start reloads the page with `?start=template` (the gallery is printed with the page).
- Texts: `pg_designer_home_texts()` in `#sdh-config`; the script has none.
- The menu is a `.dropdown-menu` not opened by Bootstrap: keys are taken on the window's capture phase (`ui/own-menu-dropdown-keydown.md`).

Related: docs/memory/designer/designer-home-screen.md
