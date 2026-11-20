---
name: decisions-made-workspace-42
description: Decisions list, workspace part 42 (2026-11-20 on): an Activity tab in Channel info and documents written in a channel, both planned
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-workspace-41.md`.

## Does a channel get an activity panel and Markdown documents? (owner, 2026-11-20, agent channel message 10569, poll 179 in message 10572)
- "kanallarda markdown desteği hakkında ne düşünürsün? markdown ekleme düğmesiyle eklenen içerik markdown olarak kanala çıktı verse (normal mesajlarda markdown işe yaramaz) nasıl fikir? ajanlar kanallarda bir konuyu anlatırken markdown kullanabilir örneğin bir plan. ayrıca planda güncelleme olduğunda bu markdown dosyasını günceller."
- "birde sağda açılıp kapatılabilir etkinlik paneli istiyorum claude dakine benzer (Kanal bilgisi paneli tepesinde tab ile 2. sekme olarak gelebilir.) burada kanalla ilgili durumu doğrumu kaç pr birleşti gibi bilgileri tutar. ajanların bağlamları kaç token onu söyler. çalışılan proje adı yazar."
- Poll 179: "İkisi de plana girsin": the architect writes both plans, the Activity tab first, then the document; a schema need is asked apart.
- Why: the owner reads a channel's state out of the conversation today, and a plan told in messages is lost in them and cannot be kept current.
- Binds: ordinary messages are not changed by it; the Activity tab is the second tab of Channel info. The plans: `plans/channel-activity.md`, `plans/channel-documents.md`.
