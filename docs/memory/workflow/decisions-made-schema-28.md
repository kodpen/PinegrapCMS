---
name: decisions-made-schema-28
description: Decisions list, schema part 28 (2026-11-19 on): schema the open version added and then gave up is removed where it was added; ws_profiles.mail_pinned for a person's Incoming e-mail menu pin
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before is schema part 27 (decisions-made-schema-27.md).

## Is schema the open version added and then gave up kept? (owner, 2026-11-19, in the architect's window, agent channel message 9493)
- No. "2026.11.0 ile veri tabanı ekleme dışında vaz geçilen veri tabanı tablo, sütun vs. olabilir bunlar bu versiyon içinde değiştiyse bir kere eklendi artık değişemez yada kaldırılamaz mantığında olmamıza gerek yok."
- His example: "tablo yapısı workspace içine yazılmıştır ama biz oradan vaz geçmiş ayrı tablo kararı vermişizdir diyelimki bu durumda workspace içine yazan kısımı kaldırıp tabloyu ayrı ekleyelim. eski sürümlerde değilse ekleme aynı sürüm içinde eklendiği yerden kaldırabiliriz."
- Why: "yakın zamanda tablosal değişiklikler oldu veya olacak bu konu atlanmasın db gereksiz şişmesin (eski sürümlerde bu hata sıkça yapıldı)".
- The architect's reading, not his words: it holds for what only the open version's steps add; what a released version brought stays a question to him. How it is done: `release/released-vs-open-version.md`. The 2026.11.0 census of it ran in PR #10391.

## Where is a person's Incoming e-mail menu pin kept? (owner, 2026-11-19, agent channel poll 154, message 9495)
- "Her kişi kendisi sabitler (Notlarım gibi)", the option that named the column: `ws_profiles.mail_pinned`, beside `notes_pinned`, added by one sub-step of the open version with `install_add_column()`. The other choice, one site-wide switch in config, needed none.
- Plan: `plans/incoming-mail-menu-card.md`, step IM1.

Related: docs/memory/workflow/decisions-made.md, docs/memory/release/released-vs-open-version.md
