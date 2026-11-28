---
name: artifacts-family-licence
description: Which licence a file of the Pinegrap Artifacts family carries (owner 2026-11-28): the editors' licence for a file written from that day on, MIT for the files of Notes and Data tables written before
---

## What is Pinegrap Artifacts?
- The owner's name for the product's own document types: a Note, a Data table, the Artifact Canvas, a canvas with scenes (`platform/office-direction.md`, `workflow/decisions-made-product-121.md`).

## Which header does a new file of the family carry?
- A PHP, JS or CSS file newly written for the family from 2026-11-28 on ends its header as the Artifact Canvas's files do (`file-header-and-embedded-libraries.md`): `@copyright   2026 Erdal Güral. All rights reserved.` and `@license     Proprietary, see license-editors.txt`.
- The PR that adds the file lists it in `license-editors.txt` at the repository root, under the heading "Pinegrap Artifacts" of "Covered files".
- The first such PR adds that heading and names the family in the licence's first paragraph, in the English and the Turkish text: the visual editor, the image editor and Pinegrap Artifacts.

## What counts as written for the family?
- What the types share: the document head (`pg_doc_head.js`, `plans/document-head.md`), the one "New" menu, the conversions between a type and an Office file.
- A new file of one type (Notes, Data tables) whose code is newly written.
- The Artifact Canvas's files were covered before: nothing changes for them.

## What stays MIT?
- Every file of Notes and Data tables that existed before that day (`workspace_note_*.js`, `includes/workspace/notes*.php`, `wtable*.php`, `workspace_wtables.php` and the rest): the owner chose the files written from now on, not every file of the family.
- A block added to such a file, to `workspace.js` or to the panel's stylesheet: the licence goes by file.
- A new file that only holds code moved out of an MIT file (a split): moved code is not newly written.
- The Office readers and views (`pg_office_view.js`, `includes/fn/office*.php`): the way in and out, not of the family.
- Tests under `tests/` and tools under `tools/`.

## What is not settled here?
- Whether published MIT code may change its licence in a later version, and the copyright line of the MIT header (Kodpen): legal questions. The architect told the owner it cannot judge them; nobody changes a published file's header on this file's word.
