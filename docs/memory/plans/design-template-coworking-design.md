---
name: design-template-coworking-design
description: Design of plans/design-template-coworking.md: which shipped features the Coworking template reuses for spaces, bookings, memberships and events, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- Spaces: a collection (the Hotel's Rooms pattern): the Spaces form with a record page `space`, a list `spaces` filtered by `?kind=` (hot desk, fixed desk, private office, meeting room, event hall), a staff page `space_new`. Seats and rates per hour, day and month are fields of the record.
- Meeting rooms by the hour: the `book_online` page places the booking widget with `'tree' => 'starter'` (a meeting room is a booking service of capacity 1, `commerce/bookings-model.md`), beside a link to the tour request, with `privacy` named `lang('privacy-policy')` ([[booking-templates]]).
- Tours and office enquiries: requests, as the Gym's join and Hotel's booking: form records with `?plan=` / `?space=` by `_cf.url_param`, a date with `min_date` today, an office-only status, messages on the record page's Comments widget, `submitter_only` view, the visitor's list and the staff list.
- Membership and day passes: the plans page links the tour request and stands without the shop; with the shop, membership products (`membership_days`: 1 for a day pass, 10-day pack as 30 days, monthly plans 30) renew the buyer's membership and a `members` folder (access `membership`) holds the members' area (house guide, perks), as the Gym and Courses templates ([[design-template-courses]]). Only shop pages carry `requires => ecommerce`; shared parts never link them.
- Community events: records of an Events collection form the Café and Bookshop way ([[design-template-bookshop-events]]): sample events dated from today, lists from today on; a sign-up request form with `?event=`.
- Not built (new behaviour): live desk availability or a floor map, door access and key cards, meeting room credits counted per member, invoicing per hour, a member directory with profiles, mail handling for virtual offices beyond words on a page.

## Look and names
- Prefix `pcw-` for classes (`pcw-brand`, `pcw-display`, `pcw-eyebrow`, `pcw-hero`, `pcw-lift`, `pcw-price`), `cw-` for field ids. Two fonts: Space Grotesk headings, Inter body. Look `modern-sharp`, palette `coral`, order 99 (93 school, 95 museum, 97 architecture), icon `bi-laptop`. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date, year or month in the sample.
- Turkish: "ortak çalışma alanı" for the space, "masa" for a desk, "toplantı odası" for a meeting room, "üyelik" for a membership, "günlük bilet" for a day pass (the shipped `Day Pass` key), "tur" for a tour, "etkinlik" for an event; check every shared key in tr.json before reuse.

Related: docs/memory/plans/design-template-coworking.md
