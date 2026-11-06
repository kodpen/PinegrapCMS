---
name: visitors-ai-section
description: AI Visibility section of the Visitors overview (ai.php, #6475): bots and firewall, fetches by day, pages per bot, assistant visits, unfetched pages, old-screen redirect
module: visitor_insights
---

## Where is it?
- `pg_visitor_ai_section($window, $user)` in `includes/fn/visitor_insights.php`; `includes/visitors/ai.php` (`pg_visitors_section_ai()`) puts it in `visitors.php#ai` with the overview's period as the window (V17, #6475). Nothing is drawn until `pg_ai_visibility_ready()`; the flyout anchor follows the same check. The figures are `visitors/ai-visibility-figures.md`.
- `visitor_ai.php` only answers a 301 to `visitors.php?p=…#ai`, its `p` kept (30 days when absent). Panel search "AI Visibility" opens the anchor.

## What does it show?
- Tiles: verified AI fetches and how many bots fetched (`pg_ai_fetch_summary()`, unverified claims kept apart), visits from AI assistants with page views, forms submitted, and with `ECOMMERCE` orders completed with the order total (minor units / 100 through `prepare_amount()`).
- Fetches by day: every bot together, each day of the window (0 for none), as `pg_visitors_chart()`.
- AI bots: every known bot, its kind under its name, the firewall state and its fetches (the state allowed, blocked, "counted only" for unverified); under it whether robots.txt carries the AI section and llms.txt is served, with a link to `settings_seo.php#pgset-ai` for role < 3 only.
- Most-read pages per bot: a row a bot in the bots' order, its 3 most read pages linked with their fetches (`pg_visitor_ai_page_rows()`, pure, over `pg_ai_top_pages()`); a row a page made 25 rows.
- Visits from AI assistants per assistant (`pg_ai_referrals()`); a line names the last day read from the daily summary, which keeps only each day's top referrers.
- Sitemap pages no AI fetched: the total in the heading, the first 50 by name, 5 shown at first.
- Tests: `tests/visitor_insights_test.php`.
