---
name: walk-in-queue-screens
description: Plan walk-in-queue, how its panel screens are put together (WQ2, WQ4, WQ7): the places list, a place in tabs with Set-up as folded rows, desks, the desk's now-calling block
---

## Which screens, and by which rules?
- WQ2: Calendars › Queue (places), a place's screen. WQ4: the desk. WQ7: the Report tab. The person's page, lobby and kiosk: `walk-in-queue-screens-public.md`.
- `ui/screen-composition-rules.md`; parts are copied from the Design examples screen, its section named in brackets (`workflow/screen-described-in-plan.md`). The parts it lacks were answered by the architect (2026-11-20, agent channel message 11146) and are named here with their classes.
- A screen step builds a whole part (a screen with its drawer), never structure first and content later. Its worker renders it at 1440 and 390 px before ready and writes "Screen check" in the PR.

## How is the places list laid out? (WQ2)
- A list on the list table driver: name, services, desks open of all, waiting now, a pill [Segmented control and status]: Open, Closed, Paused.
- One fill "Add place" [Buttons]: a small drawer asks only the name and the first service. Row menu: Open, "Print the QR poster", Delete (danger, only with no number today).

## How is a place's screen laid out? (WQ2)
- Head: name, pill, one fill following the state, "Open the queue" or "Close the queue". Tabs [Segmented control and status]: "Set-up", "Desks", "Today" (WQ4), "Report" (WQ7), each shown from its step.
- Set-up as folded rows, one open at a time [Settings pane], each with one line of what is set:
  1. "Services": a row each [Rows and gathered rows], its letter and the minutes to start the estimate with; "Add a service", each with its remove.
  2. "Hours": the week as on a Bookings staff member, days off.
  3. "Taking a number": QR, site page, kiosk switches [Fields]; party size; daily cap; the closed text.
  4. "Notices": SMS, WhatsApp, e-mail; "Tell when N are ahead"; the three texts as tabs.
  5. "Order": priority groups; the Bookings staff folded in, the grace minutes; "Not here after M minutes"; moved back once.
  6. "Keep personal data": days.
- Foot: Save as the fill, Cancel quiet; a row missing values carries a "Not set" pill, and "Open the queue" lists what is missing.
- Desks tab: a desk a row [Rows and gathered rows]: name, services, who has it open. "Add desk" is the fill; Remove in the row (danger, confirm).

## How does a desk work? (WQ4)
- From Today, or "My desk" in the menu for a place's staff member: "Open this desk" first.
- Top, the now-calling block: one `.pg-card`, the number in `.pg-figure-value`, its service and desk in `.pg-figure-label`, the name if given, a priority reason, minutes since the call. Its buttons in one row under it: "Call next" the fill [Buttons]; "Call again", "Not here", "Send to another service", "Finish" quiet; "Pause" and "Close desk" in the menu.
- Under it the waiting as rows [Rows and gathered rows]: number, service, minutes waited, pills Priority, Booked, On the way. One column on a phone.

## Which words?
- "Place", "Desk", "Number", "Waiting", "Now serving", "Not here", "about"; never "ticket", "token" or a column's name.

## What must be true?
- The places list, a place's head and the desk have one fill each.
- Set-up has six folded rows, one open at a time; a row missing values carries "Not set".
- The desk's buttons stay in one row at 1440; at 390 the waiting rows are one column.

Related: docs/memory/plans/walk-in-queue.md, docs/memory/plans/walk-in-queue-steps.md, docs/memory/plans/walk-in-queue-screens-public.md
