---
name: panel-field-title-override
description: A labelled panel field carries no title: Bootstrap 6 copies a popover title into aria-label, replacing the label as its name; what replaces the title, the scan test, what is left
---

## Why may a labelled field not have a `title`?
- At ready, `pgBindTitlePopovers()` (`backend.src.js`) binds every `[title]` without `.no-popover` as a hover popover. Bootstrap 6's `Tooltip._fixTitle()` then copies the title into `aria-label` on an element with no `aria-label` and no text (every `input`, an empty `select`), and removes the title.
- On a field that also has a `<label for>` (or `aria-labelledby`), that `aria-label` replaces the label as its accessible name: a screen reader read "Select/Deselect All" for a "Select All" switch, a hint instead of the field name. The hint also shows only on mouse hover, never on keyboard focus or touch.

## What replaces the title?
- A title that repeats the label: drop it (the "Select All" switches of the page, calendar event, organize contacts and user permission lists).
- A real hint: print it under the field as `<div class="form-text" id="<id>_hint">` through `lang()` and add `aria-describedby="<id>_hint"` (`#ws_hol_to`). Never keep a title beside `aria-describedby`: the popover sets and removes `aria-describedby` itself when it shows and hides.
- A hint on a toolbar control, where a line under it breaks the row: a compact tooltip (`no-popover` + `data-bs-toggle="tooltip"`), which keyboard focus shows too (`#pg_tr_engine`, `translations.php`; a select's option text keeps Bootstrap from writing `aria-label`, an input would still lose its label this way).
- A label that is only an icon (a Material Icons ligature such as `visibility`, or `<i class="bi …">`): give the field its own `aria-label="' . h(lang('…')) . '"` with the title's text; the title may stay for the hover (`#filter_select` in `view_products.php`, `#status` of the variant sets list in `product_builder.php`).

## How is it kept?
- `tests/panel_title_label_override_test.php` scans panel PHP and JS (not `lib/`, `vendor/`, `.min.js`) for an input, select or textarea with a title, no `.no-popover`, no `aria-label`, and a `<label for>` on its id (a PHP or JS id expression included) or `aria-labelledby`. Its known list holds the fields not fixed yet and only shrinks.

## What is left?
- The known list: `editor_select_page_or_file.php` (`folder_id`, `access_control_type`), `workdays.php` (`ws_hol_to`), `view_ads.php`, `view_contacts.php`, `view_files.php`, `view_pages.php`, `view_submitted_forms.php`, `view_users.php`; files another change was editing at the time.
- Unlabelled fields with a title (the list screens' `#select_all`, the image picker's folder and access selects) are named by the title, but their hint is still hover-only.

Related: docs/memory/ui/panel-accessible-names.md, docs/memory/ui/panel-hint-tooltip-or-popover.md
