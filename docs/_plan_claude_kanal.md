---
name: _plan_claude_kanal
description: Calling Claude into a Workspace channel via a Claude routine (Phase 1 done); scope decisions and leftovers
---

# Çalışma Alanı — Claude'u kanala çağırmak (Faz 1)

**Durum (2026-10-14):** 1.0–1.5, 1.7 ve onaylı değişiklik taslağı (1.8) bitti; taslaklar ve
ilk istek dev.pinegrap.com'da gerçek rutinle uçtan uca yanıtlandı. Ayrıntı
`docs/degisiklikler.md` → "kanalda Claude, Faz 1 (4.87)". Kapsam kararı (Erdal): yalnız
Faz 1; Claude Console API anahtarıyla değil, Claude aboneliğiyle (rutin + API tetikleyicisi)
çalışır, bağlantı site geneli tektir. Biten bölümler çıkarıldı; tam metin: `git show d9fec3a:docs/_plan_claude_kanal.md`.
İlgili: `docs/_plan_calisma_alani.md` (modül). Claude Code kanalıyla anlık bağlantı (rutinin yanına ikinci
bağlantı türü): `docs/_plan_mobil_api_claude_kanali.md` → B bölümü.

## Kalanlar

1. 1.6'nın gerçek rutinle kalanı (dev.pinegrap.com, Erdal + Claude): iki istek tek
   çalıştırmada, enjeksiyon denemesi, özel kanalda kapı. 2026-09-23'te yalnız localhost'ta
   elle taklit edilen rutinle denendi.
2. HTTP 429'un kendisi: günlük hak dolunca bekleme, gerçek rutinle.

**1.0 + 1.6 için (dev.pinegrap.com):**

1. Güncel kodu koy, 2026.4.4 yükseltmesini ya da `_seed_ws_schema.php`'yi koştur.
2. Uygulama Erişimi → "Claude" uygulaması (Çalışma Alanı + Görevler okuma/yazma, okunacak
   kayıtlar); anahtar ve gizli değeri Erdal saklar.
3. Çalışma Alanı Ayarları → "Kanallarda Claude": uygulamayı seç, istemi ve alan adını kopyala.
4. claude.ai/code/routines → New routine → Cloud (masaüstünde eski sürümlerde sayfanın adı
  "Scheduled"); istem, model, ortam (Custom ağ + alan adı + iki değişken), bağlayıcılar
  çıkarılır, API tetikleyicisi → adres + belirteç karta.
5. "Bağlantıyı dene", sonra #genel'de `@Claude`: süre, WAF (Anthropic çıkışı), 👀/✅,
  taslaklar, iki istek tek çalıştırmada, enjeksiyon denemesi, özel kanalda kapı.

---

## 1. Sınırlar

### Bilinmesi gereken sınırlar

- **Günlük çalıştırma sınırı** (Nisan 2026 duyurusu, değişebilir): Pro 5,
  Max 15, Team/Enterprise 25 çalıştırma/gün. Ek kullanım (usage credits)
  açıksa aşım ücretli devam eder. Tek seferlik zamanlanmış çalıştırmalar
  sayılmaz, API tetiklemesi sayılır.
- **Gecikme:** her tetikleme yeni bir bulut oturumu açar. Yanıt saniyeler
  değil, 1–3 dakika mertebesinde. Sohbet değil, "iş ver, sonucu kanala
  yazsın" kullanımı.
- **Research preview:** `/fire` uç noktası `experimental-cc-routine-2026-04-01`
  beta başlığıyla çalışıyor; biçim değişebilir. Kırıcı değişiklikler yeni
  tarihli başlıkla gelir, önceki iki başlık bir süre çalışır.
- **Site internetten erişilebilir olmalı:** rutin Anthropic'in ağından
  çağırır. `localhost` çalışmaz; deneme `dev.pinegrap.com` üzerinde yapılır.
- **Kimin hesabı:** site geneli tek bağlantı seçildi. Tüm `@Claude`
  çağrıları rutini kuran yöneticinin aboneliğinden ve günlük sınırından
  düşer. Oturumlar ve bağlayıcı işlemleri o kişi adına görünür.
- **API kimliği:** Pro/Max planlarında rutin ortamına "API credential"
  eklenir; anahtar oturuma hiç görünmez, yalnız listelenen alan adına giden
  isteğe ekleyen vekil sunucu ekler. Team/Enterprise'da bu özellik henüz yok.
  Orada anahtar ortam değişkenine girer ve ortamı kullanan herkes okuyabilir.

---

## 4. claude.ai tarafı (bir kez, yönetici yapar)

1. `claude.ai/code/routines` → **New routine**. Ad: "Pinegrap Çalışma
   Alanı". İstem Pinegrap'taki karttan kopyalanır. Model seçilir.
2. **Repository:** rutin bir GitHub deposu isteyebilir (belgede "one or
   more repositories"). Gerekiyorsa küçük bir depo: `CLAUDE.md` (kurallar)
   + `pg.sh` (cURL sarmalayıcı). Faz 1.0'da doğrulanacak.
3. **Environment:** yeni ortam. Network: **Custom**, izinli alan adı site
   (`dev.pinegrap.com`). **API credential** (Pro/Max): host = site;
   header `Authorization`, prefix `Basic`, değer = `base64(anahtar:gizli)`.
   Pinegrap API'si HTTP Basic kabul ediyor. Team/Enterprise'da ortam
   değişkeni kullanılır (§1'deki uyarı).
4. **Connectors:** varsayılan olarak hepsi eklenir; hepsi çıkarılır.
   Pinegrap'a erişim HTTP ile olur.
5. **Trigger → API → Generate token:** adres ve belirteç Pinegrap'taki
   karta yapıştırılır. Belirteç bir kez gösterilir.

Kimlik bilgilerini (API anahtarı, rutin belirteci) Erdal girer. Pinegrap
yalnız dar kapsamlı uygulamayı oluşturur ve saklar.

---

## 6. Güvenlik

- **Sızan tetikleme belirteci:** yalnız o rutini başlatır. Yük içeriksiz ve
  güvenilmeyen olarak sarmalanır. En kötü durumda günlük hak harcanır.
  Kartta "Yeniden üret" hatırlatması olur (claude.ai'da Regenerate).
- **İstem enjeksiyonu (kanal içeriği):** Claude'un eli API kapsamlarıyla
  sınırlı: `workspace:read/write`, `tasks:read/write` ve seçilirse kayıt
  okuma. Silme ucu yok. Yanıtlar uygulama adıyla işaretli, `api_request_log`
  her çağrıyı tutar.
- **Özel kanallar:** içerik Anthropic'e, yöneticinin hesabı üzerinden gider.
  Varsayılan kapalı; kanal sahibinin açması gerekir.
- **WAF:** yerleşik güvenlik duvarı Anthropic çıkışlı istekleri engellememeli
  (dev'de cURL engellenmişti). API anahtarlı istek yolu denenip gerekiyorsa
  `integration.php` için kural açılır.
- **Belirteç saklama:** `config.ws_claude_token` şifreli saklanır. Ayarlar
  ekranı değeri geri göstermez.

---

Kaynaklar: Claude Code "Automate work with routines" belgesi, "Trigger a
routine through the API" (Claude Platform), "Introducing routines in Claude
Code" duyurusu, Agent SDK genel bakış (üçüncü taraf claude.ai girişi notu),
"Configure cloud environments" (API credentials).
