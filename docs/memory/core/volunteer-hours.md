---
name: volunteer-hours
description: Volunteer check-in and hours (VS6): a place's QR poster, volunteer_check_in.php, the Hours tab's list of the day, "Did not come" after 30 minutes, hours approval, hours outside a shift
module: volunteer_hours, volunteer_hours_view, widgets_volunteer_hours
---

## Where is it?
- Rules and store: `includes/fn/volunteer_hours.php`; panel markup and the poster page: `includes/fn/volunteer_hours_view.php`. Pure parts tested in `tests/volunteer_hours_test.php`. Built in step VS6 of plan `volunteer-shifts`.
- Panel: the "Hours" tab of `edit_volunteer_programme.php` (`view=hours`), for everyone who sees the programme (roles 0-2, its coordinators). `view=poster&place=` prints one place's poster.
- Phone: root `volunteer_check_in.php`, in `waf_sensitive_scripts()`, no-store, noindex, no referrer, no frame, the volunteer page's throttle (`pg_volunteer_page_throttled()`).

## How does the poster work?
- A poster per place of the programme's shifts (places come from `volunteer_shifts.place`). Its QR is `volunteer_check_in.php?pr=<programme>&pl=<place>&c=<code>`; the code is HMAC-SHA256 of `volunteer-poster:<id>:<place>` under `ENCRYPTION_KEY`, 20 hex letters, nothing stored (`pg_volunteer_poster_code()`). A renamed place needs a new poster.
- Without `c` the page shows the scan button and the camera box (`assets/js/volunteer_check_in.js`, BarcodeDetector as `event_check_in.js`); it opens only this site's check-in address.

## Who is checked in?
- Only the volunteers this browser holds: `$_SESSION['volunteer']['ids']` (a signed link opened, `core/volunteer-signup.md`) and the signed-in member's profiles (`pg_volunteer_account_ids()`). Neither: a card asks to open the coordinator's link once. Active volunteers only.
- `pg_volunteer_scan_pick()` takes their signed places at that place: an open check-out first, then a check-in, then the next shift too early. One button: "Check in" from 60 minutes before the start until the end, "Check out" after a check-in until 4 hours after the end; then the time.
- Writes are conditional (`pg_volunteer_check_write()`): a second press or phone changes nothing; a check-in sets `outcome` `came`, also after "Did not come". `checked_by` is the coordinator's id, 0 for a scan.

## When is it "Did not come"?
- A signed place with no check-in 30 minutes after the start (`PG_VOLUNTEER_NO_SHOW_MINUTES`). Written by `pg_volunteer_no_show_pass()` when the Hours tab opens (no job pass), and by hand with "Did not come" on the list of the day. It feeds the show-up rate (`core/volunteer-shift-rules.md`).

## What does the Hours tab show?
- "Today": a card a shift, a row a signed volunteer with a pill (Not here yet, Checked in 09:02, Checked out, Did not come) and quiet Check in, Check out, Did not come for a volunteer with no phone.
- "Waiting for approval": a shift's row once checked in and either checked out or ended; a row outside a shift at once (`pg_volunteer_hours_waiting()`). Approve quiet per row; "Approve selected" is the toolbar's fill. Approval stamps `approved_at`/`approved_by` and keeps the minutes counted (check-in to out, else the shift's length) in `minutes`. No refuse button yet.
- "QR posters": a button a place.

## How are hours outside a shift entered?
- On the volunteer's page (signed link and My Account), active volunteers: "Add hours outside a shift" with a day in the last 90 days, hours 0.25-24 (comma or point), a note (`pg_volunteer_outside_input()`), drawn by `includes/fn/widgets_volunteer_hours.php` (a site file, so its native date field is allowed). Posts `action=hours` to `volunteer.php` (`pg_volunteer_page_act()`); a row with `shift_id` NULL waits for approval.

Related: docs/memory/core/volunteer-signup.md, docs/memory/core/volunteer-board.md, docs/memory/core/volunteer-shift-rules.md, docs/memory/plans/volunteer-shifts-screens.md
