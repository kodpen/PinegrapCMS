---
name: decisions-made-schema-23
description: Decisions list, schema part 23 (2026-11-16 on): the ws_highlights table for a reader's highlights; the columns of PU4 on auto_dialogs as confirmed; the click map's page_click_daily table
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. Entries name the issue, the date and the migration shape.

## Which table do a reader's highlights get? (owner, 2026-11-16, agent channel message 5547)
- "bunun için yeni tablo onaylıyorum": one new table, `ws_highlights`, added by HL1 (`plans/message-highlights.md`) as a migration step of the open version. No backfill.
- The columns are the architect's, not his words: `id` INT UNSIGNED AUTO_INCREMENT, primary key; `message_id`, `channel_id`, `user_id` INT UNSIGNED NOT NULL DEFAULT 0; `scope` VARCHAR(10) NOT NULL DEFAULT 'me' (`me`, `all`; a reader treats an unknown value as `me`); `quote` VARCHAR(1000) NOT NULL DEFAULT ''; `occurrence` SMALLINT UNSIGNED NOT NULL DEFAULT 0; `note` VARCHAR(255) NOT NULL DEFAULT ''; `created_at` of `ws_messages.created_at`'s type.
- Keys: `idx_message (message_id)`, `idx_user (user_id, id)`, `idx_channel (channel_id)`. No unique key: the quote is too long for one, so the same place twice is caught in code.
- Rows leave with their message, thread, channel or person ("silinen sohbetlerden ve kanallardan vurgulamalar temizlensin db yükü olmasın").

## Which columns does PU4 add to `auto_dialogs`? (architect, 2026-11-16, confirmed in PU4's draft, #9851)
- Under the owner's yes to new columns on `auto_dialogs` (`decisions-made-schema-21.md`, message 5139): `open_on` VARCHAR(10) NOT NULL DEFAULT 'delay'; `open_scroll` TINYINT UNSIGNED NOT NULL DEFAULT 50; `audience` VARCHAR(10) NOT NULL DEFAULT 'all'; `place` VARCHAR(10) NOT NULL DEFAULT 'centre'.
- Choices are VARCHAR checked against a list in code, never ENUM; a reader treats an unknown value as the default.

## Which table does the click map get? (architect, 2026-11-16, agent channel message 6041)
- Under the owner's yes to one table for the click and scroll map (`decisions-made-product-59.md`): `page_click_daily`, added by CM1 (#9902) as step 11.0.224 (`upgrade_2026_11_0_page_click_daily()`). No backfill.
- `id` CHAR(40) ascii_bin, primary key: the sha1 of day, page, device, kind, depth, area, role, address and label as stored (after the fold and the cut).
- `day` DATE; `page_id` INT UNSIGNED; `device` ENUM('desktop','mobile','tablet'); `kind` ENUM('view','depth','element','other'); `depth` TINYINT UNSIGNED; `area` VARCHAR(8) and `role` VARCHAR(6) ascii_bin; `url` VARCHAR(255) and `label` VARCHAR(60) utf8mb4_bin; `hits` INT UNSIGNED; `elements` SMALLINT UNSIGNED (the view row only).
- The two closed sets are ENUM here: one byte a row on a counter table, and both are parts of the key.
- Keys: `idx_kind_day_page (kind, day, page_id)` for the period's page list and the sweep, `idx_page_day (page_id, day)` for one page; no third. Binary collations, so that grouping an element by its four parts is the key's identity.
- Refused: a column per tenth on the view row; a hook or an extra sweep for a deleted page (its rows run out with the 400 days). The bound of 200 counts a page and day's element rows over the three devices.
- How it is written and read: `visitors/page-clicks.md`.
