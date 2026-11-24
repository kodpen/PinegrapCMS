---
name: lead-hot-contacts
description: The contacts list's Hot Contacts view (LJ4b): its condition, columns and order, the empty line, Open and Remind me (a Workspace follow-up), the bell link of contacts_hot; no assignee column yet
module: contact_list
---

## Where is it and what does it list?
- Contacts list (`view_contacts.php`), view "Hot Contacts" (`hot_contacts`) in the view menu after My Customers, offered once `contact_scores.hot` exists (`pg_contact_list_hot_ready()`).
- Contacts whose `contact_scores.hot = 1` (the row the scoring pass writes, `visitors/lead-score.md`), joined in the view itself (`pg_contact_list_view()`); the date range is not read and not shown, as on All Duplicate Contacts. Group picker, advanced filters, search, Export and Delete All work on it as on the other views.
- Order: score high to low, then the latest visit (fixed, not the kept sort; no sort menu); the column heads still sort.

## Which columns?
- `pg_contact_list_hot_columns()`: picture, Contact (name, the company under it), Score (the figure with the "Hot" pill), Why (the strongest reason, `pg_contact_list_hot_reason()`: the interest reason with the most points, else the fit one), Last visit (`last_visit_at`), the groups when toggled on, Action.
- No assignee column: contacts have no assignee field. Open point for the architect: what "assignee" reads (plan `lead-journey-steps.md`).

## What do the row actions do?
- Open: the contact screen. Remind me: only with the Workspace on and the user a member with the contacts right (`pg_contact_list_hot_remind()`).
- Remind me opens a dialog (`pg_contact_list_hot_remind_dialog()`, `assets/js/pg_hot_contacts.js`, served as written): what to follow up on, day (a week ahead), time. Save asks `ws_contact_followup` (api.php, Workspace family): `ws_contact_followup_open()` opens a follow-up task tagged with the contact, for the person, on no channel (`workspace/workspace-followups.md`). A toast says the task number and day.

## What shows with no row?
- "No contact is hot today." and, for Manager and above, a link "Change when a contact is hot" to Site Settings › Visitors and privacy, row Interest score (`pg_contact_list_hot_empty()`), through the list driver's new `empty` source key (HTML; '' keeps "No records found.").

## Where does the bell lead?
- Ops alert `contacts_hot` links `view_contacts.php?filter=hot_contacts` (`visitors/lead-tell.md`).

Tests: `tests/contact_list_hot_test.php`, `tests/workspace_followup_test.php`, `tests/lead_tell_test.php`.
Related: docs/memory/visitors/lead-tell.md, docs/memory/visitors/lead-score-settings.md
