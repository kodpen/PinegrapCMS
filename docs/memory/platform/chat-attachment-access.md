---
name: chat-attachment-access
description: Panel team chat attachments open only for the conversation's sides or a role-0 administrator, served private, no-store, whatever their folder allows (#5973)
---

## Who may open a panel chat attachment?
- A file referenced by `chat_messages.attachment_file_id` of a `chat_conversations.channel = 'backend'` conversation opens only for a signed-in side of a conversation carrying it (`initiator_user_id` or `target_user_id`) or a role-0 administrator. Pure verdict: `pg_chat_attachment_access_verdict()` in `includes/authentication.php` (shared by `get_file.php`, which never loads `functions.php`).
- The folder's own setting does not decide: the attachment usually sits in the top folder, which is public (`pg_default_upload_folder('chat_upload_folder_id')`). Same principle as submitted-form files (#1989, `workflow/decisions-made-files.md`).

## Do the file manager's readers ask it too?
- Yes (#PRNUM): `explorer_office_preview` and the file window's text (`explorer_file_get`) ask `pg_file_panel_content_verdict()` (`includes/fn/files.php`, conversations from `pg_file_chat_conversations()`), which also keeps a form attachment to edit rights on its folder (#1989). Before, the Office preview gave both to anyone who could view the folder, the file window a chat attachment's text to any manager. Tests: `tests/file_panel_content_test.php`.

## What does a refused request get?
- Not signed in: `get_file_sign_in_redirect('index.php')`, so a sub-resource fetch gets 403 and a page opening the panel sign-in. Signed in but no side: a logged 403 with `Cache-Control: no-store`.

## How is it cached?
- `Cache-Control: private, no-store`, like an ERP document: no shared cache and no browser copy, so "Delete conversation" takes effect at once. Copies a CDN kept before the fix fall out within the week; nothing purges them.

## What about site (visitor) chat?
- Not gated here: only `channel = 'backend'` rows count; a visitor conversation's files follow their folder as before.

## How is it found?
- One query with `@mysqli_query`: before the chat tables or the attachment column exist it fails and nothing is a chat attachment, so no readiness probe is needed in `get_file.php`.

## Tests?
- `tests/chat_attachment_access_test.php`; `tests/waf_subresource_test.php` counts the sign-in redirects.
