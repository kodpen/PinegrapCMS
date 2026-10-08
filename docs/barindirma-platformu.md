# Pinegrap barındırma platformu — yapılanlar ve plan

Tarih: 2026-09-29 · Sunucu: Natro cPanel ana hesabı `u1837636` · Alan: `pinegrap.site` (Cloudflare)

Tek tıkla hazır Pinegrap sitesi: `<ad>.pinegrap.site`. Siteler panelden kuyruğa girer, altın kopyadan açılır, açılınca kontrol edilir. Müşteri alan adı bağlama şimdilik elle yapılıyor (aşağıda "Kararlar").

Eski plan ve inceleme notları: `docs/_plan_barindirma.md`. Bu dosya güncel durumdur.

---

## 1. Nerede ne var

| Ne | Nerede |
|---|---|
| Yönetim paneli | https://pinegrap.site/yonetim/ — kullanıcı `admin` |
| Platform kodu (sunucu) | `/home/u1837636/pgplatform/` — web kökü dışında, klasör 0700 |
| Platform ayarı | `pgplatform/config.php` (0600): platform DB + havuzun ortak kullanıcısı |
| Platform DB | `u1837636_yonetim.pinegrap.site` (yalnız platformun; başka tablo içeremez) |
| Havuzun ortak DB kullanıcısı | `u1837636_pinegrapsitecommon` |
| Siteler | `/home/u1837636/_wildcard_.pinegrap.site/s/<ad>/` (her biri tam Pinegrap kopyası) |
| Ön denetleyici | `/home/u1837636/_wildcard_.pinegrap.site/_pgfront.php` + `.htaccess` |
| Adres haritası | `pgplatform/data/hosts.php` (platform yazar) |
| Altın kopya | `pgplatform/data/golden/` (`db.sql.gz`, `site.zip`, `manifest.json`, `meta.json`) |
| Çöp | `pgplatform/data/trash/` (silinen sitelerin dosyaları + DB dökümleri) |
| Demo | `/home/u1837636/pgsites/demo` → demo.pinegrap.site (admin/admin, saatlik sıfırlama) |
| Kaynak kod (repo) | `dev/hosting/pgplatform/`, `dev/hosting/yonetim/`, `dev/hosting/_pgp_deploy.php` |

Platform dosyaları: `bootstrap.php` (yollar, ayar), `store.php` (tablolar), `driver.php` (sunucuya bağlı işler — paylaşımlı hosting sürücüsü), `provision.php` (işler: site aç, altın kopya, kontrol, askı, sil, geri alma), `cron.php`, `panel*.php` (panel), `lib.php` (DB dökümü / geri yükleme, dosya anlık görüntüsü, config yazma, HTTP kontrolü), `demo_reset.php`, `templates/` (ön denetleyici + `.htaccess` şablonu), `patches/patch_4_6_hosted.json`.

## 2. Nasıl çalışır

- **Tek wildcard klasörü, çok site.** Cloudflare'de `*.pinegrap.site` proxied → 94.73.151.12; cPanel'de wildcard alt alan adı tek klasöre gider. `_pgfront.php` Host'a bakar, `hosts.php`'den siteyi bulur, `DOCUMENT_ROOT` / `SCRIPT_NAME` / `PHP_SELF` / `PATH_INFO`'yu sitenin kendi sunucusu varmış gibi kurar ve sitenin PHP dosyasını çalıştırır. Etkin sitenin statik dosyalarını LiteSpeed doğrudan verir (`.pg-active`). `/s/`, gizli dosyalar, `pinegrap/data`, `pinegrap/includes` dışarıya kapalı.
- **Kuyruk.** Paneldeki her iş `pgp_jobs` tablosuna yazılır; panel yanıtı gittikten hemen sonra aynı istekte çalışır (`litespeed_finish_request`), cron her dakika da çalıştırır. Her işin adım adım kaydı panelde.
- **Yeni site = altın kopya.** Havuzdan boş bir DB alınır, altın kopyanın dosyaları ve DB'si yüklenir; siteye kendi `ENCRYPTION_KEY`'i, hostname / başlık / e-posta, barındırma sabitleri yazılır; altın kopyadaki tek yönetici hesabı müşterinin kullanıcı adı / e-postası / rastgele parolasıyla değiştirilir (parola panelde bir kez gösterilir). Ziyaretçi özeti ve form görüntülenme sayaçları sıfırlanır. Sonra site kontrol edilir (dosyalar, config.php, DB, HTTPS isteği — yanıtın bu siteden geldiği `X-PG-Site` başlığıyla doğrulanır). Süre ~7 sn.
- **Altın kopyada yalnız yapı (veri yok):** oturum / token, günlük, WAF, ziyaretçi ve istatistik tabloları, form görüntülenme kayıtları, `cron_runs`, kuyruklar, ERP günlükleri (numaralar 1'den başlar). Örnek siparişler, ürünler ve form gönderimleri bilerek kalır.
- **Barındırılan site ayarları** (`PG_HOSTED`): PHP bölgeleri, dinamik bölgeler, MIG, otomatik yedek ve güncelleme denetimi kapalı; `edit_config.php` yok; özel düzen / özel PHP kapalı; yazılım güncellemesi siteden yapılamaz. DB tarafında WAF engelleme kipinde, bilinmeyen bot engeli açık, zamanlanmış işler genel işten (otomatik yedek hariç), Workspace ve ERP açık, bağlantılar https.
- **DB havuzu.** Tüm site DB'leri tek ortak kullanıcıyla. Havuza eklenen her DB denenir: bağlanılamıyor ya da boş değilse "kirli", kullanılmaz. Site silinince DB boşaltılıp havuza döner.
- **Sağlık kontrolü** `Pinegrap/<sürüm>` kullanıcı ajanıyla gider: site onu ziyaretçi saymaz, WAF'ı engellemez.

## 3. Panelden işler

- **Yeni site:** Yeni site → ad (`mysiteNNNN` önerilir), başlık, e-posta, yönetici kullanıcı adı → Kuyruğa ekle. Site sayfasında ilk parola ve yönetim bağlantısı görünür ("Kaydettim, gizle" ile kaldırılır).
- **Altın kopyayı güncellemek:** kaynak site `sablon` (sablon.pinegrap.site) üzerinde tasarım / içerik değiştirilir → Altın kopya → Güncelle. Açık sitelere dokunulmaz.
- **Yeni kaynak site (yeni sürümde):** Yeni site → "Kaynak site" → sürüm GitHub'dan indirilir (+ yama) → site sayfasındaki anahtarlı bağlantıyla kurulum sihirbazı (DB ve yönetici parolasını Erdal yazar) → "Tamamla" → Altın kopya → Güncelle.
- **Havuz:** cPanel → MySQL Veritabanları'nda DB aç, ortak kullanıcıyı TÜM YETKİLER ile ekle → panelde Veritabanı havuzu → adları her satıra bir tane yaz.
- **Askıya alma / açma:** site sayfasından; askıdaki site "şu anda kapalı" (503) gösterir. Cloudflare önbelleğindeki statik dosyalar süreleri dolana kadar sunulabilir.
- **Silme:** site adını yazarak; dosyalar + DB dökümü Çöp'e, DB boşaltılıp havuza. Çöp → "Kalıcı sil" elle.
- **Müşteri alan adı (elle):** Erdal alan adını bildiği yolla cPanel'de ek alan adı olarak ekler (belge kökü `_wildcard_.pinegrap.site`), DNS'i sunucuya yönlendirilir; panelde site sayfasında alan adı eklenir → "Kontrol et" → "Birincil yap". www ve www'suz ad ayrı eklenir; birincil olmayan birincile 301 ile yönlenir.
- **Ayarlar:** ana alan, sunucu IP, PHP yolu, kaynak sürüm etiketi + yama, kontrol aralığı; havuz kullanıcısı / parolası (yalnız yazılır); yönetici parolası; ön denetleyiciyi yeniden yaz; tabloları güncelle.

**Cron (cPanel'de ekli):**
- `* * * * * /usr/local/bin/php /home/u1837636/pgplatform/cron.php` — kuyruk, düzenli kontroller.
- `*/5 * * * * flock -n …/sitejobs-shell.lock sh -c 'for d in …/s/*/; do [ -f "$d.pg-active" ] && php -q "${d}pinegrap/job.php"; done'` — sitelerin genel işi (sunucuda `proc_open` kapalı olduğu için kabuktan).
- Demo: saatlik `demo_reset.php`, 5 dakikada bir demo `job.php`.

## 4. Yapılanlar

**Ürün (2026.4.6, çalışma ağacında, commit yok)**
- `edit_config.php` kaldırıldı (menüden de; `clean_up.php` eski kurulumlarda siler).
- `PG_HOSTED` / `PG_DEMO` kipleri: özel PHP / özel düzen / tasarım içe aktarma / yazılım güncellemesi kapalı; demoda e-posta gönderilmez, kurulum ekranı hiçbir şey yapmaz.
- `import_design.php`: `data/files` içine `.htaccess` vb. yazılmasını engelleyen yükleme kuralı (her sitede).
- Ayrıntı: `docs/degisiklikler.md`, `pinegrap/changelog.txt`. Sunucudaki siteler v2026.4.5 + `patch_4_6_hosted.json` ile bu kapıları taşıyor.

**Demo (demo.pinegrap.site)** — admin/admin, Workspace örnek verisi, saatlik geri yükleme (DB + değişen dosyalar, ~12–15 sn).

**Platform** — ön denetleyici, panel (giriş sınırı, CSRF, CSP, oturum zaman aşımı), kuyruk, havuz, altın kopya, kaynak site akışı (anahtarlı kurulum sihirbazı), kontrol, askı, silme / çöp, alan adı kaydı, cron, diskten geri alma, dağıtım betiği.

**Sunucudaki durum (29.09 16:45)**
- Siteler: `sablon` (altın kopyanın kaynağı, DB site1), `deneme1` (site2), `deneme2` (site3) — son ikisi deneme.
- Havuz: `u1837636_pinegrapsite_site4` – `site7` boş.
- Altın kopya: 29.09.2026 16:39, sürüm 2026.4.5, 260 tablo, 4.302 dosya, 93,5 MB. Kaynak: Türkçe başlangıç sitesi + "Pinegrap'a merhaba deyin" (Modern Yumuşak), 32 sayfa.
- Çöp: t1 test sitesi, wiendi.

**Denenenler (canlı)** — site açma (7 sn, HTTPS 200), yönetici parolası doğrulaması, askıya alma (503) / açma, silme → DB'nin havuza dönmesi ve yeniden kullanılması, ziyaretçi tablolarının yeni sitede boş başlaması, kurulum kilidi, `config.php` / iç klasörlerin 403 vermesi, cron ve kabuk satırıyla genel iş, sağlık kontrolünün ziyaretçi sayılmaması.

## 5. Kararlar (Erdal)

- Şimdilik bu sunucu (Natro ana hesap); sunucu değişince yalnız yeni sürücü yazılacak.
- Her site tam kopya (tek kod + çok veri değil).
- Yeni siteler altın kopyadan; panel `pinegrap.site/yonetim`, kullanıcı `admin`.
- DB'leri Erdal açıp havuza ekler; DB kullanıcısı ortak.
- Altın kopyada örnek sipariş / ürün / form verisi kalır (kullanıcı örnekten kopyalayarak ekler; kişisel bilgi yok); ziyaretçi ve sayaç verisi gitmez.
- **Müşteri alan adını otomatik bağlama iptal (şimdilik).** Natro ek alan adı için TXT doğrulaması istiyor, cPanel API yok. Alan adı gerektiğinde Erdal elle bağlar; panelin alan adı kaydı / kontrolü / birincil yapma kısmı bu elle akışta kullanılır.

## 6. Olay kaydı

**29.09 16:00 — platform kayıtları silindi.** İlk kurulumda platform DB'si olarak demonun DB'si girilmişti; demonun saatlik sıfırlaması DB'deki bütün tabloları silip yüklediği için platform tabloları da gitti. Siteler, DB'leri ve altın kopya etkilenmedi. Düzeltme: kurulum yeniden `u1837636_yonetim.pinegrap.site` ile yapıldı, siteler diskten geri alındı (`pgp_import_existing_sites`). Önlem: kurulum ekranı başka tablo içeren ya da demoya ait DB'yi reddeder. Eski ayar dosyası `pgplatform/data/config-demo-db-20260929-161124.php` (içinde parola var; istenirse silinebilir).

## 7. Sunucu kısıtları

- `disable_functions`: `exec`, `shell_exec`, `system`, `passthru`, `proc_open`, `popen`, `mail` … kapalı (web ve cron PHP'si). Sonuç: sitelerin e-postası **SMTP** ile ayarlanmalı; sitelerin genel işi kabuk cron satırıyla.
- cPanel API token yok (Natro); ek alan adı eklemek için TXT doğrulaması.
- HTTP (şifresiz) isteklerde Imunify360 "One moment, please…" sınaması gösteriyor; HTTPS sorunsuz.
- Platform ve siteler kodpen.com ve diğer müşteri siteleriyle aynı sistem kullanıcısında; ortak DB kullanıcısı bir sitedeki SQL açığında diğer DB'lere de erişir.

## 8. Açık işler (Erdal)

- [ ] Ortak DB parolasını yenile (sohbette paylaşıldı). Yenileyince güncellenecekler: panel Ayarlar → havuz kullanıcısı; `pgplatform/config.php` (platform DB — aynı kullanıcı); her sitenin `pinegrap/data/config.php`'si (`DB_PASSWORD`); demonun `config.php`'si ve `pgplatform/demo/config.php.txt`. (Toplu güncelleme için küçük bir platform işi yazılabilir — plan madde 4.)
- [ ] `deneme1` / `deneme2` silinsin mi karar ver (DB'ler havuza döner). `deneme1`'deki `www.pinegrap.com` kaydını kaldır (pinegrap.com bu cPanel'de gerçek siteye bağlı).
- [ ] Çöp'ü temizle (t1, wiendi).
- [ ] Cihazdaki geçici dosyalar: `dev/_to_delete/hosting-2026-09-29/` (`zi*` ~160 MB, `tree_hosting.tgz`, boş zip'ler, `git-index.lock`).
- [ ] Ürün değişikliklerini (2026.4.6) gözden geçirip commit et.
- [ ] Cloudflare: Always Use HTTPS ve en düşük TLS 1.2 (önerildi, açılmadı).

## 9. Plan — sonraki adımlar

1. **Site yedekleri (öncelikli).** Sitelerde otomatik yedek kapalı; yedeği platform almalı: gece DB dökümü + `data/files` (değişenler), N gün sakla, hesap dışına kopya (ör. Cloudflare R2 / başka sunucu). Şu an yedek yok.
2. **Sürüm güncelleme.** 2026.4.6 yayımlanınca: Ayarlar'da sürüm etiketini değiştir, yamayı "Yok" yap; yeni kaynak site → altın kopya. Açık siteler için güncelleme işi yazılacak: dosyaları yeni sürümle değiştir (data/ hariç) → `php install/index.php automated_upgrade` → kontrol; sırayla, hata veren sitede dur.
3. **E-posta.** Barındırılan siteler için platform SMTP'si: yeni sitede SMTP ayarlarını otomatik yazmak (platform ayarından).
4. **Toplu bakım işleri.** Ortak DB parolası değişince tüm `config.php`'leri güncelleme; tüm sitelerde tek ayar değiştirme; disk kullanımı raporu.
5. **İzleme.** Başarısız kontrol / dolan havuz / çalışmayan cron için e-posta veya bildirim.
6. **VPS / VDS sürücüsü (uzun vade).** Site başına sistem kullanıcısı ve DB kullanıcısı, gerçek vhost, sertifika ve alan adı bağlamanın otomatik olması; `proc_open` ve `mail` kısıtlarının kalkması. Platformun geri kalanı değişmez; `PGP_Driver`'ın yeni bir uygulaması yazılır.
7. **Müşteri alan adı otomasyonu (ertelendi).** Seçenek: Cloudflare for SaaS (Custom Hostnames; 100 ad ücretsiz, sonra ad başı aylık 0,10 $) + küçük bir Worker proxy'si. Müşteri yalnız `CNAME www → <hedef>.pinegrap.site` ekler; sertifika HTTP ile kendiliğinden doğrulanır. Gerekenler: pinegrap.site'da Custom Hostnames'in açılması, token'a SSL / Workers yetkileri, ön denetleyicinin anahtarlı yönlendirme başlığını kabul etmesi. www'suz kök ad için müşteri tarafında CNAME düzleştirme ya da yönlendirme gerekir. VPS'e geçilirse gerek kalmaz.
8. **Başvuru / ödeme (pinegrap.com).** Onaylanan başvurunun panelde "Yeni site" işine dönüşmesi (API ya da yarı otomatik).

## 10. Dağıtım (kod değişince)

`_pgp_deploy.php` şablonundaki `__KEY__` yerine rastgele anahtar koyulup `pinegrap.site` köküne yüklenir (cPanel Dosya Yöneticisi); `pgplatform/` + `yonetim/` zip'i gövde olarak `POST https://pinegrap.site/_pgp_deploy.php?k=<anahtar>&sha=<sha256>` ile gönderilir. Betik kod dosyalarını değiştirir (öncekileri `data/backup-<zaman>/`'a alır), `data/`, `demo/` ve `config.php`'ye dokunmaz, kendini siler.
