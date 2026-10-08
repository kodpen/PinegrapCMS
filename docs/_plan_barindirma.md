# Barındırılan Pinegrap — tek tıkla site kurulumu (plan)

Durum: ilk inceleme ve plan (2026-09-29). **Güncel durum, yapılanlar ve sonraki adımlar: `docs/barindirma-platformu.md`.** Bu dosya karar geçmişi için duruyor.

## 1. Hedef

- Onaylanan / ödemesi alınan müşteri için tek tıkla hazır bir Pinegrap sitesi: `mysite203.<kiracı-alanı>`.
- Müşteri kendi alan adını (`www.siteadi.com`) bağlayınca site o adla da açılır, e-posta ve kanonik adresler yeni ada geçer.
- Altyapı: Natro paylaşımlı hosting (cPanel) + Cloudflare.

## 2. Kararlar (Erdal, 2026-09-29)

| Konu | Karar |
|---|---|
| Panel | cPanel (Natro, `natro-thema`). Tokens arayüz sayfası 403 veriyor, ama hesapta `apitokens` özelliği açık ve UAPI çalışıyor (2026-09-29, §3.5) → token UAPI adresinden oluşturulabilir |
| Kiracı alan adı | pinegrap.com değil, ayrı bir alan adı (çerez ve itibar ayrımı) |
| Kayıt akışı | Onaylı / ücretli: site Erdal onayından ya da ödemeden sonra açılır |
| Model | Başlangıçta **her siteye tam kopya** (Model A). Tek kod + site başına veri (Model B) maliyet kaygısıyla ertelendi, bkz. §9 |

## 3. İncelemede bulunanlar

### 3.1 PHP_REGIONS'ı kapatmak tek başına yetmiyor

Aynı cPanel hesabındaki bütün siteler aynı sistem kullanıcısıyla çalışır. Bir sitede PHP çalıştırabilen biri
öbür sitelerin `data/config.php` dosyalarını, DB parolalarını ve hesaptaki bütün anahtarları okur.
PHP_REGIONS'tan bağımsız açık kalan yollar:

| Yol | Nerede | Kimde |
|---|---|---|
| PHP_REGIONS / DYNAMIC_REGIONS panelden yeniden açılabiliyor, DB parolası görünüyor | `edit_config.php` | yönetici |
| Görsel editör `custom_php` düğümü, PHP_REGIONS'a bakılmadan eval | `get_page_content.php` → `includes/fn/designer.php` `_expand_custom_php()` | yönetici, tasarımcı |
| Özel sayfa düzeni PHP dosyası olarak yazılıp include ediliyor | `api.php` `update_layout` → `data/layouts/<id>.php`, `includes/fn/content.php` include | yönetici |
| Dinamik bölgeler | `get_page_content.php`, `page_designer.php` | yönetici (DYNAMIC_REGIONS) |
| Form / sipariş kancaları | `custom_form.php`, `submit_order.php`, `edit_submitted_form.php`, `shopping_cart.php` | PHP_REGIONS'a bağlı |

Dosya yükleme tarafı sağlam: `pg_blocked_upload_extensions()` / `pg_blocked_upload_names()` php, .htaccess, .user.ini vb. engelliyor.

### 3.2 Veritabanı

- Tablo öneki yok (tablo adları kod boyunca sabit): her siteye ayrı DB.
- Her siteye ayrı MySQL kullanıcısı: `max_user_connections` kullanıcı başına; ortak kullanıcıda bir sitenin trafiği hepsini düşürür.
- Kurulum yalnız web formuyla (`install/index.php`, liveform). Başsız kurulum yok → §6.2.

### 3.3 Kaynak

- Bir kopya: ~3.500 dosya, 419 klasör, 92 MB (`data/` hariç). Natro hesabında **inode sınırı yok** (`inode_limit 0`, kullanılan 237.611) → Model A için engel değil.
- CPU / RAM / süreç (CloudLinux LVE) hesap başına, bütün siteler aynı havuzdan yer: CPU %100 (1 çekirdek), RAM 2 GB, giriş süreci 100, süreç 200, IO 100 MB/s, IOPS 25.600. Asıl tavan bunlar.

### 3.4 Cloudflare (2026-09-29, yalnız okuma)

- pinegrap.com zone'u Free plan, origin 94.73.151.12 (`cpls43.srvpanel.com`, Natro). SSL modu **Flexible**.
- Custom Hostnames (Cloudflare for SaaS) açık değil: API `1404 No quota has been allocated`. Panelden SSL/TLS → Custom Hostnames açılmalı (ödeme yöntemi ister). 100 alan adı ücretsiz, sonrası alan adı başına aylık $0.10.
- Free'de kök alan (apex) proxy yok: müşteri `www`'yi CNAME'ler, kökü kendi DNS'inde www'ye yönlendirir.
- Host başlığı değiştirme yalnız Enterprise'da: `www.siteadi.com` isteği hostinge kendi adıyla gelir → her bağlanan alan cPanel'e addon domain olarak eklenmeli (docroot = sitenin klasörü).

### 3.5 Natro cPanel hesabı (2026-09-29, yalnız okuma, Erdal'ın açtığı oturumdan)

- UAPI oturumla çalışıyor. `Features::list_features`: `apitokens` 1, `mysql` 1, `subdomains` 1, `addondomains` 1, `parkeddomains` 1, `cron` 1, `sslinstall` 1; `ssh` 0, `api_shell` 0, `autossl` 0, `multiphp` 0, `zoneedit` 0.
- `Tokens::list` boş dönüyor (henüz token yok). Tokens arayüz sayfası temada 403 verse de token `execute/Tokens/create_full_access` ile oluşturulabilir. Bunu Erdal kendisi yapar.
- DB kısıtları: önek `u1837636_`, DB adı en fazla 64, kullanıcı adı en fazla 47 karakter.
- SSH / terminal yok → hesap içi işler PHP ajan betiğiyle (§6.3) yapılır. AutoSSL kapalı → origin sertifikası Cloudflare Origin CA ile elle kurulur (birincil alanda kurulu Origin CA sertifikasının süresi dolmuş görünüyor).
- **Bu hesap dolu:** ana alan hobili.net; 17 addon domain (kodpen.com, pinegrap.com, triadyonetim.com ve müşteri siteleri), 26 alt alan (dev / crm / erp.kodpen.com, cigdemabide.pinegrap.com …), 29 DB. Müşteri siteleri bu hesaba açılırsa, bir sitede çıkacak PHP açığı bütün mevcut müşteri sitelerini ve kodpen.com'u açar. Bu yüzden kiracılar için **ayrı cPanel hesabı** şart.

## 4. Mimari

```
Yönetim aracı (ayrı hesap / sunucu)          Kiracı cPanel hesabı (yalnız müşteri siteleri)
  - başvuru / onay / ödeme                      /home/<u>/sites/<slug>/        (tam Pinegrap kopyası)
  - cPanel UAPI token  ───────────────────►     MySQL: <u>_s<id> + kullanıcı <u>_s<id>
  - Cloudflare token   ──► Cloudflare           subdomain <slug>.<kiracı-alanı> → sites/<slug>
                            *.<kiracı-alanı>     addon domain www.siteadi.com → sites/<slug>
                            custom hostnames     tek cron → platform/cron.php → her site
```

- **Hesap ayrımı:** müşteri siteleri yeni bir cPanel hesabında, ana alan adı kiracı alanı. pinegrap.com ve yönetim aracı bu hesapta olmaz. Böylece bir site delinse bile anahtarlar ve pinegrap.com erişilemez.
- **Anahtarlar** (cPanel API token, Cloudflare token) yalnız yönetim aracının ayar dosyasında. Kiracı sitelerin `config.php`'sine asla girmez (Pinegrap'ın `cloudflare.php` ekranı `CLOUDFLARE_API_TOKEN`'ı config'den okur).
- **Wildcard'ın rolü (Model A):** Cloudflare'de tek `*` kaydı → her sitede DNS işi yok. cPanel wildcard alt alanı yalnız yakalama sayfası ("bu site yok / askıda"); her sitenin kendi alt alanı (`SubDomain::addsubdomain`) wildcard'dan önce eşleşir ve kendi klasörüne gider.
- **DNS / SSL:** `*.<kiracı-alanı>` wildcard, proxied → hosting IP. SSL modu Full; origin'de Cloudflare Origin CA sertifikası (`*.alan` + `alan`). Custom hostname trafiği için Full (strict değil) ya da cPanel AutoSSL.

## 5. Site açma adımları (yönetim aracı)

Her adım idempotent; hata olursa geri alma listesi tutulur.

1. `slug` üret / doğrula (a-z0-9-, 3–30, ayrılmış adlar: www, mail, admin, api …).
2. cPanel `SubDomain::addsubdomain` → docroot `sites/<slug>`.
3. cPanel `Mysql::create_database`, `Mysql::create_user` (rastgele parola), `Mysql::set_privileges_on_database` (ALL, yalnız o DB).
4. Dosyalar: hesapta duran "altın kopya" klasöründen `sites/<slug>/` altına kopya (hesap içi kopya, dar yetkili ajan betiği ile; §6.3).
5. `data/config.php` yaz: DB bilgisi, yeni `ENCRYPTION_KEY`, `PG_HOSTED`, `PHP_REGIONS=false`, `DYNAMIC_REGIONS=false`, `MIG=false`, platform SMTP.
6. Altın DB dökümünü içe aktar → `config.hostname`, `title`, `email_address` güncelle; yönetici kullanıcıyı müşterinin e-postasıyla oluştur, parola sıfırlama bağlantısı gönder (parola üretip göndermek yok).
7. Site başına gizli değerleri yeniden üret (bkz. §6.2 kontrol listesi).
8. Cloudflare'de iş yok (wildcard kapsar). Sağlık kontrolü: `https://<slug>.<kiracı-alanı>/` 200 dönüyor mu.
9. Müşteriye hoş geldin e-postası.

## 6. Yazılım değişiklikleri

### 6.1 Barındırılan mod — `PG_HOSTED` (Faz 1, her iki modelde gerekli)

`config.php`'de tanımlanır; mod açıkken `edit_config.php` kapalı olduğu için müşteri değiştiremez.

- `init.php`: `PG_HOSTED` açıksa PHP_REGIONS / DYNAMIC_REGIONS / MIG her durumda kapalı sayılır (config'de true yazsa da).
- `edit_config.php`: kapalı (ya da yalnız okunur, parolasız).
- `_expand_custom_php()`: eval yok, düğüm boş çıkar; editörde `custom_php` düğümü eklenemez.
- Özel düzen: `update_layout` yazmaz, `content.php` `data/layouts/*.php` include etmez; üretilen düzen kullanılır.
- Menüden ve doğrudan adresten kapalı: `cloudflare.php`, `migration.php`, yazılım güncelleme (platform günceller), SMTP ayarları (platform SMTP).
- Sistem durumu widget'ı: izin onarımı (0777) gibi hosting'e dokunan düğmeler gizli.
- Kullanıcıya görünen her metin `lang()` + `tr.json`; `docs/degisiklikler.md` + `pinegrap/changelog.txt`.

### 6.2 Altın kopya ve başsız kurulum (Faz 1)

- Başlangıç: bir kez web formuyla "Önerilen" tasarım şablonuyla kurulmuş altın site → DB dökümü + dosya klasörü.
- Kontrol listesi — altın dökümden siteye taşınmaması / yeniden üretilmesi gerekenler: `ENCRYPTION_KEY` ile şifrelenmiş alanlar (dökümde olmamalı), push (VAPID) anahtarları, API anahtarları, lisans / kurulum kimliği, oturum ve token tabloları, günlükler. Tam liste Faz 1'de kod okunarak çıkarılacak.
- İleride: `php install/index.php install --...` başsız kurulum yolu (açık kaynak kullanıcılara da yarar: Docker vb.).

### 6.3 Hesap içi ajan betiği (Faz 2)

Kiracı hesabında, web kökü dışında `platform/agent.php`: yalnız "altın kopyayı klasöre kopyala, config yaz, dökümü içe aktar, siteyi güncelle" işleri. HMAC imzalı istek + zaman damgası; cPanel ya da Cloudflare anahtarı bu hesapta durmaz.

## 7. Alan adı bağlama (Faz 3)

1. Müşteri panelde `www.siteadi.com` yazar → yönetim aracına istek.
2. Cloudflare `POST /zones/<kiracı-zone>/custom_hostnames` (ssl: http doğrulama) → müşteriye CNAME talimatı: `www` → `sites.<kiracı-alanı>` (fallback origin).
3. cPanel `AddonDomain` (docroot = `sites/<slug>`).
4. Doğrulama tamamlanınca Pinegrap `config.hostname` = `www.siteadi.com`; eski alt alan yeni ada 301.
5. Kaldırma: sırayla geri.

## 8. İşletme (Faz 4)

- Tek cron → `platform/cron.php` → her site için `php sites/<slug>/pinegrap/job.php` (CLI davranışı Faz 4'te doğrulanacak).
- Güncelleme: altın kopya güncellenir, sonra sırayla her site `php install/index.php automated_upgrade`; hata veren sitede durur, raporlar.
- Yedek: site başına DB dökümü + `data/files`, hesap dışına.
- Askıya alma: subdomain docroot'u "askıda" sayfasına çevrilir, veri silinmez.

## 9. Model B (ertelendi)

Tek kod klasörü + site başına `data/`. LiveSite'tan kalma kancalar duruyor (`CONFIG_FILE_PATH`, `FILE_DIRECTORY_PATH`,
`LAYOUT_DIRECTORY_PATH`, `HTACCESS_FILE_PATH`), ama sonradan ~110 yerde (26 dosya; en çok `install/index.php` ~40,
`includes/fn/system_status.php` 12) `data/` yolu sabit yazılmış.

- Maliyet: yolları tek sabite (`PG_DATA_PATH`, varsayılanı bugünkü `data/`) bağlamak mekanik; tek kurulumlar hiç etkilenmez.
- Asıl yük: Host → site seçimi, kısa bağlantıların `.htaccess`'e yazılması (ortak dosya), güncelleme ve bütünlük denetiminin siteden alınması, yedek / WAF / kurulum ekranlarının yeniden denenmesi.
- Ne zaman: güncelleme süresi ya da bakım yükü sorun olunca (Natro'da inode sınırı olmadığından o gerekçe düştü). Yönetim aracı "site açma" katmanı ayrı yazılırsa geçişte yalnız o katman değişir.

## 10. Erdal'dan gerekenler

- [x] cPanel API: arayüz sayfası 403, ama `apitokens` açık ve UAPI çalışıyor (§3.5)
- [x] Inode sınırı: yok
- [ ] Müşteri siteleri için Natro'da **ayrı cPanel hesabı** (ana alan = kiracı alan adı)
- [ ] O hesapta API token'ı Erdal oluşturur: oturum adresinin `/cpsess…/` kısmından sonra `execute/Tokens/create_full_access?name=pinegrap-kurulum` açılır, dönen token bir kez görünür, yalnız yönetim aracının ayar dosyasına girilir (sohbete yazılmaz)
- [x] Wildcard alt alan: açılabiliyor (Erdal ana hesapta `*.pinegrap.com` → `/_wildcard_.pinegrap.com` açtı, 2026-09-29). Cloudflare'de `*` kaydı olmadığı için şu an trafik almıyor; kalıcı kurulum yeni hesapta kiracı alanıyla yapılacak
- [x] pinegrap.site (2026-09-29): Cloudflare'de `*` ve kök A kaydı (proxied, 94.73.151.12), SSL modu Full; cPanel'de addon domain (`/home/u1837636/pinegrap.site`) + wildcard (`/home/u1837636/_wildcard_.pinegrap.site`, içinde "bu site bulunamadı" test sayfası)
  - Deneme: `http://` ve (Origin CA sertifikası `*.pinegrap.site` + `pinegrap.site` Erdal tarafından cPanel'e kurulduktan sonra) `https://<herhangi>.pinegrap.site` wildcard klasörüne düşüyor ✔
  - Açık: Cloudflare'de Always Use HTTPS kapalı, Minimum TLS 1.0 (öneri: açık / 1.2)
  - Imunify360, veri merkezi IP'lerinden gelen HTTP isteğine "One moment, please…" JS sınaması gösteriyor; yönetim aracının sağlık kontrolü bundan etkilenebilir
- [ ] Natro'ya sorulacaklar:
  - 2083 portu dışarıdan (yönetim aracının sunucusundan) API için erişilebilir mi, IP kısıtı var mı?
  - Bayi (reseller / WHM) paketi seçeneği var mı? (her siteye ayrı cPanel hesabı, ayrı sistem kullanıcısı)
- [ ] Kiracı alan adı seçimi + Cloudflare'e eklenmesi
- [ ] Müşteri siteleri için ayrı cPanel hesabı
- [ ] Cloudflare: Custom Hostnames'in açılması; paylaşılan token'ın yenilenmesi ve yalnız gereken yetkilerle (Zone:Read, DNS:Edit, SSL and Certificates:Edit) yeniden oluşturulması
- [ ] Yönetim aracının nerede duracağı (ayrı hesap / sunucu)

### cPanel API kapalıysa

- DB: cPanel'de elle 10–20'lik DB + kullanıcı havuzu açılır, yönetim aracı sıradakini kullanır.
- Alt alan: wildcard `*.<kiracı-alanı>` tek klasöre; oradaki `.htaccess` Host'a göre `sites/<slug>/` altına yeniden yazar, site `config.php`'de `PATH` sabitlenir (router.php:230'daki koşulsuz `define('PATH')` `defined()` kontrolüne alınmalı).
- Müşteri alan adı: addon domain elle eklenir (müşteri başına ~1 dk). Onaylı akışta yönetilebilir, ama tam otomasyon yok.
- Böylece elle kalan iş yalnız: DB havuzunu ara ara doldurmak + bağlanan alan adını addon olarak eklemek. Gerisi (dosya, config, DB içe aktarma, Cloudflare) otomatik.
- Kalıcı çözüm istenirse: bayi paketi ya da küçük bir VPS (siteye ayrı sistem kullanıcısı, inode sınırı yok, API serbest).

## 10b. Durum (2026-09-29)

- **Ürün (2026.4.6, çalışma ağacında, commit yok):** `edit_config.php` kaldırıldı; `PG_HOSTED` / `PG_DEMO` kipleri; `import_design.php` yükleme kuralları. Ayrıntı `docs/degisiklikler.md`.
- **Platform (`dev/hosting/`):** `pgplatform/lib.php`, `pgplatform/demo_reset.php`, `_pg_fetch.php`, `_pg_patch.php` + `patch_4_6_hosted.json`, `_pg_run.php`, `_pg_demo_seed.php`. Şablonlardaki anahtar yer tutucusu (`__KEY__`) kabul edilmez (20 karakterden kısa anahtar reddedilir).
- **demo.pinegrap.site:** `/home/u1837636/pgsites/demo`, v2026.4.5 + `patch_4_6_hosted.json`; `PG_HOSTED` + `PG_DEMO`; barındırma varsayılanları uygulandı; Çalışma Alanı örnek verisi yüklendi; anlık görüntü `/home/u1837636/pgplatform/demo/` (260 tablo, 4.305 dosya).
- **Cron (ana hesap):** saatlik `demo_reset.php`, 5 dakikada bir demo `job.php`. Test sitesi t1 platform çöpüne taşındı (`pgplatform/data/trash/`).
- Kullanıcı adı / parola: admin / admin (tanıtım için bilerek).

## 10c. Yönetim platformu (2026-09-29, kuruldu)

Natro hesap bazında API token açmıyor (yanıt: VPS / VDS gerekir). Bu yüzden ilk sürüm API'siz çalışır; sunucuya bağlı her şey bir sürücüde (`PGP_Driver`), sunucu değişince yalnız yeni sürücü yazılır (ör. VPS'te gerçek vhost, site başına DB kullanıcısı).

- **Yerleşim (ana hesap u1837636):** platform `/home/u1837636/pgplatform/` (web kökü dışında; `config.php` 0600, `data/` 0700); panel girişi `pinegrap.site/yonetim/index.php` (yalnız bu dosya web kökünde). Kaynak: `dev/hosting/pgplatform/`, `dev/hosting/yonetim/`, dağıtım `dev/hosting/_pgp_deploy.php` (anahtarlı; paket gövdede POST edilir, SHA-256 denetlenir, kendini siler).
- **Veri:** platformun kendi MySQL veritabanı (`u1837636_yonetim.pinegrap.site`): `pgp_settings`, `pgp_admin`, `pgp_sites`, `pgp_hosts`, `pgp_pool`, `pgp_jobs`, `pgp_events`, `pgp_login_attempts`. Veritabanı ve havuz parolaları panelin ilk kurulum / ayarlar ekranından `config.php`'ye girer, tablolara girmez.
- **Siteler:** hepsi wildcard klasöründe `s/<slug>/` altında tam kopya. `_pgfront.php` Host'tan siteyi seçer (`data/hosts.php`), `DOCUMENT_ROOT` / `SCRIPT_NAME` / `PHP_SELF` / `PATH_INFO`'yu kendi vhost'u varmış gibi kurar; `/s/`, gizli dosyalar, `pinegrap/data`, `pinegrap/includes` kapalı. Etkin sitenin statik dosyalarını LiteSpeed doğrudan verir (`.pg-active`). Askıdaki site 503 "şu anda kapalı" sayfası; kurulum sihirbazı aşamasındaki site yalnız panelin verdiği anahtarla (`?pgp_gate=`, bir günlük çerez) açılır.
- **Akış:** panel isteği kuyruğa yazar; kuyruk yanıt gittikten hemen sonra aynı istekte çalışır (`litespeed_finish_request`), cron her dakika da çalıştırır. İşler: `source` (GitHub sürümü + yama → kurulum sihirbazı), `finalize` (barındırma sabitleri ve ayarları), `golden` (kaynak siteden altın kopya; oturum / günlük / sayaç tablolarının yalnız yapısı), `create` (altın kopyadan site: havuzdan DB, yeni `ENCRYPTION_KEY`, hostname / başlık / e-posta, yönetici hesabı + tek seferlik parola), `check`, `suspend`, `resume`, `delete` (dosyalar + DB dökümü çöpe, DB boşaltılıp havuza), `purge`, `domain_verify`.
- **Kontrol:** dosyalar, `config.php` (PG_HOSTED, PHP_REGIONS kapalı), DB bağlantısı ve tablo sayısı, birincil adrese HTTPS isteği — yanıtın bu siteden geldiği `X-PG-Site` başlığıyla doğrulanır. Açılışta ve `check_every_min` aralığıyla.
- **DB havuzu:** Erdal cPanel'de DB'leri açıp ortak kullanıcıyı (TÜM YETKİLER) ekler, adlarını panele yazar; her ad eklenirken denenir (bağlanılamıyor ya da boş değilse "kirli", kullanılmaz). Platformun ve demonun DB'si havuza alınmaz.
- **Müşteri alan adı:** panelde eklenir; cPanel'de addon domain (belge kökü = wildcard klasörü) ve DNS elle; "Kontrol et" doğrular, "Birincil yap" diğer adları 301 ile yönlendirir ve sitenin `hostname` ayarını değiştirir.
- **Sunucu bulguları:** web PHP'sinde `proc_open`, `exec`, `mail` kapalı (`disable_functions`). Sitelerin genel işi için panel, cron PHP'sinde de `proc_open` kapalıysa ikinci bir kabuk cron satırı gösterir. `mail()` kapalı olduğundan barındırılan sitelerin e-postası SMTP ile ayarlanmalı.
- **Cron (2026-09-29, eklendi):** `* * * * * php pgplatform/cron.php` (kuyruk, kontroller) ve, cron PHP'sinde de `proc_open` kapalı olduğu için, 5 dakikada bir kabuk satırı (`flock` + her `s/*/.pg-active` sitenin `job.php`'si). Platform sitelerin genel işinin çalıştığını her sitenin `cron_runs` kaydından okur; panel uyarısı buna göre kalkar.
- **Altın kopya (2026-09-29):** kaynak `sablon` (Türkçe başlangıç sitesi + Önerilen tasarım, 32 sayfa). Canlı deneme `deneme1`: açılış 7 sn, HTTPS kontrolü 200, askıya alma 503, yeniden açma, yönetici parolası doğrulandı. Başlangıç sitesinin örnek verisi (siparişler, form gönderimleri, ürünler) altın kopyada bilerek kalıyor (Erdal: kullanıcı örnekten kopyalayarak ekler; kişisel bilgi yok). `cron_runs`, kuyruk ve ERP günlük tabloları da yalnız yapı olarak alınıyor.
- **Olay (2026-09-29 16:00):** ilk kurulumda platform DB'si olarak demonun DB'si girilmişti; demonun saatlik sıfırlaması DB'deki bütün tabloları sildiği için platform kayıtları gitti (siteler, dosyaları ve DB'leri etkilenmedi). Önlem: kurulum ekranı başka tablo içeren ya da demoya ait DB'yi reddeder; kurulum diskteki siteleri (klasör + config.php + son `hosts.php`) ve kullandıkları DB'leri kayda geri alır (`pgp_import_existing_sites`). Ziyaretçi, form görüntülenme ve benzeri sayaç tabloları altın kopyada yalnız yapı (AUTO_INCREMENT 1'den); yeni sitede ziyaretçi özeti ve form görüntülenme sayıları sıfırlanır.
- **Yeniden kurulum (16:37):** platform DB'si `u1837636_yonetim.pinegrap.site`; `sablon` ve `deneme1` diskten geri alındı, havuza site3–site7 yeniden eklendi, altın kopya 16:39'da yenilendi. Canlı deneme `deneme2`: ziyaretçi / istatistik / form görüntülenme tabloları boş başladı (numaralar 1'den), örnek sipariş / ürün / form verisi duruyor. Sağlık kontrolü `Pinegrap/<sürüm>` kullanıcı ajanıyla gider: site onu ziyaretçi saymaz, WAF'ı engellemez.
- **Bilinen risk:** siteler kodpen.com ve diğer müşteri siteleriyle aynı sistem kullanıcısında; ortak DB kullanıcısı bir sitedeki SQL açığında diğer DB'lere de erişir (§3.5). Kalıcı çözüm VPS sürücüsü.
- **Test (bulut, MariaDB 10.11 + php -S):** ilk kurulum, havuz, kaynak site + gerçek kurulum sihirbazı (anahtarlı ön denetleyici üzerinden), tamamlama, altın kopya (260 tablo, 4.093 dosya, 8 sn), altın kopyadan site (2 sn), askıya alma / açma, alan adı ekleme / doğrulama / birincil, silme → çöp → kalıcı silme, aynı adla yeniden açma, `job.php` (proc_open ve kabuk satırı), giriş sınırı, CSRF.

## 11. Fazlar

| Faz | İş | Bağımlılık |
|---|---|---|
| 1 | `PG_HOSTED` + altın kopya + kontrol listesi | yok, hemen başlanabilir |
| 2 | Yönetim aracı + ajan betiği + site açma | cPanel API, kiracı alanı, ayrı hesap |
| 3 | Alan adı bağlama | Custom Hostnames açık |
| 4 | Cron, güncelleme, yedek, askıya alma | Faz 2 |
| 5 | Başvuru / ödeme akışı (pinegrap.com) | Faz 2 |
