---
name: settings-privacy
description: Site Settings › Visitors and privacy as seven folded rows (Visitor tracking, How long we keep data, KVKK notice…): what each holds, its line, ids and anchors kept
module: privacy_settings_fold
---

## How is the pane drawn?
- Folded rows (`pg_settings_fold()`, `ui/settings-fold-rows.md`), first row open: Visitor tracking, How long we keep data, Cookie consent, Legal documents, KVKK notice, Accessibility statement, Privacy centre. Order, names, icons, lines: `includes/fn/privacy_settings_fold.php` (`pg_privacy_fold_order()`, `_face()`, `pg_privacy_fold_<part>()`, pure but for `lang()`). Test: `tests/settings_privacy_fold_test.php`.
- No popover, no `div.switch`: every switch is `pg-switch`, its fields drawn under it whatever its state. One Save for the pane. Built in #10964 (rows), #10998 (retention row), #11096 (KVKK switch, Site chats rule).

## What does each row hold?
- Visitor tracking (`pgset-analytics`): line "Visits are recorded" / "not recorded", "· Google Analytics connected"; pill On/Off. "Record visits" (`visitor_tracking`), "Remember a tracking code for" (`tracking_code_duration`), "Paid tracking code mark" (`pay_per_click_flag`), Google Analytics as a connect row (`ui/connections-google-analytics-page.md`).
- How long we keep data (`pgset-retention`): "Keep visitor records for" (`visitor_retention_days`), "Remove IP addresses after" (`visitor_ip_days`), "Keep a daily summary of every day" (`visitor_summary`) with the Database Engine link; then the overview of every other period (`ui/settings-privacy-retention.md`). Line `pg_privacy_fold_retention()`: the visitor records' days and when IP addresses go; no pill.
- Cookie consent (`pgset-cookies`): the switch, Cookie Policy Address, the `#cookie-settings` line; held scripts and Google tags under a `details` "Your own scripts".
- Legal documents (`pgset-legal`): the switch and its help, the two templates, "Fields you can use in a template" under a `details`; drawn while off.
- KVKK notice (`pgset-kvkk`): "Show the KVKK notice" (`ecommerce_kvkk_enabled`, its own switch: `commerce/kvkk-notice-switch.md`), KVKK Notice Page, Explicit Consent Sentence. Line names what is filled; pill Off, "In use" or "Not used".
- Accessibility statement (`pgset-a11y-statement`): the fields, "Print the statement" quiet; line "Dated …" or "Not filled".
- Privacy centre (`pgset-privacy-centre`): line "Personal data inventory, retention rules and requests"; open, one help sentence, then "Open the Privacy centre".

## What stayed where?
- Card ids, field names and `privacy.save.php`; old anchors (`pg_settings_legacy_anchors()`): `visitor_retention_days`, `visitor_ip_days`, `visitor_summary` land on `pgset-retention`. In the dialog a field anchor opens its row (`pgSettingsFoldReveal()`); the page path follows only `#pgset-` anchors, so Database Engine links `settings_privacy.php#pgset-retention`.
- Search: the retention row by "retention", "IP address", "how long"; the KVKK row by "kvkk", "explicit consent", "privacy notice" (`registry.php` keywords).
- Chat Retention Period left Communication › Chat: `core/privacy-rule-site-chats.md`.

Related: docs/memory/ui/settings-privacy-retention.md, docs/memory/ui/settings-fold-rows.md, docs/memory/core/privacy-log-retention.md, docs/memory/commerce/kvkk-notice-switch.md
