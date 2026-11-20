---
name: channel-documents-features
description: Plan channel-documents, features N1 to N10: a note written in a channel from the composer or by an agent's route, the folded card, edits in place, versions, who may change it, the agent's name
---

## How is one written?
- (N1) The composer's attach menu gets "Write a note": a window with a title and the note's writing box (its heading, list, table and code tools). Send writes the note and its card in one move; the switch "People who may post here can edit" is off. A person who may not post in the channel is not offered it.
- (N2) `POST /workspace/channels/{id}/notes` (`workspace.channels.notes.create`, `workspace:write`, an MCP tool, dry run, Idempotency-Key): `title`, `body`, `channel_edit`. The card's message is written as `POST …/files` writes one: an application key as the application, a connection through its client, a device as the person. It answers the message id, the note id and the revision.
- (N3) A body sent over the route is Markdown: it goes through the import's rules (`ws_note_import_convert()`: `####` and deeper become `###`, a line underlined with `=` or `-` becomes a heading). At most `WS_NOTE_MAX` (60,000) characters; a longer one is refused with the limit, never cut.

## How is it changed?
- (N4) Over the API by `workspace.notes.update` with `base`, as today: a stale base answers 409 `changed_meanwhile` and nothing is written. Every save keeps a version by today's rule.
- (N5) A save of a note that has channel cards touches each card's message (`ws_message_touch()`): the open screen draws the card again and `changed_since` returns the message. No message, no wake, no inbox line.
- (N6) `ws_note_access()` gives `edit` through a channel's share row with `can_edit` to whoever may post in that channel (`ws_can_post_channel()`); without it `view`, as today. The note's owner sets it at the start or from the card's menu. A locked note is refused as today.
- (N7) A message's `note_card` over the API gains `revision`, `updated_at` and `can_edit`, so an agent reading the channel knows which note to change and from which base.

## What does the card show?
- (N8) The note drawn whole up to a fold of about 18rem, then "Show all" and "Show less" in place of the inner scroll; an opened card stays open while the screen is. The foot: the owner, "Changed by <name> · <when>", and "Versions" with their count.
- (N9) The card's menu: Read; Edit, a window over the channel with the note's writing box, whose stale save says who changed the note meanwhile, as the notes screen does; Versions, a drawer of the kept versions with who and when, Show, and Put back for who may edit; Open on the notes screen; for the owner the channel's edit switch.
- (N10) With the owner's yes to poll 180: the application that saved is kept (`ws_notes.updated_app_id`, `ws_note_versions.app_id`, 0 for a person), and the card and the versions say "Changed by @handle" for a registry agent, the application's name for any other.

## What do agents get?
- The instructions' line: a plan or a long explanation goes into a note in the channel, changed in place; its first line says up to which PR or message it is written. `tools/ws_note.php new|read|update` sends a file with the `PG_WS_*` settings and reads the base itself.
