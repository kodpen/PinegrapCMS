---
name: poll-option-parts
description: A poll option's description, picture, links, Recommended mark and the "Other" row with the voter's own words (poll_parts.php, step 11.0.213); create, change and vote rules, API fields, older polls
---

## What can an option carry?
- Beside its label (`includes/workspace/poll_parts.php`, #9635): `description` (a line, up to 500), `file_id` (a picture), `links` (up to `WS_POLL_LINKS_MAX` (3) `{url, label}`, http or https; a missing label is the address), `recommended`, `other`.
- Columns of step 11.0.213 (`upgrade_2026_11_0_ws_poll_parts()`), which also adds `ws_polls.withdrawn_at`, `withdrawn_by` and `again_of` for taking back and asking again (`poll-take-back.md`). Shapes: `workflow/decisions-made-schema-20.md`.
- Read with `ws_poll_parts_ready()` first: before the upgrade a poll with any part is refused ("The database has to be updated first.", 503 over the API); a plain poll still works.

## Which rules hold the options together?
- `ws_poll_parts_list()`, shared by create and change: blank and repeated labels left out (folded), at most ten options, at least two that are not "Other", one "Other" row, one recommended.
- The "Other" row is an option of its own (label "Other" when none is given), so it counts and closes like any other.
- A picture must be an image posted in the poll's channel (`ws_poll_parts_store_check()`): a reader sees a workspace file only through a message carrying it (`get_file.php`). A drawing is a file too.

## How does a change keep what it is not sent?
- `ws_poll_edit_check()` merges each kept option's stored parts (`ws_poll_parts_stored()`) under what is sent, so the panel form, which knows only labels, never wipes a description or a picture. `workspace.polls.update` does the same.
- An option with votes keeps its label and whether it is "Other"; its description, picture, links and mark may change.

## How is "Other" voted?
- Choosing the "Other" row needs the voter's words (`ws_poll_other_check()`, field `other_text`, up to 255): `ws_poll_vote(..., $other_text)`, the panel action's `other_text`, `ws_guest_vote()` for a guest, `POST /workspace/polls/{id}/vote` with `other_text`.
- The words are kept on that vote row (`ws_poll_votes.other_text`) and shown with the voter's name; an anonymous poll shows none.

## What do the box and the API get?
- `ws_polls_map()` gives each option `description`, `picture` (`file_id`, `name`, `url`, `image`, or null), `links`, `recommended`, `other`, `my_text` (the reader's own words), and each voter `text`.
- `WorkspacePoll` options carry the same (`picture.url` absolute), voters `text`. `workspace.polls.create` takes each option as a text or an object (`label`, `description`, `file_id`, `links`, `recommended`, `other`).
- The poll box draws them and the New poll form sends them (`poll-box.md`). Beside a question's own picture, an option's picture is a thumbnail in its row (`poll-question-picture-box.md`).

## How does a poll made before read?
- Every part is empty or 0 on its rows (no backfill), so it draws, counts and closes as before. Tests: `tests/workspace_poll_parts_test.php`.
