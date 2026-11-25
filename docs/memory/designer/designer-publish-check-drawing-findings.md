---
name: designer-publish-check-drawing-findings
description: Publish check drawing, boards P5 to P7 (step PC5): "Fix", "Show me", "Ignore on this page" with its reason, the Weight group with its budget bars and the list of unused rules
---

## Which button does a finding have?
- One: "Fix" when one press can put it right, otherwise "Show me". Never both. Both are small quiet buttons at the end of the card.
- The drawing gives "Fix" only to what needs no choice: a picture without width and height, an empty heading, a left-over value of a closed component field.

## What happens after "Fix"?
- The card turns into a Result line in its place: a green check, "Fixed: the picture got its own width and height." and "Undo". The group's count and the row's result drop at once; a page with nothing left turns "Ready".
- The fix is one undo step of the page and is saved by the same Publish. Ctrl+Z undoes it while the sheet is open.

## What does "Show me" do?
- It closes the sheet and selects the element as the path does, and opens the place where the change is made: the element's option, or the page's settings for a finding of the page.
- The line over the canvas with "Back to Publish" is the same as after a press on the path.

## How is "Ignore on this page" drawn?
- A text link at the end of the path's line, on every finding that is not blocking.
- A press opens one required field inside the card, label "Why is it ignored on this page? The reason is kept with the page.", 500 characters, with a counter, and under it "Ignore" and "Cancel". "Ignore" is off while the field is empty. Escape closes only the field.
- An ignored finding is not counted. It stays under a fold "Ignored on this page (n)" at the end of its group: a dimmed card with an eye-slash mark, the sentence, the path, the line "Ignored: “reason”" and "Stop ignoring".
- The ignored list is part of the page's draft: saved by the same Publish or by "Save this page as a draft".

## How is the Weight group drawn?
- Its head shows the count and, at the right end, the page's total ("2.3 MB in all").
- First a card of four rows: Markup, Styles, Scripts, Pictures of the first screen. Each row: the name, a bar, and "262 KB of 200 KB" (measured, then the budget).
- The bar has a mark at the budget, two thirds along. Under the mark the bar is green, up to one and a half times the budget amber, above it red. One line under the rows says this in words.
- A measure over its budget counts as one finding "to look at". Weight never blocks.
- Then the weight findings as cards: "The picture is 2,400 pixels wide and drawn at 600." ("Show me"), "The picture has no width and height." ("Fix"), "2 scripts hold the first paint: slider.js, map.js." ("Show me").
- The figures and the budget's numbers in the drawing are samples; step PC2 sets the budget.

## How are the rules no page uses drawn?
- One folded list at the end of the Weight group, head "Rules no page of the design uses" with "3 rules · 1.2 KB". Each line: the rule's selector in a fixed-width face and its stylesheet's name.
- The list ends with "Listed for you to look at; nothing is removed by itself." It has no button.
- It is about the whole design, so it is the same list on every page's row.

## What does the keyboard do?
- Enter on "Fix" fixes and moves the focus to "Undo". Enter on "Ignore on this page" opens the field with the cursor in it; Enter in the field ignores.
- A weight bar is read as text: "Scripts: 262 KB of 200 KB, over the budget".
