---
name: bootstrap-6-components
description: Step B9 of plans/bootstrap-6-panel.md: own panel widgets swapped for native Bootstrap 6 components, the conditions research found, what stays as it is
---

## Which swaps did the owner ask for?
- B9a tagin to Chips: done (#2208), how it works in `ui/pg-field-wrappers.md`.
- B9b select2 to Combobox: done (#2243), server search for `data-pg-source` (google_product_category) done (#2362); how it works in `ui/pg-combobox.md`. Follow-up done (#2428): the panel header loads no select2 asset and `backend.src.css` has no select2 rule; `assets/lib/select2` stays for site pages.
- B9c Datepicker and time fields: done (#2262), how it works in `ui/pg-datepicker.md`.
- B9d OTP: done (#2210, `ui/otp-fields.md`).
- B9e Range with value bubble: done (#2207): editor inspector (3), label editor, image quality % and disk alert % (`ui/range-value-bubble.md`). Never the palette's site Range.
- B9f form-adorn (150 sites): groups with buttons or selects (79), several inputs (12) or a hint on the add-on (9) stay input-groups. The add-on moves inside the border: pilot 3 places with screenshots first (pilot #2219, part 2 #2280, part 3 ERP, order refund and barcode #2355, part 4 gift card amounts, workspace template icon and MCP alias #2404, part 5 toolbar search fields of `view_folders.php`, `view_audit_log.php`, `view_not_found_log.php` and the ad region timing fields #2478, `ui/form-adorn.md`). Left: settings cards (with their CSS, `includes/settings/screen.php` search included), the toolbar searches of `translations.php`, `erp_cheques.php`, `erp_quotes.php`, `erp_purchase_orders.php`, `erp_stock_minimums.php` and the workspace scripts, single groups on contact screens and the rest of the order screens.
- B9g Stepper: done (#2206); how it works is in `ui/step-rail.md`.
- B9h `.hover-lift` with tokens at today's 1-2 px, done: `.mpp-pick`, explorer `.item-card`, `.esi-tile` (#2217), dashboard `.pg-brief-ws-item` and `.pg-next` (#2237).
- B9i Strength on the installer meter (`.pg-strength`): with `STRONG_PASSWORD` on, the B5 port of `validate_password_strength()` scores; off (the column default, so always at install) the default scoring only advises. Messages from `lang()`.
- B9j Avatar (57 sites), text initials in theme colours: `ws_people()`, chat and widget sources return initials and a tone index (8 `theme-*`); presence dot; no `src=""`. `pg_initials_avatar_url()` stays for e-mail, PDF, print. `.avatar-stack` is row-reverse. Slice U2 of `workspace-friendly-ui.md`.

## What did research add (B9k, not owner decisions)?
- Slice 1 done (#2273): `.ws-cb-progress-bar` to `.progress`, `.pg-chip` to `.chip`, the workspace toast to Toast (`ui/bootstrap-6-own-widget-swaps.md`).
- Next: `.btn-ghost` to `.btn-text.theme-secondary`, then the editor toast once `style_designer.js` is free. Guest toast, `.ws-chip`, role cards and status choice wait for B11 (reasons in `ui/bootstrap-6-own-widget-swaps.md`).
- Only where screenshots show no change, else in B11: own theme tokens and focus ring to v6 tokens, `.btn-ghost` (285) to `.btn-text.theme-secondary`, own toasts to Toast, `.pg-chip` to `.chip`, pills and `.ws-chip` to `.badge-subtle`, role cards to `form-field-card`, status choice to `label.btn-check.btn-subtle`, `.ws-cb-progress-bar` to `.progress`.

## What stays as it is?
- jquery-multiselect-checkbox (owner decision), number +/- buttons, empty states, callouts, segmented controls, the settings progress line, the glass widget theme, own show/hide bindings.
