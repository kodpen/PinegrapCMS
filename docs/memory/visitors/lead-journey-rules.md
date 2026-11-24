---
name: lead-journey-rules
description: The visitor journey's pure rules (lead_journey.php, LJ1a): browser key verdict, tie and move on a shared browser, tying back in the keep period, interest score half-life
module: lead_journey
---

## What does the module hold?
- Only pure verdicts: nothing reads the database, writes a cookie or prints text. Callers (LJ1b on) pass the facts in and act on the returned code. Plan: `plans/lead-journey.md`.

## When may the browser key exist?
- `pg_lead_key_verdict($consent, $has_key)`: `write` or `keep` only with the marketing yes (`yes`); any other answer (`no`, `unanswered`, `off`) gives `delete` when a key is there, `none` when not. No key before the yes; a refusal after a yes deletes it.

## When is a browser tied to a contact?
- `pg_lead_tie_decision()` returns a `verdict` code. Only the person's own act ties: `form`, `newsletter` (after the double opt-in when it is on: the caller passes it then), `sign_in`, `order`, `booking`, `campaign_click` (`pg_lead_tie_acts()`).
- Refused in this order: `panel_user` (a panel role is never tied or scored), `no_consent`, `no_key`, `not_an_act`, `no_contact`. Then `tie` (first contact), `same` (already that contact) or `move`.
- `move` (a shared computer): the key points at the new contact, `moved_from` names the earlier one; the earlier contact's visits stay with it.

## Which visits are tied back?
- `pg_lead_tie_back_ids()`: visits of the browser with no contact yet, started at or after the consent and inside the keep period (13 months by default, `pg_lead_keep_start()`; a short month's day falls to its last day). A visit already with a contact is never moved.

## How does the interest score work?
- `pg_lead_score_weights()`: points by kind (`pricing_page` 10, `contact_page` 10, `return_7d` 10, `form` 25, `booking` 30, `campaign_click` 10, `download` 15); a site's weights (LJ3, Site Settings) override known kinds only, never below 0.
- Each event fades by a half-life, 30 days by default (`pg_lead_score_decay()`): after one half-life half its points are left. A future time counts as now.
- `pg_lead_interest_score()`: the sum, rounded and capped 0 to 100, with the top three reasons as kind codes and their points (ties by kind name), and `points`: every kind's faded points. Events, fit, interests and the pass: `visitors/lead-score.md`. A return counts as `return_7d` when `pg_lead_is_quick_return()` sees a visit within 7 days of the one before.

Related: docs/memory/visitors/lead-journey-store.md
