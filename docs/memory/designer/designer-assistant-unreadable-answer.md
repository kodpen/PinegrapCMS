---
name: designer-assistant-unreadable-answer
description: The designer Assistant panel (designer_ai.js) when an answer cannot be read: its own translated sentence once per click, never the browser's parse text; a failed Apply keeps the proposal
---

## What does the panel say when an answer cannot be read?
- The editor's post (`_pgApiPost()`, `r.json()`) rejects with the browser's own words: `Unexpected token '<'…` for an answer that is not JSON (a PHP message printed in front of it, a server's error page, an empty body), `Failed to fetch` when no answer comes. Before #8970's fix the panel showed those words in a toast.
- `post()` in `assets/js/designer_ai.js` turns every failure into an `Error` with `pgKind`: `server` (a JSON error's own `message`), `unreadable` (a parse failure, or JSON without `status: success` and without a `message`) or `unreached` (any other rejection).
- Words (from `pg_design_ai_editor_config()`, `includes/designer_ai.php`): `unreadable` "The server answered with an error, so this was not done. Try again.", `unreached` "The site did not answer, so this was not done. Check the connection and try again."
- The browser's text goes to the console (`console.warn`, "Assistant: <sub> answer could not be read"), for whoever looks for the cause.

## What does a failed Apply do?
- One toast for one click (`applyProblem()`): the site's sentence or `unreached` as they are; an unreadable answer, or a success whose `tree` is not a JSON object (`answerTree()`), says `apply_unreadable` "The change could not be applied because the server answered with an error. The proposal stays here, so you can try again."; an error the editor itself throws while putting the tree in says `apply_failed`.
- The proposal keeps its state and its Apply button (enabled again when the call ends), so the same click tries again.
- The panel's first read (`ai_state`) failing puts the same words in its problem line, not a toast; a proposal opened from a Workspace link that cannot read the state says so in one toast.

## What stays open?
- The server marks a proposal `applied` / `editor` before it answers (`pg_design_ai_apply_to_editor()`). When that answer then cannot be read, the editor never got the tree but the next read of the panel draws the proposal as applied (with Apply again). Not changed here.

## Tests?
- `tests/designer_ai_unreadable_answer_test.php` runs the whole panel in Node against a stand-in editor and page: a parse failure, `{}`, `null`, a bare string, a tree that is not JSON, a network error, a JSON error, the first read failing; and that every `t('…')` word of the script is configured under `lang()`.
