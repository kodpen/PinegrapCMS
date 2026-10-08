# Plan 4 — E-posta kuyruğu, cron, dış API/webhook ve operasyon (ana ajan Fable 5.1, alt ajanlar Opus 5.5)

Durum: plan (2026-10-08). Kaynak keşif: `docs/_tespit_2026_10_08/refactor_altyapi_raporu.md` maddeler 17, 18, 19, 20, 15, 22. Satır numaraları `development` @ `0294c42`'ye göre; kaymışsa grep ile bul, yeniden keşif yapma.

## 0. Sabit bağlam

- Depo kökü `/workspace/pinegrapcms`, ürün kodu `pinegrap/`. Önce `CLAUDE.md`; skill'ler (yolları alt ajan görevine yaz): `.claude/skills/pinegrap-yeni-dosya/SKILL.md`, `pinegrap-sema-adimi`, `pinegrap-veritabani`, `pinegrap-dis-api`, `pinegrap-guvenlik`, `pinegrap-ceviri`, `pinegrap-ui-deseni`, `pinegrap-verilmis-kararlar`, `pinegrap-waf` (hız sınırı kovaları için).
- Dal `development`'tan, PR tabanı `development`, AI izi yok. Ortak dosyalar (`2026.4.8.php`, `init.php`, `tr.json`, `changelog.txt`, `docs/*`, `.github/workflows/php-checks.yml`): fetch+merge → yalnız ekleme → rebase. `upgrade_to_2026_4_8()` gövdesine yalnız kendi satırların.
- Şema etiketleri **8.30–8.39** (öneri; Erdal onaylar).
- Test koşucusu `php tools/test.php` (CI'da da koşar; A4 bu oturumda yapıldı). Sandbox `bash tools/setup_sandbox.sh` — **SMTP yok**: gönderim denemeleri PHPMailer hatasıyla düşer; kuyruk/yeniden deneme senaryoları tam da bununla test edilir. Başarılı gönderim için geçici yerel alıcı (`python3 -m smtpd`/`aiosmtpd`) kurulabilir; PR'a girmez.
- Bitiş: lint, check_lang, test.php, check_api_schema (API'ye dokunulduysa) temiz; migration iki kez; yorumlar İngilizce; PR'da "doğrulayamadıklarım".
- **Verilmiş kararlar:** `pg_curl_tls()` sertleştirmesi ödeme/kargo çağrılarının dışında kalır; `backups.php`/`software_backup` manager kapısı kalır; `data/backups/` dökümleri dokunulmaz; `pi.php`/`si.php` açık kalır.
- **Paralel planlar — dokunma:** `api.php` (Plan 1), `includes/fn/core.php` db yardımcıları ve tablo motorları (Plan 2 — `LOCK TABLES`'ı **sen** kaldırırsın, motoru Plan 2 çevirir; ikisi bağımsız), kargo yazma yolu ve `includes/api/resources/orders.php`, `includes/api/outbound/orders.php` (Plan 3), giriş/auth (Plan 5). `includes/fn/mail.php` senindir; Plan 3 `mail.php:752` tetik listesine tek satır ekler (çakışırsa merge basit).

## 1. Kapsam ve dosya sahipliği

| Faz | İş | Etiket | Dosyalar |
|---|---|---|---|
| 1 | Kampanya işi: kilit altında SMTP yok, yeniden deneme | 8.30 (`email_recipients.attempts`, `last_error`, `next_attempt_at`) | `email_campaign_job.php:100-460` |
| 2 | `mail_outbox` kuyruğu + satır içi iş | 8.31 | `includes/fn/mail.php` (`email()`), yeni `includes/fn/mail_queue.php`, yeni `mail_job.php` (kök), `includes/fn/cron.php` (iş kaydı), `job.php` (satır içi çağrı) |
| 3 | Çağrıcıları kuyruğa al | — | `forgot_password.php`, `submit_order.php` (fiş e-postası), `custom_form.php`, `includes/fn/mail.php:309-720` (yorum e-postaları) |
| 4 | `List-Unsubscribe` | — | `includes/fn/mail.php`, `email_preferences.php` |
| 5 | Cron: iş başına kilit, iki şerit | 8.32 (`cron_runs.locked_until`, `lane`) | `includes/fn/cron.php:600-910`, `job.php` |
| 6 | Merkezî hata/istisna işleyici | — | `init.php` (yalnız ekleme, küçük), yeni `includes/fn/errors.php`, `view_log.php:28-33` |
| 7 | Yedek: zip, saklama, uzak hedef | 8.33 (ayar kolonları) | `auto_backup.php`, `backups.php`, `includes/settings/*` (yedek kartı; `registry.php` deseni) |
| 8 | Dış API: outbox/queue görünürlüğü | — | `api_settings.php` (webhook kuyruğu ekranının yanına posta kuyruğu sekmesi) |

## 2. Adımlar

### Faz 1 — Kampanya işi (K–O) — en ucuz ve en acil
- **Yap:** `email_campaign_job.php:129` `LOCK TABLES … contacts WRITE, log WRITE` → yalnız sahiplenme için: kilit altında `UPDATE email_recipients SET status='sending', claimed_at=UNIX_TIMESTAMP() WHERE … AND complete='0' AND (next_attempt_at <= now) LIMIT N` ile N alıcı sahiplen, **kilidi bırak**, sonra döngüde `email()` çağır; dönüş `false` ise `attempts+1`, `last_error`, `next_attempt_at = now + pg_mail_backoff(attempts)` (takvim: 60, 300, 1800, 7200, 21600, 86400; `api_webhook_backoff()` ile aynı, ama **kendi fonksiyonun** — `includes/api/outbound/` modülüne bağımlılık yok); 6 denemeden sonra `complete='1'` + `failed='1'`. Başarılıysa bugünkü gibi `complete='1'`. Bayat `sending` (claimed_at > 15 dk) yeniden sahiplenilir.
  `email()`'in dönüşü **kontrol edilir** (`:418-430`).
- **Kanıt:** `tests/mail_queue_test.php`: `pg_mail_backoff(1..7)` takvimi, 7 → 0; sandbox: SMTP yokken kampanya koş → alıcılar `complete=0`, `attempts=1`, `next_attempt_at` ileri; işi tekrar koş → süre dolmadan atlanıyor; `attempts=6` sonrası `failed=1`; yerel SMTP açıkken tek koşuda gönderildi; kampanya koşarken başka istek `log_activity()` yazabiliyor (kilit yok — iki paralel curl ile).

### Faz 2 — `mail_outbox` kuyruğu (O)
- **Yap:** 8.31 `mail_outbox(id, created_at, send_after, attempts, last_error, status 'queued|sending|sent|failed', properties MEDIUMTEXT [JSON: to, to_name, bcc, from_*, reply_to, subject, format, body, type, attachments(yollar)], claimed_at, sent_at)` InnoDB. `includes/fn/mail_queue.php`: `pg_mail_enqueue($properties)`, `pg_mail_queue_run($limit)` (sahiplen → `email()` ile gönder → sonucu yaz; backoff Faz 1'deki), `pg_mail_queue_stale_reclaim()`. `email($properties)`'e `'queue' => true` seçeneği: cron **canlıysa** (`pg_cron_last_runs()` son 15 dk içinde `job` koşmuş) satır yazar ve `true` döner; cron ölüyse **bugünkü gibi** senkron gönderir (sitede cron kurulmamışsa e-posta gecikmez). `mail_job.php` kökte, `pg_cron_jobs()`'a `'inline' => true` (push_job deseni, `cron.php:245-251`), `job.php` satır içi çağırır. Ekler: kuyruğa alınan e-posta dosya yolu taşır; geçici ek dosyaları gönderimden sonra silinmez (çağıran karar verir) — `erp_mail_defer()` (`includes/erp/mail.php:629`) **bozulmaz**, dokunulmaz.
- **Kanıt:** migration iki kez; `tests/mail_queue_test.php`: properties JSON yaz/oku simetrik (`pg_mail_properties_encode/decode`), `queue=true` + cron ölü kararı saf fonksiyonla (`pg_mail_should_queue($last_job_run_at, $now)`); sandbox: cron canlı → satır `queued`; `job.php` → SMTP yok → `attempts=1`, `last_error` dolu, `next` ileri; yerel SMTP → `sent`.

### Faz 3 — Çağrıcılar (K, devredilebilir)
- **Yap:** `forgot_password.php`, `submit_order.php` fiş e-postası, `custom_form.php` bildirim/yanıt, yorum e-postaları (`mail.php:309-720`) → `'queue' => true`. `db_guard.php` başlık yorumundaki vakanın (SMTP boyunca DB bağlantısı) kapanışı budur. Diğer 30+ çağrı dokunulmaz (sonraki tur).
- **Kanıt:** sandbox'ta her yol tetiklenir → `mail_outbox` satırı; `grep -c "'queue' => true"` listesi PR'da.

### Faz 4 — `List-Unsubscribe` (K)
- **Yap:** `email()` içinde `type === 'campaign'` (mevcut tip değerini `email_campaign_job.php:418`'den oku) ise `$mail->addCustomHeader('List-Unsubscribe', '<mailto:…?subject=unsubscribe>, <https://…/email_preferences.php?e=…&s=…&unsubscribe=1>')` ve `List-Unsubscribe-Post: List-Unsubscribe=One-Click`; imza `pg_email_preferences_signature()` (`mail.php:245`, `ENCRYPTION_KEY` yoksa başlık eklenmez). `email_preferences.php`: `POST` + `List-Unsubscribe=One-Click` gövdesi + geçerli imza → abonelikten çıkar, 200 düz metin; CSRF **istenmez** (RFC 8058 — imza yetkidir), `waf.php`'de bu POST'un "sensitive" sayılıp engellenmediği denetlenir.
- **Kanıt:** `tests/mail_test.php`: başlık değeri üretici saf fonksiyon (`pg_mail_list_unsubscribe_headers($email)`), imzasız → boş dizi; sandbox: `curl -X POST -d 'List-Unsubscribe=One-Click'` doğru imza → çıkış, yanlış → 403; PHPMailer `SMTPDebug` çıktısında başlıklar.

### Faz 5 — Cron iş başına kilit (O)
- **Yap:** 8.32 `cron_runs.locked_until INT UNSIGNED 0`, `lane VARCHAR(8) 'light'`; `pg_cron_jobs()` girdilerine `'lane' => 'heavy'` (`auto_backup`, `seo_analyze_job`, `seo_score_job`) / `'light'`. `pg_cron_dispatch_next()` (`cron.php:647-760`): site geneli `config.job_dispatch_lock_until` yerine iş satırında atomik `UPDATE cron_runs SET locked_until=… WHERE job_name=? AND locked_until < now` (affected rows = 1 → sahip); her tıkta **şerit başına bir** iş. Seçim mantığı saf fonksiyona ayrılır: `pg_cron_pick($jobs, $runs, $now)` → `[light => name|null, heavy => name|null]`.
- **Kanıt:** `tests/cron_test.php`: `pg_cron_pick()` — vadesi gelen/kilitli/şerit senaryoları; sandbox: iki paralel `job.php` → farklı işler koşuyor, aynı iş iki kez koşmuyor (log), kilit süresi dolunca geri alınıyor.

### Faz 6 — Merkezî hata işleyici (O)
- **Yap:** `includes/fn/errors.php`: `pg_error_install()` → `set_exception_handler`, `register_shutdown_function` (fatal), `set_error_handler` yalnız **kayıt** (davranışı değiştirmez, `return false` ile PHP'nin akışı sürer); kayıt `data/temp/php_errors.log` (JSON satır: zaman, istek kimliği, URL, kullanıcı id, mesaj, dosya:satır, kısa yığın; 5 MB'ta döner, 3 dosya); `DEBUG` açıkken `E_DEPRECATED` de kaydedilir (`init.php:84` `error_reporting` **değişmez** — işleyici seviyeyi kendisi süzer). `init.php`'de `functions.php` yüklendikten hemen sonra tek satır `pg_error_install()`. `view_log.php:28-33` bu dosyayı ve `ini_get('error_log')` yolunu da listeler. `output_error()`'un `RuntimeException` yolu (`pg_seo_rendering`) ve `MYSQLI_REPORT_OFF` sözleşmesi bozulmaz.
- **Kanıt:** `tests/errors_test.php`: satır biçimlendirici, döndürme eşiği, yığın kısaltma; sandbox: `php -d auto_prepend_file= -r` yerine geçici kök betik (PR'a girmez) ile `undefined_function()` → log satırı; `view_log.php` gösteriyor; normal istek davranışı değişmedi (sayfa çıktısı aynı).

### Faz 7 — Yedek (O)
- **Yap:** `auto_backup.php`: klasör yerine `auto_backup_<Y-m-W>.zip` (`pclzip.lib.php` kökte var ya da `ZipArchive` — ikisini de dene, yoksa klasör kalır); 8.33 `config.backup_keep INT 4`, `backup_remote_type ('' | 'ftp' | 's3')`, `backup_remote_*` (şifreli, `encrypt_string_with_iv` "cipher:iv" deseni, `includes/api/outbound/connectors/base.php:206/214`); saklama: en yeni N dışındakiler silinir (yalnız `auto_backup_*` adlı olanlar; `data/backups/` döküm klasörlerine **asla**); uzak: FTP (`ftp_*`, ext yoksa kart uyarır) ve S3 uyumlu PUT (SigV4, `pg_curl` + `pg_curl_tls()`); başarısız uzak kopya `log_activity` + Sistem Durumu uyarısı. Ayar kartı `includes/settings/` registry deseni; sırlar geri render edilmez (`pinegrap-guvenlik`).
- **Kanıt:** `tests/backup_test.php`: saklama seçicisi saf fonksiyon (`pg_backup_prune_list($names, $keep)` — döküm klasör adları asla seçilmez), SigV4 imza bilinen test vektörü (AWS belgesindeki örnek); sandbox: iş koş → zip oluştu, 5. haftada en eski silindi; yerel MinIO/`python3 -m http.server` ile PUT isteği gövdesi/başlıkları doğrulanır (gerçek S3 Erdal'da).

### Faz 8 — Dış API tarafı görünürlük (K)
- **Yap:** `api_settings.php` webhook kuyruğu ekranı deseniyle "Posta kuyruğu" sekmesi: son 100 satır, durum, deneme, hata, "yeniden dene" (manager+, CSRF). `includes/api/**` **kaynaklarına** dokunulmaz (Plan 3 oradadır).
- **Kanıt:** sandbox ekran; tokensız POST reddi.

## 3. Varsayılan kararlar
- Kuyruk, cron canlı değilse senkron gönderime düşer (hiçbir sitede e-posta kaybolmaz).
- Backoff takvimi webhook'la aynı ama ayrı fonksiyon (modül bağımsızlığı).
- `error_reporting` seviyesi değişmez; işleyici yalnız kaydeder.
- Uzak yedekte önce FTP ve S3-uyumlu; SFTP yok (ssh2 ext güvenilmez).

## 4. Elle doğrulanacaklar (Erdal)
- Gerçek SMTP ile kuyruk/kampanya teslimi ve Gmail "tek tıkla abonelikten çık" düğmesinin görünmesi; canlı cron tıkı altında iki şerit; gerçek S3/FTP hedefi; büyük sitede zip süresi.

## 5. PR açıklaması şablonu
Ne değişti · Etiketler (8.30–8.3x) · Test dosyaları ve `php tools/test.php` özeti · Sandbox senaryoları (SMTP yok/var) · Denetim çıktıları · **Doğrulayamadıklarım** · Sorular.
