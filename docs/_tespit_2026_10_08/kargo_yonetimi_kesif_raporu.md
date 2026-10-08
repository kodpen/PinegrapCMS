# Kargo (sevkiyat) yönetimi — mevcut altyapı ve eksikler (keşif raporu)

Yalnız kod okuması; hiçbir dosya değiştirilmedi, çalışan örnek kurulmadı (yalnız `pg_shipping_carriers()` desenleri PHP CLI'da tek başına denendi). Yollar `pinegrap/` köküne göre. Verilmiş kararlardaki `orders.tracking_company`, `orders.notes`, `orders.tracking_code` maddeleri ve `get_express_order.php` hesaplaması eksik diye yazılmadı.

**Kısaca:** Bugün kargo yönetimi "takip numarasını elle gir, müşteriye link göster" düzeyinde. Türk kargo firmalarıyla API bağı yok, kargo durumunu sorgulayan arka plan işi yok. İki ana bulgu: (1) yöntem kodu firmayı söylemediğinde 11/12 haneli düz numaralar UPS/FedEx diye tanınıyor; (2) "gönderildi" kavramının sistemde dört farklı tanımı var.

## 1. Mevcut durum

### Tablolar (`data/backups/turkish_default/sql.sql` + migration'lar)
- **`shipping_tracking_numbers`** (sql.sql:3637): `id, order_id, ship_to_id, number varchar(100)`. MyISAM. UNIQUE yok; firma, durum, oluşturma zamanı, kaynak kolonu yok.
- **`ship_tos`** (sql.sql:3145): ad/adres/telefon, `shipping_method_id/code`, `shipping_cost`, `zone_id`, `address_verified`, `ship_date`, `delivery_date`, `packages`, `arrival_date*`. İlçe/mahalle kolonu yok.
- **`shipping_methods`** (sql.sql:4339): ağırlık/ürün/taban ücret alanları; `service` enum yalnız USPS/UPS/FedEx; `realtime_rate`; `carrier_title`, `carrier_vkn` (2026.4.4.php:2526-2528, e-arşiv için). Yöntemi `pg_shipping_carriers()` anahtarına bağlayan kolon yok.
- `zones`, `shipping_methods_zones_xref`, `zones_countries_xref`, `zones_states_xref`, `shipping_rates`, `shipping_cutoffs`, `arrival_dates`, `ship_date_adjustments`, `shipping_delivery_dates`.
- **`countries`** (sql.sql:1897): `zip_code_required`. **`states`** (sql.sql:4599): TR dökümünde 81 il (country_id=233); `code` plaka değil, il adı.
- **`verified_shipping_addresses`** (sql.sql:2979): operatörün elle tuttuğu liste.
- **`order_items`**: `shipped_quantity`, `show_shipped_quantity`. **`products`**: `weight/length/width/height`, `preparation_time`, `free_shipping`, `extra_shipping_cost`.
- **`erp_waybills`** (2026.4.4.php:2378): `carrier_title`, `carrier_vkn`, `plate`, `driver_*`, `ship_to_district/city/state`; takip numarası kolonu yok.
- `marketplace_accounts.shipment_template` (2026.4.4.php:499).

### Dosya → sorumluluk
| Dosya | Ne yapıyor |
|---|---|
| `includes/fn/ecommerce.php:5537-5606` `pg_shipping_carriers()` | 8 firma (yurtici, surat, aras, mng, ptt, ups, fedex, usps); ad, takma ad, URL, desen. TR firmalarında `patterns` bilerek boş. |
| `ecommerce.php:5636` `pg_shipping_carrier()` | Firma tanıma: (1) yöntem kodunun tamamı, (2) kelimeleri, (3) numara deseni. |
| `ecommerce.php:5713 / :5735` | `get_shipping_tracking_url()`, `get_shipping_carrier_name()` |
| `ecommerce.php:6990` `_order_has_shipped()` | Boş olmayan en az bir takip numarası → gönderildi. |
| `update_order.php:21` `update_order()` | Takip no, ship/delivery_date, shipped_qty yazan tek ortak yol; webhook + otomatik e-posta tetikler. |
| `view_order.php:880-978, :3093-3144` | Panelde gönderim başına giriş (tagin, virgülle ayrılmış numaralar). |
| `api.php:11728` `update_order` | Panel AJAX ucu |
| `includes/api/resources/orders.php:566` `api_orders_ship()` | Dış API: numara ≤100 kr, isteğe bağlı `carrier`, `notify`. |
| `includes/api/resources/orders.php:133` `api_order_shipments()` | API çıktısı; firma adı/URL dönmüyor. |
| `includes/api/outbound/orders.php:212` `mp_order_apply_tracking()` | Pazaryeri numarasını doğrudan INSERT, `order.shipped` webhook. |
| `includes/api/outbound/orders.php:269` `mp_order_mark_dates()` | Pazaryeri siparişinde tarihleri doldurur. |
| `includes/api/outbound/connectors/n11.php:749-753, 864, 892` | n11 kargo alanları, durum eşleme, toplama talebi. |
| `shipworks.php:403-545` | ShipWorks `updateshipment`; `update_order()` kullanmaz. |
| `shipping.php` | UPS/USPS/FedEx gerçek zamanlı ücret; `verify_address()` (:3115). |
| `includes/workspace/changes.php:2665-2681` | AI çalışma alanı `update_order()` üzerinden yazar. |
| `includes/fn/widgets.php:4293-4340` | Sipariş görünümü `__tracking_*` tokenları; `ECOMMERCE_DEFAULT_TRACKING_PROVIDER` yedeği yalnız burada. |
| `includes/fn/widgets.php:4428-4447 / :4496` | Zaman çizelgesi / iptal düğmesi görünürlüğü |
| `includes/templates/view_order.php:87-95`, `print_order.php:753`, `get_view_order_screen_content.php:830` | Takip linki gösterimleri |
| `includes/erp/order_bridge.php:425` `erp_order_shipment()` | Faturaya giden gönderim tarihi ve firma |
| `includes/erp/waybills.php:493 / :657` | İrsaliye geri yazımı / siparişten irsaliye |
| `includes/fn/system_status.php:2840-2876` | Tanınmayan `ECOMMERCE_DEFAULT_TRACKING_PROVIDER` uyarısı |

### Akış
1. **Ödeme:** adres alınır; `submit_order.php:6036-6041` `ship_date`/`delivery_date`'e **tahmini** tarih yazar.
2. **Hazırlık:** `print_packing_slip.php`, `view_shipping_report.php`; ShipWorks dışa aktarımı `exported` durumuna çeker (`shipworks.php:386`).
3. **Sevk:** operatör `view_order.php`'de numara girer (ya da API / n11 / ShipWorks).
4. **`update_order()`:** listeyi INSERT+DELETE ile değiştirir (:97-132); yeni numara → `shipped=true` (:113); `order.shipped` webhook (:216-233); `create_auto_email_campaigns('order_shipped')` (:237-253).
5. **Gösterim:** widget/şablon/yazdırma `get_shipping_tracking_url()`.
6. **Teslim:** operatör `delivery_date` girer; önceden boşsa `order.delivered` webhook (:66-70, :203-212). n11 `Delivered` → tarih kendiliğinden.
7. **İptal kapısı:** `process_order_cancellation()` (ecommerce.php:6800) müşteri yolunda `_order_has_shipped()`.

## 2. Eksikler ve zayıflıklar

**Firma tanıma / takip linki**
1. Yöntem kodu firmayı söylemiyorsa 11 haneli numara UPS (ecommerce.php:5580), 12/15 haneli FedEx (:5587) çıkıyor → müşteri yanlış firma sayfasına gidiyor (desen CLI'da doğrulandı; TR firmalarının hane sayıları doğrulanamadı).
2. `ECOMMERCE_DEFAULT_TRACKING_PROVIDER` yedeği yalnız yöntem kodu tamamen boşsa devreye giriyor (widgets.php:4311); config açıklaması (`data/config(default).php:284-287`) "Standart Teslimat" gibi kodlar için yazılmış. Panel (view_order.php:934), şablon (templates/view_order.php:90), `print_order.php:753` yedeği hiç kullanmıyor.
3. `"YURTİÇİ"` → `mb_strtolower` noktalı `i̇` üretir, takma adla eşleşmez (test edildi).
4. Hepsijet, Trendyol Express, Kolay Gelsin, Horoz, Ceva, Borusan, Sendeo yok; n11 HLZ/CEVA/BL kodları kullanıyor (n11.php:888).

**Veri kaydı**
5. Takip numarasında biçim doğrulaması yok: panel/`update_order.php:89-110` yalnız trim; API yalnız uzunluk (orders.php:659); `shipworks.php:513` uzunluğa bile bakmıyor.
6. Firma numara bazında tutulmuyor; her seferinde `ship_tos.shipping_method_code`'dan tahmin. API ve n11 firmayı bu alana yalnız boşsa yazıyor (orders.php:716-726, outbound/orders.php:253-258). n11'in hazır linki atılıyor (n11.php:752 okunuyor, saklanmıyor).
7. Tarihler: `ship_date`/`delivery_date` tahminle dolu; gerçekleşen için kolon yok → zaman çizelgesi tahmini tarih geçince "Delivered" yazıyor (widgets.php:4440-4446); `update_order.php:66-70` `just_delivered` önceden dolu tarihte çalışmıyor → `order.delivered` webhook'u neredeyse hiç gitmez (canlıda doğrulanamadı); `mp_order_mark_dates()` `order.delivered` göndermiyor.

**"Gönderildi" — dört tanım**
8. (a) takip numarası var: `_order_has_shipped`, API, iptal kapısı; (b) `shipped_quantity >= quantity`: `view_orders.php:442-460`, `view_shipping_report.php:96-110`, pano (`api.php:5140+`); (c) `ship_date <= bugün`: `erp_order_shipment()` (order_bridge.php:440-445), widget zaman çizelgesi (widgets.php:4436); (d) n11 durumu.

**Müşteri e-postası**
9. Yalnız `order_shipped` tetikli otomatik kampanya varsa gidiyor (TR dökümünde 9 numaralı "Kargo Yola Çıktı" profili hazır, sql.sql:5038). Mail-merge alanı yalnız `^^order_number^^` (update_order.php:241-245); takip no/firma/link yok. Hazır sayfa içeriği yalnız "Siparişiniz yola çıkmıştır!" (pregion 2903). Teslim e-postası yok; `mail.php:752` tetik listesinde `order_delivered` yok.

**Diğer yazma yolları**
10. `shipworks.php:403-545` `update_order()` dışı: webhook, e-posta, ship_date, log yok (karar mı?).
11. `mp_n11_request_collection` kayıtlı (n11.php:53) ama hiçbir yerden çağrılmıyor.

**Panel**
12. Çok alıcılı siparişte tüm alanların id'si aynı `ship_to_id_tracking_numbers` (view_order.php:974-978); `querySelector` yalnız ilkini başlatıyor (tarayıcıda denenmedi). Linki bulunamayan numara salt okunur listede görünmüyor (:937-940).
13. Toplu işlem yok: `edit_orders.php` yalnız `export_orders_for_parasut`, `remove_card_data`, `delete`, `cancel`. CSV ile toplu takip no yükleme / toplu "gönderildi" yok.

**Kapsam dışı kalanlar**
14. `pg_cron_jobs()` (cron.php:121) içinde kargo durum sorgusu yok.
15. Misafir müşteri sonradan takip edemiyor (widgets.php:3945-3955); numara+e-posta ile sorgu sayfası yok.
16. Adres doğrulama yalnız ABD: `verify_address()` ülke US değilse hiçbir şey yapmıyor (shipping.php:3173); USPS Web Tools ucu (:3224, :539, :1676) — USPS Web Tools'un 2026 başında emekliye ayrıldığı bilgisi dış kaynaklı, **doğrulanmalı**. UPS (:1994) ve FedEx (:876) eski uçlarının durumu doğrulanamadı.
17. `verified_shipping_addresses` gerçek doğrulama değil; `address_verified` set etmiyor.
18. Ödeme adımında posta kodu/telefon biçim kontrolü yok (shipping_address_and_arrival.php:86-112, billing_information.php:89); teslimat telefonu zorunlu değil; posta kodu–il tutarlılığı yok.
19. Adres alanları ters eşleniyor: ödeme adımı TR'de `city`=ilçe, `state`=il (`includes/erp/accounts.php:158-168`); n11 tersini yapıyor (n11.php:790-791) → n11 siparişinde cari ve irsaliyede il/ilçe yer değiştirir (canlıda doğrulanmadı).
20. `includes/templates/view_order.php:87` "Tracking Number" `lang()`'sız. `ERP_AUTO_INVOICE_ON = 'order_shipped'` tanımlı (init.php:668) ama okunmuyor (ERP faz sınırı olabilir — soru).

## 3. Yerel doğrulama için hazır yapı taşları
- `pg_shipping_carriers()` tek merkez; `patterns` alanı var (UPS/FedEx/USPS dolu, TR boş). "Seçilen firmanın biçimine uyuyor mu" kontrolü buraya `validate`/`length` alanı olarak girebilir. TR firmalarının biçimleri kodda yok; firma dokümanından teyit gerekir.
- PTT/uluslararası: UPU S10 (`AA123456789TR`) mod-11 kontrol hanesi yerelde doğrulanabilir (genel bilgi; kodda yok).
- Posta kodu: `erp_edoc_postcode_valid()` (includes/erp/edoc/registry.php:575) TR 5 hane. İlk iki hane = plaka; `states.code` plaka tutmadığı için kolon/sabit gerekir.
- Telefon: `erp_edoc_isbasi_phone()` (includes/erp/edoc/isbasi.php:2014) `0XXXXXXXXXX` normalizasyonu.
- TCKN/VKN: `erp_tax_number_check()` (includes/erp/accounts.php:283).
- **Uyarı:** bu üç fonksiyon ERP modülünde, modül açıkken yükleniyor; ödeme adımında kullanılacaksa `includes/fn/` altına taşınmalı (mimar kararı).
- İl: 81 il `states`'te (yalnız TR dökümü). İlçe/mahalle tablosu ve veri dosyası **yok**.
- Desi: `products.length/width/height/weight` var; hesaplanmıyor.
- Tekilleştirme: `update_order.php:97` adres+numara; `mp_order_apply_tracking` sipariş+numara; DB UNIQUE yok.

## 4. Türk kargo firmaları — bugün
| Firma | Takip linki | Ad | API (oluştur/durum/etiket) |
|---|---|---|---|
| Yurtiçi, Aras, MNG, PTT, Sürat | var | var | yok |
| Hepsijet, Trendyol Express | yok | yok | yok |

n11'den numara ve ad çekiliyor; toplama talebi bağlı değil. Trendyol/Hepsiburada bağlayıcısı yok (`connectors/` altında yalnız `base.php` ve `n11.php`). Link URL'lerinin canlılığı doğrulanmadı.

## 5. Önerilen şema taslağı (yalnız öneri)
`upgrade_2026_4_8_shipping()` benzeri alt adım:
- `shipping_tracking_numbers`: `carrier VARCHAR(20)`, `tracking_url VARCHAR(255)`, `status ENUM('','created','in_transit','out_for_delivery','delivered','returned','exception')`, `status_at`, `shipped_at`, `delivered_at` (INT UNSIGNED), `source ENUM('panel','api','marketplace','shipworks','carrier')`, `format_valid TINYINT`, `desi DECIMAL(8,2)`, `created_at`; `UNIQUE(ship_to_id, number)` (mükerrerler önce temizlenmeli).
- `shipping_methods.carrier VARCHAR(20)` — yöntemi firmaya açıkça bağlar.
- `ship_tos`: `district`, `neighborhood`, `shipped_at`, `delivered_at` (tahmini `ship_date`/`delivery_date` dokunulmadan kalır).
- Yeni `tr_districts` (`id, state_id, name, postcode_prefix`) + `states.plate_code CHAR(2)`; veri kaynağı ve lisans gerekir.
- Yeni `shipping_tracking_events` — yalnız firma API'si eklenirse.
- `email_campaign_profiles.action` enum'una `order_delivered`; `update_order()` mail alanlarına `tracking_numbers`, `carrier_name`, `tracking_url`.

## 6. Risk ve bağımlılıklar
- `_order_has_shipped()` hassas kapı: müşteri iptali (ecommerce.php:6800), havale otomatik iptal işi (job.php:287), iptal düğmesi (widgets.php:4496), API iptal 409 (orders.php:503-505), n11 iptal (outbound/orders.php:319). Biçim doğrulaması "geçersiz numarayı saymaz" olursa iptal kapısı açılır → doğrulama uyarı kipinde kalmalı.
- `update_order()` listeyi tamamen değiştiriyor (:131 DELETE); yeni kolonlar taşınmazsa her kayıtta silinir → UPDATE/INSERT ayrımı gerekir.
- Beş yazma yolu: panel, `api.php`, API resource, workspace/changes.php, n11, ShipWorks (son ikisi `update_order()` dışı). `check_api_schema.php` ve `api_order_schema()` güncellenmeli.
- Fallback/desen değişikliği widget tokenları, sistem durumu kartı ve config belgesiyle birlikte değişmeli.
- ERP: `erp_order_shipment()` ve irsaliye geri yazımı `ship_date`'e bağlı; `shipped_at` eklenirse e-arşiv "gönderim tarihi" hangisinden okunacak — ERP kararı.
- Dokunulmayacaklar: `get_express_order.php`/`get_order_preview.php` hesaplamaları, legacy custom style şablonları.
- Doğrulanmayanlar: TR numara biçimleri ve link canlılığı, UPS/FedEx uçları, çok alıcılı tagin hatası, n11 il/ilçe terslemesi.
