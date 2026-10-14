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
  `?` sütuna değil sunucu fonksiyonunun verebileceği değere bakar: her ekleme
  yolunun yazdığı `DEFAULT NULL` sütun (ürün/grup adı, sayfa adı, başlığı ve
  açıklaması, dosya adı ve türü) ya da `(string)` ile çevrilen alan `?` almaz;
  bir yolun yazmadığı ya da NULL yazdığı, fonksiyonun `null` verdiği alan
  alır (`api_openapi_type_spec()` açıklaması). O sütunları yazmayan yeni bir
  INSERT `test_api_openapi_status_create_paths_write_those_columns`'u kırar:
  alan aynı değişiklikte `?` alır.
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
- Anahtar kod (`key_codes.code`) bir teklifin koduyla aynı olamaz: ödeme
  yazılan kodu önce `offers.code`'da arar, anahtar koda ancak teklif yoksa
  bakar. Kod yazan ya da üreten yer iki tabloyu da denetler
  (`api_key_code_own_or_fail()`, `api_key_code_generate()`).
- Yazma provası istiyorsan uca `'dry_run' => true` koy ve **ilk yazmanın hemen
  üstüne** `api_dry_run_stop(...)` çağır; erken koyulan duruş yapılmamış
  doğrulamayı vaat eder. Toplu uçta `api_dry_run_requested()` ile satır satır
  çalış, yazmayı atla, sonucu sonda ver.
- Panel fonksiyonu denetimlerini yazımın içinde yapıyorsa onları
  `*_check()` olarak ayır, gerçek fonksiyon da önce onu çağırsın; uç provada
  aynısını çağırıp durur (`ws_message_send_check()`, `ws_upload_check()`,
  `ws_reaction_check()`, `ws_ack_set_check()`, `ws_approval_decide_check()`,
  `ws_task_note_add_check()`). Dosya provası diske yazmaz:
  `ws_upload_inspect()` baytlara bellekte bakar. Süresi dolmuş kaydı kapatan
  yan etki (oylama, onay isteği) provada atlanır.
- Kısmi güncellemede önce mevcut satırdan tam `$data` kur, sonra gelen alanları
  üstüne koy.
- Gövdesiz cevap veren uç (`api_send(204, ...)`) rotada `'status' => 204`
  bildirir; OpenAPI 200 yerine 204 yazar. `tools/check_api_schema.php`
  `$without_body` listesine de girer.
- İç içe nesne satır içinde nullable yazılamaz: ayrı nesne bildir, alanı
  `'Ad?'` yaz (`'Device?'`, `'ErpQuoteSignature?'`).
- Dosyayı kendi baytlarıyla veren uç `api_send_file()` ile çıkar ve rotada
  `'binary' => true` taşır (`returns` istemez, OpenAPI 200'ü binary yazar);
  `'mcp' => false` da koy. Dosyayı form parçası olarak da alan uç
  `'form_file' => 'file'` bildirir (OpenAPI'ye multipart eklenir) ve
  `$_FILES`'ı yalnız `api_send_hook() === null` iken okur
  (`ws_api_upload_input()`, `GET/POST /workspace/.../file(s)`).
  `api_send_file()` `Range` / `If-Range`'i kendisi karşılar (206, 416,
  `Accept-Ranges`); uç ayrıca bir şey yapmaz. `'form_file'` rotasında
  `Idempotency-Key` parmak izi parçanın adını, boyutunu ve SHA-256'sını
  kapsar (`api_idempotency_input()`); dosyayı başka adla alan yeni bir
  yol açarsan oraya da kat. Görselin küçük kopyası `ws_file_preview()`
  (`pg_process_image_file()`, `write => false`); kopya `data/temp`'te.
- Sonradan kişinin kendi adıyla iş yapan kayıt (zamanlanmış mesaj gibi) yalnız
  ekip cihazları oturumundan açılır (`ws_api_own_words_only()`): OAuth
  bağlantısı da uygulama adıyla yazdığı için 403 alır, yoksa bağlantının
  kurduğu iş sonra kişinin sözü olarak görünür. Uygulama anahtarı ve OAuth
  okur ve iptal eder. Uygulamanın yazdığı her şey uygulama adıyla görünür.
- Kişinin kendi cevabı ya da tercihi (okundu onayı, okundu işareti, onay,
  oy, kanal bildirim düzeyi, kanalı sabitleme ve sırası, tartışmaya
  katılma / ayrılma, oylamayı kapatma) da `ws_api_person_only()` ister:
  uygulama anahtarı 403, OAuth bağlantısı kabul. Kişinin sözlerini
  değiştiren ya da silen uç
  `ws_api_own_words_only()` ister; OAuth da reddedilir (plan kararı 11).
  Sonradan yalnız kişinin değiştirebileceği bir şeyi onun adıyla açan uç
  da (oylama açma: `ws_poll_edit()` yalnız kişinin kendi mesajını kabul
  eder) `ws_api_own_words_only()` ister.
- `ws_api_own_words_only()` isteyen uç (doğrudan ya da `ws_api_pin_target()`
  gibi bir yardımcıyla) `'mcp' => false` taşır: MCP istemcisi OAuth
  bağlantısıyla gelir, araç her çağrıda 403 alırdı.
  `test_ws_api_own_words_routes_are_no_tools` bunu denetler. Reddin metni
  "Only the person's own team devices session (POST /auth/login) can …; an
  application key or an OAuth connection …" biçimindedir, "signed in on a
  device" demez (OAuth bağlantısı da cihaz satırıdır);
  `test_ws_api_own_words_refusals_name_the_team_devices_session` denetler.
  `ws_api_person_only()` metinleri "signed in on a device" der: orada OAuth
  kabul.
- Herkes için aynı olan düzen (kanalı gruba taşıma) kişisel tercih
  değildir: `ws_api_person_only()` istemez, kanal değiştirme ucu gibi
  sahibin haklarıyla kapılanır (uygulama anahtarı kabul). Erişim değiştiren
  yazım kimin girip çıktığını hem cevapta hem provada adıyla verir
  (`POST /workspace/channels/{id}/group`, panelin önizleme fonksiyonu).
  Önizleme fonksiyonu olmayan panel yazımı için tahmini
  `ws_group_sync_plan()` gibi eşitlemenin hesabını yazmadan yapan bir
  fonksiyonla kur, cevabı eşitlemenin gerçekten yaptığından ver; sahibin
  okumadığı kanal adıyla değil sayıyla geçer (kanal grubu uçları).
- Kendi Site Günlüğü satırını `$_SESSION['sessionusername']` ile yazan
  panel fonksiyonu API'den `ws_api_as_owner()` içinde çağrılır; yoksa
  satır adsız düşer (`test_ws_api_panel_log_lines_carry_the_owner`
  `includes/workspace/api*.php`'deki çağrıları denetler). Zil satırı
  (`ws_bell_add()`) adı kendisi `ws_bell_user()`'dan alır: oturum yoksa
  uygulamanın sahibi; `ws_notify()` için sarmalamak gerekmez.
- Çalışma Alanı araması (`GET /workspace/search`) panel kayıtlarını türü
  okuyan kapsamla süzer: `ws_api_search_record_scopes()`
  (`includes/workspace/api_day.php`). `ws_record_type_keys()`'e yeni bir
  kayıt türü eklenirse oraya da kapsamıyla yaz; yazılmayan tür API'de
  aranmaz.
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
  Bearer jetonu iki türdür: kişinin kendi ekip cihazları oturumu
  (`POST /auth/login`) ve OAuth bağlantısı (ikisi de `api_devices` satırı).
  OpenAPI'deki iki güvenlik şemasının metni `api_openapi_security_schemes()`'te
  ve üç arayanı kişiye özel uçların 403 metinleriyle aynı sözcüklerle anar;
  şemanın adı `deviceToken` kalır (üretilmiş istemciler adıyla anıyor).
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
- Pinegrap AI ve MCP ajanı bir model ya da ajan çağrısı kadar sürer; API
  ucu onları `ws_api_assistants_carry_on()` ile sürdürür, yeri
  `ws_api_carry_on_where()` söyler: PHP-FPM'de yanıttan sonra, IIS'te
  yalnız açık uygulamanın yokladığı `GET /workspace/sync` çağrının içinde
  (bir adım), mesaj yazma ucunda hiç. Uzun iş başlatan yeni uç da bu yolu
  kullanır, yanıtı bekletmez.
- `GET /workspace/sync`'e "yalnız değişince" gelen yeni bir parça
  eklerken işaretini imlece **isteğe bağlı** anahtar olarak yaz
  (`ws_api_sync_cursor_write()`, örnek `p`): boşken yazılmaz, eksikken
  okunur; yoksa uygulamaların elindeki imleç 400 alır ya da her şey bir
  kez yeniden gelir. Başka bir izne bağlı sayı (zil, `account:read`)
  izin yoksa `null` döner, reddedilmez.
- Gelen kutusundan (`ws_inbox`) satır silen yeni kod doğrudan `DELETE`
  yazmaz, `ws_inbox_delete($where)` çağırır (`notify.php`): iz
  `ws_inbox_deleted`'a düşer, `GET /workspace/inbox?changed_since=` ve
  eşitlemenin `inbox_changed_at`'i silineni ancak böyle görür. Satırı
  yerinde değiştiren kod da `created_at` ya da `read_at`'i ilerletir.
  Kişiyi bir kanalın erişiminden çıkaran yeni kod (üyelik silme, kanalı
  kapatma) `ws_inbox_reach_lost($channel, $user_ids)` çağırır: göremediği
  satırların `read_at`'i ilerler, `changed_since` onları `deleted: true`
  ile verir. Kişiyi erişime geri alan yeni kod (özel kanala ekleme,
  kanalı açma, tartışmaya yeniden katılma) da önceden okuyamayanlar için
  `ws_inbox_reach_regained($channel, $user_ids)` çağırır: okunmuş
  satırların `read_at`'i yine ilerler, `changed_since` onları canlı
  satır olarak geri verir. Bir liste cevabına zarf alanı (`reload` gibi)
  `api_ok_list()`'in `$extra`'sıyla ve rotada `'list'` yanında bildirilir.
- Kişiye özel mesaj durumu ("Benden sil", `ws_message_hides`) mesajın
  `touched_at`'ini ilerletmez: kanaldaki herkesin ekranı ve cihazı mesajı
  boşuna yeniden çizer. Kişinin kendi kaydının anı (`hidden_at`)
  `ws_messages_touched()`'a okuyucuyla girer (`$user_id`;
  `ws_channel_news()`'te `user_id` seçeneği); mesaj yalnız ona API'de
  `hidden: true`, panelde silinmiş olarak döner. Kanalın değişen
  mesajlarını okuyan yeni kod okuyucuyu geçirir.

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
- İstemciyi durdurmanın yolu `oauth_clients.status = 'disabled'`
  (Uygulama Erişimi → "Bağlı uygulamalar" kartı): bağlantılar kalır, açılınca
  geri gelir. Kapalı satırı süpürme silmez ve CIMD belgesi yeniden çekilmez;
  silinirse istemci kendini yeniden kaydeder. Kişi kendi bağlantılarını
  `connected_apps.php`'de görür ve sonlandırır; bağlantıyı formdan gelen
  kimlikle kapatan kod kişiye ait olduğunu sorguda denetler
  (`pg_oauth_connection_end()`).
- İstemci uyumu (Cursor, VS Code, Gemini CLI, Codex, mcp-remote): kural
  değiştirirken `tests/oauth_test.php`'deki gerçek kayıt gövdelerini koru.
  Kayıt fazla `grant_types`/`response_types`'ı reddetmez, yerine
  desteklenenleri yazar; uygulama şeması noktasız olabilir; loopback adı ve
  portu eşleşmede yok sayılır (`pg_oauth_redirect_uri_matches()`); bilinmeyen
  kapsam (`openid`, `offline_access`) authorize'da ve yenilemede atlanır
  (`pg_oauth_scope_requested()`). Panel rehberi istemci başına adım ve ayar
  verir (`api_settings_oauth_guide()`); yeni istemci oraya eklenir.

## MCP sunucusu (2026.4.10, `mcp.php`, `includes/mcp/`)

- Araçlar **rotaların kendisidir** (`pg_mcp_tool_from_route()`): yeni bir uç
  yazınca araç kendiliğinden gelir. `summary`/`description`/parametre
  açıklamaları modelin okuduğu metindir — İngilizce, kısa ve doğru yaz.
- Dışarıda kalanlar `pg_mcp_route_is_offered()`'da: `auth.*`, `push.*`,
  `docs.*`, `*_post` takma adları, kapsamsız rotalar ve `'mcp' => false`
  taşıyan rota (uygulamanın yokladığı `GET /workspace/sync` gibi). IIS için
  POST takma adı açarken kimliği `_post` ile bitir, yoksa iki araç olur.
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

## Bağlı hesaplar: giden OAuth (2026.4.10, `includes/fn/connect.php`)

Site bir OAuth istemcisi olarak Google/Microsoft hesabına bağlanır (plan
`docs/_plan_tersine_oauth.md`, model A: her site sağlayıcının konsolunda
kendi uygulamasını kaydeder). Ekran `connections.php` (Ayarlar ›
Bağlantılar), geri dönüş adresi tek: `connect.php`
(`pg_connect_redirect_uri()`).

- Yeni sağlayıcı = `includes/connect/providers/<anahtar>.php` (uç
  adresleri, `issuers`/`issuer_pattern`, `identity_scopes`,
  `authorize_params`, `token_per_scope`, `options`, `setup` adımları).
  Kimlik belirtecine nonce taşımayan sağlayıcı `'nonce' => false` der;
  iptali erişim belirteciyle (Bearer, JSON `null` gövde) yapan sağlayıcı
  `'revoke_auth' => 'bearer'` der (Dropbox).
  Yeni özellik = `includes/connect/purposes/<anahtar>.php` (`label`,
  `description`, `owner` site/person/any, `scopes` sağlayıcı başına). Şema
  ve `connect.php` değişmez. Yalnız giriş yapan sağlayıcı (Apple)
  `'accounts' => false` taşır: Bağlı hesaplar ekranı onu listelemez,
  `pg_connect_start()` reddeder.
- Bağlan düğmesi: `connect.php`'ye POST eden bir form içine
  `pg_connect_start_fields($sağlayıcı, $amaç, 'ekranım.php')`. Aynı hesap
  yeni amaçla yeniden bağlanırsa kapsamlar birleşir (`connection_id`).
- Kullanmak: `pg_connect_accounts(array('purpose' => ...))` ile hesabı
  bul, `pg_connect_api($id, 'POST', $url, $seçenekler, $kapsamlar)` ya da
  yalnız belirteç için `pg_connect_access_token($id, $kapsamlar)`.
  Microsoft'ta belirteç kaynak başınadır: çağrının kapsamlarını ver
  (ör. `https://outlook.office.com/SMTP.Send`). Refresh token'ı asla
  okuma; yenileme kilitli ve `invalid_grant` hesabı `reauth` yapar.
- Sunucudan sunucuya alınan belirteçler (Paraşüt, kargo):
  `pg_connect_service_get/put/delete($anahtar)` — düz dosyaya ya da
  `config` kolonuna belirteç yazma.
- Şifreleme `ENCRYPTION_KEY` ister (`pg_connect_can_seal()`); yoksa ekran
  bağlamayı kapatır.
- Giden istekte cevap başlığı gerekiyorsa (sürdürülebilir yüklemenin
  `Location`'ı) `api_http_request()`'e `'response_headers' => true` ver;
  cevap `headers` (küçük harfli ad => değer) taşır.
- Örnek kullanıcı: e-posta (`purposes/mail.php`, `includes/fn/mail.php`
  `pg_mail_connection*()`). Amaç dosyası sağlayıcı başına teslim
  bilgisini (`send`) taşır; yeni posta sağlayıcısı = sağlayıcı dosyası +
  `send` girdisi. Tablolar yokken (`pg_connect_schema_ready()`) bütün
  okumalar "hesap yok" der.
- Giriş (Google, Microsoft, Apple): `includes/fn/signin.php`, dönüş adresi
  `signin.php`. Sağlayıcı dosyasındaki `signin` bloğu girişi tanımlar
  (`order`, `scopes`, `pkce`, `subject_claims`, `verified_tenants`,
  `verified_claims`, `client_secret => 'apple_jwt'`, `user_field`,
  `return` — başka dönüş dosyası, `authorize_params`); yeni
  sağlayıcı = bu blok + Ayarlar › Güvenlik'te anahtarı
  (`config.signin_providers`). Kimlik `user_identities`'te; giriş belirteç
  saklamaz. Düğmeler yalnız `pg_signin_buttons()` ile. Google'ın dönüşü
  `google_auth.php` (Google Cloud'daki adres değişmesin diye; dosya yalnız
  `signin.php`'yi çağırır), uygulaması `config.oauth_google_*`, anahtarı
  `oauth_google_enabled`. `user.user_google_id` 10.75'ten beri yalnız
  taşınmamış eski bağlar için okunur.
- Örnek kullanıcı: bulut yedek (`purposes/backup.php`,
  `includes/fn/backup_cloud.php`). Ayar formunun içindeki bağlan düğmesi
  `form="..."` ile formun dışındaki gizli formu gönderir
  (`$pg_settings_modals[]`); dönüş adresi `settings_general.php?backup_connect=<tür>#pgset-backup`
  olur ve ekran `pg_connect_flash_take()` ile mesajı yalnız o parametre
  varken alır. Büyük dosya 10 MiB parçalarla, her parça `pg_connect_api()`
  ile (belirteç arada yenilenir); Graph yükleme oturumunun adresine
  belirteç gönderilmez, `api_http_request()` doğrudan.

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
  `docs/degisiklikler.md`, `docs/sema-adimlari.md`). Yazmadan önce
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
