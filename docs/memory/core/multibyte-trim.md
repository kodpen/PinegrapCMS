---
name: multibyte-trim
description: trim()/rtrim()/ltrim() read their character list byte by byte, so "–", "—", "·" or "“" in it breaks other characters; trim those with a /u regex (pg_ai_write_cut(), #744)
---

## Why not rtrim($text, " ,;:-–—")?
- The trim family takes its list as single bytes. "–" (E2 80 93) and "—" (E2 80 94) put E2, 80, 93 and 94 on it, so the last byte of any character that ends in one of them goes too: Cyrillic "р" (D1 80), Japanese "ん" (E3 82 93), Chinese "一", Hebrew "ה", "✓", emoji such as 🚀. What is left is broken UTF-8.
- Turkish letters (ç ğ ı İ ö ş ü) end in none of those bytes, so a test with Turkish text alone passes.

## Why does it show up as an empty answer?
- Broken UTF-8 fails late and quietly: `json_encode()` returns false (`encode_json()` in api.php's `respond()` sends an empty HTTP 200 body), `h()` returns '', and PHP 7.1 has no `JSON_INVALID_UTF8_SUBSTITUTE` to fall back on.

## What to write instead?
- A character class with the `u` modifier, which matches whole characters: `preg_replace('/[\s,;:\-–—]+$/u', '', $text)` (`^…` for the start). A list of ASCII characters only is safe in trim().

## Where did it bite?
- "Write with AI" (`pg_ai_write_cut()`, includes/fn/ai_write.php): a draft or a context text cut just after a word ending in "р", "ん" or an emoji. The endpoint answered with 0 bytes, the panel said "The draft could not be fetched. Check the connection and try again.", and Pinegrap AI had already taken a draft from the daily quota and written the activity line. Fixed with the regex above (#744); cases in `test_ai_write_cut()`.
