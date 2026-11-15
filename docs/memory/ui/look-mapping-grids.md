---
name: look-mapping-grids
description: Column-mapping grids (one small select per column of an uploaded file) keep their own narrow columns in look A; the field widths of add and edit screens are for a record's fields
---

## How wide is a select in a column-mapping grid?
- As it was: a mapping grid is one control group of like selects, one per column of the file, not a record's fields, so the sheet's widths (a select 6 of the 12 columns) do not apply to it.
- Bank Statement `erp_bank_statement.php`, "Which column is which": six small selects `col-6 md:col-2`. Import Accounts `erp_accounts_import.php`, "Column mapping": one small select per CSV column `col-12 sm:col-6 lg:col-4 xl:col-3`.
- Architect (2026-11-15), channel 25 message 4637, in the look of #9704.

## Which other E5 cases stayed as they were?
- The Accountant pack's "Packs" card stays in the full-width row under both columns: in the main column (857 px at 1440) a pack's row with its links needs 916 px and would scroll sideways (#9704).
- Stock Count's Counted column is 6rem (a seven-digit count), so a product name takes at most two lines at 390 px; at 9rem a four-word name took four lines (#9704).

Related: docs/memory/ui/look-edit-screens.md
