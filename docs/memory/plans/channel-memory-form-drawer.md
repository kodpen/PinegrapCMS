---
name: channel-memory-form-drawer
description: Plan (owner 2026-11-28): the Memory tab's entry form opens in a form drawer beside the list instead of replacing it; one step, MF1
priority: 6
---

## What is asked?
- Owner (2026-11-28, agent channel message 15440, with a picture of the tab): "kanal belleği nasıl sıfırlandı bilgim yok ayrıca bu ekran da modernize edilmeli şu anda form alanı gibi garip duruyor".
- The memory was not reset: its 12 records were read over the API the same hour. He was looking at the "New entry" form, which clears the tab and draws itself in the list's place (`form()` in `workspace_memory.js` starts with `h.clear(root)`), so the memory looks empty.
- The architect's answer (message 15444): the list stays, the form opens in a drawer. He did not object.

## How is it put together? (the architect's, in the look)
- "New entry" and a row's Edit open the look's form drawer (Design examples, "Form drawer"; `ui/screen-composition-rules.md`) from the right, about 46rem wide, full width on a phone. The tab behind it keeps its list, its search words and its folds.
- Head: "New entry" or "Edit entry", under it one quiet line naming where it is kept: "This channel's memory" or "General memory, shared by every agent channel.".
- Body, one column, the fields of today in today's order: Title, Project (with its list of known projects and its help line), Text (8 rows, growing with what is typed), Tags, Expires on, the Pinned switch. Nothing is added or taken away.
- Foot: Save filled and Cancel quiet on the right; on Edit, Delete (`pg-button-danger`) on the left with the row's own confirm.
- A refused field is said under that field and the drawer stays open; Esc or Cancel with changed fields asks before closing, as the look's drawers do.
- After Save the drawer closes, the list is read again, the saved row is scrolled into view and its ground is tinted for a moment.
- `/memory` without a text opens the same drawer for the channel.
- In the General memory drawer (`workspace/channel-memory-general.md`) the form opens as a drawer over it, as an agent's Edit does over Settings (`ui/settings-assistants-long-lists.md`).

## Which step?
- MF1 (S, under 400 product lines): the above in `workspace_memory.js` and the stylesheet's source; no server change, no new text beyond "Edit entry" and the kept-in line (`php tools/tr_add.php`). `tests/workspace_channel_look_test.php` gains the check that opening the form no longer clears the list. `workspace/workspace-memory-tab.md` ("What does the form hold?") corrected; this file deleted in the same PR.

## What does it keep?
- `ws_memory_list`, the save and delete actions and their checks; the rows, headings and search of GM3a and MS3; who may write.
