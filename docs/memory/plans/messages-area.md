---
name: messages-area
description: Plan (owner 2026-11-12, #8961): one messages area in place of My Campaigns: campaigns, automatic sends as flows, templates, one sends list, channels; e-mail bodies as pages; profiles become flows
---

## What is asked?
- Owner (2026-11-12): "bu ayarladığımız whatsapp iletişim vs. bunları kampanyalar ekranı ile birleştirelim ancak önce o alanıda baştan yaratalım."; "hatta zaten eposta kampanya profilleri akışla inşa edilebiliyor o yüzden o yönelime odaklanırız".
- And: "eposta gövdesi için html sayfa tasarlanabilsin de istiyorum kampanyalarda, kampanya gönderim ve geçmişi ve eposta kuyrugu ile whatsapp sms kuyruğu tek ekranda olabilir."
- Record: `workflow/decisions-made-product-47.md`. Steps: `messages-area-steps.md`. Drawn in `plans/messages-area/messages-area.html` with its pictures.

## What did the owner decide?
- İa: the area as drawn. It takes the place of the My Campaigns row under Marketing (Turkish "İletişim") and has five tabs: Campaigns, Automatic sends, Templates, Sends, Channels.
- Sends is one list: a campaign's sending and history (a row that opens to its recipients) and every single message of the e-mail, SMS and WhatsApp queues with its source (order, form, flow, code), state and retry.
- A campaign's e-mail body is designed as a page: edit the design, start from a ready design, paste HTML, preview on a phone, send a test.
- Automatic sends are flows (`automation-flows-menu.md`), with ready recipes and the quick setup (`flows-quick-setup.md`).
- M2b: at the update every e-mail campaign profile is turned into a flow by itself and the profile screens go.

## What waits for the owner?
- The incoming e-mail moves out of the channels into this area, its boxes' settings into the settings (owner, #8961: "eposta iletişim altında da mantıklı aslında"). What happens to the e-mail cards in channels is asked; the step and the record in the decisions list follow the answer.
- A WhatsApp campaign to many stays for later (`whatsapp-channel.md`); the Campaigns tab shows its place only.

## What does every step keep?
- Nothing a running site sends is lost: e-mail and SMS campaigns, their jobs and queues keep their tables and rules (`platform/sms-campaigns.md`, `platform/sms-outbox-queue.md`); the area re-dresses and joins screens. An old address leads to its new place.
- No table or column without the owner's yes; a step that needs one stops and asks through the architect.
- Rights stay per screen as today; a tab shows only to who may open its screen.
- Lists on the table driver, the look from look A (`panel-design-language-sheet.md`); the names in the drawing are examples.
- A profile is turned into a flow only where flows run without the Workspace (step AF3 of `automation-flows-menu.md`). Across the update no send is lost or sent twice; a profile with a part no box covers keeps running and is listed, never dropped silently.
- The settings of a channel stay in the settings; the Channels tab shows each channel's state and leads there.
