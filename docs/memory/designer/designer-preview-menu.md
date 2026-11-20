---
name: designer-preview-menu
description: The designer's Preview menu beside Publish (DP23, D58, #10244): its four rows and when each shows, the ids it keeps, why Open on Site and View Page left the bars
---

## Where are Preview and Open on the site?
- In one "Preview" menu button (`#sd-preview-menu-btn`, `includes/designer_screen.php`) just before Publish in the page toolbar. The canvas bar has no Preview or Open on Site button any more, and the toolbar's View Page icon (`#sd-view-page`) is gone: it opened the same address as Open on Site (issue #10034).
- The menu (`#sd-preview-menu`) is built by `_sdPreviewMenuBuild()` on `pgUI.overlayHost()`, with the View menu's row look (`.sd-vb-menu`, `.sd-vb-row`); the toolbar would cut a menu inside it.

## Which rows does it show?
- In this order: Preview in a new window (`#sd-vb-preview`, `openPreview()`, the unsaved canvas, Ctrl+Alt+P), Open on the site (as saved) (`#sd-vb-live`, a link to `_pgPageViewUrl()`), Play as a prototype (`#sd-vb-play`), Share the prototype… (`#sd-vb-share`, `_pgPrototypeShareDialog()`).
- `_sdPreviewMenuRows({live, play, share})` decides: Preview always; Open on the site only for a saved page under its saved name (a saved draft too, as before: its private Drafts folder opens for the team); Play only when `_sdPreviewPlayUrl()` finds a page (the page on screen if it is a saved draft, else the first one the viewer shows); Share when `_pgPrototypeShareReady()`. A row the page cannot use is left out, never shown dead. Tested in `tests/designer_preview_menu_test.php`.
- `_pgTabsSyncViewButton()` sets the rows on each tab switch and save, and again each time the menu opens (a draft can change state without a tab switch). Share starts its link at the page on screen when it is a saved draft.

## How does it behave?
- Click toggles; a click outside, Esc (focus back to the button) or a window resize closes it; the arrow keys move between the shown rows. It opens under the button, right edges aligned.
- Below a 1024px window the word "Preview" goes, the icon and caret stay (as Publish's word does, `designer-narrow-screen-bars.md`). Below 768px the caret goes too and the button moves after Publish (CSS `order`): at 390px a saved draft with a long design name already overran the toolbar before this menu (View Page ended at 418px), and Publish must stay the control on screen.
- The keyboard shortcut and the command palette's Preview still call `openPreview()` directly.
