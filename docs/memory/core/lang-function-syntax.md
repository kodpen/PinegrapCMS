---
name: lang-function-syntax
description: lang() call forms: plain, variables {var:N}, case modifiers |c |u |l, plural suffix, no text concatenated outside lang(), and the lang(['info' => true]) active-language query.
---

## How do I call lang()?
```php
lang('Page Regions')
// the Turkish value, or the English string if absent
lang(['string' => '{var:1} is required', 'vars' => lang('Name')])
// -> "Ad gereklidir"
lang(['string' => '{var:1} match{suffix:1} found in {var:2} item{suffix:2}.', 'vars' => [5, 3], 'suffix' => ['es', 's']])
// -> "5 matches found in 3 items."
```

## What are the placeholders?
- `{var:1}`, `{var:2}`, ... are sequential. The `vars` array is 0-indexed, the placeholder is 1-indexed.
- `{var}` is the shortcut for a single variable (same as `{var:1}`).

## What do the case modifiers do?
- `{var:1|c}` Title Case, `{var:1|u}` UPPER, `{var:1|l}` lower; single-variable shortcuts `{var|c}`, `{var|u}`, `{var|l}`.
- Why: in Turkish a word may be lowercase mid-sentence and capitalised at sentence start; one variable used in both places takes `|c` where it starts the sentence.
- They run `mb_convert_case()` (`$safe_case` in `lang()`): UTF-8 safe (`ş` -> `Ş`, `ı` -> `I`), where byte-wise `strtoupper()` or an ASCII `toUpperCase()` leaves or breaks Turkish letters. It is not Turkish-locale aware: `i` becomes `I`, not `İ` (`istanbul` -> `ISTANBUL`, `Istanbul`), and `İ` lowercases to `i` + U+0307.

## Plural suffix?
```php
lang(['string' => '{var:1} record{suffix:1}', 'vars' => $count, 'suffix' => ($count == 1 ? '' : 's')])
// 1 -> "1 record" / 5 -> "5 records"
```
- Several suffixes: `'suffix' => [$a, $b]` fills `{suffix:1}`, `{suffix:2}`; a bare `{suffix}` takes the first.
- The Turkish translation leaves `{suffix:N}` out, since Turkish has no English plural ending (kept, the ending is glued onto the Turkish word): `"{var:1} minute{suffix:1} {var:2}": "{var:1} dakika {var:2}"`. Every `{var:N}` stays; their order may change. (The tr.json value is looked up first, then placeholders are filled.)

## May I add text around a lang() call?
- No. `lang([...]) . ' [' . implode(', ', $names) . ']'` is wrong: the translation cannot move or reword the added part. File names, counts and lists go inside the string as another `{var:N}`:
```php
log_activity(lang([
    'string' => '{var:1}{suffix:1}{var:2}{var:3}{suffix:2} that deleted with clean up tool. [{var:4}]',
    'vars'   => [$folder_text, $and_text, $file_text, implode(', ', $names)],
    'suffix' => [$folder_suffix, $file_suffix],
]), $user);
```
- The trap is most common in `log_activity()` calls.

## How do I read the active language?
- `lang(['info' => true])` returns the active language code: `'tr'`, `'en'`, etc. On a site page drawn in another language (`FRONTEND_LANGUAGE`) it is that page's language, otherwise the enforced or the software language, else `'en'`.
- `'language' => 'xx'` asks one language explicitly; with `'if_known' => true` the answer is the file's wording or `null` when the file does not know the key (nothing filled in).

## How is the language key written?
- Key is the English source string character for character, spaces, punctuation and placeholders included: `"{var:1|c} is required": "{var:1|c} gereklidir"`.

Related: docs/memory/core/translation-rules.md
