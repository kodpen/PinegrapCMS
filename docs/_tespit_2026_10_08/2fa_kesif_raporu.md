# İki adımlı oturum açma (2FA) — kimlik doğrulama haritası ve riskler (keşif raporu)

Yalnız kod okuması; hiçbir dosya değiştirilmedi, çalışan örnek kurulmadı. Yollar `pinegrap/` köküne göre.

**Özet:** Oturum tek yerde açılıyor: `includes/authentication.php:291` `pg_session_sign_in()` (L297 `$_SESSION['sessionuserid']`). Kapı oraya konamaz (remember-me, login_as_user ve kayıt yolları da buradan geçer; dosyanın sözleşmesi L16-24 yalnız `db/db_item/escape` izni verir). Kapı, parola doğrulandıktan sonra ve **`pg_device_limit_gate()` / `pg_login_set_device_cookie()` çağrılarından ÖNCE** konmalı; önce token basılırsa `software[auth]` çerezi sonraki istekte `initialize_user()` (auth.php:3518) üzerinden 2FA'sız oturum açar.

**Ön koşul (2FA'dan bağımsız kapatılmalı):** `change_password.php` oturum olmadan yalnız e-posta + mevcut parola ile oturum açtırıyor (L80 → L184). `set_password.php` sıfırlama bağlantısıyla otomatik giriş yapıyor (L124). İkisi de 2FA'yı deler.

## 1. Giriş noktaları
| # | Dosya | Kim | Oturum açılan satır | 2FA kapısı |
|---|---|---|---|---|
| 1 | `index.php` | Panel personeli; ön yüz login widget'ı da buraya post eder (`includes/fn/widgets_account.php:1283`) | L205 (parola L111, `throttle_pass` L183, device gate L198, token L199) | L183 sonrası, L197-198 öncesi |
| 2 | `membership_entrance.php` | Site üyesi | L157 (parola L98, gate L150) | L150 öncesi |
| 3 | `registration_entrance.php` | Site üyesi | L188 (parola L115, gate L181) | L181 öncesi |
| 4 | `google_auth.php` | Google ile giren herkes; doğrulanmış e-postayla **otomatik bağlanır** (~L205), personel hesabı da olabilir | L303 (gate L301) | Politika kararı; uygulanacaksa L301 öncesi |
| 5 | `device_limit.php` | 1–4'ün devamı (`device_limit_pending`, auth.php:1182) | L92 | 2FA device gate'ten önce konursa kapsanır |
| 6 | `change_password.php` | Oturumsuz ziyaretçi dahil | L184 | **Açık** — parola değişsin ama oturum açılmasın / 2FA ekranına gitsin |
| 7 | `set_password.php` | Sıfırlama bağlantısı | L124, token L135 | **TOTP'yi deler** — 2FA'lı hesapta otomatik giriş kapatılmalı |
| 8 | `login_as_user.php` | Manager+ başkası olarak girer | L73, bayrak L77 | Hedefin 2FA'sı atlanır; bu kipte 2FA yönetimi kapatılmalı |
| 9 | `includes/fn/auth.php:3518` (remember-me) ve `get_file.php:1337` (kopya) | Kayıtlı token | — | Token yalnız tam girişten sonra basıldığı sürece güvenli; 2FA açılınca eski tokenlar iptal |
| 10 | `custom_form.php:887`, `submit_order.php:4003`, auth.php:562/817 (`pg_member_register/activate`) | Yeni hesap | — | Kapı gerekmez |

**Oturum açmadan parola kabul eden yollar:**
| Yol | Ne açar | Not |
|---|---|---|
| `api.php:134` (`API_USERNAME`/`API_PASSWORD`) → auth.php ~3529-3561 | İstek başına parola, `API_AUTHENTICATED` | `barcode_*_inventory.php` de kullanıyor |
| `includes/api/resources/account.php:89` `api_auth_login` | Ekip cihazı token'ı (şema `includes/api/schema.php:942`) | `otp` parametresi ya da `mfa_required` reddi; `check_api_schema` koşulmalı |
| `shipworks.php:59` | ShipWorks, istek başına parola | — |
| `install/index.php:7194` | Yönetici parolasıyla kurulum ekranı kilidi | Yükseltme köprüsü bunun üstünde; dokunmak riskli |

Kapsam dışı: `includes/api/auth.php` (uygulama anahtarı), `workspace_guest.php` (`WS_GUEST_COOKIE`), `kiosk.php` (yalnız çıkış, L123), `developer_lock.php` (bkz. §2).

## 2. Hazır yapı taşları
- **Tek kullanımlık kod deseni (`forgot_password.php`):** `get_random_string()` (forms.php:225, `random_int`) 16 kr (L134-152); `sha256` ile `user.token` + `token_timestamp` (L159-165); `set_password.php` hash ile arar, 24 saat (L41-63). 2FA kodu için ayrı alan gerekir; `user.token` sıfırlamayla çakışır.
- **Bekleyen giriş deseni:** `pg_device_limit_gate()` (auth.php:1162-1191) `$_SESSION['software']['device_limit_pending']` (user_id, username, send_to, remember, time) → yönlendirme; `device_limit.php` 600 sn geçerlilik (L31-35), tamamlama sırası L90-105. `sessionuserid` set edilmediği için `validate_user` kendiliğinden reddeder; oturum kimliği `pg_session_sign_in` içinde yenilenir.
- **Ekran şablonu:** `pg_login_captcha_screen()` (auth.php:4321): `output_header_secure`, `no-store`, CSRF, `pg_login_captcha_passthrough()` (L4292).
- **Şifreleme:** `encrypt_string_with_iv()` (auth.php:4808, AES-256-CBC, `ENCRYPTION_KEY`), `decode_ssl_keys()` (L4793). Saklama biçimi `"cipher:iv"`: `mp_credentials_encode/decode` (`includes/api/outbound/connectors/base.php:206/214`), `erp_edoc_credentials` (`includes/erp/edoc/registry.php:264/288`). TOTP sırrı için uygun (MAC yok; geri okunması gerektiği için hash'lenmez).
- **E-posta:** `email(array(to, from_name, from_email_address, subject, body))` (`includes/fn/mail.php:1379`); hata → `false`; `pg_demo()` iken göndermeden `true` (L1395).
- **Hız sınırı:** `pg_login_throttle_guard / record_failure / pass` (auth.php:3959/3982/4068), `waf_rate` kovaları (`fail`/`lock` × IP/hesap); `pg_password_reset_guard()` (L4434), `waf_rate_exceeded()` (waf.php:1880). Her POST "sensitive" (waf.php:1734); oturum açmış kullanıcı muaf (waf.php:3147), bekleyen 2FA oturumu muaf değil (doğru).
- **Cihaz/token:** `auth_tokens` (selector:validator, `sha256(validator)`, `pinned`): `pg_auth_token_create` (L1631), `pg_auth_token_verify` (authentication.php:128), `pg_auth_token_revoke_user` (L1711, API cihazlarını da iptal eder). "Güvenilir cihaz" için aynı desen, ayrı tabloda (`auth_tokens` çıkışta ve parola değişiminde siliniyor).
- **`developer_lock.php` ikinci adım örneği olmaz:** PIN config'de genel sabit (`DEVELOPER_PIN`); doğrulanınca `md5(PIN)` kullanıcı satırına kalıcı yazılıyor (L172-176); hız sınırı yok; sayfa bazlı kapı (`initialize_developer_security`, auth.php:4753; init.php:1035). Gözlem, bulgu değil.
- **Kütüphaneler:** `includes/` ve `assets/lib/` altında TOTP, base32, QR **yok** (yalnız JsBarcode). PHP 7.1 uyumu sorun değil: `hash_hmac` 6, `random_bytes` 25, `hash_equals` 33, `random_int` 8 yerde kullanılıyor.
- **Ayarlar ekranı:** `settings_security.php` → `includes/settings/screen.php`, bölüm listesi `registry.php:171-187`; kartlar `security.php`, okuma `prep.php`, yazma `security.save.php` (sütun yoklamasıyla, L32-55, L84-95). Sabitler `init.php` ~L422-429.
- **Kullanıcı ekranları:** üye `get_my_account_profile.php:311/370` → `pg_account_security_section()` (auth.php:983), POST `account_security.php` (`pg_security_action`). Yönetici sıfırlaması `edit_user.php`: `unlock_sign_in` (L56), `pg_unlink_google` (L99) desenleri.

## 3. Şema önerisi (2026.4.8, `pinegrap-sema-adimi`)
**`user.secret_key*` kolonlarına DOKUNMA** (verilmiş karar).
- `user_mfa`: `user_id PK`, `method ENUM('totp','email')`, `totp_secret VARCHAR(255) ascii` ("cipher:iv"), `enabled_at`, `last_step` (tekrar kullanım engeli), `email_code_hash CHAR(64)`, `email_code_expires`, `email_code_attempts TINYINT`.
- `user_mfa_recovery`: `id`, `user_id` (index), `code_hash CHAR(64)`, `used_at`. 10 kod, sha256.
- Opsiyonel `user_mfa_trusted` (`auth_tokens` biçimi).
- `config`: `mfa_enabled`, `mfa_required_role` (örn. ≤2 personel zorunlu), opsiyonel `mfa_trusted_days`.
- `install_create_table`/`install_add_column`, `upgrade_2026_4_8_mfa()`; yeni tablolar `install/index.php:8215` `get_tables()`; `install_note()`, changelog `[ŞEMA]/[YENİ]`, `docs/degisiklikler.md`, `CLAUDE-tam.md`.
- **Soru:** 2026.4.8 alt adım etiket aralıklarında kimlik doğrulama için aralık yok.
- Geçiş kuralı (auth.php:1362-1380): tablo `information_schema` ile tam ad eşleşmesiyle yoklanır (`pg_auth_push_bound()` deseni); yoksa 2FA kapalı sayılır, giriş kırılmaz.

## 4. Tasarım seçenekleri
- **(a) E-posta kodu (O):** bağımlılık yok; gönderim limiti + deneme sayacı. Zayıf: sıfırlama da aynı e-postadan geçer; demo kipinde e-posta gitmez; SMTP gecikmesi DB bağlantısını tutar (db_guard vakası).
- **(b) TOTP (O–B):** base32 + RFC 6238 ~80 satır kendi kodu. QR: (1) QR yok, `otpauth://` + elle anahtar (K); (2) istemci JS QR kütüphanesi `assets/lib` (`.src`+`.min`); (3) PHP QR kütüphanesi. Dış QR servisi kullanılmaz (sır sızar). Kütüphanelerin 7.1 uyumu doğrulanamadı. Ek: yedek kodlar + kurtarma ekranı.
- **(c) İkisi (B):** ortak bekleyen giriş kapısı, yönteme göre doğrulayıcı. Önerilen sıra: önce TOTP + yedek kodlar, e-posta sonra.

## 5. Riskler ve kenar durumlar
- **Kapı sırası:** 2FA → device limit → token → oturum. Tamamlama: `connect_user_to_order()`, `remember_me`, `send_user_to_login_home()` (content.php:1769, `$_REQUEST['send_to']`); `return_to` korunmalı.
- **Hız sınırı:** `pg_login_throttle_pass()` doğru parolada hesabı sıfırlıyor (index.php:183); 2FA denemeleri ayrı kova (`mfa`+user_id). TOTP ±1 adım, `last_step` ile tekrar reddi.
- **`change_password.php` / `set_password.php`:** yukarıda; zorunlu.
- **Google:** otomatik bağlanıyor; "Google'ın 2FA'sına güven" mi yerel mi — karar. Google girişi her zaman remember=true.
- **Remember-me:** 2FA açılınca/kapanınca `pg_auth_token_revoke_user` (API cihazlarını da düşürür).
- **login_as_user:** `logged_in_as_different_user` kipinde 2FA açma/kapama engellenmeli.
- **API/entegrasyon:** `api.php` parola girişi (barkod), ShipWorks, `/auth/login` → 2FA'lı hesaba red / uygulama anahtarına yönlendirme / `otp` alanı — ürün kararı.
- **Kurulum kilidi:** `install/index.php:7194` yalnız parola; dokunmak riskli.
- **Kurtarma:** yedek kodlar; `edit_user.php`'de "2FA'yı sıfırla" (yalnız daha düşük rol için); son çare `data/config.php` sabiti ya da DB satır silme. `tools/` altında araç yok.
- **`ENCRYPTION_KEY` sıfırlama:** `includes/settings/commerce.save.php:68-140` yalnız `orders.card_number` yeniden şifreliyor; TOTP sırları çözülemez olur, herkes kilitlenir. `ENCRYPTION_KEY` tanımsızsa (L92) TOTP etkinleştirme reddedilmeli.
- **Kiosk/misafir:** etkisiz; kiosk `logout()` bekleyen kaydı temizlemeli (`includes/fn/forms.php:2200`).
- **Zaman kayması:** ±1 pencere; Sistem Durumu uyarısı opsiyonel.

## 6. Dokunulacak dosyalar (tahmin)
- Yeni: `includes/fn/mfa.php` (`functions.php` manifestine satır), kökte `mfa.php` ekranı, TOTP kurulum ekranı, opsiyonel QR JS kütüphanesi.
- Giriş yolları: `index.php`, `membership_entrance.php`, `registration_entrance.php`, `google_auth.php`, `change_password.php`, `set_password.php`, `device_limit.php`, `logout.php` / `forms.php` `logout()`.
- API: `includes/fn/auth.php` (`initialize_user` API dalı), `includes/api/resources/account.php`, `includes/api/schema.php`, `shipworks.php` (kapsam kararına bağlı).
- Ekranlar: `pg_account_security_section()`, `account_security.php`, `edit_user.php`, `view_sessions.php` (opsiyonel).
- Ayarlar: `includes/settings/registry.php`, `security.php`, `prep.php`, `security.save.php`, `init.php`.
- Şema: `includes/migrations/2026.4.8.php`, `install/index.php` (`get_tables`).
- Ortak: `tr.json`, `changelog.txt`, `docs/degisiklikler.md`, `docs/CLAUDE-tam.md`.

## Açık sorular
1. Hangi roller için zorunlu, hangileri isteğe bağlı?
2. Google girişi 2FA'dan muaf mı?
3. API, ShipWorks ve kurulum kilidi kapsamda mı?
4. TOTP'de QR olacak mı, olacaksa hangi kütüphane?
5. 2026.4.8 alt adım etiketi ne olacak?
