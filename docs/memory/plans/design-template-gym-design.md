---
name: design-template-gym-design
description: Design of plans/design-template-gym.md: which shipped features the Gym template reuses for booking, trainers and membership, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour) (no new schema, module or behaviour)
- Group class booking: a request, as the Hotel's booking: a record of the Class Bookings form (class, day, time, `?class=`, `?day=`, `?time=` by `_cf.url_param`, date with `min_date` today), an office-only status, messages on the record page's Comments widget, `submitter_only` view, My Class Bookings, the staff list. The booking widget is not used for group classes: a booking service has capacity 1 (`commerce/bookings-model.md`).
- Personal training: the `book_online` page places the booking widget with `'tree' => 'starter'`, beside a link to the request form, with `privacy` named `lang('privacy-policy')` ([[booking-templates]]).
- Trainers: a collection (the Rooms pattern): the Trainers form with a record page `trainer`, a list `trainers` filtered by `?focus=`, a staff page `trainer_new`.
- Membership: the plans page links the join request form (`join`, plan by `?plan=`, a free trial pass as one answer) and stands without the shop; with the shop, membership products (`membership_days`) renew the buyer's membership and a `members` folder (access `membership`) holds the members' area (workouts, members' timetable), as the Courses template ([[design-template-courses]]). Only shop pages carry `requires => ecommerce`; shared parts never link them.
- The timetable is a fixed table built from the template's data; each class links its booking request with the class, day and time chosen.
- Not built (new behaviour): class capacity and waiting lists, check-in, recurring bookings, freezing a membership, payments per class.

## Look and names
- Prefix `pgy-` for classes (`pgy-brand`, `pgy-display`, `pgy-eyebrow`, `pgy-hero`, `pgy-lift`, `pgy-price`), `gy-` for field ids. Two fonts: Oswald headings, Inter body. Look `bold`, palette `crimson`, order 79, icon `bi-lightning-charge`. No `opacity-*` on text ([[design-template-text-contrast]]).
- Turkish: "ders" for a class, "antrenör" for a trainer, "üyelik" for a membership, "ders programı" for the timetable; check every shared key in tr.json before reuse.

Related: docs/memory/plans/design-template-gym.md
