# Plan — Gelen e-posta: müşterinin e-postası Çalışma Alanı'na düşsün

**Durum (2026-10-14):** yalnız plan, kod yok. Konu Çalışma Alanı araştırmasının (paylaşılan
klasörde `notes/workspace-arastirma.md`, §4) 2.3 numaralı "Talep kutusu" maddesi: P2'nin
bitmemiş tek parçası, çünkü postanın hangi yolla alınacağı Erdal'ın kararı. Karar soruları en
sonda, §9'da. Karar 3 gelmeden G1, karar 1 gelmeden G2 başlamaz; her dilim tek küçük PR'dır.

**Ad karışmasın.** Çalışma Alanı'nda "gelen kutusu" bugün bildirimlerin adı: `ws_inbox`, zil
satırının `workspace_inbox` konusu ve M4 ile gelen `GET /workspace/inbox`. Bu özellik arayüzde
**"Gelen e-posta"** ve **"E-posta kutuları"**, kodda `ws_mail_*`, API'de `/workspace/mail/*`
olur; "inbox" kelimesi kullanılmaz. Dosya adı istendiği gibi kaldı.

**Kısa öneri:** müşterinin zaten var olan posta kutusunu site IMAP ile dakikada bir okusun
(seçenek A). DNS'e, Cloudflare'e ve Kodpen'in bir servisine dokunmadan ayar ekranından kurulur,
posta kutuda kaldığı için hiçbir şey kaybolmaz, sağlayıcının spam süzgeci hazır gelir. Ödünü:
bir dakikalık gecikme, cron şartı ve Microsoft 365 için ayrıca OAuth (G4).

---

## 1. Amaç ve kapsam

**Amaç.** Bir müşterinin `destek@firma.com`'a yazdığı e-posta, seçilen kanalda bir kart olarak
görünsün. Ekip orada konuşsun, görev açsın, müşteriye kanaldan e-postayla yanıt versin ve bütün
yazışma tek yerde kalsın. Araştırmadaki gerekçe: küçük işletmenin yapay zekâyı ve yazılımı en çok
kullandığı alan müşteri iletişimi; web formu → kanal kartı (#359'un `form.submitted` olayı) bunun
yarısıydı.

**Kapsamda (2.3'ün üç parçası):**
- (a) E-posta → kanal: posta kutusu bağlamak, yeni postayı kanala yazmak, ekleri almak,
  yanıtları aynı konuşmaya bağlamak.
- (b) Talep durumu (yeni, işlemde, yanıt bekliyor, kapandı), sorumlu, ilk yanıt süresi hedefi ve
  kanaldan e-postayla yanıt.
- (c) @ai ayıklama önerisi: kime gitsin, önceliği ne, benzer konuşma ya da sipariş var mı.

**Kapsam dışı (bilerek):**
- Sitenin kendi posta sunucusu ya da postanın betiğe "pipe" edilmesi: IIS'te ve paylaşımlı
  hostingte sunucunun posta yazılımına erişim yok.
- POP3: klasör ve işaret yok, IMAP her işini görür.
- IMAP IDLE (açık bağlantıyla anında haber): her açık bağlantı bir PHP işçisi tutar, IIS
  FastCGI'de süre sınırı var (araştırma §5.5'teki gerekçe).
- Postayı HTML olarak çizmek: elimizde HTML temizleyici yok, risk büyük (§6).
- Müşteri portalı, bilgi bankası, WhatsApp (araştırma 3.1 ve 3.3).

## 2. Bugün elimizde ne var

Kod okuması `development` 4155118 üzerinde.

| Parça | Nerede | Bu işte |
|---|---|---|
| Giden posta | `email()` (`includes/fn/mail.php`), gömülü PHPMailer 6; SMTP ya da bağlı hesap (`pg_mail_connection()`, `includes/connect/purposes/mail.php`: `gmail.send`, Graph `Mail.Send`, Graph isteği en çok 4 MB) | Yanıtı gönderir. Eksik: `email()` özel başlık almıyor (Message-ID, In-Reply-To, References), G3 ekler |
| Posta kuyruğu | `includes/fn/mail_queue.php`, genel görevde her tur | Yanıt kuyrukla gider; `pg_mail_properties_encode()` yeni başlıkları taşımalı |
| HTML → metin | `convert_html_to_text()` (`includes/fn/mail.php`) | Yalnız HTML parçası olan postanın metni |
| Bağlı hesaplar | `includes/fn/connect.php` (`pg_connect_api()`, mühürlü saklama `pg_connect_seal()`, servis sırları `pg_connect_service_put()`), amaçlar `includes/connect/purposes/` | IMAP parolası mühürlü saklanır; G4'te yeni amaç `mail_read`. Bugün okuma kapsamı yok |
| Zamanlanmış işler | `pg_cron_jobs()` (`includes/fn/cron.php`), `job.php` dakikada bir; webhook, push ve posta kuyruğu "inline" | IMAP okuması aynı biçimde inline, kendi süre bütçesiyle |
| Mesaj | `ws_message_send()` (kişi ya da uygulama adına, `ws_can_post_channel()`, dakikada 20 sınırı, `WS_MESSAGE_MAX` 4000 karakter), `ws_message_system()` | Posta kişi değil: ayrı yazıcı gerekir. `ws_messages.sender_kind` ENUM('user','app','system','guest'); misafir 2026.4.5'te aynı yolla (enum genişletme) eklenmişti |
| Dosya | `ws_store_upload_bytes()` (`includes/workspace/files.php`): `ws_upload_refused()`, 20 MB ya da sunucunun sınırı, kanalın klasörü | Ekler buradan geçer; bugün bir kişinin izni sorulduğu için sistem adına saklayan bir kol gerekir |
| Tartışma | `includes/workspace/threads.php`; sonuçlanan tartışma 30 gün sonra silinir (`WS_THREAD_KEEP_DAYS`) | Posta konuşması tartışmada yaşarsa bu silmeden istisna olmalı |
| Müşteri kanalı ve izleme | `includes/workspace/customer.php` (`ws_channels.contact_id`, `customer_type`), `includes/workspace/watch.php` (`ws_watch_channels()`, kayıtla ilgili satır) | Tanınan müşteriden gelen posta için müşteri kanalına satır |
| Çalışma Alanı olayları | `ws_events_own()`, `ws_event()` (#359) | "E-posta geldi", "Talep kapandı" programlanmış işlemi başlatır |
| Bildirim | `ws_notify()` (`includes/workspace/notify.php`): gelen kutusu, zil, push | Sorumluya ve kanala haber |
| Misafir | `includes/workspace/guests.php` | Karar 3'ün (c) seçeneği |
| Ayarlar | `workspace_settings.php` (`$viewer['settings']`), "Kanallarda Claude" kartı | Yeni "Gelen e-posta" kartı aynı ekranda |
| Dış API | `includes/workspace/api.php` rota tablosu (`form_file`, `binary`, `'mcp' => false`), `api_authenticate()` (anahtar + gizli, IP izin listesi, hız sınırı, istek günlüğü) | G7 uçları; itme yolu seçilirse G9'un ucu |

**Olmayanlar:** gelen posta, MIME ayrıştırıcı, IMAP istemcisi, HTML temizleyici. PHP'nin `imap`
eklentisi PHP 8.4'te çekirdekten çıkarılıp PECL'e taşındı; Pinegrap 7.1–8.5'i desteklediği için
ona güvenilemez. Araştırmadaki "IMAP eklentisi her yerde yok" kaygısı da bundan; çözümü kendi
ince istemcimiz (§3.1), eklenti gerekmez.

## 3. Seçenekler

### 3.1 A — Posta kutusunu okumak (IMAP, çekme)

Site, müşterinin var olan kutusuna (hostingin postası, Yandex, Gmail, Zoho, Microsoft 365)
dakikada bir bağlanır, yeni postaları alır.

- **Kurulum:** Çalışma Alanı ayarları › Gelen e-posta › "Kutu ekle": adres, sunucu, port (993),
  kullanıcı, parola, okunacak klasör, varsayılan kanal; "Bağlantıyı dene". Yaygın sağlayıcılar
  için ön ayar (Gmail, Yandex, Zoho, Outlook.com; cPanel / Plesk için `mail.<alan adı>`).
- **İstemci:** `includes/workspace/mail_imap.php`, `stream_socket_client()` ile; `openssl` zaten
  zorunlu. Örtük TLS (993) ya da zorunlu STARTTLS (143), sertifika doğrulaması açık, düz metin
  bağlantı yok. Komutlar: `CAPABILITY`, `LOGIN` / `AUTHENTICATE PLAIN` / `XOAUTH2`, `SELECT`,
  `UID SEARCH`, `UID FETCH` (önce `RFC822.SIZE`, sonra `BODY.PEEK[]`), `UID STORE +FLAGS (\Seen)`,
  `UID MOVE` (yoksa `COPY` + `\Deleted` + `UID EXPUNGE`), `LOGOUT`. Kaldığı yer `UIDVALIDITY` +
  son UID; `UIDVALIDITY` değişirse baştan bakılır, tekrar Message-ID ile önlenir.
- **Ne zaman:** genel görevde inline, kutu başına `GET_LOCK`, tur başına 15 sn ve 25 posta;
  ayarlarda "Şimdi bak". Cron kurulmamış sitede posta gelmez; Sistem Durumu'nda satır olur.
- **Okuduktan sonra:** posta kutuda kalır, `\Seen` işaretlenir ya da "Pinegrap" klasörüne
  taşınır (kutu başına seçim). Kutu, sitenin kopyasının yedeğidir.
- **Kimlik:**
  - Hosting postası, Yandex, Zoho: kullanıcı adı + parola ya da uygulama parolası.
  - Kişisel Gmail: iki adımlı doğrulama + uygulama parolası.
  - Google Workspace: parolayla IMAP 1 Mayıs 2025'te kapandı; uygulama parolası dar bir istisna
    olarak duruyor, asıl yol OAuth.
  - Microsoft 365: Exchange Online'da IMAP için parolayla giriş kapalı; yalnız OAuth (G4).
- **Bağımlılık:** sunucudan dışarıya 993 portu açık olmalı. Bazı paylaşımlı hostingler kapatır;
  "Bağlantıyı dene" bunu açıkça söyler.
- **Ekler:** postanın boyutu önce sorulur; 25 MB üstü alınmaz, kutuda kalır, kanala "çok büyük,
  posta kutusunda" satırı düşer. Her ek kanalın sınırına tabi.
- **Spam:** sağlayıcının süzgecinden geçmiş klasör okunur, Spam / Junk okunmaz.
- **Maliyet:** yok. **Efor:** O (istemci) + ortak çekirdek (G1).

### 3.2 B — Sitenin kendi Cloudflare Email Routing'i ve Email Worker (itme)

Alan adının (ya da bir alt alan adının) postası Cloudflare'e gelir, adresin kuralı bir Email
Worker'a gider, Worker ham postayı sitenin ucuna POST eder.

- **Kurulum:** alan adı Cloudflare DNS'inde olmalı. Email Routing açılınca MX ve SPF kayıtları
  Cloudflare'in olur; firmanın postası başka yerdeyse (hosting, Google, Microsoft) kök alanda
  açılamaz, alt alan adında açılır (`talep.firma.com`; alt alan adı destekli, catch-all yalnız
  kök alanda, her adres ayrı kural). Pinegrap'ın vereceği kısa Worker betiği yapıştırılır, iki
  gizli girilir (sitenin adresi, uygulama anahtarı ve gizli), adres kuralı Worker'a bağlanır.
  Müşteri `destek@firma.com`'u bu adrese yönlendirir ya da adresi doğrudan kullanır.
- **Akış:** Worker `message.raw`'ı değiştirmeden `POST /workspace/mail/inbound` ucuna gönderir
  (G9). Site cevap vermezse `message.forward()` ile doğrulanmış bir yedek adrese, o da yoksa
  `setReject()`.
- **Kimlik doğrulama:** uç uygulama anahtarı + gizliyle (Worker'ın gizlisinde), gövdenin HMAC'i
  ve zaman damgasıyla. Site posta kutusu parolası tutmaz. Cloudflare SPF, DKIM, DMARC ve ARC'ı
  denetler; gönderenin DMARC politikasına göre reddedilen posta Worker'a hiç gelmez.
- **Ekler:** Cloudflare'in gelen posta sınırı 25 MiB. Gövde ham `message/rfc822` gelir (multipart
  değil, `upload_max_filesize`'a takılmaz); sınır bellek (`pg_upload_limits()` mantığı) ve IIS'in
  `maxAllowedContentLength`'i.
- **Spam:** yerleşik spam puanı yok; DMARC'tan geçen spam kanala düşer, sitede süzgeç gerekir.
- **Postanın kopyası:** yok. Site düşükse yalnız yedek yönlendirme kurtarır.
- **10–15 site:** her sitenin alan adı Cloudflare'de olmalı, Kodpen her sitede Worker kurar;
  site başına yarım saat kadar ve müşterinin Cloudflare hesabına erişim.
- **Maliyet:** Email Routing ücretsiz, Workers'ın ücretsiz katmanı bu hacme yeter.
- **Efor:** uç K + Worker şablonu K + ortak çekirdek.

### 3.3 C — Kodpen'in ortak gelen posta aracısı (`in.pinegrap.com`)

B'nin tek elden işletilen hali: Kodpen kendi alanında Email Routing ve tek bir Worker çalıştırır,
siteler adreslerini oradan alır. Push aracısı (M7) ve bağlantı aracısıyla (tersine OAuth B
modeli) aynı aile.

- **Kurulum:** site "Gelen e-postayı aç" deyince lisans anahtarıyla aracıdan rastgele bir adres
  alır (ör. `k7m2q9xw@in.pinegrap.com`); aracı adresi sitenin adresine ve imza anahtarına eşler.
  Müşteri kendi posta panelinden `destek@firma.com`'u bu adrese yönlendirir: Gmail'de yönlendirme
  ve onay kodu (onay postası kanala düşer), cPanel / Plesk'te yönlendirici.
- **Microsoft 365:** dışa otomatik yönlendirme varsayılan kapalı (yeni kurumlarda 2021'den beri,
  gönderen 5.7.520 hatası alır); kurum yöneticisi bir giden spam politikasıyla açmalı.
- **Site tarafı:** B ile aynı uç (G9). Aracı Kodpen'in ayrı küçük deposu olur.
- **Güvenlik:** posta Kodpen'in Cloudflare hesabından geçer (saklanmaz); Kodpen KVKK'da veri
  işleyen olur, sözleşme ve aydınlatma metni gerekir. Yönlendirmede SPF bozulur, sahte gönderici
  ayrımı ARC ve DKIM'e kalır.
- **Postanın kopyası:** yönlendiren kutuda kalır.
- **10–15 site:** en kolayı (DNS yok, beş dakika), Microsoft 365 dışında.
- **Maliyet:** Cloudflare tarafı ücretsiz; Kodpen'e işletme ve destek yükü.
- **Efor:** uç K + aracı O (ayrı depo) + ortak çekirdek.

### 3.4 Karşılaştırma

| | A. IMAP | B. Sitenin Cloudflare'i | C. Kodpen aracısı |
|---|---|---|---|
| Nasıl gelir | Site dakikada bir kutuyu okur | Cloudflare anında siteye iter | Müşteri yönlendirir, aracı anında iter |
| Site başına kurulum | Ayar ekranında kutu bilgisi, birkaç dakika | Alan adı Cloudflare'de, MX değişir ya da alt alan adı, Worker + gizliler | Bir düğme + müşterinin posta panelinde yönlendirme |
| Bağımlılık | Dışarıya 993 portu, çalışan cron | Cloudflare DNS ve hesabı | Kodpen'in aracısı ayakta |
| Kimlik doğrulama | Site kutu parolasını (mühürlü) ya da OAuth jetonunu tutar | Uç anahtar + gizli + HMAC; site parola tutmaz | Uç anahtar + gizli + HMAC; site parola tutmaz |
| Postanın kopyası | Kutuda kalır | Yok (yedek yönlendirme kurulmazsa) | Yönlendiren kutuda kalır |
| Spam | Sağlayıcı süzmüş | Yalnız DMARC; sitede süzgeç gerekir | Yönlendiren kutunun süzgeci + DMARC |
| Ek boyutu | Kutunun sınırı; 25 MB üstü kutuda bırakılır | En çok 25 MiB | En çok 25 MiB |
| Gecikme | ~1–2 dk | Saniyeler | Saniyeler |
| Microsoft 365 | OAuth gerekir (G4) | Sorunsuz (alt alan adı) | Yönetici yönlendirmeyi açmalı |
| 10–15 sitede kolaylık | Kolay | Zahmetli | En kolay |
| Maliyet | Yok | Yok | Kodpen'e işletme yükü |
| dev'de deneme | Bir deneme kutusu yeter (karar 11) | pinegrap.com'da alt alan adı + Worker | Aracı yazılmadan denenemez |
| Efor (çekirdek hariç) | O | K + K | K + O (ayrı depo) |

### 3.5 Öneri: A

1. Her müşterinin bir posta kutusu zaten var. A, DNS'e, Cloudflare'e ve Kodpen'in bir servisine
   dokunmadan ayar ekranından kurulur; "neredeyse tek tıklama" hedefine en yakın olan bu.
2. Posta kutuda kalır: site düşse de, ayar yanlış olsa da kayıp yok; sağlayıcının spam süzgeci
   hazır. B ve C'de site postanın tek kopyası olur, spam süzgeci de bize kalır.
3. Kendi sunucusunda çalışan PHP yardım masaları (FreeScout, osTicket) ve Odoo da posta kutusunu
   okuyarak çalışır (değerlendirme, §10).
4. Ödün: ~1 dk gecikme ve cron şartı; Microsoft 365 kutuları için OAuth (G4); kendi IMAP
   istemcimizi yazmak.

İtme yolu (B ya da C) ileride gerekirse çekirdek değişmez: G9 yalnız yeni bir taşıma ekler.

---

## 4. Tasarım (seçenekten bağımsız)

### 4.1 Akış

```
destek@firma.com ──(A: IMAP, dakikada bir)──> ws_mail_fetch(kutu)
     (B/C seçilirse: Worker ──POST /workspace/mail/inbound──> aynı yere)
                                    │
                         ws_mail_ingest(ham posta, kutu)
   1. tekilleştir (kutu + Message-ID); döngü kapısı (kendi gönderdiğimiz, Auto-Submitted, toplu)
   2. ayrıştır (ws_mail_parse): başlıklar, metin, ekler, doğrulama sonucu
   3. konuşmayı bul (In-Reply-To / References / konu etiketi) ya da yeni konuşma aç
   4. kanala yaz: yeni konuşma → e-posta kartı; süren konuşma → kartın tartışmasına
   5. ekleri kanalın klasörüne koy; izin verilmeyen türleri adıyla listele
   6. haber ver: kanal (bildirim düzeyine göre), sorumlu, müşteri kanalına satır,
      "E-posta geldi" olayı
```

### 4.2 Veri (G1, şema 10.85)

- `ws_mail_boxes`: `id`, `name`, `address`, `transport` (`imap` | `graph` | `gmail` | `push`),
  `settings` (sunucu, port, güvenlik, kullanıcı; parola ya da bağlı hesap kimliği mühürlü,
  `pg_connect_seal()`), `folder`, `after_fetch` (`seen` | `move`), `move_folder`, `channel_id`,
  `reply_via` (`box` | `site` | `connection`), `uidvalidity`, `last_uid`, `last_checked_at`,
  `last_ok_at`, `last_error`, `enabled`, `created_by`, `created_at`.
- `ws_mail_conversations`: `id`, `box_id`, `channel_id`, `message_id` (kanaldaki kart),
  `thread_channel_id`, `subject`, `customer_email`, `customer_name`, `contact_id`, `status`,
  `assignee_id`, `first_in_at`, `first_reply_at`, `last_in_at`, `last_out_at`, `closed_at`.
  Durum ve sorumlu sütunları G5 için baştan konur; ikinci bir şema adımı gerekmesin.
- `ws_mail_items`: her gelen ve giden posta: `id`, `conversation_id`, `box_id`, `ws_message_id`,
  `direction` (`in` | `out`), `rfc_message_id` (+ kutuyla tekil dizin), `in_reply_to`,
  `references`, `from_email`, `from_name`, `to_list`, `cc_list`, `subject`, `body_text`
  (MEDIUMTEXT), `auth` (`pass` | `fail` | `none`), `size`, `raw_path`, `refused_files`,
  `created_at`.
- `ws_messages.sender_kind`'a `'mail'` eklenir (misafirdeki enum genişletme deseni), `sender_id`
  `ws_mail_items` satırı.
- Etiket: 10.85 çalışma alanı aralığında boş (`includes/migrations/2026.4.10.php`'nin başındaki
  not: çalışma alanı 10.80–10.89 ve 10.110–10.119, ERP 10.90'dan sonrası). Bir adım daha gerekirse
  10.112–10.118; etiket yazılırken dosyadan ve açık PR'lardan yeniden bakılır.

### 4.3 Kanalda nasıl görünür

- **Kart:** gönderenin adı ve adresi, "E-posta" rozeti (doğrulanamadıysa "Gönderen
  doğrulanamadı"), konu, temizlenmiş metin (alıntı geçmişi ve imza katlanır; 4000 karakteri aşan
  metin "Tamamını gör" ile açılır), ekler, durum rozeti (G5), "E-postayla yanıtla".
- **Yazışma kartın tartışmasında (karar 3 a):** müşterinin yanıtları müşterinin adıyla; ekipten
  "E-postayla yanıtla" ile gidenler yazanın adıyla ve "e-postayla gönderildi" işaretiyle; aradaki
  ekip konuşması iç nottur, müşteriye gitmez. Posta konuşmasının tartışması 30 günlük silmeye
  girmez; konuşma silinince ya da mesaj saklama süresiyle (araştırma 3.4) gider.
- **Metin:** `text/plain` parçası; yoksa `convert_html_to_text()`. Karakter kümeleri
  (`windows-1254`, `iso-8859-9` dahil) UTF-8'e çevrilir; başlıklarda RFC 2047, ek adlarında
  RFC 2231 çözülür. HTML hiçbir zaman HTML olarak çizilmez, uzak görseller yüklenmez; küçük satır
  içi görseller (imza logosu) atlanır, büyükleri ek olur.
- **Ekler:** kanalın klasörüne, kanalın erişim kuralıyla; `ws_upload_refused()`'ın reddettikleri
  (program, betik, SVG, HTML) saklanmaz, kartta adlarıyla listelenir.

### 4.4 Hangi kanala

1. Süren bir konuşmanın yanıtıysa konuşmanın kanalı.
2. Kutunun adres kuralı (G6): `destek+fatura@` gibi +etiket ya da kutunun takma adı → kanal.
3. Kutunun varsayılan kanalı.

Gönderen bir kişiyle eşleşirse (`contacts.email_address` ya da `user.user_email`) ve doğrulamadan
geçtiyse konuşmaya `contact_id` yazılır; müşterinin kanalı varsa oraya `ws_watch` deseniyle
"X e-posta yazdı" satırı düşer (karar 5 a). Doğrulamadan geçmeyen posta hiçbir kayda bağlanmaz.

### 4.5 Konuşma eşleme

Message-ID, In-Reply-To ve References. Bizim giden yanıtlarımızın Message-ID'si
`<pg.c{konuşma}.{rastgele}@{site}>`. Başlıklar kaybolursa konudaki `[#T123]` etiketi yedektir.
Yalnız konu benzerliğiyle eşleme yapılmaz (yanlış konuşmaya bağlama riski).

### 4.6 Yanıt (G3)

- From kutunun adresi; yazanın adı imzada ("Ayşe · Firma Destek").
- Gönderim yolu kutuda seçilir: kutunun kendi SMTP'si (IMAP ile aynı kullanıcı, 465 / 587), kutunun
  bağlı hesabı (Graph `sendMail`; 4 MB sınırını aşan ek bağlantı olarak gider) ya da sitenin genel
  SMTP'si (yalnız alan adı uyuyorsa; yoksa DMARC'a takılır, ekranda uyarı).
- `email()`'e `headers` özelliği (Message-ID, In-Reply-To, References), kuyruğa da
  (`pg_mail_properties_encode()`). Sitenin bütün giden postasına kendi kimliğini taşıyan bir
  `X-` başlığı eklenir; döngü kapısı bunu tanır.
- Kanaldaki bir dosya ek olarak seçilebilir. IMAP kutusunda istenirse Gönderilenler klasörüne
  `APPEND`; Graph bunu kendisi yapar.

### 4.7 Talep durumu (G5)

- Durumlar: yeni → işlemde → yanıt bekliyor (müşteriden) → kapandı. Müşteri yazınca "işlemde"ye
  döner, kapalıysa yeniden açılır.
- Sorumlu kişi; kutu başına ilk yanıt hedefi (saat, iş günü takvimiyle `workdays.php`), aşılınca
  sorumluya ve kanala hatırlatma.
- Kanalda "E-postalar" sekmesi (durum süzgeciyle), kartta rozet.
- Olaylar: "E-posta geldi", "Talep kapandı" programlanmış işlemi başlatır (#359 altyapısı).
- Otomatik "talebiniz alındı" yanıtı (karar 8): kutu başına, varsayılan kapalı, döngü kapısıyla,
  aynı gönderene günde bir kez.

### 4.8 @ai ayıklama (G8)

Yeni konuşmada Pinegrap AI bir öneri kartı yazar: kime, hangi öncelik, gönderenin son siparişleri
ve benzer konuşmalar, taslak yanıt. Model yalnız önerir; gönderen her zaman bir kişidir (karar 7).
Pinegrap AI anahtarı gerekir (dev'de yok).

---

## 5. Dilimler

Efor: K küçük, O orta, B büyük. Her dilim tek PR ve uçtan uca çalışır.

| Dilim | Ne | Efor | Şema | Bekleyen karar |
|---|---|---|---|---|
| G1 | Çekirdek: tablolar, MIME ayrıştırıcı, `ws_mail_ingest()`, kanalda e-posta kartı, ayarlarda `.eml` yükleyerek deneme | O | 10.85 | 3, 9 |
| G2 | IMAP ile okuma: istemci, kutu kartı, "Bağlantıyı dene", genel görevde okuma, Sistem Durumu satırı | O | yok | 1, 4 |
| G3 | Kanaldan e-postayla yanıt | O | yok | 6 |
| G4 | Microsoft 365 (istenirse Google) bağlı hesapla okuma ve gönderme | O | yok | 2 |
| G5 | Talep durumu, sorumlu, ilk yanıt hedefi, "E-postalar" sekmesi, olaylar, otomatik yanıt | O | yok (G1'in sütunları) | 8, 10 |
| G6 | Yönlendirme: +etiket ve adres → kanal, müşteri kanalına satır | K | yok | 4, 5 |
| G7 | Dış API ve MCP | K–O | yok | 7 |
| G8 | @ai ayıklama önerisi | K–O | yok | 7 |
| G9 | İtme yolu: `POST /workspace/mail/inbound` + Worker şablonu (B) ya da aracı (C, ayrı depo) | K (+ O aracı) | yok | 1 |

**G1 — Çekirdek (O, şema 10.85).** §4.2 tabloları ve enum genişletmesi; `ws_mail_parse()`
(başlık açma, RFC 2047 / 2231, karakter kümesi, multipart gezintisi, iç içe `message/rfc822` ek
olarak, alıntı ve imza katlama, boyut korumaları); `ws_mail_ingest()` (§4.1 adımları); ekleri sistem
adına saklayan kol (`ws_store_upload_bytes()`'ın izin denetimi dışında aynı kurallar, dosya sahibi
kutuyu ekleyen kişi); kanal kartı ve tartışma (JS: `php tools/build_js.php` ve `.min.js`).
Çalışma Alanı ayarlarında "Gelen e-posta" kartının iskeleti: kutu adı, adres, varsayılan kanal ve
yöneticinin bir `.eml` dosyası yükleyip kanala düşürebildiği "Bir e-posta yükle". Kaybolmuş bir
postayı içeri almak için de kullanılır; dev'de taşıma olmadan bütün çekirdek bununla denenir.

**G2 — IMAP (O).** §3.1'deki istemci, kutu kartında sunucu / port / güvenlik / kullanıcı /
parola / klasör / sonrası, "Bağlantıyı dene" (sertifika adı tutmazsa sertifikanın hangi ada
verildiğini söyler; "doğrulamayı kapat" seçeneği yok), `pg_cron_jobs()`'a inline iş, "Şimdi bak",
Sistem Durumu'nda "Gelen e-posta" satırı (son başarılı okuma 15 dakikadan eskiyse ya da son hata).
dev denemesi bir deneme kutusu ister (karar 11).

**G3 — Yanıt (O).** §4.6. Tartışmanın yazma alanında "E-postayla yanıtla" ile "İç not" ayrımı;
giden posta `ws_mail_items`'a `out` olarak yazılır; teslim edilemedi bildirimi (`multipart/report`)
yeni konuşma açmaz, konuşmaya "teslim edilemedi" satırı olur. dev'de denemek gerçek bir e-posta
gönderir; yerel test kuralı gereği bu adım Erdal'ın kendi elinde.

**G4 — Bağlı hesapla okuma (O; karar 2).** Yeni amaç `includes/connect/purposes/mail_read.php`.
Microsoft: Graph `Mail.ReadWrite` (paylaşılan kutu için `Mail.ReadWrite.Shared` ve
`/users/{adres}`), gelen kutusunda delta sorgusu, ham posta `/messages/{id}/$value` ile; aynı
`ws_mail_ingest()`. Google (karar 2 b ise): `gmail.modify` "kısıtlı" kapsam olduğu için yalnız
sitenin kendi Google uygulamasıyla (Workspace'te "Internal"), `users.messages.get?format=raw` ve
`history.list`. Yanıt aynı hesaptan (`pg_mail_connection_deliver()`).

**G5 — Talep durumu (O; karar 8, 10).** §4.7.

**G6 — Yönlendirme (K; karar 4, 5).** Kutu kartında kural listesi (+etiket ya da adres → kanal),
müşteri kanalına satır (§4.4).

**G7 — Dış API ve MCP (K–O; karar 7).** §7.

**G8 — @ai ayıklama (K–O; karar 7).** §4.8.

**G9 — İtme yolu (yalnız karar 1 b ya da c ise; o zaman G2'nin yerine geçer).** §7'deki giriş
ucu. B için Worker betiği ve kurulum adımları pinegrap.com belgesine; C için Kodpen'in ayrı deposu
(adres verme, eşleme, imzalı iletme, lisans denetimi).

## 6. Güvenlik

- **Kimlik bilgisi:** IMAP parolası `pg_connect_seal()` ile (`ENCRYPTION_KEY`) mühürlü saklanır,
  ekrana geri gelmez (son dört karakter gibi). Yalnız TLS; sertifika doğrulaması kapatılamaz.
  Kutuyu Çalışma Alanı ayarlarını düzenleyebilen (`$viewer['settings']`) ekler.
- **Sahte gönderen:** From başlığı uydurulabilir. Sağlayıcının (A'da) ya da Cloudflare'in (B/C'de)
  doğrulama sonucu okunur; DMARC'tan geçmeyen posta "Gönderen doğrulanamadı" rozetiyle düşer ve
  hiçbir müşteri kaydına bağlanmaz.
- **İçerik veridir:** postanın metni güvenilmez içeriktir. @ai, Claude ya da MCP ajanı onu
  özetlerken içindeki talimat uygulanmaz (bugünkü "model yalnız önerir" kuralı); asistan müşteriye
  e-posta göndermez (karar 7), çünkü güvenilmeyen içerik + özel veri + dışarı gönderme yolu
  araştırma §5.6'daki "ölümcül üçlü"dür.
- **HTML ve görseller:** HTML çizilmez, uzak görsel yüklenmez (izleme pikseli çalışmaz),
  bağlantılar düz metin olarak gösterilir.
- **Ekler:** `ws_upload_refused()` listesi, kanalın boyut sınırı, "görsel görsel olmalı" denetimi,
  ad temizliği; orijinal `.eml` web'den erişilemeyen bir klasörde ve yalnız kanalı okuyabilene
  `attachment` olarak verilir (karar 9).
- **Döngü:** `Auto-Submitted` (`no` dışında), `Precedence: bulk | junk | list` ve sitenin kendi
  `X-` başlığını taşıyan posta otomatik yanıt almaz; kendi giden Message-ID'lerimiz yeni konuşma
  açmaz; aynı gönderenden saatte 20'den fazla posta bekletilir ve kartta bir kez söylenir.
- **Kaynak:** tur başına süre ve adet sınırı, posta başına boyut sınırı, kutu başına kilit.
- **Gizlilik:** destek kutusunun kanalı özel kanal önerilir; posta ve ekler yalnız kanalın
  üyelerine görünür. Webhook (`pg_announce`) posta içeriği taşımaz; özel kanaldaki mesajlar için
  bugünkü kural sürer.
- **İtme ucu (G9):** yalnız uygulama anahtarı + gizli, yeni dar kapsam (`mail:inbound`), gövdenin
  HMAC'i ve 5 dakikalık zaman damgası penceresi, Message-ID ile tekrar koruması, `'mcp' => false`.

## 7. API ve MCP etkileri

- **Mesaj:** `WorkspaceMessage`'da `sender.kind = mail` ve yeni `mail` nesnesi (`conversation_id`,
  `from`, `from_name`, `subject`, `auth`, `refused_files`, tam metnin adresi). Mevcut istemciler
  bilmedikleri alanı yok sayar; M3 eşitlemesi ve M4 gelen kutusu değişmez.
- **Yeni uçlar (G7):**
  - `GET /workspace/mail/conversations` (`channel_id`, `status`, `assignee_id`, `cursor`) ve
    `GET /workspace/mail/conversations/{id}` (gelen ve giden postalar, metinleriyle):
    `workspace:read`, MCP aracı olarak da.
  - `POST /workspace/mail/conversations/{id}/reply` {text, file_message_ids}: yalnız Ekip
    cihazları oturumundan (`ws_api_own_words_only()`, yanıt kişinin imzasıyla gider), `dry_run`,
    Idempotency-Key; MCP aracı değil (karar 7 a ise MCP yalnız taslak yazar).
  - `POST /workspace/mail/conversations/{id}/status` {status, assignee_id} (G5'ten sonra):
    `workspace:write`, `dry_run`, MCP aracı olabilir.
- **Kutu yönetimi API'de yok:** parola ve sunucu bilgisi yalnız panelde.
- **İtme yolu seçilirse (G9):** `POST /workspace/mail/inbound`, gövde ham `message/rfc822`, zarf
  bilgisi `X-Pg-Envelope-To` / `X-Pg-Envelope-From` başlıklarında, kapsam `mail:inbound`,
  `'mcp' => false`. Yeni kapsam mevcut uygulamalara kendiliğinden verilmez; köprü için yeni bir
  uygulama açılır.
- **MCP:** yeni araçlar devpinegrap bağlayıcısı yeniden bağlanınca görünür.
- **OpenAPI** rota tablosundan üretildiği için ayrıca iş yok.

## 8. pinegrap.com belgesi etkileri (İngilizce, dilimler bitince)

- **/workspace-cont:** yeni "Incoming e-mail" bölümü: kutu bağlamak (Gmail uygulama parolası,
  Yandex, cPanel / Plesk, Microsoft 365 bağlı hesapla), postanın nereye düştüğü, kanaldan yanıt,
  durumlar ve ilk yanıt hedefi, "E-posta geldi" olayıyla otomasyon.
- **/api:** `mail` nesnesi, konuşma uçları, (G9'da) giriş ucu ve Worker örneği.
- **/security:** parolanın saklanışı, TLS, "Gönderen doğrulanamadı", reddedilen ekler, uzak
  görselin yüklenmemesi, asistanın e-posta göndermemesi.

Belge her dilimin PR'ıyla birlikte, yerel test ipliği üzerinden yazılır; bu plan için not yok.

## 9. Erdal'ın kararları

1. Gelen e-posta hangi yolla alınsın?
   - (a) IMAP: site, müşterinin var olan posta kutusunu dakikada bir okur. **Önerilen**
   - (b) Cloudflare: her sitenin alan adında Email Routing ve Worker postayı anında iter.
   - (c) Kodpen aracısı: müşteri postasını `in.pinegrap.com`'daki adrese yönlendirir.
2. Microsoft 365 ve Google Workspace kutuları için bağlı hesapla okuma (G4) yapılsın mı?
   - (a) Evet, Microsoft 365 için; kişisel Gmail uygulama parolasıyla IMAP'te kalır. **Önerilen**
   - (b) Evet, Google Workspace için de (sitenin kendi Google uygulaması gerekir).
   - (c) Hayır, şimdilik yalnız IMAP.
3. Bir e-posta konuşması Çalışma Alanı'nda nerede yaşasın?
   - (a) Kutunun kanalında bir kart, yazışma o kartın silinmeyen tartışmasında. **Önerilen**
   - (b) Her konuşma "E-postalar" grubunda kendi özel kanalında.
   - (c) Her gönderen kendi misafir odasında.
4. Bir sitede kaç posta kutusu bağlanabilsin?
   - (a) Birden çok; her kutunun kendi kanalı, +etiketle başka kanal. **Önerilen**
   - (b) Tek kutu; kanal kurallarla seçilir.
5. Tanınan bir müşteriden gelen e-posta nereye düşsün?
   - (a) Kutunun kanalına; müşterinin kanalına yalnız "X e-posta yazdı" satırı. **Önerilen**
   - (b) Müşterinin kanalı varsa doğrudan oraya.
6. Kanaldan verilen yanıt hangi yoldan gitsin?
   - (a) Kutunun adresinden, kutunun SMTP'si ya da bağlı hesabıyla; yazanın adı imzada. **Önerilen**
   - (b) Kutunun adresiyle ama sitenin genel SMTP ayarından.
7. Asistanlar (@ai, Claude, MCP) müşteriye e-posta gönderebilsin mi?
   - (a) Hayır; yalnız taslak yazarlar, gönderen bir kişidir. **Önerilen**
   - (b) Evet, bir kişinin onay kartıyla.
8. Yeni konuşmaya otomatik "talebiniz alındı" yanıtı gitsin mi?
   - (a) Kutu başına açılıp kapanır, varsayılan kapalı, metni düzenlenir. **Önerilen**
   - (b) Hiç olmasın.
9. Gelen postanın orijinali (`.eml`) saklansın mı?
   - (a) 30 gün saklansın, sonra yalnız metin ve ekler kalsın. **Önerilen**
   - (b) Konuşma durdukça saklansın.
   - (c) Hiç saklanmasın.
10. Talep durumu ve ilk yanıt hedefi (G5) bu planda yapılsın mı?
    - (a) Evet, yanıttan (G3) sonra ayrı dilim olarak. **Önerilen**
    - (b) Hayır, önce yalnız e-posta → kanal ve yanıt.
11. dev.pinegrap.com'da IMAP denemesi hangi kutuyla yapılsın?
    - (a) Bu iş için açılacak ayrı bir deneme kutusuyla (kodpen.com ya da pinegrap.com'da). **Önerilen**
    - (b) Erdal'ın Gmail hesabıyla, uygulama parolasıyla.

**Varsayılanlar (sorulmadı, itiraz edilirse değişir):** gelen e-posta lisans istemez (IMAP
Kodpen'e maliyet getirmiyor; C seçilirse aracı lisanslı sitelere açılır); kutuyu Çalışma Alanı
ayarlarını düzenleyebilen ekler; destek kanalı özel kanal önerilir.

## Sıra önerisi

1. Karar 3 ve 9 gelince G1; karar 1 (a) ve 4 gelince G2. İkisiyle e-posta kanala düşer.
2. G3 (yanıt), sonra G6 (yönlendirme), sonra karar 10 (a) ise G5.
3. G7 (API, MCP) ve karar 2'ye göre G4; AI anahtarı olan sitede G8.
4. G9 yalnız karar 1 (b) ya da (c) ise, o zaman G2'nin yerine.

## 10. Doğrulanamayanlar ve kaynaklar

- **Cloudflare** (2026-10-14'te belgelerin arama alıntılarından okundu): Email Routing artık
  "Cloudflare Email Service" altında; Cloudflare DNS şart; alt alan adı destekli, catch-all yalnız
  kök alanda; gelen posta en çok 25 MiB; gönderenin DMARC politikasına göre reddedilen posta
  Worker'a gelmez; Worker'da `message.raw`, `rawSize`, `forward()` (yalnız `X-` başlıklarını
  korur), `setReject()`, `reply()`; yerleşik spam puanı yok. Bulunamayanlar: Worker'ın
  `Authentication-Results` başlığını görüp görmediği, Worker hata verince gönderene ne döndüğü.
  Workers ücretsiz katman sınırları bu oturumda doğrulanmadı.
- **Microsoft:** dışa otomatik yönlendirme yeni kurumlarda 2021'den beri kapalı (5.7.520).
  Exchange Online'da IMAP için parolayla girişin kapalı olduğu ve paylaşılan kutu kapsamı
  (`Mail.ReadWrite.Shared`) bilgiden, bu oturumda doğrulanmadı.
- **Google:** Workspace hesaplarında parolayla IMAP / SMTP / POP 1 Mayıs 2025'te kapandı,
  uygulama parolası dar istisna. Gmail okuma kapsamlarının "kısıtlı" sınıfta olduğu ve herkese açık
  uygulamada yıllık güvenlik değerlendirmesi istediği bilgiden, doğrulanmadı.
- **PHP:** `imap` eklentisinin PHP 8.4'te PECL'e taşındığı bilgiden.
- **Örnek ürünler:** FreeScout, osTicket ve Odoo'nun posta kutusunu okuyarak çalışması
  değerlendirme; ürün belgelerinden doğrulanmadı.
- **Hosting:** paylaşımlı hostinglerde dışarıya 993 portunun açık olup olmadığı firmaya göre
  değişir; müşteri sitelerinde denenmedi. pinegrap.com'un Cloudflare DNS'inde olduğu (dev
  cloudflared arkasında) doğrulanmadı.
- **Kod okuması:** `includes/fn/mail.php`, `includes/fn/mail_queue.php`, `includes/fn/connect.php`,
  `includes/connect/purposes/mail.php`, `includes/fn/cron.php`, `job.php`,
  `includes/fn/files.php` (`pg_upload_limits()`), `includes/workspace/messages.php`,
  `includes/workspace/files.php`, `includes/workspace/threads.php`, `includes/workspace/watch.php`,
  `includes/workspace/customer.php`, `includes/workspace/guests.php`,
  `includes/workspace/notify.php`, `includes/workspace/api.php`, `includes/api/auth.php`,
  `includes/api/keys.php`, `workspace_settings.php`, `includes/migrations/2026.4.5.php`,
  `includes/migrations/2026.4.10.php`.
- **Kaynaklar:**
  - developers.cloudflare.com/email-service/platform/limits/
  - developers.cloudflare.com/email-service/configuration/subdomains/
  - developers.cloudflare.com/email-service/api/route-emails/email-handler/
  - developers.cloudflare.com/email-service/get-started/route-emails/
  - learn.microsoft.com/powershell/module/exchangepowershell/new-hostedoutboundspamfilterpolicy
  - knowledge.workspace.google.com/admin/sync/transition-from-less-secure-apps-to-oauth
  - support.google.com/mail/answer/185833
