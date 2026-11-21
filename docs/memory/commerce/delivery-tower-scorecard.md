---
name: delivery-tower-scorecard
description: Carrier Scorecard screen (view_carrier_scorecard.php, DT4): month and 12-month figures per carrier and province, Excel, the arrival check, the carrier order rule
module: delivery_scorecard
---

## What does the scorecard show? (T11)
- Reports menu (and the shipping menus) › "Carrier Scorecard", beside the Shipping Report; gate `validate_ecommerce_report_access()`, shipping on.
- A parcel counts in the month of its `shipped_at` (site time); parcels of incomplete or cancelled orders are left out. Read by id, 2,000 a query (`pg_delivery_scorecard_read()`), not from the nightly summary: the summary keeps 90 days and no months.
- Figures: Parcels, On time (share of the delivered parcels with a checkout estimate that arrived on or before it), Median days (calendar days shipped to delivered), With a problem (any `shipment_problems` row, open or closed), Returned (status `returned`).
- A carrier or province under 20 parcels (`PG_DELIVERY_TOWER_MIN_PARCELS`) reads "Too few parcels" in place of its five figures; the figure cards above count every parcel. A parcel with no carrier is grouped as "Carrier not known".

## How is it laid out?
- Report screen (`ui/look-report-screens.md`): tools row with the month stepper (`.pg-seg` previous, month, next), a `.pg-seg` "Last 12 months", the period text and "Excel" quiet at the right; five figure cards (`pg_report_figures_html()`); the "Arrival days to check" frame; the table frame.
- Month: a carrier a `pg-report-l1` row; its provinces are `pg-report-l2` rows, folded, opened by a `pg-gather-toggle` "Provinces" in the carrier's cell (a small inline script toggles `hidden`). A province is named as first written; keys fold as `pg_delivery_tower_province_key()`.
- Last 12 months: a `.pg-seg` of the five figures above the table (`?figure=`), a carrier a row, a month a column; a month under 20 shows a dash with "Too few parcels" for screen readers.
- Excel (`?export=xlsx`): the table as shown, through `pg_xlsx_write_plain()`; a month view row per carrier and per province.
- The link segments "Problems" / "Carrier scorecard" wait for the Delivery problems screen (DT3).

## Where does the arrival suggestion show? (T12)
- `pg_delivery_tower_arrival_check()`: the method's carrier (its own, else what its code spells) and its country figure from the nightly summary; working-day median when the method counts no weekend day as transit, calendar median otherwise; shown when it differs from `base_transit_days` by a day or more.
- Scorecard: "Arrival days to check", an enabled method a row ("Standard delivery: measured 2.6 days, set 1") with a quiet "Open the method". Edit Shipping Method: the same sentence as the help line under Base Transit Time. Nothing is changed by either.

## And the carrier order? (T13)
- `pg_delivery_tower_carrier_order()` (pure): options by the province's on-time share from the summary (the country's where the province has none), unmeasured last; equal shares by the lower `rate()` price, a price before none.
- Shown by the order screen's Create Label chooser (DT4b, #10931, `delivery-tower-label-chooser.md`).

Tests: `tests/delivery_scorecard_test.php`.
