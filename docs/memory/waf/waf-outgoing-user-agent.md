---
name: waf-outgoing-user-agent
description: Every outgoing cURL call must set CURLOPT_USERAGENT via pinegrap_user_agent(); an empty UA made one Pinegrap install block another as bot-empty.
---

## Why must outgoing requests identify themselves?
- Pinegrap sent NO `CURLOPT_USERAGENT` on any outgoing cURL call. Licence validation, update check and gateway calls went out with an empty user-agent; the Pinegrap install on the other side classified them `bot-empty` and rejected them. The software blocked itself; the symptom was "main server unreachable".
- `pinegrap_user_agent()` (waf.php) returns `Pinegrap/<version> (+<host>)`. It contains no `bot` / `crawler` / client-library token, so it is classified as an ordinary visitor.
- It is deliberately NOT on the good-bot list, otherwise anyone writing "Pinegrap" in a UA would be exempt from the rate limit.
- RULE: when adding a new outgoing call, set `CURLOPT_USERAGENT`.
