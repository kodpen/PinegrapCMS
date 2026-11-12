---
name: performance-log-screen
description: Performance Log screen (view_performance_log.php): who may clear it, the period select, the search box's character cap, the slowest requests table's labels and dates
---

## Who may open and who may clear the Performance Log?
- Opening: `validate_area_access($user, 'manager')` (role 2 and above).
- Clearing (the trash button and the `submit_clear` branch, which TRUNCATEs `perf_stats` and `perf_log`): `pg_log_clear_allowed(MASS_DELETION, USER_ROLE)` (`includes/fn/forms.php`), the activity log's rule: administrators, or everyone when mass deletion is on. Others see no button, and a post is refused with "Access denied" and logged (decision in `workflow/decisions-made-product-21.md`, #5843). Before, any screen user could clear it.

## Which period does the select show?
- `range` is `1`, `7` (default) or `30` days, read as a string from the address. The option loop compares `(string) $value === $range`: PHP holds the numeric keys of `$range_options` as integers, and the strict compare without the cast never matched, so the select showed "Last 24 hours" over the 7-day list and changing Area sent `range=1` (#9041). `view_waf_log.php` builds its period select the same way.

## How is the search term cut?
- `pg_log_search_term($value, 200)`: trimmed, cut by characters with `mb_substr()`; the box carries `maxlength="200"`. Before, `substr()` cut 200 bytes, a Turkish letter could be split, `h()` printed the broken text as an empty box and nothing was found (#5839).

## How does the slowest requests table read?
- The HTTP method column is `lang('HTTP method')` ("Yöntem"): the short key `Method` is "Metod" for other screens (`erp/erp-translation-traps.md`).
- Its time is `prepare_form_data_for_output(…, 'date and time')`, the site's format as on the audit trail, not a fixed `Y-m-d H:i:s` (#5840).
