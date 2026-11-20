---
name: lead-journey-steps
description: Plan lead-journey, steps LJ1 to LJ7: the store and the tie, the journey, the scores and interests, alerts and events, lead sources, API, close-out
---

## Which steps are there?
- LJ1 (M) first: the store the architect chose, a migration step in the open version; the consent-gated browser key and its catalogue row; the ties of J2 to J4 at each act's own save path; new `includes/fn/lead_journey.php` with the pure parts (the tie decision, the move on a shared browser).
  - `tests/lead_journey_test.php` red first: no key without the yes, a refusal after a yes, two contacts on one browser, a panel user, a tie back inside and outside the keep period.
  - Features: J1 to J4, J14.
- LJ2 (M) after LJ1: the journey read (pure merge of the sources by time), the contact screen and the Workspace customer card, the export and erase hooks, the keep period trim. Features: J5, J13.
- LJ3 (M) after LJ1: the score model and its decay (pure), the fit score, the job pass, the settings, interests from the chosen topic source. Features: J6 to J9.
- LJ4 (S) after LJ3: `contact.hot` and `contact.returned`, flow boxes and filters, the Hot contacts list, the ops alert. Features: J10, J11.
- LJ5 (M) after LJ2: first and last touch, the Lead sources report and its Excel. Feature: J12.
- LJ6 (S) after LJ4 and LJ5: the API routes and types (`php tools/check_api_schema.php`), the MCP read tool. Feature: J15.
- LJ7 (S) close-out: every J number checked against the code with its PR; `visitors/lead-journey.md` written, `visitors/visitor-channel-rule.md` and `core/contact-holders.md` pointing to it; this plan's three files deleted; the changelog `[NEW]` line named in its PR.

## Which steps run side by side?
- After LJ1: LJ2 and LJ3. `includes/fn/ops_alerts.php` is also entered by `plans/sales-watch.md` SW1: check the open PRs before claiming. The inventory catalogue of `plans/privacy-centre.md` names the new store in whichever plan lands second.
