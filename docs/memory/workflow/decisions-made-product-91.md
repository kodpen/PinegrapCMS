---
name: decisions-made-product-91
description: Decisions list, product part 91 (2026-11-20 on): a survey after a service is a service message, the periodic NPS commercial (poll 198); the export leaves out jury notes and names (poll 200)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-90.md`.

## Under which consent does a satisfaction survey go? (owner, 2026-11-20, agent channel poll 198, channel message 10840)
- "Hizmet sonrası anket hizmet iletisi, dönemsel NPS ticari".
- A survey sent after a booking, ticket, course, form, delivery or mail conversation goes without asking for marketing consent or İYS (the transactional kind) and holds no promotion; an administrator may turn a survey into a commercial one, a switch in its settings.
- The periodic NPS is always commercial: only to people with marketing consent whom İYS has not refused.
- Not chosen: every survey commercial (fewer people asked).
- Why asked: a commercial message needs marketing consent and İYS, a service message does not but may hold no promotion (`platform/sms-and-iys-rules.md`); the class of a new message kind was the owner's before (the stock notice).
- Binds: the survey message never carries the review invitation or other promotion; the invitation shows on the answer page only. "Don't ask me again", the 30 days between surveys and the bounce block hold for both kinds. The SMS and WhatsApp kind is `feedback`, its class the survey's.

## What of a jury's evaluation goes into a person's export? (owner, 2026-11-20, agent channel poll 200, channel message 10842)
- "Puanlar ve karar; notlar ve jüri adları verilmez".
- The personal data export holds the entry's state, decision, combined score and criterion scores. The reviewers' notes and names are not in the file; an administrator gives them by hand from the screen when that is due.
- Not chosen: the notes too, with reviewers shown as "Juror 1, Juror 2"; state and decision only.
- The erase covers everything, whatever the export shows: the entry and its reviews go with the person.
- Binds: the export's section of the application review desk reads no `scores` note and no reviewer; a test pins the columns it reads, as the passkeys section does.
