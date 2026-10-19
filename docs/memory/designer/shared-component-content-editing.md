---
name: shared-component-content-editing
description: Shared components opened to content editors (the designer's common region): content_editable flag, users_shared_components_xref grants, the rule, content_update under the page merge, schema 11.0.8
---

## What is it for?
- A common region is edited by managers and by the role-3 users granted it; a shared component was closed to the content level. To move common regions into the designer without taking them from those people, a designer can open a component: `shared_components.content_editable` (schema 11.0.8). Grants live in `users_shared_components_xref` (user_id, shared_component_id), the `users_common_regions_xref` model.

## Who may edit an opened component?
- `pg_shared_component_content_rule($role, $open, $is_widget, $granted)` (`includes/designer_access.php`): a manager every opened one, a user the granted ones, nobody a closed one; a system widget (row with `system_region_config`) never opens. `pg_shared_component_content_editable_ids($user, $ids)` answers it from the database; both are empty before the schema step (`pg_shared_component_content_ready()`).

## How is it saved?
- `shared_component` sub-action `content_update` (id, tree_json), the only write below role 1. The stored tree is merged with `pg_designer_merge_restricted_tree()`, as a page is: text and image addresses anywhere, the whole inside of `_editable` blocks, nested components and code nodes as stored, `<!--pg-` markers and `<?` defused. The merged JSON is answered so the editor keeps what was written. Notes are not stamped (the designer's own `update` does not stamp either).
- `set_content_editable` (id, content_editable 0/1, role 0-1) opens or closes a component and logs it; closing keeps the grants. A widget is refused.
- `list`, `get` and `prefetch` rows carry `content_editable` and `may_edit_content` (the session user).

## What cleans the grants up?
- Deleting a component (`delete`, `delete_many`) and deleting a user (`edit_user.php`, `delete_users.php`) remove their rows.

## What is not built yet?
- The designer toggle and editing an opened component on the canvas at content level (R1b), the grant checkboxes on the user screens (R1c): `plans/legacy-retirement-prerequisites.md`.
- Tests: `tests/shared_component_content_test.php`.
