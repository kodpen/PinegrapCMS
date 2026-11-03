---
name: api-campaign-profiles
description: /campaigns/profiles CRUD (PR #6353): write path shared with the profile screens, owner-based access checks, partial updates, schedule time, refusals, user-role rows
module: email_campaign_profiles
---

## Where is a profile written?
- `includes/fn/email_campaign_profiles.php`: `pg_email_campaign_profile_save($id, $values, $user_id)` (0 creates; columns of `pg_email_campaign_profile_columns()`), `_delete()`, `_name_taken()`. `add_email_campaign_profile.php` and `edit_email_campaign_profile.php` call them and keep no SQL for the row; the import still writes its own rows.
- Handlers: `includes/api/resources/campaign_profiles.php`, ids `campaigns.profiles.*`: list (newest first, cursor; filters `action`, `enabled`, `q` on the name), get, create (201), update (POST, PATCH also accepted), DELETE and its `POST …/delete` twin. All writes take X-Dry-Run. No events.

## Which checks run before a write?
- The form's value rules through `pg_email_campaign_profile_values_problem()` (the import's check): 422.
- `subject` and `from_name` must not be blank (the form requires them; the import does not check them).
- Access from the API owner, not the session: `pg_email_campaign_profile_owner_access_problem($values, $user_id, $role)`. The item and page must exist (the screen's pickers guarantee it, a body does not). A user-role owner needs a calendar of the event in `users_calendars_xref`, edit access to the custom form's folder, its own followed profile, and a body page in a public/guest/registration folder or one it may edit. The panel's `pg_email_campaign_profile_access_problem()` still reads `USER_ID`/`USER_ROLE`, which the API never defines.
- The ring rule (`pg_email_campaign_profile_closes_ring()`): 422.
- A name another profile has: 409 `campaign_profile_name_taken`.
- Commerce actions need `ECOMMERCE` and an owner of role < 3 or with the e-commerce right; booking actions need `pg_booking_ready()`, else 503 `service_unavailable`.

## How does an update read?
- `api_campaign_profile_values()` lays the sent fields over the stored row; the action then settles `action_item_id`, `schedule_period`, `schedule_base` as the form does (`pg_email_campaign_profile_action_values()`): every action but `calendar_event_reserved` sends after the action.
- `schedule_time` is `HH:MM` on the 24-hour clock; `''`/null means any time (stored `00:00:00`, answered null); a chosen `00:00` is stored `00:01:00`. The form's `validate_time()` is skipped for it, since it reads the site's clock format.

## Who sees which rows?
- Role < 3 every profile; a user-role owner with the e-mail right only the profiles it created (404 for the rest), as `view_email_campaign_profiles.php`.

## Where are the stored values printed?
- Name, subject, from name and addresses are printed through `h()` on the profile list, the campaign list and history, and the edit campaign screen; the picker options escape the name in `get_email_campaign_profile_options()`. The API stores the same text the form does.
