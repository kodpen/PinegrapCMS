---
name: messages-area-rules
description: What binds every change to the Communication area (İletişim): the owner decisions of #8961 and #9295, what a change keeps, the architect's shell and rights rulings
---

## What did the owner decide?
- Owner (2026-11-12, #8961): one messages area in place of My Campaigns, as drawn ("İa"): "bu ayarladığımız whatsapp iletişim vs. bunları kampanyalar ekranı ile birleştirelim ancak önce o alanıda baştan yaratalım." Record: `workflow/decisions-made-product-47.md`.
- Owner (2026-11-12, #8961): one Sends list and a campaign's e-mail body designed as a page: "eposta gövdesi için html sayfa tasarlanabilsin de istiyorum kampanyalarda, kampanya gönderim ve geçmişi ve eposta kuyrugu ile whatsapp sms kuyruğu tek ekranda olabilir."
- Owner (2026-11-12, #8961): automatic sends are flows (`workspace/automation-flows-decisions.md`), and at the update every e-mail campaign profile becomes a flow by itself, the profile screens going (M2b): "hatta zaten eposta kampanya profilleri akışla inşa edilebiliyor o yüzden o yönelime odaklanırız".
- Owner (2026-11-12, #8961): the incoming e-mail sits in this area, its boxes' settings in Site Settings ([[messages-area-mail-tab]], `workflow/decisions-made-workspace-21.md`).
- Owner (2026-11-13, #9295): made anew means redrawn, never carried as it is: "kampanyalar ekranları olduğu gibi geçmiş ve yeni ekranlarda o sayfalar kadar eski olarak üretilmiş"; "ben modernleştirme turu da istemiştim birlikteliğinde".

## What does every change to the area keep?
- A screen is built from the drawing (`messages-area-drawing/messages-area.html` with its light and dark pictures) in look A's recipes (`look-design-examples.md`); real screenshots go to the owner before the PR goes ready (`workflow/architect-asking.md`). The drawing's names are examples.
- Nothing a running site sends is lost: e-mail and SMS campaigns, their jobs and queues keep their tables and rules (`platform/sms-campaigns.md`, `platform/sms-outbox-queue.md`); the area re-dresses and joins screens, and an old address leads to its new place.
- No table or column without the owner's yes, asked through the architect.
- Rights stay per screen; a tab shows only to who may open its screen. Lists on the list table driver.
- A channel's settings stay in Site Settings; the Channels tab shows its state and leads there.
- A profile becomes a flow only where flows run without the Workspace (#9154); a profile with a part no box covers keeps running and is listed, never dropped silently (`core/campaign-profile-flow-gaps.md`).
- A WhatsApp campaign to many waits (`platform/whatsapp-overview.md`); the Campaigns tab shows its place only.

## What did the architect rule on the shell? (2026-11-13, #9081)
- Architect (2026-11-13): row 9 under Marketing, "Communication"; one file and gate per tab with a shared tab strip; `view_email_campaigns.php` stays the Campaigns tab; old list addresses become doors, no file deleted ([[messages-area-shell]]).
- Architect (2026-11-13): rights as before: queue rows for role <= 2, a role 3 user their own campaigns, Channels for role <= 2.
- Architect (2026-11-13): automatic campaigns leave Campaigns and show in Sends as single messages with their profile as source.
- Not asked: the columns `email_recipients.sent_at` and `mail_outbox.source`; a need comes back as a question for the owner.
