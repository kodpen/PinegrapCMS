# Pinegrap CMS — skill dizini

Bu klasör, `CLAUDE.md`'nin şişmemesi için konu konu ayrılmış bağlam dosyalarını
tutar. `CLAUDE.md` her oturumda otomatik yüklenir; buradaki dosyalar **yalnız o
konuya dokunulduğunda** okunur. Amaç token tasarrufu değil sadece: bir ekran
yazarken ERP muhasebe sözleşmesini okumak dikkat dağıtır.

## Kullanım

- Claude Code / Cowork oturumu bu klasörü skill olarak görürse: `Skill` aracıyla
  adıyla yükle (`pinegrap-veritabani` gibi).
- Görmezse (klasör bağlı ama skill listesinde değilse): dosyayı doğrudan oku —
  `.claude/skills/<ad>/SKILL.md`. İçerik aynıdır.
- Alt ajan (subagent) çalıştırıyorsan ilgili skill'in **yolunu** göreve yaz;
  ajan kendi bağlamında CLAUDE.md'yi görmeyebilir.

## Dizin

| Skill | Ne zaman |
|---|---|
| `pinegrap-veritabani` | SQL yazarken, tablo/kolon okurken, sayaç ve rollup tasarlarken |
| `pinegrap-sema-adimi` | Şema değiştiren migration adımı yazarken |
| `pinegrap-yeni-dosya` | Yeni dosya/fonksiyon/modül eklerken, dosya taşırken |
| `pinegrap-ceviri` | `lang()` / `_sdT()` / `tr.json` işlerinde |
| `pinegrap-js-varliklari` | `.src.js` / `.min.js`, panel ve ön yüz JS'i |
| `pinegrap-guvenlik` | CSRF, escape, yönlendirme, sırlar, dosya bütünlüğü, sunucu kuralları |
| `pinegrap-waf` | `waf.php`, hız sınırı, IP listeleri, güvenlik başlıkları |
| `pinegrap-ui-deseni` | Panel ekranı, araç çubuğu, form, ayarlar modalı |
| `pinegrap-roller-yetki` | Rol kapıları, izin sütunları, içerik rolleri |
| `pinegrap-tasarimci-widget` | Görsel tasarımcı, sistem widget, data binding, palette component |
| `pinegrap-dis-api` | `integration.php`, `includes/api/`, webhook, dış entegrasyon |
| `pinegrap-erp` | ERP (ön muhasebe) modülü |
| `pinegrap-verilmis-kararlar` | Denetim, "ölü kod" temizliği, refactor önerisi öncesi |

## Bakım

- Yollar `pinegrap/` köküne göredir: `includes/fn/core.php` =
  `pinegrap/includes/fn/core.php`.
- Bu klasör `.gitignore` altındadır (`.claude/`), depoya girmez —
  `CLAUDE.md` ve `docs/` ile aynı çizgide, iç geliştirme bağlamıdır.
- Tam arşiv `docs/CLAUDE-tam.md`'dir. Yeni bir kalıcı kural ortaya çıktığında
  ilgili skill'e **ve** arşive yaz; skill kısa kalsın, vaka anlatısı arşive gitsin.
