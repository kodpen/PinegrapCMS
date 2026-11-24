---
name: volunteer-account-list
description: My Account's account.volunteering list (the member's coming volunteer shifts), its tokens, and the designer twins of the volunteering section and list
module: widgets_volunteering
---

## What does the list hold?
- `account.volunteering` (key `account_id`, kind `my_account`): one row per coming shift the signed-in member signed up for or waits for, in every programme, soonest first. Filed by `pg_sw_list_account_volunteering_rows()` from `$extras['volunteering']` (`pg_volunteer_account_views()`), the same views the "volunteering" section draws. Built in step VS4b (#11251).
- Tokens: `__volunteering_programme`, `__volunteering_when` (date, time and place), `__volunteering_date`, `__volunteering_time`, `__volunteering_place`, `__volunteering_role`, `__volunteering_state`, `__volunteering_state_label`, `__volunteering_tone`.

## Which states and colours are there?
- `pg_volunteer_signup_state()` (pure): signed, offered (place offered), asked (offered and someone asked to take it), waiting (waitlist), swap (waiting for the coordinator to approve taking an offered place).
- Tone: success (signed up), warning (waitlist, place offered), info (asked, waiting for approval); the label is `pg_volunteer_signup_label()`'s.

## Why has the list no buttons?
- Cancel, Offer my place and Keep my place post forms with the form token to `volunteer.php`; a list row carries plain words only, so the buttons stay in the section.

## What does the designer draw?
- Section picker: "My volunteering". Canvas sample `_sdAccountVolunteeringHtml()`, the twin of `pg_volunteer_page_html()` with `pg_volunteer_page_site_classes()` (one programme: next shifts, one shift for you, one document).
- A new list starts as `_sdAccountVolunteeringList()`: the badge's colour is a condition on `__volunteering_tone`.

Test: `tests/volunteer_account_list_test.php` (compares the twin's next-shift markup with the PHP section through Node).

Related: docs/memory/core/volunteer-signup.md, docs/memory/commerce/event-tickets-page.md
