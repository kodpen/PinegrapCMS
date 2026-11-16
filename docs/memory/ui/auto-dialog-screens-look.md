---
name: auto-dialog-screens-look
description: The Auto Dialogs list, Create and Edit screens in look A (PU5, #9880): segmented Enabled filter, figure row, Opens and Size columns, field spans, figures in the status column
---

## What does the list show?
- `view_auto_dialogs.php` (template `includes/templates/view_auto_dialogs.php`): no title of its own; the header holds it with the driver's count (`look-list-title-settings-tile.md`).
- Toolbar: Create, then in the search slot the segmented filter All / Enabled / Disabled with counts (`pg_list_seg_filters()` over the `enabled` column; the cell carries `data-pg-filter` on/off through `pg_auto_dialog_list_enabled_cell()`), the search box and the sort menu.
- A figure row (`.pg-figures`, `look-figure-cards.md`): Enabled n / all, and the last 30 days' Times shown, Times closed, Times taken of every pop-up (`pg_auto_dialog_count_total()`).
- Columns: Name, Enabled, URL, Opens (`pg_auto_dialog_opens_label()`: after N seconds, immediately, at N% scrolled, when leaving; sort code `delay`), Size (`pg_auto_dialog_size_label()`, "400 × 650 px"; sort code `width`), Frequency, Only on Page, Last Modified, Shown (30 days), Preview. Height and Created left the list so it fits 1440 px; closed and taken are in the figure row and on the edit screen.

## What do Create and Edit show?
- Heading, description and breadcrumb (All Auto Dialogs › Create Auto Dialog, or › the pop-up's name) are passed to `pg_page_shell()`; the template draws no heading, breadcrumb or `[name]` of its own.
- Field spans (`look-field-widths.md`): Name and URL the whole row; Only on Page alone at 6 (`pg-field-span-6`); width, height, delay and frequency 3 each; Opens 6 with the scroll share 3 beside it; Shown To and Place 6 each.
- Status column on edit: Status (the Enabled switch), Last 30 Days (three stand-alone figures in `.pg-figures` with `--pg-figures: 3`; two a row on a phone, as the recipe does), Summary (adds Opens, Shown To, Place: the selects' chosen option, kept by a change listener).
- Save bar: `bi-plus-circle` / `bi-floppy` and `bi-trash` icons.
- Turkish: Centre is its own key "In the centre" (Ortada); Scrolled reads Kaydırma oranı, Shown To Kime gösterilir.

## Who looked?
- The owner's look, asked through the architect with before/after pairs at 1440 and 390, light and dark, from a sandbox with three sample pop-ups.

## Tested?
- `tests/auto_dialog_form_test.php` (`test_auto_dialog_screens_in_look_a`), `tests/auto_dialog_counts_test.php`.
