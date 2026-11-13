---
name: decisions-made-product-49
description: Decisions list, product part 49 (2026-11-13 on): a customer's WhatsApp message and SMS land in a channel as an e-mail does and are answered the same way (#9151)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries.

## Do a customer's WhatsApp message and SMS land in a channel, and are they answered there? (owner, 2026-11-13, #9151)
- Yes, as an e-mail does and by the same way of answering. Owner (2026-11-13): "whatsapp ve sms de kanallara eposta gibi düşüp aynı yolla ynıtlama içinde bir plana ekleme yapalım".
- It takes the place of "answering from the panel comes later" in W2a (`decisions-made-product-47.md`). W2a's bell notice and the line in the contact's history stay.
- The architect's, not the owner's words: the work is steps WA12 to WA18 of `plans/whatsapp-channel-steps-2.md`. One way for the person (the e-mail conversation's card, drawer, list, rights and notices); WhatsApp free text only inside Meta's 24 hours and approved templates after it; SMS only where the provider delivers incoming messages; assistants draft and never send; any table or column is asked of the owner first.

## Can a layer go under the photo in the image editor? (owner, 2026-11-08, #9198)
- Through Convert to a layer: the photo becomes a picture layer at the bottom of a canvas, its crop, colour, erased and adjusted areas and redaction in its pixels, the photo tools no longer on it; one undo brings the photo back. Details: `ui/image-editor-picture-to-layer.md`.
- Owner (2026-11-08), reporting "eklediğim görsel arkaplanın en altına gönderilemiyor. arkaplan bir görsel objesi gibi davranmıyor", chose "Arka planı katmana dönüştür" over keeping the photo in the layer order with its tools (a larger rework of the stage, the save, animation and SVG export).
