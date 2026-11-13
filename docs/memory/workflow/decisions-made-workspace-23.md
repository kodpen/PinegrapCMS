---
name: decisions-made-workspace-23
description: Decisions list, workspace part 23 (2026-11-13 on): a warning before mail to a domain that does not exist (#9267); the SMS answer from a channel and stop words by SMS or WhatsApp (#9286)
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
