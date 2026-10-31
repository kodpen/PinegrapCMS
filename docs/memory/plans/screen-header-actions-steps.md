---
name: screen-header-actions-steps
description: Steps H1-H6 of plans/screen-header-actions.md: which screens each fixes, its files and size; claim as [plan:screen-header-actions] H<n>
---

## Which steps are there?
- S is under 400 product lines, M is about 1,000. File lists are from a29a87c68; re-read before use. Steps that touch different screens may run side by side.
- H1 comes first: H2-H6 draw their bars through its helper.

- H1 (S) Toolbar helper. Done in #3912.
  - Add `pg_screen_toolbar()` in `includes/fn/output.php`. It draws `nav#button_bar.pg-toolbar` from slots: primary, secondary, grow, search, view, overflow. It draws nothing when no slot is filled.
  - Remove `pg_page_shell()`'s `actions`/`primary_button`/`action_bar_class` and the orphan docblock in `includes/fn/tour.php` (22-41).
  - `workspace_calendar_feed.php` gets a back arrow and breadcrumb to Work Calendar instead.
  - Test: `panel_screen_titles_test.php` refuses the removed keys.

- H2 (M) The way back. Done (#3933, #3971).
  - Give each screen whose bar holds a "back to list" copy a `cancel` url and a breadcrumb, then drop the copy: `add_return`, `view_return`, `view_returns`, `view_subscriptions`, `view_cart_quotes`, `product_builder`, `marketplace_products`, `workspace_template`, `workspace_wtables`, `workspace_mcp`, `find_and_replace`, `erp_stock` (card link), `erp_cashflow`.
  - Also drop the bottom link of `workspace_calendar.php` and the JS-drawn ones in `workspace_flow.js` and `workspace.js`; rebuild the bundles.
  - Add the six unlisted `history.go(-1)` arrows to `back-arrow-targets.md`: `privacy_erase`, `privacy_export`, `generate_layout`, `barcode_increase_inventory`, `barcode_decrease_inventory`, `import_design`.

- H3 (M) Bars out of `.row`/`.col`. H3a done (#4050): the 25 content screens (list in `ui/page-button-bar.md`). H3b: the other wrapped bars, `product_builder` included.
  - The screens that wrap `#button_bar` move it directly into `<main>` through the helper; a second tool column joins the bar's right end (`product_builder` left).
  - Split the screens into two PRs if needed.

- H4 (M) Lone controls and fact lines. Done (#3966, #4048).
  - Right-only bars, report headers, lone buttons, Duplicate rows: one `pg_screen_toolbar()` bar directly in `<main>`, Duplicate as slot 2.
  - Facts (counts, Disk Usage, log summaries, `[name]`, Page Style Body Tag) are text in the grow slot, beside the actions; a description in that row became `heading_description`.

- H5 (S) Screen-wide actions and recipes. Done (#4008).
  - `view_waf_log` clears in the overflow menu; New department is `workspace_settings`' primary; `api_settings` primary first; `view_shipping_report`'s link in slot 2 and its filter at the right end (H3 skips that screen); `admin-screen-toolbar-rules.md` uses the slot recipes.

- H6 (S) Workspace screens.
  - `workspace_mail` filters: pill rows become `pg_filter_select()`.
  - `workspace_calendar_feed` joins the Workspace frame.
  - Write the lasting rules into `ui/admin-screen-toolbar.md` and the decision into the decisions list, then delete this plan.
