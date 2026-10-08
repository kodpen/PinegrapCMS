# Pinegrap CMS: refactor ve altyapı adayları (kanıtlı)

Depo durumu: `development` @ `0294c42`. Kod tabanında hiçbir dosya değiştirilmedi; ölçümler grep/diff/python/node ile `/tmp` altında yapıldı.
Okunan kurallar: `CLAUDE.md`, `pinegrap-verilmis-kararlar`, `pinegrap-veritabani`, `pinegrap-sema-adimi`, `pinegrap-yeni-dosya`, `pinegrap-js-varliklari`, `pinegrap-erp`, `pinegrap-guvenlik` (ilgili bölümler).
Çalışan örnek (`setup_sandbox.sh`) kurulmadı. Çalışma anı ölçümü gerektiren maddeler "doğrulanamadı" diye işaretli.
Mevcut denetimlerin hepsi şu an temiz: `lint` (24 sn), `check_lang`, `check_bindings`, `check_api_schema`, ayrıca 3 büyük JS dosyasında `node --check`.

İş yükü: K = küçük, O = orta, B = büyük.

---

## A. Devasa dosyalar ve dağıtım noktaları

### 1. `api.php` içinde `get_widget_data`: dosyanın yarısı tek bir case
- **Kanıt:** `api.php` 15.027 satır. `case 'get_widget_data'` `api.php:844`'te başlıyor, bir sonraki case `api.php:8303`'te. Yani **7.459 satır, dosyanın %49,6'sı**. İçinde 27 pano widget'ı var (`clock`, `'1'`–`'26'`). En büyükleri: `'2'` 1.236 satır, `'7'` 813, `'5'` 688, `'1'` 568, `'23'` 405. Bu case'te `validate_user()` var, token kontrolü yok (salt okuma).
- **Neden sorun:** Tek bir widget'a dokunmak bile 15 bin satırlık dosyada diff, çakışma ve FTP yükleme riski demek. `functions.php` aynı gerekçeyle 2026-09-12'de bölünmüştü (`functions.php` başındaki yorum: "A 3 MB file could not be opened in an FTP editor…").
- **Önerilen yön:** Her widget kendi dosyasına: `includes/dashboard/widgets/<id>.php`, `pg_dashboard_widget_<id>($request)` imzasıyla. Case gövdesi bir arama tablosuna iner. Widget id'leri değişmez (22/24 emekli kuralı aynen kalır).
- **İş yükü:** O (mekanik taşıma, 27 parça). **Risk:** Düşük-orta. Widget'lar case içinde ortak yerel değişken (`$output_rows`, `$user`) kullanıyor, her parça kendi yerelini almalı.

### 2. Panel `api.php` dağıtıcısı: modül deseni dosyanın içinde zaten var
- **Kanıt:** Üst düzeyde 227 case (`switch ($action)` `api.php:275`). Alanlara göre: Çalışma Alanı `ws_` 128, tasarımcı/sayfa/bölge 24, e-ticaret (sipariş/ürün/ödeme/barkod) 23, panel sohbet 12, sistem/bakım/güncelleme/yedek 10, bildirim/push 9, pano/kabuk 7, site sohbeti 6, dosya yöneticisi 5, form 2, `test` 1.
  - 146 case (`ws_`, `chat_`, `site_chat_`) yalnız etiket. Gövdeleri tek satırla modüle devrediliyor: `require_once includes/workspace/actions.php` → `ws_handle_action($action,$request)` (`api.php:331–471`) ve `chat.php`.
  - Kalan ~81 case satır içinde: en büyükleri `file_explorer` 823, `designer` 750, `shared_component` 639, `software_update` 457, `backend_search` 451, `software_backup` 246, `designer_file` 231 satır.
  - Dış API ise rota tablosuyla çalışıyor (`includes/api/router.php` → `api_route_match()`, `api_schema()`, `includes/api/resources/*.php` altında 16 kaynak dosyası). ERP aynı kapıdan geçiyor (`includes/erp/api.php` 845 satır + `api_resources.php`).
- **Neden sorun:** Yeni bir panel ucu yine bu dosyaya giriyor. Kapı listesi (bkz. madde 3) ile case gövdesi 100–13.000 satır arayla duruyor.
- **Önerilen yön:** Dış API'deki rota tablosuna geçmek gerekmiyor, `ws_` deseni yeter. `pg_panel_actions()` her ön ek veya ad için `{file, handler, gate: 'role<=1' | 'session+token' | 'public', token: bool}` döndürür. `api.php` yalnız gövdeyi okur, kapıyı tablodan uygular ve handler'ı çağırır. Önce `designer*`, `file_explorer`, `software_*` taşınır.
- **İş yükü:** B (kademeli yapılabilir). **Risk:** Orta. `respond()`/`exit()` akışı ile `global $token` (`validate_token()`, `api.php:14990`) korunmalı.

### 3. Kapı/CSRF denetimi dağınık: bir negatif liste ve case başına tekrar
- **Kanıt:**
  - Genel kapı (`api.php:148–262`) **40 eylemi ve önek kuralını** "rol ≤ 1" kontrolünden muaf tutan bir `and ($action != '…')` zinciri. Muaf case'ler kendi denetimini yazıyor. Dosyada `validate_token()` 45, `validate_user()` 42 kez ve 6 ayrı `'Invalid login.'` bloğu geçiyor.
  - 85 case grubunun 44'ünde token kontrolü var, 41'inde yok. Yokların çoğu okuma ya da kendi modülünde kontrol ediyor.
  - Token kontrolü olmadan **yazan** uçlar: `server_config_repair` (`api.php:604`, rol 0, `.htaccess`/`web.config` yazar), `write_permissions_repair`, `ca_bundle_config_repair`, `ca_bundle_update` (rol 0), `purge_cache` (rol ≤ 2), `update_dashboard_appearance` (`api.php:8821`, `UPDATE dashboard`).
- **Neden sorun:** Kural bir yerde tanımlı değil. Her yeni uç doğru kombinasyonu elle seçiyor.
- **Ayrıca güvenlik incelemesi önerilir (doğrulanamadı):** `api.php` gövdeyi Content-Type'a bakmadan `json_decode(php://input)` ile okuyor (`api.php:68–70`). `pg_session_cookie_allow_cross_site()` HTTPS'te oturum çerezini `SameSite=None` olarak yeniden basıyor (`includes/fn/auth.php:1222–1257`, ödeme ekranları). Bu ikisi birlikte, tokensız uçlara siteler arası `text/plain` POST yolu açıyor olabilir. Canlıda denenmedi.
- **Önerilen yön:** Madde 2'deki tablonun `gate`/`token` alanları kuralı tek yere toplar. Kısa vadede yazan uçlara `validate_token()` eklenip eklenmeyeceğine güvenlik tarafı karar vermeli.
- **İş yükü:** K (token ekleme) / O (tablo). **Risk:** Düşük. İstemci tarafı token zaten gönderiyorsa davranış değişmez. Gönderip göndermediği doğrulanamadı.

### 4. Her istekte gereksiz kod yükleniyor
- **Kanıt:**
  - `api.php:273` `include_once('mysqldump.php')` (2.347 satır, 72 KB) **her panel AJAX isteğinde** çalışıyor. Sınıf yalnız `software_backup` içinde kullanılıyor ve orası (`api.php:8945`) zaten kendi `include_once`'ını yapıyor.
  - `functions.php` 31 modülü (`includes/fn/*.php`) **koşulsuz** yüklüyor: 81.178 satır, 3,93 MB, ön yüz istekleri dahil. CLI'da OPcache yokken ölçüm: yükleme 78 ms, 12 MB bellek. En büyük modüller: `ecommerce` 7.727, `designer` 6.834, `widgets_catalog` 5.943, `widgets` 5.777, `output` 4.924 satır.
- **Neden sorun:** OPcache derlemeyi emer ama bellek ve bağımlılık genişliği kalır. OPcache kapalı veya yetersiz hostta her istekte ödenir.
- **Önerilen yön:** (a) `api.php:273` satırı kaldırılır, dump sınıfı yalnız kullanıldığı yerde yüklenir (K). (b) Nadiren kullanılan modüller (`parasut`, `signature`, `system_status`, `update`, `design_themes`, `tour`) isteğe bağlı yüklemeye alınır (O, düşük öncelik).
- **İş yükü:** K / O. **Risk:** (a) düşük. (b) orta: modüller birbirini her sırada çağırabiliyor (`pinegrap-yeni-dosya`). Üretimdeki gerçek kazanç doğrulanamadı (OPcache durumuna bağlı).

### 5. Büyük JS dosyaları ham servis ediliyor; minify elle yapılıyor
- **Kanıt:**

  | Dosya | Satır | Ham | gzip -9 | Yorum + boşluk atılmış | Atılmış + gzip |
  |---|---|---|---|---|---|
  | `style_designer.js` | 49.669 | 3,08 MB | 651 KB | 1,81 MB (−41%) | 378 KB |
  | `workspace.js` | 17.869 | 736 KB | 149 KB | 483 KB | 116 KB |
  | `backend.src.js` | 11.039 | 469 KB | 101 KB | 270 KB | 57 KB |

  - Üçü de doğrudan `<script src>` ile yükleniyor: `includes/designer_screen.php:502`, `includes/workspace/screen.php:685`, `includes/fn/output.php:251`. Önbellek kırma `?v=filemtime` ile var.
  - `.min` ikizi olan dosyalar (`chat_backend`, `frontend`, `add_to_cart`, `dropzone`) elle terser'dan geçiriliyor. Bayraklar yalnız değişiklik günlüğünde yazılı (`docs/degisiklikler.md:12368`: `--compress --mangle --format ascii_only=true`). `tools/` altında bir derleme betiği yok.
  - `style_designer.js` tek bir IIFE (`:17` → `:49669`), içinde 779 iç fonksiyon, 64 bölüm başlığı ve 5.533 `_sdT('…')` çağrısı var.
  - Yazılımın ürettiği sunucu kurallarında sıkıştırma (deflate/gzip/Expires) yok (`includes/server_config.php`'de bloklar bunları içermiyor). Barındırıcının sıkıştırma yapıp yapmadığı doğrulanamadı.
- **Kısıt:** `pg_designer_i18n_keys()` (`includes/fn/designer.php:1936–1966`) `_sdT` anahtarlarını **servis edilen** `style_designer.js`'i tarayarak çıkarıyor. Minify edilmiş bir dosya servis edilirse tarayıcı kaynak dosyayı okumaya devam etmeli. `.src`/`.min` ayrımı bu yüzden şart.
- **Önerilen yön:** Tek komutlu `tools/build_js.sh`: terser bayrakları burada sabitlenir, ikizler buradan üretilir, CI da ikizin kaynakla güncel olup olmadığını denetler. `style_designer.js` önce `.src.js`/`.min.js` ikizine geçer. Bölme (bölümlere ayırıp ardışık birleştirme) ayrı ve sonraki iş olur.
- **İş yükü:** O. **Risk:** Orta. `_plan_tuval_animasyon.md` (açık sürüm 2026.4.8) aynı dosyaya dokunuyor, sıralama koordine edilmeli. Mangle'ın `_sdT` literal kuralını bozmaması için tarayıcı kaynak dosyada kalmalı.

---

## B. Tekrarlayan kalıplar

### 6. `add_*`/`edit_*` çiftleri: aynı formun iki kopyası
- **Kanıt:** Kökte 51 çift var. Ölçüm şöyle yapıldı: boş satır, 3 karakterden kısa satır ve yorum satırları atıldı, satırlar kırpıldı, sonra `diff` ile ortak satırlar sayıldı. Payda `add` dosyasının anlamlı satırları.
  - **34 çift %60 veya üzeri ortak; toplam 7.604 ortak satır.**
  - En büyükleri: `add_page`/`edit_page` 1.759 ortak (%75), `add_calendar_event`/`edit_` 654 (%80), `add_shipping_method`/`edit_` 538 (%71), `add_field`/`edit_` 504 (%73), `add_email_campaign_profile`/`edit_` 422 (%85), `add_contact`/`edit_` 373 (%68).
  - Ters örnekler kod tabanında zaten var: `add_product.php`/`edit_product.php` (154/135 satır) ikisi de `product_builder.php`'yi çağırıyor. ERP `includes/erp/account_form.php`, `invoice_form.php`, `waybill_form.php`, `till_form.php` ile tek form kullanıyor ve ERP çiftlerinde benzerlik %29–50'ye iniyor.
- **Neden sorun:** Bir alana yapılan düzeltme iki dosyaya da girmeli. Birine girmezse ekleme ve düzenleme ekranları sessizce ayrışıyor.
- **Önerilen yön:** ERP deseni: `includes/forms/<varlık>_form.php` (render + POST okuma). Kökteki iki dosya ince sarmalayıcı olarak kalır, dosya bütünlüğü kararı gereği taşınmaz. Önce en büyük üç çift.
- **İş yükü:** B (toplam), O (çift başına). **Risk:** Orta. Her çiftte ekleme ve düzenlemeye özgü dallar ayıklanmalı. Test takımı olmadığı için elle doğrulama gerekiyor.

### 7. Liste ekranları: sıralama ve sayfalama her ekranda baştan yazılmış, büyük tablolar tamamen tarayıcıya iniyor
- **Kanıt:**
  - `view_*`/`erp_*` altında **53 liste ekranı** istemci taraflı DataTables kullanıyor (`backend.src.js:1106–1129`, `pageLength` 100, "Unlimited" seçeneği var). Bunların **46'sının ana sorgusunda `LIMIT` yok**. Kodda hiç `serverSide` DataTables yok.
  - Tarih süzgeciyle daralanlar var (`view_orders.php:342`, haftalık aralık). `view_products.php`, `view_contacts.php` ve `view_users.php` ise bütün satırları HTML'e basıyor.
  - Ortak sayfalama yardımcısı yok. Yalnız ön yüz için `pg_sw_pagination_html()` (`includes/fn/widgets.php:2867`) var.
  - `$_REQUEST['screen']` doğrulama bloğu 15 dosyada kopya. `view_contacts.php:36–38` yorumu "`$number_of_screens` hiçbir yerde hesaplanmıyor, sayfa bağlantıları hiç çizilmiyor" diyor.
  - Sunucu taraflı sıralama ayrıca duruyor: 32 ekranda 332 `get_column_heading()` çağrısı, 42 ekranda `$_SESSION[...]['sort']`. `view_products.php` her süzgeç dalında aynı "sort session geçerli mi" bloğunu tekrarlıyor (8 kopya, `:137–330`) ve sıralama anahtarı olarak çevrilmiş etiketi saklıyor (`lang('Last Modified')`). Panel dili değişince saklı sıralama geçersizleşiyor.
- **Neden sorun:** On binlerce ürün veya kişiyle ekran yavaşlıyor, bellek yükseliyor (doğrulanamadı, örnek veri yok). İki sıralama sistemi (sunucu linkleri + DataTables) aynı tabloda yaşıyor.
- **Önerilen yön:** Tek bir `pg_list_state($screen, $columns_map, $default)` yardımcısı (sıralama + süzgeç + sayfa, anahtar kolon kodu, etiket değil). Büyük dört ekran (ürün, kişi, kullanıcı, gönderilen formlar) için DataTables `serverSide` + JSON uç. Toplu seçim (`multiselectCheckbox`) korunmalı.
- **İş yükü:** B. **Risk:** Orta. Toplu işlem ve dışa aktarma şu an DOM'daki satırlardan çalışıyor olabilir, ekran ekran kontrol edilmeli.

### 8. `_f` sonekli dosyalar: kökte duran fonksiyon kütüphaneleri
- **Kanıt:** 6 dosya: `duplicate_folder_f.php` (109), `duplicate_page_f.php` (744), `edit_offer_f.php` (2.029), `export_products_f.php` (554), `import_products_f.php` (594), `view_folder_and_files_f.php` (**10.130 satır, 102 fonksiyon**, 6 yerde `memory_limit -1`). URL ile çağrılmıyorlar. Eşi olan ekran, `api.php`, `includes/api/resources/{files,pages,offers}.php` ve `includes/workspace/changes.php` bunları `require` ediyor.
  - 6 dosyanın 5'inde kapı sabiti yok. Görüldüğü kadarıyla üst düzeyde yalnız fonksiyon tanımı var, doğrudan açılınca çıktı vermiyorlar (tam doğrulanmadı).
- **Not:** `pinegrap-yeni-dosya` gereği kökteki mevcut include dosyaları **taşınmaz** (dosya bütünlüğü). Bu yüzden öneri taşıma değil.
- **Önerilen yön:** `view_folder_and_files_f.php`'deki bağımsız alt sistemler (dışa aktarma, önizleme, ağaç) gerekirse `includes/explorer/` altına bölünür, kökteki dosya yükleyici olarak kalır. Kapı sabiti eklenip eklenmeyeceği ürün sahibine sorulmalı.
- **İş yükü:** O. **Risk:** Düşük-orta.

---

## C. Veritabanı erişimi

### 9. Eski kodda ham `mysqli_query`; merkezî ölçüm yapılamıyor; parametreli sorgu yok
- **Kanıt:**
  - Vendor hariç **2.420 `mysqli_query(` çağrısı, 273 dosyada**. Bunun 2.116'sı `or output_error(…)` kalıbı.
  - Yardımcı kullanımı: `db()` 1.824, `db_item()` 839, `db_items()` 1.014, `db_value()` 1.205 (toplam yaklaşık 4.880).
  - Yeni modüller neredeyse tamamen yardımcı kullanıyor: `includes/erp` 2 ham / 445 yardımcı, `includes/workspace` 0 / 860, `includes/api` 8 / 138. Ham çağrıların yoğunlaştığı yerler: `install/index.php` 105, `includes/fn/ecommerce.php` 104, `submit_order.php` 79, `api.php` 69, `edit_page.php` 57.
  - **mysqli prepared statement sayısı 0** (bulunan `->prepare(` çağrılarının hepsi HTML form sınıfına ait). `escape(` 4.622, `e(` 2.901 kez geçiyor.
  - `includes/fn/core.php:212–365`: `db`, `db_value`, `db_values`, `db_item`, `db_items` aynı hata bloğunu 5 kez tekrarlıyor (bağlantı kontrolü → `@mysqli_query` → `install_query_failed` → `output_error`).
- **Neden sorun:** Sorgu sayısı, yavaş sorgu ve hata bağlamı merkezî olarak toplanamıyor; `perf_stats` tablosunda sorgu sayısı yok (`includes/migrations/2026.3.2.php:38`). Değerin kaçışı elle yapılıyor; LIKE tuzağı skill'de ayrıca uyarılıyor.
- **Önerilen yön:** (a) Beş yardımcı tek `pg_db_run($sql)` çekirdeğine iner, burada sorgu sayacı ve süre tutulur, sonuç `perf_stats`'a yazılır (K). (b) Kaçışı kendisi yapan bir yer tutucu yardımcısı, örneğin `db_q("… WHERE id = ? AND name = ?", [$id, $name])`. Gerçek prepared statement değildir; mevcut `false` döndürme sözleşmesini (`MYSQLI_REPORT_OFF`) bozmaz. Yeni kodda zorunlu, eskide dokunuldukça kullanılır (O). (c) Ham çağrılar en sıcak dosyalardan başlayarak dönüştürülür (B, fırsatçı).
- **İş yükü:** K / O / B. **Risk:** (a) düşük. (c) orta: bazı yerler `mysqli_num_rows`, `MYSQLI_USE_RESULT` gibi akış davranışlarına dayanıyor.

### 10. Savunmacı şema yoklamaları dağınık
- **Kanıt:**
  - Migration'lar dışında **91 `SHOW COLUMNS` (41 dosya), 55 `SHOW TABLES LIKE`, 53 `information_schema`** geçişi ve **101 `*_ready()` fonksiyonu** var.
  - Ayrıca 9 ayrı "kolon/tablo var mı" yardımcısı: `waf_table_has_column`, `pg_tr_has_column`, `pg_cf_form_fields_has_column`, `install_column_exists`, `erp_overdue_column_exists`, …
  - Bunların 61'i `SHOW COLUMNS … LIKE 'ad'` biçiminde. `pinegrap-sema-adimi` bu biçimi migration'da yasaklıyor (`_` joker karakter). Sıcak yollardaki örnekler: `view_orders.php:99,103` (her açılışta 2 yoklama), `cart_action.php:567`, `job.php:264`, `api.php:8858`.
  - `config.version` zaten her istekte okunuyor (`init.php:249` `SELECT * FROM config`).
- **Neden sorun:** Her yoklama bir sorgu daha demek ve her biri kendi önbellek kuralını (static ya da yok) taşıyor. Kural ("yükseltme köprüsü") doğru, ama bedeli her istekte ödeniyor.
- **Önerilen yön:** Tek bir `pg_schema_has($table, $column = null)` yardımcısı: tam eşitlikle sorar (`information_schema`/`WHERE Field =`), sonucu `data/temp/schema_<config.version>_<VERSION>.json` dosyasına yazar. Yükseltme ve "önbelleği temizle" dosyayı siler. Yalnız sürüm karşılaştırmasına geçilmemeli: skill'deki teşhis notuna göre `includes/migrations` 0555 ise `config.version` gerçek şemanın önüne geçebiliyor.
- **İş yükü:** O. **Risk:** Düşük-orta. Önbellek bayatlarsa özellik "hazır değil" görünür, temizleme yolu net olmalı.

### 11. Çekirdek tablolar MyISAM; ERP ve kampanyalarda geçici çözümler biriyor
- **Kanıt:**
  - Kurulum dökümündeki **141 tablonun 141'i `ENGINE=MyISAM`** (`data/backups/english_default/sql.sql`). Aralarında `orders`, `order_items`, `products`, `contacts`, `config`, `log`, `next_order_number`, `email_recipients`, `email_campaigns` var.
  - Migration'la InnoDB'ye geçenler yalnız `visitors` (2026.3.6) ve `user` (2026.4.4). Yeni tablolar InnoDB (migration'larda 130 `ENGINE=InnoDB`). İdempotent yardımcı mevcut: `install_set_engine()` (`includes/migrations/runner.php:556`).
  - Bedeli: `pinegrap-erp` skill'i "`orders` MyISAM'dır: ERP transaction'ı onu geri almaz" ve "`products` MyISAM: stok sayısını transaction içinde değiştirme → `erp_stock_apply_pending()`" diyor. Kodda 14 `LOCK TABLES` var. `email_campaign_job.php:120` yorumu "We should consider moving to InnoDB" diyor.
- **Neden sorun:** Sipariş, stok ve fatura tutarlılığı uygulama tarafındaki sıralama kurallarına dayanıyor. Tablo kilitleri eşzamanlılığı düşürüyor; kirli kapanmada MyISAM onarım gerektiriyor (`2026.3.6.php:32` aynı gerekçeyi yazmış).
- **Önerilen yön:** Açık sürüme kademeli `install_set_engine()` adımları. Ağır tablolar `install_heavy_tables()`'a girer. Öncelik: `next_order_number`, `orders`, `order_items`, `products`, `email_recipients`/`email_campaigns`, `contacts`, `log`. `data/backups/` dökümlerine **dokunulmaz** (verilmiş karar); dönüşümü migration yapar.
- **İş yükü:** B. **Risk:** Orta-yüksek: büyük tablolar yeniden yazılır, uzun sürer. 4 FULLTEXT dizini ve 12 `MATCH … AGAINST` sorgusu var: InnoDB FULLTEXT MySQL 5.7'de destekleniyor, ama en kısa kelime ve stopword varsayılanları farklı olduğu için arama sonuçları değişebilir (doğrulanamadı).

### 12. `config` tek satırlık geniş tablo, satır boyutu sınırına dayanmış
- **Kanıt:** Dökümde `config` 192 kolon; migration'larda ek olarak 156 `install_add_column('config'…)` (yaklaşık 350 kolon). `docs/degisiklikler.md:14984`: "VARCHAR sütunları utf8mb4'te ~63 KB tutuyor, 65.535 bayt sınırına birkaç yüz bayt kaldı; VARCHAR(500) 1118 verdi". Her istek `SELECT * FROM config` çalıştırıyor (`init.php:249`); `init.php`'de 301 `define(`.
- **Neden sorun:** Her yeni ayar TEXT'e zorlanıyor ve şema adımı gerektiriyor. Tek satırlık kolon ayarı ölçeklenmiyor.
- **Önerilen yön:** Yeni ayarlar `config_kv` (anahtar, değer) tablosuna yazılır, aynı `define()` hattından sabite çevrilir. Mevcut kolonlar yerinde kalır, taşıma zorunlu değil.
- **İş yükü:** O. **Risk:** Düşük-orta (çift kaynak dönemi).

---

## D. Geliştirici deneyimi ve kalite altyapısı

### 13. CI dört denetimin yalnız ikisini koşuyor
- **Kanıt:** `.github/workflows/php-checks.yml` yalnız `tools/lint.php` ve `tools/check_lang.php` koşuyor (PR ve `main` push). `tools/check_bindings.php`, `tools/check_api_schema.php` ve `CLAUDE.md`'nin istediği `node --check assets/js/style_designer.js` CI'da yok. Bugün hepsi temiz, yani eklemek hiçbir şeyi kırmıyor.
- **Önerilen yön:** İki PHP adımı ve `node --check` adımı (setup-node) eklenir. İleride `.src`/`.min` tazelik kontrolü de buraya girer (madde 5).
- **İş yükü:** K. **Risk:** Çok düşük.

### 14. Composer'sız test koşucusu uygulanabilir (ölçüldü)
- **Kanıt:** `PG_FUNCTIONS_DIR` tanımlayıp `functions.php`'yi CLI'dan yüklemek veritabanı olmadan çalışıyor: 78 ms, 12 MB. `PG_ERP_ENTRY` ile `includes/erp/money.php` da yükleniyor. Denenen çağrılar beklenen sonuçları verdi:
  - `erp_kurus('1.234,56')` = 123456, `erp_kurus('1.234')` = 123400
  - `erp_split_gross(11800, 18)` = net 10000 / vergi 1800
  - `erp_allocate(100, [1,1,1])` = 34/33/33
  - `escape_url('javascript:…')` = false
  - `parse_tax_rate('18,5')` = "18.500"
  - `sql_order_direction('DROP')` = "asc"

  `docs/degisiklikler.md`'de 16 yerde "birim testi / sahte-DB / deneme betiği" geçiyor. Bu testler yazılmış, ama depoda tutulmamış.
- **En kritik ve en test edilebilir saf fonksiyonlar:** `erp_kurus`, `erp_apply_rate`, `erp_split_gross`, `erp_allocate`, `erp_line_total`, `erp_to_base` (`includes/erp/money.php`); `parse_tax_rate`, `format_tax_rate`, `get_effective_tax_rate` (`includes/fn/ecommerce.php:1193–1257`); `pg_shipping_carrier()` (desen eşleştirme, `ecommerce.php:5636`); `escape_url`, `sql_order_direction`, `escape_like` (`core.php`); `pg_transliterate_to_ascii`, `prepare_file_name`; `api_route_pattern`, `api_validate_value` (`includes/api/router.php`); `lang()` `{var:N}` doldurma.
  - `erp_next_number()` veritabanı istiyor (`FOR UPDATE`), entegrasyon katmanına ait. `setup_sandbox.sh` ile koşulabilir.
- **Önerilen yön:** `tools/test.php` + `tests/*_test.php`. Mevcut `tools/check_*.php` biçiminde, bağımlılıksız ve basit `assert_same()` ile. CI'a madde 13'le birlikte girer.
- **İş yükü:** K (koşucu) / O (ilk 50–100 test). **Risk:** Çok düşük, ürün koduna dokunmuyor.

### 15. Merkezî hata yakalama yok; uyarılar susturulmuş
- **Kanıt:** Ürün kodunda `set_error_handler`/`set_exception_handler` yok (yalnız vendor içinde). `register_shutdown_function` belli işler için var (`job.php:484`, `init.php` perf). `init.php:84` `E_ALL & ~E_NOTICE & ~E_DEPRECATED`. `output_error()` (`core.php:648`) ekrana basıyor, kalıcı kayıt tutmuyor. `log_activity()` (`forms.php:1522`) serbest metin; seviye ya da kategori yok, 983 çağrı, %1 olasılıkla 6 aydan eski kayıtları siliyor. `view_log.php:28–33` PHP hatalarını yalnız üç sabit dizindeki `error_log` dosyasından okuyor; `ini_get('error_log')` başka bir yolu gösteriyorsa panel hiçbir şey görmüyor.
- **Neden sorun:** Yakalanmamış istisna ve ölümcül hatalar operatöre görünmüyor. PHP 8.5/9 uyumluluğu için gereken deprecation listesi hiç toplanmıyor.
- **Önerilen yön:** `init.php`'de istisna ve shutdown işleyicisi. İstek kimliği, URL, kullanıcı ve yığın izi `data/temp/php_errors.log`'a (döner dosya) ya da `log` tablosuna seviye kolonuyla yazılır. `DEBUG` açıkken deprecation'lar da toplanır. `view_log` bu dosyayı ve `ini_get('error_log')`'u da okur.
- **İş yükü:** O. **Risk:** Düşük-orta. İşleyici `mysqli_report(OFF)` sözleşmesini değiştirmemeli. `output_error()` RuntimeException fırlatma yolu (`pg_seo_rendering`) bozulmamalı.

### 16. `get_file.php` içindeki çekirdek fonksiyon kopyaları birbirinden ayrışmış
- **Kanıt:** `get_file.php` (`functions.php`'yi yüklemiyor) 15 fonksiyonu aynı adla yeniden tanımlıyor. Yorumlar dışlanarak `difflib` benzerliği ölçüldü: `db` 0,04, `get_access_control_type` 0,13 (13 satıra karşı 33), `check_edit_access` 0,21, `initialize_user` 0,29 (48'e karşı 184 satır), `check_view_access` 0,76, `check_private_access` 0,83, `get_request_uri` 1,00. Erişim kontrolü yapan kopyalar da ayrışmış. Ortak bir dosya deseni zaten var: `includes/authentication.php` (`functions.php` yorumu: "shared with get_file.php").
- **Neden sorun:** Bir erişim kuralı düzeltmesi dosya sunucusuna sessizce yansımayabilir. Farkların kasıtlı olup olmadığı doğrulanamadı.
- **Önerilen yön:** Erişim fonksiyonları (`check_*_access`, `get_access_control_type`) `includes/authentication.php` gibi bağımsız bir dosyaya alınır ve iki taraf da onu kullanır. Bu yapılana kadar `tools/check_copies.php` farkları raporlar.
- **İş yükü:** O. **Risk:** Orta, güvenlik açısından hassas. Önce ayrışmaların gerekçesi okunmalı.

---

## E. Operasyon

### 17. E-posta kampanyası, SMTP gönderimi sürerken `contacts` ve `log` tablolarını yazma kilidinde tutuyor
- **Kanıt:** `email_campaign_job.php:129` `LOCK TABLES email_recipients WRITE, email_campaigns WRITE, contacts WRITE, log WRITE` → `:418` `email(array(…))` (eşzamanlı SMTP) → `:443` `UNLOCK TABLES`. Döngü alıcı başına çalışıyor, varsayılan 25 alıcı/koşu (`EMAIL_CAMPAIGN_JOB_NUMBER_OF_EMAILS`). Kilit sürerken `log_activity()` (983 çağrı yeri) veya `contacts` okuyan her istek bekliyor.
  - `email()`'in dönüşü kontrol edilmiyor (`:418–430`): gönderilemeyen alıcı yine `complete = '1'` işaretleniyor. Hata yalnız `log`'a düşüyor (`mail.php:1515–1535`), yeniden deneme yok.
- **Önerilen yön:** Kısa vadede: alıcıyı kilit altında "sending" diye sahiplen, kilidi bırak, sonra gönder, sonucu yaz (`UPDATE … WHERE complete='0'` ile koşullu). Gönderilemeyen alıcı sayaçla yeniden denenir. Uzun vadede madde 11 (InnoDB) bu kilidi gereksiz kılar.
- **İş yükü:** K–O. **Risk:** Düşük-orta (çift gönderim önleme mantığı korunmalı).

### 18. İşlemsel e-posta istek içinde eşzamanlı; kuyruk yok
- **Kanıt:** 26 dosyada 41 `email(array(` çağrısı, hepsi istek içinde SMTP'ye gidiyor (`includes/fn/mail.php:1379–1513`). Kodda mail kuyruğu yok (`outbox`/`mail_queue` geçmiyor). `includes/db_guard.php` başlık yorumu canlıda yaşanan bir vakayı anlatıyor: forgot_password, SMTP gönderimi boyunca DB bağlantısını tutuyor, `max_user_connections` doluyor, site düşüyor. Kısmi örnekler var: ERP fatura e-postası istek sonunda (`erp_mail_defer()`); webhook'ta tam kuyruk + geri çekilme (`api_webhook_queue`).
- **Önerilen yön:** `mail_outbox` tablosu + `email()`'e `'queue' => true` seçeneği + `push_job` gibi satır içi cron işi. Geri çekilme takvimi `api_webhook_backoff()` modelinden alınır. Önce şifre sıfırlama, sipariş onayı, form bildirimi kuyruğa girer.
- **İş yükü:** O. **Risk:** Orta. Cron çalışmayan sitede e-posta gecikir; cron yoksa istek sonunda gönderme yedeği gerekir.

### 19. Cron: site geneli tek kilit, her tıkta tek iş
- **Kanıt:** `pg_cron_jobs()` 20 iş tanımlıyor; `job` dağıtıcı, `api_webhook_job` ve `push_job` satır içi. `pg_cron_dispatch_next()` (`includes/fn/cron.php:647–760`) her tıkta **bir** iş seçiyor ve `config.job_dispatch_lock_until` ile site geneli kilit alıyor (`:736–745`, varsayılan 3.600 sn). Kilit atomik ve kendiliğinden düşüyor, bu kısım iyi. Ama `auto_backup` (`max_execution_time 0`, `memory_limit -1`) sürerken kampanya (300 sn), `api_sync` (300), `translation` (300), `workspace_recurring` (300) ve günlük ERP işleri bekliyor. Yedeklemenin süresi ve gerçek gecikme doğrulanamadı.
- **Önerilen yön:** Kilidi iş başına `cron_runs.locked_until` kolonuna taşımak. Ağır işler (yedek, SEO analizi) ayrı bir "heavy" şeridinde, hafif işler kendi şeridinde koşar.
- **İş yükü:** O. **Risk:** Düşük-orta (şema adımı gerekir).

### 20. Yedekleme: yalnız yerel disk, saklama süresi yok, uzak hedef yok
- **Kanıt:** `auto_backup.php` (169 satır) dökümü ve `files/`, `layouts/` klasörlerinin sıkıştırılmamış kopyasını `data/backups/auto_backup_<Y-m-W>/` altına yazıyor. Yani aynı disk, web kökü altı. `auto_backup_` klasörlerini silen kod yok (yalnız `auto_backup.php` içinde geçiyor), haftalık klasörler birikiyor. S3/FTP/SFTP/WebDAV hedefi yok. Koruma `.htaccess` `deny from all` (Apache sözdizimi); IIS/nginx altındaki koruma doğrulanamadı.
- **Önerilen yön:** Zip'leme, "son N yedeği tut" ayarı ve isteğe bağlı uzak hedef (S3 uyumlu PUT `pg_curl` ile ya da SFTP). Ürün değeri yüksek: disk arızasında bugünkü yedek siteyle birlikte gidiyor.
- **İş yükü:** O. **Risk:** Düşük (yeni özellik). `data/backups/` içindeki başlangıç dökümlerine dokunulmaz.

### 21. Uygulama katmanında önbellek yok; performans kaydında sorgu sayısı yok
- **Kanıt:** Sayfa, parça ya da sorgu önbelleği yok (`page_cache`, `apcu_`, `memcache`, `redis` aranınca yalnız temizleme kodu çıkıyor). `pg_purge_caches()` (`includes/fn/system_status.php:856`) LiteSpeed, OPcache, APCu ve temp dosyalarını temizliyor. Tek önbellek kırma yardımcısı `smart_cache()` (`core.php:2405`, yalnız yüklenen dosyalar için). `perf_stats` süre, KB ve CPU tutuyor, sorgu sayısı tutmuyor. Sayfa başına sorgu sayısı doğrulanamadı (örnek kurulmadı).
- **Önerilen yön:** Önce ölçüm (madde 9a'daki sayaç `perf_stats`'a kolon olarak girer), sonra en pahalı ön yüz widget'ları için `data/cache/` altında parça önbelleği, anahtar `içerik + updated_at`.
- **İş yükü:** O. **Risk:** Orta (bayat içerik; geçersiz kılma noktaları belirlenmeli).

---

## F. Ürün değeri (kısa)

### 22. Kampanya e-postalarında `List-Unsubscribe` başlığı yok
- **Kanıt:** Ürün kodunda (PHPMailer dışında) `List-Unsubscribe` ve `addCustomHeader` hiç geçmiyor. Gövdede tercih/abonelik iptali bağlantısı var (`mail.php:967`, `email_campaign_job.php:405`). Gmail ve Yahoo'nun 2024 toplu gönderici kuralları tek tıkla abonelik iptali başlığı (RFC 8058) istiyor. Eşik ve kesin kural metni bu çalışmada yeniden doğrulanmadı.
- **Önerilen yön:** `type = 'campaign'` gönderimlerinde `List-Unsubscribe: <mailto:…>, <https://…/email_preferences.php?…>` ve `List-Unsubscribe-Post: List-Unsubscribe=One-Click` başlıkları + POST'u kabul eden uç.
- **İş yükü:** K–O. **Risk:** Düşük.

### Eksik sanılabilecek ama mevcut olanlar (bulgu değil)
- **Webhook yeniden deneme:** Tam olarak var. `api_webhook_queue`, 6 adımlı geri çekilme (60 sn → 1 gün, `includes/api/outbound/webhooks.php:211–231`), gönderimden önce sahiplenme (`:355`), tükenen satırlar bir hafta saklanıyor (`:370`), panelde "yeniden gönder" düğmesi (`api_settings.php:265`, `webhook_retry`). Eksik değil.
- Listelerde toplu seçim (`multiselectCheckbox`, `backend.src.js:1046`), panel araması (`backend_search`), ürün ve ERP dışa aktarımı (`export_products.php`, `erp_export.php`) ve klavye kısayolu blokları zaten var.

---

## Verilmiş kararlar gereği bulgu yazılmayanlar
- Kökteki ~85 include dosyası taşınmıyor (dosya bütünlüğü). `_f` dosyaları için taşıma önerilmedi (madde 8).
- `data/backups/` dökümleri: InnoDB dönüşümü dökümü düzenleyerek değil, migration ile önerildi (madde 11).
- `software_backup` için manager kapısı, `pi.php`/`si.php`, 22/24 widget id'leri, `get_express_order.php` hesaplamaları, legacy custom style katmanı, `test_secure_mode.php`: dokunulmadı, raporlanmadı.
- `mysqli_report(MYSQLI_REPORT_OFF)` sözleşmesi: öneriler (madde 9, 15) bu sözleşmeyi koruyacak şekilde yazıldı.

## Zaten planlı (`docs/_plan_*`)
| Dosya | Başlıktaki durum | Bu raporla ilişkisi |
|---|---|---|
| `_plan_tuval_animasyon.md` | Plan, açık sürüm 2026.4.8 (A+B+C seçildi) | `style_designer.js`'e dokunuyor (`applyAssetsToIframe`, `createCanvasIframe` bölünmesi). Madde 5 (ikiz/minify/bölme) bununla sıralanmalı; bu iş yeni öneri olarak yazılmadı. |
| `_plan_on_yuz_cok_dil.md` | "plan (2026-10-04), henüz kod yok" yazıyor; `translation_job` ve `includes/fn/translate.php` mevcut, kısmen uygulanmış görünüyor | Bu raporda karşılığı yok. |
| `_plan_shared_components.md` | "kod yazılmadı" yazıyor; `api.php`'de `shared_component` case'i (639 satır) var, başlık güncel görünmüyor | Madde 2'de taşıma adayı olarak geçiyor, yeni özellik olarak önerilmedi. |
| `_plan_add_order_ajax.md` | Uygulandı (2026-09-23) | — |
| `_plan_ai_tasarim.md`, `_plan_api_mobil.md`, `_plan_varyant_fiyat.md` | Uygulandı | — |
| `_plan_calisma_alani.md`, `_plan_calisma_alani_4_5.md`, `_plan_claude_kanal.md` | Faz 1 / tablo maddeleri "bitti" | `ws_` devri madde 2'de örnek desen olarak anıldı. |
| `_plan_erp.md` | v3; Faz -1…5 çoğu tamam | MyISAM kısıtı ERP skill'inde biliniyor; madde 11 genel altyapı önerisi olarak yazıldı. |

## Doğrulanamayanlar (özet)
- Gerçek kurulumdaki sorgu sayısı, liste ekranlarının büyük veriyle süresi, OPcache'in etkisi, barındırıcının sıkıştırma yapıp yapmadığı, yedekleme süresi (çalışan örnek kurulmadı).
- Madde 3'teki tokensız yazan uçların siteler arası istekle tetiklenip tetiklenemeyeceği (tarayıcıda denenmedi).
- `get_file.php` kopyalarındaki farkların kasıtlı olup olmadığı.
- InnoDB FULLTEXT geçişinin arama sonuçlarını nasıl değiştireceği.
