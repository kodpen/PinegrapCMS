---
name: settings-card-field-widths
description: Settings card control width classes pg-f-xs/sm/md/lg, how to pick one from field attributes, and why not to write style width or malformed col classes.
---

## Which class gives a control its width?
The class says it; the cell keeps the row rhythm and the control takes as much of the cell as it wants (a three-character limit stretched half a card reads as an empty box).

| Class | Holds | Control cap | In an 890px card |
|---|---|---|---|
| `pg-f-xs` | few-character number/code, time, percent | 11rem | 176 |
| `pg-f-sm` | postal code, phone, id, quantity | 19rem | 304 |
| `pg-f-md` | name, city, amount, dropdown | 28rem | 415 (cell width) |
| `pg-f-lg` | e-mail, host name, key: **own row** | 34rem | 544 |
| `col-12` | address, sentence, textarea, code block, tag field | - | full |

- The cap applies only to a DIRECT child. A control inside `.input-group` is the group's child; narrowing it breaks the group, so the group itself is narrowed. A cell whose control is wrapped in something else keeps using the whole cell (safe direction of error).
- The threshold follows the card's CONTENT box (1px border), so it kicks in around 546px.

## How do you choose the class for a new field?
- The control's own attributes say it: `maxlength <= 8`, `type="number"`, `inputmode="numeric"` or `size <= 8` -> `pg-f-xs`.
- Never write `style="width"` / `max-width` on the control: width is the grid's job and two sources drift apart (one existed, in the device-limit field, and contradicted its cell).

## Why was the old column-class scheme replaced?
- The scheme was reduced from thirteen recipes to the classes above: 153 controls counted, `col-12 col-xxl-6` (54), `col-12` (31), `col-12 col-sm-6 col-lg-4` (13) ...; ones like `col-xxl-6` never fired at the width the pane is drawn.
- `commerce.php` had 21 class strings with a missing space (`col-xxl-6col-lg-3`): the browser sees one unknown token and those cells stayed full width at every width. This directly caused the inconsistent widths in the E-Commerce pane.

Related: docs/memory/ui/settings-card-row-layout.md
