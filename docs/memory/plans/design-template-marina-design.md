---
name: design-template-marina-design
description: Design of plans/design-template-marina.md: which shipped features the Marina template reuses for berths, charters and requests, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- Kinds of berth (visitor pontoon berth, seasonal berth, annual berth, swinging mooring, dry stack) are fixed data drawn as cards and one page each: who it suits, what it includes (water, shore power, Wi-Fi, showers), the largest boat as words, the rate as words.
- Rates are content: a table of seasons (low, high) and boat-length bands with a rate per night, per month or per year as words written with `$money()`; never a price engine.
- Charter boats are a collection ([[collection-fields]]): a type pick list (Sailing Yacht, Motor Yacht, Catamaran, Day Boat, RIB), length, berths and guests as number kinds, skippered or bareboat as a pick list, the home port, the price as words ("From ... a day"), a summary, details as long text, `photo_1`-`photo_3` image kinds, office field `featured`. The list filters `?type=` ([[collection-lists]]).
- A visitor berth request is a request, never a reservation: a record of Berth Requests (kind by `?kind=`, season by `?season=` through `_cf.url_param`, arrival and departure dates with `min_date` today, boat name, type, length and beam and draught as words, crew, shore power needed, notes, consent linking `privacy`), office-only status (Received, Berth Allocated, Confirmed, Waiting List, Declined), messages on the record page's Comments widget, `submitter_only` view, My Berth Requests, the staff list. Charter enquiries (boat by `?boat=`, dates, guests, skipper wanted, experience) and event entries (event by `?event=`, boat, class, crew) the same way with smaller forms.
- Events (Regatta, Open Day, Sailing Course, Social, Boat Show) are a collection: when and where as words, the entry fee as words, `photo_1`, `featured`.
- Arriving is words and a link: the approach, the VHF channel, depths and tides as words, opening hours of the harbour office, an "Open in maps" link to OpenStreetMap with `target _blank` ([[link-target-in-attrs]]), a harbour plan as a picture. No map, tide table or weather embed (no such component).
- News is the standard blog. No shop and no catalog: only `address_book` carries `requires => ecommerce`, as Construction and Campsite.
- Not built (new behaviour): live berth availability or a berth plan picker, prices computed from boat length, deposits or online payment, live tide or weather feeds, fuel dock queueing.

## Look and names
- Prefix `pmn-` for classes (`pmn-brand`, `pmn-display`, `pmn-eyebrow`, `pmn-hero`, `pmn-lift`, `pmn-price`, `pmn-quote`, `pmn-stat`), `mn-` for field ids. Two fonts: Josefin Sans headings, Nunito Sans body. Look `minimal`, palette `midnight` (navy), order 99 (the assembly step settles it against the templates landed by then), icon `bi-life-preserver`. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date or year in the sample.
- Turkish: "marina", "yat limanı" for a yacht harbour, "bağlama yeri" for a berth, "şamandıra" for a swinging mooring, "kara park" for dry stack, "misafir tekne" for a visitor boat, "bağlama talebi" for a berth request, "kiralık tekne" for a charter boat, "kiralama talebi" for a charter enquiry, "kaptan" for a skipper, "yat yarışı" / "regata" for a regatta, "Haberler" for News; check every shared key in tr.json before reuse.

Related: docs/memory/plans/design-template-marina.md
