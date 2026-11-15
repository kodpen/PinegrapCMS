---
name: poll-agent-questions-census-today
description: AQ1 census of the poll today: its three tables, the box, ws_poll_vote(), ws_poll_close(), who may change or close, a guest's vote, who asks as an agent, export, ws_poll.php
---

## What does a poll keep today?
- `ws_polls` (2026.4.4): `message_id` (the question is the message's body), `channel_id`, `multiple`, `anonymous`, `closes_at`, `closed_at`, `closed_by`, `result_message_id`, `created_by` (the person; an application's owner for an app poll), `created_at`. Nothing says who made it beyond the message's `sender_kind`/`sender_id` (`ws_message_app_id()`).
- `ws_poll_options`: `poll_id`, `sort`, `label` VARCHAR(255). Nothing else per option.
- `ws_poll_votes`: `poll_id`, `option_id`, `user_id`, `guest_id` (2026.4.5), `created_at`; key (poll, option, user, guest). A vote holds no words of its own.

## What does the box draw?
- `ws_polls_map()` (`includes/workspace/interact.php`) gives per message: `multiple`, `anonymous`, `closed`, `closes_at`, `closed_at`, `voters`, `voted`, `can_vote`, `can_close`, `can_edit`, `result_id`, and per option `id`, `label`, `count`, `mine`, `leading`, `voters` (none when anonymous).
- The question's edit shows as the message's "edited" (`ws_messages.edited_at`): "Changed" needs no new field.

## What do vote and close do?
- `ws_poll_vote()`: `ws_poll_vote_check()` (state by `ws_poll_vote_state()`, then `ws_can_post_channel()`), deletes the person's votes and inserts the new ones; an empty list takes the vote back; a single-choice poll keeps the first id. A vote past `closes_at` closes the poll.
- `ws_poll_close()`: asker or channel manager (`ws_can_close_poll()`); sets `closed_at`/`closed_by` first, then writes the result: a clear winner as a decision under the poll (in the application's name for an app poll), a tie or no vote as a system line; announces `workspace.poll.closed` in a public channel. `ws_polls_autoclose()` closes expired polls when the channel is read.

## Who may change, close or vote?
- Change: `ws_poll_edit_check()` takes only the person who asked, with `sender_kind = user`: an application cannot change its own poll today. `workspace.polls.update` is own-words-only, `'mcp' => false`.
- Close: the panel and `workspace.polls.close` (`'mcp' => false`); an application key only its own poll (`ws_api_poll_is_callers()`).
- Vote: people only (`ws_api_person_only()`, `'mcp' => false`).
- A guest votes in a person's poll (`ws_guest_vote()`, `includes/workspace/guests.php`); an application's poll is refused for a guest by `ws_guest_person_line()` and drawn read-only in the guest room.

## Who makes a poll as an agent today?
- `workspace.polls.create` (an MCP tool) with an application key or an OAuth connection: `ws_api_writer_app_id()` gives `$app_id`, and `ws_poll_create(..., $app_id)` sends the message as the application and rings the asked with `ws_question_ring()`.
- So Claude's routine (Claude's application key), an app agent (its key, `tools/ws_poll.php ask`) and an MCP/OAuth client make app polls. Pinegrap AI and a model assistant have no poll tool (`ws_ai_tools()` in `ai.php`): they cannot ask one. The panel, `/poll` (`commands.php`) and a team devices session make person polls (`$app_id` 0).

## What do the export and the tool do?
- Export: `ws_channel_export_poll_line()` writes `Poll: <question> — <label> <count> · …`, `Poll (closed): …` (`workspace-channel-export.md`).
- `tools/ws_poll.php`: `ask` (question and two to ten labels, recommended first) and `close`; no description, picture, link, "Other", take back or ask again.
- A discussion's purge (`threads.php`) deletes its polls' options and votes.
