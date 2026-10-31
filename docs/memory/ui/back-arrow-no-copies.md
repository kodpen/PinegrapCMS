---
name: back-arrow-no-copies
description: The way back to a parent or list screen is only the back arrow and the breadcrumb; which back-to-list copies were removed and where the screens now go back
---

## How does a screen offer the way back?
- Only by the back arrow (`pg_page_shell()` `'cancel'`) and the breadcrumb strip under the top bar. A toolbar button, card link or bottom link that repeats it ("All Orders", "All Returns", "← Marketplaces") is not drawn. A link to a related screen that is not the parent stays in the toolbar (`view_return.php` keeps "View Order").
- Why: owner, 2026-10-30: buttons and links sitting alone under a screen's title, and "back to the parent" drawn nine ways (`plans/screen-header-actions.md`).

## Which screens lost their copy (#3933)?
- Returns: `add_return.php` (arrow to Returns, or to the order on the second step), `view_return.php`, `view_returns.php` (arrow to All Orders).
- `view_subscriptions.php` (list: All Orders; record: the list), `view_cart_quotes.php`, Variant Sets (`product_builder.php`), `marketplace_products.php` (arrow to the account's Marketplaces).
- Workspace: `workspace_template.php` (Channel templates), a table on `workspace_wtables.php` (Tables; the list itself has no arrow), `workspace_mcp.php` (MCP servers, only for someone with Workspace settings).
- `find_and_replace.php` results: through `pg_page_shell()`, the summary is the heading description.
- ERP: `erp_stock.php` with one product (arrow to the whole stock), `erp_cashflow.php` both views (Cash and Bank).
- `workspace_calendar.php` lost its bottom link to Calendar subscription; that screen is in the Workspace nav and the menu.
- Pinned by `tests/back_arrow_targets_test.php` (`test_back_arrow_replaces_back_to_list_copies`).

## What about the views Workspace scripts draw in the page?
- Their in-view back buttons became the page's arrow too (#3971): `back-arrow-targets.md`, "inside a Workspace view".
