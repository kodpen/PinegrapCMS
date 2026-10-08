# Plan 5 — Güvenlik: CSRF kapısı düzeni ve iki adımlı oturum açma (ana ajan Fable 5.1, alt ajanlar Opus 5.5)

Durum: plan (2026-10-08). Kaynak keşif: `docs/_tespit_2026_10_08/2fa_kesif_raporu.md` (giriş noktaları tablosu, yapı taşları, riskler — dosya:satır orada; yeniden keşif yapma) ve `refactor_altyapi_raporu.md` maddeler 3, 16. Satır numaraları `development` @ `0294c42`'ye göre.

## 0. Sabit bağlam

- Depo kökü `/workspace/pinegrapcms`, ürün kodu `pinegrap/`. Önce `CLAUDE.md`; skill'ler (yolları alt ajan görevine yaz): `.claude/skills/pinegrap-guvenlik/SKILL.md`, `pinegrap-roller-yetki`, `pinegrap-sema-adimi`, `pinegrap-veritabani`, `pinegrap-ceviri`, `pinegrap-ui-deseni`, `pinegrap-yeni-dosya`, `pinegrap-waf`, `pinegrap-dis-api` (API giriş ucu), `pinegrap-verilmis-kararlar`.
- Dal `development`'tan, PR tabanı `development`, AI izi yok. Ortak dosyalar (`2026.4.8.php`, `init.php`, `tr.json`, `changelog.txt`, `docs/*`): fetch+merge → yalnız ekleme → rebase. `upgrade_to_2026_4_8()` gövdesine yalnız kendi satırların.
- Şema etiketleri **8.40–8.49** (öneri; Erdal onaylar).
- Test koşucusu `php tools/test.php`; sandbox `bash tools/setup_sandbox.sh` + Playwright (giriş akışı). SMTP yok: e-posta kodu seçeneği bu yüzden **ikinci tur**; TOTP tamamen yerel test edilir.
- Bitiş: lint, check_lang, test.php, check_api_schema (API giriş ucu değişirse) temiz; migration iki kez; `.src/.min` ikisi; yorumlar İngilizce; PR'da "doğrulayamadıklarım".
- **Verilmiş kararlar:** `user.secret_key*` kolonları **kullanılmaz, silinmez**; `includes/settings/prep.php` Google Client Secret geri render istisnası kalır; `pi.php`/`si.php` açık; `test_secure_mode.php` kapısız; `escape_url()` sıkılaştırılmaz; `software_backup` manager kapısı kalır.
- **Paralel planlar — dokunma:** `api.php` switch gövdeleri ve `get_widget_data` (Plan 1 taşıyor) — sen yalnız **kapı bölgesi `api.php:148-262`** ve 6 case'e tek satır `validate_token()` eklersin, **ilk PR olarak küçük tut ve erken merge et** (Plan 1 Faz 3 buna bağlı); `includes/fn/mail.php` (Plan 4; e-posta kodu turu için `email()` çağrısı yeter); `includes/migrations` motor adımları (Plan 2); kargo (Plan 3).

## 1. Kapsam ve dosya sahipliği

| Faz | İş | Etiket | Dosyalar |
|---|---|---|---|
| 1 | Oturumsuz giriş yollarını kapat (A7) | — | `change_password.php:80-184`, `set_password.php:124-135` |
| 2 | `api.php` tokensız yazan uçlar + istemci token denetimi (C3-K) | — | `api.php` (6 case: `server_config_repair:604`, `write_permissions_repair`, `ca_bundle_config_repair`, `ca_bundle_update`, `purge_cache`, `update_dashboard_appearance:8821`), `assets/js/backend.src.js`+`.min.js` (çağıran yerler token gönderiyor mu) |
| 3 | TOTP kütüphanesi (saf) | — | yeni `includes/fn/mfa.php` (+ `functions.php` manifest satırı) |
| 4 | Şema | 8.40 | `2026.4.8.php`, `install/index.php` `get_tables()` |
| 5 | Giriş kapısı + bekleyen oturum ekranı | — | `index.php:183-205`, `membership_entrance.php:98-157`, `registration_entrance.php:115-188`, `google_auth.php:205-303`, `device_limit.php:31-105`, yeni kök `mfa.php`, `includes/fn/forms.php` `logout()` (bekleyen kaydı temizle) |
| 6 | Kullanıcı ve yönetici ekranları, ayarlar | — | `includes/fn/auth.php:983` `pg_account_security_section()`, `account_security.php`, `edit_user.php`, `includes/settings/registry.php:171-187`, `security.php`, `prep.php`, `security.save.php`, `init.php` (sabitler, ekleme) |
| 7 | Parola kabul eden API yolları | — | `includes/fn/auth.php` `initialize_user` API dalı (~3529-3561), `includes/api/resources/account.php:89`, `includes/api/schema.php:942`, `shipworks.php:59` |
| 8 | `get_file.php` erişim kopyaları (C14) | — | `get_file.php`, `includes/authentication.php` |
| 9 (ikinci tur) | E-posta kodu yöntemi | 8.41 | `includes/fn/mfa.php`, `mfa.php` |

## 2. Adımlar

### Faz 1 — Oturumsuz giriş yolları (K) — 2FA'dan bağımsız, ilk PR
- **Yap:** `change_password.php`: oturum yoksa parola değişir ama **oturum açılmaz** (`pg_session_sign_in` çağrısı `pg_session_signed_in()` şartına bağlanır); yanıt "parolanız değişti, giriş yapın" + giriş sayfasına yönlendirme. `set_password.php:124`: otomatik giriş yalnız hesapta 2FA **yoksa** (Faz 5'te `pg_mfa_enabled($user_id)` gelince bağlanır; şimdilik davranış aynı, ama kod tek noktaya iner: `pg_post_password_signin($user_id)`).
- **Kanıt:** sandbox: oturumsuz `change_password` POST → parola değişti, `sessionuserid` yok; mevcut davranışı bekleyen akış (hesap sayfasından parola değiştirme) çalışıyor; `tests/auth_flow_test.php` yok (DB) → Playwright senaryosu PR'da.

### Faz 2 — CSRF kapısı (K) — ikinci küçük PR, erken merge
- **Yap:** önce envanter: 6 yazan uç için `grep -n "action: *'<ad>'\|\"<ad>\"" assets/js/backend.src.js *.php` → istemci `token` gönderiyor mu? Gönderiyorsa `validate_token()` eklemek davranışı değiştirmez; göndermiyorsa istemciye `token` eklenir (`.src`+`.min`). `api.php:148-262` negatif listesine **dokunma** (Plan 1 tabloya taşıyacak); yalnız 6 case'in başına `validate_token()`.
  `pg_session_cookie_allow_cross_site()` (`auth.php:1222-1257`, `SameSite=None`) + Content-Type'sız `php://input` (`api.php:68-70`) birleşimi: sandbox'ta başka origin'den `text/plain` POST ile tokensız bir uç tetiklenebiliyor mu **dene** (Playwright, ikinci origin `127.0.0.1:8001` basit HTML). Tetikleniyorsa PR'da **bulgu**; düzeltme kararı (örn. `api.php`'de `Content-Type: application/json` zorunluluğu ya da `Sec-Fetch-Site` denetimi) Erdal'a sorulur; bu PR'da yalnız token eklenir.
- **Kanıt:** sandbox: 6 uç tokensız → "Invalid token", tokenla çalışıyor; `node --check`; siteler arası deneme sonucu PR'da (evet/hayır + kanıt).

### Faz 3 — TOTP kütüphanesi (O, saf, testle)
- **Yap:** `includes/fn/mfa.php` (kapı sabiti; `functions.php:67` sonrasına `require_once` satırı): `pg_base32_encode/decode`, `pg_totp_secret()` (20 bayt `random_bytes`), `pg_totp_code($secret, $time_step, $digits = 6)` (RFC 6238, HMAC-SHA1, `hash_hmac('sha1', pack('N*', 0, $step), $secret, true)`), `pg_totp_verify($secret, $code, $now, $last_step, $window = 1)` → kabul edilen `step` ya da `false` (aynı/önceki step tekrar reddi), `pg_totp_uri($issuer, $account, $secret)` (`otpauth://totp/...`), `pg_mfa_recovery_codes(10)` (`XXXX-XXXX`, `random_int`), `pg_mfa_recovery_hash($code)` (sha256, normalize: büyük harf, tire yok), karşılaştırmalar `hash_equals`. PHP 7.1 uyumlu (typed property, `??=` yok).
- **Kanıt:** `tests/mfa_test.php`: RFC 6238 Ek B vektörleri (sır ASCII `12345678901234567890`; T=59 → 8 hane `94287082`, 6 hane `287082`; T=1111111109 → `07081804`; T=1234567890 → `89005924`; T=2000000000 → `69279037`); base32 gidiş-dönüş + RFC 4648 vektörleri (`"foobar"` → `MZXW6YTBOI======`); pencere ±1 kabul, ±2 red; aynı step ikinci kez red; yedek kod normalize/hash; URI kaçışı. Hepsi DB'siz.

### Faz 4 — Şema (8.40)
- **Yap:** `upgrade_2026_4_8_mfa()`: `user_mfa(user_id INT UNSIGNED PK, method VARCHAR(8) 'totp', totp_secret VARCHAR(255) CHARACTER SET ascii '' [cipher:iv, encrypt_string_with_iv], enabled_at INT UNSIGNED 0, last_step INT UNSIGNED 0, pending_secret VARCHAR(255) '' [kurulum sırasında], pending_at INT UNSIGNED 0)`, `user_mfa_recovery(id AUTO_INCREMENT PK, user_id INT UNSIGNED INDEX, code_hash CHAR(64), used_at INT UNSIGNED 0)`, InnoDB; `config.mfa_required_role TINYINT NOT NULL DEFAULT 99` (99 = zorunlu değil; 2 = rol ≤ 2 zorunlu); `get_tables()` listesi; `install_note()`. Geçiş köprüsü: giriş yolu tabloyu `information_schema` ile **tam ad** eşleşmesiyle yoklar (`pg_auth_push_bound()` deseni, `auth.php:1362-1380`); tablo yoksa 2FA kapalı sayılır.
- **Kanıt:** migration iki kez; `ENCRYPTION_KEY` tanımsızken etkinleştirme reddi (Faz 6 testinde).

### Faz 5 — Giriş kapısı (O) — sıra: parola → **2FA** → cihaz sınırı → token → oturum
- **Yap:** `pg_mfa_gate($user_id, $username, $send_to, $remember)`: hesapta 2FA açıksa (ya da `mfa_required_role` gereği açmak zorundaysa → kurulum ekranına) `$_SESSION['software']['mfa_pending'] = [user_id, username, send_to, remember, time, origin]` yazıp `mfa.php`'ye yönlendirir; yoksa `false` döner ve akış sürer. Dört giriş dosyasında `pg_login_throttle_pass()` **sonrasına, `pg_device_limit_gate()` öncesine** (satırlar §1 tablosunda). `mfa.php` (kökte, Pinegrap başlığı, `pg_login_captcha_screen()` deseni: `output_header_secure`, `no-store`, CSRF): 600 sn geçerlilik, kod/yedek kod formu, deneme kovası `waf_rate` `mfa:u:<user_id>` (5 deneme/10 dk; parola başarısı bu kovayı **sıfırlamaz**), başarıda `last_step` ya da yedek kodun `used_at` yazılır, sonra bugünkü tamamlama sırası (`device_limit.php:90-105` ile aynı: `pg_device_limit_gate` → `pg_login_set_device_cookie` → `pg_session_sign_in` → `connect_user_to_order` → `send_user_to_login_home`). `device_limit.php:31-35` bekleyen kaydın `mfa_pending`'ten geçtiğini şart koşar (2FA'lı hesapta `device_limit_pending` yalnız `mfa.php` yazar). `google_auth.php`: **aynı kapı** (varsayılan: muaf değil). `set_password.php`: `pg_mfa_enabled()` ise otomatik giriş yok. `login_as_user.php`: hedefin 2FA'sı atlanır (operatör kendi kapısından geçti), `account_security` 2FA eylemleri `logged_in_as_different_user` kipinde reddedilir. `logout()` (`forms.php:2200`) `mfa_pending`'i temizler. 2FA açıldığında/kapandığında `pg_auth_token_revoke_user($user_id)` (remember-me + API cihazları düşer; ekranda uyarı).
- **Kanıt (Playwright, sandbox):** 2FA'sız hesap akışı **değişmedi**; 2FA'lı hesap: parola sonrası `mfa.php`, `sessionuserid` ve `software[auth]` çerezi **yok** (test kodun hesapladığı TOTP ile — `pg_totp_code()` test yardımcısında), doğru kod → oturum + remember-me; yanlış kod 5 kez → kilit mesajı; aynı kod ikinci kez → red; yedek kod bir kez; 600 sn aşımı → giriş sayfası; `send_to`/`return_to` korunuyor; Google girişi (sandbox'ta Google yok → `google_auth.php` kapı satırı kod incelemesi + PR'da "doğrulanamadı"); `set_password` 2FA'lı hesapta oturum açmıyor; `login_as_user` 2FA sormuyor; cihaz sınırı aşımı 2FA'dan **sonra** geliyor.

### Faz 6 — Ekranlar ve ayarlar (O, devredilebilir: ekran parçaları)
- **Yap:** `pg_account_security_section()`'a "İki adımlı doğrulama" kartı: etkinleştir → `pending_secret` üret, `otpauth://` URI + elle girilecek base32 anahtar göster (**QR yok**; URI'yi kopyala düğmesi), kullanıcı ilk kodu girer → `totp_secret` şifrelenir, `pending_*` temizlenir, 10 yedek kod **bir kez** gösterilir (indir .txt); kapat → mevcut parola + kod; yedek kodları yenile. `ENCRYPTION_KEY` tanımsızsa kart "yapılandırma eksik" der, etkinleştirme reddedilir. `edit_user.php`: "2FA'yı sıfırla" (yalnız kendinden düşük rol için; `pg_unlink_google` deseni `:99`). Ayarlar › Güvenlik: "Zorunlu roller" (`mfa_required_role`: kapalı / yönetici / yönetici+tasarımcı+müdür), `registry.php` bölüm satırı, `prep.php`/`security.save.php` sütun yoklamalı yazma, `init.php` sabiti `MFA_REQUIRED_ROLE` (ekleme). `tr.json` anahtarları. Zorunlu rol ve 2FA'sı yoksa: girişte kurulum ekranı (oturum yine açılmaz — `mfa_pending` + `setup` modu).
- **Kanıt:** Playwright: etkinleştir→yedek kodlar→çıkış→giriş (TOTP)→yedek kodla giriş→kapat; yönetici sıfırlama; zorunlu rol ayarı açıkken yeni yönetici kurulum ekranına düşüyor; `php tools/check_lang.php`; `login_as_user` kipinde kart salt okunur.

### Faz 7 — Parola kabul eden API yolları (K–O)
- **Yap (varsayılan: red + yönlendirme):** `initialize_user` API dalı (`API_USERNAME/API_PASSWORD`), `shipworks.php:59`, `api_auth_login` (`account.php:89`) — hesapta 2FA açıksa `401` + `mfa_required` ("bu hesap uygulama anahtarıyla bağlanmalı"); `api_auth_login` ek olarak isteğe bağlı `otp` alanı kabul eder (`schema.php:942`, `check_api_schema`). Barkod betikleri (`barcode_*_inventory.php`) aynı daldan geçer; PR'da not. `install/index.php:7194` kurulum kilidi **dokunulmaz** (soru).
- **Kanıt:** sandbox curl: 2FA'lı hesapla `/auth/login` → 401 `mfa_required`; `otp` ile → cihaz jetonu; 2FA'sız hesap değişmedi; `php tools/check_api_schema.php`.

### Faz 8 — `get_file.php` erişim kopyaları (O, hassas)
- **Yap:** önce farkların gerekçesi: 15 kopya (`db` 0,04 … `get_request_uri` 1,00) için `git log -L` ile hangi tarafta ne zaman değiştiği → `docs/_get_file_kopyalar.md` (devredilebilir). Erişim kontrolü yapanlar (`check_view_access`, `check_private_access`, `check_edit_access`, `get_access_control_type`, `initialize_user`'ın erişim kısmı) `includes/authentication.php`'ye (zaten "shared with get_file.php" deseni; dosyanın yalnız `db/db_item/escape` sözleşmesine uy) tek kopya olarak alınır; `get_file.php` ve `functions.php` tarafı onu çağırır. Kasıtlı görünen fark varsa **taşıma, PR'da sor.**
- **Kanıt:** `tools/check_copies.php` (yeni denetim: iki dosyada aynı adlı fonksiyon kalmadı; CI'a adım), sandbox: korumalı dosya (giriş gerekli / rol gerekli / herkese açık) üç durumda `get_file.php` ve sayfa kapısı aynı cevabı veriyor (önce-sonra golden).

### Faz 9 (ikinci tur) — E-posta kodu
- `method='email'`, 6 haneli kod sha256 + 10 dk + 5 deneme, gönderim `email()` (Plan 4 kuyruğu merge olduysa `queue=false` — kod gecikmemeli), `pg_password_reset_guard()` deseniyle gönderim limiti. `pg_demo()` e-posta göndermez → demo sitede e-posta yöntemi kapalı.

## 3. Varsayılan kararlar (itiraz yoksa böyle)
- Yöntem: **TOTP + 10 yedek kod**; QR yok (otpauth URI + anahtar) (2026-10-08: ürün sahibi kararıyla QR eklendi, `docs/degisiklikler.md` → "QR kodu" bölümü); e-posta ikinci tur.
- Zorunluluk ayarı **kapalı başlar**; isteğe bağlı herkese açık (üyeler dahil).
- Google ile giriş **muaf değil**.
- Parola kabul eden API yolları 2FA'lı hesaba **red**; kurulum kilidi dokunulmaz.
- Kurtarma: yedek kodlar + yönetici sıfırlaması; son çare DB satırı silme (belgeye yazılır; config sabiti yok).
- `ENCRYPTION_KEY` yeniden üretme ekranı (`commerce.save.php:68-140`) TOTP sırlarını yeniden şifrelemez → o ekrana **uyarı metni** eklenir ("2FA sırları geçersiz olur"), yeniden şifreleme ayrı tur.

## 4. Elle doğrulanacaklar (Erdal)
- Gerçek Google girişi + 2FA; Google Authenticator/Aegis ile URI'nin elle girilmesi; canlı WAF altında `mfa.php` POST'larının "sensitive" sayılıp engellenmediği; mobil ekip uygulaması `otp` akışı.

## 5. PR açıklaması şablonu
Ne değişti · Etiketler (8.40–8.4x) · `php tools/test.php mfa` özeti (RFC vektörleri) · Playwright senaryo listesi (geçti/geçmedi) · Siteler arası POST denemesi sonucu · Denetim çıktıları · **Doğrulayamadıklarım** (Google, canlı WAF) · Sorular (kurulum kilidi, kasıtlı `get_file.php` farkları).
