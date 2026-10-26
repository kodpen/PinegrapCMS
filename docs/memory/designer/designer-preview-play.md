---
name: designer-preview-play
description: Links in the designer's Preview (#1867): a page link plays the open tab of that name, unsaved too; others open the site in a new tab; the Hotspots toggle
---

## What does a link in the Preview do?
- A plain click on a web link one path segment below the site address whose name is an open tab's (`_sdPreviewPlayMap()`: the typed name, the active tab's from `#sd-page-name`, then a renamed tab's saved name, '' the home tab; no locked tab; case-insensitive) posts `{pgPreviewPlay: key}` to the opener; `_sdPreviewPlay()` switches to the tab (`_pgTabsSwitch()`) and refreshes at once with `_sdLive.top` (no scroll restore). The editor's listener takes only its own window and origin.
- Any other web link opens a new tab (`noopener`), so the window keeps the preview; the open tab's own link scrolls to the top. Fragments, toggles (cancelled first), `mailto:`/`tel:`, modified clicks: unchanged.
- The "Hotspots" button (`#pg-pv-hotspots`) outlines `data-pg-pv` links (tab solid, site dashed), state in the window's sessionStorage `pgPreviewHotspots`. Script: `_sdPreviewPlayHarness()`, after `_sdPreviewHarness()`; tests/designer_preview_play_test.php. A test that cuts `_sdPreviewDocHtml()` out stubs both harnesses.

Related: docs/memory/designer/designer-live-preview.md, docs/memory/designer/designer-prototype-viewer.md
