---
name: decisions-made-product-56
description: Decisions made, product part 56 (2026-11-15): a left cart recovered within 7 days of its last touch; pop-ups built by growing Auto Dialogs, the jQuery dialog gone; Design Canvas on a video in Files
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries.

## When does a left cart count as recovered? (owner, 2026-11-15, agent channel poll 50)
- His wish (channel message 5096): a place that shows how many carts a campaign e-mail, a WhatsApp message or an offer on a page brought back, the data kept, and the system finding what weakens the returns. Why, in his words: "kullanıcıların bu gibi özelliklere önem verdiğini gördüm".
- Offered 7, 14 or 30 days or "talk first", he voted "Evet, tablo ve 7 gün (Önerilen)": a new table for the record, and a cart is recovered when completed within 7 days of its last touch (the abandoned-order mail goes for up to a week too). Outcome: channel message 5133.
- The architect's readings, not his words: "left" stays today's 6 hours; a left cart completed with no touch is counted apart; the last touch takes the recovery. Plan: `plans/cart-recovery.md`.

## How are pop-ups built? (owner, 2026-11-15, agent channel poll 51 and message 5139)
- His wish (channel message 5096): a few pop-up components, since the designer has none that opens by itself.
- Poll 51: he voted "Evet, ikisi de (Önerilen)": a rollup table for the counts (shown, closed, taken up), and no pop-up opens before the visitor has answered the cookie notice. Outcome: channel message 5136.
- Then, under the poll: "açılır pencereleri Otomatik Diyaloglar ı geliştirerek yapabiliriz. gerekiyorsa ona yeni tablo sütunları eklenir. eski modal ayarlarına ek özellikler gelir (mevcut yapısı jquery modal olması lazım bundan tamamen kurtulalım ve arayüz iyileştirmesi de olsun)".
- So no second pop-up system: Auto Dialogs grow (when one opens, how often, who sees it, a pop-up designed in the designer as its content), with new columns on `auto_dialogs` as needed; the jQuery UI dialog on sites is replaced completely; the Auto Dialogs screens are redrawn in the panel's look.
- The architect's reading, by the standing rule for running sites: rows stored today keep working with their settings untouched.
- The plan is written from the R&D agent's shape on this base (asked in channel message 5141).

## Is a video edited from Files? (owner, 2026-11-15, agent channel message 5103)
- He expected it: "tasarım tuvalinde video düzenlenir olacaktı ... bugün dosya yöneticide video düzenle seçeneği yok." The video plan was complete (closed by #9234); only the door from Files was never a step.
- So Files offers Design Canvas on an MP4 or WebM; a plain video opens as a clip on a new canvas and is saved beside its source (#9775): `ui/image-editor-video-files.md`.
