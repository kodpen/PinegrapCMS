---
name: designer-compare-live-drawing
description: The drawing of "Compare with the live page" (plan designer-compare-live, CL2 and CL3): boards L1 what opens it, L2 the Look tab's slider, L3 side by side and phone, L6 from History
---

## Where is the drawing?
- `designer/designer-compare-live-drawing/compare-live.html` on `ui/look-sheet/sheet.css`, with `compare-live-dark.jpg` and `-light.jpg`. Six boards: L1 to L3 and L6's History part here; L4 "What changed", L5 the states and L6's narrow window in `designer-compare-live-list-drawing.md`.
- CL2 builds L2, L3, L5 and L6; CL3 builds L1, L4 and the list's part of L5 and L6. The list's sentences are the reader's (`designer-compare-changes.md`).

## What opens it? (L1, step CL3)
- The page's tab menu: "Compare with the live page" after "Preview as prototype", for every page of the design. The command palette has the same row for the page that is open. No button in the page bar or the canvas bar, no key (`designer-light-and-simple.md`).
- The Publish sheet: an icon button after "Changed" on the rows of pages that are on the site and have changed; its tip and its name for a screen reader are "Compare with the live page". A draft's row has none. The window opens over the sheet; closing it gives the sheet back with its checks kept.
- History: "Compare" on a revision's row opens the same window (L6).
- Whoever may open the page in the designer has it, view mode too: nothing is written.

## What is the window? (L2, step CL2)
- History's Compare window (`page-revisions-history-panel.md`): its head, tabs and foot. The tab "Preview" becomes "Look" and stands first; "Code" is the markup tab as it is. The two frames of "Preview" are the Look tab's "Side by side": no third tab.
- Head: "Compare with the live page" and the page's name. Foot: the count in one sentence ("7 changes between the page on the site and the draft.") and "Close".
- The line over the pictures: the left side's name, "Slider" / "Side by side", "Desktop" (1280 px) / "Phone" (390 px), the right side's name. Each name says in grey what it is: "On the site · since 12 Nov, 14:02", "Draft · as it is in the designer now".
- "What changed" is a 300 px column on the right.

## How does the slider work? (L2)
- Both pictures are the whole page, one over the other, scaled to the room. The handle is a white line with a round grip: left of it the page on the site, right of it the draft. It starts in the middle.
- The handle is dragged, or moved with the arrow keys once it has focus (a ring round the grip); Home and End put it at an end; a click on a picture puts it there.
- Scrolling moves both pictures as one. Where one page is shorter, the canvas's backdrop shows under its end.

## What are "Side by side" and "Phone"? (L3)
- Side by side: the two pictures next to each other, each under its name, the page on the site left; they scroll as one, no handle.
- Phone: both pictures drawn 390 px wide, in the middle of the room; the slider and side by side work the same.
- The form and the width chosen last are remembered in the browser. The handle's place is not.

## How is it opened from History? (L6)
- The head says the revision, who, when and how, as today; the foot keeps "Load this revision", which loads the left side. Each side's name is a chooser: "The page now", "The page on the site", or any revision of the page.
- The list and the pictures work for any two versions; equal sides say "Nothing has changed".

## What does the drawing ask of the frames?
- Architect (2026-11-25): the pictures stay frames that run no script (`design-live-pictures.md`), but allow the panel's own origin (`sandbox="allow-same-origin"`, never `allow-scripts`), so the window can read a page's height and an element's box. Scrolling both as one, going to a place and the outline are the window's own layer over the frames; nothing is drawn inside a page.
- The compare's render must let the window find an element by its tree id; CL2 uses what the render already prints and adds an attribute only to this render, never to the site's page.
