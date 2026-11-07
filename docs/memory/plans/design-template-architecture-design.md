---
name: design-template-architecture-design
description: Design of plans/design-template-architecture.md: which shipped features the Architecture template reuses for projects, enquiries and careers, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- Projects are a collection (the Construction and Travel pattern, [[collection-fields]]): a type and a location pick list, the setting and the size as words, the stage (Completed, On site, In design, Competition entry) as a pick list, never a year; `photo_1`-`photo_4` image kinds drawn as the gallery, a summary, the brief as long text, the credits as long text (one "Role: Name" line each, the builders, engineers and photographers the studio worked with), the client's words, office field `featured`. Lists filter `?type=` and `?location=` ([[collection-lists]]).
- A project enquiry is a request, never a contract: a record of the Project Enquiries form (service by `?service=` through `_cf.url_param`, project type, location, site status, size, budget band, timing, drawings and photos as files in `private`, consent linking `privacy`), office-only status (Received, Call booked, Proposal sent, Appointed, Declined), messages on the record page's Comments widget, `submitter_only` view, My Enquiries, the staff list.
- A consultation request is a smaller form (service, studio or video call, preferred days as words, a few lines), staff list `staff_consultations`; no calendar, the studio answers with a time.
- Careers as Construction and Law: job openings are a collection (team and type pick lists, location, summary, duties), each job links `application?job=`; an Applications form (position, portfolio link and file, CV, letter), `submitter_only` view, staff list `staff_applications`.
- The team, awards and press are fixed data drawn as cards (no collection: they change a few times a year and a staff user edits the page); the Journal is the standard blog under its own name.
- No shop and no catalog: only `address_book` carries `requires => ecommerce`, as Law and Construction.
- Not built (new behaviour): a fee calculator, a client portal with drawings and stages, mood boards or product sourcing for clients, a 3D or VR viewer, booking a consultation slot in a calendar (the booking widget needs services and staff the studio does not sell).

## Look and names
- Prefix `pai-` for classes (`pai-brand`, `pai-display`, `pai-eyebrow`, `pai-hero`, `pai-lift`, `pai-stat`, `pai-quote`, `pai-index`), `ai-` for field ids. Two fonts: Jost headings, Karla body. Look `minimal`, palette `graphite`, order 97 (89 construction, 91 music, 93 school, 95 museum), icon `bi-rulers`. Square corners and thin rules, large photographs, numbers of the project index in small caps. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date or year in the sample: awards and press name the prize or the title, never the year.
- Turkish: "proje" for a project, "hizmet" for a service, "proje talebi" for a project enquiry, "ön görüşme" for a consultation, "stüdyo" for the studio, "ödül" for an award, "basın" for press, "Günce" for the Journal, "iş ilanı" for a job opening, "başvuru" for an application; check every shared key in tr.json before reuse.

Related: docs/memory/plans/design-template-architecture.md
