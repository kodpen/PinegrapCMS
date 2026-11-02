---
name: settings-window-turkish-texts
description: Site Settings window wording in Turkish (#5029): mid-sentence relative time, the Turkish starter dollar name, translated language names in the add-language list, settled terms
---

## Why does the footer say "a moment ago" with a small letter?
- `pg_settings_last_modified()` calls `get_relative_time()` with `'mid_sentence' => true`, which takes the `'a moment {var:1}'` key ("az önce") instead of `'A moment {var:1}'`. Before, the footer read "Son düzenleme Az önce admin tarafından" (English too: "Last modified A moment ago"). Other results start with a number or a date and need no change.

## Why does 'US Dolar' show as "ABD Doları"?
- The Turkish starter dump (`data/backups/turkish_default/sql.sql`) stores the first currency as `'US Dolar'`. `pg_currency_label()` reads it as `'US Dollar'` and prints `lang('US Dollar')`. The stored value is not changed.

## How are languages named in Diller ve Çeviri › Dil ekle?
- `lang($info[0])` of `pg_tr_known_languages()` plus the native name: "Almanca - Deutsch (de)". Every English name in that list has a Turkish key; `tests/settings_turkish_texts_test.php` fails for a new language without one.

## Which terms are settled?
- "Ödeme Yöntemleri" (not "Metodları"), "para birimi" in two words, "çalışma süresi denetimi" for the uptime check, the brand as "iyzico" in labels (Iyzipay stays in log messages), "… Etkinleştir" / "… Kabul Et" (not "-in"), "ve" instead of "&" in card titles.
