---
name: legacy-design-theme
description: The theme file of a design moved from a classic style: the activated theme the classic head printed, on the site, the canvas and the Theme file hint; the dry run's warning (LG1, #8694)
module: legacy_design_theme
---

## Which theme does a visual design load?
- Its own Theme file (Settings → Theme → Theme file, `style.theme_id`), printed before `</head>` after all of its stylesheets, as before.
- With none chosen, a design moved from a classic style loads the site's activated theme, which its classic head printed at `<stylesheet></stylesheet>`: `activated_desktop_theme`, on a mobile request `activated_mobile_theme`, else the desktop one. Any other visual design loads none.
- None either when one of the design's enabled linked stylesheet rows (not a framework row) names that file: `{path}name`, the same under `OUTPUT_PATH`, a query string ignored, the name without case. A row kept to some pages counts only on those pages.
- Symptom it fixed: a moved style with no theme of its own (every style of the starting sites) lost the activated theme and its pages opened unstyled.

## How is a moved design recognised?
- By the `legacy` flag of its framework key alone (`pg_legacy_design_from_classic()`, LG3): the move writes `legacy`, the 11.0.187 step gave it to the styles moved before. `custom` designs load no classic theme. Why no component check: `legacy-design-framework.md`.

## Where is it?
- `includes/fn/legacy_design_theme.php`: `pg_legacy_design_theme()` (pure: own, classic, device, the two activated themes, rows, output path, page), `pg_legacy_design_rows_link()` (pure), `pg_legacy_design_classic_theme()` (reads the rows above), `pg_legacy_design_theme_js()` for the editor. `pg_classic_style_component_category()` and `pg_classic_style_site_file_name()` moved here from `classic_style_design`, so a page render does not load the importer.
- `get_page_content.php`: the system-style branch of the theme block prints it.

## What does the editor show?
- `sdDesign.themeFile` (`{id, name, url}` of the desktop theme, or null). `_sdThemeInUse()` gives the chosen Theme file, else that one with `inherited: true`; `_sdActiveTheme()`, the canvas link `sd-custom-theme`, the frame rows of the code view and the Themes tab head all read it.
- Under the Theme file select, `#sd-theme-file-status`: "In use: …" while none is chosen, "With no file chosen here the design loads …" once one is. Empty for any other design.

## What does the dry run warn?
- `pg_legacy_style_css_after_theme()`: the classic head links a stylesheet or has a `<style>` after `<stylesheet></stylesheet>` (comments and scripts skipped, the body not read). The design loads the theme after all of its stylesheets, so where both set the same rule the theme now wins. `pg_ltd_plan()` passes `theme_after` (the theme's name); `pg_ltd_plan_style()` adds the warning.
- In the starting sites two heads per site do it: home-product and home-music link their font after the theme.

## What did the sandbox move of home-1 show (turkish_default)?
- Classic: bootstrap.css, themify-icons, flexslider, lightbox, ytplayer, livesite.src.css, then theme_modern.css. Moved without this: no theme, the page in the browser's default font. Moved with it: theme_modern.css, the theme's fonts and dark bars back.
- Still missing after the bulk run: every other file of the classic head. The run marks the design but writes no stylesheet or script rows (`style_custom_css` stays empty), and the editor opening the moved design lists only Bootstrap Icons and jQuery, so the menu, slider and icons are unstyled. No step of `plans/legacy-group-design-steps.md` names it yet.
- On the canvas the theme link is added before the asset rows' links; on the site it is last.

## Tests?
- `tests/legacy_design_theme_test.php`: own file, activated desktop and mobile, a design not moved, rows that link it and rows that do not, CSS after the tag, the starting sites' heads, the dry run's warning.
