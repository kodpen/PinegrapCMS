---
name: focus-after-removal
description: Where focus goes when a panel control removes, hides or replaces the focused element (ERP lines, bundle rows, contact photo, API sheet, Modify Selected, marketplace bulk bar, product group Load More)
---

## What is the rule?
- A control that removes, hides or replaces the element holding the focus moves focus to a neighbour first; otherwise focus falls to `<body>` and the next Tab starts at the page top. Read the neighbour before the element goes.
- A control that only changes a value (a select's `change`) never moves focus: Windows fires `change` on every arrow key of a closed select, so focusing elsewhere makes the other options unreachable (WCAG 3.2.2).

## Where is it applied?
- ERP invoice and waybill lines (`assets/js/erp_invoice_editor.js`, `removeLine()`): the next line's product name, else the previous line's. The last line is only emptied and keeps focus.
- Product bundle rows (`assets/js/product_bundle.js`): the next row's quantity, else `#pg_pb_bundle_search`.
- Contact photo x (`add_contact.php`, `edit_contact.php`): the inline `onclick` removes the `.item`, so a capture-phase click listener on `#software_image_picker_container` focuses the card's Change Image button before the removal. `includes/forms/contact_form.php` and `backend.src.js` stay unchanged.
- API applications result sheet (`api_settings.php`): a `<dialog open>` inside a fixed `.pg-open-sheet` layer. On `DOMContentLoaded` everything beside the sheet, level by level up to `<body>`, gets `inert`, and the sheet's first control takes focus. Close lifts `inert`, removes the sheet and focuses New Application (`[data-bs-target="#api_new"]`). The secret sheet still waits for its acknowledgement box; `inert` was chosen over `showModal()` so Escape cannot close it.
- API application drawer delete confirm: Delete this application focuses Yes, delete; Cancel focuses Delete this application again.
- Modify Selected (`includes/templates/edit_products.php`): the Price Change Method select keeps the focus; the price box below it is already shown.
- Load More Products on Create/Edit Product Group (`pg_product_group_form_products_card()` in `includes/forms/product_group_form.php`): focus moves to the first new row's tick box ('Select {product}'); when the batch is empty and the last, the strip holding the button is hidden and focus goes to `#select_all`. Every success, empty rows included, hides the strip (`has_more` false) or enables the button again; before, an empty batch (products binned, deleted or grouped elsewhere since the screen opened) left it disabled with its hourglass. A refused answer, a network error or the sign-in page of an expired session enables it again and shows `response.message`, else 'Query failed.', in a danger `pgToast`. New rows go through `pgInitInjected()`. Pinned by `tests/product_group_load_more_test.php`.

- Marketplace products bulk bar (`updateBulk()` in `assets/js/marketplace_products.js`): before the bar hides, focus inside it goes to `#mpp-select-all`, else the first row box (`ui/marketplace-products-names.md`).

## When was it done?
- PR "[iş] Removed lines and closed panels give focus on…" (October 2026), pinned by `tests/focus_after_removal_test.php`.
