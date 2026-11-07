---
name: design-template-drivingschool-design
description: Design of plans/design-template-drivingschool.md: what the Driving School template reuses, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- Licence classes are fixed data drawn as cards and one page each (they follow the law, not the season): the class letter, what it lets you drive, the minimum age as words, the lessons most learners need, the theory and practical test, a link to its courses (`courses?licence=`) and to a lesson request (`lesson_request?licence=`).
- Courses are a collection ([[collection-fields]]): name, a licence pick list, a format pick list (Standard, Intensive, Refresher, Automatic only, Theory only), the price as words built with `$money` per currency (never a product, never charged online), lessons and hours as words, gearbox, what is included as long text (one item a line), a summary, `photo_1` image kind, office field `featured`. The list filters `?licence=` and `?format=` ([[collection-lists]]); the course page opens the enrolment with the course chosen (`enrol?course=`).
- An enrolment is a request, never a payment: a record of the Course Enrolments form (course by `?course=` through `_cf.url_param`, date of birth, whether the learner already has a provisional licence or passed the theory test, preferred branch, preferred lesson times as words, notes, consent linking `privacy`), office-only status (Received, Call booked, Enrolled, Waiting list, Declined), messages on the record page's Comments widget, `submitter_only` view, My Enrolments, the staff list.
- A lesson request is a smaller form (licence by `?licence=`, instructor by `?instructor=` with "Any instructor", lesson length, gearbox, pick-up point as words, preferred day with `min_date` today, notes, consent), office-only status (Received, Confirmed, Not available), messages, My Lesson Requests, the staff list. Beside it `book_online` places the booking widget's starter ([[booking-templates]]): a school that sets lesson types and instructors in Settings › Bookings takes bookings of a free time; until then the widget shows its `no_services` text and the request form stays.
- Pass stories are a collection (learner's first name, licence class, first try or not as a pick list, instructor, lessons taken as words, the learner's words, `photo_1`, office `featured`), list by `?licence=`; the home shows the featured ones.
- Instructors, branches, theory topics, test-day steps, why us and the figures are fixed data drawn as cards (a staff user edits the page); News is the standard blog.
- No shop and no catalog: only `address_book` carries `requires => ecommerce`, as Law, Construction and Film Studio.
- Not built (new behaviour): an online theory practice quiz with scoring, a live instructor calendar beyond the booking widget, online payment of a course or lesson packs, learner progress tracking, test date booking with the authority.

## Look and names
- Prefix `pdr-` for classes (`pdr-brand`, `pdr-display`, `pdr-eyebrow`, `pdr-hero`, `pdr-lift`, `pdr-stat`, `pdr-plate`, `pdr-badge`), `drv-` for field ids. Two fonts: Barlow headings, Inter body. Look `bold`, palette `amber`, order 99, icon `bi-car-front`. A licence class letter drawn as a plate badge (`pdr-plate`). No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date or year in the sample; prices are words from `$money`, minimum ages and lesson counts are words.
- Turkish: "sürücü kursu" for the school, "ehliyet sınıfı" for a licence class, "kurs" for a course, "ders" for a lesson, "eğitmen" for an instructor, "teorik sınav" / "direksiyon sınavı" for the tests, "kayıt talebi" for an enrolment, "ders talebi" for a lesson request, "başarı hikâyesi" for a pass story, "Haberler" for News; check every shared key in tr.json before reuse.

Related: docs/memory/plans/design-template-drivingschool.md
