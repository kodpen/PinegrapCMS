---
name: phone-width-rows
description: Phone-width layout fixes (#3006): v6 .input-group does not wrap (Shipping Method base rate tiers), save bar two actions per row, Login Region code fields without a fixed width
---

## Why did the Shipping Method "or" tiers spill past the card on a phone?
- Bootstrap 6 dropped `flex-wrap` from `.input-group` (dist `bootstrap.css`, `@layer components`); the panel does not restore it. Each variable base rate tier was one group of two inputs (`min-width: 90px`) and three add-ons, about 330-340px, in a `.collapse.popover` callout about 285px wide at 390px.
- Now each tier is a `d-flex flex-wrap gap-1` row holding two groups, `input-group w-auto flex-grow-1`: the amount with its currency, and "if recipient subtotal is at least" with the subtotal and its currency. Utilities win over `.input-group`'s `width: 100%` (utilities layer).
- Each input had two `style` attributes; the parser keeps the first, so `text-align: right` was lost. They are merged.
- Pinned by `tests/phone_width_rows_test.php`.

## How does the sticky save bar lay out below 576px?
- Two actions per row: `nav.buttons.navigation .btn { flex: 1 1 calc(50% - .25rem); min-width: 0; white-space: normal; }` in the phone block of `backend.src.css`. An odd last action takes the whole row; a long label wraps inside its button.
- It used to be one action per row (`flex-direction: column`): a bar with 4-5 actions (`edit_file.php`, `view_orders.php` with rows ticked) became a 192-242px column with no surface floating over the form. The `.btn-group` in the bar is `display: contents`, so its buttons are flex items of the bar too.

## Why do the Login Region code fields carry only a height?
- `login_region_form.php` gave its four textareas an inline `width: 600px`, which beats `.form-control`'s `width: 100%`. The textarea shows until the code editor replaces it, and stays when the engine cannot be fetched, so the screens scrolled sideways on a phone. Now the inline style is `height: 200px` only.
