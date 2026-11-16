---
name: picture-text-translation-census-keep
description: Picture text translation PT0 census: keeping a language version (own table, source document, named file): schema, page pass cost, rename, delete, Recycle Bin, Disk Usage
---

## What holds for every way?
- `files.name` is the address and unique; a move to another folder keeps it; a rename changes it and rewrites no reference (`platform/file-manager-where-used.md`). Binning renames to `pgbin_<id>_<name>` in the bin folder; restore takes the name back.
- `get_file.php` checks the folder's access for a file and its derivatives; derivatives, WebP at the same address and the object store work per `files` row (`platform/image-derivatives.md`, `platform/file-object-storage.md`). Pixels under `data/` answer no URL.
- The swap is in the finalize pass, so each way costs one lookup per uncached page (`-census-places.md`).

## A: a table of its own?
- Schema: e.g. `file_translations` (file_id, language, picture file id or stored name, source doc_hash, state, drawn_at; unique file_id + language). An owner question (schema).
- Pass: the page's local addresses, then one query `files` joined to it by name for the language, or a per-language map kept in `data/cache` and cleared on each draw.
- Rename and move: keyed by file id, unaffected. Out of date: the source doc_hash differs from the newest save's.
- Delete: a row and its picture to remove in `pg_file_versions_delete_file()`'s callers and a daily sweep (as `pg_file_versions_sweep()`). Bin: the source's address is gone, so nothing is swapped; restore brings it back.
- Picture as a hidden `files` row: derivatives and the object store come free, but it shows in File Manager unless filtered. Picture under `data/`: no derivatives, `get_file.php` must serve it with the source's access check, Disk Usage must add it (`pg_files_disk_usage()` adds `file_versions` only).
- Private folder: follows the source when served through the source's check; a separate row must sit in the same folder.

## B: a list in the source's document?
- Schema: none; `languages: {en: {picture, doc_hash, sizes}}` in the newest save row's `doc_json`, which a save copies forward.
- Pass: costly: the newest `file_versions` row per picture on the page and a JSON decode of documents up to 1 MB each; needs a cache of its own, cleared on each save.
- A restore of an older version brings that version's list back; pruning (10 saves) drops lists with their rows. A file changed outside the editor has no live document, so its languages vanish.
- Rename and move: unaffected. Delete: rows go with the file, but language pictures stored as `files` rows stay orphans unless the delete and the sweep read the list. Bin, Disk Usage, private folder: as A by where the picture lives.
- The 1 MB document limit is shared with the per-language sizes and boxes.

## C: a file named beside the source?
- Schema: none; `banner.jpg` gives `banner--en.jpg` (rule to pick), a `files` row in the same folder.
- Pass: cheapest to build: rewrite each local name and ask once which exist (`files.name IN (…)`); no state, so "out of date" needs the source's doc_hash kept somewhere anyway (the language file's own version row).
- Rename: the sibling keeps the old stem and stops being found unless every rename door renames it too; move: the sibling stays in the old folder, with that folder's rights; delete and bin: the sibling stays, served alone, unless every door handles it.
- A person's own file of that name collides; siblings fill File Manager, Where used and Disk Usage (counted, as normal files).
- Private folder: right only while both sit in one folder.
