---
name: agent-channel-note-tool
description: tools/ws_note.php new|read|update: a session keeps a plan or a long explanation as one note in a channel, changed in place; the base kept in PATH.ws_note, exits 0/1/2, a 409 writes nothing
---

## What is it for?
- A plan or a long explanation goes into a note shared in the channel and is changed in place, never posted again as new messages (plan `channel-documents`, KD4a). Its first line says up to which PR or message it is written.
- Settings: `PG_WS_SITE`, `PG_WS_KEY`, `PG_WS_SECRET`, and `PG_WS_CHANNEL` (or `--channel`) for `new` (`tools/ws_client.php`). Nothing secret is printed.

## What do the commands do?
- `new --file=PATH [--title=T] [--channel-edit]`: `POST /workspace/channels/{id}/notes`, the note and its card in one move; `--channel-edit` lets whoever may post in the channel change it. Prints `note=<id> revision=<n> message=<id>`.
- `read <id> [--file=PATH]`: `GET /workspace/notes/{id}`; the body on stdout (the `note=… revision=…` line on stderr), or into PATH, which is overwritten.
- `update [<id>] --file=PATH [--title=T] [--revision=N]`: `POST /workspace/notes/{id}` with the whole body and `revision`. Prints `note=<id> revision=<n>`.
- A file of `-` is stdin (`new`, `update`). Every write sends an Idempotency-Key (`ws-note-<24 hex>` or `--idempotency-key`) and is repeated once with it after no answer (0, 502, 504).

## Where does the base come from?
- `new` and `read --file` keep `{"note_id","revision"}` in `PATH.ws_note`; `update` takes the id and revision from there when not given, and keeps the new revision after a write. `--revision` wins; a kept base of another note is not used. `*.ws_note` is in `.gitignore`.
- With no revision known, `update` exits 2: it never writes over a note blind.

## What are the exits?
- 0 written or read; 1 the site refused, or no answer (stderr names the key to repeat with); 2 usage, a missing setting, an empty or too long file (60,000 characters, the title 200).
- A 409 `changed_meanwhile` prints `note N changed since revision R (<the site's words>); nothing was written: read it again, merge, update again`, exits 1 and leaves `PATH.ws_note` as it was.
- Pure parts tested in `tests/ws_note_tool_test.php`.
