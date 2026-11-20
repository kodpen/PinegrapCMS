---
name: workspace-app-answers
description: An application's answer to its / command shown only to the writer: ws_app_answers (11.0.252), answer_token, POST /workspace/commands/answer, ws_sync answers (PR #10668)
---

## What does it do?
- An application answers the person who typed one of its / commands, and nobody else, with `POST /workspace/commands/answer` (`token`, `text`). The answer is a box under that channel's conversation, with the application's name, the command and "Only you can see this", in every window and on the phone of that person.
- Nothing is posted: no inbox line, no push, no bell. The box's close (×) hides it in that window only; reopening the channel in another window shows it again while it is kept.
- Before PR #10668 an application could only answer everyone (`POST /workspace/channels/{id}/messages`), and the "Sent to the application" box was lost on reload or in a second window (census of PR #10636: nothing kept an application's text for one person; `ws_call_signals` and its sweep were the model).

## How is the token made?
- `ws_app_command_send()` puts `answer_token` into `workspace.app.command` (`ws_app_answer_token_for()`), only once `ws_app_answers` exists; before the upgrade the payload has no such key.
- Form: `user.channel.app.time.<command as hex>.<signature>`; the signature is the first 32 hex characters of HMAC-SHA256 with `ENCRYPTION_KEY` over writer, channel, application, command and time. Nothing is stored when it is made.
- `ws_app_answer_token_read()` refuses a bad signature or a time more than a minute ahead (`token_invalid`) and one older than 900 seconds (`token_expired`, `WS_APP_ANSWER_TOKEN_LIFE`).

## Which checks does the route make?
- `ws_app_answer_check()`, shared by the dry run and the write: table there (503), token read (422 on `token`), the calling application is the token's (403 `wrong_app`), the command is still registered by it (403 `command_gone`), fewer than three answers with that token (409 `limit_reached`, `WS_APP_ANSWER_MAX`), the text cleaned as a message (`ws_tokens_normalise()`, trimmed, at most `WS_MESSAGE_MAX`, 422 on `text`), the writer still reads the channel (404 `writer_gone`).
- Scope `workspace:commands`, `'mcp' => false`; a team devices session is refused with 403 (`ws_api_command_app_id()`).

## Where is it kept and for how long?
- `ws_app_answers` (id, user_id, channel_id, app_id, command, token CHAR(32) = the signature, body TEXT, created_at); keys (user_id, channel_id, id), created_at, token. Architect confirmed the shape (agent channel message 10332), owner's yes in poll 169.
- Swept after 24 hours (`WS_APP_ANSWER_KEEP`) by `ws_app_answers_sweep()` in `workspace_recurring_job.php`.
- Deleted with their discussion (`ws_threads_purge()`), their application (Delete on the API applications screen, `api_settings.php`) and the account (`pg_user_delete()` in `includes/fn/user_write.php`).

## How does the screen get it?
- `ws_sync` with `answer_after` returns `answers` for the open channel only, after the reader has passed `ws_can_read_channel()`: one read on (user_id, channel_id, id), the newest ten of the last 24 hours, drawn with `ws_render_body()` for the reader.
- `appAnswers()` in `assets/js/workspace.js` draws them; `answerAfter` starts at 0 when a channel or discussion opens, so a reload shows the day's answers again. While another tab than Messages is open they wait for the next look.

## What could not be verified?
- Not run on a database, in the composer or on a phone in PR #10668: the migration's two runs, the route's writes and the box on screen.
