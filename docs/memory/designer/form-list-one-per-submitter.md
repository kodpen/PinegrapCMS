---
name: form-list-one-per-submitter
description: The form list's one_per_submitter option: each signed-in submitter's newest record only; used by the Social template's People and staff lists
---

## What does `one_per_submitter` do?
- A form list config key (`system_region_config`, no column), default off. On, `pg_sw_form_list_one_per_submitter_sql($cfg)` (`includes/fn/widgets.php`) adds an `AND` condition: a record with `forms.user_id > 0` is listed only when no newer complete record (higher `forms.id`) of the same form has the same `user_id`. Records with `user_id` 0 are never collapsed.
- The condition joins `$filter_where`, so the count (`^^__result_count^^`), the pages, the address check and the rows all see the collapsed set.

## Why does it look at every record, not only the filtered ones?
- So an older record never stands in for a newer one a filter or the search holds back: a member's old picture and words must not come back. The filters, the visitor's search and the viewer filter apply to what is left (owner decision on #7530, `workflow/decisions-made-product-36.md`).

## What does it leave alone?
- The record view, the feeds, the sitemap, the search index and the panel: old records stay in the database and show there.

## Where is it set?
- Designer: List Settings, "Newest record per submitter" switch (generic `data-sw-cfg-kind="bool"` control); the canvas preview follows it because the real renderer draws it.
- Social template: `people_list` and `staff_members_list` (`pinegrap-social/profiles.php`) for new installs. Fixes #7508 (People showed one card per profile ever sent).
