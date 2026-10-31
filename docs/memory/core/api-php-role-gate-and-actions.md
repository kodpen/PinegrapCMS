---
name: api-php-role-gate-and-actions
description: api.php general role gate before handlers, the exempt column, the designer endpoint's content-role allowlist, read vs write gates on shared_component, and the role numbers to compare against.
---

## Is there a role check before an api.php handler runs?
- Yes, a general gate BEFORE the handler: a non-exempt action needs a session, then `USER_ROLE > 1` gives "The User must have a Designer or Administrator role." (through `lang()`) An allowlist written inside a handler does nothing if the request never reaches it.
- Exemption used to be an `and ($action != '...')` chain before the `switch`; since 2026-10-13 it is the `exempt` column of the `includes/panel/actions.php` table (`pg_panel_gate()`), and `api.php` has no `switch` left. Adding an action: `panel-action-table.md`.
- `designer` is exempt; a content-level operator (manager, user) gets only the sub-actions in `$content_ok` of `pg_panel_designer()` (`includes/panel/designer.php`): `presence`, `leave`, `lock_release`, `note_save`, `notes_fetch`, `page_load`, `seo_check`, `import_fragment`, `form_fields`, `preview_widgets`, `widget_ghosts`, `revisions_list`, `revision_get`. A new sub-action stays closed to them until it is added there; the refusal is JSON, not `validate_area_access()`.
- Symptom of missing the gate: "two people opened the same page, no warning" (presence never sent); SEO check and canvas paste were also silently dead.

## Are read and write gated together?
- No, separately. Closing the `shared_component` endpoint entirely opened a widget-carrying page to a content role with 2 rows instead of 34: the page cannot be drawn without the widget's tree.
- Reads stay open (`list`, `get`, `prefetch`, `usage_all` and five `list_*` endpoints: custom forms, form item view pages, product groups, calendars, calendar event pages); every write is role <= 1. The one write below role 1 is `content_update`: a component a designer opened to content editors, saved under the page's merge, with its own checks in `includes/designer_access.php` (`designer/shared-component-content-editing.md`).

## Which role numbers do I compare against?
- `0 = Administrator, 1 = Designer, 2 = Manager, 3 = User` (docs/memory/core/access-control-and-roles.md).
- The `shared_component` endpoint once said `role != 0 && role != 2`, written for an old table where 2 was thought to be designer: it allowed the manager and rejected the designer. Check the table when writing a role comparison.
