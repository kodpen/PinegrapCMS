---
name: api-user-role-rows
description: Which rows an external API owner with the user role (3) reaches: contact groups, customers, campaigns, calendars, forms and submissions, 404 outside, the tests that guard it
---

## Which rows does an owner with the user role (3) reach?
- A scope opens a kind of record; for role 3 the endpoints also narrow the rows the way the panel screen does, and a row outside is answered as if it did not exist (404). Roles 0-2 reach every row.
- Contact groups: those in `users_contact_groups_xref` (`api_contact_group_owner_filter()`).
- Customers (list, get, update, both deletes): contacts filed in one of those groups (`api_customer_owner_filter()`, the rule of `validate_contact_access()`); a contact in no group is outside. `POST /customers` files the record in one of them (`contact_group_id`, or the owner's only group; several and none sent → 422), with an opt-in row of 0 unless `opt_in=true`. `POST /contact-groups/{id}/members` adds only contacts already in reach. Before PR #956 every contact was served. `tests/api_customers_test.php` fails when a customers route skips the rule.
- Campaigns: the ones it created (`api_campaign_owner_filter()`). Calendars and events: the assigned calendars (`api_calendar_ids_visible()`).
- Forms and their submissions (list, get, submissions, one submission, its update): only forms on pages in folders the account may edit, i.e. `check_edit_access()` on the form page's folder as on `view_submitted_forms.php` / `edit_submitted_form.php`. The set is `get_folders_that_user_has_access_to()` (folders with `aclfolder_rights = 2` and everything below them; a view-only row gives nothing). `api_form_owner_filter($owner, $page_id_column)` builds the condition, `api_form_submission_owned()` applies it for the submission routes. Before PR #933 every forms endpoint served every form and submission to such an account, its own password (`POST /auth/login`) was enough. `tests/api_forms_test.php` fails when a forms route skips the rule.
- A new endpoint under a scope role 3 can hold copies its screen's row rule; the scope alone opens every row.

Related: `external-api-authorization.md` (the scope gates).
