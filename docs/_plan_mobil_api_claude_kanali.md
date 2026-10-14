# Plan — Mobil API ve Claude kanalı

**Durum (2026-10-14):** yalnız plan, kod yok. İki iş var: (A) bir iOS / Android uygulamasının
Çalışma Alanı'nı kullanabilmesi için dış API'de kalan eksikler, (B) Claude'un bir Çalışma
Alanı kanalına üye gibi katılması. Her dilim tek küçük PR'dır ve uçtan uca çalışır.
`docs/_plan_api_mobil.md` (cihaz oturumu, bildirim + push, ETag) ve `docs/_plan_claude_kanal.md`
(rutinle `@Claude`, Faz 1) bitmiş işlerin plan dosyalarıdır; kalanları bu plana taşındı.

**Karar beklemeden başlayabilen dilimler:** M1–M6. Bunlar 2026-09-27 kararının
("uygulamaya özel uç yazılmaz, her istemcinin işine yarayan genel yetenek eklenir") çizgisinde.
Diğer dilimler aşağıdaki kararlardan birini bekler; hangisini beklediği §0 tablosunda yazıyor.

## Erdal'ın kararları

1. Mobil uygulama ne olacak: (a) Kodpen'in mağazalarda yayınlayacağı, her Pinegrap sitesine bağlanan tek uygulama, (b) bugünkü panelin PWA'sını mobilde iyileştirmek, (c) her müşteriye kendi markalı uygulama. **Öneri: a.** Uygulama ayrı bir proje olur, bu plan yalnız API tarafıdır.
2. Uygulamada giriş nasıl olsun: (a) OAuth, yani tarayıcıda sitenin kendi giriş ekranı (PKCE, iki adımlı doğrulama ve Google / Microsoft / Apple girişi kendiliğinden gelir), (b) bugünkü `/auth/login` (kullanıcı adı, parola, kod). **Öneri: a birincil, b yedek.**
3. Anlık bildirim (FCM / APNs) nasıl gitsin: (a) Kodpen'in push aracısı üzerinden (`push.pinegrap.com`; anahtarlar Kodpen'de, bildirim metin taşımaz), (b) her site kendi Firebase / Apple anahtarını girer, (c) yalnız web push (PWA). **Öneri: a.**
4. Push aracısı kimlere açık olsun: (a) lisanslı siteler (Pinegrap AI gibi), (b) herkese. **Öneri: a.**
5. Claude kanala hangi yolla gelsin: (a) Claude Code kanal eklentisi (açık duran bir Claude Code oturumu kanala anlık bağlanır), (b) yalnız bugünkü rutin, (c) ikisi birden, ayarlarda bağlantı türü seçilir. **Öneri: c.**
6. Claude Code kanalı kimin oturumu olsun: (a) site geneli tek oturum (Faz 1 kararı gibi, yöneticinin bilgisayarı ve aboneliği), (b) kişi başı (herkes kendi Claude Code'unu bağlar, `@Claude` soranın oturumuna gider). **Öneri: önce a, b sonra ayrı dilim.**
7. Kanal oturumuna kimler yazabilsin: (a) yalnız bağlantı sahibi, (b) ayarlarda seçilen kişiler, (c) bütün ekip. **Öneri: b.** Oturum o bilgisayarda dosya okuyup komut çalıştırabildiği için.
8. Claude neyi duysun: (a) yalnız `@Claude` yazılan mesajları ve Claude'un yanıtına verilen cevapları, (b) izinli kanalın bütün mesajlarını. **Öneri: a.**
9. Claude Code'un araç onayları (komut çalıştırma, dosya yazma) Çalışma Alanı'na gelsin mi: (a) evet, yalnız bağlantı sahibine onay kartı (mobilden de), (b) hayır, terminalde kalsın. **Öneri: a.**
10. Kanal eklentisi nerede yaşasın: (a) Kodpen'in ayrı küçük deposu (TypeScript, Claude Code eklenti pazarı), (b) Pinegrap'ın içinde komut satırından çalışan bir PHP betiği. **Öneri: a.**
11. claude.ai bağlayıcısıyla ya da mobil uygulamayla bağlanan bir kişinin yazdığı mesaj nasıl görünsün: (a) kişinin adıyla, yanında "Claude ile" gibi küçük bir rozet, (b) bugünkü gibi bağlantının adıyla "(uygulama)". **Öneri: a.**
12. Eklenti oturunca Anthropic'in resmi kanal listesine başvurulsun mu: (a) evet, (b) hayır, kurulum geliştirici bayrağıyla kalsın. **Öneri: a.**

**Önceki kararlar (yeniden sorulmaz):** mobil uygulamayı ekip kullanıcıları kullanır, müşteri
uygulaması değildir; uygulamaya özel uç yazılmaz, genel yetenek eklenir; bildirim mevcut push +
API ucuyla (2026-09-27). `@Claude` Console API anahtarıyla değil Claude aboneliğiyle çalışır;
bağlantı site geneli tektir; Claude kayıtları anahtarın izin verdiği kadar okur, kayda yalnız
onaylı taslakla yazar; günlük hak dolunca istek 24 saat kuyrukta bekler, sonra iptal edilir
(2026-09-23).

---

## 0. Dilimler

Efor: K küçük, O orta, B büyük.

| Dilim | Ne | Efor | Şema | Bekleyen karar |
|---|---|---|---|---|
| M1 | Cihaz oturumundan yazılan mesaj ve tepki kişinin kendi adıyla | K | yok (rozet için C5) | yok |
| M2 | Mesaj düzenleme, silme, okundu işareti, değişenler | K | yok | yok |
| M3 | Tek çağrıda eşitleme (`GET /workspace/sync`) | O | yok | yok |
| M4 | Çalışma Alanı gelen kutusu API'de | K | yok | yok |
| M5 | Kanala dosya yükleme ve jetonla indirme | O | yok | yok |
| M6 | Kanal tercihleri, anket oyu, tartışmaya katılma / ayrılma | K | yok | yok |
| M7 | Yerel push (FCM / APNs) kaydı ve gönderimi | O (+ aracı) | var | 1, 3, 4 |
| M8 | Eski listeden kalanlar: toplu istek, silinen kayıt listesi, `sort=`, CORS | dilim başına K | yok | gerekince |
| C1 | "Kanallarda Claude"da bağlantı türü: rutin ya da Claude Code kanalı | O | var | 5, 7 |
| C2 | Kanal eklentisi (ayrı depo) | O | — | 5, 10 |
| C3 | Araç onayı Çalışma Alanı'nda (izin aktarımı) | O | var | 9 |
| C4 | Kişi başı Claude Code oturumu | O | var | 6 |
| C5 | Bağlantıyla yazan kişinin mesajında "… ile" rozeti | K | var | 11 |

---

## A. Mobil API

### A.1 Bugün olanlar

- **Kişi adına oturum:** "Ekip cihazları" uygulaması, `POST /auth/login` (parola, `otp` ile iki
  adımlı doğrulama, giriş ekranının kilidi), `/auth/refresh` (dönen yenileme jetonu),
  `/auth/logout`, `/auth/me`, `/auth/devices`. Jeton `pgat_…` 1 saat, yenileme 30 gün.
  Parola değişince ve "her yerden çık"ta cihazlar kapanır (`includes/api/devices.php`).
- **OAuth 2.1:** yetkilendirme kodu + PKCE, dinamik kayıt ve adresi bir JSON belgesi olan
  istemci kimliği. Dönüş adresi `https`, yerel makine `http` ve uygulamanın kendi şeması
  (`com.kodpen.app:/cb`) olabilir (`includes/oauth/rules.php`). Bağlantının jetonları da
  `api_devices` satırıdır, cihaz gibi doğrulanır. Jeton `integration.php` ya da `mcp.php` için
  ayrı verilir (RFC 8707).
- **Bildirim:** `GET /notifications`, `/notifications/unread-count`, okundu / okunmadı. Web push
  (`/push/config`, `/push/subscriptions`, `/push/test`) metin taşımaz; cihaz uyanınca
  `GET /notifications?unread=true` okur. Çalışma Alanı'nda anılma, görev ve onay gibi olaylar zile
  ve push kuyruğuna düşer (`ws_notify()`).
- **Genel yetenek:** her GET'te ETag / 304, `fields=`, `updated_since` (notlar, formlar, ERP),
  kanallarda `active_since`, cihaz başına hız sınırı, idempotency anahtarı.
- **Çalışma Alanı (65 uç):** kanal listesi (`unread` sayısıyla), kanal açma ve değiştirme, üyeler,
  mesaj okuma (`since_id`, `before_id`) ve yazma, tartışma listesi ve başlatma, tepki, okundu
  isteği ve okundu verme, onay isteme ve karar, kararlar, onaylı değişiklikler, programlanmış
  mesaj, notlar ve sürümleri, görevler (devir, not, süre, yineleme), plan, arama, "Günüm",
  şablonlar. Okundu verme, onay kararı ve programlanmış mesaj yalnız cihaz oturumundan kabul
  edilir (`ws_api_person_only()`).
- **Panel PWA'dır:** `manifest.php`, `sw.js` ve web push var; mobil tarayıcıda "Ana ekrana ekle"
  ile bugün de kullanılabiliyor.

### A.2 Eksikler

| İhtiyaç | Bugün | Eksik | Dilim |
|---|---|---|---|
| Mobilden yazılan mesaj kişinin olsun | Cihaz oturumundan yazılan mesaj ve tepki uygulama adıyla düşüyor ("Ekip cihazları (uygulama)"), kişi hız sınırına ve genel kanala kendiliğinden katılmaya girmiyor (`ws_api_messages_create()` her zaman `app_id` veriyor) | Cihaz oturumunda `sender_kind = 'user'` | M1 |
| Kendi mesajını düzeltmek, silmek | Yalnız panelde (`ws_edit`, `ws_delete`) | Uç yok | M2 |
| Okundu işareti | Panel `ws_sync` ile işaretliyor; API'de okunmamış sayısı var, işaret yok | Uç yok | M2 |
| Görülmüş mesajdaki değişiklik (düzenleme, tepki, silme, oy) | Panel `ws_messages_touched()` ile alıyor | `changed_since` yok | M2 |
| Açıkken tek istekle güncel kalmak | Panel her birkaç saniyede `ws_sync` çağırıyor; API'de kanal listesi, mesajlar ve zil ayrı ayrı | `GET /workspace/sync` yok; API çağrısı kişiyi "ekranda" saymıyor, push bir dakika ertelenmiyor | M3 |
| Bildirime dokununca doğru ekranı açmak | Zil satırının konusu `workspace_inbox` kimliği, ama bu kimliği kanal / mesaja çeviren uç yok | `GET /workspace/inbox`, okundu | M4 |
| Fotoğraf ya da dosya göndermek | Panelde `ws_attach`; API'de `POST /files` yalnız görsel / PDF ve operatörün klasörüne | Kanala yükleme ucu yok | M5 |
| Kanal dosyasını açmak | Mesajdaki `file.url` `get_file.php`'ye gidiyor; o da tarayıcı oturumuna bakıyor, Bearer jetonu tanımıyor | Jetonla indirme ucu yok | M5 |
| Sessize alma, anket, tartışmaya katılma | Yalnız panelde (`ws_channel_notify`, `ws_poll_vote`, `ws_thread_join/leave`) | Uç yok | M6 |
| Uygulama kapalıyken bildirim | Yalnız web push | FCM / APNs yok | M7 |

### A.3 Dilimler

**M1 — Kişinin kendi adıyla yazmak (K, şema yok). Yapıldı (#369):** görev notu, görev kartı,
onay isteği ve şablonun sabitlenen mesajı da kişinin adıyla. Arayan cihaz oturumuysa
(`api_current_device() !== null`) `ws_api_messages_create()` ve `ws_api_messages_react()`
`app_id` vermez: mesaj kişinin, hız sınırı (`ws_rate_limited()`) ve genel kanala kendiliğinden
katılma uygulanır. Okundu isteğinin "yalnız personel isteyebilir" kuralı da o zaman uygulama
yerine kişiye bakar. Uygulama anahtarıyla yazan entegrasyonlar değişmez. OAuth bağlantısının da
kişi adına yazması karar 11'e bağlıdır (C5); karar gelene kadar bağlantı bugünkü gibi yazar.
API açıklamaları ("readers see it as written by the application") güncellenir.

**M2 — Düzeltme, silme, okundu, değişenler (K, şema yok). Yapıldı (#371).**
- `PATCH|POST /workspace/messages/{id}` {text}: panelin `ws_edit` kuralıyla (yalnız yazan, kilitli
  karar değil).
- `DELETE|POST /workspace/messages/{id}/delete`: panelin `ws_delete` kuralıyla.
- `POST /workspace/channels/{id}/read` {message_id}: `ws_channel_mark_read()`; yalnız cihaz
  oturumu (`ws_api_person_only()`).
- `GET /workspace/channels/{id}/messages?changed_since=<zaman>`: `ws_messages_touched()`; yanıtta
  `edited`, `deleted`, tepkiler ve okundu sayısı güncel.

**M3 — Tek çağrıda eşitleme (O, şema yok). Yapıldı (#374):** kanal ve tartışma listesi yalnız
değiştiklerinde gelir; rota MCP aracı değildir. Sonradan cevaba zil sayısı (`account:read` ile), gelen
kutusunun değişim anı ve kişinin uygulamasına açık kanalın sabitlenmiş mesajı, sekme sayıları ve
pano işareti eklendi. `GET /workspace/sync?cursor=…&channel_id=…`:
değişen kanallar (okunmamış sayısıyla), tartışmalar, gelen kutusu sayısı, açık kanalın yeni ve
değişen mesajları, sonraki `cursor`. ETag / 304 ile ucuz; uygulama önündeyken 3–5 sn'de bir
çağırır. Çağrı `ws_touch_seen()` yapar, böylece ekranda okuyan kişinin telefonu push ile ayrıca
uyandırılmaz (panelin davranışı). `ws_sync`'in mantığı ortak bir fonksiyona alınır, iki yer aynı
kodu kullanır. WebSocket ya da SSE yok: paylaşımlı barındırmada ve IIS'te her açık bağlantı bir
PHP işçisi tutar; uygulama arka plandayken push, öndeyken kısa yoklama yeter.

**M4 — Gelen kutusu (K, şema yok). Yapıldı (#377):** satırda `parent_channel_id`, `meeting_url`,
tek satır `GET /workspace/inbox/{id}`; zil satırının `url`'si de düzeldi. `GET /workspace/inbox`
(tür, kanal, mesaj, görev, not, plan öğesi, kimden, okundu; cursor), `POST
/workspace/inbox/read` {ids | all}. Zil satırının `subject` alanı `workspace_inbox` olduğunda
uygulama bu kaydı okuyup ilgili ekranı açar. `ws_inbox_list()` ve `ws_inbox_mark_read()`
kullanılır.

**M5 — Dosya (O, şema yok). Yapıldı (#378; önizleme ve Range hariç):** erişim kuralı
`get_file.php`'den alınmadı; uç mesajdan gider ve mesajı okuyabilen dosyayı da alır
(`ws_can_read_channel()`), böylece kopya oluşmadı. Dosya JSON içinde değil kendi baytlarıyla
döner (`api_send_file()`); iki uç MCP aracı değil. `?size=` önizleme ve video için `Range`
sonraki küçük dilim.
- `POST /workspace/channels/{id}/files` (multipart ya da `content_base64`, isteğe bağlı `text`):
  `ws_store_upload()` + `ws_message_send()`; boyut sınırı `ws_upload_max_bytes()`. Tartışmanın
  dosyası kanalın klasörüne gider (bugünkü kural).
- `GET /workspace/messages/{id}/file`: dosyayı Bearer jetonuyla, `get_file.php`'deki Çalışma
  Alanı erişim kuralıyla verir. O kural ortak bir fonksiyona alınır (iki yerde kopyası olmaz).
  Görselde `?size=` küçük önizleme. Mesajdaki `file` alanına bu adres eklenir, eski `url` kalır.

**M6 — Kanal tercihleri, anket, tartışma (K, şema yok).**
`POST /workspace/channels/{id}/notify` {level}, `POST /workspace/polls/{id}/vote` {option_ids}
(mesajda anket bilgisi `poll` olarak döner), `POST /workspace/threads/{id}/join|leave`.
Oy ve katılma yalnız cihaz oturumundan.

**M7 — Yerel push (O + Kodpen aracısı; karar 1, 3, 4).**
- Uç: `POST /push/devices` {provider: `fcm` | `apns`, token, bundle_id}, `POST /push/devices/delete`.
  Kayıt cihaz oturumuna bağlı; cihaz kapanınca silinir (web push'taki `auth_selector` kuralı).
- Gönderim: mevcut push kuyruğu (`pg_push_queue_run()`) yerel kaydı görünce aracıya
  (`push.pinegrap.com`) site anahtarıyla imzalı tek istek atar: jeton, sağlayıcı, rozet sayısı.
  Metin yok. iOS'ta `mutable-content` ve uygulamanın bildirim eklentisi metni
  `GET /notifications?unread=true` ile kendisi doldurur; Android'de FCM veri mesajı aynı işi yapar.
- Aracı Kodpen'in ayrı servisidir; bu depoda yalnız istemcisi ve sözleşmesi olur. Karar 3 (b)
  seçilirse aracı yerine sitenin kendi FCM HTTP v1 / APNs anahtarı girilir.

**M8 — Eski listeden kalanlar (dilim başına K).** Toplu istek (batch), silinen kayıt listesi
(tombstone), `sort=`, web tabanlı kabuk için CORS izin listesi (yerel uygulama CORS istemez).
İlk gerçek uygulama ihtiyaç gösterince yapılır.

### A.4 Şema

- M1–M6, M8: yok.
- M7: yerel push kayıtları için yeni tablo (`id`, `user_id`, `device_id`, `provider`, `token`,
  `bundle_id`, `created_at`, `last_ok_at`, `last_error`), açık sürüm 2026.4.10'un boş bir `10.x`
  etiketiyle; etiket yazılırken `includes/migrations/2026.4.10.php`'den seçilir.
- C5 kararı a çıkarsa `ws_messages` için bağlantıyı gösteren bir sütun (C5'te).

### A.5 Güvenlik

- Kişi adına yazma kişinin haklarıyla sınırlı kalır: etkin kapsam = uygulama ∩ kişinin hakları
  (bugünkü kural), panelin hız sınırı aynen uygulanır.
- Dosya indirme ucu tek erişim kuralını kullanır; yanıt `Cache-Control: private, no-store`,
  `X-Content-Type-Options: nosniff`, görsel dışında `attachment`. Özel kanalın dosyası herkese
  açık bir adresle verilmez.
- Push metin taşımaz; aracı yalnız jetonu ve site adresini görür, hiçbir şey saklamaz. Cihaz
  kapanınca ya da "her yerden çık"ta kayıt silinir.
- OAuth'ta PKCE zorunlu (bugün öyle). Uygulama için kendi şeması yerine doğrulanmış `https`
  bağlantısı (iOS Universal Links, Android App Links) önerilir. Jetonlar Keychain / Keystore'da.
- OAuth giriş ekranı sitenin kendisi olduğu için iki adımlı doğrulama, soru (captcha) ve dış hesapla
  giriş ek iş istemez. `/auth/login` yedek olarak kalır, giriş ekranının kilidini paylaşır.

### A.6 Dokümana gidecekler (pinegrap.com, İngilizce)

- **/api:** "Mobile and team apps" bölümü: OAuth ile giriş (PKCE, kaynak `integration.php`),
  `/auth/login` yedeği, eşitleme ucu, gelen kutusu, dosya yükleme / indirme, push kaydı.
- **/workspace-cont:** "Workspace on your phone": PWA ile kullanım, uygulamayla giriş, bildirimler.
- **/security:** cihaz listesi ve kapatma, push'un metin taşımadığı.

---

## B. Claude kanalı

### B.1 Bugün Claude'un Çalışma Alanı'na üç yolu var

| Yol | Nasıl | Kanalı dinler mi | Sınır |
|---|---|---|---|
| Rutin (Faz 1, `includes/workspace/claude.php`) | `@Claude` yazılınca site claude.ai rutinini tetikler; rutin bulutta açılır, kuyruğu REST ile okur, yanıtı yazar | Evet, `@Claude` ile | Günlük çalıştırma sınırı (Pro 5, Max 15, Team/Enterprise 25), yanıt 1–3 dk, her çağrı yeni oturum |
| claude.ai bağlayıcısı (`mcp.php`, OAuth) | Kişi claude.ai'da konuşurken Claude Pinegrap araçlarıyla kanalı okur, mesaj yazar | Hayır; yalnız kişi sorunca | Mesaj bağlantının adıyla "(uygulama)" görünür |
| Uzak ajan (Faz F, #347–#361) | Çalışma Alanı bir MCP sunucusunun ajan aracını `@takmaad` ile çağırır | Evet, takma adla | Claude'un kendisi MCP sunucusu olarak sunulmuyor; bu yol Claude'a gitmiyor |

Eksik olan: Claude'un **sürekli açık** bir oturumla, saniyeler içinde ve aynı konuşmanın
bağlamını koruyarak kanalda yanıt vermesi.

### B.2 Claude Code kanalları (2026-10-14'te belgeden okundu)

- Claude Code'un **kanal (channels)** özelliği: bir MCP sunucusu, açık duran bir Claude Code
  oturumuna dışarıdan mesaj iter; iki yönlüyse Claude yanıtı aynı sunucunun aracıyla geri yollar.
  Telegram, Discord ve iMessage eklentileri bu yolla çalışıyor.
- Sunucu Claude Code ile **aynı makinede** çalışır; Claude Code onu alt süreç olarak başlatır,
  stdio ile konuşur. Sözleşme: yetenek `experimental['claude/channel']`, olay
  `notifications/claude/channel` {`content`, `meta`} (meta anahtarları yalnız harf, rakam ve alt
  çizgi), yanıt sıradan bir MCP aracı (`reply`).
- İzin aktarımı: yetenek `experimental['claude/channel/permission']`; Claude Code
  `notifications/claude/channel/permission_request` {`request_id` (beş harf), `tool_name`,
  `description`, `input_preview`} gönderir, sunucu `notifications/claude/channel/permission`
  {`request_id`, `behavior`: `allow` | `deny`} ile döner. Terminaldeki onay penceresi de açık
  kalır, ilk gelen cevap geçer. Proje güveni ve MCP sunucusu onayı aktarılmaz.
- **Araştırma önizlemesi:** bayraklar ve sözleşme değişebilir. `--channels plugin:<ad>@<pazar>`
  yalnız Anthropic'in listesindeki (`claude-plugins-official`) ya da kurumun
  `allowedChannelPlugins` listesindeki eklentiyi kabul eder. Listede olmayan eklenti
  `--dangerously-load-development-channels` ile, yalnız etkileşimli oturumda ve bir uyarı
  penceresinden sonra yüklenir; `-p` ve Agent SDK'da bu bayrak yok sayılır.
- Team / Enterprise'da kurum sahibi kanalları açmalıdır (`channelsEnabled`); Pro / Max'ta kurum
  denetimi yok. claude.ai girişi ya da Console anahtarı ister; Bedrock / Vertex / Foundry'de yok.
- Olaylar yalnız oturum açıkken gelir. Claude meşgulken gelenler sıraya girer, sonraki turda
  birlikte işlenir. Oturum aboneliğin kullanım sınırından düşer, rutinin günlük sayısı yoktur.
- MCP SDK 2.0 ile çalışan Claude Code, 2026-07-28 protokol sürümünü konuşan bir kanal sunucusunu
  kanal olarak kaydetmiyor; eklenti eski el sıkışmada kalmalı (`MCP_PROTOCOL_NEGOTIATION=legacy`).
- Anthropic'in üçüncü taraf ürünlerde abonelik jetonu kullanımını sınırlaması (Şubat 2026) bu yola
  dokunmaz: Pinegrap abonelik jetonu görmez, kişinin kendi Claude Code'u Pinegrap'a REST ile bağlanır.

### B.3 Ne yeniden kullanılır, ne eksik

**Olduğu gibi kullanılan (Faz 1 kuyruğu kanal eklentisinin tam ihtiyacı):**
- İstek kuyruğu `ws_ai_requests` ve uçları: `GET /workspace/claude/requests`, `…/{id}/claim`,
  `…/{id}/answer` (görev ve kayıt değişikliği taslaklarıyla), `…/{id}/fail`,
  `…/{id}/change-types`, `…/{id}/records/{type}/{record_id}`, `GET /workspace/channels/{id}/context`.
- "Claude" kimliği (`ws_app_sender()`), `<@app:N>` etiketi ve seçici, kanal izni
  (`ws_claude_channel_allowed()`, `claude_access`), 👀 / ✅ işaretleri, bekçi ve 24 saat kuralı,
  Claude'un yanıtına verilen cevabın yine Claude'a gitmesi (`ws_claude_answers_claude()`), onaylı
  değişiklik kartları, `api_request_log`.
- Görsel Sayfa Editörü'nden gelen istekler ve göreve devir aynı kuyruktadır, eklenti onları da alır.
- ETag / 304: eklentinin birkaç saniyede bir kuyruğa bakması ucuzdur.
- `api_apps.last_used_timestamp`: eklentinin son yoklaması, Claude'un çevrimiçi olup olmadığını
  söyler (şema gerekmez).
- `mcp.php` (OAuth): kayıtlara geniş erişim gerekirse Claude Code'a ayrıca bağlayıcı olarak eklenir;
  eklentinin kendisi yalnız kanal araçlarını taşır.
- Faz F'den: "MCP ajanı" rozeti ve konuşmanın sürmesi (#361) fikri. Claude Code oturumu bağlamı
  zaten kendisi tuttuğu için konuşma kimliği saklamak gerekmez.

**Eksik:**
- Bağlantı türü: kanal kipinde site rutini tetiklemez (`ws_claude_dispatch()`), rutin adresi ve
  belirteci istenmez (`ws_claude_missing()`), istek eklenti alana kadar `queued` bekler (C1).
- Kimlerin yazabileceği listesi ve "Claude çevrimdışı" durumu (C1).
- Eklentinin kendisi (C2).
- İzin aktarımı: onay kaydı, yalnız bağlantı sahibine kart, karar ucu (C3).
- Kişi başı oturum (C4) ve bağlantıyla yazan kişinin rozeti (C5).

### B.4 Akış (kanal kipi, site geneli)

```
Kanal: "@Claude bu siparişin iadesini hazırla"
   │
   ├─ Pinegrap: ws_ai_requests (queued), kanalda "Claude'a iletildi". Rutin tetiklenmez.
   │
   └─ Yöneticinin bilgisayarı: claude --channels plugin:pinegrap@kodpen
         eklenti (stdio)  ── birkaç sn'de bir ──>  GET integration.php/workspace/claude/requests  (ETag, 304)
         yeni istek  →  POST …/requests/{id}/claim  (kanalda 👀)
         notifications/claude/channel
            <channel source="pinegrap" request_id="88" channel_id="4" channel="destek" sender="Ayşe">…</channel>
         Claude: context aracı → GET …/channels/4/context ; gerekiyorsa kayıt okur
         Claude: reply aracı   → POST …/requests/88/answer  (yanıt "Claude" adıyla, 👀 → ✅, değişiklik taslağı kartı)
         araç onayı gerekirse  → C3: kart yalnız yöneticide, karar eklentiye döner
```

### B.5 Dilimler

**C1 — Bağlantı türü (O, şema var; karar 5, 7).**
- "Kanallarda Claude" kartında "Bağlantı türü": Rutin (bugünkü) | Claude Code kanalı.
- Kanal kipinde: rutin alanları gizlenir; `ws_claude_dispatch()` tetiklemez; kurulum adımları
  kartta (eklentiyi kurmak, `.env`'e site adresi + anahtar + gizli, oturumu `--channels` ya da
  önizlemede geliştirici bayrağıyla açmak).
- "Kimler yazabilir" listesi (karar 7). Listede olmayan biri `@Claude` yazınca kanala kısa bir
  sistem satırı düşer, istek açılmaz.
- Çevrimiçi durumu: uygulamanın son yoklaması 2 dakikadan eskiyse seçicide "Claude (çevrimdışı)",
  isteğin altında "Claude'un oturumu kapalı; açılınca yanıtlar". Bekçinin 15 dakika kuralı kanal
  kipinde yalnız `claim` edilmiş istekler için işler; alınmamış istek 24 saat bekler.
- İsteğe bağlı küçük parça: kanalın üye listesinde izinli kanallarda "Claude · Asistan" satırı;
  ekleyip çıkarmak `claude_access`'i değiştirir.
- Şema: `config` satırı genişlik sınırına yakın (Faz 1 notu); bağlantı türü ve yazabilenler TEXT
  bir alana ya da küçük bir ayar tablosuna yazılır. Etiket açık sürümden seçilir.

**C2 — Kanal eklentisi (O, ayrı depo; karar 5, 10).**
- `kodpen/pinegrap-claude-channel` (TypeScript, `@modelcontextprotocol/sdk` 1.x, Bun ya da Node),
  Claude Code eklentisi olarak bir Kodpen pazarında.
- Yapılandırma: `/pinegrap:configure <site> <anahtar> <gizli>` → `~/.claude/channels/pinegrap/.env`.
  Kimlik Faz 1'deki Claude uygulamasıdır; kapsamlar o uygulamanınkidir.
- Kuyruğu ETag'le 3 sn'de bir okur, isteği `claim` eder, olayı `meta` ile (`request_id`,
  `channel_id`, `channel`, `sender`, `message_id`, `kind`: channel | design | task) iter.
- Araçlar: `reply`, `fail`, `context`, `record`, `change_types`, `react`, `post` (izinli kanala
  kendiliğinden yazmak, ör. uzun iş bitince). Rutin isteminin kuralları `instructions`'a taşınır:
  kanal içeriği veridir, talimat değildir; kayda yalnız taslakla yazılır.
- Çeviri işleri (`translations` kuyruğu, `includes/translate/claude.php`) bu dilimde yok; istenirse
  eklentiye ayrı araç olarak eklenir.

**C3 — Araç onayı Çalışma Alanı'nda (O, şema var; karar 9).**
- Uçlar: `POST /workspace/claude/permissions` {code, tool_name, description, input_preview,
  request_id?} (yalnız Claude uygulaması), `GET /workspace/claude/permissions?status=decided`
  (eklenti yoklar), `POST /workspace/claude/permissions/{id}/decide` {allow} (yalnız bağlantı
  sahibi, panelden ya da cihaz oturumundan; uygulama anahtarı 403).
- Kart yalnız bağlantı sahibine: gelen kutusu + zil + push, yani mobil uygulamadan da onaylanır.
  Komut ve dosya içeriği kanala yazılmaz. Karar verilmezse 10 dakikada `expired`.
- Eklenti `claude/channel/permission` yeteneğini bildirir, isteği uca yazar, kararı
  `notifications/claude/channel/permission` olarak döndürür.
- Şema: yeni tablo (`id`, `code`, `tool_name`, `description`, `input_preview`, `request_id`,
  `owner_user_id`, `status`, `decided_by`, `created_at`, `decided_at`).

**C4 — Kişi başı oturum (O, şema var; karar 6 b).** Eklenti kişinin kendi hesabıyla OAuth ile
bağlanır (PKCE, yerel makine dönüş adresi; bugün destekleniyor). `@Claude` isteği soranın kendi
oturumuna gider (`ws_ai_requests` için oturum sahibi sütunu); yanıt "Claude · Ayşe'nin oturumu"
olarak görünür; soranın oturumu kapalıysa site geneli oturuma ya da rutine düşer.

**C5 — Bağlantıyla yazan kişinin rozeti (K, şema var; karar 11 a).** OAuth bağlantısıyla (claude.ai,
mobil uygulama, başka araç) yazılan mesaj kişinin adıyla, yanında bağlantının adıyla küçük bir rozet
("Claude ile"). Şema: `ws_messages` için bağlantıyı gösteren sütun (OAuth istemci kimliği).
`ws_app_sender()` ve `refs.php` buna göre genişler.

### B.6 Şema (özet)

C1 ayar alanı ya da küçük ayar tablosu; C3 onay tablosu; C4 `ws_ai_requests` oturum sahibi
sütunu; C5 `ws_messages` bağlantı sütunu. Hepsi açık sürüm 2026.4.10'un ayrı `10.x` adımları,
idempotent yardımcılarla.

### B.7 Güvenlik

- **En büyük risk:** kanal oturumu o bilgisayarda dosya okuyup komut çalıştırabilen bir Claude
  Code'dur. `@Claude` yazan herkes o oturuma metin sokar. Bu yüzden: yazabilenler listesi (karar 7),
  oturumu sırrı olmayan ayrı bir klasörde ya da sanal makinede açmak, varsayılan izin kipi (onaysız
  kip ve `--dangerously-skip-permissions` asla), araç onayı yalnız bağlantı sahibine (C3). Kartta
  ve dokümanda açıkça yazılır.
- **Enjeksiyon:** kanal mesajı `<channel>` gövdesine düz metin olarak girer; `meta`'da yalnız kimlik
  ve ad. Eklentinin `instructions`'ı rutin istemindeki kuralı taşır. Claude'un eli API kapsamlarıyla
  sınırlı, kayda taslak dışında yazamaz, silme ucu yok (Faz 1 kuralları).
- **Gönderen kapısı:** belgenin istediği gönderen denetimi Pinegrap'tadır: istek yalnız ekipten,
  izinli kanalda ve listedeki kişiden açılır. Eklenti yalnız kendi sitesinin HTTPS adresinden okur.
- **Özel kanal:** bugünkü kural sürer, içerik bağlantı sahibinin hesabıyla Anthropic'e gider,
  kanal izni kapalıysa uçlar 403 döner.
- **Kimlik bilgisi:** anahtar ve gizli eklentinin `.env`'inde, kapsamı dar Claude uygulamasında.
  Sızarsa Uygulama Erişimi'nden yenilenir.
- **Önizleme:** sözleşme değişirse eklenti güncellenir; Pinegrap tarafı yalnız REST uçlarıdır,
  etkilenmez.

### B.8 Dokümana gidecekler (pinegrap.com, İngilizce)

- **/workspace-cont:** "Claude in channels" bölümüne "Claude Code channel" alt bölümü: ne zaman
  rutin, ne zaman kanal; kurulum; çevrimdışı durumu; araç onayları.
- **/security:** yerel oturumun riski ve önerilen kurulum (ayrı klasör, izin kipi, yazabilenler).
- **/api:** kanal kipinde kuyruk uçlarının kullanımı ve onay uçları.

---

## Sıra önerisi

1. M1 → M2 → M4 → M3 → M5 → M6 (kararsız; her biri ayrı PR, mobil uygulama başlamadan biter).
2. Kararlar 5, 7, 10 gelince C1 ve C2 birlikte (C1 bu depoda, C2 ayrı depoda); sonra C3.
3. Karar 1, 3, 4 gelince M7 ve aracının sözleşmesi.
4. C4, C5 ve M8 ihtiyaç doğunca.

## Doğrulanamayanlar ve kaynaklar

- Claude Code kanalları araştırma önizlemesi; bayrak adları ve sürüm numaraları 2026-10-14'te
  code.claude.com belgelerinden okundu, yazılırken yeniden doğrulanmalı. Bir bulut oturumunda
  (claude.ai/code) kanalın çalışıp çalışmadığı belgede yok; plan yerel ve sürekli açık bir terminal
  varsayar.
- Bir kişinin aboneliğiyle bütün ekibe yanıt veren köprü, Faz 1'deki site geneli rutinle aynı
  durumdadır; Team / Enterprise'da kurum sahibinin kanalları açması gerekir.
- App Store'un giriş kuralları (dış hesapla giriş sunan uygulamaya ek seçenek şartı) uygulama
  yazılırken Apple'ın güncel yönergesinden doğrulanmalı.
- Kod okuması: `includes/api/devices.php`, `includes/api/schema.php`, `includes/oauth/rules.php`,
  `includes/oauth/server.php`, `includes/push.php`, `includes/workspace/api*.php`,
  `includes/workspace/actions.php` (`ws_sync`, `ws_attach`), `includes/workspace/messages.php`
  (`ws_message_send()`, `ws_app_sender()`), `includes/workspace/claude.php`, `get_file.php`.
- Kaynaklar: Claude Code "Push events into a running session with channels", "Channels reference",
  "Connect Claude Code to tools via MCP" (Push messages with channels).
