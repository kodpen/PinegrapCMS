---
name: decisions-made-schema-20
description: Decisions list, schema part 20 (2026-11-14 on): ws_mail_items.send_at; the repeat columns and ws_personal_plan_skips; the two roadmap template tables; the widened poll's columns
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. Entries name the issue, the date and the migration shape.

## Where is a later mail's time kept? (owner, 2026-11-14, agent channel poll 3416)
- In a column of its own: `ws_mail_items.send_at` INT UNSIGNED NOT NULL DEFAULT 0 with INDEX `idx_later` (`send_state`, `send_at`), added by step IB6 (#9547, `workspace/mail-send-later.md`) with the idempotent helpers; no backfill.
- The owner chose "Evet, sütun eklensin (send_at)" over "Hayır, şema değişmesin; saat var olan alanda tutulsun" (the outcome: channel message 3418).
- Not chosen: the time in the out item's `sent_at` until the mail leaves. That column would then mean two things, and every reader of it (the conversation's order, the export, the API's field) could show a waiting mail as sent at a moment ahead.
- The steps after it (undo send, the sending calendar MA13) read the same column.

## Which columns and tables do repeating plans and roadmap templates get? (owner, 2026-11-14, agent channel poll 3429)
- Asked by the census PC10 (#9550, its files on the repeat and on the template). The owner voted "Evet, ikisi de eklensin". Not chosen: only one of the two, neither.
- PC11, on `ws_personal_plans`: `repeat_frequency` VARCHAR(8) NOT NULL DEFAULT '' (daily, weekly, monthly, yearly), `repeat_interval` SMALLINT UNSIGNED NOT NULL DEFAULT 1, `repeat_until` INT UNSIGNED NOT NULL DEFAULT 0, `repeat_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0, `remind_minutes` INT UNSIGNED NOT NULL DEFAULT 0, `repeat_of` INT UNSIGNED NOT NULL DEFAULT 0 with a key.
- The architect's, on the shape: `repeat_until` is the moment after the last time, exclusive as `ends_at` is and in the table's own unit, 0 for no end; not a DATE with a zero default as the census first wrote.
- PC11, table `ws_personal_plan_skips`: `plan_id` INT UNSIGNED, `day` DATE, primary key both: a time deleted or changed alone.
- PC12, table `ws_roadmap_templates`: `id`, `user_id` (the maker), `title` VARCHAR(255), `all_day` TINYINT(1), `start_minute` SMALLINT UNSIGNED, `length_minutes` INT UNSIGNED, `created_at`, `updated_at`; key `user_id`.
- PC12, table `ws_roadmap_template_steps`: `id`, `template_id`, `position` SMALLINT UNSIGNED, `title` VARCHAR(255), `day_offset` SMALLINT UNSIGNED, `has_day` TINYINT(1); key `template_id`.
- A template keeps no note, customer or people. PC13 (plans written to Google or Outlook) needs no column: its switch and calendar id are keys of the sealed `ws_calsync` entry.
- Running sites: no backfill; a plan made before repeats nothing.

## Which columns does the widened poll get? (owner, 2026-11-14, agent channel poll 3459)
- Asked by the census AQ1 (#9557) for the drawing he approved (`plans/poll-agent-questions.md`). The owner voted "Evet, sütunlar eklensin" over "Hayır, eklenmesin"; no new table.
- `ws_poll_options`: `description` VARCHAR(500), `file_id` INT UNSIGNED (a channel file), `links` TEXT NULL (JSON), `recommended` TINYINT, `other` TINYINT (the "Other" row).
- `ws_poll_votes`: `other_text` VARCHAR(255). `ws_polls`: `withdrawn_at`, `withdrawn_by`, `again_of` with a key (the poll this one asks again), beside `agent_question` (`decisions-made-schema-19.md`).
- All NOT NULL with defaults but `links`; added by the steps of AQ2 and AQ4 with the idempotent helpers, no backfill.
- No column for who is asked: the people the question mentions, else the channel's owners.
