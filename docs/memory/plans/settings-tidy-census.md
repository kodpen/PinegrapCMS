---
name: settings-tidy-census
description: Survey of the Site Settings categories (owner 2026-11-21: tidy every settings screen): the contradictions and scattered settings still open, with the plan that takes each
---

## What is this?
- Owner (2026-11-21): find and fix the settings screens' contradictions, gather settings spread over several places, make old screens modern. Architect (2026-11-21, message 11313): one category a plan, each asking the architect only for the parts it truly lacks.
- Read on development 1c77838b32; paths under `pinegrap/`. What was fixed is in each category's `ui/settings-<category>.md`; only the open lines stay here, and the plan that takes a line deletes it. The categories' order: `settings-tidy-census-order.md`.

## Which contradictions are still open?
- None. K2, the sales watch hidden in Operations alerts, is a card of its own since #11876 (`ui/settings-maintenance.md`).

## Which settings are still spread?
- Seller identity in General › Organization and again in Ecommerce › Product feeds (`commerce.php`): what each is read for is `ui/settings-ecommerce-programmes-feeds.md`; joining them waits for the architect, no plan takes it.
- Smaller, no plan: the GeoIP note and refresh twice (`firewall.php`, `languages.php`); two deletion gates, "Deleting many at once" (`mass_deletion`, Accounts and sign-in) and the Workspace's `ws_ai_bulk_delete`.

Related: docs/memory/plans/settings-tidy-census-order.md, docs/memory/plans/settings-scan-findings.md
