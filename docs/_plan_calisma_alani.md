# Pinegrap Çalışma Alanı — kanal, görev, plan panosu (v1 taslak)

**Durum:** v1.2 — bütün kararlar verildi, faz 1 uygulandı (bkz. `docs/degisiklikler.md`, 2026.4.4 Çalışma Alanı) · **Tarih:** 2026-09-23 · **Kod adı:** `workspace` (önek `ws_`)
**Arayüz adı önerisi:** "Çalışma Alanı" (bkz. §1, K1)

**Erdal'ın verdiği yön (2026-09-23):** sohbet tabanlı, Slack tarzı · kanallarda
komutla görev ve kişi etiketleme · ürün/sipariş/fatura/kişi/dosya çağırma, o
kaydın kendi ekranında da görünecek · **dış API ile baştan entegre** · iki kanal
türü: genel (şirketteki herkes) ve bireysel (davetli), sahibi bireyseli genele
çevirebilir · takvimli plan panosu: firma + kişi başına · departman ataması,
görev dağıtılınca çakışma uyarısı · rol 3 için yeni yetkiler · ileride odaya yapay
zekâ çağrılacak, **ilk etapta yok**.

**Çıkış noktası:** ekip notları dağınık (defter, başka site, hiç). Müşteri için
konuşulan plan kayda geçmiyor, kimin ne yaptığı belli değil.

**Karar verilenler (2026-09-23):** yeni roller rol numarası değil **yetki**
olarak gelir — kullanıcı rol 3 kalır, panele erişim veren ek yetki sütunları
eklenir (§3.2) · yönetici bireysel kanalı **iz bırakarak** açar (§4.2) ·
mevcut sohbet balonu (birebir mesaj + ziyaretçi sohbeti) **hep ayrı kalır**
(§4.4) · şema **2026.4.4'e alt adım**, aralık **4.80–4.89** (§16)

---

## 0. Hedef ve kapsam

**Hedef:** konuşma → karar → görev → takvim zinciri tek yerde, hepsi
Pinegrap'ın kendi kayıtlarına bağlı.

**Başarı ölçütü**

1. "Bu müşteriyle ne konuşmuştuk?" sorusu tek ekranda cevaplanır (kanal +
   kararlar + görevler).
2. Her görevin bir sorumlusu ve tarihi vardır; kimsede kalmayan iş olmaz.
3. Sipariş/fatura/ürün ekranında o kayıt hakkında konuşulanlar görünür.
4. Görev dağıtılırken aşırı yüklenen kişi, dağıtan kişiye o anda söylenir.

**İlk etapta yok:** yapay zekâ (yalnız dikişi bırakılır, §15) · müşterinin
kanala katılması (dış misafir) · sesli/görüntülü görüşme · e-postayı kanala
alma · satış hunisi (fırsat/aşama).

---

## 1. Adlandırma: "CRM" mi?

| Seçenek | Artı | Eksi |
|---|---|---|
| **CRM** | Pazarda bilinen etiket | CRM beklentisi satış hunisi, fırsat, teklif aşaması, müşteri skorudur — bunların hiçbiri ilk kapsamda yok; "CRM var ama huni yok" hayal kırıklığı yaratır |
| **Ekip** | Kısa | Görev ve plan panosunu anlatmaz |
| **Çalışma Alanı** | Kanal + görev + planın hepsini kapsar; Slack/Asana'nın kullandığı kavram | Uzun |

**Öneri:** modül "Çalışma Alanı". CRM ayağı müşteriye bağlı kanallardan gelir
(§4.1). Satış hunisi ileride ayrı faz olursa o zaman "CRM" adı hak edilir.

---

## 2. Elimizde ne var (kod tabanında doğrulandı)

| Parça | Ne sağlıyor | Çalışma alanı için anlamı |
|---|---|---|
| `chat.php` + `chat_conversations` / `chat_messages` (2026.4.2) | İki kişilik konuşma, ek (görsel/dosya/ses), yazıyor göstergesi, çevrimiçi durumu (`user_online_timestamp`, 50 sn nabız), ziyaretçi sohbeti (`channel='site'`) | Şema **iki taraflı** (`initiator_*` / `target_*` okundu sütunları) — çok üyeli kanala genişletilemez. Yardımcılar yeniden kullanılır: ek yükleme (`pg_chat_store_upload`), avatar, görünen ad, durum, hız sınırı. Kural: rol 3 ↔ rol 3 sohbet edemez |
| `assets/js/chat_backend.src.js` | Uyarlanır poll (rozet 60 sn, liste 15 sn, açık konuşma 5 sn, yazarken 2 sn) | Kanal senkronu aynı deseni kullanır (§17) |
| `notifications` + `notification_reads` + `includes/notifications.php` | Kişi başına okundu, tek görünürlük merdiveni `pg_notification_visible()` | Bildirim satırları **kişiye değil herkese** yazılıyor, görünürlükle süzülüyor — "@Ayşe" gibi kişiye özel olay için ayrı gelen kutusu gerekir (§10) |
| `push_subscriptions` + `push_queue` + `includes/push.php` | PWA push, gecikmeli kuyruk (`uq (user, source, reference)`), ekranda okunan sohbet için push'u düşürme | Bahsetme ve görev atama için hazır; yeni `source` değerleri |
| `includes/api/modules.php` | Modül kendi uçlarını, olaylarını, OpenAPI nesnelerini ve izin grubunu ekler; ERP bunu kullanıyor | **"API ile baştan entegre"nin zemini hazır**: `includes/workspace/api.php` ikinci modül satırı |
| `pg_announce()` (`includes/fn/events.php`) | Her yerden olay kuyruğa | `task.created` vb. |
| `X-Dry-Run` (`includes/api/dry_run.php`) | Yazma provası | İleride yapay zekânın "önce prova, onayla" akışı (§15) |
| Dosya Yöneticisi sanal klasör dikişi (ERP klasörleri) | Modülün dosyalarını kendi mantığıyla listeleme | "Çalışma Alanı" sanal klasörü, kanal başına alt klasör |
| `get_file.php` `erp-` kapısı | Adı `erp-` ile başlayan dosyayı yalnız ERP hakkı olana verir | Bireysel kanal dosyası için aynı desen: `ws-` → kanal üyeliği kontrolü. Bugünkü sohbet ekleri ayar klasörüne düşüyor ve o klasörün erişim türüne tabi — kanal bazında gizlilik yok |
| `calendars` modülü | Siteye yayınlanan etkinlik takvimi (konum, rezervasyon) | İç plan panosu için **uygun değil** (§8.1) |
| `contacts` (adres defteri) ve ERP cari (kişi ↔ cari bağı var) | Müşteri kaydı | Müşteri kanalının bağlamı (K11). `contacts.department` müşterinin kendi şirketindeki departmanıdır, bizim ekip departmanımız değil |
| `user` tablosu | — | **Personel tablosu değil**: üyelik sitelerinin her üyesi burada rol 3. "Rol 3 = ekip" varsayımı yapılamaz (§3.1) |
| `job.php` + `pg_cron_job_is_enabled()` | Zamanlanmış işler | Son tarih hatırlatması, gün özeti |

---

## 3. Kullanıcılar, roller, departmanlar

### 3.1 Kritik nokta: rol 3 kimdir?

`user` tablosunda hem panel personeli hem sitenin üyeleri (müşteriler) rol 3
olarak duruyor. Çalışma alanına **yalnız ekip üyesi** girer:

- Rol 0–2: varsayılan olarak ekip üyesi (K10 — Designer bazen dış ajans olabilir).
- Rol 3: yalnız `manage_workspace` yetkisi açıksa (§3.2).
- Site üyesi hiçbir `ws_` ucuna, ekrana, push'a, arama sonucuna ulaşamaz — kabul
  testinin ilk maddesi.

Yetki sütunu eklerken rol-3 merdiveninin dördü birden güncellenir (`welcome.php`,
`get_page.php`, `includes/fn/content.php`, `set_password.php` /
`reset_password.php`) ve ERP'deki "Unknown column user.manage_erp" dersinden
`pg_user_has_ws_columns()` sondası yazılır.

### 3.2 Yeni yetkiler (karar: rol değil yetki)

Rol numarası değişmez; ekip üyesi rol 3 "Kullanıcı" olarak kalır. Panele
erişim, ERP'nin `manage_erp` / `manage_erp_cash` / `manage_erp_settings`
deseniyle **öneksiz `TINYINT` yetki sütunlarıyla** verilir. `add_user.php` /
`edit_user.php`'de tek bir "Çalışma Alanı" yetki satırı: kapı anahtarı +
çekmecede alt anahtarlar (`pg_user_permission_ui()`,
`pg_user_permission_switches()`). Rol 0–2 hepsinden muaftır (ERP'deki gibi).

| Yetki | Açtığı | Kapalıyken |
|---|---|---|
| `manage_workspace` (kapı) | Çalışma alanına giriş: genel kanalları okur ve katılır, kanal açar, kendine görev, kişisel pano, kayıt etiketleri (her etiket ayrıca kaydın kendi yetkisine bakar) | Modül yok, menüde görünmez |
| `manage_workspace_assign` | Başkasına görev atama, departman havuzundan dağıtma, atama penceresinde başkalarının doluluğu | Yalnız kendine görev; kendisine atanan görevde durum ve yorum |
| `manage_workspace_board` | Ekip haftası, şirket panosu, herkesin yükü, çakışma listesi, sert çakışmayı geçme | Yalnız kendi panosu |
| `manage_workspace_settings` | Departmanlar, ekip profilleri (kapasite, çalışma günleri), modül ayarları | Ayar ekranı kapalı |

**Departman lideri** bir yetki değil, departman ayarındaki işarettir
(`is_lead`): lider **yalnız kendi departmanında** atama ve pano haklarını
kendiliğinden kazanır; şirket geneli için yukarıdaki yetkiler gerekir (K16).

**Neden yeni rol numarası değil:** kodda kabaca 100+ `== 3` ve 300+ `< 3` /
`> 2` / `>= 3` karşılaştırması var (grep sayımı). Rol 4 bunların bir kısmında
"yönetici değil", bir kısmında "hiç kimse" sayılırdı — sessiz yetki açığı riski.

Artı: panelin geri kalanı hiç etkilenmez; kullanıcı ekranındaki mevcut desen
kullanılır. Eksi: her yeni sütun rol-3 merdiveninin dört dosyasına ve
`pg_user_has_ws_columns()` sondasına işlenmeli — unutulursa tek hakkı bu olan
kullanıcı "erişim yok"a düşer.

**İleride:** dış danışman için `workspace_guest` (yalnız davet edildiği kanal,
kayıt etiketleri kapalı) — ilk etapta yok.

### 3.3 Departmanlar

- Tanım yeri: Çalışma Alanı ayarları (ad, renk, lider(ler), üyeler).
- Kişi birden çok departmanda olabilir, biri "ana departman" (K6).
  Artı: gerçek küçük ekiplerde herkes iki şapka taşır. Eksi: kapasite
  departmanlar arasında paylaşılır, çakışma hesabı kişi bazlı yapılmalı
  (zaten öyle, §9).
- Görev departmana atanabilir, kişiye atanması sonra olabilir (havuz): "Tasarım'a
  iş" denir, lider dağıtır.
- Departman oluşturulunca isteğe bağlı departman kanalı; üyeleri departmandan
  senkron.
- Ekip profili (kişi başına): unvan, günlük kapasite (varsayılan 8 sa),
  çalışma günleri. Bunlar `user`'a değil `ws_profiles` tablosuna yazılır;
  `user`'da yalnız yetki sütunları durur (üye kayıtlarıyla dolu büyük tabloyu
  gereğinden fazla genişletmemek için).

---

## 4. Kanallar

### 4.1 Türler

| Tür | Görünürlük | Not |
|---|---|---|
| **Genel** | Ekipteki herkes görür, katılmadan okuyabilir | Katılınca bildirim alır |
| **Bireysel** | Yalnız davetliler | Sahibi genele çevirebilir |
| **Doğrudan mesaj** | İki kişi | Çalışma alanında yok; sohbet balonu ayrı kalır — §4.4 |

Tür değil **özellik** olarak iki ek:

- **Müşteri bağlamı:** kanal bir müşteriye (kişi ve/veya cari) bağlanır. Cari
  kartında ve kişi ekranında "Kanal" bağlantısı + açık görevler. CRM ayağı
  budur. (K11)
- **Departman bağlamı:** departman kanalı.

### 4.2 Görünürlük geçişleri

- **Bireysel → Genel:** sahip (ve yönetici). Geçmişin tamamı herkese açılır →
  onay penceresi: "412 mesaj, 18 dosya herkese görünür olacak". Kanala sistem
  mesajı düşer.
- **Genel → Bireysel:** öneri **izin verme** (K5). Herkes zaten okudu; "artık
  gizli" demek gizlilik yanılsaması. Gerekirse yeni bireysel kanal açılır.
- **Sahiplik devri:** sahip pasifleşir/silinirse sahiplik en eski üyeye, o da
  yoksa yöneticiye.
- **Arşiv:** silinmez, salt okunur olur; etiketler ve görev bağları yaşar.

**Yöneticiler bireysel kanalın içeriğini görebilir mi?** (K4)

| Seçenek | Artı | Eksi |
|---|---|---|
| a. Görmez, yalnız adı ve üyeleri (Slack modeli) | Ekip rahat yazar | Şirket sahibi kendi şirketinde kör nokta; ayrılan çalışanın kanalı sahipsiz kalır |
| b. Her zaman görür | Tam şeffaflık | "Bireysel" kavramı anlamsızlaşır, insanlar yine başka platforma kaçar — çözmeye çalıştığımız sorun |
| c. Görmez; gerekirse **iz bırakarak açar** (kanala "Erdal denetim için kanalı açtı" sistem mesajı + `log_activity`) | İkisinin dengesi; kötüye kullanım görünür | Bir ek akış |

**Öneri:** c.

### 4.3 Mesaj

- **Biçim:** düz metin + hafif biçim (kalın, italik, satır içi kod, liste,
  bağlantı). Kullanıcı HTML'i yok; etiket token'ları sunucuda chip'e çevrilir.
  Bugünkü sohbetin "yalnız düz metin, `textContent`" kuralı burada yetmez —
  küçük, beyaz listeli bir render gerekir. Artı: okunur mesaj. Eksi:
  render güvenlik yüzeyidir, tek fonksiyonda tutulmalı.
- **Yanıt:** Faz 1'de alıntılı yanıt; Faz 2'de konu (thread) (K12).
  Konu artısı: kanal dağılmaz. Eksisi: arayüz ve "yanıtı kaçırdım" bildirimi.
- **Düzenleme/silme:** yazan düzenler ("düzenlendi" işareti); silme yumuşak
  ("mesaj silindi"). Görev ya da kararın kaynağı olan mesaj silinse de bağ kalır.
- **Kararlar ve Notlar (dağınık not sorununun asıl çözümü):**
  - Mesaj "Karar" ya da "Not" olarak işaretlenir (`/karar`, üç nokta menüsü) →
    kanalın **Kararlar** sekmesinde toplanır; kim, ne zaman, hangi bağlamda.
  - Kanal başına düzenlenebilir **Özet** belgesi (müşteri için geçerli plan,
    iletişim kişileri, kurallar). Faz 2'de sürüm geçmişi.
  - Artı: sohbet kaybolan akış olmaktan çıkar, kurumsal hafızaya dönüşür.
    Eksi: işaretleme alışkanlık ister → "Bunu karar olarak kaydet?" önerisi
    (ör. "tamam, öyle yapalım" gibi kalıplarda) Faz 2'de düşünülebilir.
- **Tepki (emoji), sabitleme:** Faz 2.

### 4.4 Bugünkü sohbet balonuyla ilişki (karar: hep ayrı)

- Balon (birebir mesaj + ziyaretçi sohbeti) olduğu gibi kalır; `chat_*`
  tablolarına ve akışına dokunulmaz, taşıma yapılmaz.
- Çalışma alanında ayrı DM türü yok; iki kişilik bir konu için iki üyeli
  bireysel kanal açılabilir. `ws_channels.kind` yalnız `public` / `private`.
- **Köprü:** çalışma alanında bir kişinin adından "Mesaj gönder" balonda o
  kişiyle sohbeti açar — iki sistem ayrı ama kullanıcı yol aramaz.
- Ortak parçalar kopyalanmaz: ek yükleme çekirdeği bugün sohbet ayarlarına bağlı
  (`pg_chat_attachments_ready()`, izin verilen türler), ortak bir yardımcıya
  ayrılır ve sohbetin davranışı değişmez; avatar, görünen ad, çevrimiçi durumu
  doğrudan çağrılır.
- Okunmamış sayaçlar iki yerde: balon (sohbet) ve menü/üst çubuk rozeti
  (çalışma alanı).
- **Açık soru (K15):** balondaki "rol 3 ↔ rol 3 yazışamaz" kuralı
  (`pg_chat_can_pair()`) iki rol-3 ekip üyesini de engeller. İkisi de
  `manage_workspace` sahibiyse kural kalksın mı? Öneri: evet — fonksiyon rol
  yerine kullanıcıya bakacak şekilde genişler, site üyeleri için kural aynen
  kalır.

Artı: çalışan sistem hiç riske girmez. Eksi: iki gelen kutusu; iki mesajlaşma
kod yolu kalıcı.

---

## 5. Etiketler ve kayıt çağırma

### 5.1 Tetikleyiciler

- `@` **kişi** (ekip üyesi) · `@tasarim` **departman** · `@kanal` herkese (yalnız
  sahip/yönetici; gürültü koruması).
- `#` **kayıt**: tek seçici, önekle daraltılır — `#sip 1045`, `#ürün kalem`,
  `#fat PGF2026…`, `#cari acme`, `#kişi`, `#dosya`, `#sayfa`, `#görev G-42`,
  `#kanal`. Önek yazılmazsa seçici sekmeli arar.
- `/` **komut** (§6).

Alternatif: her türe ayrı işaret (`$` ürün, `!` sipariş…). Eksi: ezber;
Slack/GitHub alışkanlığıyla da çelişir. **Öneri:** tek `#` + önek.

### 5.2 Saklama ve gösterim

- Mesajda token: `<@user:12>`, `<#order:1045>` — ad/numara değişse de bozulmaz.
- Chip **görene göre** çizilir:
  - Hakkı var: "Sipariş #1045 · Kargolandı · 1.250,00 ₺" + bağlantı
  - Hakkı yok: yalnız "Sipariş #1045", bağlantısız, ayrıntısız
  - Silinmiş kayıt: "silinmiş sipariş #1045"
- Kural: **etiket, izni olmayana veri sızdırmaz.** Özet bilgisi anlık okunur,
  mesajın içine kopyalanmaz (kayıt değişince chip güncel).
- `ws_refs` tablosu: her etiket bir satır (kaynak: mesaj/görev/not; hedef:
  tür + id) → ters arama.

### 5.3 "O alanda etiketlenecek" — kaydın ekranındaki kart

Sipariş, ürün, fatura, cari, kişi, dosya, sayfa ekranlarında dar durum
sütununda (CLAUDE.md UI kuralı: form sütunu + dar durum sütunu) küçük bir
**Çalışma Alanı** kartı:

- Bu kaydın geçtiği son mesajlar (kanal adıyla), açık/kapalı görevler, kararlar
- "Bu kayıt için görev oluştur" · "Kanalda paylaş"
- Kişinin göremeyeceği kanaldaki anma **sayılmaz bile** ("2 gizli kanalda geçiyor"
  demek de sızıntıdır)

Artı: kayıt bazlı hafıza — Slack/Trello'nun dışarıdan yapamayacağı şey.
Eksi: 7+ ekrana dokunuş. Çözüm: tek ortak bileşen `ws_ref_panel($type, $id)`;
Faz 1'de üç ekran (sipariş, cari/kişi, ürün), kalanı Faz 2.

### 5.4 Kayıt türü defteri

`ws_ref_types()` — her tür için: etiket, ikon, arama, özet (başlık/durum), izin
kontrolü, panel adresi. ERP türleri ERP kapalıyken kaydolmaz; modüller kendi
türünü ekleyebilir (API modül dikişi gibi). Yeni tür = tek dosyaya bir satır.

---

## 6. Komutlar

| Komut | Ne yapar | Örnek |
|---|---|---|
| `/gorev` | Görev oluşturur | `/gorev Logo revizyonu @ayse cuma #cari Acme` |
| `/ata` | Görevi atar / devreder | `/ata G-42 @mehmet` |
| `/bitti` | Görevi tamamlar | `/bitti G-42` |
| `/ertele` | Tarih kaydırır | `/ertele G-42 +2g` |
| `/karar` | Karar kaydı | `/karar Ambalaj kraft olacak` |
| `/not` | Kanal notu | `/not Muhasebe irtibatı: Selin, dahili 204` |
| `/hatirlat` | Hatırlatma | `/hatirlat @ali yarın 10:00 teklifi ara` |
| `/plan` | Kişinin/departmanın haftası (yalnız sana görünen yanıt) | `/plan @ayse` |

Kurallar:

- Türkçe ve İngilizce eş adlar (`/gorev` = `/task`); Türkçe karakterli yazım da
  tanınır (`/görev`).
- **Her komutun tıklanan bir karşılığı var** (mesaj menüsü: "Bundan görev
  oluştur", "Karar olarak kaydet"). Komut kısayoldur, zorunluluk değil — yeni
  gelen ekip üyesi ezberlemek zorunda kalmaz.
- Gönderimden önce **önizleme**: "Şunu oluşturacağım: Logo revizyonu → Ayşe,
  26 Eylül Cuma [Oluştur]". Yanlış ayrıştırma yanlış görev üretmez.
- Tarih sözlüğü kısıtlı başlar: bugün, yarın, gün adları, `26.09`, `+3g`,
  `haftaya`. Serbest doğal dil ayrıştırıcı yapılmaz (sonu gelmez).
- Görev oluşunca kanala **canlı görev kartı** düşer (başlık, sorumlu, tarih,
  durum düğmeleri); durum değişince kart güncellenir.
- Komut defteri Faz 3'te API uygulamalarına açılır → dış botlar ve ileride
  yapay zekâ kendi komutunu ekler.

---

## 7. Görevler

| Alan | Not |
|---|---|
| Numara | `G-42` — site geneli artan; komut ve etiketlerde kullanılır |
| Başlık, açıklama | Açıklamada da etiket çalışır |
| Kaynak | Kanal + mesaj (isteğe bağlı; kanalsız kişisel görev olur) |
| Sorumlu | **Tek kişi** + katılımcılar (K8) |
| Departman | İsteğe bağlı; havuz görevi için zorunlu |
| Durum | Yapılacak · Sürüyor · Beklemede · Tamam · İptal |
| Öncelik | Düşük · Normal · Yüksek · Acil |
| Başlangıç, son tarih | Takvim günü (`DATE`) |
| Tahmini süre | Dakika; isteğe bağlı — çakışma hesabının girdisi |
| Bağlı kayıtlar | `ws_refs` |
| Kontrol listesi, tekrar, bağımlılık | Faz 2 / 3 |

**Tek sorumlu mu, çok mu?** Tek: hesap soran kişi belli, "herkesin işi kimsenin
işi" olmaz. Çok: ortak işlerde doğal. **Öneri:** tek sorumlu + katılımcılar;
ortak iş gerekiyorsa alt görevlere bölünür.

**Görünürlük:** görev, kaynak kanalın görünürlüğünü miras alır. Kanalsız görev:
oluşturan + sorumlu + katılımcılar + departman lideri + yöneticiler.

**Ekranlar:** Görevlerim (bugün, bu hafta, gecikmiş, bana atanan, benim
atadığım) · kanal Görevler sekmesi · departman listesi. Kanban (durum sütunları)
Faz 2.

---

## 8. Plan panosu

### 8.1 Mevcut `calendars` modülü kullanılsın mı?

| Artı | Eksi |
|---|---|
| Takvim ekranı hazır | Siteye yayın için tasarlandı (etkinlik, konum, rezervasyon); yetkisi `manage_calendars`; iç toplantının yanlışlıkla sitede yayınlanma riski; kişi/kapasite kavramı yok |

**Öneri:** kullanma, iç model ayrı. Gerekirse ileride "bu etkinliği site
takvimine yayınla" köprüsü.

### 8.2 Takvimde ne var

- Görevler (başlangıç–bitiş çubuğu, tahmini süre)
- Plan öğeleri: toplantı, müşteri ziyareti (saatli) · izin/rapor (tam gün) ·
  şirket kapalı günleri ve resmî tatiller (ayar)

### 8.3 Görünümler

| Görünüm | Kim | Ne |
|---|---|---|
| **Ekip haftası** (ana ekran) | Yönetici, lider | Satır = kişi, sütun = gün; hücrede görevler + doluluk rengi (yeşil / sarı %80+ / kırmızı %100+). Çakışma burada görünür |
| Şirket panosu | Herkes (görebildiği kadar) | Ay görünümü, departman süzgeci |
| Departman panosu | Lider, üyeler | Ekip haftası, departmanla süzülmüş |
| Kişisel pano | Herkes | Benim haftam/ayım + günlük yük çubuğu |

Sürükle-bırak: görev çubuğunu başka güne/kişiye taşımak tarih/atama değiştirir →
çakışma denetimi hemen çalışır.

### 8.4 Takvim kütüphanesi

| Seçenek | Artı | Eksi |
|---|---|---|
| FullCalendar (standart, MIT) | Ay/hafta/gün, sürükle-bırak hazır; build gerektirmeyen tek dosya paketi var | ~300 KB; **kişi satırlı zaman çizelgesi (resource timeline) Premium eklentide** — ticari lisans ya da GPLv3 ister, MIT olan Pinegrap'a gömülemez. Ana ekran (ekip haftası) zaten elle yazılacak |
| Elle (CSS grid + tablo) | Tasarım diline tam uyum, hafif, bağımlılık yok | Ay/hafta görünümünü ve sürükle-bırakı da yazmak |

**Öneri:** elle. Ekip haftası ızgarası zorunlu olarak elle; ay görünümü basit bir
ızgara; sürükle-bırak HTML5 DnD ile.

---

## 9. Çakışma denetimi

**Tanımlar**

| # | Tür | Şiddet | Kural |
|---|---|---|---|
| 1 | Kapasite aşımı | Uyarı | Kişinin bir gündeki yükü > günlük kapasite |
| 2 | Saat çakışması | Sert | Aynı kişide iki saatli öğe üst üste (toplantı, ziyaret) |
| 3 | İzin çakışması | Sert | Kişi o aralıkta izinli |
| 4 | Departman uyumsuzluğu | Bilgi | Görevin departmanı X, kişi X'te değil |
| 5 | Yetişmeyen tarih | Bilgi | Kalan iş günü × boş kapasite < tahmini süre |
| 6 | Bağımlılık | Bilgi | "Önce G-40 bitmeli" (Faz 3) |

**Yük hesabı:** görevin iş günleri = [başlangıç..son tarih] ∩ çalışma günleri
− tatiller − kişinin izin günleri; günlük yük = tahmini süre ÷ gün sayısı.
Günün toplamı = görev yükleri + saatli öğelerin süresi. Tahmini süresi olmayan
görev: ayardaki varsayılan yük (ör. 60 dk) ya da hiç sayılmaz ve hücrede
"tahminsiz 3 görev" rozeti çıkar (K7'nin alt sorusu).

**Davranış** (K7):

- Engelleme değil, **atama anında uyarı ve seçenek**: "Ayşe 26–27 Eylül'de %140
  dolu (G-38 Logo, G-41 Katalog). [Yine de ata] [Mehmet'e ata — %40 dolu]
  [Tarihi kaydır]". Alternatif kişi aynı departmandan en boş kişi.
- Sert çakışmada (izin, saat üst üste) öneri: "Yine de ata" yalnız
  `manage_workspace_board` sahibi, departman lideri ve yönetici için.
- Liderin/yöneticinin **çakışmalar listesi** + isteğe bağlı günlük özet.

Artı: tahmini süre girildikçe çok değerli, "kim boş?" sorusunu bitirir.
Eksi: tahmin girilmezse hesap zayıflar — bu yüzden varsayılan yük ve görev
sayısı rozeti.

---

## 10. Bildirimler

**Olaylar:** bahsetme · görev atandı · görev tamamlandı (atayana) · son tarih
yarın · gecikti · kanal daveti · bireysel kanal genele çevrildi · çakışma
(lidere).

**Nereye yazılır:**

| Seçenek | Artı | Eksi |
|---|---|---|
| Mevcut `notifications` tablosu | Tek tablo | Satırlar herkese yazılıp görünürlükle süzülüyor; kişiye özel "@Ayşe" bu modele uymuyor, her bahsetme herkesin sorgusuna girer |
| **Ayrı `ws_inbox` (kişi başına satır)**, zil ikisini birleştirir | Kişiye özel, hızlı indeks (`user_id, read_at`) | Zil ucunda birleştirme |

**Öneri:** `ws_inbox` + tek zil. Push: `pg_push_enqueue($user, 'ws', $inbox_id,
$delay)` — sohbetteki gibi gecikmeli; ekranda okuyana push gitmez.

**Uygulanan (faz 1):** `ws_inbox` + modülün kendi gelen kutusu, menüdeki rozet
ve push. Zil birleştirmesi yapılmadı — zilin görünürlük modeli site geneli,
kişiye özel satırı oraya köprülemek ayrı bir iş; faz 2'ye bırakıldı. Faz 1'de
olaylar: anılma, görev verildi, görev bitti (verene), kanala eklenme. "Son
tarih yarın", "gecikti" ve günlük e-posta özeti faz 2.

**Sonra uygulanan (4.83):** zil birleştirmesi yapıldı. `notifications`'a
`target_user_id` + `reference_id` eklendi; adresli satırı yalnız hedef kişi
görür, `action = 'workspace'` satırı `ws_inbox` satırına bağlı ve okuma iki
yönlü eşitlenir. Push yine tek kişiye (`'ws'` kaynağı); kolonlar varken
`push_pending` ayrıca gelen kutusunu eklemez.
Kanal başına: tümü / yalnız bahsetmeler / sessiz. Günlük e-posta özeti isteğe
bağlı (bugünkü görevler, gecikenler).

---

## 11. Dosyalar

- Mesaja ek: `files` tablosu, mevcut yükleme kuralları (`pg_upload_name_blocked`
  vb.); sohbetteki 5 MB sınırı ayara dönmeli (ekip dosyaları büyük olur).
- Bireysel kanal dosyası `ws-` önekiyle saklanır; `get_file.php` `erp-`
  kapısının yanına `ws-` kapısı: yalnız kanal üyesi (+ denetim modundaki
  yönetici). Genel kanal dosyası: ekip üyesi. Hiçbiri site üyesine ya da
  oturumsuz ziyaretçiye verilmez.
- Kanalın **Dosyalar** sekmesi; Dosya Yöneticisi'nde "Çalışma Alanı" sanal
  klasörü (ERP deseni; kanal başına alt klasör, görene göre).
- Var olan dosyayı `#dosya` ile etiketleme; dosyanın ayrıntısında ters kart.

---

## 12. Diğer modüllerle bağlar

| Modül | Çalışma alanına verdiği | Çalışma alanından aldığı | Faz |
|---|---|---|---|
| E-ticaret | `#sip`, `#ürün` etiketleri | Sipariş/ürün ekranında kart; **kaydı izle**: kanala bağlı siparişin durumu değişince kanala sistem mesajı | 1 / 2 |
| ERP | `#fat`, `#cari`, irsaliye | Cari kartında müşteri kanalı + görevler; gecikmiş alacak işi (`erp_overdue_job`) isteğe bağlı "tahsilat takibi" görevi açar | 1 / 3 |
| Adres defteri | `#kişi` | Kişi ekranında kart; müşteri kanalı | 1 |
| Dosya Yöneticisi | `#dosya`, ekler | Sanal klasör, ters kart | 1 / 2 |
| Sayfa yöneticisi / tasarımcı | `#sayfa` | Sayfa ayarlarında kart; ileride tasarımcıda "bu sayfanın görevleri" paneli | 2 / 3 |
| Bildirim + PWA | Zil, push | — | 1 |
| Sohbet balonu (ayrı kalır) | Çevrimiçi durumu, ek yükleme yardımcıları | Kişi adından balonda sohbet açma köprüsü; rol 3 ↔ 3 kuralı (K15) | 1 |

---

## 13. Dış API ve webhook — baştan entegre

**Kural:** her fazın özelliği API'siyle birlikte çıkar; API sonraya bırakılmaz.

- Dikiş: `includes/workspace/api.php` (önek `ws_`), `includes/api/modules.php`'ye
  ikinci satır; `WORKSPACE_ENABLED` kapalıyken modül hiçbir şey eklemez.
- Kapsamlar: `workspace:read` / `workspace:write` (kanal, mesaj) ·
  `tasks:read` / `tasks:write` · `planning:read` (Faz 2).
  **Sahip tavanına da eklenir** (`ws_owner_scopes`) — eklenmezse uç 403 verir
  (API'de üç kez yaşandı). Tavan yetkilerden türer: `manage_workspace` →
  `workspace:read/write`, `tasks:read` ve kendi görevleri; `manage_workspace_assign`
  → `tasks:write` tam; `manage_workspace_board` → `planning:read`.
- Görünürlük: uygulama yalnız **üye olduğu** kanalları görür; bireysel kanala
  yalnız kanal sahibi ekler. Sahibinin göremediğini uygulama da göremez.

| Faz | Uçlar |
|---|---|
| 1 | `GET /channels` · `GET /channels/{id}/messages` (cursor) · `POST /channels/{id}/messages` · `GET /tasks` (süzgeç: sorumlu, durum, tarih, departman, bağlı kayıt) · `POST /tasks` · `POST /tasks/{id}` · `POST /tasks/{id}/complete` · `GET /departments` · `GET /refs?type=order&id=1045` ("bu kayıt hakkında ne konuşuldu") |
| 2 | `GET /schedule?user=…&from=…&to=…` (yük dahil) · `GET/POST /events` · `GET /conflicts` |
| 3 | Uygulama üyeliği uçları · komut kaydı |

**Webhook olayları:** `task.created`, `task.assigned`, `task.status_changed`,
`task.completed`, `message.created` (yalnız uygulamanın üye olduğu kanallar),
`app.mentioned` (uygulama `@` ile anıldığında — yapay zekânın kapısı).

Mevcut altyapı olduğu gibi kullanılır: HTTP Basic, idempotency, cursor
sayfalama, `X-Dry-Run`, OpenAPI, IIS için `POST` karşılıkları.

---

## 14. Mobil

Mevcut panel PWA'sı bu ekranları taşır; push hazır. Dar ekran düzeni: kanal
listesi ↔ akış ↔ ayrıntı üç sütun yerine tek sütunda geçişli. Ekip haftası dar
ekranda yatay kayar (kişi sütunu sabit). Ayrı mobil uygulama gerekirse API
zaten var.

---

## 15. Yapay zekâ için bugünden bırakılacak dikişler (ilk etapta yapay zekâ yok)

- Gönderen türü `user` / `app` / `system` — yapay zekâ bir **uygulama** olur.
- `app.mentioned` webhook + `POST /channels/{id}/messages` → kullanıcının
  kendi seçtiği dış servis kanalda yanıt verir. Pinegrap'a yapay zekâ kodu ve
  Kodpen'e bağımlılık girmez — açık kaynak ilkesiyle uyumlu.
- Komut defteri uygulamalara açılır (`/ozetle`, `/rapor`).
- İşlem yapma: uygulamanın yetkisi mevcut kapsamlar kadar; yazmadan önce
  `X-Dry-Run` → kanalda **onay kartı** ("3 siparişi Kargolandı yapacağım
  [Onayla]") → onaylanınca gerçek çağrı.

Artı: ilk etapta sıfır yapay zekâ maliyeti, kapı hazır. Eksi: kanal/mesaj
API'sini bot senaryosunu düşünerek tasarlamak küçük bir ek iş.

---

## 16. Veri modeli (taslak)

Zaman damgaları `INT UNSIGNED` unix (sohbet deseni), günler `DATE`
(boş = `'0000-00-00'`, kod tabanı karşılığı). Tablo adları `ws_` önekli.

| Tablo | Ana sütunlar |
|---|---|
| `ws_profiles` | `user_id` PK, `active`, `title`, `capacity_minutes`, `workdays` (bit maskesi) |
| `ws_departments` | `id`, `name`, `color`, `sort`, `archived` |
| `ws_department_members` | PK (`department_id`, `user_id`), `is_lead`, `is_primary` |
| `ws_channels` | `id`, `name`, `slug`, `kind` ENUM(`public`,`private`), `topic`, `owner_user_id`, `context_type`, `context_id`, `department_id`, `last_message_id`, `last_message_at`, `archived_at`, `created_at` |
| `ws_channel_members` | PK (`channel_id`, `member_kind`, `member_id`), `member_kind` ENUM(`user`,`app`), `role` ENUM(`owner`,`member`), `last_read_id`, `notify` ENUM(`all`,`mentions`,`none`), `joined_at` |
| `ws_messages` | `id`, `channel_id`, `parent_id`, `sender_kind` ENUM(`user`,`app`,`system`), `sender_id`, `kind` ENUM(`message`,`note`,`decision`,`task_card`,`system`), `body` TEXT, `task_id`, `attachment_file_id`, `edited_at`, `deleted_at`, `created_at`; KEY (`channel_id`, `id`) |
| `ws_refs` | `id`, `source_type`, `source_id`, `channel_id`, `ref_type`, `ref_id`, `created_at`; KEY (`ref_type`, `ref_id`), KEY (`source_type`, `source_id`) |
| `ws_tasks` | `id`, `number`, `title`, `description`, `channel_id`, `source_message_id`, `creator_id`, `assignee_id`, `department_id`, `status`, `priority`, `start_date`, `due_date`, `estimate_minutes`, `completed_at`, `completed_by`, `created_at`, `updated_at`; KEY (`assignee_id`, `status`, `due_date`), KEY (`department_id`, `status`), KEY (`channel_id`) |
| `ws_task_watchers` | PK (`task_id`, `user_id`) |
| `ws_events` | `id`, `kind` ENUM(`meeting`,`visit`,`leave`,`holiday`,`other`), `title`, `user_id` (0 = şirket), `department_id`, `starts_at`, `ends_at`, `all_day`, `created_by` |
| `ws_inbox` | `id`, `user_id`, `kind`, `channel_id`, `message_id`, `task_id`, `actor_id`, `created_at`, `read_at`; KEY (`user_id`, `read_at`) |
| `ws_channel_docs` | `channel_id` PK, `body`, `updated_by`, `updated_at` (sürüm geçmişi Faz 2) |
| `config` | `workspace_enabled`, `ws_task_sequence`, `ws_default_task_minutes`, `ws_upload_limit` |
| `user` | yalnız dört yetki sütunu: `manage_workspace`, `manage_workspace_assign`, `manage_workspace_board`, `manage_workspace_settings` (TINYINT) |

Migration: 2026.4.4 alt adımları **4.80–4.89** (ERP 4.58–4.69, API 4.70–4.79);
aralık `dev/_handoff/API-ERP-koordinasyon.md` tablosuna da işlenmeli ki paralel
ajanlar çakışmasın.

Görev geçmişi ekranı Faz 2'de `ws_task_history`; o zamana kadar `log_activity`.

---

## 17. Gerçek zamanlılık ve performans

- PHP + paylaşımlı hosting / IIS: WebSocket yok. SSE uzun süreli PHP süreci
  ister (FastCGI zaman aşımı, işçi tükenmesi) → hayır.
- **Poll, tek uç** `ws_sync`: açık kanalın yeni mesajları (`since_id`) + bütün
  kanalların okunmamış sayıları tek sorguda. Aralık sohbetteki gibi: ekran
  açık 4–5 sn, yazıyor 2 sn, arka planda 30–60 sn, gizli sekmede durur.
- Yük: 10 kişi × 5 sn ≈ 2 istek/sn (hafif); 50 kişi ≈ 10 istek/sn — `init.php`
  (WAF, oturum) maliyeti belirleyici. Faz 1 sonunda dev'de ölçülür.
- api.php büyütülmez: sohbetteki gibi tek satırlık dağıtıcı
  (`ws_handle_action`) → `includes/workspace/actions.php`. Rol 3 ekip üyesi bu
  uçları çağıracağı için api.php'deki genel rol kapısının muafiyet listesine
  **ve** modülün kendi izin kontrolüne yazılır.
- Arama: Faz 1'de `LIKE` + sınır; MySQL FULLTEXT Türkçe eklerde zayıf
  ("siparişi" ≠ "sipariş") — gerekirse Faz 2'de basit kök kırpma.

Artı: her hostingde çalışır. Eksi: 3–5 sn gecikme; yük kişi sayısıyla doğrusal.

---

## 18. Güvenlik ve KVKK

- Tek erişim fonksiyonları: `ws_can_read_channel($user, $channel)`,
  `ws_can_see_ref($user, $type, $id)`, `ws_can_see_task(...)`. Ekran, API,
  push, arama, ters kart, e-posta özeti, dosya kapısı — hepsi bunları çağırır
  (`pg_notification_visible()` deseni: bir kişinin zilinde görünmeyen şey
  telefonuna da düşmez).
- Çıktı escape; token → chip sunucuda; CSRF mevcut desenle; hız sınırı
  sohbetteki gibi.
- Denetim izi: görünürlük değişikliği, sahiplik devri, yöneticinin bireysel
  kanalı açması → `log_activity` + kanalda sistem mesajı.
- Saklama (K13): varsayılan süresiz (iş yazışması kurumsal kayıttır); ayarla
  kanal türü başına süre. Ayrılan çalışan: mesajları kalır, adı "eski üye"
  olarak görünür; kişisel DM'ler için ayrı kural düşünülebilir.
- Kabul testinde zorunlu: yetkisiz site üyesi (rol 3) ile her `ws_` ucu,
  `ws-` dosya adresi ve API'de 403/404.

---

## 19. Dosya yerleşimi (CLAUDE.md kuralına göre)

- Kök (yalnız URL ile çağrılanlar): `workspace.php` (kanallar), `ws_tasks.php`
  (Görevlerim), `ws_board.php` (plan panosu), `ws_settings.php` (departmanlar,
  profiller, ayarlar)
- `includes/workspace/`: `bootstrap.php`, `access.php`, `channels.php`,
  `messages.php`, `render.php`, `refs.php` (tür defteri), `commands.php`,
  `tasks.php`, `schedule.php` (yük + çakışma), `notify.php`, `files.php`,
  `actions.php`, `api.php`, `api_resources.php` — her birinde kapı sabiti
- `assets/js/workspace.src.js` + `.min.js`, `ws_board.src.js` + `.min.js`
- Menü: "Çalışma Alanı" slotu; üst çubukta okunmamış sayacı

---

## 20. Fazlar

| Faz | İçerik | API |
|---|---|---|
| **0 — iskelet** | Modül anahtarı, şema (2026.4.4, 4.80–4.89), dört yetki sütunu + kullanıcı ekranında yetki satırı + rol-3 merdiveni + sonda, departmanlar ve ekip profili ekranı | Modül dikişi, `GET /departments` |
| **1 — kanal + görev (MVP)** | Genel/bireysel kanal, bireysel → genel dönüşüm, mesaj + ek, `@` ve `#` etiketler (sipariş, ürün, kişi, cari, fatura, dosya, sayfa, görev), `/gorev` `/ata` `/bitti` `/karar` `/not`, canlı görev kartı, Görevlerim, Kararlar sekmesi, kanal Özeti, zil + push, ters kart 3 ekranda, kişi adından balona köprü | Kanal, mesaj, görev, refs uçları; `task.*`, `message.created` |
| **2 — plan panosu + çakışma** | Ekip haftası, kişisel/departman/şirket panosu, plan öğeleri (izin, toplantı), kapasite, çakışma uyarısı + alternatif kişi, sürükle-bırak, konu yanıtları, tepki, kanban, ters kartın kalan ekranları, kaydı izle, `/ertele` `/hatirlat` `/plan` | `schedule`, `events`, `conflicts` |
| **3 — botlar** | Uygulama üyeliği, komut defterinin API'ye açılması, ERP gecikmiş alacak → görev, bağımlılık, tasarımcıda görev paneli | Üyelik, komut kaydı, `app.mentioned` |
| **4 — yapay zekâ** | Ayrı karar: uygulama olarak yapay zekâ, onay kartları | — |

---

## 21. Bütün olarak artı / eksi

**Artılar**

- Veri zaten Pinegrap'ta: sipariş/fatura/ürün bağlamı, dışarıdaki bir Slack ya da
  Trello'nun yapamayacağı şey — asıl farklılaştırıcı.
- Tek oturum, tek yetki modeli, ek abonelik yok; veri kendi sunucusunda
  (KVKK, açık kaynak ilkesi).
- Altyapının büyük kısmı hazır: push, bildirim, API modül dikişi, olay
  kuyruğu, dosya, dry-run.
- Ürün için satış argümanı: e-ticaret + ERP + ekip tek pakette.

**Eksiler ve riskler**

- **Kapsam:** Slack + Asana + takvim. MVP disiplini şart; her şeyi Faz 1'de
  isteme riski en büyük risk.
- **Benimseme:** araç iyi olsa da not almayan kişi burada da almayabilir.
  Sürtünmesiz yollar (komut, "Bundan görev oluştur", ters kart, Kararlar) bunun
  için; yine de ekip içi bir kural gerekir ("müşteriyle ilgili karar kanala
  yazılır").
- **Gerçek zamanlılık:** poll ile 3–5 sn; büyük ekipte sunucu yükü.
- **Bakım:** sohbet balonu ayrı kaldığı için iki mesajlaşma sistemi kalıcı olarak
  yan yana (`chat.php` zaten ~3000 satır); ortak parçalar yardımcıya ayrılarak
  kopya kod önlenir.
- **Mobil:** PWA sınırları (iOS push için kurulu PWA gerekir).
- **Arama:** Türkçe ekler.
- **Depolama:** paylaşımlı hostingde dosya boyutu ve kota.

---

## 22. Karar bekleyenler

Karara bağlananlar işaretli; kalanların yanında önerim var — "önerilerle devam"
denirse bunlar geçerli olur.

| # | Soru | Öneri |
|---|---|---|
| K1 | Modül adı: Çalışma Alanı / Ekip / CRM | **Uygulandı:** Çalışma Alanı |
| K2 | Şema nereye | **Karar:** 2026.4.4'e alt adım, 4.80–4.89 |
| K3 | "Yeni roller" ne demek | **Karar:** rol numarası değişmez, yetki sütunları (§3.2) |
| K4 | Yönetici bireysel kanalın içeriğini görsün mü | **Karar:** görmez, gerekirse iz bırakarak açar |
| K5 | Genel → bireysel dönüşüm | **Uygulandı:** izin yok |
| K6 | Kişi birden çok departmanda olabilir mi | **Uygulandı:** evet, biri asıl (`is_primary`) |
| K7 | Çakışmada davranış; tahminsiz görev nasıl sayılır | **Uygulandı:** uyarı + seçenek; sert çakışmayı yalnız pano yetkisi, lider ve yönetici geçer; tahminsiz görev varsayılan 60 dk (ayarlanır) |
| K8 | Görev sorumlusu | **Karar (Erdal):** çok sorumlu — `ws_task_assignees`, herkes eşit sorumlu |
| K9 | Sohbet balonu | **Karar:** hep ayrı kalır (§4.4) |
| K10 | Rol 0–2 otomatik ekip üyesi mi | **Uygulandı:** evet, `ws_profiles.excluded` ile kapatılabilir |
| K11 | Müşteri kanalının bağlamı | **Karar (Erdal):** adres defterindeki kişi (`contacts.id`) |
| K12 | Konu (thread) yanıtları | **Uygulandı:** faz 2; faz 1'de alıntılı yanıt (`parent_id`) |
| K13 | Mesaj saklama | **Uygulandı:** süresiz (saklama süresi ayarı faz 2) |
| K14 | Ekip üyesi başkasına görev atayabilsin mi | **Uygulandı:** `manage_workspace_assign`; yetkisiz üye kendine ve liderliğini yaptığı departmana verir |
| K15 | Balondaki rol 3 ↔ rol 3 yasağı iki ekip üyesi (`manage_workspace`) arasında kalksın mı | **Karar (Erdal):** evet; ekip üyesi üyeye ve yöneticilere yazar, site üyeleri için kural aynen kalır |
| K16 | Departman lideri işareti olsun mu (yalnız kendi departmanında atama + pano) | **Uygulandı:** `is_lead` |
