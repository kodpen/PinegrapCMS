---
name: settings-tidy-census-order
description: Survey of the Site Settings categories, its second part: which categories still look old, their plans, and what is decided for every tidy
---

## Which categories still look old?
- Ecommerce: 127 fields in ten cards, 18 popover rows, 16 plain radios, a Ctrl multi-select for feed categories (`commerce.php`): `settings-ecommerce-tidy.md`.
- Accounting: the ERP card about 40 fields flat (`includes/settings/accounting.php`): `settings-accounting-tidy.md`.
- Updates and maintenance (cron steps, two S3 sets, K2 of `settings-tidy-census.md`): `settings-maintenance-tidy.md`.
- Mixed, with no plan: SEO, Languages and Translation, Fediverse; their faults are in `settings-scan-findings.md`.
- Old buttons in helpers drawn into settings: `includes/fn/address_autocomplete.php`, `carrier_api.php`, `carrier_labels.php` (Ecommerce's plan), `geo.php`, `google_reviews.php`.
- Done: General, Appearance, Features, E-mail, Mailboxes, Visitors and privacy, Bookings, Communication, Firewall and HTTPS, Accounts and sign-in, Assistants and agents (their `ui/settings-*.md`); the hand-over to Connections (`ui/settings-crossings-rule.md`).

## What is decided and stays?
- One place per setting, the five groups, anchors and keys kept, one Save a pane: `decisions-made-product-14.md`. A drawer, never a second dialog: `decisions-made-panel-3.md`. An "Advanced" fold: `decisions-made-workspace-15.md`.
- AI crawlers in SEO (#3908); a disabled feature's card never hidden (`ui/settings-card-save-rules.md`); the sales watch's behaviour (`decisions-made-product-84.md`).

## In which order?
- One category a plan, each written when the queue allows: Ecommerce, Accounting and Updates and maintenance run side by side (other files); the panes without a plan follow them.

Related: docs/memory/plans/settings-tidy-census.md
