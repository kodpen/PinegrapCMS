---
name: design-template-wedding-design
description: Design of plans/design-template-wedding.md: which shipped features the Wedding template reuses for packages, real weddings, enquiries and visits, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- Services, packages, venue spaces, the gallery and the planning timeline are page content from `parts.php` data; prices are words written with `$money()` (a package "From … per guest", a service "From …").
- Real Weddings are a collection (the Hotel's Rooms pattern, [[collection-fields]]): couple, space and season pick lists, `guests` as a number kind, `photo_1`-`photo_4` image kinds, story, the couple's words, suppliers as long text (one line each), office field `featured`. Lists filter `?space=` and `?season=` ([[collection-lists]]).
- An enquiry is a request, never a booking of the date: a record of Wedding Enquiries (package, service and space by `_cf.url_param`, a wished date with `min_date` today, guests, budget band), office-only status, messages on the record page's Comments widget, `submitter_only` view, My Enquiries, the staff list. A venue visit or a first consultation is booked at a free time with the booking widget (`book_visit`, starter tree, [[booking-templates]]).
- With the shop: a date-hold deposit per package and gift cards, `shippable => false` (the Events tickets, [[design-template-events]]). Only shop pages carry `requires => ecommerce`; shared parts and record pages never link them.
- Testimonials beside a Google Reviews widget, as the Hotel.
- Not built (new behaviour): a date-availability calendar for the venue, guest lists or RSVPs, seating plans, supplier portals, a budget planner that calculates, a balance invoice after the deposit.

## Look and names
- Prefix `pwd-` for classes (`pwd-brand`, `pwd-display`, `pwd-eyebrow`, `pwd-price`, `pwd-lift`, `pwd-quote`, `pwd-script`), `wd-` for field ids. Two fonts: Marcellus headings, Lato body. Look `luxe`, palette `sage`, order 87 (85 photo), icon `bi-balloon-heart`. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date, year or month in the sample: seasons and "twelve months before" only.
- Turkish: "düğün" for a wedding, "nikâh" only for the ceremony, "organizasyon" for planning, "paket" for a package, "mekân" for the venue, "salon" or "alan" for a space, "tanışma görüşmesi" for a consultation, "mekân gezisi" for a venue visit, "tarih tutma kaporası" for the deposit, "gerçek düğünler" for Real Weddings; check every shared key in tr.json before reuse.

Related: docs/memory/plans/design-template-wedding.md
