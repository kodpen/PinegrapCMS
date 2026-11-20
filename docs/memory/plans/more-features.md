---
name: more-features
description: Start file of the eight plans the owner chose from the second feature study (owner 2026-11-16, agent channel message 6424 and poll 82): his words, the architect's conditions, the plans
priority: 6
---

## What is asked?
- Owner (2026-11-16, agent channel message 6424): "yeni özellikler için araştırma yap". The study's list is message 6433; sixty candidates were checked against memory, the decisions list and the code, and most were already built or decided.
- Owner (2026-11-16, agent channel poll 82, message 6434), asked which of nine to plan: "Yeni yazılardan otomatik bülten, Kişisel randevu bağlantısı, Harita ve mağaza bulucu, Müşteri değer raporları, Tavsiye programı, Toptan satış, Etkinlik bileti, Kurslar". He left out the first-steps list.
- Laid out as the first set is (`plans/website-features.md`): `<plan>.md`, `<plan>-features.md`, `<plan>-steps.md`. The rules of `plans/website-features-rules.md` hold for every step; the existing files several plans touch are in `plans/more-features-shared-files.md`.

## Which conditions did the architect bind?
- `plans/website-second-set-conditions.md` holds the conditions for all eight plans and `plans/website-second-set-conditions-per-plan.md` what each plan adds (architect, 2026-11-16, agent channel message 6460). Each plan names the two files under "What binds it?" and does not repeat them.

## Which tables and columns may be added?
- Owner (2026-11-16, agent channel poll 84, message 6513), asked whether the fourteen tables of the first draft may be added: "mimara soralım bunu yazılımdaki bir özelliği geliştirererek yapabiliyormuyuz değerlendirsin."
- Architect (2026-11-16, agent channel message 6553), table by table: ten are not needed and four stay, one for tickets and three for courses. With no table: the digest, the meeting link, the map, customer value (on a measured rebuild), referral, wholesale prices and the waiting list. Each plan carries its reasons.
- What the set still asks: those four tables; five columns (one each on `ws_profiles`, `erp_accounts` and the product, two on `stock_notify`); the value `referral` of `gift_cards.kind`; an index on `stock_notify`. Every column goes through its step's draft PR to the architect.
- Two behaviours changed against the first draft: no place is held for the first in a waiting list, and a fixed wholesale price needs the ERP. A company's buyers left the wholesale plan (owner, poll 87).
- Owner (2026-11-16, agent channel poll 91, message 6584), asked with the architect's view whether those tables, columns, the credit kind and the index may be added: "Evet, Mimar'ın görüşüyle; sütunları Mimar onaylasın". The other choice was the columns alone, with the tickets and courses tables waiting.

## In which order are the plans given?
- Architect (2026-11-16, agent channel message 6554): all eight are priority 6. First those that need no schema (the digest, the map, customer value), then the meeting link, wholesale, referral, tickets, courses. When a step starts is the coordinators': the free steps of plans already begun come first.

## Which plans are there?
- `place-map.md`: the places of a collection on a map, with "near me".
- `referral-programme.md`: a customer brings a friend and earns store credit.
- `event-tickets.md`: a ticket with a QR code per place, check-in at the door, a waiting list.

## When is this file deleted?
- With the last of the eight plans, together with `more-features-shared-files.md`; each plan's close-out removes its own line here.
