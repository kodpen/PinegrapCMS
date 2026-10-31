---
name: explorer-drawer-names
description: File and Catalog Manager drawer rows drawn by script name themselves: access selects per user, commerce switch groups, shipping-zone lists, bulk description, backup upload path, file window code field
---

## Why do these rows carry their own names?
- They are drawn by script after load, so the label binding at DOMContentLoaded (`pgInjectAriaAndRequiredMarkers()`, `backend.src.js`) never reaches them; on Tab each was an unnamed box, or a switch read without the user or list it belongs to.

## Which ones, and how?
- Access drawer (`openAccessPanel()`): each `select.access-rights-select` has `aria-label` from `L.rights_for_user` ('Rights for {var:1}') with the row's username.
- Catalog Manager commerce access drawer: each user block is `role="group"` with `aria-labelledby="commerce_user_<id>"` on the username div, so 'Manage', 'Reports' and 'Offline payment' are read inside the user's name.
- Bulk edit products, `zoneChecks()`: each list is `role="group"` labelled by its caption (`<name>_label`, a div, not a `label` without `for`).
- Bulk edit files: `#bulk_description` has a `label[for]` (`L.bulk_description_text`, 'New description').
- Upload dialog: the Folder label (`#upload_folder_label`) points at `#upload_backup_path` in the Backups folder and back at `#upload_folder` elsewhere.
- File window: `PgCodeEditor.create()` gets `extensions: PgCM.view.EditorView.contentAttributes.of({'aria-label': L.file_content, 'aria-describedby': 'file_edit_editor_note'})`; the note under the editor says how Tab leaves the code (`L.tab_hint`, Ctrl+M or Shift+Alt+M) for an editable file and "read-only" otherwise (`code-field-keyboard-exit.md`); the plain-textarea fallback gets the same `aria-label`. No facade change was needed.

## What is not covered here?
- The Access drawer's per-row expiration date box already had its `aria-label`; date inputs belong to the datetime-picker work.

Test: `tests/explorer_drawer_names_test.php`.
