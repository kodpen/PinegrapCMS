---
name: messages-area-shell
description: The Communication area (MA1 #9182, MA7 #9200): menu row 9, the five tabs and their gates, the shared tab strip, the old lists inside it, the interim tabs, the Channels cards
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
- Campaigns tab open: My Campaigns (heading now "Communication", title "Campaigns"), My Campaign History, My SMS Campaigns. Automatic sends open: My Campaign Profiles.
- `view_sms_campaigns.php`, `edit_sms_campaign.php` and the four new screens mark row 9 in the active-row map; `mail_queue.php` still marks row 25 until MA2 makes it a door.
- No door yet: an old address becomes one in the step that moves its job (history and SMS list MA3, mail queue MA2, profiles MA9d).

## What do the new tabs show before their step?
- Rows (`pg_messages_place_rows()`) leading to where the job is done today. Sends: E-mail queue, SMS queue (`pg_sms_queue_shown()`), both role <= 2, and My Campaign History. Templates: SMS Messages and WhatsApp settings cards (role <= 2). Automatic sends: My Campaign Profiles, Automation flows when `pg_flows_menu_row()` admits. Channels has its cards since MA7 (below).
- MA2, MA6, MA8 replace each body; MA8 also moves Automatic sends to the flows' gate.

## What does the Channels tab show? (MA7, #9200)
- Four cards, two to a row: E-mail, SMS, WhatsApp, İYS; each a link to its settings (SMTP Settings, the Communication cards) with a state pill and one line.
- E-mail always sends, so its pill is the sending domain's health: the worst of SPF, DKIM and DMARC (`pg_messages_health_worst()`), looked up in DNS on each visit as SMTP Settings does. Line: sender address, transport or SMTP host, sending domain.
- SMS, WhatsApp, İYS: Connected (complete and on), Incomplete (switched on, a field missing), Off. Lines: provider and sender; number and verified name; brand code and the test environment.
- `pg_messages_channel_cards($facts, $urls)` is pure and tested; `pg_messages_channel_facts()` reads the accounts.

Test: `tests/messages_area_test.php`.
