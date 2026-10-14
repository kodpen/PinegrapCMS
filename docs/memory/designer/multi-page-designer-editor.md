---
name: multi-page-designer-editor
description: Editor screen code, save flow, JSON api.php body, tab switching/menu/rename DOM rules, toolbar overflow, palette order and translation of designer texts.
---

## Where is the editor screen and how does save work?
- One file: `includes/designer_screen.php` (`pg_designer_screen_render` / `_post`); `add_system_style.php` and `edit_system_style.php` are thin wrappers.
- Save sends all tabs in the `pages_json` field; the server first validates all of them with `dry_run`, then writes (MyISAM, no transaction). Save resets `page.page_custom_*`.
- `api.php` reads the body as JSON; FormData returns "Invalid action".
- The Delete button posts its own single-use form; `_protectMainForm()` blocks every submit of the main form.
- "View Page" needs `p.savedName` (unsaved name -> no link).

## How do tabs work in JS?
- Tab switch (`_pgTabsSwitch`) parks/loads the module-level `tree` / `undoStack` / `redoStack`; the rest of the file sees a single tree.
- Tab DOM is redrawn on every switch: do not hold references.
- Tabs are `<div role="tab">`, NOT `<button>` (an input inside a button cannot get focus). Tab name is edited in place (double click / menu).
- No name box in the toolbar: `#sd-page-name` lives in Page Settings (JS reads the same id).
- Tab menu `_pgTabsOpenMenu` (the dots and right click): Detach, Delete (see docs/memory/designer/multi-page-design-model.md).
- `.sd-toolbar.sd-toolbar-tabs` must stay `overflow: visible`: the toolbar's `overflow-x:auto` clips the dropdown; the strip scrolls inside its own `.sd-tabs-scroll`.

## Palette and translation
- Shared/System palette `_renderPaletteGroups()`: components used in this design (`_pgDesignSharedIds`) first, site-wide ones folded.
- JS `lang()` knows only preloaded keys; every text in the designer must be `_sdT('English')` (see the designer texts / translation rules).

Related: docs/memory/designer/multi-page-design-model.md
