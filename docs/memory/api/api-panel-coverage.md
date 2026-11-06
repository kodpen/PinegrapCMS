---
name: api-panel-coverage
description: How far the external API covers what the panel manages (gap list #5239, steps X1-X13 landed), how a new write route reuses the screen's write path, and what deliberately has no endpoint
---

## What does the external API cover of the panel?
- The gap list (#5239) compared the panel screens with `integration.php`; every gap it found is an endpoint now (X1-X13, last #6574): store zones, tax zones, shipping methods, countries, states, currencies, lookup lists, returns, subscriptions, commissions, users read and write, `POST /orders`, `POST /offers`, campaigns and profiles, calendars, locations and reservations, form submissions, `/design/` ads, dialogs and common regions. Each has its topic file in this folder.
- The storefront API and the vendor client were outside that comparison.

## How is a write route for a panel screen added?
- First move the screen's inline SQL into one `includes/fn/` function, and have the screen and the handler both call it, so the screen's checks and refusals hold for the API too. Each step tests its handlers; `php tools/check_api_schema.php` covers the schema row.
- Store writes take `store:write` (commerce ceiling, `api-store-zone-writes.md`); store reads stay under `orders:read`.

## What has no endpoint on purpose?
- Settings, firewall, sessions, backups, updates: `api-settings-no-endpoint.md`.
- Redirects need none of their own: short links carry `redirect_code` and `*` patterns (`POST /short-links`).
- An HTML campaign: `api-campaign-create.md`.
- Saved reports and the back-in-stock list wait for a client that asks; such a need is free work or an architect issue, not a plan step.
