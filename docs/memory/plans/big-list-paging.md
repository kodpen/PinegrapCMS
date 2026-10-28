---
name: big-list-paging
description: Steps of refactor-candidates item 3: the big panel lists (users, submitted forms, contacts, products) paged, searched and counted on the server through a JSON action, one screen per step
---

## What does every step keep?
- `table.chart` and DataTables stay (`bootstrap-6-redesign.md`); only the rows come from an `api.php` action with the screen's own gate and the form token, one page at a time. Sort stays `pg_list_state()` heading links; the action reads the kept sort from the session.
- What a person can do stays: select all and the bulk buttons act on the rows on screen, as they did (DataTables kept only the drawn page in the DOM); exports and "all that match" actions keep reading the whole filtered list on the server.
- The search box finds what it found: every word in one of the columns the row shows as text (DataTables' smart search), labels matched through their stored values.
- Claim as `[plan:refactor-candidates] 3 — <screen> list paged on the server`; one step at a time.

## Which steps are there?
- 1. Users (`view_users.php`): done, PR #2460 (`includes/fn/user_list.php`, generic helpers `includes/fn/list_paging.php`, `ui/server-paged-lists.md`).
- 2. Submitted forms (`view_submitted_forms.php`): done, PR #2509; the folder rule is SQL (`page.page_folder IN` the folders `check_edit_access()` passes); fixed the forms part of #2038.
- 3. Contacts (`view_contacts.php`): done, PR #2528; the duplicates view stays printed whole.
- 4. Products (`view_products.php`): done, PR #2655 (`ui/product-list-paging.md`).
- 5. Commissions (`view_commissions.php`, `view_recurring_commission_profiles.php`): done, PR #2675; closes #2038.
- 6. Audit trail (`view_audit_log.php`): done, PR #2719; changes read when a fold opens (`ui/audit-log-paging.md`).
- 7. Firewall log (`view_waf_log.php`): it printed the newest 500 rows of the period. Now paged, sorted and searched on the server, period/category/search filters kept in the address and sent nested (`filters`). `includes/fn/waf_log_list.php`, action `waf_log_list_rows` (`ui/waf-log-paging.md`). PR #PRNUM.
- 8. `sitemap.xml` built on every request (`get_sitemap.xml.php`): a cache only if its invalidation points are safe. Not started.
