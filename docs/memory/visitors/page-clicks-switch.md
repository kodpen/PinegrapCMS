---
name: page-clicks-switch
description: The Clicks and scrolling switch (CM3 #9913): the page_clicks settings text, its one reader and one saver in page_clicks.php, who may turn it on and from where
module: page_clicks
---

## How is it stored?
- Settings text `page_clicks` (`PG_PAGE_CLICKS_SWITCH`) = `1` when on; off removes the row (`pg_page_clicks_switch_value()`). Missing is off, so every site and a new install start off (C16).

## Who reads and writes it?
- Architect (2026-11-16, agent channel message 6222): one reader `pg_page_clicks_switch_on()` and one saver `pg_page_clicks_switch_save($on)` in `includes/fn/page_clicks.php`. The Visitors section, the Features card and the script's gate use them; CM4's layer will too; nobody writes a second one.
- The saver checks nothing: its caller has checked the Features right and the form token.

## Where can it be turned on?
- The Visitors section's "Turn on" button posts to `visitors_clicks_switch.php`: `validate_area_access($user, 'manager')` (role up to 2, the Features save's right, never `validate_visitors_access()`, which lets a User with the Visitors right in), POST only, `validate_token_field()`, then the saver and back to `visitors.php#clicks` with the period (`ui/visitors-clicks-section.md`).
- Site Settings, Features, card "Clicks and scrolling" (`pgset-page-clicks`, CM2 #9946): the switch drawn from the reader, saved through the saver only when the card was on the form (`page_clicks_card`), under the settings dialog's manager gate; the sentence the site can copy into its privacy text shown whole as a selectable text block, per device type and "no IP address" (C16; architect, message 6542).
