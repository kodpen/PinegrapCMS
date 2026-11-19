---
name: channel-decisions-files-tabs
description: Plan (owner 2026-11-19): a channel's Decisions tab as one list of wide cards, long ones folded, no sideways scroll; its Files tab with a count and a gallery light with hundreds of pictures; TD1, TD2
---

## What did the owner ask?
- Owner (2026-11-19, in the architect's window), on the agent channel's Decisions tab: "kararlar ekranı yatay kaydırma ve düzeleri karışık olduğu için hem ekran dışına taşıyor hemde yatay kaydırma oldukça zorlayıcı. bu alanda karar uzunsa katlansın ve kararlar dikey kaydırmalı kartlarda olsun(daha geniş kartlar olsun) bu alan modernleşsin."
- On its Files tab: "dosyaların tab üzerinde dosya sayısı sayacı yok ajan kanalında 298 görsel var bu ekran oldukça takılıyor. tasarım olarak oldukça hoş ama kasmayı azaltacak bir yol varsa iyi olur."

## What is wrong today? (read in the code on 2026-11-19, not tried)
- Decisions: `.ws-pane-decisions` is the scrolling pane itself and has a set height, so `columns: var(--ws-flow-col)` (`assets/css/backend.src.css`) puts each further column beside the last and the pane grows sideways. The Tasks tab flows the same way inside a box of its own and does not.
- Files: the tabs mark Decisions, Tasks and Summary with a count (`counts.` in `assets/js/workspace.js`), Files not. Every gallery tile loads the picture's original file (`img.src = message.file.url` in `drawFiles()`): lazily, but full size. `ws_channel_file_rows()` reads the newest 300 files only, so older ones leave the tab unseen.

## What is built?
- TD1 (one PR, no schema). Decisions: one list down the pane, a card as wide as the pane up to about 72rem; a decision longer than about eight lines is folded behind Show more / Show less, its head line and Show in conversation always seen; one column on a phone as now. Files: the count beside the tab's name; the gallery drawn 60 tiles at a time with Show more, tiles of a set size so nothing jumps.
- TD2 (one PR). Gallery tiles ask a small copy of the picture, not the original, and the server gives the files in pages, so the 300 limit goes. The worker first looks in `platform/` memory for the resize the site already has; a new route or a stored small copy is a question to the architect.
- Each step: its unit tests (`tests/workspace_channel_look_test.php`, `tests/workspace_files_tab_test.php`) and one composed before and after picture to the architect; the owner looks on dev after it lands.

## What is decided?
- Architect (2026-11-19): one column, the fold at about eight lines and 60 tiles a page are the reading of the owner's words, changed on his look if he wants. The two columns of CL4 (`workspace/workspace-channel-panes.md`) end with TD1; the Tasks tab keeps its columns.

## How does the plan close?
- `workspace/workspace-channel-panes.md` and `workspace/workspace-channel-files-tab.md` say the new look and this plan is deleted.
