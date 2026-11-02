---
name: visitors-ai-section
description: The AI visibility section (visitor_ai.php until the Visitors overview takes it): bots and firewall state, fetches by day, top pages per bot, AI assistant visits, unfetched sitemap pages
module: visitor_insights
---

## Where is it?
- `pg_visitor_ai_section($window, $user)` in `includes/fn/visitor_insights.php`; `visitor_ai.php` (Visitors flyout "AI Visibility", panel search, gate `validate_visitors_access()`) prints it with the period filter (`7d`/`30d`/`90d`) and a link to Page Speed. The overview's `ai` section calls the same function when `visitors.php` lands (WV5, #PR).
- Shown in the flyout once `pg_ai_visibility_ready()`; the figures are `visitors/ai-visibility-figures.md`.

## What does it show?
- Tiles: verified AI fetches and how many bots fetched (`pg_ai_fetch_summary()`, unverified claims kept apart), visits from AI assistants with page views, forms submitted, and with `ECOMMERCE` orders completed with the order total (minor units / 100 through `prepare_amount()`).
- Fetches by day: every bot together, each day of the window (0 for none), inline SVG.
- AI Bots: every known bot with its kind, fetches and the firewall state (allowed, blocked, "counted only" for unverified); under it whether robots.txt carries the AI section and llms.txt is served, with a link to `settings_seo.php#pgset-ai` for role < 3 only.
- Most-read pages per bot: 5 per bot (`pg_ai_top_pages()`), linked.
- Visits from AI assistants per assistant (`pg_ai_referrals()`); a line names the last day read from the daily summary, which keeps only each day's top referrers.
- Sitemap pages no AI fetched: the total in the heading, the first 50 by name.
- Tests: `tests/visitor_insights_test.php`.
