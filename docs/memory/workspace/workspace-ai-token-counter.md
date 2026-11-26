---
name: workspace-ai-token-counter
description: Pinegrap AI's daily token counter of the licence under the licence card's days and on the Pinegrap AI settings row: where the figure comes from, when it is asked again, when the day ends
module: ai_quota
---

## What does the counter show?
- Under "days remaining" in the account menu's licence card (`license_check('bar')`, only while the licence is valid): "Pinegrap AI today", "1,234 / 5,000 tokens" or "1,234 tokens, no daily limit", a 3px bar while there is a limit, "Starts again at 00:00" in the site's clock. `pg_ai_quota_card_html()`.
- On the Pinegrap AI settings row's facts line, after this site's 7/30 day tokens: "Licence today: 1,234 / 5,000 tokens" (`ws_ai_row_html()`, value `quota`).
- Tone: green, amber from 80%, red at the limit. Nothing is shown before the gateway has answered once, or when AI is not part of the licence.

## Whose tokens are counted?
- The licence's, every site and key of it together, as the gateway (ai.pinegrap.com) counts them: prompt and completion tokens added up. This site's own count stays the 7/30 day line (`ws_ai_tokens_line()`).
- Owner (2026-11-27): one count per licence, the numbers are totals, the day follows Istanbul time, a daily limit of 0 means no limit (calls are still counted). Over the limit the gateway answers 429; AI not in the licence, 403.

## Where does the figure come from?
- The gateway's `GET /v1/license` answer carries an `ai` block: `enabled`, `daily_tokens` (0 = no limit), `used`, `remaining`, `day`, `resets_at` (ISO, +03:00). `pg_ai_quota_from()` keeps enabled, limit, used, resets_at and the check time.
- Kept as the config_text row `ws_ai_quota` (JSON), so a panel page reads it without a call (`pg_ai_quota()`, `pg_ai_quota_save()`). No schema change.
- `ws_ai_quota_keep()` keeps only the gateway's own licence answer: a 200 with `valid`, or its refusal of the key. A page a network in between put up, or no answer, leaves the row as it was.

## When is it asked again?
- At every licence check (`ws_ai_license_check()`, every 6 hours or forced from the settings).
- After a chat call to the gateway answered 200 or 429: `ws_ai_quota_refresh()` asks `/license` (5 s timeout) when the kept figure is older than `PG_AI_QUOTA_TTL` (60 s) or from a day that has ended, at most once a minute in one request. A model assistant's own API is never asked.
- The licence card never calls out: a panel page shows the kept figure.

## What happens when the day ends?
- `pg_ai_quota_view()` counts 0 from the kept `resets_at` on, and moves the reset on by whole days, until the gateway is asked again. Other sites of the licence may have used tokens meanwhile; the figure is right again after the next call.

## Tests
- `tests/ai_quota_test.php`: the answer read, odd values, the row read back, when it is due, the day's end, tones, unlimited, the card. `tests/settings_ai_voice_rows_test.php`: the facts line.
