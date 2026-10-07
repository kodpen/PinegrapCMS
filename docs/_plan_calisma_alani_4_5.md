# Çalışma Alanı — 2026.4.5 istekleri: plan (2026-09-26)

Erdal'ın 2026-09-26 listesi. Açık sürüm 2026.4.5; Çalışma Alanı şema adımları
**5.80–5.89** (dolunca 5.110–5.119), `upgrade_2026_4_5_workspace_<konu>()`.
Her tur: `docs/degisiklikler.md` (`## 2026.4.5 — …`) + `changelog.txt`
(`2026.4.5` → `ÇALIŞMA ALANI`) + şemada `docs/CLAUDE-tam.md` tablosu.

Sıra (Erdal: önce hızlı düzeltmeler, sonra programlanmış işlem):

| # | İş | Durum | Şema |
|---|---|---|---|
| 1 | Takvimde çok günlü görevler | bitti | — |
| 2 | Özet alanında @ / # | bitti | — |
| 3 | Gelen kutusu öbür ekranlarda | bitti | — |
| 4 | Yönetici yöneticinin özel kanalını açamaz | bitti | — |
| 5 | Not değişince / Claude yanıtlayınca listede işaret | bitti | — (gelen kutusu) |
| 6 | Kanalda aşağı ok + yeni mesaj sayısı | bitti | — |
| 7 | Soldaki listede Kanal ayarları | bitti | — |
| 8 | Düzenlendi etiketi, kimler gördü, yanıta git | bitti | — |
| 9 | Benden / herkesten sil, iz bırakmadan | bitti | 5.80 |
| 10 | **Programlanmış işlem** | bitti (e-posta gerçek gönderimi denenmedi) | 5.81 |
| 10b | Programlanmış işlem genişletme: sayaçlar, web kontrolü, webhook, özet, görev, bildirim, şablonlar; sonraki adımlar ve zincir (Erdal, 2026-09-26) | bitti (gerçek webhook, e-posta, beklemeli başlatma denenmedi) | 5.85 |
| 11 | Gelişmiş süzgeçler (aciliyet) + Görevlerim / Genel Bakış'ta acil | bitti | — |
| 12 | Genel ↔ özel dönüşüm (yönetici) | bitti | — |
| 13 | Konuşmayı temizle + geçmiş sürümler | bitti | 5.83 |
| 14 | Kanal renkleri, kanallar ile öbür sekmelerin ayrı rengi | bitti | 5.82 |
| 15 | Kanal grupları (iç içe, sürükle, onay, grup yetkisi) | bitti (yönetici olmayan görünümü, grup kaldırma denenmedi) | 5.84 |
| 16 | Çoklu seçim: toplu yanıt, başka kanala / nota iletme | bitti (yönetici olmayan görünümü denenmedi) | 5.87 |
| 17 | Başa tuttur (tek mesaj, kapatılınca bir daha yok) | bitti | 5.86 |
| 18 | Başlıklı bloklar + "Başka yerden çek" | bitti (not araç çubuğundan ve # ile çekme ekranda denenmedi) | 5.88 (dizin) |
| 19 | Müşteri: kullanıcı / cari / kişi | bitti (yetkisi olmayan görünümü denenmedi) | 5.89 |
| 20 | Baş harf avatarları + hızlı rehber düzenleme | bitti (2026-09-27), sandbox'ta ve dev'de denendi | — |
| 21 | Claude kurulum anlatımı görselli, arayüz adları İngilizce | bitti (2026-09-27): 6 adım, şematik çizimler; gerçek ekran görüntüleri Erdal'dan bekleniyor (`assets/images/ws-claude-setup-<adım>.png`) | — |
| 22 | Kayıtsız müşteri kanalı + tek seferlik / süreli shortlink | bitti (2026-09-27): kalıcı misafir odası, geçici bağlantı; genel kısa bağlantılar da tek seferlik / süreli (Dosya Yöneticisi). Sandbox'ta uçtan uca denendi (`docs/calisma-alani-misafir-kanal-tasarim.md`) | 5.112 |
| 23 | Görev e-posta hatırlatması (+ isteğe bağlı bitiş saati; tekrarda "her kopyada" sorusu) | bitti (2026-09-27), sandbox'ta gönderimle denendi; dev'de şema kurulu, kutu ve canlı ipucu denendi (kayıt yapılmadı, posta yok) | 5.111 |
| 24 | Kanalı board görünümünde açma | yalnız plan: `docs/calisma-alani-kanal-pano-plani.md` (2026-09-27) | — |
| 25 | **Pinegrap AI (@ai)**: ai.pinegrap.com'daki model, Claude ile aynı yetenekler, lisans anahtarı zorunlu (geçit yokken "denetliyormuş gibi") (Erdal, 2026-09-27) | bitti; dev'de şema kurulu, uçtan uca denendi (uzun kanalda zaman aşımı giderildi: ilk çağrıda bütçeli bağlam, 2026-09-27) | 5.110 |
| 26 | Görselli tanıtım turu (pg_tour motoru, 15 adım, çizimli) (Erdal, 2026-09-27) | bitti, sandbox'ta 15 adım gezildi | — (`user.tours_seen`) |
| 27 | Kişinin resmi / adı tıklanınca kullanıcı düzenleme (yetkisi yetene) (Erdal, 2026-09-27) | bitti, sandbox'ta denendi | — |

---

## 10. Programlanmış işlem

**Kararlar (Erdal):** yalnız yöneticiler (rol 0–2) oluşturur, düzenler, iptal
eder; menüyü yalnız onlar görür. En önemli iş.

**Yapı:** ad · kurallar (birden çok, hepsi sağlanmalı) · eylem (tek) · sonra
(tek eylem ya da hiçbir şey; yalnız eylem başarılıysa).

**Kurallar**

- `at` (zorunlu, tek): tarih + saat; tekrar: yok / her gün / iş günleri /
  her hafta / her ay.
- `record`: bir kaydın alanı karşılaştırılır — `changes.php`'deki tür kayıt
  defteri (sipariş, kişi, ürün, stok, cari…) ve görev durumu; işleç eşit /
  değil / boş / dolu / büyük / küçük.
- `workday`: yalnız iş günü (`ws_is_workday()` + tatiller).

Zamanı gelince koşul sağlanmazsa çalıştırma "atlandı" olarak kaydedilir.

**Eylemler** (eylem ve sonra için aynı liste)

- `post`: bir kanala mesaj (yazı kutusuyla: @, #, kontrol listesi, tablo).
  Oluşturanın adıyla gider (onun yetkisiyle yazabildiği kanal).
- `email`: adres(ler)e konu + metin **ya da** bir sayfa (`#sayfa`;
  `get_page_content(..., $email = true)` ile kampanyalardaki gibi HTML).
  Konu boşsa sayfanın başlığı.
- `change`: kayıt değişikliği — Claude önerilerinin motoru
  (`ws_changes_input()` doğrulama + `ws_change_write_<tür>()`): sipariş iptali,
  `contact.member_id = ''`, ürün alanı… Oluşturanın yetkisiyle; kaydedilirken
  bir kez, çalışırken yeniden doğrulanır. Kanal bağlamı varsa kilitli karar
  mesajı yazılır.

**Nerede görünür**

- Kanalda: yazı kutusunun + menüsünde "Programlanmış işlem" (yönetici).
  Kaydedince kanala anket gibi bir kart mesajı düşer (`ws_schedules.message_id`):
  yöneticiye tam (kurallar, eylem, durum, düzenle / duraklat / iptal / şimdi
  çalıştır), öbür üyelere yalnız ad, zaman, durum.
- Notta: notun araç çubuğunda aynı düğme; not bağlamı (`note_id`), notun
  üstünde yöneticiye kart şeridi.
- Kenar çubuğu → "İş ve plan" → **Programlanmış işler** (yönetici): hepsi,
  durum, sıradaki çalışma, geçmiş.

**Çalıştırma**

- `ws_schedules_run()`: süresi gelenler (`status = active`,
  `next_run_at <= now`), satır başına `running_at` ile kilit; oluşturanın o
  anki yetkisiyle; koşullar → eylem → sonra; `ws_schedule_runs`'a kayıt;
  tekrarlıysa sıradaki zaman, değilse `done` / `failed`.
- Tetikler: `ws_tick` (ekran açılışı), `ws_sync` "süresi gelen var" der →
  ekran `ws_tick`'i ateşler (kanal açıkken saniyeler içinde), genel görev
  `job.php` her turunda (2026-10-04'ten beri; dakikada bir zamanlanmışsa en
  çok bir dakika gecikme) ve `workspace_recurring_job.php` (5 dakikada bir;
  önceden saatlikti ve tek zamanlanmış yol oydu).
- Sonuç: bağlam kanalına kartın altına sistem satırı; kart güncellenir;
  başarısızlıkta oluşturana gelen kutusu satırı.

**Şema 5.81:** `ws_schedules` (ad, bağlam kanal / mesaj / not, oluşturan,
`rules` / `action` / `then_action` JSON, durum, `next_run_at`, `last_run_at`,
`run_count`, `running_at`, zaman damgaları; `idx_due (status, next_run_at)`),
`ws_schedule_runs` (iş, başlangıç / bitiş, durum done / skipped / failed,
adım ayrıntısı JSON, yazılan mesaj).

---

## 11–24 kısa notlar

- **Süzgeçler:** Plan Panosu ve İş Takvimi'ne aciliyet (acil / yüksek /
  normal / düşük), durum, kanal, "yalnız bana atananlar"; Görevlerim'de "Yaklaşan
  acil" hızlı süzgeci; Genel Bakış'ta yaklaşan görevler aciliyete göre (acil
  ve yüksek önce, sonra bitiş).
- **Genel ↔ özel:** `ws_channel_make_public` var; tersini ekle, ikisini de
  yalnız yöneticiye (rol 0–2). Özele dönerken üyeler kalır, yazmış olanlar
  üye yapılır mı: sorulacak.
- **Temizle:** konuşma bir "geçmiş sürüm"e taşınır (`ws_channel_eras` gibi
  bir tablo + `ws_messages.era_id`); ayarlarda sürümler listelenir, açılınca
  salt okunur.
- **Renk ve gruplar:** 20 mat renklik sabit palet; `ws_channels.color`,
  `ws_channel_groups` (ad, renk, `parent_id`, sıra), `ws_channels.group_id`.
  Taşımadan önce ne olacağını anlatan onay. Gruba verilen yetki alt grup ve
  kanallara, özel olsa da; verilirken "N alt grup ve M kanal" uyarısı.
- **Çoklu seçim:** seçim kipi; yanıt birden çok mesaja
  (`ws_message_replies` tablosu); iletilen mesajda asıl yazan, zaman, kaynak
  kanal; kaynağı göremeyen açamaz.
- **Başa tuttur:** kanalda tek mesaj (`ws_channels.pinned_message_id`), brief
  altında benzer renkte; kişi kapatırsa (`ws_pin_dismissals`) bir daha yok.
- **Başlıklı bloklar:** blok çitine başlık (` ```tablo Başlık `); "Ekle →
  Başka yerden çek": görebildiği kanal ve notlardaki başlıklı blokları arar
  (başlık, kanal adı, yazan), seçileni ekler. `#` ile çekme ikinci tercih.
- **Müşteri:** `ws_channels.customer_type` (user / erp_account / contact) +
  id; eşleşen hesap–cari–kişi bağlantıları kartta.
- **Avatar:** kişisi olan kullanıcıda ad-soyad baş harfi, olmayanda kullanıcı
  adından (başka zemin / kare köşe); `assets/img` sabit avatar seçimi rafa.
  Sağ üst menüde eksik rehber bilgisi için hızlı düzenleme.
- **Claude kurulumu:** adımlar görselli; claude.ai arayüzündeki düğme ve menü
  adları İngilizce asıl hâliyle.
- **Kayıtsız müşteri kanalı:** yeni kanal türü `guest`; yalnız yöneticiler
  görür; müşteri tek seferlik ya da süreli kısa bağlantıyla (token) girer,
  yalnız yazı, ifade ve yanıt; başka kanal görmez. `short_links`'e tür + token
  + son kullanma.
- **Görev hatırlatması:** görevde e-posta hatırlatması açılır; kaç saat /
  dakika önce; tekrarlayan görevde her kopyada mı.
- **Board görünümü:** kanalın görevleri, notları ve kararları sütunlarda
  (duruma / türe göre gruplanabilir); ayrı plan yazılacak.
