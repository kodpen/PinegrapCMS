---
name: delivery-tower-screens
description: Plan delivery-tower, how its screens are put together (DT3 to DT5): the Delivery problems list, a problem's drawer, the carrier scorecard, the two suggestions, the settings drawer
---

## Which screens, and by which rules?
- DT3: Orders › Delivery problems and a problem's drawer. DT4: the carrier scorecard, the two suggestions. DT5: the settings drawer.
- `ui/screen-composition-rules.md`; parts are copied from the Design examples screen, the section named in brackets (`workflow/screen-described-in-plan.md`).

## How is the Delivery problems list laid out? (DT3)
- From DT4 link segments [segmented control] sit under the head: "Problems", "Carrier scorecard".
- A list on the list table driver: parcel number, order, carrier, kind as a pill [status] (Stuck, Not handed over, Delivery problem, Returned), "Since" in days, assignee, the note's first words. A marketplace order's row carries a quiet "Marketplace" pill.
- Filters: Open, Closed, All as segments; kind and carrier as selects. The screen has no fill: nothing is added here. With rows chosen the bulk bar offers "Assign to…" and "Close…".
- Empty: "No open delivery problems" and one line on what is watched.

## How is a problem's drawer laid out? (DT3)
- Opens from the row. Head: the parcel number, the kind pill, the days.
- A label and value list (`ui/look-kv-list.md`): order (a link), the customer's name as the order screen shows it, carrier, shipped on, days in transit beside the carrier's usual days for the province, the estimate given at checkout.
- "What happened": the parcel's status changes as gathered rows [rows and gathered rows], newest first, then a link to the carrier's tracking page.
- Assignee (a select) and Note (one text field).
- Foot: Save as the fill; "Close the problem" quiet, which opens the reason as a segmented choice (Sent again, Refunded, Found, Other) with its note and its own "Close" fill. No danger button: a problem is never deleted.
- A problem that closed by itself shows its reason ("Delivered", "Scanned by the carrier") in place of the foot's buttons.

## How is the scorecard laid out? (DT4)
- A report screen (`ui/look-report-screens.md`): the tools row holds the month stepper and "Last 12 months", "Excel" quiet at the right.
- Figures [cards: figure] for the period: parcels, on time, median days, with a problem, returned.
- A table frame: a carrier a row with those five columns; a row opens to its provinces (second level). A province under 20 parcels reads "Too few parcels" in place of figures; a carrier under 20 in all the same.
- "Last 12 months" turns the columns into months for one figure, chosen by a segmented choice above the table.

## Where do the suggestions show? (DT4)
- Arrival: a card above the scorecard's table, "Arrival days to check", a method a row: "Standard delivery: measured 2.6 days, set 1" and a quiet "Open the method". On the method's screen the same sentence is the one line of help under its transit days. Nothing changes by a press here.
- Carrier: in the Create Label dialog the carriers keep their select; the list is ordered, the first carries a "Suggested" pill and each option's grey second line reads the on-time share for the province and the price where `rate()` answered. The choice stays the person's.

## How are the settings laid out? (DT5)
- A drawer from the head's quiet "Settings": a switch a row for each problem kind with one line on when it is found; the two day counts folded under "Advanced". Foot: Save fill, Cancel quiet.

## Which words?
- One line of help a part. On screen: "Parcel", "Stuck", "Not handed over", "On time", "Usual days"; never "percentile", "exception" or a column's name.

Related: docs/memory/plans/delivery-tower.md, docs/memory/plans/delivery-tower-steps.md
