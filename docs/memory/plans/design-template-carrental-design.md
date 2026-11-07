---
name: design-template-carrental-design
description: Design of plans/design-template-carrental.md: which shipped features the Car Rental template reuses for the fleet, bookings and business rentals, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- The fleet: a collection (the Vehicles pattern of Dealership): the Fleet form with a record page `car`, a list `fleet` filtered by `?class=`, `?gearbox=`, `?fuel=` ([[collection-lists]]), a staff page `car_new`. Seats, doors, bags and the daily rate's figure are number kinds, `photo_1`-`photo_3` image kinds, office fields `featured` and `available`. A car is shown as "or similar", as rental companies promise a class, not a plate.
- A booking is a request, never a reservation: a record of Booking Requests as Hotel's booking (`?car=`, `?class=`, `?location=`, `?deal=` by `_cf.url_param`, pick-up and return dates with `min_date` today, times from a pick list, extras as check boxes, a driver age band), office-only status, messages on the record page's Comments widget (`booking_messages`), view `submitter_only`, My Bookings, a staff list. The price is confirmed by the staff in a message; the site shows rates "from … a day".
- Business and long-term rentals: an enquiry form the same way (`?plan=`, `enquiry_messages`), fleet size and term as bands; no company tax number or ID is asked.
- Locations, extras, deals and terms are fixed content of the template (cards and searchable sections); each location, extra and deal links the booking request with its choice made.
- No shop: the rental is paid at the counter (only `address_book` carries `requires => ecommerce`).
- Not built (new behaviour): live availability per date, a price calculated from dates and extras, a deposit or payment online, one-way fees, licence checks, damage reports with photos, fleet stock per location.

## Look and names
- Prefix `pcr-` for classes (`pcr-brand`, `pcr-display`, `pcr-eyebrow`, `pcr-hero`, `pcr-lift`, `pcr-price`), `cr-` for field ids. Two fonts: Archivo headings, Inter body. Look `modern-soft`, palette `amber`, order 99 (97 architecture), icon `bi-key`. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date or year in the sample.
- Turkish: "kiralık araç" / "araç kiralama" for the trade, "filo" for the fleet, "teslim alma" and "iade" for pick-up and return, "ek hizmet" for an extra, "kampanya" for a deal; check every shared key in tr.json before reuse.

Related: docs/memory/plans/design-template-carrental.md
