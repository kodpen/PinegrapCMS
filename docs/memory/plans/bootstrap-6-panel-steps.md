---
name: bootstrap-6-panel-steps
description: Steps B0-B12 of plans/bootstrap-6-panel.md with dependencies and status (each done step names its PR); claim as [plan:bootstrap-6-panel] B<n>
---

## Which steps run before the switch (on 5.3.8, nothing visible changes)?
- Done: B0a, B0b, B1a, B1b, B2a, B2b, B3, B5 (`bootstrap-6-panel-done.md`).
- B4 Collisions: drop `class="menu"` from `#menu`, rename `<span class="btn-text">` and the own `.submenu`; `data-bs-display="static"` on the account and notification toggles.
  - Slice 1 (#1738): `btn-text` to `pg-btn-label` in 129 panel files (`tools/bs6_rename_collisions.php`), `view_folders.php` `.submenu` to `.pg-submenu`.
  - Slice 2 (#1751): the same rename on the gift card, shipping method and ERP screens and `erp_export.php`.
  - Left: `#menu` and the toggles in `includes/fn/output.php`; `btn-text` in `edit_contact.php`, and in `add_page.php`/`edit_page.php` together with `backend.src.js` (`#create_button .btn-text`). Rerun the tool on them.
- B6 Wrappers with a switchable engine (v5 engine now): `pgChips`, `pgCombobox`, `pgDatePicker`, `pgDateTimeField`; call sites move to `data-pg-*` hooks.
  - Slice 1 (#1733): `pg_fields.js`, liveform `data-pg-*`, `pg_fields_script()`; gift cards, shipping methods, ERP screens (`ui/pg-field-wrappers.md`). Slice 2 (#1741): firewall card, `edit_page.php`, `mass_edit.php`, `view_contacts.php`. Slice 3 (#1750): `pgInitInjected()`, ACL expiry pickers. Slice 4 (#1764): menus, product groups, zones, verified addresses, product builder selects, campaigns, arrival dates, featured items, add contact, key code, commerce end-of-day time; no `select2`/`timepicker` class is left in panel markup. Left: the generic `select.select2` / `input.timepicker` inits in `backend.src.js` (now no-ops: delete them) and `create_shipping_cutoff()` (`data-pg-datetime` + `pgDateTimeField.init()`), `init_edit_comment_publish()` and the calendar all-day toggle (date/datetime switch: the wrapper needs a destroy first), `fn/content.php` pickers, `edit_contact.php` (#1706 holds it), `api_settings.php` IP chips, `view_order.php` dates and shipment chips, `add_submitted_form.php`/`edit_submitted_form.php` pickers, the product group rows in `backend.src.js` (`.product_group` select2), `offer_editor.js`, the Google category select2 in `product_builder.js` (server search), the visual editor's keyword box (`designer_screen.php`).
- B7 Visual editor stage 0: `/* @pg-site-output */` on the 54 site-output members of style_designer.js with a CI test, the chrome look pinned in style_designer.css, editor calls through `pgUI`.

## What happens at and after the switch?
- B8 Switch: `bootstrap-6-switch.md`.
- B9 Component swaps on v6, one claim each (`bootstrap-6-components.md`): B9a Chips, B9b Combobox, B9c Datepicker and time, B9d OTP, B9e Range, B9f form-adorn, B9g Stepper, B9h hover-lift, B9i Strength, B9j Avatar, B9k research proposals.
- B10 Token tuning, B11 redesign, B12 jQuery UI out of the panel: `bootstrap-6-redesign.md`.

## Where do I start?
- After the claim check, take the lowest open step whose dependencies are done and that no open PR holds; independent steps run in parallel sessions, one draft PR each; a step over about 1,000 product lines goes in several PRs, one after the other (`workflow/working-mode.md`). B1-B5 are independent; B6 needs B2a; B7 needs B1a and B2a; B8 needs B1a-B7; B9 needs B8 (B9c also B5); B10 needs B9; B11 needs B10; B12 needs B9c.
- Pre-switch PRs show their screens unchanged on the B1b screenshots (`ui/panel-screenshots.md`); later PRs show before and after. Mark a step done here with its PR numbers; once all its parts are done, its line moves to `bootstrap-6-panel-done.md`.
