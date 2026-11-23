---
name: booking-settings-messages
description: Settings › Bookings, the Messages row: value rows Mails and Text messages saying which booking events send, Change to campaign profiles or SMS Messages, the Reminders warning; writes nothing
module: booking_settings
---

## What does the Messages row show?
- Card `pgset-booking-messages` in its folded row between Booking rules and Booking widget texts (#11133); in the registry with keywords (reminder, mail, SMS, campaign profile). It posts nothing and `bookings.save.php` reads nothing of it: each setting keeps one place.
- One inner card of value rows (DX2b "Value rows"): "Mails" (Change opens `view_email_campaign_profiles.php`) and "Text messages" (Change opens `#settings/contact/pgset-sms-messages`).
- Each line names the events that send: "Goes out when confirmed and as a reminder"; a reminder nothing sends adds "Reminder: Not sent"; none: "Nothing is sent". The folded row's line: "2 of 4 mails · 1 of 4 texts".
- The meta line warns "No reminder mail or text is switched on: Reminders send nothing." while Reminders holds hours and neither sends a reminder (K4 of the plan); else it says where each is set.

## How is Reminders drawn?
- In Booking rules, a text box with its unit "hours before" (`form-adorn`), help naming the bounds (three at most, 1-168) and Messages; it posts `booking_reminder_hours` as before (#11133).

## What is left out?
- A reminder sent without a campaign profile or an SMS event: separate work, not planned; until then Reminders with neither send nothing.

## Where do the values come from?
- One pure `pg_booking_settings_messages($mail_actions, $sms_events, $reminder_hours)` (`includes/fn/booking_settings.php`, `test_booking_settings_messages`).
- Mails: the actions of switched-on campaign profiles with `action_item_id` 0 among `pg_booking_mail_actions()` (no profile, no mail: `commerce/booking-messages.md`). Texts: the switched-on events of `pg_sms_message_settings()`.

## Why the key "Goes out {var:1}"?
- The existing key "Sent {var:1}" reads as a count in Turkish ("Gönderildi: 3"), so the row's sentence has its own key.

Related: docs/memory/ui/booking-settings-rows.md, docs/memory/ui/booking-settings.md, docs/memory/commerce/booking-messages.md
