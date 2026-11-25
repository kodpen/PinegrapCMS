---
name: decisions-made-workspace-51
description: Decisions made, workspace part 51 (2026-11-24 on): a channel's Tasks and Board tabs become one tab "Board" holding tasks only, in a newer look, with ready automations on the board
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-workspace-50.md`.

## Do a channel's Tasks and Board tabs stay two tabs? (owner, 2026-11-24, to the architect in its session)
- No. His words: "kanallarda Görevler ve Pano aynı özelliği iki farklı şekilde sunan 2 özellik. görevlere odaklı. panoyu görevler ile birleştirsek mi?" He chose from the drawings: one tab, named "Board" ("Pano").
- The tab has two views, "Board" and "List". The List view is the Tasks tab as it is today, so its look decisions stand (task cards, 2026-11-10 and 11). The search, the filters and "New task" are shared.
- The architect's reading, not his words: a room without the board (a discussion, a guest room) keeps its "Tasks" tab; an old address of the Tasks tab opens the List view.

## What does the board show? (owner, same day)
- Tasks only. He first thought of keeping decisions and pinned items as filterable cards ("Kanal Panosu"), saw both drawn and chose the board of tasks.
- The grouping "By kind" goes, with its read-only columns of decisions, files, the pinned message and the summary. Decisions and Files keep their tabs; the pinned message and the summary are read in the Summary tab.
- The board of a workflow table is not touched.

## How should the board look? (owner, same day)
- His words: "panolar bu gün kanalların tema yapısıyla uygun ancak görsel açıdan zayıf ve ne işe yaradığını anlamak zor."
- It keeps the channel's theme and gets the look he saw drawn: four figures over the board saying what it holds, clearer columns and cards. As built: `workspace/workspace-channel-board-look.md`.

## Do automations come to the board? (owner, same day)
- Yes, to the board for now. His words: "panoda otomasyon entegrasyonlarımızda yok, otomasyonları ufaktan sistem geneline yaymayada başlayabiliriz"; and on the drawing: "şimdilik pano için yapalım ileride genişleteceğimiz notu düşülsün."
- A button "Automations" on the board opens a drawer of ready rules, each turned on and off with a switch. Every rule is a flow of the Flows screen: no table and no column.
- The note he asked for: the same drawer is meant for other screens later (orders, forms, contacts) as "this screen's automations". That is a plan of its own, written when he opens it; nothing of it is built now.

## Where is the work?
- Built in BM1 to BM6 (#11555, #11641, #11670, #11567, #11696 and the close-out); the plan is closed. Memory: `workspace/workspace-channel-board-tab.md`, `-look.md`, `-rules.md`, `-automations.md`, `-drawing.md`.
