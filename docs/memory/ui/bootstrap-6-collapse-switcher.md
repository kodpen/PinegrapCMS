---
name: bootstrap-6-collapse-switcher
description: Sections behind a panel switch (input.collapse-switcher) on Bootstrap 6: why they stayed 0 px high, the .show that pgCollapseSwitcherShow() adds with the display, why switch callouts lost their arrow
---

## Why did sections behind a switch never open?
- v6 has two unlayered rules: `.collapse{overflow-y:clip}` and `.collapse:not(.show){display:none;block-size:0}`. The switcher code (`input.collapse-switcher` in `assets/js/backend.src.js`) only called jQuery `.toggle(returned)`, an inline `display:block`, and never added `.show`, so a plain `.collapse` target was shown at height 0.
- Seen on: Add User / Import Users / Edit User "Show badge next to username", SMTP Settings Campaign SMTP fields, form field "Default Value" (`show-reverse`), page form item "Allow submitter to edit…" (#2585).

## What sets .show now?
- `pgCollapseSwitcherShow(target, shown)` toggles `.show` on `.collapse:not(.popover)` targets. The delegated click/change handler and `pgSyncCollapseSwitchers()` (first paint) call it right after `.toggle(returned)`; the sibling-radio loop calls it with `false` when it hides another radio's section.
- A callout (`.collapse.popover`) keeps its own unconditional `.toggleClass('show')` in `pgSyncCollapseSwitchers()`.
- `select.collapse-if-selected` already went through `pgUI.collapse`, which sets `.show` itself.

## Why did switch callouts lose their arrow?
- The same `overflow-y:clip` cut off `.popover-arrow`. The `.collapse.popover` rule in `backend.src.css` adds `overflow: visible !important; transition: none !important;`: a layered `!important` beats the unlayered normal rule, and no transition keeps the content from spilling out while it opens.

## What else does the clip affect?
- A menu placed absolutely inside a switch panel is cut off at its edge: `pg_fields.js` gives a Combobox inside `.collapse` `data-bs-strategy="fixed"` (`pg-combobox.md`).

## How was it checked?
- Computed style in headless Chromium with the v6 dist and `backend.src.css`: `.collapse` with inline `display:block` 0px high, with `.show` 24px; the callout `overflow-y` visible after the fix. The switch screens themselves were not opened in a browser. Pinned as strings by `tests/bs6_collapse_switcher_test.php`.

Related: docs/memory/ui/bootstrap-6.md, docs/memory/ui/panel-layout-pitfalls.md, docs/memory/ui/bootstrap-6-sibling-toggles-fullscreen.md
