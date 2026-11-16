---
name: decisions-made-schema-22
description: Decisions list, schema part 22 (2026-11-15 on): ws_profiles.quiet_hours and the link preview table (poll 54); the columns of ws_agent_feedback as confirmed
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. Entries name the issue, the date and the migration shape.

## Which schema do quiet hours and the link preview card get? (owner, 2026-11-15, agent channel poll 54)
- Each option's text named its schema and he chose both: quiet hours "Veritabanına tek sütun eklenir", the link preview card "Veritabanına küçük bir tablo eklenir". Translate on a message changes no schema.
- Quiet hours: `ws_profiles.quiet_hours` TEXT NULL, a JSON as `mail_away` (`start`, `end`, `off_days`), added by QH1 (`workspace/quiet-hours.md`). No backfill: an empty value is no quiet range.
- Link preview: one small table, one row per message whose first web address was read (the message, the address, title, description, site name, a state, when), added by LP1 (`plans/link-preview.md`). LP1 states its exact columns and indexes in its draft PR's body and the architect confirms them there; they are recorded here when the step lands.
- The architect's, not the owner's words: the column's JSON shape and the table keyed by message.

## Which columns does the table of marks have? (owner's yes in agent channel poll 49; columns confirmed by the architect, 2026-11-15)
- The table is his yes in poll 49 (`decisions-made-schema-21.md`): one row per marked reply. AF5a (#9814) stated its columns (agent channel message 5428) and the architect confirmed them in the answer.
- `ws_agent_feedback`: `message_id` INT UNSIGNED primary key (the marked reply), `request_id`, `channel_id`, `agent` VARCHAR(32) (the `ws_ai_requests.agent` value), `verdict` ENUM('right','wrong'), `reason` VARCHAR(300), `marked_by`, `marked_at`; a key on (`agent`, `marked_at`), one on `channel_id` if a reader needs it.
- The last mark stands (the row is replaced); undoing a mark deletes the row.
