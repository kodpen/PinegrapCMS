---
name: bug-hunt-areas-phone-dark-new-screens
description: Bug hunt 15 (2026-11-24): the 2026.11.0 screens at 390 px and in the dark theme on a sandbox; the five faults fixed in #11382, what read clean, what was not reached
---

## What was the area?
- Named by the architect, given by coordinator k1 (2026-11-24): the screens added in 2026.11.0 (volunteers, walk-in queue, pick and pack, Hot Contacts, the launch journey, share links, prototype approval's settings) at 390 px and in the dark theme: nothing wider than the screen, toolbars folded, drawers and dialogs inside the screen with the keyboard open, tables as phone cards, text readable on its ground.
- Run in headless Chromium on a `setup_sandbox.sh` instance, touch emulated, both themes; the keyboard as a 390 × 450 viewport. Records seeded by SQL and the screens' own Add drawers: long names on purpose (a 50-character shift place, a 60-character desk).
- The probe measured the page's scroll width, every element past the right edge outside a scrolling box, each drawer's and dialog's box, and text contrast over its composited ground (a gradient fill skipped).

## What did it fix? (#11382 lists each with file and line)
- Long names widened the screen to 550-600 px (volunteer Shifts tab, queue Desks tab): `min-width-0` is no class; now `min-w-0` everywhere in the panel (`ui/min-w-0-class.md`).
- The queue place's desk and screen rows: an inline `<strong>` title neither wrapped nor shortened; now `d-block`.
- The volunteer Hours tab's QR poster buttons kept a long place on one line (398 px); now `max-w-100` with the name truncated and whole in the tooltip.
- The lobby's "Called before" broke "A-004" after its hyphen beside a long desk name; `text-nowrap` in the page and its 5-second redraw.
- The launch card's "Must be fixed" pill sat inside the one-line step title and its ellipsis hid it on a phone; the pill now stands beside the title in a wrapping head.

## What read clean?
- No page wider than 390, every drawer and dialog inside 390 × 844 and 390 × 450 with its foot on screen, no text under 3:1 in either theme, on these screens:
- Volunteers (list, Shifts, Applications, Volunteers, Hours, Report, Set-up, poster); Calendars › Queue (list, Set-up, Desks, Today, Report), the desk, queue.php, the lobby, the kiosk, the queue poster.
- Pick waves, Pack, Hot Contacts (phone cards, Remind me dialog), the dashboard's launch card, purpose dialog and go-live check, a channel's Sharing in Channel info, Share by link… and its address dialog.
- Toolbars: none of these screens has more than four buttons; they wrap to a second row.

## What was not reached?
- The designer's Share prototype dialog (approval fields): the sandbox has no designer-built design; read in source only (`sm:col-6` fields stack on a phone; its inputs are `form-control-sm`).
- Pick waves with a wave and Pack with an order: no paid unsent order with a shelf address in the sample data; the empty screens were measured.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/ui/min-w-0-class.md
