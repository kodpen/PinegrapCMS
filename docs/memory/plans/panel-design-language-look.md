---
name: panel-design-language-look
description: Look A's numbers for the panel's design language: type scale and weights, the row, the icon tile and its tones, button levels, chips, gathered rows, read and unread, what a title says
---

## Where do these numbers come from?
- From the drawing the owner chose (#8842): `plans/panel-design-language/bell-drawer.jpg`. `bell-drawer.html` beside it is the drawing's source and holds every value (open it with Inter installed); the picture is what counts.
- DL1 of `panel-design-language-steps.md` turns them into `--pg-*` tokens and classes; where a Bootstrap 6 token gives the same pixel, the token is used.

## What is the type?
- Inter. In a row: 14 px / 1.45 for the title, 13 px for the text under it, 12 px for time and place. A drawer's heading 16 px / 650; a count 11 px / 600.
- Weights: 400 text, 500 chips, 550 titles and buttons, 650 headings. Nothing at 700 or above.
- Figures that stand in a column are tabular.

## What is a row?
- Padding 10 px 12 px, 8 px from the drawer's edge, corners 12 px, gap 11 px; no line between rows; hover is a faint fill (the text colour at 5%).
- Left the icon tile; then the title on one line with the time at its right; under it at most two lines of text in the second text colour; then, only when the row asks for something, a line with its button and the place.
- At the right edge the unread dot: 8 px, the accent. A read row has its title at 450 in the second text colour and a dimmed tile.
- Hover shows the row's quick actions (Mark as read, More actions) in place of the time; on touch they stay in the row's menu.

## What is the icon tile?
- 28 px, corners 8 px, a 14 px glyph in the tone's colour on that colour at 16% (dark) or 11% (light). A person is a round avatar of the same size.
- A tone names the module: team blue, shop teal, content rose, accounting and warnings amber, system gray. A new module gets its tone in DL1's list, never a colour of its own in a screen.

## Which buttons are there?
- Filled (the accent): at most one in a view, the answer the person is asked for.
- Quiet (the text colour at 8%, no border): every other row action; 28 px high, corners 8 px, 13 px / 550.
- Icon button: 32 px, corners 8 px, no border; a heading's actions.
- No bordered button in a row.

## What are chips, counts and day labels?
- Filter chip: 28 px high, round; the chosen one filled with the text colour at 10%, the others bare; its count in the third text colour.
- Count beside a title: 11 px / 600 on the text colour at 9%, round.
- Day label: 12 px / 550 in the third text colour, sentence case, no line.

## How are rows of one kind gathered?
- One row: the kind as its title with the count beside it, the sum or the newest fact as its text. "Show N …" opens the single lines under it (a 2 px line at the left, 13 px, the value at the right); "Hide" closes them.

## What does a title say?
- What happened and with what, in a few words: "New order #SP-1044", "Ayşe Demir mentioned you". No greeting, no "Congratulations", no label with a colon ("Order number: …").
- A state is told by the tile and the text, never by the title's colour.
