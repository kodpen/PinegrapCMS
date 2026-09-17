# Pinegrap CMS — Claude Bağlam Dosyası

## Proje Hakkında

Pinegrap, 2017'den beri Erdal Güral (Kodpen) tarafından geliştirilen PHP tabanlı bir CMS. LiveSite'dan fork edilmiş, 2019 LiveSite güncellemesi entegre edilmiş. Bootstrap 5 + jQuery kullanan monolitik bir yapı. PHP 7.0–8.5 uyumlu.

- Repo: `dev/pinegrap/`
- Dil dosyası: `includes/local/tr.json`
- Config dosyası: `data/config.php` (runtime'da `CONFIG_FILE_PATH` sabiti)

---

## Temel Kurallar

### Dil / Çeviri
- Tüm kullanıcıya görünen string'ler `lang('...')` ile sarılmalı.
- `lang()` argümanı `includes/local/tr.json` içinde key olarak **mutlaka** bulunmalı.
- Yeni string eklendiğinde `tr.json`'a da eklenmeli.
- Aynı kural görsel tasarımcının JavaScript'i için `_sdT('...')` ile geçerlidir
  (aşağıda).

### Görsel Tasarımcı Metinleri — `_sdT()` (2026-09-16)

`assets/js/style_designer.js` Türkçe-öncelikli yazılmıştı ve yazılım dili
İngilizceye alınınca Türkçe kalıyordu; sürüklenen widget'ın başlangıç
içeriği de. Gerekçe `docs/degisiklikler.md`, plan ve araç
`docs/_plan_tasarimci_i18n.md` + `docs/_tools/designer_i18n.php`.

- **Operatörün ya da ziyaretçinin okuyacağı hiçbir metin bu dosyada düz
  literal olamaz** — panel etiketi, `row()`/`sect()` başlığı, toast, diyalog,
  `customName`, placeholder, `aria-label`, palet bileşeninin örnek içeriği,
  başlangıç ağacının `text:` değeri. Hepsi `_sdT('English text')`; anahtar
  `lang()` ile aynı sözleşmede İngilizce kaynak metindir ve **`tr.json`'da
  bulunur**. tr.json anahtar denetimi bu dosyayı da kapsar.
- **Anahtar tek tırnaklı literal'dir.** `_sdT("…")`, `_sdT(değişken)`,
  `_sdT('a' + b)` yok: sunucu (`pg_designer_i18n_keys()`,
  `includes/fn/designer.php`) dosyayı `_sdT('…')` kalıbı için tarar ve
  yalnız bulduğu anahtarları `lang()`'dan geçirip `sdDesign.i18n` olarak
  gönderir (`includes/designer_screen.php`). Dil İngilizceyse harita boştur;
  `_sdT` anahtarı olduğu gibi döner. Tarama sonucu
  `data/temp/designer_i18n_keys.json`'da mtime:size damgasıyla önbelleklidir.
- **Değişken `{var}` / `{var:N}` ile girer**, anahtara gömülmez; `_sdT`
  çıktısı escape edilmez, HTML'e giren yerde `esc(_sdT(...))`.
- **`_pgT(key, fallback)` kaldırıldı**; Türkçe yedeği kodda taşıyan kalıp
  geri gelmez.
- **PHP varsayılan ağaçları ve widget yedek metinleri `lang()`**;
  JS'teki ikizi (`_swTypeDefaults`, `_emptyDefault`) aynı anahtarla `_sdT()`.
- Denetim: `php docs/_tools/designer_i18n.php check` (tr.json'da olmayan
  anahtar, kalan Türkçe literal, HTML içine gömülü metin, Türkçe yorum, PHP
  ağaçları). Dönüşüm tabloyla: `extract` → `docs/_tools/designer_i18n_map.tsv`
  `en` sütunu → `apply`. Tablo TR→EN terminolojinin tek kaynağıdır.
- **Türkçe tespiti `tr.json`'ın kendisini sözlük sayar.** Diyakritik taşımayan
  kelimeler ("Altta", "Standart", "Kurumsal") elle yazılmış listeden kaçıyordu;
  `has_turkish()` artık Türkçe değerlerde geçip İngilizce anahtarlarda geçmeyen
  her kelimeyi Türkçe sayar. **Yorumlarda bu sözlük kullanılmaz**
  (`has_turkish_prose()`): "chrome", "strike", "stem" gibi kelimeler
  `tr.json`'ın Türkçe tarafında da geçiyor. Yorumda tırnak içi metin ve yol
  (`/siparis`, `'Kayıt bulunamadı.'`) yorumun **konuştuğu şeydir**, dili değil —
  denetimden önce ayıklanır.
- **`skip` yalnız üç sınıf içindir:** tanıma sözlükleri (SEO genel bağlantı
  metni listesi), ham veritabanı değerleri (`complete / exported / paid /
  tamamlandı`) ve kodun eşleştirdiği eski saklanmış değerler
  (`'Kayıt bulunamadı.'`, `'Faz 1'`). Palet bileşeninin örnek içeriği —
  örnek ad, adres, e-posta, fiyat — `skip` **değildir**, çevrilir.

### Kod İçi Yorum Kuralları (YASAK LİSTESİ — istisnasız)

Ürün dosyaları müşteriye dağıtılır; yorumlar ürünün parçasıdır.

1. **Kod içine İngilizce dışında yorum eklemek YASAK.** PHP, JS, CSS, SQL —
   string içine gömülü CSS/JS yorumları dahil. (tr.json değerleri ve `lang()`
   string'leri yorum değildir, Türkçe olmaları normaldir.)
2. **AI/asistan izi taşıyan yorum eklemek YASAK.** Şunların hiçbiri bir kod
   yorumunda geçemez: "kullanıcı kararı", "kullanıcı isteği", "saha geri
   bildirimi", "kullanıcı onayıyla", "Faz 1/Faz 2/Faz 3", plan dosyası
   referansı, "dev veritabanında çalıştırıldı", "X dersi" gibi oturum/süreç
   referansları. Bunlar yazılımı AI'ın geliştirdiğinin kanıtı gibi durur ve
   müşteriye giden dosyada yer alamaz.
3. Yorum **klasik geliştirici yorumu** olur: kodun NE yaptığını ve teknik
   olarak NEDEN öyle yapıldığını anlatır (kilitler, yarışlar, geriye dönük
   uyumluluk, güvenlik kapıları). Karar tarihçesi, kim istedi, hangi konuşmada
   çıktı — bunların yeri `docs/degisiklikler.md`'dir, kod değildir.
4. Bu kural geriye dönük de uygulanır: dokunulan bir dosyada eski
   Türkçe/AI-izli yorum görülürse aynı değişiklik içinde İngilizce klasik
   yoruma çevrilir.

#### `lang()` Sözdizimi

**Basit kullanım:**
```php
lang('Page Regions')
// tr.json'dan karşılığını döner, yoksa İngilizceyi döner
```

**Değişkenli kullanım:**
```php
lang(['string' => '{var:1} is required', 'vars' => lang('Name')])
// → "Ad gereklidir"

lang(['string' => '{var:1} match{suffix:1} found in {var:2} item{suffix:2}.', 'vars' => [5, 3], 'suffix' => ['es', 's']])
// → "5 matches found in 3 items."
```

**Değişken yer tutucuları:**
- `{var:1}`, `{var:2}`, ... → sıralı değişkenler (`vars` dizisi 0-indexed, yer tutucu 1-indexed)
- `{var}` → tek değişken için kısayol (`{var:1}` ile aynı)

**Büyük/küçük harf modifier'ları:**
- `{var:1|c}` → Title Case (`mb_convert_case` ile, UTF-8 güvenli — Türkçe İ/ı için önemli!)
- `{var:1|u}` → UPPER CASE
- `{var:1|l}` → lower case
- `{var|c}`, `{var|u}`, `{var|l}` → tek değişken kısayolları

**Neden önemli (Türkçe):** Türkçe çeviride kelimeler cümle ortasında küçük, cümle başında büyük olabilir. Aynı değişkeni farklı konumlarda kullanan string'lerde `|c` modifier'ı ile dil kuralına uygun büyük harf dönüşümü otomatik sağlanır. `mb_convert_case` Türkçe i→İ dönüşümünü de doğru yapar (ASCII `toUpperCase` yapmaz).

**Suffix (çoğul eki) kullanımı:**
```php
lang(['string' => '{var:1} record{suffix:1}', 'vars' => $count, 'suffix' => ($count == 1 ? '' : 's')])
// 1 → "1 record" / 5 → "5 records"
```

**Dil bilgisi sorgusu:**
```php
lang(['info' => true])
// Aktif dil kodunu döner: 'tr', 'en', vb.
```

**tr.json key kuralı:** Key her zaman **İngilizce kaynak string** olur (değişken yer tutucuları dahil). Değer Türkçe karşılığı. Değişkenler key'de olduğu gibi korunur:
```json
"{var:1} is required": "{var:1} gereklidir",
"{var:1|c} is required": "{var:1|c} gereklidir"
```

### Dosya Başlığı (kural)

**Pinegrap için yazılan dosya LiveSite başlığı taşımaz.** Miras alınan
dosyalardaki "Originally developed as LiveSite…" bloğu yalnız onlara aittir;
yeni yazılan her PHP/JS dosyası — bugün `includes/` altındaki her şey
(üçüncü taraf kütüphaneler hariç), `waf.php` / `waf_ranges_job.php` /
`view_waf_log.php`, `manifest.php` / `manifest_icon.php` / `sw.js`, dosya
yöneticisi (`view_folders.php`, `view_folder_and_files.php`,
`view_folder_and_files_f.php`), dış API (`integration.php`, `api_settings.php`,
`api_docs.php`, `api_sync_job.php`, `api_webhook_job.php`,
`marketplace_settings.php`) — şu başlıkla açılır:

```php
<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * (dosyanın kendi açıklaması, varsa)
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
```

Yeni dosya açarken bunu kopyala; `PineGrap` değil `Pinegrap`. Üçüncü taraf
kütüphanelerin (`includes/phpmailer`, `stripe`, `iyzipay-php`, `phpexcel`,
`boxpacker`) başlıklarına dokunulmaz.

### Dosya Yerleşimi (kural)

**Kökte yalnız URL ile çağrılan dosya durur.** Arayüzü olmayan, doğrudan
adreslenmeyecek, fonksiyon/yardımcı içeren PHP dosyaları `pinegrap/includes/`
altına konur:

| Ne | Nereye |
|---|---|
| Birlikte bir altsistem oluşturan dosyalar | `includes/<altsistem>/` — örn. `includes/api/`, `includes/migrations/` |
| O altsistemin içinde ayrı bir katman | `includes/<altsistem>/<katman>/` — örn. `includes/api/resources/` |
| Tek başına duran, farklı yerlerden çağrılabilecek yardımcı | doğrudan `includes/` içinde — örn. `includes/authentication.php`, `includes/db_guard.php` |
| Üçüncü taraf kütüphane | `includes/<kütüphane>/` — örn. `includes/phpmailer/` |

**Her include dosyasının başında kapı olur.** Giriş noktasının tanımladığı
sabit yoksa dosya hiçbir şey çalıştırmaz:

```php
if (!defined('PG_API_ENTRY')) {
	exit;
}
```

Panelden de çağrılan bir dosya iki sabiti birden kabul eder
(`if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) { exit; }`).
Migration dosyalarındaki `INSTALL_OR_UPDATE` kapısı aynı kalıptır.

**Kökteki mevcut include dosyaları taşınmaz.** Kökte bu kurala uymayan ~85
include dosyası var (`get_*.php` içerik üreticileri, `*_f.php` ekran
yardımcıları, `seo*.php`, `shipping.php`, `chat.php`, üçüncü taraf
`pclzip.lib.php` / `excel_reader2.php` vb.). **Orada kalacaklar** — taşıma işi
rafa kaldırıldı, sebebi güvenlik: dosya bütünlüğü sistemi bunları izliyor
(aşağı bak). Kural **yeni yazılan koda** uygulanır.

**Dosya bütünlüğü ile ilişkisi (önemli):** `includes/` altına yeni bir alt
sistem klasörü eklendiğinde bütünlük kapsamı kendiliğinden genişler — üreteç
`includes/` ağacının tamamını yürür. Ama `includes/` **dışında** yeni bir
klasör açılırsa, `_software_create_hash.php` içindeki
`hashed_subdirectories()` dizisine eklenmediği sürece o klasör **hiç
denetlenmez**. `data/` (yapılandırma, önbellek, yedek) ve `install/`
(operatörler kurulumdan sonra siliyor) bilerek kapsam dışıdır.

### `functions.php` Bölündü — `includes/fn/` (2026-09-12)

`functions.php` 3 MB / 63 000 satır / 782 fonksiyona ulaşmıştı: FTP
düzenleyicide açılmıyor, diff okunmuyor, yarım kalan bir yükleme siteyi
düşürüyordu. Planı `docs/_plan_functions_bolme.md` (Adım 2a, eager manifest);
uygulaması **mekanik**: tokenizer her üst düzey fonksiyonu (önündeki yorumla)
olduğu gibi kesip modüle taşıdı, sıra korundu, hiçbir gövde değişmedi.
`functions.php` artık 70 satırlık bir **manifest**: iki eski `require`,
`PG_FUNCTIONS_DIR` sabiti ve 24 `require_once`. Sınıf yok, namespace yok,
lazy yükleme yok — 313 giriş noktasının hiçbirine dokunulmadı.

| Modül (`includes/fn/`) | İçerik |
|---|---|
| `core` | db*, escape/h/e, JSON, tarih-zaman, `lang()`, `truncate`, mime, HTTP yanıtları, `array_key_last` polyfill'i (**ilk yüklenir**) |
| `auth` | giriş, parola, beni-hatırla/cihaz limiti, köprü (`pg_code_version`…), CAPTCHA, giriş kısıtlama, `validate_*_access`, `initialize_user`, erişim denetimleri |
| `forms` | `select_*` üreticileri, seçenek listeleri, form verisi hazırlama, referans kodları, ön yüz ekran yükleyicileri |
| `ecommerce` | sipariş/sepet kalemi, teklifler, vergi, bölgeler, stok/rezervasyon, hediye kartı, barkod, ürün kopyalama, sipariş iptali |
| `seo` | yapılandırılmış veri, OG, robots, sitemap, etiket bulutu, ziyaretçi/form görüntüleme özetleri, performans izleyici |
| `content` | menü içeriği, gönderilmiş form içeriği, katalog arama/URL, görsel id'leri (`$image_ids_in_array` globali burada), `render_layout` |
| `calendar` · `editor` · `mail` · `contacts` | takvim · WYSIWYG/CodeMirror/görsel düzenleyici · PHPMailer + `email()` + bildirim e-postaları · `merge_contacts` |
| `designer` | sistem stilleri, ağaç doğrulama/genişletme, düğüm/bileşen render, sayfa/stil kaydetme, CSS çıktısı |
| `widgets_catalog` · `widgets_cart` · `widgets_express_order` · `widgets` | sistem widget'ları (katalog · sepet · `_eo_*` + sipariş görünümü · diğerleri) |
| `custom_form` · `files` · `cron` · `tour` | `pg_cf_*` + form gönderimi · dosya adı/yükleme sınırları · zamanlanmış işler · turlar (`PG_TOUR_SHELL_KEY` burada) |
| `output` | `output_header/footer/menu/toolbar`, `pg_page_shell`, lisans, varlık sürümleme |
| `system_status` | sistem durumu kartı ve denetimleri, önbellek temizleme, dosya bütünlüğü, yazma izinleri, changelog okuma, tablo onarımı |
| `image` · `parasut` · `update` | görsel işleme · Paraşüt · güncelleme kanalı, cURL TLS, arşiv açma |

Kurallar:

- **`dirname(__FILE__)` / `__DIR__` modülde yazılım dizini değildir**; taşınan
  kodda ikisi de `PG_FUNCTIONS_DIR` oldu (106 yer). Modüle yeni fonksiyon
  yazarken yol için `PG_FUNCTIONS_DIR . '/data/temp/...'` kullan. Kökteki
  dosyalarda ve `init.php`'de `dirname(__FILE__)` aynen kalır.
- **Yeni fonksiyon konusuna göre modüle yazılır**, `functions.php`'ye değil;
  `functions.php`'ye eklenen kod da çalışır ama dosyanın yeniden büyümesidir.
  Modül kapısı: `if (!defined('PG_FUNCTIONS_DIR')) { exit; }`.
- **`use PHPMailer\...` yalnız `mail.php`'de** geçerli. Eskiden dosya kapsamlı
  olduğu için 49.558. satırdan sonraki her niteliksiz `Exception`
  `PHPMailer\PHPMailer\Exception`'a çözülüyordu; bölünmeyle
  `pg_purge_caches()`'in `catch (Exception)` bloğu ilk kez global `\Exception`
  yakalar (davranış düzelmesi; öteki yerler zaten `\Exception` yazıyordu).
- Modül sırası yalnız fonksiyon olmayan ifadeler için önemli (polyfill,
  PHPMailer `require` + `use`, tur sabiti, görsel id globali); fonksiyonlar
  derleme anında yükseltildiği için birbirini herhangi bir sırada çağırır.
- `get_file.php` `functions.php`'yi yüklemediği için 18 fonksiyonun kopyasını
  taşımaya devam eder (bölme bunu değiştirmedi).
- Doğrulama: `docs/_plan_functions_bolme.md` §5'teki üç kapı geçti — fonksiyon
  envanteri (801 kullanıcı fonksiyonu, imzalarıyla birebir), `php -l` (25 dosya),
  bayt sayımı. Dördüncü kapı (313 giriş noktasının duman testi) dev sitede
  koşturulmalı.
- Bu dosyadaki ve `docs/`taki `functions.php:NNNNN` satır referansları
  bölünmeden öncesine aittir; fonksiyon adıyla arayın.

### Erişim Kontrolü
- `validate_area_access($user, 'administrator')` → sadece `USER_ROLE = 0` (admin)
- `validate_area_access($user, 'designer')` → designer + admin
- Her sayfanın başında `validate_user()` ile kullanıcı doğrulanır.

### Kullanıcı Rolleri ve Yetki Sınırları

| Role | Değer | Erişebilir | Erişemez |
|---|---|---|---|
| Administrator | 0 | Her şey | — |
| Designer | 1 | Tasarım araçları dahil admin-dışı her alan | Sadece-admin alanları (`'administrator'` = rol 0) |
| Manager | 2 | İçerik, e-ticaret, ayarlar | Tasarım araçları (`'designer'` alanları rol ≤ 1 ister) |
| User | 3 | Temel içerik | E-ticaret, ziyaretçi vs. için özel bayrak gerekir |

**Numara kaynağı (koddan doğrulandı, 2026-08-17):** `select_user_role()`
(functions.php:6353) dropdown'ı 0=Administrator, 1=Designer, 2=Manager,
3=User üretir; `validate_area_access()` (functions.php:612) eşlemesi
`'designer'→1`, `'manager'→2`, `'user'→3`; view_users.php filtreleri
`all_site_designers → rol<2`, `all_site_managers → rol<3`. Yani hiyerarşide
**Designer (1), Manager'ın (2) üstündedir.** (Bu tablo eskiden 1=Manager,
2=Designer, 3=Contributor yazıyordu — yanlıştı.)

**E-ticaret (ECOMMERCE) yetki kalıbı:**
- Role 0-2: `manage_ecommerce` bayrağından **muaf**
- Role 3: `manage_ecommerce = true` zorunlu

**Dış API yetki kalıbı (`integration.php`, 2026.4.4):**

Yetki iki kapıdan geçer ve ikisi de tek yerde tanımlıdır:

```php
// includes/api/scopes.php
// 1) Uygulamanın kapsamları + write->read kuralı  (api_scopes_expand)
// 2) Sahibi kullanıcının bugün devredebilecekleri  (api_owner_scopes)
// Etkin yetki = ikisinin kesişimi, HER İSTEKTE hesaplanır, saklanmaz.
$app['scopes'] = api_effective_scopes($granted, $owner);
```

Kapsam adları `kaynak:read` / `kaynak:write`. `write`, `read`'i kapsar ve bu
kural **yalnız** `api_scopes_expand()` içindedir — veritabanına operatörün
seçtiği ne ise o yazılır. Sahibin devredebilecekleri paneldeki kuralların
aynısıdır (`validate_ecommerce_access` kalıbı): role 0-2 muaf, role 3
`manage_ecommerce` ister.

Eski `apps.php` (uygulamaya ait `api_key` + **kullanıcıya** ait `secret_key`,
`has_permission()` ile `{action, type}` çiftleri) 2026.4.4'te kaldırıldı.

**`liveform` uyarı/bildirim tuzağı:** `get_warnings()` ve `output_notices()`
oturumdan **okur, silmez**. Bir kez gösterilen uyarı o ekrana her girişte
yeniden çıkar. Ekran render'dan sonra `remove_form()` çağırmalı (bkz.
`import_zip.php`, `marketplace_settings.php`, `api_settings.php`). Ayrıca
`add_notice()` çağıran her ekran `output_notices()` da çağırmalı — yoksa mesaj
oturumda birikir ve hiç görünmez.

**Giden pazaryeri katmanı (Faz 4, 2026-09-06):**

`includes/api/outbound/connectors/` — `base.php` sözleşme + ortak plumbing,
`n11.php` ilk sağlayıcı. Sözleşme bir arayüz değil, **fonksiyon adları
tablosu**: `mp_connector('n11')` onu döndürür, `mp_can()` sorar, `mp_call()`
çağırır. Yapamadığı işi adlandırmayan konnektör geçerlidir.

- **Üç tablo, mevcut tablolarda sıfır kolon.** `marketplace_accounts`
  (bağlantı başına bir satır — bir mağaza aynı sağlayıcıda iki hesap tutabilir;
  kimlik bilgileri `encrypt_string_with_iv` ile şifreli JSON, kolon
  `"<ciphertext>:<iv>"`), `marketplace_product_map`
  (`(account_id, product_id)` UNIQUE), `marketplace_sync_queue`
  (`remote_task_id` taşır). Yeni sağlayıcı = 0 şema değişikliği. Paraşüt'ün
  `config` kolonu + kayıt üstünde kimlik deseni **tekrarlanmaz**.
- **Ağ çağrısı asla istek akışında yapılmaz.** `pg_marketplace_product_changed()`
  (functions.php) stok/fiyat yazan sekiz yere birer satır olarak konmuştur ve
  yalnızca kuyruğa yazar. Yeni bir stok/fiyat yazarı eklenirse o satır da
  eklenir.
- **Kuyruk olay değil talimat taşır.** Aynı ürün için bekleyen satır varken
  ikincisi yazılmaz; saniye arayla iki değişiklik tek gönderimdir.
- **Ağ çağrısından önce 300 sn kira** (`run_after`), durum `waiting` kalır —
  üst üste binen cron atlar, süreç ölürse kira dolar. `api_webhook_queue` ile
  aynı desen.
- **`api_sync_job.php` rotasyonda, satır içi DEĞİL** (300 sn). Webhook bir
  mesajdır, stok bir durumdur; seyrek çalışmak partileri büyütür ve dakika
  başına sayılan hız sınırına daha az yazar.

**Pazaryeri siparişi içe aktarma (`includes/api/outbound/orders.php`):**

- **Birim paket, sipariş değil.** `marketplace_order_map` `(account_id,
  remote_package_id)` UNIQUE. Sipariş numarasına bağlamak aynı siparişin ikinci
  paketini mükerrer sayar.
- **Eşleme satırı siparişten ÖNCE yazılır.** İşlem yok; tekrar denemede
  siparişin iki kez yazılmasını engelleyen tek şey bu. Yarım kalan içe aktarma
  = `order_id`'si 0 olan `importing` satırı.
- **Ödeme sayfasının yan etkileri kullanılmaz.** İstenen üçü: stok düşümü,
  `pg_marketplace_product_changed()`, `create_notification('new_order')`.
  E-posta / otomatik üyelik / puan / hediye kartı / kampanya / **teklifler**
  bilerek dışarıda. `process_order_cancellation()` de kullanılmaz — ağ
  geçidinden geçmemiş siparişte iade denemesi yapar.
- **Eşleşmeyen tek kalem tüm siparişi reddettirir.** Eksik kalemli sipariş
  kargolanacak şeyi eksik gösterir ve stoğu yanlış üründen düşer.
- **Kodla eşleşen ürün o anda haritalanır** — yoksa stok değişimi hiç kuyruğa
  girmez ve ürün ilk satışından itibaren kayar.
- **Röle e-posta adresleriyle kişi eşleştirilmez** (`mp_order_email_is_personal()`):
  pazaryerleri sipariş başına röle adresi verir, eşleştirmek her siparişi tek
  müşteriye yazardı.

**Pazaryeri durum/kargo/iptal (`mp_order_apply_status()`):** Sipariş akışı
`lastModifiedDate` penceresiyle sorgulandığı için kargolanan/teslim/iptal
paketler zaten geri gelir — içe aktarılmış paketin tek güncelleme fırsatı budur.
Tarihler **yalnız doldurulur, taşınmaz** (teslim paket pencerede kaldıkça tekrar
gelir); takip numarası bir kez yazılır (`shipping_tracking_numbers`'ın benzersiz
anahtarı yok). İptal `process_order_cancellation(..., $attempt_refund = false)`
ile — parayı ağ geçidi almadı. **Stok geri alınır**; `process_order_cancellation()`
bunu hiçbir yerde yapmaz ve mağaza içi iptal için bu bilinçli olarak
değiştirilmedi. Dönüş değeri `'success'`, `'ok'` DEĞİL.

**n11'de takip numarası yazılamaz.** REST sipariş entegrasyonunda böyle bir
servis yok; kargo n11'in anlaşmalı firmalarıyla (`PUT /rest/delivery/v1/collectionRequest`,
HLZ/CEVA/BL) yapılır ve numarayı n11 atar. `mp_n11_request_collection()` var ama
**zamanlayıcıdan çağrılmaz** — bir adrese kurye gönderir.

**Pazaryerine ürün açma (`includes/api/outbound/listings.php`):**

- **Birim "article" = özellik taşıyan `product_group`.** Özellik taşımayan grup
  mağaza menüsündeki raftır; ilan olarak sunulursa ilgisiz onlarca ürün tek
  ilanın seçenekleri olur. Filtre: `product_groups_attributes_xref`.
- **Kategori ağacı sağlayıcı başına** önbelleklenir, **özellikler kategori
  başına ve istendiğinde** — hepsini çekmek dakikalık limite karşı on binlerce
  çağrıdır.
- **Özellikler iki kaynaktan:** kategorinin üründen istedikleri eşleştirme
  ekranında bir kez; varyantı ayırt eden özellik ürünün kendi seçeneğinden
  (`mp_listing_variant_value()` önce ada, sonra **değere** bakar).
- **Kategori değişince eski yanıtlar silinir.** MySQL `SET`'i soldan sağa
  değerlendirir ve sonraki atama önceki kolonun **yeni** değerini görür — bu
  yüzden `attributes_json` `remote_category_id`'den ÖNCE atanır.
- **Stok takibi olmayan ürün ilan edilmez** (ilan adet ister; sıfır ilanı
  doğduğu anda tükenmiş açar). Aynı sebeple **stok gönderiminde de** takip
  etmeyen ürün için `quantity` hiç gönderilmez.
- **KDV:** n11 yalnız 0/1/10/20 kabul eder; en yakın **alttaki** orana
  yuvarlanır (yukarı yuvarlamak alıcıdan fazla tahsil ettirir).
- `products_images_xref`'te **`id` kolonu yoktur** — `ORDER BY id` ölümcül hata.

**`orders` yazarken üç tuzak:** `reference_code` UNIQUE ve varsayılanı `''`
(ikinci sipariş çakışır — `generate_order_reference_code()` şart);
`payment_method` bir **ENUM** (aralık dışı değer sql_mode boş olduğu için
sessizce `''` olur — pazaryeri sipariş numarası `transaction_id`'ye yazılır);
`order_date` boşsa sipariş **listede ve raporda hiç görünmez**. Sipariş numarası
`LOCK TABLES next_order_number WRITE` ile alınır ve kilit bağlantının tamamına
uygulanır — arada başka tabloya sorgu yapılamaz, bu yüzden kendi fonksiyonunda.

**`orders.type`:** `online` / `marketplace` / `local`. `view_orders.php`
varsayılan filtresi `'online'` ve artık `IN ('online','marketplace')` olarak
okunuyor — pazaryeri siparişine kendi tipini verip filtreyi olduğu gibi bırakmak
siparişi kimsenin değiştirmediği bir filtrenin arkasına saklardı.

**n11 (developer.n11.com, 2026-09-06):** `api.n11.com`, `appKey`/`appSecret`
başlıkları (belgeleri iki serviste iki türlü yazıyor — konnektör ikisini de
gönderir). Ürün tarafı **asenkron**: `price-stock-update` (1000 SKU/istek) →
`taskId` → `task-details/page-query` → **SKU başına** SUCCESS/FAIL. Gönderim
üzerine "başarılı" demek yanlıştır. `REJECT` kalıcıdır; kimliksiz `IN_QUEUE`
yeniden denenir (ikisini aynı dala koymak kabul edilmiş partiyi kalıcı
başarısız yapıyordu). Sipariş: `GET /rest/delivery/v1/shipmentPackages` (ms cinsinden ve **GMT+3**
tarih — UTC göndermek üç saatlik pencereyi kaydırır; sayfa 0'dan başlar, en fazla
100), `PUT /rest/order/v1/update` yalnız `Created → Picking`. Satırda `stockCode`
satıcının kodu (eşleştirme buna bakar), `productId` n11'in — burada bir işe
yaramaz; `orderLineId` onay için gereken kimlik. **`mallDiscount` sayılmaz**:
n11'in parası, satıcıya sanki olmamış gibi ödenir; yalnız `sellerDiscount` ve
`sellerCouponDiscount` bu siparişin indirimidir. Fiyatlar ondalık ve virgül de
gelebilir. SOAP servisleri kullanılmadı.

**Dış API — ortamın koyduğu kurallar (IIS, 2026-09-06'da bulundu):**

- **`waf_is_api_request()` isteğin YOLUNA bakar, çalışan dosyaya değil.**
  `web.config` diskte dosyaya karşılık gelmeyen her isteği `router.php`'ye
  yönlendirir; IIS `/integration.php/meta` adresini dosya sayar,
  `/integration.php/products/138` adresini saymaz. Yani ucun en çok kullanılan
  şekli `SCRIPT_NAME` olarak `router.php` ile gelir. Yol içindeki **ilk `.php`
  parçası** alınır — `/index.php/integration.php/x` burada da `index.php`
  cevabını verir, muafiyet uydurulamaz.
- **`integration.php` güvenlik duvarının hassas sayacından muaftır.** Kendi
  sınırı vardır (uygulama başına, varsayılan 120/dk); adres başına 30/dk +
  otomatik ban onu kullanılamaz yapıyordu. Sayacın aradığı sinyal
  `api_offence()` ile korunur: **kimlik doğrulaması başarısız olan** istek
  güvenlik duvarına ihlal yazar.
- **IIS'te PATCH ve DELETE gönderilmez.** WebDAV ikisini de PHP'ye gelmeden 405
  ile cevaplar. PATCH'in kalıcı yan etkisi vardır: IIS o adresi artık var
  olmayan bir dosya sayar, sonraki POST site yönlendirmesine düşer ve mağazanın
  404 sayfası döner. `also_accepts` başka sunucular için duruyor.
- **`api_query()` mysqli istisnasını yakalar.** 2026-09-12'den beri `init.php`
  raporlamayı kapattığı için (yukarıda "Veritabanı") sorgu istisna yerine
  yine `false` dönüyor; bu try/catch ikinci emniyet olarak duruyor, tek
  emniyet olarak değil. Hatayı okuyan yol her iki durumu da karşılar.
- **Gövdede tanınmayan alan 422 ile reddedilir** (`api_request_body_keys()`).
  Query string denetlenmez: önbellek kırıcı, analitik etiketi ve vekil sunucu
  işaretleri oraya takılır.
- **`POST /products` ekranın yazdığı yerden yazar:** `pg_pb_create_product()`.
  İkinci bir INSERT yoktur. `pg_pb_save_submit_form_fields()` kaynağını
  parametre olarak alır (`$_POST` varsayılan), `$product['user']` çağıran
  tarafından verilebilir. Alınmış ad 409 döner — `get_unique_name()` sessizce
  `AD[1]` yapardı.

### Veritabanı
- `db('SQL')` → sorgu çalıştırır
- `db_items('SQL')` → sonuç dizisi döner
- `db_value('SQL')` → tek değer döner
- `e($val)` → SQL escape (mysqli_real_escape_string)
- `escape($val)` → aynı şey (eski alias)
- `escape_like($val)` → LIKE sorguları için escape
- Tablo adları **tekil**: `page` (pages değil!), `style`, `pregion`, `cregion`, `dregion`, `forms`, `form_data`

**Sorgu hatası `false` döner, istisna fırlatmaz — `mysqli_report(MYSQLI_REPORT_OFF)`
(2026-09-12).** Kod tabanı baştan sona bu sözleşmeye göre yazılmış: 96 yerde
`mysqli_query(...) or exit(...)`, başka yerlerde `if (!$result)` ya da başına
`@`. PHP 8.1'den beri mysqli varsayılanı hatayı **istisna** olarak bildiriyor;
o kontrollerin hiçbiri istisna görmediği için her SQL hatası yakalanmamış fatal
— **gövdesi boş 500**, sebebi yalnız error.log'da — oluyordu. `install/index.php`
yazıldığından beri raporlamayı kapatıyordu; aynı satır `router.php` ve
`init.php`'ye de kondu, yani ön yüz, panel, api.php ve zamanlanmış işler artık
aynı sözleşmede. **Bu satırlar kaldırılmaz**; hatayı görmek isteyen
`mysqli_error(db::$con)` okur.

### Güvenlik
- `get_token_field()` → CSRF token input
- `validate_token_field()` → POST'ta token doğrulama
- `h($val)` → HTML escape (htmlspecialchars)

**Dosya bütünlüğü:** `_software_create_hash.php` (yalnız dev makinada, ürünle
dağıtılmaz; `https://dev.pinegrap.com/_software_create_hash.php`) `pinegrap/`
kökündeki dosyaların ve `hashed_subdirectories()` listesindeki alt ağaçların
SHA-256 özetlerini `pinegrap/data/temp/hash_reference.json`'a yazar;
`check_directory_file_integrity()` (`includes/fn/system_status.php`) bunları
saatte bir karşılaştırır. **Sürüm yayınlamadan önce referans yeniden
üretilmeli**, yoksa değişen her dosya "kurcalanmış" görünür. 2026.4.4'ten
itibaren `includes/` ağacı da kapsamda.

Referans iki türlü olur ve ikisi farklı işlenir (2026-09-12):

- Üreteç referansa `_generated` damgası koyar (host, sürüm, zaman).
  **Damgadaki host ve sürüm bu kurulumla aynıysa referans buranın gerçeğidir**:
  dosyalarla karşılaştırılır, kodpen.com'daki kopyayla hiç karşılaştırılmaz ve
  onunla değiştirilmez. Bu kural olmadan dev makinası hiç susturulamıyordu —
  her yeniden üretilen referans aynı istekte kodpen.com'daki kopyayla eziliyor,
  yeni listelenen dosyalar "extra" olarak dönüyordu.
- Damgası başka bir host'u gösteren (müşteri sitesine paketle gelen) ya da
  damgasız (ağdan indirilen) referans 12 saatte bir kodpen.com'daki
  `pinegrap_hash_referance[SÜRÜM].json` ile karşılaştırılır ve **onunla
  değiştirilir** (kurcalanmış yerel referansı yakalayan kopya).
- Denetimin kendi defteri (son sonuç, son koşum, kodpen.com'a son bakış)
  `data/temp/hash_reference_state.json`'da; referansın içine **yazılmaz**.
  Eskiden yazılıyordu ve kodpen.com'a yüklenen referans `last_local_check`'i
  "eksik dosya" diye taşıyordu. Üreteç her çalıştığında state dosyasını siler.
- **Referansta neyin dosya olduğuna şekil karar verir**
  (`pg_integrity_reference_files()`): değeri 64 haneli onaltılık olan anahtar
  dosyadır, gerisi yok sayılır — yerel referansta da, kodpen.com'dan gelen
  kopyada da. İsim listesi yalnız yazıldığı günün isimlerini kapsıyor ve
  dolaşımdaki eski kopyalar yıllarca kalıyor. Filtreden sonra dosya girdisi
  kalmayan referans bozuktur.
- **`clean_up.php` `data/temp`'i süpürerken `hash_reference.json`'ı atlar.**
  O bir önbellek değil; silmek yetkili referansı uzak kopyayla takas eder ve
  her yerel fark kurcalama gibi okunur. `pg_purge_caches()` ile aynı kural.
  Yanındaki `hash_reference_state.json` önbellektir, süpürülür.
- Yayın: üretilen dosya olduğu gibi kodpen.com'a
  `pinegrap_hash_referance[SÜRÜM].json` adıyla yüklenir; içine başka bir şey
  yazılmaz.

---

## Önemli Sabitler (init.php'de tanımlanır)

| Sabit | Açıklama | Kullanım |
|---|---|---|
| `CONFIG_FILE_PATH` | `dirname(__FILE__) . '/data/config.php'` | Config dosyası yolu |
| `OUTPUT_PATH` | Site kök URL'i | Frontend linklerde |
| `OUTPUT_SOFTWARE_DIRECTORY` | Backend klasör adı (genellikle `software`) | Backend linklerde |
| `ECOMMERCE` | E-ticaret aktif mi | `ECOMMERCE === true` |
| `ADS` | Reklam modülü aktif mi | `ADS === true` |
| `SET_ERROR_REPORTING` | Hata raporlama kontrolü | `SET_ERROR_REPORTING === true` |

`ECOMMERCE` ve `ADS` sabitleri settings tablosundan okunur. Kontrol: `defined('ECOMMERCE') && ECOMMERCE === true`

**Sır taşıyan config alanı sabite açılmaz.** Paraşüt'ün gizli anahtarı ve
parolası `config.parasut_credentials_enc` içinde tek şifreli JSON blob olarak
durur (`"<ciphertext>:<iv>"`, `encrypt_string_with_iv()`'in döndürdüğü çift) ve
`init.php` yalnız blob'u sabite alır. Çözme, Paraşüt'ü gerçekten çağıran istekte
`_parasut_credentials()` ile yapılır. Aynı kalıp `mp_credentials_encode()` /
`mp_credentials_decode()`'da da var (`includes/api/outbound/connectors/base.php`)
— tek sütun, çünkü sağlayıcı yeni bir alan isterse şema değişmesin. Ayarlar
ekranı değeri **geri render etmez**; boş kutu "kayıtlıyı koru" demektir. Not:
`api_settings.php` şifrelemez, `hash_hmac` ile hash'ler — geri okunması gereken
bir kimlik bilgisi için o kalıp kullanılamaz.

---

## Config Sistemi

- `parse_config_file()` → `data/config.php` dosyasını regex ile parse eder (runtime sabitlerini değil, fiziksel dosyayı okur)
- `update_config_define($content, $key, $value, $type)` → define değerini günceller
- `remove_config_define($content, $key, $type)` → define'ı dosyadan kaldırır
- Boolean alanlar 3-state select ile gösterilir: "Not Selected" (define yok) / true / false
- `edit_config.php`'de değişiklik yoksa dosya yazılmaz (`$config_content === $config_raw` kontrolü)

---

## Tablo Yapıları (Sık Kullanılanlar)

### Adres/slug üretimi — tek tablo

Türkçe ve aksanlı harfleri ASCII'ye çeviren **tek** yer:
`pg_transliterate_to_ascii()` (functions.php). Yeni aksan tablosu yazma, mevcut
bir tabloyu "genişletme" — buradan geçir.

| Ne | Fonksiyon | Biçim |
|---|---|---|
| Gönderilmiş form (blog/forum) | `create_address_name()` | küçük harf + tire: `bulut-bilisimin-avantajlari` |
| Ürün / ürün grubu | `prepare_catalog_item_address_name()` | b/k korunur, alt çizgi: `Cay_Bardagi` |
| Dosya adı | `pg_ascii_file_name()` | ad korunur, alt çizgi; `[ ]` **kalır** (sürüm eki) |
| Sayfa adı, kısa link adı | **çeviri YOK** | operatörün yazdığı ad; `seo.php` `url_non_ascii` ile raporlar |

**Sıra kuralı: önce çevrim, SONRA `mb_strtolower()`.** Tersi `İ` harfini
`i` + birleşen nokta (U+0307) yapıyor, nokta da ayırıcıya dönüşüp kelimeyi ikiye
bölüyor (`İçin` → `i-cin`).

2026.4.4'e kadarki hata buydu; oradaki tablo ayrıca bozuk bir kodlama çevriminde
U+FFFD'ye çökmüş, altmış altı anahtarı aynılaşmış ölü bir tabloydu. Geri getirme.

### `files`
- `files.name` **adresin kendisidir.** Her okuyucu dosyayı
  `FILE_DIRECTORY_PATH . '/' . name` diye çözüyor, router da istenen yolu bu
  kolonla eşleştiriyor. **Adı değiştiren her kod diskteki dosyayı da yeniden
  adlandırmak zorunda** (`edit_file.php`, kaydetme dalı) — ve `rename()`
  başarısızsa `UPDATE` hiç çalışmamalı, yoksa kayıt var olmayan bir dosyayı
  gösterir. 2026.4.4'e kadar bu yapılmıyordu; zip/görsel türlerinde indirme
  kırılıyor, metin türlerinde ise eski dosya diskte öksüz kalıyordu.

### `page`
- `page_id`, `page_name`, `page_folder`, `page_title`
- Frontend URL: `OUTPUT_PATH . page_name`
- `sitemap` / `noindex` / `nofollow` — TINYINT bayraklar. `sitemap = 1` ve
  `noindex = 1` **birlikte olamaz**; kayıt tarafı `sitemap`i sıfırlar.

### `pregion` (Sayfa Bölgeleri)
- `pregion_id`, `pregion_name`, `pregion_content`, `pregion_page` (FK → `page.page_id`)
- Düzenleme: sayfanın frontend URL'sinden yapılır (edit_page.php değil!)
- JOIN gerekli: `LEFT JOIN page ON pregion.pregion_page = page.page_id`

### `cregion` (Ortak/Tasarımcı Bölgeleri)
- `cregion_id`, `cregion_name`, `cregion_content`, `cregion_designer_type`
- `cregion_designer_type = 'no'` → Ortak Bölge → `edit_common_region.php?id={cregion_id}`
- `cregion_designer_type = 'yes'` → Tasarımcı Bölgesi → `edit_designer_region.php?id={cregion_id}`

### `dregion` (Dinamik Bölgeler)
- `dregion_id`, `dregion_name`, `dregion_code`
- Düzenleme: `edit_dynamic_region.php?id={dregion_id}`

### `forms` + `form_data`
- `forms.id` → form gönderimi ana kaydı → `edit_submitted_form.php?id={forms.id}`
- `form_data.id` → her alan için ayrı satır (form_data.id ≠ forms.id!)
- `form_data.form_id` → FK → `forms.id`
- `form_data.data` → alan değeri (aranabilir içerik)
- Bağlantı için: `form_data.form_id` kullan, `form_data.id` değil

### `style` (Sayfa Stilleri)
- `style_id`, `style_name`, `style_code`
- Düzenleme: `edit_custom_style.php?id={style_id}`

### Görsel Tasarımcı — çok sayfalı tasarım (2026.4.4, migration 4.22)

Bir **tasarım** = `style_layout='visual_designer'` olan bir `style` satırı +
`page_style` FK ile ona bağlı N sayfa. Yerleşim:

| Nerede | Ne |
|---|---|
| `page.page_tree_json` | sayfanın düzen ağacı |
| `page.page_tree_code` | ağaçtan üretilen HTML — **render bunu okur** (`get_page_content.php`) |
| `style.style_custom_css/js/fonts` | tüm sayfalarda ortak assets (Styles / JavaScript / Fonts / Theme paneli) |
| `style.theme_id`, `collection`, `additional_body_classes`, `style_head` | zaten stil seviyesindeydi |
| `style.style_tree_json`, `style_code`, `page.page_custom_*` | **fallback**, yeni editör yazmaz |

- Ağacı okuyan her sorgu **`pg_page_tree_sql_expr()`** kullanır
  (`COALESCE(NULLIF(page.page_tree_json,''), style.style_tree_json)`);
  `style.style_tree_json`'a doğrudan LIKE yazma. Tek sayfa için
  `pg_page_tree_json($page_id)`.
- `pg_multi_page_design_ready()` beş kolonu birden yoklar; köprü kuralı
  gereği yeni kolon varsayılmaz.
- "Görsel tasarımcı sayfası" ölçütü **`layout_type='system'` değildir** —
  o sütun her standart sayfada `system`, legacy sistem yerleşimleri de
  onu kullanır. Ölçüt `pg_page_is_visual_design()`: sayfanın kendi
  `page_style`'ı `visual_designer` **veya** kendi ağacı var. Düzenleme
  bağlantısı her yerde `pg_page_edit_url()` (view_pages, gezgin,
  edit_page.php yönlendirmesi). `select_style()` görsel tasarımları
  listelemez — klasöre/sayfaya varsayılan olarak atanamaz.
- Dosya Yöneticisi ZIP → tasarım: `import_design_zip.php` →
  `pg_designer_create_design_from_import()` (stil + sayfalar
  `Import/<proje>` klasörüne, sentinel yazılmaz) → editör. Editör içi import
  da sayfaları `folder_id`'ye koyar ve klasör select'ine seçenek ekler.
- Editör ekranı tek dosyada: `includes/designer_screen.php`
  (`pg_designer_screen_render` / `_post`); `add_system_style.php` ve
  `edit_system_style.php` ince sarmalayıcı. Kayıt `pages_json` alanıyla
  tüm sekmeleri gönderir; sunucu önce hepsini `dry_run` ile doğrular,
  sonra yazar (MyISAM, transaction yok). Kayıt `page.page_custom_*`'ı
  sıfırlar.
- `api.php` **gövdeyi JSON okur**; FormData "Invalid action" döner.
- JS'de sekme geçişi modül-seviyesi `tree`/`undoStack`/`redoStack`'i
  park eder/yükler (`_pgTabsSwitch`); dosyanın kalanı tek ağaç görür.
  Sekme DOM'u her geçişte yeniden çizilir — referans tutma.
- JS `lang()` yalnız önceden yüklenmiş anahtarları bilir; tasarımcıdaki
  her metin `_sdT('English')` (aşağıda "Görsel Tasarımcı Metinleri").
- Sil düğmesi kendi tek-kullanımlık formunu post eder;
  `_protectMainForm()` ana formun her submit'ini bloklar.
- Sekme menüsü (`_pgTabsOpenMenu`, ⋮ ve sağ tık): Ayır →
  `api.php designer/page_detach` (yeni style + assets kopyası); Sil →
  `designer/page_delete` → gezginin `explorer_recycle_delete`'i
  (Geri Dönüşüm Kutusu, `page_style` korunur). **Kutudaki sayfa sekmede
  listelenmez:** tasarımın sayfalarını okuyan her sorgu
  `pg_designer_not_binned_sql()` ekler. Son sayfa ne ayrılır ne silinir.
- `page_folder` asla 0 yazılmaz (`pg_designer_save_page` köke çevirir);
  0 olan sayfa Dosya Yöneticisinde görünmez ve kutuya gönderilemez.
- `.sd-toolbar.sd-toolbar-tabs` `overflow: visible` — çubuğun
  `overflow-x:auto`'su dropdown'ı kırpar; şerit kendi `.sd-tabs-scroll`'unda
  kayar.
- HTML / ZIP içe aktarma: çekirdek `includes/designer_import.php`, uç nokta
  `designer_import.php` (multipart; `api.php` JSON okur). Sayfalar
  kaydedilmemiş sekme, dosyalar `Import/<proje>/…` altına hemen yazılır.
  Inline `<svg>` parse'tan önce ham alınır (libxml `<path/>`'i iç içe
  yapar): `bi-*` sınıflı → icon node, gerisi `.svg` dosyası + image node;
  **custom_html üretme.** `srcset`/`sizes` görsele alınmaz (src'yi ezer).
  Sayfa adları çeviriden önce belirlenir (`$ctx->page_names`), sayfa-arası
  bağlantılar oradan çözülür. Ayrıntı: `docs/degisiklikler.md`.
- Sekme adı yerinde düzenlenir (çift tık / menü); sekmeler `<div role="tab">`,
  `<button>` değil — buton içindeki input odak alamıyor. Araç çubuğunda ad
  kutusu YOK; `#sd-page-name` Sayfa Ayarları'ndadır (JS aynı id'yi okur).
- Karşılama ekranı `view_system_styles.php` (`pg_designer_start_screen`,
  menü `$menu_items[21]`): Yeni Oluştur → `add_system_style.php` (doğrudan
  tuval), İçe Aktar → `?start=import` (`sdDesign.autoImport`), Tasarımlar
  listesi. Editör çıkışı ve silme buraya döner. `view_styles.php` yalnız
  `style_layout <> 'visual_designer'`; `add_style.php` kaldırılacak.
- Son sayfa silinebilir; `_pages` boşalınca `_pgLeaveEmpty()`. Tasarım Sil
  düğmesi `pg_designer_style_live_usage()` ile kutudaki sayfaları saymaz;
  kutudakilere dokunmaz. Karşılama listesindeki satır Sil →
  `designer/design_delete` (canlı sayfalar kutuya, stil silinir). Liste
  standart DataTable, toplu seçim sütunu yok.
- "Sayfayı Görüntüle" `p.savedName` ister (kaydedilmemiş ad → bağlantı yok).
- Ortak/Sistem paleti `_renderPaletteGroups()`: bu tasarımda kullanılanlar
  (`_pgDesignSharedIds`) önce, site geneli katlı.
- Denetim: `_validationRules` (istemci, düğüm başına, `seo` alanı = sunucu
  kodu) → tuval rozeti `applyIssueBadges()`; `api.php designer/seo_check`
  → `pg_seo_evaluate_meta` + `pg_seo_analyze_html('fragment')` +
  `pg_seo_compose`, yazmaz. Kayıt `pg_seo_analyze_record` +
  `pg_seo_recalculate` koşturur. Yeni düğüm-seviyeli SEO kodu eklerken
  ikizini istemciye de yaz — sunucu bulgusu düğüme eşlenemez.
- Tema `files` satırıdır (`design=1`); Stiller listesindeki "tema" satırı
  `cssFiles`'a girmez, `select[name=theme_id]`'den okunur
  (`_sdActiveTheme`). "Tema olarak kaydet" → `api.php designer/theme_save`.
- Canvas doğrudan-çocuk kuralları (`X > .sd-wrap { display:contents }` +
  kuralı gerçek çocuğa yeniden uygula): `.row`, `.d-grid`, **tüm
  `.d-*-flex`**, `.ratio` (`.ratio > .sd-wrap > *` mutlak konum). Yeni bir
  Bootstrap kapsayıcısı eklerken CLAUDE.md "Bootstrap'in Doğrudan-Çocuk
  Varsayımı" bölümüne bak. `content/span`/`text` sarmalayıcısı
  `display:inline`.

### `products` / `product_groups`
- Sadece `ECOMMERCE === true` iken erişilebilir
- `products`: `edit_product.php?id={id}`
- `product_groups`: `edit_product_group.php?id={id}`
- Fiyat kolonları **kuruş cinsinden** saklanır; dış API de kuruş döner (`api_money()`), panel gösterirken `/100` yapar

**Varyant modeli — ayrı tablo yok.** `products` içindeki her satır satın
alınabilir tek bir şey; aynı ürünün farklı rengi/bedeni ayrı satırdır. Onları
bir arada tutan `product_groups`:

- `products_attributes_xref` (product_id, attribute_id, option_id, sort_order) —
  satırın hangi seçimlerin karşılığı olduğu
- `products_groups_xref` (product, product_group, sort_order) — satır hangi
  gruplarda
- `product_groups_attributes_xref` (product_group_id, attribute_id,
  default_option_id, sort_order) — grubun eksen sırası. **Tekil anahtarı yok:**
  aynı nitelik bir gruba iki kez bağlanabilir (örnek katalogda bağlı). Grup
  başına nitelik bir kez okunur.
- `product_attributes` (name = içeride, label = müşteriye) /
  `product_attribute_options` (`no_value` = "seçim yapılmadı" satırı, varyant
  değildir)

Dış API'nin döndürdüğü alanlar `includes/api/resources/products.php` ve
`product_groups.php` içindeki `api_*_present()` fonksiyonlarıdır — tek kaynak
orasıdır, burada tekrarlanmaz. Fiyat dışarıya **kuruş** olarak çıkar
(`api_money()`), panelde `/100`.

**Liste ucunda satır başına sorgu yazılmaz.** Liste 250 satıra kadar döner;
görsel/nitelik/grup okumaları `api_product_extras()` ve
`api_product_group_extras()` gibi toplu yardımcılara girer (`WHERE id IN (...)`),
sunum fonksiyonu hazır diziden okur.

**Vergi: bölge "alınır mı", ürün "ne kadar" der (2026.4.4).** `tax_zones` bölge
başına tek oran tutar ve alıcının adresi bölgeyi seçer — verginin **alınıp
alınmayacağına** hâlâ bu karar verir. `products.tax_rate` (DECIMAL(6,3) UNSIGNED
**NULL**) yalnız oranı değiştirir:

- `NULL` = ürünün kendi oranı yok, bölgenin oranı geçerli (her eski satırın anlamı)
- `0.000` = sıfır oranlı, vergi uygulanan her yerde %0
- Adres hiçbir bölgede değilse vergi yok — ürün ne oran taşırsa taşısın

Tek karar noktası `get_effective_tax_rate($product_rate, $zone_rate)`;
`update_order_item_taxes()` satır başına onu çağırır. `parse_tax_rate()` girdiyi
saklanır hâle getirir (boş → NULL, virgül → nokta, 0-100 sınırı),
`format_tax_rate()` ekrana yazar (0.000 boş kutuya dönmez). Site varsayılanı
`get_default_tax_rate()`.

**Vergi SATIR toplamı üzerinden hesaplanır (2026.4.4'ten beri).**
`order_items.tax_total` = `round(oran/100 × birim_fiyat × adet)`, yani satırın
**tamamının** vergisi. Toplarken `SUM(tax_total)` — adetle **çarpma**.

`order_items.tax` artık yazılmıyor. Eskiden birim vergiydi ve toplayan herkes
`tax * quantity` yapıyordu; sütun tarihsel veri için duruyor ve yeni satırlarda
`0`. Bir yerde 0 vergi görüyorsan muhtemelen `tax` okunuyordur. Sütun 2026.5.0'da
düşürülecek.

**Neden değişti:** `round(oran × birim) × adet` ile `round(oran × birim × adet)`
adet 1'de aynı, adet 2+'de %40–90 olasılıkla 1–3 kuruş farklı. Sepet önizlemesi
(`widgets_cart.php`), Paraşüt ve UBL-TR e-belge biçimi zaten satır tabanındaydı;
yalnız ödeme yolu birim tabanındaydı ve fatura tahsil edilen tutara oturmuyordu.
Dörtten üçü satır tabanında olduğu için ödeme yolu onlara hizalandı.
`widgets_cart.php`'de bu tutarsızlığın eski bir belirtisi ("tutar değişti"
reddi) kayıtlıydı; o da kapandı.

**PayPal Express'te satır bazlı `L_PAYMENTREQUEST_0_TAXAMT` artık
gönderilmiyor** — o alan birim vergi ister ve bir satır tutarı her zaman tam
birimlere bölünemez. Ödeme düzeyindeki `PAYMENTREQUEST_0_TAXAMT` siparişin
vergisini zaten taşıyor.

## ERP Modülü (2026.4.4, Faz 0–2)

`config.erp_enabled` ile açılır; sabit `ERP_ENABLED` (savunmacı okunur).
Ekranlar `erp_*.php`, kapı `validate_erp_access($user, $area)` — `$area` boş,
`'cash'` veya `'settings'`. Modül kapalıyken 404 değil, hata + ayar kartına
bağlantı (`pg_settings_return_url('commerce', 'pgset-erp')`).

**Tablolar modül kapalıyken de kurulur.** Şemayı anahtara bağlamak, sonradan
açan siteyi sürüm numarasının söylediğinden farklı bir şemaya düşürür ve
idempotent adımlar "zaten var" diyerek o boşluğu sabitler.

**Para tamsayı kuruş, `BIGINT`.** Kod tabanının geri kalanıyla aynı; `bcmath`
yok. Oranlar `DECIMAL(6,3)` / `DECIMAL(15,6)`, miktar `DECIMAL(15,4)`.
Tek çarpım/yuvarlama noktası `includes/erp/money.php` olacak.

**`erp_invoice_items.tax_total` bilerek `tax` değil** — `order_items.tax`
birim vergidir, fatura satırı satırın toplam vergisini tutar. Ad farkı okuma
anında durduruyor.

**Cari kart `contacts`'a bağlanır** (`erp_accounts.contact_id`), `customers`
diye bir tablo yok. `parasut_contact_id` `contacts`'ta durur, `erp_accounts`'a
kopyalanmaz.

**Yetki üç sütun:** `manage_erp` (kapı), `manage_erp_cash`,
`manage_erp_settings` — hepsi öneksiz `TINYINT`. Üçlü Yok/Okuma/Yazma deseni
bu kod tabanında yok. Yeni bir yetki eklerken rol-3 kullanıcının panele
girebilirliğine karar veren dört merdiven de güncellenir: `welcome.php:59`,
`get_page.php`, `includes/fn/content.php`, `set_password.php` /
`reset_password.php`. Atlanırsa tek hakkı o olan kullanıcı "erişim yok"a düşer.

**Menü slotu 22** (`includes/fn/output.php`). Slotlar kalıcıdır —
`user.selected_appmenu_items_array` onları pinler, yeniden kullanılmaz.


### Ekran deseni: liste `table.chart`, düzenleme ayrı sayfa

ERP ekranları da yazılımın kendi desenini kullanır, kendi çizdiği listeyi değil:

- **Liste** `view_*` gibi tek iş yapar: `<table class="chart table-hover table"
  style="width:100%;display:none;">`. DataTables'ı `backend.src.js` otomatik
  kurar (arama, sayfalama, sütun görünürlüğü, sıralama, `display:none`'ı
  `fnInitComplete` kaldırır). İlk sütun `<th class="noVis">Eylem</th>` ve içinde
  `edit_*.php?id=`'ye giden kalem düğmesi.
- **Ekleme/düzenleme ayrı sayfa**: `add_erp_account.php` / `edit_erp_account.php`,
  `add_erp_till.php` / `edit_erp_till.php`, `add_erp_invoice.php` /
  `edit_erp_invoice.php`, `add_erp_receipt.php`, `add_erp_transfer.php`.
  `if (!$_POST) { form } else { validate_token_field(); kaydet; go(); }`.
  Alanlar `$liveform->output_field()` ile — hata işaretlemesi ve geri doldurma
  oradan gelir; elle `<input>` yazılmaz. İki sayfanın ortak alanları
  `includes/erp/account_form.php` ve `includes/erp/till_form.php`.
- Yeni sayfa açınca `includes/fn/output.php` içindeki `$active_menu = 22`
  switch'ine ekle, yoksa menü vurgusu kaybolur.

**Sırası anlam taşıyan listeler DataTable değildir**: cari ekstresi, kasa
defteri, fatura kalemleri. Yürüyen bakiye ancak tarih sırasında doğrudur; kalem
numarası belgenin kendisidir. Bunlar düz `table table-hover` ile çizilir.

### İade ve iptal

Kesilmiş fatura düzenlenmez; geri dönüş iki yoldan olur (`includes/erp/returns.php`).

- **İade** kendi belgesidir ve **kendi serisinde** koşar: fatura serisi + `I`
  (PGF → PGFI), ya da `ERP_RETURN_SERIES`. Aynı seride olamaz —
  `erp_invoices.uniq_number` (direction, series, number, issue_year) `doc_type`
  içermez, ve zaten aynı belge numarası iki belgede olmamalıdır.
- **Tam iade ana faturanın rakamlarını kopyalar**, yeniden hesaplamaz: ana
  faturanın KDV'si siparişin başlık KDV'sine bağlıydı ve satırlardan türetilenin
  bir kuruş uzağında olabilir. Kısmi iadede satır orantılı bölünür
  (`round(indirim × miktar / satılan)`, KDV kalan matrahtan).
- `erp_invoice_items.returned_qty` ana faturada birikir; iade iptal edilirse geri
  düşülür. Aynı malın iki belgeyle iki kez iade edilmesini bu engeller.
- **İptal** yalnız üzerine hiçbir şey asılmamışken yapılabilir (tahsis yok, iade
  yok). Defter kaydını silmez, **ters kayıtla çevirir**; numarayı serbest
  bırakmaz. Faturadan gelen sipariş `erp_invoice_id = 0` ile yeniden
  faturalanabilir hâle döner.
- `erp_invoice_open_amount()` hem tahsisi hem iadeyi düşer; ama **durumu yalnız
  tahsis belirler** — iade edilmiş bir fatura "ödenmiş" değildir, tahsil
  edilecek bir şeyi kalmamıştır.

### Fatura kapatma: tahsis, para değil

Tahsilat zaten cariyi alacaklandırır ve bakiyeyi düşürür. `erp_settlements`
bunun **üstüne bir kayıt atmaz**, yalnız hangi hareketin hangi faturayı
kapattığını tutar. Kapatma için ikinci bir defter kaydı atmak aynı parayı iki
kez sayar — bakiye tam da tahsil edilen tutar kadar kasadan ayrışır.

- `UNIQUE KEY (invoice_id, account_txn_id)`: aynı çifti yeniden yazmak tutarı
  düzeltir, üstüne eklemez (`ON DUPLICATE KEY UPDATE`).
- `erp_invoices.paid_total` ve `status` bu satırlardan **türetilir**
  (`erp_invoice_refresh_paid()`), artırılmaz. Bakiyelerle aynı gerekçe.
  `cancelled` ve `draft` durumlarına dokunulmaz.
- Tahsis ne faturanın açığını ne de tahsil edileni aşar; fazlası açık hesapta
  kalır.
- **Otomatik eşleştirme yok** (plan §550). En eski açık faturayı kapatmak sürpriz
  mutabakat bozar; tahsis her zaman birinin kararıdır.
- `erp_post_receipt()`'in `invoice_id`'si tahsisi çağıranın transaction'ı içinde
  yazar. Reddedilen bir tahsis tüm tahsilatı geri alır — yarım yazılmış tahsilat
  bırakmaz.

### İndirimli siparişin KDV'si

`submit_order.php:851` indirimi **toplamı hesaplamadan önce** KDV'den düşer
(`$tax - round($tax * ($discount / $subtotal))`), çünkü indirim matrahı
küçültür. `order_items.tax_total` ise indirimsiz satır vergisini tutar. İkisini
karıştıran her okuma yanlış fatura üretir.

`erp_order_lines()` indirimli satırda KDV'yi **kalan matrahtan yeniden alır**
(`erp_apply_rate($line_total - $pay, $oran)`), kendisinden pay düşmez — satırda
`oran × matrah = KDV` doğru kalmalı, e-fatura bunun üzerinden denetlenir.
Sonra satır toplamı siparişin başlık KDV'sine bağlanır: fark yalnız yuvarlama
ölçeğindeyse (KDV'li satır başına ≤1 kuruş) satırlara dağıtılır, daha genişse
kapı reddeder. Kapı `erp_next_number()`'dan **önce** çalışır; reddedilen deneme
numara tüketmez.

Demo verisi bu kuralı uygulamıyordu; `_seed_demo.php` düzeltildi ve mevcut
satırlar `_seed_fix_discount_tax.php` ile onarıldı (yalnız hatanın imzasını
taşıyanlar; tekrar koşturulabilir).

---

**Sipariş başlık tutarları kalem değildir.** `orders.shipping`, `orders.discount`,
`orders.surcharge`, `orders.gift_card_discount` yalnız başlıkta durur;
`order_items` toplamı siparişin toplamı **değildir**. Faturaya veya dışarıya
gönderen her kod bunları ayrı ayrı ele almak zorunda. Özellikle `discount`:
vergiden de başlıkta düşülür (`submit_order.php:850`) ama `order_items.tax`
indirimsiz kalır, yani satırları olduğu gibi göndermek fazla faturalamaktır.

### `orders`
- Sadece `ECOMMERCE === true` iken erişilebilir
- Fiyat kolonları **kuruş cinsinden** saklanır; dış API de kuruş döner (`api_money()`), panel gösterirken `/100` yapar
- **Dikkat — gerçek kolon adları** (yanlış tahminlere karşı):

| Kolon | Değil |
|---|---|
| `tax` | ~~`tax_total`~~ |
| `shipping` | ~~`shipping_total`~~ |
| `subtotal` | ✓ doğru |
| `discount` | ✓ doğru |
| `surcharge` | ✓ doğru |
| `total` | ✓ doğru |
| `user_id` | ~~`user`~~ (FK to users, **NOT** orders.user) |
| `order_date` | int unix timestamp (NOT datetime string) — `(int)$order['order_date']`, not `strtotime()` |

**Order_items kolonu YOK olanlar:**
- ❌ `item_number` (SKU) — yerine **products.code** JOIN ile çekilir (`p.code AS item_code`)

**Opsiyonel kolonlar (legacy şemada YOK, sadece upgrade'li install'larda):**
- `notes` — sipariş notları. Defensive: `SHOW COLUMNS FROM orders LIKE 'notes'` ile probe edip sorgula.
- `member_id` — orders'da değil, **`contacts.member_id`** (FK via `orders.contact_id`)

**Status enum:** `'incomplete', 'complete', 'exported', 'cancelled'` (2026.1.26'da `'cancelled'` eklendi).

**API'de dönen `orders` (liste) alanları:**
`id`, `order_number`, `order_date`, `status`, `billing_first_name`, `billing_last_name`, `billing_email_address`, `billing_company`, `subtotal`, `discount`, `tax`, `shipping`, `surcharge`, `total`, `payment_method`, `transaction_id`, `special_offer_code`, `user`, `member_id`

**API'de dönen `orders` (tekil, id ile) ek alanlar:**
`billing_address_1`, `billing_address_2`, `billing_city`, `billing_state`, `billing_zip_code`, `billing_country`, `billing_phone_number` + `items[]` dizisi

`items[]` her satır için: `id`, `product_id`, `name`, `catalogue_name`,
`quantity`, `price`, `tax`, `shipping`. **İki ad**, çünkü iki ayrı soruya cevap
veriyorlar: `name` satırın kendi kopyası (`order_items.product_name`, satış
anında yazılır, faturaya giden odur), `catalogue_name` kataloğun bugünkü hâli
(`products.name`, ürün silinmişse `null`). `tax` ve `shipping` **birim
başınadır**.

Bu diziye üç satır yukarıdaki "YOK olanlar" listesine rağmen `item_number`
yazılmıştı ve `GET /orders/{id}` hiç çalışmadı (2026-09-06'da düzeltildi).
Aynı belgede biri doğru biri yanlış iki satır varsa, kolonu **şemadan**
doğrula. `orders.notes` için de aynısı: `api_orders_has_notes()` yokluyor.

### Sipariş İptal Etme (2026.1.26)
**Config defaults** (data/config.php):
- `ECOMMERCE_ORDER_CANCEL_ALLOWED` (bool, default `false`) — Müşteri self-service iptal etkin mi
- `ECOMMERCE_ORDER_CANCEL_UNTIL_SHIPPED` (bool, default `true`) — Sadece kargolanmadan önce iptal edilebilsin mi

**Endpoint:** `cancel_order.php` — CSRF + ownership check + status update. **Refund manuel** (gateway dashboard'unda). REFUND ACTION REQUIRED log entry'si bırakılır.

**Order View widget tokenları:**
- `__cancel_form` — pre-built HTML form (CSRF + order_id + reason + submit + refund warning)
- `__cancel_status` — `?cancelled=1|shipped|already` flash mesajı için alert

**Visibility flag'leri** (order_view default tree'sinde otomatik wire'lı):
- `can_cancel` — config açık + status != 'cancelled' + (opsiyonel) henüz kargolanmamış
- `is_cancelled` — status == 'cancelled'
- `cancel_flash_success` / `cancel_flash_shipped` / `cancel_flash_already`

**Yeni kolonlar** (orders): `cancelled_at INT(10) UNSIGNED`, `cancelled_by INT(10) UNSIGNED`, `cancellation_reason VARCHAR(500)`

### Sipariş İptal — Admin UI + Email + Auto-refund (2026.1.27)

**Shared core:** `process_order_cancellation($order_id, $reason, $is_admin, $user_id, $attempt_refund = null)` (functions.php). `cancel_order.php` (müşteri + admin per-row), `edit_orders.php` (`action=cancel` bulk) **ve** `view_order.php` (detay ekranı modalı) bu fonksiyonu çağırır — başka iptal yolu yoktur. Status / shipment gates + DB update + log + opsiyonel email + opsiyonel Iyzipay refund. Dönüş: `['status' => 'success'|'already'|'shipped'|'not_found'|'error', 'refund_status' => ...]`.

**Admin Cancel UI** (`view_orders.php`):
- Her satırda "İptal" butonu (status `cancelled`/`incomplete` değilse görünür) → `type="button"` + `data-order-id`, sayfa formunun dışındaki `#pg-admin-cancel-form`'u POST eder + CSRF + `pgConfirm()` modal (bkz. 2026.1.29 — satıra `<form>` konmaz)
- Bulk: "Cancel Selected" butonu (mevcut "Delete Selected" pattern'iyle) → `edit_orders.php` `action=cancel` döngüsü, sequential
- Status filter ve badge **'cancelled'** kullanır (eski 'canceled' yazımı backward-compat olarak kabul edilir)

**Email notification** (`process_order_cancellation` içinde, defensive try/catch):
- Config gate: `ECOMMERCE_CANCEL_EMAIL_NOTIFICATION` (bool, default `true`)
- Müşteri billing_email_address'ine `email()` helper ile HTML formatında bildirim (Türkçe — `Order Cancelled`, `Cancellation reason`, refund açıklaması)
- `EMAIL_ADDRESS` yoksa veya billing_email_address boşsa sessizce atlanır

**Iyzipay auto-refund** (opt-in):
- Config gate: `ECOMMERCE_ORDER_CANCEL_AUTO_REFUND` (bool, default **`false`**)
- True ise: Iyzipay Cancel API (`\Iyzipay\Model\Cancel::create`) çağrılır, sonuç `orders.refund_status` kolonuna yazılır
- Cancel başarısız olursa cancellation geri ALINMAZ — log'a düşer, operator manuel iade yapabilir
- Sadece `payment_method ∈ {Iyzipay, Pay With Iyzico}` + `transaction_id` dolu + `total > 0` durumda denenir

**Yeni kolonlar** (orders, 2026.1.27 migration — defensive `SHOW COLUMNS` probe `_orders_has_refund_columns()`):
- `refund_status ENUM('', 'pending', 'refunded', 'failed', 'manual_required')`
- `refunded_at INT(10) UNSIGNED NULL`
- `refund_reference VARCHAR(255)` — gateway transaction ID

### Sipariş İptal — Tek Akış + Onarım (2026.1.29)

**Neden:** İptal iki ayrı yerde ayrı yazılmıştı ve ikisi de kırıktı.

| Kırık nokta | Sebep |
|---|---|
| `view_orders.php` satır-içi İptal butonu hiçbir şey yapmıyordu | Buton, sayfa genelindeki `<form action="edit_orders.php">` **içinde** ikinci bir `<form>` içindeydi. HTML iç içe form'a izin vermez → parser iç `<form>` etiketini **tamamen düşürür**. Buton dış formu boş `action` ile gönderiyordu, gizli inputlar + ikinci CSRF token dış forma sızıyordu, `querySelectorAll('.pg-admin-cancel-form')` hiçbir şey bulmadığı için onay handler'ı da bağlanmıyordu. |
| `view_order.php` iptali siparişi iptal etmiyordu | Legacy `cancel_order()` enum'a `'canceled'` (tek L) yazıyordu. `upgrade_to_2026_1_26()` enum'u `'cancelled'` (çift L) yapıp `'canceled'`ı **enum'dan çıkarmıştı** → UPDATE sessizce `''` yazıyordu (strict mode'da hata veriyordu). Aynı ALTER, o an DB'de duran `'canceled'` satırlarını da `''`e çevirmişti. |
| Detay sayfasında iptal butonu sık sık hiç görünmüyordu | Buton `ECOMMERCE_PAYMENT_GATEWAY == 'Iyzipay' && $transaction_id` koşuluna bağlıydı; havale / kapıda ödeme siparişlerinde çıkmıyordu. |

**Kurallar:**
- **Tek giriş noktası:** `process_order_cancellation()`. `cancel_order()` **kaldırıldı**; hediye kartı geçersiz kılma mantığı bu fonksiyona taşındı.
- **Enum yazımı `'cancelled'` (çift L).** `view_orders.php` filtre/badge'inde eski tek-L yazımı yalnızca *okuma* tarafında backward-compat kabul edilir; **hiçbir yere yazılmaz**.
- Yeni 5. parametre `$attempt_refund` (`null` = config'i takip et, `true`/`false` = zorla). Detay ekranındaki modal `true` geçer — operatör "ödeme iptal edilecek" yazan modalı onaylamıştır.
- **Sipariş tablosu satırına asla `<form>` koyma.** Satır butonları `type="button"` + `data-order-id`; POST hedefi, sayfa formunun **kardeşi** olan tek gizli `#pg-admin-cancel-form`. Listener `document`'a **delegate** edilir (tablo DataTable — satırlar sayfalama/sıralamada DOM'dan çıkıp giriyor).
- Restore işlemi `cancelled_at/cancelled_by/cancellation_reason` ve (refunded değilse) `refund_status/refunded_at/refund_reference` alanlarını da **temizler** — yoksa geri alınmış siparişte order_view timeline'ında "İptal Edildi" adımı asılı kalır.

### Kargo Kapısı — Kim, Neye Göre Engellenir (2026.1.29)

**Yönetici hiçbir zaman engellenmez.** Panelden iptal eden operatörün kodun
bilmediği bağlamı vardır (müşteri aradı, kargo geri döndü, mükerrer sipariş).
`process_order_cancellation()` içinde kapı `!$is_admin` ile çevrilidir.

**Müşteri yalnızca takip kodu varsa engellenir.** Ölçüt `_order_has_shipped()`:

| Kaynak | Kolon |
|---|---|
| Sipariş geneli | `orders.tracking_code` |
| Alıcı bazlı (çoklu alıcı) | `shipping_tracking_numbers.number` (boş satır sayılmaz) |

**`ship_date` bilerek yok sayılır.** Birçok kurulumda sipariş anında *planlanan*
sevk tarihi olarak doldurulur; ona bakan eski kural, daha binadan çıkmamış
siparişlerde müşteriye iptali kapatıyordu.

**`$is_admin` artık yük taşıyan bir parametre** — log notunun yanında kargo
kapısını da atlatır. Ziyaretçiye açık bir yoldan rolü doğrulamadan `true`
geçirme.

**"Operatör" rol 0 demek değildir.** `cancel_order.php` `USER_MANAGE_ECOMMERCE`
sabitine bakar (= `validate_ecommerce_access()` kuralı: rol < 3 veya
`manage_ecommerce` bayraklı rol-3 kullanıcı). Eskiden `USER_ROLE === 0` bakıyordu;
`view_orders.php` Manager ve Designer'ı da içeri aldığı için onların satır-içi
İptal butonu **müşteri dalına** düşüp "bu siparişin sahibi değilsin" diye
reddediliyordu — aynı ekrandaki toplu iptal ise çalışıyordu
(`edit_orders.php` koşulsuz `true` geçiyor). Aynı ekran, aynı kullanıcı, iki
farklı cevap.

**`validate_user()` PK'yı `id` anahtarıyla döndürür, `user_id` değil.**
(`user_id` ham kolon adı; `api.php` / `apps.php` auth yolunda o geçerli.)
Yanlış anahtarı okumak `cancelled_by = 0` yazıyordu — raporlar bunu "müşteri
kendi iptal etti" diye yorumlar.

### DATE Kolonunu `''` ile Karşılaştırma (strict mode + PHP 8.1 tuzağı)

`ship_tos.ship_date`, `gift_cards.expiration_date`, `arrival_date` gibi kolonlar
`DATE NOT NULL DEFAULT '0000-00-00'`. "Boş" değerin karşılığı **sıfır tarih**,
boş string **değil**.

```sql
AND ship_date != ''            -- ❌ MySQL strict: "Incorrect DATE value: ''"
AND ship_date != '0000-00-00'  -- ✅ kod tabanının her yerinde çalışan kalıp
```

MySQL `''`i DATE'e çeviremez ve strict mode'da hata verir. Sorgu `false` döner
(2026-09-12'den beri; öncesinde PHP 8.1 varsayılanıyla `mysqli_sql_exception`
fırlatıp sayfayı 500'lüyordu — bkz. "Veritabanı"), yani satır sessizce
kaybolur: `db()/db_value()` boş döner ve ekran eksik veriyle çizilir.

Sıfır tarih *karşılaştırması* (`= '0000-00-00'`) sorunsuzdur — kod tabanında 17
yerde çalışıyor. Sorun yalnızca `''`.

Bu tuzak iptal özelliğinde iki yerde vardı (`process_order_cancellation()`
kargo kontrolü + order_view `can_cancel` bayrağı). İkisi de sipariş iptali
zaten kırık olduğu için hiç tetiklenmemişti; iptal düzelince ortaya çıktı.

### Order View — Kargo Takip Linki (2026.1.27)
**Token'lar:**
- `__tracking_code` — ham takip numarası (orders.tracking_code)
- `__tracking_link` — kargo firması deep-link URL'i (provider çözülemezse boş)
- `__tracking_company_name` — kargo firması görünür adı (örn. "Yurtiçi Kargo")

**Visibility flag:** `has_tracking_link` (hem code hem provider çözülürse true)

**Provider kaynağı:**
1. `orders.tracking_company` kolonu (opsiyonel — `SHOW COLUMNS` ile defensive probe)
2. Yoksa: `ECOMMERCE_DEFAULT_TRACKING_PROVIDER` config define

**Desteklenen provider key'leri ve URL template'leri:**

| Key | Kargo | URL template |
|---|---|---|
| `yurtici` | Yurtiçi Kargo | `https://www.yurticikargo.com/tr/online-servisler/gonderi-sorgula?code={code}` |
| `aras` | Aras Kargo | `https://kargotakip.araskargo.com.tr/?code={code}` |
| `mng` | MNG Kargo | `https://service.mngkargo.com.tr/iettransport/iettransport.svc/json/GetMngShipmentStatus?Code={code}` |
| `ptt` | PTT Kargo | `https://gonderitakip.ptt.gov.tr/Track/summary?id={code}` |
| `surat` | Sürat Kargo | `https://www.suratkargo.com.tr/KargoTakip/?kargotakipno={code}` |

Yeni provider eklemek: `_eo_tracking_provider_url()` + `_eo_tracking_provider_label()` her ikisine de ekle (`functions.php`). URL template'inde literal `{code}` sentinel kullan.

### Order View — Timeline (2026.1.27)
**Token:** `__timeline` — pre-built `<ul.pg-ov-timeline>` Bootstrap 5 + Bootstrap Icons HTML (inline CSS, self-contained).

**Visibility flag:** `has_timeline` (en az 1 event'in timestamp'i varsa true)

**Event'ler ve veri kaynakları (boş olanlar atlanır):**

| Event | Icon | Renk | Veri kaynağı |
|---|---|---|---|
| Sipariş Oluşturuldu | bi-receipt | primary | `orders.order_date` |
| Ödeme Alındı | bi-credit-card | success | `transaction_id` doluysa `order_date` proxy |
| Kargoya Verildi | bi-truck | info | `MIN(ship_tos.ship_date)` |
| Teslim Edildi | bi-box-seam | success | `MIN(ship_tos.delivery_date)` |
| İptal Edildi | bi-x-circle | danger | `orders.cancelled_at` (probe — 2026.1.26+) |

### Order View — Yazdırılabilir Fatura (2026.1.27)
**Token:** `__order_invoice_pdf_url` — müşteriye yönelik yazdırılabilir fatura endpoint URL'i.

**Visibility flag:** `can_print_invoice` (sadece login'li sahip için true; misafir siparişlerinde gizli)

**Endpoint:** `order_invoice_print.php`
- GET-only (state değişikliği yok → CSRF token gerekmiyor)
- `order_id` parametresi zorunlu
- **Ownership check:** `orders.user_id === USER_ID` veya admin (`USER_ROLE === 0`)
- Sayfa yüklendiğinde otomatik `window.print()` çağrılır (Ctrl+P kaydet-as-PDF için)
- Bağımlılık yok — tcpdf/mpdf/dompdf bundled değil; HTML print-CSS yaklaşımı bilinçli tercih

### EO Widget — Sipariş Notu (2026.1.28)
**Yeni eo_field binding:** `notes` (input/textarea üzerinde `_bindings.eo_field='notes'`).

Designer EO widget içinde textarea/input ekler, eo_field binding ile `notes`'a bağlar. submit_order.php defansif kolon kontrolü yapar (`SHOW COLUMNS FROM orders LIKE 'notes'`), kolon varsa `orders.notes`'a kaydeder; yoksa sessizce atlar. Legacy install'lar bozulmaz, upgrade'liler özellik kazanır.

### EO Widget — Adres Defteri (2026.1.28)
**Yeni section binding:** `address_book` (semantic div üzerinde `_bindings.section='address_book'`).

Sadece giriş yapmış üyeler için render edilir. Üyenin geçmiş `complete`/`exported` siparişlerinden DISTINCT billing adreslerini çekip `<select>` olarak gösterir (en fazla 10 adres). Visitor bir adres seçince inline JS, sipariş formundaki billing_* inputlarını otomatik doldurur (name attribute'una göre `querySelector`).

- Guest / ghost mod / geçmiş siparişi olmayan üye → boş string döner (binding sessizce gizlenir)
- Payload her option'da `data-pgab='{"billing_first_name":"…",…}'` JSON olarak inline
- JS one-shot (`window.__pgEoAddressBookBound`) — birden fazla EO widget olsa bile tek bağlanır
- Fill sonrası her input için `change` event dispatch'i — country/state cascade'ler tetiklenir

**Hızlı doldurma (B2):** Member contact bilgilerinden billing_* otomatik prefill `get_express_order.php`'de ZATEN mevcut (line ~2587-2670 contact fetch + assign_field_value zinciri). Yeni iş gerekmedi.

### Cart Widget — Düşük Stok Uyarısı (2026.1.28)
**Yeni config define:** `ECOMMERCE_LOW_STOCK_THRESHOLD` (int, default tanımsız = özellik kapalı).
Operator `data/config.php`'de `define('ECOMMERCE_LOW_STOCK_THRESHOLD', 5);` yazarak aktif eder.

**Yeni cart loop token'ları:**

| Token | Ne döner |
|---|---|
| `__item_has_low_stock` | `'1'` (low) veya `''` (normal/sınırsız) — visibility flag olarak kullanılabilir |
| `__item_stock_warning` | "Son 3 adet kaldı" (low stock varsa), aksi takdirde `''` |
| `__item_inventory_quantity` | Kalan stok adedi (inventory takipliyse), aksi `''` |

Uyarı sadece şu durumda çıkar: `inventory=1 AND backorder=0 AND quantity > 0 AND quantity <= threshold`. Stok izlenmiyorsa veya backorder izinliyse uyarı yok. Empty token'lar standard loop preg_replace cleanup'ı ile temizlenir — designer token'ı koymadıysa fark yok.

### Sepet + Order View — Legacy Parite Turu (2026.1.30)

**Order view salt-okunur bloklar:**
- `_pg_render_order_item_form_data_readonly()` / `_pg_render_order_item_gift_card_readonly()`
- Token: `__item_form_data_html`, `__item_gift_card_html`. Tasarımcı token'ı
  koymadıysa satır sonuna otomatik eklenir (shopping_cart ile aynı desen).
- Değer semantiği legacy `get_submitted_product_form_content_with_form_fields()`
  ile aynı: çoklu değer virgülle birleşir, `wysiwyg` alan escape **edilmez**.

**Maskeli kart:** `_pg_mask_order_card_number()`. `orders.card_number` üç
biçimde durabilir — zaten maskeli (`*` ile başlar) / şifreli (>16 karakter,
mcrypt gerekir, PHP 7.2+ ile pratikte çözülemez) / düz. Çözümleme sırası
legacy `get_order_receipt.php:1265` ile birebir. BIN öneki **yalnızca gerçekten
elimizdeyse** basılır. Token `__card_number_masked` / `__card_type`, bayrak
`has_card_number` / `has_card_type`.

**Durum rozeti sınıfları artık ayarda:** `_pg_order_status_stage()` durumu
6 aşamaya indirger (complete / pending / shipped / cancelled / refunded /
default), `_pg_order_status_badge_class()` cfg'den okur.
**JS ikizi `SD_OV_STATUS_CLASS_DEFAULTS` — lockstep güncelle.**

**Sepet satır tipleri** (legacy `get_shopping_cart.php:770-815` ile aynı dallanma):

| Satır | Adet kontrolü | Token |
|---|---|---|
| `selection_type='donation'` | Para birimi önekli tutar kutusu (`donations[<id>]`) | `__item_is_donation` |
| `added_by_offer=1` | Düz sayı, düzenlenemez | `__item_added_by_offer` |
| normal | Qty stepper | — |

Bağış tutarı `cart_action.php`'de binlik ayracından temizlenip kura bölünüp
**`round()`** ile kuruşa çevrilir; ≤0 satırı siler.

**Kampanya hediyeleri (pending offers):** `_pg_render_pending_offers()`,
section binding `pending_offers`, token `__pending_offers`.
**Kendi `<form>`'u vardır** — sepet güncelleme formunun içine konursa HTML
parser iç formu düşürür ve "Ekle" yanlış formu gönderir. Alan adları legacy
ile birebir olmak zorunda (`add_pending_offers()` substr/explode ile ayrıştırır).

**Sepet sorgusunda eksik olan stok kolonları:** `inventory` /
`inventory_quantity` / `backorder` hiç SELECT edilmiyordu → 2026.1.28'de
eklenen düşük stok uyarısı **hiç tetiklenemiyordu.** Yeni: `out_of_stock_message`
ile satır bazlı stokta-yok mesajı (`_pg_rich_text_to_inline()`'dan geçer).

**Vergi/kargo uyarısı:** `_pg_cart_tax_shipping_notice()` → `__tax_shipping_notice`.
Yalnızca gerçekten bilinmeyeni söyler; ikisi de geçerli değilse boş döner.

### Sepette Grup Bantları (alıcı / yinelenen)

Sepet döngüsü düz bir liste; legacy'nin iç içe blokları yok. Gruplama
**"koşunun ilk satırını işaretle"** kalıbıyla yapılır: satırlar önce
sıralanır, sonra döngü bir imleç tutarak grup başını bulur ve yalnız o
satırda bant token'ını doldurur.

| Grup | Sıralama | Bant token |
|---|---|---|
| Alıcı | SQL `ORDER BY ship_to_id, id` | `__item_recipient_heading` |
| Yinelenen | PHP `usort` (alıcı → bugün/yinelenen → id) | `__item_recurring_heading` |

Yinelenen ayrımı **SQL'de sıralanamaz** — `is_recurring` bir tarih
karşılaştırmasına bağlı (`recurring_start_date == bugün` ise satır yine
"bugünün ücretleri"ne girer, legacy `:1155`).

Alıcı bandı legacy'nin üç koşulunda çıkar: kargo açık + çoklu alıcı modu +
`ship_to_id > 0`. `ship_to_id = 0` "kendim" demektir, ayırt edilecek bir şey
yoktur, bant basılmaz.

### Sepette Ödeme Planı ve Liveform Kopyası (2026-09-16)

`recurring_payment_period` / `recurring_number_of_payments` /
`recurring_start_date` sütunlarını **yalnız sepet güncellemesi yazar**
(`add_order_item()` yazmaz). Legacy'de ziyaretçi ödeme adımına o POST'tan
geçerek varır; widget'ın Ödemeye Geç'i düz bağlantı olduğu için renderer
düzenli bir satırın boş planını ilk gösterimde ürün varsayılanlarıyla yazar
(`_pg_cart_recurring_defaults`) ve `cart_action.php` her güncellemede her
düzenli satırı yazar — müşteri düzenleyebiliyorsa gönderileni (legacy alan
adları, gateway kuralları), değilse varsayılanı. Reddedilen satır saklı
planını korur. Token'lar `__item_recurring_schedule_html` (konmazsa satır
sonuna eklenir), `__item_recurring_editable`, `__item_recurring_start_date`.

**Sepet renderer'ı Messages düğümünü satır döngüsünden ÖNCE çizer**
(statik ağaç ~568, döngü ~650). Düğüm `shopping_cart` formunu tüketir; döngü
içinde `$_pg_cart_form_lf->get_field_value()` çağıran kod o anda silinmiş
formu okur ve hiç değer bulamaz (hediye kartı bloğu böyleydi). Satır
kurucuları **`$_pg_cart_lf_snapshot`** (`pg_liveform_snapshot`, ağaç
çizilmeden önce alınan kopya) okur; yeni bir satır bloğu liveform'a
ihtiyaç duyarsa aynı kopyayı alır, liveform nesnesini değil. Üyelik
widget'larındaki "önce oku, sonra Messages düğümünü ekle" kuralının sepet
biçimi budur.

### Personele Özel Kontroller İki Yerde Gate'lenir

`__offline_payment_checkbox` müşteriye görünmez (rol < 3 veya
`set_offline_payment`). Kapı hem render'da hem `cart_action.php` POST
tarafında ayrı ayrı kontrol edilir. **İkincisi şart:** checkbox yalnızca
HTML; ziyaretçi `offline_payment_allowed=1` POST edip ödemeden sipariş
tamamlayabilirdi. Render tarafındaki gizleme bir yetki kontrolü değildir.

### Tablo Widget'larında Dar Ekran (2026.1.30)

Legacy'nin `table.mobile_stacked` media query'si (livesite.src.css:490) sistem
widget'a taşınmamıştı. EO sepet tablosunun sağ sütunları sabit
`6+8+8+3 = 25rem`; ~900px altında ürün adı sütunu 188px'e düşüp beş satıra
sarıyordu. `table-responsive` çare değil — tablo taşmıyor, sıkışıyor.

**Çözüm (Bootstrap-only, ek CSS yok):**

```
thead → d-none d-md-table-header-group
tr    → d-block d-md-table-row (+ border-bottom, mobilde satır ayrımı için)
td/th → d-block d-md-table-cell
text-end → text-start text-md-end
```

Qty stepper'daki sabit `width:9rem` → `max-width:9rem` (sabit genişlik tüm
tablonun küçülemediği taban oluyordu).

**Üç yerde birden güncellenir:** `_eo_default_designer_tree()` (PHP),
`_buildExpressOrderStarterTree()` (JS), `_eo_render_cart_summary()` (sunucu
fallback). `colspan` satırlarına `d-block` **verilmez** — hücre tablo
düzeninden çıkınca colspan iptal olur.

### Cart Widget — Save for Later (2026.1.28)
**Yeni config define:** `ECOMMERCE_SAVE_FOR_LATER` (bool, default tanımsız/false).

**Yeni DB kolonları** (order_items): `saved_for_later TINYINT(1) DEFAULT 0`, `saved_at INT(10) UNSIGNED`, `idx_saved_for_later` index. Upgrade `2026.1.28` ile gelir.

**cart_action.php endpoint** (opt-in — sadece define açıksa çalışır):
- POST `submit_save_for_later=1` + `save_item_id=<int>` → satırı saved listesine taşır
- POST `submit_restore_saved=1` + `save_item_id=<int>` → sepete geri döner
- CSRF yok (cart_action genel deseni); ownership check `order_items.order_id === session_order_id`
- Defensive kolon probe: özellik açık ama kolon yoksa sessiz no-op

Aktif cart query'si `saved_for_later = 0` filter'ı uygular — saved item'lar cart'ta görünmez. Saved item listesini render eden ayrı widget/section şu an YOK; designer mevcut remove_url + custom POST pattern'iyle UI kurabilir. Tam UI implementasyonu sonraki cycle'da.

### Designer Canvas — Görsel Placeholder + Loop Band (2026.1.28)
- **Image binding ipucu:** Bağlı `<img>` elementinde src boşsa SVG placeholder içinde token adı görünür (`[item_image]` gibi) — designer hangi binding olduğunu canvas'tan görür.
- **Loop area info-band:** `sd-loop-area` artık `<div class="sd-loop-band">` gerçek DOM element'iyle başlık taşır (önce ::before pseudo idi). Bootstrap Icon `bi-arrow-repeat` + "Loop alanı — 1 kayıt gösteriliyor (runtime'da her kayıt için tekrarlanır)" metni. Sadece canvas — frontend etkilenmez.

### `ads` (Reklamlar)
- Sadece `ADS === true` iken erişilebilir
- Düzenleme: `edit_ad.php?id={id}`

### `comments` (Yorumlar)
- `edit_comment.php?id={id}`

---


## Dosya Yapısı (Önemli Dosyalar)

| Dosya | Açıklama |
|---|---|
| `init.php` | Bootstrap: DB, sabitler, session, hata raporlama |
| `router.php` | URL yönlendirme |
| `functions.php` | Tüm yardımcı fonksiyonlar (~24000+ satır) |
| `liveform.class.php` | Form doğrulama ve session yönetimi |
| `edit_config.php` | Config dosyası düzenleme arayüzü |
| `find_and_replace.php` | Toplu bul-değiştir (yeniden yazıldı) |
| `view_files.php` | Dosya yönetimi |
| `view_design_files.php` | Tasarım dosyaları yönetimi |
| `data/config.php` | Canlı config (define'lar) |
| `data/config(default).php` | Varsayılan config şablonu |
| `includes/local/tr.json` | Türkçe çeviri dosyası |
| `install/index.php` | Kurulum ekranı; yükseltmeyi `includes/migrations/runner.php`'ye devreder |
| `includes/migrations/` | Sürüm geçmişi (`versions.php`), sürüm başına şema dosyası, `legacy.php`, `runner.php` |

### `.src.js` Düzenlenir, `.min.js` Servis Edilir — İkisi Birden

`ENVIRONMENT_SUFFIX` sabiti hangisinin yükleneceğini seçer
(`frontend.<suffix>.js`, `chat_backend.<suffix>.js`, `add_to_cart.<suffix>.js`,
`assets/lib/dropzone/dropzone.<suffix>.js`) ve çoğu kurulumda `min`'dir.
**Yalnız `.src.js`'i düzenlemek hiçbir şey yapmaz** ve hata vermez: kod
doğrudur, dosya okunmaz. Belirti "kodu değiştirdim, sayfada eski davranış".

`assets/js/` altındaki dosyaların (`backend.src.js`, `style_designer.js`,
`page_designer.js`…) minifiye ikizi **yoktur**, doğrudan servis edilirler —
bu yüzden ayrım kolayca gözden kaçar. Karar ölçütü dosya adı değil, o dosyayı
basan `<script src>` satırında `ENVIRONMENT_SUFFIX` geçip geçmediğidir.

**Ön yüzde çalışan JS iki yerde aranır.** Panel ekranları `backend.src.js`
yükler; ziyaretçinin gördüğü sayfa `frontend.<suffix>.js` yükler. İkisinin de
kendi klavye kısayolu bloğu ve kendi araç çubuğu kancaları vardır. Bir
düğmeyi taşırken ikisini birden ara — yalnız `backend.src.js`'e bakmak
Ctrl+G'yi sessizce düşürdü (2026-09-13).

## DB Şema Değişiklikleri (includes/migrations)

**Kural:** Doğrudan SQL yazmak yerine tüm `ALTER TABLE` / `CREATE TABLE` değişiklikleri
yükseltme sistemi ile yapılır. Sistem 2026-08-28'den beri `install/index.php`'de değil,
`pinegrap/includes/migrations/` altındadır (install klasörü sunucudan silinebiliyor,
includes altı kalıyor):

| Dosya | Ne |
|---|---|
| `includes/migrations/versions.php` | Sürüm geçmişi, düz liste, sıra bağlayıcı. Satır silinmez, numara yeniden kullanılmaz. |
| `includes/migrations/<sürüm>.php` | O sürümün `upgrade_to_<sürüm>()` fonksiyonu (2026.2.5.php → `upgrade_to_2026_2_5()`). Yalnız DB'ye dokunan sürümlerin dosyası vardır; kod-only sürüm listede durur, dosyası olmaz. |
| `includes/migrations/legacy.php` | 2017.2 – 2023.2.1 fonksiyonları, olduğu gibi. |
| `includes/migrations/runner.php` | Yardımcılar (`install_add_column` …), `db()` emniyet ağı, kilit, koşum döngüsü `install_run_upgrades()`, ön kontrol `install_preflight()`, yedek `install_backup_database()`, son koşum işareti `install_last_*()`. |
| `install/index.php` | Yalnız ekran + form + temiz kurulum; yükseltmeyi runner'a devreder (`install_action=upgrade_step` / `backup_database` JSON uçları). |

### Yükseltme adımı nasıl yazılır

Her `ADD` / `CREATE` / `DROP` / `MODIFY` / `CHANGE` **önce sorar, sonra yapar**;
`db("ALTER TABLE x ADD ...")` yazmak yasaktır. Yardımcılar (`runner.php`):

```php
install_add_column('config', 'waf_enabled', "TINYINT(1) NOT NULL DEFAULT 0");
install_add_index('visitors', 'idx_user_agent', "INDEX idx_user_agent (user_agent(64))");   // tanım index adını içerir
install_create_table('waf_rate', "CREATE TABLE waf_rate ( ... ) ENGINE=InnoDB ...");
install_drop_column($t, $c);  install_drop_index($t, $i);  install_drop_table($t);
install_modify_column($t, $c, $def, $required = true);   // kolon yoksa hata; rename sonrası için $required = false
install_rename_column($t, $old, $new, $def);            // eski yoksa yeni varsa geçer
install_rename_table($old, $new);  install_set_engine($t, 'InnoDB');
install_table_exists / install_column_exists / install_index_exists / install_column_info($t, $c)
install_note('...');   // ekranda o sürümün altında görünen not
```

Probe'lar ada tam bakar (`SHOW COLUMNS ... WHERE Field =`, `information_schema`);
`LIKE` kullanılmaz çünkü `_` joker karakterdir. Veri ifadeleri (`UPDATE` / `DELETE`)
tekrar koşulabilir yazılır (`WHERE ... IS NULL`, `WHERE created_at = 0` gibi).
Enum daraltan bir `MODIFY` her zaman `install_column_info()` ile önceki şekli kontrol
eder (2026.1.14 / 2026.1.26 örnekleri).

**Emniyet ağı:** runner koşarken `db()`/`db_value()`/`db_item()`… başarısız bir şema
ifadesinde `install_query_failed()`'e sorar: 1050 (tablo var), 1060 (kolon var),
1061 (index var), 1091 (DROP hedefi yok), `DROP TABLE`'da 1146 → not düşülür,
geçilir. Diğer her hata (1062 duplicate entry, 1054, 1064…) `InstallQueryException`
olur; döngü sürümü "başarısız" işaretler, `config.version` son tamamlanan sürümde
kalır ve ekran ifadeyi + MySQL metnini gösterir. `exit()` yok.

**Koşum:** `install_run_upgrades($versions, $from_key, array('stream' => true))` —
`GET_LOCK` (yoksa `flock`), `ignore_user_abort(true)`, shutdown guard (fatal hata da
ilerleme dosyasına yazılır), **sürüm işareti fonksiyon döner dönmez, ekrana bir şey
basılmadan** yazılır. Yarıda kesilen yükseltme "Tekrar dene" ile kaldığı yerden sürer.
Dosyadaki sözdizimi hatası `ParseError` olarak yakalanır, yalnız o sürümü durdurur.

**Ekran (2026-08-29'dan beri) istek başına bir sürüm koşturur:** JavaScript
`install_action=upgrade_step` ile `array('one' => true)` çağırır, yanıttaki `next` ile
sürer; `locked` → 5 sn bekler, hata → "Tekrar dene", yanıt yoksa → "Devam Et".
Başarısız adım `data/temp/upgrade_last.json`'a yazılır; ekran açılışında "Son koşum"
satırı ve "Yükseltmeye devam et" düğmesi bundan gelir, sürüm geçince silinir.
Uç nokta denetimden sonra `session_write_close()` yapar (uzun adım paneli kilitlemesin).
JavaScript yoksa form gönderimi eski tek-istek akışına düşer. Büyük bir tabloyu yeniden
yazan yeni bir sürüm eklerken `install_heavy_tables()` haritasına `'<sürüm>' =>
array('<tablo>')` satırı eklenir; ön kontrol kartı satır sayısı / boyut uyarısını
oradan üretir. Ön kontrol uyarır, engellemez.

**Cron:** `php pinegrap/install/index.php automated_upgrade` (anahtar istemez) veya
`install/index.php?automated_upgrade=true&secret=<AUTOMATED_UPGRADE_SECRET>` —
sabit `data/config.php`'de, opt-in, ≥16 karakter; yoksa yol kapalı; ikisi de tek
istekte, düz metin. Yönetici oturumu (`software_update.php` yönlendirmesi) anahtar
istemez ve ekrana iner: döngü kendiliğinden başlar, bekleyen sürüm yoksa `welcome.php`'ye
döner. Yanlış anahtar 403 + deneme sayacı.

**Test:** `docs/_plan_upgrade_sistemi.md` "Test protokolü". Sahte-MySQL koşum
harness'ı (eski/yeni eşdeğerlik, çift koşum, yarım kalma) geliştirme kayıtlarında:
eski kod çift koşumda 41 sürümün 26'sında, 84 kesinti noktasında düşüyordu; yeni kod
sıfır.

### OpenSSL Anahtar Üretimi: Yapılandırma Dosyası Yoksa Üretim Yok

`openssl_pkey_new()` (ve `openssl_pkey_export()`) anahtar üretmeden önce bir
OpenSSL yapılandırma dosyası okur. Windows'ta kurulumla gelmez, paylaşımlı
hostta `OPENSSL_CONF` var olmayan bir yolu gösterebilir; ikisinde de çağrı
`error:07000072:configuration file routines::no such file` ile başarısız olur
ve argümanların doğruluğu bunu kurtarmaz. Belirti: anahtar üreten özellik
"kullanılamıyor" der, fonksiyonlar ve eğri listesi ise yerindedir.

Kural: anahtar üreten her yol önce normal denenir, başarısız olursa
`'config' => includes/openssl.cnf` ile bir kez daha denenir (`push.php`
`pg_push_create_vapid_keys()` örnek). O dosya ürünle dağıtılır, içinde gizli
veya site'a özel bir şey yoktur ve **sağlayıcı bölümü yazılmaz** — bölüm
yazılırsa yalnız sayılan sağlayıcılar yüklenir. Hata ayıklarken
`openssl_error_string()` döngüsü gerçek sebebi verir; `@` ile susturulan çağrı
tek başına "false" der.

### `disable_functions`: Host'un Kaldırabileceği Fonksiyon Sorulmadan Çağrılmaz

Paylaşımlı hostlar `disable_functions` ile fonksiyon kaldırır ve liste bize ait
değildir. **PHP 8'de devre dışı fonksiyon tanımsız fonksiyondur:**
`function_exists()` false döner, çağrı `Error` fırlatır, `@` susturmaz. İlk
saha çöküşü (2026-09-01) tam olarak buydu: ön kontrol kartındaki
`@disk_free_space()` bir müşteri sunucusunda yükseltme ekranını beyaz sayfaya
çevirdi.

Sahada görülen liste: `disk_free_space`, `disk_total_space`, `ini_get_all`,
`set_time_limit`, `ignore_user_abort`, `getmypid`, `php_uname`,
`sys_getloadavg`, `getrusage`, `error_log`, `mail`, `fsockopen`, `symlink`,
`chmod`/`chown`, tüm `exec`/`shell_exec`/`proc_*`/`posix_*` ailesi.

```php
if (function_exists('set_time_limit')) { @set_time_limit(0); }
$pid = function_exists('getmypid') ? getmypid() : uniqid();
$request['uname'] = function_exists('php_uname') ? php_uname() : PHP_OS;
```

Kural: bu fonksiyonların verdiği şey her zaman **isteğe bağlı** kabul edilir —
yoksa cevap "bilinmiyor" olur, akış durmaz. Kurulum / yükseltme yolunda
(`install/index.php`, `includes/migrations/*`) istisna yoktur: o yol yeni bir
sunucuda ilk kez çalışan yoldur. `runner.php`'de `install_log()` `error_log`
için sarmalayıcıdır; ön kontrol kartı `catch (Throwable)` ile sarılıdır ve
içinde ne patlarsa patlasın yükseltme yine sunulur.

### Yayınlanmış Sürüm Kapalıdır, Yayınlanmamış Sürüm Açıktır

**Yayınlanmış son sürüm ve hazırlanan (açık) sürüm `docs/degisiklikler.md`
başındaki "Dağıtım durumu" bölümünde yazar.** Yeni bir şey eklemeden önce
oraya bak.

Ölçüt **yayın**dır, geliştirme kurulumunda koşmuş olmak değil. Sunuculara
çıkmış bir numara kapalıdır: `$versions` satırına, `changelog.txt` başlığına
ve `upgrade_to_*` fonksiyonunun getirdiği DB değişikliğinin anlamına
dokunulmaz. Yayınlanmamış numara **açıktır**: yeni şema adımı o sürümün
girişine alt adım olarak eklenir (`upgrade_2026_4_4_<konu>()` gibi), changelog
bölümü nihai durumu anlatır, geliştirme içi düzeltmeler [DÜZELTME] olarak
yazılmaz. **Yayınlanmamış sürüm varken yeni numara açılmaz.** 2026.4.3'ten
sonra sekiz numara birikmesinin sebebi bu kuralın eski hâliydi ("dev'de bir
kez koşan numara da kapalıdır"); 2026-08-28'de sekizi tek 2026.4.4'e
indirildi ve kural değişti.

Yükseltme döngüsünün baktığı tek şey `config.version`:

```php
$function_name = 'upgrade_to_' . str_replace('.', '_', $version['number']);
if ($version_key > $database_version_key) { $function_name(); }
```

Anahtarı veritabanındakinden **büyük** olan sürüm çalışır, **eşit** olan
atlanır. Bu yüzden açık sürüme adım eklendiğinde, o sürümü zaten koşmuş dev
veritabanında yeni adım kendiliğinden işlemez; yeniden koşturmak için
`config.version` bir önceki yayınlanmış sürüme çekilir (`UPDATE config SET
version = '2026.4.3'`) ve yükseltme yeniden çalıştırılır. Veritabanına
erişimin olmadığı bir kurulumda aynı iş **Yapılandırma ekranındaki**
"Veritabanı Şema Sürümü" alanından yapılır (`edit_config.php`, yalnız
yönetici); alan yalnız `versions.php`'de bulunan numaraları kabul eder, çünkü
listede olmayan bir numara yükseltme ekranını tamamen kapatır. Bütün adımlar
"var mı → geç" korumalı olduğundan uygulanmış olanlar atlanır; bu aynı
zamanda fonksiyonun çift koşum testidir. Korumasız bir `ADD` / `CREATE`
yazmak yasaktır.

**Belirti:** "Özelliği ekledim ama ekranda görünmüyor / Upgrade seçeneği
çıkmıyor" → dev veritabanı sürümü zaten geçmiş; yukarıdaki gibi geri çek.
Yeni numara **açma**.

Adlandırma: sürüm girişi `upgrade_to_2026_4_4()` (sistemin kurduğu tek isim
budur), alt adımlar `upgrade_2026_4_4_recycle_bin()` gibi `to_` almaz —
onlar sistem tarafından değil, girişten çağrılır.

### Nasıl Eklenir?

Önce "Dağıtım durumu"na bak. **Açık (yayınlanmamış) sürüm varsa** 1. ve 2.
adımı atla: adımı `upgrade_to_<açık sürüm>()` girişine yeni bir alt adım
fonksiyonu olarak ekle ve girişten çağır; changelog'da o sürümün bölümünü
güncelle. Yalnız son sürüm yayınlandıysa yeni numara açılır:

1. `includes/migrations/versions.php` listesinin sonuna yeni numarayı ekle:
   ```php
   '2026.4.5',
   ```
2. DB'ye dokunuyorsa `includes/migrations/2026.4.5.php` dosyasını yaz (başlık + guard
   + fonksiyon; komşu dosyalardan kopyala):
   ```php
   if (!defined('INSTALL_OR_UPDATE')) { exit; }

   function upgrade_to_2026_4_5() {
       install_add_column('user', 'secret_key_hash', "VARCHAR(64) DEFAULT NULL");
       install_add_index('user', 'idx_secret_key_hash', "INDEX idx_secret_key_hash (secret_key_hash)");
       // Backfill: mevcut kayıtları doldur — tekrar koşulabilir yaz
   }
   ```
   Kod-only sürüm için dosya yok. `php -l` çalıştır.
3. Upgrade arayüzünden çalıştırılır — elle SQL çalıştırmak gerekmez. Dev DB'de iki kez
   koştur: ikincisinde ekran "zaten yerindeydi" demeli.
4. **Yeni tablo eklediysen `install/index.php` içindeki `get_tables()`
   listesine ekle** (temiz kurulumdan önce DROP edilecek tablolar; liste
   yalnız DROP için kullanılır, sıra önemsizdir). Atlanırsa aynı veritabanına
   yapılan temiz kurulum eski tabloyu yerinde bırakır, kurulumdan sonra koşan
   yükseltme onu görüp "zaten var" diye geçer ve site eski şemayla açılır —
   adım idempotent olduğu için hata da vermez, sessizce yanlış olur.
   (`functions.php` `check_and_repair_database_tables()` bir zamanlar ikinci
   bir liste tutuyordu; artık tabloları `information_schema`'dan kendisi
   buluyor, oraya bir şey eklenmez.)
5. **Değişiklik günlüğünü yaz** (aşağıya bak). Bu adım isteğe bağlı değildir.

### Bayat Migrasyon Dosyası — kapalı klasör, eski dosya, yarım şema

**Olay (2026-09-12, dev.kodpen.com):** ara dönemde konmuş 26 adımlı
`2026.4.4.php` zip 3–5 kez açılınca da eski hâliyle kaldı; runner onun kısa
listesini koştu, `config.version = 2026.4.4` yazdı, yeni kod `api_webhooks`
/ `waf_rate_limit_api` isteyince pano ve ayarlar 500 verdi. **Sebep OPcache
değil, klasör izni:** sunucuda `includes/migrations` (ve `includes/`
altındaki öteki klasörler) **0555**, içindeki dosyalar 0666, kök / `data` /
`install` / `assets` 0777. Kapalı klasöre yeni dosya eklenemez; geçici dosyaya
yazıp yeniden adlandıran açıcılar (çoğu) var olan dosyayı da değiştiremez —
dosya olduğu gibi kalır. Beta kanalıyla "var olan `2026.4.4.php`'nin üzerine
daha uzun hâlini yazmak" rutin iş.

Şemayı sürüm listesiyle çapraz doğrulayan bir katman denendi ve **kaldırıldı**
(sürdürülebilirliği baltalıyor; her sürümde ikinci bir liste istiyor). Kalan
koruma dosya sistemi tarafında:

| Katman | Nerede |
|---|---|
| Yazma izinleri taraması | `pg_write_permission_scan()` (`includes/fn/system_status.php`) — yazılım dizininin her klasörü, `data/` dışı her dosyası; istek başına bir kez, durum önbelleğinde 10 dk |
| Güncelleme ekranı | `software_update.php` indirmeden önce tarar; kapalı klasör varsa listeler, **Güncelle kapalı**, yönetici için **"Dosya izinlerini ayarla"** düğmesi (`api.php` `write_permissions_repair`) → onarım → sayfa yenilenir → normal akış |
| Sistem durumu kartı | "Yazma İzinleri" işi (ağırlık 12, `server`): klasör kapalıysa kırmızı + panelde yol/mod listesi, yalnız dosya kapalıysa sarı; **Onar** düğmesi aynı uca gider |
| Onarım | `pg_write_permission_repair()` — klasör 0777, dosya 0666 (güncellemeyi iki kullanıcı uygular: panel = web sunucusu, FTP/dosya yöneticisi = hosting hesabı); `chmod` yalnız sahibinde işler, başka sahibe ait girdiler adıyla raporlanır |
| Paket açma | `pg_extract_archive()` — açmadan önce yazılamayan hedefler `chmod`/`unlink`, kalan ya da klasörü yazılamayan → hiçbir şey açılmadan durur; açtıktan sonra boyut + CRC-32 doğrulaması (`stale`) |
| Şema-geride 500 vermez | `get_system_status_checks()` `api_webhooks`'u sormadan `SHOW TABLES LIKE` ile yoklar; `settings.php` `waf_rate_limit_api`'yi `waf_table_has_column()` kapısıyla yazar |

Kurallar:

- Bir migrasyonun eklediği tablo/kolonu **köprü dışında** okuyan kod da
  önce yoklar. (Tarihçe: `get_system_status_checks()` `api_webhooks` satırı
  500 vermişti — `@mysqli_query` istisnayı susturmuyor. 2026-09-12'den beri
  raporlama kapalı, ama yoklamanın sebebi bu değil: yok olan tabloyu okuyan
  kod hâlâ yanlış cevap üretir.)
  Kolon için `waf_table_has_column()`, tablo için `SHOW TABLES LIKE`.
- Belirti: "zip açtım, dosya eski kaldı" → önce Yazma İzinleri kartı: kapalı
  klasör (0555) var mı? Yeni dosya oraya eklenemez, geçici dosya + yeniden
  adlandırma ile açan araçlar var olanı da değiştiremez.
- Belirti: "site güncel görünüyor ama X tablosu yok" → `config.version`'ı bir
  önceki yayınlanmış sürüme çekip yükseltmeyi yeniden koştur (adımlar
  idempotent) — ama önce `includes/migrations/<sürüm>.php`'nin paketteki
  kopya olduğundan emin ol.

### Yükseltme Köprüsü Kuralı — kod şemadan önce iner

`software_update.php` dosyaları değiştirir, yükseltmeyi **sonra** koşturur;
elle dosya atılan sitede yükseltme hiç koşmamış da olabilir. O aralıkta **bu
sürümün kodu bir önceki sürümün tablolarında ve oturum biçiminde** çalışır.
2026.4.4 bunu bir kez yaşadı: 4.3 sitesi yönlendirmede oturumunu kaybetti
(eski oturumda `sessionuserid` yok), giriş `user_password_algo` seçti, kurulum
kilidi de aynı sütunu seçti — site kendi yükseltmesine ulaşamadı.

Köprüde çalışan kod **yeni sütun/tablo varsayamaz**:

- Giriş yolu (`validate_login`, `pg_password_verify`, API girişi), kurulum
  kilidi ve `initialize_user()` bir önceki yayınlanmış şemada çalışmak
  zorundadır. Yeni bir sütun/tablo okuyacaksa önce **probe**:
  `pg_user_has_password_algo()`, `pg_auth_tokens_table_exists()`,
  `pg_auth_tokens_have_pin()` kalıbı (static, SHOW COLUMNS/TABLES, istek başına bir kez).
- Kapı: `pg_require_current_schema()` — `validate_user()` ve giriş ekranı
  `index.php` ilk iş bunu çağırır; `config.version` koddan (`pg_code_version()`,
  versions.php'nin son satırı) gerideyse ziyaretçi `install/index.php`'ye gider.
  Kapının arkasındaki her şey güncel şemayı varsayabilir; probe oraya yazılmaz.
- Kurulum ekranının `check_if_administrator_is_logged_in()`'ı **bir önceki
  sürümün kimlik kanıtını da kabul eder** — hem oturum çifti (`sessionusername` +
  `sessionpassword` MD5) hem beni-hatırla çerez çifti (`software[username]` +
  `software[password]`), yalnız `install_bridge_open()` iken (config.version <
  kod). Kimlik biçimi değişen her sürümde bu fallback yeni eski-biçime göre
  güncellenir — köprü bu. Köprü kapanınca ölü koddur.
- `initialize_user()` eski oturum anahtarlarını ve eski çerez çiftini **şema
  geridenken temizlemez**; yükseltmeyi taşıyan tek kanıt onlar.
- Sürüm atlama serbesttir (2025.x → en güncel): kapı `version_compare` ile
  çalışır, runner sürümleri sırayla koşar, köprü son adıma kadar açık kalır.
- Her yeni sürümde şu test zorunlu: bir önceki yayınlanmış sürümün DB'si +
  yeni dosyalar → (a) eski yönetici oturumuyla `software_update.php` akışı,
  (b) oturumsuz `install/index.php` kilit ekranı → parola → yükseltme → giriş.

### Her Upgrade İçin Zorunlu: Değişiklik Günlüğü

Yeni bir sürüm numarası eklediğin her seferde **iki dosya** güncellenir:

| Dosya | Kim okur | Ne yazılır |
|---|---|---|
| `pinegrap/changelog.txt` | Operatör / müşteri | Sürüm başına birkaç madde. **Ne** değişti, kullanıcı ne fark edecek. En yeni sürüm en üstte. |
| `docs/degisiklikler.md` | Geliştirici | **Neden** değişti. Ölçüm, kök sebep, reddedilen alternatif, bilinen ödün. |

`changelog.txt` etiketleri: `[YENİ]` `[DÜZELTME]` `[HIZ]` `[ŞEMA]` `[GÜVENLİK]`
`NOT`. Şema değişikliği olan her sürümde `[ŞEMA]` satırı bulunmalı — operatör
yükseltmenin veritabanına dokunacağını görmeli.

**Ayrım neden var:** `changelog.txt` ürünle birlikte dağıtılır ve müşteriye
gider; oraya "MyISAM tablo kilidi 946 MB indeks güncelliyordu" yazılmaz.
`docs/degisiklikler.md` depoda kalır ve altı ay sonra "bunu neden böyle
yapmıştık" sorusunu cevaplar — `CLAUDE.md` kalıcı kuralları, o dosya ise o
kuralların hangi olaydan doğduğunu tutar.

**Bir hatayı düzeltiyorsan belirtiyi de yaz.** "IPv6 yasakları işlemiyordu"
tek başına aranabilir değil; "tek bir adres için onlarca mükerrer yasak satırı,
adres ise hiç engellenmiyor" aynı belirtiyi tekrar gören kişinin arayacağı
cümledir.

### Mevcut Değişiklikler

| Versiyon | Değişiklik |
|---|---|
| `2026.1` | `short_links.file_id`, `config.indexnow_key`, `iyzipay_3ds_state` tablosu, `custom_apps.permissions` (JSON) |
| `2026.1.1` | `config.allowed_bots`, `config.block_unknown_bots` |
| `2026.1.2` | `visitors` tablosuna `idx_dedup` index |
| `2026.1.3` | `user.secret_key_hash VARCHAR(64)` + index + backfill (API hızlı arama) |
| `2026.1.25` | `files.image_width/height/optimization_percent` (lazy cache) |
| `2026.1.26` | `orders.status` enum'a `'cancelled'` eklendi + `cancelled_at/cancelled_by/cancellation_reason` kolonları (müşteri self-service iptal) |
| `2026.1.28` | `order_items.saved_for_later/saved_at` + `idx_saved_for_later` index (Cart Save-for-Later özelliği için altyapı, ECOMMERCE_SAVE_FOR_LATER opt-in) |
| `2026.1.27` | `orders.refund_status` ENUM + `refunded_at` + `refund_reference` (admin cancel UI + email bildirim + Iyzipay auto-refund) |
| `2026.1.29` | Veri onarımı (şema değişikliği yok): `2026.1.26` ALTER'ının `''`e çevirdiği `orders.status` satırları — `cancelled_at` doluysa `'cancelled'`, değilse `'complete'` |
| `2026.2.4` | WAF: `config.waf_*` (18 kolon), `waf_log` / `waf_rate` / `waf_ip_reputation` tabloları, `banned_ip_addresses`'e `list_type` / `source` / `note` / `expires_at` / `created_at` / `hit_count` |
| `2026.2.5` | `visitors.user_agent VARCHAR(255)` + `idx_user_agent(user_agent(64))` prefix index |
| `2026.2.6` | `waf_log` DROP+CREATE (toplama şemasi: `event_key`/`window_start`/`hit_count`/`last_seen` + `uniq_event`), `config.waf_log_max_rows`, retention 30→14 gün |
| `2026.2.7` | `banned_ip_addresses.ip_address` VARCHAR(15)→VARCHAR(45) (IPv6 kesiliyordu), auto kayıt temizliği, mükerrer toplama, `uniq_entry(ip_address, list_type, source)` |
| `2026.3.1` | `waf_log.reference` + index; `perf_log`'dan `idx_peak_memory` / `idx_script` / `idx_duration` düşürüldü (rapor sorguları kullanmıyor, her istekte boşa yazılıyordu) |
| `2026.3.4` | `visitor_stats_hourly` + `visitor_content_hourly` (InnoDB, sha1 `bucket_key`), `config.visitor_rollup_max_id` / `_cursor` / `_done` |
| `2026.3.5` | Ziyaretçi özetleri backfill'i (şema değişikliği yok, `visitors` salt-okunur) |
| `2026.3.6` | `visitors` MyISAM → InnoDB (motor kontrollü, yeniden çalıştırılabilir) |
| `2026.3.7` | Veri onarımı (şema değişikliği yok): backfill'in ana sayfayı iki satıra bölmesi düzeltildi |
| `2026.4` | `form_fields.product_group_id` / `template_field_id` + iki index, `product_groups.form` / `form_name` / `form_label_column_width` / `form_quantity_type` (varyant seti ürün formu şablonu) |
| `2026.4.1` | `submitted_form_view_stats` (InnoDB, günlük kova), `config.sfv_rollup_cutover` / `_cursor` / `_done` + parçalı backfill |
| `2026.4.2` | Birleştirme: 4.2–4.17 arası on altı çalışma numarası. Adımlar için `install/index.php` içindeki `upgrade_2026_4_2_*` fonksiyonlarına bakın |
| `2026.4.3` | `page.noindex` / `page.nofollow` (sayfa bazında arama motoru dizini) |
| `2026.4.4` (4.40) | `_security_headers`: `config.security_headers` / `security_frame_protection` / `security_hsts` / `security_csp_mode` / `security_csp_policy` / `waf_text_log` / `waf_inflight_limit` / `waf_auto_ban_max_minutes` / `login_throttle_captcha_after` (güvenlik başlıkları + CSP raporlama, fail2ban günlüğü, iki güvenlik duvarı tavanı, giriş sorusu) |
| `2026.4.4` (4.33) | `_push_signout`: `push_subscriptions.auth_selector` + index (çıkışta o tarayıcının aboneliği düşer; silme `pg_auth_token_revoke()` içinde, oturumun bittiği tek nokta) |
| `2026.4.4` (4.30) | `_app_icon`: `config.app_icon` (kurulu uygulamanın simgesi; Ayarlar'da dosya adı seçilir, `manifest_icon.php` istenen boyutu çizip `data/temp/app_icon` altında saklar) |
| `2026.4.4` (4.29) | `_push_queue`: `push_queue` tablosu (kişi, kaynak, referans üçlüsünde tekil; `send_after` gecikmeyi taşır) |
| `2026.4.4` (4.28) | `_web_push`: `push_subscriptions` tablosu (endpoint SHA-256'sı üzerinden tekil), `config.push_vapid_public` / `push_vapid_private` (panelin uygulama olarak kurulup cihaza bildirim düşürmesi) |
| `2026.4.4` (4.27) | `_notification_reads`: `notification_reads` tablosu (bildirim, kişi) + `readed = 1` satırları için backfill; `readed` sütunu köprü için duruyor |
| `2026.4.4` (4.23) | `designer_presence` / `designer_page_lock` tabloları, `page.page_notes_at` (eşzamanlı düzenleme + not bildirimi) |
| `2026.4.4` (4.22) | `_multi_page_design`: `page.page_tree_json` / `page_tree_code`, `style.style_custom_css` / `_js` / `_fonts` + backfill (görsel tasarımcı: ağaç sayfaya, assets stile) |
| `2026.4.4` | Birleştirme: 4.4–4.11 arası sekiz çalışma numarası (2026-08-28). Giriş `upgrade_to_2026_4_4()`, alt adımlar: `_image_limits` (`config.image_*` 6 sütun + dosya yeniden adlandırma düzeltmesi), `_structured_data` (`config` og_default_image / organization_logo / merchant_* / custom_jsonld, `page.custom_jsonld`), `_ai_bot_ranges` (`waf_bot_ranges` tablosu, `config.waf_allow_ai_fetchers` / `waf_allow_ai_search` / `waf_ai_ranges_checked`), `_recycle_bin` (`recycle_bin` tablosu, `config.recycle_*`), `_upload_folders` (`config.chat_upload_folder_id` / `product_upload_folder_id`), `_catalog_bin_and_sign_in` (`products` / `product_groups` `recycled` + `recycled_enabled`, `recycle_bin.item_type` genişletme, `config.login_throttle*`, `user.tours_seen`, `user` InnoDB, `dashboard` satırı + `order_widgets` VARCHAR(1024)), `_dashboard_appearance` (`dashboard.widget_themes` → `widget_theme`, `bg_image` → `panel_backdrop`), `_catalog_address_index` (`products` / `product_groups` `address_name(191)`), `_external_api` (dış API: `api_apps`, `api_request_log`, `api_rate_bucket`, `api_idempotency`, `api_webhooks`, `api_webhook_queue` tabloları; `products.idx_timestamp` + `orders.idx_order_date`; `config.api_*` 5 kolon; `custom_apps` → `api_apps` pasif göçü). Ayrıntı: `docs/degisiklikler.md` "2026.4.4 — birleştirme" |

---

## Yapılandırılmış Veri ve Open Graph (2026.4.4)

- **Uydurma veri asla.** `review` / `aggregateRating` gerçek yorum verisi
  olmadan yazılmaz. Google `offers` tek başına yeter; Rich Results Test'in
  uyarısı hata değildir. Legacy'deki "google require it so we set random"
  bloğu bu yüzden kaldırıldı — geri getirme.
- **JSON-LD daima dizi + `json_encode()`.** Dizge birleştirme yasak: tırnaklı
  tek bir ürün adı bütün bloğu geçersiz yapar ve okuyucular sessizce atar.
- **Özel JSON-LD** (`page.custom_jsonld` + `config.custom_jsonld`): kayıtta
  `json_decode` ile doğrula (`output_error`), çıktıda decode → encode
  (`pg_custom_jsonld_block()`) — `/` kaçışı `</script` çıkışını imkânsız kılar.
  Metni asla olduğu gibi echo etme.
- **`media` alanında değer karar verir** (`pg_resolve_submitted_form_og_image`):
  alan zengin metne/videoya da bağlanabilir; video adresi og:image'a **girmez**.
- **Merchant parçaları** (`pg_product_merchant_jsonld_parts`) yalnız
  `shippable = 1` + `MERCHANT_COUNTRY` doluyken; iki üretim yolu da (legacy
  blok, `_pg_build_product_jsonld`) aynı yardımcıdan geçer.
- **OG görsel çözücüleri** `SELECT *` kullanır: `files.image_width/height`
  4.4'te geldi, kolon adı yazmak eski veritabanında sorguyu kırar.
- **`strutured_data` anahtarı SEO kartında** (E-Ticaret'te değil — orada
  e-ticaret kapalı sitede görünmüyordu). Yalnız yapılandırılmış veri üreten
  ayarlar (site JSON-LD, merchant) anahtara `collapse-switcher` ile bağlı;
  OG'yi besleyen paylaşım görseli/logo bağlı DEĞİL.
- **Çözülen her görsel adresi `pg_normalize_url_slashes()`'ten geçer.**
  Saklanan içerik çift çizgili adres taşıyabiliyor (`http://host//x.jpg`);
  IIS isteği affeder ama yayılan işaretleme tek yazım taşımalı. Yeni bir OG /
  JSON-LD adres kaynağı eklersen onu da geçir.
- Sayfayı tanıtan JSON-LD splice'ı (`BlogPosting`/`Organization`/breadcrumb/
  özel) e-postada ve edit modunda **üretilmez**; `STRUTURED_DATA` kapalıysa
  susar. (Sabitin yazımı bozuk ama tutarlı — düzeltme.)

---

## Yönlendirme Adresi — `HOSTNAME`'in Arkasına Ham Girdi Konmaz

Yazılımın her yerindeki kalıp:

```php
header('Location: ' . URL_SCHEME . HOSTNAME . $send_to);
```

Burada saldırgan **eğik çizgiden sonrasını değil, alan adından sonrasını**
yazar. `@evil.com/x` → `https://site.com@evil.com/x` (`@` öncesi userinfo,
tarayıcı evil.com'a gider). `.evil.com/x` → `https://site.com.evil.com/x`.

**Kural: `HOSTNAME`'den sonra gelen her kullanıcı kaynaklı değer
`pg_safe_redirect_path()`'ten geçer.** Bu yalnız `header('Location:')` için
değil — `window.parent.location = …`, `<a href>`, `go()` argümanı, hepsi.
`escape_javascript()` ve `h()` **URL doğrulayıcı değildir**; tırnak ve
etiket kaçırırlar, adresin nereye gittiğine bakmazlar.

### Fonksiyon yalnız ilk iki karaktere bakar — bu bir tasarım kararı

`/` ile başlayıp ikinci karakteri `/` veya `\` olmayan bir dize,
`https://host` sonrasına konduğunda yetkili bölümü (authority)
değiştiremez. Sonrası yoldur.

Bu yüzden `pg_safe_redirect_path()` içinde `str_replace`, `preg_replace`,
`urlencode` ya da `parse_url` + yeniden birleştirme **yoktur ve olmayacak**.
Adresin gövdesine dokunan her "düzeltme" şunları bozar:

| Ne | Nerede | Örnek |
|---|---|---|
| `[` `]` | Dosya sürüm eki, 54 yerde `name="x[]"` dizi parametresi | `/dosya [1].pdf`, `?filter[]=jpg` |
| Türkçe / UTF-8 | Ürün ve sayfa adresleri | `/urun/çay_bardağı_seti` |
| Yüzde kodlaması | Aramalar, `send_to` | `?q=%C3%A7ay` |
| `? # & =` | Her sorgu dizesi | — |

Normalleştirmeye ihtiyaç yok, çünkü tehlike gövdede değil önekte.

### `escape_url()`e dokunma

`//`, `http://`, `https://` adreslerini bilerek geçerli sayar: o fonksiyon
**gösterim** için doğrular. `http_referer` basan ekranlar
(`print_order.php:166`, `edit_submitted_form.php:749`) ve takvim
bağlantıları harici adres taşır — orada dış adres hata değil, veri.
Sıkılaştırmak yönlendirmeyi düzeltmez, raporları bozar.

### Doğrulayıcı bağlamından ayrılamaz

`pg_safe_back_url()` göreli `.php` yollarına izin verir ve **`href=`
içinde doğrudur** (tarayıcı mevcut dizine göre çözer, aynı köken).
Aynı çıktı `go()`'ya verildiğinde `HOSTNAME` başa yapıştığı için
`x.evil.com/a.php` → `https://site.comx.evil.com/a.php` oluyordu —
saldırganın kaydedebileceği bir alt alan adı.

**Bir değeri yeni bir yere koyarken doğrulamasının orada da geçerli
olduğunu ayrıca kanıtla.** İki farklı yer, iki farklı doğrulama ister.

### `go()` merkezî olarak sertleştirildi (130+ çağrı)

- Şema taşıyan adres yalnız kendi öneğimizle başlıyorsa kabul edilir ve
  önek soyulur. Önek eşleşmesi tek başına yetmez —
  `https://site.com.evil.com` de öneği paylaşır; öneğin **hemen ardından**
  `/`, `?` veya `#` gelmelidir.
- Eğik çizgisiz göreli yol, çalışan betiğin dizinine göre çözülür
  (tarayıcının `href` için yaptığı). Ham hâlde `HOSTNAME`'e yapıştırmak
  yukarıdaki alt alan adı saldırısıdır.
- Karşılaştırma **düz dize**; `parse_url` + yeniden kurma yapılırsa sorgu
  dizesindeki köşeli parantezler yeniden yazılma riskine girer.

Değiştirilmeyen tek biçim `$_SERVER['PHP_SELF']` — sunucu üretir, daima
`/` ile başlar.

### Elle Doğrulama Yazma

Kod tabanında üç ayrı elle yazılmış kontrol vardı, **üçü de aynı şeyi
kaçırıyordu**: `\` karakteri. `preg_match('#^/[^/]#', $back)` `/\evil.com`
dizesini geçirir. Yeni bir yönlendirme yazarken kendi regex'ini kurma,
merkezî fonksiyonu çağır.

`parse_url()` çıktısının biçimine de güvenme: `@evil.com/x` gibi bir girdide
`path`e ne koyacağı okunması gereken bir davranıştır. **Doğrulamayı
ayrıştırmadan önce yap**, soru ortadan kalksın.

---

## Router Yolunda `functions.php` YOK

`router.php` üç şeyi `init.php`'den geçmeden gönderiyor: `get_file.php`,
`robots.txt`, `sitemap.xml`. Bilinçli — `functions.php` megabaytlarca ve her
görsel isteğinin onu yüklemesi anlamsız.

**Sonuç:** bu üç yolda `lang()` ve `functions.php`'deki her şey **yok**. Oradan
`lang()` çağırmak, kullanıcıya gösterilecek hata mesajını boş bir HTTP 500'e
çevirir — ve gizlenen şey tam olarak teşhis için gereken cümledir.

Çözüm kalıbı: yerel bir metin sarmalayıcı. `waf.php` → `waf_text()`,
`get_file.php` → `get_file_text()`. İkisi de `function_exists('lang')` varsa
devrediyor, yoksa İngilizce metni (dizi biçiminde `{var:n}` doldurarak)
döndürüyor. Bu dosyalara yeni bir kullanıcı mesajı eklerken `lang()` değil,
o sarmalayıcı kullanılır.

`get_robots.txt.php` ve `get_sitemap.xml.php` kendi `init.php`'lerini
çağırdıkları için bu kısıtın dışında.

---

## Pano Satış Haritası — widget 1 (2026.4.4)

Veri `includes/sales_map.php`, işaretleme `api.php` `case '1'`, iskelet ve
kayıt girişi `welcome.php`.

**Yazılım tek ülkeye satılmıyor; kartın tabanı dünya haritasıdır.** Bir ülkenin
kendi haritası üstüne gelen ikinci katmandır ve yalnız o ülke için sınır dosyası
paketlediysek vardır (bugün TR). Sağdaki liste **her iki görünümde de** bölge
(il / eyalet) düzeyindedir: harita ölçek verir, liste kırılım.

- **Yeni ülke eklemek üç şeydir:** SVG'yi üret (`docs/_tools/build_map_svg.js`,
  `world` ve `regions <ADM0_A3>` modları), `pg_sales_map_region_maps()`'e satır
  ekle, bölge adı tablosunu yaz. `api.php` ülkeye özel hiçbir şey bilmez; ülkeye
  özel her şey o kayıttan geçer.
- **Haritalar `assets/maps/`:** `world-countries.svg` (path id = ISO 3166-1
  alpha-2), `tr-regions.svg` (ISO 3166-2). Kaynak Natural Earth (public domain).
  Natural Earth'ün **kendi bölge adları kullanılmaz** — verisinde "Zinguldak",
  "Kinkkale", "K. Maras" gibi yazımlar var; oradan yalnız geometri ve ISO kodu
  alınır.
- **Bölge adı üç kaynaktan, bu sırayla:** ülkenin harita tablosu → mağazanın
  `states` tablosu ("TX" → "Texas") → siparişteki ham değer. Ülke adı ekrana
  `lang()`'dan geçer; **filtreye giden değer ham saklanan addır**.
- **Eşleştirme `pg_sales_map_normalize()`** ile: aksan, harf durumu, boşluk ve
  noktalama atılır. Çevrim **önce**, `mb_strtolower` **sonra**
  (`pg_transliterate_to_ascii` kuralı). Sahadaki yazımlar
  `pg_sales_map_aliases()`'ta (Afyon, İçel, Urfa, Maraş…).
- **Açılış görünümü en çok sipariş alan ülkedir**, haritası varsa. Payı koşul
  değildir: ikiye bölünmüş bir mağaza da en çok sattığı ülkeyle açılır, dünya
  menüde bir tık uzakta. **Dünya varsayılan değil, yedektir** — baskın ülkenin
  sınır dosyası yoksa o gelir. Baskın ülke **sipariş adedine** (ciroya değil) ve
  **tüm zamanlara** göre seçilir; dönem değişince harita değişmez.
- **Rakamlar Satış Raporu'nun "Billing State" özetiyle aynı tanımdan gelir**
  (`orders.billing_country` / `billing_state`, `status IN
  ('complete','exported')`, tip süzgeci yok). Bağlantılar bu yüzden
  `view_orders.php`'ye `type=any` ile gider. **Ülke filtresine kod değil ad
  gider:** o süzgeç adı da LIKE ile arıyor ve `US` filtresi Belarus siparişlerini
  de getirir.
- **Renk basamakları quantile** (`pg_sales_map_steps()`, ülke ve bölge için aynı
  fonksiyon). Satış çarpık dağılır; aralığı eşit bölmek baskın olan dışındaki her
  yeri en soluk basamağa düşürür.
- **Altı kombinasyon (2 görünüm × 3 dönem) tek sorgudan üretilip gömülür;**
  anahtarlar yeni istek atmaz. Sağ listenin satırları da PHP'de üretilir, çünkü
  bir pano satırının tek tanımı `pg_widget_row()`'dur. Harita dosyaları talep
  üzerine inip katman olarak saklanır.
- **Anahtarlar haritanın köşesindeki menüde** (`.pg-smap-menu`), altında şerit
  yok: şerit panelin yüksekliğinden çalıyordu ve `.btn-group > .btn`
  `flex: 1 1 auto` yüzünden her düğme panelin üçte birini alıp etiketini iki
  satıra sarıyordu. Menü Popper'a **`strategy: "fixed"`** ile kurulur — kutu bir
  kaydırma kutusunun içinde ve mutlak konumlanan menü orada kesilir. Seçili satır
  **tik** ile işaretlenir, renkle değil.
- **Kart rengi `.widget[widget-id="1"]`**: mağaza kartlarının yeşilinden bir adım
  ötede teal (`#0d9488`). Harita rampası `--pg-accent`'e bağlıdır
  (`color-mix`), yani kartın rengini değiştirmek haritayı da değiştirir.
- **Kapı `USER_MANAGE_ECOMMERCE_REPORTS`'tır, `USER_MANAGE_ECOMMERCE` değil.**
  Kart bir satış raporudur ve bu iki izin kullanıcıda ayrı ayrı durur
  (`validate_ecommerce_report_access()` ↔ `validate_ecommerce_access()`).
  Rol 0-2 ikisini de koşulsuz taşır; rol 3 için operatörün verdiği bayrak
  belirler.
- **Bağlantılar ayrı kapıdan geçer.** Kartın her bağlantısı
  `view_orders.php`'ye gider ve o ekran `manage_ecommerce` ister; raporu
  okuyabilen ama siparişleri yönetemeyen kullanıcıya satırlar linksiz çizilir
  (`pg_widget_row` href'siz div üretir) ve tuval `is-static` alır — imleç de
  gitmeyeceği bir yeri işaret etmez.
- **Karşılama satırı (`pg-brief-line`) da bu veriden konuşur.** `welcome.php`
  sinyal bloğunda iki alan: `map_lead` (haftanın en yoğun yeri) ve `map_new`
  (bu hafta sipariş veren, önceki üç haftada vermeyen yer) — tek sorgu, iki
  pencere. `map_new` ayrıca **önceki üç haftada başka sipariş olmasını** şart
  koşar; yoksa mağazanın ilk haftasında her yer "yeni" olur. Ad çözümü kartla
  ortak (`pg_sales_map_label()`).
- **Satırın iki yarısı iki kapıdan geçer.** Sinyal `USER_MANAGE_ECOMMERCE_REPORTS`
  ile toplanır; öneri yarısı `manage_ecommerce` yoksa **hiç üretilmez** (değeri
  sipariş ekranı bağlantısında). Okuma yarısı `h()` ile bütün kaçırıldığı için
  oraya bağlantı konmaz — yer adı orada düz metindir.
- Widget `welcome.php`'deki dakikalık yenileme listesinde **değildir** (tam
  tablo taraması, ilk yüklemede bir kez).
- **id 1 emekli yuvaydı ve bilerek yeniden kullanıldı**; 22 ve 24 emekli kalır.
  Gerekçe ve ödün `welcome.php` kayıt yorumunda.

### `overflow: hidden` Flex Kutusunun Taban Boyunu İptal Eder

`visible` dışında her `overflow` değeri, flex öğesinin otomatik en küçük
boyutunu (`min-height: auto`) sıfıra çevirir. Yani kutu içeriğinin altına
sıkışabilir hale gelir.

Satış haritası panelinde bu, dar ekranda **paneli 0 piksele indirip il listesini
haritanın üstüne çizdi**; geniş ekranda hiçbir belirti yoktu, çünkü orada
`.pg-split` zaten kendi `overflow-y: auto`'sunu veriyor. Belirti yalnız kart tek
sütuna düştüğünde görünür.

Kural: bir flex kutusuna `overflow` yazmadan önce o eksende bir taban
(`min-height`/`min-width`) olduğundan emin ol — ya da kutuyu boyutlandıran kural
zaten varsa `overflow` yazma.

## Düzenleyici Taşıyan İşaretlemeyi Atmadan Önce `autoRefresh: false`

**CodeMirror'ın `autoRefresh` eklentisi, gizli bir düzenleyiciyi 250 ms'de bir
yoklar ve cevap "hâlâ gizli" olduğu sürece kendini yeniden kurar.** Bir
düzenleyici işaretlemesi değiştirildiği ya da penceresi kapandığı için bir daha
görünür olmuyorsa, o yoklama **sekme kapanana kadar sürer**. Eklenti ayrıca
`window`'a `mouseup` ve `keyup` bağlar ve onları da kaldırmaz: terk edilmiş her
düzenleyici için her tıklamada bir işleyici daha koşar.

**`toTextArea()` bunu durdurmaz.** Eklenti yıkımı değil **seçeneği** dinler;
düzenleyici yok edildikten sonra artık belgede olmayan bir sarmalayıcının
`offsetHeight`'ını okumaya devam eder.

**Kural: düzenleyici taşıyabilecek bir bölgeyi değiştiren ya da kapatan her kod
önce `window.pgReleaseEditors(scope)` çağırır** (`backend.src.js`). Fonksiyon
kapsamdaki her `.CodeMirror` örneğinde `setOption('autoRefresh', false)` yapar —
eklentinin dinlediği şey budur.

**Belirtisi hata değildir ve aramakla bulunmaz:** sekme kaymaya devam eder
(compositor boştadır), tıklama ve sağ tık menüsü durur (onlar ana iş
parçacığını ister), Chrome "sayfa yanıt vermiyor" demez (tek bir uzun iş yoktur,
sürekli yenileri vardır). Ölçmenin yolu `setTimeout`'u kancalayıp bir şey
açılıp kapandıktan **sonra** saniyede kaç zamanlayıcı çalıştığını saymaktır;
sayı turla birlikte doğrusal büyüyorsa sızıntı vardır.

Bu, aşağıdaki Chart.js kuralının aynı sınıfıdır: canlı bir nesne tutan
işaretleme öylece atılamaz.

---

## Grafik Taşıyan İşaretlemeyi Değiştirmeden Önce `destroy()` (2026.4.4)

**Chart.js ürettiği her grafiği kendi kayıt defterinde tutar** (v4:
`Chart.instances`, anahtar canvas). Canvas'ı DOM'dan çıkarmak grafiği o
defterden **çıkarmaz**: örnek `ResizeObserver`'ını, verisini ve onunla gelen
tüm yanıtı canlı tutar, pencere her yeniden boyutlandığında liste baştan sona
yürünür.

Panel (`welcome.php`) widget gövdelerini dakikada bir atıp yeniden yazıyor.
Elli dakika açık kalan bir sekmede **7 canvas'a karşılık 58 grafik** vardı,
51'i kopmuş bir canvas'a çiziyordu; tur başına 6 birikiyor. Belirti "sekme
donuyor" gibi okunur ama ana iş parçacığı kilitli değildir — sürekli meşguldür.

**Kural: grafik taşıyabilecek bir bölgeyi `.html()` / `.remove()` /
`.append()` ile değiştiren her kod, önce o bölgedeki grafikleri kapatır.**
`welcome.php` içindeki `destroy_widget_charts($scope)` bunu yapar
(`Chart.getChart(canvas).destroy()`); yeni bir tazeleme yolu eklenirken o da
çağrılır.

Kanca jQuery'nin `remove()`'una **takılmadı** (`$.cleanData` üzerine yazmak):
her ekranı etkilerdi ve grafiği kimin sahiplendiğini gizlerdi. Widget
tazelemesi, grafikli işaretlemeyi rutin olarak değiştiren tek yerdir.

---

## Web Kökündeki Sunucu Kuralları — `includes/server_config.php` (2026.4.4)

Ana dizindeki `web.config` / `.htaccess` **yazılım klasörünün dışındadır**;
hiçbir güncelleme paketi onu taşımaz. Yani `install/index.php` içine yazılan
bir kural yalnız yeni kurulumlara ulaşır. Kural listesi bu yüzden tek bir
yerdedir ve iki uç onu okur:

| Uç | Ne yapar |
|---|---|
| `install/index.php` | Dosya yoksa `pg_server_config_default()` ile hepsini yazar; varsa `pg_server_config_repair(false)` ile yalnız eksikleri ekler |
| Sistem Durumu kartı | `pg_server_config_scan()` ile blok blok tarar; yönetici karosu `pg_server_config_repair(true)` çağırır (`api.php` → `server_config_repair`) |

Dosya `includes/` altındadır, `install/` altında değil: kurulum klasörü canlı
sunucudan silinebiliyor (`includes/migrations/runner.php` ile aynı gerekçe).

### Yeni kural nasıl eklenir

`pg_server_config_blocks($server)` listesine bir blok eklenir — `key`
(kararlı kimlik), `label`, `why` (tek cümle: onsuz ne açıkta), `level`
(`core` / `required` / `recommended`), `detect` (dosyada arayan regex),
`snippet`, `anchor`. Sunucuya özel dala eklenir; IIS ve Apache karşılıkları
ayrı yazılır. Sonra `pg_server_config_default()` içindeki dosya iskeletine
de konur.

**Değişmez: varsayılan, kendi `detect`'inden geçmek zorundadır.** Geçmezse
taze kurulum kendini ilk gün "eksik" ilan eder. Blok eklerken üç sunucunun
varsayılanını üretip her `detect`'i üstünde koştur.

### Onarım kuralları

- **Yalnız ekler.** Dosyada olan hiçbir şey yeniden yazılmaz. Tek istisna
  `$fix_path` ile yönlendirme yolu.
- **DOM değil dizge cerrahisi.** `DOMDocument::saveXML()` dosyayı baştan sona
  yeniden girintiler ve yorumları oynatır; operatörün dosyası incelenebilir
  kalmalı.
- **Çıpası olmayan blok atlanır**, uydurma bir yere yazılmaz —
  `<requestFiltering>` dışındaki bir `<hiddenSegments>` her isteği 500'ler.
  Atlananlar **sayılır ve mesajda söylenir**: hepsi atlandığında "eksik
  yoktu" demek, Sistem Durumu aynı anda "3 eksik" derken yalandır.
- **IIS sonucu `simplexml_load_string` ile ayrıştırılmadan yazılmaz.**
  Okunamayan bir web.config tüm siteyi 500.19 yapar ve operatör "daha
  güvenli yapar" yazan bir düğmeye basmıştır.
- **Yedek `data/temp/server_config/` içine.** `.htaccess`in yanına konan bir
  `.bak` düz metin olarak servis edilir; `data/` ise bu dosyanın yazdığı
  kuralın koruduğu yerdir.
- Kapanış etiketinin ofsetine yazarken `pg_server_config_split_indent()`
  kullan: o noktaya kadarki metin etiketin girintisiyle biter, snippet'i
  doğrudan eklemek ilk satırı iki kez girintiler.

### Desen `PATH` taşımaz, eylem taşır

Dosya yazılım klasörünün **yanındadır**, yani `PATH`in gösterdiği dizindedir.
Oradaki bir IIS `match` ya da Apache `RewriteRule` deseni zaten o dizine
görelidir; `PATH` eklemek deseni `magaza/magaza/data/` yapar ve kural
**hiçbir isteğe uymaz**. Eylem tersidir: yeniden yazma hedefi site köküne
göre çözülür, `PATH` oraya aittir. nginx `location` da mutlak URI'dir.

`pg_server_config_path()` ve `pg_server_config_software_dir()` bu ayrım için
ayrıdır. Klasör adını asla sabit yazma — 2026.4.4 öncesi alt dizin dalı
`'/pinegrap/router.php'` arıyordu ve yeniden adlandırılmış her kurulumda
sessizce hiçbir şey yapmıyordu.

### Klasör adı ölçülür, varsayılmaz — ve dosyada eskiyebilir

Konum soran üç yardımcı da **hesaplar**: bu dosya her zaman
`<kök>/<yazılım>/includes/server_config.php` konumundadır, dolayısıyla kendi
yolu cevabı verir (`pg_server_config_web_root()`,
`pg_server_config_software_dir()`). `SOFTWARE_DIRECTORY` tanımlıysa yetkili
odur — operatör `config.php`'de sabitleyebiliyor — ama tanımsızken literal
yazılmaz: **uydurulmuş bir klasör adı, doğru okunan ve hiçbir şeyi korumayan
kural üretir.**

Klasör adı kuralların **içinde üç yerde** geçer (router hedefi + `data` ve
`includes` desenleri). Kurulum yeniden adlandırılır ya da dosya başka bir
kurulumdan kopyalanırsa router kuralı **gürültülü** bozulur (site 404 verir),
iki engelleme kuralı ise **sessiz** bozulur: var olmayan bir klasörü
eşleştirmeye devam ederler, gerçek `<yeni>/data/` servis edilir, dosyada
yanlış görünen hiçbir şey yoktur. Blok tanıma kural adına baktığı için "var"
der.

`pg_server_config_retarget($contents, $server)` üçünü birden çevirir ve
`stale_path` ayrı bir karşılaştırma **değildir** — "retarget tek bayt
değiştiriyor mu?" sorusudur. Raporlanan ile düzeltilebilen böylece aynı şey
olmak zorunda kalır. Yeni bir kural klasör adı taşıyorsa retarget'a da girer.

### Sunucu türü tek kaynaktan sorulmaz

`SERVER_SOFTWARE` bazı CGI kurulumlarında ve komut satırında hiç yoktur.
`pg_server_config_detect_server()` önce ona bakar, cevap yoksa ana dizinde
**duran dosyaya**: `web.config` IIS dünyasının, `.htaccess` Apache dünyasının
bıraktığı dosyadır. İkisi de yoksa cevap `unknown` kalır. Koşulsuz Apache
varsaymak, IIS sitesine hiçbir işe yaramayan bir dosya yazıp "korunuyor"
demekti.

### Bir satırı önbellekten, yanındaki düğmeyi dosyadan okuma

`get_system_status_checks()` on dakika önbelleklidir; sunucu kuralları karosu
dosyayı canlı okur. İkisi ayrıştığında ekranda "2 eksik" diyen bir satırın
yanında yapacak işi olmadığı için **hiç çizilmeyen** bir düğme kalır ve bu
"eksik diyor, düzeltmiyor" diye okunur.

Kural: **bir kontrol satırı ile onun eylem düğmesi aynı okumadan beslenir.**
`pg_server_config_scan()` istek başına memoize; `pg_server_rules_signature()`
taramanın karar verdiği her şeyi tek dizgede toplar, önbellekle birlikte
saklanır ve okunurken karşılaştırılır — fark varsa önbellek atılır. Yeni bir
"kontrol + düğme" çifti eklerken aynı deseni kur.

### `X-Powered-By` PHP'nin başlığıdır

IIS'te `<customHeaders><remove>` yalnız **IIS'in eklediği** başlıklara
ulaşır; `X-Powered-By: PHP/8.x` başlığını PHP kendisi ekler. Apache'de
`Header always unset` ona da ulaşır. Bu yüzden başlık `header_remove()` ile
`router.php` ve `init.php`'de düşürülür — `expose_php` php.ini'dedir ve
paylaşımlı hostingde bizim değildir.

### Sistem Durumu ayrıntı paneli kontrol başınadır

`$makeIcon(..., $detail)` ile gelen satırlar `system_status_detail_<n>`
kutusuna yazılır ve kutunun başında hangi kontrole ait olduğu durur. Tek bir
`#system_status_detail` vardı; ikinci ayrıntılı kontrol eklenince iki chevron
aynı kutuyu açıyordu.

**Onay soran karo `<button>` ise `event.isDefaultPrevented()` oku.**
`.pg-health-tile[data-confirm-content]` işleyicisi yalnız `preventDefault()`
çağırır; bir bağlantıda bu gezinmeyi durdurur, bir düğmede durduracak bir şey
yoktur.

---

## Sayfa Bazında Arama Motoru Dizini (2026.4.3)

`noindex` anahtarı **üç şeyi birden** yapar. Biri eksikse hata vardır:

| Sonuç | Nerede |
|---|---|
| `<meta name="robots" content="noindex, follow">` | `get_page_content.php` — `$meta_tags` bloğunun başı |
| `robots.txt`'te `Disallow` satırları | `pg_build_robots_disallow_rules()` (functions.php) → `get_robots.txt.php` |
| Site haritasından düşme | `get_sitemap_info.php` + kayıt tarafında `sitemap = 0` zorlaması |

`nofollow` **bağlı** anahtardır: yönergeyi nitelemekten başka işi yok ve
`noindex` kapalıyken **0 olarak saklanır**. Üç anlamlı durum vardır, dördüncüsü
saklanamaz.

### Kurallar

- **`sitemap` bayrağını okuyan her yeni sorgu `noindex`i de sormalı.** Ana sayfa
  döngüsünün yanı sıra katalog detay / form öğe görünümü / takvim etkinliği alt
  sorguları kendi `LEFT JOIN page` satırlarından okuyor; biri unutulursa o
  sayfanın öğe adresleri haritada kalır.
- **`robots.txt` kuralı asla düz önek olmaz.** `Disallow: /ara` `/arac-kiralama`
  sayfasını da engeller. Sayfa başına üç çıpalı satır yazılır: `…$`, `…?`, `…/`.
  (`*` ve `$` RFC 9309 dilbilgisinde tanımlı.)
- **Kuralın hangi gruba yazıldığı önemli:** kural üstündeki `User-agent`
  satırına aittir. Ek içerikte `User-agent: *` grubu **varsa** kurallar onun
  içine yazılır (`pg_merge_robots_rules_into_catch_all()`), grubu açan ardışık
  `User-agent` satırlarının sonuncusundan sonra — aralarına girmek tanımı böler,
  araya giren boş satır/yorum grubu bitirmez. **Yoksa** kendi grubumuz açılır ve
  ek içerikten **ÖNCE** yazılır; sonrasına eklenirse o metin bir
  `User-agent: Googlebot` bloğuyla bitiyorsa kurallar sessizce ona daralır.
- **İkinci bir `User-agent: *` açmaktan kaçınılır.** Standarda göre sorun değil
  (RFC 9309 §2.2.1 aynı jetonlu grupların birleştirilmesini zorunlu kılar), ama
  ilk eşleşen grupta duran ilkel ayrıştırıcı operatörün `Disallow: /` satırını
  kaçırır.
- **Özel klasördeki sayfa `robots.txt`'e yazılmaz.** Dosya herkese açık; giriş
  arkasındaki adresi oraya yazmak onu yayınlamaktır.
- **Hiç noindex sayfa yoksa `robots.txt` bayt bayt eskisiyle aynı çıkar.**
- `pg_page_noindex_ready()` — kodu alıp DB'yi yükseltmeyen kurulumda özellik
  sessizce yok olur. **İki kolonu birden yoklar** (yükseltme iki ayrı `ALTER`
  çalıştırıyor). `pg_sfv_stats_ready()` ile aynı desen.
- **Anahtar DÖRT ekranda:** `add_page.php`, `edit_page.php` (legacy sayfa
  ekranı) ve `add_system_style.php`, `edit_system_style.php` (Görsel Editör
  sayfa ayarları paneli). Dördü de aynı `page` satırını yazıyor; birine eklenen
  alan diğerinden kaydedilince kaybolur. Panelde blok koşulsuz — Görsel Editör
  sayfaya tür vermiyor, hepsi `standard` / `system`.
- **PHP ↔ JS ikizi:** kayıt tarafındaki `sitemap = 0` zorlaması ile
  `bindPageIndexingSwitches(ids)` (backend.src.js) aynı kuralı söyler; lockstep
  güncelle. Fonksiyon eleman kimliklerini parametre alır: sayfa ekranı
  varsayılanları (`noindex` / `nofollow` / `sitemap`), panel `pg_page_*` ve bir
  de `hint` kullanır. Ekranda `disabled` bir kutunun gönderilmemesine güvenmek
  yetmez — POST'un o ekrandan gelme zorunluluğu yok. (Sayfa ekranı çıplak
  checkbox gönderir, devre dışı kutu hiç gitmez; panel gizli `value="0"` ile
  eşler, sıfır gider. İkisi de aynı sonuca varır.)
- **Meta etiket `<meta_tags></meta_tags>` yer tutucusu silinmişse `</head>`
  öncesine ayrıca yerleştirilir.** Bloktaki diğer her şey sessizce eksilebilir;
  operatörün açtığı anahtarın cevabı eksilemez. E-posta gövdesinde
  (`$email === true`) hiç basılmaz.

### Bilinen ödün

`robots.txt` ile engellenen sayfa taranmaz, dolayısıyla üzerindeki `noindex`
etiketi de okunmaz. Kombinasyon **sonuçlara henüz girmemiş** sayfada doğrudur
(tarama bütçesi hiç harcanmaz); zaten listelenen bir sayfayı düşürmek için
yavaş yoldur. Ekranda uyarı olarak duruyor.

---

## Makale Görüntülemesi — Günlük Kova (2026.4.1)

**Ölçüm:** günde 336.000 istek alan bir sitede istekler duvar saatinin **%84'ünü
bekleyerek** geçiriyordu (frontend 127 ms / 21 ms CPU, backend 99 / 19). Oran
saatten saate değişmiyordu ve düşük trafikte **daha kötüydü** — yani sebep kendi
yükü değildi.

Kaynak `submitted_form_views`: **8 milyon satır, 1,1 GB, veritabanının %73'ü**,
946 MB'ı indeks. **MyISAM** — her makale görüntülemesinde bir INSERT, ve MyISAM
INSERT'i **tabloyu tamamen kilitler**. Kilit tutulurken dört B-tree güncelleniyor,
hiçbiri `key_buffer_size`'a sığmıyor. InnoDB buffer pool isabet oranı %84,3
(sağlıklı: %99,9+).

**Dört indeksin ikisi hiç kullanılamıyordu:**

| İndeks | Kardinalite | Sorun |
|---|---|---|
| `submitted_form_id` | 28 | Bileşik indeksin **soldan ilk kolonu** — tamamen fazlalık |
| `page_id` | 6 | 8 milyon satırda 6 değer; MySQL böyle bir indeksi hiç seçmez |

**Ve bu 1,1 GB tek bir yönetici ekranı içindi.** Okuyan tek sorgu
`get_form_view_directory_screen_content.php` — "son N günde en çok okunanlar".
Kullanıcının gördüğü sayaç **başka tablodadır** (`submitted_form_info.number_of_views`,
çalışan toplam) ve dokunulmadı. İkisi aynı fonksiyonda 30 satır arayla yazıldığı
için karıştırılması kolay.

### Yeni Şema

```sql
PRIMARY KEY (submitted_form_id, page_id, view_date)   -- 4+4+3 = 11 bayt
```

**Neden sha1 `bucket_key` yok:** `visitor_content_hourly`'de hash gerekmişti,
çünkü anahtar kolonları utf8mb4 varchar'dı ve 767 baytı aşıyordu. Burada üçü de
sabit genişlikte; hash fazladan bir katman olurdu.

**Neden hiç ikincil indeks yok:** saklama sweep'i ve sayfa/makale silme yolları
tarama yapıyor — ama birkaç bin satır tarıyorlar. Her yazışta indeks bedeli
ödeyip bunu kurtarmak, tam olarak eski tabloyu o hâle getiren takas.

### Backfill — Eski Tabloda PRIMARY KEY Yok

`SHOW INDEX` çıktısındaki altı satırın hepsi `Non_unique = 1`. Yani
`pg_visitor_backfill_step()`'teki id imleci burada kullanılamaz.

**İmleç `timestamp` olur, parça birimi bir takvim günü.** Gün hem indeksli bir
sınır hem de yazılan kovanın kendisi, dolayısıyla bir parça `(makale, sayfa)`
başına tek satıra düşer.

`strtotime('+1 day', $ts)` kullanılır, `+86400` **değil**: gün her zaman 86.400
saniye değildir ve DST sınırı bir tarihi iki parçaya bölerdi.

### Cutover — Çift Sayma Önlemi

Yükseltme anında `sfv_rollup_cutover` sabitlenir. Canlı yazım o andan itibaren
kovaya gider; backfill yalnız `timestamp < cutover` satırlarını işler. Kesim
gününün ikiye bölünmesi `ON DUPLICATE KEY UPDATE views = views + VALUES(views)`
ile doğru toplanır — imleç bir parçayı iki kez işlemediği için ekleme güvenlidir.

### Saklama

Sweep **1/500** örneklenir (eski kod 1/100 idi). Kova tablosu milyonlar yerine
birkaç bin satır tuttuğu için çok daha seyrek geçiş yeterli.

**Rollup canlıyken eski tablo süpürülmez.** Backfill onu okuyor; imlecin henüz
ulaşmadığı satırları altından silmek o geçmişi sessizce kaybettirirdi.

### Eski Tablonun Tasfiyesi

Backfill bitince `DROP` değil **`RENAME TABLE ... TO submitted_form_views_old`**.
Hiçbir kod ona yazmadığı için kilit üretmez. Türetilmiş veriyi yeniden
üretebiliriz; kaynağı silersek geri dönüş kalmaz.

### Geriye Dönük Uyumluluk

`pg_sfv_stats_ready()` — kodu alıp DB'yi yükseltmeyen kurulumda eski per-view
INSERT yolu çalışmaya devam eder, okuma sorgusu da eski dala düşer.
`waf_schema_ready()` ve `pg_pb_form_template_ready()` ile aynı desen.

### Bilinen Ödün

`$summary_timestamp = time() - ($summary_days * 86400)` — "tam N×24 saat önce".
Günlük kovada sınır **takvim gününe** yuvarlanır, tek sınırda kısmi bir günlük
fark kalır. "En çok okunanlar" sıralaması için önemsiz; saatlik kova bunu
kaldırırdı ama tabloyu 24 katına çıkarırdı.

---

## Varyant Seti Ürün Formu — Şablon Grupta, Kopya Üründe (2026.4)

**Sorun:** ürün formu `form_fields.product_id` ile tek ürüne bağlı. Kod tabanında
99 `SELECT ... FROM form_fields` var, hepsi `page_id` veya `product_id` ile
filtreli. Dokuz renk/beden kombinasyonu olan bir üründe operatörün aynı formu
dokuz kez çizmesi gerekiyordu.

**Karar:** çalışma anı **hiç değişmez** — her ürün kendi satırlarına sahip
olmaya devam eder, katalog / sepet / `submit_order.php` dokunulmaz. Üstüne bir
şablon katmanı eklenir:

| Satır | `form_type` | `product_id` | `product_group_id` | `template_field_id` |
|---|---|---|---|---|
| Şablon alanı | `'product_group'` | 0 | grup | 0 |
| Şablondan üretilen kopya | `'product'` | ürün | grup | şablon satırı |
| Varyanta elle eklenmiş alan | `'product'` | ürün | 0 | 0 |
| Legacy tek ürün formu | `'product'` | ürün | 0 | 0 |

`form_field_options` **değişmedi** — `form_field_id`'ye bağlı olduğu için şablon
satırının seçeneklerini de taşır.

**Neden ayrı tablo değil:** `select_field_position()` (functions.php:3143) zaten
`product_id` / `page_id` diye dallanıyor; alan düzenleme ekranları bu ayrımı
taşıyacak biçimde yazılmış. Üçüncü bir dal eklemek, 24 kolonluk şemayı ve tüm
alan tipi mantığını ikinci kez yazmaktan ucuz.

### Sıfır Kimlikli Sorgu Tuzağı

Şablon satırlarının hem `page_id` hem `product_id` değeri **0**.

`WHERE page_id = '$x'` ve `WHERE product_id = '$x'` kalıpları kod tabanında var;
`$x` sıfır gelirse bu sorgular **2026.4'ten önce de** yanlış küme döndürüyordu
(ürün formu satırlarının `page_id`'si zaten 0, sayfa formu satırlarının
`product_id`'si zaten 0). Şablon satırları yeni bir hata sınıfı yaratmıyor,
mevcut olanı büyütüyor.

**Kural: ürün formu okuyan her sorgu `form_type = 'product'` pinler.** Legacy
davranış değişmez (o satırların `form_type`'ı zaten `'product'`), ama şablon
satırlarının hiçbir koşulda render'a sızmayacağı garanti altına alınır. Sepet,
ödeme, sipariş fişi ve paket listesi yollarındaki tüm okumalar pinlendi:

| Yer | Ne okur |
|---|---|
| `functions.php` `get_form()` ×2 | Alan sorgusu + office-use-only probe'u |
| `functions.php` `get_submitted_product_form_content_with_form_fields()` | Fiş / sipariş görünümü / paket listesi |
| `functions.php` `_pg_render_cart_item_form_data()` / `..._readonly()` | Sistem widget sepet ve sipariş görünümü |
| `functions.php` `duplicate_product()` | Ürün kopyalama |
| `functions.php` sipariş öncesi ön doldurma | `(product_id, name)` araması |
| `shopping_cart.php` / `get_shopping_cart.php` ×3 | Legacy sepet |
| `express_order.php` / `get_express_order.php` ×3 | Hızlı sipariş |

### Silinmiş Ürünün Fişi (pin'in ortaya çıkardığı eski hata)

`get_submitted_product_form_content_with_form_fields()` ürün id'sini
`order_items LEFT JOIN products` ile alıyor. Ürün silinmişse `products.id`
**NULL** gelir, sorgu `WHERE product_id = ''` olur, MySQL bunu 0'a çevirir ve
**hiçbir ürüne ait olmayan tüm satırlar** eşleşir.

Bu 2026.4'ten önce de böyleydi — o zaman sayfa formu satırları eşleşiyordu.
Şablon satırları eşleşen kümeyi büyütüyor, ve `pg_pb_delete_variant_set()` ürün
silmeyi rutin bir işlem hâline getirdiği için tetiklenme olasılığı artıyor.
Fonksiyon artık ürün yoksa boş döner.

**Genel kural:** `LEFT JOIN products` ile alınan bir id'yi sorguya koymadan önce
boş olup olmadığına bak. `INT` kolonu `''` ile karşılaştırmak hata vermez,
sessizce 0 olur.

### Uygulama Kuralı

`pg_pb_apply_form_template($group_id)` (product_builder.php):

> Şablondan gelen alanlar yeniden yazılır. Varyanta elle eklenmiş alanlar
> korunur.

İkinci yarıyı `template_field_id` sağlar: kopya, geldiği şablon satırının
id'sini taşır, yani "geçen sefer ürettiklerimi sil" bir `WHERE` koşuludur,
tahmin değil. Kopyanın **düzenlenmiş** olup olmadığını tespit etmek bilerek
yapılmadı — `edit_field.php`'ye kanca takmayı ve alan alan karşılaştırma
gerektirir, oysa yukarıdaki cümle soruyu zaten cevaplıyor.

**Her alan değişikliğinden sonra otomatik çalışır**, "uygula" butonuna
bırakılmaz: çizilip uygulanmamış bir şablon, ürünlerinde formu olmayan bir set
bırakır ve ekranda bunu söyleyen hiçbir şey olmaz.

`pg_pb_copy_row()` `SELECT *` + kimlik kolonlarını düşürme ile çalışır, yazılı
kolon listesiyle değil: `form_fields` yıllar içinde kolon topladı
(`rss_field`, `quiz_question`, `contact_field`, `upload_folder_id`…) ve elle
tutulan bir liste, eklenen bir sonraki kolonu sessizce kopyalamamaya başlardı.

`pg_pb_form_template_ready()` — kodu alıp DB'yi yükseltmeyen kurulumda özellik
sessizce yok olur, eksik kolonda hata vermez.

---

## Performans İzleyicisi — İstek Yolu Maliyeti (2026.3.1)

`perf_monitor_shutdown()` her istekte bir satır yazar. Ölçüm aracının kendisi
ölçtüğü şeyi bozmamalı:

**`fastcgi_finish_request()` PHP-FPM'e özeldir.** mod_php, CGI ve **IIS**'te
yoktur — orada tüm shutdown işi yanıt tamamlanmadan önce çalışır, yani gecikme
gerçekten ziyaretçiye yansır. Fonksiyon `$flushed` bayrağı tutar; **flush
edilemediyse en pahalı iş (sweep) hiç çalışmaz.**

**Satır tavanı `PERF_MONITOR_MAX_ROWS`** (varsayılan 200.000). `waf_log`'daki
ile aynı gerekçe: zamana dayalı saklama yoğun sitede tabloyu sınırlayamaz.
`MAX(id)` ile hesaplanır, `COUNT(*)` değil.

**Index'ler yazma maliyetidir.** Rapor sorguları denetlendi: `idx_peak_memory`
(rapor türetilmiş tabloda sıralıyor), `idx_script` (`GROUP BY` bir `CASE`
ifadesinde) ve `idx_duration` (yüzdelik sorgusu zaten `log_timestamp` ile
filtreli) hiç kullanılmıyordu. Beş ikincil index → iki.

**p95 dev `OFFSET` ile hesaplanmamalı.** MySQL atladığı her satırı yürür;
milyonlarca satırda rapor sayfası sunucunun en ağır sorgusu olur. 20.000
satırın üstünde en yavaş %5'lik dilim okunup minimumu alınır — 95. yüzdelik
tanımı gereği o dilimin içindedir, sonuç aynı.

**Yoğun sitede önce `PERF_MONITOR_SAMPLE_RATE` düşürülür**, izleyici kapatılmaz.
%10 örnekleme aynı ortalama ve yüzdelikleri onda bir yazma ile verir. Tüm
sabitler `data/config(default).php`'de açıklamalarıyla listelidir.

---

## IPv6 Yasağı — Kolon Genişliği Tuzağı (2026.2.7)

`banned_ip_addresses.ip_address` IPv6'dan eski ve **`VARCHAR(15)`** idi —
`255.255.255.255` tam olarak bu genişlik. Bir IPv6 adresi yazıldığında
15. karakterden sonrası **sessizce kesiliyordu**. Ortaya bileşik bir hata
çıkıyordu:

1. `waf_auto_ban()` **tam adresle** mevcut satır arıyor, bulamıyor
   (kayıtlı olan kesik kütük).
2. INSERT ediyor, MySQL yine kesiyor.
3. `waf_ip_matches()` tam adresi kütükle karşılaştırıyor, `false` dönüyor
   → **yasak hiç uygulanmıyor**.
4. Aynı istemci tekrar ihlal ediyor, 1. adıma dönülüyor.

Görünen belirti: tek bir adres için onlarca özdeş yasak satırı, o adres ise
hiç engellenmeden gezmeye devam ediyor. **Hem mükerrer satırlar hem yasağın
işlememesi bu tek kolonun sonucu.** IPv4 hiç etkilenmiyordu (12-15 karakter),
bu yüzden aylarca fark edilmedi.

**Kural:** yeni bir IP kolonu `VARCHAR(45)` olmalı (IPv4-mapped IPv6'nın en
uzun hâli 45 karakter). `waf_log`, `waf_ip_reputation` doğru boyutlandırıldı.

**`waf_auto_ban()` artık tek atomik `INSERT ... ON DUPLICATE KEY UPDATE`.**
Eski SELECT-sonra-INSERT ayrıca **yarış durumu** taşıyordu: aynı saldırgandan
gelen iki eşzamanlı istek "kayıt yok" görüp ikisi de ekleyebiliyordu.
`uniq_entry(ip_address, list_type, source)` mükerreri yapısal olarak
imkânsız kılar.

`settings.php` manuel liste INSERT'i de ODKU kullanır — aksi hâlde ekranı
ikinci kez kaydetmek UNIQUE ihlaliyle `Query failed` verirdi.

---

## WAF Kaydı — Toplama ve Tavan (2026.2.6)

**Sorun:** olay başına bir satır yazılıyordu. Tek bir test patlaması 6500 satır
üretti. Sürekli bir saldırıda log'un kendisi saldırıdan daha büyük bir yük
kaynağına dönüşüyor — güvenlik duvarı kendi veritabanını boğuyor.

**Toplama anahtarı:** `sha1(ip | rule_id | action | category | path)` +
5 dakikalık kova (`WAF_LOG_WINDOW`). Yazma tek bir
`INSERT ... ON DUPLICATE KEY UPDATE`; tekrar eden olayda tablo **hiç
büyümez**, sadece `hit_count` artar.

**Query string anahtardan bilerek çıkarılır.** Payload her istekte değişir;
anahtara girseydi tam da toplamanın en gerekli olduğu anda (fuzzing) toplama
işlevsiz kalırdı. Payload örnek olarak `matched` sütununda durur.

**Tekrarda örnek sütunlar (`matched`, `request_url`, `user_agent`) yeniden
yazılmaz.** İlk örnek yeterlidir; her engellenen istekte beş string sütunu
güncellemek maliyeti geri getirir.

| Senaryo | İstek | Satır |
|---|---|---|
| Tek IP burst | 6.500 | 1 |
| Fuzzing, 3000 farklı payload | 3.000 | 1 |
| Dağıtık, 200 IP | 10.000 | 200 |
| 400 farklı yol taraması | 400 | 400 |

**Satır tavanı `waf_enforce_log_cap()`** — zamana dayalı saklama tek başına
tabloyu sınırlayamaz: yeterince büyük bir saldırı saklama penceresinin içini
doldurur. Tavan, tablonun en kötü durumunu bilinebilir kılar.

`COUNT(*)` **kullanılmaz** (InnoDB'de tam index taraması, canlı istek üzerinde
çalışıyor). `MAX(id)` sabit zamanda index'ten gelir; auto-increment olduğu için
kesme noktası olarak yeterince doğrudur. Tavan sweep'te **1/20** sıklıkta
çalışır (saklama temizliği 1/200) — flood sırasında 200'de bir çalışan bir
emniyet valfi emniyet valfi değildir.

**Raporlamada `SUM(hit_count)` kullan, `COUNT(*)` değil.** Aksi hâlde bir
saldırı avuç içi kadar olay gibi görünür.

---

## Ziyaretçi Raporlaması — Yazarken Topla (2026.3.4)

**Ham `visitors` tablosunu sayarak rapor üretme.** Günde 100–200 bin ziyaretin
olduğu bir sitede birkaç hafta sonra bu tablo milyonlarca satır olur;
`HOUR(FROM_UNIXTIME(start_timestamp))` üzerinden gruplayan her sorgu indeks
kullanamaz ve geçici tablo kurar. Panel 20–30 saniyede yükleniyordu, rapor
sayfası hiç açılmıyordu.

**İki özet tablosu** (`waf_log`'un 2026.2.6'daki biçimi — bucket key +
`INSERT ... ON DUPLICATE KEY UPDATE`; tekrar eden görüntüleme satır eklemez,
sayacı artırır):

| Tablo | Anahtar | Ne tutar |
|---|---|---|
| `visitor_stats_hourly` | `(stat_date, stat_hour)` | Yeni oturum + sayfa görüntüleme. Trafik ne olursa olsun günde 24 satır. |
| `visitor_content_hourly` | `bucket_key = sha1(tarih\|saat\|page_id\|item_type\|item_id)` | Saat + sayfa + öğe başına görüntüleme |

`bucket_key` neden hash: kolonların kendisinden bileşik UNIQUE kurmak
utf8mb4'te ~780 bayt eder, MySQL 5.6 COMPACT'ta kolon başına 767 bayt sınırını
aşar.

**Sorgu dizesi anahtara girmez.** Payload her istekte değişir; girseydi tam da
toplamanın gerektiği anda (sayfalama, filtre kombinasyonları) işlevsiz kalırdı.

### Öğe Kimliği — Başlık Değil, Birincil Anahtar

`landing_page_name` yalnızca **ilk** sayfada yazılıyordu ve o noktada slug
zaten sıyrılmıştı (`get_page.php:92` katalog detay, `:122` form item view).
Her ürün `urun-detay`, her makale `blog-gorunum` olarak kaydediliyordu —
"saat 15'te hangi makale okundu" sorusunun cevabı veritabanında hiç yoktu.

`pg_track_content($type, $id)` ile render kodu çözdüğü öğeyi bildirir;
`pg_record_visitor_page_view()` okur. **İlk kayıt kazanır** — sayfada birden
çok sistem widget'ı varsa URL'nin işaret ettiği öğe, alttaki "benzer ürünler"
widget'ı tarafından ezilmemeli.

Kayıt noktaları:

| Kaynak | Yer |
|---|---|
| Ürün / ürün grubu (legacy katalog) | `get_catalog_item_from_url()` |
| Ürün (sistem widget, `/b/<slug>`) | `_render_system_widget_catalog_item_view()` |
| Form / blog (legacy) | `get_form_item_view.php` |
| Form / blog (sistem widget) | `_render_system_widget_form_item_view()` |

**Başlık satıra kopyalanmaz**, `item_type` + `item_id` saklanır ve rapor
okunurken `pg_visitor_label_content_rows()` toplu JOIN ile çözer (tip başına
tek sorgu). Ürün adı değişince tüm geçmiş rapor da güncellenir. Silinmiş
kayıtta sayfa adına düşülür — trafik kaybolmaz.

`get_catalog_item_from_url()` artık **memoize**. Tek katalog detay
render'ında 5 yerden çağrılıyordu (`get_page_content.php` 2172, 3074, 3409,
3431, 5043) ve her seferinde aynı iki sorguyu tekrar çalıştırıyordu.

### Öğe Linki Sayfa Adından Kurulmaz

Kayıttaki `page_name` öğeyi **gösteren şablondur**, öğenin adresi değil. Tek
başına kullanılırsa panel `/blog-post` gibi hiçbir şey gösteremeyen bir sayfaya
link verir. `pg_visitor_label_content_rows()` üç biçimi de kurar:

| Öğe | Adres | Kaynak desen |
|---|---|---|
| Ürün / grup | `{kayıtlı sayfa}/{address_name}` | `get_catalog.php:1088` |
| Form, güzel URL açık | `{liste sayfası}/{address_name}` | `get_form_list_view.php:1407` |
| Form, güzel URL kapalı | `{kayıtlı sayfa}?r={reference_code}` | `get_form_list_view.php:1411` |

Güzel URL'de kayıtlı sayfa **kullanılamaz** — `get_page.php:122` sayfa adını
form item view'a çevirir, oysa link **liste** sayfasını ister (`/blog/...`,
`/blog-post/...` değil).

"Güzel URL açık" iki koşuldur: `custom_form_pages.pretty_urls = 1` **ve** en az
bir `form_fields.rss_field = 'title'` alanı (adres adı o alandan türetilir).
`check_if_pretty_urls_are_enabled()` ile aynı kural, ama satır başına değil
sonuç kümesi başına tek sorgu.

### Grafik Ziyaretçi Çizer, İpucu Görüntüleme Sayar

`visitor_stats_hourly.new_visitors` = **o saatte başlayan oturum**.
`visitor_content_hourly.views` = **o saatte yapılan görüntüleme**. 14:00'te
girip 15:00'te 10 sayfa okuyan ziyaretçi 15:00'e 0 oturum + 10 görüntüleme
bırakır. İkisi de doğru; **etiketsiz yan yana konursa çelişkili görünür**
(rapor edilen kusur: ipucunda "makale · 10", grafikte 0). Widget ipucu her iki
sayıyı da adıyla basar.

### Ana Sayfa Birden Çok Adla Kaydedilir

`''`, `'/'`, `'index.php'`, legacy `'example.com/'` ve sayfanın kendi adı —
hepsi aynı sayfa. Canlı izleme etkilenmez (`get_page.php` ana sayfayı izleme
çalışmadan önce çözer, gerçek adı yazar); **yalnız backfill** bunları
birleştirmek zorundadır, yoksa ana sayfa panelde iki satır olur ve trafiği
ikiye bölünür. `pg_home_page_group_expression()` bu iş için yazılmıştı;
rollup `page_id` ile grupladığı için artık gerekmiyor.

### İki Özet Tablosu Aynı Şeyi Saymak Zorunda (2026.3.8)

`visitor_stats_hourly.page_views` `SUM(visitors.page_views)` ile,
`visitor_content_hourly.views` ise `COUNT(*)` ile dolduruluyordu. Biri
**görüntüleme**, diğeri **oturum** sayıyordu. Panel ipucu saatin toplamını
birinciden, o saatin en çok görüntülenen öğesini ikinciden okuduğu için
ekranda şu çıkıyordu:

    21:00
    Sayfa Görünümleri: 29
    ↳ home-1 · 1

İkisi de `SUM(page_views)` kullanmalı. Backfill'de bir oturumun tüm
görüntülemeleri **giriş sayfasına** yazılır — giriş sayfasını şişirir, ama
geçmiş satırda başka sayfa kaydı yok; alternatif veriyi tamamen atmak.

### `visitors.page_views` Akan Bir Toplamdır — Kesme Noktası id Yetmez

Backfill her oturumun `page_views` kolonunu **o satıra vardığı anda** okur.
Kesme noktasının altındaki bir oturum hâlâ geziniyorsa, aynı görüntülemeler
hem canlı sayaçla hem de backfill'in okuduğu son değerle sayılır. 25
görüntülemenin 29 görünmesinin sebebi buydu.

`pg_visitor_view_awaits_backfill()`: ziyaretçinin id'si **imlecin ilerisinde
ve tavanın altındaysa** canlı sayım atlanır — o satır backfill'in işi. İmleç
o satırı geçtikten sonra backfill bir daha bakmayacağı için canlı saymak
doğrudur, kayıp olmaz.

**Özet tabloları türetilmiş veridir; bozulursa yeniden üretilir.** Bir kova
tek bir toplam tutar, hangi yarısının nereden geldiği yazmaz — yerinde
düzeltilemez. `2026.3.8` iki tabloyu `TRUNCATE` edip `visitors`'tan yeniden
kurar. Bedeli: yükseltmeden beri canlı toplanan **öğe kırılımı** sayfa
seviyesine iner (`visitors` zaten öğeyi hiç bilmiyordu).

### Backfill Saat Dilimini Kendi Ayarlamak Zorunda

Canlı sayaç kovayı **PHP'nin** saatiyle seçer (`date('Y-m-d')` / `date('G')`),
backfill ise **MySQL'in** saatiyle (`DATE(FROM_UNIXTIME(...))`). İkisi
ayrışırsa geçmiş ile yeni veri farklı saatlere düşer ve tam ikisinin
birleştiği yerde fark kadar bir dikiş kalır.

Normal isteklerde sorun yok: `init.php:541` her istekte
`SET time_zone = date('P')` çalıştırır. **Ama `install/index.php` `init.php`'yi
hiç yüklemez** — yalnız `config.php` ve `functions.php` require eder. Yani
yükseltme, MySQL sunucusunun varsayılan saat dilimiyle çalışır.
`pg_visitor_backfill_step()` bu yüzden ilk iş `pg_sync_mysql_timezone()`
çağırır.

Ad yerine **offset** (`date('P')`) yazılır: isimli dilimler MySQL'in timezone
tablolarının dolu olmasını ister, paylaşımlı hostingde çoğu zaman boştur.

Not: saatlerin kaymış görünmesi çoğu zaman bu değil, **sitenin kendi saat
dilimi ayarıdır** (Ayarlar → Saat Dilimi). Kayma tüm ekranlarda tutarlıysa
önce oraya bak; yalnız rapor ekranlarında varsa kod tarafına bak.

### Backfill Kesilebilir Olmak Zorunda

**`ini_set('max_execution_time')` yeterli değildir.** Yazılım onlarca farklı
sunucu tipinde çalışıyor; IIS FastCGI (`requestTimeout` varsayılan 90 sn) ve
nginx proxy isteği PHP'nin ayarına bakmadan keser. Üstelik sürüm numarası
upgrade fonksiyonu döner dönmez yazılır (`includes/migrations/runner.php`,
`install_run_upgrades()`) ve her adım tekrar koşulabilir — yarıda kesilen bir
yükseltme kaldığı yerden sürer, ama kesilen adımın kendisi baştan başlar.

Bu yüzden pahalı adımlar **ayrı sürümlere** bölündü (3.4 şema / 3.5 backfill /
3.6 motor), her biri kendi sürümünü ayrı işaretler. `pg_visitor_backfill_step()`
zaman bütçesi + `config.visitor_rollup_cursor` ile çalışır, **her parçadan
sonra** konumu yazar. Kalan iş yönetici panele girdikçe 3 saniyelik
dilimlerle tamamlanır; kimsenin uzun bir sayfayı bekletmesi gerekmez.

**Tavan `MAX(id)` tablolar oluşturulmadan ÖNCE okunur.**
`pg_visitor_rollup_ready()` tablo görünür olur olmaz `true` döner ve canlı
sayım başlar. Tavan önce okunursa iki aralık çakışamaz; arada kalan birkaç
görüntüleme **eksik** sayılır, mükerrer değil — yanlış olunacaksa bu yönde
olmalı.

`pg_visitor_rollup_ready($recheck)` — statik önbelleği atlatan parametre.
Yükseltme tabloyu aynı istek içinde oluşturuyor; önceden `false` önbelleklenmiş
olsaydı backfill kendini sessizce atlar ve başarı raporlardı.

### Rapor Sayfası — PHP'de Değil SQL'de Topla

`view_visitor_report.php` eşleşen **her** ziyaretçi satırını LIMIT'siz çekip
PHP dizisinde topluyordu. Üstelik `detail` kutusu kapalıyken bile her satırı
`$results[...]['visitors'][]` dizisine ekliyor, sonra o diziyi hiç
kullanmıyordu — sayfanın açılmama sebebi buydu.

- Toplamlar `GROUP BY` + `SUM()`/`COUNT()` ile gelir, **daima tüm eşleşen
  satırları** kapsar (sayfalama toplamları etkilemez).
- Detay satırları ayrı sorguda, `LIMIT`/`OFFSET` ile 500'erli.
- Tam veri için akış CSV: `MYSQLI_USE_RESULT` + 1000 satırda bir `flush()`.
  Tepe bellek tek satır. **`mysqli_query()` varsayılanı sonucu PHP'ye
  tamamen çeker** — dizide biriktirmemek tek başına yetmez.
- `$where`, `prepare_sql_operation()` ve tüm `summarize_by` seçenekleri
  **değişmedi**; gelişmiş filtreler ham `visitors` tablosunu okumaya devam
  eder.

### ONLY_FULL_GROUP_BY (MySQL 5.7+ varsayılanı)

Rollup sorguları yazılırken iki tuzak:

- **GROUP BY'da sıra numarası kullanma** (`GROUP BY 1, 2`). MySQL 8'de
  kaldırıldı. Alias yaz.
- **GROUP BY ile birlikte gruplanmamış kolon seçme.** `visitor_content_hourly`
  saatlik zirve sorgusunda beraberlik PHP'de çözülür (`ORDER BY stat_hour, id`
  + ilk satır kazanır), `GROUP BY c.stat_hour` ile değil.
- Bucket key'i **gruplamadan sonra** hesapla. `SHA1(...)`'i GROUP BY'lı
  SELECT'e koymak, MySQL'den hash'in kendi girdilerine fonksiyonel bağımlı
  olduğunu kanıtlamasını ister; sürüme göre değişir. Backfill türetilmiş
  tabloda gruplar, dışarıda hash'ler.

### MyISAM Neden Bırakıldı

Tablo seviyesinde kilit. `update_visitor_page_data()` **her sayfa
görüntülemesinde** `UPDATE visitors` çalıştırır ve MyISAM'da UPDATE tabloyu
dışlayıcı kilitler — bu yükte sayfa görüntülemeleri zaten birbirini
bekliyordu. MyISAM'ın eşzamanlı ekleme optimizasyonu kurtarmaz: yalnız
tabloda boşluk yokken çalışır, sürekli UPDATE alan tabloda hep boşluk vardır.
Buna 20 saniyelik bir rapor okuma kilidi eklenince sitedeki her ziyaretçi
sıraya girer — panel açıkken sitenin yavaşlamasının sebebi buydu.

İkinci sebep kurtarma: temiz olmayan kapanış sonrası bu boyutta bir MyISAM
tablosu `REPAIR TABLE` ister, saatlerce sürebilir ve o süre boyunca tablo
yazılamaz.

**Yalnız `visitors` dönüştürüldü.** `search_items` FULLTEXT indeks taşır
(`get_search_results.php:486` `MATCH ... AGAINST`), eski MySQL'de FULLTEXT
sadece MyISAM'da vardır.

## Ziyaretçi Sayacı — Dedup Anahtarı

**`http_referer` dedup anahtarından çıkarıldı (2026.2.5).**

`init_tracking()` içindeki "son 30 dakikada aynı ziyaretçi var mı" sorgusuna
yalnızca **çerez kabul etmeyen** istemciler girer — yani neredeyse tamamen
otomatik trafik. Böyle bir istemci gezdiği her sayfada farklı referer
gönderdiği için eşleşme hiç tutmuyor ve **her istek için yeni bir ziyaretçi
satırı** açılıyordu. Blog gezen tek bir kazıyıcı on binlerce "ziyaretçi"
üretiyordu; site sayacının Google Analytics'in çok önünde gitmesinin sebebi
buydu (GA JavaScript tabanlı, botların hiçbirini saymıyor).

Kampanya ayrımı bozulmaz: `tracking_code` ve `affiliate_code` anahtarda
kalmaya devam ediyor. `http_referer` **kayda yazılmaya devam eder**, sadece
eşleştirme ölçütü değildir. Birden fazla satır eşleşebildiği için sorguya
`ORDER BY stop_timestamp DESC LIMIT 1` eklendi — en son aktif kayıt kazanır.

**Ziyaretçi IP'si artık `waf_client_ip()` ile çözülür.** CDN arkasında
`REMOTE_ADDR` edge sunucusudur; tüm ziyaretçiler birkaç adrese çöküyordu.
Güvenilen vekil tanımlanmamışsa fonksiyon `REMOTE_ADDR` döndürür, yani düz
hosting'de davranış birebir aynıdır.

**`user_agent` kolonu defansif probe ile yazılır** (`waf_table_has_column`).
Kodu alıp DB'yi yükseltmeyen kurulumda sayfa görüntüleme hata vermez, kolon
sessizce atlanır.

Teşhis sorguları: `docs/ziyaretci-sayaci-teshis.sql`

---

## WAF (Web Uygulama Güvenlik Duvarı) — `waf.php`

**Bağımsız modül.** `functions.php`'ye bağımlı değil, çünkü iki farklı bootstrap'tan
çağrılır: `router.php` (get_file.php / robots.txt / sitemap.xml — bunlar `init.php`'ye
hiç uğramaz) ve `init.php` (doğrudan istenen tüm software script'leri).

**İki aşama, ayrı mandal (latch):**

| Aşama | Nerede | Ne çalışır | Neden |
|---|---|---|---|
| `waf_run('router')` | router.php, `REQUEST_URL` tanımından sonra | IP listeleri, saldırı aracı UA, **global** hız sınırı | Oturum henüz başlamadı |
| `waf_run('init')` | init.php, `initialize_user()` sonrası | + imza taraması, **hassas** hız sınırı | Tasarımcı ile saldırganı ancak burada ayırt edebiliriz |

### WAF Veritabanının Arkasındadır — `includes/db_guard.php`

`init.php`: `db_connect()` **225**, `waf_run('init')` **737**. Bağlantı havuzu
dolduğunda istek 225'te ölür ve **güvenlik duvarı hiç çalışmaz** — saldırganı
yasaklayamaz, kilit kendini besler. 2026-08-31'de bir siteyi bu düşürdü
(`forgot_password.php` + SMTP bekleyen bağlantı + `max_user_connections`).

Üç kural buradan doğdu:

- **Bağlantı hatası fatal olamaz.** PHP 8.1'den beri mysqli exception fırlatır
  ve `@` bunu bastırmaz; `db_connect()`'in `else` dalı yıllardır ölü koddu.
  `try/catch` + 503 zorunlu. İstek başına stack trace, disk kotalı hostingde
  ikinci bir çöküş sebebidir — log **dakikada bir** satır yazar.
- **Yalnız geçici hata tripler.** 1040/1203/1226/2002/2003/2006/2013 → geri
  çekil; 1045/1049 → hata sayfası. Beklemek yanlış parolayı düzeltmez ve
  "sonra deneyin" ekranı onu düzeltebilecek tek kişiden gizler.
- **`pg_db_guard_check()` `functions.php`'den ÖNCE çağrılır.** `db_connect()`'e
  varıldığında istek zaten megabaytlarca kodu ayrıştırmıştır. Guard dosyası bu
  yüzden bağımsızdır: DB yok, `functions.php` yok, `lang()` yok, session yok —
  `waf.php` ve `get_file.php` ile aynı kısıt.

**Yasak aynası** (`data/temp/ban_shield.txt`): tek yazıcı
`waf_ban_shield_refresh()`, waf.php'de. Vekil satırları (`P`) blok satırları
(`B`) kadar önemlidir — CDN arkasında `REMOTE_ADDR` edge'dir, çözemeyen ayna
hiçbir şeyle eşleşmez. `waf_client_ip()` mantığı **kopyalanmaz**, onun çözdüğü
liste dosyaya yazılır; iki kopya zamanla ayrışır. Ayna 6 saatte bayatlar
(kaldırılmış yasak, ekranı olmayan dosyadan uygulanmaya devam etmesin),
otomatik yasakta `waf_ip_lists(true)` ile anında tazelenir, atomik yazılır.
**Yalnız zaten yasaklanmış adresi tanır** — ilk dalga yine DB'ye ulaşır.

**Pahalı iş DB bağlantısı tutarken yapılmaz.** `email()`, dış API çağrısı,
uzun döngü — hepsinin önüne sınır konur (`pg_password_reset_guard()` deseni).
Giriş throttle'ı *başarısızlık* sayar; sıfırlama/gönderim uçlarında sayılacak
başarısızlık yoktur, **istek hızı** sayılır.

**Hız sınırı istek sayar, asıl kıt kaynak bağlantı-saniyesidir.** `waf_rate`
sınırları (hassas: 30/dk) ucuz istek varsayımıyla ayarlı. SMTP bekleyen bir uçta
20/dk × 30 sn = 10 eşzamanlı bağlantı — **sınırın altında kalarak havuzu
doldurmak mümkün.** Yeni bir uç bağlantı tutarken dış bir şey bekliyorsa kendi
sınırını alır; genel hız sınırına güvenme.

**Sessizce reddeden koruma, korumanın yarısıdır.** 2026-08-31 olayında saldırı
`waf_log`'a hiç düşmedi (havuz doluyken tabloya yazılamıyor) ve operatör günler
sonra PHP hata kaydından fark etti. Yeni bir sınır/kapı eklerken `waf_log_event`
çağrısını da ekle. Kesinti kaydı (`db-unavailable`) **skor 0** ve mod
kontrolünden önce yazılır: kesinti güvenlik kararı değil, olgudur — skorlanırsa
site geri geldiği anda gelen ilk ziyaretçi yasaklanır.

**Kalkan yalnız engeller, saymaz.** DB öncesinde tek karar verilir: "bu adres
zaten yasaklı mı?" Hız sınırı, imza taraması, sayaç — hiçbiri yok.

**DB öncesinde sayaç kurma.** Bir tur denendi (kuşatma modu, 2026-08-31),
incelemede çıkarıldı. Tekrar yazmadan önce neden çalışmadığını oku:

- **Pencere hiç kapanmıyordu.** `FILE_APPEND` mtime'ı ilerletir, yani "yeni
  pencere aç" testi *ilk istekten geçen süreyi* değil *son istekten beri geçen
  sessizliği* ölçüyordu. 10 istek/dk 6 dakikada 60 eşiğini aşıyordu — ve
  `router.php` `get_file.php`'den önce çalıştığı için **her görsel bir sayım**;
  30 görselli tek sayfa 30 sayım.
- **İstek başına iki kez çalışıyordu.** `router.php` ön denetleyici,
  yönlendirdiği sayfa `init.php`'yi içeriyor. Latch yoktu, eşik fiilen yarıya
  iniyordu. (`pg_db_guard_check()` artık `static $done` taşıyor.)
- **Saldırgan tek `X-Forwarded-For` başlığıyla muaftı.** Başlık adresi
  doğrulanamaz yapıyor, yanlış adresi saymamak için sayım atlanıyordu — yani
  sayılan tek kitle o başlığı hiç göndermeyen normal tarayıcılardı. **Saldırganın
  atladığı, müşterinin atlayamadığı bir sınır, sınır olmamasından kötüdür.**
- Rutin bir MySQL yeniden başlatması (2006) sayımı 10 dakika açıyordu.
- Bu katmanda izin listesi yok; yanlış pozitifin çıkış yolu FTP'ydi.

İlk ikisi düzeltilebilirdi. Üçüncüsü hata değil, **bu katmanda çözülemeyecek
bir tasarım**: hangi vekilin başkası adına konuşabileceğini söyleyen liste
veritabanındadır. Adrese güvenilemeyen yerde sayım yapılmaz.

**Aynada vekil satırları bayatlamaz, blok satırları bayatlar.** Asimetri
bilinçli: bayat yasak tehlikelidir (operatörün kaldırdığını, ekranı olmayan bir
dosyadan uygular), bayat vekil aralığı değildir (CDN blokları aylarca değişmez,
yalnız isteğin *hangi* adrese yazılacağını belirler).

İkisini birlikte eskitmek CDN arkasında kalkanı **sessizce işlevsiz** bırakır:
vekil satırı yoksa `REMOTE_ADDR`'e (edge'e) düşülür, yasaklar hiçbir yasak
listesinde bulunmayan bir adrese karşı denenir. Üstelik uzun bir kesinti,
aynanın tazelenemediği (DB gerektiren) tam o durumdur.

### CIDR Öneki Sayı Değilse Desen **Reddedilir**

`waf_ip_in_cidr()` ve aynadaki ikizi `waf_ip_matches_simple()`, `/` sonrasını
`ctype_digit()` ile doğrular. Boş ya da sayı olmayan önek → `false`.

Eskiden `(int) $parts[1]` yazıyordu. `(int) "abc"` ve `(int) ""` **sıfırdır**,
sıfır da geçerli bir önek uzunluğudur ("hiçbir biti karşılaştırma"), dolayısıyla
fonksiyon **dünyadaki her adres için `true`** dönüyordu:

| Desen | Eski sonuç |
|---|---|
| `1.2.3.4/` | herkes engellenir |
| `1.2.3.4/abc` | herkes engellenir |
| `1.2.3.4/24x` | herkes engellenir |

Yasak listesi operatörün elle yazdığı serbest metin alanı; sondaki eğik çizgi
tek tuş. Üstelik kalkan bu deseni **veritabanına varmadan**, ekranı olmayan bir
dosyadan uyguluyor — yani hatayı gösterecek hiçbir yer yok.

`0.0.0.0/0` **bilerek** herkesi kapsamaya devam eder: operatörün yazdığı desen
korunur, yazmadığı desen uydurulmaz.

<!-- pg-waf-docs:begin (bu blok _restore_waf_docs.php tarafından yönetilir) -->
### Özne: IPv4'te Adres, IPv6'da /64 — `waf_ip_subject()`

Adres başına sayan her sayaç **özneyi** sayar, ham adresi değil: IPv4'te
adresin kendisi, IPv6'da `/64` (CIDR biçiminde, çünkü aynı string yasak
listesinde geçerli desendir ve sayılan şey yasaklanabilir). Gerekçe: IPv6
abonesine en az /64 verilir, içindeki her adres bedavadır; adres anahtarlı
bir kova hiçbir zaman 1'i geçmez ve son kullanılan adrese konan yasak hiçbir
şeye mal olmaz. Kullananlar: üç hız sınırı, `waf_register_offence()` (sayım
ve yasak hedefi), giriş kilidi `'a'` öznesi, `pwreset-ip`, eş zamanlı istek
tavanı. `waf_log.ip_address` gerçek adresi yazmaya devam eder.

**IPv4 /24 toplanmaz.** CGNAT arkasındaki operatör bloğu tek kova olarak
sayılırsa kampanya günü gerçek müşteri 429 alır; IPv4'te rotasyon zaten
bedava değildir.

**/64 yasağı izinli adresin üstüne konmaz.** `waf_subject_contains_allowed()`
blok içinde izin listesinden bir adres bulursa yalnız suçlu adres
yasaklanır. Kalkan yalnız B satırı taşır; blok yasağı izinli adresi izin
listesine hiç bakılmadan kapıdan çevirirdi.

### Yasak Merdiveni — `hit_count` Yasak Sayısıdır

`waf_auto_ban()` tek `INSERT … ON DUPLICATE KEY UPDATE` ile: önceki yasak
**bitmişse** `hit_count + 1` ve süre `taban × 4^(hit_count−1)`
(60 → 240 → 960 → 3840 → 10080 dk; tavan `waf_auto_ban_max_minutes`,
varsayılan 7 gün). Yasak sürerken yalnız "en az taban kadar kalsın". Bu
yüzden `hit_count` **yasak sayısıdır**, olay sayısı değil — tek dalgadaki eş
zamanlı istekler merdiveni tırmandırmaz. Atama sırası MySQL'in soldan sağa
değerlendirmesine dayanır; tersi basamağı bir alta düşürür (kısa, asla
uzun). Dönüş değeri fiili süre (dk); log satırı onu yazar.

**Süresi dolan auto satırı hemen silinmez.** `waf_sweep()` log saklama
süresi kadar (retention 0 ise 30 gün) tutar; merdivenin hafızası budur.
`waf_ip_lists()` zaten `expires_at > now` filtreler, engellemede fark yok.
Kaldır düğmesi satırı siler → merdiven sıfırlanır, bilinçli.

### Eş Zamanlı İstek Tavanı — `waf_inflight_*`

Hız sınırı dakikada istek sayar; havuzu dolduran şey **aynı anda açık**
bağlantıdır (2026-08-31: SMTP bekleyen `forgot_password.php`). Hassas
script'lerde özne başına en fazla `waf_inflight_limit` (varsayılan 3,
0 kapatır) istek açık olabilir; fazlası 429, kural `concurrent`, kategori
`rate`, offence 5. `init` aşamasında, hassas hız sınırıyla aynı
muafiyetler ve aynı `waf_rate_limit` anahtarı.

Mekanizma dosyadır: `data/temp/waf_inflight_<sha1(özne)>.txt`, satır
başına `token başlangıç`, `flock(LOCK_EX)` altında oku-karar-yaz,
`register_shutdown_function` ile bırakma (fatal ve `exit()` sonrası da
koşar). 120 sn'den eski slot ölü sayılır — sert ölen worker'ın sızdırdığı
slot kendini onarır; sweep 10 dk dokunulmamış dosyayı siler. Token pid
değildir: pid tekrar eder, `getmypid` disable_functions'ta olabilir.
Mekanizmanın kendi hatası (yazılamayan dizin, kilit alınamadı) **her zaman
geçirir**. İzleme modunda slot yine alınır ki log ilk reddi değil tamamını
göstersin. DB'den önce koşmaz: özne çözümü güvenilir vekil listesini,
o da DB'yi ister ("DB öncesinde sayaç kurma" yukarıda).

### Güvenlik Başlıkları Firewall Anahtarına Bağlı Değildir

`waf_send_security_headers()` (her iki bootstrap) ve `waf_send_csp_header()`
(yalnız init.php — router dosya servis eder) `waf_run()`'ın **dışında**
çağrılır. Kapatma anahtarı mutlaktır kuralı bozulmasın diye: firewall'ı
kapatan operatör yanıt başlıklarını kaybetmemeli. Kendi anahtarı
`security_headers`. HSTS ayrı ve varsayılan **kapalı** — bir yıllık
taahhüt, kapatmak geri almaz; yalnız Güvenli Mod açıkken
(`url_scheme === 'https://'`, ekranda kutu ona kadar pasif, kayıtta 0'a
zorlanır) ve `check_if_request_is_secure()` doğruysa gönderilir — "https
mi" sorusunun **tek** cevabı odur (`TRUST_PROXY_SSL_HEADERS`,
`test_secure_mode.php`); ikinci bir tanım yazma. Bu yüzden HSTS init
aşamasından, CSP ile gider. `Permissions-Policy` `(self)` ile yazılır,
`()` ile değil; `payment` bilerek yok.

**Yerleşik CSP öğrenmek için yazılmıştır.** Satır içi kod ve `'unsafe-eval'`
serbest, yazılımın kendi CDN'leri (jsDelivr, jQuery, DataTables, CodeMirror,
Google Fonts, Cloudflare beacon'ları) listeli, geri kalan her üçüncü taraf
ihlal. **Panele yeni bir dış kaynak eklersen `waf_csp_policy()`'ye de ekle**,
yoksa her panel sayfası rapor üretir. Operatör politikası yerleşiği tümüyle
değiştirir; CR/LF katlanır; `report-uri` yoksa eklenir.

**Toplayıcı `csp_report.php` bağımsızdır** — init/DB/session yok, yalnız
waf.php; 16 KB gövde, GET 405, 204 döner. Dosyaya (origin, yönerge, yol)
üçlüsünde toplar: `data/temp/csp_reports.json`, 500 kayıt / 14 gün. Router
üzerinden geçmediği için hassas hız sınırına girmez — girseydi ihlalli bir
sayfa ziyaretçinin sonraki POST'una 429 yedirirdi. `waf_log`'a yazılmaz:
ziyaretçi başına satır tavanı gerçek olayları dışarı iter.

### fail2ban Günlüğü — `data/firewall.log`

`YYYY-MM-DD HH:MM:SS pinegrap-firewall DENY|BAN ip=<adres> k=v…`, yerel
saat. **`ip=` daima ziyaretçi adresi**, aralık değil (`<HOST>` eşlesin);
/64 yasağı `subject=`'te. Yazanlar: `waf_deny()` (kural `waf_last_rule()`
ile — `waf_log_event()` her çağrıda son kuralı hatırlar), auto-ban
(`waf_register_offence`), giriş kilidi, kalkan (`pg_ban_shield_text_log`,
ikiz kopya; anahtarı aynanın `L` satırından okur). 5 MB'ta bir kopya
döner. Yeni bir deny yolu eklersen satırı da ekle.

### Giriş Sorusu — `pg_login_captcha_*`

Kilitten önce, `login_throttle_captcha_after` başarısızlıktan sonra (3; 0
kapatır). **Karar `waf_rate` sayaçlarından, oturumdan değil** — çerezi atan
oturumu da atar, sayaç kalır. `get_captcha_fields()` kullanılmaz (cevap
gizli alanda base64, betik okur); soru oturuma bağlı, tek kullanımlık, 10
dk. Ara ekran POST işleyicisinden basılır (`pg_login_captcha_gate` guard'dan
hemen sonra, `pg_login_captcha_after_failure` hata işaretlendikten sonra);
giriş formlarının dört çizim yerine alan eklenmez. **Kimlik alanlarının ad ve
id'si, yerine geçtiği giriş formuyla birebir aynıdır** (`email`/`password`,
üyelikte `u`/`p`) — parola yöneticileri kaydı bunlarla eşler; kendi uydurduğum
id'lerle (`login_captcha_password`) yönetici ya hiç doldurmadı ya da başka bir
kaydı doldurdu, kullanıcı doğru parolayı girdiğini sanıp "parola yanlış" aldı
(2026-09-16, dev). Parola isteyen ekran, parolanın kaydedildiği ekrana
benzemek zorundadır. **Yanlış cevap başarısız deneme sayılır
(`pg_login_record_failure`), offence sayılmaz.** İlk sürüm offence sayıyordu
ve dev'de ofis adresini bir saat yasakladı: soru kilitten ÖNCEKİ adımdır,
kilitten sert olamaz; beş yanlış cevap bir ofisi 403'ün arkasına koyamaz.
API girişi dışında.

### Yasak Listesi Moddan Bağımsızdır, Kapatma Anahtarından Değil

`waf_run()`'un IP listesi dalı **her modda** engeller (`waf_deny(403, ref,
true)`; `$always`, izleme mandalını tek noktada tutup onu aşan tek kapıdır).
Gerekçe: izleme modu "güvenlik duvarı kendi kararıyla insan reddetmeye
başlamasın, ben izleyeyim" demektir; "yasakladığım adresleri serbest bırak"
demek değildir. Liste operatörün **verisidir** — elle yazdığı ya da ekranda
görüp bıraktığı satırlar — ve oraya girmeyi hak etmiş bir adres, operatör
modu değiştirdi diye kabul edilebilir olmaz. İki gerçek kullanım buna
dayanır: izlerken elle adres yasaklamak, ve saldırı sırasında izlemeye
geçerken zaten yasaklı olanlara kapıyı açmamak.

**Kapatma anahtarı yine mutlaktır.** `waf_run()` kapalıyken döner ve
`waf_ban_shield_refresh()` **mod kontrolünden ÖNCE** çağrılır: kapalıyken
ayna B satırsız yazılır, yani DB öncesi kalkan da bir istek içinde susar —
altı saatlik bayatlamayı beklemez. Bu yüzden refresh `waf_sweep()`'ten
alındı: sweep kapalı modda hiç çalışmaz, oysa aynanın tam o istekte
yazılması gerekir.

`check_banned_ip_addresses()` zaten firewall'ın dışındaydı; bu değişiklik ana
yolu ona uydurur.

### Kalkan da İzin Listesine Uyar ve Artık Loglanır

Ayna `A` satırları taşır (`waf_ban_shield_refresh()`), kalkan blok
kontrolünden önce onlara bakar. Yoksa bir aralığı yasaklayıp içindeki kendi
adresini izin listesine alan operatör, ekranı olmayan tek katmanda kapıdan
dönerdi. **A satırları bayatlamaz** (P satırları gibi): bayat izin birini bir
katman sonra denetlenmek üzere içeri alır, bayat yasak ise kimseye
açıklanamayan bir ret üretir — ikisinden yalnız biri siteyi havadan indirir.

Kalkanın DB'si yok, bu yüzden reddi `data/temp/shield_pending.log`'a tek
satır yazar (`ts \t ip \t yol`, 256 KB tavan) ve DB'si olan ilk istek
`waf_shield_drain()` ile bunu `waf_log`'a toplu yazar (`ip-list-shield`,
`action=block`, aynı 5 dakikalık kova kimliği, drenaj başına en çok 200
grup). Koşulsuz uygulanan bir kural koşulsuz görünür olmalıdır — 2026-08-31
olayının asıl bileşeni "sessizce reddeden koruma"ydı. Drenaj da mod
kontrolünden önce çağrılır: olan şey olgudur, karar değil.

### Olası Yanlış Pozitifler — `view_waf_log.php`

İzleme → Engelleme kararının verisi: işaretlenen adreslerden hangileri
gerçek kişi. Dört kanıt, ucuzdan pahalıya: `waf_log.user_id > 0`;
`auth_tokens` (beni hatırla; çözümlenmiş adres); `log`'da adlı kullanıcı
(`log_user ∉ {'', 'UNKNOWN', lang('UNKNOWN')}`); tamamlanmış sipariş
(`orders.ip_address` INET_ATON → yalnız IPv4). `log` ve `orders` adresle
indekslenmemiştir → yalnız son 20.000 satır (PK aralığı); ikisi de
`REMOTE_ADDR` tutar → CDN arkasında sessiz kalırlar, yanlış eşleşmezler.
Dönem filtresiyle çalışır, kategori/arama ile değil. "İzin Ver"
`firewall.save.php` ile aynı satırı yazar (`allow` / `manual`), adresi
kapsayan auto yasakları siler, kalkanı tazeler; izinli adres listeden düşer.
Kaldır ve İzin Ver kalkanı **anında** tazeler (`waf_ip_lists(true)` +
`waf_ban_shield_refresh(true)`), mtime throttle'ına bırakılmaz.
<!-- pg-waf-docs:end -->
### Kapatma Anahtarı Mutlaktır (2026.3.1)

`waf_enabled = 0` iken `waf_run()` **koşulsuz döner.** Bot filtresi dahil hiçbir
şey çalışmaz, hiçbir şey loglanmaz.

Önceki sürüm burada `block_unknown_bots`'u ayrı tutuyordu — gerekçe "bot filtresi
güvenlik duvarından eski, kendi anahtarı var" idi. **Yanlıştı ve üretimde
kesintiye yol açtı:** güvenlik duvarını kapatmak engellemeyi durdurmuyordu, yani
yanlış pozitif ayıklayan operatörün engellemeyi durdurma yolu kalmıyordu.
Hiçbir şeyi kapatmayan bir anahtar, anahtar olmamasından kötüdür.

`WAF_ENABLED` sabiti `init.php`'de config satırından tanımlanır — `ECOMMERCE`,
`ADS`, `VISITOR_TRACKING` ile aynı blokta. `waf_mode()` önce onu okur.

`check_banned_ip_addresses()` bunun dışındadır: güvenlik duvarından eski,
operatörün form bazında çağırdığı ayrı bir özellik.

### İzleme Modu Tek Noktadan Garanti Edilir

`waf_deny()` gövdesinin ilk satırı `waf_mode() !== 'block'` kontrolüdür.
Çağıranların hepsi zaten `$blocking` bakıyor, ama "hepsi" tam da yeni bir dal
eklendiğinde bozulan türden bir varsayımdır. İzleme modunun sözü tek yerde
tutulur.

### Referans Numarası Saklanır

`waf_reference()` istek başına **static** — engelleme sayfasında yazan kod ile
log satırındaki kod aynıdır. Eskiden deny anında üretilip hiçbir yere
yazılmıyordu; ziyaretçiye operatörün sorgulayamayacağı bir kod veriliyordu.
`waf_log.reference` kolonu + log ekranında aranabilir.

### Güncelleme Paketi — Sessiz Kısmi Kurulum

**`extractTo()` bir bool döndürür ve eski kod ona hiç bakmıyordu.** Tek başına
bu ihmal, "güncelleme sonrası eksik dosya" probleminin sebebiydi: çıkarma
yazamadığı ilk kayıtta durur (kilitli dosya, izin, dolu disk, `open_basedir`),
sonraki her şey **hiç oluşturulmaz**, ekranda ise "güncelleme tamamlandı"
yazar. Yarım kalmış kurulum sonra elle onarılmak zorunda kalınır.

`pg_extract_archive()` (functions.php) üç şeyi sırayla kanıtlar:

1. `ZipArchive::CHECKCONS` ile açılır — bozuk/yarım paket **tek dosyaya
   dokunmadan** reddedilir, yarı uygulanmaz.
2. `extractTo()` dönüşü kontrol edilir.
3. Arşivin içindeki **her kayıt diskte aranır.** Asıl kısmi çıkarmayı yakalayan
   adım budur; (2) bazı platformlarda kayıt atlanmasına rağmen `true` dönebilir.

Ayrıca indirme tarafında dört sessiz hata kapatıldı: HTTP durum kodu hiç
kontrol edilmiyordu (403/404 gövdesi `.zip` olarak yazılıyordu), kısa transfer
`false` dönmediği için başarı sayılıyordu, `fopen(...,'x')` dönüşü ve `fwrite()`
bayt sayısı yok sayılıyordu.

**Kural:** indirilen paketi işleme almadan önce HTTP 200, beklenen bayt sayısı
ve `PK` imzası (`pg_looks_like_zip()`) doğrulanır; çıkarma daima
`pg_extract_archive()` üzerinden yapılır. `download_assistant.php` bağımsız
çalışmak zorunda olduğu için aynı mantığın kendi kopyasını taşır.

### Güncelleme Kanalı: Kararlı / Beta

`config.software_update_channel` (`ENUM('stable','beta')`, migration 4.21) →
`init.php` sabiti `SOFTWARE_UPDATE_CHANNEL`. Kolon yoksa ya da değer enum'un
dışındaysa `stable`.

Kanalı **kod içinde tek yerden** sorulur (functions.php):

| Fonksiyon | Döner |
|---|---|
| `pg_update_channel()` | `'stable'` / `'beta'` |
| `pg_update_request_key()` | `latest_version` / `latest_beta_version` |
| `pg_update_package_file()` | `pinegrap_software_update.zip` / `pinegrap_software_update_beta.zip` |

Üç çağıran var ve **üçü de aynı yerden sormak zorunda**: günlük kontrol
(`software_update_check.php`), güncelleyici ekranı (`software_update.php`) ve
güncelleyicinin adımları (`api.php` → check / download / replace). Bir kanalda
sürüm sorup diğerinden paket indiren bir site, kendi sürümüyle hiç
karşılaştırmadığı bir paketi kurar. `api.php` indirme adımında paket adını bir
kez alıp `$update_package`'a koyar; `replace` adımı aynı fonksiyonu çağırır —
iki taraf ayrışırsa açma adımı olmayan dosyayı arar.

**`download_assistant.php` kararlıda kalır, kanalı hiç okumaz.** Yazılımı
bozulmuş bir siteyi elle kurtarma yolu; oranın cevabı herkesin çalıştırdığı
yayındır.

Kanal Ayarlar → Genel'den değişir (güncelleyici ekranı güncelleme yokken
ayarlara geri attığı için oradan değiştirilemez). Değişince
`software_update_available` + `last_software_update_check_timestamp` sıfırlanır
ve bekleyen `software_update` bildirimi silinir: eski cevap diğer kanaldan
geldi. **Beta'dan kararlıya dönmek sürüm indirmez** — şema geri alınamadığı
için dosyaları geri yazmak veritabanını ileride bırakırdı; site kurulu
sürümde kalır, kararlı yayın onu geçince güncelleme görünür.

Sürüm karşılaştırması üç parçalı sayısaldır (`explode('.')`), yani **beta
paketleri de `major.minor.maintenance` ile numaralanmalıdır**; `2026.4.5-beta1`
karşılaştırılamaz.

### Güncelleme Kanalında TLS Doğrulaması

`CURLOPT_SSL_VERIFYPEER` lisans / güncelleme / paket indirme yollarında **0**
idi; yanındaki `VERIFYHOST = 2` hiçbir işe yaramıyordu (zincir doğrulanmazsa
doğrulanmış bir sertifika yoktur, hostname'i neye karşı eşleştireceksin).

Kod taşıyan kanalda bunun bedeli farklıdır: araya girebilen biri kendi
arşivini servis eder, web köküne açılır ve çalışır. Güncelleme mekanizması
teslimat mekanizmasına dönüşür.

`pg_curl_tls()` (functions.php) ve bağımsız ikizi `da_curl_tls()`
(download_assistant.php) doğrulamayı açar. **Başarısız olunca güvensize
düşmez** — sessiz downgrade, saldırganın yalnızca ilk denemeyi bozmasını
gerektirdiği için doğrulamasızlıktan kötüdür. Vazgeçmek `config.php`'de açık
bir karardır:

| Sabit | Amaç |
|---|---|
| `CURL_CA_BUNDLE` | CA paketi varsayılan yolda değilse cacert.pem yolu |
| `ALLOW_INSECURE_UPDATE_TLS` | Son çare, varsayılan `false` |

`pg_curl_tls_hint()` cURL 60/77/35 hatalarını operatörün yapabileceği bir
cümleye çevirir — aksi hâlde ekranda "cURL error 60" yazar ve sebebin karşı
taraf değil kendi CA paketi olduğu anlaşılmaz.

**Ödeme ağ geçitleri (`submit_order.php`, Givex) ve kargo API'leri
(`shipping.php`) bilerek dışarıda bırakıldı.** Bazı eski ağ geçitlerinin
sertifikaları gerçekten bozuktur; canlı ödemeyi kırma riski, oradan elde
edilecek kazancın çok üstünde.

### Muafiyet Yalnızca Yola Bakar

`waf_is_excluded()` sorgu dizesini **atar**, yalnızca yolu eşleştirir.

Tüm `REQUEST_URI`'ye bakmak güvenlik duvarının tamamının atlanmasıydı: deseni
herkes bir parametreye koyabilir. `api2` muafken

    /hesabim?x=1&foo=api2&q=' OR 1=1--

muafiyete takılıyor ve imza taraması, hız sınırı, IP listesi — hepsi
atlanıyordu.

**Sonuç:** bir beslemeyi sorgu parametresiyle (`?rss=true`) muaf tutamazsın,
çünkü o tam olarak saldırganın ekleyeceği dizedir. Muaf tutmak istiyorsan yolu
muaf tut.

### robots.txt Asla Engellenmez

`waf_path_is_always_served()`: `/robots.txt` ve `/.well-known/` bot
politikasından muaftır.

**robots.txt'i engellemek kendi kendini baltalar.** O dosya tarayıcıya neyi
taramamasını söyleyen dosyadır; vermezsen tarayıcı uyacağı kuralı öğrenemez ve
tahmin etmeye devam eder. Canlı bir sitenin logunda tam olarak bu görüldü:
AhrefsBot, SemrushBot ve DotBot `/robots.txt`'ten defalarca geri çevrildi, her
seferinde "git" denmesi fırsatı kaybedildi.

`.well-known/` ACME sertifika yenilemesi taşır. Engellenmesi gürültülü
başarısız olmaz — doksan gün sonra sertifika dolduğunda tüm site ile birlikte
başarısız olur.

Muafiyet **yalnızca bot politikasındandır**. IP kara listesi ve hız sınırı
uygulanmaya devam eder.

**Besleme okuyucuları iyi-bot listesindedir** (Feedly, Inoreader, Feedbin,
NewsBlur, Miniflux, Netvibes, The Old Reader, FeedFetcher). Her istekleri
abone olmuş gerçek bir kişiyi temsil eder; engellenirse besleme sessizce boşalır
ve kimse "beslemem durdu" diye haber vermez.

### Giden İstekler Kendini Tanıtır

**Pinegrap hiçbir giden cURL çağrısında `CURLOPT_USERAGENT` göndermiyordu.**
Lisans doğrulama, güncelleme kontrolü ve ağ geçidi çağrıları boş user-agent ile
gidiyordu; karşı taraftaki Pinegrap kurulumu bunları `bot-empty` sayıp
reddediyordu. **Yazılım kendini engelliyordu**, belirti ise "ana sunucuya
erişilemedi" hatasıydı.

`pinegrap_user_agent()` (waf.php) → `Pinegrap/<sürüm> (+<host>)`. İçinde
`bot`/`crawler`/istemci kütüphanesi token'ı **yoktur** — sıradan ziyaretçi
olarak sınıflanır. Bilerek iyi-bot listesine konmadı: aksi hâlde UA'ya
"Pinegrap" yazan herkes hız sınırından muaf olurdu.

Yeni bir giden çağrı eklerken `CURLOPT_USERAGENT` set et.

**Kritik kural — imza taraması `init` aşamasında kalmalı.** Sayfa bölgeleri sayfanın
kendi frontend URL'inden düzenlenir. Router aşamasında oturum yok; orada tarasaydık
`<script>` içeren bir bölgeyi kaydetmek saldırıdan ayırt edilemezdi.

**Fail-open zorunlu.** `waf_run()` gövdesi try/catch içinde; şema yoksa, regex hata
verirse, tablo erişilemezse istek **geçer**. Canlı mağazayı kıran güvenlik duvarı,
güvenlik duvarı olmamasından kötüdür.

### Skor kalibrasyonu (imza kuralları)

Eşik: `low`=15, `medium`=10 (varsayılan), `high`=6.

**Kural:** tek başına kesin olan kural **10** alır (tek başına engeller). Belirsiz
olan **4-6** alır (teyit ister). **7-9 ölü bölgedir** — ne engeller ne de güvenle
göz ardı edilir; oraya kural koyma. (`rce-ssti`=7 bilinçli istisna: Pinegrap Twig/Jinja
render etmez, bu bir keşif probu.)

`'targets'` anahtarı kuralı hedefe daraltır (`ARGS`/`BODY`/`COOKIE`/`REQUEST_URI`/
`REFERER`/`USER_AGENT`). `xss-jsuri` BODY'den muaftır: bir ürün yorumunda
"javascript:" geçmesi meşrudur, redirect parametresinde değildir.

### Bot sınıflandırma sırası — değiştirme

`attack tools → bad bots → good bots → generic → operatör listesi → artık sinyal`

Eski filtre izin listesini **önce** kontrol ediyordu; UA'ya "googlebot" eklemek
engellemeyi tamamen atlatıyordu. İzin listesi artık en sonda. `waf_good_bots()`
token'ları ile `waf_bad_bots()`/`waf_attack_tools()` token'ları **çakışmamalı**
(test bunu doğruluyor).

Artık sinyal regex'i token sınırlıdır (`(?:^|[^a-z0-9])bot(?:[^a-z0-9]|$)`) —
düz `bot` alt dizesi CUBOT telefonlarını engelliyordu.

### AI Botları — ranges doğrulaması (2026.4.4)

AI botları iki türdür ve **karıştırılmaz**: eğitim tarayıcıları (GPTBot,
ClaudeBot, CCBot, PerplexityBot) `waf_bad_bots()` içinde kalır; kullanıcı
getiricileri (ChatGPT-User, Claude-User, Perplexity-User) ve AI arama
dizinleyicileri (OAI-SearchBot, Claude-SearchBot) `waf_ai_bots()` üzerinden
izinlidir — rDNS yerine operatörün yayınladığı IP listesiyle doğrulanır
(`waf_bot_ranges` tablosu).

- **`waf_ai_bots()` token'ları HER ZAMAN doğrulanır** — anahtarlar kapalıyken
  ve operatör token'ı elle "İzin Verilen Botlar" alanına yazmışken bile.
  `waf_verify_bot()` ranges dalı `waf_good_bots()` lookup'ından **önce** gelir.
  Elle izin listesi bu token'lar için "doğrulamasız güven" değil "doğrulamalı
  izin"dir.
- **Fail-open merdiveni rDNS'ten farklı:** taze listede (≤30 gün) eşleşmeyen
  IP = `spoofed` (liste operatör beyanıyla tamdır); bayat listede eşleşmeyen =
  `unknown`; listede eşleşen her zaman `verified`; liste yoksa `unknown`.
- **`unknown` verdict'li AI token'ı iyi bot ayrıcalığı ALMAZ.**
  `waf_handle_bot()` onu `unverified` sınıfına çevirip bilinmeyen-bot
  politikasına düşürür — liste henüz çekilmemişken (taze yükseltme, çekim
  başarısız) sahte "ChatGPT-User" UA'sı salt metinle içeri giremez; davranış
  4.6 öncesiyle birebir aynı kalır. rDNS botlarında (`googlebot` vb.)
  `unknown` fail-open'dır, bu kural yalnız `waf_ai_bots()` token'larına özeldir.
- **waf.php'de HTTP yoktur.** Çekim yalnız `pg_waf_refresh_ai_ranges()`
  (functions.php): ayarlar düğmesi (force), pano WAF widget'ı (6 saatlik
  deneme damgasıyla), `waf_ranges_job.php` (cron kataloğu, günlük).
  **Başarısız/boş çekim eski satırı korur** — iyi listeyi ezmek tüm gerçek AI
  botlarını sahteciye çevirir.
- Anthropic üç botunu tek listede yayınlar (`anthropic-bots`): UA token'ı
  niyeti, IP kökeni kanıtlar. `claudebot` UA'sı IP listede olsa da engellenir.
- Yeni sağlayıcı eklerken: `waf_ai_bots()` + `pg_waf_ai_range_sources()` +
  token çakışma testi (bad/attack listeleriyle iki yönlü alt dize).

### Her Sayfa Bir HTML Belgesi Değildir

Bir Pinegrap sayfası, stili ve bölgeleri ne üretiyorsa odur. Operatör bilerek
JSON, XML, CSV ya da çıplak parça döndüren sayfalar yazıyor — dinamik bölgeyle
kurulmuş API uçları (`kodpen.com/api2`) bunun canlı örneği.

**Çıktının belge olduğunu varsayan hiçbir kural koyma.** `</head>`'e etiket
zorlamak, `<title>` beklemek, canonical aramak — hepsi bu varsayıma dayanır ve
o sayfalarda ya anlamsız bulgular üretir ya da çıktıyı bozar.

- `pg_seo_output_is_document()` (seo_structure.php) ilk 2 KB'a bakar; belge
  değilse yapı denetimi hiç çalışmaz. **Cömert olmalı**: yanlış "evet" zaten
  üretilecek bulguları üretir, yanlış "hayır" gerçek bir sayfayı skordan
  sessizce düşürür.
- 2 KB sınırı bilinçli — daha ileri okumak, JSON dizesinin ya da kod örneğinin
  içindeki markup'ı eşleştirmeye başlar.
- `page_type` bu soruyu **cevaplamaz**: aynı tipte iki sayfa, içine ne
  konduğuna göre biri belge biri JSON üretebilir.

**Bilinen risk:** robots splice'ı (`preg_replace('/<\/head>/i', ...)`, 2026.4.3)
aynı varsayımı taşıyor. `noindex` işaretli bir JSON sayfasının gövdesinde
`</head>` geçerse etiket JSON'un içine girer. Yeni bir splice yazarken önce
`pg_seo_output_is_document()` benzeri bir kapı koy.

### Eylem Bağlantısı `rel="nofollow"` Taşır

`send_to` (ya da benzeri bir dönüş adresi) taşıyan her ziyaretçi bağlantısı
**sayfa başına farklı bir URL** üretir. Tarayıcı bunları ayrı sayfa sanar ve
hepsini gezer — her tarayıcı ayrı ayrı.

Ölçüldü (2026-08-31): yorum için giriş bağlantısında `rel` yoktu ve giriş
sayfası **1,7 milyon görüntülemeyle** tüm sitenin en çok istenen sayfası oldu;
aramadan gelen gerçek trafik günde 100-200. Tarama bütçesi yazılara değil giriş
formuna gitti, ziyaretçi sayacı anlamsızlaştı, `visitors` ve
`submitted_form_views` şişti.

**WAF bunu çözemez ve çözmemeli:** Googlebot iyi bot olarak hız sınırından
muaftır, doğrusu da budur. Sorun engellememesi değil, onu davet etmemizdi.

Kural: giriş, kayıt, şifre sıfırlama, abonelik, sepetten kaldırma — kısaca
**eylem** olan her bağlantı `rel="nofollow"` alır. Dizinlenecek bir belge değil,
yapılacak bir iştir.

Sistem widget tarafında `rel` **sunucu damgalar** (`remove_from_cart` action
binding), tasarımcıya bırakılmaz: bir tarayıcının "bu ürünü kaldır" bağlantısını
takip edip edemeyeceği tasarım tercihi değildir.

Tamamlayıcı: giriş sayfasında `page.noindex` (2026.4.3) açılırsa sayfa
`robots.txt`'e de `Disallow` yazılır ve tarayıcı adresi hiç istemez. Ödünü aynı
dosyada "Bilinen ödün" başlığı altında.

### Gerçek IP — `waf_client_ip()`

`$_SERVER['REMOTE_ADDR']` Cloudflare arkasında **ziyaretçi değil edge sunucusudur**.
Proxy başlıkları yalnızca peer bilinen bir proxy ise kabul edilir (Cloudflare
aralıkları gömülü, gerisi `waf_trusted_proxies`). Aksi hâlde saldırgan başlığı
uydurup IP yasağını atlar.

`check_banned_ip_addresses()` ve `apps.php` artık bunu kullanır.

### Geriye dönük uyumluluk

- `waf_schema_ready()` `waf_config()`'ten **ayrıdır**. Yükseltme yapılmamış kurulumda
  config satırı yine okunur, çünkü `block_unknown_bots` orada yaşar ve bu dosyadan
  eskidir. İkisini birleştirmek, güncellemeyi alıp DB'yi yükseltmeyen operatörün
  mevcut bot filtresini sessizce kapatırdı.
- WAF kapalıyken bile `block_unknown_bots` kendi anahtarıyla çalışmaya devam eder.
- `settings.php` yalnızca `source='manual'` satırları yeniden yazar. Eski kod
  `TRUNCATE` ediyordu; artık bu, her ayar kaydında otomatik yasakları ve izin
  listesini silerdi.

---

## UI Kuralları

- Bootstrap 5 kullanılıyor
- `output_header([...])` / `output_footer()` → sayfa çerçevesi
- `$liveform->output_errors()` / `output_notices()` → bildirimler
- `$liveform->output_field([...])` → form alanı render
- `$liveform->add_notice(...)` / `add_error(...)` → mesaj ekle
- `$liveform->mark_error('field', 'mesaj')` → alan hatasını işaretle ve `go()` ile redirect
- `go($url)` → redirect (header location)
- İkon sistemi: **Bootstrap Icons** tercih edilir (`<i class="bi bi-icon-name"></i>`). Material Icons eski kodlarda hâlâ mevcut (`<span class="material-icons">icon_name</span>`) ancak yeni geliştirmelerde Bootstrap Icons kullanılmalı.
- Kartlar: `mb-5` ile aralıklı

### Yönetim Ekranı Tasarım Prensibi — referans: `view_folders.php`

Yeni ya da elden geçirilen her yönetim ekranı bu deseni izler. Referans
uygulama **`view_folders.php`** (Dosya Yöneticisi): ekranın yapabildiği her şey
**başlığın hemen altındaki TEK araç çubuğunda** toplanır, altında sade bir
içerik alanı kalır. (O ekrandaki önizleme/ağaç paneli dosya yöneticisine
özeldir, prensibin parçası değildir.)

Araç çubuğunun sırası ve dili:

| Yuva | Ne | Sınıf |
|---|---|---|
| 1 | Birincil eylem (çoğu zaman menü açar: "Yeni", "Kaydet") | `btn btn-sm btn-primary rounded-pill px-3` |
| 2 | İkincil araçlar, gerekirse tek menüde toplanmış | `btn btn-sm btn-ghost` |
| 3 | Konum / breadcrumb — esner (`flex: 1 1 220px`) | — |
| 4 | Arama / süzme | `input-group input-group-sm rounded-pill` |
| 5 | Görünüm değiştirici (varsa) | `btn-group btn-group-sm` + `btn-ghost.active` |
| 6 | Taşma menüsü `⋮` — nadir ve ekrana özel olan her şey | `btn btn-sm btn-ghost` + `dropdown-menu-end` |

Kurallar:

- **Niyet başına tek reçete.** Aynı işi yapan iki düğme aynı görünür:
  ilgili ekrana git / yerinde araç → `btn-sm btn-outline-secondary`;
  geri alınamaz iş → `btn-sm btn-outline-warning`; birincil → `btn-primary`.
  Tek listede üç ayrı ağırlık (dolu + outline + `btn-lg`) karışıklıktır.
- **Dört düğmeden fazlası menüye.** Yan yana duran ve çoğu zaman ilgisiz olan
  düğmeler yerine tek bir menü; `view_folders`'ta "aktarım" menüsünün gerekçesi
  budur: nerede durduğuna göre değişen dört düğme, çubukta sürekli soluk durur.
- **İkon her zaman `<i class="bi …"></i>` içinde.** Düğmenin `class`'ına
  `bi bi-x` yazma; `<span class="bi …">` de kullanma.
- **Menü satırlarında renk kullanma — kural YALNIZ menülere aittir.** Menüde
  renk zaten başka bir şey söylüyor: dosya yöneticisinde klasörün erişim
  durumu ve türü renkle anlatıldığı için, sarı bir menü satırı "dikkat" değil
  *"bu klasörlerle ilgili"* diye okunur. Bu yüzden menüde her satır aynı
  renktedir (`link-body-emphasis`); tehlikeyi ikon, onay diyaloğu ve grup
  ayracı anlatır.

  Yazılımın genelinde böyle bir kısıt **yoktur**: düğmelerde
  (`btn-outline-warning`), rozetlerde, uyarı kutularında renk her zamanki gibi
  önem ve durum anlatır. Bunu menü dışına taşıma — örneğin sayfa içindeki
  `alert-warning`ın klasörlerle hiçbir ilgisi yoktur, orada sarı yalnızca
  "dikkat" demektir. Çakışma yalnız menüde doğuyor, çünkü menü satırları
  klasör listeleriyle aynı görsel dili paylaşıyor.
- **Sınıflar paylaşılan dosyada, sayfada değil.** Çubuğun iskeleti
  `assets/css/backend.src.css` sonundaki *Admin screen toolbar* bloğunda:
  `.pg-toolbar`, `.pg-toolbar-grow`, `.pg-toolbar-search`, `.btn-ghost`
  (+ `.btn-ghost.active`) ve dar ekran kuralları. Yeni ekran bu sınıfları
  kullanır; kendi `#id`'sine yeni bir çubuk CSS'i yazmaz. `view_folders.php`
  hâlâ kendi `#explorer_toolbar` kopyasını taşıyor (çalışıyor, dokunulmadı) —
  o ekrana elleneceği ilk fırsatta paylaşılan sınıflara geçirilmeli.
- **Ghost varyantı** (`btn-ghost`) yalnız Bootstrap'in kendi düğme
  değişkenleriyle tanımlıdır; açık/koyu tema bu yüzden ikinci bir kural
  gerektirmez.
- **Kart başlığı deseni:** `card-header` = `d-flex justify-content-between
  align-items-center`, solda `text-uppercase h5 text-primary fw-bold mb-0`
  başlık, sağda o karta ait tek bağlantı düğmesi. Kartın içine kartla aynı adı
  taşıyan ikinci bir başlık koyma.
- **Dar ekran:** çubuk satırlara ayrılır — kontroller ilk satırı kendine alır,
  breadcrumb ve arama tam genişlikte alt satırlara iner (`order` + `flex-basis`
  ile; `view_folders.php`'deki `@media (max-width: 767.98px)` bloğu örnektir).

Site Ayarları bu prensibe 2026-09-12'de taşındı, sekiz kategoriye bölündü ve
sayfa olmaktan çıkıp modala girdi — aşağıdaki "Site Ayarları" bölümüne bak.

---

### Site Ayarları — Her Ekranın Üstünde Açılan Modal (2026-09-12)

Tek ekran 5219 satırdı ve tek `<form>` her kaydetmede **247 sütunu birden**
yazıyordu. Önce sekiz kategoriye bölündü, sonra kategoriler ekran olmaktan
çıkıp bir modalın içine girdi: **ayar neredeyse her zaman başka bir işin
ortasında istenir**, ve bir sayfaya gitmek o işi bırakmak demektir.

```
Modal (asıl yol)
  includes/settings/modal.php      kabuk: başlıktaki düğme + boş diyalog + extras kutusu
  settings_pane.php                uç nokta: GET ?pane=<kat> kartlar, POST kaydeder, ikisi de JSON
  includes/settings/fragment.php   pg_settings_fragment() / pg_settings_apply()
  assets/js/backend.src.js         pgSettingsModal, window.pgOpenSettings(), pgInitInjected()

Sayfa (JavaScript'siz yol; panelden bağlanmaz)
  settings_<kategori>.php          8 ince sarmalayıcı: init + PG_SETTINGS_KEY + screen.php
  includes/settings/screen.php     sayfa akışı: kabuk, araç çubuğu, kaydet çubuğu, POST/redirect
  settings2.php                    hub (kutucuklar); eski #pgset- adreslerini karşılar
  settings.php                     ESKİ tek ekran; öksüz, Temizlik listesinde

Ortak
  includes/settings/registry.php   TEK liste: kategoriler, bölümler, anahtar kelimeler, ilgili ekranlar
  includes/settings/prep.php       GET hazırlığı (her kategori tamamını koşturur)
  includes/settings/<kat>.php      kartlar → $pg_settings_cards (+ $pg_settings_modals, $pg_settings_scripts)
  includes/settings/<kat>.save.php o kategorinin yazdıkları
```

Kategoriler ve kartları (27 bölüm):

| Pane | Kartlar |
|---|---|
| `general` | server · software · channel · datetime · cron |
| `appearance` | theme · editor |
| `features` | features · images |
| `seo` | seo · analytics |
| `contact` | campaigns · chat · mailchimp |
| `security` | session · device · signin · membership |
| `firewall` | waf · bots · iplists |
| `commerce` | store · shipping · giftcards · invoice · payments · affiliate |

**Kart taşımak iki satırdır:** `registry.php`'deki `sections` girdisi ve kart
bloğunun `includes/settings/<key>.php` dosyaları arasında taşınması. Kolon
sahipliği kartı izler — kartı taşıyan, onun yazdığı kolonları da
`<key>.save.php` dosyaları arasında taşır, yoksa kolon sahipsiz kalır ve hiç
yazılmaz.

#### Biçim her genişlikte aynı

Sol tarafta liste, yanında tek pane, ve **yalnız pane kaydırılır**. Üç boy:

| Genişlik | Diyalog | Liste |
|---|---|---|
| ≥ 992px | yüzer, kenarlarda arkadaki ekran görünür | solda, 15rem |
| 576–992px | tüm ekran | solda, 12rem |
| < 576px | tüm ekran | pane'in üstüne kayan çekmece |

- **Kaydırma zincirindeki her flex öğesi `min-height: 0` taşır.** Bir flex
  öğesi ANA eksende içeriğinin altına inmez; sütun yönünde bu, `.pg-sm-main`'in
  bütün kartlarının yüksekliğine büyümesi, pane'in hiç kaydırılamaması ve
  kaydet çubuğunun ekranın bin piksel altında kalması demektir. Telefonda
  ölçülen buydu: 812 piksellik diyalogda 1921 piksellik içerik, Kaydet
  ulaşılamaz. Satır yönünde (masaüstü) `min-height: auto` ana eksen olmadığı
  için zarar vermiyordu — yani hata yalnız dar ekranda görünüyordu.
- **Diyalog yüksekliği Bootstrap'in kendi kenar boşluğuyla tutarlı olmak
  zorunda.** `--bs-modal-margin` ≥576px'te 1.75rem'dir; içerik `100vh - 3rem`
  olunca toplam pencereden 8 piksel taşar, modal kendi içinde kaydırılır ve
  diyalog ekranın üstünden çıkar. Değer `3.5rem`.
- **`100dvh`, `100vh`'den sonra ve `@supports` içinde yazılır.** Telefon
  tarayıcısının araç çubuğu kayarken gizlenir ve `100vh` uzun olanı tutar;
  `dvh` canlı ölçüdür, bilmeyen tarayıcı `vh` ile kalır.
- **Telefonda `modal-fullscreen-lg-down` tek başına yetmez.** Diyalogun
  `max-width: min(1180px, calc(100vw - 3rem))` kuralı Bootstrap'in
  `max-width: none`'ını yener ve tam ekran olması gereken kutunun kenarında
  arkadaki ekran görünür; ≤991.98px dalında `max-width: none` yazılır.
- **Çekmece `visibility`'yi süreli geçişe koymaz.** `visibility` ayrık bir
  özelliktir: süre verilirse değişim yolun ortasında olur ve geçiş hiç
  koşmazsa çekmece erişilemez kalır. Açılışta anında, kapanışta kaymanın
  sonunda (`visibility 0s linear .18s`). Kapalı çekmece `visibility: hidden`
  olmak zorunda — ekran dışındaki bir liste yoksa odak alır ve okunur.
#### Ayarlar bir yer değil

- **Arayüz turunda kendi adımı var** (`pg_tour_shell_steps()`, `at => 'settings'`,
  hedef `#pg_settings_open`). Çerçeve değiştiği için `PG_TOUR_SHELL_KEY`
  `shell.3`'e çıkarıldı — turu herkes bir kez daha izler; menü adımlarındaki
  "bir de ayarlar" cümlesi de düştü, çünkü orada artık yok.
- **Sol menüde girdisi yok.** Düğme başlıkta, bildirim zilinin yanında
  (`pg_settings_modal_button()`), `$menu_items` 20. yuvası bilerek boş — yuva
  numaraları kaydırılırsa komşu girdilerin yerleri değişir.
- **Düğme ve diyalog `output_header()` içinde basılır, footer'da değil.**
  Görsel sayfa editörü (`includes/designer_screen.php`) ve `page_designer.php`
  footer'ı çağırmıyor; footer'da basılan bir diyalog tam olarak en çok işe
  yarayacağı ekranlarda yok olur.
- **Toolbar hariç** (`$toolbar != true`): o başlık düzenlenen sitenin
  çerçevesindeki ince şerit, görüntü boyutunda bir diyalogun orada yeri yok.
  `output_header_secure()` ekranları (seçiciler) hiç almaz.
- **Her yerden açılır:** `window.pgOpenSettings('firewall', 'pgset-waf')`.
- **Kanca adresin kendisidir.** Ekmek kırıntısı, araç çubuğu düğmesi ve pano
  widget'ı tek bir `<a href>` basan yardımcılardan geliyor ve o yardımcılar
  öznitelik geçirmiyor — yani "her bağlantıya `data-pg-settings` ekle" diye
  bir kural tutulamaz. `pgSettingsModal.fromUrl()` dört biçimi tanır:
  `#settings[/<pane>[/<kart>]]`, `settings_<pane>.php[#<kart>]`,
  `settings2.php` / `settings.php` (son kullanılan pane) ve eski `#pgset-…` /
  `#pgsub-…` çıpaları. Öznitelik yalnız kendi adresi olmayan kontrol için.
- **`href` gerçek bir ekran olarak kalır.** Diyalog basılmayan yerde, orta
  tıkta ve yeni sekmede bağlantının yapacağı iş odur. Adresi elle yazma:
  `pg_settings_link($pane, $section)` (`includes/fn/output.php`).
- **Eski tek ekran öksüz ve `clean_up.php` listesinde.** Kodun hiçbir yerinden
  ona bağlantı, yönlendirme ya da include yok — kalan `settings.php` geçişleri
  yorum. Silinmesi operatörün kararı: dosya listede duruyor, Temizlik ekranını
  çalıştıran siler. Silindikten sonra da panel içindeki eski bağlantılar
  çalışmaya devam eder (çözümleyici onları tanıyıp doğru pane'i açıyor);
  kaybolan tek şey adres çubuğuna elle yazılan bir yer imi.
- **Hub ekranı yok.** `settings2.php` silindi: kutucuk ekranının işini
  diyaloğun kenar çubuğu yapıyor ve sayfa yolundan geriye sekiz kategori
  ekranı kalıyor — üstlerinde bir şey yok. `pg_settings_link()` kategorisiz
  çağrıldığında **ilk kategorinin** sayfasını döner (`registry.php` sırası),
  diyalog da o kategoride açılır; "en son baktığım yer" başlıktaki dişlinin
  işidir. `pg_settings_hub_url()` kaldırıldı. Eski `settings2.php` bağlantıları
  çözümleyicide karşılanmaya devam ediyor.
- **Tanımadığı adrese dokunmaz.** Pane sidebar satırlarına karşı doğrulanır, ve
  şema taşıyan adres yalnız bu köken ise kabul edilir; `settings_pane.php`,
  `api_settings.php`, başka bir host'un `settings.php`'si normal gezinir.
- **Kart taşındıysa çıpa kazanır, dosya adı değil.** `settings_security.php#pgset-waf`
  diyaloğu Güvenlik Duvarı'nda açar, çünkü `pg_settings_legacy_anchors()` kartın
  bugün nerede olduğunu söylüyor. Bir kartı başka kategoriye taşırken haritaya
  satırı yaz; yazmazsan o karta giden her eski bağlantı yanlış pane'e düşer.
- **Hiçbir ekmek kırıntısı ayarları üstüne almaz.** Kırıntı bir konum
  iddiasıdır: "buradasın, üstünde şu var, tıkla çık." Ayarlar artık bir yer
  değil, ekranın üstünde açılan bir kutu — o kırıntıya basmak hiçbir yere
  gitmez, bulunduğun sayfanın üstüne diyalog açar ve bozuk bağlantı gibi
  okunur. `settings_<kat>.php`, site günlüğü, oturumlar, uygulama erişimi,
  pazaryerleri, yapılandırma ve bul-değiştir ekranlarından kaldırıldı; geriye
  kalan tek kırıntı gerçek atadır (oturumlar tek hesaba daraltılmışken "Tüm
  Oturumlar"). **Geri düğmesi de gitti**: ok başlıkta "yukarı" gibi duruyor
  ama yukarısı yok — bastığında zaten üstünde olduğun sayfaya bir diyalog
  açıyor. O ekranlarda `.pg-breadcrumb` şeridi hiç basılmıyor; ayarlara tek
  açık kapı başlıktaki dişli, ve o her ekranda.
- **Bağlantı olmayan kontrol `pgSettingsGo(url)` çağırır.** Başlıktaki geri
  düğmesi `onclick` taşıyan bir `<button>`; devredilmiş dinleyici onu hiç
  görmez. `pg_page_shell`'in `cancel['url']`'ü bu yüzden oradan geçer —
  `cancel['onclick']` ile elle `window.location` yazan ekran diyaloğu atlar.
- **Perde de aynı soruyu sorar.** `pg_preloader_markup()`'ın tıklama dinleyicisi
  belgede ve **yakalama** aşamasında, yani bizim devredilmiş dinleyicimizden
  önce koşar: sorması olmasa hiçbir yere gitmeyen bir sayfanın üstüne tam ekran
  perde indirir. Kendi listesini tutmaz, `pgSettingsModal.asked(a)` sorar.
- **Bootstrap modalı içindeki bağlantı devri kendi yapar.** Komut paletinin
  ayar çipi `stopPropagation()` eder ve diyaloğu arama kutusunun
  `hidden.bs.modal`'ında açar: iki modal aynı anda açılırsa geride ikinci bir
  perde ve kaydırma çubuğunu geri alamayan bir gövde kalır.
- **Başka ekranda biten araç `pg_settings_return_url()` ile döner.** Dönülecek
  bir ayar ekranı yok; adres `welcome.php#settings/<pane>/<kart>` olur ve
  diyalog varışta kendini açar (`purge_cache`, `clean_up`, `cloudflare`,
  `software_update`, `view_parasut_inbox`).
- **Araç formda bıraktığı cümleyi kaybetmez.** O araçlar `liveform('settings')`
  üstüne `add_notice()` / `mark_error()` yazıp yönlendiriyor ve okuyacak ekran
  kalmadı: uç nokta GET dalında `get_notices()` + `get_errors()` ile devralır,
  `remove_form()` ile düşürür, diyalog ilk pane'de gösterir. **Tam bir kez** —
  devralmadan önce silmek cümleyi yok eder, silmeden devralmak onu her açılışta
  geri getirir.

#### Sonradan gelen işaretleme

- **Pane `.html()` ile basılır, `innerHTML` ile değil.** Kartların içindeki
  satır içi `<script>`'ler CodeMirror'ı ve etiket alanlarını (`tagin`) kuruyor;
  jQuery `.html()` enjekte ettiği betiği çalıştırır, `innerHTML` çalıştırmaz.
  Bu bir tercih değil.
- **`$pg_settings_scripts` pane'in içine eklenir.** jQuery bir betiği yalnız
  içine konduğu düğüm belgeye bağlıyken koşturur; kopmuş bir `<div>`'e append
  etmek onu sessizce hiç çalıştırmaz.
- **Kapsam alan başlatıcı tek yerde durur.** `pgBindTitlePopovers(scope)` ve
  `pgSyncCollapseSwitchers(scope)` ready bloğundan çıkarıldı; ready bloğu
  onları `document` üzerinde, `pgInitInjected(scope)` pane üzerinde çağırır.
  Ready bloğuna kapsam almayan yeni bir başlatıcı yazmak, AJAX ile gelen
  işaretlemeyi **sessizce** kurulmamış bırakır (42 `collapse-switcher` yanlış
  durumda açılır, konsolda hiçbir şey yazmaz).
- **`collapse-switcher` ve `collapse-if-selected` dinleyicileri devredilmiştir**
  (`$(document).on(...)`). Doğrudan bağlanan bir dinleyici sonradan gelen
  kontrolü görmez.
- **CodeMirror'ın kirliliği kendi `change` olayından gelir.** Kendi belgesini
  tutar, textarea'ya olay üretmez; yalnız `:input` dinlemek Kaydet düğmesini
  kapalı bırakır. Kaydetmeden önce her `.CodeMirror` örneğinde `save()` çağrılır,
  yoksa `serialize()` pane'in geldiği değeri gönderir.
- **"Kaydedilmemiş değişiklik" bir olay değil, iki sorudur.** Tarayıcının
  autofill'i ve parola yöneticisi pane çizildikten sonra alanlara yazıyor ve
  tuş vuruşunun ürettiği **güvenilir** (`isTrusted`) `input`/`change`
  olaylarının aynısını üretiyor — olaya bakarak kimin yazdığı anlaşılmaz.
  Tek başına olayı dinlemek pane'i daha açılırken "kaydedilmemiş" yapıyor,
  kategori değiştirmek ve kutuyu kapatmak da kimsenin sebep olmadığı bir soru
  soruyordu. Ölçüt: **(1)** diyalog içinde değer değiştirebilecek bir şeye
  dokunulmuş olacak (`:input` / `label` / `.CodeMirror` / extras kutusu
  üzerinde `pointerdown`, ya da bir kontrolde `keydown`/`paste`/`drop`) **ve**
  **(2)** `snapshot()` pane'in geldiği değerden farklı olacak. Kaydırmak ve
  kart başlığına tıklamak birinciyi geçmez; yazıp geri almak ikinciyi geçmez.
  `snapshot()` her CodeMirror'ı textarea'sına yazıp `serialize()` eder ve
  karşılaştırma 120 ms geciktirilir (kod alanı büyük olabilir).
- **Taban değer `paint()` içinde, bir kare sonra değil alınır.** Araya giren
  bir doldurma taban olurdu ve sonraki kayıt onu kimse seçmemişken saklardı.
- **Kimsenin seçmediği değer geri konur.** Kaspersky gibi kimlik dolduran bir
  parola yöneticisi `autocomplete`'i de vendor opt-out özniteliklerini de yok
  sayıyor (Kuruluş Adresi 2 sahada böyle dolduruldu). Yalnız "kirli sayma"
  demek yetmez — değer ekranda kalır ve **bir sonraki kayıtta veritabanına
  girer**, üstelik artık sessizce. `hold()` pane geldiğinde her **görünür**
  kontrolün değerini ayrı ayrı tutar; `review()` sahipsiz bir değişikliği
  eski değerine geri yazar ve pane başına bir kez `say('filled')` ile söyler.
  Gizli kontroller dışarıda: jeton, etiket alanlarının gizli girdisi ve
  CodeMirror'ın textarea'sı bu yazılımın kendi yazdıkları, geri koymak kendi
  kendimizle dövüşmek olurdu. Sonradan görünür olan kontrol (operatörün açtığı
  blok) her review'da o anki değeriyle listeye alınır — kapalıyken zaten
  doldurulamazdı.
- **Sahiplik saatle belirlenir: son 1200 ms içinde bir el hareketi.** Hangi
  kontrole basıldığına bakılmaz, çünkü çoğu zaman başka bir kontroldür —
  operatör "Üret"e basar, değer hiç dokunmadığı alanda belirir. `pointerdown`
  ve `keydown` **belgede, yakalama aşamasında** dinlenir: kartın kendi
  diyaloğu ayarlar diyaloğunun **yanındaki** kutuda, açtığı dosya seçici daha
  da başka yerde, ama ikisindeki basış da bu pane üzerinde çalışmaktır.
- **El hareketi kendi review'unu ister.** Bir kart betiği alana yazarken olay
  üretmiyor; bu satır olmadan üretilen anahtar Kaydet'i açmıyor (eskiden de
  açmıyordu) ve bir sonraki review onu kimse istememiş gibi geri koyuyordu.
- **`pg_settings_stamp_autocomplete()` vendor opt-out'larını da basar**
  (`data-lpignore`, `data-1p-ignore`, `data-bwignore`, `data-form-type`).
  Bilmediği özniteliği yok sayan yönetici için zararsız; bunlar savunmanın
  nazik yarısı, işi bitiren geri koymadır.
- **Doldurucu hiç olay üretmeyebilir.** Kaspersky değeri kontrolün üstüne
  doğrudan yazıyor — `input` yok, `change` yok — yani olaya bağlı her dinleyici
  uyuyor. Diyalog açıkken `patrol()` **600 ms'de bir** yoklar; yoklama görünür
  kontrol başına bir dizge karşılaştırması, pahalı yarı (kod alanlarını
  textarea'ya itip formu serileştirmek) yalnız gerçekten bir şey bulan tıkta
  koşar. Operatör çalışırken (son 1200 ms içinde el hareketi) yoklama çekilir;
  o sırada zaten `review()` sürüyor.
- **Üç kez geri konan alan bırakılır, ama sunucuya gitmez.** Geri koydukça
  yeniden yazan bir yönetici diyalog açık kaldığı sürece titrer; üçüncüden
  sonra değer ekranda bırakılır ve `save()` serileştirmeden **hemen önce** onu
  tutulan değerine döndürür. Ekranda ne göründüğü tartışılır, veritabanına ne
  girdiği tartışılmaz.
- **Metin alanları kilitli gelir** (`latch()` / `unlatch()`): JavaScript her
  görünür `input`/`textarea`'ya `readonly` + `data-pgro` koyar, ilk
  `pointerdown` / `keydown` / `focusin` (belgede, **yakalama** aşamasında —
  maskeler ve saat seçici odakta bağlanıyor) kilidi açar. Autofill ve parola
  yöneticileri `readonly` alanı atlar; ayarlarda kimseye doldurulması gereken
  bir alan yok. **Kilit sunucudan değil betikten gelir**: betik çalışmazsa alan
  hiç kilitlenmemiştir ve her zamanki gibi yazılır — tersi, betik bozulduğu gün
  formu kilitlerdi. Onay kutusu / radyo / select `readonly`'yi zaten yok sayar,
  atlanır; ekranın kendi kilitlediği alan işaretsizdir ve öyle kalır.
  Bootstrap `[readonly]`'yi griye boyadığı için `[data-pgro]` rengini geri alır
  — kapalı bir alan, devre dışı bir alan gibi görünmemeli.

#### Kartın kendi diyaloğu

- **Diyaloglar pane'e değil `#pg_settings_modal_extras`'a konur** — ayarlar
  diyalogundan **sonra** gelen kardeş kutu. İç içe modal dıştakinin perdesinin
  arkasında kalır; ikisi de `z-index: 1055` olduğundan sıralamayı DOM sırası
  belirler.
- **Bootstrap'in modal tetikleyicisi kartlarda yeniden adlandırılır**
  (`pg_settings_own_dialog_triggers()`: `data-bs-toggle="modal"` →
  `data-pgdialog="modal"`). Bootstrap'in `data-api`'si yeni modalı göstermeden
  önce açık olanı kapatır ve **önlenemez**: devredilmiş Bootstrap dinleyicileri
  belgeye **yakalama aşamasında** bağlıdır, belgenin içindeki hiçbir dinleyici
  onun önüne geçemez, `stopPropagation()` çok geç çalışır. Tek çıkış, aradığı
  özniteliğin orada olmaması.
- **Diyalogların kendisi dönüştürülmez:** içlerindeki `data-bs-dismiss`
  Bootstrap'in işi ve bulunduğu diyaloğu bulur; `screen.php`'nin sayfa akışı da
  kartları olduğu gibi basar, orada dıştaki modal yok ve data-api doğru olan.
- **İç diyalog kapanırken gövdeden `modal-open` sınıfını alıp gider.**
  Bootstrap onu tek modal varmış gibi yönetir; dıştaki hâlâ açıktır ve
  arkasındaki ekran yeniden kaydırılmaya başlar. `hidden.bs.modal` üzerinde geri
  konur.
- **Pane değişirken ve diyalog kapanırken extras `dispose()` edilir**, yalnız
  boşaltılmaz: altından öğesi çekilen bir Bootstrap örneği perdesini sayfada
  bırakır ve onu kapatacak hiçbir şey kalmaz.

#### Uç nokta

- **Cevap her zaman JSON, hata da.** `pg_error_throws(true)`
  (`includes/fn/core.php`) `output_error()`'u istisna fırlatacak hâle getirir
  (`pg_seo_rendering()` bayrağının ikizi); uç nokta kart üretimini ve kaydı
  `try`/`catch` içinde çağırır. Mesaj `pg_settings_pane_complaint()`'ten geçer —
  `output_error()` ekran için yazar ve "geri dön" bağlantısı taşıyabilir.
- **Kapı uçta:** `validate_user()` + `validate_area_access($user, 'manager')`.
  Çizilmeyen bir düğme yetki kontrolü değildir.
- **CSRF elle karşılaştırılır** (`hash_equals`) — `validate_token_field()` ekran
  basar. Jeton her pane cevabında yenilenir: diyalog açık kalırken başka bir
  sekme onu harcayabilir. 403 `expired: true` taşır ve tarayıcı sayfayı
  yeniler; diyaloğun arkasındaki ekran da o noktada bayattır.
- **`liveform` formu her iki dalda da bırakılır** (`remove_form()`).
  `get_warnings()` / `output_notices()` oturumdan okur, silmez: pane çizildikten
  sonra bırakılmazsa aynı bildirim her açılışta geri gelir.
- **Kaydetmeden sonra pane yeniden okunur.** Kayıt normalleştirir (liste
  virgülle birleşir, sıfır sınır bire çıkar); operatör formda yazdığını değil
  saklananı görmelidir.
- **Güvenli Mod'u açan kayıt şema değiştirir.** Cevap yeni şemadaki adresi
  taşır (`reload`) ve tarayıcı oraya taşınır; eski şemaya yapılacak her istek
  karışık içerik olarak bloklanır. Güvenli Mod Güvenlik Duvarı panelindedir
  (HTTPS ve Güvenlik Başlıkları kartı); `$url_scheme`'i `firewall.save.php`
  üretir, `fragment.php` onu okur. `general.save.php` `url_scheme` **yazmaz**
  — yazsaydı Genel'i kaydetmek Güvenli Mod'u kapatırdı.
- **Formda `data-pg-no-curtain` var.** `pg_preloader_markup()`'ın `submit`
  dinleyicisi belgede ve yakalama aşamasında, formun submit'i iptal etmesinden
  önce koşar; bir metin alanında Enter tam ekran perdeyi hiçbir yere gitmeyen
  bir sayfanın üstüne indirir.

#### Kartlar ve kayıt

- **Bir kategori yalnız kendi sütunlarını yazar.** Yeni bir ayar eklerken
  kontrolü `<kat>.php`'ye, yazmayı `<kat>.save.php`'ye koy. Başka bir
  kategorinin sütununu yazma — "her kayıt her sütunu yazar" davranışı iki
  üretim hatası üretti (ortaklık alanları ve barkod etiket şablonu her kayıtta
  siliniyordu).
- **`$sql_*` parçası kurulduğu dosyada splice edilir.** Parçayı bir dosyada
  kurup başka bir dosyanın `UPDATE`'ine koymak sessizce boş yazar
  (`software_language` ile bir kez yaşandı).
- **Kontrolü olmayan sütuna yazma.** `post_value()` boş döner ve sütun
  sıfırlanır. Sütunun sahibi başka bir ekransa (barkod şablonu → etiket
  düzenleyici) Ayarlar ona hiç dokunmaz.
- **`prep.php` içinde `$row` config satırıdır ve öyle kalmalıdır.** Kartlar onu
  o adla okuyor; ikinci bir sorgunun sonucunu `$row`'a yazmak — sıradaki sipariş
  numarası, pem dosyaları, para birimleri döngüsü böyle yapıyordu — dosyanın
  kalanında `$row`'u `false` bırakır. `empty($row['x'])` bir boole üstünde hata
  vermez, sessizce "yok" der: saklanmış CDN sağlayıcısı bu yüzden hiç
  uygulanmıyordu. Yeni sorgu kendi değişkenine yazar.
- **Her kart açtığı her `<div>`'i kapatmak zorunda** ve bu gözle görülmez. Bir
  kartı daha büyük bir kartın içinden kesip çıkarmak sarmalayıcı `div`'i arkada
  bırakır; kart bir `div` sızdırdığında **kendinden sonraki bütün kartları
  yutar**. Belirti "kartlar birbirine yapışmış" + "kaydet düğmesi asılı kalıyor";
  ikisi de kartın kendisini göstermez. `<div` / `</div>` sayısını kart başına
  karşılaştır.
- **Bölüm listesi `registry.php`'de.** Kart id'si = bölüm id'si = çipin hedefi =
  `#hash`. Kart ekleyip kaydı güncellememek çipi var olmayan bir çıpaya bağlar.
  Çipler pane'de **gerçekten bulunan** kartlardan kurulur: koşullu bir kart
  (görsel ayarları) her kurulumda çizilmez.
- **Kenar çubuğu, komut paleti ve hub aynı kayıttan beslenir**
  (`pg_settings_categories()` / `pg_settings_tool_groups($user)`). İkinci bir
  liste yazma.
- **Listenin kendi yüzeyi var** (`--bs-secondary-bg`), `--pg-tint` değil.
  O jeton %5 beyaz bir yıkamadır; temanın en koyu rengi üstünde görünmez ve
  kenar çubuğu "arka planı yok" diye okunur — diyalog da olması gereken
  biçimi kaybeder. Etkin satır `--bs-body-bg` ile pane'in rengine döner, yani
  listeden pane'e açılmış bir oyuk gibi durur; ikisinin birbirine ait olduğunu
  söyleyen şey bu.
- **Kenar çubuğunda arama kutusu yok.** Sekiz satırın hepsi ekrandayken
  yazmayı istemek, kısayol değil fazladan adımdır. `data-pgsearch` ve
  `filter()` onunla birlikte kaldırıldı; `registry.php`'deki `keywords` durur
  — onu komut paleti okuyor.
- **Kapalı özelliğin kartı gizlenmez.** Kontroller DOM'da kalır, yoksa o
  kategorinin kaydı görünmeyen ayarları boşaltır. Ticaret kapalıyken Mağaza
  kartının başındaki not bunu söyler.
- **Ortak `prep.php` bilerek bölünmedi.** 581 ifadeyi sahiplik grafiğiyle
  dağıtmak denendi; 82'si tek sahibe bağlanamadı (`.=` ile döngüde kurulan
  `$output_*` parçaları) ve yanlış dosyaya düşen hazırlık değişkeni **boş bir
  alan** olarak, hata vermeden çıkar. Yeni hazırlık kodu `prep.php`'ye yazılır.
- **Kartlar tam genişlikte üst üste durur**, iki sütun değil: `.pg-set-card`
  bir CSS container'dır.
- **Bir ayar bir satırdır: solda ne olduğu, sağda ne olduğu.** Kart bir
  kararlar listesi gibi okunmalı, o yüzden öyle çiziliyor — adı ve açıklaması
  solda, onu ayarlayan kontrol sağda, iki karar arasında bir çizgi.
  **Kartlar yeniden yazılmadı**: kural stylesheet'te ve **şekle** göre devreye
  giriyor — bir etiket + sıradan bir kontrol taşıyan hücre, ya da tek bir onay
  kutusu/anahtar taşıyan hücre. Textarea, kod alanı, etiket alanı, tablo, düğme
  grubu, iç içe satır: hiçbiri dokunulmuyor, çünkü onlar tam genişliği zaten
  istiyor. **Radyo grupları da dışarıda** — her seçeneği kartın iki ucuna
  dağıtmak grubu grup olmaktan çıkarır.
- **Ölçüt `:has()`, ve bu bilinçli.** Desteklemeyen tarayıcı eski yığılmış
  düzeni görür; yarısı uygulanmış bir düzen değil. Bir stylesheet'te eksik
  özelliğin doğru davranışı budur: ekran çalışmaya devam eder, yalnız daha sade
  olur.
- **Çizgi satırların ARASINA çizilir, altına değil** (`* + <yüklem>` üstünde
  `border-top`). "Sonuncunun altına çizme" bir stylesheet'in soramayacağı
  soruyu sorar — *görünen* son satırı: bir bloğu açan anahtar, blok kapalıyken
  de onu sayfada tutuyor, dolayısıyla ekrandaki son satır neredeyse hiçbir zaman
  `:last-child` değildir. Google ile Giriş kartı böyle, tek ayarının altında
  asılı bir çizgiyle duruyordu.
- **Adı ve kontrolü aynı satırı paylaşır, açıklama ikisinin altına iner.**
  Izgara `"pgname pgctrl" / "pgnote pgnote"`. Kontrol önce iki satırı birden
  kaplıyordu — gerekçe "ad ve açıklamanın ortasına gelsin" idi — ve **kartı
  eğri gösteren şey buydu**: bir satırı aşan öğe yüksekliğini kapladığı izlere
  dağıtır, dolayısıyla boş bir açıklama satırı bile kontrolün yüksekliğinden
  pay alır ve adı ortadan yukarı kaldırır. Sekiz panelde ölçüldü: düz
  alanların hepsi 7px, giriş grupları 19px, simge seçici 28px — ve **miktar
  kontrole göre değiştiği için** basitçe kaymış değil, eğri duruyordu. Tek
  satırı paylaşınca o satır hangisi uzunsa onun boyunda olur ve ikisi de
  içinde ortalanır; kontrolün yüksekliği ne olursa olsun.
- **Kart tek bir dikey ritim taşır** (`--bs-gutter-y: 0` + her hücreye aynı
  `padding-bottom`, satırın altından `margin-bottom` ile geri alınır).
  Bootstrap'in oluk modeli her hücreye üst kenar boşluğu verip satırın
  kendisini aynı kadar yukarı çeker; dönüşen hücre oluğu bırakınca o negatif
  yarı karşılıksız kalıyor ve kartın 1rem'lik gövde dolgusu yok oluyordu —
  ölçüldü: sekiz panelin **her kartında** başlıkla ilk ayar arasında 0 piksel.
  Karışım ayrıca aynı karta, sıradaki hücrenin türüne göre iki farklı boşluk
  veriyordu.
- **`:has()` içinde `:has()` YAZILAMAZ.** "İçinde dönüşmüş hücre olan satır"
  tam olarak bunu ister; seçici geçersiz olur ve kural tek kelime etmeden
  düşer. Yukarıdaki ritim bu yüzden oluk değişkeniyle yazıldı, yüklemle değil.
  (Yazıldı, kabul edildi, hiç uygulanmadı; belirtisi "düzelttim ama
  değişmedi" idi.)
- **Kartlar kendi ayıraçlarını çizmez.** Sekiz `<div class="col-12"><hr/></div>`
  hücresi kaldırıldı: çizgiyi artık stylesheet çiziyor, elle konan `<hr>` ona
  ikinci bir çizgi ekliyor ve araya oluk + kendi kenar boşluğunu koyup ritmi
  bozuyordu. Grup başlığı (`<h6>`) duruyor — o bir ayıraç değil, isim. Yeni bir
  karta `<hr>` koyma.
- **Etiket alanı sıradan bir kontrol değildir** (`input.form-control:not(.tagin)`).
  `tagin`'in gerçek kontrolü `div.tagin-wrapper`; yanındaki `input.form-control`
  gizli bir depodur ve yüklem onu görüp güvenlik duvarındaki yedi hücreyi
  yanlışlıkla dönüştürüyordu.
- **Bir alanın etiketi `form-label`'dır.** 31 yerde `form-check-label`
  yazıyordu (çoğu ödeme sağlayıcı blokları); yüklem onları tanımıyor ve o
  satırlar tek başlarına eski düzende kalıyordu. Sınıf onay kutusunun
  etiketine aittir, alanınkine yazma.
- **Çizgi ile yazı aynı yerden başlar, ve orası kartın içerik kenarıdır.**
  `.row` negatif yatay kenar boşluğu taşır ve karşılığını her hücreye padding
  olarak geri verir; hücreye çizilen bir kenarlık bu yüzden içindeki yazıdan
  **yarım gutter daha geniş** olur — çizgi iki uçtan da metnin dışına taşar ve
  kart eğri durur (ölçülen: çizgi 305, yazı 317). Dönüşen hücrede padding
  margin'e devredilir (`padding-inline: 0` + `margin-inline: gutter/2`,
  genişlik `calc(100% - gutter)`); yazı yerinden kımıldamaz, çizgi tam olarak
  yazının başladığı ve bittiği yerde başlayıp biter.
- **`:has()` taşıyan bir kuralı sıradan bir seçiciyle iptal etmeye çalışma.**
  Aynı tuzağa iki kez düşüldü: `:last-child` (0,4,0) kenarlık kuralını (0,5,1)
  yenemedi ve hiç uygulanmadı. Aynı yüklemi taşımayan hiçbir kural o kuralı
  ezemez.
- **Yerleştirme kuralları yakalayıcıdan daha özgül olmak zorunda.** Beklenmeyen
  çocuğu tam satıra indiren `> *` kuralı `:has()` argümanının özgüllüğünü
  taşıyor; `grid-area` yazan kurallar aynı yüklemi taşımazsa `grid-column`
  onları yener ve kontrol açıklamanın üstüne biner (Hassasiyet alanında böyle
  oldu). `:has()`'in özgüllüğü en özgül argümanı kadardır — kısa yüklem yazma.
- **Dönüşen hücre her zaman tam genişliktir**; aşağıdaki yarım-satır ızgarası
  dönüşmeyen hücreler için geçerlidir. Üç yan yana, kart ne kadar geniş olursa
  olsun sıkışık okunur. (Merchant Center bloğu bir zamanlar altı yan yanaydı,
  oturum kısıtlaması üç.)
- **Kontrolün genişliğini sınıf söyler.** Hücre satırın ritmini tutar, kontrol
  hücrenin ne kadarını istiyorsa onu alır; üç karakterlik bir sınır yarım kart
  boyunca uzayınca boş bir kutu gibi okunur ve operatör içine başka ne
  gireceğini aramaya başlar.

  | Sınıf | Ne tutar | Kontrol tavanı | 890px kartta |
  |---|---|---|---|
  | `pg-f-xs` | birkaç karakterlik sayı/kod, saat, yüzde | 11rem | 176 |
  | `pg-f-sm` | posta kodu, telefon, id, adet | 19rem | 304 |
  | `pg-f-md` | ad, şehir, tutar, açılır liste | 28rem | 415 (hücre kadar) |
  | `pg-f-lg` | e-posta, sunucu adı, anahtar — **kendi satırı** | 34rem | 544 |
  | `col-12` | adres, cümle, textarea, kod bloğu, etiket alanı | — | tam |

  Tavan yalnız **doğrudan çocuğa** uygulanır: `.input-group` içindeki kontrol
  grubun çocuğudur ve orada daraltmak grubu koparır, o yüzden grubun kendisi
  daraltılır. Kontrolü başka bir şeyin içine sarılmış hücre bütün hücreyi
  kullanmaya devam eder — yanılmanın güvenli yönü bu.
  Eşik kartın *içerik* kutusuna göredir, yani 1px kenarlık yüzünden 546 piksel
  civarında devreye girer.
- **Bu iş on üç reçeteden indirildi.** 153 kontrol sayıldı; `col-12 col-xxl-6`
  (54), `col-12` (31), `col-12 col-sm-6 col-lg-4` (13)… ve `col-xxl-6` gibi
  yarısı pane'in çizildiği genişlikte hiç devreye girmiyordu. Ayrıca
  `commerce.php`'de **21 yerde sınıf dizgisinde boşluk eksikti**
  (`col-xxl-6col-lg-3`): tarayıcı bunu tanımadığı tek bir belirteç sayıyor,
  o hücreler her genişlikte tam genişlik kalıyordu. E-Ticaret panelindeki
  tutarsız genişliklerin doğrudan sebebi buydu.
- **Kontrolün kendi özniteliği ne kadar yer istediğini söyler.**
  `maxlength <= 8`, `type="number"`, `inputmode="numeric"` ya da
  `size <= 8` olan bir alan `pg-f-xs` olur. Yeni alan yazarken sınıfı buna göre
  seç; kontrole `style="width"` / `max-width` yazma — genişlik ızgaranın işi
  ve iki kaynak zamanla ayrışır (bir tanesi vardı, cihaz sınırı alanında, ve
  hücresiyle çelişiyordu).
- **Diyalogda sahte parola alanı YOKTUR ve geri konmaz.** Tek ekranın taşıdığı
  iki sahte alandan biri gizli bir `type="password"` idi ve **sorunun kendisi
  oydu**: bir forma konan gizli parola girdisi, bir parola yöneticisine
  söylenebilecek en yüksek sesli cümledir — "burası bir giriş formu". Kaspersky
  buna kayıtlı kullanıcı adını destek e-posta alanına yazarak cevap veriyordu
  (sahada görülen: alan "admin" oluyor). Alan kaldırılınca doldurma durdu.
  Sahte alanların savunduğu şey Chrome'un kendi autofill'iydi; onun yerini
  aşağıdaki kilit aldı ve Chrome `readonly` alanı atlar. `screen.php`'nin sayfa
  akışında da parola sahtesi kaldırıldı, metin sahtesi duruyor.
  `pg_settings_stamp_autocomplete()` yine her kontrole
  `autocomplete="pg-no-autofill"` basar (`"off"` yetmez — Chrome emin olduğu
  alanda onu yok sayar).
- **Akrilik açıksa diyalog da camdır.** Görünüm → Şeffaf Akrilik Efekti gövdeye
  `advanced-visuals` koyuyor ve `.backdrop` işaretli her açılır menü o
  filtreden çiziliyor; ayarlar diyaloğu da aynı türden bir nesne — üstüne
  serildiğin ekrana kapatınca geri dönüyorsun — ve **aynı filtre değerleriyle**
  çiziliyor, kendi reçetesiyle değil. Arkasındaki perde de hafifletiliyor
  (`.pg-settings-open` gövde sınıfı, diyaloğun kendi koyduğu; yanındaki
  `pg-side-panel-open` tasarımcı paneliyle ortak olduğu için onunla ayırt
  edilemezdi): donuk cam, arkasında opak siyah bir yaprak varken donuk cam
  değildir. Kartlar dolgusunu bırakır — bu temada kart zaten pane'den bir adım
  ötede (ölçüldü: rgb(16,17,20) üstünde rgb(16,17,19)), yani kartı çizen rengi
  değil kenarlığı. Anahtar kapalıyken her yüzey eskisi gibi opaktır.
- **`alert-dismissible` üstünde `py-*` olmaz.** Bootstrap kapatma düğmesini
  mutlak konumla sağ üste park eder ve ona kendi dolgusunu verir; alerti
  kısaltmak düğmeyi kutunun dışına taşırır. Kapatma düğmesi olmayan bilgi
  alertlerinde `py-2` sorun değil.
- **Bir kartın betiği o kartın modülüne yazılır** (`$pg_settings_scripts`).
  `onclick` ile çağrılan bir fonksiyon sayfa betiğinde kalırsa bölmede düşer ve
  düğme sessizce hiçbir şey yapmaz (bu bir kez oldu: `pgRobotsNoIndex`).
#### Kartın kendi düğmesi: adı gönderilmek zorunda

Bir kart kaydetmenin yanında bir iş de yaptırabilir, ve bunun yolu sayfada ne
ise diyalogda da odur: **adı olan bir `type="submit"` düğmesi**. Kayıt modülü
onu `post_value('<ad>')` ile sorar, yani ayarlar önce yazılır, iş sonra yapılır
— tek kart düğmesi olan "Bot IP Listelerini Güncelle" böyle çalışır.

**Hiçbir serileştirici submit düğmesini göndermez.** Bir submit düğmesi ancak
formu *gönderdiğinde* başarılı bir kontroldür, `serialize()` ise o noktada
tıklamaya değil forma bakıyordur. Diyalogda bunun bedeli sessizdi: düğme
kategoriyi kaydediyor, işi yapmıyor ve "kaydedildi" diyordu. Adı tıklama
anında hatırlanıp gövdeye geri konuyor (`pressed`, `backend.src.js`).

- **Diyalogun kendi Kaydet'i `type="button"`**, o yüzden bu yola hiç girmez;
  düz kayıt düz kalır. Bir sonraki kayda ad yapışmadığı **doğrulandı**.
- **Metin alanında Enter da bu düğmeyi çalıştırır** (formun tek submit'i o) —
  sayfada da öyle. Karta ikinci bir adlı submit düğmesi koyarken bunu bil.
- **Bildirim metindir, işaretleme değil** (`notice()` `.text()` kullanır). İş
  bir DNS kaydı ya da anahtar döndürüyorsa cümlenin içine düz metin olarak
  koy; `<textarea>` yazarsan ekranda etiketiyle birlikte görünür.
- **Kaydetmeyen bir düğme gerekirse** ("kaydetme, sadece şunu yap") bunun
  sözleşmesi **yok** ve bilerek yok: bir tur yazıldı, SMTP ayarlarda kalmaya
  karar verilince kullananı kalmadı ve çıkarıldı. Aynı işi iki yoldan yapan
  iki mekanizmayı yan yana yaşatmak, hangisinin seçildiği karta göre değiştiği
  için sessiz hata üretir. Gerçek bir ihtiyaç doğduğunda o ihtiyaca göre
  yazılır — tahmine göre değil.

#### Reddedilen kayıt `invalid`'dir, `error` değil

Bir kayıt modülü, hata yazılımın değil operatörün ise **fırlatmaz, işaretler**
(`mark_error`). Uç nokta kayıttan sonra `check_form_errors()` sorar ve
işaretlenmişse `status: 'invalid'` + `errors` döner, "kaydedildi" demez.

**Pane bu durumda yeniden OKUNMAZ** ve bu bilinçli: işaretler formda duruyor
ama saklanandan yeniden boyamak, operatörün az önce yazdığı her şeyi alıp
götürür — düzeltmek için önünde olması gereken tam da o. Diyalog kirli kalır,
alanlar olduğu gibi durur, cümleler kutunun başında görünür.

- **İşaretleyen modül hiçbir şey yazmaz, kategorinin tamamı için.** MailChimp
  anahtarı reddedilince Kuruluş Adı da yazılmaz: yarısı kaydedilmiş bir
  kategori, tek bir Kaydet düğmesinin iki farklı şey rapor etmesidir. Modül
  doğrulamayı `UPDATE`'ten **önce** yapar ve işaretlendiyse `return` eder.
- **Reddedilen kayıt günlüğe "değiştirildi" yazmaz.** `pg_settings_apply()`
  `log_activity()`'yi `check_form_errors()` kapısının arkasına aldı.
- **Ağa çıkan doğrulama kayıt içinde olur, ayrı bir düğmede değil.** MailChimp
  anahtarı, listeyi ve mağazayı Mailchimp'e sorar, gerekiyorsa mağazayı orada
  yaratır; hepsi Kaydet'in içinde. Diyalogun kaydı zaten AJAX, saniyeler
  sorun değil.

#### Diyaloga giden cümle metindir

`pg_settings_pane_words()` her bildirimi ve her hatayı `strip_tags` +
`html_entity_decode`'dan geçirir. Bildirim kutusu **metin** yazar (`.text()`),
ve doğrusu da bu: bu cümleler kayıt modüllerinden, buraya uğrayan araçlardan
ve onların üzerinden Mailchimp gibi servislerden geliyor — hiçbiri sayfaya
işaretleme olarak girmemeli. Etiketler gösterilmez, düşürülür (`<textarea>`
taşıyan bir bildirim yoksa DNS kaydını etiketin içine gömerdi); varlıklar
çözülür, çünkü ekran için kaçırılmış bir mesaj buraya `=&gt;` diye geliyor ve
karşısında kaçılacak HTML kalmıyor.

- **Yeni bir kategori** = `registry.php`'ye giriş + `includes/settings/<key>.php`
  + `<key>.save.php` + `settings_<key>.php` sarmalayıcı (sayfa yolu için).
  Menüde vurgulanacak bir girdi yok.

---

### Ürün Düzenleme ve Set Düzenleme Ayrı Ekranlardır

| Nereden | Düğme | Nereye |
|---|---|---|
| Tüm Ürünler listesi | Düzenle | `edit_product.php?id=N` — **tek ürün**, yeni arayüz |
| Varyant Setleri listesi | Düzenle | `edit_product_group.php?id=N` — grup, içindeki ürünlere link verir |

**Ürün düzenleme ekranında varyant matrisi bulunmaz.** Matris yalnızca ürün
*eklerken* ve grup ekranında vardır. Sebep yasak değil, yapı: matris tek ürün
ekranında olsaydı, operatör bir varyantı düzeltmeye girip altına yeni bir
varyant ekleyebilirdi — varyantın varyantı olmaz. Kontrol o ekranda hiç
bulunmadığı için durum imkânsızdır, kural koymaya gerek kalmaz.

**Ürün, gruba ait olduğu için ikinci sınıf değildir.** Aynı ürün bir grubun
varyantı iken başka bir grubun düz üyesi olabilir (`products_groups_xref` çoklu
üyeliğe izin verir). Bu yüzden ürünün kendi ayrıntılı düzenleme ekranı olmak
zorunda; "zaten set ekranından düzenleniyor" varsayımı yanlıştır.

### URL'e Girecek Hiçbir Ad ASCII Dışı Olamaz (IIS)

IIS, yüzde kodlu UTF-8 yolu çözüp **sunucunun ANSI kod sayfasına yeniden
kodlayarak** PHP'ye verir. Ölçüldü: tarayıcı `%C3%B6` gönderir, `REQUEST_URI`'ye
`f6` olarak ulaşır, veritabanındaki değer ise `c3b6`'dır. `REQUEST_URI` içinde
`%` bile kalmaz — `rawurldecode()` yapacak bir şey bulamaz.

Sonuç: **adında Türkçe karakter olan hiçbir kayıt URL'den bulunamaz.** Dosya
diskte durur, panelde görünür, adresi 404 verir. Hata "dosya bulunamadı" değil
**"sayfa bulunamadı"**dır — router dosyayı bulamayınca sayfa aramaya geçer.

`prepare_file_name()` bunu `pg_ascii_file_name()` ile çözer (Türkçe eşleme
önce; genel aksan temizleyici `İ`yi bozar, `ş`/`ğ`yi tanımaz). **Aynı açık
`prepare_catalog_item_address_name()`'de duruyor** — Türkçe adresli ürün
sayfaları aynı şekilde 404 verir; değiştirmek yayındaki bağlantıları
etkileyeceği için ayrı bir karardır.

Kural: URL'e girecek yeni bir ad üretiyorsan ASCII'ye indir. Sunucunun düzgün
davranacağına güvenme.

### `.sortable()` Kuran Her Ekran İçin Tuzak

`backend.src.js:446` ready'de `$(".ui-sortable").sortable({items:"a",
handle:this, axis:'y', delay:300, containment:"parent", helper:'clone'})`
çalıştırır.

`ui-sortable`, **jQuery UI'nin kendi eklediği** sınıftır. Bu satır markup'ta
işaretlenmiş listeleri değil, **sayfadaki her kurulmuş sortable'ı** bulur; canlı
widget'ta `.sortable({...})` ikinci widget kurmaz, **seçenekleri ezer**. Kod
tabanında elle yazılmış `class="ui-sortable"` yoktur.

Yeni bir ekranda sortable kurarken:

1. Kurulumu **`$(function(){...})` içine al.** Ready callback'leri kayıt
   sırasına göre çalışır; `backend.src.js` önce yüklendiği için onun handler'ı
   önce çalışır, seninki en son yazar. Anında (parse anında) kurarsan ezilirsin.
2. O satırın dokunduğu **her seçeneği açıkça yaz** — `items`, `handle`, `axis`,
   `delay`, `containment`, `helper`, `scroll`, `dropOnEmpty`, `revert`,
   `tolerance`. Yazmadığın seçenek onun verdiği değeri korur.

Legacy ürün/grup ekranları satır içi `$(".sortable-list").sortable({...})` ile
kazara kurtulur (ready kuyruğunda sonra kayıtlılar). Belirti: sürükleme **hiç**
çalışmaz (`items:"a"` hiçbir şeyle eşleşmez), veya kesilen sürüklemede
`helper:'clone'` yüzünden listede **kopya kalır**.

### Yetki Satırları — kullanıcı ekranları (`includes/user_permissions.php`)

`add_user.php`, `edit_user.php` ve `import_users.php` yetki bölümünü tek bir
yerden çizer: `pg_user_permission_ui($config)` satırları ve panelleri,
`pg_user_role_cards()` rol kartlarını, `pg_user_permission_everything()` tam
yetkili roller için gelen kartı üretir. Ekran yalnız **seçim markup'ını** verir
(`$config['panels']`), çünkü ekleme ekranı üreticileri boş, düzenleme ekranı
kullanıcının seçimleriyle çağırır.

Uyulması gereken dört kural:

- **Alan adı ve değeri değişmez.** Bazı kolonlar `'yes'`, bazıları `'1'` saklar
  (`manage_calendars` = `yes`, `view_card_data` = `1`); üç ekranın POST
  işleyicileri bu tam değerlere bakar. Yeni bir switch eklerken değeri
  `pg_user_permission_switch()`'e olduğu gibi geçir.
- **Kapının ne sürdüğü isimle söylenir.** Kendi kolonu olan grupta anahtar o
  kolondur (`gate_field`). Kolonu olmayan grupta anahtar bir UI kapısıdır ve
  neyi temizleyeceği `gate_scope` (bir liste) ya da `gate_fields` (paneldeki
  alanlar) ile yazılır. Sayaçlardan tahmin edilmez: sayfa türleri içerik
  satırında sayılır, ama erişimi veren klasör listesidir.
- **Sayılar sunucuda değil, kutulardan okunur.** Satırdaki rozetleri
  `backend.src.js` panelin `input.multiselect-checkbox` durumundan yazar; PHP'ye
  geçen `counts` yalnız ilk kapı durumunu belirler. Böylece rozet ile
  gönderilecek veri ayrışamaz.
- **Paneller formun içinde durur.** Bootstrap offcanvas DOM'da hiçbir şeyi
  taşımaz, dolayısıyla panel `<form>` içindeyken alanları normal gönderilir.
  Kendi formu olan şeyler (oturum kapatma, Google bağlantısını kesme) ana formun
  **dışında** kalmak zorundadır — form iç içe geçemez; `edit_user.php`'de bunlar
  ana form kapandıktan sonra gelen ayrı bir panelde.

Sınıflar `backend.src.css` sonundaki *Admin screen — user permission rows*
bloğunda (`.pg-perm*`, `.pg-role*`, `.pg-identity*`, `.pg-fact*`,
`.pg-session*`). Sohbet balonu `z-index: 1080` ile offcanvas'ın (1045) üstünde
kaldığı için panel açıkken `body.pg-side-panel-open` ile gizlenir; paneli
yükseltmek balonu perdenin üstünde bırakırdı.

**`multiselectCheckbox` kutu başına olay atmaz.** "Hepsini seç" kutuların
`.checked`'ini programatik yazar ve yalnız **kendi anahtarı** için tek bir
`change` tetikler. Bir onay kutusunun yanındaki davranışı kutunun `onclick`'ine
bağlarsan hepsini seç onu hiç çalıştırmaz. Davranışı olaya değil **duruma**
bağla ve listede herhangi bir değişiklik olduğunda hepsini yeniden eşitle;
`pgSyncAclExpiry()` ve `pgSyncCheckboxList()` bu kalıbın örnekleri. Aynı sebeple
"hepsini seç" anahtarının üçüncü durumunu (`indeterminate`) da eklenti değil bu
kod hesaplar.

**Panelin içine jQuery UI takvimi koyacaksan `z-index`'i zorla.** Takvim
`<body>`'ye asılır ve `z-index`'ini alanın atalarından hesaplayıp satır içi
yazar; offcanvas (1045) ya da modal (1055) içindeki bir alanda bu değer küçük
kalır ve takvim açıldığı panelin **arkasında** çizilir. `#ui-datepicker-div`
bu yüzden `backend.src.css`'te 1065'e `!important` ile sabitli.

**`db_items()` dizi döner, `mysqli_result` değil.** Üzerinde
`mysqli_fetch_assoc()` çağırma — `foreach` kullan. Bu tuzak oturum listesinde
uzun süre gizli kaldı: boş sonuç boş dizidir, `if ($rows)` guard'ına takılır ve
yalnız gerçekten satır varken ölümcül olur.

### Adım Rayı — sırayla okunan ekranlar (`edit_offer.php`)

Bölümleri yukarıdan aşağı sırayla okunan ekranlar numaralı bir rayla çizilir:
solda numaralı düğüm ve düğümleri birleştiren çizgi, sağda sıradan bir
Bootstrap kartı. Bootstrap'te stepper yok, o yüzden ray `backend.src.css`
sonundaki *Admin screen step rail* bloğunda: `.pg-step`, `.pg-step-rail`,
`.pg-step-node` (+ `.pg-step-done` / `.pg-step-todo` durum renkleri). Blok
paylaşılan dosyadadır; yeni ekran bu sınıfları kullanır, kendi kopyasını
yazmaz.

**Rayın dışında kalan her şey Bootstrap'indir.** Genişlik için sınıf
uydurulmaz: alan genişliği `col-*` ızgarasından gelir (`col-7 col-sm-5
col-md-3 col-xl-2` gibi), satırlar `list-group list-group-flush`, etiketler
`badge`, hata metni `small text-danger-emphasis`, hizalama `d-flex` / `ms-auto`
/ `gap-*` yardımcılarıyla yapılır. Ekrana `max-width` konmaz — yönetim
ekranları `container-fluid` genişliğindedir.

**Görünürlük `d-none` ile açılıp kapanır, `hidden` ile değil.** Bootstrap'in
`d-flex` / `d-block` gibi display yardımcıları `!important` taşır ve `[hidden]`
kuralını yener; üzerinde ızgara ya da flex sınıfı olan bir öğe `el.hidden =
true` ile gizlenmez. `offer_editor.js`'teki `show(el, on)` yardımcısı bunu tek
yerden yapar.

### Teklif Editörü — kural ve hareketler teklifin parçasıdır

`edit_offer.php` + `edit_offer_f.php` + `assets/js/offer_editor.js` üçlüsü tek
bir durum nesnesi taşır: teklif, koşul satırları, sonuç satırları. Kaydetme tek
JSON isteği (`api.php` → `action: offer_editor`, `type: offer_save`);
`api.php`'deki genel rol kapısında muaf tutulur, yetkiyi
`pg_offer_editor_handle()` kendi içinde `validate_user()` +
`manage_ecommerce` ile verir.

Uyulması gereken dört kural:

- **Ad sorulmaz.** `offer_rules.name` / `offer_actions.name`
  `_pg_offer_auto_name()` ile üretilir. Ekrana ad alanı eklenmez.
- **Paylaşılan satır kopyalanır.** Kural ya da hareket satırını başka teklif de
  kullanıyorsa UPDATE değil INSERT (`_pg_offer_rule_write()` /
  `_pg_offer_action_write()`); bağı kopan satır başkası kullanmıyorsa silinir.
- **Tür kayıtları iki taraflı.** Yeni koşul/sonuç türü hem
  `_pg_offer_condition_types()` / `_pg_offer_action_types()` içine hem JS
  tarafına eklenir; biri eksikse tür ne eklenebilir ne kaydedilebilir.
- **Çocuklar önce, `offers` en son.** MyISAM'da transaction yok; doğrulama
  (`_pg_offer_validate()`) hiçbir şey yazmadan biter, yazma sırası bozulmaz.

Para birimi `BASE_CURRENCY_SYMBOL` içinde HTML varlığı olarak durur
(`&#8378;`); ekrana ve JSON'a `_pg_offer_currency()` (yani
`html_entity_decode`) ile çıkar — ham sabiti basmak `&#8378;` yazdırır.

---

## Sistem Widget — Data Binding Mimarisi

**Temel kural:** Sistem widget'larındaki TÜM veri akışı ve fonksiyonlar **data binding** üzerinden olur. Magic CSS class'lar yok. Designer:
1. Bir element seçer
2. Sağ panelde uygun binding dropdown'undan (text, src, href, action, eo_field, section, value) bağlamayı yapar
3. Backend o elementi bağlamaya göre işler

**Binding türleri:**

| Binding | Element tipi | Ne yapar |
|---|---|---|
| `_bindings.text=X` | content (heading/paragraph/text), semantic | Backend'in X token değerini elementin text içeriğine yazar |
| `_bindings.src=X` | content[image], semantic <img> | Backend'in X token değerini src attribute'a yazar |
| `_bindings.href=X` | content[link], semantic <a> | href'i token URL'iyle değiştirir |
| `_bindings.action=X` | semantic <button>/<a>, content[link], component[btn] | Element'i ilgili eyleme bağlar (submit, navigate, qty stepper, remove) |
| `_bindings.eo_field=X` | semantic <input>/<select>/<textarea> | Express Order form field'ına bağlar (name, id, value pre-fill) |
| `_bindings.value=X` | semantic <input> | Cart widget loop'ta cart_qty gibi sistem-managed input |
| `_bindings.section=X` | semantic <div> | Container'ı backend section HTML'iyle doldurur |
| `_bindings.eo_visible_if=X` | herhangi semantic container | Backend flag false ise node tree'den drop edilir |

**`href` bağlıysa URL alanı gizlenir** (`_sdHrefIsBound()` +
`_sdBoundHrefNotice()`, style_designer.js). Dört panelde birden: Link Options,
Button Options, semantic `<a>`, generic component href satırı. Sebep: sunucu
href'i render'da yeniden yazıyor, kutuya yazılan URL hiçbir işe yaramıyordu —
"görünür ama işlevsiz" yasağı. Bağlama select'i değişince `renderProperties()`
çağrılır, ama **yalnız `href` için**: custom token input'u her tuş vuruşunda
`_setBinding()` çağırıyor, orada panel yeniden çizilirse odak kaçar.

**HTML üreten token'ların canvas önizlemesi** `_SD_HTML_PREVIEW_TOKENS`
içinde (style_designer.js). Köşeli parantezli yer tutucu (`[Durum Rozeti —
sunucuda HTML]`) yerine gerçek markup basılır; semantic node'da
`insertAdjacentHTML`, paragraph content'te zaten `innerHTML`. Yeni bir
HTML-emit token eklersen buraya da bir önizleme yaz — yoksa tasarımcı
canvas'ta boşluk görür.

**Mevcut action binding'leri:**

| Action | Hedef | Etki |
|---|---|---|
| `add_to_cart` | catalog_item_view button | Ürünü sepete ekler |
| `catalog_add_to_cart` | catalog_listing card | Per-card sepete ekle |
| `cart_update` / `cart_checkout` | shopping_cart button | Sepet güncelle / Ödeme |
| `cart_qty_inc` / `cart_qty_dec` | shopping_cart loop button | Miktar +1 / -1 |
| `eo_submit_purchase` | EO button | Siparişi tamamla |
| `eo_submit_update` | EO button | Sepet/totals güncelle |
| `eo_qty_inc` / `eo_qty_dec` | EO loop button | Miktar +1 / -1 |
| **`remove_from_cart`** | shopping_cart + EO loop, semantik tag-agnostic | Per-row ürün kaldırma (a→href, button→data-pg-remove-href + JS) |
| `apply_coupon` | shopping_cart button | Teklif kodu uygula |
| `coupon_form` | shopping_cart form | Form action/method + CSRF |
| `open_filters` / `clear_filters` / `submit_search` | catalog_listing | Filtre offcanvas, temizle, ara |

### Fonksiyonel vs Dekoratif Class'lar

**FONKSIYONEL (silinmemeli — JS / CSS davranışı bağlı):**
- `pg-eo-form` → `[data-pg-eo-form]` ile form identification
- `pg-eo-cc-fields` → CC payment toggle JS hook
- `pg-eo-total-formatted` → Install fee JS canlı total güncelleme
- `pg-eo-installment-fee-row` / `pg-eo-installment-fee-value` → Install fee JS toggle
- `pg-eo-surcharge-row` → Surcharge JS toggle
- `pg-eo-terms-modal` → Bootstrap modal hedef
- `pg-qty-stepper` → Qty stepper JS sınır wrapper
- `pg-eo-applied-offers-anchor` / `pg-eo-saved-cart-anchor` / `pg-eo-upsell-anchor` → Auto-injection detection
- `pg-eo-applied-offers` → Auto-inject self-detection
- `data-*` attributes (data-pg-qty-action, data-pg-remove-href, data-pg-eo-form, data-pg-cc-required) → JS hook'ları

**DEKORATİF (silinebilir — yalnızca CSS hook):**
- ~~pg-eo-item-cell~~, ~~pg-eo-item-image~~, ~~pg-eo-item-name~~, ~~pg-eo-item-short-desc~~, ~~pg-eo-item-full-desc~~ — **artık default tree'de yok** (2026.1.26+)
- ~~pg-eo-item-extras-anchor~~ — değiştirildi, text binding kullanılıyor

**Designer için kural:** Bir element'i silebilir → yeni element ekleyebilir → data binding menüsünden aynı bağlamayı yapabilir → sistem çalışmaya devam eder. Class'lar **fonksiyon DEĞİL** veri bağlama fonksiyon.

---

## Görsel Tasarımcı — Aynı Anda İki Kişi (2026.4.4)

Çekirdek `includes/designer_collab.php`; uç noktalar `api.php` →
`designer/presence|leave|lock_release|comment_*`; istemci tarafı
`style_designer.js` içindeki `_collab*` bloğu.

| Tablo | Anahtar | Ne tutar |
|---|---|---|
| `designer_presence` | `uniq_session(session_key)` | Açık editör **sekmesi** başına bir satır, 20 sn nabız |
| `designer_page_lock` | `PRIMARY KEY(page_id)` | Sayfayı kim düzenliyor |

### Kurallar

- **Kimlik sekmedir.** Anahtar `sessionStorage`'da (`pg_sd_tab`). Aynı kişinin
  iki sekmesi tek kilit için kavga etmemeli.
- **Kilit tek `INSERT ... ON DUPLICATE KEY UPDATE` ile alınır.**
  SELECT-sonra-INSERT yarış üretir. Devir koşulu:
  `(session_key = <benim> OR last_seen < <bayat>)`.
- **`session_key` SET listesinde EN SONA yazılır.** MySQL atamaları soldan
  sağa değerlendirir; anahtar önce güncellenirse sonraki koşulların hepsi
  "benim" der ve kilit her isteyene geçer.
- **Kilit bir yetkidir.** `pg_collab_may_edit_page()` **kayıt yolunda**
  çağrılır (`includes/designer_screen.php`). Kaydet düğmesini kapatmak yetki
  kontrolü değildir — POST'un o ekrandan gelme zorunluluğu yok.
- **Kilitli sayfa kaydı düşürmez, atlanır** ve uyarı olarak bildirilir.
  Editör tüm sekmeleri birden kaydeder.
- **Bayatlama (`PG_COLLAB_STALE`, 65 sn) asıl emeklilik mekanizmasıdır**;
  `leave` / `lock_release` yalnız devri hızlandırır. Öldürülen tarayıcı veda
  edemez.
- **`_sdMayEdit(node)` düğüm başına sorar.** Bugün cevabı sayfa kilidi;
  önizleme modu bu fonksiyonun **cevabını** değiştirecek, yanına ikinci bir
  kapı takılmayacak. Çağrı noktaları: `handleDrop`, satır-içi metin
  düzenleme, `onDelete`, iki klavye dinleyicisi.
- **Görüntüleme modunda düzenleme panelleri gizlenir, soluklaştırılmaz:**
  bileşen paleti, sağ özellik paneli ve alt panel şeridi (`.sd-bp-*`)
  `display:none`; geriye yalnız Genel Bakış kalır ve tıklanabilir olmalı —
  notu bırakacağın elemanı oradan buluyorsun. Sayfa ayarları `inert` ile
  kilitlenir (alan alan `disabled` değil: kilit açılınca zaten devre dışı
  olanları da açardı). Her şey `_collabSetMode()` içinde **iki yönlü**
  yazılır; kilit editör açıkken serbest kalabilir. Alt panelin kendisi
  `#sd-bottom-panel`'dir (sınıfı `sd-panel-bottom` — ikisi ters yazılmış);
  yalnız içindeki şeritleri gizlemek altta boş bir bant bırakır.
- **`_sdGuardItem` önce kilide bakar, sonra role.** `_sdIsContentLevel()`
  *hangi düğüme* dokunulabileceğini sorar; kilit *hiçbirine* der. Rol
  seviyeli maddeler (kilitle, ortak bileşene dönüştür, düzenlenebilir alan)
  da bu guard'dan geçmek zorunda — geçmedikleri sürece görüntüleme modundaki
  yönetici menüden her şeyi yapabiliyordu.
- **Görüntüleme modunda sağ tık menüsü tek maddedir: Not Ekle.** Menünün hiç
  açılmaması bozuk bir sağ tık gibi okunur; soluk bir liste de aynı şeyi
  söyleyip daha uzun sürer.
- **Sohbet balonu editörde `pg_chat_launcher_html()` ile basılır.** Bu ekran
  `output_footer()` çağırmaz (görüntü yüksekliğinde flex uygulama; footer
  çubuğu kaydırma üretir), dolayısıyla footer'a bağlı hiçbir şey buraya
  kendiliğinden gelmez. Balonun konumu `body.design` önekli kurallarla
  ayarlanır — `chat.php` kendi `<style>`'ını belgenin sonunda bastığı için
  eşit özgüllükteki kural kaynak sırasına yenilir.
- **Görüntüleme modu şeridi kapatılamaz**; modal sayfa başına bir kez çıkar
  (`_collab.warned` + `lockedPage`).
- **Avatar `title` kullanır, popover değil** — modal backdrop'ı altında
  popover görünmez.
- Ad/avatar `pg_chat_user_brief()`'ten gelir; sohbet kuruluysa avatar
  `window.pgChatOpenWith(userId)` çağırır.
- **Yorum katmanı yazılmaz.** Görüntüleme modundaki kullanıcının yazma yolu
  editörün var olan düğüm notudur (`props._notes`). Tek istisna uç noktası
  `designer/note_save`: tek sayfa, tek düğüm, tek özellik yazar, `page_tree_code`'a
  dokunmaz — kilidin bilinçli ve dar istisnası.
- `pg_collab_ready()` iki tabloyu birden yoklar; yükseltilmemiş kurulumda uç
  nokta `ready:false` + `may_edit:true` döner ve editör eskisi gibi davranır.
- **Devralma yalnız aynı kullanıcının öteki sekmesinden.** Kilit sekmeye
  ait olduğu için aynı kişi ikinci sekmede görüntüleme moduna düşer;
  `holder.user_id === sdDesign.userId` ise şerit ve modal "Tasarımı devral"
  sunar (`_collabTakeOver` → `_collabBeatNow(true)` → `presence` +
  `takeover: 1`). Sunucu `pg_collab_claim_page(..., $take_over_own)`
  devir koşuluna `OR user_id = me` ekler — **başka kullanıcının kilidi bu
  yolla asla düşmez**, bayrağı kim gönderse de. Uyarı koşulsuzdur: öteki
  sekmenin kaydedilmemiş işi buradan bilinemez (kapalı/donmuş sekme cevap
  veremez), soru operatöre sorulur. Devrilen sekme bir sonraki nabızda
  görüntüleme moduna düşer, işi bellekte kalır, Kaydet kapanır.

### Not bırakıldığında karşı taraf yenilemez

- **Sinyal nabza biner.** `presence` yanıtı `notes` haritası taşır
  (`page_id => page_notes_at`); değişen sayı `designer/notes_fetch`'i
  tetikler. Yeni bir yoklama döngüsü açma.
- **`page.page_notes_at` bir tamsayıdır, imza değil.** Alternatif her nabızda
  her sayfanın `page_tree_json`'ını okumaktı; sütun LONGTEXT, sekme başına bir
  tane, ve cevap neredeyse her zaman "değişmedi". `pg_collab_notes_ready()`
  defansif.
- **`notes_fetch` ağaç döndürmez.** Soran kişi kendi düzenlemesinin
  ortasında; ağaç göndermek onu "işini at" ile "iki düzeni birleştir" arasında
  bırakır. Bildirim bu seçimi yapmaz.
- **Birleştirme yerelde yazılmakta olan notu ezmez ve hiçbir notu silmez.**
  Ölçüt: `_notes` var + `_notes_at` yok ⇒ bu tarayıcının henüz göndermediği
  not, kazanır. Uzakta silinen not yenilemeye kadar ekranda kalır.
- **Birleştirme `saveState()` çağırmaz, kirli bayrağına dokunmaz.** Operatörün
  yapmadığı bir değişiklik, kaydetme teklifinin sebebi olamaz.
- `_collabPersistNote()` **her rolde** çalışır; kaydetme yetkisi olanda hata
  sessizdir (en olası sebep düğümün henüz kayıtlı ağaçta olmaması, ve onun
  için bu bir hata değil).

### Palet Bileşeni Ya Ağaçtır Ya Atomiktir — Üçüncüsü Yok

Palete bırakılan her şey `createFromPalette()` içinde **gerçek düğüm ağacına
patlar**; geriye yalnız iki atomik bileşen kalır, `btn` ve `badge`, ve yalnız
onlar `COMPONENTS` içinde `render()` + `toHTML()` taşır. Geri kalan yirmi altı
girdi sadece `label` / `icon` / `defaultProps`'tur.

Bir zamanlar on tür (navbar, hero, card, carousel, accordion, tabs, pricing,
features, progress, alert_box) **ikisini birden** taşıyordu: yeni bırakmada
ağaç, patlama öncesi kaydedilmiş sayfada opak `component` düğümü. Aynı palet
öğesi, sayfanın ne zaman yapıldığına göre iki farklı davranış — içi seçilebilen
bir kart ile seçilemeyen bir kart. O `render()`/`toHTML()` çiftleri silindi
(~224 satır). **Yeni bir bileşene `render()` yazma**: ağaca patır, ya da
gerçekten tek elemansa `btn`/`badge` gibi atomik olur.

**Düğmenin iki modeli tek panelden düzenlenir.** `propsButtonLinkOptions()`
hem `component`/`btn`'i hem semantic `<a>`/`<button>`'ı karşılar: bileşen
dalı props'tan, semantic dal `cssClass`'tan okur. İkisine ayrı panel yazma.
Varyant / Çerçeveli / Boyut satırları `displayStyle === 'btn'` kapısının
arkasındadır — `.btn` taşımayan bir elemanda `btn-sm` hiçbir şey yapmaz ve
o satır çizilmez. Kısıtlı model (bileşen, çocuk alamaz) panelin başındaki
tek satırlık notla söylenir; semantic düğme not taşımaz. **Paletteki "Düğme"
öğesi semantic üretir**; `component`/`btn` düğümleri yalnız sistem
widget'larının hazır düzenlerinde bulunur.

**İki düğme modeli bilerek duruyor ve karışık değildir.** Düz bir çağrı
düğmesi `component`/`btn`'dir (variant ve boyut açılır listeleri, metin alanı);
çerçeve özniteliği ya da çocuk işaretleme taşıyan düğme `semantic`
`<button>`'dır (navbar aç/kapat, kapatma, karusel kontrolü, akordiyon başlığı,
sekme, dropdown maddesi — ölçüldü: 21 yapısal, 42 düz). Biri ötekine
çevrilemez: `component` düğümünün çocuğu olmaz, `semantic` düğümün de variant
paneli yoktur.

### Editörün Verisi Betikten ÖNCE Tanımlanır

`includes/designer_screen.php` önce `OUTPUT_PATH` / `sdRegionData` /
`sdDesign` bloğunu, sonra `style_designer.js`'i basar. **Sıra zorunludur:**
`_sdT()` haritayı çağrıldığı anda okur ve `COMPONENTS`, `CONTENT`, form
paleti gibi modül seviyesi kayıtlar dosya ayrıştırılırken kurulur. Veri
bloğu sonra gelirse o etiketler İngilizce anahtarda donar ve tr.json'a ne
yazılırsa yazılsın değişmez — belirti "bazı yerlerde çeviri yok", sebebi
ise eksik çeviri değil, geç gelen harita. Editörün ayrıştırma anında
ihtiyaç duyduğu her yeni global bu bloğa yazılır, `$(document).ready`
bloğuna değil.

### Katman Adı Rolü Söyler, Rol Başına Tek Ad

`customName` operatörün ağaçta okuduğu addır; `Body`, `Item`, `Head` gibi
bir ad otuz düğümlük bir ağaçta hiçbir şey söylemez.

- **Aynı rol her blokta aynı adı taşır.** `Kapat Düğmesi`, `Açılır Menü
  Öğesi`, `Sosyal Bağlantılar`, `Navbar Konteyneri`, `Menü Bağlantıları`,
  `Tablo Gövdesi` — yeni bir blok yazarken var olan adı kullan, eş anlamlısını
  uydurma (`Anchor` / `Close` / `Toggle` / `Compare Table` böyle doğmuştu).
- **Jenerik ad bağlamını alır:** bir `<tbody>` `Tablo Gövdesi`, bir
  `.modal-body` `Modal Gövdesi`, bir `.offcanvas-body` `Offcanvas Gövdesi`.
- **Numara anahtara gömülmez:** `_sdT('Tab {var}', 1)`, `_sdT('Item {var}', n)`.
  `'Tab 1'` hem çeviriyi üçe böler hem `{var}` kuralını çiğner.
- **Sarmalayıcı ve çocuğu aynı adı taşımaz** — ad sarmalayıcıdadır.
- **Boş `customName` yazma.** Ad yoksa alanı hiç koyma; ağaç o zaman etiketi
  gösterir, boş satır değil.
- **Palet kaydının `label`'ı ve `defaultProps` içindeki görünen metinleri
  `_sdT()` ile sarılır.** Denetleyici bu konumda tek kelimelik büyük harfli
  değeri tanımlayıcı sayıp atlar, yani **sessiz kalır**; gözle doğrula.
- **Yer tutucu metin Latince olmaz.** Bırakılan bileşenin varsayılan metni
  operatöre ne yapacağını söyler; Lorem ipsum ne okunur ne de silinmesi
  gerektiğini belli eder.

### Tuvalde Hiçbir Bağlantı Gezinemez — `#fragment` Dahil

Tuval belgesinin `<head>`'inde `<base href="<site kökü>">` vardır (kök-göreli
varlık yolları gerçek siteye çözülsün diye). **`<base>` varken `href="#bolum"`
aynı belge içinde bir çapa değildir:** base'e göre çözülür, `<site>/#bolum`
olur, bu da tuvalin kendi adresinden başka bir belgedir ve iframe oraya gider.
"Fragment zararsızdır, kaydırma yapar" varsayımı burada yanlıştır ve bu hata
tam o varsayım yüzünden uzun süre ayakta kaldı.

- Tıklama işleyicisi `<a href>` için **koşulsuz** `preventDefault()` yapar.
  Fragment yine anlamını gösterir: hedef `canvasDoc.getElementById` ile bulunup
  `scrollIntoView` edilir.
- **Bootstrap'e bağlı bağlantı da muaf değildir.** `preventDefault()` yayılmayı
  durdurmaz, Bootstrap'in devredilmiş işleyicileri olayı almaya devam eder ve
  sekme/akordiyon/dropdown çalışır. Muafiyet vermek, Bootstrap kendi
  `preventDefault`'una varamadığında (bir istisna fırlatırsa) varsayılan eylemi
  canlı bırakır.
- Orta tık ayrı bir kapıdır: `auxclick` de kapatılır. Klavyeyle etkinleştirme
  `click` ürettiği için ek işleyici istemez.

**Dropdown'ı Bootstrap değil editör açar.** Bootstrap Dropdown menüyü
tetikleyicinin **kardeşi** olarak arar; tuvalde araya `.sd-wrap` girdiği için
bulamaz ve istisna fırlatır (`display:contents` DOM kardeşliğini değiştirmez —
doğrudan-çocuk varsayımının kardeş sürümü). Tıklama işleyicisi bu yüzden
`[data-bs-toggle="dropdown"]` için menüyü kapsam üzerinden bulup `show`
sınıfını kendisi çevirir ve olayı `stopPropagation()` ile Bootstrap'e
ulaştırmaz. **Menü açık kalır**: operatör içindeki öğeyi seçip düzenleyebilsin
diye; Bootstrap olsa bir sonraki tıklamada kapatırdı. Yeni bir Bootstrap JS
bileşeni eklerken kardeş/çocuk ilişkisine dayanıp dayanmadığını kontrol et.

### Geniş Oluk `.container` İçinde Taşar — `g-4 g-lg-5` Yaz

`g-5` satıra iki yandan −24 px kenar boşluğu verir, `.container`'ın dolgusu
12 px'tir. 576 px altında `.container` tam genişliktir, yani fark doğrudan
görüntü alanının dışına taşar ve **telefonda sayfa yana kayar**. 576 px
üstünde `.container` bir `max-width` alıp kenarda boşluk bıraktığı için
taşma yutulur — hata yalnız dar ekranda görünür, masaüstünde bakan göremez.

Kural: satır oluğu `gutter: '4'`, geniş oluk isteniyorsa `cssClass: 'g-lg-5'`.
Düz `gutter: '5'` yazma.

**Responsive ölçümü nasıl yapılır:** paletten çift tık bloğu **seçili düğümün
içine** koyar, ardışık bırakmalar blokları iç içe geçirir ve sahte taşma
ölçtürür. Her blok tek başına ölçülür: bırak → `#sd-html-tree`'den üretilen
HTML'i al (etiket içindeki `&nbsp;` normal boşluğa çevrilir) → Ctrl+Z ile
geri al. Ölçüm tuvalde değil, o HTML'in temiz bir Bootstrap sayfasındaki
hâlinde yapılır — tuvaldeki `.sd-wrap` sarmalayıcıları gerçek sayfada yoktur.

### Blok Stili: Ya Bootstrap, Ya Stiller Paneli — Satır İçi Değil

Ek CSS ya hiç olmaz, ya da tasarımın Stiller paneline tanımlanır. Satır içi
`style` ve sayfaya basılan `<style>` elementi ikisi de operatöre görünmez.

1. **Bootstrap'te varsa sınıfı kullan**, kural yazma: `object-fit-cover`,
   `display-*`, `fs-*`, `overflow-x-hidden`, `opacity-*`, `position-sticky`
   (Bootstrap 5.3.8, `assets/lib/bootstrap-5.3.8`).
2. **Yoksa kuralı `_SD_BLOCK_CSS`'e, ihtiyacı `_SD_BLOCK_CSS_NEEDS`'e yaz.**
   Blok bırakıldığında `_sdEnsureBlockStyles()` yalnız o bloğun kurallarını
   Kullanıcı Stilleri'ne ekler; ad zaten varsa dokunmaz, yani operatörün
   düzenlediği kural korunur ve ikinci bırakma mükerrer üretmez. Sınıf adı
   `pg-` önekli olur.
3. **`<style>` elementi sayfaya basılmaz.** `custom_html` düğümüne stylesheet
   koymak, geri alma yığınına girmeyen ve panelden görünmeyen bir kural
   demektir.

**Tek istisna, özellikler panelinin kendi yazdığı style özellikleridir:**
`background-image` / `-size` / `-position` / `-repeat` / `-attachment`,
`background-color`, `font-size`, `object-position` (`setAttrStyleProp`).
Orada `style` özniteliği panelin **deposudur**; stylesheet'e taşımak kontrolü
operatörün elinden alır. Bir de Bootstrap'in veri olarak yazdığı
`progress-bar` genişliği satır içi kalır.

### Blokların Örnek İçeriği Tek Kanondan Gelir

Yeni bir hazır blok yazarken örnek içeriği uydurma; aşağıdaki kümeden seç.
Her blok kendi dünyasını kurarsa palet, aynı siteyi üç ayrı şirket gibi
gösterir.

| Ne | Kanon |
|---|---|
| Sitenin kendi markası | `ACME` |
| Müşteri / logo / referans şirketi | `Vertex`, `TechBase`, `StartMate`, `LaunchLab`, `Northwind`, `PineWorks`, `OrbitSoft`, `Quanta`, `ByteWave`, `Helios` — **sitenin kendi markası müşteri olarak kullanılmaz** |
| Kişi | `Jane Cooper`, `Michael Brooks`, `Emily Turner`, `Daniel Ross` |
| Plan | `Starter`, `Pro`, `Enterprise` |
| E-posta / alan adı | `example.com` (`name@example.com`, `docs.example.com`) |
| Görsel | fotoğraf `picsum.photos`, logo `placehold.co` |

- **Fiyat da örnek içeriktir ve `_sdT()`'den geçer.** Anahtar İngilizce
  kaynak metindir (`_sdT('$199')`), Türkçe karşılığı tr.json'da (`₺199`).
  Ham `'₺199'` yazmak İngilizce siteyi lirayla fiyatlandırır.
- **Para birimi ve dönem tek anahtara gömülmez.** Tutar bir düğüm, `/mo`
  ayrı bir düğüm (`Price` sarmalayıcısının içinde) — fiyat bloklarının deseni
  budur, yeni blok da onu kullanır.
- **`_sdT()` anahtarı İngilizcedir.** Anahtarın içine Türkçe yazmak
  (`_sdT('Studio Paketi')`) çeviriyi imkânsız kılar: Türkçe site de İngilizce
  site de aynı Türkçe metni görür.

### Palet İki Sekmedir: Yapı Taşı ve Hazır Blok

**Bileşenler** sekmesi yapı taşıdır (Layout, Semantic, Bootstrap, Content,
Form, Regions); **Bloklar** sekmesi bitmiş sayfa bölümüdür. Ölçüt uzunluk
değil tür: bırakıldığında onlarca düğümlük bir ağaca patlayan şey bloktur.

- **Yeni blok `_sdBlockPaletteItems()` listesine yazılır, gruplamaya
  dokunulmaz.** `_sdBlockPaletteGroups()` blokları `componentType` önekine
  göre bucketlar (`navbar-`, `hero-`, `cta-` …). Hiçbir öneke uymayan blok
  "Diğer" altında çıkar — palette görünmeyen blok yok demektir. Yeni bir
  aile açıyorsan önekini o tabloya ekle; tek bir bloğu elle yerleştirme.
- **Üç liste tek plumbing kullanır:** `_sdPaletteItemHTML`,
  `_sdPaletteCategoriesHTML`, `_sdWirePaletteItems`, `_sdWirePaletteSearch`
  (bileşen paleti, blok paleti, son kullanılanlar şeridi). Dördüncü bir
  liste eklerken kendi kopyanı yazma. Bağlanmış satır `data-sd-wired`
  taşır, yani aynı kökü iki kez bağlamak dinleyiciyi tekrarlamaz.
- **Son kullanılanlar şeridi Bileşenler sekmesindedir**, hangi sekmeden
  eklendiğinden bağımsız olarak oraya yazar.
- **Sekme şeridi dört başlıkla dolu.** `#sd-panel-tabs` kapsamındaki kural
  büyük harf dönüşümünü kaldırıp yazıyı bir basamak küçültüyor; beşinci bir
  sekme eklemeden önce başlıkların kırpılıp kırpılmadığını ölç
  (`scrollWidth > clientWidth`). Başlığı kısaltarak çözme — onlar paylaşılan
  `_sdT()` anahtarlarıdır.

### Tasarımcı Çubukları Dar Ekranda Etiketini Bırakır, Kontrolünü Değil

992px altında araç çubukları kontrol saklamaz; **etiketini** saklar.
`.sd-tabs-add-txt` ve `.sd-vb-txt` gizlenir, düğme ikonuyla kalır, adı
`title`'da durur. Görünüm çubuğu (`.sd-bp-bar`) ve iki grubu sarmalanır
(`flex-wrap:wrap`), taşan kontrol ikinci satıra iner.

- **Bir kontrolü `display:none` ile yok etme.** Sayfa çubuğu
  `overflow:visible` (Sayfa Ekle açılır menüsü onu gerektiriyor), yani taşan
  kısım kaydırılamaz, kesilir. Çözüm yer açmaktır: etiketi ikona indir,
  dolguyu azalt, gerekirse sar. Kaydet ya da Geri Al gibi bir düğme dar
  ekranda kaybolursa bu bir hatadır.
- **Yeni bir çubuk düğmesi eklerken etiketini ayrı bir `<span>`'e koy**
  (`sd-vb-txt` kalıbı) ve `title` ver; yoksa dar ekranda ikonlaşamaz.
- **Ölçüt `scrollWidth > clientWidth` değil, ekranın dışına taşan öğedir.**
  `overflow:visible` bir kapta scrollWidth taşmayı göstermez. Kontrolleri
  tek tek `getBoundingClientRect().right > innerWidth` ile ara.

### Modal Ölçüsü İşaretlemede Değil CSS'te Durur

Ayarlar modalinin rayı (`.sd-settings-nav`, 230px) ve form paneli
(`.sd-settings-body`, 640px) bir zamanlar satır içi `style` ile
ölçülüyordu; satır içi stil hiçbir media query'ye yenilmediği için dar
ekran kuralı `!important` yığınına dönüyordu. Ölçü, renk ve punto
`style_designer.css`'tedir.

- **Panel/modal iskeletine satır içi ölçü yazma** — genişlik, `max-width`,
  `flex-direction`, dolgu. Bootstrap'ın `flex-column`, `p-4`, `mb-3` gibi
  yardımcıları da aynı tuzaktır: `!important` üretirler ve media query'de
  geri alınmaları gerekir. İçerik kutularında (form satırı, rozet) serbest.
- **992px altında ray yatay şeride döner**, grup başlıkları
  (`.sd-settings-nav-title`) gizlenir. Başlıklar dikey listenin yanında
  anlamlı; her sekme panelinin başındaki kapsam cümlesi aynı bilgiyi
  zaten veriyor, o yüzden bilgi kaybı yok. Yeni bir sekme eklersen hapın
  metnini kısa tut — şeritte yan yana duracak.

### Panel Yerleşimi: Yapışık Panel Akıştan Çıkar, Yerine Ray Kalır

Sol ve sağ panel iki durumda yaşar. **Yerleşik**: `.sd-body` flex akışında,
bugünkü hali. **Yapışık**: `position:absolute`, tuvalin üstüne kayan bir
çekmece; akışta yerine 32px'lik bir ray (`.sd-rail`) durur. Durum
`.sd-body` üzerindeki `sd-dock-left` / `sd-dock-right`, açıklık
`sd-open-left` / `sd-open-right` sınıflarıyla taşınır.

- **992px altında yapışıklık zorunludur** (`sd-dock-forced`), üstünde
  panel başlığındaki düğmeyle seçilir ve `localStorage`'a yazılır
  (`pinegrap_designer_dock_left` / `_right`). Eşik `_SD_DOCK_BP`; ölçüm
  `window.innerWidth` ile yapılır, `matchMedia` ile değil — CSS ile JS'nin
  aynı sayıyı iki yerde taşımaması için tek kaynak o sabittir.
- **Panelleri dar ekranda alt alta dizen bir kural yazma.** 2026-09-16'da
  silinen `@media (max-width:768px)` kuralı tam olarak bunu yapıyordu ve
  tuvali posta kutusuna çeviriyordu. Dar ekran çözümü yapıştırmadır.
- **Yapışık panelin genişliği `clamp(240px, var(--sd-left-width),
  calc(100vw - 64px))`.** Kenar sürükleme hâlâ `--sd-left-width` /
  `--sd-right-width` değişkenlerini yazar, bu yüzden kullanıcının seçtiği
  genişlik iki durumda da geçerlidir. Panele satır içi `width` yazma.
- **Aynı anda tek katman açıktır.** Bir çekmece açılırken diğeri ve alt
  paneldeki tabaka kapanır. Arka karartma (`#sd-dock-backdrop`) **yalnız**
  `sd-dock-forced` altında görünür: geniş ekranda yapışık panel açıkken
  tuval tıklanabilir kalmalı.
- **Alt panel yapışıkken üç şerittir.** `.sd-panel-bottom.sd-docked`
  ızgarayı dikey flex'e çevirir; üç `.sd-bp-quad` başlığı 28px'lik şeritlere
  iner, dokunulan şerit `sd-dock-open` ile açılır ve panel 52vh olur. Şerit
  için ayrı düğme çizme — başlıkların kendisi düğmedir; ikinci bir etiket
  seti `_sdT()` anahtarlarını ve rozetleri çiftler.
- **Ray, palet sekme şeridinden üretilir.** `#sd-panel-tabs` içindeki her
  sekmenin ray karşılığı vardır (`_SD_RAIL_ICONS` ile ikonlanır); sekme
  eklersen ikonunu o tabloya yaz, yoksa `bi-grid` ile çıkar. Ray düğmesi
  sekmeyi `click()` ile seçer, sekme mantığını kopyalamaz.

### Tuvaldeki Öğeden Düğüme: `_sdFindNodeForEl()`

`findNodeById(id, tree)` **yalnız sayfa ağacını** bilir. Widget'ın içindeki
düğüm ortak bileşenin kendi ağacındadır (`_sharedCache[sid].tree`), dolayısıyla
sonuç `null` olur ve çağıran sessizce çıkar. Sağ tık menüsü tam olarak bunun
yüzünden **hiç açılmıyordu** — `preventDefault()` çalışmadığı için tarayıcının
kendi menüsü geliyordu.

Kural: tuval DOM'undan gelen bir `data-sd-id` için `_sdFindNodeForEl(id, el)`,
ebeveyn için `_sdAnyParent(node)` kullanılır. Sıra `data-sw-sid` → sayfa ağacı
→ tüm önbellek; damga zaten ağaçlar arası **kimlik çakışması** için basılıyor.
Aynı hata `[data-modal-section-id]` / `[data-modal-dlg-id]` işleyicilerinde de
vardı.

---

## Seçilen Sayfa Kaydedilene Kadar Öteki Tasarımındır

"Var Olan Sayfayı Seç" sayfayı kopyalamaz ve o an taşımaz: sekme açılır,
`page.page_style` ancak **kayıtta** bu tasarıma geçer. O aralıkta sayfa
sunucu için başka bir tasarımın sayfasıdır.

- **Sekme durumu üçtür, iki değil:** yeni sekme (`page_id === 0`), seçilmiş
  ama kaydedilmemiş (`p.pickedPending`), bu tasarımın sayfası. `page_id > 0`
  tek başına "bu tasarımın sayfası" demek **değildir** — `page_style` şartı
  arayan `page_delete` / `page_detach` bu yüzden sessizce reddediyordu.
- **"Tasarımdan Çıkar" sunucuya gitmez.** Geri alınacak bir şey yok; sekme
  kapanır, sayfa geldiği tasarımda kalır. "Ayır" yeni bir tasarım üretir —
  ödünç alınmış bir sayfa için yanlış cevap odur.
- `pickedPending` temizlenmez: kayıt editörü yeniden yükler, sayfa sunucudan
  bu tasarımın kendi sayfası olarak gelir.
- **`_sdT()` anahtarına değişken gömme.** Çeviri anahtara göre bulunur ve
  bütün metni değiştirir; içine gömülen ad çeviriyle birlikte kaybolur.
  Değişken `{var}` ile girer: `_sdT('It stays in {var} as it was.', owner)`.

---

## Gövde Etiketi: Birleştir, Atma

`<body>`'yi tarif eden **iki** yer var — Gövde Ayarları'ndaki özel kontroller
ve alttaki Öznitelikler paneli — ve `class` / `style` / `id` her ikisinde de
yazılabiliyor. Kural: **birleştir.**

| Parça | Kaynaklar (sırayla) |
|---|---|
| `class` | stil düzeyi "Ek gövde sınıfları" → kök `cssClass` → `_attrs` `class` |
| `style` | `fontFamily` → `inlineStyle` → `_attrs` `style` |
| `id` | kök `id`, boşsa `_attrs` `id` |

`id` istisnadır çünkü tek etiketde iki kimlik birleştirilemez; birinin yetkili
olması gerekir. Gerisinde atmak için sebep yok — **sakladığı şeyi
yayımlamayan bir alan, o alanı reddetmekten kötüdür.**

**Üç yer lockstep:** `generate_style_code_from_tree()` (PHP, frontend'i bu
yazar), `_sdBodyAttrParts()` → `generateHTML()` ve aynı yardımcı → tuval
`renderCanvas()`. Eskiden PHP üçünü de atıyordu, JS yalnız `style`'ı
tanıyordu, tuval yalnız `cssClass` + yazı tipini uyguluyordu: aynı ağaç üç
farklı gövde üretiyordu.

**`page_tree_code` anlık görüntüdür.** Gövde etiketini değiştiren her düzeltme
yalnız yeni kayıtlarda görünür; var olan sayfalar bir kez yeniden
kaydedilmeden yayına çıkmaz.

---

## Hata Yolu da Bir Yoldur — Orada Var Olmayan Metot Çağırma

`liveform` sınıfında **`add_error()` yoktu** ve dört dosyada dokuz yerden
çağrılıyordu: `edit_config.php` (×2), `edit_orders.php`,
`import_design_zip.php` (×5), `includes/designer_screen.php`. Hepsi **boş
gövdeli HTTP 500** üretiyordu.

Dokuzunun da ortak özelliği: **yalnız bir şey ters gittiğinde çalışan kod.**
Bu yüzden yıllarca görülmedi — ve etkisi tam olarak en kötü yerdeydi:
operatör "config dosyası açılamadı" yerine bomboş bir sayfa görüyordu.

Kurallar:

- Sınıfa yeni bir mesaj metodu çağırmadan önce **sınıfta olduğunu doğrula.**
  `mark_error($field, $msg)` alan bazlıdır; form seviyesindeki mesaj
  `add_error($msg)`'dir ve sentetik bir alan anahtarına yazar
  (`_form_error_0`, `_form_error_1`, …) — `output_errors()` alanları dolaşıp
  `error` bayrağı aradığı için ayrı bir liste görünmez, ve tek anahtara
  `mark_error()` yapmak yalnız sonuncu mesajı saklardı.
- **Hata dalını en az bir kez koştur.** Bir özelliği "çalışıyor" saymak için
  mutlu yol yetmez; reddedilme yolu da bir ekran üretir.
- PHP 8'de tanımsız metot `Error` fırlatır ve `@` bastırmaz — sessiz değil,
  **görünmez** bir çöküştür (500 + sıfır bayt).

---

## Not Yazarı Sayfayı Yazan Fonksiyonda Damgalanır

`pg_stamp_note_authors(&$tree, $page_id, $user)` (functions.php),
`pg_designer_save_page()` içinde **birleştirmeden sonra, `json_encode`'dan
önce** çağrılır.

- **`save_system_style()`'da damgalama YOK.** O fonksiyonun kapsamında
  `$page_id` de `$user` da yoktur (yalnız `$user_id`) ve çok sayfalı editör
  ona ağaç göndermez — ağaç sayfaya aittir. Oraya yazılan damgalama sessizce
  hiçbir şey yapmıyordu; belirti "notta yazar adı yok" idi.
- Metni **değişmemiş** not eski damgasını korur; yoksa sayfayı kaydeden kişi
  üzerindeki bütün notları kendi üstüne almış olur.
- Tarayıcının gönderdiği `_notes_by` hiçbir yolda kopyalanmaz.

---

## Yükleniyor Perdesi — Her Ekranda, Tek Yerde (2026.4.4)

`pg_preloader_markup()` (functions.php) `output_header()` içinde, `<body>`'den
hemen sonra basılır. İşaretleme, stil kancası ve betik **birlikte** gelir:
perde, aşağıdaki hiçbir stil ya da betik yüklenmeden önce ayaktadır, çünkü o
noktada başka hiçbir şeye güvenilemez.

- **Perde bir resim değildir.** Sayfanın kendi arka planı (`--bs-body-bg`) +
  üstte ince bir çubuk + ortada marka + üç nokta. Her ekranda, saatte onlarca
  kez görünüyor; kendine ait kişiliği olan her şey üçüncü ziyarette yorar.
  Ortadaki blok 300 ms gecikmelidir — hızlı gelen sayfa yalnız çubuğu gösterir,
  logo dakikada iki kez yanıp sönmez.
- **`PRIVATE_LABEL` açıkken marka basılmaz**, yalnız üç nokta kalır. O modun
  tamamı yazılımın kendini adlandırmaması üzerine kurulu; perde bunun
  istisnası olamaz.
- **Marka `pg_logo_svg($id_suffix)`'ten gelir**, ikinci bir kopya yazılmaz —
  başlık ve perde aynı sekiz kilobaytlık yol listesini paylaşır. İki kopya aynı
  sayfada olduğu için gradyan kimlikleri sonek alır ve sonek **tek
  `preg_replace` geçişiyle** eklenir: dizi verilen `str_replace` her çifti tüm
  dizge üzerinde sırayla koşturur, ikinci geçiş birincinin çıktısına geri girer
  (`pgLogoGradient-plIcon-pl`).
- **Yapay taban yok.** Ölçüt DOM'un hazır olması. Ekran hazır DOM'dan sonra
  kurulmaya devam ediyorsa (Görsel Editör) `window.pgPreloaderHold = true`
  yazar ve boyandığında `window.pgPreloaderDone()` çağırır.
- **Çıkışta da kalkar:** bağlantı tıklaması ve form gönderimi
  `pgPreloaderShow()` çağırır. Filtreler: `target`, `download`,
  `data-bs-toggle`, `#`/`javascript:`/`mailto:`, dış köken, yalnız fragman
  değişimi, değiştirici tuşlar.
- **Takılı kalmanın dört çıkışı olmalı:** DOM hazır / gerçekleşmeyen
  yönlendirmeden sonra ilk etkileşim / zaman aşımı / betik hiç çalışmadıysa
  CSS animasyonu. İlk `hide()` betiğin çalıştığını kanıtlar ve o animasyonu
  **siler** — `forwards` dolgusu kalırsa uzun açık duran sayfada perde bir
  daha hiç açılamaz.
- **`toolbar.php` hariç** (düzenlenen sitenin üstündeki şerit),
  `output_header_secure()` de hariç (modal içi seçici ekranlar).
- Sınıflar `assets/css/backend.src.css` sonundaki *Loading curtain* bloğunda;
  ekrana özel ikinci bir perde yazılmaz.

---

## Özel Form — Kontrol Alandır, Liste Yoktur (2026.4.4)

`custom_form` widget'ında **ayrı bir alan listesi yoktur.** Widget'ın
içindeki her `input` / `select` / `textarea` (gizli girdi dahil;
submit/reset/button/image hariç) sayfanın formunun bir alanıdır ve tanımı
kontrolün üstündedir. Sunucu her kayıtta alan satırlarını widget ağacından
türetir; editör yalnız form düzeyi ayarları gönderir.

| Alan sütunu | Kaynak |
|---|---|
| `name` | `name` özniteliği — panelde "Alan adı" |
| `type` | etiket + `type` (`_pg_cf_type_by_input()` / `CF_TYPE_BY_INPUT`; listede olmayan her `type` text box) |
| `required` | `required` özniteliği |
| `label` | `<label for>`; radyo/onay grubunda kapsayan `<fieldset>`'in `<legend>`'i; yoksa `_cf.label`; yoksa placeholder / aria-label; yoksa ad |
| seçenekler | `<option>`'lar; grupta aynı adı taşıyan kutular |
| `default_value` | `value` özniteliği / textarea metni |
| `validation_regex` | `pattern` özniteliği |
| `multiple` | `select[multiple]` ya da checkbox |

Markup'ın söyleyemediği her şey düğümün **`props._cf`** kaydında: `ids`
(`{page_id: form_fields.id}`), `label` (etiket öğesi silinmişse),
`rss_field`, `contact_field`, `office_use_only`, `validation_message`,
`upload_folder_id`, `quiz_question` / `quiz_answer`.

### Kaynak: yeni form / var olan form

| `form_source` | Anlamı |
|---|---|
| `page` (varsayılan) | Form, widget'ın **durduğu sayfaya** aittir; kontroller onun alanlarıdır. Şablon: bir girdi + CAPTCHA + Gönder (`_cfStarterTree`). |
| `existing` | Başka bir sayfanın formu burada gösterilir. Seçilince widget'ın içi o formun alanlarından yeniden kurulur (`_cfBuildFromFields`); alanlar **salt-okunur**, o formun kendi sayfasında düzenlenir. Öksüz form (`pg_cf_form_is_orphaned`) bu sayfaya taşınabilir. |

`form_source` yoksa: `form_id` doluysa `existing`, boşsa `page`; sayfanın
kendisini gösteren `existing` de `page` sayılır (`_cfWidgetOf().owns` /
`_pg_cf_page_form_widgets()`).

### Tek türetici sunucudadır

- `_pg_cf_controls($tree, $page_id)` alan listesini üretir;
  `pg_cf_reconcile_page_form($page_id, $user_id, $settings)` her sayfa
  kaydında sayfanın kendi form widget'larını okur, `pg_cf_sync_page_form()`'a
  **tam listeyi** verir — silme dahil — ve dağıtılan id'leri widget ağacına
  geri damgalar (`_pg_cf_stamp_ids`). Kayıt cevabı `forms[]` taşır; istemci
  `_cfAdoptIds` ile önbelleğine yazar (kirletmez).
- İstemcideki `_cfFieldsOf()` aynı okumanın ikizidir ve **yalnız paneli
  besler**. Etiket kuralı, tip kuralı ve gruplama kuralı iki tarafta aynı
  olmak zorunda; birini değiştiren ötekini de değiştirir.
- **Kayıt sırası:** `_flushDirtyShared()` bir söz döndürür ve
  `_saveAjaxImpl` onu bekler. Sayfa kaydı widget ağacını sunucudan okuduğu
  için widget güncellemesini geçemez.
- Form widget'ı olmayan sayfanın formuna dokunulmaz — o formu silmek bu
  fonksiyonun işi değil. Widget var ama kontrol yoksa liste boştur ve alanlar
  silinir (uyarıyla).

### `ids` sayfa başına bir haritadır

Widget ortak bileşendir; iki sayfada "yeni form" modunda durabilir ve her
sayfanın kendi satırları vardır. `_cf.ids[page_id]`; sayfaya ait olmayan id
yok sayılır ve ada göre eşlenir. Ad değişince id kalır (gönderimler kopmaz);
kaldırılan kontrolün satırı `deleted` / `lost_values` ile silinir; kopyalanan
metin kontrolü `_cfEnsureNames` ile yeniden adlandırılır ve id'sini bırakır.

**PHP round-trip tuzağı:** `json_decode(…, true)` boş `{}`'ı boş diziye
çevirir, `json_encode` onu `[]` yazar; JS'te `[]` üstüne konan anahtar
stringify'da kaybolur. `_cfRec()` ve `_cfAdoptIds()` diziyi boş sayıp `{}`
ile değiştirir. `_cf` / `ids` hiçbir zaman boş bırakılmaz.

### Adlandırma ve gruplama (`_cfEnsureNames`)

- Aynı `<fieldset>` içindeki **aynı tipteki** kutular bir addır (bir soru);
  fieldset dışındaki kutular kendi adlarını korur — Seçenekler paneli bir
  kutuya seçenek eklerken adı kopyalar, o kutular bilerek tek alandır.
- Adsız kontrol etiketinden ad alır (`_cfSuggestFieldName`); adı başka bir
  metin kontrolünde olan kontrol kopyadır, yeniden adlandırılır.
- Bırakma (`_cfAdoptOnDrop`), çift tık, yapıştırma, varsayılan düzen ve
  kayıt serileştirmesi (`_cfEnsureNamesAll`) aynı fonksiyondan geçer.
- Etiket iki yere yazılmaz: `<label for>` / `<legend>` varsa panel **o
  öğenin metnini** yazar; yoksa `_cf.label`.
- `canDrop`'ta `cf_field` → `semantic`; palet bloğu kendi tipinin gittiği
  yere gider.

### RSS rolleri widget başına tek

Bir rol formda bir alanda: ikinci alana verildiğinde ilkinden alınır ve toast
söyler (`_cfRoleHolder`); sunucu türeticisi de ilk kazanır. Widget paneli
dört rolü ve sahiplerini listeler, "Başlık" verilmemişse uyarır.

### Ziyaretçiden gizle

Legacy anlamıyla: `custom_form.php` alanı POST'tan yalnız formda
`office_use_only=true` gizli alanı varsa **ve** gönderen sayfayı düzenleme
yetkisiyle açmışsa okur; aksi hâlde varsayılan değeri yazar. Render
(`_render_system_widget_custom_form($…, $mode)`) `$mode === 'edit'` +
`check_edit_access()` iken kontrolü bırakır ve gizli alanı basar; ziyaretçide
kontrolü, `for`'u ona bakan etiketi ve boşalan sarmalayıcıyı düşürür.

### Render (`pg_cf_apply_field_bindings`)

- Kontrol `name`'e göre satırla eşlenir (`pg_cf_field_map` küçük harf
  anahtarlı); `name` satır id'sine yazılır (`[]` çoklu), `id`'ye dokunulmaz
  (`<label for>` ona bakar; yoksa uydurulur).
- Öznitelikler çizildiği gibi kalır — kayıt zaten onlardan türedi. Yalnız:
  `title` = `validation_message`; `<select>` seçenekleri alandan basılır
  (legacy ekrandan değişen liste markup'a dokunmadan ulaşsın); başlangıç
  değeri liveform → `?value_<id>` → rehber → klasör adı → varsayılan.
- **Çoklu onay grubunda `required` soyulur** (tarayıcı hepsini ister; sunucu
  "en az biri" der); tek onay kutusu tutar. Radyoda hepsine yazılır.
- Tasarlanan formun altına hiçbir şey eklenmez; `__submit_label` token'ı
  yalnız eski ağaçlar ilk kayda kadar boş basılmasın diye `lang('Submit')`.
- `get_page_content.php`'nin `custom form` dalı widget'ı hem `pregion`'da hem
  `page_tree_json`'da arar — görsel sayfada bölge yoktur.

### Seçenekler elemana aittir, forma değil

`<select>`, radyo ve onay kutusu kendi seçenek listesini taşır ve
"Seçenekler" paneli her yerde açılır.

| Eleman | Liste nerede |
|---|---|
| `<select>` | `<option>` çocukları |
| radyo / onay kutusu | kardeş `.form-check` blokları (`_sdCheckBlocks`) |

- **Paralel bir `_choices` dizisi yazma.** İşaretleme depodur.
- **Grup klonlanır, yeniden yaratılmaz**; kimlik `stem`, `stem_2`, `stem_3`;
  klon şablonun `name`'ini kopyalar.
- **Bir nesne literalinde anahtar tekrarı yok.** Metin okuma `_sdTextOf()`.

### Formu olan sayfa `page_type`'tan tanınmaz

Görsel sayfa `standard` kalır; formu olduğunu `custom_form_pages` satırı +
görsel tasarım olması söyler. Formları sayan/listeleyen her sorgu
`pg_form_page_sql($alias)` kullanır. Sitemap / arama indeksi / legacy render
dalları tür semantiğidir, dokunulmaz. Yeni bir form listesi yazarken
`page_type = 'custom form'` yazma.

### Diğer kurallar

- **Doğrulama iki yerde:** `pattern` tarayıcıda, `custom_form.php`
  `/^(?:…)$/u` ile sunucuda; bozuk ifade yok sayılır. Form `novalidate` +
  `needs-validation`; `pg_cf_validation_script()` sayfa başına bir kez.
- **Sonraki sayfa** form kaydınındır (`confirmation_type='page'` +
  `confirmation_page_id`), widget ayarı değil; var olmayan sayfa mesaja düşer.
- **Boş e-posta gövdesi boş e-posta demektir.** `pg_cf_default_email_body()`
  alanlardan üretir; operatör bir harf değiştirdiği an gövde onundur.
- **Gönderim sonrası formu tekrar çizme** (`?<page_id>_confirmation=true`).
- **Hatalar liveform'dan**, bu forma daraltılmış
  (`_pg_inject_messages_node($tree, (string)$page_id)`).
- **CAPTCHA bölüm bağlamasıdır**; boş dönerse kapsayıcı da düşer.
- `pg_rendered_page()` `get_page.php`'de **erken** kaydedilir.
- `pg_cf_page_form_ready()` — üç tabloyu birden yoklar; yükseltilmemiş
  kurulumda özellik sessizce yok olur.
- Legacy alan ekranı (`edit_field.php`) yine çalışır ama **kayıt tuvaldir**:
  orada değişen `rss_field` bir sonraki tasarım kaydında kontrolün `_cf`'siyle
  ezilir.
- Eski ağaçlar yükte taşınır (`_cfMigrateWidgetTree`: `_bindings.form_field`
  → `name`, `__submit_label` bağlaması → düğme metni) ve ilk kayıtta yeni
  biçimde yazılır.

---

## İmza Alanı — Değerli Olan Çizim Değil, Kayıttır (2026.4.4)

Çekirdek `includes/fn/signature.php`; yakalama `assets/js/signature_pad.js`;
alan türü `form_fields.type = 'signature'` (özel formlarda, `view_fields.php` /
`add_field.php` / `edit_field.php`).

**Bu bir nitelikli elektronik imza (e-imza) DEĞİLDİR** ve 5070 sayılı kanun
anlamında ıslak imzanın yerine geçmez. Ürettiği şey adi elektronik imzadır;
değeri tamamen yanındaki kayıttadır. Bunu ekranda ya da belgede "yasal olarak
ıslak imza ile aynı" diye anlatma.

### İki ayrı soru, iki ayrı cevap — karıştırma

| Soru | Cevap |
|---|---|
| Bu **kayıt** sonradan düzenlendi mi? | `seal` (HMAC, `ENCRYPTION_KEY`) → `pg_signature_verify()` |
| İmzalanan **belge** sonradan değişti mi? | `document_hash` → `pg_signature_document_matches()` |

İkincisi olmadan birincisi yeterli görünür ama değildir: veritabanına hiç
dokunmadan, sözleşme metnini taşıyan `information` alanını panelden düzenlemek
imzayı başka bir belgenin altına taşır. Hash alan tanımlarının tamamını
(etiketler ve `information` içerikleri dahil) **ve** verilen cevapları kapsar;
imzanın kendi değeri dışarıdadır.

**Hash'e giren her şey `form_data`'dan geri okunabilmek zorunda.** Doğrulama
cevapları oradan okuyup yeniden hesaplıyor; imza anındaki değerle bayt bayt
aynı olmazsa gösterge her belgede "değişmiş" der. Hash'e yeni bir şey eklerken
gidiş-dönüşü gerçek bir kayıtla ölç, varsayma.

### Değişmez kurallar

- **Kilit şemadadır:** `UNIQUE (form_id, form_field_id)`. Aynı gönderimin aynı
  alanına ikinci imza yazılamaz — unutulabilecek bir kontrol değil.
- **İmza gösterilir, sunulmaz.** `edit_submitted_form.php` imza için hiçbir
  kontrol çizmez. Öteki alanlar orada düzenlenebilir; düzenlenebilen imza delil
  değildir. Yeni bir ekran imzayı gösterecekse `pg_signature_display()` çağırır.
- **Zaman, adres ve kimlik istekten alınmaz.** `signed_at` sunucu saatidir,
  adres `waf_client_ip()` ile çözülür (CDN arkasında `REMOTE_ADDR` edge'dir).
  İsteğin etkileyebildiği bir değer delil değildir.
- **Tarayıcıdan gelen PNG GD'den yeniden kodlanır**
  (`pg_signature_png_from_data_url`). Data URL saldırgan denetimindedir; hem
  geçerli PNG hem geçerli PHP olan bir dosya GD'den geçince ikisi birden olmayı
  bırakır. Boyut kod çözmeden önce reddedilir.
- **Görüntü `files` satırıdır** (`attachment = 1`, `optimized = 1`), `form_data`
  ona `file_id` ile bakar. Base64'ü `form_data.data`'ya yazma — o kolon aranan
  içeriktir.
- **Çizgi verisi (`strokes`) saklanır**: yapıştırılmış bir resimle gerçek bir
  imzayı ayıran tek şey hareketin sırası ve zamanlamasıdır.
- **Düzen üreteci markup değil çağrı basar**
  (`<?=pg_signature_field($custom_form_fields['<id>'])?>`). Pad bir canvas, bir
  gizli girdi ve bir düğmenin birbiriyle ve betikle anlaşmasıdır; markup basmak
  o üçlünün eski bir sürümünü her sayfada sonsuza kadar taşır.
- **`touch-action: none` süs değil:** onsuz telefonda canvas üstünde sürüklemek
  sayfayı kaydırır ve hiçbir şey çizilemez.
- **Alanın etiketi onay cümlesidir** ve `consent_text` olarak saklanır. Uzun
  metin ayrı bir `information` alanına yazılır — o da hash'e girer.
- `pg_signature_ready()` tabloyu **ve** enum değerini birden yoklar (köprü
  kuralı): kodu alıp DB'yi yükseltmeyen kurulumda alan türü hiç sunulmaz.
- **Kanıt makbuzu hash'in kapsadığı kümeyi gösterir, fazlasını değil**
  (`signature_receipt.php` → `pg_signature_receipt_html()`). "Belge değişmemiş"
  satırının anlamı olması için gösterilen ile hash'lenen aynı olmak zorunda;
  `pg_signature_document_parts()` ve `pg_signature_document_matches()` aynı
  okumayı yapar. Hash'e bir şey eklersen makbuza da ekle.
- **Belge değişmişse uyarı en üstte olur.** Makbuzun geri kalanı belgenin
  bugünkü hâlidir; imzalanan hâl o değilse okuyucu bunu okumadan önce bilmeli.

- **Alan belirteci (`^^alan_adi^^`) imzayı basar, adını değil.** Tek nokta
  `prepare_form_data_for_output()`'un `signature` dalı; gönderi görünümü, liste
  görünümü ve onay ekranı oradan geçer. HTML isteyen çağrıda `<img>`, düz metin
  isteyen çağrıda adres — hiçbirinde kontrol değil.
- **Sorgu dizesindeki ayraç `h()`'ye `&` olarak verilir.** Zaten `&amp;`
  yazılmış bir adres `h()`'den `&amp;amp;` olarak çıkar, tarayıcı `amp;x` adlı
  bir parametre gönderir ve uç nokta aradığı değeri boş bulur. Belirti: adres
  elle yazılınca çalışan, tıklanınca 404 veren bağlantı. **Bağlantı tıklanarak
  test edilir**, adres çubuğuna yazılarak değil.

### Zaman damgası (RFC 3161)

- **Damgalanan değer üçüncü tarafın yeniden hesaplayabileceği bir şey olmalı:**
  `sha256(document_hash \n image_hash \n signed_at)`, üçü de makbuzda basılı.
  Mührü damgalamak işe yaramaz — HMAC anahtarı bizde, dışarıdan kanıtlanamaz.
  Tarif değişirse makbuzdaki açıklama da değişir.
- **Damga olduğu gibi saklanır** (base64). Yeniden kodlamak üstündeki imzayı
  bozar; doğrulama bir RFC 3161 aracının işidir, makbuz dosyayı o yüzden
  indirtir.
- **Ağ çağrısı istek akışındadır ve bu bilinçli bir istisnadır.** Pazaryeri
  kuralının tersi: orada gönderilen bir durumdur, burada bir andır. Bedel 6
  saniyelik zaman aşımıyla sınırlı ve başarısızlık **imzayı reddetmez** —
  damgasız saklar ve site günlüğüne yazar.
- **Ayar `config` tablosunda, `data/config.php`'de değil.** Dört sütun
  (`signature_tsa_url` / `_auth` / `_username` / `_password`), `init.php`
  sabitleri oradan tanımlar (`WAF_ENABLED` deseni, kolon yoksa boş adres).
  Kart: Site Ayarları → Özellikler → İmza Zaman Damgası.
  **`SIGNATURE_TSA_URL` boşken hiçbir istek yapılmaz**; özellik kapalı gelir.
- **Ayarları sınayan bir düğme sabitleri okuyamaz.** Kayıttan sonra koşar ve
  sabitler hâlâ sayfanın açıldığı andaki değerleri taşır — okursa bir önceki
  adresi dener. `pg_signature_request_stamp()` bu yüzden `$settings` alır;
  `&$reason` de başarısızlığı ekrana taşır, günlüğe yazmakla yetinmez.
- **Kimlik biçimi listesinde yalnız çalışan biçimler durur.** Bugün "Yok" ve
  "HTTP Basic". Kamu SM kimliği isteğin içinde taşıyor ve kodlaması
  doğrulanmadı; doğrulanana kadar listeye eklenmez — çalışmayan bir seçenek
  olmayandan kötüdür.
- **Ücretsiz damga gerçek ve doğrulanabilirdir ama "nitelikli zaman damgası"
  değildir.** Ekranda ve belgede o unvanı kullanma; lisanslı sağlayıcı kontör
  karşılığı satar ve aynı ayardan tanımlanır.
- **ASN.1 elle yazıldı** çünkü PHP'nin `openssl` eklentisi zaman damgası
  fonksiyonlarını açmıyor ve `openssl` komut satırı paylaşımlı hostta kapalı
  olabiliyor (`disable_functions` kuralı). DER okuyucusu octet string'lerin
  içine de girer — damganın imzalı içeriği bir octet string'tir.

### `form_fields.type` enum'una değer eklemenin istisnası

Aşağıdaki "Palet 'Form' Grubu" kuralı `form_fields.type` enum'una bir şey
eklenmemesini söyler ve gerekçesi doğrudur: paletteki bilinmeyen bir tip text
box'a düşer, ve bu kabul edilebilir. **İmzada kabul edilebilir değil** — legacy
alan ekranları tipi bu kolondan okuyup neyi çizeceğine, neyi doğrulayacağına ve
nereye yazacağına karar veriyor, ve imzanın text box'a düşmesi imza yerine boş
bir metin kutusu demek. Bu yüzden `'signature'` enum'a eklendi.

Eklerken enum **var olan tanımdan genişletilir**, elle yeniden yazılmaz: kolon
yıllar içinde değer topladı ve literal bir liste, o satırdan sonra eklenen
değeri sessizce düşürür (`upgrade_2026_4_4_signature_field()` örnek).

---

## Sistem Widget — Sayfanındır, Türü Kimliğidir (2026.4.4)

Editör tek sayfa varken yazıldı; bir tasarım artık N sayfa. Bir
`shared_components` satırı birden çok sayfada durabilir (bilerek: aynı giriş
formu iki sayfada), ama bunun bedeli **bir sayfadan yapılan tür değişikliği
ötekini yeniden yazar**. Kurallar:

- **Palet türleri sunar.** `SW_TYPES` (style_designer.js) tek listedir: tür,
  Türkçe etiket, ad sluğu, ikon. "Yeni" grubu, panelin tür seçimi ve otomatik
  ad hep buradan. Yeni bir widget türü eklerken bu listeye girer; `_swTypeDefaults`
  türün başlangıç anahtarlarını verir (tür değiştirme ve yaratma aynı fonksiyonu
  kullanır — iki liste yazma). Tür yalnız `_swIsType` ile kabul edilir.
- **Ad `<sayfa>-<tür>-widget`** (`_swAutoName`, `_swSlug`; adsız sekme
  `sayfa`). Boş widget `<sayfa>-widget`; tür seçilince ad hâlâ otomatik
  kalıptaysa (`_swIsAutoName` — sunucunun ` [n]` sonekiyle birlikte)
  yeniden adlandırılır, operatörün yazdığı ad dokunulmaz. Panelde ad bir
  girdi (`#sd-sw-name` → `_swRename`). Sunucu tekilliği `_sc_unique_name`
  ile korur; istemcide sayaç tutma ("Sistem Widget 1 [11]" bunun eseriydi).
- **Boş widget boş görünür.** `regionType === ''` panelde "— İçerik türü
  seçin —" yer tutucusudur (`_typeUnset`); ilk türü seçilmiş gibi gösterme.
  `typeof regionType !== 'string'` olan eski satırlar form listesidir —
  renderer'ın varsayılanı, o fallback yalnız onlar için.
- **Tür değişikliği kapılı.** `_swOtherPlacements(sid)` boş değilse
  (`_sharedRefUsageInDesign` → bu tasarımın öteki sekmeleri, kaydedilmemişler
  dahil; `_sharedUsage` → öteki tasarımlar) üç seçenek: kopya
  (`_swForkForThisPage`: yeni satır, aktif sayfadaki yerleşimler ona çevrilir,
  `_cf.ids` soyulur — satır id'leri orijinalin sayfasına aittir), hepsinde
  değiştir, vazgeç (`data-sw-current` ile eski değere dön). Üç düğmeli soru
  `sdChoose`'dur; `pgConfirm` iki düğmelidir, üçüncüyü iki soruya bölme.
- **Bütün sekmeleri yürü.** Widget silme, yeniden adlandırma, "nerede
  kullanılıyor" — hepsi `_pgAllPageTrees()` üzerinden (aktif sekme için canlı
  `tree`, ötekiler için park edilmiş `p.tree`). Yalnız `tree`'yi yürüyen kod
  öteki sekmelerde "Bulunamadı" yerleşimi bırakır. Öteki sekme mutasyona
  uğradıysa `_pgTabsRefreshDirty()`.
- **`usage_all` satırı `page_id, page_name, style_id, style_name` taşır.**
  4.22'de sorgu sayfaya taşınırken `page_id AS style_id` kalmıştı;
  `_pgDesignSharedIds`'in `u.style_id === styleId` karşılaştırması hiç
  tutmuyordu. Kullanım metni `_sharedUsageText`: sayfa adı, başka
  tasarımdaysa parantezle tasarım.

### Sayfa seçiciler: bu tasarım önce, kaydedilmemiş sekme `tab:<key>`

Widget ayarındaki her sayfa seçici `_swPageOptions(cur, sunucuListesi,
{types, test, none, noneValue})` ile çizilir, değeri `_swPageVal(el)` ile
okunur (pozitif id | `tab:<key>` | 0). "Bu tasarım" optgroup'u `_pages`
sekmelerini listeler — `types` verilmişse `_swTabHasWidget` o türde widget
taşıyan sekmeleri (sunucu listesinin ikizi; form detay için ek `test`) —
kaydedilmemiş sekme `tab:<key>` değeriyle. Yeni bir sayfa seçici yazarken
elle `<option>` üretme; `parseInt(this.value)` bir `tab:` değerini 0 yapar.

Çözümleme iki yerde, aynı kural: sunucu `pg_designer_resolve_tab_refs()` /
`pg_designer_resolve_tab_values()` (designer_screen.php) sayfalar id
aldıktan sonra widget satırlarını ve formun `confirmation_page_id`'sini
yazar — bu yüzden `pg_cf_reconcile_page_form` sayfa döngüsünün **dışında**,
`$id_map` dolduktan sonra çalışır; istemci `_swResolveTabRefs(keyMap)`
`_pgTabsAfterSave` içinde, sekme anahtarları `p<id>`'ye çevrilmeden **önce**.
Eşleşmeyen anahtar (kapatılmış sekme) 0'dır. Renderer'a hiçbir şey eklenmez:
her okuma `(int)` yapar, `tab:` 0'dır.

### Bootstrap: `[title]` olan toggle'a popover bağlanmaz

`backend.src.js` `$('[title]').popover()` ile başlığı olan her öğeye ipucu
bağlar. Bootstrap 5 öğe başına **tek** bileşen örneği tutar; popover önce
bağlanınca `data-bs-toggle="dropdown"` düğmesinin Dropdown örneği
yaratılamaz ("doesn't allow more than one instance per element"),
`clearMenus` `getInstance` null bulur ve menü **hiç kapanmaz**. Seçici
`dropdown / tab / pill / button / tooltip / popover` toggle'larını dışarıda
bırakır. Yeni bir toggle'a ipucu gerekiyorsa sarmalayıcıya ver, düğmeye
değil.

### Seçenekler paneli satırı ve bölümü

- `row(label, ctrl)`: etiket sütunu 92px, **sarar**; `nowrap` yazma, uzun
  etiket kontrolün altına giriyordu.
- `sect()` (`.sd-props-section`) ile `as()` (`.sd-app-sect`) aynı başlık
  kuralını kullanır: başlığın altında girintili çizgi. Bölüm sarmalayıcısına
  kendi kenarlığını verme — iki bölüm ailesi iki farklı çizgi çizer.

## Üyelik Widget'ları — Legacy Uç, Tasarımcı Form (2026.4.4)

`login_form`, `forgot_password`, `registration`, `membership` (2026-09-15,
`docs/_plan_uyelik_widgetlari.md`). Gerekçe `docs/degisiklikler.md`.
Kurallar:

- **Widget legacy işleyiciye post eder.** Giriş `<software>/index.php`,
  sıfırlama `forgot_password.php`, kayıt `registration_entrance.php`,
  üyelik `membership_entrance.php` (ikisi de `register=true`). Oran
  sınırı, kilit, adres sızdırmayan bildirim, cihaz
  sınırı orada yaşar; ikinci bir işleyici (`member_action.php` gibi)
  **açılmaz**. Widget'a özel ihtiyaç o dosyaya parametre olur: `return_to`
  = hatada dönülecek tasarlanan sayfa (`pg_safe_redirect_path` ile),
  `send_to` = başarı hedefi. İkisi ayrı sorudur; birini ötekine katlama.
- **Kayıt ve aktivasyonun gövdesi `pg_member_register($form, $opts)` ve
  `pg_member_activate($form, $opts)`'tır** (`includes/fn/auth.php`); legacy
  ekran da widget da onları çağırır. Doğrulama ve yazma sırası legacy'nin
  birebir taşınmasıdır — oraya yeni bir kural eklemek iki ekranı birden
  değiştirir, bilerek. `username` gönderilmemişse e-postadan türetilir
  (`field_in_session` ile "yok" ile "boş" ayrılır); aktivasyon adres
  alanını `email_address` ya da `email` adıyla okur (hangisi geldiyse).
  `$opts['contact_fields']` (sütun ⇒ değer) `pg_cf_contact_fields()`
  listesine karşı süzülür; sütun adı **asla** POST'tan okunmaz — form gizli
  `pg_widget_id` gönderir, `pg_member_widget_register_opts($sid, $form,
  $type)` sütunları o widget'ın kendi `tree_json`'ındaki
  `_cf.contact_field`'lardan çözer ve başka türün id'sini reddeder.
  Aktivasyonda `member_id` / `expiration_date` üye listesinin verisidir,
  form yazamaz.
- **Kontrol alandır, ad sözleşmedir.** Widget içindeki
  `input`/`select`/`textarea` formun alanlarıdır; `name` legacy'nin adıdır
  (`email`, `password`, `remember_me`, `screen`). Sunucu kontrolü adıyla
  bulur (`_pg_member_control_name`), doldurur (`_pg_member_fill_control` —
  parola hiç dolmaz), kapalı özelliğin kontrolünü düşürür. Düğme
  `<button type="submit">`; `<a href="#">` düğme değildir.
- **Sunucu formu sarar** (`_pg_member_form_wrap`): `<form action=…
  method="post">` + `get_token_field()` + gizli alanlar. Tasarımcı form
  etiketi çizmez; özel formla aynı model.
- **Kapalı özellik = çıktı yok.** `REMEMBER_ME` / `cfg.show_remember_me`
  kapalıyken `remember_me` kontrolü **etiketi ve boşalan sarmalayıcısıyla**
  düşer (`_pg_member_drop_controls`: `<label for>` id ile eşlenir, yalnız
  düşeni tutan `<div>` de gider). Boş token'a bağlı `href` taşıyan bağlantı
  düşer (`_pg_member_drop_empty_links`). Boş bölüm kapsayıcısı
  `pg_cf_apply_section_bindings` ile zaten düşüyor. Ölü `href=""`,
  inert kontrol, gizli `display:none` yok — yokluk.
  **Tuval ikizi `_sdMemberBindingOff(node, cfg)`** (style_designer.js):
  renderer'ın düşürdüğü her şeyi soluklaştırır ve sebebini rozetle söyler
  (`data-sd-off-reason`: "Kapalı — site ayarı" / "Kapalı — ayarlar
  panelinden açın"). Site anahtarları `sdDesign.siteFlags` ile gelir
  (`includes/designer_screen.php`; remember_me, forgot_password_link,
  password_hint, captcha, strong_password, google). Renderer'a yeni bir
  düşürme kuralı yazan bu fonksiyona da yazar — `_sdCatalogBindingDisabled`
  ile aynı lockstep. Etiket `for` üzerinden kontrolünü izler; her çocuğu
  düşen sarmalayıcı da soluklaşır, tek çocuğu kalan sarmalayıcı düz kalır.
- **Üyelik formu da `.pg-cf-form`dur:** `_pg_member_form_wrap` özel formla
  aynı `novalidate` + `needs-validation` sınıflarını basar ve
  `pg_cf_validation_script()`'i ekler. Betik sayfa başına bir kez çıkar,
  `DOMContentLoaded`'ı bekler ve sayfadaki **her** `.pg-cf-form`u bir kez
  bağlar (`data-pg-validated`); ilk formun içinde hemen koşan eski hâli
  yalnız kendi formunu görüyordu.
- **Hata ve bildirim Messages düğümünden**, işleyicinin liveform adıyla
  (`login`, `forgot_password`, `register` — `registration` değil,
  `membership_entrance`). Düğüm mesajı basar ve formu tüketir; bu
  yüzden renderer liveform'dan okuyacağını (`email`, `screen`,
  `remember_me`) **Messages düğümünü eklemeden önce** okur ve ekran
  çizildikten sonra `$lf->remove()` der (legacy ekranlar da öyle; bayat
  bir deneme sonraki ziyareti şekillendirmez).
  `$_SESSION['login_error']` türü anahtarlar hiç var olmadı; yeniden
  icat etme.
- **Widget her durumda kendi ağacını çizer — ağacı atlayan dal yok.**
  Girişli ziyaretçi, onay ekranı, "kapalı özellik" — hepsi ağacın
  budanmasıdır (`_pg_member_render_signed_in`: kontroller, bağlantılar ve
  bölümler düşer, söz `add_notice` ile liveform'a yazılır ve **widget'ın
  Messages düğümünde**, tasarımcının koyduğu kapsayıcının içinde çıkar).
  Ağacın yerine düz bir `<div class="alert">` döndüren renderer, mesajı
  container'sız, sayfanın kenarına yapışık basar — bu hata bir kez yapıldı
  ve kullanıcı yakaladı. Yeni bir durum eklerken de aynı: ağaç kalır,
  içerik değişir.
- **Mesaj kendi widget'ının Messages düğümünde çıkar, başkasınınkinde
  değil.** Aynı sayfada birden çok Messages düğümü olabilir (iki widget +
  sayfa düzeyi). Widget içindeki düğümün `formName`'ini renderer o
  işleyicinin liveform adıyla damgalar (`_pg_inject_messages_node($tree,
  '<ad>')`) ve düğüm yalnız o formu basıp tüketir; düğüm yoksa widget'ın
  ilk container'ının başına eklenir. Sayfa düzeyindeki düğüm
  (`<!--pg-messages-placeholder-->`, `get_page_content.php`) widget'lar
  çizildikten **sonra**, kalan her şeyi basar — widget'lar kendi
  mesajlarını tüketmiş olduğu için oraya sızmaz. Sıra bu yüzden
  değişmez: `_expand_system_widgets` önce, placeholder sonra. Yeni bir
  widget renderer'ı yazarken bu çağrı zorunludur — damgasız (boş
  `formName`) bir düğüm sayfadaki **her** formu basar ve iki widget'lı
  sayfada ötekinin hatasını gösterir.
- **Token = adres/ad; bölüm = sunucunun ürettiği blok.** Giriş: bölümler
  `google_signin`, `forgot_password_link`, `register_link`; token'lar
  `__site_name`, `__redirect_url`, `__forgot_password_url`,
  `__register_url`, `__google_signin_url`. Sıfırlama: bölüm
  `password_hint`; token'lar `__site_name`, `__password_hint`, `__back_url`.
  Kayıt: bölümler `captcha`, `password_rules`, `google_signup`,
  `login_link`; token'lar `__site_name`, `__login_url`,
  `__google_signup_url`, `__password_rules` (HTML), `__opt_in_label`.
  Üyelik: kayıtla aynı eksi `captcha`, artı `__member_id_label`; Google
  `'membership'` modunda ve Google dönüşünde (`google_membership_pending`)
  çizilmez, o dönüşte parola `required` değildir.
  Kendi görünümünü isteyen tasarımcı bölüm yerine token'a `href` bağlar.
  Google girişten **yalnız giriş** (`pg_google_signin_url($send_to,
  'none')`), kayıt widget'ından `'create'`. `pg_google_signup_allowed()`
  kayıt/üyelik widget'ı taşıyan canlı sayfayı da entrance sayar
  (`pg_member_widget_published`).
- **Kayıt ve üyelik widget'ında kontrol ya çekirdek ya rehber sütunudur.**
  Çekirdek adlar `MEMBER_CORE_FIELDS` (JS) ↔ `pg_member_register()` /
  `pg_member_activate()`'in okuduğu adlar — ikisi lockstep. Öteki her kontrol Options → Üyelik Alanı → Rehber
  bağlantısı ile bir `contacts` sütununa bağlanmadıkça **saklanmaz**. Kayıt
  özel formun `_cf.contact_field`'ıdır, ikinci bir alan uydurma. Panel
  `_memberFieldSection` — özel formun `_cfFieldSection`'ı `custom_form`'a
  bağlıdır, onu genişletme.
- **Sayfa adı iki yerden yazılır, biri açıkken ötekini ezme.** Yeni sayfa
  sekmede satır içi ad kutusunu açar (`_pgTabsRename`); `#sd-page-name`
  işleyicisi o kutu açıksa etiketi `textContent` ile yeniden yazmaz, kutunun
  değerini yazar. Sebep: sökülen odaklı öğeye Chromium **hâlâ bağlıyken**
  `blur` basar, kutu boş değerini geri uygular ve yazılan ad silinir;
  `isConnected` bunu yakalayamaz.
- **Sayfa seçicileri** (`redirect_page_id`, `forgot_password_page_id`,
  `register_page_id`, `login_page_id`) `_swPageOptions` / `_swPageVal`
  ile; `types` ilgili widget'ı taşıyan sekmeleri süzer. Kayıt bağlantısı üç
  durumlu tek seçici: yok / `legacy` (`show_register_link: true`,
  `register_page_id: 0`) / sayfa. Renderer `(int)` okur; `tab:` çözümlemesi
  `pg_designer_resolve_tab_values`'ın genel yürüyüşündedir.
- **Girişli ziyaretçi** giriş widget'ında form değil "zaten giriş yapmış"
  bildirimi + Devam Et / Güvenli Çıkış görür; `$mode === 'edit'` formu
  gösterir ki sayfa üzerinde çalışılabilsin. Dispatch
  (`_expand_system_widgets`) bu iki renderer'a `$mode` geçirir.
- **`screen` sıfırlamanın durum makinesidir**: `''` form, `password_hint`
  ipucu + aynı form (gizli `screen=password_hint` ile yine gönderir),
  `confirm` kontroller düşer, bildirim kalır, form sarılmaz. Onay bildirimi
  bilinen/bilinmeyen adres için aynıdır (2026-08-31 kararı) — widget
  tarafında "adres bulunamadı" üretme.

## Tasarımcıda İçerik Rolleri (2026.4.4)

Çekirdek `includes/designer_access.php`. İki seviye:

| Seviye | Kim | Ne |
|---|---|---|
| `full` | yönetici (0), tasarımcı (1) | Editör her zamanki hâliyle |
| `content` | manager (2), kullanıcı (3) | Metin + `props._editable` işaretli blokların içi |

### Değişmez Kurallar

- **Ortak bileşen / sistem widget'ı `content` seviyesine hiç açılmaz** —
  işaretli alanın içinde bile. Ağacı `shared_components`'ta durur ve o
  bileşeni kullanan her sayfaya aittir. Tasarımın `style_custom_*`, tema ve
  gövde sınıfları da aynı sebeple yazılmaz.
- **Tarayıcı neyin GÖSTERİLECEĞİNE, sunucu neyin YAZILACAĞINA karar verir.**
  `_sdMayEdit(node, what)` nezakettir; kapı `pg_designer_save_page()` içindeki
  `pg_designer_merge_restricted_tree()`.
- **Kayıtta reddetme yok, kopyalamama var.** Kayıtlı ağaç yürünür; işaretli
  alanın içinden bütün alt ağaç, dışından yalnız `text` alınır. Gerisi atılır —
  kopyalanmayan değişiklik olmamıştır. Farkları bulup reddetmek, bir ağacın
  farklılaşabileceği her yolu saymayı gerektirirdi.
- **Çocuklar konuma göre eşleşir, `_id`'ye göre değil.** İşaretli alanın
  dışında yapı meşru olarak değişemez; ve istek gövdesinden gelen bir id'ye
  güvenmek gerekmez.
- **Kabul edilen alt ağaçtaki `_editable` bayrakları soyulur** (kökteki
  kayıtlı düğümden yeniden basılır): yoksa operatör kendi yetkisini genişletir.
  Alt ağaçtaki `shared_ref`'ler kayıtlı hâlleriyle geri konur.
- **Sayfanın kimliği içerik değildir.** Ad / klasör / ana sayfa işareti kayıtlı
  satırdan geri yazılır — **mükerrer-ad kontrolünden önce**, yoksa operatörün
  seçmediği bir ad yüzünden kayıt reddedilir. Başlık, açıklama, anahtar
  kelimeler düzenlenebilir kalır.

### Düğüm Başına Sorulan Soru Ağacı Taramaz

`_sdCanEditNode` her düğümde soruluyor ve cevabı `_sdInEditableArea` veriyor.
İlk hâli yukarı doğru `findParent` ile yürüyordu; `findParent` her çağrıda
**bütün ağacı** tarar, yani soru O(n²) oldu: 200 düğüm 1 ms, 500 düğüm 3 ms,
1000 düğüm **17 ms** — tuval ve ağaç ayrı ayrı sorduğu için boyama başına iki
katı. İçe aktarılmış bir sayfa bini rahat geçer.

Cevap artık ağaç değiştiğinde bir kez kurulan bir `WeakSet`'ten geliyor
(`_sdEditableSet`, işaret yukarıdan aşağı devrediliyor): 1000 düğümde 0 ms.
Küme `saveState()` ve `render()` içinde geçersizleşir — biri her mutasyondan,
öteki her boyamadan önce çalıştığı için arada bayat kalamaz.

**Kural:** render sırasında düğüm başına çağrılan bir yüklem, ağacı yürüyen
bir yardımcıyı çağıramaz. Cevabı bir geçişte hazırla.

### Not Yazarı Sunucuda Damgalanır — Ama Her Kayıtta Değil

`pg_designer_save_page()` yalnız **metni değişmiş** notu yeniden damgalar;
değişmemiş not `_notes_by` / `_notes_at` değerlerini korur. Aksi hâlde sayfayı
kaydeden kişi, üzerindeki bütün notları kendi üstüne almış olurdu.
Tarayıcının gönderdiği `_notes_by` birleştirmede hiç kopyalanmaz. Not düzenleme
penceresi, metin değiştiğinde eski damgayı siler ki liste bir sonraki yüklemeye
kadar yanlış adı göstermesin.

`_notes` birleştirmede **işaretli alan dışında da** taşınır: not bir açıklamadır,
sayfa içeriği değil — işbirliği özelliği görüntüleme modundaki oturuma bile not
bırakmayı bilerek verir.

### Yetkiyi Soran Yerler, Cevaplayan Yerden Fazladır

`_sdCanEditNode(node, what)` **sessiz** yüklem, `_sdMayEdit` onu saran ve
reddederken toast basan hâli. **Render sessizi, etkileşim konuşanı çağırır** —
tuvali çizerken `_sdMayEdit` çağırmak düğüm başına bir bildirim demektir.

Soran yerlerin tamamı: iki sağ tık menüsünün her mutasyon maddesi
(`_sdGuardItem`), ağaçtan sürükleme (`draggable` **ve** bırakma hedefi),
Delete/Backspace, `handleDrop`, `onDelete`, satır içi metin düzenleme, tuval
araç çubuğundaki tutamaç ve sil düğmesi, `renderProperties`. Yeni bir mutasyon
yolu eklerken bu listeye de ekle; ilk sürümde menüler ve ağaç sürüklemesi
unutulmuştu ve kullanıcı ilk denemede sayfanın tamamını silebildi.

`_sdGuardItem` maddeyi **kaldırmaz, soluklaştırır**: kaybolan menü maddesi
"yazılım bozuk" diye okunur, soluk olan "bunu ben yapamıyorum". Çoklu seçimde
hepsi ya da hiçbiri — beşin üçünü silmek operatöre istemediği bir sayfa
bırakır.

### İçerik Seviyesinde "Her Zaman Onun Olan" İki Şey

Metin **ve görsel**. Sunucu birleştirmesi işaretli alan dışında yalnız
`props.text` ve görselin **adresini** kopyalar (`_pg_dm_take_image_src`);
boyut, sınıf, alt metin kayıtlı hâlinde kalır. Arka plan görseli dışarıda —
o biçimlendirmedir. Gerekçe: başlığı değiştirebilen ama üstündeki fotoğrafı
değiştiremeyen operatöre işin yarısı verilmiş olur.

### Birleştirmede Çocuklar `_id` ile Eşleşir

Konum eşleştirmesi bir kopyalama karşısında kırılıyordu: işaretli alan
dışında çoğaltılan bir düğüm sonraki bütün kardeşleri bir kaydırıyor ve
kayıtlı B, gönderilen A'nın metnini alıyordu. Kopya doğru şekilde düşüyordu —
hasar altındaki her şeydeydi.

İstek gövdesinden gelen id'ye güvenmek burada bedava: işaretli alan dışında
kopyalanan tek şey metin ve görsel adresi, ikisini de operatör o düğümde
zaten değiştirebilir.

### Gövde Sınıfları Sayfaya Aittir

Kök düğümün `cssClass` / `id` / `_attrs`'ı `generate_style_code_from_tree()`
tarafından `<body>`'ye basılır ve tasarımcının Attr paneli `<body>` seçiliyken
bunları zaten düzenletir. Tasarım seviyesindeki "Ek gövde sınıfları" ayarı
**kaldırıldı**: tanımladığı şey bir sayfanın gövdesiydi ama ayar tasarım
geneliydi, dolayısıyla hiçbir sayfa kendi sınıfını alamıyordu. Eski değer ilk
kayıtta her sayfanın kök `cssClass`'ına taşınır ve sütun temizlenir — düşürmek
canlı bir sitenin biçimini soyar, bırakmak düzenleyicisi olmayan bir değeri
uygulamaya devam etmek olurdu.

### `locked` ile `hidden`

`pg_designer_page_access()`: açık klasördeki düzenlenemez sayfa **listelenir,
açılmaz** (operatör zaten var olduğunu biliyor); özel/üyelik klasöründeki
sayfa **hiç listelenmez** — adının kendisi bilgidir.

### `_sdInEditableArea` `_sdAnyParent` Kullanır

`findParent(n, tree)` ortak bileşenin kendi ağacında null döner ve devralma
ilk düğümde durur: işaretli bir bloğun her çocuğu işaretsiz görünür.

### `api.php` Gövdede Kullanıcı Adı ve Parola Kabul Eder

Panelin XHR'ı için değil: `migration.php` başka bir kurulumun `api.php`'sine
bu şekilde bağlanır (bkz. `send_api_request()`). `barcode_*_inventory.php` da
aynı yolu taşır. `integration.php` (anahtar/gizli anahtar) bunun modern
karşılığıdır; `api.php`'nin bu yolu yerinde bırakıldı çünkü taşıma ekranının
tek bağlantı yöntemi.

İki sabit, iki ayrı şey söyler ve **karıştırılmamalıdır**:

| sabit | anlamı |
|---|---|
| `API_USERNAME` | gövdede bir kullanıcı adı **gönderildi** |
| `API_AUTHENTICATED` | parola **doğrulandı** (yalnız `initialize_user()` tanımlar) |

`validate_token()` CSRF jetonunu **yalnız `API_AUTHENTICATED` varsa** atlar.
`API_USERNAME`'e bakan bir kontrol, başka bir sitenin gövdesine uydurma bir ad
koyarak jetonu atlatmasına izin verir — `api.php` gövdeyi `php://input`'tan
okuduğu için `Content-Type` engel değildir, `enctype="text/plain"` bir form
yeter ve ön kontrol isteği doğmaz.

Aynı sebeple `initialize_user()`'ın beni-hatırla dalı `API_USERNAME` taşıyan
istekte **hiç çalışmaz**: kimliğini gövdesinde söyleyen istek yalnız onunla
yanıtlanır, çerezle değil.

Parola denemesi `pg_login_throttle_guard()` / `pg_login_record_failure()` ile
sayılır — giriş ekranlarıyla aynı kova. Buraya yeni bir parola kabul eden uç
eklersen sayacı da eklemek zorundasın; yoksa giriş ekranındaki kilit o uçtan
atlatılır. `pg_login_throttle_deny()` `API_USERNAME` varken json döner.

Güvenlik duvarı tarafında her iki uç da `waf_handle_rate_sensitive()`'in
dışındadır, çünkü ikisinin de **kendi** kovası vardır: `integration.php`
uygulama başına (`includes/api/ratelimit.php`), `api.php` adres başına
(`waf_rate_limit_api`, `waf_handle_rate_api()`). `waf_rate_limit_sensitive`
giriş/ödeme ölçüsüdür ve `api.php`'ye uymaz — açık sohbet penceresi tek başına
dakikada 30 istek atar. Bot sınıfı muafiyeti ikisinde de kalır; taşıma çağrısı
başka bir sunucudan gelir.

**`api.php`'ye yeni bir ziyaretçi ucu eklerken hız için ayrıca bir şey yapman
gerekmez** — kova eylem adına değil ucun kendisine bağlıdır. Eylem listesi
tutsaydık listeye yazılmayan yeni bir mağaza eylemi sessizce 30/dk'ya düşerdi.

### `api.php`'nin Başında Genel Bir Rol Kapısı Var

`switch ($action)`'dan **önce**, muafiyet listesi olan bir blok: listede
olmayan her eylem için `USER_ROLE > 1` ise "The User must have a Designer or
Administrator role." Bir `case` bloğunun içine yazdığın izin listesi, istek
oraya ulaşmadığı sürece hiçbir şey yapmaz.

`designer` bu yüzden muafiyet listesine eklendi; kararı kendi izin listesi
veriyor (`presence`, `leave`, `lock_release`, `note_save`, `page_load`,
`seo_check`, `import_fragment`, `form_fields` — gerisi reddedilir).

**Belirti:** "aynı sayfayı iki kişi açtık, uyarı çıkmadı" — varlık sinyali
hiç gönderilemiyordu. Aynı sebeple SEO denetimi ve tuval yapıştırması da
sessizce ölüydü.

### Okuma ile Yazma Ayrı Kapılardır

`shared_component` ucu tamamen kapatılınca, widget taşıyan bir sayfa içerik
rolüne **34 satır yerine 2 satır** açıldı: widget'ın ağacı olmadan sayfa
çizilemiyor. Okuma uçları (`list`, `get`, `prefetch`, `usage_all` ve üç liste
ucu) açık kalır; yazan her şey rol ≤ 1'dir.

### Yeni `explorer_*` Alt Eylemi İki Yere Yazılır

`view_folder_and_files_f.php` içindeki `pg_explorer_handle()` switch'ine bir
`case` eklemek **yetmez**: `api.php`'nin `file_explorer` dalı alt tipleri
**açık bir izin listesiyle** karşılıyor (`case 'explorer_list': case
'explorer_tree': …`). Listede olmayan tip `pg_explorer_handle()`'a hiç
ulaşmaz.

Belirti tanıdık olmayan bir belirtidir: istek **HTTP 200** döner ve **gövdesi
boştur**. Ne hata sayfası, ne JSON, ne de error.log'da bir satır — çünkü
ortada hata yok, yalnızca hiçbir dalın eşleşmediği bir switch var. İstemci
tarafında bu `$.ajax`'ın `error` dalına düşer ve ekranda "İstek başarısız
oldu" yazar, yani yanlış yere baktırır.

Adı `explorer_catalog_` ile başlayan tip ayrıca ticaret kapısından geçer
(rol ≤ 2 ya da `manage_ecommerce`); adlandırma bu yüzden anlamlıdır.

### JSON Konuşan Uçta Ret de JSON Olur

`validate_area_access()` HTML hata sayfası basar. Bu oturumda üç ayrı yerde
aynı hata yapıldı (görsel kaydetme ucu, CSRF, tasarımcı API kapısı): JSON
bekleyen çağıran "unexpected token <" alır ve operatöre hiçbir şey söylenemez.

### Erişilemeyecek Yolu Teklif Etme

Karşılama ekranı içerik rollerine üç tasarım-oluşturma kartı gösteriyordu;
`add_system_style.php` zaten tasarımcı istiyor. Bir düğme ya çalışır ya da
orada olmaz.

### İçerik Kabuğu Gizleyeceği Şeyden Sonra Çalışmalı

`_sdApplyContentChrome()` boot'ta bir kez çağrılıyordu ama palet ve ayar
penceresi o noktadan sonra kuruluyor. Fonksiyon idempotent; boot'ta ve
300/1200 ms sonra tekrar uygulanır.

### `api.php` Rol Karşılaştırmaları

`shared_component` ucu `role != 0 && role != 2` diyordu — 2'nin tasarımcı
sanıldığı eski tabloya göre yazılmış; manager'a izin verip tasarımcıyı
reddediyordu. Rol karşılaştırması yazarken CLAUDE.md'deki tabloya bak:
**0=Administrator, 1=Designer, 2=Manager, 3=User.**

## Görsel Düzenleyici — Bağlanabilir Araç (2026.4.4)

Pintura üç parçaya ayrıldı; her ekran istediğini alır:

| Parça | Ne |
|---|---|
| `image_editor_save.php` | JSON uç noktası. `{token, file_name\|file_id, mode:'replace'\|'copy', type, data}` → `{name, url, width, height, size}` |
| `assets/js/image_editor_modal.js` | `window.openImageEditor({src, fileName, token, saveUrl, onSaved})` — tek modal, `codemirror_modal.js` sözleşmesi |
| `get_image_editor_includes()` | Pintura + modal + `window.pgImageEditorLocale` (lang()'den) |

`image_editor_edit.php` duruyor: içerik bağlamından (ürün/reklam/bölge/takvim)
açılan `object_type` mekanizması ona ait ve taşınmadı.

### `clean_up.php` Listesi Canlı Dosya Adı Taşıyamaz

`clean_up.php` içindeki `$file_list` "güvenle silinebilir" diyor ve Temizlik
aracı onu harfiyen uyguluyor. `image_editor_save.php` orada bir LiveSite
kalıntısı olarak duruyordu; **aynı ad sonradan Pintura düzenleyicisinin ucuna
verildi.** Temizliği çalıştıran operatör canlı bir ucu sildi ve o andan sonra
görsel düzenleyicideki her kayıt 404 döndü.

Belirti hiçbir yerde sebebe bağlanmıyor: araç "temizlendi" diyor, düzenleyici
"Görsel kaydedilemedi. (404)" diyor, error.log'da satır yok — çünkü ortada
hata yok, dosya yok.

Listeye ad eklemeden önce hiçbir şeyin onu çağırmadığı doğrulanır:
`grep -rn "<ad>" --include=*.php --include=*.js .` Bir adı yeniden kullanmak
da aynı kapıya çıkar: eski bir adı yeni bir dosyaya vermeden önce listede olup
olmadığına bakılır.

Aynı dosya `data/temp`'i **dizin olarak** da süpürüyor, yani oraya konan her
yeni dosya sorulmadan silinecekler listesine girer. Orası bunun için doğru
yer — hepsi yeniden üretilen önbellek — ama yeniden üretilmeyen ya da yeniden
üretildiğinde **aynı** dosya olmayan bir şey konacaksa `$temp_keep`'e yazılır
(`hash_reference.json` örneği yukarıda).

### JSON Gövdede `pg_post_body_discarded()` Kullanma

Fonksiyon "`$_POST` boş + `Content-Length` dolu" durumunu gövdenin atıldığı
sayar. Form gönderiminde doğru; **JSON gövdede her zaman doğru**, çünkü PHP
`$_POST`'u JSON'dan doldurmaz. Küçücük bir PNG 413 dönüyordu.

Ham akışa bak: `php://input` gerçekten atılmış gövdede boştur.

### CSRF Reddi de İstenen Biçimde Olmalı

`validate_token_field()` süresi dolmuş oturuma `output_error()` ile HTML basar;
JSON bekleyen çağıran "unexpected token <" alır. JSON uç noktası aynı kuralı
(oturum jetonu karşılaştırması) kendi uygular ve 403 + JSON döner.

### Yazma Kuralları

- Temp dosya + `rename()`.
- `files.image_width/height` **her iki yolda da** yazılır (sütunlar
  `SHOW COLUMNS` ile yoklanır), `optimized = 0`.
- Tasarım dosyası (`design = 1`) **her rolde** reddedilir — `image_editor_edit.php`
  da reddediyor, iki giriş noktası aynı dosyaya farklı cevap veremez.
- Hareketli GIF üzerine yazılmaz; yalnız yeni dosya.

### Tasarımcı Tarafı: Sözleşme Alandır, Düğüm Değil

Düğme `sdOpenImagePicker` ile aynı sözleşmeyi kullanır — "hangi input'u
dolduruyorum". Görsel adresi taşıyan her alan düğmeyi alır; araç düğüm bilmez.
Görsel düğümü `src`'si ve arka plan görseli bu yüzden birer satırla çalıştı.

- **Değiştir:** ad değişmediği için ağaca dokunulmaz. Önbellek kırıcı **yalnız
  çizilmiş `<img>`'e** yazılır; ağaca yazmak eskiyen bir adres saklamak olurdu.
- **Yeni dosya:** input'un `change` işleyicisi düğümü yazar; toast tasarımın
  da kaydedilmesi gerektiğini söyler.
- **Görsel, tasarımın kaydedilmesini beklemez.** Dosya diskte bir nesnedir;
  fotoğraf düzenlemesini ilgisiz bir Kaydet düğmesine bağlamak düzenlemeyi
  kaybetme yoludur.

### Metin Alanına Yazarken: innerHTML mi, Düz Metin mi

| Düğüm | `props.text` nasıl basılır |
|---|---|
| `content` (paragraph/heading) | innerHTML — yazan taraf `esc()` yapar |
| `semantic` | tuvalde metin düğümü, `generateHTML()`'de `esc()` — ham yazılır |

Karıştırmanın iki yönü de sessizdir: biri etiketleri uygular, öteki ziyaretçiye
`&lt;div&gt;` yazdırır.

### `<pre>` / `<textarea>` / `<code>` İçinde Boşluk İçeriktir

`generateHTML()` (asıl derleyici; `buildNodeHTML` ikinci yol) her etiketin
içine girinti + satır sonu koyar. Bu üç etikette o boşluk ziyaretçinin
gördüğü boşluktur — tek satırda, içine hiçbir şey eklenmeden yazılırlar.
Yeni bir boşluk-duyarlı etiket eklersen `_semWsTags`'e ekle.

**Test notu:** Pintura sentetik `button.click()` görmez — `doka:process` hiç
doğmaz. Gerçek işaretçi tıklaması gerekir.

## HTML İçe Alma — Üç Giriş, Tek Dönüştürücü (2026.4.4)

`includes/designer_import.php` HTML'i düğümlere çevirir. Üç yol onu besler:

| Yol | Uç nokta | Sonuç |
|---|---|---|
| Dosya (.html / .zip) | `designer_import.php` (multipart) | Sayfa + varlıklar + dosyalar |
| Yapıştırılan sayfa | `api.php designer/import_paste` | Sayfa + varlıklar, dosya yok |
| Tuvale yapıştırma | `api.php designer/import_fragment` | Yalnız düğümler |

`api.php` gövdeyi **JSON** okur; dosya taşıyan yol bu yüzden ayrı betikte.
Cevabı sekmelere çeviren tek yer `_pgImportApply()` — ikinci bir kopya yazma.

### Yapıştırma İçe Aktarma Değildir

`pg_designer_import_fragment()` (`$ctx->fragment = true`):

- **Diske yazmaz.** Satır içi `<svg>` dosyaya değil `custom_html`'e gider
  (Bootstrap ikonu yine ikon düğümü olur). Bir Ctrl+V, Dosya Yöneticisinde
  dosya bırakmamalı.
- **`<style>` / `<script>` sayılır ve atılır**, tasarımın ortak varlıklarına
  **eklenmez**: tek yapıştırma tasarımın her sayfasını değiştirirdi ve varlık
  paneli geri alma yığınında değil. Kaç blok atlandığı toast'ta söylenir.
- **Çözülemeyen adres olduğu gibi kalır.** `about.html` → `/about` yeniden
  yazması yalnız arşivin sayfa listesi varken doğrudur; yapıştırmada tahmin,
  çalışan bağlantıdan ayırt edilemeyen bozuk bağlantı üretir.

### Tek Ctrl+V, İki Pano

Panoda ya burada kopyalanmış düğümler ya dışarıdan gelmiş işaretleme vardır.
Ayrım **tuşa** göre değil **panonun içeriğine** göre yapılır:

- İç kopyalama sistem panosuna da yazar: `/*pinegrap-nodes*/` + düğüm JSON'u
  (`_sdClipCopy`). Yan etki: iki editör penceresi arasında kopyala-yapıştır.
- `paste` olayı bu öneki görürse düğüm, görmezse işaretleme yapıştırır.
- **Keydown'daki `preventDefault()` koşulludur** (`if (_sdClipStamp) return;`).
  Koşulsuz olsaydı `paste` olayı hiç doğmaz, bir kez düğüm kopyalayan
  operatör oturum boyunca dışarıdan yapıştıramazdı. Yazma reddedilirse
  (güvensiz bağlam / izin yok) eski bellek içi yol aynen çalışır.

### Metin Kovası

`p`, `pre`, `code`, `h1`–`h6` ve `paragraph`/`heading` içerik düğümleri
(`_sdIsTextSink`): oraya yapıştırılan işaretleme `esc()`'ten geçip **metin**
olur. Ölçüt işaretlemenin kendisi olamaz — kod örneği ile sayfaya konacak
gerçek blok bayt bayt aynıdır; ayıran tek şey operatörün hedefidir.
Satır içi metin düzenlemede de yapıştırma düz metne indirgenir (kaynak
sitenin span'leri ve renkleri sızmasın).

### Nereye Yapışır

Seçili düğüm çocuk tutabiliyorsa **içine**, tutamıyorsa **yanına**
(`_sdPasteTarget`). İç düğüm yapıştırması kardeş ekler — orada niyet
çoğaltmaktır. Yapıştırılan kontroller `_cfAdoptOnDrop()`'tan geçer.

## Tasarımcı Paneli Elemanı Yanlış Tanıtamaz (2026.4.4)

Panel, seçili elemanın **ne olduğunu** söyler. Söylediği şey yanlışsa
tasarımcı yanlış olanı düzeltmeye çalışır ve genelde bozar.

- **Her desteklenen etiket kendi grubunda olmalı.** `propsSemantic()`
  etiket gruplarından birine düşürür; grubu olmayan etiket `structural`'a
  düşer, kendi etiketi listede olmadığı için `<select>` ilk seçeneği
  gösterir. `<label>` seçince kutuda "Section" yazıyordu ve tek tık onu
  gerçekten `<section>` yapıp formun etiketini kaybettiriyordu. Gruplar:
  `structural` / `text` / `interactive` / `list` / `formish` (label,
  fieldset, legend, output) / `optionish` (option, optgroup).
- **HTML varsayılanlarını panelde yeniden tanımlama.** `isButtonInput`
  listesinde `''` vardı; tipi yazılmamış bir `<input>` HTML'de `text`'tir.
  İçe aktarılan işaretlemede tip sık sık yok ve o girdiler Düğme panelini
  alıp Ad / Değer / Zorunlu satırlarını kaybediyordu.
- **Bir attribute'un satırı, o attribute'u taşıyan her elemanda olmalı.**
  "Değer" satırı on altı girdi tipinin sekizinde vardı; Attr panelinden
  yazılan varsayılan gösterilecek satır bulamıyor, panel "değer boş"
  diye okunuyordu. Metin alanının varsayılanı ise attribute değil metin
  düğümüdür (`props.text`) — onun da satırı olmalı.
- **İki panel aynı yeri yazıyorsa biri değişince öteki yeniden çizilir.**
  `_sdSchedulePropsRefresh()` 400 ms geciktirir ve odak `#sd-properties`
  içindeyse atlar (yoksa yazarken imleç kaçar).

### Tuvalde Seçim `renderCanvas()` Çağırmaz

Girdi/düğme tıklaması capture aşamasında `mousedown` ile yakalanır ve
`renderSelectionOnly()` çalışır — sınıfları çevirir, yan panelleri çizer,
tarayıcının o an yürüttüğü etkileşime dokunmaz. `click` dalı form
kontrollerinde bilerek çıkmaya devam eder: node'u orada yeniden çizmek
Chromium'un sentetik `change`'ini düşürüyor (tema seçici ondan besleniyor).

### `label[for]` İki Yönden Korunur

| Ne | Nerede |
|---|---|
| id değişti → etiket taşınır | `_sdRetargetLabels(oldId, newId)` — sayfa ağacı **ve** her önbellekli ortak bileşen ağacı; sahibi kirli işaretlenir |
| `for` hiç yazılmadı → sunucu eşleştirir | `pg_cf_link_labels()`, `pg_cf_apply_field_bindings()`'den **sonra** (id'ler ancak orada kesinleşir) |

Sunucu tarafı yalnız **tek etiket + tek kontrol** olan sarmalayıcıda bağlar.
Birden fazlası varsa hangisine ait olduğu tahmindir; **yanlış `for`, eksik
`for`'dan kötüdür**. `type="hidden"` etiketlenmez.

### Radyo/Onay Grubu Tek Alandır

Grup **`<fieldset>`** ile tanımlıdır: aynı fieldset içindeki aynı tipteki
kutular bir addır, `<legend>` sorunun etiketidir (`_cfEnsureNames`,
`_cfLeadOf`). Her kutuyu ayrı benimsemek üç cevaplı bir soruyu üç evet/hayır
alanına bölüyor ve gönderim tek değer yerine üç değer saklıyordu. Fieldset
dışındaki tek kutu (KVKK onayı) kendi alanıdır. Paletin "Onay grubu" /
"Radyo grubu" blokları bu yüzden fieldset + legend ile gelir.

Alanın tipi kontrolün kendisidir; panelde tip seçimi yoktur. Tipi değiştirmek
kontrolü değiştirmektir (Input paneli `type`, ya da başka bir palet bloğu).

### Palet "Form" Grubu Saf Kontroldür

`CF_PALETTE` / `CF_PALETTE_ORDER` (style_designer.js): metin, e-posta,
telefon, sayı, web adresi, şifre, çok satırlı metin, açılır / çoklu liste,
onay kutusu, onay grubu, radyo grubu, anahtar, tarih, saat, tarih ve saat,
dosya, renk, aralık, gizli alan, kayan etiketli, etiket, alan grubu, gönder,
sıfırla, CAPTCHA, form. Her biri `div > label + kontrol` (grup: `fieldset >
legend + kutular`), Türkçe varsayılan etiket, tohum `name`. Yeni bir kontrol
türü eklenirken palete girer; **`form_fields.type` enum'una bir şey eklenmez**
— listede olmayan her `type` text box'tır ve `custom_form.php` onu doğrular.

### Genel Bakış Ağacı Panelden Düzenlenen Şeyi Listelemez

`_sdTreeFolded()`: `select` / `datalist` / `optgroup` çocuklarının tamamı
`<option>` ise satır yaprak olur, yanına "N seçenek" rozeti gelir. Seçenekler
Seçenekler panelinden düzenlenir; beş seçenek beş satır demek, tek sorunun
iki yanındaki alanları ekrandan itmek demektir.

Aynı gürültünün ikinci kaynağı: bir bloğun sarmalayıcısına, etiketine **ve**
kontrolüne aynı `customName`'i yazmak. Alanın adını yalnız sarmalayıcı taşır.

### Aynı Sayıyı İki Yerde Hesaplayan Kod Aynı Grupları Beslemeli

`pg_seo_compose($meta, $structure, $link, $speed)` — parametreler isteğe
bağlı ve eksik argüman **hata vermez**, sessizce başka bir ağırlık tablosu
seçer. `api.php designer/seo_check` iki argümanla çağırıyordu (60/40),
`pg_seo_recalculate()` ise dördüyle: tasarımcı 48, sayfalar listesi 46.

Canlı kontrol artık dördünü besliyor. Meta ve yapı tuvalden; **bağlantı
(`page.seo_link_score`) ve hız (`pg_seo_evaluate_speed`) kayıtlı satırdan
okunur** — ikisi de kaydedilmiş işaretlemeden ve gelen trafikten ölçülür,
kaydetmeden önce bilinemez. Panel dört grubu da yazar.

## Express Order — `recipient_loop_area` Node Type (Multi-Recipient)

**Amaç:** Çoklu alıcılı sepetlerde her ship_to satırı için designer-customizable tasarım şablonu. Tek alıcıda mevcut `has_single_recipient` ile address row görünür; çoklu alıcıda eskiden server-rendered `_eo_render_shipping_section` (designer customization yok). Yeni `recipient_loop_area` node type ile designer her alıcı için kendi şablonunu çizebilir.

### Designer kullanımı

1. Express Order sistem widget seç (sağ panel)
2. **Hızlı Sipariş Tasarımcı Düzeni → Çoklu Alıcı Tasarımı** bölümünde **"Alıcı Listesi Bloku Ekle"** butonu
3. Widget kökünde teal-band ile `Alıcı Listesi` node'u belirir; istediğin yere sürükle, içine input/heading/text ekle
4. Form girişlerinde standart `shipping_first_name`, `shipping_last_name`, `shipping_address_1`, `shipping_city`, `shipping_state`, `shipping_zip_code`, `shipping_country`, `shipping_phone_number`, `shipping_company`, `shipping_salutation` eo_field bağlamasını kullan — sunucu her alıcı için `shipping_<rid>_*` şeklinde yeniden adlandırır

### Server-side pipeline (functions.php)

- `_eo_tree_has_recipient_loop_area($tree)` — tespit
- `_eo_split_recipient_loop_area($tree)` — template'i ayırır, `<!--pg-recipient-loop-slot-->` marker bırakır
- `_eo_compute_recipient_tokens($recipient, $index, $count, $lf)` — per-recipient token map
- `_eo_render_recipient_loop($template_children, $recipients, $lf)` — her alıcı için clone + field bindings + token replace

**Render adımları:**
1. Tree'de `recipient_loop_area` varsa → `$_eo_has_recipient_loop = true` + `$_eo_shipping_skip_addr = true` (server-rendered address fields suppress; kargo/teslim tarihi extras yine render edilir)
2. Visibility + section bindings uygulandıktan sonra **field bindings çağrılmadan önce** template ayrılır (yoksa `recipients[0]`'ın rid'i tüm iterasyonlara burn-in olur)
3. Static tree normal render
4. Her recipient için: clone template → `_eo_apply_field_bindings(rid)` → `_eo_prefill_input_values_in_tree` → `_eo_apply_action_bindings` → render → token replace
5. Concatenated HTML `<!--pg-recipient-loop-slot-->` marker'a inject

### Binding token'ları

**Per-recipient text/href tokens (heading/paragraph/span için):**

| Token | Açıklama |
|---|---|
| `^^__recipient_index^^` | 1-based sıra (1, 2, 3, ...) |
| `^^__recipient_count^^` | Toplam alıcı sayısı |
| `^^__recipient_id^^` | ship_tos.id (manuel id'ler için) |
| `^^__recipient_name^^` | ship_to_name → first+last fallback → "Alıcı N" |
| `^^__recipient_address_first_name^^` | shipping_<rid>_first_name (read-only) |
| `^^__recipient_address_last_name^^` | shipping_<rid>_last_name |
| `^^__recipient_address_company^^` | shipping_<rid>_company |
| `^^__recipient_address_street^^` / `^^__recipient_address_address_1^^` | shipping_<rid>_address_1 |
| `^^__recipient_address_address_2^^` | shipping_<rid>_address_2 |
| `^^__recipient_address_city^^` | shipping_<rid>_city |
| `^^__recipient_address_state^^` | shipping_<rid>_state |
| `^^__recipient_address_zip^^` / `^^__recipient_address_zip_code^^` | shipping_<rid>_zip_code |
| `^^__recipient_address_country^^` | shipping_<rid>_country |
| `^^__recipient_address_phone^^` / `^^__recipient_address_phone_number^^` | shipping_<rid>_phone_number |

**Form input bağlaması (input/select/textarea):**

- `_bindings.eo_field = 'shipping_first_name'` → sunucu `shipping_<rid>_first_name` adıyla emit eder
- `_bindings.eo_field = 'shipping_country'` özel: 240 ülke option list'i sunucu tarafından inject edilir

### Visibility flag'leri

| Flag | True koşulu |
|---|---|
| `has_single_recipient` | Cart needs shipping AND **tek** recipient |
| `is_multi_recipient` | Cart needs shipping AND **>1** recipient |
| `has_shipping` | Cart en az 1 shippable item içeriyor |

Çoklu alıcıya özgü bir bloku `_bindings.eo_visible_if = 'is_multi_recipient'` ile sar.

### Geriye Dönük Uyumluluk

- **Mevcut sayfalar etkilenmez** — `recipient_loop_area` tree'de yoksa eski server-rendered `_eo_render_shipping_section` aynen çalışır
- **Default tree'ler değişmedi** — "Varsayılan Düzeni Yükle" hala mevcut tek-recipient odaklı şablonu yükler
- **Single-recipient + recipient_loop_area:** template bir kez render edilir (loop count = 1)
- **recipient_loop_area + statik shipping_* row birlikte:** birini `eo_visible_if='is_multi_recipient'`, diğerini `eo_visible_if='has_single_recipient'` ile koşullandır

### Yapısal Kurallar

- 1 widget içinde **en fazla 1 adet** `recipient_loop_area`
- Sadece `regionType='express_order'` sistem widget içine drop edilebilir (canDrop guard)
- İçinde başka bir `recipient_loop_area` veya `loop_area` kullanılamaz
- Silinebilir / kopyalanabilir (zorunlu değildir; designer istediği zaman kaldırabilir)
- POST anahtarları sunucu walker tarafından üretilir: `shipping_<rid>_<field>` — `submit_order.php`'nin recipient validator döngüsü zaten beklediği format

---

## Görsel İşleme — Tek Motor, İki İş (2026.4.4)

**Piksellere dokunan tek yer `pg_process_image_file()`** (functions.php). İkinci
bir sıkıştırma yolu açma; hangi kodun çalıştığı çağrı yerine göre değişirse
aynı dosya iki ekranda iki farklı sonuç verir.

| İş | Ne yapar | Kim ister |
|---|---|---|
| optimize | Metadata siler, **aynı piksel boyutunda** yeniden sıkıştırır | Varsayılan; `max_dimension` verilmeyen her çağrı |
| resize | Uzun kenarı tavana indirir, sonra sıkıştırır | Yalnız açıkça istendiğinde |

**Küçültme asla ima edilmez.** Tam ölçüsüyle yerleştirilmiş bir tasarım
görselinin "optimize et" düğmesinden sağ çıkması gerekir.

### Değişmez Kurallar

1. **Sonuç orijinalden büyükse hiçbir şey yazılmaz** — küçültmede bile. Palet
   PNG ölçeklenince palete sığmayan kenarlar üretir ve *küçük resim daha ağır
   dosya* olur. İki düğme de "daha hafif dosya" sözü veriyor.
2. **Yazma temp dosya + `rename()`.** Dosyanın üstüne doğrudan yazmak, disk
   dolarsa yarım bir görsel bırakır — ve `files.name` o dosyanın genel
   adresidir.
3. **EXIF yönü etiketle değil pikselle çözülür.** Etiketi strip'ten sonra geri
   yazmak yalnız her okuyucu ona uyduğu sürece çalışır; GD zaten hiç yazmıyordu.
   Belirti: "optimize ettim, telefon fotoğrafı yan döndü".
4. **Renk uzayı yalnız CMYK ise çevrilir.** Koşulsuz `setColorspace(RGB)`
   dönüştürmez, *etiketler* — sRGB görseli lineer diye etiketlemek solgun renk
   demektir.
5. **Hareketli GIF'e dokunulmaz** (`pg_image_is_animated_gif()`). İki kütüphane
   de ilk kareyi okuyup durağan yazar; ekranda sebebini söyleyen hiçbir şey
   olmaz.
6. **Bellek önce hesaplanır** (`pg_image_ensure_memory()`). GD küçültme
   sırasında piksel başına 4 bayttan iki kopya tutar; 24 MP fotoğraf ≈ 200 MB.
   128 MB limitte bu yavaşlık değil, isteğin ortasında fatal error — yükleme
   yolunda operatörün gördüğü **boş sayfadır**. Limit yalnız bu istek için ve
   1 GB tavana kadar yükseltilir; `-1` yapmak tek yüklemenin değil kutunun
   düşmesi demektir.
7. **Küçültme ölçütü bayt değil piksel.** `pg_image_can_be_resized()`. Düşük
   kaliteli 6000 px fotoğraf 1 MB'ın altında olabilir ve yine altı kat
   geniştir; ağır bir 1200 px ekran görüntüsünün piksel kaybetmekten kazanacağı
   yoktur.
8. **Eşik hedefin altına inemez** (`pg_image_settings()` kıstırır) — yoksa düğme
   küçültmenin hiç değiştirmeyeceği görsellerde teklif edilir.

### Ayarlar (config, defansif)

`pg_image_settings()` / `pg_image_settings_ready()` — `pg_page_noindex_ready()`
kalıbı. **Altı sütun birden yoklanır** (yükseltme altı ayrı `ALTER` çalıştırır).
Sütunlar yoksa gömülü varsayılanlar geçerli olur ve Ayarlar'daki blok gizlenir.

| Ayar | Varsayılan | Ne |
|---|---|---|
| `image_product_optimize` | 1 | Ürün yükleme boru hattı açık mı |
| `image_product_max_dimension` | 2400 | Ürün fotoğrafı uzun kenar tavanı |
| `image_product_min_dimension` | 500 | Uyarı eşiği (Merchant Center 2027) |
| `image_file_resize_trigger` | 2560 | Dosyalarda küçültme düğmesi bu üstünde çıkar |
| `image_file_max_dimension` | 1920 | Küçültme hedefi |
| `image_resize_quality` | 82 | **Yalnız gerçekten küçültülürken.** Düz optimize eski boyut-merdivenini korur (85/75/65/60) |

### Ürün Yükleme Yolu

`add_file.php` opt-in `image_profile=product` alanıyla çalışır — **yalnız** ürün
ve varyant seti ekranları gönderir. Başka her yükleme (dosya yöneticisi, seçici
dropzone'u, `editor_select_image.php`) baytı baytına gelir gibi kaydedilir.

- Kayıt satırı yükleme anında `optimized = 1` + `image_width/height` ile yazılır
  → Dosyalar ekranı alınmış bir kazancı vaat eden rozet göstermez.
- **Kitaplıktan seçilen görsele hiç dokunulmaz.** O dosya bir düzine başka
  sayfada kullanılıyor olabilir. Tek uyarı noktası ürünün SEO Ayrıntısı paneli
  (`pg_seo_render_catalog_image_note()`), ve o **skoru etkilemez** — Merchant
  Center kullanmak zorunlu değil.

### `files.image_width` / `image_height` Bir Önbelleştir

Yalnız boşken doldurulur (`pg_file_image_dimensions()`). **Dosyanın piksellerini
değiştiren her kod bu ikisini de yazmak zorunda** — döndürme dalı yazmıyordu ve
çeyrek tur en/boyu değiştirdiği için liste eski ölçüyü gösteriyordu. Küçültme
düğmesi artık bu sütunlara baktığı için eskimiş değer görünür hataya dönüşür.

### Yükleme Sınırları — Boş `$_POST` Bir Hatadır

**`post_max_size` aşılırsa PHP gövdeyi betik çalışmadan önce atar.** `$_POST` ve
`$_FILES` boş gelir, tek iz error log'daki bir uyarıdır. Bir yükleme betiği bunu
"henüz POST yok" sanıp formu yeniden çizer ve **200** döner — tarayıcı için bu
başarıdır. Belirti: *"dosyayı seçiyorum, yüklendi diyor, yükleme yapmıyor."*

`pg_post_body_discarded()` bunu `Content-Length` üzerinden yakalar ve
`add_file.php`'de `init.php`'den sonraki ilk iştir. **POST alan her yeni betikte
bu kontrol olmalı.**

Buna bağlı iki kural:

1. **JSON isteyen bayrak gövdede olamaz.** `jsonreturn` gövdedeydi; gövdenin
   kaybolduğu istek tam da JSON cevabına en çok ihtiyaç duyulan istekti. Sorgu
   dizesinde gider (`add_file.php?jsonreturn=true`), `X-Requested-With` yedektir,
   cevap **413**'tür.
2. **`ini_get()` dizgi döner.** `"8M"` ile bir dosya boyutunu karşılaştırmak
   PHP'de `8` ile karşılaştırmaktır. Daima `pg_ini_bytes()`.

### `pg_upload_limits()` — Üç Tavan

| Ayar | Aşılırsa |
|---|---|
| `post_max_size` | Gövde tamamen atılır, betik hiçbir şey göremez |
| `upload_max_filesize` | Dosya hata koduyla + boş `tmp_name` ile gelir |
| `max_file_uploads` | Fazla dosyalar **hata kodu bile olmadan** düşer |

`request_max` = `post_max_size` − 64 KB (multipart zarfı, alan adları, token da
gövdenin içinde).

**Sınırı aşmamak, sınırı büyütmekten iyidir** — sınır sunucunun ve operatör
çoğu zaman onu değiştiremez:

- Çok dosyalı yükleme **parçalara bölünüp sırayla** gönderilir
  (`uploadBatches()` / `sendBatch()`). Paralel göndermek aynı baytları aynı anda
  tele koyar. Bir parçanın hatası sonrakileri düşürmez — `sendBatch()` asla
  reject etmez.
- Tek başına `upload_max_filesize` üstündeki dosya **gönderilmez.**
- Yükleme ekranları ve Ayarlar ekranı sınırları **yazar.**

### Tarayıcıda Ön-Küçültme — Yalnız Sığmayan Dosya İçin

**Sığan dosyaya dokunulmaz.** Sunucu Lanczos ile ve orijinal baytlardan yeniden
kodlar; canvas'ın yapacağı her şeyden iyidir. Ön-küçültme yalnızca sunucunun
reddedeceği dosya içindir — *gelen kötü kopya, gelmeyen iyi kopyadan iyidir.*

**Yön, buradaki tek gerçek tehlike.** Canvas'ın metadata'sı yoktur: tarayıcı
`Orientation` etiketini uygulamadan çözerse yukarı çıkan şey yan bir resimdir
ve sunucunun düzeltmesinin okuyacağı etiket de artık yoktur.
`ImageBitmapOptions` üyesi feature-detect **edilemez**, bu yüzden tarayıcıya
doğrudan sorulur: `Orientation=6` etiketli 2×1 JPEG, uyan tarayıcıdan 1×2
döner (`orientationApplied()`, bir kez, önbellekli). Uymayan tarayıcıdan JPEG
küçültmesi istenmez; PNG/WebP yön taşımaz.

Kodlama sırası **önce kalite, sonra piksel** — 2400 px/q0.60, 1400 px/q0.85'ten
iyi okunur. PNG'de kalite kolu yok, yalnız piksel hareket eder. Hiçbir
kombinasyon sığmazsa dosya **gönderilmez**, adıyla bildirilir.

`shrinkTo` / `shrinkQuality` sunucunun kullanacağı sayıların **aynısıdır**
(`pg_pb_upload_limits()`). Aynı fotoğraf için telin iki yanında iki farklı tavan,
sonradan açıklanamayacak bir farktır. Özellik kapalıysa `shrinkTo` sıfırdır.

Canvas Blob'unun adı yoktur: `form.append('file[]', blob, name)` — üçüncü
argüman olmadan sunucuya `blob` diye kaydedilir.

### `$_FILES[...]['error']` Okunmadan Kayıt Açılmaz

Sınırı aşan dosya boş `tmp_name` ile gelir; `copy()` başarısız olur ve dönüşüne
bakılmazsa `INSERT` yine çalışır → bağlantısı 404 veren, optimize düğmesi
"diskte yok" diyen bir dosya satırı. `UPLOAD_ERR_NO_FILE` sessizce atlanır (o
bir hata değil, formun boş satırı); kalan her kod operatöre söylenir.

### Optimizasyon Yüzdesi Rozeti Pahalıdır

- `calculate_optimizable_percent($path)` → tam decode + yeniden sıkıştırma yapar.
- `OPTIMIZE_PERCENT_THRESHOLD = 30` (view_files.php) → optimize edilmemiş görsel
  sayısı ≤ 30 ise hesapla, üstündeyse düğmeyi yüzdesiz göster.
- Pano widget'ı (api.php) **hiç hesaplamaz**, yalnız önbelleklenmiş sütunu okur.
- Desteklenen tipler: `jpg`, `jpeg`, `png`, `gif`, `bmp`, `tiff`, `tif`, `webp`.
  `tif` yalnız küçük resim/ölçü listesinde — `optimize_image()` o yazımı
  reddeder, düğme sunmak garantili hata vermek olur.

---

## Hata Raporlama

`init.php` ve `router.php`'de:
```php
if (!defined('SET_ERROR_REPORTING') or SET_ERROR_REPORTING) {
    ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_DEPRECATED);
}
```
`E_DEPRECATED` PHP 7+'da built-in sabit, config'den override edilemez (eski blok kaldırıldı).

---

## find_and_replace.php Mimarisi

Tablo tanımları `$all_table_defs` dizisinde, her tanım şu alanları içerir:

```php
[
    'label'        => lang('...'),       // Görünen ad
    'table'        => 'tablo_adi',       // DB tablo adı
    'id_column'    => 'id',              // PK kolonu
    'name_column'  => 'name',            // Görünen ad kolonu
    'extra_select' => [],                // Ek SELECT kolonları
    'extra_join'   => '',                // JOIN ifadesi
    'base_where'   => '',                // Sabit WHERE koşulu (opsiyonel)
    'columns'      => ['content'],       // Aranacak kolonlar
    'edit_url'     => 'edit_x.php?id={id}', // {kolon_adi} yer tutucusu
    'url_raw'      => false,             // true = OUTPUT_PATH . url (frontend), false = backend
]
```

- `url_raw = true` → `OUTPUT_PATH . url` (pregion için frontend sayfası)
- `url_raw = false` → `OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . url`
- ECOMMERCE kontrolü: `products`, `product_groups` tabloları için
- ADS kontrolü: `ads` tablosu için

## REST API — apps.php / apps_settings.php

### Şifreleme Mimarisi (`user` tablosu)

| Kolon | Amaç | Açıklama |
|---|---|---|
| `secret_key` | Güvenli saklama | AES-256-CBC, her şifrelemede rastgele IV |
| `secret_key_iv` | IV değeri | `encrypt_string_with_iv()` ile üretilir |
| `secret_key_hash` | Hızlı DB arama | `hash_hmac('sha256', $plain, ENCRYPTION_KEY)` — deterministik |

```sql
-- Migration (bir kez çalıştır)
ALTER TABLE user ADD COLUMN secret_key_hash VARCHAR(64) DEFAULT NULL;
CREATE INDEX idx_user_secret_key_hash ON user (secret_key_hash);
```

**Neden iki yapı?**  
`encrypt_string_with_iv()` her çağrıda farklı ciphertext üretir (random IV). Dolayısıyla `WHERE secret_key = ?` çalışmaz. Hash deterministik olduğu için `WHERE secret_key_hash = ?` ile tek sorguda kullanıcı bulunur. Decrypt döngüsü YOK.

### apps.php Doğrulama Akışı

```php
// 1. api_key → custom_apps tablosunda ara
$query = "SELECT ... FROM custom_apps WHERE api_key_hash = '...' LIMIT 1";

// 2. secret_key → user tablosunda ara (decrypt yok!)
$secret_hash = hash_hmac('sha256', $SECRET, ENCRYPTION_KEY);
$query = "SELECT ... FROM user WHERE secret_key_hash = '$secret_hash' LIMIT 1";
```

### Güvenlik Kuralları

- `$_REQUEST` yerine `array_merge($_GET, $_POST)` — Cookie injection önler
- `hash_equals()` yerine artık DB sorgusu (timing-safe değil, ama DB latency bunu maskeler)
- Hata mesajları birleşik: `"Invalid credentials."` — hangi alan yanlış olduğu belirtilmez
- `h()` JSON response içinde KULLANILMAZ — JSON encoding kendi escaping'ini yapar
- Endpoint izinleri: `has_permission($permissions, $action, 'read|edit')`

### Yeni Endpoint'ler

| Endpoint | İzin | GET | POST |
|---|---|---|---|
| `product` | ecommerce | read/edit | read/edit |
| `pages` | — | read/edit | read/edit |
| `users` | role < 2 | read/edit | read/edit |
| `visitors` | manage_visitors | read | — |
| `site_settings` | — | read/edit | read/edit |

---

## Arayüz / Tasarımcı / Frontend Konvansiyonları

> Bu bölüm **kısa kural setidir**.
>
> - Gerekçeler, tuzaklar, legacy paritesi anlatısı → `pinegrap-arayuz` skill'i
> - **Yeni palette component'i** (hero, CTA, pricing, features...) yazacaksan
>   → **`docs/component-development-guide.md`'yi baştan sona oku.** Zorunlu
>   mimari kısıtlar (custom JS yok, hardcoded inline style yok, ek CSS yok,
>   sadece BS 5.3.8 + Bootstrap Icons) ve `render()` ↔ `toHTML()` eşitliği
>   orada tanımlı. Bölüm 9'daki kontrol listesi tamamlanmadan component
>   "bitmiş" sayılmaz.

### İki Ayrı Katman — Karıştırma

| Katman | Ne | Rehber |
|---|---|---|
| **Palette component** | Tasarımcının sürüklediği hazır bloklar (hero, CTA, pricing). Saf sunum, veri bağlamaz. | `docs/component-development-guide.md` |
| **Sistem widget** | Veriye bağlı bölgeler (catalog_listing, express_order, shopping_cart). `shared_components.system_region_config` + data binding. | Bu bölüm + `pinegrap-arayuz` skill'i |

Ortak nokta: ikisi de aynı node tree modelini (`type`/`props`/`children`) ve
aynı canvas'ı kullanır — tree şeması, node tipleri, auto-wrap ve `canDrop`
kuralları için yine `component-development-guide.md` bölüm 1 ve 4 geçerlidir.

### Hangi Katman Production, Hangisi Geliştirme

| Katman | Durum | Kural |
|---|---|---|
| **Legacy custom style** (`get_catalog.php`, `get_shopping_cart.php`, custom style şablonları) | **Production** — gerçek müşteri sitelerinde çalışıyor | Dokunma. Doğru davranışın ölçütü olarak kullan. |
| **Sistem widget** (`_render_system_widget_*`, `style_designer.js`) | **Geliştirme** — henüz hiçbir sitede kullanılmıyor | Serbestçe temizle. |

Sistem widget tarafında **geriye dönük uyumluluk için ölü kod tutma.** "Eski
ağaçlar bozulmasın" gerekçesi burada geçersiz — kullanıcısı yok. Aynı işi yapan
iki mekanizmayı yan yana yaşatmak, hangisinin kazandığı ağaca göre değiştiği
için sessiz hata üretir (tasarımcı ayardan metni değiştirir, buton bağlamayla
çizildiği için hiçbir şey olmaz).

Değer ölçütü: **varsayılan şablon doğruluğu + tasarımcı esnekliği.** Kullanıcı
test sırasında şablonu sık sık sıfırlıyor; varsayılan şablon yanlışsa hata
sonsuza kadar geri gelir.

### Değişmez İlkeler

1. **Legacy parite ölçüttür.** Bir sistem widget özelliği eklerken/düzeltirken
   doğru davranışın tanımı, aynı işi yapan legacy sayfa tipinin çıktısıdır
   (`catalog` / `catalog detail` / `form list view` ...). Tahmin etme, legacy
   sayfayı aç ve karşılaştır.
2. **Ayar paneli switch'i gerçek switch olmalı.** Bir özellik kapalıysa ona
   bağlı elementler frontend'e **hiç çıkmaz**; tasarımcı canvas'ında
   soluklaşır. "Görünür ama işlevsiz" hâli yasak (boş offcanvas açan ölü
   buton gibi).
3. **Ürün grupları iç içedir.** "Bu gruptaki ürünler" ifadesi neredeyse her
   zaman "bu grubun **altındaki her yerdeki** ürünler" demektir. Doğrudan
   `products_groups_xref` üyeliğine bakan her sorgu şüphelidir.
4. **Kod içi yorumlar İngilizce**, kullanıcıya görünen string'ler `lang()`.
5. **Fiyatlar kuruş (int)** saklanır. İndirim yüzdesi kesirli kuruş üretir →
   `(int)` ile **kırpma**, `round()` kullan (aksi hâlde kartla feed 1 kuruş ayrışır).

### catalog_listing Sistem Widget

**Varsayılanlar** (PHP + designer JS'te aynı olmak zorunda):

| Ayar | Varsayılan | Nerede |
|---|---|---|
| `group_navigation` | `'drill_down'` | `functions.php` + `style_designer.js` (3 nokta) |
| `order_by_field` | `'sort_order'` | — |
| `empty_message` | `'Ürün bulunamadı.'` | — |

**Grup kartı davranışı** (`display_type`):

| Tip | Link hedefi | Sepete Ekle |
|---|---|---|
| `browse` | `{liste_sayfası}/{address_name}` — aynı sayfada genişler | ✗ (strip edilir) |
| `select` | `{detail_page}/{address_name}` — ürün detayında açılır | ✗ (strip edilir) |

`select` grup ve ürün linkleri **`detail_page_id` ayarı yoksa `#` olur.**
Kod hatası değil, eksik ayar.

**Grup kapsamı — duruma göre değişir:**

- Normal gezinme → **sadece doğrudan üyeler** (klasör listesi UX'i)
- Arama veya herhangi bir filtre aktif → **tüm alt ağaç**
- Fiyat aralığı ve filtre seçenekleri → **her zaman tüm alt ağaç**

**Ortak yardımcı:** `_pg_catalog_group_subtree_ids($root_ids)` → `root => [self + tüm alt gruplar]`.
Alt ağaç yürüyüşü **PHP'de** yapılır, recursive SQL CTE ile değil (her render'da
çalışıyor + CTE desteği şart koşulmasın diye). Arama dalındaki CTE istisnadır.

**Grup fiyat aralığı** legacy `get_price_range()` ile aynı üç kuralı uygular:
alt ağaç özyinelemesi + indirim uygulaması + `selection_type='donation'` hariç.

### Kapalı Özellik = Çıktı Yok

Frontend: `_pg_catalog_binding_disabled()` (functions.php) — binding'i kapalı
özelliğe bağlı node'lar tree'den düşürülür (çocuk filtreleme aşamasında, yani
`search_form` düşerken içindeki input + buton da gider).

Tasarımcı: `_sdCatalogBindingDisabled()` (style_designer.js) — **aynı kuralın
aynası, ikisi lockstep güncellenmeli.** Node `.sd-binding-off` alır (soluk +
grayscale + "KAPALI" rozeti). `pointer-events:none` **konmaz** — seçilemezse
düzeltilemez.

| Binding | Kapatan ayar |
|---|---|
| `action=search_form` / `submit_search`, `value=search_query` | `search_enabled` |
| `action=open_filters` / `clear_filters`, `section=filters_offcanvas` / `attribute_filters` | fiyat + stok + özellik filtrelerinin **hepsi** kapalıysa |

`filter_chips` / `active_filters` **düşürülmez** — kategori ve arama pinlerini
de taşırlar, boşken zaten hiçbir şey render etmezler.

Filtre switch'leri `render()` **ve** `renderProperties()` çağırmalı, yoksa
canvas soluklaşmayı göstermez.

### RSS + Yapılandırılmış Veri (Sistem Widget)

Legacy RSS/JSON-LD `page.page_type` ile gated; sistem widget sayfaları
`page_type='standard'` olduğu için hiç girmiyordu. Köprü fonksiyonlar
`functions.php`'de `_pg_` önekiyle:

| Fonksiyon | İş |
|---|---|
| `_pg_find_system_widget_on_page($page_id, $regionType)` | Sayfadaki widget'ı bul (`ORDER BY id ASC` — deterministik olmalı) |
| `_pg_catalog_group_subtree_ids($root_ids)` | Alt ağaç genişletme (paylaşımlı) |
| `_pg_catalog_group_paths($root_id)` | `gid => "A > B > C"` (`g:product_type` için) |
| `_pg_catalog_product_group_paths($root_id)` | `pid => path` (en derin/spesifik yol kazanır) |
| `_pg_build_product_rss_item($product, $url, $extra)` | Tek `<item>`; `$extra`: `sale_price_cents`, `product_type` |
| `_pg_build_catalog_listing_rss_parts()` / `_pg_build_catalog_item_rss_parts()` | Kanal parçaları |
| `_pg_build_product_jsonld()` / `_pg_build_catalog_listing_jsonld()` | Product / ItemList JSON-LD |

**Entegrasyon noktaları:** `get_page.php` (rss=true dalındaki `elseif` zinciri),
`get_page_content.php` (`$rss_feeds` dizisi + `</head>` JSON-LD splice).

**Kurallar:**
- Kanal `title`/`link`/`description` **aktif grubu** yansıtır, host sayfayı değil.
  Öncelik: grup `title` → `short_description` → `name` → sayfa.
- `<head>`'deki RSS `<link>` href'i drill segmentini **taşımalı**
  (`/a/Office_Supplies?rss=true`).
- Açıklamalar DB'de entity'li duruyor → `html_entity_decode()` **sonra** `h()`.
  Aksi hâlde `y&amp;uuml;ksek` çıkar.
- JSON-LD'de legacy'nin uydurma `review`/`aggregateRating` bloğu **taşınmadı**
  (sahte puan üretmek doğru değil).
- `STRUTURED_DATA` sabiti yazım hatalıdır (C yok) — kod tabanında tutarlı, düzeltme.

### Veri Bağlama: Yerleşime Değil, İçeriğe

Bağlama **her zaman** bir içerik elemanına yapılır: `span`, `h1..h6`, `p`, `a`, `img`.
**Yapısal etiketlere bağlama yapılmaz:** `table, thead, tbody, tfoot, tr, td, th,
caption, colgroup, col`.

Neden: `<td>` yerleşimdir — hizalama, genişlik, ait olduğu sütun ona aittir.
Değeri doğrudan hücreye bağlarsan tasarımcı onu linkle saramaz, yanına rozet
koyamaz, sadece değeri biçimlendiremez; hücreye ikinci bir eleman bırakırsa
bağlama onu sessizce ezer.

```php
// YANLIŞ
$sem('td', 'text-end', '₺39,95', array('bindings' => array('text' => '__item_price')))

// DOĞRU
$sem('td', 'text-end', array(
    $sem('span', '', '₺39,95', array('bindings' => array('text' => '__item_price'))),
))
```

Araç tarafı: `_getBindableProps()` yapısal etiketlerde metin bağlamasını
**önermez**, yerine açıklayıcı uyarı gösterir. Zaten bağlanmışsa dropdown
görünmeye devam eder (yoksa eski ağaçlardaki bağlama görünmez olurdu).
`_sdMigrateStructuralBindings()` ağaç yüklenirken eski bağlamaları otomatik
olarak hücre içindeki bir `<span>`/`<img>`'e taşır (idempotent).

### WYSIWYG Alanını `text` Bağlamasına Verme (blok içinde blok)

Rich-text editörüyle düzenlenen alanlar DB'de **blok sarmalı** durur:
`products.out_of_stock_message` → `<p>Sorry, this item…</p>` (varsayılanı
`add_product.php` böyle yazıyor). Bunu `text` bağlamasıyla bir
`content/paragraph` node'una verirsen `<p>` içine `<p>` girer — geçersiz HTML.

Tarayıcı iç içe geçirmez, **dış `<p>`'yi kapatır**: tek bağlı elemandan üç DOM
node çıkar — tasarımcının class'larını taşıyan **boş kabuk**, sahipsiz kalan
mesaj, ve sondaki boş paragraf. Düz üründe kabuk `[data-pg-bind]:empty` ile
gizlenir, sadece sahipsiz metin görünür. **Varyantlı üründe**
`pg_civ_variants.js` aynı mesajı kabuğa da yazar → aynı cümle iki kez
(biri kutulu, biri düz) çıkar. İki üründe farklı davranması bu yüzdendir.

**Kural:** WYSIWYG alanını token olarak yayınlamadan önce
`_pg_rich_text_to_inline()` ile satır-içine indir (blok kapanışları `<br>`,
`<a><strong><em><span>…` korunur). Uygulandığı yerler: catalog_listing loop,
catalog_item_view token map, varyant JS payload'ı — **üçü birden**.

Ayrıca: operatörün kendi cümlesini ayrıca `.alert` kutusuna sarma. Metin zaten
biçimli geliyor (`<strong>Stok Tükendi</strong>` gibi); ikinci bir görsel
kapsayıcı tek olguyu iki bildirim gibi gösterir. Şablon düz vurgulu metin
kullanır, kutuyu isteyen tasarımcı CSS sınıfı alanından ekler.

### Bootstrap'in Doğrudan-Çocuk Varsayımı (tekrarlayan hata sınıfı)

Bootstrap'in birçok kuralı **doğrudan çocuk** seçicisi kullanır (`.row > *`,
`.d-grid > *` …). Canvas'ta araya `.sd-wrap` girer ve kural **wrapper'ı** eşler.
`display:contents` wrapper'ın kutusunu kaldırır ama **DOM'dan kaldırmaz** — yani
seçici hâlâ wrapper'ı bulur, uygulanan stil kutusu olmayan bir elemana gider ve
buharlaşır.

Bu tek kök sebep üç ayrı bug üretti:

| Belirti | Kırılan kural |
|---|---|
| Sütunlar birbirine yapışık (Ad/Soyad arası boşluk yok) | `.row > *` gutter padding |
| Buton tasarımcıda dar, frontend'de tam genişlik | `.d-grid > *` grid item |
| Loop bandı ilk sütuna sıkışıp 5 satıra sarıyor | `<td>`'ye `display:flex` → `colspan` iptal |

**Kural:** Bootstrap bir kapsayıcıya doğrudan-çocuk stili veriyorsa canvas'ta
**iki şey birden** gerekir:
1. `X > .sd-wrap { display:contents }` — wrapper'ı layout'tan çıkar
2. `X > .sd-wrap > * { …kuralın kendisi… }` — stili gerçek çocuğa yeniden uygula

(2)'de Bootstrap'in `width`/`max-width`/`flex` gibi boyut kurallarını **kopyalama**:
`X > .sd-wrap > *` seçicisi `.col-md-6`'dan daha spesifiktir, kopyalarsan
sütun genişliklerini ezersin. Sadece padding/margin taşı.

Bir `<td>`/`<th>`'ye asla `display:flex` verme — hücre tablo düzeninden çıkar ve
`colspan` iptal olur. Flexbox'ı hücrenin içine koyduğun bir `<div>`'e ver.

### Tasarımcı Canvas (style_designer.js)

- **`buildSharedRefEl()` sistem widget'ın KENDİ ağacını (`cached.tree`) render eder**,
  modül seviyesindeki `tree` değişkenini değil. Node'un ebeveynini ararken
  `_sysWidgetRenderTreeRoot || tree` kullan — yoksa arama sessizce başarısız olur.
  (Bu, EO tablo "binme" bug'ının asıl sebebiydi.)
- `_sysWidgetRenderCfg` ve `_sysWidgetRenderTreeRoot` **lockstep** set/reset edilir.
- `<tbody>/<thead>/<tfoot>` içindeki `loop_area` → `buildTableLoopAreaEl()`
  (gerçek `<tr>` üretir; `<div>` tablo düzenini bozar).
- `.sd-loop-area` **`width:100%`** almalı — `.row` içinde unwrap edilince
  flex-item shrink ile büzüşür.

### Sistem Widget Sayfaları (canlı test buralardan)

| URL | Widget |
|---|---|
| `/c` | shopping_cart |
| `/d` | express_order |
| `/e` | order_view |
| `/b/<slug>` | catalog_item_view |

**Aynı adın arkasında iki backend var.** Kullanıcının makinesinde
`dev.pinegrap.com` hosts dosyasıyla yerel IIS'e gider (`insider` DB —
`/c` `/d` `/e` **yok**, 404). Sandbox'tan aynı ad genel DNS'le Cloudflare
arkasındaki uzak kopyaya gider (oradaki DB'de var). Kod klasörü OneDrive'la
ortak, veritabanları ayrı. phpMyAdmin (`localhost:8081`) yerel DB'yi
gösterir. Hangi tarafta test ettiğini bil; iç tarayıcı = yerel.

`/cart`, `/express-order`, `/checkout-*` **legacy** sayfalardır — sistem
widget değişiklikleri oralara yansımaz. Sepet değişikliğini legacy `/cart`'ta
aramak boşa zaman harcamaktır (bir kez yapıldı).

Dolu sepetle test: `/c?r=<reference_code>` — kayıtlı-sepet linki oturum
çerezi olmadan da sepeti geri yükler.

Değişiklik sonrası sayfada görünmüyorsa sırayla: **(1)** kayıtlı ağaç mı
kullanılıyor (yeni node'lar yalnız "Varsayılan Düzeni Yükle" sonrası girer),
**(2)** opcache — dosya değişiminin sunucuya yansıması ~1 dk sürebilir.

### Heredoc İçinde `$` (sessiz uyarı sızıntısı)

`_eo_render_widget_js()` gibi `<<<HTML` heredoc'larında **JS yorumları da
interpolasyona girer.** İçinde `$tot_row` geçen bir yorum satırı, sayfanın
her render'ında `Undefined variable $tot_row` uyarısı bastırıyordu. Heredoc
içindeki her literal `$` **`\$` ile kaçırılmalı**; kasıtlı interpolasyonlar
zaten `{$var}` biçiminde.

### Değişiklik Yaparken Kontrol Listesi

1. Legacy muadili sayfayı aç, çıktıyı karşılaştır (ürün sayısı dahil).
2. Alt grup içeren bir kategoride test et — düz kategoride her şey doğru görünür.
3. PHP ↔ JS ikizi olan kural mı? (varsayılanlar, binding-disabled) İkisini de güncelle.
4. `node --check assets/js/style_designer.js` (sandbox'ta `php -l` yok).
5. Canlı doğrula: `dev.pinegrap.com`, **çıkış yapmış** hâlde — edit modunda
   JSON-LD/RSS bilerek üretilmez.
