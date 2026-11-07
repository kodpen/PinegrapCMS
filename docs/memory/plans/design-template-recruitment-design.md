---
name: design-template-recruitment-design
description: Design of plans/design-template-recruitment.md: which shipped features the Recruitment template reuses for jobs, applications and employer enquiries, what it does not build, its look and Turkish words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- Jobs are a collection (the Travel Tours and Construction Projects pattern, [[collection-fields]]): a job has a sector, a city, a contract type and a work mode as pick lists, the salary as words ("%1$s to %2$s a month" from the base currency), the employer shown as words ("A leading logistics company": an agency rarely names its client), a summary, duties and requirements as long text (one line per item), the consultant's name, office field `featured`. Lists filter `?sector=`, `?city=` and `?type=` ([[collection-lists]]).
- An application is a record of the Applications form (the job by `?job=` through `_cf.url_param`, "Open application" without one, sector and city wanted, notice period, CV in `private`, a covering letter, consent linking `privacy`); office-only status (Received, Shortlisted, Interview, Offer, Placed, Not this time), messages on the record page's Comments widget, `submitter_only` view, My Applications, the staff list. Registering a CV is the open application.
- An employer enquiry is a record of the Staff Requests form (company, contact, sector by `?sector=`, city, the role, how many people, contract type, start window, a job description file in `private`, consent); office-only status (Received, Call booked, Shortlist sent, Filled, Closed), messages, `submitter_only` view, My Enquiries, the staff list.
- The consultants are fixed data drawn as cards on `team` and `about`; Career Advice is the standard blog (the candidate guides are its sample posts); job alerts are the newsletter's contact group.
- No shop and no catalog: only `address_book` carries `requires => ecommerce`, as Law and Construction.
- Not built (new behaviour): job alerts matched to a candidate's sectors, a candidate database employers can search, employers posting their own jobs, timesheets for temporary staff, interview scheduling beyond messages, a salary calculator.

## Look and names
- Prefix `prc-` for classes (`prc-brand`, `prc-display`, `prc-eyebrow`, `prc-hero`, `prc-lift`, `prc-stat`, `prc-quote`, `prc-salary`), `rc-` for field ids. Two fonts: Plus Jakarta Sans headings, Inter body. Look `modern-soft`, palette `emerald`, order 99 (89 construction, 91 music, 93 school, 95 museum, 97 architecture), icon `bi-person-workspace`. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date, year or month in the sample.
- Turkish: "iş ilanı" for a job opening, "başvuru" for an application, "aday" for a candidate, "işveren" for an employer, "personel talebi" for an employer enquiry, "danışman" for a consultant, "Kariyer Rehberi" for Career Advice, "İş Alarmı" for job alerts; check every shared key in tr.json before reuse.

Related: docs/memory/plans/design-template-recruitment.md
