---
name: designer-preview-as-drawing-choosers
description: The "Record", "Visitor" and "Case" menus of the "Preview as" bar as drawn (boards P2 to P4 of designer-preview-as-drawing): rows, labels, states, what a case sets
---

## What is in the "Record" menu?
- 320px wide: a search field with the focus in it ("Search by name"), a caption naming whose records these are (as "Products of “Chairs”"), then the rows. A row is the record's name with one grey detail at its right: a product's price, a submission's date, an event's day.
- The ticked row is the one drawn now. The record today's Preview draws carries the grey note "shown by default"; choosing it is the same as "Reset" for this chooser.
- The first rows come without typing, with a grey foot as "5 of 214. Type to find the others." Typing searches by name after a short pause: "Searching…", then what was found.
- Nothing found: "No record matches “marble”." A record the person may not open on its own panel screen is not listed and not counted, so the answer is this same line.
- A widget with no record yet: the chooser reads "No record" and its menu one line, as "“Offer form” has no submission yet."; the page is drawn as today.
- More than one single-record widget: a segmented row at the top names the widgets (up to three; more become a select in the same place) and the chooser shows the first record and "+1".
- Keyboard: typing goes to the search; Down and Up move, Enter chooses and closes, Escape closes; Left and Right change the widget in the segmented row.

## What is in the "Visitor" menu?
- The chooser stands only on a page with an access rule or an audience condition; a page with neither is drawn the same signed in or out.
- "Signed out" (today's Preview) and "Signed in" with the second line "Alex Morgan, the sample account": the canvas's sample account, in no group.
- Under the caption "Member of a group": only the groups the page's access rules name; the sample account is drawn as a member of the chosen one. A page with no access rule has no such part.
- Under the caption "Visit": "First visit" (as today) and "Returning", only where the page has audience conditions. It is a second choice beside the rows above, so the menu stays open after it; the chooser then reads as "Signed out, returning".
- While the visitor is signed in or a member, a grey line with the lock: "The address, e-mail and phone of a real customer are masked."

## What is in the "Case" menu?
- Two-line rows, only the cases a widget of the page has: "Typical" (The page as most visitors see it), "Empty" (A list with no record, an empty cart, a search with no result), "Long" (The longest title and text among the records), "Sold out" (A product that cannot be bought), "Error" (A form sent with a required field missing).
- Under a rule, the last row: "Check all cases" with the list mark (`designer-preview-as-drawing-check.md`).
- A choice closes the menu and the page is drawn again at once. The texts of the page are the site's own: the case only decides what the widgets are given.

## What does a case set?
- "Long" and "Empty" choose the record themselves: "Record" shows what is drawn and is dimmed until the case is "Typical" again. "Visitor" stays free.
- A form page has "Typical" and "Error" only; a page with "Typical" alone has no "Case" chooser.
