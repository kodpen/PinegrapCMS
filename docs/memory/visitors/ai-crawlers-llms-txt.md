---
name: ai-crawlers-llms-txt
description: AI section of robots.txt (Content-Signal, Disallow groups in step with the WAF switches) and llms.txt: settings, where each line goes, which pages llms.txt lists (2026.11.0, 11.0.21, #739).
---

## Where is it set and stored?
- Site Settings › SEO › AI Crawlers (`pgset-ai`, `includes/settings/seo.php`). Columns from step 11.0.21: `config.robots_ai_rules`, `robots_ai_training`, `llms_txt` (TINYINT, default 0) and `additional_llms_content` (TEXT). All off after the upgrade: robots.txt stays byte-identical and `/llms.txt` answers as before.
- Code: `includes/fn/ai_crawlers.php`; `get_robots.txt.php` calls `pg_robots_txt_content()`, `get_llms.txt.php` serves llms.txt.

## Who decides which AI crawler is turned away?
- Training (`pg_ai_crawler_catalog()['train']`: GPTBot, ClaudeBot, Google-Extended, Applebot-Extended, CCBot, meta-externalagent, Amazonbot, Bytespider): the card's own "Allow AI training" switch.
- Assistants (ChatGPT-User, Claude-User, Perplexity-User) and AI search (OAI-SearchBot, Claude-SearchBot): the WAF switches `waf_allow_ai_fetchers` / `waf_allow_ai_search`, shown read-only on the card. One source, so robots.txt never contradicts the firewall. `tests/seo_ai_crawlers_test.php` holds these lists to `waf_ai_bots()` and the real training crawlers to `waf_bad_bots()`.
- PerplexityBot is left out: Perplexity calls it a search crawler, the WAF blocks it as a bad bot.

## What is written, and where?
- Each turned-away crawler gets its own `User-agent: X` / `Disallow: /` group at the TOP of the file. Not after the operator's text: a text ending on a User-agent line would absorb it and close the site to that crawler.
- An allowed crawler gets no group: it reads `User-agent: *`, so noindex pages stay out for it too. A group of its own would make it ignore them.
- `Content-Signal: search=…, ai-input=…, ai-train=…` goes into the catch-all group exactly like the noindex rules (operator's group, else ours with `Allow: /` when it has no rule). ai-input = assistants or AI search let in; ai-train = training switch; a catch-all with `Disallow: /` makes all three `no`.
- The operator's text wins: a crawler it names keeps only its own group; its own Content-Signal line suppresses ours and the comment preamble.

## What does llms.txt contain?
- `# name` (organization, else browser title, else host), `> description` (site meta description), the operator's Markdown, `## Pages` (translated heading), `## Optional` (untranslated: the format's name) with the sitemap link.
- Pages: sitemap rules (sitemap=1, not noindex, public, not archived, not in the recycle bin), types standard/folder view/photo gallery/custom form/form list view/form view directory/calendar view/catalog; cart, order forms and search results left out. Home first, then views of the last 30 days (`visitor_content_hourly`), then newest change; at most 50. Title = page title, home falls back to the browser title, others to the page name.
- `text/plain; charset=utf-8`, `X-Robots-Tag: noindex`. Default language only; `/en/llms.txt` is redirected to the root like robots.txt.

## How is llms.txt routed?
- `router.php` sends `llms.txt` to `get_llms.txt.php` only while `router_llms_txt_enabled()`; switched off it falls through to a file of that name in the file manager, else a page (404). A physical file in the web root is served by the web server and never reaches this.
- Not exempt from the WAF bot policy (unlike robots.txt): a blocked training crawler is not owed the guide.

Related: docs/memory/visitors/robots-txt-disallow-rules.md, docs/memory/waf/waf-ai-bots-ranges.md
