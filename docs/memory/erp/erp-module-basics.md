---
name: erp-module-basics
description: ERP module switch and access gates, the three permission columns, menu slot 22, why tables always install, and naming traps (tax_total, contacts link).
---

## How is the ERP module switched on and gated?
- `config.erp_enabled`; constant `ERP_ENABLED` (read defensively).
- Screens are `erp_*.php`; gate is `validate_erp_access($user, $area)` with `$area` empty, `'cash'` or `'settings'`.
- Module off: not a 404; show an error plus a link to the settings card (`pg_settings_return_url('commerce', 'pgset-erp')`).

## Are ERP tables created when the module is off?
- Yes, always. Tying the schema to the switch drops a site that enables it later onto a schema different from what its version number says, and idempotent steps would answer "already exists" and freeze that gap.

## Which permission columns exist?
- Three plain `TINYINT` columns, no prefix: `manage_erp` (gate), `manage_erp_cash`, `manage_erp_settings`. The None/Read/Write triple pattern does not exist in this codebase.
- When adding a permission, update all four ladders that decide whether a role-3 user can enter the panel: `welcome.php:59`, `get_page.php`, `includes/fn/content.php`, `set_password.php` / `reset_password.php`. If skipped, a user whose only right is the new one falls to "no access".

## Which menu slot does the ERP use?
- Slot 22 (`includes/fn/output.php`). Slots are permanent: `user.selected_appmenu_items_array` pins them; never reuse one.

## What naming traps exist?
- `erp_invoice_items.tax_total` is deliberately not `tax`: `order_items.tax` is the unit tax, an invoice line holds the line's total tax. The name difference stops a wrong read.
- The account card links to `contacts` (`erp_accounts.contact_id`); there is no `customers` table. `parasut_contact_id` stays on `contacts` and is not copied to `erp_accounts`.

Related: docs/memory/erp/erp-money-and-currency.md
