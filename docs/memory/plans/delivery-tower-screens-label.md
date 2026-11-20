---
name: delivery-tower-screens-label
description: Plan delivery-tower, step DT4b: the Create Label chooser on the order screen for an address whose method names no carrier; its parts, the carriers' order, the label path's one argument
---

## What is there today, and what was wrong in the plan?
- `view_order.php`: "Create Label" is a submit button of `shipment-label-form` with a confirm; nobody picks a carrier. `pg_shipment_label_method_driver()` takes the method's linked driver, else the first ready driver that ships with the method's carrier; with no carrier, the first ready driver.
- The plan spoke of a Create Label dialog with a carrier select. There is none: the architect's error, found by DT4 #10890 (channel message 11252).

## When does the chooser show?
- Only for an address whose shipping method names no carrier and links no driver, while two or more drivers are ready (`pg_shipment_label_drivers()`). In every other case the button keeps today's confirm.
- Never for a return label, a marketplace's label, the bulk run or the automatic label: they keep today's pick.

## How is the chooser laid out?
- A dialog `pgUI` opens (a sheet from the bottom under 576 px), title "Create label", one line: "This shipping method names no carrier. Choose who carries the parcel."
- The ready drivers as the rows of one inner card [cards]: each row a label wrapping a radio, the driver's name as the row title and a grey second line. The first row carries a green "Suggested" pill [status] and is chosen when the dialog opens.
- The second line: "On time 94% in Ankara" (the province's share, else the carrier's across the country, as T2), then " · 84,50 ₺" where `rate()` answered. With no figure: "Not enough parcels yet".
- Order: `pg_delivery_tower_carrier_order()` (DT4): on-time share, then price. No figure for any driver: today's order and no pill.
- Under the rows one meta line: "A suggestion from your own deliveries; the choice is yours." Foot: "Create label" fill, Cancel quiet.
- The rows come from one action when the dialog opens. While it reads the prices the card shows one line, "Reading prices…"; a price that does not answer in time is left out and the rows still show.

## What changes in the label path?
- `pg_shipment_label_create()` takes an optional chosen driver. It is used only when the method names no carrier, links no driver and the driver is ready; otherwise it is ignored and today's pick stands. Nothing after the pick changes.
- The dialog posts the same `shipment-label-form` with the driver's key in one more field.

## What must be true?
- A method that names a carrier or links a driver: no chooser, and a posted driver is ignored (test).
- One ready driver: no chooser.
- No driver posted (the bulk run, the automatic label, an old form): the first ready driver, as today (test).
- A driver key that is not ready is ignored (test).
- The action answers only to a reader with the orders right and carries no address beyond the province.

## Whose step?
- DT4b (S) after DT4: `view_order.php`, `includes/fn/shipment_labels.php` (the one argument), the new action and the dialog's script. The worker opens the dialog at 1440 and 390 px and writes the Screen check.

Related: docs/memory/plans/delivery-tower-screens.md, docs/memory/plans/delivery-tower.md
