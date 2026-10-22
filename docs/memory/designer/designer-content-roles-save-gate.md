---
name: designer-content-roles-save-gate
description: Designer content roles (manager/user): what they may edit, how pg_designer_merge_restricted_tree() merges a save, note stamping, body classes, locked vs hidden.
---

## Which access levels exist in the designer?
Core `includes/designer_access.php`.
| Level | Who | What |
|---|---|---|
| `full` | administrator (0), designer (1) | editor as usual |
| `content` | manager (2), user (3) | text + inside of blocks marked `props._editable` |

## What are the immutable rules of the content level?
- A shared component / system widget is never opened to `content` from a page, even inside a marked area: its tree lives in `shared_components` and belongs to every page using it. The only way in is a component a designer opened, saved through its own `content_update` (docs/memory/designer/shared-component-content-editing.md); a widget never opens. Design `style_custom_*`, theme and body classes are not written for the same reason.
- The browser decides what is SHOWN, the server decides what is WRITTEN. `_sdMayEdit(node, what)` is courtesy; the gate is `pg_designer_merge_restricted_tree()` inside `pg_designer_save_page()`.
- On save there is no rejection, only non-copying: the saved tree is walked; from inside a marked area the whole subtree is taken, from outside only `text`; the rest is discarded (diffing and rejecting would mean enumerating every way a tree can differ).
- Children match by `_id`, not position. Position matching broke on duplication: a node duplicated outside the marked area shifted all later siblings by one and the saved B received sent A's text. Trusting a request-body id is free here: outside the marked area only text and image address are copied, and the operator may change both on that node anyway.
- `_editable` flags in an accepted subtree are stripped (re-stamped from the saved root node), else an operator widens their own authority. `shared_ref`s in the subtree are put back to their saved state.
- Page identity is not content: name / folder / home-page flag are written back from the saved row BEFORE the duplicate-name check, else a save is rejected over a name the operator did not choose. Title, description, keywords stay editable.

## What is always the content operator's, outside marked areas?
- Text AND image. The merge copies only `props.text` and the image ADDRESS (`_pg_dm_take_image_src`); size, class, alt stay as saved. Background image is outside (it is formatting). Reason: an operator who can change a title but not the photo above it got half the job.

## How are note authors and `_notes` handled?
- `pg_designer_save_page()` re-stamps only notes whose TEXT changed; unchanged notes keep `_notes_by` / `_notes_at` (else the saver would take ownership of every note). Browser-sent `_notes_by` is never copied in the merge. The note edit window clears the old stamp on text change so the list does not show a wrong name until next load.
- `_notes` is carried even outside marked areas: a note is a comment, not page content; collaboration deliberately lets even a view-mode session leave notes.

## What about body classes and locked/hidden pages?
- Root node `cssClass` / `id` / `_attrs` are printed on `<body>` by `generate_style_code_from_tree()` and the Attr panel edits them with `<body>` selected. The design-level additional body classes setting (`style.additional_body_classes`) was REMOVED (it described one page's body but was design-wide, so no page could have its own class). Old value moves to each page's root `cssClass` on first save and the column is cleared (dropping it would strip a live site's styling).
- `pg_designer_page_access()`: a non-editable page in an open folder is LISTED but not opened (operator knows it exists); a page in a private/membership folder is NOT listed at all (the name itself is information).

Related: docs/memory/designer/designer-content-roles-ui-guards.md, docs/memory/designer/designer-content-roles-translations.md
