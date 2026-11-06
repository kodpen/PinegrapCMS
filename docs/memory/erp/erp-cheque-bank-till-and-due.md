---
name: erp-cheque-bank-till-and-due
description: Which bank till a cheque step may use (screen and API alike), and what the cheque list's "Due within a week" card counts.
---

## Which bank till may a deposit, collect or pay step use?
- `erp_cheque_bank_till_refusal()` is asked in `erp_cheque_step()`, so the screen and the API refuse alike: the till must exist, be active, not be the cheque's portfolio, and keep the portfolio's currency (deposit included, or the cheque could never be collected).
- The bank already stored on the cheque (`bank_till_id`) may be inactive, so a cheque at a bank taken off later can still be collected.
- Before, only the API checked: the screen let a stale form deposit into a missing till and collect into an inactive one, because `allow_inactive_till` on `erp_post_transfer()` covers both tills, not only the portfolio.

## What does the list's "Due within a week" card count?
- Open cheques (portfolio, deposited, given) due from today to a week on. Past-due open ones are counted apart, `erp_cheque_totals()['overdue']`, shown in red under the number.
- Before, the count had no lower bound and took in cheques overdue for years, unlike the invoice list's "Due this week".
