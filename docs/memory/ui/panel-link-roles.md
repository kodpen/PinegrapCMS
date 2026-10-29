---
name: panel-link-roles
description: Links turned into buttons (report Edit, Add Filter, Unlock Sign-in), real Create an User link, Optimize/Shipment Tracking/Print names, edit-file previews and Download names
---

## Which links became buttons, and why?
- A link with `href="#"` or `href="javascript:void(0)"` is read as a link and ignores Space. The order and visitor report screens' Edit (`#edit_button`), the Add Filter of both report screens and of `edit_form_list_view.php` are `<button type="button">`.
- Report Edit hides its own `#button_bar`, so it moves focus to the first visible field of `#edit_form`; Cancel Edit hides the form with focus inside it, so it focuses `#edit_button` first. Without that, focus stayed on a hidden element.
- Edit User's Unlock Sign-in is `<button type="submit" form="unlock_sign_in_form">`; the form carries that id. It submitted from an `onclick` before.
- Edit Contact's "Create an User" has a real `href` (built raw, printed through `h()`); it was `href="#!"` with `window.location`, so open-in-new-tab and copy-link gave `#!`.

## How are buttons with a figure named?
- A visible `title` names nothing when the control has text (`pgInjectAriaAndRequiredMarkers()` and Bootstrap's `_fixTitle()` copy it into `aria-label` only for a textless control), so a button whose text is a figure is named by the figure.
- The Optimize buttons of `view_files.php` and `view_design_files.php` and the orders list's Shipment Tracking button carry a `visually-hidden` action word before the figure ("Optimize this image 45%", "Shipment Tracking 1"); their Bootstrap icons are `aria-hidden`.
- The order view's packing slip and print-order buttons carry `aria-label` Print Packing Slip / Print Order (both read "Print" before); they are now closed with `</button>`.

## What about the edit-file screens?
- The preview link of `edit_file.php` (an empty `stretched-link`) and the thumbnail link of `edit_design_file.php` repeat Open in New Tab: `tabindex="-1" aria-hidden="true"`, image `alt=""` (as in `order-view-picker-links.md`).
- Download uses `download="<file name>"`; it was `download="image"`, so a PDF, CSS or font was saved as "image" (the lists were fixed in #2875).

## What was not checked?
- Nothing was rendered in a browser. Test: `tests/panel_link_roles_test.php`.

Related: docs/memory/ui/panel-accessible-names.md, docs/memory/ui/order-view-picker-links.md
