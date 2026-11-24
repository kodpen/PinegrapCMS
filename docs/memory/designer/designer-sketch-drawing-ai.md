---
name: designer-sketch-drawing-ai
description: The Sketch tool's two AI actions as drawn (ai-and-picture.html, boards E1 to E3 and E6): the "Describe the page" window, the sketch being drawn, "Write the texts" and its messages
---

## How does "Describe the page" open?
- From "More" on a sketch page, with Pinegrap AI on: a small window, head "Describe the page".
- One field, placeholder "What is the page for, and what should be on it?", and under it the line "Your words are sent to Pinegrap AI."
- On a page that has boxes an amber line: "The 11 boxes on this page are replaced. Ctrl+Z brings them back." On an empty page it is not shown.
- Foot: "Cancel" (quiet), "Draw the sketch" (filled), dimmed while the field is empty. Ctrl+Enter presses it, Escape cancels.
- The window keeps the words of its last use on that page.

## What does the page show while the sketch is drawn, and after?
- Busy: the head shows a spinner, "Drawing the sketch…" and "Stop" (quiet, small); the boxes appear greyed and take no pointer. "Stop" or Escape puts the page back as it was.
- Drawn: "Sketch drawn from your words: 10 boxes." with "Undo" (quiet, small), which is Ctrl+Z. They are boxes like any other: chips from the reader, an unsure one with its amber mark.
- No answer: "The sketch could not be drawn. Nothing was changed." with "Try again". Stopped: "Stopped. The page is as it was."
- The AI only draws boxes and types headings into them. The kinds stay the reader's and "Build the page" is the same build as for a sketch drawn by hand.

## How does "Write the texts" run?
- From "More" on a built page, with Pinegrap AI on. No window and no choice: it starts at once.
- The sections that still hold sample content get texts from the page's details (name, title, description) and the headings typed in the sketch. Those are what is sent.
- A section edited in Design is never written over; Header and Footer, shared by other pages, are left out too.
- The texts come in the page's language.
- While it runs the head carries a quiet mark, a small spinner and "Writing the texts… 3 of 7" in the place of "Draft", and keeps its actions. Nothing is locked: the page, the sketch and the rest of the designer stay open; a section edited meanwhile is not written over.
- "Stop writing the texts" is the first row of "More" for that time ("Write the texts" is dimmed under it) and keeps what is written so far.

## What are its messages?
- Written: "Texts written in 4 sections of **Classes**. 1 edited section was left as it is." with "Undo" (quiet, small). The result is one Undo step: "Undo" or Ctrl+Z takes all the texts back at once.
- Nothing to write: "Every section of **Classes** was edited. No text was changed."
- No answer: "The texts could not be written. 2 sections were written, the rest keep their sample content." with "Try again".

## What changes on a phone, and when Pinegrap AI is turned off?
- The "Describe the page" window takes the full width; the field keeps its height, the buttons stay at the bottom side by side.
- With Pinegrap AI turned off while a window of it is open, the window closes with the message "Pinegrap AI is off." and nothing is sent.
