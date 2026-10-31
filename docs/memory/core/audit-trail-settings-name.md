---
name: audit-trail-settings-name
description: A Site Settings save in the audit trail and the Site Log: the category key is stored, the category's name is shown
module: audit_view
---

## What does a settings save store?
- `includes/settings/screen.php` and `fragment.php` record `pg_audit_record('update', 'config', 0, $before, $key)`: the label is the category key (`seo`, `api`, `maintenance`). Keys stay when a category is renamed (API became Integrations, #4067), so the key is kept.

## How is it shown?
- `pg_audit_list_row()` shows the label through `pg_audit_view_record_name($type, $label)`: for type `settings` the category's `label` from the registry in the panel language (`pg_audit_view_settings_names()`, read once per request), the key when the registry no longer knows it; any other type as stored. Before (#4158) the screen read "Settings api".
- The text search (`label LIKE`) and the CSV still use the stored key.

## What does the Site Log say?
- "the {var:1} settings were modified" with the category's name (the key when unknown), written in the panel language of the request, like every `log_activity()` line. Before, "settings were modified" without the category.

## Tests?
- `tests/audit_file_content_test.php`: names by key, unknown key, other types; every `*.save.php` module has a `label` in the registry. PR #4260.

Related: docs/memory/core/audit-trail-screen.md, docs/memory/core/audit-trail-writers.md
