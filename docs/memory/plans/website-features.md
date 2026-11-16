---
name: website-features
description: Start file of the nine website-side plans (owner 2026-11-16, agent channel messages 5651 and 5667): his words, how each plan is laid out, the plans in the proposed order, which wait for his answer
priority: 6
---

## What is asked?
- Owner (2026-11-16, agent channel message 5651): "eticaret bcağımız fena değil. website tarafımız güçlenmeli. ama bir cms için iyi olur denebilecek özellik araştırması yaparmısın. yüksek değer, geliştirme zorluğu önemli değil"
- Owner (2026-11-16, agent channel message 5667): "Çoklu site dışındakiler için planları hazırla detaylı planlar olsun. olabildiğince az sayfayı dokunmaya çalışalım ama sayfa ve veri tabanı eklenmesi gerekiyorsa sorun değil. Çoklu site konusu da şu an için fazla büyük bir plan ama ileride düşünülebilir."
- Owner (2026-11-16, agent channel message 5694): "planları sen yazıyorsun ancak advisor olarak @mimarı kullanabilirsin". The R&D agent wrote the nine plans; its list is message 5657, its correction 5709.

## How is each plan laid out?
- `<plan>.md`: what is asked, what is there today, the decisions that bind it, what the owner and the architect still decide, what is left out, whose look.
- `<plan>-features.md`: the numbered features, each one sentence a test or a picture can show. Nothing is built that is not a number, and no number is skipped.
- `<plan>-steps.md`: the steps, each with the existing files it touches, its new files, its tests and pictures, and the numbers it builds.
- The rules every step keeps: `plans/website-features-rules.md`.

## Which plans are there, in the proposed order?
- `collection-rows.md`: rows inside a record (a recipe's steps), edited on the record's own form.
- `click-scroll-map.md`: which links and buttons of a page are clicked and how far visitors scroll.
- `content-review.md`: a person and a "review by" date for a page or record, and the list of what is due.
- `share-card.md`: a share image drawn from the title and the logo for a page that has none.
- `listen-block.md`: a "Listen" button that reads an article aloud.
- `prototype-comments.md`: a customer's pinned comments on a shared prototype.
- `visitor-push.md`: notifications a visitor without an account subscribes to.
- `release-bundle.md`: several pages prepared, previewed, published and taken back together. Waits for the owner's answer.
- `publish-approval.md`: a content editor's change waits for a designer's yes. Waits for the owner's answer.
- The order within the same priority is the architect's; the proposed one puts the content model first and the two that wait last.

## What is left out of the set?
- Several sites from one panel (message 5661). Owner (2026-11-16, message 5667): too big for now, may be thought about later.
- Site search insights: already built (`visitors/site-search-rollup.md`, the Site Search section of Visitors). Its small gaps were handed to the architect in the agent channel, not planned here.

## When is this file deleted?
- With the last of the nine plans, together with `website-features-rules.md`; until then each plan's close-out removes its own line here.
