---
name: channel-memory-mark
description: The decision mark on a message by a person or an agent (ws_messages.marked_by_kind): who marks, what is markable, naming the marker
---

## Who marked a message?
- `ws_messages.marked_by_kind` (`user` default, `app`): an application key or an OAuth connection marks as the application (`marked_by` = application id), a team devices session as the person (`ws_message_mark($viewer, $message, $kind, $app_id)`). An agent's mark never reads as a person's, so it is never an owner decision. Without the column an application's mark is refused (503 / "The database has to be updated first.").
- Any non-system message is markable: `sender_kind` `user` or `app` (`ws_message_mark_check()`, `can_mark` in the payload).
- Name the marker only with `ws_mark_by_name()` and collect people with `ws_mark_people_ids()`: a bare `marked_by` lookup names the person whose id the application has. `GET /workspace/decisions` gives `marked_by` (`user_id` null for an application) and `marked_by_kind`; the person filter counts only people's marks. A discussion's copy keeps the kind (`ws_thread_copy_sync()`).
