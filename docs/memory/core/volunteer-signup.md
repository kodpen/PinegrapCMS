---
name: volunteer-signup
description: A volunteer's own shifts (VS4): volunteer.php's signed link, My Account's volunteering section, Sign up, waitlist, Cancel to the cut-off, Offer my place and its swap approval, Send sign-up link
module: volunteer_signup, volunteer_page_view, widgets_volunteering
---

## Where is it?
- `volunteer.php` (root, in `waf_sensitive_scripts()`): `?v=<volunteer id>&t=<token>` opens that volunteer's page in look A (`output_header_secure()`); no-store, noindex, no referrer, no frame; 120 opens in five minutes an address (`pg_volunteer_page_throttled()`). A wrong, withdrawn or left link is one 404 card.
- My Account: section `volunteering` and flag `has_volunteering` in `_pg_my_account_extras()` for a signed-in member whose contact has a profile not left (`pg_volunteer_account_ids()`); drawn in the site's look by `includes/fn/widgets_volunteering.php`. Its list `account.volunteering` and designer twins: `core/volunteer-account-list.md`.
- Rules and store: `includes/fn/volunteer_signup.php`; markup: `includes/fn/volunteer_page_view.php`. Pure parts tested in `tests/volunteer_signup_test.php`. Built in step VS4 of plan `volunteer-shifts`.

## How is the link made?
- The profile drawer's "Send sign-up link" (active or paused volunteers) issues a new token (`pg_volunteer_link_send()`), stores only its hash in `link_hash`, and e-mails the link; a link sent before stops working.
- A link opened adds the volunteer to `$_SESSION['volunteer']['ids']`, which `get_file.php` reads for the volunteer's own document files.

## What does the page show?
- Per programme: "My next shifts" (signed and waiting rows of shifts not ended), "Shifts for you" (an active volunteer of an open programme: shifts of the next 60 days, at most 100, `PG_VOLUNTEER_AHEAD_DAYS`), "My documents" (kind and date only), "Shift calls" (`core/volunteer-messages.md`).
- A role is offered only when the volunteer fits it (`pg_volunteer_fits()`): "Sign up" with a free place, "Take this place" for an offered place nobody asked for, else "Join the waitlist" (`pg_volunteer_shift_choices()`).
- A signed place: "Cancel" until the programme's cut-off, past it "Offer my place", an offer "Keep my place" while nobody asked; a waiting row "Leave the waitlist" until the start (`pg_volunteer_signup_actions()`).

## How is a place written?
- Every action runs under `GET_LOCK('pg_volunteer_shift_<id>')` and checks the choice again (`pg_volunteer_self_act()`), so a role never goes over its count. A cancelled row of the same volunteer is used again (the unique shift-volunteer key).
- Cancelling a signed place runs the waitlist pass for its role (`pg_volunteer_waitlist_write()`): the first waiting who still fit become signed and get an e-mail.
- My Account's buttons post to `volunteer.php` with `p` (one of the member's own profiles) and `send_to`; the answer is a `my_account` liveform notice.

## How does a swap work?
- "Take this place" writes a waiting row with `swap_from` = the offered row. When the programme's `swap_approval` is off it finishes at once; else the shift's drawer shows "Asks to take a place" with Approve and Refuse (`pg_volunteer_swap_decide()`).
- Finishing cancels the offered row, signs the taker and e-mails both. A waiting row with `swap_from` is no waitlist place; cancelling an offered place cancels the swap asked of it.

Related: docs/memory/core/volunteer-board.md, docs/memory/core/volunteer-shift-rules.md, docs/memory/plans/volunteer-shifts-screens-own.md
