---
name: launch-journey-steps
description: Plan launch-journey, steps LJ1a to LJ7: the pure catalogue, the store, the set-up card and purpose, the go-live check, the first week and team paths, the agency summary, API, close-out
---

## Which steps are there?
- LJ1a (S) first, no schema: new `includes/fn/launch_journey.php`, pure: the step catalogue per purpose, each step's rule over a facts array, blocker or warning, the payment-live rule per driver (Stripe by key prefix), the first-week window. `tests/launch_journey_test.php` red first: a shop with PayTR in test mode, a blog, `Disallow: /` in the robots extras, a hidden blocker, a week ending. Features: L1, L2, L4.

- LJ1b (S) after LJ1a, after the owner's yes to the schema poll: the purpose, go-live time and hidden steps as a migration step in the open version, in the PR body, asked of the architect before ready. Features: L1, L4, L5 (the store).

- LJ2 (M) after LJ1b: the facts reader (each source's function, no title matching), the dashboard card through `pg_dashboard_v2_cards()` (`cards/launch.php` answering data, its renderer in `pg_dash_cards_<group>.js`), the purpose choice, "Do it" links, "Not for this site", "Hide set-up", Site Settings › General's purpose field. Features: L1, L2, L3.

- LJ3 (M) after LJ2: the go-live check and its "Go live" as the architect answers, the confirmation naming each blocker, the Site Log line, the go-live time. Features: L4.

- LJ4 (M) after LJ3: the First week card; a team member's path card; first-visit hint lines and their marks as the architect answers. Features: L5, L6.

- LJ5 (M) after LJ2, after the owner's yes to the summary table: the agency summary drawer, the signed page (a new root file, in `waf_sensitive_scripts()`), the customer's text answers, the bell line, withdraw. Features: L7.

- LJ6 (S) after LJ3: the read route with its types, `php tools/check_api_schema.php`; the MCP tool; the guide's `start` topic (`includes/fn/agent_guide.php`). Features: L8.

- LJ7 (S) close-out: every L number checked against the code with its PR; `ui/launch-journey.md` written; this plan's four files deleted; the changelog `[NEW]` line named in its PR.

## Which steps run side by side?
- LJ1a can start before the architect's answer. After LJ2: LJ3 and LJ5; after LJ3: LJ4 and LJ6. LJ2 enters `includes/dashboard/v2/cards.php`, LJ5 `waf.php`: check the open PRs before claiming.

Related: docs/memory/plans/launch-journey.md, docs/memory/plans/launch-journey-features.md, docs/memory/plans/launch-journey-screens.md
