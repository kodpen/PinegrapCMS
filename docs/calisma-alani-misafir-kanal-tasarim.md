# Çalışma Alanı — kayıtsız müşteri (misafir) görüşmesi ve tek seferlik / süreli kısa bağlantı

Durum: **yapıldı** (plan maddesi 22, şema 5.112 `upgrade_2026_4_5_workspace_guests()`,
2026-09-27). İlk sürüm öneri olarak yazılmıştı; Erdal'ın kararları aşağıda,
kod bu kararlara göre kuruldu.

## 1. Kararlar (Erdal, 2026-09-27)

| Soru | Karar |
|---|---|
| Ayrı tablo mu, `short_links` mi? | **`short_links`.** Kısa bağlantı mekaniği kullanılır; bu sırada genel kısa bağlantılar da tek seferlik / süreli olabilir hâle gelir. |
| Tek seferlik ne demek? | Adres girilmez, **token üretilir**; bağlantıyla siteye bir kez ulaşılınca bağlantı bir daha işe yaramaz. |
| Süreli bağlantının süresi | **Oluşturulurken seçilir** (1 saat, 1 gün, 3 gün, 1 hafta, 30 gün, 90 gün). |
| Bulmaca (captcha) | **Yok.** Bağlantıyı rastgele biri değil, yalnız yöneticiler üretir. |
| Nereden üretilir? | Genel tek seferlik / süreli kısa bağlantı: **Dosya Yöneticisi › Yeni › Kısa Bağlantı**. Misafir bağlantısı: **yalnız Çalışma Alanı'ndan** (Kısa Bağlantı Ekle'den değil); yalnız mekanik ortak. |
| Misafir dosya gönderebilir mi? | **Hayır.** |
| Eski konuşmayı görür mü? | **Hayır.** Misafir yalnız kendi odasını görür (bkz. 3). |
| Yazma alanı | Yalnız kalın, eğik, üstü çizili gibi basit biçimler; etiket, komut, blok yok. |
| E-posta bildirimi | **Yok.** Misafire posta gitmez. |
| Var olan bir odaya çağrılır mı? | **Hayır.** Misafire kendi odası açılır; oda kalıcıdır, geçici olan misafirin giriş yoludur (bkz. 2). |

## 2. Model: kalıcı misafir odası, geçici bağlantı

- Personel (rol 0–2) **"Misafirle görüşme başlat"** der (Genel Bakış'taki
  başlat düğmelerinde, yeni kanal formunun altındaki bağlantıda). Misafirin
  adı, isteğe bağlı konu, bağlantı türü (tek seferlik / süreli + süre) ve
  isteğe bağlı odadaki personel seçilir.
- Oda: `ws_channels.kind = 'guest'`, adı **"Misafir · <ad> · <konu>"**,
  içinde yalnız personel (rolü 3 olan eklenemez). Claude ve Pinegrap AI bu
  odada hiç sorulmaz.
- Bağlantının adresi **bir kez** gösterilir (kopyala düğmesiyle); personel
  misafire kendisi gönderir. Veritabanında yalnız SHA-256 özeti ve ilk 6
  karakteri durur.
- **Görüşmeyi bitir:** misafirin bağlantıları ve oturumları kapanır, oda
  **arşivlenir** (silinmez, kayıt olarak kalır).
- **Yeni bağlantı:** önceki bağlantıyı ve onunla giren tarayıcıları kapatır;
  bitmiş görüşmenin odasını yeniden açar (kaybolan telefon için de budur).
- İç tartışma misafir odasında yapılmaz: misafirin mesajları seçilip
  personelin kendi kanallarına **iletilir**. Misafir odasına ileti
  yapılamaz (iç kanal adları ve içerik taşınmasın).
- Oda başında şerit: misafirin adı, bağlantının durumu (açılmayı bekliyor /
  şu tarihte açıldı / şu tarihe kadar geçerli / kapalı), "Şu an burada" ya
  da son görülme, "misafir odaya eklenen her şeyi görür, hiçbirini
  ekleyemez" notu, "Yeni bağlantı" ve
  "Görüşmeyi bitir". Şerit kanalın yoklamasıyla canlı yenilenir. Yazı
  kutusunun kenarı sarıdır.

## 3. Misafirin gördüğü ve yapabildiği

- Sayfa: `workspace_guest.php` (panelden bağımsız, sade, telefona uygun,
  açık/koyu tema cihaza göre). Başlıkta site adı, konu, odadaki personelin
  resimleri.
- **Sohbet motoru aynı** (Erdal, 2026-09-27): misafir kendi odasını
  personel gibi görür. Personelin mesajları personel ekranındaki
  `ws_render_body()` ile çizilir (tablolar, hesaplı hücreler, kod,
  başlıklı bloklar, kontrol listeleri); personelin eklediği resim, video,
  ses ve dosyalar (`workspace_guest.php?file=<mesaj>`, yalnız o odanın
  misafirine), oylamalar ve görev kartları (okunur) görünür. Yanıtlanan
  mesajın özeti ve ifadeler de. Sistem satırları gösterilmez. Personelin
  `@kişi` etiketi yalnız ad olarak, `#kayıt` etiketi yalnız türüyle
  (`[Sipariş]`) okunur; panele bağlantı yoktur.
- Misafir **oy verir** (`ws_poll_votes.guest_id`) ve kontrol listesini
  **işaretler** (`ws_checks.guest_id`; görevlere dönüşmüş liste hariç,
  onun işaretleri görevleri ilerletir). Personelin ekranında oy ve işaret
  misafirin adıyla görünür.
- Oda yalnız bu görüşmeye ait olduğu için "eski konuşmayı görmez" kararı
  kendiliğinden sağlanır: misafir başka hiçbir kanalı, kişiyi, kaydı görmez.
- Ekleyemediği: dosya, resim, tablo, oylama, görev, etiket, komut. Yazabildiği:
  düz metin, **kalın** (`**`), _eğik_ (`_`), ~~üstü çizili~~ (`~~`) —
  personelin ekranıyla aynı işaretler; mesaj yanıtlama; sekiz ifadeden
  biri. Yazdığı her iki ekranda da bu kısıtlı biçimle çizilir
  (`ws_guest_render()`): tablo yazsa düz metin kalır. En çok 2000 karakter. Etiket işaretleri (`<@…>`, `<#…>`),
  başlıklı blok, kod bloğu, `/komut` misafirden gelince ayıklanır; `<` `>`
  zararsız karakterlere çevrilir.
- Hız sınırı: mesajlar arası 1 sn, dakikada 20, günde 300.
- Yoklama 4 sn (sekme arka plandayken 15 sn); sunucu her seferinde
  mesajların imzasını karşılaştırır, değişiklik yoksa mesaj göndermez.
- Personel misafirin mesajını silebilir (rol 0–2); silinen mesaj misafirin
  sayfasından da kalkar.

## 4. Bağlantı ve oturum güvenliği

| Tehdit | Önlem |
|---|---|
| Token tahmini / tarama | 32 bayt rastgele (43 karakter base64url); geçersiz/harcanmış token ile açılış WAF'a suç (`waf_register_offence`, ağırlık 5); açılış IP başına dakikada 10 (`waf_rate_exceeded(…, 'ws_guest_open', 10, 60)`) |
| Bağlantının sızması | Tek seferlik: ilk açan tarayıcı sahiplenir (koşullu `UPDATE … WHERE used_at = 0`), sonra bağlantı ölür, misafir o tarayıcıdaki çerezle sürer |
| Önizleme botunun bağlantıyı harcaması | Router token'ı `workspace_guest.php#t=…` olarak "#" arkasına taşır; sayfa token'ı POST ile talep eder. Adresi çeken önizleme hiçbir şey harcamaz |
| Adres çubuğu, geçmiş, Referer, sunucu günlüğü | Sayfa token'ı okur okumaz adresten siler (`history.replaceState`); `Referrer-Policy: no-referrer`, `Cache-Control: no-store`, `X-Robots-Tag: noindex`, `X-Frame-Options: DENY` |
| Veritabanı sızıntısı | Token da oturum çerezi de yalnız SHA-256 özetiyle saklanır |
| Panel yetkisine sıçrama | Misafir çerezi `pg_ws_guest` (HttpOnly, SameSite=Lax, https'te Secure) hiçbir panel/`api.php` eylemini açmaz; misafir yalnız `workspace_guest.php`'nin dört eylemine erişir |
| CSRF | Oturuma bağlı ayrı `csrf` (`hash_equals`) |
| Kapatılan bağlantının çalışması | Her istekte oturum → bağlantı → misafir → oda zinciri yeniden okunur (bitmiş görüşme, arşivlenmiş oda, süresi dolmuş/kapatılmış bağlantı oturumu kapatır) |
| XSS | Misafir metni HTML olarak saklanmaz; ekrana `htmlspecialchars` sonrası yalnız `<strong>`, `<em>`, `<del>`, `<a rel="nofollow noopener noreferrer">`, `<br>` olarak çıkar |

Oturum süresi: süreli bağlantıda bağlantının bitişi; tek seferlikte 30 gün
kullanılmazsa biter (her kullanımda uzar).

## 5. Genel kısa bağlantılar (Dosya Yöneticisi)

- Kısa Bağlantı sihirbazında **"Açılma şekli"**: Kalıcı (bugünkü gibi) /
  Tek seferlik (adres otomatik üretilir, yalnız ilk ziyarette çalışır) /
  Süreli (adı siz verirsiniz, seçtiğiniz süreye kadar geçerli).
- Tek seferlik bağlantının tam adresi oluşturulunca **bir kez** gösterilir;
  listede ilk 6 karakteriyle (`pKl6J8…`) durur, adı değiştirilemez,
  kopyalanamaz (yeni bir tane oluşturulur), "Ziyaret et" adresi bilmez.
  Hedefi değiştirilebilir.
- Süreli bağlantı düzenlenirken **"Yeni geçerlilik süresi"** ile şimdiden
  itibaren uzatılabilir; çoğaltılınca süresiyle çoğalır.
- Önizleme panelinde durum (tek seferlik: ilk ziyarette çalışır / şu tarihte
  açıldı; artık çalışmıyor / şu tarihe kadar geçerli / süresi doldu) ve
  açılma sayısı.
- Router: süresi dolmuş ya da kullanılmış bağlantı `short_link_gone.php`'ye
  gider (sitenin hata sayfası, 410). Tek seferlik bağlantıyı bot / önizleme
  / HEAD isteği harcamaz (`waf_classify_bot` "human" değilse boş sayfa).
  Kalıcı olmayan bağlantının URL yönlendirmesi 301 değil 302'dir (tarayıcı
  kalıcı taşınma diye aklında tutmasın).
- Elle ekleme sayfası (`add_short_link.php`, Dosya Yöneticisi menüsündeki
  "Kısa Bağlantı Oluştur") aynı "Açılma şekli" seçimini taşır: tek
  seferlikte ad kutusu gizlenir, tam adres oluşturulunca listedeki
  bildirimde bir kez gösterilir; süreli için süre seçilir.
- Klasik "Kısa Bağlantılar" listesi token adresli bağlantıları ilk
  karakterleri ve durumlarıyla gösterir (ziyaret düğmesi yok);
  `edit_short_link.php` böyle bir bağlantıyı Dosya Yöneticisi'ne yollar.
  Editörün bağlantı seçicisi, panel araması ve son değişenler adı boş
  bağlantıları göstermez. Misafir bağlantıları (`destination_type =
  'workspace_guest'`) hiçbir kısa bağlantı listesinde yoktur.

## 6. Şema (5.112)

- `short_links`: `link_mode` ENUM('permanent','once','timed'), `token_hash`
  CHAR(64) + `idx_token`, `token_hint`, `expires_at`, `used_at`,
  `use_count`, `last_used_at`, `ws_guest_id` + `idx_ws_guest`;
  `destination_type`'a `'workspace_guest'`.
- `ws_channels.kind`'a `'guest'`; `ws_messages.sender_kind`,
  `ws_reactions.sender_kind`, `ws_message_forwards.source_sender_kind`'a
  `'guest'` (`sender_id` = `ws_guests.id`).
- `ws_poll_votes.guest_id` (birincil anahtar `poll_id, option_id, user_id,
  guest_id` oldu) ve `ws_checks.guest_id`: kullanıcının oyu/işareti
  `guest_id = 0`, misafirinki `user_id = 0`.
- Yeni tablolar `ws_guests` (id, channel_id, name, created_by, created_at,
  ended_at, ended_by, last_seen_at) ve `ws_guest_sessions` (guest_id,
  link_id, session_hash UNIQUE, csrf, ip (ilk üç sekizli), user_agent_hash,
  created_at, last_seen_at, expires_at, ended_at); ikisi de
  `install/index.php` `get_tables()`'ta.
- Enum'lar değeri yoksa genişletilir (ikinci koşu tabloya dokunmaz).

## 7. Dosyalar

- `includes/workspace/guests.php` (yeni): oda, bağlantı, oturum, misafir
  istekleri (`ws_guest_handle()`), metin temizleme ve gösterim.
- `workspace_guest.php`, `assets/js/workspace_guest.js`,
  `assets/css/workspace_guest.css` (yeni): misafirin sayfası.
- `router.php` (`router_short_link_gate()`), `short_link_gone.php` (yeni),
  `includes/fn/core.php` (`pg_short_link_modes_ready()`,
  `pg_short_link_new_token()`, `pg_short_link_durations()`,
  `pg_short_link_state()`).
- Dosya Yöneticisi: `view_folder_and_files_f.php`, `view_folders.php`.
- Çalışma Alanı: `channels.php`, `messages.php`, `interact.php`,
  `notify.php` (gelen kutusunda `guest_message`), `forward.php`,
  `claude.php`, `ai.php`, `groups.php`, `customer.php`, `pins.php`,
  `home.php`, `api_resources.php`, `actions.php` (`ws_guest_start`,
  `ws_guest_relink`, `ws_guest_end`), `screen.php`, `workspace.js`,
  `backend.src.css`; `api.php` izin listesi.

## 8. Açık kalanlar / sonraki adım

- Oda bir müşteriye (kişi / kullanıcı / cari, 5.89) kanal ayarlarından
  bağlanabilir; misafir adıyla eşleştirme elle yapılır.
- Süresi dolmuş misafir oturumlarının süpürülmesi (satırlar küçük; ileride
  bakım işine eklenebilir).
