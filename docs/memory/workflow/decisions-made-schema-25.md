---
name: decisions-made-schema-25
description: Decisions list, schema part 25 (2026-11-16 on): the second study's tables and columns, the site_push_watch table, the file_translation table, a column for application command arguments
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The three `site_push` tables: `decisions-made-schema-24.md`.

## May the second feature study's tables and columns be added? (owner, 2026-11-16, agent channel polls 84 and 91)
- Poll 84 (message 6513), asked about the first draft's fourteen tables: "mimara soralım bunu yazılımdaki bir özelliği geliştirererek yapabiliyormuyuz değerlendirsin."
- The architect weighed each plan against what exists (messages 6553 and 6554): most tables fell away. Poll 91 (message 6584), asked with that view: "Evet, Mimar'ın görüşüyle; sütunları Mimar onaylasın".
- What stays, plan by plan, and the order of the schema steps: `plans/more-features.md` and `plans/more-features-shared-files.md`. Each column is confirmed by the architect in its step's draft PR.
- Reading for later: when a table is proposed, the architect first says whether something that exists can be grown, then asks him with that view.

## Which table do an order's and a stock notice's visitor notifications share? (owner, 2026-11-16, agent channel poll 94, message 6781)
- Owner: "Evet, eklenebilir" (one table for both notices, the columns the architect's). Not chosen: the two notices not built for now.
- Architect (messages 6785 and 6786): `site_push_watch`. `kind` TINYINT UNSIGNED (the message's kind: 2 stock, 3 order), `record_id` INT UNSIGNED (kind 2: `stock_notify.id`; kind 3: the id of the order's ship-to address), `subscription_id` INT UNSIGNED, `moments` TINYINT UNSIGNED (bits of the moments already sent; kind 3: 1 shipped), `created_at` INT UNSIGNED.
- All NOT NULL DEFAULT 0. Primary key (`kind`, `record_id`, `subscription_id`), key `idx_subscription (subscription_id)`. No `order_id` column and no column on `stock_notify`.
- A moment is sent once: the bit is set by an UPDATE that asks for it unset, and the notice goes only when one row changed; the last moment deletes the row. Rows go with their subscription, with their order or stock request, and after 180 days in the sender's tidy pass.
- VP6 adds the table and its helpers in `includes/fn/site_push.php`; VP5 uses it after VP6 landed and adds no column.

## Which table keeps a language's picture? (owner, 2026-11-16, agent channel poll 98, message 6808; columns the architect's)
- Owner's yes to a small table of its own: `decisions-made-product-64.md`. Added by #10028 (`core/picture-text-translation-store.md`).
- Architect (2026-11-16): `file_translation`. `file_id` INT UNSIGNED (the source picture), `language` VARCHAR(10) ascii, `picture_file_id` INT UNSIGNED (the language's picture, a `files` row; 0 until drawn), `state` TINYINT UNSIGNED (0 waiting, 1 drawn, 2 out of date), `fit` TINYINT UNSIGNED (1: a text drawn at the smallest size still did not fit), `doc_hash` CHAR(40) ascii (the source document it was drawn from), `created_at`, `drawn_at`, `drawn_by` INT UNSIGNED.
- All NOT NULL with a default. Primary key (`file_id`, `language`), keys `idx_picture (picture_file_id)` and `idx_state (state, language)`.
- Only `state` 1 is shown to a visitor. A save of the source and a changed translation of one of its texts set the row to 2. No column on `files`, none on the translation tables.

## May ws_app_commands get a column for a command's arguments? (owner, 2026-11-16, agent channel poll 97, message 6795)
- Owner: "Evet, eklenebilir; sütunu Mimar onaylasın". Existing rows and running sites are untouched: a command without a definition works as today.
- The column, confirmed on CC10's draft PR (`plans/channel-commands-steps-2.md`): `decisions-made-schema-26.md`.
