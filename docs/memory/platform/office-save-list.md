---
name: office-save-list
description: One workbook save as an ordered list of changes of every kind (office_edit.php, OF1): steps, one lock and version check, each change on the workbook the one before left, refusals naming their place
module: office_edit
---

## What does one workbook save take?
- `pg_office_xlsx_save()` takes one ordered `cells` list mixing every kind: cells `{sheet, name, cell, value}`, format ranges (`office-sheet-format.md`), a row or column inserted or deleted (`office-sheet-rows-columns.md`), a sheet added, renamed, deleted or moved (`office-sheets.md`). Same doors as before: `explorer_file_save`, `ws_file_office_save`; no new endpoint, permission or schema.
- Before OF1 (plan office-sheet-steady) a list mixing kinds, or holding two non-cell changes, was refused as `none`.

## How is the list applied?
- `pg_office_xlsx_save_steps()` (pure) reads it into steps: cells next to each other in the list are one step (a cell named twice takes the last value), every other change a step of its own, each with its place (`at`, 0-based).
- Under one lock and one version check, each step is written by its kind's writer (`pg_office_xlsx_save_parts`, `_format_parts`, `_shift_parts`, `_sheets_parts`) with its own refusals, onto the workbook as the step before left it. A position or sheet name in an entry is the workbook's at that point of the list: after a rename the new name, after an insert the moved rows.
- One step writes into the file as before. Several are built one after the other in a copy in data/temp (`.work`), which takes the file's place (`pg_office_replace_file()`) only when every step went in.
- A cell typed into a row the sheet lacks (an inserted row, a new sheet) makes that row (`office-cell-edits.md`).

## What limits are there?
- At most 5000 entries (`many`), 5000 cells a format range (`many`), 100 steps (`PG_OFFICE_EDIT_MAX_STEPS`, `steps`): each step rebuilds the package once.

## What does a refusal give?
- Nothing is written: the file stays byte for byte. The result carries `at`, the place of the change refused (for a cell step the last entry naming the refused cell), and with more than one entry the message is "Change {n} of {count} was not saved. …".
- Refusals of the whole file or save (`ready`, `none` of an empty list, `many`, `steps`, `busy`, `changed` of the version, `signed`, `password`, `read`, `save`) carry `at` null and no place in the message.

## What was checked?
- `tests/office_save_list_test.php`: the steps, a mixed list (cells, format, insert, typing into the inserted row, rename, typing by the new name, add, typing into the new sheet, move) read back by `pg_office_read()`, four refusals at later places leaving the file byte for byte, a stale version. Not tried in a browser: the view still sends one kind a save until OF2 and OF3.
