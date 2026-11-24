---
name: volunteer-programmes
description: Contacts › Volunteers (VS2): the programmes list, a programme's Set-up rows, applications from its form with Approve, the Volunteers tab and the profile drawer; who sees what
module: volunteer_programmes, volunteer_programme_setup
---

## Where is it?
- `view_volunteer_programmes.php` (list, "Add programme" drawer) and `edit_volunteer_programme.php` (head with Open/Pause the programme, tabs Shifts (the default, `core/volunteer-board.md`), Applications, Volunteers, Hours (`core/volunteer-hours.md`), Set-up; the profile drawer by `?volunteer=`). Script `assets/js/volunteer_programme.js`, plain, no twin. Built in #11136, step VS2 of plan `volunteer-shifts`.
- Pure rules and the store reads/writes: `includes/fn/volunteer_programmes.php` (in `functions.php`), tested in `tests/volunteer_programmes_test.php`. The Set-up form: `includes/fn/volunteer_programme_setup.php`, loaded by the screen only.
- Menu: Contacts › Volunteers, inside the Contacts menu, so a role 3 coordinator needs contact access to see the link; the screens themselves open by membership alone.

## Who sees what?
- Roles 0-2 see every programme, add programmes and edit Set-up. A panel user listed in a programme's `coordinators` sees that programme's Applications and Volunteers tabs and approves, pauses and marks as left (`pg_volunteer_user_sees()`, `pg_volunteer_coordinator()`); no Set-up tab.
- The profile drawer shows a document's kind and dates only, never its number or file. Uploading (and its exclusion, `core/volunteer-document-file.md`) comes with VS4c.

## How does an application come in?
- `custom_form.php`, after the contact is known, calls `pg_volunteer_application_record()` for a completed entry: each open programme on that form page gets a `volunteers` row `applied` with skills and free days from the fields named in Set-up. No contact (no e-mail mapped to the contact) means no application.
- A contact keeps one profile in a programme: a second entry, or one from a volunteer who left, changes nothing.
- A free-days field's answers are matched by number 1-7, an English or site-language day name, or its first three letters; a chosen day is free all day (`00:00`-`24:00`); none chosen means no limit.

## What do Approve, Pause and Mark as left do?
- `pg_volunteer_move()`: applied to active (Approve, stamps `approved_at`/`approved_by`, queues the welcome e-mail from the programme's Welcome text), active and paused back and forth, active or paused to left (`left_at`). A write conditional on the old state: a double press changes once.
- Approve refuses an applicant under the programme's minimum age when the birth-date field gives a readable `YYYY-MM-DD`; without a date it approves.

## How is Set-up stored?
- Six folded rows: Application form (`page_id`, `form_fields` skills/birth_date/free_days by field name), Roles (`roles`: key `rN` => name, skills, trainings as course ids, documents as kinds; hint "Do not ask for a health report.").
- Then Sign-up rules (`cutoff_hours` 0-720, `min_age` 0-99, `swap_approval`), Calls and reminders (`call_days` 0-14, `call_channels` email/sms/whatsapp), Messages (`texts` welcome/call/reminder/thanks, empty for the standard text, `{name}` and `{programme}` filled), Coordinators (user ids).
- "Not set" pill on Application form, Roles or Coordinators while empty (`pg_volunteer_programme_missing()`). No column was added (decision schema-47).

## What do the lists show?
- Programmes: active volunteers, applications, this week's places filled of needed (signed rows, shifts Monday to Sunday), Open/Paused.
- Applications: name, applied, skills, documents pill (Valid, Missing, Running out within 30 days; "None needed" when no role needs a document), Approve per row and "Approve selected" (the fill) for ticked rows.
- Volunteers: state pill, roles whose skills they have, approved hours this year, show-up rate.

Related: docs/memory/core/volunteer-signup.md, docs/memory/core/volunteer-store.md, docs/memory/core/volunteer-shift-rules.md, docs/memory/plans/volunteer-shifts-screens.md
