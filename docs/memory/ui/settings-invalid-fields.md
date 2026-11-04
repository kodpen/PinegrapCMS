---
name: settings-invalid-fields
description: A refused Site Settings save marks its fields by name; the dialog marks and focuses them (#3026), a settings page puts the typed values back (pgSettingsRestore())
---

## How does the dialog show which field was refused?
- The invalid answer carries `fields`: name => plain sentence from `pg_settings_marked_fields()` (`includes/settings/registry.php`; skips `_form_error_*`, `notices`, `warnings`), an object even when empty.
- `refused()` in `backend.src.js` marks each control by name (also `name[]` and nested `name[...]`, never a hidden twin, names through `CSS.escape`) with `pgFieldInvalid()` and its sentence, sets `form.scrollTop = 0` so the notice at the top of `.pg-sm-body` shows, and focuses the first visible marked field (focus unlatches it). Editing a marked field clears its mark; the next refused save clears the old ones first.
- Why (#3026): pressing the footer Save while scrolled down showed nothing, and no field was marked or focused.

## And a settings page (settings_<key>.php)?
- A refused save still redirects, but `screen.php` keeps what was posted in `$_SESSION['software']['pg_settings_typed'][key]` (`pg_settings_typed_values()`: by control name, `x[]` also as `x[0]`..., token, decoy and Save button left out). The next GET prints it once, without password and file controls (`pg_settings_typed_restorable()`), with the marked fields, to `pgSettingsRestore()` at DOM ready after the category scripts: values go back over the cards drawn from the database, changed boxes and lists fire `change`, marked fields get `pgFieldInvalid()` and the first is focused.
- Why: the page redrew every card from storage, so all other edits were lost and the refused field showed the old value, unmarked.

Related: docs/memory/ui/settings-invalid-vs-error.md, docs/memory/ui/inline-feedback-placement.md, docs/memory/ui/settings-modal-autofill-lock.md
