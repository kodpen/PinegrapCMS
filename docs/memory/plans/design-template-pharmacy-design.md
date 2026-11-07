---
name: design-template-pharmacy-design
description: Design of plans/design-template-pharmacy.md: the shipped features it reuses, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- Prescription requests: the Veterinary appointment's request pattern: a record of the Prescription Requests form (the patient's name, date of birth, the prescription number or "repeat of my last one", the medicines in a text area, collect in store or home delivery, the preferred day with `min_date` today, a note; `?service=`, `?pharmacist=` by `_cf.url_param`), an office-only status (received, being prepared, ready to collect, out for delivery, collected), messages on the record page's Comments widget, `submitter_only` view, My Prescriptions, the staff list. The request page says that the original prescription is shown at the counter and that nothing is dispensed without it.
- Questions to the pharmacist: a second request form (topic, the question, medicines already taken) with a private reply thread on its record page (`submitter_only`), My Questions, the staff list; never a public Q&A.
- Online booking: `book_online` places the booking widget with `'tree' => 'starter'` (vaccinations and health checks are the operator's booking services), beside a link to the request form and the on-duty page, with `privacy` named `lang('privacy-policy')` ([[booking-templates]]).
- Pharmacists: a collection (the Vets pattern): the Pharmacists form with a record page `pharmacist`, a list `team` filtered by `?service=` (the service a pharmacist leads), a staff page `pharmacist_new`.
- On duty: a fixed page with this pharmacy's duty pattern by weekday (no fixed dates), the night bell, and how to find the pharmacy on duty tonight (the chamber's list, the emergency number); the account bar's notice links it on every page.
- Health checks: a fixed page with each check, what it measures, how long it takes and its price, linking `book_online`; no results are stored.
- Shop: health products as ordinary products (vitamins, skin care, baby care, first aid, devices); prescription medicine is never sold. Only shop pages carry `requires => ecommerce`; shared parts never link them.
- Health Advice: the blog, with a topic field so the list filters by `?topic=`.
- Not built (new behaviour): a patient record or medication history, prescription upload with document checks, e-prescription lookups, refill reminders by due date, a live on-duty rota by date, stock lookup per medicine.

## Look and names
- Prefix `prx-` for classes (`prx-brand`, `prx-display`, `prx-eyebrow`, `prx-hero`, `prx-lift`, `prx-price`, `prx-cross`), `rx-` for field ids. Two fonts: Lexend headings, Source Sans 3 body. Look `modern-soft`, palette `ocean`, order 99 (the assembly step settles it against the templates landed by then), icon `bi-capsule`. Rounded cards, a soft teal background on alternate sections. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date, year or month in the sample.
- Turkish: "eczane" for pharmacy, "eczacı" for pharmacist, "reçete" for prescription, "nöbetçi eczane" for the on-duty pharmacy, "nöbet" for duty, "aşı" for vaccination, "sağlık ölçümü" for a health check, "sağlık önerileri" for Health Advice; check every shared key in tr.json before reuse (the Clinic's and Veterinary's words).

Related: docs/memory/plans/design-template-pharmacy.md
