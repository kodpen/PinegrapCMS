---
name: multi-page-designer-editor
description: Editor screen code, save flow, JSON api.php body, the dirty check and unchanged pages, tab switching/menu/rename DOM rules, toolbar overflow, palette order and designer texts.
---

## Where is the editor screen and how does save work?
- One file: `includes/designer_screen.php` (`pg_designer_screen_render` / `_post`); `add_system_style.php` and `edit_system_style.php` are thin wrappers.
- Save sends all tabs in the `pages_json` field; the server first validates all of them with `dry_run`, then writes, with no transaction (the code opens none, and a table over the 2026.4.8 limits may still be MyISAM: `data/table-engines-and-row-size.md`). Save resets `page.page_custom_*`.
- `api.php` reads the body as JSON; FormData returns "Invalid action".
- The Delete button posts its own single-use form; `_protectMainForm()` blocks every submit of the main form.
- "View Page" needs `p.savedName` (unsaved name -> no link).

## Which pages does a save write?
- A page nothing touched goes without its tree, marked `unchanged: 1` (`_pgPageUnchanged()`: saved before, not a picked page, `_pgPageDirty()` clean, the validation removed nothing, and none of the shared components it shows, nested ones included, is `_sharedDirty`). The server neither writes, scores, translates nor logs it, and only puts it in the `pages` reply (the one save that folds the old design-wide body classes onto pages reads such pages back and writes them).
- New work in the page save that depends on anything besides the page's tree and settings adds that condition to `_pgPageUnchanged()`, or the change never reaches that page.
- The dirty check `_pgBaselineOf()` serialises the tree with `_pgBaselineTreeKey()`, which skips `_expanded` (the Layers panel's open state, set on the selection's ancestors) and an empty `_attrs`; the settings and form settings count. Panel drawing writes nothing to a node; a new UI state kept on the node gets its key skipped there, or selecting an element shows the page as unsaved.

## How do tabs work in JS?
- Tab switch (`_pgTabsSwitch`) parks/loads the module-level `tree` / `undoStack` / `redoStack`; the rest of the file sees a single tree.
- Tab DOM is redrawn on every switch: do not hold references.
- Tabs are `<div role="tab">`, NOT `<button>` (an input inside a button cannot get focus). Tab name is edited in place (double click / menu).
- No name box in the toolbar: `#sd-page-name` lives in Page Settings (JS reads the same id).
- Tab menu `_pgTabsOpenMenu` (the dots and right click): Detach, Delete (see docs/memory/designer/multi-page-design-model.md). Tab order and groups: `designer-tab-groups.md`.
- `.sd-toolbar.sd-toolbar-tabs` must stay `overflow: visible`: the toolbar's `overflow-x:auto` clips the dropdown; the strip scrolls inside its own `.sd-tabs-scroll`.

## How are the palette and the designer's texts handled?
- Shared/System palette `_renderPaletteGroups()`: components used in this design (`_pgDesignSharedIds`) first, site-wide ones folded.
- JS `lang()` knows only preloaded keys; every text in the designer must be `_sdT('English')` (`core/sdt-designer-js-text-rules.md`).

Related: docs/memory/designer/multi-page-design-model.md
