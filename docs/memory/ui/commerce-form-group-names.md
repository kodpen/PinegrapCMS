---
name: commerce-form-group-names
description: Commerce forms names: State Country label, ship date Adjustment pair, shipping day sets, rate tiers and rate boxes, product group Display Type and gift card Format legends
---

## What was wrong?
- Create/Edit State: `Country` was `<label for="name">`, so the name box had two labels and the country select none. It is `for="country_id"` now.
- Ship date adjustments: `Adjustment` was a label with no `for` over an input group; the days box and the earlier/later select had no name.
- Shipping method: three seven-day checkbox sets (Handling on, Ships on, Available on) were told apart only by an h3; base rate tiers 2-4 read only `or` and `if recipient subtotal is at least`; the three `Don't apply to first item` switches did not say their rate.
- Product group Display Type and the product's gift card e-mail Format were orphan labels over radios; Browse/Select explained themselves only in a hover `title`.

## How are they named now?
- Adjustment: a `div.form-label#adjustment_label`; the input group is `role="group"` labelled by it; the days box is `aria-labelledby="adjustment_label adjustment_days_unit"` (the `days` text), the select `adjustment_label adjustment_type_label` (a visually-hidden `Direction`).
- Day sets: the row under each h3 is `role="group"` with `aria-label` from the same key. The h3 markup stays byte-identical (`tests/form_card_headings_test.php` pins it).
- Tier rows 2-4: `role="group"` named `Base Rate {var:1}` (the number). Rate boxes: `role="group"` `aria-labelledby="<rate>_label"`, the id on the rate label.
- Display Type and Format: `<fieldset>` + `<legend class="form-label fs-md">`; ids, names and `data-bs-target` kept. Browse/Select carry their explanation in a visually-hidden span through `aria-describedby`; the `title` stays for hover.

Test: `tests/commerce_form_group_names_test.php`.
