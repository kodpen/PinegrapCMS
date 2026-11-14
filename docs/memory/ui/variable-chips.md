---
name: variable-chips
description: Variable chips (WA8b): a message's {{n}} drawn as chips naming what they stand for, the shared pgVarChips editor and pg_var_chip_html(), drag, click, move and remove, what the textarea keeps
module: var_chips
---

## What are they?
- A message's variable shown as a chip in the accent's tint that names what it stands for (Customer's name, Order number …) instead of Meta's `{{1}}`. Owner (2026-11-13), channel 25 #2222: `platform/whatsapp-template-designer.md`.
- Shared: `includes/fn/var_chips.php` (`pg_var_chip_html($label, $palette)`, `pg_var_chips_script()`), `assets/js/pg_var_chips.js` (served as written, no twin), CSS block "Look A: variable chips" in `backend.src.css`. First user: `whatsapp_template.php`; the Flows WhatsApp box's variable rows (`workspace/workspace-flows-whatsapp-box.md`) and the WA5 rows that say what fills a variable reuse the chip.

## How does the editor work?
- `pgVarChips.mount(textarea, {labels, palette, max, readonly, texts, onChange})` puts a `contenteditable` editor before the textarea and hides it; the textarea stays the posted field and always holds the text with `{{1}}`, `{{2}}` … numbered by first appearance. `labels` maps the numbers it opens with to names.
- A chip's identity is its label: the same label twice is one variable. `onChange(text, labels)` gives the labels in number order, so a screen renames its per-variable fields (`label_<n>`, `sample_<n>`) after each change.
- Palette chips (`[data-pg-var-label]` buttons) are dragged in or clicked in at the last caret; chips in the message are dragged to move and have a remove button; Enter is a line break, paste is plain text. `insert(label)` and `wrap(marker)` serve the screen's own buttons.
- A palette must not cancel `mousedown`: that also cancels the drag (found on the sandbox).

## What is not done?
- No keyboard way to move a chip (cut and paste of a chip loses it: paste is plain text); remove and insert again instead.

Test: `tests/whatsapp_design_test.php` (`pg_var_chip_html()`). Drag, move, remove and save were run on the sandbox with Playwright.

Related: docs/memory/platform/whatsapp-template-designer.md
