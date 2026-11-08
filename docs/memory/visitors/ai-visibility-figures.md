---
name: ai-visibility-figures
description: AI visibility figures and GET /reports/ai-visibility: fetches per bot, top pages per bot, unfetched sitemap pages, robots/llms state, AI assistant referrals, 400-day sweep.
module: ai_visibility
---

## What does the module read?
- `ai_fetch_daily` only (the counter, `ai-fetch-counter.md`), never raw requests; `pg_ai_visibility_ready()` probes `fetches` (2026.11.0 step 11.0.150).
- Bots: `pg_ai_fetch_bots()` = the `agent` and `search` lists of `pg_ai_crawler_catalog()` plus `unverified`; the names match `waf_ai_bots()` (a test fails if they part). `pg_ai_bot_policy()` adds what the site tells each: the firewall switch of its kind (`agents`/`search`; `unverified` never allowed), `robots_ai_rules`, `llms_txt`.
- `pg_ai_fetches_by_day()` (day, bot, SUM), `pg_ai_fetch_bot_totals()` (every known bot, 0 included), `pg_ai_top_pages()` (per bot, `unverified` and page 0 left out, labels through `pg_visitor_label_content_rows()`; a page id no longer in `page` goes in with `deleted => true`, which the labeller turns into "Deleted page" with no link: its empty name used to read as the home page linked to the site root), `pg_ai_unfetched_pages()` (the sitemap's public pages — the `pg_llms_txt_pages()` filter: sitemap, noindex, folder not archived or binned, public — then `pg_ai_sitemap_listed_pages()` drops what sitemap.xml leaves out as `get_sitemap_info.php` does: a standard page with an unlisted system widget (`pg_sitemap_unlisted_widget_types()`) and every home page but the newest; minus every page a verified bot fetched in the window). Without that step the card listed login, account and detail pages the sitemap never offers.

## How are visits from AI assistants found?
- `pg_visitor_ai_hosts()` maps referrer hosts to assistants (chatgpt.com, chat.openai.com, perplexity.ai, copilot.microsoft.com, gemini.google.com, claude.ai, you.com, poe.com and others); `pg_visitor_ai_assistant()` also takes the `www.` form, any case, a trailing dot.
- `pg_ai_referrals()` sums `visitors` rows with `referring_host_name IN (...)` and, for days the retention period emptied, `visitor_stats_daily` rows of dimension `referring_host_name` (`is_rest = 0`: a host outside a day's top values is lost there), as `api_reports_visitors()` does; `summary_to` names the last summarized day.
- The `ai` channel (`visitor-channel-rule.md`) uses the same host list; this function keeps reading hosts, since the channel does not say which assistant sent the visit.

## What does the API answer?
- `GET /reports/ai-visibility` (scope `visitors:read`, `api_reports_ai_visibility()` in `includes/api/resources/visitors.php`, type `AiVisibilityReport`): bots, days, top_pages (`limit` per bot, 1-50), unfetched_pages (total and the first 50 by name), robots_ai_rules, llms_txt, referrals (totals, assistants, summary_to). Window as the other visitor reports (`api_visitors_window()`, 400 days). 503 `service_unavailable` before 11.0.150. The MCP tool comes from the route row.

## How long are rows kept?
- 400 days (`PG_AI_FETCH_DAYS`): `job.php` runs `pg_ai_fetch_sweep()` once a day (state file `data/temp/ai_fetch_sweep.json`), 5,000 rows a statement within a 5-second budget.
- Tests: `tests/ai_visibility_figures_test.php`. PR: #5601. The screen: `ui/visitors-ai-section.md`.
