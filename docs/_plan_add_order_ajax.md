# add_order.php — AJAX dönüşümü ve iki kitleli arayüz

Durum: **uygulandı** (2026-09-23). Ayrıntı ve doğrulama: `docs/degisiklikler.md`
"Yerel satış ekranı AJAX'a geçti" bölümü.
Önizleme: `docs/_mockup_add_order.html` (etkileşimli; Kasa/Belge, genişlik ve tema değiştirilebilir).

## 1. Bugünkü durum

- Ekran yedi POST eylemiyle çalışıyor (`set_customer`, `clear_customer`, `add_to_cart`,
  `increase_qty`, `decrease_qty`, `remove_item`, `complete_order`); her biri 302 + tam
  sayfa yüklemesi. Barkod okutmak ve her `+`/`−` tıklaması sayfayı baştan çiziyor;
  sayfa yüklenirken gelen ikinci okutma kaybolabiliyor.
- `<main class="container">` — `container-fluid` olmalı. Sol sütunda satır içi
  `min-width:300px;max-width:420px`.
- Kartlar `border-4 border-primary` (tasarım dilinde yok); Tamamla düğmesinde Material
  Icons (yeni kodda yasak).
- Tamamlayınca `view_order.php`'ye gidiliyor: tezgâhta her satıştan sonra geri dönmek gerekiyor.
- Fiyatlar KDV hariç gösteriliyor; tezgâhtaki kişi raf etiketindeki KDV dahil fiyatı
  ekranda göremiyor.
- Tamamlamada stok yeniden denetlenmiyor; çift tıklama/iki sekme aynı siparişi iki kez
  tamamlamaya çalışabiliyor (`status = 'incomplete'` koşullu güncelleme yok).
- Yerel satış `order.created` olayını kuyruğa yazmıyor (`submit_order.php` yazıyor).
- İki arama ucu (`search_products`, `search_customers`) zaten JSON — korunur.

## 2. Hedef

- Hiç tam sayfa yenilemesi yok; sunucu tek doğruluk kaynağı (sepet oturum + `order_items`).
- **Dükkân kullanıcısı:** okut → okut → tamamla; para üstü; bir sonraki müşteriye anında hazır.
- **ERP kullanıcısı:** cari, e-Fatura/e-Arşiv türü, vade, kasa/banka ve fatura aynı adımda;
  e-Belgeyi durduracak eksik cari bilgisi satıştan önce görünür.

## 3. İki görünüm, tek ekran

Araç çubuğundaki görünüm değiştirici (`btn-group` + `btn-ghost.active`): **Kasa | Belge**.
ERP açık ve kullanıcıda `manage_erp` varsa varsayılan Belge; yoksa değiştirici hiç
görünmez, ekran Kasa'dır. Seçim tarayıcıda (localStorage) kullanıcı başına saklanır.
İki görünüm aynı DOM'dur; fark sınıf ve sunucunun döndürdüğü sütunlardır.

| | Kasa | Belge |
|---|---|---|
| Ürün girişi | büyük alan, "okuyucu hazır" göstergesi, `× adet` çarpanı | aynı, standart boy |
| Sepet sütunları | Ürün · Miktar · Birim fiyat (KDV dahil) · Tutar | Ürün · Miktar · Birim fiyat (KDV hariç) · KDV % · KDV · Tutar |
| Müşteri | çip: "Sıcak satış" (F4 ile değiştir) | cari kartı: bakiye, vade, e-Fatura/e-Arşiv, eksik bilgi uyarısı, "Cari özeti" çekmecesi |
| Ödeme | büyük yöntem düğmeleri, Alınan → Para üstü, hızlı tutarlar | yöntem, kasa/banka, "Sonra tahsil et" → hesaplanan vade tarihi |
| Fatura | yetki varsa tek onay kutusu | onay kutusu + belge türü |
| Tamamlanınca | sonuç kartı (para üstü büyük), sepet sıfır, odak ürün alanında | sonuç kartı + Faturayı aç / Tahsilatı aç / Siparişi aç |

## 4. Yerleşim

- `container-fluid`; araç çubuğu `#button_bar`: Son satışlar · Sepeti boşalt · [boşluk] ·
  Kasa|Belge · ⋮ (Klavye kısayolları, Sesli uyarı, Satış ayarları).
- Form sütunu `col-12 col-lg-8 col-xxl-9 order-1`: ürün + müşteri kutusu, SEPET kartı.
- Durum sütunu `col-12 col-lg-4 col-xxl-3 order-2`, içindeki kutu `position-sticky`:
  ÖZET (toplamlar, Belge'de KDV kırılımı), ÖDEME, **Satışı tamamla**, Belge'de CARİ.
- **Desenden bilinçli sapma:** birincil düğme alt kaydet çubuğunda değil, durum
  sütununda — tezgâhta toplam ve düğme okuturken hep görünür kalmalı; alt çubuk ikisini
  ayırır. Gerekçe koda yorum olarak yazılır. `lg` altında sütunlar alt alta iner ve
  `col-12 order-3 position-sticky` alt çubuğu "Toplam ₺… [Tamamla]" gösterir.
- "Son yerel satışlar" sayfa altındaki katlanır karttan sağdan çekmeceye taşınır
  (add_user / api_settings'te onaylanan dil); açılınca AJAX ile yüklenir, satırda Fatura kes.

## 5. AJAX mimarisi

**Uç:** aynı dosya. `POST add_order.php` + `X-Requested-With: XMLHttpRequest` → her zaman
JSON. Başlıksız POST bugünkü gibi 302 ile döner (JS kapalıyken ve eski formlarda çalışır);
iki yol aynı eylem fonksiyonunu çağırır.

**Kapı:** `USER_LOGGED_IN` + e-ticaret yetkisi (arama uçlarıyla aynı, red JSON).
CSRF `hash_equals($_SESSION['software']['token'], $_POST['token'])` elle —
`validate_token_field()` HTML hata basar. Oturum düşmüşse `{ok:false, code:"session"}`
→ ekranda "Oturum sona erdi — sayfayı yenileyin" kutusu.

**Eylemler**

| action | girdi | not |
|---|---|---|
| `state` | — | sekme odağa gelince ve ilk yüklemede |
| `add_item` | `barcode` \| `product_id`, `quantity` | stok denetimi; eklenen satır id'si döner |
| `set_qty` | `item_id`, `quantity` | mutlak miktar (tekrar gelirse zarar yok); 0 = sil; `increase/decrease` buna iner |
| `remove_item` | `item_id` | |
| `clear_cart` | — | yeni; onay ekranda |
| `set_customer` / `clear_customer` | `contact_id` \| `account_id` | müşteri + ödeme kutusunu yeniden döndürür (fatura kesilebilirliği değişir) |
| `complete` | `pay_method`, `pay_till`, `issue_invoice` | sonuç kartı + boş sepet döner |
| `recent` | — | çekmece içeriği |

**Yanıt**

```json
{
  "ok": true,
  "code": "",
  "message": "Silikon Şeffaf 280 ml sepete eklendi.",
  "level": "success",
  "line_id": 4812,
  "cart": {
    "count": 4, "units": 9,
    "total_cents": 238900,
    "html": { "rows": "<tr>…</tr>", "summary": "…", "savebar": "…" }
  },
  "customer": { "html": "…", "can_invoice": true },
  "pay": { "html": "…" },
  "result": null
}
```

`complete` başarılıysa `result`: `{order:{id,number,url}, invoice:{id,number,url}|null,
receipt:{id,url}|null, warnings:[…]}`. Sipariş tamamlanıp fatura/tahsilat düşerse
`ok:true` + `warnings` — sipariş geri alınmaz, sonuç kartı "Faturayı yeniden dene" sunar.

**HTML sunucuda üretilir.** Satırlar, özet, müşteri ve ödeme kutuları PHP fonksiyonlarıdır;
ilk sayfa da AJAX yanıtı da aynı fonksiyonları çağırır (tek render kaynağı, `lang()` ve
`prepare_amount()` biçimi korunur). JS yalnız yerleştirir; para üstü dışında hiçbir toplamı
kendisi hesaplamaz.

**İstemci**

- İstek kuyruğu sıralıdır: hızlı okutmalar sırayla işlenir, kuyruk boşalana dek Tamamla kilitli.
- `scannerDetection` kalır; form submit yerine kuyruğa `add_item`. `*` → `-` dönüşümü korunur.
  Ürün alanında Enter aynı işi yapar; `× n` çarpanı bir sonraki eklemeden sonra 1'e döner.
- `visibilitychange` → `state` (iki sekme tutarlılığı).
- JSON olmayan yanıt / ağ hatası → uyarı + `state` yeniden çekilir (durum sunucudan gelir,
  istemci tahmin yürütmez).
- Hata sesi (bulunamadı / stok sınırı) Web Audio ile kısa bip; ⋮ menüden kapatılır.

## 6. Aynı işte yapılacak sunucu düzeltmeleri

- `complete`: stok yeniden denetimi (hangi satır, kaç adet var), `UPDATE orders … WHERE
  id = … AND status = 'incomplete'` + etkilenen satır sayısı (çift tamamlama), sıra numarası
  kilidinin hata yolu JSON.
- `api_webhook_enqueue('order.created', …)` — `submit_order.php` ile aynı yük.
- Material Icons → `bi-check2-circle`; `border-4 border-primary` ve satır içi genişlikler kalkar.

## 7. Klavye

F2 ürün alanı · F4 müşteri · F9 veya Ctrl+Enter tamamla · Esc arama sonucunu kapat ·
sonuçlarda ↑ ↓ Enter · sepette seçili satırda `+` `−` `Del`. Genel Ctrl+S/D/E/G ile
çakışmaz (formlar `disable_shortcut` kalır).

## 8. Dosyalar

- `pinegrap/add_order.php` — eylem dağıtıcı + sayfa iskeleti
- `pinegrap/includes/local_sale.php` — **yeni**: eylem ve render fonksiyonları (kapı sabitiyle)
- `pinegrap/assets/js/add_order.js` — **yeni**, doğrudan `<script src>` + `?v=` (`.min` ikizi yok)
- `pinegrap/assets/css/backend.src.css` — ekrana özel birkaç sınıf (`.pg-sale-*`)
- `tr.json` (+ `php tools/check_lang.php`), `docs/degisiklikler.md`, `pinegrap/changelog.txt`

## 9. Kapsam dışı (ayrı iş önerisi)

Satırda fiyat/iskonto değiştirme (ERP satır hesabını etkiler) · satışı beklet (birden çok açık
sepet) · parçalı ödeme (nakit + kart) · ESC/POS fiş yazıcısı (şimdilik `print_order.php`) ·
mağaza sepetiyle aynı oturum anahtarının paylaşılması (bugünkü durum).

## 10. Test

ERP kapalı / açık · `manage_erp` yok · `manage_erp_cash` yok · sıcak satış carisi tanımsız ·
10 hızlı ardışık okutma = 10 adet, sırayla · iki sekme · oturum düşmesi · stok 2 iken 3 okutma ·
Tamamla'ya çift tık · 400 / 820 / 1440 px · açık/koyu tema · `php tools/lint.php`.

## 11. Kararlar (Erdal, 2026-09-23)

1. Tamamla düğmesi durum sütununda kalır (alt çubuk yalnız `lg` altında).
2. Kasa görünümünde fiyat en pratik hâliyle: KDV dahil; Belge'de KDV hariç.
3. Tamamlamadaki üç sorun (stok yeniden denetimi, çift tamamlama,
   `order.created`) bu işte çözüldü.
4. **Her tezgâh satışı faturalanır**: satıcı müşteriye vermese de fatura
   oluşur; alıcı bilgisinin sonra nasıl tamamlanacağı ERP yöneticisinin
   tercihidir. Satışta vergi numarası sorulmaz; carisi olan müşteride zaten
   çözülür. "Faturayı kes" kutusu kaldırıldı.
5. **Hızlı cari ekleme**: müşteri aramasının sonunda "Yeni cari aç" — yalnız ad.
6. Önizlemedeki tasarıma sadık kalınır; panelin başka ekranlarındaki düğme
   türlerinden farklı olsa da.
