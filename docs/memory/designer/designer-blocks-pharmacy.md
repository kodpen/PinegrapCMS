---
name: designer-blocks-pharmacy
description: The Pharmacy block family (pharmacy- prefix): duty panel, reference readings, travel vaccines, medicine schedule; general blocks reused instead; no motion; rule and test
---

## Where does it live?
- `assets/js/style_designer_blocks_pharmacy.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `pharmacy-`, palette group "Pharmacy" (`bi-capsule`). Built for the Pharmacy template's polishing round ([[design-template-pharmacy]], `plans/design-template-own-looks-order.md`, `pharmacy-1`).

## Which blocks does it hold?
- `pharmacy-duty`: "When we are closed": three answers (our duty nights, closed right now, an emergency) as icon rows, beside a card "At the night window" (numbered steps, what to bring); under them an `alert-danger` line with a `tel:112` button. No rota by date.
- `pharmacy-readings`: "Know your numbers": a table each for blood pressure and fasting blood sugar, a row per band (`th scope="row"` holding a pill with an icon and a word: Normal, Slightly high, High), its figures (`{var:1} to {var:2}`), what to do; a note that a single reading is no diagnosis and the doctor's targets come first.
- `pharmacy-vaccines`: a table of travel vaccines: vaccine (row head), for whom, doses, how long before the trip; "Book a travel consultation".
- `pharmacy-schedule`: a medicine per row (name, how to take it), a column per time of day (morning, midday, evening, bedtime, each with an icon); a time without a dose says "No dose".

## Which general blocks does a pharmacy place instead?
- Health checks with a price: `services-price-list` / `services-icon-cards`. Prescription path: `steps-numbered`. Pharmacists: `team-grid` / `team-profiles`. Opening hours: `hours-card`. The branch: `locations-cards`. Health products: the shop's own pages.
- The readings, vaccine and schedule tables are not `pricing-comparison` / `compare-table` (plans in columns with checks) nor `hours-seasons` (a row per day, a column per season).

## What do its rules and texts follow?
- One rule: `pg-pharmacy-figures` (tabular figures on the three tables). Each table sits in a named, focusable `table-responsive` region.
- A quiet sector ([[design-template-own-look]]): no `props._fx`; the test holds every block still.
- Shared keys with a wrong Turkish sense are not reused: "Raised" (Toplanan), "Reading" (Okuyorum), "None" (Hiçbiri); the blocks say "Slightly high", "Your reading", "No dose".
- Figures are general guidance (blood pressure below 120/80 normal, 140/90 high; fasting sugar 70–99, 126 and above high), sample text the pharmacist edits.

## How is it tested?
- `tests/designer_blocks_test.php` checks the file like every family file; `tests/designer_blocks_pharmacy_test.php` runs it under Node: a section with one `h2` and no skipped level per block, no fixed colour or `rx-`/`prx-` class, every `{var}` filled, no year, no motion, every head cell with a scope, the 112 line, a meaning pill per band, the vaccine and schedule heads, the rule in the block stylesheet.

Related: docs/memory/designer/designer-blocks-general.md, docs/memory/designer/designer-blocks-ferry.md
