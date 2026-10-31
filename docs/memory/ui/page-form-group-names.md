---
name: page-form-group-names
description: Create/Edit Page type properties and the ERP accounts import: radio questions are legends, the custom form's two e-mail blocks are named by their switch
---

## Which radio groups on the page form are fieldsets?
- In `includes/forms/page_form.php` 13 questions were a bare `<label class="form-label">` (some with `for=""`) over `div.form-field` radios, so `pgInjectAriaAndRequiredMarkers()` bound them to nothing and a screen reader heard only the options.
- Now `<fieldset>` + `<legend class="form-label fs-md">` around only the radio wrappers: Product Description Type (express order, shopping cart, shipping method, order preview, order receipt), Format (express order and order preview receipts, order form layout, custom form submitter and administrator e-mails), Confirmation Type, "If User has already submitted form in the past, then show", Layout Type (System Options).
- The collapse popovers the radios open stay outside the fieldset; ids, names, `onclick` and `data-bs-target` are unchanged (`show_or_hide_custom_form_*`, the collapse switcher and `order_form_product_layout_row_2` read them).

## How do the custom form's two e-mail blocks differ for a screen reader?
- Both open at once and each holds a Subject box and a Format pair. Each collapse (`#show_or_hide_custom_form_submitter_email`, `…_administrator_email`) is `role="group"` with `aria-labelledby` its switch label (`custom_form_submitter_email_label`, `custom_form_administrator_email_label`), so its fields are heard under "E-mail Submitter" or "E-mail Administrator".

## And the ERP accounts import?
- `erp_accounts_import.php`: "If the account already exists" is the legend over Update it / Skip it.

Rule: `toggle-and-mark-names.md`. Test: `tests/page_form_group_names_test.php`.
