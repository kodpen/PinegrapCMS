---
name: lead-journey-steps
description: Plan lead-journey, steps LJ1a to LJ7: the pure rules, the store and the tie, the journey, the scores and interests, alerts and events, lead sources, API, close-out
---

## Which steps are there?
- LJ1a (S) done (#10842), no schema: new `includes/fn/lead_journey.php` with the pure parts: the tie decision, the move on a shared browser, the score model and its decay.
  - `tests/lead_journey_test.php` red first: no key without the yes, a refusal after a yes, two contacts on one browser, a panel user, a tie back inside and outside the keep period, a score after its half-life. Features: J2 to J4, J6 (the rules).
- LJ1b (M) done (#10882), after LJ1a, only after the owner's yes to poll 194 (message 10747): the four tables as a migration step in the open version, the columns of the schema decisions list; the consent-gated browser key and its catalogue row; the ties at each act's own save path. Features: J1 to J4, J14.
- LJ2 (M) done (#10946), after LJ1b, started first when LJ1b lands, before LJ3 (erase is `none` in LJ1b only because `includes/fn/privacy.php` and `privacy_inventory.php` were held by #10877 and #10878): the journey read (pure merge of the sources by time), the contact screen and the Workspace customer card, the export and erase hooks, the keep period trim. Features: J5, J13.
- LJ3 (M) done (#11216), after LJ1b: the fit score, the job pass, the settings read (config_text `lead_score`, keep period and half-life used), interests from the page's folder, the Interest card on the contact screen. Features: J6 to J9.
- LJ3b (S) done (#11245), after LJ3: the settings pane "Interest score" (no Site Settings › Contacts category exists: placed beside the cookie notice under Privacy unless the architect names another), the contacts list's "Score" column, the product's category as a topic (the product named on the visit's page row). Features: J6, J9.
- LJ4 (S) done (#11275), after LJ3b: `customer.hot` and `customer.returned`, the flow start boxes (outputs score, fit, strongest interest for a condition box), the `contacts_hot` ops alert (`visitors/lead-tell.md`). Features: J10, J11.
- LJ4b (S) after LJ4: the Hot contacts view on the contacts list (`lead-journey-screens.md`), its empty line, Open and "Remind me" (the follow-up), the alert's bell link to that view; the contact group conditions by interest score range and by topic (`includes/fn/audiences.php`, J9). Contacts have no assignee column: the architect names what "assignee" reads before the column is drawn. Features: J9, J11.
- LJ5 (M) done (#11116), after LJ2: first and last touch, the Lead sources report and its Excel. Feature: J12.
- LJ6 (S) after LJ4b and LJ5: the API routes and types (`php tools/check_api_schema.php`), the MCP read tool. Feature: J15.
- LJ7 (S) close-out: every J number checked against the code with its PR; `visitors/lead-journey.md` written, `visitors/visitor-channel-rule.md` and `core/contact-holders.md` pointing to it; this plan's three files deleted; the changelog `[NEW]` line named in its PR.

## Which steps run side by side?
- LJ1a can start before the poll's answer. After LJ1b: LJ2 and LJ3. `includes/fn/ops_alerts.php` is also entered by `commerce/sales-watch.md` SW1: check the open PRs before claiming. The inventory catalogue (`core/privacy-inventory.md`) names the new store in the same PR.
