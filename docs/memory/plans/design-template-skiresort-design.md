---
name: design-template-skiresort-design
description: Design of plans/design-template-skiresort.md: the shipped features the Ski Resort template reuses, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- Slopes are a collection ([[collection-fields]]): a difficulty pick list (Green: Beginner, Blue: Easy, Red: Intermediate, Black: Expert), an area pick list, a status pick list (Open, Closed, On Hold) the staff set each morning, length and vertical drop as words, the lift that serves it as words, a summary, details as long text, `photo_1`, office field `featured`. The list filters `?difficulty=` and `?area=` ([[collection-lists]]). Badge colours cannot follow the record, so difficulty colours are a fixed legend.
- Lifts are a collection too: kind (Gondola, Chairlift, Drag Lift, Magic Carpet), area, status, ride time and capacity as words. No page per lift; the `lifts` list and the conditions page show them.
- Conditions are words the staff edit in the designer (snow depth, last snowfall, temperature, wind, avalanche danger) beside the slope and lift lists.
- Lift passes (half day, day, three days, six days), season passes (adult, young, family, senior) and rental (ski set, snowboard set, helmet, children's set, per day) are one price table in parts.php read by `$money()`: the public pages say them as words, the shop makes them products of three groups, `shippable => false`; only the shop pages and `address_book` carry `requires => ecommerce`. A pass is collected at the ticket office from the receipt; rental is collected at the rental shop.
- A ski school lesson is a request, never a booking: the Lesson Requests form (lesson by `?lesson=`, level by `?level=`, age group by `?age=` through `_cf.url_param`, first day with `min_date` today, days as a pick list, number of skiers, ski or snowboard, notes, consent linking `privacy`), office-only status (Received, Instructor Booked, Waiting List, Cancelled), messages on the record page's Comments widget, `submitter_only` view, My Lessons, the staff list.
- Lodging is fixed data (hotel, chalets, apartments, mountain hut) drawn as cards with a "from" price as words; a stay or group enquiry is a request the same way (stay by `?stay=`, group kind by `?group=`, arrival with `min_date` today, nights, guests, lessons or passes wanted, notes, consent), status (Received, Offer Sent, Confirmed, Closed).
- Events are a collection dated as Café and Restaurant: a `date` field counted from the day the template opens (`$event_on`), time, place, kind pick list, price as words, `photo_1`, `featured`; the list shows today on, sorted by date.
- News is the standard blog; staff are fixed data on `about`.
- Not built (new behaviour): live lift or slope status feeds, an interactive trail map, dated passes or dynamic pricing in the cart, instructor scheduling, lodging availability or a room booking engine, weather or snow APIs, webcams.

## Look and names
- Prefix `psk-` for classes (`psk-brand`, `psk-display`, `psk-eyebrow`, `psk-hero`, `psk-lift`, `psk-price`, `psk-stat`, `psk-quote`), `skr-` for field and section ids (never `sk-`: secret scanners read it as an API key). Two fonts: Outfit headings, Nunito Sans body. Look `minimal`, palette `sky`, order 99 (the assembly step settles it), icon `bi-snow`. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date, year or month in the sample: seasons are words, events count from today.
- Turkish: "kayak merkezi" for the resort, "pist" for a slope, "teleferik" for a gondola, "telesiyej" for a chairlift, "teleski" for a drag lift, "yürüyen bant" for a magic carpet, "skipass" for a lift pass, "sezon kartı" for a season pass, "ekipman kiralama" for rental, "kayak okulu" for the ski school, "ders talebi" for a lesson request, "konaklama" for lodging, "grup talebi" for a group enquiry, "kar durumu" for conditions, "etkinlik", "Haberler" for News; check every shared key in tr.json before reuse.

Related: docs/memory/plans/design-template-skiresort.md
