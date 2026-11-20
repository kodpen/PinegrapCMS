---
name: look-settings-other-categories
description: Site Settings Privacy to General and the settings page toolbar in look A (S5c of panel-old-screens): buttons, pills, tables, the date format segments, what stayed
---

## Which files moved?
- `includes/settings/privacy.php`, `privacy_analytics.php`, `languages.php`, `seo.php`, `workspace_team.php`, `fediverse.php`, `appearance.php`, `api.php`, `general.php`, the page `screen.php` and `prep.php` (step S5c of the plan panel-old-screens). Contact had nothing old left but the popover arrows. No `*.save.php` changed: field names and values stay.
- `tools/check_look.php` counts 0 old buttons in all of them; their baseline entries are gone.

## What changed in the cards?
- Card and row buttons are `pg-button pg-button-quiet pg-button-sm` (Connect a Google account, Read the figures now, Open the Translations screen, Search and audits, Block All Search Engines, robots.txt, llms.txt, Look me up, Lift, Try again, Reset Widgets, Release, Update Bot IP Lists, New department, Add a holiday, Add a calendar address).
- Stop using this property and the Fediverse Block are `pg-button-danger pg-button-sm`.
- A department's Edit and a calendar's Days are `pg-button-ghost pg-button-sm`, its archive button `pg-icon-button pg-icon-button-sm`.
- The holiday and calendar drawers' Add / Add and read are the drawer's one `pg-button-fill`, no icon.
- The IndexNow key left the SEO card for IndexNow's page of Connections in CX11a (`connections-indexnow-page.md`).
- Group headings: SEO's Site Map and Crawling, Firewall, llms.txt and prep's Installed Application and Structured Data stay `h6` (the settings grid's group-divider rule keys on `h6:first-child`) with `pg-group-label`; Google Analytics figures, Target languages and Blocked accounts and servers are `h3.pg-group-label` (ids kept, `tests/settings_group_names_test.php`).
- Pills: Google key stored teal / no key gray, an AI engine's "ready" teal, Fediverse delivery status (Waiting amber, Sent teal, Failed rose), the e-document capability gray and test environment amber.
- Tables (Team members, the three Fediverse tables, Legal Documents' fields) are `table table-hover mb-0 pg-embedded-table` (`look-embedded-table.md`).
- Number fields in an adorned box: inline `text-align: right` became `text-end` (Privacy, Appearance, API, the sign-in throttle in prep).
- Small text outside alerts is `pg-text-meta` (#10485): prep's e-document "Last checked" line (tone kept), the empty Departments text (`ws-empty`) and a holiday calendar's Days list.
- Icon tiles: the four Application API switches (`system`) and the Integrations link rows (34 px tile instead of a grey icon).

## What are the Date Format and Time Format?
- General's two radio pairs are a segmented control: `fieldset` > `div.pg-seg` > `label.pg-seg-item` holding a `visually-hidden` radio. `backend.src.css` draws the thumb for `.pg-seg-item:has(> input:checked)` and the focus ring for `:has(> input:focus-visible)`, in the shared `pg-seg` rules. The names and values posted are unchanged.

## What changed on the settings page?
- The category menu button is `pg-button-quiet` (Save is the page's one fill), the section jumps are `pg-button-ghost pg-button-sm` links (no `.chip`), the section count is `pg-text-meta`, the previous/next icon buttons sit in `d-flex gap-1`. The save bar's `bottom: .5rem` moved from `style=` into `backend.src.css`.

## What stayed?
- The popover arrows' inline position, the e-document fields' `display:none` (scripts toggle them), the share card sample's inline width (no utility caps it at 480 px), the Safari autofill decoy's inline position, the `alert` notes.
- The calendar rows' buttons are drawn by `includes/workspace/workdays.php` with the class each caller passes; the Workspace Settings callers are not this step's.

Related: docs/memory/ui/look-settings-screens.md, docs/memory/ui/look-settings-maintenance-security.md, docs/memory/ui/look-commerce-settings-screens.md
