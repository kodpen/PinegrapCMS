---
name: picture-text-fit-screens
description: Plan picture-text-fit, one language's texts corrected by hand: what a correction keeps, the Languages list's rows and the language view of the Artifact Canvas, described for PF3 and PF4
---

## What is a correction? (architect, 2026-11-26, agent channel request 3129)
- For one text layer and one language: the box's place, its width and the letters' size. It is kept on the layer in the canvas document, by language code, and holds no words: the translation is still the store's.
- A text with a correction is not fitted: its translation is drawn at that size, wrapped between words to that width, from that place. A language whose every unfitting text is corrected is "Picture drawn", never "Look by hand".
- When the translation changes later the correction stays and the new words wrap in the same box.
- A save of the document turns the drawn pictures out of date and they are drawn again, as every save does; the source's texts, their places and the other languages' pictures come out the same.
- A layer deleted takes its corrections; a copy of a layer takes them along; a language removed from the site leaves them unread.

## How does the Languages list change?
- A row whose every text is translated gains "Correct by hand" after Draw. On a "Look by hand" row it is the row's main button and Draw stays beside it.
- A row with a correction says "Corrected by hand: {n} text(s)" under its state.
- The list's note gains a sentence: a text that does not fit is moved and sized for that language alone, and the source picture stays as it is.
- With changes not saved yet the button first asks to save, since a language is drawn from the saved document.

## What is the language view?
- "Correct by hand" closes the list and shows the canvas as that language's picture is drawn: the saved document with the language's words, fitted or corrected.
- A bar takes the place of the tool options above the stage: the language's name, the line "Only this language's texts move here. The source picture does not change.", then "Back to automatic" and "Done". On a phone the bar sits at the stage's foot, where the selection bar is.
- Only text layers can be picked, and only with Select: the rail shows no other tool, the layers list marks the rest as locked. A picked text has its box: a drag moves it, a corner sizes the letters, a side sets the width the lines wrap to. The words cannot be typed here; a link in the bar's menu opens the Translations screen's Pictures group.
- A text that has a correction carries a small dot on its box and in the layers list. "Back to automatic" takes the correction off the picked text, or off every text of the language when none is picked, after a question.
- Each change is one undo step. "Done" leaves the view; the changes are in the document and the canvas's Save keeps them, then the language is drawn again.
- A text that still runs past the picture's edge shows the amber outline the canvas uses for a layer off the board, and the row keeps "Look by hand" until it is inside.

## What is left out?
- Other words, another font or another colour for one language; a layer shown in one language only; a correction for the source language.
