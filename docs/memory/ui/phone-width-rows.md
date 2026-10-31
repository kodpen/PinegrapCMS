---
name: phone-width-rows
description: Phone-width fixes: v6 .input-group and .btn do not wrap (rate tiers, order documents card, sign-in rows), Login Region code fields, inner flex clusters wrap, marketplace grid minimum
---

## Why did the Shipping Method "or" tiers spill past the card on a phone?
- Bootstrap 6 dropped `flex-wrap` from `.input-group` (dist `bootstrap.css`, `@layer components`); the panel does not restore it. Each variable base rate tier was one group of two inputs (`min-width: 90px`) and three add-ons, about 330-340px, in a `.collapse.popover` callout about 285px wide at 390px.
- Now each tier is a `d-flex flex-wrap gap-1` row holding two groups, `input-group w-auto flex-grow-1`: the amount with its currency, and "if recipient subtotal is at least" with the subtotal and its currency. Utilities win over `.input-group`'s `width: 100%` (utilities layer).
- Each input had two `style` attributes; the parser keeps the first, so `text-align: right` was lost. They are merged.
- Pinned by `tests/phone_width_rows_test.php`.

## Why do the Login Region code fields carry only a height?
- `login_region_form.php` gave its four textareas an inline `width: 600px`, which beats `.form-control`'s `width: 100%`. The textarea shows until the code editor replaces it, and stays when the engine cannot be fetched, so the screens scrolled sideways on a phone. Now the inline style is `height: 200px` only.

## How does a row with a sentence and buttons fit a phone?
- Bootstrap 6 makes `.btn` nowrap (`--bs-btn-white-space: nowrap`). In a flex row that cannot wrap, the buttons keep their width and the sentence column shrinks to a few pixels (one letter or word per line) while the buttons run past the row.
- Rule: the row gets `flex-wrap`, the text column `flex-grow-1 min-w-0` with `style="flex-basis: 10rem"`; the buttons then drop to a line under the text on a phone and stay beside it on a desktop. A 12rem basis is too wide: inside Edit User's 32rem drawer it pushes "Turn off two-step verification" to its own line at 1280.
- Rows fixed this way: the order screen's documents card steps (`erp_order_flow_card()`, its print, action, goto and greyed buttons share one `d-flex flex-wrap gap-3` box), Edit User's "Sign in with …" and two-step rows in the sign-in drawer, and the sign-in lock alert.
- Pinned by `tests/phone_width_rows_test.php` (the card is rendered from a state array).

## Why does an inner cluster in a wrapping row carry its own flex-wrap?
- A row's `flex-wrap` only moves its direct children; a child that is itself `d-flex` without wrap keeps its buttons on one line and pushes the page sideways. So the inner cluster wraps too, with `row-gap-*` when it may break onto two lines.
- Fixed this way: the visitor report export + pager cluster (403px, page 436px wide at 320 and 390), the firewall log Content Security Policy card header buttons, the system style source Save & Return / Save / Cancel row, and the form item view "Available variables" / "Hints" header.

## Why do marketplace listing cards use minmax(min(280px, 100%), 1fr)?
- `.mpa-grid` in `marketplace_products.php` (its own `@layer custom` block) had `minmax(280px, 1fr)` in a 256px box at 320, so each listing card ran 9px past the outer card's border. `min(280px, 100%)` lets the single column shrink to the box.
- Pinned by `tests/phone_width_strips_2_test.php`.
