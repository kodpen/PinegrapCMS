---
name: general-appearance-settings-tidy
description: Plan (owner 2026-11-21): General and Appearance as folded rows, one Site address row showing redirect and HTTPS, each field saying what it reaches, footer links as list rows
priority: 8
---

## What is asked?
- Owner (2026-11-21): fix the settings screens' contradictions, gather spread settings, make old screens modern. Item 5 of `plans/settings-tidy-census-order.md` (census K3, K5, K6, K7).

## What contradicts today? (development 6169b52410)
- (G1) The site address is set in three panes: Hostname in General › Server & Domain (`includes/settings/general.php:98-101`), "Redirect other host names to the site address" in SEO, Secure Mode in Firewall and HTTPS (`firewall.php:667`); General names neither.
- (G2, K7) "Proxy Address" (`general.php:102-105`) is the outgoing proxy (`PROXY_ADDRESS`, `init.php:469`: payments, updates, Mailchimp, Cloudflare), with no help line; Firewall's Trusted proxies are those in front of the site. The IP is a disabled box.
- (G3, K3) "Support E-mail Address" (`general.php:63-66`) is the From of all system mail and DKIM's fallback domain, and nothing says so; the label stays (`workflow/decisions-made-product-11.md`). Organization is one flat grid.
- (G4) Date Format is not the date of a page served in another language: that page uses the language's own format from Languages (`pg_sw_default_date_format()`, `includes/fn/widgets.php:620`); the card says nothing.
- (G5, K5) The acrylic effect's help speaks of "the device you are using" (`appearance.php:35`), though it is site-wide; the Dashboard card mixes site-wide Card Style and Panel Backdrop with Refresh Time, kept for this sign-in only (`ui/settings-dashboard-card.md`).
- (G6, K6) "Custom CSS" (`appearance.php:39-44`) styles the panel only (`includes/fn/output.php:68-78`), and Private Label's Control Panel Stylesheet URL is a second panel stylesheet in another card.
- (G7) Editor Version a plain radio pair (`appearance.php:187`); `div.switch` drawings in Theme, Rich-text Editor and Software; Spell Checker Engine a disabled box; Title Case labels starting "Enable".
- (G8) Private Label's switch hides its fields (`collapse-switcher`, `appearance.php:284`); five Footer Link pairs, ten boxes, drawn empty or not (`:264`).

## What does the plan settle itself?
- Both panes become folded rows (`pg_settings_fold()`); every switch `pg-switch`, Editor version `pg-seg`.
- General's rows: "Organization", "Site address", "Software", "Date and time"; Appearance's: "Panel look", "Dashboard", "Rich-text editor", "Private label branding" (`plans/general-appearance-settings-tidy-screens.md`).
- "Site address" holds Hostname, the IP as a line and "Outgoing proxy" with its line linking `#settings/firewall/pgset-proxies`; from GA4 two value rows show the host-name redirect (SEO) and Secure Mode (Firewall and HTTPS) with Change: each stays in its pane.
- Each field says what it reaches: Support E-mail Address, Date format's line on other languages, Custom CSS relabelled "Panel CSS" with the stylesheet URL named, the acrylic line "for everyone who uses the panel".
- The Dashboard row in two groups: "For everyone" (Card style, Panel backdrop, Reset widgets), "Only for you, until you sign out" (Refresh every).
- The footer links become up to five list rows, each edited in a drawer (`pg_settings_drawer()`), "Move up" / "Move down", posting today's `footer_link_<n>_label` / `_url`.

## What is left out?
- A refresh time kept per person (a column); the seller identity in Ecommerce's feeds (item 7).

## What binds it?
- Field names, ids, columns and what a save stores do not change; card ids and anchors keep working; one Save a pane; no schema change, no new part; the dashboard and private-label gates stay. The architect agreed (message 11506).

Related: docs/memory/plans/general-appearance-settings-tidy-steps.md, docs/memory/plans/general-appearance-settings-tidy-screens.md, docs/memory/plans/general-appearance-settings-tidy-screens-appearance.md, docs/memory/plans/settings-tidy-census-order.md
