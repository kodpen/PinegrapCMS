# Plan 3 — E-ticaret: kargo (sevkiyat) yönetimi ve yerel doğrulama (ana ajan Fable 5.1, alt ajanlar Opus 5.5)

Durum: plan (2026-10-08). Kaynak keşif: `docs/_tespit_2026_10_08/kargo_yonetimi_kesif_raporu.md` (dosya:satır kanıtları orada; yeniden keşif yapma).
Satır numaraları `development` @ `0294c42`'ye göre.

## 0. Sabit bağlam

- Depo kökü `/workspace/pinegrapcms`, ürün kodu `pinegrap/`. Önce `CLAUDE.md`; skill'ler (yolları alt ajan görevine yaz): `.claude/skills/pinegrap-veritabani/SKILL.md`, `pinegrap-sema-adimi`, `pinegrap-ceviri`, `pinegrap-ui-deseni`, `pinegrap-dis-api`, `pinegrap-tasarimci-widget` (widget tokenları), `pinegrap-verilmis-kararlar`, `pinegrap-js-varliklari`.
- Dal `development`'tan, PR tabanı `development`, AI izi yok. Ortak dosyalar (`2026.4.8.php`, `tr.json`, `changelog.txt`, `init.php`, `data/config(default).php`, `docs/*`): fetch+merge → yalnız ekleme → rebase. `upgrade_to_2026_4_8()` gövdesine yalnız kendi satırların.
- Şema etiketleri **8.20–8.29** (öneri; Erdal onaylar).
- Test koşucusu `php tools/test.php`; `tests/shipping_test.php` zaten var (bugünkü davranışı sabitler; davranış değişince **testle birlikte** değişir). Sandbox: `bash tools/setup_sandbox.sh` (e-posta: SMTP yok — `email()`'in sandbox'ta ne döndüğünü ilk adımda ölç; kampanya e-postası için `email_recipients`/gövde DB'den doğrulanır).
- Bitiş: lint, check_lang, test.php, **check_api_schema** (API'ye dokunuluyor), check_bindings (widget tokenlarına dokunuluyor), `.src.js`+`.min.js` ikisi; migration iki kez; yorumlar İngilizce; PR'da "doğrulayamadıklarım".
- **Verilmiş kararlar:** `orders.tracking_code` kampanya kodudur, kargoyla ilgisi yok; `orders.tracking_company` ve `orders.notes` **ERP için duran alanlardır — dokunma** (firma sevkiyat satırına yazılır; PR'da soru olarak not düş); `get_express_order.php`/`get_order_preview.php` hesaplamaları değişmez; legacy custom style şablonları production'dır.
- **Paralel planlar — dokunma:** `api.php` (Plan 1; `update_order` AJAX ucu `api.php:11728` **yalnız** `update_order()` imzası değişirse tek satır, Plan 1'e haber ver), `includes/api/outbound/webhooks.php`, `includes/fn/mail.php` (Plan 4 — `create_auto_email_campaigns` tetik listesi `mail.php:752` için yalnız ekleme, fetch+merge sonrası), `includes/migrations` genel (Plan 2 motor değiştirir — senin ALTER'ların kolon ekler, çakışmaz), giriş/auth (Plan 5).

## 1. Kapsam ve dosya sahipliği

| Faz | İş | Etiket | Dosyalar |
|---|---|---|---|
| 1 | Firma tanıma düzeltmeleri (A2) | — | `includes/fn/ecommerce.php:5537-5760`, `includes/fn/widgets.php:4293-4340`, `view_order.php:934`, `includes/templates/view_order.php:87-95`, `print_order.php:753`, `get_view_order_screen_content.php:830`, `data/config(default).php:284-287`, `tests/shipping_test.php` |
| 2 | Şema: sevkiyat satırı durumu | 8.20, 8.21 | `2026.4.8.php`, `install/index.php` `get_tables()` (yeni tablo yoksa gerekmez) |
| 3 | Tek yazma yolu `pg_shipment_record()` | — | yeni `includes/fn/shipping.php`, `update_order.php`, `includes/api/outbound/orders.php:212-283`, `includes/workspace/changes.php:2665-2681` (yalnız çağrı), `shipworks.php` (**dokunma**, soru) |
| 4 | Panel: firma seçimi, durum, uyarı kipi doğrulama, tagin id | — | `view_order.php:880-978, 3093-3144`, `assets/js/backend.src.js` + `.min.js` (ilgili bölüm) |
| 5 | Müşteri: e-posta alanları, teslim tetiği, zaman çizelgesi, misafir takip | — | `update_order.php:237-253`, `includes/fn/mail.php:752` (ekleme), `includes/fn/widgets.php:3945-3955, 4428-4447`, yeni widget alt bölümü |
| 6 | Toplu işlem | — | `edit_orders.php`, `view_orders.php:1140-1202` |
| 7 | Dış API ve webhook yükü | — | `includes/api/resources/orders.php:133, 566-726`, `includes/api/schema.php`, `includes/api/outbound/connectors/n11.php:749-791` |
| 8 (opsiyonel) | İlçe alanı + posta kodu–il tutarlılığı | 8.22 | `ship_tos`, `states.plate_code`, ödeme widget'ı |

## 2. Adımlar

### Faz 1 — Firma tanıma (K) — önce testler kırmızı, sonra yeşil
- **Yap:**
  1. `tests/shipping_test.php`'ye yeni beklentiler: `pg_shipping_carrier('12345678901', 'Standart Teslimat', 'yurtici')` → `yurtici` (üçüncü parametre: site varsayılanı); `'YURTİÇİ KARGO'` kodu → `yurtici`; `'Hepsijet'` → `hepsijet`; `'Trendyol Express'` → `trendyolexpress`; 11 haneli düz numara + boş kod + **varsayılan yok** → bugünkü gibi `ups` (bu test adı değişmeden kalır).
  2. `pg_shipping_carrier($number, $method_code, $default = '')`: sıra **(1) yöntem kodu tamamı, (2) kelimeleri, (3) `$default` (geçerli anahtar ise), (4) numara deseni.** Türkçe küçültme: `mb_strtolower` sonrası `İ`→`i` için `str_replace(array('İ','I'), array('i','ı'), …)` **önce** uygulanır (test: `'YURTİÇİ'`). Yeni girişler `pg_shipping_carriers()`'a: `hepsijet` (`https://www.hepsijet.com/gonderi-takibi/{code}`), `trendyolexpress`, `kolaygelsin`, `horoz`, `ceva`, `sendeo` — URL'leri siteden teyit et, teyit edilemeyeni **ekleme** (PR'a yaz). n11 kodları `HLZ`/`CEVA`/`BL` alias olarak (`n11.php:888`).
  3. Çağıran altı yer (`widgets.php:4311`, `view_order.php:934`, `templates/view_order.php:90`, `print_order.php:753`, `get_view_order_screen_content.php:830`, `order_bridge.php:425`) `ECOMMERCE_DEFAULT_TRACKING_PROVIDER` sabitini `$default` olarak geçer. `config(default).php:284-287` açıklaması gerçek davranışa uydurulur. `templates/view_order.php:87` "Tracking Number" → `lang()`.
- **Kanıt:** `php tools/test.php shipping` yeşil; `php tools/check_lang.php`; sandbox'ta `ECOMMERCE_DEFAULT_TRACKING_PROVIDER='yurtici'` + yöntem "Standart Teslimat" + 11 haneli numara → müşteri sipariş görünümünde Yurtiçi linki (önce: UPS). Sistem Durumu kartı tanınmayan değeri hâlâ uyarıyor.

### Faz 2 — Şema (8.20, 8.21)
- **Yap:** `upgrade_2026_4_8_shipments()` (8.20): `shipping_tracking_numbers` → `carrier VARCHAR(20) NOT NULL DEFAULT ''`, `tracking_url VARCHAR(255) NOT NULL DEFAULT ''`, `status VARCHAR(20) NOT NULL DEFAULT ''` (değerler kodda sabit: `created|in_transit|out_for_delivery|delivered|returned|exception`; ENUM değil, sürücü ekleyince ALTER gerekmesin), `status_at`, `shipped_at`, `delivered_at`, `created_at` `INT UNSIGNED NOT NULL DEFAULT 0`, `source VARCHAR(12) NOT NULL DEFAULT 'panel'`, `format_warning TINYINT NOT NULL DEFAULT 0`; mükerrer (`ship_to_id`,`number`) satırlarda en düşük id dışındakiler silinir, sonra `install_add_index(... UNIQUE)`; `shipping_methods.carrier VARCHAR(20) ''`. Backfill: mevcut satırlarda `shipped_at = ship_tos.ship_date` (sıfır tarih değilse ve bugünden ileri değilse — `'0000-00-00'` karşılaştırması, `''` **değil**), `created_at = shipped_at`.
  `upgrade_2026_4_8_delivered_trigger()` (8.21): `email_campaign_profiles.action` ENUM'una `order_delivered` (önce `SHOW COLUMNS … WHERE Field = 'action'` ile tip oku; idempotent MODIFY yardımcısı var mı `runner.php`'de bak, yoksa `install_modify_column` benzeri **sorup yapan** adım yaz).
- **Kanıt:** sandbox'ta migration iki kez; `tests/shipping_test.php`'e `pg_shipment_statuses()` saf listesi; `install_note()` metinleri.

### Faz 3 — Tek yazma yolu (O) — iptal kapısı değişmez
- **Yap:** yeni `includes/fn/shipping.php` (kapı sabiti, `functions.php` manifestine satır): `pg_shipment_record($order_id, $ship_to_id, $number, $opts)` → `carrier` (verilmemişse `pg_shipping_carrier()` ile çözülür), `tracking_url`, `source`, `shipped_at` (yeni satırda `time()`), `status='created'`; **UPSERT** (varsa UPDATE — DELETE+INSERT yok); `format_warning` = `pg_shipping_number_check($number, $carrier)` sonucu. `pg_shipment_delivered($ship_to_id, $time)` → `delivered_at`, `status='delivered'` ve **bir kez** `order.delivered` webhook + `order_delivered` kampanya tetiği.
  `update_order.php:97-132` listeyi `pg_shipment_record()`/silme ile eşler (silinen numara satırı DELETE); `:66-70` `just_delivered` artık `delivered_at` önceden 0 ise; `mp_order_apply_tracking()` ve `mp_order_mark_dates()` aynı yardımcıyı kullanır (n11 linki `tracking_url`'e; `Delivered` → `pg_shipment_delivered`). `shipworks.php` **dokunulmaz** (PR'da soru: aynı yola alınsın mı).
  `_order_has_shipped()` (`ecommerce.php:6990`) **değişmez**: boş olmayan numara = gönderildi; `format_warning` sayımı etkilemez (test).
- **Kanıt:** `tests/shipping_test.php`: `pg_shipping_number_check()` (TR firmaları için yalnız "rakam ve uzunluk 8–20" uyarı kipi; UPS `1Z` deseni; PTT/uluslararası UPU S10 `^[A-Z]{2}\d{9}[A-Z]{2}$` + mod-11 kontrol hanesi — RFC'deki örnekle test); sandbox: panelden numara gir → satırda `carrier/shipped_at/source=panel`; aynı numarayı tekrar kaydet → satır sayısı değişmez; numarayı sil → satır gider; teslim tarihi gir → `delivered_at` dolu, `api_webhook_queue`'da **tek** `order.delivered`; müşteri iptal denemesi numara varken 409/engel (önce-sonra aynı).

### Faz 4 — Panel (O)
- **Yap:** `view_order.php` gönderim bloğunda numara başına firma `<select>` (`pg_shipping_carriers()` + "Otomatik"), durum rozeti, `format_warning` için sarı uyarı (**kaydı engellemez**); tagin alanlarının id'si `ship_to_id_tracking_numbers_<ship_to_id>` (`:974-978`), JS `querySelectorAll`; link çözülemeyen numara salt okunur listede de görünür (`:937-940`). `pinegrap-ui-deseni` form reçetesi.
- **Devredilebilir:** evet (görev: dosya:satır, beklenen DOM, `.src/.min` ikisi).
- **Kanıt:** Playwright: iki alıcılı siparişte her iki tagin çalışıyor; firma seçimi kaydediliyor; uyarı görünüyor ama kayıt oluyor; `node --check backend.src.js`.

### Faz 5 — Müşteri tarafı (O)
- **Yap:** `update_order.php:241-245` mail-merge alanlarına `^^tracking_numbers^^`, `^^carrier_name^^`, `^^tracking_url^^` (çok paket: satır başına "firma — numara — link"); `mail.php:752` tetik listesine `order_delivered` (yalnız ekleme); TR başlangıç dökümündeki "Kargo Yola Çıktı" sayfasına **dokunma** (`data/backups/` kararı) — bunun yerine `install_note()` ile operatöre "kampanya sayfasına ^^tracking_url^^ ekleyin" notu. Widget: `__tracking_*` tokenları önce satırın `carrier`/`tracking_url`'ini, yoksa çözümü kullanır; zaman çizelgesi `shipped_at`/`delivered_at` doluysa onları, yoksa bugünkü mantığı (`widgets.php:4428-4447`). Misafir takip: sipariş görünümü widget'ı `order_number + billing_email` POST'unu kabul eder (`widgets.php:3945-3955` kapısına üçüncü yol), `waf_rate` kovasıyla 10/saat/IP, CSRF, yalnız gönderim bilgisi (kart/adres yok). Token listesi değişiyorsa `check_bindings`.
- **Kanıt:** sandbox: numara girince `email_recipients`/gövdede link var (DB'den oku; SMTP yok); teslimde `order_delivered` kampanyası tetiklendi; misafir sorgusu doğru/yanlış e-posta; `php tools/check_bindings.php`.

### Faz 6 — Toplu işlem (K–O)
- **Yap:** `edit_orders.php`'ye `mark_shipped` (seçili siparişlere firma + numara listesi) ve `view_orders.php`'ye CSV yükleme (`order_number,number,carrier`; `phpexcel` gerekmez, `fgetcsv`), satır satır `pg_shipment_record()`; sonuç ekranı (kaç başarılı, hangi satır neden atlandı). Rol kapısı `USER_MANAGE_ECOMMERCE`.
- **Kanıt:** CSV ile 3 satır (1 geçersiz sipariş no) → 2 kayıt + 1 hata satırı; tokensız POST reddi.

### Faz 7 — Dış API ve webhook yükü (K–O)
- **Yap:** `api_orders_ship()` `carrier` anahtar listesine göre doğrular (geçersiz → 422), yanıtta `carrier_name`, `tracking_url`, `format_warning`; `api_order_shipments()` → `carrier`, `carrier_name`, `tracking_url`, `status`, `shipped_at`, `delivered_at`, `source`; `schema.php` güncellenir; `order.shipped`/`order.delivered` yükü aynı alanlar. n11 il/ilçe: `n11.php:790-791` ödeme adımı sözleşmesine (`city`=ilçe, `state`=il, `erp/accounts.php:158-168`) uydurulur — **yalnız yeni içe aktarmalar**, eski kayıt düzeltilmez (PR'da soru).
- **Kanıt:** `php tools/check_api_schema.php`; sandbox curl: `POST /orders/{id}/ship` geçerli/geçersiz carrier; `GET /orders/{id}` sevkiyat alanları; webhook kuyruk satırı yükü.

### Faz 8 (opsiyonel, 8.22) — İlçe ve posta kodu–il
- `ship_tos.district`, `states.plate_code` (81 il için sabit liste koddan backfill), `pg_tr_postcode_matches_state($zip, $state)` (ilk iki hane = plaka) **uyarı kipinde**; ödeme widget'ında alan **layout'ta varsa** okunur (A5 kuralı: zorunluluk koda gömülmez). İlçe/mahalle veri seti **bu planda yok** (kaynak+lisans kararı).

## 3. Varsayılan kararlar
- "Yerel doğrulama" = (a) takip no biçim denetimi **uyarı kipinde** + (b) posta kodu–il (Faz 8); barkodla paket–sipariş eşlemesi ve ilçe verisi sonraki tur.
- Firma `shipping_tracking_numbers.carrier`'da; `orders.tracking_company`'ye dokunulmaz.
- ShipWorks yolu değişmez; `ERP_AUTO_INVOICE_ON` okunmaması ERP'ye sorulur.
- Kargo firması API sürücüleri (oluştur/etiket/durum) bu planda yok; `status` kolonu ve `source='carrier'` değeri onlar için hazır.

## 4. Elle doğrulanacaklar (Erdal)
- Gerçek takip URL'lerinin canlılığı (7 firma); n11 canlı siparişinde il/ilçe; kampanya e-postasının gerçek SMTP ile gelişi; mevcut sitelerde kampanya sayfasına `^^tracking_url^^` ekleme.

## 5. PR açıklaması şablonu
Ne değişti · Etiketler · `_order_has_shipped()` değişmedi (test adı) · Golden/sandbox senaryoları · Denetim çıktıları · **Doğrulayamadıklarım** · Sorular: `orders.tracking_company`, ShipWorks, n11 eski kayıtlar, `ERP_AUTO_INVOICE_ON`.
