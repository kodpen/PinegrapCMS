---
name: visitors-places
description: The places and devices section of the Visitors overview (includes/visitors/places.php): country and city top lists, device and first/return split bars, where a place comes from
---

## What does the section show?
- Countries and cities: the top 10 values of `pg_visitor_breakdown('country' | 'city', …)` with visits and their share of all the period's visits (not of the visits that know a place). An empty value is never listed.
- Under the lists one line: a place is known for N of M visits (the sum of the country rows with a value).
- Devices: one bar of segments desktop, mobile, tablet and unknown, with a legend of visits and share; first and return visits likewise (`first_visit` values `1` and `0`).
- No visit in the period: the section frame with `pg_visitors_empty()`. An unknown dimension (`pg_visitor_breakdown()` false for `device`) draws nothing.

## Where does a visit's country and city come from?
- Not from the IP: `billing_information.php` writes the billing address's city, state, zip and country code into the visit's row. So most visits have none, and the line under the lists says so.
- `pg_visitors_places_country_label()` names a two-letter code with `pg_geo_country_name()` (panel language); anything else is shown as stored.

## How is the unknown part counted?
- `pg_visitors_places_split($rows, $total, $labels)` (pure): named values matched without regard to case; the unknown part is the period's total visits minus the named ones, so an empty value, an unnamed value and the summary's rest row (never listed by the breakdown) all land there. It is listed only when it has visits; a total under the named visits is raised to them, so no share goes over 100 %.
- Shares: one decimal under 10 %, whole from 10 % up, `< 0.1 %` for a part too small to show (`pg_visitors_places_share_text()`).

## What does the stylesheet hold?
- The `places` block of `assets/css/pg_visitors.src.css`: two auto-fit grids, the split bar (`.pg-visitors-split`, segment colours `.pg-visitors-split-0`…`-5` from the `--pg-*-ink` tokens, unknown in a faded secondary ink) and its legend.
- Tests: `tests/visitors_places_test.php`.
