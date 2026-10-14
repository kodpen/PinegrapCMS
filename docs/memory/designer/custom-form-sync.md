---
name: custom-form-sync
description: custom_form field derivation on the server, per-page ids map and its PHP round-trip trap, name/group rules (_cfEnsureNames), RSS roles per widget.
---

## Where is the field list derived?
- **Only on the server.** `_pg_cf_controls($tree, $page_id)` produces the field list; `pg_cf_reconcile_page_form($page_id, $user_id, $settings)` reads the page's own form widgets on every page save, gives `pg_cf_sync_page_form()` the **complete list** (deletion included) and stamps dispatched ids back into the widget tree (`_pg_cf_stamp_ids`). The save response carries `forms[]`; the client writes it into its cache with `_cfAdoptIds` (does not dirty it).
- Client `_cfFieldsOf()` is the twin of the same read and **only feeds the panel**. Label, type and grouping rules must match on both sides; change one, change the other.
- **Save order:** `_flushDirtyShared()` returns a promise and `_saveAjaxImpl` awaits it. Page save reads the widget tree from the server, so it cannot overtake the widget update.
- A page without a form widget: its form is untouched (deleting it is not this function's job). Widget present but no controls => list empty and fields are deleted (with warning).

## How does the per-page `ids` map work?
- The widget is a shared component; it can stand in "new form" mode on two pages, each with its own rows: `_cf.ids[page_id]`. Ids not belonging to the page are ignored; matching is by name.
- Rename keeps the id (submissions stay linked); a removed control's row is deleted with `deleted` / `lost_values`; a copied text control is renamed by `_cfEnsureNames` and drops its id.
- **PHP round-trip trap:** `json_decode(..., true)` turns empty `{}` into an empty array, `json_encode` writes `[]`, and a key set on a JS `[]` is lost in stringify. `_cfRec()` and `_cfAdoptIds()` treat an array as empty and replace it with `{}`. Never leave `_cf` / `ids` empty.

## How are names and groups assigned (`_cfEnsureNames`)?
- **Same-type** boxes inside the same `<fieldset>` are one name (one question); boxes outside a fieldset keep their own names. The Options panel copies the name when adding an option to a box; those boxes are deliberately one field.
- A nameless control takes a name from its label (`_cfSuggestFieldName`); a control whose name is on another text control is a copy and is renamed.
- Drop (`_cfAdoptOnDrop`), double click, paste, default layout and save serialization (`_cfEnsureNamesAll`) all go through the same function.
- The label is not written in two places: if `<label for>` / `<legend>` exists the panel writes **that element's text**; otherwise `_cf.label`.
- In `canDrop`, `cf_field` -> `semantic`; a palette block goes where its own type goes.

## How do RSS roles work?
- One role sits on one field per form: giving it to a second field takes it from the first and a toast says so (`_cfRoleHolder`); the server deriver also lets the first win. The widget panel lists the four roles and owners and warns if "Başlık" is not given.

Related: docs/memory/designer/custom-form-model.md
