---
name: shared-component-content-editing
description: Shared components opened to content editors (the designer's common region): content_editable flag, users_shared_components_xref grants, the rule, content_update under the page merge, schema 11.0.8
---

## What is it for?
- A common region is edited by managers and by the role-3 users granted it; a shared component was closed to the content level. To move common regions into the designer without taking them from those people, a designer can open a component: `shared_components.content_editable` (schema 11.0.8, PR #575). Grants live in `users_shared_components_xref` (user_id, shared_component_id), the `users_common_regions_xref` model.

## Who may edit an opened component?
- `pg_shared_component_content_rule($role, $open, $is_widget, $granted)` (`includes/designer_access.php`): a manager every opened one, a user the granted ones, nobody a closed one; a system widget (row with `system_region_config`) never opens. `pg_shared_component_content_editable_ids($user, $ids)` answers it from the database; both are empty before the schema step (`pg_shared_component_content_ready()`).

## How is it saved?
- `shared_component` sub-action `content_update` (id, tree_json), the only write below role 1. The stored tree is merged with `pg_designer_merge_restricted_tree()`, as a page is: text and image addresses anywhere, the whole inside of `_editable` blocks, nested components and code nodes as stored, `<!--pg-` markers and `<?` defused. The merged JSON is answered so the editor keeps what was written. Notes are not stamped (the designer's own `update` does not stamp either).
- `set_content_editable` (id, content_editable 0/1, role 0-1) opens or closes a component and logs it; closing keeps the grants. A widget is refused.
- `list`, `get` and `prefetch` rows carry `content_editable` and `may_edit_content` (the session user).

## What cleans the grants up?
- Deleting a component (`delete`, `delete_many`) and deleting a user (`edit_user.php`, `delete_users.php`) remove their rows.

## How does the editor show it?
- The designer's switch "Content editors" sits in the Shared Component section of a selected `shared_ref` (not for widgets, only while `sdDesign.sharedContentReady`); it calls `set_content_editable` at once and keeps `contentEditable` on the cache entry.
- `prefetch` fills `mayEditContent` on `_sharedCache[sid]`. `_sdSharedOpenToContent(sid)` reads it; `_sdNodeSharedSid(node)` comes from the owner map built in `_sdEditableSet()`'s pass, so no per-node walk. `_sdCanEditNode()` refuses every node of a closed component (`_sdInClosedShared()`, text included) and applies the page rule inside an opened one; canvas fade, tree marks, the read-only panel and the nudge follow it.
- `_sdTrackSharedEditing()` marks only an opened component dirty at content level; `_flushDirtyShared()` sends it as `content_update` and, when the answer differs from what was sent, puts the stored tree in the cache and redraws.
- Symptom fixed with it (PR #579): a manager could type into a shared component's text (only widgets were refused on screen) and the save failed with "Permission denied.".

## Where are the grants given?
- User screens (add, edit, import; PR #580): Shared Content panel, list "Shared components the User may edit" (fields `shared_component_<id>`), shown only when a component is open; `get_checkboxes_for_items_user_can_edit('shared_components')` / `get_items_user_can_edit()` in `includes/fn/content.php`. Saved for role 3 by `pg_save_shared_component_grants($user_id, $_POST)`, which rewrites only the rows of open components: a grant on a closed one stays for when it opens again.
- A role-3 user still reaches a component only through a page the designer lets them open (folder edit right); owner 2026-10-27: they open it on its own in the designer (`shared-component-opened-alone.md`). Common regions made components: `common-region-to-shared-component.md`. Tests: `tests/shared_component_content_test.php`.
