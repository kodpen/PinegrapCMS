---
name: store-credit-steps-2
description: Steps SC5-SC7 of plans/store-credit.md: the staff and account screens with mails and expiry, API and webhooks, close-out; claim as [plan:store-credit] SC<n>
---

## Which steps are there?
- Sizes, tests and memory rules as in `store-credit-steps.md`; SC5 runs beside SC2-SC4 once question 2 is answered.

- SC5 (M) Screens, mails and expiry, cut in three. After SC1 and question 2.
  - SC5a (S) The customer's card (`edit_user.php`): a "Store credit" section with the balance, the cards, Issue (amount, reason, expiry), Void. Done in PR #6641. Memory: `commerce/store-credit-screens.md`.
  - SC5b (S) My Account "Store credit": the built-in templates and the designer widget (`designer/account-widget-forms.md`): balance, cards and what used them, the points conversion of SC3. Done in PR #6660. Memory: `commerce/store-credit-screens.md`.
  - SC5c (S) Mails `credit_issued` and `credit_expiring` as campaign profile actions with their fields; the general job expires cards on their date and sends the expiring notice 14 days ahead once (a `notified` bit, question 2: a migration step). Memory: `commerce/store-credit-mails.md`.

- SC6 (S) API, MCP, webhooks, personal data. After SC2-SC5.
  - `GET /customers/{id}/credit` (balance, cards) and `POST` (issue, void) with scopes and `dry_run` (`api/api-endpoint-rules.md`); events `credit.issued`, `credit.used`, `credit.expired` through `pg_announce()`; the export lists a customer's cards, erasure voids them and clears `user_id`. Memory: `api/api-customer-credit.md`.

- SC7 (S) Close-out. After SC1-SC6.
  - PR body names `[NEW] Added store credit: points convert to credit, refunds can go to credit, and a customer's balance is applied at checkout`; decisions to `commerce/`; the two plan files and the line in `feature-plans-2026-11.md` deleted; `php tools/memory_index.php`.
