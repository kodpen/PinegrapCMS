---
name: launch-journey-describe-line
description: The set-up card's line "Make your site from a description" (SB6): when it is shown, where "Start" leads, why it is not a step, how the brief gets the purpose
module: launch_journey
---

## What is it?
- A row of its own under the steps of the "Set up your site" card (`ui/launch-journey-setup-card.md`): icon `bi-stars` on a violet tile, title "Make your site from a description", the start's own row line "A whole site from your words" (the pane's lead was cut off at 390 px), and "Describe it" (quiet button, the start's own name). A line above sets it off from the steps.
- Data: `describe` of `includes/dashboard/v2/cards/launch.php`, from the pure `pg_launch_journey_describe_line($problem)` (`includes/fn/launch_journey.php`); drawn by `describeLine()` in `assets/js/pg_dash_cards_launch.js`; style `.pg-dash-launch-describe` in `pg_dash.src.css`. Test: `tests/launch_journey_test.php`.

## When is it shown?
- While the card is shown and Pinegrap AI can write: `pg_site_brief_problem()` is empty. Otherwise `describe` is null and nothing is drawn: the card offers no disabled row; the New design window shows the start disabled with its reason (`designer/describe-start.md`).
- Not a step: not counted in "N of M", no "Not for this site", it does not keep the card from closing.

## Where does "Describe it" lead, and how does the purpose arrive?
- `view_system_styles.php?start=describe`: the New design window opens on "Describe it" (`designer/new-design-window.md`).
- The URL carries no purpose: the pane reads the same stored `launch_journey` row (`purpose`, else `suggested`) the card shows, so the brief's kind is the purpose chosen on the card (`pg_designer_describe_pane()`).
