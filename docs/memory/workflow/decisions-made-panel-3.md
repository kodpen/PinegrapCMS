---
name: decisions-made-panel-3
description: Decisions list, panel part 3: Bootstrap 6 choices of 2026-10-25 (multiselect kept, liked screens, Stepper, Avatar, password meter, old browser notice); the Site Settings dialog's drawer (2026-11-11)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the date and the reason.

## Why is jquery-multiselect-checkbox no Bootstrap 6 component? (owner, 2026-10-25)
- The owner kept it when the panel moved to Bootstrap 6, and the component swaps left it out on purpose (`ui/bootstrap-6-own-widgets-kept.md`). Not a missed swap; do not replace it without the owner.

## Which screens must never look worse? (owner, 2026-10-25)
- Workspace, `product_builder`, `edit_user`, `welcome`, `view_product_groups`, `view_offers`, `edit_offer`, header, footer and the main menu: the owner likes them as they are, so a change keeps or improves their look. They were the reference of the edit screen redesign and were not redone (`ui/edit-screen-wide-layout.md`). The visual editor keeps its look.

## Which Bootstrap 6 components did the owner name? (2026-10-25)
- `edit_offer` and the installer use the Stepper (`ui/step-rail.md`); avatars are the v6 Avatar with theme colours (`ui/panel-avatar.md`); the password meter is strict only while "Require Strong Password" (`config.strong_password`) is on (`ui/password-strength-js.md`). Not to be swapped back for own widgets.

## What does an old browser get in the panel? (owner, 2026-10-25)
- Old browsers get a non-blocking, session-closable "update your browser" notice in the panel: CSS.supports tests for `light-dark()`, `selector(:has(*))` and `oklch(from …)`; below Chrome/Edge 123, Firefox 128, Safari 17.5. Reason: Bootstrap 6 and the panel stylesheet rely on them, and those browsers draw transparent dialogs and menus, invisible empty fields and wrong toggle states (`ui/bootstrap-6-browsers.md`). The panel keeps working: no blocking screen, no list of supported browsers. Built as `ui/panel-old-browser-notice.md`.

## What opens from a card inside the Site Settings dialog? (owner, 2026-11-11)
- A drawer from the dialog's right edge, inside the dialog, never a second dialog over it. A trial: first Cron Jobs (`plans/settings-workspace-move-steps.md`, SD1); he looks at it on dev before the other card dialogs follow.
- Owner (2026-11-11): "site ayarları modalı içinden açılan 2. modallar örneğin: cron örnekleri modalı scroll yapılamıyor ve metinleri modal dışına taşıyor. modal içinden açılan modalları 2. bir modal katmanı eklemek bizim için uygun olmayacaktır, ayarlar kaydetmeden terkedilme riski var o yüzden modalın sağından açılan bir drawer denemesi yapabiliriz."
- Reason: a second dialog layer lets the settings be left without saving; the Cron Jobs dialog could not be scrolled and its lines ran out of it.
