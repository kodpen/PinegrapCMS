---
name: designer-shared-marks-drawing
description: The drawing of shared components and system widgets on the canvas, boards Q1 to Q4: nothing at rest, the frame and tag under the pointer and when chosen, the View switch "Shared parts"
---

## Where is the sheet?
- `designer/designer-shared-marks-drawing/shared-marks.html` with `drawing.css`, pictures `shared-marks-dark.jpg` and `shared-marks-light.jpg`. It is step SM1 of `plans/designer-shared-marks.md`; where a plan line differs, the drawing wins (`workflow/screen-follows-drawing.md`).
- Why it was drawn: `workflow/decisions-made-product-111.md` (owner, 2026-11-25).

## What is drawn at rest (Q1)?
- Nothing. The green ring (3 px outside, 3 px inside, on the wrapper and each direct child) and the system widget's 2 px indigo border go: no ring, border, tint or tag while a part is neither under the pointer nor chosen.
- The canvas has the site's sizes: the border made a widget 4 px wider and taller.
- Kept: the dashed stand-in of a component that is empty, loading or a loop's template; the frame Outline draws; green `#10b981` for shared, indigo `#6366f1` for system, blue for an element of the page.

## What shows under the pointer and when chosen (Q2)?
- The pointer on a shared component: a 1.5 px frame in green on its box and a green name tag, the puzzle icon, the name, "Shared" lighter ("Header · Shared"). On a system widget the same in indigo, the chip icon, "System". These replace "[Shared] Header" and "[System] Cart".
- Only the innermost part under the pointer gets a tag. A shared component or widget the pointer is inside of, but not on, shows a 1 px line of its colour.
- Chosen: a 2 px frame in the part's colour and the tag with the grip. Around a chosen element of a component the component's 1 px line stays.
- The tag's place follows an element's tag today: above the frame, inside the top edge when the part touches the canvas's top, under a small part in a bar. It never pushes anything and never covers its part.
- A frame takes no room, also where the wrapper has no box of its own (a button group, a menu, an input group): there it goes on the child.
- The warning at the first change inside a shared component stays as it is, once per component and visit. A content editor gets the same frame and tag, no grip.

## What is "Shared parts" (Q3)?
- A switch row with the puzzle icon, second in the canvas bar's View menu under "Outline", counted in the menu's badge; in the command palette "Show shared parts", and "Hide shared parts" while it is on. No button, no key.
- On: every shared component wears a soft green wash, a 1 px line and its tag inside its top left corner; every system widget a soft indigo wash and a 1 px line, its tag only under the pointer. No 3 px ring, nothing doubled on children, no size changed; the frames of Q2 work the same over it.
- Off at first for everyone; remembered per person and browser where the other View switches are kept.
- Design mode's canvas only: not Preview, not a picture of the page, not Prototype mode.

## What is not touched (Q4)?
- The Overview's rows, the palette's "Shared" and "System" tabs and the Options panel keep their colours and icons: they are the designer's own parts and draw nothing on the page.
