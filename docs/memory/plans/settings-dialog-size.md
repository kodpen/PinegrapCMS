---
name: settings-dialog-size
description: Plan (owner 2026-11-20, urgent, in the architect's window): the Site Settings dialog wider and taller on a desktop, never full width; one step, SZ1
priority: 8
---

## What is asked?
- Owner (2026-11-20, in the architect's window, urgent): "ayarlar modalı daha büyük ve uzun olsun (tam genişlik değil)". Recorded in `workflow/decisions-made-panel-11.md`.

## What is there today?
- From 1024px up the dialog floats in the middle: width `min(1040px, calc(100vw - 3rem))`, height `min(680px, calc(100vh - 3.5rem))` with its `dvh` twin, the list 232px (`ui/settings-modal-layout.md`). Below 1024px it is full screen.

## What did the architect decide? (2026-11-20)
- Width `min(1240px, calc(100vw - 6rem))`, height `min(880px, calc(100vh - 3.5rem))` and the same in `dvh`. The screen stays in sight at both sides at every width from 1024px (at least 3rem a side), so it is never full width.
- The list stays 232px; the pane takes the room. A card's drawer keeps its own width. Nothing below 1024px changes.

## Which steps are there?
- SZ1 (S): the two values and the `dvh` twin in `assets/css/backend.src.css` (the bundle is built by the landing, never the PR); the tests that hold the old values; `ui/settings-modal-layout.md` corrected in the same PR; this plan deleted in it.
  - Seen on dev at 1024, 1366, 1440 and 1920 px wide and 768, 900 and 1080 px high: the pane alone scrolls, the save bar stays in sight, a card's drawer opens inside the dialog. The PR says what was seen.
  - `Decision:` the owner's words in the architect's window, 2026-11-20 (`workflow/decisions-made-panel-11.md`).

## What binds it?
- The rules of `ui/settings-modal-layout.md`: every flex item of the scroll chain keeps `min-height: 0`; `100dvh` after `100vh` inside `@supports`; the height agrees with the dialog's margin.
- `backend.src.css` is entered by other plans' steps too (KA2, KD2, AT1): never two PRs in it at once; this step is the smallest and goes first.
