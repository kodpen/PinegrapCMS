---
name: short-link-shared-form
description: The short link create and edit screens share includes/forms/short_link_form.php: what it holds, what stays in the screens
---

## Where is the short link form?
- `includes/forms/short_link_form.php` (gate `PG_FUNCTIONS_DIR`). `add_short_link.php` and `edit_short_link.php` stay at the root as thin wrappers and `require_once` it.
- `pg_short_link_form_cards($liveform, $form)` prints the destination type, name and function option cards. `$form['extra_rows']` follows the name row (create: link mode + answer; edit: answer), `$form['after_main']` sits between the name card and the options card (edit: QR card), `$form['keep']` names the page, group, product and file the edited link points at, so their lists keep them while they are in the Recycle Bin (nothing on create).
- `…_redirect_code_row($liveform)` is the "How it answers" row ('' before 11.0.12). `…_row_styles($type)` says which option rows open; `…_destination_options()` the type picker.

## What does a save share?
- `pg_short_link_form_validate_name($liveform, $ignore_id)` (required, pasted address, name rule, free; `null` on create, the posted id on edit) returns the cleaned name.
- `…_validate_destination($liveform, $current)` checks the type and its target and returns the `page_id` to save; a binned target is refused unless `$current` (the stored row, `null` on create) already points at it.
- `…_answer($liveform, $id, $name, $current_code)` runs `pg_short_link_redirect_columns()` on a form without errors.
- `…_destination_column($type)` names the column and field of the target (`file` → `file_id`; a page has none, it keeps `page_id`). Create inserts only that column; edit sets it and clears the other three.

## What stays in the screens?
- Create: the 404 log prefill, link mode and duration (one-time links skip the name check), the INSERT with token columns, the notice with the one-time address.
- Edit: not-found, binned and nameless guards, the field prefill, the Visit link, the QR card, delete (to the bin when ready), the UPDATE, the redirect back with `?id=`.
- Tests: `tests/short_link_form_test.php`; source checks in `short_link_requests_test.php`, `catalog_bin_test.php`, `db_q_logs_short_links_system_test.php` read the form file.
