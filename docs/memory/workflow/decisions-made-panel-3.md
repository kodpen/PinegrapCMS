---
name: decisions-made-panel-3
description: Decisions list, panel part 3: Bootstrap 6 choices of 2026-10-25 that stay: jquery-multiselect-checkbox kept, the liked screens and the visual editor's look, Stepper, Avatar, the password meter
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the date and the reason.

## Why is jquery-multiselect-checkbox no Bootstrap 6 component? (owner, 2026-10-25)
- The owner kept it when the panel moved to Bootstrap 6, and the component swaps left it out on purpose (`ui/bootstrap-6-own-widgets-kept.md`). Not a missed swap; do not replace it without the owner.

## Which screens must never look worse? (owner, 2026-10-25)
- Workspace, `product_builder`, `edit_user`, `welcome`, `view_product_groups`, `view_offers`, `edit_offer`, header, footer and the main menu: the owner likes them as they are, so a change keeps or improves their look. They were the reference of the edit screen redesign and were not redone (`ui/edit-screen-wide-layout.md`). The visual editor keeps its look.

## Which Bootstrap 6 components did the owner name? (2026-10-25)
- `edit_offer` and the installer use the Stepper (`ui/step-rail.md`); avatars are the v6 Avatar with theme colours (`ui/panel-avatar.md`); the password meter is strict only while "Require Strong Password" (`config.strong_password`) is on (`ui/password-strength-js.md`). Not to be swapped back for own widgets.
