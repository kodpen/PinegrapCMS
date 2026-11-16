---
name: more-features
description: Start file of the eight plans the owner chose from the second feature study (owner 2026-11-16, agent channel message 6424 and poll 82): his words, the architect's conditions, the plans
priority: 6
---

## What is asked?
- Owner (2026-11-16, agent channel message 6424): "yeni özellikler için araştırma yap". The study's list is message 6433; sixty candidates were checked against memory, the decisions list and the code, and most were already built or decided.
- Owner (2026-11-16, agent channel poll 82, message 6434), asked which of nine to plan: "Yeni yazılardan otomatik bülten, Kişisel randevu bağlantısı, Harita ve mağaza bulucu, Müşteri değer raporları, Tavsiye programı, Toptan satış, Etkinlik bileti, Kurslar". He left out the first-steps list.
- Laid out as the first set is (`plans/website-features.md`): `<plan>.md`, `<plan>-features.md`, `<plan>-steps.md`. The rules of `plans/website-features-rules.md` hold for every step; the existing files several plans touch are in `plans/more-features-shared-files.md`.

## Which conditions did the architect bind? (2026-11-16, agent channel message 6460)
- No second engine: each plan opens with what is there today and what it reuses, by file and function. No second sender, ledger, calendar, QR maker or PDF library.
- Timed work hangs on the general job or a job script that takes its turn with `pg_cron_claim()`, before anything is sent: a job that runs twice sends once (`platform/job-script-claims.md`).
- Mail leaves through the mail queue or the campaign path, where the bounce gate and consent are (`core/mail-bounces.md`); never a direct `email()`.
- Money moves only through an order: a product and an order line, whole minor units, `round()`. A refund takes the right back too.
- A new table that keeps a person's data is in the personal data export and in `privacy_erase.php`, and says how long it is kept.
- What differs by person or group (a wholesale price, progress) never enters the anonymous page copy; the plan says how.
- Capacity, stock and the use of a code are taken with one conditional UPDATE, never read then written.
- A plan's first step is server and data only. The cart and checkout files, `workspace.js` and `style_designer.src.js` are held by other open PRs: a step that enters them names the clash.
- Steps stay near 1,000 product lines; every plan says what is left out and whose look it is.

## Which tables may be added?
- Architect (message 6460): the owner's yes of poll 65 covered the first set's tables only; this set's are asked of him in one question. Until his answer no step that adds a table starts; the columns then go through each step's draft PR to the architect, as before.

## Which plans are there?
- `post-digest.md`: new posts of a list sent by themselves as an e-mail to a contact group.
- `meeting-link.md`: a team member's own "book a time with me" page.
- `place-map.md`: the places of a collection on a map, with "near me".
- `customer-value.md`: repeat purchase, lifetime value, cohorts and customer groups.
- `referral-programme.md`: a customer brings a friend and earns store credit.
- `wholesale.md`: group offers, price lists, a minimum order, a company's several buyers.
- `event-tickets.md`: a ticket with a QR code per place, check-in at the door, a waiting list.
- `course-progress.md`: a learner's progress, timed lessons, a certificate.
- The order within the same priority is the architect's.

## When is this file deleted?
- With the last of the eight plans, together with `more-features-shared-files.md`; each plan's close-out removes its own line here.
