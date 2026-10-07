---
name: "pinegrap-waf"
description: "Pinegrap CMS'in dahili güvenlik duvarına (waf.php) dokunurken kullan: kural sırası, bot sınıflandırma, skor bandı, hız sınırı, IP listeleri, kalkan aynası, kayıt toplama ve güvenlik başlıkları."
---

# Pinegrap — WAF (`waf.php`)

## Mimari kısıtlar

- `waf.php` `functions.php`'den **bağımsızdır**; iki bootstrap'tan çağrılır
  (`router.php` ve `init.php`). Bağımlılık eklersen
  `get_file.php` / `robots.txt` / `sitemap.xml` yolu ölür. (Bu üç yolda `lang()`
  yoktur; yerel sarmalayıcı kullan: `waf_text()`, `get_file_text()`.)
- İki aşamayı ve ayrı mandalı koru: `waf_run('router')` = IP listeleri + saldırı
  aracı UA + global hız sınırı; `waf_run('init')` = ek olarak imza taraması +
  hassas hız sınırı.
- İmza taramasını **asla** router aşamasına taşıma: oturum yoktur, `<script>`
  içeren bölge kaydı saldırıdan ayırt edilemez.
- `waf_run()` gövdesi try/catch içinde kalır — **fail-open zorunlu**; şema,
  regex veya tablo hatası isteği geçirmeli.
- `waf.php` içinde HTTP çağrısı yazma (çekim yalnız
  `pg_waf_refresh_ai_ranges()`, `functions.php` tarafında).
- `pg_db_guard_check()` `functions.php`'den önce çağrılır; `includes/db_guard.php`
  bağımsız kalmalı (DB yok, `lang()` yok, session yok) ve yalnız geçici DB
  hatalarında geri çekilir (1040/1203/1226/2002/2003/2006/2013).
- Kesici tek bağlantı hatasında açılmaz: bağlantı `pg_db_guard_connect()` ile
  açılır (hızlı geçici hata iki kez daha denenir, 1 sn'yi aşan hata denenmez).
  Açıkken 3 sn'de bir tek istek yoklar (`pg_db_guard_claim_probe()`);
  mekanizma hatası **yoklamasız** kalmalı, seli içeri almamalı. Pencere
  mtime'dan (son hata), referans/süre dosya içeriğinden (kesinti başı) ölçülür.

## Bot sınıflandırma

- Sırayı değiştirme: **saldırı araçları → kötü botlar → iyi botlar → jenerik →
  operatör listesi → artık sinyal.** İzin listesi öne alınırsa UA'sına
  "googlebot" yazan herkes geçer.
- `waf_good_bots()` token'ları ile `waf_bad_bots()`/`waf_attack_tools()`
  token'larını çakıştırma.
- `waf_ai_bots()` token'larını her zaman ranges ile doğrula (`waf_bot_ranges`);
  doğrulama dalı iyi-bot lookup'ından önce gelir.
- AI botlarında `unknown` verdict'e iyi-bot ayrıcalığı verme → `unverified`
  sınıfına düşür. (rDNS botlarında `unknown` fail-open'dır, karıştırma.)
- Boş/başarısız ranges çekiminde eski satırı koru; ezersen tüm gerçek AI botları
  sahteci sayılır.
- Besleme okuyucularını (Feedly, Inoreader, Feedbin, NewsBlur, Miniflux,
  Netvibes, The Old Reader, FeedFetcher) iyi-bot listesinde tut.
- Yeni giden cURL çağrısında `CURLOPT_USERAGENT` set et
  (`pinegrap_user_agent()`); boş UA `bot-empty` sayılır ve yazılım kendini
  engeller. Bu token'ı iyi-bot listesine ekleme.

## Skor ve kural yazımı

- Eşikler: `low`=15 / `medium`=10 / `high`=6. Tek başına kesin kural **10**,
  belirsiz kural **4–6**; **7–9 ölü bölgeye kural koyma**.
- `'targets'` ile hedefi daralt (`ARGS`/`BODY`/`COOKIE`/`REQUEST_URI`/`REFERER`/
  `USER_AGENT`); `xss-jsuri` BODY'den muaf kalmalı (ürün yorumu meşrudur).
- `waf_is_excluded()` yalnız yola bakar, sorgu dizesini atar. Muafiyeti sorgu
  parametresine bağlarsan tüm WAF `&foo=...` ile atlatılır.
- `/robots.txt` ve `/.well-known/` bot politikasından muaf kalır
  (`waf_path_is_always_served()`); IP kara listesi ve hız sınırı yine uygulanır.
  Engellenirse ACME yenilemesi 90 gün sonra sessizce ölür.
- `waf_is_api_request()` isteğin **yoluna** bakar, çalışan dosyaya değil.

## Modlar ve kapatma

- `waf_deny()`'ın ilk satırındaki `waf_mode() !== 'block'` kontrolü kalır:
  izleme modunun sözü tek noktada tutulur.
- IP listesi dalı **her modda** engeller; izleme modu "yasakları serbest bırak"
  demek değildir.
- `waf_enabled = 0` iken `waf_run()` koşulsuz döner, hiçbir şey loglanmaz.
- `waf_ban_shield_refresh()` ve `waf_shield_drain()` mod kontrolünden **önce**
  çağrılır.
- `waf_schema_ready()`'yi `waf_config()`'ten ayrı tut.

## IP, özne ve kalkan aynası

- `waf_client_ip()` tek gerçek IP kaynağıdır; proxy başlığı yalnız peer bilinen
  vekilse kabul edilir. Mantığını kopyalama.
- Adres başına sayan her sayaçta özneyi `waf_ip_subject()` ile al (IPv4 = adres,
  IPv6 = `/64` CIDR); IPv4'te /24 toplama. `waf_log.ip_address` gerçek adresi
  yazmaya devam eder.
- CIDR önekini `ctype_digit()` ile doğrula; `(int)` cast'ı `1.2.3.4/` desenini
  "herkesi engelle"ye çevirir. `0.0.0.0/0` bilerek herkesi kapsar.
- `/64` yasağını izinli adresin üstüne koyma (`waf_subject_contains_allowed()`).
- Aynada (`data/temp/ban_shield.txt`) asimetriyi koru: `B` satırları 6 saatte
  bayatlar, `P` (vekil) ve `A` (izin) satırları bayatlamaz.
- DB öncesinde sayaç kurma; kalkan yalnız "bu adres zaten yasaklı mı?" sorusunu
  cevaplar.
- `waf_auto_ban()`'da `hit_count` **yasak sayısıdır**, olay sayısı değil; süre
  `taban × 4^(hit_count−1)`, tavan `waf_auto_ban_max_minutes`.
- Süresi dolan auto yasak satırını hemen silme; `waf_sweep()` retention kadar
  (0 ise 30 gün) tutar — merdivenin hafızası odur.
- `settings.php` yalnız `source='manual'` satırlarını yeniden yazsın;
  `TRUNCATE` otomatik yasakları ve izin listesini siler.
- Eş zamanlı tavan dosya tabanlıdır (`waf_inflight_*`, `flock(LOCK_EX)`);
  mekanizmanın kendi hatası **her zaman geçirmeli**.

## Kayıt

- Toplayarak yaz: anahtar `sha1(ip|rule_id|action|category|path)` + 5 dk kova
  (`WAF_LOG_WINDOW`), tek `INSERT ... ON DUPLICATE KEY UPDATE`. Query string
  anahtara girmez.
- Tekrarda örnek sütunları (`matched`, `request_url`, `user_agent`) yeniden
  yazma.
- Satır tavanında `MAX(id)` kullan; `waf_enforce_log_cap()` sweep'te 1/20,
  saklama temizliği 1/200 sıklıkta.
- Yeni bir sınır/kapı eklerken `waf_log_event()` çağrısını da ekle — sessizce
  reddeden koruma korumanın yarısıdır.
- `waf_reference()` istek başına static kalsın; engelleme sayfasındaki kod ile
  `waf_log.reference` aynı olmalı.
- Yeni deny yolunda `data/firewall.log` satırını da yaz; `ip=` daima ziyaretçi
  adresi olsun, /64 `subject=`'te.
- Raporda `SUM(hit_count)` kullan, `COUNT(*)` değil.

## Güvenlik başlıkları

- `waf_send_security_headers()` her iki bootstrap'ta, `waf_send_csp_header()`
  yalnız init'te — `waf_run()`'ın **dışında** çağrılır, kendi anahtarı
  `security_headers`.
- HSTS yalnız Güvenli Mod + `check_if_request_is_secure()` doğruyken.
- "HTTPS mi" sorusunun tek cevabı `check_if_request_is_secure()`'dur; ikinci bir
  tanım yazma (`pg_request_is_https()` Cloudflare Flexible'ı geçirir).
- Panele yeni dış kaynak eklersen `waf_csp_policy()`'ye de ekle;
  `csp_report.php` bağımsız kalsın ve `waf_log`'a yazmasın.
