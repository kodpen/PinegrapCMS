---
name: list-stale-filter
description: All Ads, Verified Shipping Addresses and Submitted Forms reset a remembered filter whose ad region, state or custom form is gone to '[All]' (pg_list_filter_known())
---

## Why did a list come up empty under '[All] (N)'?
- These screens keep their filter pick in the session as a record id. When the record was deleted (or a role-3 user lost access to it) no `<option>` matched, so the browser showed the first one, '[All] (N)', while the WHERE clause still narrowed to the gone id. All Ads: Delete Ad Region refuses a region that still has ads, so the list was always empty. States: deleting a state deletes its verified addresses. Submitted Forms: only a binned form's rows, or none.

## What happens now?
- `pg_list_filter_known($value, $ids, $all = '[All]')` (`includes/fn/list_paging.php`, always loaded): a string compare of the remembered value against the ids the pick list offers; anything else (unknown id, empty, not a string) becomes '[All]'.
- `view_ads.php` calls it after `$ad_regions` is read (role 3 gets only its regions), `view_verified_shipping_addresses.php` after `$states` is read; both write the answer back to the session before the options are printed.
- Submitted Forms: inside `pg_submitted_form_list_defaults($custom_forms)`, which the screen and the paging action (`includes/panel/lists.php`) both call. It resets `custom_form` and drops unknown ids from the advanced filters' `custom_forms`.
- `view_contacts.php` / `contact_list.php` already did this for the contact group.

## Where is it pinned?
- `tests/list_stale_filter_test.php`: the helper, the Submitted Forms defaults and a source scan of the two screens.

Related: docs/memory/ui/list-page-state.md
