---
name: explorer-toast-long-names
description: File Manager notices (toast()) wrap a long file name inside the 350 px box with the close and Undo buttons in it; why the Bootstrap 6 toast's flex column pushed them out
---

## Why did a long file name push a notice's text and buttons out of the box?
- A Bootstrap 6 `.toast.show` is a flex column, and the explorer's toast carries `align-items-center`, so its inner `.d-flex` row was only as wide as its widest word.
- A name of one hundred letters without a break (the stored name is cut to 100) made the row 900 px to 2,000 px wide inside the 350 px toast, centred and clipped by its `overflow: hidden`: the text was cut on both sides and the close and Undo buttons sat outside (#8979, PR #9090). The upload notice is sticky, so it stayed until a reload.
- Bootstrap 5 drew `.toast` as a block, where the same markup filled the box.

## What does the toast do now?
- `toast()` in `view_folders.php` builds `<div class="d-flex w-100"><div class="toast-body flex-grow-1 min-w-0 text-break">`: the row fills the toast and a word that does not fit breaks inside it (`text-break` is `word-break: break-word`, which also lowers the word's minimum width). The close button keeps `flex-shrink-0`, the action button `text-nowrap`.
- Utility classes only; no rule in `backend.src.css`.

Related: docs/memory/ui/explorer-bin-toast.md, tests/explorer_upload_notices_test.php
