---
name: api-php-role-gate-and-actions
description: api.php general role gate before handlers, exempt table, designer allowlist, read vs write gates on shared_component, and the role number table.
---

## Is there a role check before an api.php handler runs?
- Yes, a general gate BEFORE the handler: every non-exempt action needs a session, then `USER_ROLE > 1` gives "The User must have a Designer or Administrator role." An allowlist written inside a handler does nothing if the request never reaches it.
- Exemption used to be an `and ($action != '...')` chain before the `switch`; since 2026-10-13 it comes from the `exempt` column of the `includes/panel/actions.php` table (`pg_panel_gate()`); `api.php` has no `switch` left.
- `designer` is exempt (`exempt => true`); its own allowlist decides: `presence`, `leave`, `lock_release`, `note_save`, `page_load`, `seo_check`, `import_fragment`, `form_fields` — the rest is rejected.
- Symptom of missing the gate: "two people opened the same page, no warning" (presence never sent); SEO check and canvas paste were also silently dead.

## Are read and write gated together?
- No, separate gates. Closing the `shared_component` endpoint entirely opened a widget-carrying page to a content role with 2 rows instead of 34: the page cannot be drawn without the widget's tree. Read endpoints (`list`, `get`, `prefetch`, `usage_all` and three list endpoints) stay open; everything that writes is role <= 1.

## Which role numbers do I compare against?
- `0 = Administrator, 1 = Designer, 2 = Manager, 3 = User` (see docs/memory/core/access-control-and-roles.md).
- The `shared_component` endpoint once said `role != 0 && role != 2`, written for an old table where 2 was thought to be designer: it allowed the manager and rejected the designer. Check the table when writing a role comparison.
