---
name: decisions-made-schema-8
description: Decisions list, schema part 8 (2026-11-01 evening on): the referring_search_engine ENUM of visitors appended with today's search engines for the Visitors overview
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue or plan and the date.

## Today's search engines in `visitors.referring_search_engine` (owner, 2026-11-01, `plans/visitors-overview.md` V4)
- The ENUM gains, appended after its last value: Yandex, DuckDuckGo, Ecosia, Brave Search, Qwant, Startpage, Baidu, Naver, Seznam. `install_modify_column()` after reading the current type so nothing already there is lost (as 2026.4.10 did for `summarize_by_*` and `device`), one sub-step of `includes/migrations/2026.11.0.php` with the next free label when pushed; the two install dumps follow; `get_tables()` is unchanged. Appending keeps the ENUM's one-byte storage, so the table of millions of visits is not rewritten. Reason: Yandex is the second search engine of Turkish visitors and was counted as a referral; the detection list dated from 2001 and matched Google only on `www.google`.
- Refused: the column as VARCHAR (a rewrite of the table), a lookup table (one more join on every visit written).
