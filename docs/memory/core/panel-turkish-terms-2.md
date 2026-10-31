---
name: panel-turkish-terms-2
description: More Turkish panel words (shipping method, Site Log file lines, Cloudflare setting values, Private Label Branding card, gift card and commission labels), continued from panel-turkish-terms
module: cloudflare_tools
---

## Which word does the panel use? (continued from [[panel-turkish-terms]])
- Shipping method: "nakliye yöntemi" everywhere (headings, sub-headings, messages; #4112, #4263). "gönderim" stays only in "gönderim tarihi ayarlaması" (Ship Date Adjustment) and "gönderim kesintisi".
- Site Log file lines: one pattern, "dosya ({var:1}) oluşturuldu / düzenlendi / silindi / Görsel Düzenleyici ile …"; never "dosyası (x)" or "(x) dosyası" (#4263).
- Private Label Branding card (Site Settings › Appearance, administrators only): "Özel Marka Kimliği"; its switch "Enable private label branding" ("Özel marka kimliğini etkinleştir"), the logo hint "Leave blank to use the default Pinegrap logo". The administrator's Appearance description names all four cards; "Dashboard" in a description is "Panel kartı", the card title "Panel" (#4263).
- Gift card "Original Amt": "İlk Tutar"; commission frequency "One-Time": "Tek seferlik" (#4263).

## How are Cloudflare setting values shown?
- Cloudflare Tools' notice and Site Log line after a toggle print the value through `pg_cloudflare_setting_value_label()` (`includes/fn/cloudflare_tools.php`): on/off, under_attack, essentially_off, low, medium, high become lower-case `lang()` words ("açık", "saldırı altında", "orta" …); an unknown value is shown as Cloudflare sent it. They printed the raw API value before (#4263). Test: `tests/cloudflare_setting_values_test.php`.

Adding or changing a value: `php tools/tr_add.php [--replace]` only (`lang-files.md`).
