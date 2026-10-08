---
name: "pinegrap-roller-yetki"
description: "Pinegrap CMS'te kullanıcı rolleri, alan kapıları, izin sütunları ve tasarımcıdaki içerik rolleri ile çalışırken kullan."
---

# Pinegrap — roller ve yetki

## Rol tablosu

| Rol | Değer | Erişebilir | Erişemez |
|---|---|---|---|
| Administrator | 0 | Her şey | — |
| Designer | 1 | Tasarım araçları dahil admin-dışı her alan | Sadece-admin alanları |
| Manager | 2 | İçerik, e-ticaret, ayarlar | Tasarım araçları |
| User | 3 | Temel içerik | E-ticaret vb. için özel bayrak gerekir |

Hiyerarşide **Designer (1), Manager'ın (2) üstündedir.**

## Kapılar

- Her sayfanın başında `validate_user()`.
- `validate_area_access($user, 'administrator')` → yalnız rol 0;
  `'designer'` → rol ≤ 1; `'manager'` → rol ≤ 2.
- E-ticaret: rol 0–2 `manage_ecommerce` bayrağından muaftır, rol 3 için
  zorunludur. Aynı kalıp dış API sahibi kapsamlarında da geçerlidir.
- `api.php`'de `switch ($action)` öncesinde genel bir rol kapısı vardır; `case`
  içine yazdığın izin listesi oraya ulaşmayan isteği durduramaz — yeni eylemi
  muafiyet listesine **ve** ilgili alt izin listesine ekle.
  `includes/panel/actions.php` tablosuna alınan eylemler için de muafiyet hâlâ
  `api.php` zincirindedir; tablonun `exempt` sütunu onunla uyuşmak zorundadır
  (test karşılaştırır).
- Okuma ve yazma uçlarını ayır (ör. `shared_component`'in `list/get/prefetch`
  uçları içerik rolüne açık, yazan her şey rol ≤ 1).
- Yeni `explorer_*` alt eylemini iki yere yaz: `pg_explorer_handle()` switch'i
  **ve** `file_explorer` izin listesi (`includes/panel/explorer.php`); yoksa
  HTTP 200 + boş gövde.

## İzin sütunları

- Yetki sütunları öneksiz `TINYINT`'tir (`manage_erp`, `manage_erp_cash`,
  `manage_erp_settings` gibi). Yok/Okuma/Yazma üçlüsü bu kod tabanında yoktur.
- Yeni yetki sütunu eklerken rol-3 merdiveninin **dördünü** birden güncelle:
  `welcome.php`, `get_page.php`, `includes/fn/content.php`,
  `set_password.php` / `reset_password.php`. Atlanırsa tek hakkı o olan kullanıcı
  "erişim yok"a düşer.
- Yetki switch'inde alan adını ve **tam değerini** koru (`manage_calendars` =
  `'yes'`, `view_card_data` = `'1'`); POST işleyicileri tam değere bakar.
- Yetki satırlarını `pg_user_permission_ui($config)` / `pg_user_role_cards()` /
  `pg_user_permission_everything()` ile çiz.
- Kartı ve bağlantılarını ayrı kapıla: rapor kartı
  `USER_MANAGE_ECOMMERCE_REPORTS`, sipariş bağlantıları `manage_ecommerce` —
  ikisi kullanıcıda ayrı durur.

## Tasarımcıda içerik rolleri (`includes/designer_access.php`)

- Ortak bileşeni ya da sistem widget'ını `content` seviyesine **asla** açma
  (işaretli alan içinde bile); `style_custom_*`, tema ve gövde sınıfları da
  yazılmaz.
- Asıl kapı `pg_designer_save_page()` içindeki
  `pg_designer_merge_restricted_tree()`'dir; `_sdMayEdit` yalnız nezakettir —
  düğmeyi gizlemek yetki kontrolü değildir.
- Kayıtta reddetme değil **kopyalamama** uygula: işaretli alanın içinden alt
  ağaç, dışından yalnız `props.text` ve görsel `src`'si alınır.
- Kabul edilen alt ağaçtaki `_editable` bayraklarını soy, `shared_ref`'leri
  kayıtlı hâliyle geri koy.
- Sayfanın ad/klasör/ana sayfa alanlarını kayıtlı satırdan, mükerrer-ad
  kontrolünden **önce** geri yaz.
- Birleştirmede çocukları `_id` ile eşleştir (konum eşleştirmesi kopyalamada
  kayar ve yanlış düğüme metin yazar).
- Yeni mutasyon yolu eklerken guard listesine de ekle: iki sağ tık menüsü,
  ağaçtan sürükleme, bırakma hedefi, Delete/Backspace, `handleDrop`, `onDelete`,
  satır içi metin, tuval araç çubuğu, `renderProperties`.
- Render'da sessiz `_sdCanEditNode`, etkileşimde konuşan `_sdMayEdit` çağır.
- İçerik rolünün erişemeyeceği düğmeyi hiç gösterme
  (`_sdApplyContentChrome()` idempotenttir).
- Rol seviyeli menü maddelerini de `_sdGuardItem`'dan geçir (önce kilit, sonra
  rol); `_sdGuardItem` maddeyi kaldırmaz, soluklaştırır.
- Çeviri bölümü (Seçenekler paneli) içerik düzeyinde metnini
  düzenleyebildiği düğümde açılır (salt okunur panelin üstünde), widget
  ve ortak bileşen içinde açılmaz. Rol 3'e `translations_action.php`
  yalnız `node` / `save` / `status`'u, `page_id` için
  `pg_designer_page_access() === 'edit'` iken açar; yazma ve onayda
  `pg_tr_string_page_scope()`: metnin bu sayfa dışındaki her kullanımı
  kullanıcının düzenleyebildiği bir sayfada olmalı (ortak bileşen,
  widget, menü, form, katalog değil); değilse `node` cevabı metni
  `locked` işaretler ve panel salt okunur çizer.
- Taslak durumunu (`page_draft`) yalnız tam erişim değiştirir; içerik
  düzeyi kayıt saklı durumu korur. Taslakta erişim kararı dönülecek
  klasöre göredir (`pg_designer_page_access()`).
