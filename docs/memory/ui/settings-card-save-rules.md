---
name: settings-card-save-rules
description: Rules for writing settings cards and their save modules: own columns only, $sql_* splice, prep.php $row, balanced divs, registry.php sections.
---

## Which columns may a category write?
- A category writes ONLY its own columns. A new setting: control in `<cat>.php`, write in `<cat>.save.php`. Never write another category's column: the old "every save writes every column" behaviour caused two production bugs (partnership fields and the barcode label template were wiped on every save).
- Never write a column that has no control: `post_value()` returns empty and the column is reset. If another screen owns the column (barcode template -> label editor) Settings never touches it.
- A `$sql_*` fragment is spliced in the file where it is built. Building the fragment in one file and putting it in another file's `UPDATE` silently writes empty (happened once with `software_language`).

## What is `$row` in `prep.php`?
- `$row` is the config row and must stay so; cards read it under that name. Never assign a second query's result to `$row` (next order number, pem files, currency loop did): the rest of the file then sees `$row` as `false`. `empty($row['x'])` on a boolean does not fail, it says "absent" silently (the stored CDN provider was never applied for this reason). A new query writes to its own variable.
- The shared `prep.php` was deliberately NOT split: distributing 581 statements by an ownership graph was tried; 82 could not be tied to one owner (`$output_*` fragments built with `.=` in loops), and a preparation variable landing in the wrong file shows up as an EMPTY field, with no error. New preparation code goes into `prep.php`.

## Why must every card close every `<div>` it opens?
- Invisible by eye. Cutting a card out of a bigger card leaves the wrapper `div` behind; a card leaking a `div` swallows ALL following cards. Symptoms: "cards stuck together" + "save button hanging"; neither points at the card itself. Compare the `<div` / `</div>` count per card.

## Where is the section list and how does it feed the UI?
- Section list is in `registry.php`. Card id = section id = chip target = `#hash`. Adding a card without updating the registry binds the chip to a nonexistent anchor.
- Chips are built from cards that are ACTUALLY in the pane: a conditional card (image settings) is not drawn on every install.
- Sidebar, command palette and the removed hub all feed from the same registry (`pg_settings_categories()` / `pg_settings_tool_groups($user)`). Do not write a second list.
- A card of a disabled feature is NOT hidden: its controls stay in the DOM, otherwise saving that category would blank the invisible settings. With commerce off, a note at the top of the Store card says so.

Related: docs/memory/ui/settings-modal-architecture.md
