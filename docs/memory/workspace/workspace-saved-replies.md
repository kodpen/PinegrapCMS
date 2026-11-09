---
name: workspace-saved-replies
description: Saved replies (ws_saved_replies, 11.0.114, #3090): the table, personal and team scopes, the store's checks, the /workspace/saved-replies routes and tools
---

## What is kept?
- `ws_saved_replies` (step 11.0.114, `install_create_table()`): `user_id`, `scope` (VARCHAR(16), 'personal'), `shortcut` (VARCHAR(32)), `title` (VARCHAR(100)), `body` (TEXT), `created_at`/`updated_at`; `KEY idx_user`, `UNIQUE uq_shortcut (user_id, scope, shortcut)`. Decided in #3075 (option b) so team replies came without a table change.
- A team reply (#8405) is a row with `scope` `team` and `user_id` 0: the unique key keeps a team shortcut unique for the whole team, and deleting a user takes no team reply along. `ws_saved_reply_owner_id()` gives the user_id a scope's rows carry; `ws_saved_reply_scope()` turns anything but exactly `team` into `personal`.
- `body` keeps `{customer}` and `{order}` as typed; they are filled when the reply is put in (10b, from the channel's customer, `ws_customer_summary()`), so no customer data is stored.
- The rows go with their user: `delete_users.php` and `edit_user.php` delete them beside `users_messages_xref`.

## How is a reply checked? (`includes/workspace/saved_replies.php`)
- `ws_saved_reply_shortcut()`: the `;` dropped, Turkish lower case (`ws_lower()`), one word of letters, digits, `-`, `_`, at most 32 characters; '' otherwise.
- `ws_saved_reply_input_check($input, $partial)`: pure; a new reply needs shortcut and body, no title means the shortcut; a change checks only the fields sent. Body at most `WS_MESSAGE_MAX` (it goes into a message).
- `ws_saved_reply_save_check($viewer, $input, $row, $scope)`: a team reply without the right is 403 `not_allowed` before anything is read; then 503 `not_ready`; then `ws_saved_reply_rows_check()` (pure) against the scope's rows: at most `ws_saved_reply_limit()` (200 per person, `WS_SAVED_REPLIES_TEAM_MAX` 500 for the team; 409 `limit_reached`), shortcut free within the scope (409 `shortcut_taken`; a personal and a team reply may share one). A change keeps the row's scope whatever is sent. `ws_saved_reply_save()` calls it; an INSERT/UPDATE the unique key refuses is `shortcut_taken` too (collation: `utf8mb4_unicode_ci`).
- Every read and write names `user_id` and `scope`: a person never reaches another's personal row, administrators included. `ws_saved_reply_delete()` refuses a team row without the right itself, not only its callers.

## Which API routes and tools? (`api_saved_replies.php`)
- `GET/POST /workspace/saved-replies`, `GET/POST(+PATCH) /workspace/saved-replies/{id}`, `DELETE …/{id}` and `POST …/{id}/delete`; `workspace:read` / `workspace:write`, every write `dry_run`. Another person's id is 404. List, get, create and update are MCP tools; delete is not (cannot be undone, as notes).
- `scope` (`personal`/`team`, default `personal`): a query parameter of the list, a body field of the create; an id route finds the owner's reply first, then the team's. A team write or delete without the right is 403 `forbidden`, checked before the rehearsal stops. Answers carry `scope`, never `user_id`.
- An application key writes the owner's replies as notes do: a reply is the owner's text, never posted by itself.
- Tests: `tests/workspace_saved_replies_test.php`.

## Who may write team replies?
- `workspace-saved-replies-team.md` (#8405): the right, its column and where it is drawn.

## How are they put in and managed?
- The composer's `;` list and the drawer: `workspace-saved-replies-composer.md` (#3114). `ws_saved_replies_for_box()` gives the person's replies, then the team's (`ws_saved_replies_order()`), so on a shared shortcut the person's comes first; a non-member reads none of the team's. The `ws_saved_replies` action also answers `manage_team`; `ws_saved_reply_save` / `ws_saved_reply_delete` take `scope`.
- The e-mail reply box opens the same `;` list (#8402): `workspace-saved-replies-composer.md`.
