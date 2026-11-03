---
name: language-redirect-matching
description: How the automatic language redirect reads a browser language tag: regions, Chinese script before region, aliases
module: language_redirect
---

## How is a browser language tag matched to a site language?
- `pg_lang_match()` tries the tag's own form, then the bare language (`en-GB`, then `en`); old codes are aliases (`iw` he, `in` id, `nb`/`nn` no, `fil` tl); last, another variety of the same language.
- Portuguese: region `BR` is `pt-BR`, anything else `pt`.

## Which Chinese does a visitor get?
- The script decides when the tag has one: `Hant` Traditional (`zh-TW`), `Hans` Simplified (`zh-CN`). Without a script, `TW`, `HK` and `MO` read Traditional, the rest Simplified.
- Before #PR the region won over the script, so `zh-Hans-HK` (an iPhone set to Simplified in Hong Kong) was sent to the Traditional pages.
