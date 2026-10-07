---
name: "pinegrap-guvenlik"
description: "Pinegrap CMS'te CSRF, çıktı escape, yönlendirme, sır saklama, dosya bütünlüğü, sunucu yapılandırması (.htaccess/web.config) ve güncelleme/TLS konularında kullan."
---

# Pinegrap — güvenlik sözleşmesi

WAF'ın kendisi ayrı: `pinegrap-waf`.

## Temel

- **CSRF:** formda `get_token_field()` ile alan bas, POST'ta
  `validate_token_field()` ile doğrula.
- **Çıktı:** HTML'e giren her değer `h($val)`. `_sdT()` çıktısı escape edilmez,
  `esc(_sdT(...))` yaz.
- Sorgu dizesindeki ayracı `h()`'ye `&` olarak ver; `&amp;` yazılmış adres
  `&amp;amp;` olur.

## Yönlendirme — `HOSTNAME`'in arkasına ham girdi konmaz

- `HOSTNAME`'den sonra gelen her kullanıcı kaynaklı değeri
  `pg_safe_redirect_path()`'ten geçir: `header('Location:')`,
  `window.parent.location`, `<a href>`, `go()` argümanı dahil. `@evil.com/x`
  girdisi `https://site.com@evil.com/x` olur.
- Kendi yönlendirme regex'ini yazma. Elle yazılmış üç kontrolün üçü de `\`
  karakterini kaçırıyordu (`preg_match('#^/[^/]#', $back)` → `/\evil.com` geçer).
- `escape_javascript()` ve `h()` URL doğrulayıcı değildir; adresin nereye
  gittiğine bakmazlar.
- `pg_safe_redirect_path()` içine `str_replace`, `preg_replace`, `urlencode`
  veya `parse_url`+yeniden birleştirme **ekleme** — tehlike gövdede değil
  önektedir; gövdeye dokunmak `name="x[]"` dizi parametrelerini (54 yer),
  UTF-8 adresleri ve sorgu dizesini bozar.
- Doğrulamayı ayrıştırmadan **önce** yap.
- Doğrulanmış bir değeri yeni bağlama taşırken doğrulamasının orada da geçerli
  olduğunu ayrıca kanıtla: `pg_safe_back_url()` `href=` içinde doğrudur ama
  `go()`'ya verilince `x.evil.com/a.php` → `https://site.comx.evil.com/a.php`.
- Şema taşıyan adreste önek eşleşmesi tek başına yetmez; önekten hemen sonra
  `/`, `?` veya `#` gelmelidir.
- `escape_url()`'ü **sıkılaştırma**: gösterim için doğrular ve `//`, `http://`,
  `https://` adreslerini bilerek geçerli sayar.
- `$_SERVER['PHP_SELF']` dışında hiçbir yönlendirme girdisini "sunucu üretiyor"
  diye muaf tutma.

## Sırlar

- Sır taşıyan config alanı sabite açılmaz: şifreli blob olarak saklanır
  (`"<ciphertext>:<iv>"`, `encrypt_string_with_iv()`) ve kullanılacağı istekte
  çözülür (`_parasut_credentials()`, `mp_credentials_encode/decode()`).
- Geri okunması gereken sırrı **hash'leme** (`api_settings.php`'nin `hash_hmac`
  kalıbını kopyalama) — kurtarılamaz olur.
- Ayar ekranı sırrı geri render etmez; boş kutu "kayıtlıyı koru" demektir.
  (Yazılı istisna için `pinegrap-verilmis-kararlar`.)
- Diyaloğa sahte parola alanı koyma; `pg_settings_stamp_autocomplete()` vendor
  opt-out'larını basar.

## Dosya bütünlüğü

- Kurulumların referansı **GitHub'daki sürüm etiketidir** (`v<sürüm>`, beta
  kanalında önce `v<sürüm>-beta`): `pg_integrity_github_reference()` etiketin
  ağacını alır, 12 saatte bir tazeler; dosya git blob SHA-1'iyle karşılaştırılır
  (`pg_integrity_git_blob_matches()`: olduğu gibi, CRLF→LF, tümü CRLF). Yayın
  için referans üretilmez, kodpen.com'a yüklenmez. **Etiket yayından sonra
  taşınmaz** ve paket etiketten üretilir — etiket neyse referans odur.
- `_software_create_hash.php` (yalnız geliştirme makinasında, ürünle dağıtılmaz)
  yalnız dev makinanın **kendi** referansını üretir: `_generated` damgası bu
  host + sürümü gösterince SHA-256 ile denetlenir, GitHub'a sorulmaz. Çalışma
  ağacından üretildiği için (artıklar, push edilmemiş iş) hiçbir yere
  yüklenmez; 2026.4.4 siteleri için kodpen.com'daki bayt tabanlı `[2026.4.4]`
  kopyasına dokunma.
- Kapsam ve yok sayılan adlar tek yerde: `pg_integrity_scope_directories()`
  (`includes/`), `pg_integrity_ignored_files()`, `pg_integrity_in_scope()`;
  üreteç de bunları okur. Kapsam listesini iki yere yazma.
- Etiketi olmayan sürüm ya da GitHub'a erişemeyen sunucu
  `unable_to_fetch_reference` döner ve widget'ta **sarıdır**; bu kurcalama
  değildir, kırmızıya çevirme.
- `clean_up.php` ve `pg_purge_caches()` `data/temp/hash_reference.json`'ı
  **atlamalı** (silmek yetkili referansı uzak kopyayla takas eder);
  `hash_reference_state.json` önbellektir, süpürülür.
- Referans ve denetim hash'i **satır sonundan bağımsızdır**:
  `pg_integrity_hash_file()` (CRLF→LF). Dev klasörü `core.autocrlf=true`, paket
  LF — `hash_file()` ile üretilen referans Windows'ta sağlıklı, sunucuda
  2026.4.4'te 765 dosya "kurcalanmış" diyordu. Denetim önce bayt bayt, sonra bu
  özetle bakar; üreteçte `hash_file()`'a geri dönme.
- Referansta neyin dosya olduğuna **şekil** karar verir
  (`pg_integrity_reference_files()`: değeri 64 haneli onaltılık olan anahtar) —
  isim listesi yazma.
- Yayın öncesi `pinegrap/data/cacert.pem` ve `includes/iyzipay-php/cacert.pem`
  güncel `https://curl.se/ca/cacert.pem` ile bayt bayt aynı olur; ikincisi
  `includes/` içinde olduğu için yalnız yayınla (etiketle) değişir, dev
  makinada değiştirdikten sonra dev referansı yeniden üretilir.
- `clean_up.php` `$file_list`'ine ad eklemeden önce
  `grep -rn "<ad>" --include=*.php --include=*.js .` ile hiçbir şeyin
  çağırmadığını doğrula.

## Yapılandırılmış veri ve robots

- Özel JSON-LD'yi olduğu gibi echo etme: kayıtta `json_decode` ile doğrula,
  çıktıda decode→encode (`pg_custom_jsonld_block()`).
- JSON-LD'yi daima dizi + `json_encode()` ile üret; dizge birleştirme yasak.
- Sahte `review`/`aggregateRating` üretme.
- Özel klasördeki (giriş arkasındaki) sayfayı `robots.txt`'e yazma — dosya
  herkese açıktır, adresi oraya yazmak onu yayınlamaktır.
- Sayfa engelini düz önek olarak yazma (`Disallow: /ara` `/arac-kiralama`'yı da
  engeller); sayfa başına üç çıpalı satır yaz (`…$`, `…?`, `…/`).

## Sunucu kuralları (`includes/server_config.php`)

- Yeni kuralın varsayılanı kendi `detect`'inden geçmek zorundadır; üç sunucunun
  varsayılanını üretip her `detect`'i üstünde koştur.
- Onarım yalnız ekleme yapar, var olanı yeniden yazmaz;
  `DOMDocument::saveXML()` kullanma (operatörün dosyası incelenebilir kalmalı).
- IIS sonucunu `simplexml_load_string` ile doğrulamadan yazma — okunamayan
  `web.config` tüm siteyi 500.19 yapar.
- Yedeği `data/temp/server_config/` içine koy; `.htaccess` yanına konan `.bak`
  düz metin olarak servis edilir.
- Desene `PATH` ekleme (desen zaten o dizine görelidir); eylem/rewrite hedefine
  aittir.
- Klasör adını sabit yazma: `SOFTWARE_DIRECTORY` yoksa
  `pg_server_config_web_root()` / `_software_dir()` ile hesapla — uydurulmuş
  klasör adı, doğru okunan ve hiçbir şeyi korumayan kural üretir.
- Klasör adı taşıyan her yeni kuralı `pg_server_config_retarget()`'a da ekle.
- Sunucu türünü koşulsuz Apache varsayma (`pg_server_config_detect_server()`).

## Güncelleme, paket, TLS

- İndirilen paketi işleme almadan önce HTTP 200 + beklenen bayt + `PK` imzası
  (`pg_looks_like_zip()`) doğrula; çıkarma daima `pg_extract_archive()` ile.
- Güncelleme/lisans/paket ve CA indirmesinde `pg_curl_tls()` (ikizi
  `da_curl_tls()`) kullan; başarısızlıkta güvensize düşme.
- Güncelleme kanalını tek yerden sor (`pg_update_channel()`,
  `pg_update_request_key()`, `pg_update_package_file()`).
- CA paketinde kaynak yalnız https, hedef `data/cacert.pem`, atomik `rename()`.
- OpenSSL anahtarı üreten yolu önce normal dene, başarısızsa
  `'config' => includes/openssl.cnf` ile bir kez daha dene; o dosyaya sağlayıcı
  bölümü yazma.

## Giriş ve oturum

- Giriş sorusu kararını `waf_rate` sayaçlarından ver, oturumdan değil; yanlış
  cevap `pg_login_record_failure` sayılır, offence sayılmaz.
- Kimlik alanlarının ad/id'si giriş formuyla birebir aynı olmalı (parola
  yöneticileri).
- Parola kabul eden yeni uca `pg_login_throttle_guard()` /
  `pg_login_record_failure()` sayacını ekle.
- Dış bir şey bekleyen yeni uca (SMTP, API) kendi sınırını ver
  (`pg_password_reset_guard()` deseni).
- `api.php`'de `API_USERNAME` (gövdede ad gönderildi) ile `API_AUTHENTICATED`
  (parola doğrulandı) karıştırılmaz; `validate_token()` CSRF'i yalnız
  `API_AUTHENTICATED` varsa atlar.
- JSON konuşan uçta reddi de JSON + doğru statü ile ver; `validate_area_access()`
  ve `validate_token_field()` HTML basar ("unexpected token <").
