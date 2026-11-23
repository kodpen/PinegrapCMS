---
name: launch-journey-steps
description: Plan launch-journey, steps SJ1a to SJ7: the pure catalogue, the store, the set-up card and purpose, the go-live check, the first week and team paths, the agency summary, API, close-out
---

## Which steps are there?
- SJ1a (S) done, PR #11160. First, no schema: new `includes/fn/launch_journey.php`, pure: the step catalogue per purpose, each step's rule over a facts array, blocker or warning, the payment-live rule per driver (Stripe by key prefix), the first-week window. `tests/launch_journey_test.php` red first: a shop with PayTR in test mode, a blog, `Disallow: /` in the robots extras, a hidden blocker, a week ending. Features: L1, L2, L4.

- SJ1b (S) done, PR #11200. After SJ1a: the agency summary's lines table (poll 206, `workflow/decisions-made-schema-46.md`) as a migration step in the open version, its columns brought to the architect before ready. Features: L7 (the store).

- SJ2 (M) after SJ1a: the journey's `config_text` row (purpose, go-live time, hidden steps; `pg_config_text_save()`, no schema), the facts reader (each source's function, no title matching), a template key's purpose, the dashboard card through `pg_dashboard_v2_cards()` (`cards/launch.php` answering data, its renderer in `pg_dash_cards_<group>.js`), the purpose choice, "Do it" links, "Not for this site", "Hide set-up", Site Settings › General's purpose field. Features: L1, L2, L3.

- SJ3 (M) after SJ2: the go-live check and its "Go live" turning maintenance mode off and removing the robots catch-all itself (poll 209; never a payment mode), the confirmation naming each blocker and each of the two changes, a Site Log line for each, the go-live time. Features: L4.

- SJ4 (M) after SJ3: the First week card; a team member's path card; first-visit hint lines, marked as `hint:<screen>` keys in `user.tours_seen` (`pg_tour_mark()`), only for screens in the catalogue. Features: L5, L6.

- SJ5 (M) after SJ2 and SJ1b: the agency summary drawer, the signed page (a new root file, in `waf_sensitive_scripts()`), the customer's text answers, the bell line, withdraw. Features: L7.

- SJ6 (S) after SJ3: the read route with its types, `php tools/check_api_schema.php`; the MCP tool; the guide's `start` topic (`includes/fn/agent_guide.php`). Features: L8.

- SJ7 (S) close-out: every L number checked against the code with its PR; `ui/launch-journey.md` written; this plan's files deleted; the changelog `[NEW]` line named in its PR.

## Which steps run side by side?
- SJ1a first. After SJ1a: SJ1b and SJ2; after SJ2: SJ3, and SJ5 once SJ1b landed; after SJ3: SJ4 and SJ6. SJ2 enters `includes/dashboard/v2/cards.php`, SJ5 `waf.php`: check the open PRs before claiming.

Related: docs/memory/plans/launch-journey.md, docs/memory/plans/launch-journey-features.md, docs/memory/plans/launch-journey-screens.md
