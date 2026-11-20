---
name: marketplace-repricer-screens
description: Plan marketplace-repricer, how its screens are put together: the Pricing tab of an account's Settings drawer (RP2), the product's marketplace row, the Repricing screen (RP3b)
---

## Which screens, and by which rules?
- RP2: the "Pricing" part of a marketplace account's Settings drawer and the product's marketplace row. RP3b: Marketplaces › account › Repricing.
- `ui/screen-composition-rules.md` and the ranks of `ui/look-marketplace-ranks.md`; parts are copied from the Design examples screen, the section named in brackets.

## How is the Pricing part laid out? (RP2)
- A tab "Pricing" of the Settings drawer, the drawer's parts as a segmented control [segmented control] once it has one; the drawer keeps its one Save.
- Three groups under group labels [fields]:
  1. "Channel price": percent, fixed amount, rounding as segments (None, Whole, ,90, ,99). One line: "The site price with VAT, changed by these."
  2. "Rules by category or brand": rows [rows] of scope, percent, fixed, rounding; "Add a rule" quiet; a row's Remove danger small. One line says the most specific rule wins.
  3. "Robot": "Try first" switch [fields: switch] with its line "Writes what it would send to the log and sends nothing"; the step (Amount or Percent as segments and one field); minimum margin. The limit of "Grow while winning" is folded under "Advanced".
- After a save that changed a rule, one notice line: prices are sent again in the background.

## How does the product's marketplace row change?
- No new card. The account's row gains: the channel price now (grey when by the rules), "Own price" (a field; empty means by the rules), strategy as a small select (Fixed, Win, Grow while winning; n11 only Fixed), the floor as grey text, and with RP3b the rank and the last change.
- Past five fields the row folds them under "Pricing" in the row.

## How is the Repricing screen laid out? (RP3b)
- Views as link segments: "Products" and "Log".
- Products: figure cards [cards: figure] Winning, Losing, At the floor, No cost; a click on one filters the list. Columns: product, our price, buybox price, rank, strategy, floor, last change with a reason pill [status].
- Log: time, product, old and new price, reason, and "Sent" or "Try first" as a pill.
- While "Try first" is on, one line above both views: "Try first is on: nothing is sent", with a link to the Pricing tab.

## Which words?
- "Channel price", "Own price", "Floor", "Try first", "Buybox"; never a strategy's code or a table's name.
