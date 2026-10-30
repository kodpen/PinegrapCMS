---
name: panel-tab-rows
description: Panel tab rows say which tab is open (role=tab + aria-selected, named tabpanel) and move with the arrow keys; a hidden Bootstrap tab is also disabled
---

## What must a tab row carry?
- `role=tablist` on the row, `role=tab` with `aria-selected` and `aria-controls` on each tab, `role=tabpanel` with `aria-labelledby` (the open tab's id) on the pane.
- A roving tabindex: only the open tab is `tabindex=0`; ArrowLeft/Right (and Up/Down), Home and End move to a tab and open it.
- A state shown only by styling (a hidden sheet in italics, an edited sheet's CSS dot) is also said in a `visually-hidden` span inside the tab.

## How do Bootstrap 6 tabs fit?
- `data-bs-toggle="tab"` sets roles, `aria-selected` and the roving tabindex itself, but gives a pane `aria-labelledby` only when its tab button has an `id`: give every tab button an id and `aria-controls`.
- `Tab._keydown` skips only disabled tabs (`isDisabled()`), not hidden ones. A tab hidden with `d-none` on its `<li>` must also get `disabled`, or the arrow keys open a tab nobody can see and focus drops out of the row.

## Where is it done?
- API application drawer (`api_settings.php`): every tab button has `d_<pane>_label`; `openApp()` disables the Devices tab for a server key and the Events tab for a device or OAuth application along with hiding them.
- Image picker (`editor_select_image.php`, My Files / Unsplash): `esiSwitchTab()` keeps `aria-selected` and tabindex in step; a document keydown handler moves along `#esi_tabs`.
- Spreadsheet preview (`assets/js/pg_office_view.js` `renderBook()`, File Manager and Workspace): tablist named by `strings.sheets`, pane id per render (`office_pane_N`), `.office-tab-changed` span kept in step in `drawStatus()` (`strings.changed_mark`).
- The same preview off the tabs: an editable grid's first cell is its Tab stop, a read-only sheet's pane is (`tabindex=0`, scrolls); a `focusin` selects a Tab-reached cell (`select()`), so arrows and typing work. An editable document's first paragraph is its stop and a Tab-reached paragraph is picked; a read-only document box is a stop, named `strings.doc` (#PR; before, Tab reached nothing, or an unpicked paragraph the keys ignored). The `:focus-visible` rings in backend.src.css are still to come.
- Not here: the chat window tabs.

Test: `tests/panel_tab_rows_test.php`, `tests/office_view_keyboard_test.php`.

Related: docs/memory/ui/current-and-pressed-state.md, docs/memory/ui/pg-ui-adapter.md
