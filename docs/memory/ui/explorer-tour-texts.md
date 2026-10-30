---
name: explorer-tour-texts
description: File and Catalog Manager tour steps name the control they ring by its printed label; the toolbar More button is named; keys stay .4 on rewording
---

## How do the File and Catalog Manager tour steps point at a control?
- By the label the screen prints, position only as an extra (`view_folders.php`, `$explorer_tour`): "the File Manager / Catalog Manager panel of the main menu (on the left)", "Search, this box", "Filters, the button beside it" (with "Clear filters" on the filter line), "Grid view and List view", "the status bar under the listing" (which says how many items are selected), "More actions (the three dots)", "More, the three dots at the end of the toolbar".
- The item-menu step names every way in: right-click, the Menu key or Shift+F10 ([[explorer-keyboard]]), a long press on touch. Never "anything in the middle of the screen", "the sliders", "the two buttons on the right", "the bar along the bottom" or "this menu": the bubble is read aloud (`role=dialog` plus a `role=status` line), and those name nothing to look for. Same rule as [[notice-button-names]].
- `#explorer_more_button` (the `more` step's target) carries `title` and `aria-label` `lang('More')` ("Daha fazla"); it was an unnamed icon-only menu toggle.

## When does the tour key change?
- Only when a step is added or removed (`explorer_files.4` / `explorer_catalog.4`, pinned by `tests/explorer_side_test.php`); rewording a step keeps the key, as `shell.6` did (#3450).
- `tests/folder_tour_texts_test.php` pins the wording, that each quoted label is still printed, the More button's name and that every `tourAnchor()` target id is in the markup.
