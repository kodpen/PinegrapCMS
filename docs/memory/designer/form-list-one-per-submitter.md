---
name: form-list-one-per-submitter
description: form_list_view option one_per_submitter ("Newest record per submitter"): each signed-in submitter's newest record only, anonymous rows kept, count, paging and search after the collapse (#7508)
module: widgets
---

## What does `one_per_submitter` do?
- A form list widget config key (`system_region_config`, like `max_results`; no column, no migration), default off. On, the list keeps for each `forms.user_id` > 0 only the highest `forms.id` among the form's complete records; rows with `user_id` 0 (sent without signing in) are never collapsed.
- `pg_sw_form_list_one_per_submitter_sql($form_page_id, $cfg)` in `includes/fn/widgets.php` returns the `AND (…)` clause, or `''` when off. `_render_system_widget_form_list()` adds it to its three queries: the count, the exact-value check of a URL filter and the rows.
- Owner decision (#7530, recorded in `workflow/decisions-made-product-36.md`): the Social template's profiles, sent again to change them, showed every old picture and bio in People.

## Why is the newest chosen before the filters and the search?
- The decision says the count, paging and search run after the collapse, so `^^__result_count^^` is the number of cards. The derived table picks each member's newest record of the whole form; an older record that alone matches a search or a `?topic=` never comes back. A member whose newest record does not match is simply not listed.
- A derived table (`SELECT … FROM (SELECT MAX(id) … GROUP BY user_id) t`), so the database builds the id list once instead of per row.

## What does it not change?
- The record view, its `?r=` address, the list's feed and sitemap, the search index and the panel: old records stay in the database and open by their address. A list without the option still shows every record.

## Where is it set?
- Designer, List Settings of a form list: "Newest record per submitter" (`data-sw-cfg="one_per_submitter"`, `data-sw-cfg-kind="bool"`).
- Social template: `people_list` and `staff_members_list` (new installs, `pinegrap-social/profiles.php`).
