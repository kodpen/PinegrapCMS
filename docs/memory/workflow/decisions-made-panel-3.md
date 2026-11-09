---
name: decisions-made-panel-3
description: Decisions list, panel part 3: old browsers get a non-blocking, session-closable "update your browser" notice in the panel and its standalone screens
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue and the date.

## What does an old browser get in the panel? (owner, 2026-10-25)
- Old browsers get a non-blocking, session-closable "update your browser" notice in the panel: CSS.supports tests for `light-dark()`, `selector(:has(*))` and `oklch(from …)`; below Chrome/Edge 123, Firefox 128, Safari 17.5. Reason: Bootstrap 6 and the panel stylesheet rely on them, and those browsers draw transparent dialogs and menus, invisible empty fields and wrong toggle states (`plans/bootstrap-6-findings.md`). The panel keeps working: no blocking screen, no list of supported browsers. Built as `ui/panel-old-browser-notice.md`.
