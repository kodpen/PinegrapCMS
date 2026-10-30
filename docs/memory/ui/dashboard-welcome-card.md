---
name: dashboard-welcome-card
description: The dashboard top card (welcome.php, .pg-clockbar): greeting on the left, time and date on the right in one full-width card; phone stacking, themes, ids the scripts read
---

## What is the top of the dashboard?
- One card, `<section class="card pg-clockbar">` in `welcome.php`, above the widget grid: the brief (`#pg_brief`: hour mark, greeting, day note, the workspace strip such as "Açık görevler", the "Say something else" button) on the left, `.pg-clockbar-now` (time `#welcome_time_clock`, date `#welcome_time_date`, next meeting `.pg-clockbar-next`) on the right.
- Same outer width as the grid (both sit in `#content`), the panel `.card` radius, the grid's 2rem gap under it, a stroke of `--clock-gradient` down the left edge (`.pg-clockbar::before`).
- CSS: the "Dashboard welcome card" block in `assets/css/backend.src.css`; the brief has no surface of its own any more.

## Why one card?
- Owner, 2026-10-27, on welcome.php at 1440 px: the greeting card on the left and a big clock pushed to the right left an empty gap that split the row, and the clock looked odd on the right. Before, `.pg-brief` was its own small card and the clock was bare text at 2.25rem.

## How does it lay out?
- From 768 px: a row; the brief grows, the time column is right-aligned, vertically centred and set off by a left hairline (at most 40% wide). Time 1.75rem, date under it in small capitals.
- Under 768 px: stacked, brief first, then a top hairline and time and date on one baseline; time 1.5rem under 576 px.
- Glass widget theme: the card turns translucent like the widgets; the other themes keep `--pg-card-bg`.

## What must not change?
- The ids and classes the page script reads: `#pg_brief`, `.pg-brief-mark .bi`, `.pg-brief-greet`, `.pg-brief-text`, `.pg-brief-line`, `.pg-brief-body`, `.pg-brief-ws`, `#pg_brief_again`, `#welcome_time_clock`, `.pg-clockbar-now`, `.pg-clockbar-next` (clock draw, brief refresh, next-meeting countdown).
- There is no setting that hides the clock.

## Where do the brief's settings links go?
- Every `settings_<category>.php#pgset-…` link in `brief.php` must name a card id the registry lists for that category (`pg_settings_categories()`); `tests/dashboard_brief_test.php` reads the ids from `registry.php` and fails on a stale anchor. The device-limit hint opens `#pgset-device`; it opened `#pgset-session` before (#3558).

Related: docs/memory/ui/dashboard-widget-files.md, docs/memory/ui/panel-css-tokens.md
