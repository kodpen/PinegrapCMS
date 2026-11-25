---
name: designer-first-tour-drawing
description: The first tour's drawing (DD14), boards T1 to T3 and T7: the first card, the five stops' ring and bubble, a content editor's three stops, the phone's sheet; the sentences
---

## Where is the sheet?
- `designer/designer-first-tour-drawing/first-tour.html` with `drawing.css`, pictures `first-tour-dark.jpg` and `first-tour-light.jpg`. Boards T1 to T3 and T7 are step FT2 of `plans/designer-first-tour.md`; T4 to T6 are FT3 (`designer-first-tour-help-drawing.md`). Where a plan line differs, the drawing wins (`workflow/screen-follows-drawing.md`).
- The window is drawn as a plan at the places measured in a 1440 by 900 px window; the anchors are FT1's (`designer-first-tour-model.md`).

## What is the first card (T1)?
- 320 px, 16 px from the window's right edge and 16 px above the status bar, over everything but a dialog: an icon, "New here? A one-minute tour", a second line ("Five stops on the real buttons." or, for a content editor, "Three stops on what you can change."), "Start", "Not now".
- Shown once the page is drawn, never over a dialog, while typing or dragging; else it waits for the next quiet moment of the visit. Once per person and browser, whatever the answer. "Not now" and Escape close it; the tour stays in the Help window as "Take the tour".
- It is announced and takes no focus (Tab reaches it after the page bar). It fades in over 160 ms, not with reduced motion. The tour's part loads only when the card is due.

## What is a stop (T2)?
- A ring round the real control: 2 px accent, a soft 7 px halo, 3 px outside the box, corners 8 px; it follows the control on resize and when a panel opens or closes, and glides to the next in 200 ms (jumps with reduced motion).
- A bubble of 320 px beside the ring where there is most room, its arrow on the ring, never over the control or outside the window: "Tour · 3 of 5" with dots, the sentence with bold first words, "Skip" left, "Back" and "Next" right; the last stop has "Back" and "Done", the first no "Back".
- Nothing is dimmed or locked: a click on the ringed control does what it always does and the tour stays. The tour waits while a dialog is open. A control not on the screen is skipped and the count follows ("2 of 4").
- "Done", "Skip" and Escape end it; nothing is shown after. Focus goes to the bubble's "Next" at each stop and back where it was at the end; Enter or the right arrow is Next, the left arrow Back. Each stop is read out whole.

## Which sentences?
- Add: "Add something. Everything you can put on a page is here. Drag one onto the page, or click it."
- Shape: "Shape it. Click anything on the page, then change how it looks here."
- See: "See it. Look at the page as a visitor will, on a phone or a wide screen."
- Publish: "Put it on the site. Nothing reaches your site until you press Publish."
- Find: "Find anything. Ctrl+Shift+P finds every command by its name. Help lives under this button."
- A content editor (T3): "What you may change. The framed parts of this page are yours. Click one." (the whole canvas ringed, the bubble inside at its foot); "How. Change its text, picture or link here, or type right on the page."; "How it reaches the site. Press this when you are done. If your site asks for approval, a designer looks first." The third names "this" button, never its label, and holds with or without approval.
- The tour adds no mark to the page: an editor's parts are framed as the designer frames them today.

## What changes at a phone's width (T7)?
- Under 576 px the card and the bubble are a sheet across the window's foot, corners 14 px at the top; the ring stays on the control. When the ringed control is in the window's lower half the sheet stands under the bars at the top.
- The palette and Options are closed there, so the designer's tour has four stops ("1 of 4"); the Help window fills the width with 12 px at each side.
