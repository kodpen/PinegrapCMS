# Pinegrap AI geçidi — Pinegrap'ın beklediği yanıtlar

Pinegrap'taki `@ai` (Çalışma Alanı, `includes/workspace/ai.php`) modele
`https://ai.pinegrap.com/v1` üzerinden OpenAI uyumlu API ile gider. Bugün bu
adreste doğrudan LM Studio sunucusu duruyor (Cloudflare Tunnel, geçit yok).
Bu belge, önüne konacak lisans geçidinin (proxy) ne yapması gerektiğini
anlatır: Pinegrap tarafı bugünden bu sözleşmeye göre yazıldı, geçit gelince
Pinegrap'ta değişiklik gerekmez.

## Pinegrap'ın her istekte gönderdikleri

| Başlık | Değer |
|---|---|
| `Authorization` | `Bearer <lisans anahtarı>` — sitenin Çalışma Alanı Ayarları › Pinegrap AI kartına girilen anahtar (sitede şifreli saklanır) |
| `X-Pinegrap-Site` | sitenin alan adı (`HOSTNAME_SETTING`, ör. `dev.pinegrap.com`) — anahtarın bu siteye ait olup olmadığını denetlemek için |
| `X-Pinegrap-Version` | Pinegrap sürümü (ör. `2026.4.5`) |
| `User-Agent` | `Pinegrap/<sürüm> (workspace-ai)` |

Anahtar biçimi Pinegrap'ta yalnız kaba denetlenir: boşluksuz, yazdırılabilir
ASCII, 16–256 karakter. Anlamını yalnız geçit bilir.

## Kullanılan uçlar

| Uç | Ne zaman |
|---|---|
| `GET /v1/license` | lisans durumu (aşağıda). En çok 6 saatte bir; ayar kaydedilince ve "Bağlantıyı dene"de hemen |
| `GET /v1/models` | modeli seçmek için; adında `embed` geçenler atlanır, ilk kalan kullanılır ve saklanır |
| `POST /v1/chat/completions` | her model çağrısı: `model`, `messages`, `tools` (function calling), `tool_choice` (`required` ilk turda, sonra `auto`), `temperature` 0.2, `max_tokens` 3000, `stream: false` |

## `GET /v1/license` yanıtı

Geçerli anahtar:

```json
{ "valid": true, "expires_at": "2027-09-27T00:00:00Z", "plan": "…" }
```

`expires_at` ISO 8601 ya da unix zamanı olabilir; yoksa süre sınırsız sayılır.
Pinegrap bu tarihi saklar ve tarih geçince geçide sormadan durur.

Geçersiz ya da süresi dolmuş anahtar (iki biçimden biri):

```json
{ "valid": false, "status": "expired" }
{ "valid": false, "status": "invalid" }
```

## Anahtarı reddederken (her uçta)

| HTTP | Gövde | Pinegrap ne yapar |
|---|---|---|
| 401 | `{"error": {"code": "license_invalid", "message": "…"}}` | anahtar **geçersiz**: bekleyen istekler "anahtar bu siteyle eşleşmiyor ya da geçerli değil" diye kapanır, yeni anahtar girilene kadar @ai çalışmaz |
| 402 ya da 403 | `{"error": {"code": "license_expired", "message": "…"}}` | lisans **süresi dolmuş**: aynı şekilde durur, "yenileyip yeni anahtarı girin" der |

Kabul edilen kodlar: `license_invalid`, `license_missing`, `license_mismatch`
(anahtar bu siteye ait değil), `license_revoked` → geçersiz;
`license_expired` → süresi dolmuş. `licence_…` yazımı da kabul edilir.

**Önemli:** gövde JSON olmalı. JSON olmayan bir 403 (ör. Cloudflare'in bot
denetimi sayfası) lisans reddi sayılmaz; Pinegrap onu bağlantı hatası gibi
ele alır ve biraz sonra yeniden dener.

## Geçit yokken (bugünkü durum)

`GET /v1/license` LM Studio'da yoktur; LM Studio `200` ile
`{"error":"Unexpected endpoint or method. (GET /v1/license)"}` döner.
Pinegrap bunu (ya da 404'ü) "geçit henüz yok" diye okur, lisansı **kabul
edilmiş (pending)** sayar ve ayar kartında "Kabul edildi: lisans geçidi henüz
kurulmadığı için anahtar şimdilik denetlenmiyor" yazar. Yine de anahtar
girilmeden @ai hiç çalışmaz.

## Model sunucusunun hata biçimi

LM Studio, modelin araç çağrısı biçimi bozuk çıktığında 400 ile
`"The model produced output that does not match the expected peg-native format"`
döner. Pinegrap bunu tanır ve o turu araçsız, düz yazı yanıtla bitirir. Geçit
bu 400'ü olduğu gibi geçirmeli (gövdede `does not match` / `peg` geçmeli).

## Geçidin yapması önerilenler

- Anahtarı `X-Pinegrap-Site` ile eşleştirmek (anahtar başına izinli alan
  adları; `www.` ve alt alan adları kuralı).
- Anahtar başına hız sınırı (dakikada istek) ve günlük token kotası; aşımda
  `429` + `Retry-After`. Pinegrap 429'da istekleri bir dakika bekletir.
- İstek gövdesini kaydetmemek ya da kısa süre tutmak: gövdede kanal
  konuşmaları ve kayıt bilgileri var.
- `Authorization` başlığını LM Studio'ya iletmemek (gerek yok).
