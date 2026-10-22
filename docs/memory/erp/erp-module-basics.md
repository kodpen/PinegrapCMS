---
name: erp-module-basics
description: ERP module switch and gate areas, the four permission columns, defensive constants, why tables always install, step names, menu slot 22 and titles, naming traps, where open ERP work is tracked.
---

## Why is ERP code risky?
- Its errors are silent accounting errors: the same money counted twice, a document number wasted, the VAT worked out differently in two places. Read the files of this folder before changing a flow.
- The phased build (phases -1 to 9, 2026.4.4–2026.4.10) is finished. Open work: plans/erp-edocument-leftovers.md (e-document drivers, accountant sign-off, manual checks), plans/erp-parasut-driver-design.md, plans/erp-auto-invoice-earchive.md.

## How is the module switched on and gated?
- `config.erp_enabled` → `ERP_ENABLED`. Screens are `erp_*.php`, `add_erp_*.php`, `edit_erp_*.php`, `get_erp_*.php`; each gates with `validate_erp_access($user, $area)` (`includes/fn/auth.php`).
- `$area`: `''` (the module), `'cash'` (`manage_erp_cash`), `'settings'` (`manage_erp_settings`); for the read-only right also `'read'` (a form that only reads, such as an export), `'accountant'` (the accountant's pack) and `'write'` (a change without a form, such as a job run by hand): erp-readonly-accountant-right.md.
- Module off: not a 404. The gate prints "The ERP module is not switched on." with a link to the settings card (`pg_settings_return_url('commerce', 'pgset-erp')`): a screen that silently disappears gets reported as broken.

## Which permission columns exist?
- Four plain `TINYINT` columns: `manage_erp` (the module), `manage_erp_cash` (tills), `manage_erp_settings`, `manage_erp_readonly` (4.78). Roles 0–2 have the first three by role and are never read-only.
- A right that alone lets a role-3 user into the panel goes into every role-3 ladder (core/permission-columns.md); `manage_erp` is in all of them. The read-only right never stands alone (it sits on `manage_erp`), so it needs no ladder entry.

## Why are ERP constants read defensively?
- `init.php` reads every `config.erp_*` column with a default (`ERP_ENABLED`, `ERP_SELLER_VKN`, `ERP_FX_ENABLED`, `ERP_WALKIN_ACCOUNT_ID`…): files land before the upgrade adds the column, and a missing column must read as "off", not as a notice on every page. Code that may run without `init.php` (the test runner, `tools/test.php`, loads no `init.php`) checks `defined('ERP_…')` first.

## Are ERP tables created when the module is off?
- Yes, always, whatever `config.erp_enabled` says. Tying the schema to the switch drops a site that enables it later onto a schema different from what its version says, and the idempotent steps would answer "already exists" and freeze that gap.

## Where do new ERP schema steps go?
- Into the open version's migration file as `upgrade_<version>_erp_<topic>()`, called at the end of `upgrade_to_<version>()`'s list with an ERP label (ranges: release/version-numbering.md). Earlier ERP steps: 4.43–4.103 in 2026.4.4, 8.58 in 2026.4.8, 10.58–10.61 in 2026.4.10.

## Which menu slot and titles does the ERP use?
- Slot 22 (`includes/fn/output.php`), shown with the module on to role < 3 or `USER_MANAGE_ERP`. Slots are permanent (`user.selected_appmenu_items_array` pins them); never reuse one.
- A new ERP screen adds its file name to the `case` list that sets `$active_menu = 22`, or the menu does not light up on it.
- Menu title `'Resource planning (ERP)'` ("Kaynak Planlama (ERP)"), dashboard heading `'Enterprise resource planning (ERP)'`. Do not change the translation of `lang('ERP')`: the permission row, the settings card and the File Manager root use it too.

## What naming traps exist?
- `erp_invoice_items.tax_total` is deliberately not `tax`: `order_items.tax` is the unit tax, an invoice line holds the line's total tax. The name difference stops a wrong read.
- The account card links to `contacts` (`erp_accounts.contact_id`); there is no `customers` table. `parasut_contact_id` stays on `contacts` and is not copied to `erp_accounts`.

Related: docs/memory/erp/erp-money-and-currency.md
