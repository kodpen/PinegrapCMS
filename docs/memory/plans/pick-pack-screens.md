---
name: pick-pack-screens
description: Plan pick-pack, how the shelf and wave screens are put together (PP2, PP3): the shelf address field, the Pick waves list, "Make a wave", a wave's screen, its pick list on paper and phone
---

## Which screens, and by which rules?
- PP2: the shelf address. PP3: Orders › Pick waves, a wave's screen, the pick list. The Pack screen, the short pick and the Packing card (PP4, PP5): `pick-pack-screens-pack.md`.
- `ui/screen-composition-rules.md`; parts are copied from the Design examples screen, the section named in brackets (`workflow/screen-described-in-plan.md`). A user with the packing right alone sees no price, total or e-mail on any of them.

## Where is the shelf address typed? (PP2)
- On the product's stock card: with locations a "Shelf" field in each location's row beside its count; without, one field under the count. In Change Selected one entry, "Shelf address".

## How is Pick waves laid out? (PP3)
- A list on the list table driver: wave (its name or number), made on and by, stock location, addresses, units, a pill [status] (Open, Picked, Closed), packed of all.
- One fill, "Make a wave" [buttons]. Row actions quiet small: Open, "Print the pick list"; Delete in the row's menu, danger, only while no address of it is packed, its confirm saying the orders go back to waiting.
- "Make a wave" is a drawer: carrier, marketplace, stock location, ship-by day, "Single-item addresses only" as its fields, a live line "42 addresses, 118 units match" and "Make the wave" as the fill. The orders list gains the bulk action "Add to a wave".

## How is a wave's screen laid out? (PP3)
- Head: the name, the pill; "Print the pick list" as the fill, "Close the wave" quiet.
- Segments [segmented control]: "Pick list", "Addresses".
- Pick list: rows in shelf order [rows], each the shelf address in large type at the left, picture, code, name and variant options, units, "for N addresses". On a phone each row is a large tick target; ticked rows grey and sink to the end; a bar at the top reads "12 of 31 picked".
- Addresses: order, ship-by day (amber today, red when late), units, a pack pill (Waiting, Packed, Short). An order that left reads "Cancelled, taken out" as a grey row.
- The printed list is the print view on light paper (`ui/print-views-light-paper.md`): the wave's code, then the same rows with a box to tick.

## Which words?
- One line of help a part. On screen: "Wave", "Pick list", "Shelf", "Pack", "Short"; never "bin", "ship_to" or a column's name.

Related: docs/memory/plans/pick-pack.md, docs/memory/plans/pick-pack-steps.md, docs/memory/plans/pick-pack-screens-pack.md
