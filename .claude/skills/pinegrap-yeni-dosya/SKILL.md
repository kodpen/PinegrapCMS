---
name: "pinegrap-yeni-dosya"
description: "Pinegrap CMS'e yeni PHP/JS dosyası, yeni fonksiyon, yeni klasör veya gömülü kütüphane eklerken kullan. Dosya başlığı, yerleşim, kapı sabitleri, includes/fn modül seçimi, olay duyurma."
---

# Pinegrap — yeni dosya ve yeni fonksiyon

## Dosya başlığı

Pinegrap için yazılan hiçbir dosya LiveSite başlığı taşımaz. Yeni PHP/JS dosyası
şununla açılır (üçüncü taraf kütüphanelere — `phpmailer`, `stripe`,
`iyzipay-php`, `phpexcel`, `boxpacker`, `dompdf` — dokunulmaz):

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

`PineGrap` değil, **`Pinegrap`**.

## Yerleşim

- Kökte yalnız URL ile çağrılan dosya durur.
- Arayüzü olmayan yardımcılar `includes/` altına: birlikte altsistem
  oluşturanlar `includes/<altsistem>/`, tek başına duran yardımcı doğrudan
  `includes/`, üçüncü taraf kütüphane `includes/<kütüphane>/`.
- Kökteki mevcut ~85 include dosyası **taşınmaz** (dosya bütünlüğü sistemi
  onları izliyor); kural yalnız yeni koda uygulanır.
- `includes/` **dışına** yeni klasör açarsan `_software_create_hash.php`
  içindeki `hashed_subdirectories()` dizisine ekle — yoksa o klasör hiç
  denetlenmez. `includes/` altı kendiliğinden kapsamdadır.
- Gömülü kütüphane çalışma anında `includes/` içine yazamaz; geçici/önbellek
  çıktısı `data/cache/<kütüphane>/` altına gider (yoksa her saat "kurcalanmış"
  uyarısı).
- Kütüphane gömerken `composer.json`'daki `php` kısıtına bak ve sürümü **taban
  7.1 ya da altı** kalacak şekilde sabitle; tabanı yükseltmek ürün sahibinin
  kararıdır.
- Yeni pano widget'ı `includes/dashboard/widgets/widget_<id>.php` dosyasına
  girer (kapı `PG_DASHBOARD_WIDGETS`); `pg_dashboard_widget_<id>($request,
  $user)` echo/exit yapmaz, `status`/`message`/`data` dizisi döndürür. Id
  `welcome.php` kayıt defterine de girer; 22 ve 24 emeklidir, yeniden
  kullanılmaz.

## Kapı sabiti

Her include dosyası kapıyla başlar:

```php
if (!defined('PG_API_ENTRY')) {
	exit;
}
```

- Panelden de çağrılıyorsa:
  `if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) { exit; }`
- Migration dosyalarında: `INSTALL_OR_UPDATE`
- `includes/fn/` modüllerinde: `if (!defined('PG_FUNCTIONS_DIR')) { exit; }`

Kapısız dosya doğrudan URL'den çalıştırılabilir.

## Yeni fonksiyon `includes/fn/` modülüne yazılır

`functions.php` 70 satırlık bir manifesttir; gövde 24 modüle bölünmüştür.
Fonksiyon konusuna göre modüle yazılır, `functions.php`'ye değil.

Modüller: `core` (db*, escape/h/e, JSON, tarih, `lang()`, polyfill — ilk
yüklenir), `auth`, `forms`, `ecommerce`, `seo`, `content`, `calendar`, `editor`,
`mail`, `contacts`, `designer`, `widgets_*`, `custom_form`, `files`, `cron`,
`tour`, `output`, `system_status`, `image`, `parasut`, `update`, `events`.

- Modül içinde yol için `PG_FUNCTIONS_DIR` kullan; `__DIR__` /
  `dirname(__FILE__)` modülde yazılım dizinini **vermez**. Kökteki dosyalarda ve
  `init.php`'de `dirname(__FILE__)` aynen kalır.
- `use PHPMailer\...` yalnız `includes/fn/mail.php`'de geçerlidir; başka modülde
  niteliksiz `Exception` global `\Exception`'dır.
- Modül yükleme sırası yalnız fonksiyon-dışı ifadeler için önemlidir;
  fonksiyonlar birbirini her sırada çağırabilir.
- `get_file.php` `functions.php`'yi yüklemez. Oturum ve klasör erişim
  fonksiyonları (`pg_auth_token_verify`, `pg_load_user_row`,
  `get_access_control_type`, `check_view_access`, `check_edit_access`,
  `pg_folder_edit_access`, `check_private_access`) iki tarafın da yüklediği
  `includes/authentication.php`'de tek kopyadır; oraya yazarken dosya
  başlığındaki sözleşmeye uy (yalnız `db/db_value/db_item/db_items/escape` +
  PHP, `lang()` / `output_error()` yok). Kalan kopyalar `tools/check_copies.php`
  içindeki `$intended` listesindedir (gerekçe `docs/_get_file_kopyalar.md`) —
  onlardan birini değiştirirken `get_file.php`'deki kopyayı da güncelle;
  listede olmayan yeni bir kopya CI'da `FAIL` olur.
- Eski notlardaki `functions.php:NNNNN` satır referansları bölünmeden öncesine
  aittir; fonksiyonu **adıyla** ara.

## Olay duyurma

Yeni bir kayıt oluşturan ekran ya da form, API'nin kuyruğunu doğrudan çağırmaz:

```php
pg_announce('page.created', array('id' => $id, 'name' => $name));
```

(`includes/fn/events.php` — API kurulu değilse sessizce döner.) Kişi için
`pg_announce_contact_created($id)` (e-postayı kendisi okur, ikinci argüman almaz). Olay adı `api_webhook_events()` kataloğunda
tanımlı olmalı ve **toplu içe aktarma yollarından çağrılmaz** (beş bin kuyruk
satırı yerine tek listeleme yeterlidir).

## Yeni sayfa / iş betiği eklerken

- Her sayfanın başında `validate_user()`, alan kapısı
  `validate_area_access($user, 'administrator'|'designer'|'manager')`
  (bkz. `pinegrap-roller-yetki`).
- Yeni cron/job betiğinde kapı kalıbını kopyala: `pg_cron_is_background_run()`
  (CLI veya `PG_CRON_DISPATCH`) dışındaki her isteği, düğmeyi sunan panel
  ekranının kapısından geçir. `send_to` var mı diye bakarak kullanıcı
  doğrulamasını atlama.
- `add_notice()` çağıran ekran `output_notices()` de çağırmalı ve render'dan
  sonra `remove_form()` demeli (uyarılar oturumdan okunur, silinmez).
- Stok/fiyat yazan yeni kod eklersen `pg_marketplace_product_changed()` satırını
  da ekle; ağ çağrısını istek akışında yapma, kuyruğa yaz.
- POST alan yeni betikte `init.php`'den hemen sonra `pg_post_body_discarded()`
  kontrolünü yap (JSON gövdeli uçta **yapma**, `php://input`'a bak).
- URL'e girecek her adı ASCII'ye indir (`pg_ascii_file_name()` /
  `prepare_file_name()`); çevrimi **önce**, `mb_strtolower()`'ı **sonra**
  uygula — tersi `İ`yi `i`+U+0307 yapar. Yeni aksan tablosu yazma,
  `pg_transliterate_to_ascii()` kullan.

## Yorum kuralı (hatırlatma)

Kod içi yorumlar **İngilizce**, AI/süreç izi taşımaz. Ayrıntı `CLAUDE.md`'de.

## Bitirmeden önce

`php tools/lint.php` temiz; yeni dosyada başlık bloğu var; include ise kapı
sabiti var.
