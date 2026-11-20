---
name: privacy-centre-screens
description: Plan privacy-centre, how the request box's screens are put together (KV4): the Requests view of the Privacy centre, a request's drawer with tabs, "Prepare the answer" as numbered steps
---

## Which screens, and by which rules?
- KV4: the requests list, a request's drawer, "Prepare the answer", the reminders. KV5's public form is site output, not the panel.
- `ui/screen-composition-rules.md`; parts are copied from the Design examples screen, the section named in brackets.

## Where does the list sit?
- Contacts › Privacy centre gains views as link segments [segmented control]: "Inventory" (today's screen, unchanged) and "Requests". A count of open requests sits on the Requests segment.
- Requests is a list on the list table driver: kind, person, received, due, who handles it, status, result (P11). Due is a pill [status]: days left in grey, amber from 7 days, red from 2 days and when overdue.
- The view's one fill: "Add a request" (a request typed in by staff). Filters as segments: Open, Answered, All.
- Reminders have no screen: the bell and e-mail at 7 and 2 days, and one line above the list ("3 requests end within 7 days") that filters it.

## How is a request's drawer laid out?
- Head: the person, the kind, the due pill. Tabs [segmented control]: "Request", "Answer", "History".
- Request: the text, how it came (My Account, staff, public form), the proof's state, who handles it (a select), status.
- History: the request's `audit_log` lines as gathered rows [rows and gathered rows], newest first.
- Foot: Save as the fill; "Refuse" danger with a reason field in its confirm. A request is never deleted here: the 7-day sweep and the retention rule remove it.

## How is "Prepare the answer" laid out?
- The Answer tab, as numbered steps, one open at a time:
  1. "Check who asks": the proof's state; staff ticks that identity is checked.
  2. "Gather": information runs the personal data export, erasure the erase dry run; the result is a label and value list (`ui/look-kv-list.md`), and for an erasure what will go and what the law keeps.
  3. "Write the answer": the e-mail's text from the kind's template, editable; the export attached for information.
  4. "Send": the fill "Send the answer"; for an erasure "Erase and send", danger, after the confirm.
- The sent answer is shown read-only in this tab afterwards.

## Which words?
- One line of help a step. The note that defaults are no legal advice stays once, on Inventory.
