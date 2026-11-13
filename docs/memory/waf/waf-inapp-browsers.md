---
name: waf-inapp-browsers
description: Social apps' in-app browsers classify human: bot lists hold crawler tokens only (bytespider not bytedance, pinterestbot, yeti/), Better Uptime matched by its real user agent
---

## What went wrong?
- `waf_classify_bot()` matched company names: `bytedance` (bad bots) caught TikTok's in-app browser (`BytedanceWebview/...`), so a person opening a shared link in TikTok was refused; `pinterest` and `naver` (good bots) took the Pinterest app (`[Pinterest/iOS]`) and the Naver app (`NAVER(inapp; ...)`) for crawlers (#9125).
- `betteruptime` never matched Better Uptime, which sends `Better Uptime Bot Mozilla/5.0 ...`: the residual "bot" signal classed the monitor as a bad bot.

## What do the lists hold now?
- Crawler tokens only, never a company's name. Bad bots: `bytespider` stays, `bytedance` is gone. Good bots: Pinterest is `pinterestbot` and `pinterest/0.` (the preview fetcher, `Pinterest/0.2 (+...bot.html)`); Naver is `yeti/` and `naverbot`; Better Uptime is `better uptime bot`.
- Instagram, Facebook (`FBAN`, `FB_IAB`), LinkedIn (`[LinkedInApp]`), Snapchat and Telegram in-app browsers carry no listed token and classify `human`; their crawlers (`facebookexternalhit`, `linkedinbot`, `telegrambot`) keep their class.

## How is it held?
- `tests/waf_inapp_browser_test.php`: the in-app user agents of TikTok, Pinterest, Naver, Instagram, Facebook, LinkedIn, Snapchat and Telegram classify `human`; the vendors' crawlers keep `good` or `bad`. A new list token is tried against that test first.

Related: docs/memory/waf/waf-score-calibration-and-bot-order.md
