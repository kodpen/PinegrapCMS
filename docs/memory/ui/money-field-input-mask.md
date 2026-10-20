---
name: money-field-input-mask
description: Panel money fields with the currency input mask (data-inputmask-alias currency): fill them in machine format, read them with pg_normalize_amount(), require them; the gift card balance symptom
---

## How does the currency mask read a value?
- `data-inputmask-alias="currency"` with `groupSeparator ","` reads "." as the decimal point and "," as the thousands separator, whatever the panel language. A value written for the language ("50,00" in Turkish) is read as 5,000.00; "1.234,50" as 1.23.

## How is a masked field filled?
- In machine format: `sprintf('%01.2f', $minor_units / 100)` ("50.00"), as `edit_zone.php`, `edit_shipping_method.php` and `edit_gift_card.php` do. Never `pg_format_number()`, which writes the panel language's separators.

## How is it read back?
- `pg_normalize_amount()` takes either way of typing ("12,50", "1.234,56", "1,234.56"), then `(int) round($value * 100)`. An empty value has to be refused first (`validate_required_field()`): `'' * 100` is a TypeError on PHP 8, a 500 that loses the rest of the form.

## What was the symptom? (#728, PR #925)
- Gift card screen (`edit_gift_card.php`) on a Turkish panel: saving only the notes or the expiry date turned a ₺50,00 balance into ₺5.000 and ₺1.234,50 into ₺1,23; an empty balance gave a 500. The balance is now filled in machine format and required.
