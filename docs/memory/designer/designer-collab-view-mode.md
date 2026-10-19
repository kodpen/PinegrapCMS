---
name: designer-collab-view-mode
description: Designer view mode when another person holds the lock: _sdMayEdit, _sdGuardItem, hidden panels, one-item context menu, chat bubble, avatars and the narrow note_save exception.
---

## How does the editor decide a node may be edited?
- `_sdMayEdit(node)` asks per node. Today the answer is the page lock; preview mode will change this function's **answer**; no second gate is added beside it. Call sites: `handleDrop`, inline text editing, `onDelete`, two keyboard listeners.
- **`_sdGuardItem` checks the lock first, then the role.** `_sdIsContentLevel()` asks *which node* may be touched; the lock says *none*. Role-level items (lock, convert to shared component, editable field) must go through this guard too, otherwise an admin in view mode could do everything from the menu.

## What is hidden in view mode?
- Editing panels are hidden (`display:none`), not dimmed: component palette, right property panel, bottom panel strips (`.sd-bp-*`). Only Overview remains and must stay clickable (you find the element to leave a note on there).
- Page settings are locked with `inert`, every page pane of the settings modal with its own note, not field-by-field `disabled` (unlocking would also enable fields that were already disabled).
- Everything is written **two-way** in `_collabSetMode()`; the lock may be released while the editor is open.
- The bottom panel itself is `#sd-bottom-panel` (class `sd-panel-bottom`; the two names are reversed). Hiding only the strips inside leaves an empty band at the bottom.
- **The view-mode right-click menu has one item: Add Note** ("Not Ekle"). A menu that never opens reads as a broken right click; a faded list says the same and takes longer.
- **The view-mode strip cannot be closed**; the modal appears once per page (`_collab.warned` + `lockedPage`).

## How is the chat bubble shown in the editor?
- Printed via `pg_chat_launcher_html()`. This screen does not call `output_footer()` (flex app at viewport height; the footer bar causes scrolling), so nothing tied to the footer arrives automatically.
- Bubble position uses rules prefixed `body.design`: `chat.php` prints its own `<style>` at the end of the document, so an equal-specificity rule loses by source order.
- **Avatar uses `title`, not a popover** (a popover is invisible under the modal backdrop). Name/avatar come from `pg_chat_user_brief()`; if chat is set up the avatar calls `window.pgChatOpenWith(userId)`.

## Can a view-mode user write comments?
- **No comment layer is written.** The write path is the editor's existing node note (`props._notes`).
- Only exception: endpoint `designer/note_save` writes one page, one node, one property, never touches `page_tree_code`: a deliberate, narrow exception to the lock.

Related: docs/memory/designer/designer-collab-locking.md
