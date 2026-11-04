---
name: commission-list-paging
description: The commission lists searched on the server, on the list table driver since #6380; issue #2038 (search found only the page PHP printed); what the screens keep
module: commission_list
---

## What was wrong, and what changed?
- Both screens read 100 rows for a page of their own and DataTables searched only those, so a commission on page 2 was never found (issue #2038). Server paging came with #2675; since #6380 both lists are list table driver sources (`pg_commission_list_source()`, `pg_commission_profile_list_source()`, `ui/list-table-driver-lists.md`): rows in batches, search and heading sort on the server, the sort menu kept (`pg_list_state()`), the id as the last level.
- Columns, rows, sort and search are in `includes/fn/commission_list.php`; status and frequency sort by the label shown (`pg_list_label_order()`).

## What stays on the commissions screen?
- The date range (advanced filters) in the session: `pg_commission_list_range()` defaults to a month ago to today and replaces a part that is not a number; the rows action reads the same range. The four totals (pending, payable, ineligible, paid), cards above the list, cover the whole range, not the search.
- `update_recurring_commissions()` runs on the screen only.
- The status filter was never applied (`$sql_status` was always empty and its menu never printed); the dead code went, the behaviour is the same.

## What does the search box find?
- Affiliate name and code, reference code, order number, product with its short description, the users in Created and Last Modified, status and frequency by the label shown ("One-Time" is a commission with no profile), and an amount typed as digits with `.`/`,` against the stored minor units.
