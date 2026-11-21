---
name: general-appearance-settings-tidy-steps
description: Plan general-appearance-settings-tidy, steps GA1 to GA5: General as folded rows, Appearance as folded rows, footer links as list rows, the Site address row's value rows, close-out
---

## Which steps are there?
- GA1 (M) done (#11013): General as folded rows (`pg_settings_fold()`) "Organization", "Site address", "Software", "Date and time", each with its state line. Organization in three groups (Name and address, Support e-mail address with its line, Seller); the IP a line, not a box; "Proxy Address" labelled "Outgoing proxy" with its line and the link to `#settings/firewall/pgset-proxies`; Verbose database errors a `pg-switch`; Date format's line on other languages. Fixes G2, G3, G4, G7 (General).
- Tests of GA1: `tests/settings_card_ids_test.php` and `settings_card_names_test.php` with `pgset-organization`, `pgset-server`, `pgset-software`, `pgset-datetime` kept; every field posts under its old name, `general.save.php` unchanged; the registry keywords gain "outgoing proxy" and keep "proxy address" (architect, message 11506: an old label stays searchable).

- GA2 (M), done (#11012): Appearance as folded rows "Panel look" (`pgset-theme`), "Dashboard", "Rich-text editor", "Private label branding". The acrylic switch's line "for everyone who uses the panel"; "Custom CSS" labelled "Panel CSS", its line naming the private label stylesheet; the Dashboard row in its two groups; Editor version a `pg-seg`; the spell checker a state line; Private label's fields drawn while off (no `collapse-switcher`). Fixes: G5, G6, G7 for Appearance, G8's switch.
- Tests of GA2: the card ids kept; a manager's pane still has no Dashboard row and no Private label row; Reset widgets still posts `dashboard_reset_widgets`; `appearance.save.php` unchanged; the keywords gain "panel css" and keep "custom css".

- GA3 (S) after GA2: the five footer links as list rows (label and address), "Add a link" while fewer than five, each edited in a drawer (`pg_settings_drawer()`), "Move up" / "Move down" and "Remove" in a row's menu; the drawer's fields post `footer_link_<n>_label` / `_url` in row order, a removed link posts empty. The order from one pure `pg_private_label_links_order()`, red test first. Fixes: G8.

- GA4 (S) after GA1 and DX2b (`plans/design-examples-patterns-mail.md`, "Value rows"): in "Site address", value rows "Other host names" (Redirected / Not redirected, Change to `#settings/seo/pgset-seo`) and "HTTPS" (Secure Mode on / off, Change to `#settings/firewall/pgset-headers`). Writes nothing. Fixes: G1.

- GA5 (S) close-out: `ui/settings-general.md` and `ui/settings-appearance.md` written (rows, what moved, what stayed); `ui/settings-dashboard-card.md`, `ui/theme-screens.md` and `ui/look-settings-other-categories.md` corrected; item 5 of `plans/settings-tidy-census-order.md` removed; this plan's files deleted.

## Which steps run side by side?
- GA1 and GA2: different files; each edits only its own category's lines of `registry.php`. GA3 waits for GA2, GA4 for GA1.

## How is a step checked?
- A step copies its parts from the Design examples screen and writes no recipe of its own; it renders the pane at 1440 and 390 px before ready, as an administrator and as a manager, and writes "Screen check" in the PR.

Related: docs/memory/plans/general-appearance-settings-tidy.md, docs/memory/plans/general-appearance-settings-tidy-screens.md, docs/memory/plans/general-appearance-settings-tidy-screens-appearance.md
