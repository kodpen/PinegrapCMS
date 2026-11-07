---
name: design-template-veterinary-design
description: Design of plans/design-template-veterinary.md: which shipped features the Veterinary template reuses for appointments, vets and health plans, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- Appointment requests: the Clinic's and Gym's request pattern: a record of the Appointments form (pet's name, species, age, the service, the vet, preferred day and time with `min_date` today, the reason, `?service=`, `?vet=`, `?species=` by `_cf.url_param`), an office-only status, messages on the record page's Comments widget, `submitter_only` view, My Appointments, the staff list.
- Online booking: `book_online` places the booking widget with `'tree' => 'starter'`, beside a link to the request form and the emergency line, with `privacy` named `lang('privacy-policy')` ([[booking-templates]]).
- Vets: a collection (the Trainers pattern): the Vets form with a record page `vet`, a list `vets` filtered by `?species=` (the pets a vet sees most), a staff page `vet_new`.
- Health plans: the `health_plans` page shows the plans per species with a monthly price and links the sign-up request (`plan_join`, plan by `?plan=`); it stands without the shop. With the shop the plans are ordinary products (a year of care paid at once) beside pet care products; no membership access is given. Only shop pages carry `requires => ecommerce`; shared parts never link them.
- Pet Care Articles: the blog, with a species field so the list filters by `?species=`.
- Emergencies: a fixed page with the out-of-hours number and what to do on the way; the account bar's notice links it on every page.
- Not built (new behaviour): a pet record per owner (a reference limited to the owner's own pets), medical history, vaccination reminders by due date, repeat prescriptions online, health plan instalments.

## Look and names
- Prefix `pvt-` for classes (`pvt-brand`, `pvt-display`, `pvt-eyebrow`, `pvt-hero`, `pvt-lift`, `pvt-price`, `pvt-paw`), `vt-` for field ids. Two fonts: Nunito headings, Nunito Sans body. Look `playful`, palette `emerald`, order 99 (95 museum, 97 architecture), icon `bi-heart-pulse`. Rounded cards, a soft mint background on alternate sections. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date, year or month in the sample.
- Turkish: "veteriner hekim" for a vet, "evcil hayvan" for a pet, "randevu" for an appointment, "sağlık planı" for a health plan, "acil" for emergency; check every shared key in tr.json before reuse (`Our Doctors` reads the Clinic's word).

Related: docs/memory/plans/design-template-veterinary.md
