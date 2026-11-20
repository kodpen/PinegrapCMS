---
name: walk-in-queue-screens
description: Plan walk-in-queue, how its screens are put together (WQ2 to WQ7): the places list, a place in tabs with Set-up as folded rows, the desk, the person's page, the lobby and kiosk
---

## Which screens, and by which rules?
- WQ2: Calendars › Queue (places), a place's screen. WQ3 and WQ5: the number page and the person's page. WQ4: the desk. WQ6: lobby and kiosk. WQ7: the Report tab.
- `ui/screen-composition-rules.md`; parts are copied from the Design examples screen, its section named in brackets (`workflow/screen-described-in-plan.md`); a part it lacks is asked of the architect, never made.

## How is the places list laid out? (WQ2)
- A list on the list table driver: name, services, desks open of all, waiting now, a pill [Segmented control and status]: Open, Closed, Paused.
- One fill "Add place" [Buttons]: a small drawer asks only the name and the first service. Row menu: Open, "Print the QR poster", Delete (danger, only with no number today).

## How is a place's screen laid out? (WQ2)
- Head: name, pill, one fill following the state, "Open the queue" or "Close the queue". Tabs [Segmented control and status]: "Set-up", "Desks", "Today" (WQ4), "Report" (WQ7), each shown from its step.
- Set-up as folded rows, one open at a time (the settings pane of `plans/design-examples-patterns.md` DX1; before it lands, [Cards]), each with one line of what is set:
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
- Top: the number now served large [Type and text], its service, the name if given, a priority reason, minutes since the call. "Call next" the fill [Buttons]; "Call again", "Not here", "Send to another service", "Finish" quiet; "Pause" and "Close desk" in the menu.
- Under it the waiting as rows [Rows and gathered rows]: number, service, minutes waited, pills Priority, Booked, On the way. One column on a phone.

## What does the person see? (WQ3, WQ5)
- A page in the site's own look, not the panel: the place's name, the services as large choices, name and phone optional, "Take a number". Then their page: the number large, "N ahead", "about M minutes", the desk when called, "Leave the queue" quiet. Remote join: the same with "I'm on my way".

## What do the lobby and kiosk show? (WQ6)
- Lobby: no menu, the whole screen: now serving (number and desk) large, the last five calls, a chime. Kiosk: the number page at tablet size, back to the start after a minute idle.
- Parts the Design examples screen lacks, asked of the architect: the lobby board, the kiosk's large service choices, the desk's now-serving block.

## Which words?
- "Place", "Desk", "Number", "Waiting", "Now serving", "Not here", "about"; never "ticket", "token" or a column's name.

Related: docs/memory/plans/walk-in-queue.md, docs/memory/plans/walk-in-queue-steps.md
