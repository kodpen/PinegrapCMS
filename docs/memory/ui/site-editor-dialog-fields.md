---
name: site-editor-dialog-fields
description: CKEditor's Link and Image dialogs on a site page in Edit mode: livesite.src.css sets the skin's field width, padding and select arrow again over a theme's input and select rules
---

## Why does the site stylesheet style CKEditor's dialog fields?
- A classic theme styles every `input[type="text"]` and `select` (width, height 50 px, padding, a grey background, `appearance: none`; `theme_modern.css` of the sample site), and those rules outrank CKEditor's skin (`assets/lib/ckeditor_4_20/skins/moono-lisa/dialog.css`).
- `livesite.src.css` and the hand-kept `livesite.min.css` (linked from the classic styles' own head code, `workflow/decisions-made.md`) set the skin's values again with `!important`: text fields 100 % wide, height auto, padding 4px 6px (5px left on focus); selects `calc(100% - 2px)`, 28 px, padding 3px 3px 3px 6px, `appearance: auto`, no background picture; both white, border-box.

## What was the symptom? (#9117)
- The old rule set `width: inherit` and `padding: 0`: the Link dialog's Display Text and URL fields were 157 px in a 450 px column, the selects lost their arrow and Protocol read "http:/". In the panel the same dialogs were fine (livesite css is not loaded there).
- Checked in the sandbox (`/services`, Edit mode, Link and Image dialogs): the fields now match the panel's dialog on Edit Product Group, but for the panel's Picture… button.

## What is still open?
- The Page…, File… and Picture… buttons on site pages (`picker-editor-bridge.md`).

Tests: `tests/site_editor_dialog_fields_test.php`.
