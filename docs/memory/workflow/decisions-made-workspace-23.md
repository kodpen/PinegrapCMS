---
name: decisions-made-workspace-23
description: Decisions list, workspace part 23 (2026-11-13 on): missing-domain mail warning (#9267); SMS answer and stop words (#9286); agents among who has seen a message (#9314); what a tag wakes (#9341)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries.

## Is an e-mail to a domain that does not exist sent without a word? (owner, 2026-11-13, #9267)
- No: before a Workspace e-mail leaves, an address whose domain does not exist gets a warning.
- Owner (2026-11-13), on a mail the queue showed as sent: "eposta iletildi görünyor ancak eposta kutusuna düşmemiş"; asked "Olmayan alan adına gönderimde uyarı veren küçük bir plan yazayım mı?": "Evet, yaz".
- The architect's, not the owner's words: a warning and not a refusal, the person may send anyway; a lookup that fails warns of nothing; a near domain is named when the sending mailbox's own is within two letters; the queue's word "Sent" stays. Behaviour: `workspace/mail-domain-warning.md` (MW1, #9303).

## Who can be answered by SMS from a channel? (owner, 2026-11-13, #9286)
- Only the conversation's number, within 7 days of its last SMS, as a transactional kind of its own (`reply`); no consent is asked, since it answers what the customer wrote.
- Owner (2026-11-13), asked "Kanaldan SMS ile yanıt kime gidebilsin?": "Yazana, 7 gün içinde (Önerilen)". Not chosen: only to a number with SMS marketing consent; no SMS answer at all.
- Step WA12 of `plans/whatsapp-channel` asked; where the conversation is kept: `decisions-made-schema-18.md`.

## What does a stop word received by SMS or WhatsApp do? (owner, 2026-11-13, #9286)
- STOP, IPTAL, RET, DUR and the others write an SMS, and so WhatsApp, marketing refusal to the consent log and İYS.
- Owner (2026-11-13), asked "SMS veya WhatsApp'tan gelen dur sözcükleri (STOP, IPTAL, RET, DUR…) ne yapsın?": "Ret kaydı yazsın (Önerilen)". Not chosen: the word only shown in the conversation.

## Are agents listed among who has seen a message? (owner, 2026-11-13, #9314)
- Yes, under the message and in the "Who has seen it" drawer, in every channel. Owner (2026-11-13): "hem mesajların altında hemde kimler gördü alanında gören ajanların bilgileri yer almıyor. bu hem burası hemde normal kanallarda önemli".
- Why: a message naming three agents read "Everyone in the channel has seen it" and said nothing of them, so he could not tell whether an order had reached them.
- How it is counted is the architect's (technical, no table or column): `plans/message-seen-agents.md`.

## Whose tag wakes an agent, and where? (owner, 2026-11-13, #9341)
- A guest's message, a mail's text and an answer of Claude, Pinegrap AI or an MCP assistant never wake an agent. Owner (2026-11-13): "misafir mesajı, e-posta ve yapay zekâ yanıtı ajan uyandırmaz kalmalı bu kural geçerli."
- The channel's own agents (the registry agents added to it, application and model agents alike) are not under that rule: one naming another in its answer wakes it, with the brakes. Owner: "yapay zekâ yanıtı yanlış anlaşılmasın kanaldaki ajanlar bundan muaf bu arada".
- In an agent channel (its agent switch on) a tag written by the owner or a manager of that channel wakes the tagged agent wherever it stands in a channel item (poll, task, table cell, note), but not in a canvas. Owner: "ancak ajan kanallarında sahip veya atanmış yönetici nerede geçirirse geçirsin uyanabilir yapabiliriz.(tuval hariç)". The two readings in brackets and of "manager" he confirmed: "okumaların doğru."
- A tag in a message's own body keeps its rule for every team member in every channel; the send paths that skipped the ask call are a fix. Why: he tagged an agent in a message with a picture and got no answer. Plan: `channel-item-tags-wake` (TW1, TW2).
