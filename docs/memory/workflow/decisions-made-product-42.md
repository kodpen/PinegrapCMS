---
name: decisions-made-product-42
description: Decisions list, product part 42 (2026-11-10 on): one modern picker for choosing a file or a page across the software (no "Choose from File Manager" button), ready for other records later
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries.

## How is a file or a page chosen across the software? (owner, 2026-11-10)
- Owner (2026-11-10): by one modern picker, built for the whole software. Asked whether a "Choose from File Manager" button should stand beside the searchable file and image pickers (#8488, step F3 of the file picker search plan), the owner chose none of the four options and widened the question.
- In the owner's words: "dosya seçme özelliği yazılım geneli güncellensin. sayfa seçme özelliği yazılım geneli güncellensin. tek bir modern seçici oluşturalım. çok titiz geliştirilsin ve bu günün modern dosya yöneticisine uygun bir yapıda olsun."
- What is fixed:
  - One picker replaces the separate ways of choosing a file and of choosing a page, on every screen.
  - Its structure follows today's File Manager.
  - It is ready to choose other kinds of records later; the owner named a product, a quote, a shipping method and a contact.
  - When the software's own editor replaces CKEditor, the editor's file choosing reaches the picker through a bridge of its own.
  - It is built with great care, as a plan of its own: `plans/one-picker.md`.
- F3 is not built: no button is added beside the searchable pickers (`ui/file-picker-search.md`); they are among the places the picker takes over.
- Refused with it: the name search alone, the editor's image browser on the picture pickers, a folder dialog with names only, a pick mode of the File Manager as a step of the search plan. Each keeps or adds a separate way of choosing.
- Architect (2026-11-10): until the picker's step for a screen lands, that screen keeps the picker it has; nothing is converted ahead of the plan.
