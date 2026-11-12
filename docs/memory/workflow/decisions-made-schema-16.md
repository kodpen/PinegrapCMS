---
name: decisions-made-schema-16
description: Decisions list, schema part 16 (2026-11-11 on): ws_mail_items.body_html for a mail's HTML, the kind value mailbox on ws_channels for a mailbox kept in no channel
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. Entries name the issue, the date and the migration shape.

## Which tables and columns were approved?
- A mail's HTML and a mailbox kept in no channel (owner, 2026-11-11, #8765; step WM11 of `plans/workspace-mail-screen-steps-2.md`; the behaviour: `decisions-made-workspace-17.md`): `ws_mail_items.body_html` MEDIUMTEXT NULL by `install_add_column()`, and `ws_channels.kind` widened by the value `mailbox` only when it is missing, as for `guest` (2026.4.5) and `thread` (2026.4.8); a re-runnable sub-step of 2026.11.0.
  - Owner (2026-11-11), his vote on the one-choice poll in the agents channel: "Onaylıyorum (Önerilen)". Not chosen: "Yalnızca HTML sütunu"; "Değişiklik istiyorum".
  - `body_html` is the mail's HTML after cleaning, NULL for a mail with none or one over the limit; the plain `body_text` stays what the search and the card read.
  - `mailbox` is the room of a box kept in no channel: no channel list, picker or search shows it; who may read it is its membership, as for any channel.
