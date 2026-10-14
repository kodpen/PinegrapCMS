# Dış API — mobil / ekip cihazı yetenekleri (2026.4.5)

**Durum (2026-10-14):** A (cihaz oturumu), B (bildirim + push) ve C (ETag / 304, `fields=`,
`updated_since`) bitti ve dev'de denendi (2026-09-27). Ayrıntı `docs/degisiklikler.md` →
"Dış API: ekip cihazı oturumu, bildirim/push uçları, ETag ve fields=". Biten bölümler
çıkarıldı; tam metin: `git show d9fec3a:docs/_plan_api_mobil.md`. Sahibi: API (`includes/api/**`, `api_settings.php`).

**Devamı (2026-10-14):** mobil uygulamanın Çalışma Alanı ihtiyaçları ve aşağıdaki kalanlar
`docs/_plan_mobil_api_claude_kanali.md` → A bölümünde dilimlere bölündü.

## Kalanlar

1. Gerçek cihazdan giriş: Erdal'ın denemesi bekleniyor.
2. Toplu istek (batch).
3. Silinen kayıt listesi (tombstone).
4. Sıralama (`sort=`).
5. CORS izin listesi (web tabanlı mobil kabuk için); gerekirse cihaz uygulamasına alan.
6. Yerel FCM / APNs push (şimdilik web push).

Ertelenenlerden ERP uçlarına `updated_since` geldi (cari kartları, faturalar).

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
