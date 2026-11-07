---
name: design-template-campsite-design
description: Design of plans/design-template-campsite.md: which shipped features the Campsite template reuses for pitches, cabins and stay requests, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- Kinds of stay (tent pitch, touring pitch, glamping tent, cabin) are fixed data drawn as cards and one page each: who it suits, what it has, sleeps as words, the price as words per season.
- Pitches and cabins are a collection (the Hotel Rooms pattern, [[collection-fields]]): a kind pick list, sleeps and size number kinds, hook-up, shade, view as pick lists, `photo_1`-`photo_3` image kinds, the price as words, office field `featured`. The list filters `?kind=` ([[collection-lists]]).
- Seasonal prices are content: a table of seasons (low, mid, high) with dates as words and a price per night as words written with `$money()`; never a price engine.
- A stay request is a request, never a reservation: a record of Stay Requests (unit by `?unit=`, kind by `?kind=`, season by `?season=` through `_cf.url_param`, arrival and departure dates with `min_date` today, adults, children, pets, vehicle or outfit length, notes, consent linking `privacy`), office-only status (Received, Offered, Confirmed, Waiting list, Declined), messages on the record page's Comments widget, `submitter_only` view, My Stay Requests, the staff list. Group enquiries (schools, clubs, rallies, weddings in the meadow) the same way with a smaller form.
- The location is words and a link: address, coordinates as text, directions by car, train and bus, an "Open in maps" link to OpenStreetMap with `target _blank` ([[link-target-in-attrs]]), a park plan as a picture. No map embed (no such component).
- News is the standard blog. No shop and no catalog: only `address_book` carries `requires => ecommerce`, as Construction and School.
- Not built (new behaviour): live availability or a pitch calendar, prices per date, a deposit, online payment, channel-manager sync, a pitch picker on a map.

## Look and names
- Prefix `pcp-` for classes (`pcp-brand`, `pcp-display`, `pcp-eyebrow`, `pcp-hero`, `pcp-lift`, `pcp-price`, `pcp-quote`, `pcp-stat`), `cp-` for field ids. Two fonts: Bitter headings, Source Sans 3 body. Look `natural` if the look list has it, else `elegant`; palette forest green; order 99 (the assembly step settles it against the templates landed by then), icon `bi-tree`. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date or year in the sample.
- Turkish: "kamp alanı" for a campsite, "çadır yeri" for a tent pitch, "karavan yeri" for a touring pitch, "glamping çadırı", "kulübe" / "bungalov" for a cabin, "konaklama talebi" for a stay request, "grup talebi" for a group enquiry, "sezon" for a season, "olanaklar" for facilities, "etkinlikler" for activities, "Haberler" for News; check every shared key in tr.json before reuse: the shared "Setting" reads "Ayar" (a settings screen), so a unit's setting is labelled "Place in the Park" (Kamp Alanındaki Yeri); "konaklama talebi" declines to "talepleri", so tests match "konaklama tale".

Related: docs/memory/plans/design-template-campsite.md
