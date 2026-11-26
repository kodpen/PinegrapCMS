---
name: settings-tidy-census-order
description: Survey of the Site Settings categories, its second part: how old each category looks, what is already planned or decided, and the order of the per-category tidy plans
---

## How old does each category look?
- Look A today: Integrations, Assistants and agents, Features, Communication, Firewall and HTTPS, Accounts and sign-in (the `pg_settings_fold()` users), Channels; Team and working hours nearly.
- Mixed: General, Appearance, SEO, Visitors and privacy, Languages and Translation, Fediverse, Updates and maintenance.
- Old: Ecommerce (127 fields, 18 popover rows, 16 plain radios), Bookings (a fieldset a service and an always-open empty "New service"). Accounting's flat ERP card is folded rows since 2026-11-26 (`ui/settings-accounting.md`).
- Old parts: popover rows under switches; plain radios where `pg-seg` is the rule; cards over 8 fields; raw input tables; long help paragraphs; logs inside settings; a Ctrl multi-select for feed categories (`commerce.php:1120-1124`).
- Old buttons in helpers drawn into settings, not reached by the S5 pass: `includes/fn/address_autocomplete.php:804`, `carrier_api.php:648`, `carrier_labels.php:1446`, `geo.php:658`, `google_reviews.php:451`.

## What is already planned?
- Assistants and agents: done (AT1 to AT6, `ui/settings-fold-rows.md`). Features: done (`ui/settings-features.md`). E-mail: done (`ui/settings-mail.md`). Mailboxes: done (`ui/look-settings-mailboxes.md`). Visitors and privacy: done (`ui/settings-privacy.md`). Bookings: done (`ui/booking-settings.md`). Communication: done (`ui/settings-communication.md`). Firewall and HTTPS with Accounts and sign-in: done (`ui/settings-firewall.md`, `ui/settings-accounts.md`).
- Credentials on category cards: all moved to Connections, the plan closed on 2026-11-24 (`ui/connections-one-place.md`); a tidy no longer waits for a connections step.
- The starting-point sheet and the numbered-steps drawer: DX2 (#10885).

## What is decided and stays?
- One place per setting, the five groups, anchors and keys kept, one Save a pane: `decisions-made-product-14.md`. A drawer, never a second dialog: `decisions-made-panel-3.md`. An "Advanced" fold: `decisions-made-workspace-15.md`.
- AI crawlers in SEO (#3908); Bookings' own hours honouring Workspace holidays (`commerce/bookings-model.md`); a disabled feature's card never hidden (`ui/settings-card-save-rules.md`); the sales watch's behaviour (`decisions-made-product-84.md`).

## In which order are the plans written?
- One category a plan, each written when the queue allows and the session's PR before it has landed.
- 5. General and Appearance: done (`ui/settings-general.md`, `ui/settings-appearance.md`).
- 7. Ecommerce, after its CX11 step. 8. Accounting: done (`ui/settings-accounting.md`). 9. Updates and maintenance: done (`ui/settings-maintenance.md`; the two S3 sets stay apart).
- Which date format wins: a page in another language uses that language's format, said under Date Format (`ui/settings-general.md`). The Workspace menu's "Workspace Settings" entry stays: it names the group (`workspace/workspace-settings-place-names.md`).

Related: docs/memory/plans/settings-tidy-census.md
