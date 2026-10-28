---
name: panel-accessible-names
description: Names screen readers get in the panel: translated aria-labels, labels that point at a field, chip and select2 inputs, the settings autofill decoy, user-coloured badges (#2063)
---

## How does a control get its accessible name?
- `aria-label` text goes through `lang()` like visible text: `aria-label="' . h(lang('Close')) . '"`, never a literal English word. A visible `title` does not name a button for a screen reader.
- A `<label for>` names the id the field really has. A field without a visible label gets a `visually-hidden` label (Find and Replace) or an `aria-label` (a list row's checkbox: "Select {var:1}" with the row's name; the Workspace task status filters: `t('task_status_filter')`).
- Chips (`data-pg-chips`): the `<label>` names the hidden input, so `pg_fields.js` copies its text to the box people type in as `aria-label`.
- select2 on site pages (`v5` combobox) gets `language` texts (no results, searching, search) from the labels on the `pg_fields_script()` tag.

## How are badges on a user-chosen colour made readable?
- `pg_readable_ink_on($hex)` (`includes/fn/design_themes.php`) gives white where it reads at 4.5:1 on the colour, else `#18181b`; `readableInk()` in `workspace.js` is the same rule for a channel's department badge. Used for department badges (Workspace Settings, channel header). Anything that is not `#rgb`/`#rrggbb` keeps white.

## What is not covered yet?
- DataTables pagination names come from its language file (#2043).
- Waiting on files other work holds: the settings autofill decoy `#fake_user_name` (`includes/settings/screen.php`, needs `tabindex="-1"` and `aria-hidden`), the broken `for` of Tracking Code Duration and the feed groups label (`settings/seo.php`), the Iyzipay protected currency select id (`settings/prep.php`), the dark-theme today number of the Work Calendar (`backend.src.css`), and the close buttons of `get_page_content.php` and the installer.

Test: `tests/contrast_test.php` (`test_contrast_readable_ink_on`).
