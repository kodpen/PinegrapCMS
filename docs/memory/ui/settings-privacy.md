---
name: settings-privacy
description: Site Settings › Visitors and privacy as folded rows: the six rows and their state lines, what each row holds, what stayed (ids, field names, the save), the KVKK notice's own row
module: privacy_settings_fold
---

## How is the pane drawn?
- Folded rows (`pg_settings_fold()`, `ui/settings-fold-rows.md`), first row open, in this order: Visitor tracking, Cookie consent, Legal documents, KVKK notice, Accessibility statement, Privacy centre. The order, names, icons and lines: `includes/fn/privacy_settings_fold.php` (`pg_privacy_fold_order()`, `pg_privacy_fold_face()`, `pg_privacy_fold_<part>()`, pure but for `lang()`).
- The purpose line is the category's description in `registry.php`: what the site records of its visitors, how long it keeps personal data and what it asks them.
- No popover and no `div.switch`: every switch is `pg-switch`, its fields drawn under it whatever its state. Test: `tests/settings_privacy_fold_test.php`.

## What does each row say and hold?
- Visitor tracking (`pgset-analytics`): "Visits are recorded" / "Visits are not recorded", "· Google Analytics connected" when the tag is on; pill On/Off. Holds "Record visits" (`visitor_tracking`), "Remember a tracking code for" (`tracking_code_duration`), "Paid tracking code mark" (`pay_per_click_flag`), the visitor periods and the daily summary, and Google Analytics as a connect row (`#pgsub-google-analytics`).
- Cookie consent (`pgset-cookies`): the switch, Cookie Policy Address, the `#cookie-settings` line; the held scripts and Google tags help under a `details` "Your own scripts".
- Legal documents (`pgset-legal`): the switch and its help, the two templates, "Fields you can use in a template" under a `details`; drawn while off.
- KVKK notice (`pgset-kvkk`, its own card since this row): KVKK Notice Page and Explicit Consent Sentence. The line names what is filled and ends "used while Legal documents is on"; pill "In use" only while Legal documents is on and something is filled (`pg_legal_kvkk_settings()`).
- Accessibility statement (`pgset-a11y-statement`): the fields as before, "Print the statement" a quiet button; the line "Dated …" or "Not filled".
- Privacy centre (`pgset-privacy-centre`): the line is the centre's sentence; the row holds only "Open the Privacy centre".

## What stayed?
- Card ids, field names and `privacy.save.php`; old anchors (`pg_settings_legacy_anchors()`) land on the cards, and the client opens the row a card or field sits in (`pgSettingsFoldReveal()`).
- The search finds the KVKK row by its keywords (kvkk, explicit consent, aydınlatma metni) and Visitor tracking by "retention" and "IP address".

Related: docs/memory/ui/settings-fold-rows.md, docs/memory/ui/screen-composition-rules.md, docs/memory/commerce/checkout-kvkk-notice.md
