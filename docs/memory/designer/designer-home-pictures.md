---
name: designer-home-pictures
description: The Visual Page Editor home's and Choose a Template's pictures: a design's real page, templates' home pages, the theme picker that redraws them, the drawings as the fallback
module: design_previews
---

## What does a design's card show?
- A design's large card and tile: the page the card's Edit opens (home first, else the first page the operator may open) as a first-time visitor sees it, `design_preview.php?page_id=` in a live picture (`pg_designer_list_thumb($d, $open)`, `design-preview-address.md`). Until it arrives, or when it cannot be drawn: the template's drawing in the design's theme, else the blank outline. No page to open: the blank outline alone. The browser bar's address stays.

## What does Choose a Template show?
- Choose a Template: each card the template's home page (`&still=1`: no script), the version badge on it; a template with no page a preview can show keeps its drawing. "Preview" (an `<a target="_blank">`) opens the tour in the card's theme; "Use this theme" is gone (template-previews plan, owner 2026-11-05).
- Picker (`pg_designer_template_theme_picker()`): first choices "Template's theme" / "Template's palette" (`@template`), checked: each card shows and opens in the theme its file names (nothing named: plain Bootstrap). Another choice sets each card's wanted address; 300 ms after the last change `PGLivePicture.set()` redraws, cards in view first. Use This Template always carries `&look=&palette=` (empty = plain). A palette from a theme file is not in `pg_design_palettes()`: the pictures drop it, Use carries it.
- Twins: `pg_designer_template_card_theme()` / `theme()`, `pg_designer_template_theme_query()` / `themed()`; the PHP address equals the script's, so the first sync reloads nothing.

## Where is it?
- `includes/designer_screen.php`: `pg_designer_list_thumb()`, `pg_designer_design_picture_src()`, `pg_designer_list_drawing()`, `pg_designer_template_card_theme()`, `pg_designer_template_picture_src()`, `pg_designer_template_theme_query()`, `pg_designer_template_modal()`, `pg_designer_template_theme_picker()`; frameless boxes in `assets/css/designer_home.css`. Tests: `tests/designer_home_test.php`.
