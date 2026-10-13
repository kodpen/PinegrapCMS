---
name: "pinegrap-dis-api"
description: "Pinegrap CMS'in dış API'si (integration.php, includes/api/), webhook olayları, kapsamlar, giden konnektörler ve pazaryeri entegrasyonlarıyla çalışırken kullan."
---

# Pinegrap — dış API ve entegrasyon

Kaldırılmış olan: `apps.php`, `apps_settings.php`, `custom_apps` tablosu
(2026.4.4). Geri ekleme. Güncel yapı: `integration.php` + `includes/api/`
(auth, scopes, ratelimit, router, `resources/`, `outbound/`), panel
`api_settings.php` / `api_docs.php`.

## Uç yazarken

- Sunum tek yerde: `includes/api/resources/*.php` içindeki `api_*_present()`.
- Liste ucunda satır başına sorgu yazma; `WHERE id IN (...)` toplu yardımcı
  kullan (liste 250 satıra kadar döner).
- Gövdede tanınmayan alan 422 ile reddedilir (`api_request_body_keys()`);
  query string denetlenmez.
- Yeni hata kodu uydurma: yalnız `validation_failed`, `not_found`, `forbidden`,
  `invalid_cursor`, `service_unavailable`.
- Her uç için `'returns'` bildir (`'ErpInvoice'` veya
  `array('list' => 'ErpInvoice')`); nesne alanlarını üreten fonksiyonun yanında
  alan başına bir satır tanımla, nullable için `'string?'`.
- `'type' => 'list'` parametresine öğe türünü `'of'` ile yaz (`'integer'`,
  `'string'` ya da `array('hash' => 'string', ...)`); yazılmazsa OpenAPI onu
  nesne listesi sanar. Gövde parametresinin `description`'ı belgeye girer.
- Panelin OpenAPI'si (`api_docs.php?openapi=1`) resource dosyalarını
  `api_openapi_load_declarations()` ile yükler: yeni resource dosyasının kapısı
  `PG_API_ENTRY` **ya da** `PG_API_PANEL` kabul etmeli ve üst düzeyde yalnız
  fonksiyon tanımı taşımalı.
- Para `api_money()` (kuruş), gün `Y-m-d`, an ISO.
- `POST /products` ekranın yazdığı yerden yazar (`pg_pb_create_product()`) —
  ikinci bir INSERT yazma; alınmış ad 409 döner.
- Ürün güncellemede `group_ids`, `attributes`, `images` bağların **yerine
  geçer** (boş liste temizler). Barkod yalnız eklenir, değiştirilmez.
  Kaydın SEO'sunu etkileyen her yazım cevaptan önce
  `pg_seo_recalculate()` çağırır (`api_products_rescore()`, sayfa, grup);
  yalnız `seo_analysis_current = 0` yazıp bırakma.
- **IIS'te PATCH ve DELETE gönderilmez** (WebDAV 405; PATCH ayrıca sonraki
  POST'u 404'e düşürür): `'method' => 'POST', 'also_accepts' => array('PATCH')`.
  Silme için `POST /x/{id}/delete` takma adı ekle. İstisna: `POST /webhooks/{id}`
  ilk sürümden beri **silmedir**; webhook güncellemesi `PATCH /webhooks/{id}` ve
  `POST /webhooks/{id}/update`'tedir. Yönlendirici ilk eşleşeni aldığı için
  yeni rotanın yolunu eski bir `also_accepts` ile çakıştırma.
- Ürün ve ürün grubu silme uçları varsayılan olarak katalog Geri Dönüşüm
  Kutusu'na atar (`permanent` ile kalıcı); listeler kutudaki kaydı göstermez,
  tekil okuma `recycled` alanıyla gösterir.
- Yazma provası istiyorsan uca `'dry_run' => true` koy ve **ilk yazmanın hemen
  üstüne** `api_dry_run_stop(...)` çağır; erken koyulan duruş yapılmamış
  doğrulamayı vaat eder. Toplu uçta `api_dry_run_requested()` ile satır satır
  çalış, yazmayı atla, sonucu sonda ver.
- Kısmi güncellemede önce mevcut satırdan tam `$data` kur, sonra gelen alanları
  üstüne koy.
- Gövdesiz cevap veren uç (`api_send(204, ...)`) rotada `'status' => 204`
  bildirir; OpenAPI 200 yerine 204 yazar. `tools/check_api_schema.php`
  `$without_body` listesine de girer.
- İç içe nesne satır içinde nullable yazılamaz: ayrı nesne bildir, alanı
  `'Ad?'` yaz (`'Device?'`, `'ErpQuoteSignature?'`).
- Sonradan kişinin kendi adıyla iş yapan kayıt (zamanlanmış mesaj gibi) yalnız
  cihaz oturumundan açılır (`ws_api_person_only()`); uygulama anahtarı okur ve
  iptal eder. Uygulamanın yazdığı her şey uygulama adıyla görünür.
- Panelde yeni bir özellik kayıt yapısını değiştirdiyse (yeni durum, yeni bağ,
  yeni kanıt) var olan API nesnesine de yansıt: olay kataloğunda olup nesnede
  okunamayan alan bırakma (4.8'de teklif imzası ve tartışmalar böyle kaldı).

## Kapsam ve güvenlik

- Yeni kapsam `includes/api/scopes.php`'ye yazılır; etkin yetki
  `api_effective_scopes($granted, $owner)` ile **her istekte** hesaplanır,
  saklanmaz. `write`→`read` genişletmesi yalnız `api_scopes_expand()` içindedir.
- Kimlik doğrulaması başarısız olan istek `api_offence()` ile güvenlik duvarına
  ihlal yazar.
- `POST /auth/login` sitenin giriş kapısını (`pg_mfa_gate()`) aynalar: iki adımlı
  doğrulaması açık hesap `otp` ister (401 `mfa_required`), rolü gereği kurmak
  zorunda olup kurmamış hesap 403 `mfa_setup_required` alır. Parola denetimi
  bunlardan önce gelir.
- `api.php` ve `integration.php` `waf_handle_rate_sensitive()` dışındadır, kendi
  kovaları vardır (`waf_rate_limit_api` adres başına, `ratelimit.php` uygulama
  başına, varsayılan 120/dk).
- Anahtar/parola üretme veya kimlik kutusu doldurma — o kullanıcının işi.

## Cihaz oturumu ve genel yetenekler (2026.4.5)

- İki kimlik vardır: uygulama (Basic, anahtar+gizli) ve **cihaz** (Bearer,
  `includes/api/devices.php`). Cihazda `api_current_app()['owner']` giriş yapan
  kişidir; sahibi okuyan her kod (Çalışma Alanı, ERP kasa, audit) kendiliğinden
  o kişiyle çalışır. Uç yazarken "sahip" deme, `$app['owner']` oku.
- `api_current_device()` null değilse istek bir cihazdan gelir. Cihazda
  `webhooks:manage` yoktur, `account:*` hep vardır.
- `'public' => true` yalnız jeton veren uçlar içindir (login/refresh/logout);
  başka uca koyma — kimlik, hız kovası ve idempotency atlanır.
- Kişinin kendine ait uçları (bildirim, cihaz, push) `account:read/write`
  ister; sunucu uygulamasına operatör ayrıca verir.
- ETag/304 ve `?fields=` `api_send()` içinde her 200 GET'e uygulanır; uçta ek
  iş gerekmez. Cevabı `api_ok()`/`api_ok_list()` dışında basan uç bunlardan
  yararlanamaz.
- Liste ucuna `updated_since` ancak damga **her yazarda** güncelleniyorsa
  eklenir (`contacts.timestamp` güncellenmiyor → müşterilerde yok).
- Sahibin `manage_*` evet/hayır bayrakları `api_load_owner()`'da boolean'a
  çevrilir; ham 'no' stringi `== true` ile doğru çıkar.

## OAuth 2.1 (2026.4.10, `oauth.php`, `includes/oauth/`)

- Üçüncü kimlik: kişinin onay ekranında bağladığı dış uygulama (MCP
  istemcisi). Jetonu **`api_devices` satırıdır** (`oauth_client_id > 0`);
  ayrı jeton tablosu açma — iptal kancaları (parola, her yerden çıkış,
  hesap silme) o tabloya bağlı.
- Operatörün anahtarı `api_apps.kind = 'oauth'`; `pg_oauth_app()` etkin en
  eskisini döner, yoksa uçlar ve `.well-known` 404.
- Bağlantının kapsamı onaylanan ∩ tavan ∩ kişinin hakları, her istekte
  (`pg_oauth_effective_scopes()`); `webhooks:manage` OAuth ile verilmez,
  `account:*` kendiliğinden eklenmez.
- Yeni bir kaynak (MCP ucu) `pg_oauth_resources()`'a girer ve kendi giriş
  dosyasında `PG_OAUTH_RESOURCE` tanımlar; başka kaynağın jetonu reddedilir.
- OAuth uçları API'nin hata biçimini kullanmaz: `pg_oauth_fail()`,
  `error_description` İngilizce düz metin (RFC: yalnız ASCII), `lang()` yok.
- Yenilemeyi kendi biçiminde cevaplayan kod `api_device_rotate_pair()`
  kullanır; `api_device_rotate()` API biçiminde `api_fail` eder.
- `client_id` bir https adresiyse istemci kimlik belgesidir (CIMD):
  `pg_oauth_client_resolve()` yalnız yetkilendirme ekranında ve kişi giriş
  yaptıktan sonra çeker (`api_http_request()`, 5 KB, yönlendirme yok);
  token ucu belgeyi çekmez, `pg_oauth_client()` ile kayıtlı kopyayı okur.

## MCP sunucusu (2026.4.10, `mcp.php`, `includes/mcp/`)

- Araçlar **rotaların kendisidir** (`pg_mcp_tool_from_route()`): yeni bir uç
  yazınca araç kendiliğinden gelir. `summary`/`description`/parametre
  açıklamaları modelin okuduğu metindir — İngilizce, kısa ve doğru yaz.
- Dışarıda kalanlar `pg_mcp_route_is_offered()`'da: `auth.*`, `push.*`,
  `docs.*`, `*_post` takma adları, kapsamsız rotalar. IIS için POST takma
  adı açarken kimliği `_post` ile bitir, yoksa iki araç olur.
- `tools/call` rotayı süreç içinde çalıştırır; işleyici yine `api_send()` /
  `api_fail()` ile çıkar, `api_send_hook()` cevabı JSON-RPC'ye çevirir.
  İşleyici `$_SERVER`/`php://input` okumamalı, `api_request_input()` ve
  `api_header()` kullanmalı (MCP bunları ayarlayıcıyla doldurur).
- Verilen adres sitenin kökü: `site.com/mcp.php` (yönlendirici
  `pinegrap/mcp.php`'ye devreder). Bir kaynağın birden çok adresi
  `pg_oauth_resource_addresses()`'tadır; istemci belgedeki `resource`'u
  bağlandığı adresle karşılaştırdığı için her adres kendi belgesini alır.
  Kaynak karşılaştırması yazımla değil `pg_oauth_resource_key()` ile yapılır.
- İki protokol kuşağı: 2026-07-28 (oturumsuz, `Mcp-Method`/`Mcp-Name`
  başlıkları zorunlu) ve `initialize` ile açılan 2025-xx. Oturum kimliği
  verilmez, akış (SSE) yok.

## Webhook olayları

- Olay duyurmak için `pg_announce('<ad>', array(...))` (`includes/fn/events.php`,
  her yerde yüklü); alternatifi `api_webhook_enqueue()`.
- Duyurduğun olay adı `api_webhook_events()` kataloğunda tanımlı olmalı.
- **Toplu içe aktarma yollarından olay duyurma**; beş bin kuyruk satırı yerine
  tek listeleme yeterlidir ve bunu katalog metninde yaz.

## Giden konnektörler

- Yeni konnektör = `includes/api/outbound/connectors/<ad>.php` + `base.php`
  sözleşmesi (fonksiyon adları tablosu, arayüz değil).
- Yeni sağlayıcı **sıfır şema değişikliği** gerektirir; kayıt üstüne kimlik
  kolonu ekleme.
- Kuyruk deseni: aynı kayıt için bekleyen satır varken ikincisini yazma, ağ
  çağrısından önce 300 sn kira (`run_after`, durum `waiting`). Ağ çağrısını
  istek akışında yapma.

## Modül dikişi (ERP → API)

- ERP, API'ye bir şey eklemek için `includes/api/**` altına **dokunmaz**; her şey
  `includes/erp/api.php` dikiş yerinden bildirilir. Eksik kanca
  `dev/_handoff/` ile istenir.
- Dikiş yerinde beş isteğe bağlı fonksiyon: `erp_api_routes()`,
  `erp_webhook_events()`, `erp_openapi_objects()`, `erp_owner_scopes($owner)`,
  `erp_scope_groups()` (label/description/icon dolu olsun).
- Modül dosyası kendi bağımlılıklarını kendisi `require_once` eder; API entry
  onu yüklemez. Modül yalnız açıkken yüklenir.
- Ad çakışmasında API'nin kendi girdisi kazanır; modül çekirdek bir adı
  (`order.created`) ezemez.
- Dosya sahipliği: `includes/api/**` + `api_settings.php` + `api_docs.php` +
  `integration.php` + `webhooks.php` API'nin; `includes/erp/**` + kök `*erp*.php`
  ERP'nin.
- Migration adım numaraları çakışmasın (ERP 4.58–4.69, API 4.70–4.79); yazmadan
  önce runner listesine bak.
- Ortak dosyalarda **yalnız ekleme** yap, tam yeniden yazma (`tr.json`,
  `changelog.txt`, açık sürümün migration dosyası, `init.php`,
  `docs/CLAUDE-tam.md`, `docs/degisiklikler.md`). Yazmadan önce
  `git fetch origin development && git merge origin/development`, push'tan önce
  `development` üzerine rebase; PR'ın tabanı `development`'tır, `main` değil.

## Pazaryeri

- Eşleme satırı siparişten **önce** yazılır (transaction yok; tekrar denemede
  mükerrer siparişi engelleyen tek şey budur). Yarım kalan içe aktarma =
  `order_id=0` olan `importing` satırı.
- İçe aktarmada yalnız üç yan etki uygulanır: stok düşümü,
  `pg_marketplace_product_changed()`, `create_notification('new_order')`.
  E-posta, üyelik, puan, hediye kartı, kampanya ve teklif bilerek dışarıdadır.
- Eşleşmeyen tek kalem tüm siparişi reddettirir; röle e-posta adresiyle kişi
  eşleştirilmez.
- İptalde `process_order_cancellation(..., $attempt_refund = false)` çağrılır ve
  **stok elle geri alınır**; dönüş değeri `'success'`.
- Stok takibi olmayan ürün ilan edilmez; KDV en yakın **alttaki** orana
  yuvarlanır.
- n11 ürün tarafı asenkrondur: gönderim üzerine "başarılı" deme, `taskId` ile
  SKU başına sonucu oku; `REJECT` kalıcı, kimliksiz `IN_QUEUE` yeniden denenir.
  Sipariş tarihleri ms ve GMT+3.
- Kimlik bilgileri `marketplace_accounts` içinde şifreli JSON
  (`"<ciphertext>:<iv>"`).

## e-Belge sağlayıcıları (Logo İşbaşı)

- Kimlik bilgilerini hiçbir dosyada tutma; ayar kartından girilir, token
  `erp_edoc_providers.settings` içinde şifreli durur.
- `getplatform` çağırma (entegrasyon anahtarıyla 403); Logo'nun verdiği adres
  doğrudan base URL'dir. Test ortamının döndürdüğü `baseUrl`'i yok say.
- Kota gerçek kısıttır (ayda 3000 okuma / 7000 yazma): belge başına en az istek
  at, durum sorgusunu düğme/kuyrukla yap, döngüde sorgulama.
- Göndermeden önce zorunlu alanları sürücüde denetle (TCKN/VKN, unvan, adres,
  şehir, ilçe, posta kodu) — kota harcamamak için.
- `integrationInvoices` yalnız taslak yaratır; belge
  `POST einvoices/eInvoiceWithJson?invoiceId={id}` (gövdesiz) ile resmîleşir.
- Başarıyı kelimeye bakarak belirleme: başarılı gönderim `HTTP 400` +
  `isError:true` dönebilir; belgenin kendi durumunu bir kez oku.
- `eStatus: 0` = "kaydedildi, GİB'e gönderilmedi" (`created`).
- ETTN ve belge numarasını gönderim yanıtından al (`data.uuid`, `data.no`).
- `GetOutgoingInvoiceDataList`i birincil durum kaynağı yapma.
- İndirilen belgenin türünü baytlara bakarak belirle (PDF adresi GİB'e geçmemiş
  belgede HTML, UBL adresi zip döndürür).
- `cancel_invoice` ve `send_waybill` desteklenmiyor olarak kalır.

## Bitirmeden önce

`php tools/lint.php`, `php tools/check_lang.php`, `php tools/check_api_schema.php`
temiz olmalı. Sunucuya alan eklediysen `erp_api_<nesne>_schema()`'ya da ekle —
`check_api_schema.php` modül dosyalarını taramaz.
