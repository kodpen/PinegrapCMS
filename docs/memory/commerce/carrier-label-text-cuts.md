---
name: carrier-label-text-cuts
description: Carrier label drivers cut a carrier's message, error text and the Sendcloud name with mb_substr(), never byte substr(): a split letter empties the order screen notice or the request body
module: carrier_labels
---

## How does a label driver shorten a carrier's text?
- With `mb_substr($x, 0, N, 'UTF-8')` (aras, basitkargo, kargonomi, shippo, sendcloud). mbstring is used without a guard in the drivers.
- Order-number cuts (ASCII) and the `raw` answer kept for the log stay byte `substr()`.

## Why not substr()?
- A Turkish or accented message cut at 200/300 bytes could end inside a letter. Nothing repairs it on the way: `pg_label_answer()` only trims, and `h()` (`htmlspecialchars()` without `ENT_SUBSTITUTE`) returns `''` for invalid UTF-8, so the order screen's "X refused the label: …" notice printed empty.
- Sendcloud cut the recipient and return sender name at 75 bytes before `json_encode()` (no invalid-UTF-8 flag): a split letter made the body `false` and the announcement went out empty, refused with an unrelated error.

## How is it pinned?
- `test_carrier_label_<driver>_long_refusal_is_cut_on_a_character` in each driver's test (valid UTF-8, `htmlspecialchars()` keeps the text) and `test_carrier_label_sendcloud_long_name_keeps_the_body`.
