---
name: poll-take-back
description: Take back and Ask again on a poll (poll_again.php): the asker (a person or the asking application), withdrawn_at, again_of, panel actions, API withdraw and again routes
---

## Who may change, take back or ask again a poll?
- Its asker (`ws_poll_is_asker()`): the person who asked, with the question their own message, or the application whose message the question is. An application's owner is not the asker of its poll.
- Over the API `ws_api_poll_asker_app()`: the asking application with its key or connection (`ws_api_poll_is_callers()`, as the close), otherwise the person's own words (`ws_api_own_words_only()`). `workspace.polls.update`, `.withdraw` and `.again` stay off MCP.
- An agent changes its own poll's question as an edit of its own message (`ws_message_edit(..., $as_app)`); the tags the edit adds ask as the application's.

## What does taking back do?
- `ws_poll_withdraw()`: an open poll only (not closed, not taken back), not in an earlier version. Sets `ws_polls.withdrawn_at` and `withdrawn_by` (the person, an application's owner).
- The poll stays in the channel; the box draws it greyed (`poll-box.md`). `ws_poll_vote_state()` refuses a vote ("The poll was taken back.", field `withdrawn_at`) before it looks at the closing time, so a vote past `closes_at` does not close it. `ws_guest_vote()` refuses too.
- `ws_poll_close()` returns without a result and `ws_polls_autoclose()` skips it: a taken-back poll never writes a decision or a tie line. Votes already cast stay stored and are never counted into a result.
- The change (`ws_poll_edit_check()`) refuses it.

## What does asking again do?
- `ws_poll_ask_again()`: a new poll in the same channel by the same asker, made with `ws_poll_create()` first, then `again_of` = the old poll's id. The old one is taken back when still open; an answered or closed one stays (architect, message 3460).
- What is sent replaces the old poll's question, options, multiple, anonymous (`ws_poll_again_data()`); the rest is carried over, the options with their parts. `closes_at` is not carried. The new poll is held to the rules of a new one (`ws_poll_input()`, `ws_poll_parts_store_check()`).

## What do the box and the API get?
- `ws_polls_map()`: `can_edit`, `can_withdraw` and `can_ask_again` all rest on `ws_poll_person_asked()` (the person who asked, with the question their own message): an application's poll gives its owner all three false, as the change refuses them; the application changes it through its key.
- Before #9819 (issue #9811) `can_edit` asked only `created_by`, so an application's owner saw Edit and `can_edit: true`, then got 403 "Only the person who asked can change the poll."
- Also `withdrawn`, `withdrawn_at`, `again_of` / `again_of_message`, `asked_again` / `asked_again_message` (the newest poll asking it again, `ws_poll_again_links()`). `can_vote`, `can_edit`, `can_close` are false on a taken-back poll.
- `WorkspacePoll`: the same, ids as `again_of`, `again_of_message_id`, `asked_again`, `asked_again_message_id` (null for none), `withdrawn_at` as a time.
- Panel actions `ws_poll_withdraw` and `ws_poll_again` (`poll_id`, and for an ask-again `question`, `options`, `multiple`, `anonymous`); the box draws the buttons (`poll-box.md`).
- API: `POST /workspace/polls/{id}/withdraw` (`workspace.polls.withdraw`) and `POST /workspace/polls/{id}/again` (`workspace.polls.again`, 201 with the new poll's message), both with a dry run.

## How does a poll made before read?
- `withdrawn_at` and `again_of` are 0 (step 11.0.213, `poll-option-parts.md`): not taken back, not asked again. Before the upgrade the take-back and ask-again refuse ("The database has to be updated first.", 503). Tests: `tests/workspace_poll_again_test.php`.
