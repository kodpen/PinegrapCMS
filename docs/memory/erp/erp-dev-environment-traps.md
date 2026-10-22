---
name: erp-dev-environment-traps
description: Traps when developing ERP code on the dev setup: orders writes a test rollback misses, device_commit_files overwrite, long lint runs, strict sql_mode on migrations, PHP via Python heredoc.
---

## Why must a test script restore orders by hand?
- An ERP rollback cannot be trusted to undo a write to `orders` (MyISAM until 2026.4.8; a large table stays MyISAM until converted). A test that calls a function writing `orders`, such as `erp_invoice_cancel()` (sets `orders.erp_invoice_id = 0`) or `erp_invoice_from_order()`, puts the order's link back by hand afterwards, or the order stays unlinked from its invoice.

## What goes wrong committing files to the device?
- `device_commit_files`: committing the same target file a second time from the same staged path can lose the old content (the outputs binding does not update mtime). Stage the second version under a NEW file name (`*.v2.php`), commit it, then verify on the device with `grep`.

## How should a long `tools/lint.php` run be done?
- The device connection is slow; the lint may not finish in 175 s. Run it in the background (`nohup … > $HOME/lint_out.txt &`) and read the file afterwards.

## Why does a single migration sub-step fail on dev?
- `install_add_column` under strict `sql_mode` gives 1067 on `erp_invoices` (`supplier_invoice_date` default '0000-00-00'). The installer opens its session with `SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'`; a helper that bootstraps the runner by hand must do the same.

## Which traps apply when writing PHP through a Python heredoc?
- Escapes such as `·` stay literal inside a PHP single-quoted string: write the real character.
- A JS comment embedded in a PHP single-quoted string that contains `'s` cuts the string.

## Where are the other dev-only rules?
- Credentials and keys are entered by the owner (Erdal); nobody else creates API keys or fills password/key boxes on dev: see erp-api-events-and-testing and erp-isbasi-driver. E-mail on dev: erp-invoice-mail.

Related: docs/memory/erp/erp-api-events-and-testing.md, docs/memory/erp/erp-order-cancel-and-refund.md
