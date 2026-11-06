---
name: design-template-school-design
description: Design of plans/design-template-school.md: which shipped features the School template reuses for programmes, events and admissions, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- Programmes are fixed data drawn as cards and one page each (a school's programmes change rarely): age range as words, group size, hours, what a day holds, the fee as words ("Ask for this year's fees"), never a price.
- Events are a collection (the Travel Tours pattern, [[collection-fields]]): a programme pick list (or "Whole school"), the date and time as words (no fixed date in the sample), a place, a summary, details as long text, a `photo_1` image kind, office field `featured`. The list filters `?programme=` ([[collection-lists]]).
- An admission application is a request, never an enrolment: a record of the Admissions form (programme by `?programme=` through `_cf.url_param`, the child's name and birth date, start term, parent's name, phone, notes, documents as files in `private`, consent linking `privacy`), office-only status (Received, Visit booked, Offered, Waiting list, Declined), messages on the record page's Comments widget, `submitter_only` view, My Applications, the staff list.
- A visit request is a smaller form (preferred day as words, number of visitors), staff list `staff_visits`.
- Staff are fixed data drawn as cards on `staff_team` and `about`; News is the standard blog.
- No shop and no catalog: only `address_book` carries `requires => ecommerce`, as Law and Construction.
- Not built (new behaviour): fee payment, a parent portal with grades, attendance or timetables, a class messaging app, a calendar feed.

## Look and names
- Prefix `psc-` for classes (`psc-brand`, `psc-display`, `psc-eyebrow`, `psc-hero`, `psc-lift`, `psc-stat`), `sc-` for field ids. Two fonts: Nunito headings, Inter body. Look `playful`, palette `sunset`, order 93 (79 gym, 81 charity, 83 travel, 85 photo, 87 wedding, 89 construction, 90 forum, 91 music), icon `bi-backpack`. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date or year in the sample; children are never named or pictured by face in the sample.
- Turkish: "program" for a programme, "sınıf" for a class, "ön kayıt başvurusu" for an admission application, "okul ziyareti" for a visit, "etkinlik" for an event, "öğretmen" / "kadro" for staff, "veli" for a parent, "Haberler" for News; check every shared key in tr.json before reuse.

Related: docs/memory/plans/design-template-school.md
