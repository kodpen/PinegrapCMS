---
name: decisions-made-product-47
description: Decisions list, product part 47 (2026-11-12 on): WhatsApp as a channel, templates, offer codes (#8950); one messages area, campaign profiles turned into flows (#8961)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries.

## Does WhatsApp become a message channel, and how far at first? (owner, 2026-11-12, #8950)
- Yes, beside e-mail, for orders, forms and flows. Plan `plans/whatsapp-channel.md`.
- Owner (2026-11-12): "sms türkiye için zorlayıcı oldu artık ilerişim eposta + whatsapp oldu o yüzden whatsapp entegrasyonunu geliştirelim sipariş ve formlar için olanak açalım".
- Asked three questions by letters, the owner answered "W1a, W2a, W3a":
- W1a: a site connects with its own Meta app and a permanent token, the card showing the steps. Not chosen: Kodpen's verified app in between; both.
- W2a: a customer's reply shows as a bell notice and in the contact's history; answering from the panel comes later. Not chosen: answering from the panel now; replies not read.
- W3a: first the messages that go to one person (orders, forms, a flow's step); campaigns later with a consent decision of their own. Not chosen: campaigns in the first scope.

## Are WhatsApp templates designed in the panel, and can a message carry an offer code? (owner, 2026-11-12, #8950)
- Yes: a designer with a preview as on a phone, a header image, video or document, buttons and a link, sent to WhatsApp for approval; an offer code as the offer's shared code or as a key for each recipient.
- Owner (2026-11-12): "whatsapp sablon tasarımı için sanki whatsapp teleffon ekranında açıkmış gibi bir önizleme yaratsak iyi olur. görsel ve diğer uygun öğe ve bağlantı da ekleme olsun. teklif kodu gönderilebilsin (hem toplu hemde herkese ayrı anahtarlı sistem)"; shown the drawing: "Şa".
- Asked who gets a message with an offer code, the owner answered "Ka": the designer and the offer code are in the first scope; a marketing-type template goes only to a contact who gave marketing consent, and one by one (after an order, after a form, as a flow step); a campaign to many stays for later. Not chosen: offer codes and marketing templates wholly later; campaigns to many now.
- The architect's, not the owner's words: which stored consent counts as that marketing consent is not decided yet; step WA0 proposes it and it is asked of the owner.

## Is the campaigns area made anew as one messages area? (owner, 2026-11-12, #8961)
- Yes, as drawn: in place of My Campaigns, with the tabs Campaigns, Automatic sends (flows), Templates, Sends and Channels; one Sends list for campaigns and the e-mail, SMS and WhatsApp queues; a campaign's e-mail body designed as a page. Plan `plans/messages-area.md`.
- Owner (2026-11-12): "bu ayarladığımız whatsapp iletişim vs. bunları kampanyalar ekranı ile birleştirelim ancak önce o alanıda baştan yaratalım."
- And: "eposta gövdesi için html sayfa tasarlanabilsin de istiyorum kampanyalarda, kampanya gönderim ve geçmişi ve eposta kuyrugu ile whatsapp sms kuyruğu tek ekranda olabilir."; shown the drawing: "İa".

## What happens to the e-mail campaign profiles? (owner, 2026-11-12, #8961)
- "M2b": at the update each is turned into a flow by itself and the profile screens go. Not chosen: profiles kept with a "turn into a flow" button; both kept for good.
- Owner (2026-11-12): "hatta zaten eposta kampanya profilleri akışla inşa edilebiliyor o yüzden o yönelime odaklanırız".
- The architect's, not the owner's words: flows first run without the Workspace; no send is lost or sent twice across the update; a profile with a part no box covers keeps running and is listed.
