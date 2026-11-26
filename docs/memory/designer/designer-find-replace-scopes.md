---
name: designer-find-replace-scopes
description: The designer's Find and Replace window (DO25): This page, All pages and Code files, the list per page before Replace All, one undo step a page, pages left out and named, the matcher PGFindReplace
---

## Where is it?
- Ctrl+F and the canvas bar's Find call `openFindReplaceModal()` in `style_designer.src.js`, which only runs the part `findreplace` (`includes/designer_parts/findreplace.php`): the matcher `assets/js/pg_find_replace.js` (`window.PGFindReplace`, pure) and the window `assets/js/style_designer_findreplace.js`. `_frWalk()` stays in the core for the class readers.
- No new bar button; the window opens on "This page", every time (no remembered scope).

## What does each scope search?
- This page: the open page's words (`PGFindReplace.TEXT_PROPS` of every node), as before DO25.
- All pages: every page of the design loaded in the editor (`K.pages`, the active one through `_pgTreeOf()`). Below the counts, one row a page with its matches; the pages left out after them, struck through, with the reason. A row opens that page's tab.
- Code files: the code view's loaded file tabs (not the page tab), searched with `^`/`$` per line like the code view's own search. A read-only tab is listed as left out. With no file tab open the window says the files open in the code view are searched.
- Never searched: classes, ids, link addresses, bindings, the inside of a placed shared component (its own row; edit it in the component) and collection records (content, changed where records are edited).

## Which pages are left out?
- A page another editor has open for editing: `_pgTabHolder(p)` from the presence beat (`designer-collab-locking.md`), and the open page while this tab is in view mode (`_collab.mayEdit === false`, the holder's name). Named in the row: "Open for editing by …".
- A page without content rights for its folder (`p.access === 'locked'`).
- The save path still checks the lock (`pg_collab_may_edit_page()`); the list only says it before the replace.

## How is it undone?
- One undo step a page: the open page through `saveState()`; any other page gets `{ tree, shared: {}, assets: null }` pushed on its own `undoStack` (redo cleared, 50 kept), which `_pgTabsSwitch()` takes up. Undo on that page takes back what was replaced there.
- A code file gets the new text with `ed.setValue(text, true)`: one step of that editor's undo; the tab shows unsaved and is saved as usual (Ctrl+S).
- Nothing is written to the server: Save or Publish sends the changed pages, which the dirty check now sees.

## How does matching work?
- `pattern()` refuses a regular expression that does not compile and one that matches empty text. A plain replacement goes in as typed (`$&`, `$1` are text); a regular expression reads its groups. A whole word uses letters of any script (`\p{L}`), so "çerez" is not found inside "çerezler".
- Tests: `tests/designer_find_replace_test.php` (Node).
