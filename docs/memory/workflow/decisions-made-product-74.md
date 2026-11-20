---
name: decisions-made-product-74
description: Decisions list, product part 74 (2026-11-17): the owner's looks of polls 139 (place map list), 140 (picture drawing queue) and 141 (the Courses screen); managers and above open the Courses screen
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-73.md`.

## Is the place map block's list form accepted? (owner, 2026-11-17, agent channel poll 139, message 8930)
- "Evet, böyle çıksın" to one sheet of six pictures (MP3 #10062): the heading, the search box and the place cards (name, address, opening hours, phone link, "Yol Tarifi") on a page; the search by district, postcode or name; "Aramanıza uyan bir yer yok." when nothing matches; one card a row on a phone; the block on the designer's canvas with a sample map picture and sample cards.
- The cards use the site theme's own card and button styles. The map and "Haritayı göster" are MP4's and come to him apart (`plans/place-map.md`, "Whose look?").

## Is the picture drawing queue accepted? (owner, 2026-11-17, agent channel poll 140, message 8977)
- "Evet, böyle çıksın" to one sheet of six pictures (PT5 #10266): "Bekleyen görselleri çiz" with its count on the Pictures group; the running view (count left, "Durdur", each row's picture, language and state); the stopped view ("Durduruldu: 3 kaldı", "Devam Et"); an "Elle bakılmalı" row with "Tasarım Tuvali'nde düzenle"; the finished view; on a phone the name whole and the pill under it.
- The poll told him one line was still coming: the end line counts the rows to look at by hand ("…; 2 görsele elle bakılmalı"). What the queue does is in the memory file its PR brings.

## Is the Courses screen accepted? (owner, 2026-11-17, agent channel poll 141, message 9051)
- "Evet, böyle çıksın" to one sheet of eight pictures (CO2 #10280): the list with a course's problems under its name and the state pill; the editor (name, private folder, classroom page, "Dersler sırayla", "Sertifika", the lessons table); the learners with "CSV indir" and "İlerlemeyi sıfırla"; a course with a problem named in the Summary card; the list, the lessons and the learners on a phone, nothing scrolled sideways.
- The poll told him one line was still coming: the lessons table's head in the field label's size and ink at 1440, as at 390.

## Who may open the Courses screen? (architect, 2026-11-17, agent channel message 9031)
- Managers and above (role below 3): `validate_area_access($user, 'manager')` on both screens and the menu row. A course ties a product that opens a folder, the folder's access and the members' progress, which roles 0 to 2 already manage; it is no design tool and no site setting.
- Role 3 never opens it by a folder's edit right alone; a right of its own would be a later yes (`core/permission-columns.md`).

Related: docs/memory/workflow/decisions-made.md, docs/memory/workflow/decisions-made-product-73.md
