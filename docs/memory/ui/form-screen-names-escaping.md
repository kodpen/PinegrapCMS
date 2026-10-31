---
name: form-screen-names-escaping
description: Form panel screens escape form names, field labels and pick list choice labels; liveform select option labels are printed raw, option values stay raw
---

## Which form screens escape stored names, and where?
- liveform's `select` prints option labels (the array keys) as they are, so the screen escapes the key where it builds the options: `$options[h($label)] = $value`.
- Add/Edit Submitted Form (`includes/forms/submitted_form_form.php`): pick list choice labels. For a collection reference, product or page pick list these are record titles typed by site visitors, product short descriptions or page names; a raw `</select><img onerror>` title broke out of the select on a manager's screen.
- Add Submitted Form and Import Submitted Forms: the custom form list (form_name / page_name).
- Edit Form List View and Edit Form Item View: the form name in the "Displays submitted forms from" line. Edit Form List View also escapes field labels in the "show by default" option and the browse field label.
- Pinned by `tests/form_screen_names_escaping_test.php` (#3491).

## Why is the pick list option value left raw?
- liveform compares the stored value with the raw option value to mark the selected choice; an escaped value would never match a value with an apostrophe or `&`.
- Known gap: a value containing `"` breaks its `value` attribute (`12" Pizza` posts `12`). liveform is shared with site output (`includes/fn/content.php`), so changing its select printing is a separate change.

Related: docs/memory/core/output-escaping.md
