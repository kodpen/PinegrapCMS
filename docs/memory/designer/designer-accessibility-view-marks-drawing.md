---
name: designer-accessibility-view-marks-drawing
description: The drawing of the accessibility view's Headings and Names marks, a fault's card and the "See as" sight filters: boards V3, V4 and V5 of the accessibility view sheet
---

## Which boards are these?
- V3 Headings, V4 Names and V5 "See as" of `designer/designer-accessibility-view-drawing/accessibility-view.html`. The View row, the card, Tab order and the other states: `designer-accessibility-view-drawing.md`.

## How are headings drawn? (V3, step AV2)
- A tag "H1" to "H6" stands above the heading's first line and the heading is framed. A heading without a level of its own (`role="heading"`) reads "H2 · assumed" and is never marked.
- A skipped level is amber and dashed and reads "H4 · skips H3", on the first heading that skips only, as the audit counts it.
- The card's body is the outline: a row per heading, indented by its level, cut with an ellipsis; the marked heading's row carries "skips H3". A row chooses its heading and scrolls the canvas to it. Past eight rows the list scrolls inside the card.
- A page with no heading: no mark, and the card says "This page has no heading." in the outline's place.

## What does a marked place open? (V3)
- A click on an amber mark chooses the element and opens the fault's card beside it: the audit's own sentence for that rule, "Fix" where the audit has one, and "Show in the audit", which opens the audit panel at that row.
- Without "Fix" (board V6): where the audit has none for that rule, and for a person who may not change the element. The sentence and "Show in the audit" stay.
- "Fix" is the audit's: the same action, the same undo step. The view adds no fix and no rule of its own. The faults it can show are the reader's six: `a11y_heading_order`, `a11y_img_alt`, `a11y_link_name`, `a11y_button_name`, `a11y_form_label`, `a11y_frame_title`.

## How are names drawn? (V4, step AV2)
- A region is outlined with a dashed line and tagged with its role and, where it has one, its name: "Banner", "Navigation “Main menu”", "Main", "Form “Newsletter”", "Content info". The roles are the reader's; a form or a region without a name is none.
- A picture carries a chip at its bottom left: its text in quotes, cut at the picture's width; "decoration" in italics where it is marked as one; amber "no text" where it has none.
- A control is marked only when it has no name: an amber dashed frame and "Button · no name", "Link · no name", "Field · no label", "Frame · no title". A named control shows its name as a tip under the pointer.
- The tags never cover each other: a region's tag is inside its top left corner, the navigation's above its frame, "Main" in its top right corner, a control's under it.
- The card's foot: "5 regions, 4 pictures. Three places have no name." The words are the designer's, translated; a name is shown as the page has it.

## What is "See as"? (V5, step AV3)
- The values: Off, Red-weak, Green-weak, Blue-weak, No colour, Blurred, one at a time. The two common ones carry how many people see so, in grey ("1 in 100", "5 in 100").
- While a filter is on a pill stands at the foot of the canvas: "Seen as: green-weak" and "Turn off". Nobody mistakes a filtered canvas for the design.
- It filters the page only: the canvas's frame, the marks, the card and every panel keep their colours. It works with any kind of mark, and with none.
- Style only: a colour matrix or a blur on the canvas's page. No picture is read, nothing enters the tree, the Preview and the saved design never have it.
- Never kept: the filter goes off with the view, on a reload and when the designer is opened again.
- Dark canvas and reduced motion are untouched: the filter is laid over whatever the canvas shows.

## What is only an example?
- The sample site "Still Water Yoga", its counts (15 stops, 6 headings, 9 names) and the sentences standing for the audit's.
