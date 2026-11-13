---
name: explorer-locale-order
description: File and Catalog Manager sort with the panel language's Intl.Collator; searches use searchFold() (İ/I/ı/i alike, ü as u), bulk-rename find foldText(); Shared groups by label
---

## How does the File and Catalog Manager order its rows?
- `sortItems()` re-sorts every list in the browser (default: name ascending), so the server's collation is lost. Its text keys (name, type, folder, account, status, access) are compared with one cached `textCollator()`: `Intl.Collator(document.documentElement.lang, {numeric: true, sensitivity: 'base'})`, the same set-up as the panel table sorter. Numbers and the ISO `date` key keep their plain compare; without `Intl` the keys are lowercased and compared by code unit.
- Before: keys were lowercased and compared with `<`/`>`, so Ç Ğ İ Ö Ş Ü ı came after Z ("Armut, Defter, Saat, Zeytin, Çanta, …").

## How do the searches match İ, I, ı and i, and ü or ç?
- `foldText()`: `toLocaleLowerCase('tr')`, then ı → i. İ, I, ı and i become one letter and no character grows (never NFD on the whole text): `bulkRenameReplace()` slices the original text by indexes found in the folded one. Used by the case-insensitive bulk-rename find, which stays accent-exact (an "s" must not replace the "ş" of a folder name). The rename clash key keeps its own Turkish fold.
- `searchFold()`: `foldText()`, then each accented Latin letter (U+00E0–U+024F, U+1E00–U+1EFF) as its base letter, one character for one (the letter's NFD first character, ø ł đ ħ ŧ from a table); æ, œ and ß stay. Used by the in-folder search (`visibleItems()`, name, description, short description), type-ahead (`typeAheadJump()`), the Move/Copy/Add-to-group picker filter (`renderTransferTargets()`) and the Send to channel filter (`renderWsSendChannels()`).
- Why (#9021): a stored file name is ASCII (`prepare_file_name()` writes Türkçe ğüş as Turkce_gus), so "türkçe" never found it, while the file picker (`ui/file-picker-search.md`) and the server's `fold_keyword_for_matching()` already dropped accents.
- Before: `toLowerCase()` made İ into i + U+0307 ("iade" missed "İade formu.pdf"); the bare Turkish fold made I into ı ("img" missed "IMG_0001.JPG", a rename of "img" changed nothing). The designer uses the same loose fold.

## How does the Shared view order its folder groups?
- `pg_explorer_compare_shared_groups()` (`view_folder_and_files_f.php`, used by `includes/explorer/handle_shared.php`) returns `pg_geo_label_compare()` on the path: the panel language's Collator, else `strcasecmp`. Before, `strcasecmp` put Çalışanlar, Özel and Üyeler after Z.
- Pinned by `tests/explorer_locale_order_test.php`.

Related: docs/memory/ui/list-label-sort.md, docs/memory/ui/explorer-keyboard.md
