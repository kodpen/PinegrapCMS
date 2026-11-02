---
name: contact-list-filters
description: Contacts list filters (contact_list.php): offered groups only, [None] from [All], range to today, box search, typed date, period links keep the view, export formula guard
module: contact_list
---

## Which group can the session hold?
- `pg_contact_list_clean_groups()` runs for every role: the picked group must be `[All]`, `[None]` (role < 3) or a group of `pg_contact_list_groups()`, else it is `[All]`; the advanced filters' checked groups lose any other value.
- Before, only role 3 was checked: a deleted group or `[Tümü]` left from a Turkish panel kept the list empty while the picker showed `[All]` (#5323).

## Why do the advanced filters open with [None] checked?
- From `[All]`, `pg_contact_list_initial_checked()` checks every group and, for role < 3, `[None]`; without it the contacts in no group dropped out the moment the filters opened (#5326).

## How does the date range move?
- `pg_contact_list_prepare($user, $groups, $request)` calls `pg_list_period_state()` with the oldest contact as the default start (`$start` parameter of `pg_list_period_state()`/`pg_list_period_default()`): unless picked, the range ends today on every request.
- `view_contacts.php` no longer copies the six date parts into the session itself; it passes `$_REQUEST`, so the advanced filters sending the untouched default back is no pick. The paging action passes nothing. A stop day kept from the session's first day hid the contacts added later (#5324).

## How do the advanced filter boxes match?
- Each box is one term of `pg_list_search_where()`, the table search's condition with the Turkish I/ı folding (#5328). Description filters in every view; only the opt-in box is left out of My Subscribers.

## What happens to a typed expiration date?
- `pg_date_input_iso()` (`includes/fn/forms.php`) reads ISO and the site's day/month order with `/ - , .` separators and a four-digit year; anything else is ''. A date that is no date is left out of the query and the box shows "The date is not valid." (#5327). Handed on raw, `31.12.2026` made MySQL refuse the comparison: the list said Query failed and the export wrote the error HTML into the file. The export now runs its query before the download starts.

## Why do the period links carry filter?
- The view is read only from `?filter=`. `pg_period_nav()` takes a sixth `$query` array that every link carries; the contacts list passes `array('filter' => $filter)` (#5329).

## How are export cells written?
- Text cells through `csv_cell()` (formula guard, #5335); `opt_in`, `expiration_date`, `affiliate_approved` and `affiliate_commission_rate` only quoted, so they stay numbers.

## Why does the duplicates view show no group picker?
- `$show_hide_contact_group_select` was set after the picker's markup was built; it is set first now (#5333).
