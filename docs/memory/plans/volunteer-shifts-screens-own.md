---
name: volunteer-shifts-screens-own
description: Plan volunteer-shifts, the volunteer's own pages (VS4, VS6, VS7) in the site's look and the scan page on a phone; the words the screens use
---

## What does a volunteer see? (VS4, VS7)
- In the site's own look: "My volunteering": my next shifts with "Cancel" (or "Offer my place" past the cut-off), "Shifts for you" with "Sign up" or "Join the waitlist", my hours and "Get my certificate", my documents with their dates and "Upload a new one" (a PDF or an image; the file opens only for the volunteer and the coordinators), and a switch "Shift calls" with the channel chosen.
- The signed-link page shows the same, for that volunteer only.

## How does the scan page work? (VS6)
- On a phone: the shift's name and one large button, "Check in" or "Check out", then the time. The scan button and the camera box are `event_check_in.php`'s (architect, 2026-11-20, agent channel message 11146).

## Which words?
- "Programme", "Shift", "Role", "Sign up", "Waitlist", "Did not come", "Hours"; never "slot", "token" or a column's name.

## What must be true?
- "My volunteering" and the signed page list only the volunteer's own shifts, hours and documents.
- With "Shift calls" off, no call for an unfilled shift reaches that volunteer; the day-before reminder still does.
- The scan page has one button, and it reads "Check out" only after a check-in.

Related: docs/memory/plans/volunteer-shifts-screens.md, docs/memory/plans/volunteer-shifts-steps.md
