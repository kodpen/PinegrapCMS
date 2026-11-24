---
name: decisions-made-product-97
description: Decisions list, product part 97 (2026-11-21 on): with "Allow AI training" on, the firewall lets the training crawlers through instead of refusing them as bad bots; rate limits still apply
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-96.md`.

## Does the firewall refuse AI training crawlers while "Allow AI training" is on? (owner, 2026-11-21, agent channel poll 216, channel message 11488)
- "Evet, anahtar açıkken geçsinler": with the switch on (SEO › AI Crawlers), the firewall no longer refuses the training crawlers (`pg_ai_crawler_catalog()['train']`: GPTBot, ClaudeBot, Google-Extended, Applebot-Extended, CCBot, meta-externalagent, Amazonbot, Bytespider) from its bad-bot list.
- Binds: the firewall reads the SEO switch; no new setting. With the switch off they are refused as today. Rate limits apply to them as to any visitor.
- Why: the switch wrote the allow into robots.txt while the firewall still refused the same crawlers, so it did nothing unless the shop also typed each bot into Allowed Bots.
- Not chosen: "Hayır, güvenlik duvarı hep reddetsin" (the switch would have acted on robots.txt only, with a line on the screen saying they are refused).

## How does a training crawler pass? (architect, 2026-11-21)
- It is taken off the bad-bot match only: it gets no good-bot privilege and no skipped check, so a request that only claims the name gains nothing a plain visitor does not have. The other firewall rules (attack patterns, bans, limits) stay.
- The two kinds stay apart (`waf/waf-ai-bots-ranges.md`): user fetchers and AI search indexers keep their own switch and their range check; this switch does not touch them.
- Brought by #11023: `waf/waf-ai-training-crawlers.md`; the Bots row says whether training crawlers pass (`ui/settings-firewall.md`).
