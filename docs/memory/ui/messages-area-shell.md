---
name: messages-area-shell
description: The Communication area (MA1 #9182, MA7 #9200): menu row 9, five tabs and gates, the tab strip, doors, the interim tabs, the Channels cards
module: messages_area
---

## What is the shell?
- Main menu row 9 under Marketing is "Communication" (İletişim), slot and icon kept, gate as before: role <= 2 or the Campaign Manager right. Its panel lists the tabs the person may open, then "New campaign" (`add_email_campaign.php`); history and profile entries left the panel.
- Five tabs, each its own screen and gate (`pg_messages_area_tabs()` in `includes/fn/messages_area.php`): Campaigns `view_email_campaigns.php`, Automatic sends `messages_automatic.php`, Templates `messages_templates.php`, Sends `messages_sends.php` (all `validate_email_access()`), Channels `messages_channels.php` (role <= 2).
- Architect's ruling behind it: `plans/messages-area-decided.md` (#9081).

## How is the tab strip drawn?
- `pg_messages_tab_strip_for($key, $user)` prints `ul.nav.nav-tabs` links, the open one `active` with `aria-current="page"`; a tab the viewer may not open is left out, and with fewer than two tabs no strip is drawn. Links, not `role=tab`: each tab is an address.
- A new tab screen uses `pg_messages_tab_page()` (heading "Communication", the strip, the body, the footer). An old screen inside the shell prints the strip above its toolbar.

## Which old screens sit inside the shell?
- Campaigns tab: `view_email_campaigns.php` (below). Automatic sends open: My Campaign Profiles.
- `view_sms_campaigns.php`, `edit_sms_campaign.php` and the four new screens mark row 9 in the active-row map; `mail_queue.php` is a door since MA2b (#9275); its row 25 case in the map stays, unused.
- Doors (`validate_user()`, then `go()`, no right of their own): `view_email_campaign_history.php` to `?state=finished`, `view_sms_campaigns.php` to `?channel=sms` (MA3). `mail_queue.php` to the Sends tab with its channel (MA2b). Still to come: profiles (MA9f).

## What do the new tabs show before their step?
- Rows (`pg_messages_place_rows()`) leading to where the job is done today. Templates lists its SMS texts since MA6 (`messages-area-templates-tab.md`) and leads to the WhatsApp card (role <= 2). Automatic sends: My Campaign Profiles, Automation flows when `pg_flows_menu_row()` admits. Channels has its cards since MA7 (below).
- Sends has its list since MA2b (`platform/messages-area-sends.md`); MA8 replaces Automatic sends' body; MA8 also moves Automatic sends to the flows' gate.

## What does the Channels tab show? (MA7, #9200)
- Four cards, two to a row: E-mail, SMS, WhatsApp, İYS; each a link to its settings (SMTP Settings, the Communication cards) with a state pill and one line.
- E-mail always sends, so its pill is the sending domain's health: the worst of SPF, DKIM and DMARC (`pg_messages_health_worst()`), looked up in DNS on each visit as SMTP Settings does. Line: sender address, transport or SMTP host, sending domain.
- SMS, WhatsApp, İYS: Connected (complete and on), Incomplete (switched on, a field missing), Off. Lines: provider and sender; number and verified name; brand code and the test environment.
- `pg_messages_channel_cards($facts, $urls)` is pure and tested; `pg_messages_channel_facts()` reads the accounts.

## Where is the Campaigns tab described?
- `messages-area-campaigns-tab.md` (MA3, #9215).

Test: `tests/messages_area_test.php`.
