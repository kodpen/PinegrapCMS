---
name: erp-percent-shares
description: Whole-percent shares of a set of amounts (erp_percent_shares()) add up to 100; the dashboard's Receivables by age card reads them once for its bar and legend.
---

## How are percentage shares of a total worked out?
`erp_percent_shares($amounts)` (`includes/erp/money.php`) splits 100 over the amounts by largest remainder through `erp_allocate()`, so the shares always add up to 100; when the amounts sum to 0 every share is 0. Never `round($amount * 100 / $total)` per row in a screen: three equal parts read 33% + 33% + 33% = 99% and 100 / 100 / 400 read 17% + 17% + 67% = 101%.

## How does the dashboard's Receivables by age card use them?
`erp_dashboard.php` computes the bucket shares once and uses the same figure for each `.progress-stacked` segment's width and `aria-valuenow` and for the legend, so the bar fills its track and the legend adds up. A non-empty bucket whose share comes to 0 (below half a percent) gets no segment and its legend reads `<1%` (`'&lt;' . erp_percent_text(1)`), never 0% beside a non-zero amount.

## What was it fixing?
Each share was rounded on its own: the legend summed to 99% or 101%, the bar left up to a 2% sliver of empty track, and a 0,50 TL bucket of 1.000,00 TL read 0% with a 0-wide segment. Tests: `tests/erp_money_test.php` (`test_erp_percent_shares_*`).
