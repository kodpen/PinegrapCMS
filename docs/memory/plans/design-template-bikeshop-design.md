---
name: design-template-bikeshop-design
description: Design of plans/design-template-bikeshop.md: the shipped features the Bike Shop template reuses, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- Bikes, parts and accessories are the catalog with the shop: products in four groups, bikes with a size option (S, M, L, XL) as the store's product options, `shippable => true` for parts and accessories, bikes `shippable => false` (collected in store, said in words); workshop vouchers as products. Only the shop pages and `address_book` carry `requires => ecommerce`; the public pages say prices as words from one price table in parts.php read by `$money()`.
- A repair or service is a request, never a booking: the Service Requests form (job by `?job=`: puncture, safety check, full service, overhaul, suspension, e-bike check; bike kind by `?bike=` through `_cf.url_param`; make and model; preferred drop-off date with `min_date` today; what is wrong; consent linking `privacy`), office-only status (Received, Booked In, In the Workshop, Waiting for Parts, Ready to Collect, Collected), office-only quote as words, messages on the record page's Comments widget, `submitter_only` view, My Services, the staff list.
- A bike fit is a request the same way (fit kind by `?fit=`: basic, full, cleat only; height, inseam, riding style, pains or goals; preferred date), and `book_online` places the booking widget beside it for a free time ([[booking-templates]]); fit packages on the `fit` page reuse `pricing-tiers`.
- Group rides are a collection dated as Café and Restaurant events: a `date` field counted from the day the template opens (`$ride_on`), start time, pace pick list (Social, Steady, Fast), drop rule pick list (No-drop, Drop ride), distance, climbing, meeting point, route link (a web address field), `photo_1`, `featured`; the list shows today on, sorted by date, filter `?pace=`. The weekly sample is drawn by `bikeshop-rides`; the calendar is the collection.
- Workshop load this week and the next free drop-off are words the staff edit in the designer (`bikeshop-workshop`); no live queue.
- News is the standard blog; mechanics and fitters are fixed data on `about` (`team-grid`); brands stocked `logos-grid`; opening hours `hours-card`.
- Not built (new behaviour): a live workshop queue or repair tracking by SMS, a frame size calculator, bike hire with dates or deposits, trade-in valuation, finance or cycle-to-work schemes in the cart, Strava or route-file (GPX) uploads and maps, ride sign-up with a capacity, a service history per bike frame number.

## Look and names
- Prefix `pbs-` for classes (`pbs-brand`, `pbs-display`, `pbs-eyebrow` only where the look needs it, `pbs-workbench`), `bikeshop-` for field and section ids. Two fonts: Saira Condensed headings, Schibsted Grotesk body. A graphite and signal-orange palette with a cool steel surface; order and look settled by the assembly step; icon `bi-bicycle`. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date, year or month in the sample: rides count from today, seasons are words.
- Turkish: "bisiklet dükkânı", "atölye" for the workshop, "servis talebi", "bakım", "genel revizyon" for an overhaul, "kadro bedeni" for a frame size, "bike fit" kept with "sürüş pozisyonu ayarı" in the lead, "grup sürüşü", "tempo" for pace, "kimse geride kalmaz" for No-drop, "yedek parça", "aksesuar", "Haberler" for News; check every shared key in tr.json before reuse.

Related: docs/memory/plans/design-template-bikeshop.md
