# Plan — Tersine OAuth: Pinegrap'ın dış hesaplara bağlanması

**Durum (2026-10-14):** ortak altyapı (§3, A modeli; #282) ve önerilen beş senaryo bitti:
4.1 e-posta Gmail / Microsoft 365 hesabıyla (#294), 4.2 Search Console (#310), 4.3 Microsoft
ve Apple ile giriş (#306; Google girişi `signin.php` akışında, #318), 4.4 yedekler Drive /
OneDrive / Dropbox'a (#312), 4.6 Çalışma Alanı'nda MCP istemcisi (`docs/_plan_oauth.md`
Faz F). 4.14'ün bu plandaki payı da bitti: Paraşüt jetonu şifreli depoda; UPS (#302), FedEx
(#308) ve USPS (#314) REST API'leri OAuth `client_credentials` ile. Ayrıntı
`docs/degisiklikler.md`, kurallar `.claude/skills/pinegrap-dis-api/SKILL.md` → "Bağlı
hesaplar". Biten bölümler (envanter, altyapının ayrıntısı, biten senaryolar, sıra)
çıkarıldı; tam metin: `git show d9fec3a:docs/_plan_tersine_oauth.md`.

**Kalan hiçbir madde Erdal seçmeden başlamaz**; seçilen her madde kendi thread'i ve PR'ıyla
yapılır.

## Kalanlar

| Madde | Ne | Efor | Not |
|---|---|---|---|
| 4.6 uzak ajan | MCP sunucusunun kendisi ajan (seviye C) | dilim başına K | dilim 1 bitti (#347); 2-5 aşağıda |
| 4.5 | Çalışma Alanı takvim eşitleme (Google Takvim / Outlook) | Adım 0 K, OAuth O–B | Adım 0 OAuth'suz: gizli iCal adresi |
| 4.7 | Notlar: Notion ve OneNote | O (Notion MCP üzerinden K) | 4.6 hazır, Notion MCP takma adla bağlanabilir |
| 4.8 | Medyaya Drive / OneDrive / Dropbox'tan dosya almak | K–O | kişisel bağlantı |
| 4.9 | Meta: katalog, Pixel + Conversions API, Instagram, WhatsApp | B | yalnız B modeli; karar 4 |
| 4.10 | Google Merchant Center | O | ürün akışı adresi 4.9 ile ortak |
| 4.11 | Google Analytics 4 | O | talep gelince |
| 4.12 | Google İşletme Profili | O | talep gelince |
| 4.13 | Sosyal paylaşım (Facebook Sayfası, Instagram, LinkedIn) | O + süreç | talep gelince |
| 4.15 | Yapay zekâ hesapları (OpenRouter PKCE) | K | önerilmez |
| B modeli | `connect.pinegrap.com` bağlantı aracısı, Kodpen adına Google doğrulaması | — | karar 3; §3.1 |

---

## 3. Ortak altyapı (yalnız 3.1)

Altyapının kendisi (sağlayıcı kaydı, tablolar, uçlar, jeton yaşam döngüsü, güvenlik) kodda
ve `pinegrap-dis-api` skill'inde. B modeli için iki modelin karşılaştırması:

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

---

## 4. Kalan senaryolar

Efor: K küçük, O orta, B büyük. "Şart" sağlayıcının istediğidir (2026-10 itibarıyla; §7).

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

### 4.15 Yapay zekâ hesapları (Claude, OpenAI) — önerilmez

- Anthropic, Free/Pro/Max aboneliklerinin OAuth jetonlarını yalnız Claude Code ve claude.ai için
  sınırlıyor (Şubat 2026); üçüncü taraf ürün API anahtarı kullanır. OpenAI'da kişi hesabıyla API
  OAuth'u yok. Pinegrap AI lisans anahtarıyla, Claude rutini tetikleme jetonuyla zaten çalışıyor.
- İstenirse tek seçenek: OpenRouter'ın PKCE akışı kişinin kendi anahtarını üretir; OpenAI uyumlu
  olduğu için `includes/workspace/ai.php`'ye "kendi modelini bağla" olarak oturur. Efor K.

### 4.6 Uzak ajan (seviye C; Faz F'nin son parçası)

- **Ne:** sunucunun kendisi bir ajan (başka bir Pinegrap sitesi, bir şirketin asistanı): mesajı
  alır, yanıtlar. Pinegrap AI'a gerek yok; zekâ karşı tarafta. Yönetici "mesajı alan aracı"
  seçer, takma ada yazılan mesaj o araca olduğu gibi gider (`notes/mcp-arastirma.md` §3).
- **Neden dilimli:** tam hâli kanal, uzun süren iş (MCP `tasks` eki), görev ve not, yazan araç
  onayı ve konuşmanın sürmesini kapsıyor; tek küçük PR'a sığmıyor. Her dilim uçtan uca çalışır,
  kendi PR'ı.
- **Kurallar (her dilimde):** araç yalnız okuyan kuralla açıksa doğrudan çalışır, yazan araç
  onay kartıyla (dilim 4); OAuth'lu sunucuda soranın kendi hesabı, yoksa sitenin; yalnız
  Pinegrap AI'ın sorulabildiği kanalda (`ws_ai_channel_allowed()`); ajanın yanıtı başka bir
  hizmetin metni: kilitli, etiketleri kimseyi anmaz, hiçbir model onu talimat olarak okumaz.

| Dilim | Ne | Durum |
|---|---|---|
| 1 | Kanalda `@takmaad` ve ajanın yanıtına cevap → seçilen araca tek metin argümanında (cevapta cevaplanan mesaj alıntılı) → yanıt mesajın altına, soranın adıyla kilitli. İstek `ws_ai_requests` `agent = 'mcp'`, ekranlar (`ws_mcp_agent_kick`) ve saatlik iş taşır. Şema 10.89: `ws_mcp_servers.agent_tool`, `ws_mcp_calls.via` += `agent`. | bitti (#347) |
| 2 | Uzun süren iş: `tools/call` MCP `tasks` ekiyle (2026-07-28 `resultType` ≠ `complete` bugün hata); görev kimliği istekte saklanır, ekranlar ve saatlik iş `tasks/get` ile yoklar, bitince `tasks/result`; satırda "@x üzerinde çalışıyor", iptal `tasks/cancel`. `pg_mcp_client_send()` görev yanıtını hata saymaz, `ws_ai_requests`'e görev kimliği sütunu (şema). | sırada |
| 3 | Görevde uzak ajan: göreve devir (`mcp:<id>`) ve görev notunda `@takmaad`, ajan kipindeki sunucuda Pinegrap AI yerine doğrudan araca (görevin başlığı, açıklaması, talimat), yanıt görevin Asistan bölümüne (`ws_task_delegation_answer()`). Bugün bu ikisi ajan kipinde de Pinegrap AI ile, ajan aracı sıradan bir araç olarak çalışır; Pinegrap AI bağlı değilse takma ad görevde seçilemez. | sırada |
| 4 | Yazan ajan aracı: OAuth'lu sunucuda `approve` kurallı araç seçilebilir; her mesaj önce `mcp_call` onay kartında bekler, soranın kendi hesabıyla gider (`ws_mcp_calls_propose()` / `ws_mcp_call_run()`), yanıt karar mesajı yerine ajanın yanıtı olarak düşer. | sırada |
| 5 | Konuşmanın sürmesi ve kimlik: sunucu yapısal sonuçta bir konuşma kimliği dönüyorsa saklanıp sonraki cevapta geri gönderilir (bugün yalnız cevaplanan mesaj alıntılanıyor); yanıtın takma adın kendi üye kimliğiyle görünmesi (`ws_app_sender()` / `refs.php` genelleştirmesi) — bugün soranın adıyla, başında "@x, `araç` aracıyla yanıtladı". | isteğe bağlı |

---

## 6. Erdal'ın kararları (açık olanlar)

Cevaplananlar: 1 (ilk beş öneri yapıldı), 2 (önce A modeli), 5 (`shipping.php`'nin
UPS / FedEx / USPS kısmı REST API'lerle yeniden yazıldı).

3. Kodpen adına Google doğrulama başvurusu ve `connect.pinegrap.com` aracısı — evet / hayır.
4. Kodpen adına Meta işletme doğrulaması ve App Review (4.9, 4.13) — evet / hayır.

---

## 7. Doğrulanamayanlar ve kaynaklar

- Sağlayıcı şartları 2026-10-13'te kamuya açık belgelerden ve ikincil kaynaklardan okundu; başvuru
  anında sağlayıcının kendi konsolunda yeniden doğrulanmalı. Özellikle: `webmasters` ve
  `webmasters.readonly` kapsamlarının hassas sınıfı (Google Cloud Console › Data Access),
  Microsoft'un tarihleri (Message Center MC786329, 2026-01-27 güncellemesi), FedEx'in müşteri bazlı
  geçiş tarihleri.
- Kalan senaryoların canlı denemesi dev.pinegrap.com'da, Erdal'ın hesabıyla yapılır;
  bulut oturumu dev.pinegrap.com'a ulaşamaz.
- Kaynaklar: Microsoft MC786329 (SMTP AUTH Basic), Google "Sensitive scope verification" ve
  Gmail kapsam sınıfları, Google Shopping Content API "Deprecation and sunset", Meta Instagram
  Platform "App Review" ve Commerce Platform "Onboarding (MBE)", USPS "Web Tools APIs Retired
  January 25, 2026", FedEx "Migrate to FedEx APIs", Claude Code "Legal and compliance".
