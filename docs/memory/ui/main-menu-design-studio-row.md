---
name: main-menu-design-studio-row
description: Main menu row 21, Page Design Studio: its Other entry holding the old Page Styles and Ads entries in a second-level panel, the gates, how Other's screens mark it, retired slots 16 and 17
---

## What is in row 21?
- Titled Page Design Studio (slot 21, the only row under Design, #8987). Its panel keeps the studio's own entries for role < 2, then a divider and Other (`bi-three-dots`, a chevron), last.
- Other opens a second-level panel, `menu_panel_21_other` (`pg_menu_other_markup()`, pure, `includes/fn/output.php`; `tests/menu_panel_test.php`), shut until opened: the former Page Styles row's entries in their order (Page Styles, Themes, Design Files, Find and Replace, Mass Edit, Menus, the region lists), then Ads and Create Ad after a divider. Back goes up to row 21's panel, as in System tools.

## Who gets row 21 and Other?
- Each entry keeps its gate (`main-menu-entry-gates.md`): the studio's entries and Other's design entries role < 2; Ads `ADS === true` and (role < 3 or an ad region the user may edit). Other is drawn only when one of its entries is there; row 21 when role < 2 or Other is.
- Below the designer (a manager, a role 3 user with an ad region, Ads on) row 21 holds Other alone, with Ads in it; the studio home stays in their Pages panel. Taken in #8987 as the step's own wording: the row is drawn for whoever may open one of its entries.

## How do Other's screens mark it?
- The ad screens, the styles, themes, design file, import, `legacy_to_designer.php`, migration, find and replace, mass edit, menu and region screens set `$active_menu = 21` and `$menu_other_here` in `output_menu()`'s switch.
- There the Other entry carries `active` and `aria-current="true"`, so the kept panel (`pgMenuPanel`, `main-menu-panels.md`) reopens on every one of them, also on a screen with no entry of its own. Row 21's `data-pg-menu-panel` names Other's panel (the row's `'opens'`, `pg_menu_row_click_panel()`): a click on the row lands in Other, Back leads to the studio's entries. No script or stylesheet changed for it.
- On the rail the click widens the rail over Other's panel like any panel.

## What happened to rows 16 and 17?
- Ads (16) and Page Styles (17) are no longer built; their slots are retired with 3, 13, 18, 19 and 20, never reused (`tests/menu_category_test.php`). Their `pg_menu_icon()` drawings `ads` and `styles` went with them.
- A pinned 16 or 17 needs nothing: `user.selected_appmenu_items_array` is only written (`user_pinned_app_update`, `includes/panel/dashboard.php`); nothing reads it and no screen calls the action, so a stored 16 or 17 pins nothing.

## What was left for the stylesheet?
- `backend.src.css` is a shared file and was not touched in #8987: the `.pg-mi-ads` and `.pg-mi-styles` rules are unused; the active Other entry has no visible mark of its own; the Turkish title "Sayfa Tasarım Stüdyosu" ends in an ellipsis at the 250px expanded width when the row is marked (semibold), its `title` carries the whole name.

Related: docs/memory/ui/main-menu-categories.md, docs/memory/ui/main-menu-panels.md, docs/memory/ui/settings-system-tools.md
