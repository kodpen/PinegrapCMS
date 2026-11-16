---
name: workspace-api-scheduled-message-changes
description: Workspace API scheduled messages: change one (POST …/update), schedule a reply (parent_id), send one now (POST …/send), and the checks shared with the panel
---

## Which routes are there?
- `workspace.scheduled_messages.update`, `POST /workspace/scheduled-messages/{id}/update` (`ws_api_scheduled_messages_update()`, api_threads.php). It changes only the fields sent: `text`, `send_at`, `parent_id` (0 = none). A failed message waits again once saved (#9786).
- `workspace.scheduled_messages.send`, `POST /workspace/scheduled-messages/{id}/send` (`ws_api_scheduled_messages_send()`). It is the panel's Send now. The answer is the message that was posted: the person's first message in that channel after the newest id read before posting.
- `parent_id` on `workspace.scheduled_messages.create` and on update. `ws_api_scheduled_message_parent()` refuses a bad parent with 422 on `parent_id` via `ws_message_parent_check()`, as `messages.create` does. The panel's save drops a parent from another channel silently.

## Why /update and not POST on the bare path?
- `workspace.scheduled_messages.delete` already takes POST on `/workspace/scheduled-messages/{id}` (`also_accepts` POST, because IIS answers DELETE itself). A change there would turn running clients' delete calls into changes. So the change gets its own `/update` path, as `/webhooks/{id}/update` and the task time entries do.

## Who may call them?
- Own words, like create: `ws_api_own_words_only()`, `'mcp' => false`. The message is posted later in the person's own name, so an application key or an OAuth connection is refused with 403.
- `ws_api_scheduled_message_mine_or_404()` finds the row for update, send and delete. It must be the person's own, still active or failed, and in a channel Claude's key may be asked in; otherwise 404.

## Which checks do they share with the panel?
- `ws_scheduled_message_save_check()` (scheduled_messages.php) holds every rule of `ws_scheduled_message_save()`: own row, not being posted now (`running_at`), a channel the person may post in, the words (no command except /ai and /claude), the moment (later than now, at most a year ahead) and the 50-message limit for a new one. The panel's save, the API's create and the API's update all call it. Create no longer keeps a second copy of these rules.
- `ws_scheduled_message_send_check()` checks that the message is waiting or failed and not being posted now (`running_at`), that its channel still exists, and runs `ws_message_send_check()` (rights, rate limit). The API runs it before the dry run; a refusal is 422 and leaves the row as it was.
- `ws_scheduled_message_send_now()` runs `ws_scheduled_execute($id, true)` and gives the failure text. The panel's `ws_scheduled_message_send` action and the API both use it. Its row in `workspace_flows_parity_table()` points there.
- Tests: tests/workspace_api_scheduled_message_changes_test.php.

## What does a change to a message being posted get?
- The refusal "It is being posted right now.", the same text Delete and Send now give: from the check (so the dry run and `/update` answer 422, the panel shows it), and from the save when its `UPDATE … AND running_at = 0` changes no row because the run claimed the row after the check. Before #9837 such a change was answered as saved and dropped, also for a row with a stale `running_at` (#9833).
