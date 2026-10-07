# Dış API — mobil / ekip cihazı yetenekleri (2026.4.5)

Durum: A, B, C uygulandı ve dev'de denendi (2026-09-27); gerçek cihaz girişi Erdal'ın denemesini bekliyor. Sahibi: API (includes/api/**, api_settings.php). Ayrıntı: `docs/degisiklikler.md` → "Dış API: ekip cihazı oturumu…".

## Karar özeti (Erdal, 2026-09-27)

| Soru | Cevap |
|---|---|
| Uygulamayı kim kullanacak | Ekip / panel kullanıcıları (müşteri uygulaması değil) |
| Oturum | Panel girişi + cihaz jetonu |
| Yetenek kapsamı | "Mobil uygulama için plan belli değil" → uygulamaya özel uç yazılmaz; **tüm uçlara genel yetenek** eklenir |
| Bildirim | Mevcut web push + API ucu |

Üçüncü cevabın yorumu: belli bir ekranın ucu (ör. "mobil ana sayfa özeti") yazılmaz.
Bunun yerine her istemcinin ihtiyaç duyacağı ortak yetenekler eklenir: kişi adına
oturum, "ben kimim / neyi görebilirim", bildirim + push kaydı, koşullu GET (ETag/304),
seçili alan (fields=), tutarlı `updated_since`.

## A. Cihaz oturumu (kişi adına)

- Operatör API ayarlarında **"Ekip cihazları"** türünde bir uygulama açar. Bu tür:
  - gizli anahtar taşımaz (Basic ile çağrılamaz); anahtarı gizli değildir,
  - kapsamları cihaz oturumlarının **tavanıdır**, durumu (etkin/kapalı) aç-kapa düğmesidir.
- `POST /auth/login` {username, password, device_name, platform?, app_version?, client_key?}
  - `client_key` yoksa sitenin tek/ilk etkin "Ekip cihazları" uygulaması kullanılır →
    genel bir mobil uygulama yalnız site adresi + kullanıcı adı + parola ile bağlanır.
  - Giriş ekranının kilidi aynen uygulanır (`pg_login_throttle_*`); soru (captcha) eşiği
    geçilmişse API 429 döner — API, giriş ekranından zayıf bir kapı olamaz.
  - Yalnız panel hakkı olan hesap girer (meta:read dışında en az bir kapsam).
- Jeton: erişim `pgat_…` (1 saat), yenileme `pgrt_…` (30 gün, her yenilemede döner).
  Yalnız HMAC özetleri saklanır (`api_secret_hash`). Eski yenileme jetonu 60 sn içinde
  tekrar gelirse yeniden döndürülür (ağ kopması), sonra gelirse cihaz kapatılır (çalıntı).
- `Authorization: Bearer pgat_…` → istek **o kişi olarak** çalışır:
  `api_current_app()` = cihaz uygulaması satırı, `owner` = giriş yapan kişi.
  Etkin kapsam = uygulama kapsamları ∩ kişinin devredebildiği kapsamlar
  (`api_owner_scopes`) − `webhooks:manage` + `account:*`.
- Hız sınırı cihaz başına (`api_device_rate`), tavan uygulamanın dakika sınırı.
- Idempotency anahtarı cihaz başına ad alanında (sha256('device:<id>:<key>')).
- Parola değişince / "her yerden çık"ta cihaz oturumları da kapanır
  (`pg_auth_token_revoke_user` kancası).
- Uçlar: `/auth/login`, `/auth/refresh`, `/auth/logout`, `/auth/me`,
  `/auth/devices`, `DELETE|POST /auth/devices/{id}(/delete)`.
- Panel: uygulama çekmecesinde "Cihazlar" sekmesi (kim, hangi cihaz, son kullanım, kapat).

## B. Bildirim + push

- Kapsam grubu `account` (read/write): kişinin kendi bildirimleri, cihazları, push kaydı.
  Cihaz oturumunda her zaman vardır; sunucu uygulamasına operatör verir.
- `GET /notifications` (unread, cursor), `GET /notifications/unread-count`,
  `POST /notifications/read` {ids | all}, `POST /notifications/unread` {ids}.
  Görünürlük tek yerden: `pg_notification_visible()`.
- `GET /push/config`, `POST /push/subscriptions` (endpoint, p256dh, auth, old_endpoint), `POST /push/subscriptions/delete`,
  `POST /push/test`. Push gövdesizdir; istemci uyanınca `GET /notifications?unread=1` okur.
  Cihaz oturumundan yapılan kayıt `auth_selector = 'api:<cihaz>'` taşır → cihaz kapanınca
  kayıt da silinir.

## C. Tüm uçlara genel yetenek

- **ETag / If-None-Match → 304**: her başarılı GET cevabına zayıf ETag; aynıysa gövdesiz 304.
  `Cache-Control: no-store` kalır (paylaşılan önbellek sızıntısı yok; istemci kendi saklar).
- **fields=**: `?fields=id,name,price` — liste (`data[]`) ve tek kayıt cevaplarında üst düzey
  alanları süzer; `id` her zaman kalır.
- **updated_since**: form gönderimleri ve Çalışma Alanı notları; kanallarda `active_since`. Müşterilere eklenmedi (`contacts.timestamp` her yazarda güncellenmiyor).
- **Güvenlik düzeltmesi**: `api_load_owner()` evet/hayır bayraklarını mantıksal değere çevirir
  (`'no' == true` PHP'de doğru: rolü Kullanıcı olan hesap e-ticaret/kişi/form kapsamı alıyordu).

## Ertelenenler

- Toplu istek (batch), silinen kayıt listesi (tombstone), sıralama (`sort=`).
- CORS izin listesi (web tabanlı mobil kabuk için) — gerekirse cihaz uygulamasına alan.
- Yerel FCM/APNs push (şimdilik web push).
- ERP uçlarına `updated_since` (ERP dosyaları ERP'nin).

## Şema (5.71, `upgrade_2026_4_5_api_devices`)

- `api_apps.kind` ENUM('server','device') DEFAULT 'server'
- `api_devices` (id, app_id, user_id, name, platform, app_version, access_hash, access_expires,
  refresh_hash, refresh_expires, refresh_prev_hash, refresh_rotated_at, created_timestamp,
  last_used_timestamp, last_used_ip)
- `api_device_rate` (device_id, window_start, hits)
