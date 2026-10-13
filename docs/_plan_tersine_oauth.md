# Plan — Tersine OAuth: Pinegrap'ın dış hesaplara bağlanması

Durum: plan (2026-10-13). Satır ve fonksiyon adları `development` @ `15ae403`'e göre.
**Hiçbir madde Erdal seçmeden başlamaz**; seçilen her madde kendi thread'i ve PR'ıyla yapılır.

İlişkili: `docs/_plan_oauth.md` (sunucu tarafı; oradaki Faz F "Çalışma Alanı'nda MCP istemcisi"
bu planın 6. maddesi olur), proje notu `notes/mcp-arastirma.md`, `notes/workspace-arastirma.md`.

## 0. Kısa cevap

Bugün Pinegrap bir **OAuth sunucusu**: Claude, Cursor, VS Code gibi dış uygulamalar kişinin onayıyla
Pinegrap'ın API'sine ve MCP'sine bağlanıyor (`oauth.php`, `mcp.php`). Tersi, Pinegrap'ın
**OAuth istemcisi** olması: site, operatörün ya da panel kişisinin onayıyla Google, Microsoft,
Meta gibi hesaplara bağlanır ve onların adına iş yapar.

Pinegrap'ın kullanım amaçlarından çıkan 15 senaryo var (§4). Önerilen ilk beşi:

1. **E-postayı Microsoft 365 / Gmail hesabıyla göndermek** — Microsoft parolalı SMTP'yi
   Aralık 2026 sonunda varsayılan olarak kapatıyor; şirket e-postasıyla gönderen siteler için acil.
2. **Google Arama Konsolu verisi** — Hoş geldin ve SEO ekranlarında hangi aramayla kaç tıklama
   geldiği; site doğrulaması ve site haritası gönderimi tek tıkla.
3. **Yedeklerin Google Drive / OneDrive / Dropbox'a gitmesi** — bugün yalnız FTP ve S3 var.
4. **Sosyal girişin genişlemesi** — Google girişi zaten var; Microsoft ve Apple eklenir.
5. **Çalışma Alanı'nda MCP istemcisi** — tek genel istemciyle Notion, Linear, GitHub, Atlassian
   gibi yüzlerce servisin araçları `@takma ad` ile kullanılır; notlar (7.) da buradan beslenir.

Hepsi tek bir ortak altyapıyı kullanır (§3): sağlayıcı kaydı, şifreli jeton deposu ve
yenileme, tek dönüş adresi, Ayarlar › Bağlantılar altında "Bağlı hesaplar" ekranı.

---

## 1. Pinegrap ne için kullanılıyor, hangi dış hesaplara dokunuyor

Müşteri siteleri (yıllık lisans + barındırma, 10–15 site) Pinegrap'ı şu işler için kullanıyor.
Her işin dışarıdaki karşılığı, tersine OAuth'un o işe ne katacağını gösterir.

| Kullanım | Pinegrap'ta | Dışarıdaki karşılığı | OAuth'un katacağı |
|---|---|---|---|
| Kurumsal site, blog, çok dilli içerik | sayfalar, görsel tasarımcı, çeviri, SEO puanı | Google Arama, Bing | Arama Konsolu verisi, doğrulama, site haritası (§4.2) |
| E-ticaret | katalog, sepet, sipariş, kargo, ödeme | Google Merchant, Meta katalog, kargo/ödeme API'leri | ürün akışı, Merchant API, Meta (§4.9, §4.10), kargo jetonu (§4.14) |
| Üye ve müşteri hesapları | tek `user` tablosu, giriş/kayıt/üyelik ekranları | Google, Microsoft, Apple kimliği | sosyal giriş (§4.3) |
| Form, rehber (CRM), e-posta kampanyası | `forms`, `contacts`, kampanya kuyruğu | kurumun e-posta hesabı | OAuth ile gönderim (§4.1) |
| ERP (ön muhasebe) | fatura, cari, kasa, e-Belge | Paraşüt, Logo İşbaşı, banka | ortak şifreli jeton deposu (§4.14) |
| Çalışma Alanı (ekip) | mesaj, görev, not, takvim, asistan | Google/Outlook takvimi, Notion, OneNote, dış MCP sunucuları | takvim eşitleme, notlar, MCP istemcisi (§4.5–4.7) |
| Medya | dosya kütüphanesi | Drive, OneDrive, Dropbox | dosya seçici (§4.8) |
| Barındırma ve işletim | yedek, WAF, Cloudflare | bulut depolama | uzak yedek (§4.4) |

## 2. Koddaki dış bağlantılar (envanter, `15ae403`)

- **Google ile giriş zaten bir OAuth istemcisi**: `google_auth.php` (yetkilendirme kodu, `openid email
  profile`, `access_type=online`, jeton saklanmıyor). `state` + `nonce` oturumda, `id_token`'ın `iss`,
  `aud`, `nonce`, `email_verified` alanları denetleniyor; imza denetlenmiyor (jeton doğrudan TLS'li
  token ucundan geldiği için OIDC Core 3.1.3.7'ye uygun). Ayar `config.oauth_google_*`
  (`migrations/2026.4.4.php:1153`), bağ `user.user_google_id`, düğme `pg_google_signin_button()`
  (`includes/fn/auth.php:313–355`), ayar kartı Ayarlar › Güvenlik.
- **Paraşüt** (`includes/fn/parasut.php:381–515`): parola akışı + yenileme jetonu. Jeton önbelleği
  **düz metin** `data/parasut_token.json` (`PARASUT_TOKEN_CACHE`, `init.php:709`), yenileme jetonu dahil.
- **Kargo fiyatı** (`shipping.php`): UPS XML (`:1994`), FedEx SOAP (`:876`), USPS Web Tools (`:1676`).
  Üçü de karşı tarafta kalktı (USPS Web Tools 2026-01-25, FedEx SOAP 2026-06-01, UPS 2024'ten beri
  yalnız OAuth). Bugün büyük olasılıkla çalışmıyorlar; yerlerine gelenler OAuth `client_credentials` ister.
- **E-posta**: PHPMailer 6.12 (`includes/phpmailer/`), SMTP yalnız kullanıcı adı + parola
  (`includes/fn/mail.php:1451–1470`), ayarlar `data/config.php` sabitlerinde (`smtp_settings.php`).
  PHPMailer'ın `OAuthTokenProvider` arayüzü bağımlılıksız; `OAuth.php` League paketine bağlı, gömülü değil.
- **Yedek**: uzak kopya yalnız `ftp` ve `s3` (`pg_backup_remote_type()`, `includes/fn/backup.php:344`).
- **Asistan**: Pinegrap AI `ai.pinegrap.com` (lisans anahtarı), Claude rutini (tetikleme jetonu,
  `config.ws_claude_token`). Kişi hesabıyla bağlanan yapay zekâ yok.
- **Ziyaretçi verisi** sitenin kendi sayacından (`visitors`, `visitor_stats_hourly`); Google Analytics
  yalnız ölçüm kodu; IndexNow var (`includes/fn/seo.php:2734`). Arama Konsolu yok.
- **Ürün akışı**: katalog sayfalarında `?rss=true` ile eski `g:` RSS'i (`get_page.php:555–600`).
  Merchant API ve Meta katalog yok.
- **Takvim**: Çalışma Alanı iCal yayını (#267) ve resmî tatil için dış iCal adresi okuma
  (`ws_holiday_feeds`, `includes/workspace/workdays.php:342–436`). Google/Outlook API yok.
- **Medya**: adresten içe alma yalnız API'de (`api_files_create()`, `source_url`).
- **Ayarlar**: `pg_settings_tool_groups()` (`includes/settings/registry.php:369`) zaten bir
  **"Bağlantılar"** grubu taşıyor: SMTP, Uygulama Erişimi, Pazaryerleri. Kişinin kendi OAuth
  bağlantıları (içe doğru) `connected_apps.php`'de.
- **Giden HTTP**: `api_http_request()` (`includes/api/outbound/http.php:131`): yalnız https, iç ağ
  engeli, yönlendirme yok, TLS doğrulamalı; `max_body` varsayılanı **1000 bayt** (jeton cevabı için
  yükseltilmeli), form kodlaması yok, gövde string (büyük dosya akışı yok).
- **Gizli saklama**: ev deseni `"<şifreli>:<iv>"` içinde JSON (`encrypt_string_with_iv()`,
  `includes/fn/auth.php:4615`; `mp_credentials_encode()`, `erp_edoc_credentials_save()`).
- **İş zamanlayıcı**: `pg_cron_jobs()` (`includes/fn/cron.php:130`), `job.php` dakikada bir.

## 3. Ortak altyapı

Her senaryo bunu kullanır; ilk seçilen senaryonun PR'ıyla birlikte kurulur, ayrı bir "altyapı
PR'ı" beklemez.

### 3.1 İki kimlik modeli

Bir sağlayıcıya bağlanmak için o sağlayıcıda kayıtlı bir **uygulama** (client id + gizli) gerekir.

| | A. Sitenin kendi uygulaması | B. Pinegrap bağlantı aracısı |
|---|---|---|
| Ne | Operatör Google Cloud / Entra / Meta'da uygulama açar, kimliği Ayarlar'a girer (bugünkü Google girişi böyle) | Kodpen her sağlayıcıda tek uygulama açar ve doğrulatır; siteler `connect.pinegrap.com` üzerinden bağlanır |
| Dönüş adresi | sitenin kendi `connect.php`'si | aracının adresi; jeton siteye sunucudan sunucuya devredilir |
| Onay ekranında görünen | sitenin adı | "Pinegrap" |
| Google'ın hassas kapsamları | doğrulanmamış uygulama uyarısı ve 100 kişi sınırı; Google Workspace kurumunda "Internal" seçilirse uyarısız | Kodpen bir kez doğrulatır (alan adı, gizlilik politikası, video; birkaç iş günü) |
| Meta | her site ayrı App Review — pratik değil | Kodpen bir kez App Review + işletme doğrulaması |
| Kodpen'e bağımlılık | yok | aracı ayakta olmalı; yenileme aracıdan geçer |
| Uygun olduğu yer | müşteri girişi (marka görünür), Workspace kurumları | operatör bağlantıları (Arama Konsolu, yedek, e-posta), Meta |

**Varsayılan öneri:** altyapı iki modeli aynı sağlayıcı kaydıyla taşır. Önce A yapılır (bugün çalışır,
Kodpen'de hiçbir onay beklemez); Kodpen paralelde Google doğrulamasına başvurur, B hazır olunca
operatör bağlantılarında varsayılan B olur. B lisans özelliğidir (Pinegrap AI'nin lisans anahtarıyla
`ai.pinegrap.com`'a gitmesi gibi).

**Aracının akışı (B):** site, lisans anahtarıyla imzalı bir başlatma isteği yapar (site adresi,
sağlayıcı, kapsamlar, sitenin PKCE meydan okuması) → kişi sağlayıcıda onaylar → aracı kodu Kodpen'in
gizlisiyle jetona çevirir → tarayıcıyı tek kullanımlık devir koduyla sitenin `connect.php`'sine
gönderir → site jetonu aracıdan sunucudan sunucuya, PKCE doğrulayıcısıyla alır. Aracı jeton
**saklamaz**; yenilemede site yenileme jetonunu aracıya gönderir, aracı gizliyi ekleyip sağlayıcıya
iletir. İptal sağlayıcıya doğrudan yapılır.

### 3.2 Sağlayıcı kaydı

`includes/connect/registry.php` → `pg_connect_providers()` (deseni `mp_providers()` ve
`erp_edoc` kaydı). Her sağlayıcı bir dosya: `includes/connect/providers/<ad>.php`, fonksiyon adları
tablosu (arayüz değil, `connectors/base.php` gibi). Bir sağlayıcının taşıdıkları:

- uçlar: authorize, token, revoke, userinfo / OIDC keşif adresi, JWKS;
- istemci kimlik doğrulaması: `client_secret_post` / `basic`, Apple için ES256 imzalı JWT gizli;
- authorize ek parametreleri (Google `access_type=offline`, `prompt=consent`,
  `include_granted_scopes=true`; Dropbox `token_access_type=offline`);
- **amaçlar** (`purposes`): `signin`, `mail_send`, `search_console`, `backup`, `files_pick`,
  `calendar` …; her amaç kendi kapsam kümesini ister (artımlı onay);
- jeton tuhaflıkları: yenileme jetonu döner mi (Microsoft, Dropbox döndürür), hiç yoksa uzun ömürlü
  değişim (Meta 60 gün), Google yenileme jetonu 6 ay kullanılmazsa düşer, "Testing" durumdaki
  Google uygulamasında 7 günde düşer;
- desteklediği modeller (A, B).

Yeni sağlayıcı **sıfır şema değişikliği** ister (dış API skill'inin konnektör kuralı).

### 3.3 Tablolar (2026.4.10, API bloğu: 10.72)

- `connect_apps` — A modelinin uygulama kimlikleri: `provider` (birincil), `client_id`,
  `secret_enc` (`"<şifreli>:<iv>"`, Apple için anahtar kimliği + takım + `.p8` dahil JSON), `enabled`,
  zaman damgaları. Bugünkü `config.oauth_google_*` buraya `google` satırı olarak taşınır;
  `google_auth.php` buradan okur (davranış aynı, dönüş adresi aynı).
- `connect_accounts` — bir bağlantı = bir dış hesap: `id`, `provider`, `model` (`own`/`broker`),
  `user_id` (0 = site geneli, aksi hâlde kişinin kendi bağlantısı), `external_id` (sağlayıcının `sub`'ı),
  `external_label` (e-posta, sayfa adı), `scopes`, `tokens_enc` (erişim, yenileme, son kullanma,
  ek alanlar; şifreli JSON), `expires_at` (düz; iş zamanlayıcısı sorgular), `status`
  (`active`/`needs_reauth`/`disabled`), `last_error`, `last_used`, `refreshed_at`, `created_by`,
  zaman damgaları. Tekil anahtar `(provider, user_id, external_id)`.
- Bir özellik hangi bağlantıyı kullandığını **kendi ayarında** tutar (ör. yedek ayarı JSON'unda
  `connection_id`); "bu bağlantıyı kim kullanıyor" sorusu amaç kaydındaki bir geri çağırmayla
  cevaplanır (`pg_connect_purposes()`), ayrı bağ tablosu yok.
- Yetkilendirme sürerken `state`, PKCE doğrulayıcısı, `nonce`, dönüş yeri oturumda durur
  (`google_auth.php` deseni, 10 dk); tablo yok.
- Sosyal giriş için ayrıca `user_identities` (`user_id`, `provider`, `subject`, `email`, zaman):
  `user.user_google_id` korunur, migration satırları buraya da kopyalar (§4.3).

### 3.4 Uç noktalar ve ekranlar

- `pinegrap/connect.php` — **tek dönüş adresi** (`https://<site>/<yazılım klasörü>/connect.php`;
  sağlayıcıya bir kez kaydedilir). POST + CSRF ile başlatır (sağlayıcı, amaç, dönüş yeri), GET ile
  kodu karşılar; Apple'ın `form_post` dönüşü için POST'u da karşılar.
- Giriş ekranları ayrı kalır: `google_auth.php` olduğu gibi çalışır (kayıtlı dönüş adresleri
  bozulmasın); yeni sağlayıcılar `signin.php?provider=` ile gelir, ikisi aynı kaydı ve yardımcıları
  kullanır.
- **Ayarlar › Bağlantılar › "Bağlı hesaplar"** (`connections.php`, `registry.php:369`'daki
  `$connections` listesine bir satır): sağlayıcı kartları, A modelinin kimlik çekmecesi, site geneli
  bağlantılar (hesap, kapsamlar, kullanan özellikler, durum), Bağla / Yeniden bağla / Kes.
  Kapı: Uygulama Erişimi ekranıyla aynı.
- **Hesap menüsü › "Bağlı hesaplarım"** (`my_connections.php`): kişinin kendi bağlantıları (takvim,
  dosya seçici, kişisel posta). İçe doğru olan "Bağlı uygulamalar" (`connected_apps.php`) yanında
  durur; ad ayrımı bilerek: *uygulama* bize bağlanır, *hesap* bizim bağlandığımızdır.
- Bağlantı düşerse (`needs_reauth`) sahibine bildirim (`create_notification()`) ve Hoş geldin
  Operasyon Masası'nda bir satır.

### 3.5 Jeton yaşam döngüsü

- `pg_connect_token($connection_id)` geçerli erişim jetonunu döner; bitimine 120 sn kala yeniler.
  Yenileme `GET_LOCK('pg_connect_<id>')` altında yapılır: Microsoft ve Dropbox yenileme jetonunu
  her kullanımda değiştirir, iki eşzamanlı yenileme bağlantıyı düşürür. `invalid_grant` →
  `needs_reauth`.
- Ağ çağrısı istek akışında yapılmaz (konnektör kuralı); istisna, kişinin o an beklediği bağla /
  seç / dene düğmeleri. Eşitleme ve gönderim işleri kuyruktan yürür.
- `connect_job` (`pg_cron_jobs()`'a bir satır, günlük): Meta uzun ömürlü jetonunu süresi dolmadan
  değiştirir, 6 aya yaklaşan Google bağlantısını bir yenilemeyle canlı tutar, Apple JWT gizlisini
  (en çok 6 ay) yeniler, özelliklerin eşitleme işlerini tetikler.
- Kesme: sağlayıcıda iptal (Google `revoke`, Meta `DELETE /me/permissions`, Dropbox
  `token/revoke`; Microsoft'ta tekil iptal yok, satır silinir) + satır silinir. Kişi silinince kişisel
  bağlantıları da silinir. Bağlama ve kesme site günlüğüne yazılır.

### 3.6 Güvenlik

- PKCE `S256` her akışta (gizli istemcide de); `state` 128 bit, oturuma, kişiye, sağlayıcıya ve
  amaca bağlı, tek kullanımlık. OIDC'de `nonce`. Sağlayıcı `iss` dönüş parametresini veriyorsa
  (RFC 9207) beklenen yayıncıyla karşılaştırılır — tek dönüş adresinde karıştırma saldırısına karşı.
- Sağlayıcı uçları kodda sabit; yönetici adresi yalnız MCP sunucularında ve özel OIDC'de girilir,
  o istekler `api_http_request()`'in iç ağ engelinden geçer.
- Jetonlar şifreli; tarayıcıya, günlüğe, API/MCP cevabına hiç çıkmaz. Çözme yalnız kullanım anında.
- En dar kapsam; her amaç kendi kapsamını ister (Google'da artımlı onay).
- **Vekil karışıklığı:** site geneli bağlantıyı başkası yalnız okumak için kullanır; yazma ya kişinin
  kendi bağlantısıyla ya bağlantı sahibinin onayıyla (Workspace araştırmasının Faz F kararı).
- Dış servisten gelen içerik (posta, not, MCP araç sonucu) Pinegrap AI için güvenilmeyen veridir;
  bugünkü "model önerir, yazma onaylıdır" kuralı aynen geçerli.

### 3.7 Kod yerleşimi

`includes/connect/registry.php`, `client.php` (authorize adresi, kod değişimi, yenileme, iptal,
`client_credentials` ve parola akışı), `store.php` (tablo + şifreleme), `broker.php` (B modeli),
`providers/google.php`, `microsoft.php`, … Kök: `connect.php`, `signin.php`, `connections.php`,
`my_connections.php`, `connect_job.php`. Yeniden kullanılan: `pg_oauth_base64url()` ve kapsam
yardımcıları (`includes/oauth/rules.php`), `api_http_request()` (form kodlayıcı
`pg_connect_form()` ve büyük dosya için parça parça yükleyen bir kardeş eklenir).

---

## 4. Senaryolar

Efor: K küçük, O orta, B büyük. "Şart" sağlayıcının istediğidir (2026-10 itibarıyla; §7).

### 4.1 E-postayı Microsoft 365 / Gmail hesabıyla göndermek — öneri 1

- **Neden:** Microsoft, Exchange Online'da parolalı SMTP gönderimini (SMTP AUTH Basic) **Aralık 2026
  sonunda mevcut kiracılarda varsayılan olarak kapatıyor** (yönetici yeniden açabilir; kesin kaldırma
  tarihi 2027'nin ikinci yarısında duyurulacak). Google Workspace parolalı uygulama erişimini 2025'te
  kapattı; kişisel Gmail'de uygulama şifresi yalnız iki adımlı doğrulamayla çalışıyor. Şirket
  e-postasıyla sipariş, form ve kampanya postası gönderen her müşteri sitesi etkilenir.
- **Yapılış:** PHPMailer'ın `OAuthTokenProvider` arayüzünü uygulayan küçük bir sınıf
  (`getOauth64()` → `pg_connect_token()`), `AuthType = 'XOAUTH2'`. Microsoft: kapsam
  `https://outlook.office.com/SMTP.Send offline_access` (kiracıda posta kutusu için "Authenticated
  SMTP" açık olmalı) ya da Graph `Mail.Send`. Google: SMTP XOAUTH2 `https://mail.google.com/`
  kapsamı ister ve o **kısıtlı** kapsamdır (yıllık CASA güvenlik denetimi); bunun yerine Gmail API
  `gmail.send` (hassas, denetimsiz): PHPMailer iletiyi kurar (`preSend()`, `getSentMIMEMessage()`),
  site `users/me/messages/send`'e yollar.
- **Dokunduğu:** `includes/fn/mail.php:1451–1470`, `smtp_settings.php` ("Hesapla bağlan" seçeneği,
  sistem ve kampanya postası ayrı), posta kuyruğu.
- **Efor:** O. **Model:** A hemen; Google için B doğrulaması (`gmail.send` hassas).

### 4.2 Google Arama Konsolu (Search Console) — öneri 2

- **Neden:** Sitenin kendi sayacı ziyareti görür ama ziyaretçinin **hangi aramayla** geldiğini,
  sayfanın Google'da kaç kez göründüğünü ve dizinde olup olmadığını göremez. SEO puanı ekranının
  yanına gerçek arama verisi koymak "tek tıkla site" hedefine doğrudan oturur.
- **Yapılış:** Site Doğrulama API'si (`siteverification.verify_only`) ile meta etiketi siteye
  kendiliğinden konur ve doğrulanır; mülk Arama Konsolu'na eklenir; site haritası gönderilir
  (`webmasters`); günlük iş Search Analytics'ten sayfa + sorgu özetini çeker (`webmasters.readonly`,
  veri 2–3 gün gecikmeli, 16 ay geçmiş); URL Denetimi ile sayfa başına "dizinde mi" (mülk başına
  günlük kota). Yeni tablo `seo_search_daily` (gün, sayfa, sorgu, tıklama, gösterim, sıra) —
  ayrı şema adımı.
- **Dokunduğu:** `includes/fn/seo.php`, `seo.php` (sayfa listesi sütunları), Hoş geldin kartı
  (`includes/dashboard/widgets/`), `get_sitemap.xml.php`; IndexNow olduğu gibi kalır.
- **Şart:** `webmasters` kapsamları Google'da hassas sınıfta (başvuruda doğrulanacak) → B modeli
  için doğrulama; A modelinde uyarılı çalışır.
- **Efor:** O.

### 4.3 Sosyal giriş: Microsoft ve Apple (Google var) — öneri 4

- **Neden:** Mağaza müşterisinin hızlı kaydı (dönüşüm) ve panel ekibinin şirket hesabıyla girişi.
- **Yapılış:** `google_auth.php`'nin mantığı sağlayıcıdan bağımsız hâle gelir (`signin.php`), Google
  davranışı değişmez. Microsoft: Entra'da çok kiracılı + kişisel hesap uygulaması, `common` ucu;
  `iss` kiracı kimliği taşır, şablonla denetlenir; çok kiracılı uygulama için yayıncı doğrulaması.
  Apple: Apple Developer Program (yıllık 99 USD), Services ID, https dönüş adresi, gizli yerine
  `.p8` anahtarla imzalanan ES256 JWT (en çok 6 ay; `connect_job` yeniler), ad ve e-posta
  istenince dönüş `form_post` (POST), ad yalnız ilk girişte gelir, e-posta röle adresi olabilir.
  Bağ `user_identities`'e yazılır; panel kişisi yine `pg_mfa_gate()`'ten geçer.
- **Dokunduğu:** `google_auth.php`, `includes/fn/auth.php:313–355`, `get_login.php`, kayıt ve
  üyelik ekranları, `includes/fn/widgets.php` (giriş/kayıt/üyelik widget'ları), Ayarlar › Güvenlik,
  `account_security.php` (bağı kaldırma).
- **Efor:** O (Microsoft K, Apple O). **Model:** A (onay ekranında mağazanın adı görünsün).
- Facebook ile giriş: talep gelirse; Meta uygulaması gerekir (§4.9).

### 4.4 Yedekleri Google Drive / OneDrive / Dropbox'a göndermek — öneri 3

- **Neden:** Barındırma işinde müşterinin kendi bulutunda duran, sunucudan bağımsız yedek. Bugün
  yalnız FTP ve S3 var; KOBİ'lerin çoğunda ikisi de yok.
- **Yapılış:** `pg_backup_remote_type()`'a `gdrive`, `onedrive`, `dropbox`; ayar JSON'unda
  `connection_id` + klasör. Kapsamlar dar: Drive `drive.file` (**hassas değil**, doğrulama yok;
  yalnız uygulamanın yazdığı dosyalar), OneDrive `Files.ReadWrite.AppFolder`, Dropbox uygulama klasörü.
  Büyük arşiv için parça parça yükleme (Drive resumable, Graph upload session, Dropbox
  upload_session) → `api_http_request()`'in dosyadan akıtan kardeşi. Saklama sayısı uzak kopyada
  da uygulanır.
- **Dokunduğu:** `includes/fn/backup.php`, `backups.php`.
- **Efor:** O. **Model:** A yeterli (kapsamlar doğrulama istemiyor).

### 4.5 Çalışma Alanı takvim eşitleme (Google Takvim / Outlook)

- **Neden:** Görev bitiş tarihleri ve toplantılar kişinin kendi takviminde; dış toplantılar
  Workspace takviminde meşgul olarak görünsün.
- **Adım 0 (OAuth'suz, K):** kişisel "dış takvim adresi" — Google ve Outlook gizli iCal adresi verir;
  `ws_holiday_feeds` mekaniğiyle okunur, takvimde salt okunur katman. Okuma ihtiyacının çoğunu karşılar.
- **OAuth adımı:** Workspace görevlerini kişinin takvimine yazmak, oradaki değişikliği geri almak
  (Google `syncToken`, Graph delta sorgusu; anlık bildirim kanalları sonra). Kapsam Google
  `calendar.events` (hassas), Microsoft `Calendars.ReadWrite`; toplantıya Meet/Teams bağlantısı
  eklenebilir.
- **Dokunduğu:** `includes/workspace/calendar_feed.php`, `workdays.php`, `workspace_calendar.php`,
  `workspace_recurring_job.php`. Yalnız `ws_enabled()` iken.
- **Efor:** Adım 0 K, OAuth adımı O–B. Kişisel bağlantı.

### 4.6 Çalışma Alanı'nda MCP istemcisi (`_plan_oauth.md` Faz F) — öneri 5

- **Neden:** Tek bir genel istemci, sağlayıcıya özel kod yazmadan yüzlerce servisi (Notion, Linear,
  GitHub, Atlassian, Asana, Stripe, Sentry, HubSpot…) `@takma ad` olarak Workspace'e getirir.
  "Önemli entegrasyonları bünyesinde barındıran" hedefine en kısa yol.
- **Yapılış:** MCP yetkilendirme belgesinin istemci tarafı: korumalı kaynak belgesi (RFC 9728) →
  sunucu belgesi (RFC 8414) → istemci kimliği: Pinegrap kendi **istemci kimlik belgesini** yayınlar
  (CIMD; ör. `oauth.php/client`, sunucu tarafında zaten okuyoruz), olmazsa dinamik kayıt → PKCE,
  `resource` (RFC 8707), `iss` denetimi. Taşıma, araç döngüsü ve üç seviye (komut, ajan, uzak ajan)
  `notes/mcp-arastirma.md`'deki gibi; ince istemci `api_http_request()` üstüne (Composer yok, PHP 7.1).
  Sunucu satırı `ws_mcp_servers`, bağlantısı `connect_accounts`.
- **Kural:** site geneli bağlantı salt okuma araçları için; yazan araç kişinin kendi bağlantısı ya da
  sahibin onayıyla.
- **Efor:** B.

### 4.7 Notlar: Notion ve OneNote (Workspace açıksa)

- **Neden:** Ekiplerin notları çoğu zaman Notion ya da OneNote'ta; Workspace notlarıyla aralarında yol
  olsun: içe al, dışa gönder, not içinden dış sayfaya bağ.
- **Yapılış:** Notion — önce 4.6 üzerinden Notion'un barındırılan MCP sunucusu (sağlayıcıya özel
  kod yok); doğrudan bağlanmak gerekirse Notion genel entegrasyonu (OAuth, kişi onayda hangi
  sayfaları paylaşacağını seçer), bloklar ↔ Markdown çevirisi. OneNote — Graph `Notes.ReadWrite`,
  sayfa HTML'i ↔ not gövdesi. Yalnız `ws_enabled()` iken görünür; içe alınan not sıradan bir
  Workspace notudur (`ws_notes`, sürümleri `ws_note_versions`), dış sayfayla bağı gerekiyorsa
  Workspace bloğundan bir şema adımıyla tutulur.
- **Olmayanlar:** Google Keep API yalnız Google Workspace kurumsal hesaplarında yönetici yetkisiyle
  açık (kişisel hesapta yok); Evernote ve Apple Notlar için kullanılabilir bir API yok.
- **Dokunduğu:** `includes/workspace/notes.php`, `workspace_notes.php`, `assets/js/workspace.js`
  (+ `php tools/build_js.php`).
- **Efor:** O (Notion MCP üzerinden K).

### 4.8 Medyaya Drive / OneDrive / Dropbox'tan dosya almak

- **Neden:** Görseli önce bilgisayara indirip yeniden yüklemek yerine buluttan seçmek.
- **Yapılış:** Google Picker + `drive.file` (hassas değil), OneDrive dosya seçicisi, Dropbox Chooser
  (yalnız uygulama anahtarı, **OAuth gerekmez**). Seçilen dosya sunucuda jetonla indirilir, mevcut
  yükleme kurallarından geçer (`pg_upload_limits()`, yasak adlar).
- **Dokunduğu:** `add_file.php`, `editor_add_file.php`, `get_files_for_picker.php`, `includes/fn/files.php`.
- **Efor:** K–O. Kişisel bağlantı.

### 4.9 Meta: Facebook/Instagram katalog, Pixel + Conversions API, Instagram akışı, WhatsApp

- **Adım 0 (OAuth'suz, K, 4.10 ile ortak):** düzgün bir ürün akışı adresi (CSV/XML). Meta Commerce
  Manager ve Google Merchant Center onu zamanlanmış olarak çeker; bugünkü `?rss=true` eski biçim.
- **OAuth adımı:** Meta Business Extension (Meta'nın akışı: Pixel, katalog, reklam hesabı seçimi;
  katalog yükleme ve Pixel kurulumu bizde). Mağaza (Shops) kurulumu kapalı beta. `catalog_management`
  App Review + işletme doğrulaması ister. Instagram akışı sitede: Instagram Login ile
  `instagram_business_basic`; birden çok işletmeye hizmet eden uygulama App Review ile Advanced
  Access ister (Basic Display API 2024'te kapandı). WhatsApp Cloud API: Embedded Signup, Kodpen'in
  Tech Provider olması — Türkiye'de sipariş bildirimi için değerli ama süreç büyük.
- **Model:** yalnız B (Kodpen'in Meta uygulaması). **Karar Erdal'ın:** Kodpen adına Meta işletme
  doğrulaması ve App Review.
- **Efor:** B (çoğu süreç).

### 4.10 Google Merchant Center

- **Neden:** Ücretsiz Google Alışveriş listelemeleri; ret edilen ürünün sebebi ürün ekranında.
- **Yapılış:** Adım 0 akış adresi (4.9 ile ortak). OAuth adımı **Merchant API** (Content API for
  Shopping 2026-08-18'de kapandı, 2026-09-01'den beri aralıklı 410, 2027 başında tamamen kapanıyor):
  fiyat ve stok değişikliği anında, ürün durumları ekranda. Kapsam `content`.
- **Dokunduğu:** `get_page.php` (akış), ürün ekranları, `google_taxonomy_action.php`.
- **Efor:** O.

### 4.11 Google Analytics 4

- **Neden:** "Tek tık" kurulum: GA4 mülkünü ve veri akışını Admin API ile açıp ölçüm kimliğini
  siteye kendiliğinden yazmak; Data API ile Hoş geldin'de GA özeti.
- **Değer:** düşük–orta; sitenin kendi sayacı ve ölçüm kodu zaten var.
- **Efor:** O. Talep gelince.

### 4.12 Google İşletme Profili

- **Neden:** Yerel işletme sitesinde Google yorumları ve çalışma saatleri.
- **Şart:** API erişimi Google'ın başvuru formuyla açılıyor, `business.manage`. Salt okunur 5 yorum
  için Places API (yalnız API anahtarı) yeter.
- **Efor:** O. Talep gelince.

### 4.13 Sosyal paylaşım (blog, ürün, kampanya → Facebook Sayfası, Instagram, LinkedIn)

- **Şart:** Meta `pages_manage_posts` App Review; LinkedIn şirket sayfası Community Management API
  onayı; X API ücretli kademe → önerilmez.
- **Efor:** O + süreç. Talep gelince; 4.9'un Meta uygulamasına biner.

### 4.14 Kargo, ödeme ve ERP sürücüleri için ortak jeton deposu

- **Durum:** `shipping.php`'deki UPS / FedEx / USPS çağrıları karşı tarafta kalkmış API'ler; yerlerine
  gelenler OAuth `client_credentials` ister (kullanıcı yönlendirmesi yok, yalnız sunucu). Türk kargo
  firmaları ve iyzico OAuth kullanmıyor. Paraşüt'ün yenileme jetonu düz metin dosyada.
- **Bu planın payı:** `client.php` `client_credentials` ve parola akışını da taşır, jetonlar
  `connect_accounts`'ta şifreli durur; Paraşüt önbelleği oraya taşınır (K). Sürücülerin kendisi
  (kargo, e-Belge, pazaryeri, ödeme) **Erdal'ın yerel işi** (2026-10-13 kapsam kuralı); `shipping.php`'nin
  durumu `docs/_plan_kargo.md`'ye not düşülür.

### 4.15 Yapay zekâ hesapları (Claude, OpenAI) — önerilmez

- Anthropic, Free/Pro/Max aboneliklerinin OAuth jetonlarını yalnız Claude Code ve claude.ai için
  sınırlıyor (Şubat 2026); üçüncü taraf ürün API anahtarı kullanır. OpenAI'da kişi hesabıyla API
  OAuth'u yok. Pinegrap AI lisans anahtarıyla, Claude rutini tetikleme jetonuyla zaten çalışıyor.
- İstenirse tek seçenek: OpenRouter'ın PKCE akışı kişinin kendi anahtarını üretir; OpenAI uyumlu
  olduğu için `includes/workspace/ai.php`'ye "kendi modelini bağla" olarak oturur. Efor K.

---

## 5. Önerilen sıra

| Sıra | Ne | Neden bu sırada |
|---|---|---|
| 1 | Altyapı (§3, A modeli, Google + Microsoft sağlayıcıları; `google_auth.php` kayda taşınır) + **4.1 e-posta** | Microsoft'un Aralık 2026 kapatması; altyapıyı gerçek bir işle sınar |
| 2 | **4.2 Arama Konsolu** | En görünür değer, yalnız operatör bağlantısı |
| 3 | **4.4 yedek** + 4.8 medya seçici | Aynı sağlayıcılar, doğrulama istemeyen kapsamlar |
| 4 | **4.3 Microsoft / Apple girişi** | Altyapı oturduktan sonra K–O |
| 5 | **4.6 MCP istemcisi** → 4.7 notlar → 4.5 takvim | Notion MCP'den gelirse notlar ucuzlar; takvim Adım 0 istenirse öne çekilir |
| 6 | Akış adresi (Adım 0) → 4.10 Merchant → 4.9 Meta | Meta, Kodpen'in işletme doğrulamasını bekler |
| Paralel | Kodpen tarafı: `connect.pinegrap.com` aracısı, Google doğrulama başvurusu (`gmail.send`, `webmasters.readonly`, `calendar.events`) | Haftalar sürebilir; erken başlarsa 2. ve 5. sırada B hazır olur |
| Talep gelince | 4.11, 4.12, 4.13, 4.15 | |
| Erdal'ın yerel işi | 4.14 sürücüleri | |

Şema etiketleri API bloğundan: `connect_apps` + `connect_accounts` + `user_identities` **10.72**,
Arama Konsolu tablosu **10.73**, `ws_mcp_servers` Workspace bloğundan (10.82+).

## 6. Erdal'ın kararları

1. Hangi senaryolar yapılsın (§4 numaralarıyla).
2. Kimlik modeli: önce A, sonra B (varsayılan) ya da baştan B.
3. Kodpen adına Google doğrulama başvurusu ve `connect.pinegrap.com` aracısı — evet / hayır.
4. Kodpen adına Meta işletme doğrulaması ve App Review (4.9, 4.13) — evet / hayır.
5. `shipping.php`'nin UPS / FedEx / USPS kısmı: kargo planında yeniden mi yazılsın, kapatılsın mı.

## 7. Doğrulanamayanlar ve kaynaklar

- Sağlayıcı şartları 2026-10-13'te kamuya açık belgelerden ve ikincil kaynaklardan okundu; başvuru
  anında sağlayıcının kendi konsolunda yeniden doğrulanmalı. Özellikle: `webmasters` ve
  `webmasters.readonly` kapsamlarının hassas sınıfı (Google Cloud Console › Data Access),
  Microsoft'un tarihleri (Message Center MC786329, 2026-01-27 güncellemesi), FedEx'in müşteri bazlı
  geçiş tarihleri.
- Hiçbir sağlayıcıyla gerçek bağlantı denenmedi; bulut oturumu dev.pinegrap.com'a ulaşamaz. Her
  senaryonun canlı denemesi dev.pinegrap.com'da, Erdal'ın hesabıyla yapılır.
- Kaynaklar: Microsoft MC786329 (SMTP AUTH Basic), Google "Sensitive scope verification" ve
  Gmail kapsam sınıfları, Google Shopping Content API "Deprecation and sunset", Meta Instagram
  Platform "App Review" ve Commerce Platform "Onboarding (MBE)", USPS "Web Tools APIs Retired
  January 25, 2026", FedEx "Migrate to FedEx APIs", Claude Code "Legal and compliance".
