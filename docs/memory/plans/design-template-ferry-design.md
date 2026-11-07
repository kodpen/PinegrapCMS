---
name: design-template-ferry-design
description: Design of plans/design-template-ferry.md: which shipped features the Ferry template reuses for routes, timetables, fares, bookings and notices, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- Routes (four sample crossings between two mainland ports and two islands) are fixed data drawn as cards and one page each: the two ports, the crossing time and distance as words, the ship that sails it, sailings per day per season, the onboard services, the fares as words.
- Ports are fixed data on one page: address, check-in closes as words, parking, how to arrive by bus or car, an "Open in maps" link to OpenStreetMap with `target _blank` ([[link-target-in-attrs]]).
- Timetables are content: a table per route and season (low, high) with departure times as words; the line writes its own dates each year. Never a live schedule.
- Fares are content: a table of fare kinds (adult, child, senior, car, motorcycle, campervan or van, bicycle, cabin) with a single-crossing fare per currency written with `$money()`; never a price engine.
- A booking request is a request, never a ticket: a record of Booking Requests (route by `?route=`, fare kind by `?fare=` through `_cf.url_param`, single or return, outward and return dates with `min_date` today, a preferred sailing as words, adults, children, vehicle kind, registration, length as words, cabin wanted, assistance needed, notes, consent linking `privacy`), office-only status (Received, Confirmed, Waiting List, Declined, Cancelled), messages on the record page's Comments widget, `submitter_only` view, My Bookings, the staff list. Group enquiries (route, date, group size, coach, notes) the same way with a smaller form.
- Travel Notices are a collection ([[collection-fields]]): a kind pick list (Disruption, Timetable Change, Weather, Port Works, General), the route as a pick list of the route titles, when as words, a summary, details as long text, `photo_1`, office field `featured`. The list filters `?route=` ([[collection-lists]]); a route page links `notices?route=<title>` in the pick list's own words.
- News is the standard blog. No shop and no catalog: only `address_book` carries `requires => ecommerce`, as Marina and Campsite.
- Not built (new behaviour): live seat or deck availability, online tickets and payment, prices computed from vehicle length, live ship tracking, weather feeds, push or SMS alerts of disruptions.

## Look and names
- Prefix `pfy-` for classes (`pfy-brand`, `pfy-display`, `pfy-eyebrow`, `pfy-hero`, `pfy-lift`, `pfy-price`, `pfy-quote`, `pfy-stat`, `pfy-time`), `fy-` for field ids. Two fonts: Barlow Condensed headings, Source Sans 3 body. Look `minimal`, palette `ocean` (deep teal and signal orange); order settled by the assembly step; icon `bi-water`. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date or year in the sample.
- Turkish: "feribot" for a ferry, "hat" for a route, "iskele" / "liman" for a port, "sefer" for a sailing, "sefer saatleri" for timetables, "ücretler" for fares, "yaya yolcu" for a foot passenger, "araçlı yolcu" for a vehicle passenger, "kabin" for a cabin, "rezervasyon talebi" for a booking request, "grup talebi" for a group enquiry, "sefer duyuruları" for travel notices, "Haberler" for News; check every shared key in tr.json before reuse.

Related: docs/memory/plans/design-template-ferry.md
