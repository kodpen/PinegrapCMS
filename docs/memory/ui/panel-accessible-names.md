---
name: panel-accessible-names
description: Names screen readers get in the panel: translated aria-labels, labels that point at a field, the one required marker, chip and select2 inputs, the settings autofill decoy, user-coloured badges (#2063)
---

## How does a control get its accessible name?
- `aria-label` text goes through `lang()` like visible text: `aria-label="' . h(lang('Close')) . '"`, never a literal English word; landmarks too (`Button Bar`, `data edit buttons`: `tests/panel_landmark_names_test.php`). A visible `title` does not name a button for a screen reader.
- A `<label for>` names the id the field really has. A field without a visible label gets a `visually-hidden` label (Find and Replace) or an `aria-label` (a list row's checkbox: "Select {var:1}" with the row's name; the Workspace task status filters: `t('task_status_filter')`).
- Close buttons (`.btn-close`) of dialogs, drawers and toasts always carry `aria-label` Close: a v6 dialog opened with `showModal()` puts focus on it first. Icon-only buttons and pager links get the action's name (Edit, Delete, Search, Previous page / Next page, Invoice Detail, Software Theme); the settings screen's disabled step button reuses Previous / Next. JS-built markup uses the screen's own label map (`t('Close')` in `image_editor_modal.js` from `$labels` in `get_image_editor_includes()`, `L.close` in `view_folders.php`). Pinned by `tests/panel_accessible_names_test.php`; the API applications screen (search box, dialog titles, fields, permission groups) by `tests/api_settings_theme_test.php`.
- A dialog's `aria-labelledby` names its `h5.dialog-title` id (`cron_jobs_title`), never the dialog (that reads its whole body). A filter select behind an icon-ligature `<label for>` (`visibility`) carries a translated `aria-label`, else the ligature names it (#2668).
- The settings autofill decoy `#fake_user_name` (`includes/settings/screen.php`) is `tabindex="-1" aria-hidden="true"`: out of keyboard and screen-reader reach.
- Chips (`data-pg-chips`): `pg_fields.js` copies the hidden input's `<label>` text to the typed box as `aria-label`.
- select2 on site pages (`v5` combobox) gets `language` texts (no results, searching, search) from the labels on the `pg_fields_script()` tag.

## Where does a required field's marker come from?
- `pgInjectAriaAndRequiredMarkers()` (`backend.src.js`) appends one `.pg-required-marker` to the label of every `required` control, so a label never carries a hand-written `*`: the two together read "*Product ID / SKU *" (#2057; the product screen in PR #2618, the folder, product group and referral source screens in #2481). A box that a script-side save refuses blank (the product screen's attribute name, in a dialog outside the form) carries `required` too and gets the same marker.
- The script skips a label whose text already starts with `*`, so a screen that keeps a hand-written marker shows one.

## How are badges on a user-chosen colour made readable?
- `pg_readable_ink_on($hex)` (`includes/fn/design_themes.php`) gives white where it reads at 4.5:1 on the colour, else `#18181b`; `readableInk()` in `workspace.js` is the same rule for a channel's department badge. Used for department badges (Workspace Settings, channel header). Anything that is not `#rgb`/`#rrggbb` keeps white.

## What is not covered yet?
- DataTables pagination names come from its language file (#2043).
- Waiting on files other work holds: the broken `for` of Tracking Code Duration and the feed groups label (`settings/seo.php`), the Iyzipay protected currency select id (`settings/prep.php`), the dark-theme today number of the Work Calendar (`backend.src.css`), the close buttons of `get_page_content.php` and the installer; the products list filter select (`view_products.php`) and the SearchBox dialog of `includes/fn/output.php` (its `aria-labelledby` names no element).
- About 55 fields with a `title` beside a `<label>` (`panel-hint-tooltip-or-popover.md`).

Reports, date filters: `report-screen-controls.md`.

Test: `tests/contrast_test.php` (`test_contrast_readable_ink_on`).
