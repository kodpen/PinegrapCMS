---
name: bootstrap-6-components
description: First-stage swaps of own panel widgets for native Bootstrap 6 components (Bootstrap 6 plan): targets, conditions found in research, what stays as it is
---

## Which swaps happen in the first stage?
- tagin to Chips (26 sites): the named input stays the posted store (separator-joined). Chips posts nothing itself and auto-inits only on DOMContentLoaded, so settings cards loaded by AJAX init through `pgInitInjected()`. Fill silently so loading does not mark a form dirty.
- select2 to Combobox (27): the native `<select>` stays the posted store (`name[]` arrays, liveform, `required`). Combobox has no remote search (google_product_category needs an own fetch layer), no new entries and no clear (add an explicit empty item). On ERP documents the account choice re-rates taxes, so a native `change` must fire.
- jQuery UI datepicker to Datepicker (31 date, 11 date and time, 8 time-only sites): init from JS with our own parse and format of the site date format, never the data API or `new Date()` parsing. Date and time is Datepicker plus `<input type="time">` composed into the original field. Time-only becomes `<input type="time">`, only after B5, or 17:00 is stored as 00:00. ACL expiry pickers inside drawers open inline or inside the dialog.
- PIN fields to OTP (mfa.php, developer_lock): the 6-digit code plus a separate recovery-code mode (8 characters). No auto-submit: attempts are rate limited.
- Range to Range with a value bubble: the editor inspector (3) and the label editor; candidates are image quality % and the disk alert %. Never the palette's site Range.
- Input with icon to form-adorn (150 sites): groups holding buttons or selects stay input-groups (79). The add-on moves inside the border, so pilot on 3 places with screenshots first.
- Step rails to Stepper: edit_offer, the installer, ERP import. edit_offer's per-section done/todo states map to `theme-success`/`theme-danger` items.
- Hover lifts to `.hover-lift` (4 elements), tokens set to today's 1-2 px.
- Password check to Strength: the scoring follows `config.strong_password` (the twin of `validate_password_strength()` when it is on); messages through `lang()`.
- Avatars to Avatar (57 sites) with text initials and theme colours. `.avatar-stack` is row-reverse; sizes through `--bs-avatar-size`. This is slice U2 of `workspace-friendly-ui.md`.
- In the same pass: own theme tokens to v6 theme tokens, own focus ring to the focus-ring tokens, `.btn-ghost` (285) to `.btn-text.theme-secondary`, own toasts to Toast, `.pg-chip` to `.chip`, counters, status pills and `.ws-chip` to `.badge-subtle`, btn-check cards to `form-field-card`, `.ws-cb-progress-bar` to `.progress`.

## What stays as it is?
- jquery-multiselect-checkbox (owner decision; v6 has no counterpart), number +/- buttons, empty states, callout boxes, segmented controls, the settings indeterminate progress line, the glass widget theme, own checkbox-driven show/hide bindings.
