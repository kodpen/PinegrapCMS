---
name: workspace-notes-import-zip-names
description: How the notes import reads zip entry names (UTF-8 flag, CP857 from Windows/7-Zip on a Turkish system, libzip's CP437 guess) and why "Toplantì notlarì" happened
---

## Why were note names from a Windows zip garbled ("Toplantì notlarì") and Turkish-named attachments missing?
- A zip entry's name is bytes plus one "this is UTF-8" flag. Windows' built-in "Compressed folder" and 7-Zip on a Turkish system write the name in the OEM code page CP857 with the flag off. libzip (`statIndex()['name']`, the default of `getNameIndex()`) takes such a name for CP437 and converts it: ı→ì, ş→ƒ, İ→ÿ. The result is valid UTF-8, so `ws_note_import_entry_name()` (which only repairs invalid UTF-8) saw nothing wrong; the note was titled "Toplantì notlarì" and `[şartname](Ekler/şartname.pdf)` matched no entry, so the attachment was reported missing and the link became words (#775).

## How are the names read now?
- Since PR #1145 the stage (`ws_note_import_stage()`, notes_import.php) reads the raw bytes with `getNameIndex($i, ZipArchive::FL_ENC_RAW)` and passes them through `ws_note_import_zip_name($raw, $guess)` (notes_io.php, pure): valid UTF-8 is used as it is, flag or no flag (7-Zip `-mcu`, every Linux or Mac zip, our own export); anything else is decoded as CP857 from a 128-entry table, U+FFFD for the three bytes CP857 leaves undefined. Without raw bytes (the library answered false) libzip's guess is kept.
- A table, not `iconv('CP857', …)`: mbstring does not know CP857 and iconv on some hosts (musl, a trimmed glibc) does not either, and a conversion that fails silently would bring the bug back.
- Why CP857 for every non-UTF-8 name: the Western European letters of CP437 and CP850 (é, ü, ö, ç, ñ, …) sit on the same bytes in CP857, so a zip from another Windows locale still reads right; only the positions CP857 gives to ı, İ, ş, Ş, ğ, Ğ differ. There is no reliable way to tell the code pages apart from the bytes alone.
- Entries are read later by index (`getFromIndex()`), never by name, so the decoded name is only what the note is called and what links are matched against.
- Tests: tests/workspace_notes_io_test.php (`test_workspace_notes_io_import_zip_names_without_the_utf8_flag`).

Related: docs/memory/workspace/workspace-notes-import.md
