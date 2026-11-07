---
name: design-template-cityhall-design
description: Design of plans/design-template-cityhall.md: which shipped features the City Hall template reuses for services, notices, events and citizen requests, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- Service areas (permits and building, water and roads, waste and recycling, social support, culture and sport, parks and environment, civil affairs) are fixed data drawn as cards and one page each: the services of the area as cards (what it is, who may apply, documents as words, where to apply, "Make a request" linking `request?topic=`).
- The mayor, council members (role and committee, no party), committees and departments (head, floor, phone, hours as words) are fixed data drawn as cards; council meetings are words ("first Tuesday of each month").
- Notices are a collection (the School Events pattern, [[collection-fields]]): a type pick list (Public notice, Tender, Council decision, Vacancy, Zoning notice), a reference number, the publish and deadline dates as words, a department pick list, a summary, details as long text, a document as a file, office field `featured`. The list filters `?type=` ([[collection-lists]]).
- Events are a second collection: a category pick list (Culture, Sport, Children, Market, Ceremony), date and time as words, a place, a summary, details, `photo_1` image kind, office field `featured`; the list filters `?category=`.
- A citizen request is a record of the Requests form, never a case system: kind (Request, Complaint, Suggestion, Information), topic by `?topic=` through `_cf.url_param`, neighbourhood pick list, the place as words, the description, a photo as a file in `private`, name, phone, consent linking `privacy`; office-only status (Received, Forwarded, In progress, Resolved, Closed) and department; messages on the record page's Comments widget, `submitter_only` view, My Requests, the staff list. A meeting request with the mayor's office the same way with a smaller form (subject, preferred days as words).
- Service points are words: address, hours table, phone, an "Open in maps" link with `target _blank` ([[link-target-in-attrs]]); no map embed.
- News is the standard blog. No shop and no catalog: only `address_book` carries `requires => ecommerce`, as Construction and School.
- Not built (new behaviour): bill or tax payment, an e-government login, live case tracking numbers, a public request map, a council vote record, an e-tender bidding system, a calendar feed.

## Look and names
- Prefix `pcy-` for classes (`pcy-brand`, `pcy-display`, `pcy-eyebrow`, `pcy-hero`, `pcy-lift`, `pcy-stat`, `pcy-ref`; `pch-` is Charity's), `cy-` for field ids. Two fonts: Public Sans headings, Source Sans 3 body. Look `classic` if the look list has it, else `minimal`; a civic blue palette; order 99 (the assembly step settles it against the templates landed by then), icon `bi-building`. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date or year in the sample; the city and its people are invented.
- Turkish: "belediye" for the city hall, "belediye başkanı" for the mayor, "belediye meclisi" for the council, "komisyon" for a committee, "müdürlük" for a department, "hizmet" for a service, "duyuru" for a notice, "ihale" for a tender, "meclis kararı" for a council decision, "talep" / "şikâyet" / "öneri" for a request, a complaint, a suggestion, "mahalle" for a neighbourhood, "hizmet noktası" for a service point, "etkinlik" for an event, "Haberler" for News; check every shared key in tr.json before reuse.

Related: docs/memory/plans/design-template-cityhall.md
