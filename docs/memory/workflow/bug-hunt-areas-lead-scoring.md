---
name: bug-hunt-areas-lead-scoring
description: Bug hunt of lead scoring (LJ1 to LJ4c), named by the architect in the agent channel: what was read, the three faults fixed, what was found clean
module: lead_score
---

## What was the area, and what was read?
- Architect, agent channel: lead scoring, plan steps LJ1 to LJ4c. LJ6's API files were left alone (open PR #11315).
- Read: `includes/fn/lead_score.php`, `lead_tell.php`, `lead_journey.php` (score, decay, keep start), `lead_journey_view.php` (read, erase, export, trim), the Hot Contacts parts of `contact_list.php`, the interest conditions of `audiences.php`, `contacts.php` (merge holders), `includes/settings/privacy.save.php`, the `customer.*` lines of `includes/workspace/watch.php`, `privacy_export.php`, and the tests `lead_score`, `lead_tell`, `lead_journey*`, `contact_list_hot`, `contact_list_followed_by`.

## What was fixed?
- A contact merge deleted every score row and the pass never came back to the survivor (old moved visits are no candidate): the person left Hot Contacts and the interest group conditions until a new visit. Now one row is kept and marked for work (`visitors/lead-score-pass.md`).
- A row at interest 0 was never worked out again: after the keep period trimmed its visits it kept its interests and last visit for good. Now a row past the keep start is worked out daily and goes.
- A changed threshold, points, pages or half-life reached the rows only at their next scoring, up to a day later: Hot Contacts listed contacts under a raised threshold whose Score column showed no Hot pill. Now the save marks every row for the next pass.

## What was found clean?
- Decay and the score: half-life fading, a future time as now, rounding and the 0 to 100 cap, reasons by points with ties by kind.
- Told once: `customer.hot` only on a rise over the threshold (again after a fall, as decided), `customer.returned` once a day for a visit newer than the row's last, never with `customer.hot` for the same visit; the pass excludes contacts already scored in it, and its record is claimed against a second run.
- The daily `contacts_hot` alert: only after a finished pass, once a day, counting `hot_at` in the last day.
- Hot Contacts: the inner join on one row a contact, no date range, the fixed order, Export and Delete All through the same FROM; Followed by reads open tasks only, in one query.
- Group conditions: "at most" counts a never-scored contact as 0; the topic `LIKE` pattern cannot match another id or kind (TEXT column, `json_encode()` order).
- Erase deletes the four tables by contact; export carries visits, journey rows and the score row.

## Which issues were opened?
- None.
