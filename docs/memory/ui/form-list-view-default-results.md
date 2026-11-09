---
name: form-list-view-default-results
description: When a classic form list view page (get_form_list_view.php) lists its records, and why a page with browse and search both off always lists
module: forms
---

## When does a form list view page list its records?
- `pg_form_list_view_shows_results($search, $browse_enabled, $show_results_by_default, $visitor_filtered)` (`includes/fn/forms.php`) decides.
- Search off and no usable browse (`$browse_enabled`: browse on with at least one browse field that has a filter): always lists; the visitor has nothing to do first.
- Otherwise: lists when "Show Results by Default" is on, or the visitor picked a browse field and filter, searched, or sent the simple or advanced search form. Else the page sets `browse_or_search_required` and the template asks for a search.

## Why are `$browse_enabled`, `$search_query`, `$advanced_filter` and `$number_of_results` set at the top?
- The block that sets them runs only when browse or search is on. A page with both off left them unset, so the old `isset($browse_enabled) && …` clause never matched it: the page showed no records and "You may search above to find results." with no search box, and logged undefined-variable warnings (issue #7747). They now start as `false`, `''`, `false`, `0` before that block.

## How is it tested?
- `tests/form_list_default_results_test.php`: the helper's cases and the page wiring (initialisation before the block, no `isset($browse_enabled)` left).
