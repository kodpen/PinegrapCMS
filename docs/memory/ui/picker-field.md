---
name: picker-field
description: The picker field pg_picker_field() (PK5): combobox with the chosen value and its picture, search list, Browse, clear, phone sheet, marks; which screens use it and who prints the script
module: picker
---

## What is the field?
- `pg_picker_field($kind, $name, $value, $options)` (`includes/fn/picker.php`), the dialog's other face (`picker-dialog.md`). PK5 of `plans/one-picker.md`, PR #8751; it delivers F3 of the search plan.
- A row: a box with the chosen value's picture (2.25rem), its name and a meta line (folder path, W × H, size), clear (×) and a caret; Browse… beside it opens the dialog on the value, or on the typed text.
- The value is posted from a hidden input with the field's name (`data-pg-picker-value`); the text input carries the id the label names (`role="combobox"`). A pick fires `change` on the hidden input.

## Which options does it take?
- `id` (default the name), `title` (the dialog's), `placeholder` (default "Type to search…"), `describedby` (the help text's id), `invalid` (the form refused the value: red box and `aria-invalid` until another is chosen), `user` (id, role) where the request has no `USER_ID`/`USER_ROLE` (an AJAX handler).
- An unknown kind prints ''. The stored value is resolved by `pg_picker_resolve_answer()` with the user's access.
- It uses `is-error`, never `is-invalid`: the feedback injector (`pgInjectAriaAndRequiredMarkers`) would add text to an `.is-invalid` wrapper.

## How is a stored value marked?
- `pg_picker_field_view()` (pure): missing (name kept, an id as `#42`, "Stored value · file not found", amber note "This file is no longer in Files; choose another."); hidden ("A file in a folder you cannot open", lock icon); binned (red note, restore or choose); offline (amber note, choose a picture everyone may see).
- The note is a `.form-text` under the row, in `aria-describedby`; a pick removes it. The value is posted unchanged; the saves keep their checks (`settings-public-image-pickers.md`, `commerce/short-link-recycle-bin.md`).

## How does typing search?
- After 250 ms the kind's `search` request (8 rows) fills a list under the box: picture, name with the match highlighted, meta. The last row is "All results for “…” in Browse" (or "Browse…"), Alt+↓ too. A stale answer is dropped by a sequence number.
- Keys: ↓/↑ open and move, Enter chooses the active or first row (never submits the form), Escape restores the stored value and closes the list. The keydown listener sits on the document in the capture phase: Bootstrap's Escape handler on a settings dialog would otherwise close the dialog.
- Leaving the field restores the chosen text. A binned or archived stored page opening the dialog at the root: PK6.

## What is drawn on a phone?
- Below 576 px a tap on the box opens a half sheet (`.pg-pfield-sheet`, a `pgUI.dialog`) with its own search input and list; the box input is read-only so no keyboard pops up. Browse shows only its icon.

## Who prints the script?
- The screen prints `pg_picker_script()` (once per request); `pg_picker_field()` never does. `includes/settings/prep.php` runs before every settings category, so it never prints it: `seo.php`, `general.php` and `fediverse.php` print it with their cards.
- Its listeners sit on the document, so injected markup (Settings cards, the File Manager window) works as printed.

## Which screens use it?
- Settings › SEO: Site Icon (`site_icon`), Application Icon, Default Share Image (`public_image`); General: Organization Logo; Fediverse: Avatar (`public_image`, empty = the organization logo).
- Add/Edit Short Link: File (`file`, posted as `file`, `invalid` from liveform).
- File Manager short link window: `handle_short_links.php` sends `file_field` (posted as `file_id`, id `sl_file`, the link's own file); `view_folders.php` places it and reads `#sl_fields [data-pg-picker-value]`.

Tests: `tests/picker_test.php` (view and markup), `tests/picker_js_test.php` (field helpers), `tests/short_link_requests_test.php`, `tests/settings_public_image_test.php`.
