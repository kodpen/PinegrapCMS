---
name: designer-image-focus-drawing
description: The drawing of the image focus (plans/designer-image-focus.md, IF2 and IF3): the sheet, its four boards, what each step builds from it and what the sheet settles
---

## Where is the drawing?
- `designer/designer-image-focus-drawing/`: `image-focus.html` with a `-dark.jpg` and a `-light.jpg` picture; `drawing.css` sits on top of `ui/look-sheet/sheet.css`. The designer's own parts are dark in both pictures.
- The sample is an Image of the page "About us" with a drawn photograph and the focus 70% · 32%. Names and numbers are examples; the labels are the steps'. The value itself: `image-focus-value.md`.

## Which board belongs to which step?
- IF2: F1 "Focus" in an Image's Options panel (Content tab, section "Image", under "Fit"): the whole picture with one point and three frames (phone, tablet, desktop); no focus set, set, while dragged. F2 a phone's own focus, a value typed before, a card bound to records, a background picture in "Color and background".
- IF3: F3 the button "Set focus" in the selected element's toolbar and the mode it starts; F4 a section's background picture in the mode, and the canvas at 375.

## What does the drawing add to the designer's bars and menus?
- No button in the page bar, the canvas bar or a panel's head (`designer-light-and-simple.md`). One control in the Options panel, and one icon button with the tooltip "Set focus" in the toolbar the selected element already has.
- Both show only where a picture is cut: an Image with a picture, and a background whose size is "Cover" or whose picture is larger than its box. No picture, or a repeated pattern: neither is drawn.

## What does the sheet settle that the plan left open?
- One press on the whole picture puts the point there; a drag moves it. The canvas and the three frames follow, and the whole move is one undo step.
- Keys on the focused point: the arrows move it by 1%, Home puts it in the middle. The value beside "Focus" is read out as it changes.
- "Reset" removes the focus: the element draws as before one was set. With none set the point rests in the middle, pale, over the hint "Drag the point onto what must stay in view."
- The frame of the screen the canvas bar shows carries the ring. A focus set while the bar shows a smaller screen belongs to that screen and the smaller ones: a chip names it ("Phone"), a foot line says what wider screens keep, and "Same on every screen" removes those and keeps the widest.
- A value typed by hand before is shown where it points, with "Typed before as center top. It is kept as typed until the point is moved."
- A card bound to records shows the sample record's picture and says the focus is the same for each record's picture.
- The mode: the picture shows whole, dimmed outside its frame, and is dragged under the frame; the element's own content fades. Its line: "Drag the picture under its frame. Arrows move it by 1%.", the value, "Cancel", "Keep". Nothing else can be selected; Enter or a press outside keeps, Escape puts back. One undo step.

## Which states are drawn, and which have no board?
- Empty: F1 (no focus set). Filled: F1, F2. A second value: F2 (a screen's own, a typed one). Narrow: F4, where the line wraps. Busy and error: none, the focus is saved with the design.
- No board: a screen where the picture is not cut says in the mode "This screen shows the whole picture." and has nothing to drag.
- No board: a tablet's own focus is drawn as the phone's, with the chip "Tablet".
- No first-use line beyond the hint under the control.

## Which parts are drawn from what exists, and which are new?
- As they are today: the Options panel's head, tabs, search, section and rows, its info line, the selected element's toolbar and outline, the canvas bar.
- New: the control (the whole picture, the point, the three frames with their screen icons, the chip, the foot line), the toolbar's icon, the mode's dimmed picture, its grab mark and its line.
