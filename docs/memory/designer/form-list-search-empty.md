---
name: form-list-search-empty
description: The form list's empty state when a visitor's search matches nothing: "Nothing matched your search", not the configured empty message
---

## What does a form list print when its search finds nothing?
- `lang('Nothing matched your search. Try fewer or different words.')`, from `pg_sw_form_list_empty_message($configured, $search_active)` (`includes/fn/widgets.php`), in the same empty-state element (`pg_sw_state_message_html()`, class `pg-sw-empty`).
- Without an active search (`$search_active`: search on, a query, fields to search) it is the widget's `empty_message`, else "No records found.". No setting, no script; the canvas is unchanged.

## Why?
- #3458 (item 2): a list whose configured message read "No topics yet." said so after a search with no hits, as if the list were empty. Fixed in #3574.
