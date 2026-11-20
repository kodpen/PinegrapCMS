---
name: decisions-made-product-79
description: Decisions list, product part 79 (2026-11-19 on): the own Office code replaces PHPExcel and grows to full scope: more previews, wider editing, shared Excel export and import
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-78.md`.

## How far does the product's own Excel and Word code go? (owner, 2026-11-19, channel messages 9747 and 9751; poll 156, message 9752)
- "kaliteli bir planla tam geçişi yapalım ve yazılım geneli kullanım alanını genişletelim. Dosya yönetici ve kanallarda önizleme ve düzenleme açısından özellik genişlemesi yapalım."
- Poll 156: "Tam kapsam". Its text: previews of .xls, .ods, .csv and .pptx (slide text and pictures); in Excel rows, columns and sheets added and removed, bold, colour and number format, sort, filter and find; "Yeni tablo" and "Yeni belge" in the File Manager and a channel; in Word bold, italic, headings, lists, paragraphs added and removed; a shared "Excel'e aktar" on panel lists and a shared reader with a sheet choice in imports. PHPExcel goes, no library is added.
- Not chosen: the same without Word editing and .pptx; the move alone.
- It replaces the scope of 2026-10-19 ("simple editing", .pptx and binary files out) in `platform/office-files-scope.md` as the steps land.
- The architect's reading, not his words: the steps and what is left out (`plans/office-own-code.md`).

Related: docs/memory/workflow/decisions-made.md, docs/memory/platform/office-files-scope.md
