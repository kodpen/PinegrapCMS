---
name: screen-header-actions-steps
description: Steps H1-H6 of plans/screen-header-actions.md: which screens each fixes, its files and size; claim as [plan:screen-header-actions] H<n>
---

## Which steps are there?
- S is under 400 product lines, M is about 1,000. File lists are from a29a87c68; re-read before use. Steps that touch different screens may run side by side.
- H1 comes first: H2-H6 draw their bars through its helper.

- H1 (S) Toolbar helper.
  - Add `pg_screen_toolbar()` in `includes/fn/output.php`. It draws `nav#button_bar.pg-toolbar` from slots: primary, secondary, grow, search, view, overflow. It draws nothing when no slot is filled.
  - Remove `pg_page_shell()`'s `actions`/`primary_button`/`action_bar_class` and the orphan docblock in `includes/fn/tour.php` (22-41).
  - `workspace_calendar_feed.php` gets a back arrow and breadcrumb to Work Calendar instead.
  - Test: `panel_screen_titles_test.php` refuses the removed keys.

- H2 (M) The way back.
  - Give each screen whose bar holds a "back to list" copy a `cancel` url and a breadcrumb, then drop the copy: `add_return`, `view_return`, `view_returns`, `view_subscriptions`, `view_cart_quotes`, `product_builder`, `marketplace_products`, `workspace_template`, `workspace_wtables`, `workspace_mcp`, `find_and_replace`, `erp_stock` (card link), `erp_cashflow`.
  - Also drop the bottom link of `workspace_calendar.php` and the JS-drawn ones in `workspace_flow.js` and `workspace.js`; rebuild the bundles.
  - Add the six unlisted `history.go(-1)` arrows to `back-arrow-targets.md`: `privacy_erase`, `privacy_export`, `generate_layout`, `barcode_increase_inventory`, `barcode_decrease_inventory`, `import_design`.

- H3 (M) Bars out of `.row`/`.col`.
  - The screens that wrap `#button_bar` move it directly into `<main>` through the helper.
  - The second tool columns join the bar's right end: `view_pages`, `view_ads`, `product_builder`, `editor_select_page_or_file`, `view_log`, `view_shipping_report`.
  - `view_pages`' eight buttons go to slot 1 plus one menu.
  - Split the screens into two PRs if needed.

- H4 (M) Lone controls and fact lines.
  - Bars whose only control sits at the right: `erp_invoice_recurrences`, `view_audit_log`, `view_waf_log`, `view_not_found_log`.
  - Report headers: `view_order_report`, `view_visitor_report`, `view_performance_log`.
  - Lone buttons: `erp_bank_statement`, `erp_stock_count`, `calendars.php`.
  - The "Duplicate" rows of the edit screens join their bar.
  - "Disk Usage" lines (`view_files`, `view_design_files`, `backups`) go into the bar.
  - Remove the `[name]` second headings and the "Page Style Body Tag" fact rows (these move into the record's card).

- H5 (S) Screen-wide actions and recipes.
  - "Clear Log" and the CSP clear (`view_waf_log`), "New department" (`workspace_settings`), the primary placed last (`api_settings`), and the related link drawn as primary (`view_shipping_report`).
  - Align `admin-screen-toolbar-rules.md` with slot 2's `btn-text`.

- H6 (S) Workspace screens.
  - `workspace_mail` filters: pill rows become `pg_filter_select()`.
  - `workspace_calendar_feed` joins the Workspace frame.
  - Write the lasting rules into `ui/admin-screen-toolbar.md` and the decision into the decisions list, then delete this plan.
