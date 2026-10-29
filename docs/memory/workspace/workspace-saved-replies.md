---
name: workspace-saved-replies
description: Saved replies (ws_saved_replies, 11.0.114, #3090): the table, the store's checks, personal rows only, the /workspace/saved-replies routes and tools, rows deleted with their user
---

## What is kept?
- `ws_saved_replies` (step 11.0.114, `install_create_table()`): `user_id`, `scope` (VARCHAR(16), 'personal'), `shortcut` (VARCHAR(32)), `title` (VARCHAR(100)), `body` (TEXT), `created_at`/`updated_at`; `KEY idx_user`, `UNIQUE uq_shortcut (user_id, scope, shortcut)`. Decided in #3075 (option b): `scope` is there now so team replies come without a table change or a key rebuild; only the role-3 right column (five ladder files, `pg_user_has_ws_columns()`) is left for them.
- `body` keeps `{customer}` and `{order}` as typed; they are filled when the reply is put in (10b, from the channel's customer, `ws_customer_summary()`), so no customer data is stored.
- The rows go with their user: `delete_users.php` and `edit_user.php` delete them beside `users_messages_xref`.

## How is a reply checked? (`includes/workspace/saved_replies.php`)
- `ws_saved_reply_shortcut()`: the `;` dropped, Turkish lower case (`ws_lower()`), one word of letters, digits, `-`, `_`, at most 32 characters; '' otherwise.
- `ws_saved_reply_input_check($input, $partial)`: pure; a new reply needs shortcut and body, no title means the shortcut; a change checks only the fields sent. Body at most `WS_MESSAGE_MAX` (it goes into a message).
- `ws_saved_reply_save_check()` adds the database: 503 `not_ready`, at most `WS_SAVED_REPLIES_MAX` (200) per person (409 `limit_reached`), shortcut free among the person's own (409 `shortcut_taken`). `ws_saved_reply_save()` calls it; an INSERT/UPDATE the unique key refuses is `shortcut_taken` too (collation: `utf8mb4_unicode_ci`).
- Every read and write names `user_id` and `scope = 'personal'`: a person never reaches another's row, administrators included.

## Which API routes and tools? (`api_saved_replies.php`)
- `GET/POST /workspace/saved-replies`, `GET/POST(+PATCH) /workspace/saved-replies/{id}`, `DELETE …/{id}` and `POST …/{id}/delete`; `workspace:read` / `workspace:write`, every write `dry_run`. Another person's id is 404. List, get, create and update are MCP tools; delete is not (cannot be undone, as notes).
- An application key writes the owner's replies as notes do: a reply is the owner's text, never posted by itself.
- Tests: `tests/workspace_saved_replies_test.php`.

## How are they put in and managed?
- The composer's `;` list and the drawer: `workspace-saved-replies-composer.md` (#3114).

## What is left?
- Team replies: a role-3 right column (five ladder files, `pg_user_has_ws_columns()`) and reading `scope = 'team'`.
