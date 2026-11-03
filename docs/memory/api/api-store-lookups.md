---
name: api-store-lookups
description: Store lookup lists over the external API: arrival dates, ship date adjustments, verified shipping addresses, referral sources; routes, scopes, the write path shared with the screens
---

## Which routes are there?
- `/store/arrival-dates`, `/store/ship-date-adjustments`, `/store/verified-addresses`, `/store/referral-sources`: list and GET one (`orders:read`), create (201), update (POST, PATCH also accepted), DELETE and its `POST …/delete` twin (`store:write`). No events.

## Where is a row written?
- `includes/fn/store_lookups.php`: the screens and the API handlers call the same save and delete functions; the screens' form checks decide what is refused.
