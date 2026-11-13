---
name: look-screen-widths
description: Root panel screens outside the look's widths (no pg-measure-edit, -list or -report, or a container of their own) on 2026-11-13, and the step that takes each, LW5-LW7 for the rest
---

## How was it found?
- Every root file calling `pg_page_shell()`/`output_header()` (276, `delete_`/`duplicate_`/`remove_` left out), loaded signed in on a sandbox at 1920 px: body class, `#content` width, a capped block inside. Screens that need a record id (404 without one) and the ERP screens were read from the code. 52 take `pg-measure-list`, 42 `pg-measure-edit`, 3 `pg-measure-report` on the page itself; the rest below. Names are root files without `.php`.
- Without a measure, `#content` is the window less the menu (1860 px at 1920): forms and tables run the whole width.

## Which step takes which screen?
- Not the look's (plan rule: no File Manager, designer, Workspace): `workspace*` (11), `view_folders`, `view_fields` (form designer), `generate_layout`, `legacy_to_designer`, `import_design`, `view_system_style_source`, `edit_page_layout`, `edit_theme_css`, `toolbar`, `preview_form`.
- The dashboard `welcome` keeps its look (DL5a not given, #8938). `design_examples` is the look's own page.
- Open in the settings dialog (DL5d, #8952): `cloudflare`, `software_update`, `view_parasut_inbox` send the browser to `welcome.php#settings…`.
- LW2 / DL5c field widths (add and edit screens without the class): `add_email_campaign`, `edit_email_campaign`, `edit_sms_campaign`, `edit_offer`, `add_erp_manual_invoice`, `add_erp_purchase_order`, `add_erp_quote`, `edit_erp_invoice_draft`, `edit_erp_purchase_order`, `edit_erp_quote`; details screens `view_order`, `view_return`, `view_visitor`; code editors `edit_custom_style`, `edit_javascript`, `editor_edit_file` (whether an editor fills the window goes with LW2's proposal).

## Which screens did no step take? (new steps, `plans/look-widths-and-switch.md`)
- LW5 Lists and views not on the table driver, so DL5b (#8921) did not reach them: `calendars` (the month view), `view_menu_items`, `view_sessions`, `view_performance_log`, `view_accessibility`, `backups`, `translations`, `marketplace_products`, `erp_purchase_orders`, `erp_quotes`, `erp_expenses`, `erp_expense_categories`, `erp_cheques`, `erp_bank_statements`, `erp_stock_counts`, `erp_invoice_recurrences`, `erp_audit`.
- LW6 Settings, tool and import pages at the edit measure. Settings: `api_settings`, `smtp_settings`, `marketplace_settings`, `erp_settings`, `connections`, `connected_apps`, `device_pairing`, `developer_lock`, `database_engine`.
- LW6 tools: `system_tools`, `clean_up` (its own `.container`, 1440 px), `migration`, `experiments`, `page_experiments`, `api_docs`, `create_file`, `find_and_replace`, `mass_edit`, `privacy_erase`, `privacy_export`, `barcode_increase_inventory`, `barcode_decrease_inventory`, `approve_affiliate`.
- LW6 imports and ERP pages: every `import*` but `import_design` (10), `erp_export`, `erp_accounts_import`, `erp_account_sync_item`, `erp_accountant`, `erp_bank_statement`, `erp_inbox_document`, `erp_reconciliation`, `erp_stock_count`.
- LW7 Reports at the report width (DL5e, #8981, took the report view only): `erp_dashboard`, `erp_aging`, `erp_cashflow`, `erp_profit`, `search_console`, `search_audits`. `visitors` keeps its own look (`visitors-screen-look.md`).

## Which measured screens hold a narrower block?
- None that narrows the page: a list cell stops at 352 px (`--pg-list-table-cell-max`), `add_container`'s length row is 600 px.

Related: docs/memory/ui/look-page-widths.md
