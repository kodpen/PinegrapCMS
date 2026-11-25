---
name: designer-compare-live-list-drawing
description: The drawing of Compare's "What changed" list joined to the two pictures, the window's states and its narrow form: boards L4, L5 and L6 of the compare-live sheet
---

## Which boards are these?
- L4 "What changed", L5 the states and the narrow part of L6 of `designer/designer-compare-live-drawing/compare-live.html`. What opens the window, the Look tab, the slider and History: `designer-compare-live-drawing.md`.

## What is a row? (L4, step CL3)
- The reader's row (`designer-compare-changes.md`, `rows()`), most visible first: the kind's icon, the sentence and, in grey, its place ("in Section: Welcome"). The kind is said by the words, never by a colour. The count beside "What changed" is the number of rows.
- Icons by kind: added `bi-plus-circle`, removed `bi-dash-circle`, picture `bi-image`, words `bi-fonts`, moved `bi-arrow-up` / `bi-arrow-down`, link `bi-link-45deg`, look `bi-palette`, other `bi-three-dots`.

## What does choosing a row do? (L4)
- It scrolls the pictures to the place and outlines it in blue, with the row's sentence as the outline's tag. Each picture is scrolled to its own place of the element, so the element stands at the same height in both.
- A place on one side only: for an added element the handle goes to the left end so the draft shows, for a removed one to the right end so the page on the site shows. In "Side by side" only that picture carries the outline.
- A row of a text opens when chosen and shows the old and the new words under "Before" and "Now", as plain text, cut after six lines.
- A row of several elements ("Look changed (3 elements)", "Other changes (1)") outlines them all and opens into their names ("Text “Full”"); a name goes to its element.
- Nothing is left out: a change the reader cannot name is counted under "Other changes". A row whose element is not found in a picture still shows in the list and outlines nothing.

## Which states are drawn? (L5)
- Opening: the page on the site is drawn first, under a quiet shimmer and "Drawing the page on the site…"; the draft is drawn once the first is seen ("The draft is drawn next"). The list is there at once: it is read from the two trees and does not wait for the pictures. Nothing is drawn before the window opens.
- A page never on the site (the reader's `new`): a line over the picture, "This page is not on the site yet. This is the draft alone.", the draft alone, no handle and no "Side by side"; the width chooser stays. The list's place says "The whole page is new."
- Nothing to compare (`same`): one picture, no handle, and "Nothing has changed. The draft is the same as the page on the site."
- A picture that has not come after 12 seconds gives its half to "The page on the site could not be drawn." and "Try again", which draws that picture only. The list stays.
- A list that could not be read (`unreadable`): "The changes could not be listed. The two pictures are still the two pages."
- The Publish sheet's icon is not offered for a page never on the site or one that has not changed.

## What does a narrow window look like? (L6)
- Under 768 px the window fills the screen: head "Compare" with the two tabs, a line with the two sides' names, the slider alone drawn at a phone's width. No "Side by side", no width chooser.
- The foot's "What changed · 7" swaps the pictures for the list; "The pictures" swaps back. A row chosen in the list swaps back and shows its place.

## What is only an example?
- The sample site "Still Water Yoga", its seven rows, the places' names, the dates and the person. The sentences are the reader's `TEXT`; the screen's own words are the ones in quotes here, translated through `_sdT()`.
