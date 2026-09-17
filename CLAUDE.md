# Pinegrap CMS — Claude Bağlam Dosyası

Bu dosya **her oturumda otomatik yüklenir**, bu yüzden kısa tutulur: yalnız her
görevde geçerli olan kurallar burada durur. Gerekçeler, tuzaklar ve vaka notları
için en alttaki **Ayrıntı nerede** bölümüne bak; oradaki dosyaları ihtiyaç
duydukça, bölüm bölüm oku.

---

## Proje

- PHP tabanlı monolitik CMS. 2017'den beri Erdal Güral (Kodpen) geliştiriyor;
  LiveSite fork'u, 2019 LiveSite güncellemesi entegre edilmiş.
- Bootstrap 5 + jQuery. PHP 7.0–8.5 uyumlu. Veritabanı MySQL/MariaDB, erişim `mysqli`.
- **Bu depo yalnız ürünü içerir. Kod tabanı `pinegrap/` altındadır ve
  değişiklikler oraya yazılır.** Bu dosyadaki yollar o köke göredir:
  `includes/fn/core.php` demek `pinegrap/includes/fn/core.php` demektir.
- Geliştirme klasörü (`dev/`), iç planlar ve hata günlükleri depoya **girmez**;
  yalnız geliştirme makinasında durur. Depoda bulamadığın bir belgeyi uydurma —
  sor.
- Dil dosyası `includes/local/tr.json`, yapılandırma `data/config.php`
  (çalışma anında `CONFIG_FILE_PATH` sabiti). Gerçek `config.php` asla
  commit'lenmez; depodaki yalnız boş taslaktır, örnek değerler
  `data/config(default).php` içindedir.

---

## Çalışma ortamı

Sandbox'ta PHP 8.3 hazır gelir. **Composer kullanılmaz**: üçüncü taraf
kütüphaneler `includes/` altında gömülüdür, bağımlılık kurulumu yoktur. Kod
okuma, düzenleme ve `tools/` altındaki denetimler için hiçbir hazırlık gerekmez.

Çalışan bir örnek gerekiyorsa — yalnızca çalışma zamanı hatasını yeniden üretmek
için — tek komut yeter:

```bash
bash tools/setup_sandbox.sh
```

MariaDB'yi kurup başlatır, veritabanını oluşturur, `data/config.php`'yi yazar,
kurulum sihirbazını çalıştırır ve `127.0.0.1:8000`'de sunar; sonunda giriş
bilgilerini basar. Şema yalnız kurulum sihirbazının sürdüğü migration
runner'ından çıkar, hazır bir SQL dökümü yoktur. **Bu kurulum pahalıdır,
gerekmedikçe yapma**; görevlerin çoğu kod okuma ve statik denetimle çözülür.
Betik hata verirse sebebini yaz, etrafından dolaşma.

---

## Değişmez kurallar

### 1. Kod içi yorumlar İngilizce — istisnasız

PHP, JS, CSS, SQL; string içine gömülü CSS/JS yorumları dahil. Ürün dosyaları
müşteriye dağıtılır, yorumlar ürünün parçasıdır. (`tr.json` değerleri ve
`lang()` string'leri yorum değildir; Türkçe olmaları normaldir.)

Dokunduğun bir dosyada Türkçe yorum görürsen aynı değişiklik içinde İngilizceye
çevir.

### 2. Yorumda AI/süreç izi yasak

Bir kod yorumunda şunların hiçbiri geçemez: "kullanıcı kararı", "kullanıcı
isteği", "kullanıcı onayıyla", "saha geri bildirimi", "Faz 1/2/3", plan dosyası
referansı, oturum/konuşma referansı. Karar tarihçesinin yeri değişiklik günlüğüdür
(`docs/degisiklikler.md`, depo dışı). Yorum klasik geliştirici yorumudur: kodun ne
yaptığını ve teknik olarak neden öyle yapıldığını anlatır (kilitler, yarışlar,
geriye dönük uyumluluk, güvenlik kapıları).

### 3. Kullanıcıya görünen her metin çeviriden geçer

- PHP ve panel: `lang('English text')`.
- `assets/js/style_designer.js`: `_sdT('English text')` — panel etiketi,
  `row()`/`sect()` başlığı, toast, diyalog, `customName`, placeholder,
  `aria-label`, palet bileşeninin örnek içeriği, başlangıç ağacının `text:`
  değeri dahil. Bu dosyada düz literal metin kalamaz.
- **Anahtar her zaman tek tırnaklı düz literal'dir** (`_sdT("…")`,
  `_sdT(değişken)`, `_sdT('a' + b)` yok): sunucu dosyayı `_sdT('…')` kalıbı
  için tarar.
- Anahtar **`tr.json`'da bulunmak zorundadır**; yeni string eklediysen
  `tr.json`'a da ekle.
- Değişken `{var}` / `{var:N}` ile girer, anahtara gömülmez. `lang()`
  sözdizimi için `docs/LANG_USAGE.md`.

### 4. Yeni dosya başlığı

Pinegrap için yazılan hiçbir dosya LiveSite başlığı taşımaz. Yeni PHP/JS dosyası
şununla açılır (üçüncü taraf kütüphanelere dokunulmaz):

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

`PineGrap` değil, `Pinegrap`.

### 5. Dosya yerleşimi ve kapı sabiti

Kökte yalnız URL ile çağrılan dosya durur. Arayüzü olmayan yardımcılar
`includes/` altına: birlikte altsistem oluşturanlar `includes/<altsistem>/`,
tek başına duran yardımcı doğrudan `includes/` içine, üçüncü taraf kütüphane
`includes/<kütüphane>/`.

Her include dosyası kapıyla başlar:

```php
if (!defined('PG_API_ENTRY')) {
	exit;
}
```

`pinegrap/` kökündeki mevcut ~85 include dosyası **taşınmaz** (dosya bütünlüğü
sistemi onları izliyor); kural yalnız yeni koda uygulanır. `includes/` dışına
yeni bir klasör açarsan `_software_create_hash.php` içindeki
`hashed_subdirectories()` dizisine eklenmediği sürece o klasör hiç denetlenmez.

### 6. Yeni fonksiyon `includes/fn/` modülüne yazılır

`functions.php` artık 70 satırlık bir manifest; gövde 24 modüle bölünmüş
(`core`, `auth`, `forms`, `ecommerce`, `seo`, `content`, `designer`,
`widgets*`, `system_status`, `output`, `image`, `update` …). Yeni fonksiyon
konusuna göre ilgili modüle yazılır, `functions.php`'ye değil.

- Modül içinde yol için `PG_FUNCTIONS_DIR` kullan; `__DIR__` / `dirname(__FILE__)`
  modülde yazılım dizinini vermez.
- Modül kapısı: `if (!defined('PG_FUNCTIONS_DIR')) { exit; }`.
- `use PHPMailer\...` yalnız `mail.php`'de geçerlidir.
- Eski notlardaki `functions.php:NNNNN` satır referansları bölünmeden öncesine
  aittir; fonksiyon adıyla ara.

### 7. Veritabanı sözleşmesi

- `db('SQL')` çalıştırır, `db_items('SQL')` dizi döner, `db_value('SQL')` tek
  değer döner.
- `e($val)` / `escape($val)` SQL escape, `escape_like($val)` LIKE için.
- **Tablo adları tekildir**: `page`, `style`, `pregion`, `cregion`, `dregion`,
  `forms`, `form_data`.
- **Sorgu hatası `false` döner, istisna fırlatmaz.** `init.php`, `router.php` ve
  `install/index.php` `mysqli_report(MYSQLI_REPORT_OFF)` çağırır; kod tabanı
  baştan sona bu sözleşmeye göre yazılmıştır (96 yerde
  `mysqli_query(...) or exit(...)`). **Bu satırlar kaldırılmaz.** Hatayı okumak
  isteyen `mysqli_error(db::$con)` okur.

### 8. Şema değişikliği yalnız migration ile

`db("ALTER TABLE x ADD ...")` yazmak yasaktır. Her `ADD`/`CREATE`/`DROP`/
`MODIFY`/`CHANGE` önce sorar, sonra yapar; yardımcılar
`includes/migrations/runner.php` içinde:

```php
install_add_column('config', 'waf_enabled', "TINYINT(1) NOT NULL DEFAULT 0");
install_add_index('visitors', 'idx_user_agent', "INDEX idx_user_agent (user_agent(64))");
install_create_table('waf_rate', "CREATE TABLE waf_rate ( ... ) ENGINE=InnoDB ...");
install_drop_column / install_drop_index / install_drop_table
install_modify_column / install_rename_column / install_rename_table
install_table_exists / install_column_exists / install_index_exists / install_column_info
install_note('...');
```

- Yeni sürüm satırı `includes/migrations/versions.php`'ye eklenir; satır
  silinmez, numara yeniden kullanılmaz. DB'ye dokunan sürümün kendi dosyası
  olur (`2026.2.5.php` → `upgrade_to_2026_2_5()`).
- Veri ifadeleri (`UPDATE`/`DELETE`) tekrar koşulabilir yazılır.
- Enum daraltan `MODIFY` önce `install_column_info()` ile önceki şekli kontrol
  eder.
- Her yükseltme değişiklik günlüğüne kaydedilir (`docs/degisiklikler.md`, depo dışı).

### 9. `.src.js` düzenlenir, `.min.js` servis edilir — ikisi birden

`ENVIRONMENT_SUFFIX` sabiti hangisinin yükleneceğini seçer ve çoğu kurulumda
`min`'dir. Yalnız `.src.js` düzenlemek **hiçbir şey yapmaz ve hata vermez**
(`frontend.*`, `chat_backend.*`, `add_to_cart.*`, `dropzone.*`). Ölçüt dosya adı
değil, o dosyayı basan `<script src>` satırında `ENVIRONMENT_SUFFIX` geçip
geçmediğidir.

Ön yüzde çalışan JS iki yerde aranır: panel ekranları `backend.src.js`, ziyaretçi
sayfası `frontend.<suffix>.js` yükler. İkisinin de kendi klavye kısayolu bloğu ve
araç çubuğu kancaları vardır — bir düğmeyi taşırken ikisini birden ara.

### 10. Güvenlik

- CSRF: `get_token_field()` ile alan basılır, `validate_token_field()` ile POST
  doğrulanır.
- HTML çıktısı `h($val)` ile escape edilir.
- Yönlendirmede `HOSTNAME`'in arkasına ham girdi konmaz; `go()` merkezî olarak
  sertleştirilmiştir, `escape_url()`e dokunulmaz.
- Sır taşıyan config alanı sabite açılmaz; şifreli blob olarak saklanır ve
  kullanılacağı istekte çözülür. Ayar ekranı sırrı geri render etmez — boş kutu
  "kayıtlıyı koru" demektir.
- Sürüm yayınlamadan önce dosya bütünlüğü referansı yeniden üretilmelidir
  (`_software_create_hash.php`, yalnız geliştirme makinasında bulunur ve ürünle
  dağıtılmaz), yoksa değişen her dosya "kurcalanmış" görünür.

### 11. Para kuruş cinsinden `int`

Fiyatlar kuruş olarak saklanır. İndirim yüzdesi kesirli kuruş üretir; `(int)`
ile kırpma, `round()` kullan.

---

## Kullanıcı rolleri

| Rol | Değer | Erişebilir | Erişemez |
|---|---|---|---|
| Administrator | 0 | Her şey | — |
| Designer | 1 | Tasarım araçları dahil admin-dışı her alan | Sadece-admin alanları |
| Manager | 2 | İçerik, e-ticaret, ayarlar | Tasarım araçları |
| User | 3 | Temel içerik | E-ticaret vb. için özel bayrak gerekir |

Hiyerarşide **Designer (1), Manager'ın (2) üstündedir.** Kapılar:
`validate_user()` her sayfanın başında, `validate_area_access($user, 'administrator')`
yalnız rol 0, `'designer'` rol ≤ 1, `'manager'` rol ≤ 2.

E-ticaret: rol 0–2 `manage_ecommerce` bayrağından muaftır, rol 3 için zorunludur.

---

## UI kuralları (özet)

- Bootstrap 5. Sayfa çerçevesi `output_header([...])` / `output_footer()`.
- Bildirimler `$liveform->output_errors()` / `output_notices()`, alan render
  `$liveform->output_field([...])`, yönlendirme `go($url)`.
- **İkon Bootstrap Icons**, her zaman `<i class="bi bi-…"></i>` içinde. Düğmenin
  `class`'ına `bi bi-x` yazma, `<span class="bi …">` kullanma. Material Icons
  eski kodda kalabilir, yeni kodda kullanılmaz.
- Yönetim ekranı deseni: ekranın yapabildiği her şey başlığın altındaki **tek
  araç çubuğunda** toplanır; referans uygulama `view_folders.php`. Sınıflar
  `assets/css/backend.src.css` sonundaki *Admin screen toolbar* bloğunda
  (`.pg-toolbar`, `.pg-toolbar-grow`, `.pg-toolbar-search`, `.btn-ghost`) —
  ekrana özel yeni çubuk CSS'i yazma.
- Niyet başına tek reçete: birincil `btn-primary`, ilgili ekrana git / yerinde
  araç `btn-outline-secondary`, geri alınamaz iş `btn-outline-warning`. Dört
  düğmeden fazlası menüye iner.
- **Menü satırlarında renk kullanılmaz** (`link-body-emphasis`); tehlikeyi ikon
  ve onay diyaloğu anlatır. Bu kural yalnız menülere aittir — düğme, rozet ve
  uyarı kutusunda renk her zamanki gibi kullanılır.
- `add_notice()` çağıran ekran `output_notices()` de çağırmalı; gösterilen uyarı
  oturumdan silinmediği için ekran render'dan sonra `remove_form()` çağırır.

Tasarımcı/widget katmanı için: palette component yazacaksan
`docs/component-development-guide.md` **baştan sona** okunur (custom JS yok,
hardcoded inline style yok, ek CSS yok; yalnız Bootstrap + Bootstrap Icons).
Sistem widget'larında doğru davranışın ölçütü legacy sayfanın çıktısıdır —
tahmin etme, legacy sayfayı aç ve karşılaştır.

---

## Bir iş ne zaman biter

Birim test takımı ve CI kapısı **yoktur**; "testler geçti" denemez. Bunun yerine
iki denetim betiği vardır ve ikisi de temiz çıkmadan iş bitmiş sayılmaz:

```bash
php tools/lint.php         # tum agacta php -l
php tools/check_lang.php   # lang() / _sdT() anahtarlari tr.json ile ortusuyor mu
```

1. `php tools/lint.php` temiz.
2. `php tools/check_lang.php` temiz — eksik anahtar ve `_sdT()` literal kuralı
   ihlali yok.
3. Yeni dosyada başlık bloğu, include ise kapı sabiti var.
4. Yeni ve dokunulan yorumlar İngilizce, süreç/AI izi taşımıyor.
5. Şema değişikliği varsa migration üzerinden ve tekrar koşulabilir.
6. `.src.js` düzenlendiyse ikizi `.min.js` de güncellendi.
7. Değişiklik, değişiklik günlüğüne yazılacak şekilde PR açıklamasında özetlendi.

PR açıklamasında ne değiştiğini, neden öyle yapıldığını ve **neyi
doğrulayamadığını** yaz (çalışan örnek kurulmadıysa bunu açıkça belirt).

---

## Ayrıntı nerede

Depoda iki katkı rehberi bulunur ve bunlar sandbox'ta okunabilir:

| Konu | Dosya |
|---|---|
| Palette component yazımı (zorunlu okuma) | `docs/component-development-guide.md` |
| `lang()` sözdizimi ve kullanımı | `docs/LANG_USAGE.md` |

Geri kalan iç belgeler — tam bağlam dosyası, değişiklik günlüğü ve konu
planları — **bilerek depo dışındadır** ve yalnız geliştirme makinasında durur
(`docs/CLAUDE-tam.md`, `docs/degisiklikler.md`, `docs/_plan_*.md`).

Bu dosyaları sandbox'ta **arama, olmadıklarını sorun sanma ve içeriklerini
tahmin etme.** Yukarıdaki kurallar günlük işin tamamına yeter; bir kararın
gerekçesi gerçekten gerekiyorsa PR açıklamasına soru olarak yaz, uydurma.
