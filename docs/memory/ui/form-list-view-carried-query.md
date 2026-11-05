---
name: form-list-view-carried-query
description: How a form list view's browse filter and pagination links carry the current query string, and the nested-list crash it fixed
module: forms
---

## How do a form list view's links keep the address's other parameters?
- `get_form_list_view.php` builds the browse filter links (every parameter not prefixed `<page id>_`) and the system layout's pagination links (every parameter but its page number) with `pg_form_list_carry_query($parameters, $skip)` in `includes/fn/forms.php`: a list is written as `name[]=value` pairs, joined with `&`, without the `?`.
- A nested list (`?a[x][y]=1`, or `b[k][]=3` inside a list) is left out; plain siblings stay.
- The pagination block parses the current address itself when browse and search are off, and reads its query only when there is one.

## What did this fix?
- Symptom: any visitor could take a form list view page down with `?a[x][y]=1`: `urlencode()` of the inner array threw a TypeError on PHP 8. A page without a query string and more than one result page logged an undefined `query` key warning.
- Tested in `tests/form_list_query_carry_test.php` (PR #6970).
