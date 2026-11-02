---
name: web-vitals-ai-visibility-steps-2
description: Steps WV4-WV6 of plans/web-vitals-ai-visibility.md: AI visibility figures and referrals, the Speed and AI sections with the card, close-out; claim as [plan:web-vitals-ai-visibility] WV<n>
---

## Which steps are there?
- Sizes, tests and memory rules as in `web-vitals-ai-visibility-steps.md`; WV4 runs beside WV2, WV5 after both.

- WV4 (M) AI visibility figures and referrals. After WV3.
  - `includes/fn/ai_visibility.php`: fetches per bot and day, the pages each bot reads most, pages never fetched although in the sitemap, the llms.txt and robots state per bot beside it (`pg_ai_crawler_settings()`); visits from AI assistants: `pg_visitor_ai_hosts()` (chatgpt.com, perplexity.ai, copilot.microsoft.com, gemini.google.com, claude.ai, you.com and the rest) applied to `visitors.referring_host_name` in the summary queries; when `plans/visitors-overview.md` V4 lands, its `ai` channel replaces the list and this function reads the channel. API `reports/ai-visibility`. Memory: `visitors/ai-visibility-figures.md`.

- WV5 (M) Screens and the dashboard card. After WV2 and WV4.
  - Sections "Speed" and "AI visibility" in the Visitors overview (`visitors.php`, after its V1 is merged; until then both open from the Visitors flyout as `visitor_vitals.php` and `visitor_ai.php` with the overview's toolbar, and move in when it lands): site p75 tiles with the good share, the slowest pages with their counts, the trend; bots with their fetches, the most-read pages per bot, AI referrals and what those visitors did (forms, orders from `visitors`). The new dashboard's `perf` card (`new-dashboard-cards-2.md`) shows the site p75 and the good share beside the response time. Memory: `ui/visitors-speed-section.md`, `ui/visitors-ai-section.md`.

- WV6 (S) Close-out. After WV1-WV5.
  - PR body names `[NEW] Added real-user page speed figures and an AI visibility view to Visitors`; decisions to `visitors/`; the two plan files and the line in `feature-plans-2026-11.md` deleted; `php tools/memory_index.php`.
