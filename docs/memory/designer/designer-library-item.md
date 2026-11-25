---
name: designer-library-item
description: My library item model (ML1): a subtree to an item, an item into a design (rules added or renamed, variables, references cleared and marked), the store as design files, rights
module: designer_library
---

## What does an item hold?
- `pg_library_item_from_subtree()` (`includes/fn/designer_library.php`, pure): `format` `pinegrap-library-item`, `version` 1, `name` and `group` (one line, at most 60 characters), `tree`, `rules`, `variables`, `widgets`, `shared`, `refs`.
- The tree loses `_id`, `_expanded`, `_sharedDirty` and the notes (`_notes`, `_notes_by`, `_notes_at`). A `shared_ref` becomes `props.libraryWidget` (`w<sid>`) or `props.libraryShared` (`s<sid>`) with the row's name, tree and, for a widget, kind and config (no `template_origin`). A placement of a row the caller did not give is left out and counted in `dropped`.
- `rules`: the User Styles rules whose selector names one of the item's own classes, a class that is not Bootstrap's (`pg_library_framework_classes()`, read from `assets/js/designer_vocab/bootstrap5.json`). Each is `selector`, `media` (the @media/@supports chain), `body`; comments are dropped.
- `variables`: name => value of every `var(--x)` the tree's props and the rules read, and the ones those values read; `--bs-*` are left out (every design has them).
- `refs`: kind => id => name for every record a widget config or a node's props name: `page` (a key with `page_id` or `form_id`, and `_cf.ref`; forms and collections are pages), `group` (`contact_group_id`, `group_id`), `folder` (`folder_id`). `_bindings` are field names, kept as they are.

## What does inserting into a design do?
- `pg_library_item_into_design($item, $design, $site)` returns the tree and what the caller writes: `css` to append to User Styles, `widgets` and `shared` rows to make, `renamed`, `vars_added`, `rules_added`, `cleared`.
- Rules by class name: the design has the same rules for the class, nothing; none, the item's rules are added; different ones, the design's stay and the item's class gets `<class>-<n>`, the first free number, in its rules and in every `cssClass` of the copy. The editor's line that says so: `designer-my-library-save-and-group.md`.
- A variable the design defines keeps the design's value; a missing one is added in a `:root` rule with the item's value. A value holding `{ } ; < >` is never written.
- A reference keeps its id when the site has that record under the same name, takes the id of the only record of that name otherwise, and is cleared (`''`) when there is none or two. The node or the widget placement gets `props._libraryUnbound` (the kinds cleared); a widget entry lists them in `cleared`.
- A widget is always made anew (the copy is the page's own). A shared component the site has (same row id and name) is placed again by `sharedId`; another is returned in `shared` for the caller to make.

## Where is the library kept?
- One design file per item in the file manager's root folder: `library-<slug>.json`, `files.type = 'json'`, `design = 1`, the way a theme is a design CSS file; no table, no column. It shows in the Design Files list.
- `pg_library_list()` (by `order`, then name), `pg_library_get()`, `pg_library_save()`, `pg_library_update()`, `pg_library_delete()`; `pg_library_item_decode()` refuses a file that is not an item, too large (1 MB), of a newer version or nameless, and drops a rule or variable that could close its block.
- Export, Import and the checks of an imported file: `designer-library-export-import.md`.

## Who may do what?
- `pg_library_may($action, $user)`: `use` (see, insert) for designer access full or content (roles 0-3); `save`, `manage`, `export`, `import` for full only (administrator, designer).

## How is it tested?
- `tests/designer_library_test.php`.
