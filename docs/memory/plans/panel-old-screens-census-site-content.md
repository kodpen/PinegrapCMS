---
name: panel-old-screens-census-site-content
description: Census LM1b, content screens against look A: pages, comments, short links, pop-ups, 404 log, accessibility, page tests (S1); menus, regions, ads, styles, themes, design files (S4)
---

## How is a line read?
- `file`: lines / marker lines / `style=` / fields / cards → estimate; method in `panel-old-screens-census-site.md`. All are on their look width already.

## S1: which pages and comments screens, and what is old? (620; 12,230 lines)
- Pages list `view_pages.php` 418 / 7 / 0 / 1 / 0 → 21: device toggle, "New page" pill and bulk buttons as Bootstrap buttons, a tools menu of its own.
- Create Page `add_page.php` 599 / 2 / 2 / 6 / 6 → 26.
- Edit Page `edit_page.php` 1,872 / 16 / 3 / 16 / 12 → 72: nine `btn-text` links in the toolbar (layout, form, list view, item view, shipping and billing forms).
- `includes/forms/page_form.php` 4,401 / 47 / 44 / 102 / 9 → 269, the largest: 102 fields per page type, 44 inline styles (31 `position`, 11 `text-align`).
- Comments list `view_comments.php` 232 → 12 (toolbar only); Edit Comment `edit_comment.php` 687 / 12 / 8 / 7 / 6 → 38, with a 28-line `<style>`; `includes/forms/comment_form.php` clean.
- Short links: `view_short_links.php` → 14, `add_short_link.php` → 5, `edit_short_link.php` → 7, `includes/forms/short_link_form.php` 860 / 6 / 4 / 7 / 6 → 32, `import_short_links.php` 206 / 6 → 16 (upper-case card titles).
- Pop-ups: `view_auto_dialogs.php` → 13; `add_auto_dialog.php`, `edit_auto_dialog.php`, `includes/forms/auto_dialog_form.php` clean.
- 404 log `view_not_found_log.php` 319 / 12 → 28 (seven Bootstrap buttons); Accessibility `view_accessibility.php` 214 / 10 / 0 / 0 / 4 → 30, two own tables; Page tests `page_experiments.php` 450 / 15 / 2 / 5 / 6 → 37, upper-case titles, an own table.
- `edit_pages.php` (page bulk edit endpoint) draws nothing.

## S4: which menu, region, ad and design screens, and what is old? (430; 9,280 lines)
- Lists, toolbar only (→ 12 to 15 each): `view_menus.php`, `view_regions.php`, `view_ads.php`, `view_styles.php`, `view_themes.php`, `view_design_files.php`.
- Menu items `view_menu_items.php` 446 / 14 / 6 / 0 / 3 → 32: not on the table driver (#9230), its own tree table.
- Forms: `includes/forms/menu_form.php` → 31, `menu_item_form.php` → 34, `common_region_form.php` → 23, `designer_region_form.php` → 20, `login_region_form.php` → 23, `dynamic_region_form.php` → 17, `ad_form.php` → 31, `ad_region_form.php` → 23, `custom_style_form.php` → 24, `design_file_form.php` → 20.
- Screens beside them: `add_theme_file.php` 656 → 23, `edit_theme_file.php` 304 → 17 (132-line `<style>`, no literals), `add_design_file.php` → 9, `edit_design_file.php` → 11, `edit_common_region.php` → 3; the other add and edit wrappers (menu, menu item, regions, ads, custom style) are clean.
- The work is mostly field widths and card lines in the shared form files; few literals.
