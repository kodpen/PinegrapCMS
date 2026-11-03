---
name: decisions-made-schema-10
description: Decisions list, schema part 10 (2026-11-01 on): the search engines appended to the visitors referring-engine ENUM; the ActivityPub reactions table
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the migration shape.

## Which columns were approved?
- Today's search engines in `visitors.referring_search_engine` (owner, 2026-11-01, V4 of `plans/visitors-overview.md`): appended after the ENUM's last value: Yandex, DuckDuckGo, Ecosia, Brave Search, Qwant, Startpage, Baidu, Naver, Seznam; `install_modify_column()` after reading the current type, the next free label of `2026.11.0.php`, the two install dumps. Appending keeps one-byte storage, so the table is not rewritten. Reason: Yandex was counted as a referral and Google matched only `www.google`. Refused: VARCHAR (a rewrite), a lookup table (a join on every visit written).
- ActivityPub reactions (architect, 2026-11-03, #6391, AP2 of `plans/activitypub.md`): new table `ap_reactions` (`id`; `kind` ENUM('like','announce'); `actor_id`, `object_id`, `activity_id` VARCHAR(255); `reaction_key` CHAR(40) UNIQUE, sha1 of kind, actor and object; `created_at` INT UNSIGNED; keys on object and kind, activity, actor) in the next free 2026.11.0 sub-step after AP1's, `install_create_table()`, in `get_tables()`. One row per actor, kind and object: a repeated delivery is ignored, an Undo deletes by activity id or the recomputed key, a Delete of the actor or a block (AP5) by actor; counts are `COUNT(*)` per object and kind, read by AP4's count line; `object_id` is the object address AP3 makes. Refused: counter columns on `forms` records and `page` (no dedup, Undo unmatched, two wide tables altered) and dropping the counts. Fediverse only: site-member likes come in the later general plan of #6296, which decides its own table.
