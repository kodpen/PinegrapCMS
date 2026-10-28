---
name: commission-list-paging
description: The commission lists (view_commissions.php, view_recurring_commission_profiles.php) paged and searched on the server; issue #2038 (search found only the page PHP printed); what the screens keep
module: commission_list
---

## What was wrong, and what changed?
- Both screens read 100 rows for a page of their own (`?screen=` links under the table) and DataTables searched only those, so a commission on page 2 was never found (issue #2038, also fixed for the submitted forms list in PR #2509). The rows, both counts and the search now come from `commission_list_rows` and `commission_profile_list_rows` (`includes/panel/lists.php`, gate role < 3 or `manage_ecommerce`, token checked). The `?screen=` links are gone; DataTables pages.
- Rows, sort and search are in `includes/fn/commission_list.php`; the sort stays the heading links and the sort menu (`pg_list_state()`), with the id as the last level.

## What stays on the commissions screen?
- The date range (advanced filters) in the session: `pg_commission_list_range()` defaults to a month ago to today and replaces a part that is not a number; the action reads the same range. The four totals (pending, payable, ineligible, paid) still cover the whole range, not the search.
- `update_recurring_commissions()` runs on the screen only.
- The status filter was never applied (`$sql_status` was always empty and its menu never printed); the dead code went, the behaviour is the same.

## What does the search box find?
- Affiliate name and code, reference code, order number, product with its short description, the users in Created and Last Modified, status and frequency by the label shown ("One-Time" is a commission with no profile), and an amount typed as digits with `.`/`,` against the stored minor units.
