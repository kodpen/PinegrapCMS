---
name: visitor-push-panel
description: Visitor notifications in the panel: the Notifications card on Channels, the screen Send a notification, Pick a page or record, the test to this device, the sent list
module: site_push_panel
---

## Where is it?
- Communication › Channels shows a fifth card, "Notifications" (`pg_site_push_channel_card()`, added by `pg_messages_channel_cards()` when `pg_messages_channel_facts()` gives `site_push`). On while the switch is on; the line names the subscribers and the last news sent, and "Off in Features" while off.
- The card leads to `messages_notifications.php`, drawn with the Channels tab open (`pg_messages_tab_page('channels', …)`); menu row 9 (`output_menu()`). Role <= 2 (`validate_area_access($user, 'manager')`), as the Channels tab.

## What does the screen do?
- The form: "Pick a page or record", Title (60), Text (200), Link (a path of the site; empty is the home page). "Send to N subscriber(s)" writes kind 0 through `pg_site_push_message_send()`; the sender's pass sends it (`platform/visitor-push-sender.md`).
- With the switch off or before the upgrade a note says so and the form is disabled; the send would answer `off` anyway.
- The form sends once (#10249): the first send that goes through (the confirmed news or a test) sets `sending`, disables both `button[name="send"]` and sets `aria-busy`; any later submit is cancelled. The action travels in a hidden `send` input (a disabled button drops its value). A page restored from the back/forward cache is reset. Before, a second press while the post wrote the queue sent the news to every subscriber again without asking.
- The server has no one-use guard: `messages_notifications.php` takes every valid post as a new message.
- Errors are sentences from `pg_site_push_send_error()`; the form keeps what was typed (liveform).

## Where does Pick a page or record read?
- `?pick=<term>` (JSON, read only) searches `search_items` by title or address, live rows only (`pg_search_items_live_sql()`), at most 10. `pg_site_push_pick_items()` drops files and other hosts and puts `OUTPUT_PATH` before the index's relative address. A site whose search index never ran finds nothing.

## How does Send a test to this device work?
- `assets/js/pg_site_push_panel.js` asks the browser for the site worker's registration at `OUTPUT_PATH` and its push subscription; with one, the endpoint goes with the form and the button turns on. The server finds the subscription by `endpoint_hash` (`pg_site_push_subscription_by_endpoint()`) and writes kind 1 to it alone.
- The panel's own worker (`sw.js`) has the software directory as its scope, so it never answers for the site.

## What does the list show?
- News and tests (`pg_site_push_listed_kinds()`), newest first, on the list table driver: message, type pill, sent, subscribers (queued), delivered, failed, removed. Stock and order messages are not listed.
