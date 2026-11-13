---
name: panel-design-language-look
description: Look A's numbers (second pass) for the panel's design language: type, surfaces and depth, fields, buttons, the segmented control, the status pill, the icon tile and tones, rows, what a title says
---

## Where do these numbers come from?
- From the sheet the owner approved (#8842, its second pass #8860): `plans/panel-design-language/`; `panel-design-language-sheet.md` lists its pages. `sheet.css` holds every token and shared part; a page's own style block only places things. The pictures are what counts.
- DL1 turns them into `--pg-*` tokens and classes; where a Bootstrap 6 token gives the same pixel, the token is used.

## What is the type?
- Inter for text, its Display cut for headings (letter spacing -0.03em at 24 px, -0.015em at 17 px). Text 14 px / 1.45; second text and buttons 13 px; time, place, help and table headings 12 px; a count 11 px.
- Screen title 24 / 650; section or card title 17 / 650 (15 / 650 in the status column); row title and label 550. Weights 400, 500, 550, 650; nothing at 700 or above. Figures in a column are tabular.

## What are surfaces and depth?
- The page: its colour with two faint glows at the top (the accent, violet).
- A card: a top-to-bottom gradient of two near surface colours, a 1 px line, a light line inside its top edge, a soft drop shadow; corners 16 px (20 px for a drawer or a sheet).
- An inner card (settings rows): a faint tint with an inset line, corners 16 px, rows split by a line.
- A floating bar (the save bar): a translucent surface with a 16 px blur, corners 16 px, a stronger shadow.

## What is a field?
- 36 px high (32 px inside a row), corners 10 px, inset: the field colour, a 1 px line, an inner shadow. Focus: the accent as its line and a 4 px ring of it at 24%. Error: a rose line, a 4 px ring at 16%, the message under it in rose at 12 px.
- Label above, 13 / 550, 6 px gap; help under, 12 px in the third text colour; a unit or a counter at the field's right end.
- Switch 38 by 22 px, on is the accent gradient. Checkbox 17 px, corners 6 px.

## Which buttons are there?
- Filled: the accent gradient with a light top edge and a soft glow under it; at most one in a view.
- Quiet: the text colour at 6% with an inset line. Ghost: text only, in the second text colour. Danger: rose on rose at 12% with a rose line.
- 34 px high, corners 10 px, 13 / 550; small 28 px, corners 8 px. Icon button 32 px (28 px small), corners 9 px; the chosen or hovered one gets the quiet fill.

## What are the segmented control, the status pill and the icon tile?
- Segmented control (in place of filter chips): a quiet track with 3 px padding, corners 11 px; segments 26 px high, 13 / 500; the chosen one is a raised thumb; a count in the third text colour.
- Status pill: 22 px high, round, 12 / 550, a 6 px dot with a halo, the tone as a faint fill with a line of it. Paid and live teal, waiting amber, on the way blue, cancelled and out of stock rose, draft gray.
- Icon tile: 28 px (34 px in a settings row), corners 9 px (11 px), a 14 px glyph in the tone on a diagonal gradient of the tone (26% to 11% dark, 17% to 8% light) with a line of it. A person is a round avatar.
- A tone names the module: team blue, shop teal, content rose, accounting and warnings amber, reports violet, system gray. A new module gets its tone in DL1's list, never a colour of its own in a screen.

## What is a row, and how are rows gathered?
- The bell's row (`bell-drawer.html`): tile, a title on one line with the time at its right, at most two lines of text, a button only when the row asks for something, the unread dot at the right; a read row is dimmed; hover shows the quick actions.
- Rows of one kind: one row with the kind as its title and the count beside it; "Show N …" opens the single lines, "Hide" closes them.

## What does a title say?
- What happened and with what, in a few words: "New order #SP-1044". No greeting, no label with a colon. A state is told by the tile, the pill and the text, never by the title's colour.
