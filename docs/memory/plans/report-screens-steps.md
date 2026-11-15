---
name: report-screens-steps
description: Steps of plans/report-screens.md: RS1-RS8c landed or done; the close-out (RS9) left
---

## Which steps are there?
- S under 400 product lines, M about 1,000; no schema; each step its tests and its memory topic. Built in the order written.
- Landed (their rules are in the topic files): RS1 the Sales map card's See all, #8691 (`commerce/order-report-ready-reports.md`); RS2 the shared view on the order report, #8709 (`ui/report-view.md`); RS3 its ready reports, #8724 (`commerce/order-report-ready-strip.md`); RS4 the visitor report, #8764; RS5 its ready reports, #8800; RS6 shipping, #8811 (`commerce/shipping-report.md`); RS7 ERP VAT, #8827.

- RS8 (S) The look and the close-out: the asked steps below, then the lasting rules into the topic files and this plan's two files deleted.
  - Look request: channel 25 message 4150 (2026-11-15): the four screens from a sandbox with demo data, light and dark, 1440 and 390 px.
  - The look is the architect's (delegated by the owner's 2645), answered in channel 25 message 4223: the screens stand in their shape, light and dark; nothing is redrawn. RS8a, RS8b and RS8c below; RS8b and RS8c may run beside RS8a. After the three land, two pictures per changed screen (light 1440 and 390) in the 4150 thread; then RS8 closes the plan.
- RS8a (S) Done: #9700. The shared parts, mostly on a phone:
  1. A segmented control that does not fit its row (ready reports, periods) is one row that scrolls sideways; it never wraps into a box of three or four rows (4153, 4161).
  2. An odd last figure card takes the whole row on a phone (shipping's Total, VAT's Difference).
  3. The report's title wraps and is never cut with an ellipsis ("Kaynağa göre ziyaret..."); the pill goes under it. The breadcrumb's last part ends with an ellipsis, not mid-letter.
  4. A table on a phone: the first column stays in place and takes at most half the width, its header wraps; today the order report shows only names, every figure is off the screen.
  5. The charts' x-axis: ticks at even steps (shipping jumps 15, 17, 19, 22; the VAT year shows Temmuz and then Ağustos).
- RS8b (S) Done: #9718. Order and visitor reports:
  1. A ready order report shows its period and the period control as every order report does; "Sales by region" shows 153 orders of no named days.
  2. Country names in place of codes in the map's list and the table's group rows, where the site knows the name; the export keeps the code.
  3. The visitor report's bars card, alone in its row, takes the whole row as the map card does.
  4. The visitor report's "Average Order Amount" is the order total divided by visitors (25.818,54 / 1.762 = 14,65), in the card and in the table, as it always was. The figure stays; its label says what it is: "Order amount per visitor" (tr_add). The export keeps its header.
- RS8c (S) Done: #9738. Shipping and ERP VAT:
  1. Shipping, 1440: the products table is cut at the card's edge (one letter of the column after "Cmt 28 Kas"). Fourteen days fit at 1440 with narrower day columns; below that the table scrolls inside its card.
  2. One shape for the period beside the calendar icon on the four screens: the panel's date text (`get_absolute_time()`, type date) and an en dash. Today there are three ("Cmt 17 Eki 2026 - Paz 15 Kas 2026", "15 Kasım 2026 – 28 Kasım 2026", "1/11/2026 – 30/11/2026"). Table cells keep the site's date format.
  3. The VAT notice about orders without an invoice: body text size, the look's note; today its text is larger than everything around it.
  4. VAT: the "by document type" card shows only with two kinds or more (one bar says nothing); the year chart's title names both series ("VAT by month").
  5. VAT documents card: on a phone the "Document type" label sits with its select; the year's table shows 15 of 59 rows and nothing says so: a count in the card's head, and pages or "Show all".
