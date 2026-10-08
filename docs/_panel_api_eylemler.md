# Panel `api.php` eylem envanteri

Durum: envanter (2026-10-08), Plan 1 Faz 3'ün girdisi (`docs/_plan_panel_api_widget.md`).
Kaynak: `pinegrap/api.php` @ `dbcea85` (`claude/panel-api-widget`). Sonraki `ccef814` yalnız dosya
sonundaki altı pano yardımcısını `includes/dashboard/widgets.php`'ye taşıyor; `switch ($action)` bölgesi
(satır 1–7316) iki committe bayt bayt aynı, satır numaraları `ccef814`'te de geçerli.

Kapsam: `switch ($action)` içinde satır içi duran **81 case + `default`**. Kapsam dışı (tek satırla
modüle devredenler): `chat_*` (11, 312–340), `ws_*` (128, 342–489), `site_chat_*` (6, 491–502),
`get_widget_data` (880–888).

## Nasıl üretildi

Tokenizer tabanlı betikler `/tmp/inv/` altında (depoya girmez):

```bash
git show HEAD:pinegrap/api.php > /tmp/inv/api_head.php
php -d memory_limit=2G /tmp/inv/analyze.php /tmp/inv/api_head.php pinegrap   # -> cases.json, funcs.json
php /tmp/inv/globals_check.php                                              # global $x / $GLOBALS bağımlılıkları (çağrı derinliği ≤ 2)
php /tmp/inv/tl.php <dosya>...                                              # include edilen dosyada üst düzey kod
php /tmp/inv/gen.php                                                        # tablolar (manual.php: grup + elle düzeltilen hücreler)
# istemci (her eylem için, .min.js hariç, api.php hariç):
grep -rnE "action[\"']? *(:|=>|=) *[\"']<ad>[\"']|action=<ad>([^a-z_]|$)" pinegrap --include=*.php --include=*.js | grep -v "\.min\.js"
# ilk geçişte bulunamayanlar için ikinci geçiş (yardımcı fonksiyonla çağıranlar: pg_health_job, pg_push_send, api()):
grep -rnE "['\"]<ad>['\"]" pinegrap --include=*.php --include=*.js | grep -v "\.min\.js"
# kapı kayıtları (sandbox, yalnız yazmayan eylemler):
php /tmp/golden/record.php before --actions-only
```

- `analyze.php`: ana `switch`'in derinlik-1 `case` etiketlerini bulur (ardışık boş etiketleri birleştirir),
  her gövde için kapı çağrılarını sırasıyla, doğrudan yazmaları (SQL anahtar sözcüğü + tablo,
  dosya fonksiyonları, `$_SESSION` ataması, curl), çağrılan kullanıcı fonksiyonlarından
  geçişli yazmaları (`log_activity` ve `db*` sarmalayıcılarında durur), yanıt biçimlerini,
  include'ları, gövdede atanmadan okunan değişkenleri, döngü/iç switch dışındaki `break`/`continue`,
  `return`, `__FILE__`, adlı fonksiyon tanımlarını çıkarır.
- **Sınırlar:** "dış kapsam" taraması doğrusal ilk-görülme sırasıyla yapılır (bir dalda atanıp öbür
  dalda okunan değişkeni kaçırabilir); dinamik çağrılar (`$fn()`, `call_user_func`) çağrı grafiğine
  girmez; vendored kütüphaneler (Iyzipay, dompdf…) taranmadı. Büyük case'lerin (designer,
  file_explorer, shared_component, designer_file, software_*) kapı ve not hücreleri elle okunarak yazıldı.

## Lejant

- **Genel kapı:** `api.php:155-284`. `muaf` = negatif listede. `genel` = önce `API_MFA_REQUIRED`
  → HTTP 401 JSON `mfa_required`; `!USER_LOGGED_IN` → HTTP 200 `{"status":"error","message":"Invalid login."}`;
  `USER_ROLE > 1` → HTTP 200 `"The User must have a Designer or Administrator role."`
  (`get_form`/`get_forms` için bu üçüncü adım atlanır).
- **Kendi kapısı:** `@` satır numarası. Çağrılan kapıların kendi red biçimi:
  `validate_token()` → JSON "Invalid token." (`global $token` okur; `API_AUTHENTICATED` varsa atlanır);
  `validate_user()` → oturum yoksa **302** `index.php?send_to=…` (gövde boş, `Content-Type: application/json`);
  `validate_area_access()` ve `validate_ecommerce_access()` → **HTML** `output_error` (ve `log_activity`).
- **Yazıyor mu:** `db:` doğrudan SQL'in tablosu; `dosya:` doğrudan dosya fonksiyonu; `session`; `dış:` curl/HTTP;
  `dolaylı X←f` = çağrılan `f()` (ya da onun çağırdığı) yazıyor. `validate_*_access←log_activity` yalnız red yolunda yazar.
- **Yanıt · son:** `respond` (= `echo encode_json` + `exit`), `echo+exit` (`echo encode_json($response); exit();`),
  `output_error` (HTML; çoğu `mysqli_query(...) or output_error(...)` sorgu hatası yolu). "son" = case'in son iki ifadesi.
- **Include:** ¹ `dirname(__FILE__)` ile — dosya `includes/panel/` altına taşınınca yol bozulur, `PG_FUNCTIONS_DIR`'e
  çevrilmeli; ² göreli yol (include_path/cwd'ye bağlı); ³ `PG_FUNCTIONS_DIR` ile (taşınmaya dayanıklı).
- **Dış kapsam:** gövdenin case içinde atamadan okuduğu değişkenler. `$request` beklenen; **kalın** olanlar dikkat.
- **Ara break:** son `break;` dışında döngü/iç switch dışında kalan `break` satırları (fonksiyona taşınınca derleme hatası).

## Taşımayı doğrudan etkileyen bulgular (özet)

1. **`global $user` bağımlılığı (madde 8).** Bugün case gövdesi global kapsamda çalıştığı için
   `$user = validate_user();` global `$user`'ı yazar ve aşağıdaki fonksiyonlar onu `global $user` /
   `$GLOBALS['user']` ile okur. Gövde bir fonksiyona taşınınca `$user` yerel olur, bu fonksiyonlar `null` görür:
   - `file_explorer`: case içinde tanımlı `get_folder_breadcrumb()` (4704) ve `get_folder_table()` (4750)
     `global $user; global $folders_that_user_has_access_to;` okur — ikincisi de case gövdesinde (4537/4543) atanır.
     Ayrıca `pg_explorer_handle()` → `pg_explorer_folder_visible()` → `check_folder_access_in_array()` (`includes/fn/auth.php:2445`, `global $user`).
     `check_folder_access_in_array()` `$user` yoksa rol 0-2'yi de "erişim yok" sayar (kapalı tarafa düşer: yönetici klasör göremez).
   - `designer` (`page_delete`, `design_delete` → `pg_explorer_handle()` zinciri), `designer_file` (`list_folders` → `select_folder(0)`,
     `includes/fn/auth.php:2605`), `tour_seen` (`pg_tour_mark()` → `pg_tour_ready()` `$GLOBALS['user']`, `includes/fn/tour.php:83,119`).
   - Ayrıca short-link seçenekleri (`get_page_options`, `get_file_options`, `get_product_group_options`) `global $user` okur ve
     `pg_explorer_handle()` içinden çağrılır.
   - `validate_token()` `global $token` okur; `$token` `api.php:148-150`'de global kapsamda atanıyor, taşınan gövdeden çağrılması sorun değil
     (dağıtıcı `$token`'ı global bırakmalı).
   → Seçenekler: dağıtıcıda handler'dan önce `$GLOBALS['user']` atamak, ya da bu fonksiyonlara `$user` parametresi geçirmek. Karar mimarın.
2. **`__FILE__` yolları (madde 7).** `dirname(__FILE__)` ile 36 include ve 6 veri yolu satırı var: Sistem Durumu önbelleği
   (`/data/temp/system_status_cache.json`, 594/651/721/768), `server_config_repair` web kökü `dirname(dirname(__FILE__))` (672),
   `software_update` `define('_PATH', dirname(__FILE__))` (2060). `includes/panel/` altında hepsi yanlış dizini gösterir.
   Göreli yollar: `include_once('mysqldump.php')` (1537), `require_once('includes/iyzipay-php/IyzipayBootstrap.php')` (2211),
   `$backup_location = 'data/backups/'` (1490).
3. **Dosya düzeyi `use`.** `software_backup` `new IMysqldump\Mysqldump(...)` (1542) — takma ad `api.php:22`
   `use Ifsnop\Mysqldump as IMysqldump;`. Yeni dosya kendi `use` satırını taşımalı.
4. **Ara `break` (madde 9).** Sistem Durumu case'lerinde `respond(...)` sonrası erişilemez `break;`: 557, 638, 663, 705, 750, 803, 846, 868.
   Fonksiyon gövdesinde döngü/switch dışında `break` → derleme hatası ("'break' not in the 'loop' or 'switch' context"); `return;` olmalı.
   Başka yerde döngü dışı `break`/`continue`, case düzeyinde `return`, `static` değişken yok.
5. **Case içinde adlı fonksiyon tanımları.** `file_explorer`: `get_folder_breadcrumb`, `get_folder_table`, onun içinde
   `get_access_control_icon_classes`, `get_file_icon` (koşullu tanım; istek başına bir kez çalıştığı için bugün sorun yok).
   Dördünü çağıran istemci türü (`get_breadcrumb`, `get_tables`, `get_folder_id`, `delete_file`) bulunamadı.
6. **Boş gövdeli HTTP 200.** `file_explorer` iç `switch ($request['type'])` `default`'suz → bilinmeyen `type` boş 200;
   `get_tables` dalı `echo` + `break` (exit yok, betik sona akar). `software_update_check` koşul yanlışken (ya da `software_update_check()`
   sonrası) boş 200 — sandbox kaydı bunu gösteriyor. Diğer büyük case'lerin iç switch'leri `default` ile JSON hata döner.
7. **Tanımsız değişken (bugünkü hata).** `software_update` `check` adımı cURL yoksa `$liveform->mark_error(...)` (1778) çağırır;
   `api.php` kapsamında `$liveform` hiç atanmıyor → bugün de `null` üzerinde metot çağrısı (Fatal). Taşımayla değişmez.
8. **Tokensız yazanlar (madde 5).** Hepsi ön yüz/ziyaretçi ucu ve genel kapıdan muaf:
   `add_to_cart` (sipariş/kalem + session), `get_shipping_methods` (`shipping_rates` önbelleği, `ship_tos` UPDATE + kargo API),
   `get_delivery_date` (`shipping_delivery_dates` önbelleği + USPS/FedEx), `get_installment_options` ve `eo_get_installments`
   (yalnız dış Iyzipay çağrısı). Panel tarafında yazan her case token istiyor (`offer_editor` handler içinde).
   Token var ama **oturum/rol kontrolü yalnız genel kapıya dayanan** yazanlar: `update_designer_region`, `update_file`,
   `update_layout`, `update_style`, `update_page_designer_properties`, `update_toolbar_properties`, `delete_order`, `update_order`,
   `update_dashboard_appearance` (`validate_user()` çağırmıyorlar; genel kapı rol ≤ 1 ve oturumu zaten istiyor).
9. **Genel kapı ile niyet çelişkisi gibi duranlar (soru).** `push_*` (5) ve `user_pinned_app_update`, `update_dashboard_appearance`,
   `update_toolbar_properties` genel kapıda (rol ≤ 1); kardeşleri (`update_dashboard_widgets`, `tour_seen`, `contact_quick_save`,
   bildirim uçları) "her rol" diye muaf tutulmuş. Barkod uçları (6) genel kapıda olduğu için kendi `validate_ecommerce_access`'leri
   rol 2/3'e hiç ulaşmıyor. Davranış değiştirmek bu işin kapsamında değil; tabloya geçerken **bugünkü** kapı korunmalı.
10. **`designer_file` erişilemez dallar.** Baştaki `role > 1` reddi (7091) sonrası `role === 3` dalları (7147, 7258) erişilemez görünüyor.
    Bulgu değil, not: dokunma.

## Eylem tabloları

Gruplar görevdeki öneriye göre; dosya adı önerisi başlıkta. Satır numaraları `dbcea85`.

### software — yedek, güncelleme → `includes/panel/software.php`

| Eylem | Satır (n) | Genel kapı | Kendi kapısı (sırayla) | Yazıyor mu | Token | Yanıt · son | Include | Dış kapsam | Ara break | İstemci |
|---|---|---|---|---|---|---|---|---|---|---|
| `software_backup` | 1471–1715 (245) | muaf | `validate_user`@1479 → `area:manager`@1480 → `validate_token`@1481 | dosya: mkdir, rmdir, unlink, copy, file_put_contents; dolaylı db←validate_area_access/log_activity | var | echo+exit · `}` break | `mysqldump.php`² | `$request`; **`IMysqldump\Mysqldump`**@1542 — `api.php:22` `use Ifsnop\Mysqldump as IMysqldump;` dosya düzeyi takma ad | — | `view_folders.php:8620`, `assets/js/backend.src.js:6116` |
| `software_update_check` | 1717–1731 (15) | muaf | `validate_token`@1720 → `validate_user`@1721 | koşul doğruysa dolaylı db (config son kontrol damgası, bildirim) + dış (güncelleme sunucusu) ← `software_update_check()`; sandbox'ta `SOFTWARE_UPDATE_CHECK=FALSE` → hiç yazmaz | var | koşul yanlışsa **boş gövde 200**; doğruysa `software_update_check()` (çıktı yok) + `exit` → yine boş 200 · break | `software_update_check.php`¹ | — | — | `assets/js/backend.src.js:8336` |
| `software_update` | 1733–2188 (456) | muaf | `USER_LOGGED_IN`@1743 → `validate_user`@1749 → `area:manager`@1750 → `validate_token`@1751 → `pg_hosted()`@1755 | db: notifications; dosya: unlink, fopen(w), fwrite; dış: pg_curl_tls, curl_exec; dolaylı db←validate_area_access/log_activity; dosya←pg_extract_archive | var | respond, echo+exit, output_error · exit; break | — | `$request` (1780'de case içinde `$request = array()` ile **ezilir**); **`$liveform`**@1778 — api.php kapsamında hiç tanımlı değil (cURL yoksa bugün de null üzerinde metot çağrısı); `define('_PATH', dirname(__FILE__))`@2060 | — | `software_update_check.php:109`+2, `includes/settings/general.save.php:239`, `includes/fn/core.php:2765`, `software_update.php:181`+3 |

- `software_backup`: İç `switch ($step)` 8 dal + `default` (hepsi echo+exit). `data/backups/` göreli yolu (`$backup_location`, 1490) cwd'ye bağlı. `include_once('mysqldump.php')` göreli yol (1537).
- `software_update_check`: Gate kaydı: admin/rol3 → HTTP 200 boş gövde; oturumsuz/tokensız → "Invalid token." (önce `validate_token`). `require` (once değil) — aynı istekte ikinci kez çağrılmaz, sorun değil.
- `software_update`: İç `switch ($step)`: `check`, `download`, `replace`, `default`. `pg_hosted()` → "Software updates are managed by the hosting platform."

### system — Sistem Durumu işleri → `includes/panel/system.php`

| Eylem | Satır (n) | Genel kapı | Kendi kapısı (sırayla) | Yazıyor mu | Token | Yanıt · son | Include | Dış kapsam | Ara break | İstemci |
|---|---|---|---|---|---|---|---|---|---|---|
| `database_deep_check` | 543–618 (76) | muaf | `validate_user`@549 → `role>=3`@552 → `validate_token`@563 | dosya: unlink; dolaylı dosya←check_and_repair_database_tables; db←check_and_repair_database_tables | var | respond · respond; break | — | — (`dirname(__FILE__).'/data/temp/system_status_cache.json'`@594) | 557 | `assets/js/backend.src.js:6489` (`pg_health_job`), düğme `includes/dashboard/widgets/widget_2.php:596` |
| `server_config_repair` | 620–689 (70) | muaf | `validate_user`@631 → `role!==0`@633 → `validate_token`@644 | dosya: unlink; dolaylı dosya←pg_server_config_repair | var | respond · respond; break | — | — (`dirname(__FILE__)`@651, **`dirname(dirname(__FILE__))`**@672 = web kökü) | 638, 663 | `assets/js/backend.src.js:6510` (`pg_health_job`), düğme `widget_2.php:459` |
| `ca_bundle_config_repair` | 691–732 (42) | genel | `validate_user`@698 → `role!==0`@700 → `validate_token`@711 | dosya: unlink; dolaylı dosya←pg_ca_bundle_config_repair; db←log_activity | var | respond · respond; break | — | — (`dirname(__FILE__)`@721) | 705 | `assets/js/backend.src.js:6523` (`pg_health_job`), düğme `widget_2.php:484` |
| `write_permissions_repair` | 734–779 (46) | muaf | `validate_user`@743 → `role!==0`@745 → `validate_token`@756 | dosya: unlink; dolaylı dosya←pg_write_permission_repair; db←log_activity | var | respond · respond; break | — | — (`dirname(__FILE__)`@768) | 750 | `assets/js/backend.src.js:6538` (`pg_health_job`), `software_update.php:366` |
| `purge_cache` | 781–825 (45) | muaf | `validate_user`@796 → `role>=3`@798 → `validate_token`@809 | dolaylı dosya←pg_purge_caches; db←log_activity | var | respond · respond; break | — | — | 803 | `assets/js/backend.src.js:6554` (`pg_health_job`), düğme `widget_2.php:623` |
| `ca_bundle_update` | 827–878 (52) | genel | `validate_user`@839 → `role!==0`@841 → `validate_token`@849 | dolaylı dosya←pg_ca_bundle_update; dış←pg_ca_bundle_update; db←log_activity | var | respond · respond; break | — | — | 846, 868 | `assets/js/backend.src.js:6571` (`pg_health_job`), düğme `widget_2.php:698` |

- `ca_bundle_config_repair`: Genel kapıdan **muaf değil** (rol ≤ 1 ister) ama kendi kapısı zaten rol 0; Sistem Durumu yorumu "üç iş" diyor, muaf listede dört ad var, bu ve `ca_bundle_update` yok.

### dashboard — pano ve hesap menüsü → `includes/panel/dashboard.php`

| Eylem | Satır (n) | Genel kapı | Kendi kapısı (sırayla) | Yazıyor mu | Token | Yanıt · son | Include | Dış kapsam | Ara break | İstemci |
|---|---|---|---|---|---|---|---|---|---|---|
| `user_online_check` | 288–304 (17) | muaf | `validate_token`@289 → `USER_LOGGED_IN`@291 | db: `user.user_online_timestamp` ← `who_is_online()` (init.php:1058 her oturumlu istekte de aynı UPDATE'i yapar) | var | respond · respond; break | — | — | — | `assets/js/backend.src.js:8282` |
| `sitemap_check` | 504–541 (38) | muaf | `USER_LOGGED_IN`@511 → `validate_token`@518 | dolaylı db←update_sitemap_and_ping; dış←update_sitemap_and_ping | var | respond · respond; break | — | — | — | `assets/js/backend.src.js:8313` |
| `user_pinned_app_update` | 1205–1232 (28) | genel | `validate_user`@1209 → `validate_token`@1210 | db: user | var | output_error, echo+exit · exit; break | — | `$request` | — | çağıran bulunamadı |
| `update_dashboard_widgets` | 1328–1405 (78) | muaf | `USER_LOGGED_IN`@1335 → `validate_token`@1342 | db: dashboard | var | respond, echo+exit · exit; break | — | `$request` | — | `welcome.php:875` |
| `update_dashboard_appearance` | 1408–1469 (62) | genel | `validate_token`@1413 | db: dashboard | var | echo+exit · exit; break | — | `$request` | — | `welcome.php:899` |
| `tour_seen` | 4453–4462 (10) | muaf | `validate_user`@4455 → `validate_token`@4456 | dolaylı db←pg_tour_mark | var | respond · respond; break | — | `$request`; **`$GLOBALS['user']`** ← `pg_tour_mark()`→`pg_tour_ready()` (`includes/fn/tour.php:83,119`) | — | `assets/js/pg_tour.js:626` |
| `contact_quick_save` | 4466–4475 (10) | muaf | `validate_user`@4468 → `validate_token`@4469 | dolaylı db←pg_contact_quick_save; dosya←pg_contact_quick_save | var | respond · respond; break | — | `$request` | — | `includes/fn/output.php:1377` |

- `user_online_check`: Gate kaydı: oturumsuz/tokensız → "Invalid token." (token önce). Rol 3 → success.
- `user_pinned_app_update`: Genel kapıda (rol ≤ 1). Uygulama menüsü sabitleme rol 2/3 için reddedilir; istemci bulunamadı.
- `update_dashboard_appearance`: **Genel kapıda** (rol ≤ 1) — kardeşi `update_dashboard_widgets` muaf ("open to every backend role"). Kendi oturum kontrolü yok, genel kapıya dayanıyor.

### notifications — bildirim ve push → `includes/panel/notifications.php`

| Eylem | Satır (n) | Genel kapı | Kendi kapısı (sırayla) | Yazıyor mu | Token | Yanıt · son | Include | Dış kapsam | Ara break | İstemci |
|---|---|---|---|---|---|---|---|---|---|---|
| `check_unread_notifications` | 890–913 (24) | muaf | `validate_user`@891 | okuma | yok | echo+exit · exit; break | `includes/notifications.php`¹ | — | — | `assets/js/backend.src.js:2341` |
| `edit_notifications` | 915–945 (31) | muaf | `validate_token`@919 → `validate_user`@920 | dolaylı db←pg_notification_delete/pg_notification_mark_unread | var | echo+exit · exit; break | `includes/notifications.php`¹ | `$request` | — | `assets/js/backend.src.js:2617` |
| `get_notifications` | 947–998 (52) | muaf | `validate_token`@951 → `validate_user`@952 | dolaylı db←pg_notification_mark_read | var | echo+exit · exit; break | `includes/notifications.php`¹ | `$request` | — | `assets/js/backend.src.js:2458` |
| `remove_notifications` | 1000–1030 (31) | muaf | `validate_token`@1002 → `validate_user`@1003 | dolaylı db←pg_notification_delete/log_activity | var | echo+exit · exit; break | `includes/notifications.php`¹ | — | — | `assets/js/backend.src.js:2409` |
| `push_config` | 1032–1063 (32) | genel | `validate_token`@1033 → `validate_user`@1034 | dolaylı db←pg_push_vapid_public_key | var | echo+exit · exit; break | `includes/push.php`¹ | `$request` | — | `assets/js/backend.src.js:2021`, `:2279` (`pg_push_send`) |
| `push_subscribe` | 1065–1096 (32) | genel | `validate_token`@1066 → `validate_user`@1067 | dolaylı db←pg_push_subscription_delete/pg_push_subscription_save | var | echo+exit · exit; break | `includes/push.php`¹ | `$request` | — | `assets/js/backend.src.js:2247` (`pg_push_send`), `sw.js:234` |
| `push_unsubscribe` | 1098–1112 (15) | genel | `validate_token`@1099 → `validate_user`@1100 | dolaylı db←pg_push_subscription_delete | var | echo+exit · exit; break | `includes/push.php`¹ | `$request` | — | `assets/js/backend.src.js:2267` (`pg_push_send`) |
| `push_test` | 1114–1132 (19) | genel | `validate_token`@1115 → `validate_user`@1116 | dolaylı db←pg_push_notify_user; dış←pg_push_notify_user | var | echo+exit · exit; break | `includes/push.php`¹ | — | — | çağıran bulunamadı (`view_sessions.php:285` yalnız `?push_test=` sonucunu okuyor) |
| `push_pending` | 1134–1203 (70) | genel | `validate_user`@1139 | okuma | yok | echo+exit · exit; break | `includes/notifications.php`¹, `chat.php`¹, `includes/workspace/bootstrap.php`¹ | — | — | `sw.js:119` |

- `check_unread_notifications`: Token yok (okuma). Gate kaydı: oturumsuz → 302 giriş; rol 3 ve tokensız → success.
- `push_config`: push_* beşi de **genel kapıda** (rol ≤ 1): rol 2/3 kullanıcı için push abone/yapılandırma/bekleyen uçları "The User must have a Designer or Administrator role." döner.
- `push_pending`: `sw.js` (service worker) çağırır; genel kapıda olduğu için rol 2/3 cihazlarında boş bildirim. `chat.php` ve `includes/workspace/bootstrap.php` koşullu yüklenir.

### commerce — mağaza, sepet, barkod, teklif → `includes/panel/commerce.php`

| Eylem | Satır (n) | Genel kapı | Kendi kapısı (sırayla) | Yazıyor mu | Token | Yanıt · son | Include | Dış kapsam | Ara break | İstemci |
|---|---|---|---|---|---|---|---|---|---|---|
| `get_installment_options` | 2190–2373 (184) | muaf | yok | dış: Iyzipay API (vendored kütüphane; tarayıcı görmüyor) | yok | echo+exit · exit; break | `includes/iyzipay-php/IyzipayBootstrap.php`² | `$request` | — | `frontend.src.js:148` |
| `eo_get_installments` | 2402–2496 (95) | muaf | yok | dış: Iyzipay API | yok | echo+exit · exit; break | `includes/iyzipay-php/IyzipayBootstrap.php`¹ | `$request` | — | `includes/fn/widgets_express_order.php:2677`+2 |
| `eo_default_tree` | 2503–2510 (8) | muaf | yok | okuma | yok | echo+exit · exit; break | — | — | — | `assets/js/style_designer.js:26441`+1 |
| `eo_required_sections` | 2511–2543 (33) | muaf | yok | okuma | yok | echo+exit · exit; break | — | — | — | çağıran bulunamadı |
| `add_to_cart` | 2545–2554 (10) | muaf | yok | dolaylı db (sipariş/kalem), session (sepet), olası dış (gerçek zamanlı kargo ücreti) ← `add_to_cart()` | yok | echo+exit · exit; break | `add_to_cart.php`¹ | `$request` | — | `assets/js/style_designer.js:40554`+1, `includes/fn/widgets_catalog.php:3711`+3, `add_to_cart.src.js:55` |
| `delete_order` | 2556–2567 (12) | genel | `validate_token`@2558 | dolaylı db←delete_order; session←delete_order | var | echo+exit · exit; break | `delete_order.php`¹ | `$request` | — | çağıran bulunamadı |
| `get_common_regions` | 2569–2588 (20) | genel | yok | okuma | yok | echo+exit · exit; break | — | — | — | `migration.php:216` |
| `get_cross_sell_items` | 2590–2599 (10) | muaf | yok | okuma | yok | echo+exit · exit; break | `get_cross_sell_items.php`¹ | `$request` | — | `cross_sell.src.js:87` |
| `get_cross_sell_for_product` | 2615–2630 (16) | muaf | yok | okuma | yok | echo+exit · exit; break | — | `$request` | — | `assets/js/pg_civ_variants.js:318` |
| `get_delivery_date` | 2634–2643 (10) | muaf | yok | dolaylı db: `shipping_delivery_dates` önbelleği (DELETE/INSERT) + dış (USPS/FedEx) ← `get_delivery_date()` | yok | echo+exit · exit; break | `shipping.php`¹ | `$request` | — | `frontend.src.js:4463` |
| `get_product` | 3717–3928 (212) | muaf | yok | okuma | yok | output_error, echo+exit · exit; break | — | `$request` | — | `assets/js/pg_civ_variants.js:497`, `includes/fn/widgets_catalog.php:4293`, `frontend.src.js:3799` |
| `get_shipping_methods` | 3933–3942 (10) | muaf | yok | dolaylı db: `shipping_rates` önbelleği (DELETE/INSERT), `ship_tos` UPDATE + dış (kargo API) ← `get_shipping_realtime_rate()` | yok | echo+exit · exit; break | `shipping.php`¹ | `$request` | — | `includes/fn/widgets_express_order.php:3332`+2, `frontend.src.js:4371` |
| `update_order` | 4320–4331 (12) | genel | `validate_token`@4322 | dolaylı db←update_order; session←update_order; dış←update_order | var | echo+exit · exit; break | `update_order.php`¹ | `$request` | — | çağıran bulunamadı |
| `update_product_status` | 4343–4378 (36) | muaf | `validate_token`@4345 → `validate_user`@4347 → `ecommerce_access`@4348 | db: products; dolaylı db←validate_ecommerce_access/log_activity | var | echo+exit · exit; break | — | `$request` | — | çağıran bulunamadı |
| `update_product_group_status` | 4380–4402 (23) | muaf | `validate_token`@4382 → `validate_user`@4384 → `ecommerce_access`@4385 | dolaylı db←validate_ecommerce_access/update_product_group_status | var | echo+exit · exit; break | `update_product_group_status.php`¹ | `$request` | — | çağıran bulunamadı |
| `getproductlist` | 5301–5368 (68) | genel | yok | okuma | yok | `echo respond(...)` (respond zaten exit eder) · break | — | — | — | çağıran bulunamadı |
| `get_unselected_products` | 5370–5442 (73) | muaf | `validate_user`@5371 → `ecommerce_access`@5372 | okuma | yok | echo+exit · exit; break | — | `$request` | — | `add_product_group.php:319`, `edit_product_group.php:761` |
| `get_product_barcodes` | 5489–5506 (18) | genel | `validate_user`@5490 → `ecommerce_access`@5491 → `validate_token`@5492 | dolaylı db←validate_ecommerce_access | var | respond · respond; break | — | `$request` | — | `assets/js/backend.src.js:8017`+2 (`api()`) |
| `generate_product_barcode` | 5509–5548 (40) | genel | `validate_user`@5510 → `ecommerce_access`@5511 → `validate_token`@5512 | okuma (yalnız benzersiz kod üretir, kaydetmez) | var | respond · respond; break | — | `$request` | — | `assets/js/backend.src.js:8060` (`api()`) |
| `save_product_barcode` | 5551–5584 (34) | genel | `validate_user`@5552 → `ecommerce_access`@5553 → `validate_token`@5554 | db: product_barcodes; dolaylı db←validate_ecommerce_access/log_activity | var | respond · respond; break | — | `$request` | — | `assets/js/backend.src.js:8039` (`api()`) |
| `delete_product_barcode` | 5587–5600 (14) | genel | `validate_user`@5588 → `ecommerce_access`@5589 → `validate_token`@5590 | db: product_barcodes; dolaylı db←validate_ecommerce_access/log_activity | var | respond · respond; break | — | `$request` | — | `assets/js/backend.src.js:8146` (`api()`) |
| `bulk_assign_barcodes` | 5603–5666 (64) | genel | `validate_user`@5604 → `ecommerce_access`@5605 → `validate_token`@5606 | db: product_barcodes; dolaylı db←validate_ecommerce_access/log_activity | var | respond · respond; break | — | `$request` | — | çağıran bulunamadı (`view_folders.php:12754` toplu düzenleme `assign_barcodes` alanı `view_folder_and_files_f.php:4987` üzerinden gider) |
| `save_barcode_template` | 5669–5681 (13) | genel | `validate_user`@5670 → `ecommerce_access`@5671 → `validate_token`@5672 | db: config; dolaylı db←validate_ecommerce_access/log_activity | var | respond · respond; break | — | `$request` | — | `assets/js/backend.src.js:6261` |
| `offer_editor` | 5686–5691 (6) | muaf | case'te yok; handler içinde (`edit_offer_f.php:1878`) `validate_token` → `validate_user` → `role>=3 && !manage_ecommerce` → "Permission denied." | dolaylı db←pg_offer_editor_handle | var (handler içinde) | respond (handler her dalda respond eder, sonda "Invalid request.") · break | `edit_offer_f.php`¹ | `$request` | — | `assets/js/offer_editor.js:1021`, `includes/templates/view_offers.php:187` |

- `get_installment_options`: Herkese açık (ön yüz ödeme). İç `switch (ECOMMERCE_PAYMENT_GATEWAY)`. `require_once('includes/iyzipay-php/…')` **göreli** yol.
- `add_to_cart`: Herkese açık ön yüz ucu; **token yok, yazıyor** (sepet: tasarım gereği ziyaretçi ucu).
- `delete_order`: Genel kapı (rol ≤ 1) + token; `validate_user()` çağrılmıyor.
- `get_common_regions`: `migration.php` (LiveSite göç aracı) uzak API çağrısı; gövdede username/password.
- `get_delivery_date`: Herkese açık; token yok.
- `get_product`: Herkese açık (ön yüz). `or output_error()` sorgu hatasında HTML basar.
- `get_shipping_methods`: Herkese açık; token yok.
- `update_order`: Genel kapı + token; `validate_user()` yok. `update_order.php` Plan 3'ün dosyası (dokunma listesi).
- `update_product_status`: Muaf + kendi `validate_ecommerce_access` (red **HTML** `output_error`).
- `update_product_group_status`: Aynı; `update_product_group_status.php` workspace `changes.php:3022` tarafından da fonksiyon olarak yüklenir.
- `getproductlist`: `db("SELECT …")` sonucu `mysqli_fetch_*` ile geziliyor.
- `get_unselected_products`: Okuma; token yok. Red **HTML** (`validate_ecommerce_access`).
- `get_product_barcodes`: Barkod uçları (6) **genel kapıda**: rol ≤ 1 dışındaki ve `manage_ecommerce` bayraklı rol 2/3 kullanıcıları `validate_ecommerce_access`'e hiç ulaşmaz.

### content — sayfa/klasör/dosya/form/bölge okuma, menü, yükleme → `includes/panel/content.php`

| Eylem | Satır (n) | Genel kapı | Kendi kapısı (sırayla) | Yazıyor mu | Token | Yanıt · son | Include | Dış kapsam | Ara break | İstemci |
|---|---|---|---|---|---|---|---|---|---|---|
| `upload_file` | 1236–1326 (91) | muaf | `validate_token`@1237 → `validate_user`@1238 → `check_edit_access((int) $folder)`@1256 | db: files; dosya: fopen(w), fwrite; dolaylı db←log_activity | var | respond, output_error, echo+exit · exit; break | — | `$request` | — | çağıran bulunamadı |
| `get_dynamic_region` | 2784–2813 (30) | genel | yok | okuma | yok | echo+exit · exit; break | — | `$request` | — | `assets/js/page_designer.js:171` |
| `get_dynamic_regions` | 2815–2846 (32) | genel | yok | okuma | yok | echo+exit · exit; break | — | `$request` | — | `assets/js/page_designer.js:1647` |
| `get_file` | 2848–2891 (44) | genel | yok | okuma | yok | echo+exit · exit; break | — | `$request` | — | `assets/js/page_designer.js:151` |
| `get_folders` | 2893–2920 (28) | genel | yok | okuma | yok | echo+exit · exit; break | — | — | — | `migration.php:283` |
| `get_form` | 2922–2930 (9) | genel (yalnız oturum) | yok | okuma | yok | respond · respond; break | `forms.php`¹ | — | — | çağıran bulunamadı |
| `get_forms` | 2932–2940 (9) | genel (yalnız oturum) | yok | okuma | yok | respond · respond; break | `forms.php`¹ | — | — | çağıran bulunamadı |
| `get_page` | 3127–3178 (52) | genel | yok | okuma | yok | echo+exit · exit; break | — | `$request` | — | `assets/js/page_designer.js:1218` |
| `get_pages` | 3631–3715 (85) | genel | yok | okuma | yok | echo+exit · exit; break | — | — | — | `migration.php:316` |
| `create_dynamic_region` | 4087–4114 (28) | genel | `validate_token`@4088 → `validate_user`@4089 → `area:administrator`@4090 → `role<1`@4092 | db: dregion; dolaylı db←validate_area_access/log_activity | var | output_error, echo+exit · exit; break | — | `$request` | — | `assets/js/page_designer.js:865` |
| `update_dynamic_region` | 4153–4201 (49) | genel | `validate_token`@4154 → `validate_user`@4161 → `role!==0`@4163 | db: dregion | var | respond, echo+exit · exit; break | — | `$request` | — | `assets/js/page_designer.js:474` |
| `sort_menu_items` | 5444–5486 (43) | muaf | `validate_token`@5445 → `validate_user`@5447 → `role==3`@5460 | db: menu_items, menus; dolaylı db←log_activity | var | respond · respond; break | — | `$request` | — | çağıran bulunamadı |

- `upload_file`: Muaf; klasör erişimi `check_edit_access`. Token var, istemci bulunamadı.
- `get_folders`: Gate kaydı: oturumsuz → "Invalid login." (genel kapı, 200), rol 3 → "The User must have a Designer or Administrator role.", tokensız → success.
- `get_form`: Gate kaydı: rol 3 → "Access denied." (forms.php `check_access`). Eksik opsiyonel anahtarlar `forms.php` içinde PHP Warning üretir (sunucu logunda, gövdede değil).
- `get_page`: Gate kaydı genel kapıyla aynı (bkz. `get_folders`).
- `create_dynamic_region`: `validate_area_access(...,'administrator')` reddi **HTML**; ardından ikinci kez `role<1 && DYNAMIC_REGIONS`.
- `sort_menu_items`: Muaf; rol 3 için `get_items_user_can_edit('menus')`. İstemci bulunamadı.

### designer — eski sayfa tasarımcısı uçları + görsel tasarımcı → `includes/panel/designer.php`

| Eylem | Satır (n) | Genel kapı | Kendi kapısı (sırayla) | Yazıyor mu | Token | Yanıt · son | Include | Dış kapsam | Ara break | İstemci |
|---|---|---|---|---|---|---|---|---|---|---|
| `get_design_files` | 2645–2714 (70) | genel | yok | okuma | yok | echo+exit · exit; break | — | `$request` | — | `assets/js/page_designer.js:1619` |
| `get_designer_region` | 2716–2747 (32) | genel | yok | okuma | yok | echo+exit · exit; break | — | `$request` | — | `assets/js/page_designer.js:161` |
| `get_designer_regions` | 2749–2782 (34) | genel | yok | okuma | yok | echo+exit · exit; break | — | `$request` | — | `assets/js/page_designer.js:1634`, `migration.php:249` |
| `get_items_in_style` | 2942–3085 (144) | genel | yok | okuma | yok | echo+exit · exit; break | — | `$request` | — | `assets/js/page_designer.js:1380` |
| `get_layout` | 3087–3125 (39) | genel | yok | okuma | yok | echo+exit · exit; break | `generate_layout_content.php`¹ | `$request` | — | `assets/js/page_designer.js:140` |
| `get_style` | 3944–3981 (38) | genel | yok | okuma | yok | echo+exit · exit; break | — | `$request` | — | `assets/js/page_designer.js:130` |
| `get_styles` | 3983–4051 (69) | genel | yok | okuma | yok | echo+exit · exit; break | — | `$request` | — | `assets/js/page_designer.js:1605`, `migration.php:182` |
| `create_design_region` | 4060–4086 (27) | genel | `validate_token`@4061 → `validate_user`@4062 → `role<1`@4064 | db: cregion; dolaylı db←log_activity | var | output_error, echo+exit · exit; break | — | `$request` | — | `assets/js/page_designer.js:819` |
| `update_designer_region` | 4115–4151 (37) | genel | `validate_token`@4116 | db: cregion | var | echo+exit · exit; break | — | `$request` | — | `assets/js/page_designer.js:462` |
| `update_file` | 4203–4242 (40) | genel | `validate_token`@4204 | db: files; dosya: unlink, file_put_contents | var | echo+exit · exit; break | — | `$request` | — | `assets/js/page_designer.js:450` |
| `update_layout` | 4244–4316 (73) | genel | `validate_token`@4245 → `pg_hosted()`@4249 | db: page; dosya: unlink, file_put_contents; dolaylı db←log_activity | var | echo+exit · exit; break | `generate_layout_content.php`¹ | `$request` | — | `assets/js/page_designer.js:437` |
| `update_page_designer_properties` | 4333–4341 (9) | genel | `validate_token`@4335 | session | var | respond · respond; break | — | `$request` | — | `assets/js/page_designer.js:1817` |
| `update_style` | 4404–4438 (35) | genel | `validate_token`@4405 | db: style | var | echo+exit · exit; break | — | `$request` | — | `assets/js/page_designer.js:425` |
| `update_toolbar_properties` | 4440–4447 (8) | genel | `validate_token`@4441 | session | var | respond · respond; break | — | `$request` | — | `frontend.src.js:388` |
| `designer` | 5696–6444 (749) | muaf | `validate_token`@5697 → `validate_user`@5698 → `pg_designer_is_full($user)` değilse: `pg_designer_access()`≠NONE **ve** alt eylem 11'lik içerik listesinde, yoksa "Permission denied."@5721 → alt eylemlerde `pg_designer_page_access()`@5840, `role==3`@6020/6032/6316 | db: page, style, recycle_bin, system_style_cells, preview_styles, files; dosya: file_put_contents; dolaylı db←pg_design_ai_panel/pg_collab_beat/pg_collab_claim_page; dış←pg_design_ai_panel/pg_designer_widget_ghosts/pg_designer_preview_widgets; session←save_system_style/pg_explorer_handle/pg_designer_widget_ghosts; dosya←pg_explorer_handle/pg_design_template_prepare/pg_recycle_park_name | var | respond, pg_explorer_handle · `}` break | `includes/designer_access.php`¹, `includes/designer_collab.php`¹, `includes/designer_ai.php`¹, `view_folder_and_files_f.php`¹, `includes/designer_import.php`¹, `includes/designer_import.php`¹, `seo.php`¹, `seo_structure.php`¹, `includes/designer_screen.php`¹, `view_folder_and_files_f.php`¹ | `$request`; `page_delete`/`design_delete` → `pg_explorer_handle()` zinciri `check_folder_access_in_array()` **`global $user`** okur | — | `assets/js/style_designer.js:45490`+2, `includes/designer_screen.php:857`+1 |
| `designer_file` | 7085–7304 (220) | muaf | `validate_token`@7086 → `validate_user`@7087 → `role>1` → "Permission denied."@7091 (sonraki `role===3` dalları @7147/@7258 bu yüzden erişilemez görünüyor) | db: files; dosya: fopen(w), fwrite, unlink, file_put_contents; dolaylı db←log_activity | var | respond · `}` break | — | `$request`; `list_folders` → `select_folder(0)` **`global $user`** okur (`includes/fn/auth.php:2605`) | — | `assets/js/style_designer.js:36139`+5 |

- `update_designer_region`: Yazıyor; yalnız genel kapı + token (`validate_user` yok).
- `update_file`: Yazıyor (dosyayı diskte değiştirir); yalnız genel kapı + token.
- `update_layout`: Yazıyor (db + `FILE_DIRECTORY_PATH` altına dosya); genel kapı + token + `pg_hosted()`.
- `update_style`: Yazıyor; yalnız genel kapı + token.
- `update_toolbar_properties`: Ön yüz araç çubuğu (`frontend.src.js:388`) çağırır ama **genel kapıda** (rol ≤ 1): rol 2/3 editörün aç/kapa tercihi kaydedilmez.
- `designer`: 25 alt eylem (`switch ($sub)` + `default` "Unknown action."); `ai_*` 6 alt eylem switch'ten önce `respond(pg_design_ai_panel())`. Include'ların hepsi `dirname(__FILE__)`.
- `designer_file`: 5 alt eylem + `default` "Unknown sub_action.".

### explorer — dosya yöneticisi → `includes/panel/explorer.php`

| Eylem | Satır (n) | Genel kapı | Kendi kapısı (sırayla) | Yazıyor mu | Token | Yanıt · son | Include | Dış kapsam | Ara break | İstemci |
|---|---|---|---|---|---|---|---|---|---|---|
| `file_explorer` | 4478–5299 (822) | muaf | `validate_user`@4479 → `validate_token`@4480 → `type` `explorer_catalog_*` ise `role>2 && !manage_ecommerce` → "Access denied" (JSON), değilse `validate_area_access($user,'user')` (HTML red) → `check_view_access($folder_id)`@4527 → `role==3` ise ACL klasörleri | db: files, system_theme_css_rules, preview_styles; dosya: unlink; session; dolaylı db←log_activity/validate_area_access/pg_explorer_handle; dosya←pg_explorer_handle; session←pg_explorer_handle | var | respond, echo+exit, `pg_explorer_handle` (respond), output_error; **iç `switch ($request['type'])`'te `default` yok → bilinmeyen `type` = HTTP 200 boş gövde**; `get_tables` echo + break (exit yok) · `}` break | `view_folder_and_files_f.php`¹ | `$request`; **case içinde tanımlı `get_folder_breadcrumb()`@4704 ve `get_folder_table()`@4750 `global $user; global $folders_that_user_has_access_to;` okur** (ikisi de case gövdesinde atanan değişkenler); `pg_explorer_handle()` → `check_folder_access_in_array()` `global $user` | — | `view_folders.php:2253` |

- `file_explorer`: 69 iç etiket: 65 `explorer_*` (hepsi `view_folder_and_files_f.php` `pg_explorer_handle()`), eski 4 tür `delete_file`, `get_folder_id`, `get_breadcrumb`, `get_tables` — bu dördünün istemcisi bulunamadı. Case içinde 4 adlı fonksiyon tanımı (`get_folder_breadcrumb`, `get_folder_table`, onun içinde `get_access_control_icon_classes`, `get_file_icon`).

### shared_component → `includes/panel/shared_component.php`

| Eylem | Satır (n) | Genel kapı | Kendi kapısı (sırayla) | Yazıyor mu | Token | Yanıt · son | Include | Dış kapsam | Ara break | İstemci |
|---|---|---|---|---|---|---|---|---|---|---|
| `shared_component` | 6446–7078 (633) | muaf | `validate_token`@6447 → `validate_user`@6448 → `role>1` ise yalnız 9 okuma alt eylemi (`list`, `usage_all`, `prefetch`, `get`, `list_custom_forms`, `list_form_item_view_pages`, `list_product_groups`, `list_calendars`, `list_calendar_event_pages`) → tablo yoksa hata | db: shared_components; dolaylı db←log_activity | var | respond · `}` break | — | `$request` | — | `assets/js/style_designer.js:681`+26 |

- `shared_component`: 21 alt eylem + `default` "Invalid action."; `_sc_unique_name()` (api.php sonu) yalnız burada kullanılır.

### search → `includes/panel/search.php`

| Eylem | Satır (n) | Genel kapı | Kendi kapısı (sırayla) | Yazıyor mu | Token | Yanıt · son | Include | Dış kapsam | Ara break | İstemci |
|---|---|---|---|---|---|---|---|---|---|---|
| `backend_search` | 3180–3629 (450) | muaf | `validate_user`@3181 → sonuç kümeleri rol/bayrakla süzülür: `role<=1` tasarım, `role<=2` yönetim, `manage_ecommerce`/`manage_forms`/`manage_contacts` | okuma | yok | echo+exit · exit; break | `includes/settings/registry.php`³ | `$request` | — | `assets/js/backend.src.js:1625`+1 |

- `backend_search`: Token yok (okuma). Gate kaydı: rol 3 → success (10 eylem kısayolu), tokensız → success. `define('PG_SETTINGS_MENU')` sonra `includes/settings/registry.php` (kapısı bu sabit).

### test → `includes/panel/test.php`

| Eylem | Satır (n) | Genel kapı | Kendi kapısı (sırayla) | Yazıyor mu | Token | Yanıt · son | Include | Dış kapsam | Ara break | İstemci |
|---|---|---|---|---|---|---|---|---|---|---|
| `test` | 4053–4059 (7) | genel | yok | okuma | yok | echo+exit · exit; break | — | — | — | `migration.php:159` |

- `test`: `migration.php:159` uzak siteye bağlantı testi olarak çağırır (gövdede username/password).

### default (switch sonu) → `api.php`'de kalır

| Eylem | Satır (n) | Genel kapı | Kendi kapısı (sırayla) | Yazıyor mu | Token | Yanıt · son | Include | Dış kapsam | Ara break | İstemci |
|---|---|---|---|---|---|---|---|---|---|---|
| `default` | 7306–7315 (10) | — | yok | okuma | yok | echo+exit · exit; break | — | — | — | — |

- `default`: "Invalid action." — tabloya geçişte kalan `switch`'in sonu olarak durmalı.

## Include edilen dosyalar (madde 7)

Tokenizer ile her dosyanın fonksiyon/sınıf gövdesi dışındaki (üst düzey) kodu çıkarıldı; ayrıca dosyadaki
fonksiyonlarda `global` / `$GLOBALS` arandı.

| Dosya | Yükleyen case(ler) | Üst düzey kod | Kapsam değişkeni (`$request`, `$user`, `$action`, `$token`, `$response`…) | `global` |
|---|---|---|---|---|
| `add_to_cart.php` | add_to_cart | yalnız `if (!defined('PG_FUNCTIONS_DIR')) exit;` | okumuyor/yazmıyor | yok |
| `delete_order.php` | delete_order | yok (yalnız fonksiyon) | — | yok |
| `update_order.php` | update_order | yok | — | yok |
| `update_product_group_status.php` | update_product_group_status | yok | — | yok |
| `shipping.php` | get_delivery_date, get_shipping_methods | yok | — | yok |
| `forms.php` | get_form, get_forms | yok | — | yok |
| `get_cross_sell_items.php` | get_cross_sell_items | yok | — | yok |
| `generate_layout_content.php` | get_layout, update_layout | yok | — | yok |
| `software_update_check.php` | software_update_check (`require`, once değil) | yok | — | yok |
| `view_folder_and_files_f.php` | file_explorer, designer (page_delete, design_delete) | yok | — (`$folders_that_user_has_access_to` parametreyle geçiyor) | yok — ama çağırdığı `check_folder_access_in_array()`, `get_page_options()` vb. `global $user` okur (bkz. bulgu 1) |
| `edit_offer_f.php` | offer_editor | `if (!defined('PG_OFFER_OPEN_END_DATE')) define(...)` | — | yok (handler `validate_token()` → `global $token`) |
| `seo.php`, `seo_structure.php` | designer (`seo_check`) | yok | — | yok |
| `includes/designer_access.php` | designer | kapı + `define` (PG_DESIGNER_ACCESS_*) | — | yok |
| `includes/designer_collab.php` | designer (presence…) | kapı + `define` (PG_COLLAB_*) | — | yok |
| `includes/designer_ai.php` | designer (ai_*) | kapı + `require_once PG_FUNCTIONS_DIR` (designer_import, designer_access) | — | yok |
| `includes/designer_import.php` | designer (import_*) | kapı | — | yok |
| `includes/designer_screen.php` | designer (`theme_*`) | kapı | — | yok |
| `includes/push.php` | push_* | kapı | — | yok |
| `includes/notifications.php` | bildirim uçları, push_pending | kapı | — | yok |
| `includes/settings/registry.php` | backend_search | kapı `!defined('PG_SETTINGS_ENTRY') && !defined('PG_SETTINGS_MENU')` → case `define('PG_SETTINGS_MENU', true)`'yu include'dan **önce** yapıyor; taşınırken sıra korunmalı | — | yok |
| `chat.php` | push_pending | yok | — | yok |
| `includes/workspace/bootstrap.php` | push_pending | kapı + 41 `require_once PG_FUNCTIONS_DIR/...` (alt dosyalarda da üst düzey kod yok) | — | yok |
| `mysqldump.php` | software_backup | `namespace Ifsnop\Mysqldump; use ...;` + sınıf tanımları | — | yok |
| `includes/iyzipay-php/IyzipayBootstrap.php` | get_installment_options (göreli), eo_get_installments | yok (vendored) | — | — |

**Sonuç:** listelenen dosyaların hiçbiri üst düzeyde `$request`/`$user`/`$action`/`$token`/`$response` okumuyor ya da
yazmıyor; fonksiyon içinden include edilmeleri kendi başına anlam değiştirmez. Risk dosyalarda değil, çağırdıkları
`global $user` okuyan yardımcılarda (bulgu 1) ve `dirname(__FILE__)` yollarında (bulgu 2).

## `api.php` sonundaki fonksiyonlar

`dbcea85`'te `api.php:7318-7620`. `ccef814` ile ilk altısı `includes/dashboard/widgets.php`'ye taşındı; `api.php`'de
`respond` (7318), `_sc_unique_name` (7326), `validate_token` (7340) kaldı. "Kullanan" sütunu `ccef814` çalışma ağacından.

| Fonksiyon | `dbcea85` satırı | Kullanan dosyalar |
|---|---|---|
| `pg_activity_daily` | 7329 | `includes/dashboard/widgets/widget_8.php`, `widget_10.php`, `widget_13.php` |
| `pg_widget_headline` | 7371 | `widget_8.php`, `widget_10.php`, `widget_13.php` |
| `pg_widget_row` | 7452 | widget_1, 3, 6, 7, 8, 9, 10, 11, 12, 13, 15, 16, 17 |
| `pg_strip_anchor_tags` | 7515 | yalnız `pg_widget_row()` (aynı dosya) |
| `pg_readable_ink` | 7524 | yalnız `pg_widget_row()` (aynı dosya) |
| `pg_widget_row_heading` | 7553 | `widget_6.php`, `widget_8.php`, `widget_15.php` |
| `respond` | 7560 | `api.php` (201 çağrı), `view_folder_and_files_f.php` (252), `edit_offer_f.php` (16), `includes/dashboard/widgets.php` (1). **Aynı adla ayrıca tanımlı:** `barcode_increase_inventory.php`, `barcode_decrease_inventory.php` (kendi uçları; ortak bir include'a taşınırsa bu iki dosyayla çakışır) |
| `_sc_unique_name` | 7568 | yalnız `api.php` `shared_component` (2 çağrı) |
| `validate_token` | 7582 | `api.php` (56), `edit_offer_f.php` (`pg_offer_editor_handle`, 1). **Aynı adla ayrıca tanımlı:** `barcode_increase_inventory.php`, `barcode_decrease_inventory.php`. `global $token` okur |

Komut: `grep -rn --include=*.php --include=*.js -E "(^|[^a-zA-Z0-9_>:\$])<ad>\(" pinegrap | grep -v "function <ad>("`.

## Genel kapı muafiyet listesi (`api.php:155-251`)

38 tam ad + 3 önek kuralı = **41** madde (görevde 40 dendi; fark: `get_widget_data` ya da önek kuralları sayılmamış olabilir).
Her tam adın bir case'i var; case'i olmayan muaf ad yok.

| # | Muaf | Case | # | Muaf | Case |
|---|---|---|---|---|---|
| 1 | add_to_cart | 2545 | 22 | get_widget_data | 880 (kapsam dışı) |
| 2 | get_product | 3717 | 23 | file_explorer | 4478 |
| 3 | get_installment_options | 2190 | 24 | tour_seen | 4453 |
| 4 | eo_get_installments | 2402 | 25 | contact_quick_save | 4466 |
| 5 | eo_default_tree | 2503 | 26 | backend_search | 3180 |
| 6 | eo_required_sections | 2511 | 27 | sort_menu_items | 5444 |
| 7 | get_shipping_methods | 3933 | 28 | software_update_check | 1717 |
| 8 | get_delivery_date | 2634 | 29 | user_online_check | 288 |
| 9 | get_cross_sell_items | 2590 | 30 | `chat_*` (önek) | 11 case, 312–340 |
| 10 | get_cross_sell_for_product | 2615 | 31 | `site_chat_*` (önek) | 6 case, 491–502 |
| 11 | update_product_status | 4343 | 32 | `ws_*` (önek) | 128 case, 342–489 |
| 12 | update_product_group_status | 4380 | 33 | sitemap_check | 504 |
| 13 | get_unselected_products | 5370 | 34 | shared_component | 6446 |
| 14 | upload_file | 1236 | 35 | designer_file | 7085 |
| 15 | update_dashboard_widgets | 1328 | 36 | designer | 5696 |
| 16 | software_backup | 1471 | 37 | offer_editor | 5686 |
| 17 | software_update | 1733 | 38 | database_deep_check | 543 |
| 18 | remove_notifications | 1000 | 39 | server_config_repair | 620 |
| 19 | get_notifications | 947 | 40 | write_permissions_repair | 734 |
| 20 | check_unread_notifications | 890 | 41 | purge_cache | 781 |
| 21 | edit_notifications | 915 | | | |

Genel kapıda kalan 44 kapsam içi case (muaf 37 + genel 44 = 81, artı `default`): ca_bundle_config_repair, ca_bundle_update, push_config, push_subscribe,
push_unsubscribe, push_test, push_pending, user_pinned_app_update, update_dashboard_appearance, delete_order,
get_common_regions, get_design_files, get_designer_region, get_designer_regions, get_dynamic_region, get_dynamic_regions,
get_file, get_folders, get_form¹, get_forms¹, get_items_in_style, get_layout, get_page, get_pages, get_style, get_styles,
test, create_design_region, create_dynamic_region, update_designer_region, update_dynamic_region, update_file,
update_layout, update_order, update_page_designer_properties, update_style, update_toolbar_properties, getproductlist,
get_product_barcodes, generate_product_barcode, save_product_barcode, delete_product_barcode, bulk_assign_barcodes,
save_barcode_template (¹ yalnız oturum). Önek kuralları ileride aynı önekle eklenecek her eylemi de muaf yapar.

## Sandbox kapı kaydı (yazmayan temsilci eylemler)

`/tmp/golden/before/gate_<eylem>.json` (ve aynı içerik `after/`'ta). Dört durum: admin (oturum+token),
oturumsuz (cookie yok), rol 3 (`golden_role3`, oturum+token), tokensız (admin oturumu, `token` alanı yok).
Tekrar: `php /tmp/golden/record.php after` (tam koşu, gate dosyalarını da üretir) ya da `--actions-only`.
İki ardışık koşu fark 0.

| Eylem | admin | oturumsuz | rol 3 | tokensız |
|---|---|---|---|---|
| `get_page` | 200 success | 200 error "Invalid login." | 200 error "The User must have a Designer or Administrator role." | 200 success |
| `get_folders` | 200 success | 200 error "Invalid login." | 200 error "The User must have a Designer or Administrator role." | 200 success |
| `backend_search` | 200 success | 302 → `index.php?send_to=…` boş gövde | 200 success | 200 success |
| `software_update_check` | 200 boş gövde | 200 error "Invalid token." | 200 boş gövde | 200 error "Invalid token." |
| `check_unread_notifications` | 200 success "Check Success" | 302 → `index.php?send_to=…` boş gövde | 200 success "Check Success" | 200 success "Check Success" |
| `get_form` | 200 success | 200 error "Invalid login." | 200 error "Access denied." | 200 success |
| `test` | 200 success | 200 error "Invalid login." | 200 error "The User must have a Designer or Administrator role." | 200 success |
| `user_online_check` | 200 success "Online status updated." | 200 error "Invalid token." | 200 success "Online status updated." | 200 error "Invalid token." |

Okuma: oturumsuz yanıtın biçimi kapının **sırasına** bağlı — genel kapıdakiler JSON "Invalid login.", önce `validate_token()`
çağıranlar JSON "Invalid token.", önce `validate_user()` çağıranlar 302. Tabloya geçerken bu sıra korunmazsa gate dosyaları değişir.

## Grup önerisi üzerine notlar

- `get_common_regions` (yalnız `migration.php` çağırıyor; ortak bölge = `cregion` listesi) görevde `commerce`'te;
  ticaretle ilgisi yok, `content` daha doğal. Tabloda görev listesine uydum.
- `get_dynamic_region(s)`, `create_dynamic_region`, `update_dynamic_region` görevde `content`'te; `get_designer_region(s)`,
  `create_design_region`, `update_designer_region` `designer`'da. İkisi de aynı eski sayfa tasarımcısının (`assets/js/page_designer.js`)
  uçları; aynı dosyada durmaları daha tutarlı olabilir (öneri: hepsi `designer`). Tabloda görev listesine uydum.
- `get_file` (`page_designer.js:151`) ve `update_file` (`page_designer.js:450`) aynı istemcinin okuma/yazma ikilisi ama biri
  `content`, biri `designer`'da. Aynı gerekçe.
- `get_pages`, `get_folders`, `get_styles`, `get_designer_regions`, `get_common_regions`, `test` `migration.php` (LiveSite göçü)
  tarafından uzak siteye username/password ile çağrılıyor; bunlar dış sözleşme sayılır, adları ve yanıt biçimleri değişmemeli.
- `sitemap_check` ve `user_online_check` her panel sayfasının arka plan işleri; `dashboard` yerine `system`'e de uyar.
- `offer_editor` zaten tek satırla `edit_offer_f.php`'ye devrediyor (kapsam dışı ws_/chat_ gibi); taşımaya gerek yok, tabloya satır olarak girebilir.

## Sorular

1. `push_*` (5), `user_pinned_app_update`, `update_dashboard_appearance`, `update_toolbar_properties` ve barkod uçları (6)
   genel kapıda (rol ≤ 1). Kardeş uçlar "her rol" diye muaf. Bilinçli mi, yoksa muaf listesine eklenmeyi mi unuttu? (Taşımada bugünkü davranış korunacak; soru ayrı iş için.)
2. `global $user` bağımlılığı için dağıtıcıda `$GLOBALS['user']` atamak kabul mü, yoksa `check_folder_access_in_array()`,
   `select_folder()`, `pg_tour_ready()` ve short-link seçenek fonksiyonlarına `$user` parametresi mi eklenmeli? (İkincisi `includes/fn/auth.php`'ye dokunur — Plan 5'in dosyası.)
3. `file_explorer`'ın iç fonksiyonları (`get_folder_table` vb.) ve dört eski `type`'ı istemcisiz görünüyor. Aynen taşınsın mı (öneri: evet, aynen)?
4. `software_update` `$liveform` (1778) bugünkü bir hata; taşıma PR'ında dokunulmasın mı?
