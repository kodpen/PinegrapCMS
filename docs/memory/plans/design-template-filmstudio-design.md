---
name: design-template-filmstudio-design
description: Design of plans/design-template-filmstudio.md: which shipped features the Film Studio template reuses, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- The showreel is a `video` element with a poster (the Courses lesson player): the studio puts its own file in the template's folder, or swaps the player for its video host's embed (a custom HTML node). Until then the poster shows.
- Projects are a collection (the Construction and Architecture pattern, [[collection-fields]]): an industry and a service pick list, the client, where it was shot and the running time as words, the status (Released, In post-production) as a pick list, never a year; `still_1`-`still_4` image kinds, the film's address as a text field drawn as a link opening in a new window (never an embed from a record value; [[bound-href-scheme]] keeps it to http and https), a summary, the credits as long text (one "Role: Name" line each), the client's words, office field `featured`. Lists filter `?industry=` and `?service=` ([[collection-lists]]).
- A project enquiry is a request, never a contract: a record of the Project Enquiries form (service by `?service=` through `_cf.url_param`, what the film is for, where it will be shown, length, budget band, delivery window, a brief or references as files in `private`, consent linking `privacy`), office-only status (Enquiry received, Call booked, Proposal sent, In production, Declined), messages on the record page's Comments widget, `submitter_only` view, My Enquiries, the staff list.
- A studio hire request is a smaller form (space by `?space=`, preferred date with `min_date` today, length, crew size, kit needed as words, notes), staff list `staff_studio_hires`; no calendar, the studio answers with a confirmation. The booking widget is not used: it needs services and staff the studio does not sell by the hour.
- Crew calls as Construction's careers: the Jobs collection (department and type pick lists, location, summary, duties), each call links `application?job=`; an Applications form (role, showreel or portfolio link, CV, letter), `submitter_only` view, staff list `staff_applications`.
- The crew, the studio's spaces, why us and the figures are fixed data drawn as cards (no collection: they change a few times a year and a staff user edits the page); the Journal is the standard blog under its own name.
- No shop and no catalog: only `address_book` carries `requires => ecommerce`, as Law and Construction.
- Not built (new behaviour): a client review area with time-coded notes on a cut, file delivery of masters, a live studio calendar, a cost calculator, embedding a film from a record's address.

## Look and names
- Prefix `pfm-` for classes (`pfm-brand`, `pfm-display`, `pfm-eyebrow`, `pfm-hero`, `pfm-lift`, `pfm-stat`, `pfm-quote`, `pfm-play`), `fm-` for field ids (never `fs-`: Bootstrap's font-size utilities use it). Two fonts: Oswald headings, Inter body. Look `bold`, palette `midnight`, order 99 (89 construction, 91 music, 93 school, 95 museum, 97 architecture), icon `bi-film`. Wide 16:9 stills, a dark showreel band, the running time beside each project. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date or year in the sample: a project is Released or In post-production.
- Turkish: "proje" for a project, "hizmet" for a service, "proje talebi" for an enquiry, "stüdyo kiralama" for a studio hire, "ekip" for the crew, "ekip ilanı" for a crew call, "başvuru" for an application, "tanıtım filmi" for the showreel, "çekim" for a shoot, "kurgu" for an edit, "Günce" for the Journal; check every shared key in tr.json before reuse.

Related: docs/memory/plans/design-template-filmstudio.md
