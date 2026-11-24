---
name: lead-hot-contacts-followed-by
description: What the Hot Contacts view's "assignee" column reads (lead-journey J11): "Followed by", the people holding an open follow-up task of the contact; no assignee column on contacts
---

## What does the plan's "assignee" column read?
- Architect (2026-11-24), the open point of LJ4b (#11291): contacts have no assignee field and none is added. The column is named "Followed by" and reads the people who hold an open follow-up task tagged with the contact, the tasks "Remind me" opens (`ws_contact_followup_open()`).
- Why: the list's question is "is somebody on this contact already?", and the follow-up task answers it from what is already kept. A person set on the contact itself would be a new field and a product rule of its own (who may set it, what it changes); nobody asked for it, and that would be the owner's to decide.

## How is it drawn?
- One column after Last visit, shown only when the Workspace is on. Up to two names as the list's person chips, then "+N"; empty when no open follow-up exists. A done or cancelled task no longer counts.
- The reader's own row reads "You". It is not sortable and no filter is added.
- Read in one batch for the page's rows, never a query a row.
- "Remind me" stays offered on a followed contact: a second person may follow too.

## Which step?
- LJ4c (S) of the lead-journey plan, after LJ4b lands, before LJ6. LJ6's API answer of a hot contact carries the same people as `followed_by` (user ids), null with the Workspace off.
- The step's PR adds its line to the plan's steps file and corrects the screens file's word "assignee".

## Tests?
- Unit: the batch reader on rows with none, one and three open follow-ups, a done task left out, the Workspace off.
