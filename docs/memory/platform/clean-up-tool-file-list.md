---
name: clean-up-tool-file-list
description: clean_up.php $file_list and data/temp sweep: never list a live file name, how to verify before adding, and $temp_keep for non-regenerable temp files.
---

## Why can't the `clean_up.php` list hold a live file name?
- `$file_list` in `clean_up.php` means "safe to delete" and the Cleanup tool applies it literally. `image_editor_save.php` sat there as a LiveSite leftover; the same name was later given to the Pintura image editor's endpoint. An operator who ran Cleanup deleted a live endpoint, and every save in the image editor then returned 404.
- The symptom links to no cause anywhere: the tool says "cleaned", the editor says "Görsel kaydedilemedi. (404)", `error.log` has no line (there is no error, the file is gone).

## What do I check before touching the list?
- Before adding a name verify nothing calls it: `grep -rn "<name>" --include=*.php --include=*.js .`
- Reusing an old name for a new file reaches the same door: before giving an old name to a new file, check whether it is in the list.

## What about `data/temp`?
- The same file sweeps `data/temp` as a DIRECTORY, so any new file placed there lands in the delete-without-asking list. It is the right place for regenerable cache. Anything not regenerated, or not regenerated as the SAME file, must be written to `$temp_keep` (example: `hash_reference.json`).
