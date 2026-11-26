---
name: picture-text-fit-view
description: The Artifact Canvas's language view (PF4, #11904): "Correct by hand" on a Languages list row, the bar, what can be moved, the corrections written on Done, the queue's Look by hand row opening it
module: image_editor
---

## Where does it open?
- The Languages list: a row whose every text is translated (`M.languageDrawable()`) has "Correct by hand" after Draw; on a Look by hand row it is the row's main (filled) button. A row with corrections says "Corrected by hand: {n} texts" (one: "Corrected by hand: 1 text") from `picture_languages`' `corrected`. The list's note ends with `langFixNote`.
- With changes not saved yet, the button asks "Save your changes first: a language is corrected on the saved picture." with Cancel and Save (Save closes the canvas, as every Save).
- "Draw the waiting pictures": a Look by hand row's "Edit in Artifact Canvas" carries `data-pg-image-language`; the loader passes `language` to `pgImageEditor.open()` and the editor enters that language's view once the picture and its languages are loaded (`lvAsked()`).

## What does the view show?
- Part `langview` (`assets/js/pg_image_editor_langview.js`, labels `pg_image_editor_strings_langview()`). `M.languageView(doc, texts, code, layout, board)`: the editor's saved document as the language is drawn, every layer but a text locked; beside it each text's words before the fit and where the fit alone puts it (`auto`). The editor's document, history and tools are kept aside; the view has its own undo history.
- A bar over the stage's top (at the stage's foot where the panel is a sheet): the language's name, "Only this language's texts move here. The source picture does not change.", More (a link "Edit the words in Translations" to `translations.php?group=pictures`, from the answer's `words_url`, empty under role 2), Back to automatic, Done.
- Only Select on the rail; the panel lists the layers, texts pickable, the rest locked; the selection bar, the context menu, typing, Delete, copy and paste are off. The n, s and turning handles are hidden.

## How does a change become a correction?
- Every `change()` in the view runs `M.languageViewSettle()`: only each text's place, size and correction are kept (anything else comes back); a move or a corner (sizes the letters) gives `M.languageCorrectionAt()` (the inverse of the put), its width scaled with the letters; a side handle sets the wrap width (`lvFix`, letters kept); then every text is put again: corrected by `languageCorrectedPut()`, else at `auto`.
- A dot marks a corrected text's box and its row; a text past the picture's edge (`M.languagePast()`) has a dashed `--pg-warning` outline.
- Back to automatic: the picked texts' corrections off; with none picked every text's, after a question.

## What does Done do?
- `M.languageViewCorrections()` writes the view's corrections into the canvas document, nothing else, as one undo step; the canvas is then unsaved and its Save keeps them and draws the language again. Escape with nothing picked, Save, Close and the Languages button end the view the same way first.

## Tests?
- `tests/picture_text_view_test.php`: the inverse per alignment and turn, the edge, the view's document, the settle (move, side, corner, back, a delete refused), Done's write, the labels and the queue's door, and the editor's glue under Node.
