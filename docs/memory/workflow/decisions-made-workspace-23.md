---
name: decisions-made-workspace-23
description: Decisions list, workspace part 23 (2026-11-13 on): a warning before a Workspace e-mail is sent to an address whose domain does not exist (#9267)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries.

## Is an e-mail to a domain that does not exist sent without a word? (owner, 2026-11-13, #9267)
- No: before a Workspace e-mail leaves, an address whose domain does not exist gets a warning.
- Owner (2026-11-13), on a mail the queue showed as sent: "eposta iletildi görünyor ancak eposta kutusuna düşmemiş"; asked "Olmayan alan adına gönderimde uyarı veren küçük bir plan yazayım mı?": "Evet, yaz".
- The architect's, not the owner's words: a warning and not a refusal, the person may send anyway; a lookup that fails warns of nothing; a near domain is named when the sending mailbox's own is within two letters; the queue's word "Sent" stays. Plan: `plans/mail-domain-warning.md` (MW1).
