---
name: design-template-travel-design
description: Design of plans/design-template-travel.md: which shipped features the Travel template reuses for tours, bookings, deposits and reviews, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- Destinations and tours are collections (the Hotel's Rooms pattern, [[collection-fields]]): a destination has a region pick list, a cover image and a summary; a tour has a destination and a style pick list, `days` and `group_size` as number kinds, `length` as words ("4 days, 3 nights", `$length_text()`), `photo_1`-`photo_3` image kinds, the price as words written with `$money()` from `$tour_prices`, departures as words, the itinerary as long text (one line per day, `$itinerary_text()`), inclusions, office field `featured`. Lists filter `?region=`, `?destination=`, `?style=` ([[collection-lists]]).
- The itinerary page is the tour's record page; reviews are the page's comments with stars (`'comments'` with `rating`), so a traveller's review sits on the tour it is about.
- A booking is a request, never a reservation: a record of Booking Requests (tour by `?tour=` through `_cf.url_param`, departure date with `min_date` today, travellers, room choice), office-only status, messages on the record page's Comments widget, `submitter_only` view, My Bookings, the staff list. A tailor-made enquiry the same way.
- With the shop: a deposit product per sample tour and gift cards in a Tour Packages group, `shippable => false` (the Events tickets, [[design-template-events]]). Only shop pages carry `requires => ecommerce`; shared parts and record pages never link them.
- Not built (new behaviour): live seat counts per departure, prices per date, a balance invoice after the deposit, flight or hotel search, supplier sync.

## Look and names
- Prefix `ptv-` for classes (`ptv-brand`, `ptv-display`, `ptv-eyebrow`, `ptv-price`, `ptv-lift`, `ptv-quote`), `tv-` for field ids. Two fonts: Poppins headings, Nunito Sans body. Look `modern-soft`, palette `sky`, order 83 (79 gym, 81 charity), icon `bi-airplane`. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date or month in the sample: departures are words like "Every Saturday".
- Turkish: "tur" for a tour, "destinasyon" for a destination, "gezi programı" for an itinerary, "kapora" for a deposit, "rezervasyon talebi" for a booking request; check every shared key in tr.json before reuse.

Related: docs/memory/plans/design-template-travel.md
