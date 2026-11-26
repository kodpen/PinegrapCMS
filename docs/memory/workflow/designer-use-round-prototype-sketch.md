---
name: designer-use-round-prototype-sketch
description: Use round UR3 (PR #11884): the Prototype view's Sketch tool used in a browser as a drawing board; the eight fixes, what was left and what could not be run
---

## What was used, and how?
- A `setup_sandbox.sh` instance, Chromium at 1440 × 900 and 390 × 844 (touch), the panel light and dark (the chrome is dark in both), and once by the keyboard alone (Tab, Shift+Tab, Enter, Space, letters, the chip menu's arrows).
- Empty design: the empty page's "Sketch it", "New page", seven boxes drawn, headings typed, kinds chosen, "Page details" saved, "Build the page", a box deleted and "Build again", "More" › "Reference picture" (the chooser opened).
- Ten pages and more: the Bakery template (60 tabs, 138 frames) for "New page", the zoom and Esc. `node tests/js/sketch_feel.js keys`: 27 PASS after the fixes.

## What was found and fixed?
- "New page" centred a frame taller and wider than the view: its head and left edge were hidden. It now zooms to the frame as Enter does; Esc or "All" goes back to the map.
- The empty page's "Sketch it" opened the map with no sketch page to draw in; on a design read slowly the frame landed over the page's frame. It now waits for the map's first drawing and makes a page.
- A drawn box's pressed corner did not line up, only the dragged one: three cards drawn side by side started at 17, 170, 324. Test first (`test_designer_sketch_rect`).
- The Sketch windows' and the frame's buttons used Bootstrap's own colours: a pale blue Save with dark text beside a black Cancel. On `--sd-accent-strong` / `--sd-raised-bg-2`, dimmed while off.
- Shift+Tab from the map reached the hidden empty-page cards and the Design canvas behind the view; Enter ran them. What the view lies over is `inert` while it is open.
- Enter on a frame reached by Tab zoomed to it but left the other frame selected, so it was drawn dimmed. Enter selects it first.
- After the reader ran, a box still told a screen reader "Not read yet". Its name follows its chip.
- The zoom's crumb kept "Page 1" after "Page details" renamed the page.

## What was left, and why?
- No box can be drawn by the keyboard alone: drawing needs a pointer. A new way to draw is the architect's (asked in the PR).
- With a sketch page selected the links panel said "Select a page to see its links."; since SX1 (#11907) it says "A sketch page has no links until it is built."
- At 390 px a frame zoomed to is at 48 %: the chips and the head's "Build the page" are about 6 px text; two fingers zoom in.
- Each build on a new design makes a new shared "Header [n]" / "Footer [n]" (names are site-wide): "Build again" lists "Footer [2]".
- An unsaved open tab is "Untitled page" on the map while its tab says "New Page".
- The empty band of the context bar over the map is the Design canvas's (not this feature's).

## What could not be run?
- The AI rows of "More" (no key) and "Read the picture" (no picture placed: the chooser was opened, none picked).
- Timing (`sketch-feel.md`) was not measured again: no fix runs while a pointer moves.
