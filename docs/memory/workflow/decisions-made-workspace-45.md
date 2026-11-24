---
name: decisions-made-workspace-45
description: Decisions made, workspace part 45 (2026-11-21 on): a sheet or document in a channel opens in the preview by its name, Preview and Download carry their words, a sheet can be saved as a Data table
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-workspace-44.md`.

## How does a spreadsheet or document open in a channel? (owner, 2026-11-21, agent channel message 11700)
- Owner, with a .xlsx he had posted: "kanalda xlsx gösteren bir düğme yok önizleme yeni sekmede tarayıcıda açıyor ve indiriyor. bu dosyalar görüntülenebilir ve istenirse veri olarak kaydet seçeneği olmalıydı".
- What he met: the preview was there, as an eye icon with no word at the end of the file's row; the file's name was a link to the file itself, so a click opened a new tab and downloaded it (`platform/office-files.md`).
- Architect (2026-11-21): for every file the channel can show in its preview window (Word, Excel, .ods, .xls, .csv, .pptx, PDF, text), in a message's file card and in the Files tab:
  - A click on the name or the card opens the preview window; it never opens the file's address.
  - The row's buttons carry their words: "Preview" (quiet small, the eye icon before it) and "Download" (quiet small); under 576 px the icons alone, each with its name for a screen reader.
  - A file the channel cannot show keeps the name as its download link, as today.
- "Save as data": in the preview window's head for a sheet (.xlsx, .xls, .ods, .csv, .tsv), a quiet button beside "Save a copy". It starts the file import of the Data tables (`workspace/workflow-table-import.md`) on that file and the sheet shown: a new table in this channel with guessed column types, or rows added to a table the person may write, confirmed first as that import confirms. For who may post in the channel and make a table there; nobody else sees the button.
- The file stays as it is; the table does not follow the file afterwards. No schema: the import's own writes.

Related: docs/memory/workflow/decisions-made.md, docs/memory/platform/office-files.md, docs/memory/workspace/workflow-table-import.md
