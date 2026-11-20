---
name: decisions-made-schema-37
description: Decisions list, schema part 37 (2026-11-20 on): the application that saved a note, kept on the note and on its versions
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-schema-36.md`.

## Is the application that changed a note kept? (owner, 2026-11-20, agent channel poll 180, message 10580)
- "Evet, iki sütun eklensin".
- `ws_notes.updated_app_id` and `ws_note_versions.app_id`, both INT UNSIGNED NOT NULL DEFAULT 0; 0 means a person saved. Existing notes are not changed.
- Why: a note in a channel is changed by agents too, and every agent's key acts for a person, so the card and the versions named the same person for all of them.
- Binds: the columns serve the card's and the versions' "Changed by @handle" (plan channel-documents, step KD3); added by a migration step of the open version with the idempotent helpers.
