---
name: design-template-museum-design
description: Design of plans/design-template-museum.md: the shipped features the Museum template reuses, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- Exhibitions are a collection ([[collection-fields]]): a state pick list (Now On, Coming Soon, Past), the dates as words ("Until the end of spring", never a fixed date), the gallery, the curator, a summary, details as long text, `photo_1`-`photo_3` image kinds, office field `featured`. The list filters `?status=` ([[collection-lists]]); a museum moves an exhibition from Coming Soon to Now On to Past by editing its record.
- Collection highlights are a second collection: a department pick list (Paintings, Sculpture, Works on Paper, Decorative Arts, Photography, Archaeology), the maker, the period as words ("Late 19th century"), the material, the gallery where it hangs (or "In store"), the story as long text, `photo_1`, office field `featured`. The list filters `?department=`.
- Events and talks are a collection (kind: Talk, Guided Tour, Workshop, Family Day, Late Opening, Concert), when and where as words, the price as words ("Free with admission"), `photo_1`, `featured`; a place is a request (Event Bookings form with `?event=` through `_cf.url_param`, number of places, notes, consent), office-only status, messages in the record page's Comments widget, `submitter_only`, My Bookings, the staff list.
- A school visit is a request (School Visits form: the session by `?session=`, the school, the year group, pupils and adults, preferred days as words with `min_date` today, access needs, consent), office-only status, messages, My School Visits, the staff list. Learning sessions are the template's data, by age.
- Timed entry: `book_online` places the booking widget's starter ([[booking-templates]]); the museum sets its entry slots up as a bookable service with times in Settings › Bookings.
- With the shop: admission tickets (Adult, Concession, Young Person, Family, Exhibition Ticket) and membership tiers (Individual, Joint, Family, Patron) as products of an Admission and a Membership group, `shippable => false` (the Events tickets, [[design-template-events]]). Prices come from one table (`$admission_prices`, `$tier_prices`) through `$money()`, so the visit and support pages show the shop's figure. Only shop pages carry `requires => ecommerce`; shared parts and public pages never link them (the visit page and the support page say the prices as words).
- Not built (new behaviour): a ticket with a date and time picked in the cart (a template catalog cannot give a product its own order form), capacity per slot across tickets and bookings, a member card or member pricing at checkout, Gift Aid, an online collection database import, an audio guide.

## Look and names
- Prefix `pmz-` for classes (`pmz-brand`, `pmz-display`, `pmz-eyebrow`, `pmz-price`, `pmz-lift`, `pmz-quote`, `pmz-hero`, `pmz-stat`), `mz-` for field ids. Two fonts: Fraunces headings, Work Sans body. Look `editorial`, palette `burgundy`, order 95 (93 school), icon `bi-bank2`. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date, year or month in the sample; an object's period is words.
- Turkish: "sergi" for an exhibition, "eser" for an object, "koleksiyon" for the collection, "bilet" for a ticket, "üyelik" for membership, "etkinlik" for an event, "okul ziyareti" for a school visit, "küratör" for a curator, "Haberler" for News; check every shared key in tr.json before reuse.

Related: docs/memory/plans/design-template-museum.md
