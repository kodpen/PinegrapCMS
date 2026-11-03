---
name: decisions-made-schema-10
description: Decisions list, schema part 10 (2026-11-01 on): the search engines appended to the visitors referring-engine ENUM
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the migration shape.

## Which columns were approved?
- Today's search engines in `visitors.referring_search_engine` (owner, 2026-11-01, V4 of `plans/visitors-overview.md`): appended after the ENUM's last value: Yandex, DuckDuckGo, Ecosia, Brave Search, Qwant, Startpage, Baidu, Naver, Seznam; `install_modify_column()` after reading the current type, the next free label of `2026.11.0.php`, the two install dumps. Appending keeps one-byte storage, so the table is not rewritten. Reason: Yandex was counted as a referral and Google matched only `www.google`. Refused: VARCHAR (a rewrite), a lookup table (a join on every visit written).
