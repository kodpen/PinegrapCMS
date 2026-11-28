---
name: decisions-made-schema-57
description: Decisions made, schema part 57 (2026-11-28 on): a person's own status (available, busy, out, sleeping) that agents read, kept in the column ws_profiles.status
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-schema-56.md`.

## What does a person's status get? (owner, 2026-11-28, agent channel message 15451)
- His words: "kullanıcı statüsü durumu özelliğe getirelim müsait meşgul dışarıda vesaire vesaire gibi seçenekler olsun kanallardaki ajanlar da bu durumları görebilsinler bunu görerek duruma göre hareket edilir".
- His examples: he goes out, follows only the channel and may not see a question asked in a desktop session; an agent that names a person reads the status, calls accordingly and knows how long to wait; with "uyuyor" written, an agent can say why no answer came.
- On the schema: "Sema değişikliği kesin olacaktir user tablosuna sütun ile çözülürse o şekilde ilerleriz tablo gerekirse sor".
- One column is enough and no table is needed, so nothing was asked by a poll: `ws_profiles.status` TEXT NULL, a JSON as `quiet_hours` and `mail_away` beside it (`kind`, `note`, `until`, `at`), added by a step of the open version with the idempotent helpers. NULL is no status; no backfill.
- The architect put the column on the person's Workspace row (`ws_profiles`), not on `user`: quiet hours and "I am away" are kept there and every people query joins it already. Told to him in the answer (message 15456), with the offer to move it to `user` on his word.
- His yes covers one column for the status and nothing else: no table, no history of statuses.
- Running sites: nobody has a status until they set one; the online dot, quiet hours, "I am away" and leave days are unchanged.
- The architect's, not the owner's words: the kinds, the note's 80 characters, the end time, that a status holds no notification, where it is shown and what an agent's brief says (`plans/person-status.md`).
