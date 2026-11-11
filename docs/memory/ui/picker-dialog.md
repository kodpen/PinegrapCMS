---
name: picker-dialog
description: The picker dialog pgPicker.open() (assets/js/pg_picker.js, PK2): what it shows, how a pick is handed back, the keyboard, the phone sheet, the Browse button, what picker.php added for it
module: picker
---

## Where is it and how is it opened?
- `assets/js/pg_picker.js`, served as `assets/js/pg_picker.min.js` (`pg_js_bundles()`); styles in `backend.src.css` under "The picker dialog". PK2 of `plans/one-picker.md`, PR #8562.
- A screen prints `pg_picker_script()` once (the `#pg_picker_config` JSON with the `picker.php` address and `pg_picker_texts()`, then the bundle); it works in markup injected with jQuery, as the Settings cards are.
- `pgPicker.open({kind, value, title})` returns a promise of the picked item or null, settled once the dialog has closed (`dialog-result-after-close.md`).
- `pg_picker_browse_button($kind, $field_id, $title)` prints Browse…: it opens the dialog at the field's value and writes the pick into the field (a select gets the option, then `pgCombobox.set()`, which fires `change`).
- Used only on Settings › SEO › Site Icon (kind `site_icon`) until PK5 moves the other fields.

## What does it show?
- Title and the kind's types (`types` of the tree answer), toolbar: Up, breadcrumb, search, sort, grid and list; folder tree with counts; the open folder's sub-folders as folder tiles, then its files; footer with the pick (picture, folder path, size), a hint, Cancel and Choose.
- Tree counts are the kind's files in a folder and every folder under it (`pg_picker_subtree_counts()`).
- Search: results from every folder with their folder line, the match highlighted, Best match first (the server's order); a chip keeps them to the open folder and the folders under it.
- A folder path leaves out the top folder once there is one below it (`pathText()`), as the screens draw "Görseller / Ürünler".
- Not yet: Upload, Recent and several picks (PK3), pages (PK4).

## How do the keys work?
- In the items: arrows move over the grid (columns measured from the tiles) or the list, Home, End, PageUp/Down; the cursor on a file is the pick; Enter chooses a file or opens a folder; Backspace and Alt+↑ go up; letters jump by name (type-ahead).
- In the tree: ↑ ↓ Home End move, → unfolds or goes to the first child, ← folds or goes to the parent, Enter or Space opens.
- Search: ↓ or Enter moves to the items. Escape clears a search first, then closes; it is taken on `window` in the capture phase, as Quick Look does (`explorer-keyboard.md`).
- Double click chooses; closing with ×, Cancel or Escape changes nothing and gives the focus back.

## What is drawn on a phone?
- Below 576 px the dialog is a bottom sheet: search on its own row, a folder chip, sort, grid and list; three tiles a row; Cancel and the tree hidden.
- The chip opens a folder list in the pane (the open folder among its siblings, at the top the folders inside it): tapping a row opens its files, the arrow goes into it, the path goes back, Back returns.

## How are names sorted and matched?
- Sort keys name, modified, size, type, folders first, `Intl.Collator(lang, {numeric, sensitivity: 'base'})`; size and modified start largest/newest, a tie goes by name. View and sort are kept in localStorage (`pg.picker.*`).
- The highlight folds as the server matches (`fold_keyword_for_matching()`: case, İ/I/ı/i, accents, ß as ss) with an index map, so a letter that folds to two still slices the original.

## What did picker.php gain for it?
- File items carry `size`, `width`, `height`, `modified`, `size_label`, `access` (the folder's inherited access), `url` and `thumb` (the 320 px derivative, `image-panel-thumbnails.md`); search and resolve items carry `path`; a list answer carries `crumbs` (a role 3 user sees only their folders); tree rows carry `count`; the tree answer carries `types`.

Tests: `tests/picker_test.php`, `tests/picker_js_test.php` (Node). Related: picker-registry.md
