---
name: settings-invalid-fields
description: A refused Site Settings save names its marked fields (pg_settings_marked_fields()); the dialog marks them with pgFieldInvalid(), shows the notice and focuses the first (#3026)
---

## How does the dialog show which field was refused?
- The invalid answer carries `fields`: name => plain sentence from `pg_settings_marked_fields()` (`includes/settings/registry.php`; skips `_form_error_*`, `notices`, `warnings`), an object even when empty.
- `refused()` in `backend.src.js` marks each control by name (also `name[]` and nested `name[...]`, never a hidden twin, names through `CSS.escape`) with `pgFieldInvalid()` and its sentence, sets `form.scrollTop = 0` so the notice at the top of `.pg-sm-body` shows, and focuses the first visible marked field (focus unlatches it). Editing a marked field clears its mark; the next refused save clears the old ones first.
- Why (#3026): pressing the footer Save while scrolled down showed nothing, and no field was marked or focused.

Related: docs/memory/ui/settings-invalid-vs-error.md, docs/memory/ui/inline-feedback-placement.md, docs/memory/ui/settings-modal-autofill-lock.md
