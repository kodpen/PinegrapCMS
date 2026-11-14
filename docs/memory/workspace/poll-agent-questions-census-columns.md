---
name: poll-agent-questions-census-columns
description: AQ1 census: the columns the poll's new parts need (description, picture, links, recommended, "Other", take back, ask again), older polls, API, export, guests
---

## Which columns does AQ2 need? (proposed, the owner's to decide)
- `ws_poll_options.description` VARCHAR(500) NOT NULL DEFAULT '': the line under an option.
- `ws_poll_options.file_id` INT UNSIGNED NOT NULL DEFAULT 0: the picture, a `files` row already posted in the channel (a drawing is a file too). `get_file.php` lets a reader see a workspace file only through a message's `file_id` (idx_file), so a picture must be a channel file, or that check learns `ws_poll_options`.
- `ws_poll_options.links` TEXT NULL: JSON list of `{url, label}`, a few at most, http(s) only.
- `ws_poll_options.recommended` TINYINT UNSIGNED NOT NULL DEFAULT 0: at most one per poll ("Recommended" pill).
- `ws_poll_options.other` TINYINT UNSIGNED NOT NULL DEFAULT 0: the "Other" row, an option of its own so it counts and is chosen like any other (the drawing shows "Başka 0 oy").
- `ws_poll_votes.other_text` VARCHAR(255) NOT NULL DEFAULT '': the voter's own words on the "Other" row.
- `ws_polls.withdrawn_at` INT UNSIGNED NOT NULL DEFAULT 0 (and `withdrawn_by` the same shape): taken back. Not `closed_at`: a closed poll writes a result and draws its counts, a taken-back one draws greyed, takes no vote and writes nothing.
- `ws_polls.asks_again` INT UNSIGNED NOT NULL DEFAULT 0 with a key: on the new poll, the id of the one it asks again; the old box finds its newer one by it ("Asked again" link).
- The "Changed" mark needs none: the message's `edited_at`.

## Which does AQ4 need?
- `ws_polls.agent_question`: decided (`workflow/decisions-made-schema-19.md`).
- Who is asked: none proposed. The mentioned people of the question, else the channel's owners, read when drawn and checked as `ws_question_ring()` picks them (`ws_question_ring_pick()`). The alternative, kept for the owner: `ws_polls.asked_user_ids` VARCHAR(255) NOT NULL DEFAULT '' fixed at asking.
- Who answered, and when: none: the votes (`user_id`, `created_at`), `closed_by`, `closed_at`; `result_message_id` stays 0.
- The request row and its brief: none (`poll-agent-questions-census-wake.md`).

## Which does AQ3 or AQ5 need?
- None. AQ3 draws the AQ2 fields; AQ5's ring, Today line, export and `tools/ws_poll.php` read them.

## How does a poll made before read?
- Every new column defaults to empty, 0 or NULL, so an older poll is a person's poll with plain options, no "Other", not taken back, not asked again: drawn, counted and closed as today. No backfill. An app poll made before stays a poll (`agent_question` 0).

## What does the API return?
- `WorkspacePoll` (`ws_api_poll_schema()`) gains `agent_question`, `withdrawn`/`withdrawn_at`, `asks_again` and `asked_again_by` (poll ids, 0 none), `asked` (who may answer, for an agent question); each option `description`, `picture` (file id and its address, null none), `links`, `recommended`, `other`, and on the voters of "Other" their `text` unless anonymous.
- `workspace.polls.create` and `.update` take the option fields as objects beside today's plain strings, which keep working.

## What does the export write?
- A taken-back poll `Poll (taken back): …`; an agent question `Agent question: <question> — <answer> (<who>)`, no counts; "Other" with its words. Wording is AQ5's.

## Where is a guest refused?
- A guest never answers an agent question: today's `ws_guest_person_line()` refusal holds, and AQ4 adds `agent_question` to `ws_guest_vote()`. A guest still votes in a person's poll, "Other" included.
