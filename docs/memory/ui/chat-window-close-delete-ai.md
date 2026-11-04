---
name: chat-window-close-delete-ai
description: Panel chat window: close/delete leave only on success, the closer's read cursor, image attachments as links, the AI Chat snippet's texts and theme
---

## When do Close and Delete Conversation leave the conversation?
- Only when `chat_close` / `chat_delete` answer `status: success` (`leaveOnSuccess()` in `chat_backend.src.js`). A refusal (expired token, access denied, not found) keeps the view and alerts the server's message; a failed request alerts `STR.request_failed` (`lang('An error occurred.')`).
- Before, both returned to the list whatever the answer, so a refused close looked done.

## Why is a closed conversation not unread for the person who closed it?
- `pg_chat_close()` moves the closer's own read column (`initiator_last_read_id` or `target_last_read_id`, by side) onto the closing message, like a sender's on their own message. The other side still sees it as new. Before, the closer got an unread badge for their own close.

## How does an image attachment open?
- `attachmentNode()` wraps the preview in `<a href target=_blank rel=noopener>`; the image `alt` names the link. Before, it was an `<img>` with a click handler only, unreachable from the keyboard.

## How does the AI Chat follow the panel language and theme?
- `pg_chat_render_backend_launcher()` passes `ai.translations` (snippet keys such as `chatPlaceholder`, `sendButtonLabel`, `historyTitle`, each through `lang()`); the JS sets them as the `chat-page-snippet` element's `translations` attribute (JSON), which the snippet merges over its English defaults.
- Colours: the element's `--search-snippet-*` custom properties are set from `--pg-*` tokens in `chat.php`, and `color-scheme` follows `html[data-bs-theme]`, so the snippet follows the panel theme rather than the OS scheme.

Test: `tests/chat_close_delete_ai_test.php`.
