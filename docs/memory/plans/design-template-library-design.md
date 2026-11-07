---
name: design-template-library-design
description: Design of plans/design-template-library.md: which shipped features the Library template reuses for the catalogue, events, membership and rooms, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- Branches are fixed data drawn as cards and one page each (a library's branches change rarely): address, opening hours per day as words, what the branch has (children's corner, study tables, computers, step-free entrance), the phone.
- The catalogue is a collection ([[collection-fields]]): title, author, a format pick list (Book, Large Print, eBook, Audiobook, DVD, Magazine), an audience pick list (Adults, Teens, Children), a genre, the shelf mark, the branch that holds it, availability as words ("On the shelf", "On loan"), a summary, `photo_1` image kind (the cover), office field `new_arrival`. The list filters `?format=` and `?audience=` ([[collection-lists]]); the home lists the new arrivals. It is a showcase the staff keep, never a link to a library management system.
- An item reservation is a request: a record of the Reservations form (the title by `?title=` through `_cf.url_param`, the library card number, the pick-up branch, notes), office-only status (Received, Ready to collect, Collected, Not available), messages on the record page's Comments widget, `submitter_only` view, My Reservations, the staff list.
- Events and reading clubs are a collection (kind: Reading Club, Author Talk, Story Time, Workshop, Digital Help, Exhibition), the branch, when as words (no fixed date in the sample), the age group, a summary, details as long text, `photo_1`, `featured`; a place is a request (Event Bookings form with `?event=`, number of places, notes, consent), office-only status, messages, My Bookings, the staff list.
- A library card is a request (Membership form: name, birth date, address, phone, home branch, card kind Adult / Young Reader / Visitor, proof of address as a file in `private`, consent linking `privacy`), office-only status (Received, Card ready to collect, Card issued, More information needed), messages, My Memberships, the staff list.
- Rooms and spaces are fixed data (study room, meeting room, community hall, children's activity room: size, seats, equipment, the branch); a room booking is a request (Room Bookings form: the room by `?room=`, purpose, people, preferred day and time as words with `min_date` today, notes, consent), office-only status (Received, Confirmed, Not available), messages, My Room Bookings, the staff list.
- Staff are fixed data drawn as cards on `about`; News is the standard blog.
- No shop and no catalog: only `address_book` carries `requires => ecommerce`, as Law, Construction and School.
- Not built (new behaviour): loans, renewals and due dates, fines, a live catalogue search against a library system (ILS), automatic hold queues, a booking calendar with room capacity, an eBook lending service.

## Look and names
- Prefix `plb-` for classes (`plb-brand`, `plb-display`, `plb-eyebrow`, `plb-hero`, `plb-lift`, `plb-stat`, `plb-cover`, `plb-hours`), `lib-` for field ids (`lb-` is the lightbox's class prefix). Two fonts: Literata headings, Source Sans 3 body. Look `elegant`, palette `forest`, order 99, icon `bi-book`. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date or year in the sample; sample titles and authors are made up.
- Turkish: "kütüphane" for the library, "şube" for a branch, "katalog" for the catalogue, "yeni gelenler" for new arrivals, "ayırtma" for a reservation, "okuma kulübü" for a reading club, "etkinlik" for an event, "üyelik" / "kütüphane kartı" for membership, "salon" / "oda" for a room, "Haberler" for News; check every shared key in tr.json before reuse.

Related: docs/memory/plans/design-template-library.md
