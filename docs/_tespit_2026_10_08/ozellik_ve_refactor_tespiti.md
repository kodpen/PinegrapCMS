# Pinegrap CMS — Özellik ve refactor adayları (tespit raporu, 2026-10-08)

Kaynak: `development` @ `0294c42`. Üç paralel kod incelemesi (kargo, kimlik doğrulama, refactor/altyapı). Hiçbir dosya değiştirilmedi; çalışan örnek kurulmadı. Ayrıntı ve dosya:satır kanıtları şu üç dosyada:

- `kargo_yonetimi_kesif_raporu.md`
- `2fa_kesif_raporu.md`
- `refactor_altyapi_raporu.md` (22 kanıtlı bulgu)

Verilmiş kararlar (`pinegrap-verilmis-kararlar`) bulgu olarak yazılmadı; çakışma ihtimali olan yerler "soru" olarak ayrıldı. İş yükü: K/O/B.

---

## Kademe A — küçük, hemen değer (her biri K)

| # | İş | Kanıt | Neden |
|---|---|---|---|
| A1 | Kargo e-postasına takip no / firma / link mail-merge alanları | `update_order.php:241-245` yalnız `^^order_number^^`; hazır "Kargo Yola Çıktı" sayfası (pregion 2903) numara içermiyor | Bugün giden e-posta işe yaramaz içerikte |
| A2 | `ECOMMERCE_DEFAULT_TRACKING_PROVIDER` yedeğini belgeye uydur + Türkçe `İ` eşleşmesi + eksik firmalar (Hepsijet, Trendyol Express, Kolay Gelsin…) | `widgets.php:4311`, `config(default).php:284-287`, `ecommerce.php:5541+` | "Standart Teslimat" yöntem kodlu sitede müşteri UPS/FedEx sayfasına gidiyor |
| A3 | `api.php:273` `include_once('mysqldump.php')` kaldır | 72 KB her panel AJAX isteğinde yükleniyor; `software_backup` kendi include'ını yapıyor | Tek satır |
| A4 | CI'a eksik denetimler: `check_bindings`, `check_api_schema`, `node --check` | `.github/workflows/php-checks.yml` yalnız lint + check_lang | Hepsi bugün temiz, kırmaz |
| A5 | Kampanya e-postalarına `List-Unsubscribe` / `List-Unsubscribe-Post` başlığı | Ürün kodunda hiç yok (`mail.php`) | Gmail/Yahoo toplu gönderici kuralı (eşik metni yeniden doğrulanmalı) |
| A6 | Yedek: zip + "son N yedeği tut" | `auto_backup.php` sıkıştırmasız, silme kodu yok, haftalık klasörler birikiyor | Disk dolması |
| A7 | **2FA ön koşulu:** `change_password.php` oturumsuz giriş (L80→L184) ve `set_password.php` otomatik giriş (L124) | 2FA'dan bağımsız kapatılmalı; aksi halde 2FA delinir | Güvenlik |

---

## Kademe B — ürün sahibinin iki isteği

### B1. Kargo yönetimi (yerel doğrulamalı) — O/B

**Bugün:** "numarayı elle gir, müşteriye link göster". Firma 8 (5 TR), hiçbiriyle API yok; durum sorgulayan cron işi yok; misafir takip sayfası yok; toplu işlem yok.

**Omurga sorunu (tek cümle):** sipariş durumu `incomplete/complete/exported/cancelled` ile sınırlı ve "gönderildi"nin sistemde **dört farklı tanımı** var (takip no var / `shipped_quantity` / `ship_date<=bugün` / n11 durumu); `ship_date`/`delivery_date` ödeme anında **tahminle** dolduğu için zaman çizelgesi tarih geçince "Teslim edildi" yazıyor ve `order.delivered` webhook'u fiilen hiç gitmiyor (`update_order.php:66-70`).

**Önerilen çekirdek (öncelik sırasıyla):**
1. **Sevkiyat satırı başına durum + gerçekleşen tarih:** `shipping_tracking_numbers`'a `carrier`, `status`, `shipped_at`, `delivered_at`, `source`, `tracking_url`, `UNIQUE(ship_to_id, number)`; `shipping_methods.carrier` ile yöntem→firma açık bağ. Tahmini tarihlere dokunulmaz. `update_order()` INSERT+DELETE yerine UPDATE/INSERT (aksi halde yeni kolonlar her kayıtta silinir).
2. **Yerel doğrulama (uyarı kipinde):** `pg_shipping_carriers()`'a firma başına `validate`/`length`; TR posta kodu 5 hane + il plaka tutarlılığı (`states.plate_code` gerekir); telefon normalizasyonu; desi hesabı. **Kural:** geçersiz biçim `_order_has_shipped()` sayımına girmez diye yazılırsa müşteri iptal kapısı açılır — doğrulama yalnız uyarır, satırı saymaktan çıkarmaz.
3. **Müşteri tarafı:** A1 + `order_delivered` tetiği; numara+e-posta ile misafir takip sayfası.
4. **Panel:** toplu takip no yükleme (CSV) ve toplu "gönderildi"; çok alıcılı siparişte tagin id çakışması (`view_order.php:974-978`).
5. **Sonraki faz:** firma API sürücüleri (`includes/shipping/<firma>.php`, ERP `edoc/registry.php` sürücü deseni), cron ile durum sorgusu, `shipping_tracking_events`.

**Verilmiş kararla çakışma — soru:** `orders.tracking_company` "ERP için duran alan, devamı gelecek" diye korunuyor. Firma bilgisini sevkiyat satırına (`shipping_tracking_numbers.carrier`) mi, bu kolona mı yazalım?

**Netleştirilmesi gereken:** "Yerel doğrulama" ile kastedilen (a) takip numarası biçim denetimi, (b) il/ilçe/posta kodu tutarlılığı (ilçe verisi repoda **yok**; kaynak + lisans gerekir), (c) sevk anında paketi barkodla siparişe karşı doğrulama — hangisi/hangileri?

Diğer bulgular (raporda): n11 il/ilçe ters eşlemesi (`n11.php:790` vs `erp/accounts.php:158`), ShipWorks'ün `update_order()` dışı yazması (karar mı?), `ERP_AUTO_INVOICE_ON` okunmuyor (ERP faz sınırı mı?), ABD-only `verify_address()` ve USPS Web Tools bağımlılığı (ölü olabilir, doğrulanmalı).

### B2. İki adımlı oturum açma — O (TOTP) / B (TOTP + e-posta)

**Hazır olanlar:** bekleyen-giriş deseni (`pg_device_limit_gate()` → `device_limit.php`), tek kullanımlık kod deseni (`forgot_password.php`), AES şifreleme (`encrypt_string_with_iv`, "cipher:iv" biçimi), giriş hız sınırı kovaları, güvenli ekran şablonu (`pg_login_captcha_screen()`), `auth_tokens` (güvenilir cihaz için örnek). TOTP/base32/QR kütüphanesi yok; RFC 6238 ~80 satır kendi kodu, PHP 7.1'de sorunsuz.

**Kapı nereye:** parola doğrulandıktan sonra, `pg_device_limit_gate()` ve `pg_login_set_device_cookie()`'den **önce** (önce token basılırsa çerez 2FA'sız oturum açar). Dört giriş dosyası: `index.php`, `membership_entrance.php`, `registration_entrance.php`, `google_auth.php`.

**Şema:** yeni `user_mfa` + `user_mfa_recovery` (+ opsiyonel `user_mfa_trusted`); `config.mfa_enabled`, `mfa_required_role`. `user.secret_key*` kolonlarına dokunulmaz (verilmiş karar).

**Kararlar (ürün sahibi):**
1. Zorunlu roller? (öneri: rol ≤ 2 personel zorunlu, üye isteğe bağlı)
2. Google ile giriş muaf mı?
3. Parola kabul eden API yolları (`api.php` `API_PASSWORD`, ShipWorks, `/auth/login` cihaz oturumu) ve kurulum kilidi kapsamda mı? (öneri: 2FA'lı hesaba red + uygulama anahtarına yönlendirme)
4. QR: yok (otpauth URI + elle anahtar) / istemci JS kütüphanesi / PHP kütüphanesi?
5. 2026.4.8 alt adım etiket aralığı (kimlik doğrulama için tanımlı aralık yok).

**Kritik riskler:** `ENCRYPTION_KEY` sıfırlaması TOTP sırlarını çözülemez kılar (`commerce.save.php:68-140` yalnız kart numarasını yeniden şifreliyor); `login_as_user` kipinde 2FA yönetimi kapatılmalı; 2FA açılınca mevcut remember-me tokenları iptal (API cihazlarını da düşürür); kurtarma yolu (yedek kodlar + `edit_user.php` sıfırlama) baştan planlanmalı.

---

## Kademe C — yapısal refactor ve altyapı

| # | Konu | Kanıt | Yön | İş | Risk |
|---|---|---|---|---|---|
| C1 | `api.php` → `get_widget_data` case'i dosyanın %49,6'sı (7.459 satır, 27 widget) | `api.php:844-8303` | Her widget `includes/dashboard/widgets/<id>.php`; id'ler değişmez (22/24 emekli) | O | Düşük-orta |
| C2 | Panel `api.php` eylem tablosu | 227 case; 146'sı zaten `ws_`/`chat_` ile tek satırda modüle devrediliyor (`api.php:331-471`); kalan ~81 satır içinde | Aynı `ws_` deseniyle kademeli taşıma (`designer*`, `file_explorer`, `software_*` önce); `{file, handler, gate, token}` tablosu | B | Orta |
| C3 | Kapı/CSRF dağınık | 40 eylemi muaf tutan `and` zinciri (`api.php:148-262`); tokensız yazan uçlar: `server_config_repair`, `write_permissions_repair`, `ca_bundle_*`, `purge_cache`, `update_dashboard_appearance` | **Güvenlik incelemesi** (tarayıcıda denenmedi): gövde Content-Type'sız `php://input` + ödeme ekranında `SameSite=None` (`auth.php:1222-1257`) birlikte siteler arası POST yolu açıyor olabilir | K | Düşük |
| C4 | İşlemsel e-posta senkron, kuyruk yok | 26 dosyada 41 `email()`; `db_guard.php` vakası (SMTP boyunca DB bağlantısı → site düştü) | `mail_outbox` + `email(['queue'=>true])` + satır içi cron; geri çekilme `api_webhook_backoff()` modelinden | O | Orta (cron yoksa istek sonu yedeği) |
| C5 | Kampanya işi SMTP sırasında `contacts`+`log` WRITE kilidinde; gönderilemeyen alıcı "tamamlandı" | `email_campaign_job.php:129→418→443` | Kilit altında sahiplen, kilidi bırak, gönder, sonucu yaz; yeniden deneme sayacı | K-O | Düşük-orta |
| C6 | Composer'sız test koşucusu | `functions.php` CLI'da DB'siz 78 ms yükleniyor; `erp_kurus`, `erp_split_gross`, `erp_allocate`, `escape_url`, `parse_tax_rate` denendi, doğru; değişiklik günlüğünde 16 kez "geçici test yazıldı, atıldı" | `tools/test.php` + `tests/*_test.php`, CI'a girer | K/O | Çok düşük |
| C7 | Merkezî hata yakalama yok | `set_exception_handler` yok; `init.php:84` notice/deprecation susturulmuş; `view_log` yalnız 3 sabit dizin | `init.php`'de istisna + shutdown işleyici, döner log, `view_log` okur; `MYSQLI_REPORT_OFF` sözleşmesi korunur | O | Düşük-orta |
| C8 | MyISAM çekirdek tablolar | Dökümde 141/141 MyISAM (`orders`, `order_items`, `products`, `contacts`, `log`, `config`); yalnız `visitors`, `user` dönüştürülmüş; 14 `LOCK TABLES`, `erp_stock_apply_pending` geçici çözümü | Migration ile kademeli `install_set_engine()` (`next_order_number`, `orders`, `order_items`, `products` önce); döküm dosyalarına dokunulmaz | B | Orta-yüksek (FULLTEXT sonuçları değişebilir) |
| C9 | Liste ekranları: 53 ekran istemci DataTables, 46'sında `LIMIT` yok; sıralama anahtarı çevrilmiş etiket (dil değişince bozuluyor) | `view_products.php:137-330` 8 kopya blok | `pg_list_state()` yardımcısı; büyük 4 ekran için serverSide | B | Orta |
| C10 | `add_*`/`edit_*` çiftleri | 51 çiftin 34'ü ≥%60 aynı, 7.604 ortak satır (`add_page`/`edit_page` 1.759) | ERP `*_form.php` deseni; kök dosyalar ince sarmalayıcı kalır | B (çift başına O) | Orta |
| C11 | `SHOW COLUMNS`/`_ready()` yoklamaları | 91 + 55 + 53 + 101 fonksiyon; 9 ayrı "kolon var mı" yardımcısı | Tek `pg_schema_has()` + sürüm anahtarlı dosya önbelleği (yalnız sürüm karşılaştırmasına geçilmez) | O | Düşük-orta |
| C12 | `config` tablosu ~350 kolon, 65.535 bayt satır sınırına dayanmış | `degisiklikler.md:14984` | Yeni ayarlar `config_kv`; mevcut kolonlar yerinde | O | Düşük-orta |
| C13 | Büyük JS ham servis; minify elle | `style_designer.js` 3,08 MB (gzip 651 KB → yorumsuz 378 KB); `workspace.js` 736 KB; `backend.src.js` 469 KB; derleme betiği yok | `tools/build_js.sh` + `.src/.min` ikizi; `_sdT` tarayıcısı kaynak dosyayı okumalı | O | Orta — `_plan_tuval_animasyon` ile sıralanmalı |
| C14 | `get_file.php` 15 çekirdek fonksiyon kopyası ayrışmış (erişim kontrolü dahil) | benzerlik 0,04–1,00 | `includes/authentication.php` deseniyle ortak dosya; önce farkların gerekçesi | O | Orta (güvenlik) |
| C15 | Cron tek kilit, her tıkta tek iş; yedek sürerken 300 sn'lik işler bekler | `cron.php:647-760` | İş başına kilit, "heavy"/"light" şerit | O | Düşük-orta |
| C16 | Uzak yedek hedefi yok | `auto_backup.php` aynı disk, web kökü altı | S3 uyumlu PUT (`pg_curl`) / SFTP | O | Düşük |
| C17 | Uygulama önbelleği yok; `perf_stats` sorgu sayısı tutmuyor | `core.php:212-365` 5 yardımcı aynı blok | Önce `pg_db_run()` çekirdeği + sayaç; sonra parça önbelleği | K→O | Orta |

**Bulgu değil (mevcut):** webhook yeniden deneme (6 adımlı geri çekilme, sahiplenme, panelde yeniden gönder), toplu seçim, panel araması, ürün/ERP dışa aktarımı.

---

## Ürün sahibine sorular (bulgu değil, karar)

1. Kargo: "yerel doğrulama" kapsamı (B1'deki a/b/c) ve `orders.tracking_company` vs sevkiyat satırı kararı.
2. 2FA: B2'deki 5 karar.
3. ShipWorks'ün `update_order()` dışında yazması, `ERP_AUTO_INVOICE_ON`'un okunmaması, `_f` dosyalarında kapı sabiti yokluğu, `get_file.php` kopya farkları — kasıtlı mı?
4. C3'teki tokensız yazan uçlar için güvenlik incelemesi isteniyor mu?
5. Belge bakımı: `_plan_shared_components.md` ("kod yazılmadı" ama `api.php`'de 639 satırlık case var) ve `_plan_on_yuz_cok_dil.md` ("henüz kod yok" ama `translation_job` mevcut) başlıkları bayat.

## Önerilen sıra

Kademe A (bir tur) → A7 + B2 (2FA) → B1 kargo çekirdeği (1–3) → C6 test koşucusu + C7 hata işleyici (sonraki refactorların güvenlik ağı) → C1/C2 `api.php` bölme → C4/C5 e-posta → C8 InnoDB (ayrı sürüm, uzun test).

Seçim yapıldığında ilgili iş için `docs/_plan_<konu>.md` taslağı ve geliştirici ajanına verilecek adım adım uygulama planı hazırlanır.
