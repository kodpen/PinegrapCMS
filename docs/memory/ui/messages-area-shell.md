---
name: messages-area-shell
description: The Communication area (MA1 #9182, MA7 #9200, MA10b #9453): menu row 9, Incoming e-mail and the five tabs with their gates, its menu pin and dashboard card, the tab strip, doors, the Channels cards
module: messages_area
---

## What is the shell?
- Main menu row 9 under Marketing is "Communication" (İletişim), slot kept (`main-menu-communication-icon.md`); shown to whoever may open one of its tabs (role <= 2, the Campaign Manager right, the Flows screen's right, or a reader of a mailbox), leading to the first of them. Its panel lists the tabs the person may open, then "New campaign" (`add_email_campaign.php`).
- Six tabs, each its own screen and gate (`pg_messages_area_tabs()` in `includes/fn/messages_area.php`): Incoming e-mail `messages_mail.php` (gate `mail`, `ws_mail_list_visible()`; `messages-area-mail-tab.md`), Campaigns `view_email_campaigns.php`, Automatic sends `messages_automatic.php` (`email_or_flows`, `messages-area-automatic-tab.md`), Templates `messages_templates.php`, Sends `messages_sends.php` (all `validate_email_access()`), Channels `messages_channels.php` (role <= 2).
- Every tab has its content: Campaigns `messages-area-campaigns-tab.md`, Templates `messages-area-templates-tab.md`, Sends `platform/messages-area-sends.md`, Channels below. `pg_messages_place_rows()`, the rows a tab showed before its step, is left to its test.
- Incoming e-mail also outside the area, behind the same gate (owner 2026-11-19, `workflow/decisions-made-product-78.md`): a person pins it to their own menu as row 27 (`main-menu-mail-row.md`), and the new dashboard offers its card (`dashboard-v2-mail-card.md`). Row 9 and the tab stay.
- Architect's ruling behind it (#9081): `messages-area-rules.md`; the start file `messages-area-overview.md`.

## How is the tab strip drawn?
- `pg_messages_tab_strip_for($key, $user)` prints `nav.pg-area-tabs.pg-messages-tabs` > `.pg-seg` of links, the open one `is-active` with `aria-current="page"` (MA12 #9363; its look: `messages-area-look.md`); a tab the viewer may not open is left out, and with fewer than two tabs no strip is drawn. Links, not `role=tab`: each tab is an address.
- A new tab screen uses `pg_messages_tab_page()` (heading "Communication", the strip, the body, the footer). An old screen inside the shell prints it first in `#content`, notices and toolbar under it.
- Centred in the window on every tab (owner 2026-11-19, poll 153): `area-tab-strip-centred.md`.

## Which old screens sit inside the shell?
- The Campaigns tab, `view_email_campaigns.php`. The campaign profiles still running are reached from Automatic sends' note.
- `view_sms_campaigns.php`, `edit_sms_campaign.php` and the four new screens mark row 9 in the active-row map; its row 25 case for `mail_queue.php` stays, unused.
- Doors (`validate_user()`, then `go()`, no right of their own): `view_email_campaign_history.php` to `?state=finished`, `view_sms_campaigns.php` to `?channel=sms` (MA3), `mail_queue.php` to the Sends tab with its channel (MA2b #9275). Profiles since MA9f: a converted profile's edit leads to its flow, and with no profile left every profile screen leads to Automatic sends ([[core/campaign-profile-screens-left]]).

## What does the Channels tab show? (MA7, #9200)
- Four cards, two to a row and all as high as the highest (MA12): E-mail, SMS, WhatsApp, İYS; each card a link to its settings (E-mail sending, the Communication cards) with a state pill and one line.
- E-mail always sends, so its pill is the sending domain's health: the worst of SPF, DKIM and DMARC (`pg_messages_health_worst()`), looked up in DNS on each visit as E-mail sending does. Line: sender address, transport or SMTP host, sending domain.
- SMS, WhatsApp, İYS: Connected (complete and on), Incomplete (switched on, a field missing), Off. Lines: provider and sender; number and verified name; brand code and the test environment.
- `pg_messages_channel_cards($facts, $urls)` is pure and tested; `pg_messages_channel_facts()` reads the accounts.

Test: `tests/messages_area_test.php`.
