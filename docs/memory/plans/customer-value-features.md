---
name: customer-value-features
description: Plan customer-value, features U1 to U15: what counts as an order, who is a customer, the one summary the general job rebuilds, the Customers report's figures, rights, the API
---

## What counts, and who is a customer?
- (U1) One pure function says what counts: an order that is complete or exported and not cancelled; its value is its total less what was refunded, in the site's base currency. A marketplace order kept in another unit is left out and counted apart on the screen.
- (U2) A customer is the order's billing e-mail, folded; an order with no e-mail goes by its contact. Nothing keeps that key: it is worked out again at every rebuild.
- (U3) The same function and key are used by every figure of the report; no figure counts orders its own way.

## How is the summary kept?
- (U4) One summary, a JSON kept as a settings text: the tiles, the months, the cohorts, the groups' cells and the top customers, each currency apart, with the moment it was worked out. No table.
- (U5) The general job rebuilds it whole from the orders, under one claim, in runs bounded in time that go on where they stopped; a finished rebuild replaces the summary in one write. No write is added to the order path.
- (U6) Until the first rebuild has finished the screen says how far it is; after that it says as of when the figures are.
- (U7) The summary holds no name, address or e-mail: a top customer is kept as an order's number, and the name is read from that order when it is shown. An erased person leaves it at the next rebuild.

## What does the Customers report show?
- (U8) Tiles for a period of whole months: customers, the repeat purchase rate (customers with two orders or more), orders per customer, value per customer, days between orders.
- (U9) By month: value and orders from first-time against returning customers, as a chart and its table.
- (U10) Cohorts: the month of the first order against the months after it, twelve of them: the share of each cohort that ordered again.
- (U11) Groups by fixed bounds the screen states, never by moving fifths: last order within 30, 90, 180, 365 days or longer ago, against one, two to three, or four and more orders; each cell with its customers and value, named New, Returning, Loyal, At risk and Lost.
- (U12) Top customers by value, of all time and of the last twelve months. Names show, and the CSV is offered, only to whoever may also read contacts; others see the figures.

## What can be done with it?
- (U13) "Make a contact group" on a cell opens the group editor filled with the rules that exist (the last order's age and the number of orders): a group filled by rules, with no new kind of rule. It counts as those rules do, so its size can differ from the cell's, and the screen says so.
- (U14) `GET /reports/customer-value` gives the summary under the orders scope; the top customers' names only with the customers scope.

## Who sees it?
- (U15) Whoever may open the commerce reports, on a site with commerce on. A new screen under the reports menu; no existing report changes.
