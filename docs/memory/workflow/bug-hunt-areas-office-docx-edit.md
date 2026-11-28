---
name: bug-hunt-areas-office-docx-edit
description: Bug hunt of Word documents edited in place, server side (DA1-DA7, 2026-11-28): what was fixed, what read clean, what was left
---

## Which area and files?
- Word documents edited in place, server side, named by the architect on 2026-11-28: `includes/fn/office_docx_*.php`, `includes/fn/office_edit.php`, `includes/fn/document_paragraphs.php`, and their callers `includes/explorer/handle_file_window.php` and `includes/workspace/file_edits.php`. `assets/js/pg_office_view.js` was left out (held by another PR). Hunted in the PR `[iş] Bug hunt — Word documents edited in place`.
