---
name: explorer-locale-order
description: File and Catalog Manager (view_folders.php) sort text keys with the panel language's Intl.Collator and match with foldText() (İ/I/ı/i alike); the Shared view's groups sort by pg_geo_label_compare()
---

## How does the File and Catalog Manager order its rows?
- `sortItems()` re-sorts every list in the browser (default: name ascending), so the server's collation is lost. Its text keys (name, type, folder, account, status, access) are compared with one cached `textCollator()`: `Intl.Collator(document.documentElement.lang, {numeric: true, sensitivity: 'base'})`, the same set-up as the panel table sorter. Numbers and the ISO `date` key keep their plain compare; without `Intl` the keys are lowercased and compared by code unit.
- Before: keys were lowercased and compared with `<`/`>`, so Ç Ğ İ Ö Ş Ü ı came after Z ("Armut, Defter, Saat, Zeytin, Çanta, …").

## How do the searches match İ, I, ı and i?
- `foldText()`: `toLocaleLowerCase('tr')`, then ı → i. İ, I, ı and i become one letter and no character grows (never NFD): `bulkRenameReplace()` slices the original text by indexes found in the folded one.
- Used by the in-folder search (`visibleItems()`), type-ahead (`typeAheadJump()`), the Move/Copy/Add-to-group picker filter (`renderTransferTargets()`), the Send to channel filter (`renderWsSendChannels()`) and the case-insensitive bulk-rename find. The rename clash key keeps its own Turkish fold.
- Before: `toLowerCase()` made İ into i + U+0307 ("iade" missed "İade formu.pdf"); the bare Turkish fold made I into ı ("img" missed "IMG_0001.JPG", a rename of "img" changed nothing). The designer uses the same loose fold.

## How does the Shared view order its folder groups?
- `pg_explorer_compare_shared_groups()` (`view_folder_and_files_f.php`, used by `includes/explorer/handle_shared.php`) returns `pg_geo_label_compare()` on the path: the panel language's Collator, else `strcasecmp`. Before, `strcasecmp` put Çalışanlar, Özel and Üyeler after Z.
- Pinned by `tests/explorer_locale_order_test.php`.

Related: docs/memory/ui/list-label-sort.md, docs/memory/ui/explorer-keyboard.md
