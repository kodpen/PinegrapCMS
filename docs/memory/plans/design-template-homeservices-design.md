---
name: design-template-homeservices-design
description: Design of plans/design-template-homeservices.md: shipped features it reuses for services, estimates, visits and areas, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- Services are a collection (the Travel Tours pattern, [[collection-fields]]): a category pick list, `summary`, `duration` as words ("2 to 3 hours"), `crew` as a number kind, the price as words written with `$money()` ("From … per visit" or "per hour"), what is included as long text, `photo` image kind, office `featured`. Lists filter `?category=` ([[collection-lists]]). Reviews are the service page's comments with stars (`'comments'` with `rating`).
- The team is a collection too: name, role (a category or Office), `years` number, summary, `photo`; no link to site users.
- A visit booking is a request, never a confirmed slot: a record of Visit Bookings (service and area by `_cf.url_param`, a day with `min_date` today, a time window pick list, address, access radios, notes), an office-only status, messages on the record page's Comments widget, `submitter_only` view, My Visits, the staff list.
- An estimate request the same way (service, area, property type and size, urgency, description, photos in the `private` folder), status Estimate requested / Estimate sent / Accepted / Declined; the estimate itself is a staff message on the record.
- Service areas, visit days, call-out notes, the price guide, why-us points and the how-it-works steps are page content from `parts.php` data.
- With the shop: care plans and gift cards as products with `shippable => false` (the Events tickets, [[design-template-events]]). Only shop pages carry `requires => ecommerce`; shared parts and record pages never link them.
- Not built (new behaviour): live slot availability or a technician calendar, a postcode checker, price calculators, paying a booking online, recurring visit schedules, technician tracking.

## Look and names
- Prefix `phs-` for classes (`phs-brand`, `phs-display`, `phs-eyebrow`, `phs-price`, `phs-lift`, `phs-quote`), `hs-` for field and section ids. Two fonts: Manrope headings, Source Sans 3 body. Look `modern-sharp`, palette `steel`, order 99, icon `bi-tools`. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date, year or month in the sample: visit days are words ("Every weekday").
- Turkish: "hizmet" for a service, "ziyaret" for a visit (a booking of a visit is "ziyaret talebi", never "rezervasyon"), "fiyat teklifi" or "teklif" for an estimate, "hizmet bölgesi" for a service area, "ekip" for the team, "usta" for a technician, "bakım planı" for a care plan; check every shared key in tr.json before reuse.

Related: docs/memory/plans/design-template-homeservices.md
