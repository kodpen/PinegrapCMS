---
name: browser-prepared-submit
description: A form that prepares data in the browser before posting it (Image Editor, Edit File's SVG to raster conversion) sends once, through requestSubmit(), and only when the data is ready.
---

## How does such a form send?
- Through `form.requestSubmit(submitter)`, never `form.submit()` or jQuery `.submit()`. Those fire no submit event, so `pgSubmitGuard` (`button-busy-state.md`) never sees them, and they drop the pressed button's name.
- Its submit handler cancels the first pass without `stopImmediatePropagation()` and keeps a busy flag that cancels further submits while it works. It acts only for the buttons it belongs to (`e.submitter`), so Delete and Duplicate pass through untouched. Edit File's SVG conversion acts only for `submit_save` and `submit_save_and_return` (#3176).

## What do the buttons do while the data is not ready?
- They are drawn `disabled` and enabled once the data is in the form. A failed preparation keeps them disabled and says so in a `role="status"` line. They are disabled on the first click and enabled again on `pageshow` from the back/forward cache. The labels never change. This is how the Image Editor's Save Option dialog works (#3176).

## Why does the server check too?
- `image_editor_edit.php` refuses an empty `image_file` before `unlink()`. A click before the conversion ended once replaced the original with a 0-byte file, and a double click created two copies (#3176).
