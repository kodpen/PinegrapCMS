# OAuth 2.1 yetkilendirme sunucusu — MCP hazırlığı (2026.4.10)

Durum: Faz A yazıldı ve sandbox'ta uçtan uca denendi (2026-10-13); gerçek bir MCP istemcisiyle deneme MCP sunucusunu (Faz C) bekliyor. Sahibi: API
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
| A | Yetkilendirme sunucusu: keşif belgeleri, dinamik istemci kaydı, yetkilendirme kodu + PKCE, jeton/yenileme, geri alma; jetonun `integration.php`'de geçmesi; panelde "Bağlı uygulamalar (OAuth)" türü | Bu PR |
| B | Panelde kayıtlı istemci listesi (kapatma, silme), kişinin kendi "bağlı uygulamalarım" ekranı | Sonra |
| C | MCP sunucusu (`mcp.php`): araçlar dış API kaynaklarının üstüne kurulur, jetonu bu sunucu verir | Sonra |
| D | İstemci kimlik belgesi (Client ID Metadata Document, MCP 2025-11): `client_id` bir https adresi olur, sunucu belgeyi çeker. Dışarıya istek attığı için SSRF koruması ister | Sonra |
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
  (`http://127.0.0.1`, `http://[::1]`, `http://localhost`) için port serbesttir
  (RFC 8252). `https` dışı adres yalnız loopback ya da ters alan adlı özel şema
  (`com.ornek.app:/cb`) olabilir.
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
