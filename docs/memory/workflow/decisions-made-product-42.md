---
name: decisions-made-product-42
description: Decisions list, product part 42 (2026-11-10 on): one modern picker for a file or a page (no "Choose from File Manager" button), its screens, archived pages; the image editor's clip timeline and length
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

## Are the picker's drawn screens approved? (owner, 2026-11-10)
- Owner (2026-11-10, #8514): yes, with one correction: "Onay; arşiv seçilemez." The pictures of `plans/one-picker/` (#8511) are the layout and behaviour PK2-PK6 build.
- An archived page (a page in an archived folder) is shown with its mark and cannot be chosen, like a binned one; pictures 03 and 11 draw it as choosable. Today's rule stays on every page picker: such a page is not offered as a new choice, a stored one stays in the field, marked (`ui/stored-choice-kept.md`).
- Why: visitors get 410 for an archived page (`get_page.php`), so a menu item, a next page or the home page would point at a page nobody can open.
- Architect (2026-11-10): a kind with the public condition lists only public files, as `pg_file_picker_where()` does; where PK4 lists a binned page is the step's call.

## When does a clip edit change the image editor document's length? (owner, 2026-11-10)
- Owner (2026-11-10, #8521): the clip timeline (V3, #8510) is approved as built: "Onay; ek adımda düzeltilsin."
- The rule to change: the length follows the clips, but a clip edit never ends the document before another layer's motion. As built, every clip edit sets the length to the last clip's end, which cuts an end card or a text after the video, and the length slider holds only until the next clip edit.
- Step V3b of `plans/image-editor-video-steps.md` builds it, with the filmstrip and waveform of screen 35 that V3 left out (`ui/image-editor-clip-timeline.md`).
