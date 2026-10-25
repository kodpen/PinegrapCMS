---
name: bootstrap-6-panel-steps
description: Steps B0-B12 of plans/bootstrap-6-panel.md with dependencies and status (each done step names its PR); claim as [plan:bootstrap-6-panel] B<n>
---

## Which steps run before the switch (on 5.3.8, nothing visible changes)?
- Done: B0a, B0b, B1a, B1b, B2a, B5 (`bootstrap-6-panel-done.md`).
- B2b and on: move the calls and listeners onto pgUI area by area (cold areas first; `check_bs6.php` counts what is left), fix the 3 parse-time scripts (welcome.php, api_settings.php, erp_lists.js), read open state with `isOpen()`, add `preventDefault()` to the settings drawer's Escape handler. Leave style_designer.js to B7.
  - B2b slice 1 (#1692): dashboard (`welcome.php` parse-time popover, widget 1 menu), offers template, product builder, sale screen (`add_order.js`), ERP (`erp_lists.js` parse-time drawer, `erp_account_quick.js`, `erp_accountant.php`, `view_parasut_inbox.php`), translations, Cloudflare, form item view, AI field marks, tour. Left: `backend.src.js`, workspace, `view_folders.php`, `designer_home.js`, `page_designer.js`, `api_settings.php`, settings, `seo.php`, `codemirror_modal.js` and `image_editor_modal.js` (their own modal markup: `pgUI.dialog.build()`), `image_editor_edit.php`, `includes/designer_screen.php`.
- B3 Token layer: panel CSS reads `--pg-*` tokens, mapped to v5 variables in one block (`ui/panel-css-tokens.md`). B3a the block, the v5 `-rgb` uses and the accent role variables: done (#1694). B3b the neutral variables in backend.src.css. B3c the other panel stylesheets and inline style blocks.
- B4 Collisions: drop `class="menu"` from `#menu`, rename `<span class="btn-text">` and the own `.submenu`; `data-bs-display="static"` on the account and notification toggles.
- B6 Wrappers with a switchable engine (v5 engine now): `pgChips`, `pgCombobox`, `pgDatePicker`, `pgDateTimeField`; call sites move to `data-pg-*` hooks.
- B7 Visual editor stage 0: `/* @pg-site-output */` on the 54 site-output members of style_designer.js with a CI test, the chrome look pinned in style_designer.css, editor calls through `pgUI`.

## What happens at and after the switch?
- B8 Switch: `bootstrap-6-switch.md`.
- B9 Component swaps on v6, one claim each (`bootstrap-6-components.md`): B9a Chips, B9b Combobox, B9c Datepicker and time, B9d OTP, B9e Range, B9f form-adorn, B9g Stepper, B9h hover-lift, B9i Strength, B9j Avatar, B9k research proposals.
- B10 Token tuning, B11 redesign, B12 jQuery UI out of the panel: `bootstrap-6-redesign.md`.

## Where do I start?
- After the claim check, take the lowest open step whose dependencies are done and that no open PR holds; independent steps run in parallel sessions, one draft PR each; a step over about 1,000 product lines goes in several PRs, one after the other (`workflow/working-mode.md`). B1-B5 are independent; B6 needs B2a; B7 needs B1a and B2a; B8 needs B1a-B7; B9 needs B8 (B9c also B5); B10 needs B9; B11 needs B10; B12 needs B9c.
- Pre-switch PRs show their screens unchanged on the B1b screenshots (`ui/panel-screenshots.md`); later PRs show before and after. Mark a step done here with its PR numbers; once all its parts are done, its line moves to `bootstrap-6-panel-done.md`.
