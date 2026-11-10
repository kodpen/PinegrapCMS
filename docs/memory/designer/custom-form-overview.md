---
name: custom-form-overview
description: Start file for the custom-form-* files: what a designed form is, the decisions that bind it, its parts by the panel label, which file holds what, how a new control or field setting is added
---

## What is a designed form?
- The Custom Form system widget (`custom_form`) in the visual designer. Its controls are the form: every `input`/`select`/`textarea` inside it is a field, and the server derives the `form_fields` rows from the tree on each page save. There is no separate field list (`custom-form-model.md`, `custom-form-sync.md`).
- Visitors post to `custom_form.php`; the page is drawn by `_render_system_widget_custom_form()` (`includes/fn/widgets.php`) through `pg_cf_apply_field_bindings()` (`custom-form-render.md`).
- Modules: `includes/fn/custom_form.php` (fields, rules, render), `custom_form_steps.php`, `form_payments.php`, `form_payment_step.php`, `form_mails.php`.

## Which decisions bind it?
- The canvas is the record: the legacy field screens only show a designer form's fields, and they refuse to change them (`custom-form-options-and-rules.md`).
- #5035 (`custom-form-logic.md`): a field hidden by a rule is dropped and skipped on the server too; a fee is a one-line order of a product, with no payments table; an unpaid record is saved, has the "Unpaid" badge and gets no mail until paid.
- A form without rules, steps or a fee renders byte for byte as before; the legacy renderers in `includes/fn/content.php` are untouched.
- Translation switch: per field, every field included by default (owner, 2026-10-23, `custom-form-translate-switch.md`).

## What are its parts, by the label a person sees?
- Form palette (`CF_PALETTE`): Text … Hidden field, Field group, Step, Submit button, CAPTCHA (`designer-form-palette-and-fields.md`; options of a list or group: `custom-form-options-and-rules.md`).
- "Form settings" button and the Custom Form Settings section: notification, auto-reply, confirmation, the silent-form warning (`custom-form-settings-shortcut.md`); empty mail body and missing next page: `custom-form-email-and-confirmation-fallbacks.md`.
- Form Field panel rows:
  - "Hide from the visitor (staff only)": `custom-form-render.md`;
  - "Not before today" and "Required when": `custom-form-field-rules.md`;
  - "Shown when": `custom-form-shown-when.md`;
  - "Fill from the address": `custom-form-url-prefill.md`;
  - "Rich-text Editor": `custom-form-rich-text.md`;
  - "Include in translation": `custom-form-translate-switch.md`.
- Step (progress, Back/Next, the browser draft): `custom-form-steps.md`.
- Fee rows of the settings, the payment step, staff screens and API, mails after payment: `custom-form-payment.md`, `custom-form-payment-step.md`, `custom-form-payment-staff.md`, `custom-form-mails.md`; template fee products: `design-template-form-fees.md`.

## Which file holds what?
- Field columns and `props._cf` keys; form source `page`/`existing`: `custom-form-model.md`.
- Derivation, the per-page `ids` map, the PHP `{}`→`[]` trap, names and groups, RSS roles: `custom-form-sync.md`.
- The order of a control's starting value: `custom-form-url-prefill.md`.

## How is a new control or field setting added?
1. A setting that markup cannot hold goes into `props._cf` on the field's lead control (`_cfFieldLeadOf()`). Never leave `_cf` empty (`custom-form-sync.md`).
2. Read it in `_pg_cf_controls()` and in its client twin `_cfFieldsOf()`; a new input type goes into `_pg_cf_type_by_input()` and `CF_TYPE_BY_INPUT`, and a palette control into `CF_PALETTE`.
3. A stored setting needs a column (a migration step, asked first) or a key of `form_fields.rules` (no schema; cleaned by `pg_cf_rules_clean()`).
4. Draw it in `pg_cf_apply_field_bindings()`; a site check goes in `custom_form.php`. Staff paths, the API and MCP stay exempt unless decided otherwise.
5. Labels as `_sdT('…')`, Turkish via `php tools/tr_add.php`; `php tools/build_js.php`; `system-widget-change-checklist.md`; a `tests/custom_form_<topic>_test.php`, with a parity case for a form that does not use it.
