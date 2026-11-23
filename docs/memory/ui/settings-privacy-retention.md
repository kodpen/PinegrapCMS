---
name: settings-privacy-retention
description: Visitors and privacy › How long we keep data: every retention period set elsewhere as value rows with Change (pg_privacy_retention_overview()), why each stays where set
module: privacy_retention
---

## What does it show?
- `pg_privacy_retention_overview($facts)` in `includes/fn/privacy_retention.php` (pure but for `lang()`, `tests/privacy_retention_overview_test.php`): one value row per period set elsewhere, its value today and "Change" opening its card. One inner card [Value rows]; under it the meta line: erasures and rule runs stay at least the record years (`core/privacy-log-retention.md`).
- Rows: Audit trail lines (`security/pgset-audit`, only once its column exists), Consent history (fixed, no Change), Firewall log (`firewall/pgset-waf-logs`, with its row limit), API log (`api/pgset-api-storage`), Cancelled bookings (fixed, only with bookings, `commerce/booking-retention.md`), then the Privacy centre's rules Site chats, Passive contacts, Form submissions, Incomplete carts, Orders, Closed requests (Change opens `privacy_centre.php`).
- A rule's line: "Off: kept until deleted", "On, waiting for a dry run and your yes: kept until deleted", or "Kept N days/years". A fixed row says why in its line.
- A new period set outside this pane gets a row here and a key in `pg_privacy_inventory_retentions()` (`core/privacy-inventory.md`).

## Why is each period still set elsewhere?
- Each period stays beside what bounds the same data (the firewall log by its size limit, the audit trail on Security, the rules in the Privacy centre); this row only shows them, so one look shows every period and where it is set: one place per setting (`workflow/decisions-made-product-14.md`). Built in #10998.

Related: docs/memory/ui/settings-privacy.md, docs/memory/core/privacy-log-retention.md, docs/memory/core/privacy-retention-rules.md
