---
name: designer-inline-text-editing
description: Canvas in-place text editing: spaces typed into a button label, labels of colour/date/file inputs, no edit hint on bound text, the canvas <html lang> for Turkish capitals.
---

## Why does the editor write the space in a button label itself?
- A `<button>` takes Space as its own activation, so the contenteditable inside it (the virtual label span of `_createVirtualText()`, a tab or accordion header in `setupInlineEditing()`) never gets the character.
- Both Space branches call `_sdInsertEditSpace(el)`: `execCommand('insertText', false, ' ')` writes it the way typing does (U+00A0 where a plain space would collapse). Without the command a U+00A0 text node goes in at the caret.
- A plain U+0020 node at the caret was collapsed by Chromium on the next character: "Hemen Ara" was saved and published as "HemenAra".
- What was typed is read back through `_sdInlinePlainText()` (U+00A0 → space): the virtual label's blur and the plain-text branch of `_sdInlineEditValue()`. The saved tree holds plain spaces.

## Why does a click on a colour or date label not open the picker on the canvas?
- A click on a `<label>` activates its control. For `color`, `date`, `time`, `datetime-local`, `month`, `week` and `file` that opens the browser's chooser, which keeps the keyboard: the first click of the double-click that starts editing the label opened it, and every typed character was lost (the label stayed "Renk").
- The canvas click guard (capture listener in `_sdInitCanvasDoc()`) calls `preventDefault()` when `e.target.closest('label')` passes `_sdLabelOpensChooser()` (it reads `label.control`). A click on the control itself inside a wrapping label is left alone.
- Checkbox and radio labels keep their native click: the theme switch block depends on the synthetic `change` event. `setupInlineEditing()` still takes `for` off a label while it is being edited.

## Why does bound text show no "double-click to edit" hint?
- Text with `_bindings.text` refuses the edit on double-click (toast), because the binding rewrites it on every render. `setupInlineEditing()` therefore sets neither `cursor:text` nor the title on it; the title comes from `_sdInlineEditHint(node, rich)`, which also keeps a node's own title or tooltip.
- The virtual label of a bound button already had `cursor:default` from `.sd-wrap.sd-has-binding .sd-virtual-text`.

## Why does the canvas document carry `<html lang>`?
- `text-transform: uppercase` follows the document language: without `lang` the canvas drew a Turkish "i" as "I" ("MARKA KIMLIĞI") while the preview and the page, which carry `lang="tr"`, show "İ".
- `_sdDocLang()` (the panel's `<html lang>`, `en` without one) is the one source: the canvas `doc.write()`, the HTML export in `generateHTML()` and the code view document (`_sdCodeDocument()`).

## How does an in-place edit inside a shared component get saved?
- Both commit paths (the virtual label's blur, the `setupInlineEditing()` commit) call `_sdSharedMarkEdited(node)`; a double-click selects the node through `_sdInlineSelect()`. Details and the save-time catch: `shared-component-editor-cache.md`.

## Where is it tested?
- `tests/designer_inline_text_test.php` runs the helpers under Node with stub documents and checks where they are wired in.
