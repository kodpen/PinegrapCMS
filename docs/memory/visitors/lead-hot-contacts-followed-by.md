---
name: lead-hot-contacts-followed-by
description: The Hot Contacts view's "Followed by" column (LJ4c, #11301): the people holding an open follow-up task of the contact, read in one batch; no assignee column on contacts
module: contact_list
---

## What does the plan's "assignee" column read?
- Architect (2026-11-24), the open point of LJ4b (#11291): contacts have no assignee field and none is added. The column is named "Followed by" and reads the people who hold an open follow-up task tagged with the contact, the tasks "Remind me" opens (`ws_contact_followup_open()`).
- Why: the list's question is "is somebody on this contact already?", and the follow-up task answers it from what is already kept. A person set on the contact itself would be a new field and a product rule of its own (who may set it, what it changes); nobody asked for it, and that would be the owner's to decide.

## How is it drawn?
- One column after Last visit, shown only when the Workspace is on. Up to two names as the list's person chips, then "+N"; empty when no open follow-up exists. A done or cancelled task no longer counts.
- The reader's own row reads "You". It is not sortable and no filter is added.
- Read in one batch for the page's rows, never a query a row.
- "Remind me" stays offered on a followed contact: a second person may follow too.

## Where is the code?
- LJ4c (#11301), `includes/fn/contact_list.php`. `pg_contact_list_workspace_on()` gates the column (and Remind me). `pg_contact_list_followers()` reads the page's contacts in one query (`ws_refs` of `task`/`contact` joined to `ws_tasks` status todo, doing, waiting and `ws_task_assignees`); `pg_contact_list_rows()` then names the people with one `ws_people()` call.
- `pg_contact_list_followers_map()` (pure) keeps each person once, in the order of their first open task; `pg_contact_list_followed_cell()` (pure) draws the chips (`pg-initials` in the person's tone, then the name).
- Any open task tagged with the contact counts, not only the ones Remind me opened: a channel task on the contact also says somebody is on it.

## And the API?
- `GET /customers/hot` (LJ6) carries the same people as `followed_by` (user ids), `[]` for nobody, null with the Workspace off (`api/api-lead-journey.md`).

## Tests?
- `tests/contact_list_followed_by_test.php`: the map on none, one and four people, a done and a cancelled task left out, the Workspace off, the cell (You first, two chips, "+N", an account gone), the column's place.

## What was not checked live?
- No running site: the query and the column never ran against a database or in a browser (2026-11-24).
