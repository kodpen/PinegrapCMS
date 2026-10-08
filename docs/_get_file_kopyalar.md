# `get_file.php` fonksiyon kopyaları

`router.php` dosya isteklerini `init.php`'den ve `functions.php`'den geçirmeden
`get_file.php`'ye verir (her görsel isteği megabaytlarca PHP ayrıştırmasın
diye). Bu yüzden dosya ihtiyaç duyduğu yardımcıları kendi içinde tanımlar. Bu
belge o fonksiyonların listesidir: hangisinin `functions.php` arkasında bir aslı
var, ikisi nasıl ayrışmış, hangisi ortak dosyaya taşındı, hangisi bilerek
kopya olarak kaldı.

Ortak kodun yeri `includes/authentication.php`'dir: iki taraf da onu yükler.
Sözleşmesi dosya başlığındadır — yalnız `db()`, `db_value()`, `db_item()`,
`db_items()`, `escape()` ve PHP'nin kendisi; `lang()` ve `output_error()`
çağrılmaz (`get_file.php`'de `lang()` yoktur), yalnız bir tarafta olan bir
fonksiyon `function_exists()` ile çağrılır.

Denetim: `php tools/check_copies.php` (CI'da). `get_file.php`'deki her
fonksiyon adını `functions.php`, `includes/authentication.php` ve
`includes/fn/*.php` içinde arar; aşağıda **bilerek bırakıldı** yazan satırlar
betikteki `$intended` listesiyle birebir aynıdır. Listede olmayan yeni bir
kopya `FAIL`, listede olup artık kopya olmayan ad `WARN` verir.

## Tablo

Satır numaraları `development @ 81a0bd9`'a (taşımadan önceki `get_file.php`)
göredir. Tarih/commit sütunları `git log -L` çıktısıdır (birleştirme
commit'leri hariç). Deponun geçmişi 2026-08-13'te tek bir ilk commit'le
(`cb4957d`) başlar: o commit'i gösteren satır "depo açıldığından beri
değişmedi" demektir. `includes/fn/` modülleri 2026-09-17'de `6b10e40`
("2026.4.4 BETA", `functions.php` bölünmesini de taşıyan toplu commit) ile
doğdu; asıl tarafta yalnız o commit görünüyorsa fonksiyonun `functions.php`
içindeki son değişikliği parantezde verildi.

| # | Fonksiyon | `get_file.php` satırı | Asıl | Fark | `get_file.php` son değişiklik | Asıl son değişiklik | Bu PR |
|---|---|---|---|---|---|---|---|
| 1 | `get_file_text` | 1152 | — (yalnız `get_file.php`) | Kopya değil: `lang()` yokken İngilizce metne düşen yedek, `{var:n}` yerine koyar | `6b10e40` 2026-09-17 | — | dokunulmadı |
| 2 | `output_error` | 1182 | `includes/fn/core.php` | **Mantık farkı.** Kendi `http_response_code()` + 403/404/410 yedeği, MySQL hatasını `htmlspecialchars()` ile mesaja ekleyip düz HTML basar. Asıl: SEO geçişi/JSON çağıranda istisna fırlatır, MySQL ayrıntısını yalnız `DEBUG`/kurulumda gösterir, `lang()`'li tasarımlı hata sayfası (`get_error_screen()`, `pg_sw_error_context()`) çizer | `812ccbc` 2026-09-18 | `15c007d` 2026-09-27 | **bilerek bırakıldı** |
| 3 | `db` | 1240 | `includes/fn/core.php` | **Mantık farkı.** Yerel sürüm sonuç döndürmez (yalnız yazma sorguları için). Asıl: bağlantı kontrolü, kurulum koşucusu kancası (`install_query_failed()`), satır sayısına göre değer/satır/liste döner | `cb4957d` 2026-08-13 | `6b10e40` 2026-09-17 (`functions.php`: `cb4957d`) | **bilerek bırakıldı** |
| 4 | `db_value` | 1245 | `includes/fn/core.php` | Hata yolu farkı: asılda bağlantı kontrolü, kurulum kancası ve satır yokken `isset` koruması (yerel sürüm aynı `null`'u PHP uyarısıyla döndürür) | `cb4957d` 2026-08-13 | `6b10e40` 2026-09-17 (`functions.php`: `cb4957d`) | **bilerek bırakıldı** |
| 5 | `db_item` | 1252 | `includes/fn/core.php` | Hata yolu farkı: asılda bağlantı kontrolü ve kurulum kancası; başarılı yolda aynı | `cb4957d` 2026-08-13 | `6b10e40` 2026-09-17 (`functions.php`: `cb4957d`) | **bilerek bırakıldı** |
| 6 | `db_items` | 1258 | `includes/fn/core.php` | Asılda ek `$key_column` parametresi, bağlantı kontrolü ve kurulum kancası; parametresiz çağrıda aynı sonuç | `cb4957d` 2026-08-13 | `6b10e40` 2026-09-17 (`functions.php`: `cb4957d`) | **bilerek bırakıldı** |
| 7 | `escape` | 1272 | `includes/fn/core.php` | Asıl bağlantı yokken `addslashes()`'e düşer; `get_file.php`'ye bağlantısız ulaşılamaz (başındaki `class_exists('db')` kapısı) | `cb4957d` 2026-08-13 | `6b10e40` 2026-09-17 (`functions.php`: `cb4957d`) | **bilerek bırakıldı** |
| 8 | `get_access_control_type` | 1276 | `includes/fn/auth.php` | **Mantık farkı, sonuç aynı.** Yerel: satır satır özyineleme, `folder_parent` döngüsünde sonsuz özyineleme. Asıl: `folder` tablosunu bir kez okur, istek boyunca önbellekler, döngüde `public` döner | `cb4957d` 2026-08-13 | `6b10e40` 2026-09-17 (`functions.php`: `cb4957d` — önbellek ve döngü koruması ilk commit'te zaten vardı) | **taşındı** → `includes/authentication.php` (asıl sürüm; sorgu `db_items()` ile) |
| 9 | `log_activity` | 1294 | `includes/fn/forms.php` | **Mantık farkı.** Asıl: `$user` boşsa `USER_USERNAME`, CLI'de `REMOTE_ADDR` koruması, ERP denetim izine satır (`erp_audit_log_line()`), `lang()`'li hata | `bcaae80` 2026-09-18 | `522742a` 2026-09-24 | **bilerek bırakıldı** |
| 10 | `initialize_user` | 1313 | `includes/fn/auth.php` | **Mantık farkı.** Yerel sürüm yalnız beni-hatırla jetonunu ve oturum kullanıcısını yükler, daha az sabit tanımlar (`USER_LOGGED_IN`, `USER_ID`, `USER_EMAIL_ADDRESS`, `USER_ROLE`, `USER_MEMBER_ID`, `USER_EXPIRATION_DATE`, `USER_MEMBER`, `USER_MANAGE_FORMS`). Asıl: eski oturum/çerez göçü, jetona bağlı oturum ve cihaz sınırı, saat dilimi ve tüm `USER_*` sabitleri | `83cbb45` 2026-09-18 | `522742a` 2026-09-24 | **bilerek bırakıldı** |
| 11 | `check_view_access` | 1389 | `includes/fn/auth.php` | Aynı (yalnız boşluk/satır kırma) | `cb4957d` 2026-08-13 | `6b10e40` 2026-09-17 (`functions.php`: `cb4957d`) | **taşındı** → `includes/authentication.php` |
| 12 | `check_edit_access` | 1464 | `includes/fn/auth.php` | Mantık aynı: yerel sürüm, asılda `pg_folder_edit_access($folder_id, USER_ID, USER_ROLE)`'a ayrılmış yürüyüşün satır içi hâli; asılda `isset` korumaları ve `(int)` kullanıcı kimliği | `cb4957d` 2026-08-13 | `6b10e40` 2026-09-17 (`pg_folder_edit_access` bu commit'le geldi) | **taşındı** → `includes/authentication.php` (`pg_folder_edit_access` ile birlikte) |
| 13 | `check_private_access` | 1519 | `includes/fn/auth.php` | Mantık aynı: yerel sürüm tarih karşılaştırmasından önce `initialize_timezone()` çağırır; asılda `isset` korumaları | `cb4957d` 2026-08-13 | `6b10e40` 2026-09-17 (`isset` korumaları bu commit'le geldi) | **taşındı** → `includes/authentication.php` (asıl sürüm + `function_exists('initialize_timezone')`) |
| 14 | `initialize_url_constants` | 1591 | — (`init.php` / `router.php` içinde satır içi) | Kopya değil: `HOSTNAME`, `SOFTWARE_DIRECTORY`, `PATH`'i yalnız yönlendirme gerektiğinde tanımlar | `cb4957d` 2026-08-13 | — | dokunulmadı |
| 15 | `get_request_uri` | 1631 | `includes/fn/forms.php` | Aynı (yalnız yorum girintisi) | `cb4957d` 2026-08-13 | `6b10e40` 2026-09-17 (`functions.php`: `cb4957d`) | **bilerek bırakıldı** |
| 16 | `check_if_request_is_secure` | 1662 | `includes/fn/core.php` | Aynı (yorumlar ve `TRUE`/`true` yazımı); kopyanın üstünde "KEEP IN SYNC" notu var | `cb4957d` 2026-08-13 | `6b10e40` 2026-09-17 (`functions.php`: `cb4957d`) | **bilerek bırakıldı** |
| 17 | `check_proxy_ssl_headers` | 1682 | `includes/fn/core.php` | Aynı (yorumlar ve `TRUE`/`true` yazımı); "KEEP IN SYNC" notu var | `cb4957d` 2026-08-13 | `6b10e40` 2026-09-17 (`functions.php`: `cb4957d`) | **bilerek bırakıldı** |
| 18 | `initialize_timezone` | 1724 | — (`init.php` içinde satır içi, MySQL `time_zone`'u da ayarlar) | Kopya değil: PHP saat dilimini yalnız tarih gerektiğinde kurar; ortak `check_private_access()` onu `function_exists()` ile çağırır | `cb4957d` 2026-08-13 | — | dokunulmadı |

Ayrıca `pg_folder_edit_access` (`get_file.php`'de hiç yoktu) asıl
`check_edit_access()`'in parçası olduğu için onunla birlikte
`includes/fn/auth.php`'den `includes/authentication.php`'ye taşındı; çağıranları
(`includes/notifications.php`, `includes/api/resources/*.php`,
`includes/workspace/changes.php`, `includes/workspace/refs.php`,
`view_folder_and_files_f.php`) değişmedi.

Özet: 18 fonksiyon → 4'ü taşındı, 11'i bilerek bırakılan kopya, 3'ünün
`functions.php` arkasında aslı yok. Taşımadan sonra `get_file.php` 14 fonksiyon
tanımlar.

## Bilerek bırakılanlar neden kaldı

- **`get_file.php` `functions.php`'yi yüklemez.** Tek bir görsel isteği için 24
  modülü ve `init.php`'yi ayrıştırmamak dosyanın var oluş nedenidir; `db*`,
  `escape` ve URL/TLS yardımcıları (`get_request_uri`,
  `check_if_request_is_secure`, `check_proxy_ssl_headers`) bu yüzden yerel
  kalır. Ortak dosyaya alınmaları da mümkün değildir: `includes/authentication.php`
  onları **kullanır**, tanımlarsa `functions.php` tarafıyla çakışır.
- **`output_error` dosya sunumuna özgüdür.** Dosya isteğine 403/404 kodu ve düz
  bir HTML hata satırı döner; tasarımlı hata sayfası (`get_error_screen()`) tüm
  ön yüz hattını, `lang()`'i ve oturumu gerektirir. Mesajlar
  `get_file_text()`'ten geçer.
- **`initialize_user` bilerek küçüktür.** Dosya kararının ihtiyaç duyduğu
  sabitleri tanımlar; asıl sürümün oturum göçü, cihaz sınırı ve saat dilimi
  kısmı `functions.php`'deki yardımcılara dayanır. Ortak parça
  (`pg_auth_token_verify()`, `pg_load_user_row()`, `pg_session_sign_in()`) zaten
  `includes/authentication.php`'dedir.
- **`log_activity`** dosya isteğinin kendi kayıtlarını yazar (beni-hatırla
  girişi, reddedilen özel dosya/ek erişimi); ERP denetim izi ve
  `USER_USERNAME` varsayılanı `functions.php` tarafına aittir.

## Taşımanın ödünü

`get_file.php` artık klasörün erişim türünü öğrenmek için `folder` tablosunun
tamamını tek sorguda okur (`SELECT folder_id, folder_parent,
folder_access_control_type FROM folder`); önceden klasör derinliği kadar tek
satırlık sorgu atıyordu. Tipik bir sitede birkaç KB'dir; panel tarafı bunu
zaten her istekte yapıyordu. Karşılığında `folder_parent` döngüsü olan bir
ağaçta dosya isteği artık sonsuz özyinelemeye girmez, `public` döner (panel
tarafıyla aynı karar).
