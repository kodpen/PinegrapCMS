---
name: dashboard-v2-content-cards
description: New dashboard cards gsearch, ga, content, comments, files, activity: data sources, helpers shared with widgets 7, 18, 25, what was left out
---

## Where do the cards read from?
- `includes/dashboard/v2/cards/<id>.php`, drawn by `assets/js/pg_dash_cards_content.js` (content, comments, files, activity) and `pg_dash_cards_visitors.js` (gsearch, ga). Added in #4861.
- `comments`: `pg_dashboard_waiting_comments($user, $limit)` in `widgets/widget_25.php`, the widget's own query moved into a helper both call (scope as `view_comments.php`, binned pages left out).
- `files`: `pg_dashboard_files_where($user)` in `widgets/widget_18.php` (archived folders out, design files only role < 2, contributors by granted folder; null = nothing granted). The ring sums `files.size` by type group; "free" comes from `disk_free_space(FILE_DIRECTORY_PATH)` and is left out where the host disables it.
- `activity`: widget 7's `pg_dashboard_activity_kinds()` with five rows a kind, its HTML rows turned into plain text (`pg_dashboard_v2_plain()`). Chips: Content = content changes, comments, campaigns; Form = forms, contacts; Team = users; Security = site log; orders only under All. A chip shows only when one of its kinds is open to the person.
- `gsearch`: `search_console.php` totals; periods 0/1/2 are 1, 7 and 28 days ending on `pg_gsc_latest_day()` (Google is two or three days late). Average position's tone is inverted: a smaller number is better.
- `ga`: the kept 28-day report of `pg_ga_state()`. It cannot be cut to another period and has no average time, so the card's `period` is null and the fourth tile is views. Sources: three and "Other", whole shares adding to 100.
- `content`: the four pages with the newest `page_timestamp` (binned left out; a contributor's filtered by `pg_folder_edit_access()`), state from `pg_page_is_draft()` and `publish_at` when the column exists, link `pg_page_edit_url()`. The picture is a drawn sketch, not a screenshot: no page thumbnail exists.

## What was left out?
- Comment Spam: `comments` has no spam state and no endpoint changes a comment, so Approve opens `edit_comment.php` (the rule "with no endpoint, open the record") and Spam is not drawn. A real Approve/Spam needs an endpoint and, for spam, a schema decision.
