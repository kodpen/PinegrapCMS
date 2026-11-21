---
name: settings-tidy-census-order
description: Survey of the Site Settings categories, its second part: how old each category looks, what is already planned or decided, and the order of the per-category tidy plans
---

## How old does each category look?
- Look A today: Integrations, Assistants and agents (the only `pg_settings_fold()` user), Channels; Team and working hours nearly.
- Mixed: General, Appearance, SEO, Visitors and privacy, Languages and Translation, Accounts and sign-in, Fediverse, Updates and maintenance.
- Old: Ecommerce (127 fields, 18 popover rows, 16 plain radios), Accounting (the ERP card about 40 fields flat, `accounting.php:29-288`), Bookings (a fieldset a service and an always-open empty "New service"), Features (one long switch list, social three levels deep), Firewall and HTTPS (6 popovers, 6 radios, the live bans table), Communication's Chat card.
- Old parts: popover rows under switches; plain radios where `pg-seg` is the rule; cards over 8 fields; raw input tables; long help paragraphs; logs inside settings; a Ctrl multi-select for feed categories (`commerce.php:1120-1124`).
- Old buttons in helpers drawn into settings, not reached by the S5 pass: `includes/fn/address_autocomplete.php:804`, `carrier_api.php:648`, `carrier_labels.php:1446`, `geo.php:658`, `google_reviews.php:451`.

## What is already planned?
- Assistants and agents: `plans/assistants-settings-tidy*.md`. E-mail: `plans/mail-settings-one*.md`, `plans/mailboxes-tidy.md`. Features: `plans/features-switchboard*.md`. Visitors and privacy: `plans/privacy-settings-tidy*.md`. Firewall and HTTPS, Accounts and sign-in: `plans/security-settings-tidy*.md`.
- Credentials on category cards: connections-merges CX11 (`plans/connections-merges-build.md`); Ecommerce, Accounting, Integrations, Updates and maintenance and the Workspace's categories are still to come, and a tidy of one of them waits for its CX11 step.
- The starting-point sheet and the numbered-steps drawer: DX2 (#10885).

## What is decided and stays?
- One place per setting, the five groups, anchors and keys kept, one Save a pane: `decisions-made-product-14.md`. A drawer, never a second dialog: `decisions-made-panel-3.md`. An "Advanced" fold: `decisions-made-workspace-15.md`.
- AI crawlers in SEO (#3908); Bookings' own hours honouring Workspace holidays (`commerce/bookings-model.md`); a disabled feature's card never hidden (`ui/settings-card-save-rules.md`); the sales watch's behaviour (`decisions-made-product-84.md`).

## In which order are the plans written?
- One category a plan, each written when the queue allows and the session's PR before it has landed.
- 1. Features as one switchboard: `plans/features-switchboard.md`; Commerce and Accounting keep their switch and show on the board.
- 2. Visitors and privacy: `plans/privacy-settings-tidy.md`; every retention shown in one row, each still set beside its feature; K1 waits for the architect.
- 3. Bookings: services as list rows with a drawer, no empty block; staff hours in a drawer with links to Team and working hours.
- 4. Firewall and HTTPS with Accounts and sign-in: `plans/security-settings-tidy.md`; one Limits row, radios to `pg-seg`, popovers to folds, bans and logs as rows (K8); K4 (AI training against the firewall) asked there.
- 5. General and Appearance: the site address in General, K3, K5, K6, K7, footer links as list rows.
- 6. Communication: the Chat card as folded rows.
- 7. Ecommerce, after its CX11 step. 8. Accounting's ERP card split, after its CX11 step. 9. Updates and maintenance (cron steps, one S3, K2), after its CX11 step.
- 10. One small PR, no plan: the five helpers' old buttons and the K9 messages.
- Unverified: which date format wins (General or the language's); whether the "Workspace Settings" menu labels are meant.

Related: docs/memory/plans/settings-tidy-census.md
