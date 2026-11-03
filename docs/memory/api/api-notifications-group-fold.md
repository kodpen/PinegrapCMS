---
name: api-notifications-group-fold
description: GET /notifications group filter (site or workspace, applied in SQL before paging) and the fold key on every Notification row, the panel bell's chips and flow-run folding (#5085)
---

## What does group do on GET /notifications?

`group=workspace` lists only rows whose `action` is `workspace` (what the
workspace told this person); `group=site` lists every other row, a NULL
action included. No group lists every row. It is the panel bell's split
(`includes/panel/notifications.php`, `'group'` on each item). Another value
answers 400 like any bad enum. The condition comes from
`api_notifications_group_where()` and goes into the WHERE before the cursor
and the batches, so a page holds `limit` rows of the group when there are
that many (filtering on the client threw most 30-row pages away).

## What is fold on a Notification?

The key the panel folds a row by: `pg_notification_display()`'s `fold`,
filled only for workspace rows from `ws_bell_fold_key()`
(`flow:<flow id>:ended|failed` for a flow run's notice). An empty key is
`null` (`api_notification_fold()`). The server does not fold the list: a
client folds rows with the same key under the newest, as
`pg_notification_fold()` does for the panel. Declared `string?` in
`api_notification_schema()`.

## Which test guards it?

`tests/api_notifications_group_fold_test.php`: both groups and no group, a
failed flow run's key, the schema field and the route's enum.
