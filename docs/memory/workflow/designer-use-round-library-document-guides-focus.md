---
name: designer-use-round-library-document-guides-focus
description: Use round UR7 (PR #11856): My library, a page from a document, canvas guides and a picture's focus point used in a browser; the eight fixes, what was left and what could not be run
---

## What was used, and how?
- A `setup_sandbox.sh` instance, Chromium at 1440 × 900 and 390 × 844, light and dark panel scheme; the document start once by the keyboard alone (command palette, form, Read, Build).
- Empty design: Blank, then "From a document" with a pasted plain text (headings straight over their paragraphs, prices, questions, contact); My library on the page it built (save, group, Blocks group, Manage, Export all, Import).
- Ten pages and more: the Online store template (36 pages) for canvas guides (bands and their keys, Alt measure, Shift+G, the View menu switch) and the focus point (Focus control, keys, "Set focus" mode).

## What was found and fixed?
- A pasted text whose headings stand straight over their paragraphs (no blank line under them, as Word copies) read as two sections. Such a block's first short line is now a heading (`document-reader.md`, `tests/document_reader_test.php`).
- The built hero kept its starter's sample buttons ("Get Started", "More Info"): `clean()` knew only content nodes. Unfilled `btn` components and the group they leave empty go (`tests/document_start_test.php`).
- The designer's "Page from a document" window said its title twice (the window's and a 28 px heading); the drop place broke its title one word per line at 390 px under the buttons. The window shows only the lead line; the drop place wraps its buttons.
- That window opened with the focus on Close, so a paste went nowhere. The text field takes it.
- My library's windows (save, Manage, Import) used Bootstrap's `btn btn-solid`/`form-control`, pale beside the panel's windows. Now `pg-button` and `pg-input`.
- Manage library's group counts were empty blobs: `--sd-text` (the dark chrome's) on a light dialog. The window's colours read `--pg-*` tokens, right in light and dark.
- Export named a file "m-teri-hizmetleri-library.json" for "Müşteri Hizmetleri"; letters go through `pg_ascii_file_name()` first (`tests/designer_library_io_test.php`).
- At 390 px the saved line's "Show" changed nothing: the palette sat in its closed drawer. It opens the drawer.

## What was left, and why?
- After "Read" the focus falls to the page (the button it was on is gone). The fix is in `pg_document_rows.js`, which PR #11839 (UR2) also changes: it waits on that one.
- Plain lines like "Single class: 15 EUR" under "Prices" read as text with the amber "?", and "Name: text" lines as text, not cards; an address without street words is not a contact line. A new reading rule is the architect's.
- "Import to my library" is "Kütüphaneye içe aktar" in Turkish while the feature is "Kitaplığım": a reworded label is the architect's.
- The empty canvas' line "This page is empty. How would you like to start?" is pale grey on white, under 3:1.
- Focusing a control of a closed drawer (Tab, or a script) scrolls `.sd-body` sideways at 390 px: the docking layout keeps closed drawers focusable. Not one of these features.
- At 390 px the toasts stand over Manage library's foot (Import, Done) until they go.
- The canvas guides' first-use bar names Alt on a phone, and takes three lines there.

## What could not be run?
- "Sort it for me" (no AI key) and a .docx file (pasted text only).
- "Set focus" dragging a cut picture: the template's pictures are drawn whole, so the mode said "This screen shows the whole picture." (PR #11609 ran the drag on the built part).
- A library item holding widgets or a cleared binding (the amber mark); the content-role view of the group.
