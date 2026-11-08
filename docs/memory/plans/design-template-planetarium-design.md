---
name: design-template-planetarium-design
description: Design of plans/design-template-planetarium.md: the shipped features the Planetarium template reuses, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- Dome shows are a collection dated as Café and Restaurant events: a `date` field counted from the day the template opens (`$show_on`), showtimes as words ("11:00, 13:30, 16:00"), length in minutes, minimum age, language, an audience pick list (Families, Schools, Adults, Small Children), a summary, details as long text, `photo_1`, office field `featured`; the list shows today on, sorted by date, filtered by `?audience=` ([[collection-lists]]).
- Telescope nights are a second dated collection: date, start time, the target of the night as words, a place pick list (Main Dome, Roof Terrace, Dark-Sky Site), places as words, an office-only status (On, Called Off, Moved), `photo_1`; the status is the staff's call, never a weather feed.
- Tonight's sky, the objects of the season and the moon phases are words the staff edit in the designer, drawn by the family's blocks; no live sky data.
- Opening hours reuse `hours-card` (or `hours-seasons`); prices by visitor reuse `pricing-tiers`; membership levels reuse `pricing-tiers`; astronomers reuse `team-grid`; the telescopes reuse `features-grid` or `launch-specs` only for a spec table.
- Tickets (dome show adult, child, family; telescope night) and memberships (single, family, patron, free shows for a year) are one price table in parts.php read by `$money()`: the public pages say them as words, the shop makes them products of two groups, `shippable => false`; only the shop pages and `address_book` carry `requires => ecommerce`. A ticket is shown at the door from the receipt.
- A school or group visit is a request, never a booking: the Visit Requests form (visit kind by `?visit=`: dome show and workshop, observatory day, star party, teacher training; grade by `?grade=` through `_cf.url_param`; date with `min_date` today; number of pupils; show wanted as words; accessibility needs; notes; consent linking `privacy`), office-only status (Received, Confirmed, Waiting List, Cancelled), messages on the record page's Comments widget, `submitter_only` view, My Visits, the staff list.
- Astronomy news is the standard blog; the observatory's history is fixed data on `observatory`.
- Not built (new behaviour): seat maps or timed seat capacity in the cart, live weather or cloud forecasts, computed moon phases or ephemerides, a live telescope camera, membership cards or member pricing in the cart, push alerts for called-off nights.

## Look and names
- Prefix `ppm-` for classes (`ppm-brand`, `ppm-display`, `ppm-hero`, `ppm-time`, `ppm-price`), `plm-` for field and section ids. Two fonts: Sora headings, IBM Plex Sans body. A night palette (`aurora` or `midnight`, the assembly step settles it) with dark night sections; look settled by the assembly step; icon `bi-stars`. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date, year or month in the sample: shows and nights count from today, the moon counts "in N days".
- Turkish: "planetaryum", "gözlemevi" for the observatory, "kubbe" for the dome, "kubbe gösterisi" for a dome show, "seans" for a showtime, "teleskop gecesi", "gökbilimci" for an astronomer, "gök cismi", "gezegen", "bulutsu", "gökada", "yıldız kümesi", "Ay evresi", "dolunay", "okul ziyareti", "üyelik", "bilet", "Haberler" for News; check every shared key in tr.json before reuse.

Related: docs/memory/plans/design-template-planetarium.md
