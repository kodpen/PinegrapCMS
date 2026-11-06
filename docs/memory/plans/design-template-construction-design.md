---
name: design-template-construction-design
description: Design of plans/design-template-construction.md: which shipped features the Construction template reuses for projects, quotes and careers, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- Projects are a collection (the Travel Tours pattern, [[collection-fields]]): a project has a sector and a service pick list, a location, the year as words ("Completed 2024" is a fixed date, so the sample says "Completed" or "In progress"), the size and duration as words, `photo_1`-`photo_3` image kinds, a summary, the scope as long text (one line per item), the client's words, office field `featured`. Lists filter `?sector=` and `?service=` ([[collection-lists]]).
- A quote request is a request, never a contract: a record of the Quote Requests form (service by `?service=` through `_cf.url_param`, project type, location, budget range, start window, plans and photos as files in `private`, consent linking `privacy`), office-only status (Received, Site visit, Quote sent, Won, Declined), messages on the record page's Comments widget, `submitter_only` view, My Quotes, the staff list.
- Careers as the Law template: job openings are a collection (department and type pick lists, location, summary, duties), each job links `application?job=`; an Applications form (position, CV, letter), `submitter_only` view, staff list `staff_applications`.
- The team is fixed data drawn as cards on `team` and `about` (no collection: a contractor's leaders change rarely); News is the standard blog.
- No shop and no catalog: only `address_book` carries `requires => ecommerce`, as Law.
- Not built (new behaviour): a cost calculator, project tracking for clients (stages, site diary), subcontractor portal, tender documents behind a login beyond the form's files.

## Look and names
- Prefix `pcn-` for classes (`pcn-brand`, `pcn-display`, `pcn-eyebrow`, `pcn-hero`, `pcn-lift`, `pcn-stat`), `cn-` for field ids. Two fonts: Archivo headings, Inter body. Look `bold`, palette `amber`, order 89 (79 gym, 81 charity, 83 travel, 85 photo, 87 wedding), icon `bi-bricks`. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date or year in the sample.
- Turkish: "proje" for a project, "hizmet" for a service, "teklif talebi" for a quote request, "keşif" for a site visit, "iş ilanı" for a job opening, "başvuru" for an application, "Haberler" for News; check every shared key in tr.json before reuse.

Related: docs/memory/plans/design-template-construction.md
