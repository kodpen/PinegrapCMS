---
name: settings-tidy-census
description: Survey of the Site Settings categories (owner 2026-11-21: tidy every settings screen): the contradictions and scattered settings still open, with the plan that takes each
---

## What is this?
- Owner (2026-11-21): find and fix the settings screens' contradictions, gather settings spread over several places, make old screens modern. Architect (2026-11-21, message 11313): one category a plan, each asking the architect only for the parts it truly lacks.
- Read on development 1c77838b32; paths under `pinegrap/`. What was fixed is in each category's `ui/settings-<category>.md`; only the open lines stay here, and the plan that takes a line deletes it. The categories' order: `settings-tidy-census-order.md`.

## Which contradictions are still open?
- (K2) The sales watch ("Take a product off sale on a price error", `maintenance.php`) is hidden and switched off with Operations alerts (`includes/fn/ops_alerts.php`). Behaviour is decided (`decisions-made-product-84.md`), its place is not: `settings-maintenance-tidy.md`.

## Which settings are still spread?
- Two Amazon S3 field sets in Updates and maintenance (`maintenance.php`), no S3 or FTP entry on Connections: `settings-maintenance-tidy.md`.
- Seller identity in General › Organization and again in Ecommerce's feeds (`commerce.php`): `settings-ecommerce-tidy.md` (EC5).
- Smaller, no plan: the GeoIP note and refresh twice (`firewall.php`, `languages.php`); two deletion gates, "Deleting many at once" (`mass_deletion`, Accounts and sign-in) and the Workspace's `ws_ai_bulk_delete`.

Related: docs/memory/plans/settings-tidy-census-order.md, docs/memory/plans/settings-scan-findings.md
