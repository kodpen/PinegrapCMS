---
name: "pinegrap-ceviri"
description: "Pinegrap CMS'te kullanıcıya görünen metin yazarken kullan: lang() sözdizimi, style_designer.js içindeki _sdT(), tr.json bakımı ve check_lang.php denetimi."
---

# Pinegrap — çeviri katmanı

Kullanıcıya görünen **her** metin çeviriden geçer. Kod içi yorumlar İngilizce
kalır; `tr.json` değerleri ve `lang()` string'leri yorum değildir.

## PHP ve panel: `lang()`

- Basit metin: `lang('Page Name')`.
- Değişkenli:
  `lang(array('string' => '{var:1} Page(s) title updated.', 'vars' => array($count)))`
- Çoğul/ek: `{suffix:N}` + ayrıca `'suffix' => array(...)` dizisi.
- `vars` 0-indexed, yer tutucu 1-indexed; numaraları sırayla eşleştir.
- Filtreler: `|c` Title Case, `|u` UPPER, `|l` lower (`mb_convert_case`;
  Türkçe İ/ı için ASCII `toUpperCase` kullanma).
- Aktif dil kodu: `lang(['info' => true])`.
- **Dışarıda birleştirme yok.** `lang(...) . ' [' . implode(...) . ']'` yazma;
  dosya adı, sayaç, liste de `{var:N}` olarak string'in içine girer. Tuzak en
  çok `log_activity()` çağrılarında.
- Anahtar her zaman **İngilizce kaynak metindir**, yer tutucular dahil.

Tam sözdizimi: `docs/LANG_USAGE.md` (depo dışı; bağlı geliştirme klasöründe).

## Görsel tasarımcı: `_sdT()`

`assets/js/style_designer.js` içinde operatörün ya da ziyaretçinin okuyacağı
hiçbir metin düz literal olamaz: panel etiketi, `row()`/`sect()` başlığı, toast,
diyalog, `customName`, placeholder, `aria-label`, palet bileşeninin örnek
içeriği, başlangıç ağacının `text:` değeri.

- **Anahtar tek tırnaklı düz literal olmalı**: `_sdT("…")`, `_sdT(değişken)`,
  `_sdT('a' + b)` sunucunun tarayıcısına (`pg_designer_i18n_keys()`,
  `includes/fn/designer.php`) görünmez ve metin çevrilmez.
- Anahtara Türkçe yazma, değişken ve numara gömme: `_sdT('Tab {var}', 1)`,
  `_sdT('It stays in {var} as it was.', owner)`. `'Tab 1'` çeviriyi böler.
- `_sdT()` çıktısı escape **edilmez**; HTML'e giren yerde `esc(_sdT(...))`.
- Fiyat da örnek içeriktir: `_sdT('$199')` → tr.json `₺199`. Ham `'₺199'` yazma.
  Para birimi ile dönemi tek anahtara gömme (tutar bir düğüm, `/mo` ayrı düğüm).
- `_pgT(key, fallback)` kaldırıldı, geri getirme. PHP varsayılan ağaçları
  `lang()`, JS ikizleri (`_swTypeDefaults`, `_emptyDefault`) aynı anahtarla
  `_sdT()`.
- `skip` yalnız üç sınıf içindir: tanıma sözlükleri, ham veritabanı değerleri
  (`complete/exported/paid`), kodun eşleştirdiği eski saklanmış değerler. Palet
  örnek içeriği (ad, adres, e-posta, fiyat) `skip` **değildir**.
- Denetim: `php docs/_tools/designer_i18n.php check`.
- Belirti "bazı yerlerde çeviri yok" ise sebep eksik çeviri değil, geç gelen
  haritadır: veri bloğu `style_designer.js`'ten **önce** basılmalı
  (`includes/designer_screen.php`).

## `tr.json` bakımı

- Dosya: `includes/local/tr.json`. Yeni string eklediysen karşılığını da ekle.
- Anahtarı PHP/JS'teki string ile **birebir** yaz: boşluk, noktalama,
  büyük/küçük harf dahil.
- Çeviri değerinde `{var:N}` / `{suffix:N}` yer tutucularını koru; sırasını
  Türkçe'ye göre değiştirebilirsin ama silme.
- Yeni anahtarı dosyanın **sonuna** ekle; biçimi değiştirme, alfabetik sıralama
  yapma. (Ortak dosya — eşzamanlı çalışmada yalnız ekleme yap.)
- Yazmayı atomik yap: geçici dosya + `rename()`; yarım okunan dosya o sayfadaki
  tüm metinleri İngilizceye düşürür.
- Yeni anahtar yazmadan önce mevcut karşılığa bak: `Net` → "KDV Hariç",
  `From` → "Şundan" gibi tuzaklar var.

## Ön yüz: dil dosyası olmayan diller

- Yazılım yalnız `tr.json` ve `en.json` ile gelir. Ürün sahibinin kararı
  (2026-10-09): **yazılımın arayüz dili ayarlardan gelir ve sitenin
  çevirisine girmez.** Siteye eklenen başka bir dilin sayfasında (ko, de, …)
  yazılım metinleri İngilizce kalır. Tek istisna çerez penceresidir: yalnız
  `pg_consent_ui_keys()` listesindeki anahtarlar `pg_tr_ui_text()` →
  `pg_tr_ui_translate()` yoluyla çeviri deposundan okunur ve Çeviriler
  ekranında **"Çerez penceresi"** grubunda (`owner_type 'ui'`, adı tarihsel)
  çevrilir. Bu listeyi genişletmek, araç çubuğunu ya da dinamik kod bloğunu
  çeviriye sokmak bu kararı bozmaktır — sormadan yapma.
- Deneme dilleri için `includes/local/<kod>.json` eklenmez; dosyası olan
  dilde dosya her zaman geçerlidir.
- `pg_tr_ui_sync()` (`pg_tr_extract_group('ui')`, Çeviriler ekranı açılınca)
  grubu pencerenin metinleriyle eşitler: eksikleri önden kaydeder, listede
  olmayan `ui` kullanımlarını ve yalnız onlara ait çevirileri siler.
  Bildirime yeni metin eklersen listeye de ekle; `tests/consent_test.php`
  listeyi dosyadaki `lang()` çağrılarıyla karşılaştırır.
- `lang()`'tan geçen veritabanı metni (bir etiket, bir ürün adı) bu yoldan
  geçmez: içeriktir, sayfanın çevirisine aittir.
- Lorem ipsum (`pg_tr_placeholder_latin()`) çeviriye girmez; örnek içerikte
  zaten yazılmaz.

## Bitirmeden önce

`php tools/check_lang.php` temiz olmalı — eksik anahtar ve `_sdT()` literal
kuralı ihlali yok.
