# Pinegrap ERP — Plan (v3)

**Durum (2026-10-14):** Faz -1, 0, 1, 2, 4 ve 5 bitti (2026.4.4; ayrıntı
`docs/degisiklikler.md`, sözleşme `.claude/skills/pinegrap-erp/SKILL.md`). Devamı
`docs/_plan_erp_ozellikler.md` (Faz 6–9). Biten bölümler bu dosyadan çıkarıldı; planın
tam metni git geçmişinde: `git show d9fec3a:docs/_plan_erp.md`.

## Kalanlar

Hepsi Faz 3'ün (e-belge) ya da dış onayın kalanı. 2026-10-13 kapsam kararıyla e-Belge
sağlayıcıları Erdal'ın yerel işidir; bulut thread'leri `includes/erp/edoc/`'a dokunmaz.

1. Paraşüt sürücüsünün gönderim yolu (`includes/erp/edoc/parasut.php`): API kimliği
   destekten gelmedi. Tasarım aşağıda §7 ve "Faz 3".
2. `erp_edoc_queue` işleyicisi (tablo var, işleyici yok) ve ona bağlı e-belge hata
   bildirimi (Faz 5'in kalanı).
3. Logo İşbaşı canlı anahtarı (Erdal girer). Test ortamında zincir uçtan uca çalışıyor.
4. Logo'dan kod listeleri: iade ve istisna kodları, internet satışında
   `eArchivePaymentType`.
5. e-İrsaliye gönderimi, §7.6 koşuluyla (dahili irsaliye hazır).
6. Mali müşavir onayı: VUK 509 alanları, iade yolları (§6.5), mevzuat notu (§11);
   sorular `docs/erp_malimusavir_sorulacaklar.md`.

---

## 6. İş akışları (yalnız 6.5)

### 6.5 İptal ve iade — üç ayrı yol
Mükellef olmayan tüketici fatura kesemez; iade faturasını alıcı keser.

| Durum | Doğru yol |
|---|---|
| Fatura kesilmemiş | Taslak silinir, cari hareket yoksa iş yok |
| Kesilmiş, e-belge gönderilmemiş | Fatura iptal (`cancelled`), ters cari kayıt |
| **Mükellef alıcı** (e-fatura) | Alıcı iade faturası keser → Pinegrap'e **alış faturası** olarak kaydedilir |
| **Mükellef olmayan alıcı, 8 gün içinde** | e-Arşiv **iptali** (GİB'e bildirim) — kuyrukta `cancel` |
| **Mükellef olmayan, 8 gün sonrası** | Tüketicinin doldurup imzaladığı **iade bölümü** → `doc_type='return'` |

`earchive_cancel_deadline` alanı ve panelde kalan gün sayacı bu ayrımı operatöre gösterir.

**[v3] Kanca noktası:** `process_order_cancellation()` — `includes/fn/ecommerce.php:6187`, imza:
```php
process_order_cancellation($order_id, $reason = '', $is_admin = false, $user_id = 0, $attempt_refund = null)
```
Fonksiyonda genel bir hook sistemi yok; tek duyuru noktası `api_webhook_enqueue('order.cancelled', ...)` (`:6374`). ERP kancası **oraya bitişik** yazılır, aynı yerde, aynı sırada. Çağıranlar: `cancel_order.php:89`, `edit_orders.php:582`, `view_order.php:2653`, `includes/api/resources/orders.php:338`, `includes/api/outbound/orders.php:291`.

---

## 7. Paraşüt entegrasyonu (Faz 3)

### 7.1 [v3] Kimlik ve saklama
Paraşüt API v4 OAuth2 **password grant** ile belgelenmiş ve **mevcut kod da onu kullanıyor** (`parasut.php:137-160`, `client_id:client_secret` Basic Auth + kullanıcı e-postası/parolası + `company_id`).

**Önce şu kontrol edilecek:** `authorization_code` grant destekleniyorsa parolayı **hiç istememek** mümkün. Faz 3'ün ilk işi bunu teyit etmek.

**Password grant'e mecbur kalınırsa:** `refresh_token` saklanır ve **gerçekten kullanılır** (bugün saklanıp kullanılmıyor, `parasut.php:178`). Parola ilk bağlantıdan sonra silinebilir hâle gelir.

**[v3] Şifreleme deseni — v2'nin referansı yanlıştı.** `api_settings.php` gizli anahtarı **şifrelemiyor**, `hash_hmac` ile geri döndürülemez hash'liyor (`includes/api/keys.php:83-86`) — kimlik bilgisi için kullanılamaz. Doğru desenler:

| Desen | Yer | Ne zaman |
|---|---|---|
| Çift sütun: `<alan>` + `<alan>_iv` | `encrypt_string_with_iv()` / `decode_ssl_keys()` — `includes/fn/auth.php:4540` / `:4525`; örnek `oauth_google_client_secret` + `oauth_google_secret_iv` (`2026.4.4.php:1004-1006`) | Tek alan |
| Tek sütun `"cipher:iv"`, JSON blob | `mp_credentials_encode()` / `mp_credentials_decode()` — `includes/api/outbound/connectors/base.php:203` / `:216` | Birden çok alan, şema değişmeden büyüsün isteniyorsa |

Her ikisi de anahtarı `ENCRYPTION_KEY`'den türetiyor: `substr(hash('sha256', ENCRYPTION_KEY, true), 0, 32)`, AES-256-CBC. Sabit `data/config.php`'de.

**Öneri:** ikinci desen (`mp_credentials_encode`) — Paraşüt'ün `client_secret` + `password` + ileride `refresh_token`'ı tek `parasut_credentials_enc` sütununda taşır, her yeni alan için migration gerekmez. Panelde geri render edilmez, yalnız "•••• son 4".

### 7.2 [v3] İstek katmanı — `api_http_*` kullanılacak
`includes/api/outbound/http.php` **var ve tam istendiği gibi** çalışıyor:
```php
api_http_request($method, $url, $options)  // :131 → array('ok','status','body','error','duration_ms')
api_http_check_url($url)                   // :49  → şema beyaz listesi + DNS çözümü
api_http_should_retry($outcome)            // :237 → 0/408/429/5xx evet; 400/404/410 hayır
```
SSRF: `http`/`https` beyaz listesi, `FILTER_FLAG_NO_PRIV_RANGE|NO_RES_RANGE` (bulut metadata dâhil), **`CURLOPT_RESOLVE` ile IP pinning** (DNS rebinding kapalı), `FOLLOWLOCATION = false`, `SSL_VERIFYPEER=1` + `VERIFYHOST=2`.

**Dikkat — varsayılanlar Paraşüt'e uymuyor:**
- `timeout` varsayılan **10 sn**, `CONNECTTIMEOUT` sabit 5 sn. Paraşüt e-belge çevrimi için **30 sn** geçilir (mevcut `parasut.php` de 30 kullanıyor).
- `max_body` varsayılan **1000 bayt** — hata mesajı için yeterli, JSON yanıt için değil. **`max_body => 0`** (kesme yok) geçilir, yoksa fatura yanıtı sessizce budanır.
- Her metot serbest (`CURLOPT_CUSTOMREQUEST`); `api_http_post()` yalnız POST sarmalayıcısı.

- Token süresi dolmuşsa tek yenileme, yarışa karşı `GET_LOCK`.
- 401 → bir kez yenile, yine 401 → `parasut_last_error`, kuyruk `failed`, panelde kırmızı rozet.
- **Sayfalama:** Paraşüt `page[size]`'ı sessizce **25**'e kırpıyor. Katman 25'e sabitler, `meta.total_pages` üzerinden döner. *(Mevcut kod zaten 25 kullanıyor — uyumlu.)*
- **Kendi hız sınırımız:** Paraşüt'ün **belgelenmiş rate limit'i yok** — duyurulmadığı anlamına geliyor, olmadığı değil. İstemcide muhafazakâr sınır (örn. 5 istek/sn), 429 yine de geri çekilmeyle.

### 7.3 e-Fatura mı e-Arşiv mi — kesim anında sorulur
Alıcının vergi/kimlik numarası GİB'de kayıtlıysa **e-fatura**, değilse **e-arşiv**. `e_invoice_inboxes` ucu cevabı veriyor. **Fonksiyon zaten var** (`parasut_check_einvoice_address()`, `parasut.php:958`) ama fatura akışında çağrılmıyor — bugün karar kullanıcının bastığı butondan geliyor. Faz -1'de bağlanır.

1. **"TCKN → doğrudan e-arşiv" yanlış.** Şahıs işletmeleri TCKN ile e-fatura mükellefi olabiliyor; `is_person=1` ile "mükellef değil" aynı şey değil. Sorgu **TCKN için de** yapılır.
2. **Uzun TTL yanlış yöne hata yapar.** Önbellek "mükellef değil" derken kayıt olmuş olabilir; mükellefe e-arşiv kesmek **geçersiz belge** üretir. Artık: **kesim anında taze sorgu**; `einvoice_*` alanları yalnız panel gösterimi için önbellek (≤24 sa). *(Mevcut kodda önbellek hiç yok — her çağrı canlı. Bu yönüyle doğru davranıyor.)*

Sorgu başarısızsa belge **kesilmez**, kuyrukta bekler.
**Birden fazla kutu dönebilir** — `einvoice_aliases`'a hepsi yazılır; varsayılan ilk kayıt, operatör cari kartından değiştirebilir; seçilen `einvoice_alias` belgede `to` alanı olarak gider.

### 7.4 Senaryo — `basic` / `commercial`
`e_invoices` create gövdesi `scenario` (temel/ticari) ve `to` (GİB etiketi) istiyor. **Mevcut kod `scenario => 'commercial'` sabit gönderiyor ve `to` hiç göndermiyor** (`parasut.php:641`) — ikisi de düzeltilir.

**Ret yalnız ticari senaryoda mümkündür**; temel faturada alıcı reddedemez. `erp_invoices.edoc_scenario` + mağaza varsayılanı `config.erp_einvoice_scenario` (B2C ağırlıklı mağaza için `basic`). `rejected` durumu yalnız `commercial` belgelerde beklenir.

### 7.5 [v3] Kuyruk ve asenkron sonuç — merdiven tick'e göre

Paraşüt'te e-belge çevrimi asenkron: istek `trackable_job` döndürüyor ve **job ID'si yalnız 15 dakika geçerli**. *(Mevcut kodda `trackable_job` kelimesi hiç geçmiyor — Faz -1 P1.)*

**[v3] `job.php` gerçek cron'dur, ziyaretçi trafiği değil.** `includes/fn/cron.php:22-30`: cron kayıtları yazılımın dışında yaşar (crontab / Windows Task Scheduler / hosting paneli). Ayar ekranı komutu basıyor (`includes/settings/prep.php:1816,1828`). Yani:

> **Tick sıklığı = operatörün crontab'ı.** Mevcut inline işlerin aralığı 60 sn olduğuna göre dakikalık tick bekleniyor, ama 5 dakikalık kurulum da normaldir. v2'nin "10sn, 30sn, 60sn" merdiveni **saniye cinsinden yazılamaz** — iki tick arasında zaten dakikalar var.

Merdiven **tick sayısıyla** ifade edilir:
```
create     → contacts upsert + sales_invoices create   → parasut_invoice_id
convert    → e_invoices | e_archives create            → parasut_job_id
             parasut_job_expires_at = now + 15 dk
poll       → trackable_jobs/{id}    HER TICK, pencere dolana kadar (en çok 15 dk)
             1 dk'lık cron'da ~15 deneme, 5 dk'lıkta ~3 — ikisi de yeterli
reconcile  → GET e_invoices/{id} | e_archives/{id}     UZUN: 5dk, 30dk, 2sa, 6sa, 24sa
             (pencere dolduğunda; belgenin kendisinden durum + GİB numarası)
pay        → sales_invoices/{id}/payments              UZUN merdiven
cancel     → sales_invoices/{id}/cancel                UZUN merdiven
```
6 denemeden sonra `abandoned` + panelde uyarı. **`reconcile` garanti, `poll` hızlandırma** — 5 dakikalık cron'da pencere kaçarsa `reconcile` yine de sonucu getirir.

**[v3] Sevk şekli — `inline` sözleşmesi (doğrulandı):**
`includes/fn/cron.php:121-250` kataloğuna `erp_edoc_job` eklenir, `'inline' => true` ile. Sözleşme:
1. İş **rotasyondan çıkar** — `pg_cron_dispatch_next()` atlar (`cron.php:585-590`), yoksa kazandığı tick'te iki kez koşar.
2. `job.php` her tick'te **doğrudan** çağırır, sıra kilidi almadan (`job.php:333-356` deseni).
3. `pg_cron_job_is_enabled('erp_edoc_job')` yine geçerli — operatör Ayarlar → Zamanlanmış Görevler'den seçmezse çalışmaz (`cron.php:544-547`).
4. `pg_cron_ran('erp_edoc_job')` çağrısını **job.php yapar**.
5. Bağımsız betik olarak da kalır (`erp_edoc_job.php`) — operatör ayrı crontab satırı verebilir.

Bütçe: **10 sn** (push ile aynı; webhook 15 sn). Kuyruk boşken tek indeksli okuma.

**Gerekçe:** rotasyon tick başına tek iş çalıştırıp site geneli kilit tutuyor — kilit `config.job_dispatch_lock_until` üzerinde atomik koşullu UPDATE, **TTL varsayılan 3600 sn** (`cron.php:648-651`). Yedekleme o kilidi bir saate kadar tutabilir. Müşteri ödeme yaptıktan bir saat sonra gelen fatura, webhook'un geç gelmesinden çok daha fazla şikâyet üretir.

> **[v3] Not:** Site geneli kilit `GET_LOCK` değil. Token yenileme yarışı için `GET_LOCK` kullanmak yine de doğru — o ayrı bir kilit, iş kilidiyle karışmaz.

### 7.6 e-İrsaliye — Faz 4 ön koşulu
Paraşüt API v4'te `shipment_documents` yalnız `index/create/show/edit/delete` sunuyor; **e-belgeye çevirme ucu bulunamadı**. Mevcut `parasut_create_shipment()` de yalnız stok irsaliyesi yaratıyor, GİB'e giden bir e-İrsaliye değil — yani kod bu tespiti doğruluyor.

**Faz 4 bu soru cevaplanmadan başlamaz.** Erişilemiyorsa Faz 4 **yalnız dahili irsaliye** olarak daraltılır ve `erp_waybills.edoc_*` sütunları **eklenmez**.

### 7.7 [v3] Paraşüt'ün kendi hesabı — kabul kriteri bunun sınavı
Paraşüt `sales_invoice_details`'ta `unit_price`, `quantity`, `vat_rate`, `discount_*` alıyor ve **KDV'yi kendisi hesaplıyor**; tutarı dışarıdan dayatmanın yolu yok. Bizim hesabımız satır toplamı tabanına geçmezse (§5.9 B) sapma kaçınılmaz.

Bu yüzden Faz 3'ün sert kabul kriteri: **Paraşüt'e gönderilen ve Paraşüt'ten dönen `gross_total` birebir eşit.** Eşit değilse belge kesilmez, kuyruk `failed`, operatör uyarılır. Sessiz kuruş farkı yok.

Kuruş → TL çevrimi tek yerde: `includes/erp/parasut/map.php`, `$kurus / 100` ve 2 hane. *(Mevcut kod bunu üç yerde ayrı ayrı yapıyor: `parasut.php:247, 554, 591`.)*

### 7.8 Paraşüt → Pinegrap (gelen webhook) — gövdeye güvenilmez
`parasut_webhook.php` (kökte, tek URL ucu), URL'de tahmin edilemez belirteç (`config.parasut_webhook_token`).

**Paraşüt'ün belgelenmiş HMAC imzası bulunamadı.** İmza yoksa belirteci bilen herkes e-belge durumu yazabilir. Bu yüzden webhook gövdesi **hiçbir zaman veri kaynağı değildir**: gelen istek yalnız *"şu belgeyi yeniden çek"* tetikleyicisidir, kuyruğa `reconcile` düşer, gerçek durum **kendi kimliğimizle** okunur.

Webhook kurulamazsa `reconcile` zaten aynı bilgiyi getiriyor — hızlandırıcı, bağımlılık değil.
`waf.php` hassas betik listesine ve `waf_is_api_request()`'e tanıtılır.

---

## 8.8 [v3] Cari bilgisinin dış kaynaktan çekilmesi (Faz 3 kalemi)

**Soru:** cari kartı (ünvan, vergi dairesi, adres) bir kaynaktan otomatik
doldurulabilir/güncellenebilir mi?

**Kaynak envanteri:**

| Kaynak | Verdiği | Durum |
|---|---|---|
| **GİB e-Fatura mükellef listesi** — Paraşüt `e_invoice_inboxes` | Ünvan, GİB etiketi, mükellef mi | **Kullanılabilir.** Uç Faz -1'de zaten bağlandı, şu an yalnız e-fatura/e-arşiv kararında kullanılıyor |
| GİB Vergi Levhası Sorgulama (`sorgu.gib.gov.tr`) | Ünvan, vergi dairesi, faaliyet kodu | CAPTCHA var, resmî API yok — kazıma sözleşmeye aykırı, **kapsam dışı** |
| MERSİS / Ticaret Sicil Gazetesi | Resmî ünvan, adres | Açık API yok |
| Entegratör VKN sorgulama servisleri | Ünvan + adres + vergi dairesi | Ücretli abonelik, ayrı karar |

**Sonuç:** ünvan ve mükellefiyet otomatik gelebilir; **adres ve vergi dairesi
güvenilir biçimde gelmez** — onlar müşteriden veya sipariş adresinden alınır.

**Tasarım (Faz 3'te, Paraşüt kimlik bilgisi geldiğinde):**

1. Cari kartında VKN yanına **"Sorgula"** düğmesi → `parasut_check_einvoice_address()` →
   dönen ünvan ve GİB etiketi kartı doldurur.
2. `erp_account_for_contact()` içinde otomatik: kişi `contacts`'ta "Ahmet Yılmaz"
   diye kayıtlı olabilir ama fatura "AHMET YILMAZ OTOMOTİV LTD. ŞTİ."ye kesilmeli.
   VKN varsa ünvan sorgudan düzeltilir.
3. Tazeleme: mükellefiyet durumu değişir (mükellef olmayan sonradan olur).
   §7.3'teki kural geçerli — **kesim anında taze sorgu**, karttaki `einvoice_*`
   alanları yalnız gösterim için ≤24 sa önbellek. Buna gecelik toplu tazeleme
   işi eklenebilir.

**Ön koşul ve risk:** `e_invoice_inboxes` yanıtının **ünvan alanı canlı veriyle
doğrulanmadı** — bu kurulumda entegrasyon hiç çalıştırılmadı ve elde Paraşüt
API kimlik bilgisi **yok**. Kimlik bilgisi geldiğinde ilk iş bir VKN sorgulayıp
yanıtın tam şeklini görmek olmalı. Ünvan dönmüyorsa 1. ve 2. maddeler düşer,
geriye yalnız mükellefiyet kontrolü kalır (ki o zaten çalışıyor).

---

## 10. Fazlar (yalnız Faz 3)

### Faz 3 — e-belge sürücüleri (Paraşüt devri + Logo İşbaşı) — **yön değişikliği 2026-09-20**

Erdal'ın kararı (2026-09-20 gece): e-belge katmanı tek sağlayıcıya yazılmaz.
`includes/erp/edoc/registry.php` sürücü sözleşmesini tanımlar
(`erp_edoc_<kod>_info/fields/ping/check_taxpayer/send_invoice/poll/cancel_invoice/fetch_document/send_waybill`),
her sağlayıcı bir dosya (`parasut.php`, `isbasi.php`; ileride NES, Turkcell
e-Şirket vb.), mağaza sahibi Site Ayarları → E-Ticaret → **E-Fatura**
kartının **e-Belge Sağlayıcısı (ERP)** bloğunda radyo ile seçer (ilk hâli
ERP Ayarları'ndaydı, aynı gün taşındı); kimlik bilgileri `erp_edoc_providers` tablosunda
AES ile saklanır (Paraşüt kendi kartındaki mevcut kimlikleri okur). Belgeler
`edoc_provider` / `edoc_external_id` ile hangi sağlayıcıdan geçtiğini taşır
(4.61). Modülün geri kalanı yalnız `erp_edoc_call()` / `erp_edoc_supports()`
görür. Aşağıdaki Paraşüt maddeleri **Paraşüt sürücüsünün** işi; İşbaşı
sürücüsü **2026-09-21'de belgeden yazıldı** (`send_invoice`, `poll`,
`fetch_document`, `check_taxpayer`; notlar `docs/_isbasi_api_notlari.md`),
fatura ekranında "e-Belge" kartı var; gerçek anahtarla ilk test bekleniyor.
İşbaşı sürücüsü aynı sözleşmeyi İşbaşı REST API'siyle doldurur (API anahtarı Logo
desteğinden, kullanıcı adı + parola ile; belgeler İşbaşı hesabıyla giriş
ister — bir test hesabı gerekir).

**Gelen e-faturalar (2026-09-23, 4.67):** sözleşmeye `inbox` / `inbox_document`;
İşbaşı'da `myInvoicesList` + `DocumentUblDatawithuuid`. Liste `erp_inbox.php`,
inceleme `erp_inbox_document.php`; alma onaylı ve **taslak** alış faturası yazar,
tedarikçi carisi yoksa UBL'den açılır. Tevkifat, KDV dışı vergi ve belge geneli
indirim reddedilir (ERP tutamıyor). Ayrıntı `docs/degisiklikler.md`.

**Cari eşitleme (2026-09-23, 4.69):** sözleşmeye `accounts` / `account` / `account_save`,
yetenek `accounts`; İşbaşı'da `firms/firms`, `GET firms/{id}`, `PUT firms` (önce oku, yalnız
kendi alanlarını değiştir). Kartlar `erp_edoc_accounts`'ta, bağ orada; vergi numarası tek
cariyle eşleşen kart okumada bağlanır, farklar alan alan onayla eşitlenir, kart ERP'ye alınır
ya da cari sağlayıcıya gönderilir. Bakiye aktarılmaz. Bağlı carinin faturası kartın koduyla
gider; faturanın durumu sorulunca İşbaşı'nın kullandığı kart bağlanır. Ekranlar
`erp_account_sync.php`, `erp_account_sync_item.php`. Ayrıntı `docs/degisiklikler.md`.

**Ön koşullar (Paraşüt sürücüsü):**
1. **API kimlik bilgisi** — `client_id`/`client_secret` self-servis değil, destek üzerinden. *(Mevcut kurulumda alan dolu mu, kontrol edilecek.)*
2. **`authorization_code` grant desteği teyidi** (§7.1)
3. **Sandbox durumu** — `parasut_use_sandbox` işlevsiz (§1.2 P2). Resmî sandbox yoksa testler **canlıda, gerçek GİB'e giden belgelerle**: yazılı iptal prosedürü + düşük tutarlı, tek kalemli, **kendi VKN'mize** kesilen test faturaları.
4. **Faz -1 tamamlanmış olmalı** — devir onarılmış koddan yapılır.

İş: `includes/erp/edoc/parasut.php` sürücüsüne devir (registry sözleşmesi), `api_http_*` üzerinden istemci, bağlantı ekranı, contacts upsert, mükellef sorgusu, kuyruk (poll + reconcile), e-fatura/e-arşiv, `pay()`, gelen webhook, günlük, widget kontrolü, `parasut.php` fatura/irsaliye yolunun kaldırılması.
**Kabul:**
- e-fatura mükellefi VKN'ye e-fatura, mükellef olmayana e-arşiv; **TCKN'li mükellef doğru şekilde e-fatura alıyor**
- GİB numarası geri geliyor — hem `poll` (15 dk içinde) hem `reconcile` (pencere kaçtığında); **5 dakikalık cron kurulumunda da**
- **Paraşüt'e gönderilen ve dönen `gross_total` birebir eşit** (§7.7)
- Paraşüt kapalıyken panel tam çalışıyor; açıldığında kuyruk kendiliğinden boşalıyor
- Token yenilemesi eş zamanlı iki istekte **tek kez** koşuyor
- Webhook gövdesi kurcalanmış istekle geldiğinde **hiçbir alan değişmiyor**
- **[v3]** `includes/fn/parasut.php`'de fatura/irsaliye yaratan kod kalmadı; `view_parasut_inbox.php` çalışmaya devam ediyor

---

## 11. [v3] Riskler

| Risk | Etki | Önlem |
|---|---|---|
| **Canlı schema probe kodu** (`parasut.php:831-903`) | Paraşüt'te **mükerrer irsaliye**, geri alınamaz | Faz -1 P0: tamamı silinir |
| **`vat_rate = 0`** | Kesilen her belge KDV'siz | Faz -1 P0 |
| **Düz metin Paraşüt parolası** | Kimlik bilgisi sızıntısı | Faz -1 P0, `mp_credentials_encode` deseni |
| `order_items.tax` birim vergi | Yanlış fatura tutarı (3 kez çıktı) | Fatura sütunu `tax_total`; köprüde tek fonksiyon, birim testi |
| **Başlık kalemleri faturaya girmiyor** | Fatura toplamı ≠ sipariş toplamı | §6.3 dağıtım kuralı, Faz 2 kabul testi |
| **VUK 509'un üç alanı sistemde yok** | e-Arşiv belgesi eksik | §6.4 — üç yeni sütun Faz 2 iş kalemi |
| **Yuvarlama tabanı çatışması** | "Kuruşu kuruşuna eşit" sağlanamaz | §5.9 — önce ölç, sonra A/B seç |
| `get_tables()` atlanması | Yeniden kurulumda sessizce eski şema | §5.11 + Faz 0 kabul testi |
| **System status cache `v` bump'ı unutulur** | Yeni kontrol 10 dk görünmez, "çalışmıyor" sanılır | §8.4 madde 6 |
| **Sandbox yok / işlevsiz anahtar** | Geri alınamaz GİB kaydı | Faz 3 ön koşulu: yazılı iptal prosedürü, kendi VKN'mize düşük tutarlı test |
| API kimlik bilgisi self-servis değil | Faz 3 belirsiz süre bekler | Başvuru **Faz 1 başlarken** |
| `trackable_job` 15 dk ömürlü | Belge sonsuza dek "gönderiliyor" | Çift merdiven: `poll` + `reconcile` (§7.5) |
| **5 dakikalık cron'da poll penceresi kaçar** | Sonuç gecikir | `reconcile` garanti yol; `poll` yalnız hızlandırma |
| Mükellefiyet önbelleği | Mükellefe e-arşiv → geçersiz belge | Kesim anında taze sorgu |
| İstisna/tevkifat kodu yokluğu | Belge GİB'den döner | Satırda kod alanları, ürün kartında varsayılan |
| Paraşüt password grant | Mağaza sahibinin parolası | Önce `authorization_code`; olmazsa refresh_token şifreli |
| Webhook imzası yok | Sahte durum yazımı | Gövdeye güvenilmez, yalnız `reconcile` tetikleyicisi |
| **`api_http_request` max_body 1000 bayt** | Paraşüt yanıtı sessizce budanır | `max_body => 0`, `timeout => 30` açıkça geçilir |
| Site geneli iş kilidi (TTL 3600) | Fatura bir saat geç gider | Kuyruk `'inline' => true` |
| Bakiye önbelleğinin defterden ayrışması | Yanlış bakiye | Tek yazım fonksiyonu + gecelik mutabakat + widget kontrolü |
| **MyISAM tablo** | Atomik tahsilat imkânsız | `ENGINE=InnoDB` açıkça yazılır |
| `add_order.php` vergi hesaplamıyor | Panel siparişinden KDV'siz fatura | ✅ Düzeltildi (2026-09-20): satır bazında KDV, müşteri seçimi, sıcak satış carisi |
| functions.php'nin yeniden şişmesi | `includes/fn/` bölme işi geri alınır | ERP kodu `includes/erp/` |
| api.php'nin büyümesi | 13.6k satır daha büyür | Desen B (`erp_action.php`) |
| Paralel ajan çalışması | Üstüne yazma | Düzenlemeden hemen önce yeniden okuma, cerrahi düzenleme |

**Mevzuat notu:** Ben mali müşavir değilim. UBL-TR istisna/tevkifat kodları, VUK 509 zorunlu alanları, e-Arşiv iptal süresi ve iade bölümü uygulaması Faz 2 başlamadan mağazanın mali müşavirine doğrulatılmalı. Bu plandaki mevzuat maddeleri kamuya açık kaynaklardan derlendi, resmî görüş değildir.

---

## Kaynaklar

### Kaynaklar (v2 doğrulama turu)
- [Paraşüt API v4 belgeleri](https://apidocs.parasut.com/)
- [AvvaMobile.Core.Parasut](https://github.com/AvvaMobile/AvvaMobile.Core.Parasut) — trackable job 15 dk, `e_invoice_inboxes`, `scenario`/`to`
- [Sergeant61/parasut-api-v4](https://github.com/Sergeant61/parasut-api-v4) — kaynak listesi, `shipment_documents` metotları
- [yigitkonur/mcp-parasut](https://github.com/yigitkonur/mcp-parasut) — `page[size]` 25 sınırı
- [ekoseoglu/php-parasut-api-v4](https://github.com/ekoseoglu/php-parasut-api-v4) — password grant
- [salyangoz/pazaryeri-parasut](https://salyangoz.github.io/pazaryeri-parasut/) — client_id/secret destek üzerinden
- [Qural Denetim — e-ticarette e-Arşiv zorunlu alanları (VUK 509)](https://quraldenetim.com/e-ticaret-kapsaminda-e-arsiv-fatura-ve-satis-iadelerindeki-ozel-esaslar/)
- [Paraşüt — e-Arşiv fatura iptali 8 gün](https://www.parasut.com/blog/e-arsiv-fatura-nasil-iptal-edilir)
- [GİB — e-Fatura İptal, İhtar/İtiraz Bildirim Kılavuzu](https://ebelge.gib.gov.tr/dosyalar/kilavuzlar/e-Fatura_Iptal_Ihtar_Itiraz_Bildirim_Kilavuzu_V_1_1.pdf)
- [UBL-TR Kod Listeleri — İstisna, Tevkifat ve Muafiyet Kodları](https://www.kapsamymm.com/wp-content/uploads/2015-56-Nolu-Sirk%C3%BCler-Eki-UBL-TR_Kod_Listeleri-Istisna_Tevkifat_ve_Muafiyet_Kodlari.pdf)
- [Faturaport — KDV oranına göre kod seçimi](https://faturaport.com/blog/e-fatura/e-faturada-kdv-oranina-gore-hangi-kod-secilmeli)
- [Edoksis — GİB kayıtlı kullanıcı listesi güncelleme aralığı](https://www.edoksis.net/e-fatura-sorular/)
- [Paraşüt — e-İrsaliye](https://www.parasut.com/e-irsaliye)
