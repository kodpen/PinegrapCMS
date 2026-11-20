---
name: website-features
description: Start file of the nine website-side plans (owner 2026-11-16, agent channel messages 5651 and 5667): his words, how each plan is laid out, the plans and the order their steps are given in
priority: 6
---

## What is asked?
- Owner (2026-11-16, agent channel message 5651): "eticaret bcağımız fena değil. website tarafımız güçlenmeli. ama bir cms için iyi olur denebilecek özellik araştırması yaparmısın. yüksek değer, geliştirme zorluğu önemli değil"
- Owner (2026-11-16, agent channel message 5667): "Çoklu site dışındakiler için planları hazırla detaylı planlar olsun. olabildiğince az sayfayı dokunmaya çalışalım ama sayfa ve veri tabanı eklenmesi gerekiyorsa sorun değil. Çoklu site konusu da şu an için fazla büyük bir plan ama ileride düşünülebilir."
- Owner (2026-11-16, agent channel message 5694): "planları sen yazıyorsun ancak advisor olarak @mimarı kullanabilirsin". The R&D agent wrote the nine plans; its list is message 5657, its correction 5709.
- Owner (2026-11-16, agent channel message 5739): "bana soruları seçmeli olarak sor." His answers (agent channel polls 63 to 75) stand in the plan each belongs to; the one on tables in `website-features-rules.md`.

## How is each plan laid out?
- `<plan>.md`: what is asked, what is there today, the decisions that bind it, what the owner and the architect still decide, what is left out, whose look.
- `<plan>-features.md`: the numbered features, each one sentence a test or a picture can show. Nothing is built that is not a number, and no number is skipped.
- `<plan>-steps.md`: the steps, each with the existing files it touches, its new files, its tests and pictures, and the numbers it builds.
- The rules every step keeps: `plans/website-features-rules.md`. The existing files several plans touch: `plans/website-features-shared-files.md`.

## Which plans are there, in the proposed order?
- `click-scroll-map.md`: which links and buttons of a page are clicked and how far visitors scroll.
- `visitor-push.md`: notifications a visitor without an account subscribes to.
- `release-bundle.md`: several pages prepared, previewed, published and taken back together.
- `publish-approval.md`: a content editor's change waits for a designer's yes.
- Architect (2026-11-16, agent channel message 5847): every plan stays 6; a step opens only in a place with no begun plan's step free (`workflow/plan-order-begun-first.md`). Given: to `@elcin` RW1, then LS1 once RW1 has landed; to `@adnan` PA1 and, once PU8 (#9877) and LP1 (#9843) have landed, CM1. After CM1 OG1 and PN1 side by side, then CH1, VP1, last RB1, taken by the coordinator with a place.
- Two steps that touch the same existing file are never open together, another plan's open PR counted: `plans/website-features-shared-files.md`.

## What is left out of the set?
- Several sites from one panel (message 5661). Owner (2026-11-16, message 5667): too big for now, may be thought about later.
- Site search insights: already built (`visitors/site-search-rollup.md`, the Site Search section of Visitors). Its small gaps were handed to the architect in the agent channel, not planned here.

## When is this file deleted?
- With the last of the nine plans, together with `website-features-rules.md` and `website-features-shared-files.md`; until then each plan's close-out removes its own line here.
