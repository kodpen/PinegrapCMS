---
name: api-panel-coverage
description: How far the external API covers what the panel manages (gap list #5239, X1-X13 landed; audit #7967), how a new write route reuses the screen's write path, and what deliberately has no endpoint
---

## What does the external API cover of the panel?
- The gap list (#5239) compared the panel screens with `integration.php`; every gap it found is an endpoint now (X1-X13, last #6574): store zones, tax zones, shipping methods, countries, states, currencies, lookup lists, returns, subscriptions, commissions, users read and write, `POST /orders`, `POST /offers`, campaigns and profiles, calendars, locations and reservations, form submissions, `/design/` ads, dialogs and common regions. Each has its topic file in this folder.
- The storefront API and the vendor client were outside that comparison.

## What did the second audit (#7967, 2026-11-07) find?
- On a sandbox with only the installer's sample records (ERP, Workspace on), 245 GET routes and 353 `dry_run` writes ran: no 500, no PHP output in a body. Not called (no `dry_run`): `POST /auth/login`, `/auth/pair`, `/auth/refresh`, `/auth/logout`, `/push/test`, `/experiments/{id}/start`, `/pause`, `/end`. Every `pg_announce()` name is in the event catalogue.
- Gaps (plan `api-forms-for-agents.md`), screen mirrored -> what to add:
  - G1 `view_stock_moves.php` -> `GET /inventory/moves`: landed (PR #8097), `api-inventory-locations.md`.
  - G2 `edit_comment.php` Answer box -> `POST /comments/{id}/answer` through `pg_product_question_answer_save()` (`comments:write`): landed (PR #8116), `api-product-question-answer.md`.
  - G3 `edit_contact_group.php` -> `by_rule` on ContactGroup, `POST /contact-groups/{id}/refresh` (the audience_refresh action).
  - G4 `edit_submitted_form.php` History -> `GET /forms/{id}/submissions/{submission_id}/revisions`, one revision and its restore: done (`api/api-form-submission-revisions.md`).
  - G5 `edit_contact.php` suppressed badge, `view_contacts.php` -> `email_suppressed` on Customer, `POST /customers/{id}/allow-mail` (`pg_mail_bounce_allow_contact()`).
  - G6 `experiments.php` sheet -> `dry_run` on start, pause, end.
  - G7 `DELETE /pages/{id}`, `/files/{id}`: `pg_recycle_folder_id(true)` makes the bin before the stop; its log reads `$_SESSION['sessionusername']`, absent on the API path.
- Not routes, for a technical reason: an import (`import.php`) is a run over many requests (cursor file, run lock, 8 s slices, an uploaded source, 30-day undo); the records it writes are reachable one by one. A design template is installed in the designer: `pg_design_template_prepare()` writes rows, the pages stay unsaved tabs until Publish there, and no server-side publish exists.

## How is a write route for a panel screen added?
- First move the screen's inline SQL into one `includes/fn/` function, and have the screen and the handler both call it, so the screen's checks and refusals hold for the API too. Each step tests its handlers; `php tools/check_api_schema.php` covers the schema row.
- Store writes take `store:write` (commerce ceiling, `api-store-zone-writes.md`); store reads stay under `orders:read`.

## What has no endpoint on purpose?
- Settings, firewall, sessions, backups, updates: `api-settings-no-endpoint.md`.
- Redirects need none of their own: short links carry `redirect_code` and `*` patterns (`POST /short-links`).
- An HTML campaign: `api-campaign-create.md`.
- Saved reports and the back-in-stock list wait for a client that asks; such a need is free work or an architect issue, not a plan step.
