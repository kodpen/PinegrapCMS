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
- `api.php`'de handler'dan önce genel bir kapı vardır (oturum, sonra rol ≤ 1);
  handler içine yazdığın izin listesi oraya ulaşmayan isteği durduramaz.
  Kapıyı `includes/panel/actions.php` tablosu belirler (`pg_panel_gate()`):
  `exempt => true` kapıyı atlatır, `any_role => true` yalnız oturum ister,
  tabloda olmayan ad kapıdan geçer. Rol 2/3'e açık yeni eylem `exempt => true`
  **ve** handler'da kendi oturum/token/rol kontrolüyle yazılır; muaf adlar
  `tests/panel_actions_test.php`'de sabit listeyle karşılaştırılır, ekleme
  iki yerde bilerek yapılır.
- Okuma ve yazma uçlarını ayır (ör. `shared_component`'in `list/get/prefetch`
  uçları içerik rolüne açık, yazan her şey rol ≤ 1; tek istisna kendi
  kapısını taşıyan `content_update`).
- Yeni `explorer_*` alt eylemini iki yere yaz: `pg_explorer_handle()` switch'i
  **ve** `file_explorer` izin listesi (`includes/panel/explorer.php`); yoksa
  istek `pg_explorer_handle()`'a hiç ulaşmaz, `"Unknown type."` JSON hatası
  döner.

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

- Ortak bileşeni ya da sistem widget'ını sayfa kaydından `content` seviyesine
  **asla** açma (işaretli alan içinde bile); `style_custom_*`, tema ve gövde
  sınıfları da yazılmaz. Tek istisna tasarımcının açtığı ortak bileşendir
  (`content_editable`, 11.0.8): kendi `content_update` kaydıyla, aynı
  birleştirmeyle, `pg_shared_component_content_editable_ids()`'in saydığı
  kişilere (yönetici hepsi, kullanıcı `users_shared_components_xref`'tekiler).
  Sistem widget'ı hiç açılmaz.
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
