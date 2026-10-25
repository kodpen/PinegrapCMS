---
name: workspace-notes-import-keep
description: Google Keep notes from a Takeout zip (notes_keep.php, PR #1656): found in any zip, laid out in the stage as Markdown and attachments, untitled notes named by their first line
---

## How does a Keep export come in?
- Google Takeout gives Keep as a zip: a note is `Takeout/Keep/<title>.json` (title, `textContent` and newer `textContentHtml`, `listContent`, `labels`, `attachments` by `filePath`, `annotations` web links, `isTrashed`, times in microseconds), with an HTML copy and its pictures beside it. The person uploads that zip like any other; nothing to pick.
- In the zip branch of `ws_note_import_stage()`, `ws_note_keep_stage()` reads every `.json` entry (up to `WS_NOTE_IMPORT_TEXT_MAX`, through `pg_zip_entry_contents()`). A file counts as a Keep note only when it is a JSON object with a Keep time and a text or checklist (`ws_note_keep_parse()`), so a vault's `package.json` never does. No Keep note: the zip is read as it is. Any Keep note: the stage is built from the Keep notes alone (the HTML copies, `Labels.txt` and other files are not read), `source.zip` is removed and the stage reads `files/` like an Evernote import (`$read_zip`). The preview, batches and finish are the Markdown import's (workspace-notes-import.md).

## What does a note become?
- A Markdown file `files/<n>.txt` named `<title>.md` (`ws_note_export_name()`), written by `ws_note_enex_file()`: title, created, updated and labels (as tags) in the front matter. Created falls back to the edit time on older exports without `createdTimestampUsec`.
- Text: `textContentHtml` through `ws_note_enex_markdown()` (bold, italic, struck words kept), with Keep's one `<p>` a line turned into `<div>` first, else an empty line would come between every line; without it, `textContent` as written. Then the checklist as `- [ ]`/`- [x]` items, then the web links (http and https only) as `[title](url)`.
- An untitled note (common in Keep) takes its first line of text or checklist, cut to 80 letters (`ws_note_keep_title()`), not "note-N": Keep shows such a note by its text.
- Notes in Keep's bin (`isTrashed`) are left out; a zip of only those says "The Google Keep export holds no notes outside the bin." Pinned, archived and colour are not carried (the import has no field for them).

## What happens to the pictures?
- Each attachment is copied out of the zip to `attachments/<n>/<name>` (a row with a fifth value `1`, never read as a note) and linked at the end of the text. The path beside the note is tried, then in any case, then a file of the same name with another extension (Takeout names a picture `.jpeg` in the note and `.jpg` in the zip). One the zip lacks keeps its link, which the preview reports as missing; one past `ws_upload_max_bytes()` is counted only, so its link becomes words at import, as for a zip.
- All attachments together are copied only up to `WS_NOTE_IMPORT_FILES_BYTES` (512 MB, the bytes one import may link); the rest are counted only. Without that ceiling a zip of a few KB naming one well-packed picture thousands of times filled the disk, each name copying it out again (found in the merge review of #1656).

## Where are the tests?
- tests/workspace_notes_keep_test.php: times, the Keep shape, titles, the body, a Takeout zip read into a stage, a zip without Keep notes left alone, a zip of binned notes refused, the byte ceiling on copied attachments. The hook in `ws_note_import_stage()` needs a real upload and is not unit-tested.
