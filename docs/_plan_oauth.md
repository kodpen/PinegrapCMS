# OAuth 2.1 yetkilendirme sunucusu — MCP hazırlığı (2026.4.10)

Durum: Faz A birleşti (PR #244, 2026-10-13). Faz C + D (MCP sunucusu ve
istemci kimlik belgesi) birleşti (PR #245; kök adres ve WAF düzeltmesi
PR #246). Gerçek Claude bağlayıcısıyla doğrulandı (2026-10-13,
dev.pinegrap.com, IIS + cloudflared): Claude kimlik belgesiyle tanındı, onay
ve jeton alındı, araç listesi geldi. Sahibi: API
(`includes/api/**`, `api_settings.php`) + yeni `oauth.php`, `includes/oauth/`.
Ayrıntı: `docs/degisiklikler.md` → "OAuth 2.1 yetkilendirme sunucusu".

## Neden

Pinegrap'a MCP sunucusu eklendiğinde Claude gibi dış uygulamalar Pinegrap'a
**kişi adına** bağlanacak. MCP'nin yetkilendirme belgesi bunun için OAuth 2.1
ister: istemci sunucuyu keşfeder, kendini kaydeder, kişiyi tarayıcıda giriş ve
onay ekranına gönderir, PKCE ile kod alır ve kodu jetona çevirir. Bugünkü iki
kimlik (uygulama anahtarı+gizli, cihaz girişi kullanıcı adı+parola) buna
uymaz: dış uygulama kişinin parolasını görmemeli, kişi neyi verdiğini ekranda
seçmeli ve istediği an geri alabilmeli.

## Kapsam

| Faz | İçerik | Durum |
|---|---|---|
| A | Yetkilendirme sunucusu: keşif belgeleri, dinamik istemci kaydı, yetkilendirme kodu + PKCE, jeton/yenileme, geri alma; jetonun `integration.php`'de geçmesi; panelde "Bağlı uygulamalar (OAuth)" türü | Birleşti (#244) |
| B | Panelde kayıtlı istemci listesi (kapatma, silme), kişinin kendi "bağlı uygulamalarım" ekranı | Tamamlandı: Uygulama Erişimi → "Bağlı uygulamalar" kartı, `connected_apps.php`. İzin verilen istemci listesi yok |
| C | MCP sunucusu (`mcp.php`): araçlar dış API kaynaklarının üstüne kurulur, jetonu bu sunucu verir | Birleşti (#245, #246); Claude ile doğrulandı |
| D | İstemci kimlik belgesi (Client ID Metadata Document): `client_id` bir https adresi olur, sunucu belgeyi çeker. Dışarıya istek attığı için SSRF koruması ister. MCP 2026-07-28 dinamik kaydı eskimiş saydı, istemciler önce bunu dener | Birleşti (#245); Claude ile doğrulandı |
| F | Çalışma Alanı'nda MCP istemcisi: dış sunucular `@takma ad` ile, dizin mekaniği (araştırma: `/mnt/project-files/notes/mcp-arastirma.md`). İstemci tarafı OAuth altyapısı `docs/_plan_tersine_oauth.md` §3, bu faz orada §4.6 | Sürüyor: sunucu dizini, istemci ve araç izinleri (şema 10.82, #297); kanalda `@takma ad` ile Pinegrap AI |
| E | "Pinegrap ile giriş" (OpenID Connect, `id_token`) — başka yazılımlarda oturum açmak için | Karar bekliyor |

## Kararlar (varsayılanlar, Erdal değiştirebilir)

- **Jetonu kim verir:** Pinegrap'ın kendisi (ayrı bir kimlik sunucusu yok).
- **Kim bağlanabilir:** panel hakkı olan kişi (cihaz girişiyle aynı kural:
  `meta:read` ve `account:*` dışında en az bir kapsam). Üye hesapları değil.
- **Operatörün anahtarı:** API ayarlarında "Bağlı uygulamalar (OAuth)" türünde
  bir uygulama. Durumu OAuth'u açar/kapatır, kapsamları verilebilecek en geniş
  yetkidir, dakika sınırı her bağlantıya ayrı uygulanır. Bu uygulama yokken
  keşif belgeleri 404 döner; dış istemci sunucuyu hiç görmez.
- **İstemci kaydı:** açık dinamik kayıt (RFC 7591), adres başına saatte 10.
  Kaydın kendisi yetki vermez; her bağlantı bir kişinin onayından geçer.
  30 gün hiç kullanılmamış dinamik istemci silinir.
- **Jeton:** cihaz oturumuyla aynı altyapı (`api_devices` satırı): erişim
  `pgat_…` 1 saat, yenileme `pgrt_…` 30 gün ve her kullanımda döner, eski
  yenileme 60 sn sonra tekrar gelirse bağlantı kapanır. Parola değişince,
  "her yerden çık"ta, hesap silinince bağlantılar da kapanır (mevcut kancalar).
- **Yetki:** kişinin onay ekranında işaretlediği kapsamlar ∩ uygulamanın
  tavanı ∩ kişinin bugün devredebildiği; her istekte yeniden hesaplanır.
  `webhooks:manage` OAuth ile verilmez.
- **Kaynak (RFC 8707):** jeton istenen kaynağa bağlanır. Bugün tek kaynak
  `integration.php`; MCP sunucusu geldiğinde listeye eklenir, bir kaynağın
  jetonu diğerinde geçmez.

## Akış

```
İstemci (Claude)                    Pinegrap
  GET /.well-known/oauth-protected-resource/<yol>/integration.php
                                      → authorization_servers: [site]
  GET /.well-known/oauth-authorization-server
                                      → authorize, token, register, revoke adresleri
  POST oauth.php/register             → client_id (genel istemci, gizli yok)
  Tarayıcı: oauth.php/authorize?response_type=code&client_id&redirect_uri
            &code_challenge&code_challenge_method=S256&state&scope&resource
                                      → giriş yoksa panel girişi (iki adımlı doğrulama dahil)
                                      → onay ekranı: uygulama adı, dönüş adresi, kapsamlar
                                      → redirect_uri?code&state&iss
  POST oauth.php/token grant_type=authorization_code&code&code_verifier&redirect_uri
                                      → access_token, refresh_token, expires_in, scope
  integration.php/... Authorization: Bearer pgat_…
  POST oauth.php/token grant_type=refresh_token
  POST oauth.php/revoke token=…
```

## Uç noktalar

| Adres | İş |
|---|---|
| `/.well-known/oauth-authorization-server` (+ `/openid-configuration`) | RFC 8414 sunucu belgesi; alt klasöre kurulumda `/<klasör>/.well-known/openid-configuration` |
| `/.well-known/oauth-protected-resource/<yol>` | RFC 9728 kaynak belgesi |
| `oauth.php/authorize` | GET onay ekranı, POST onay/ret (CSRF) |
| `oauth.php/token` | `authorization_code`, `refresh_token` |
| `oauth.php/register` | RFC 7591 dinamik kayıt |
| `oauth.php/revoke` | RFC 7009 |

`integration.php` jetonsuz/yanlış jetonlu isteğe `WWW-Authenticate: Bearer
resource_metadata="…"` başlığını da ekler (yalnız OAuth açıkken).

## Tablolar (2026.4.10, alt adım 10.70)

- `api_apps.kind` → `ENUM('server','device','oauth')`
- `oauth_clients`: `client_id` (`pgoc_…`), gizli özeti (gizli istemci için),
  ad, adres, dönüş adresleri (JSON), kimlik doğrulama yöntemi, kayıt yolu,
  oluşturan adres, zaman damgaları, durum.
- `oauth_codes`: kod özeti, istemci, kişi, dönüş adresi, kapsamlar, kaynak,
  PKCE meydan okuması, son kullanma (60 sn), kullanıldı/cihaz. Kod ikinci kez
  gelirse ondan çıkan bağlantı kapatılır.
- `api_devices` + `oauth_client_id`, `scopes`, `resource`.

## Güvenlik notları

- PKCE zorunlu, yalnız `S256`; `plain` ve örtük akış yok.
- Dönüş adresi kayıtlı adresle **birebir** eşleşir; yalnız loopback
  (`http://127.0.0.1`, `http://[::1]`, `http://localhost`) yol ve sorgusuyla
  karşılaştırılır, port ve loopback adı serbesttir (RFC 8252). `https` dışı
  adres yalnız loopback ya da özel şema olabilir (`com.ornek.app:/cb`,
  `cursor://…`); tehlikeli şemalar `pg_oauth_forbidden_redirect_schemes()`'te
  reddedilir. Ters alan adı şartı 2026.4.10'da kalktı: Cursor, Windsurf gibi
  istemciler tek kelimelik şema kullanıyor, kodu PKCE koruyor.
- İstemci ya da dönüş adresi geçersizse hiçbir yere yönlendirilmez, hata sayfası
  gösterilir (açık yönlendirme olmasın).
- Onay ekranı `X-Frame-Options`/CSP ile çerçeveye alınamaz; onay POST'u CSRF
  jetonu ister.
- Jeton, kod ve gizli düz saklanmaz: `api_secret_hash()` (ENCRYPTION_KEY ile HMAC).
- Gizli istemcinin yanlış gizlisi güvenlik duvarına ihlal yazar (`api_offence()`).

## Doğrulanamayanlar

Sandbox'ta (http, PHP yerleşik sunucusu) akışın tamamı denendi. Denenmeyen:
gerçek bir MCP istemcisiyle (Claude "özel bağlayıcı") bağlantı — bunun için
önce MCP sunucusu (Faz C) gerekir —, https'li canlı sunucu ve IIS'te
`.well-known` yolunun yönlendiriciye düşmesi.

## Faz C + D: MCP sunucusu ve istemci kimlik belgesi

**MCP sürümü.** Güncel belge 2026-07-28: oturum yok, `initialize` yok, her
istek tek POST, yetenekler `server/discover` ile sorulur, `resultType`
zorunlu. İstemcilerin bir kısmı hâlâ 2025-11-25 / 2025-06-18 konuşuyor;
sunucu ikisini de cevaplar: gövdede `initialize` gelirse eski yol (oturum
kimliği verilmez, eski sürüm buna izin veriyor), `MCP-Protocol-Version:
2026-07-28` gelirse yeni yol (`Mcp-Method` / `Mcp-Name` başlıkları gövdeyle
karşılaştırılır, uyuşmazsa 400 / -32020).

**Uç.** `pinegrap/mcp.php` — PATH_INFO yok. Site onu kök adreste verir
(`site.com/mcp.php`, yönlendirici dosyaya devreder); klasördeki adres de
çalışır, iki adres de kendi korumalı kaynak belgesini alır
(`pg_oauth_resource_addresses()`). Kaynak listesine `mcp` olarak girer (RFC 8707): `integration.php`
için verilmiş jeton burada, burası için verilmiş jeton orada geçmez. 401'de
`WWW-Authenticate: Bearer resource_metadata=".../.well-known/oauth-protected-resource/<yol>/mcp.php"`.
GET/DELETE 405; bildirimler 202; tarayıcı istemcileri için CORS.

**Kimlik.** OAuth jetonu (asıl yol), cihaz jetonu ya da uygulama
anahtarı+gizli (komut satırı istemcileri için) — `api_authenticate()`
olduğu gibi. Hız sınırı, IP listesi, kayıt günlüğü API'ninkiyle aynı.

**Araçlar = dış API uçları.** Her rota bir araç: ad rota kimliğinden
(`products.list` → `products_list`; Claude API'nin araç adı kuralı nokta
kabul etmiyor), açıklama rotanın özeti + açıklaması, `inputSchema`
parametrelerden (OpenAPI üretecinin tip çevirisi), `annotations`:
GET `readOnlyHint`, silme/iptal `destructiveHint`. Yalnız çağıranın
kapsamlarının karşıladığı araçlar listelenir. Dışarıda kalanlar: `auth.*`,
`push.*`, `docs.*` ve IIS için açılmış `*_post` takma adları. Yazma
uçlarında `dry_run` parametresi (API'nin `X-Dry-Run`'ı): model önce
deneyip sonra yazabilir.

**Çalıştırma süreç içinde.** Siteye kendi kendine HTTP isteği atılmaz
(paylaşımlı hostingte loopback ve sertifika sorunları, iki PHP işçisi).
`tools/call` rotayı `api_run()`'ın yaptığı sırayla çalıştırır: kapsam,
parametre doğrulama, işleyici. İşleyiciler `api_send()` ile çıkar; MCP
isteğinde `api_send()` cevabı JSON-RPC sonucuna çevirir (2xx →
`content` + `structuredContent`, diğerleri → `isError: true` ve API'nin hata
gövdesi). Bunun için `router.php`'deki istek okuyucularına (yöntem, yol,
girdi, başlık) birer ayarlayıcı, `response.php`'ye bir çıkış kancası eklenir.
API günlüğüne yol `/mcp/<rota yolu>` olarak yazılır.

**CIMD (Faz D).** `client_id` bir https adresiyse (yol içerir, parça/kullanıcı
adı/nokta bölüm yok) istemci kaydı aranmaz, belge o adresten çekilir:
5 KB sınırı, yönlendirme izlenmez, iç ağ adresleri reddedilir
(`api_http_request()`), belge `client_id`'yi aynen taşımalı, gizli
taşımamalı, `token_endpoint_auth_method` yalnız `none`. Belge
`oauth_clients`'a `registered_by = 'metadata'` olarak 1 gün önbelleğe alınır;
yenileme başarısızsa 7 güne kadar eski kopya kullanılır. Çekme yalnız
yetkilendirme ekranında ve **kişi giriş yaptıktan sonra** yapılır (girişsiz
biri siteyi rastgele adreslere istek attıramasın), ayrıca adres başına
saatte 30 ile sınırlı. Onay ekranı istemcinin adını değil kimlik adresinin
alan adını öne koyar; dönüş adresi başka bir alan adındaysa uyarır. Sunucu
belgesine `client_id_metadata_document_supported: true` eklenir.

**Şema (10.71).** `oauth_clients.client_id` `VARCHAR(255) ascii_bin`
(adres sığsın; ascii, eski MySQL'in 767 baytlık dizin sınırı için),
`registered_by`'a `metadata`, `metadata_expires`.

**Panel.** OAuth uygulamasının çekmecesine "MCP sunucu adresi" (Claude'da
"özel bağlayıcı" eklerken yapıştırılan adres).

**Doğrulanamayacaklar.** Gerçek Claude bağlayıcısıyla deneme https'li,
internetten erişilen bir site ister (dev.pinegrap.com); sandbox'ta MCP
istemcisi taklit edilir. CIMD'nin gerçek bir belgeyi çekmesi sandbox'ta
DNS olmadığı için yalnız ret yolundan denenebilir.
