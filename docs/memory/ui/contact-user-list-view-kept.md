---
name: contact-user-list-view-kept
description: Contacts and Users lists keep their view (filter) through Export, Delete All, Merge, Advanced Filters, Create and Import
module: contact_list
---

## Where does the Contacts list keep its view?
- `view_contacts.php` reads the view only from `$_GET['filter']`. Every control that reloads it carries `filter`:
  - `#export_form` (Export, Merge, Delete All Contacts) has a hidden `filter`: a GET form with no action replaces the whole query string.
  - The Advanced Filters toggle and close links end in `&amp;filter=` + `h(urlencode($filter))`; the `#search_advanced` form (Update) has a hidden `filter`.
  - Delete All Contacts redirects to `view_contacts.php?filter=<view>`.

## What went wrong before?
- Export, Delete All Contacts and Merge ran on the default view (every contact in the date range). Delete All on My Subscribers deleted every contact; Merge on All Duplicate Contacts passed every contact to `merge_contacts()`, which merged all blank-address contacts into one (`'' == ''`).
- Opening Advanced Filters or pressing Update reset the view to All My Contacts.

## How do Create and Import keep the view?
- Create and Import on both lists send `send_to=` + `h(urlencode(REQUEST_URL))`. The view select submits a hidden `filter` and its own (`?filter=a&filter=b`) and sort links add `sort=…`, so an unencoded address was cut at the first `&` and Create/Import/back arrow returned to the old view.
- The period arrows (`pg_period_nav()`, `includes/fn/output.php`) still drop `filter`.

Test: `tests/contact_user_list_view_kept_test.php`.
