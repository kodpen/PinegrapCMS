# Pinegrap ERP — Plan (v3)

**Durum:** taslak · **Tarih:** 2026-09-16 · **Kapsam:** Faz -1 – Faz 5
**Arayüz:** https://dev.pinegrap.com/pinegrap/welcome.php

**Karar verilenler:** opsiyonel modül (aynı kod tabanı) · hedef mevcut e-ticaret mağazaları · cari hem müşteri hem tedarikçi · Pinegrap tek doğru kaynak, Paraşüt yalnız e-belge kanalı · **para = tamsayı kuruş (BIGINT)** · **Faz 0 şeması 2026.4.4'e alt adım** · **mevcut Paraşüt entegrasyonu önce acilen onarılır, ERP sonra onun yerine geçer**

> **v3 notu:** v2 planı repoya bağlanmadan yazılmıştı ve §13 "bağlanınca doğrulanacaklar" listesi bırakmıştı. v3'te o listenin 11 maddesi de kod tabanında doğrulandı. **Yedi varsayım çöktü, dört karar değişti.** Değişenler **[v3]** ile işaretli. v2 tam metni `_plan_erp.v2.bak.md` dosyasında duruyor.

---

## Durum (2026-09-20; tablo 2026-09-16 tarihli, sonradan güncellendi)

| Faz | Durum |
|---|---|
| **Faz -1** — Paraşüt onarımı | **tamam**, 2026.4.4 içinde |
| **Faz 0** — ERP iskeleti | **tamam ve dev'de koştu** — `upgrade_2026_4_4_erp_core()` |
| **Faz 1** — cari + kasa | **tamam** — atomik tahsilat, ekstre, virman, **fatura kapatma**, döviz, CSV içe aktarma (#147), çek (4.54); cari ↔ kişi/kullanıcı bağı ekranlarda (2026-09-20: cari formunda kişi ara-seç, kişi ekranında cari, toplu cari açma düğmesi) |
| **§5.9 vergi tabanı** | **karar verildi ve uygulandı** — B: satır toplamı (2026.4.4) |
| **Faz 2** — fatura | **tamam** — tezgâhta tamamla + fatura + tahsilat tek adım (2026-09-20 gece); sipariş → fatura, elle fatura + kalem editörü, PDF, VUK 509 alanları, kapatma, iade ve iptal, tahsilat düzeltmeleri (#149); 2026-09-20: kalem editörü katalogdan ürün (ad = `short_description`), stok, KDV, kampanya iskontosu + ek iskonto (4.58), barkod, alan bazlı hata; sipariş ekranında "Faturayı Kes", faturada "İrsaliyeyi Kes" (geri yazım). **Mali müşavir onayı hâlâ bekliyor** |
| Faz 3 — **e-belge sürücüleri** (Paraşüt + Logo İşbaşı, ileride başkası) | **Logo İşbaşı tarafı tamam (2026-09-21, migration 4.61–4.63):** `includes/erp/edoc/` sürücü katmanı; sağlayıcı seçimi Site Ayarları › E-Ticaret › E-Fatura kartında; şifreli kimlikler; canlı/test ortamı seçimi; **zincir uçtan uca çalışıyor — oluştur → GİB'e gönder (`einvoices/eInvoiceWithJson`) → durum → imzalı PDF/UBL**, test ortamında `BASARIYLA TAMAMLANDI`'ya kadar doğrulandı. Mükellef sorgusu cari kartına yazılıyor (`check_taxpayer`). **Kalan:** canlı anahtar (Erdal), Paraşüt sürücüsünün gönderim yolu (API kimliği bekliyor), `erp_edoc_queue` işleyicisi, iade/istisna için Logo'dan kod listesi, internet satışı `eArchivePaymentType` kodları |
| **Faz 4** — dahili irsaliye | **tamam, dev'de doğrulandı** (2026-09-20) — §7.6 gereği e-İrsaliye yok; `edoc_*` sütunu açılmadı. Bkz. `docs/degisiklikler.md` "Dahili irsaliye" |
| **Faz 5** — raporlar / dış API | **tamam (kanca bekliyor)** — yaşlandırma + pano (#151), dışa aktarım profilleri (#148), vade günü (4.53), gecikmiş alacak hatırlatmaları (4.55) + 2. tur (4.56), cari mutabakat mektubu (PDF + e-posta), kasa akışı raporu, **dış API ERP kaynakları (13 uç) ve 7 webhook olayı `includes/erp/api.php` dikişiyle (2026-09-20)**; sahip tavanı kancası API tarafından eklendi (2026-09-20 gece), API 17 uç (cari güncelleme, tahsilat iptali, irsaliye ve mutabakat PDF'i eklendi). e-belge hata bildirimi Faz 3'e bağlı |

### Faz 0 kabul testi — dev.pinegrap.com, 2026-09-16

- Yükseltme koştu: **126 ifade, 96'sı zaten yerindeydi** (30 yeni adım)
- **Çift koşum:** ikinci turda **126 ifadenin 126'sı da atlandı** — sıfır yan etki
- 11 tablo, 3 yetki sütunu, 6 mevcut tablo sütunu ve config alanları yerinde
  (`erp_default_series` varsayılanı `PGF` olarak okunuyor)
- Modül kapalıyken: ekranlar hata + ayar bağlantısı veriyor, menüde görünmüyor,
  yetki satırı çizilmiyor — site bugünküyle birebir aynı
- Modül açıkken: menü slotu 22 geliyor ve aktif, yedi ekranın hepsi 200 dönüyor
  (Cari Hesaplar, Faturalar, Fatura, Kasa ve Banka, İrsaliyeler, ERP Ayarları,
  ERP Panosu), `add_user`'da ERP yetki satırı çizilıyor
- Mevcut ekranlar etkilenmedi: `welcome.php`, `view_orders.php`,
  `view_order.php`, `view_users.php` temiz
- Yedek alındı: `data/backups/pre_upgrade_2026.4.3_20260916_181712`

**Yakalanan kendi hatam:** yeni yetki sütunları `includes/authentication.php`
SELECT'ine doğrudan yazılmıştı; şema inmeden panel "Unknown column
user.manage_erp" ile komple kapandı — CLAUDE.md'nin "Yükseltme Köprüsü Kuralı".
`pg_user_has_erp_columns()` sondasıyla düzeltildi ve tam da kapsaması gereken
durumda (sütunlar yokken) doğrulandı. Yeni yetki sütunu ekleyen herkes bu
sondayı güncellemeli.

### Uygulamada plandan sapılan üç yer

1. **Yetki: beş alan × okuma/yazma yerine üç anahtar.** `manage_erp` (kapı),
   `manage_erp_cash`, `manage_erp_settings`. Gerekçe: üçlü Yok/Okuma/Yazma
   deseni bu kod tabanında hiç yok (sütunlar `ENUM('no','yes')` veya
   `TINYINT`) ve fatura/irsaliyeyi cari hesaptan ayırmanın pratik karşılığı
   yok; kasa ile ayarların var. §8.3 buna göre okunmalı.
2. **Ayarlar: ayrı "ERP" bölümü yerine E-Ticaret içinde `pgset-erp` kartı.**
   Yeni bölüm registry + ekran + kaydedici + hazırlık değişkeni demek; üç alan
   için fazla. Faz 1 ayarları büyütürse ayrı bölüme taşınabilir.
3. **DATE varsayılanı `'0000-00-00'`.** Plan bir şey söylemiyordu; kod
   tabanının "boş tarih" karşılığı bu ve on yedi yerde karşılaştırılıyor.

### Faz -1'de plana ek olarak çıkan ve düzeltilenler

Envanter sırasında planda olmayan dört hata bulundu:
- `PARASUT_TC_IN_FIELD = 'tax_number'` ayarı hiç çalışmıyordu (numara
  `orders`'tan okunuyordu, o sütun `contacts`'ta)
- Vergi numarası zorunluluğu **e-arşivi** engelliyordu — yani sıradan tüketici
  siparişi hiç faturalanamıyordu
- `saved_for_later` satırları faturaya giriyordu (ödenmemiş mal)
- e-fatura/e-arşiv kararı kullanıcının bastığı düğmeden geliyordu

### §5.9 ölçümü yapıldı

Simülasyon (100.000 birim fiyat × {%1, %10, %20} × adet 1–6):

| adet | sapan oran | ortalama | maks |
|---|---|---|---|
| 1 | **%0** | 0 | 0 |
| 2 | %40–50 | 1 kr | 1 kr |
| 3 | %67–80 | 1 kr | 1 kr |
| 5 | %80 | 1,5 kr | 2 kr |
| 6 | %80–90 | 1,7 kr | 3 kr |

**Adet 1'de sapma yok.** Bu, B seçeneğini (siparişi satır-toplamı tabanına
geçirmek) beklenenden çok daha ucuz kılıyor: satırların çoğu adet 1 ve orada
hesap birebir aynı kalır. Faz 2'nin önerisi B.

**Karar: B uygulandı (2026-09-16).** `order_items.tax_total` eklendi
(`round(oran/100 × birim × adet)`), `order_items.tax` artık yazılmıyor ve
2026.5.0'da düşürülecek. On bir okuma noktası taşındı. Ödeme yolu, sepet özeti,
Paraşüt ve UBL-TR artık aynı tabanda; `_parasut_variance_notice()` beklenen
değeri sıfır.

**Doğrulandı:** sipariş dışa aktarımının tamamında her kalem satırının vergisi
`round(birim × adet × 0,20)` ile birebir — sıfır uyuşmazlık. Geri doldurma ve
on bir okuma noktasının kablolaması kanıtlandı.

**Ayrışma vakası da sınandı:** bir ürüne geçici `tax_rate = 10` verilip
₺299,95 × 3 ile ödeme önizlemesine gidildi. Önizleme **₺89,99** gösterdi —
satır tabanı (`round(29995 × 3 × 0,10) = 8999`). Eski birim tabanı ₺90,00
verirdi. Ürün oranı geri alındı, test sepeti sipariş #817 (silinebilir).

**Kalan risk:** PayPal Express satır vergisi alanı kaldırıldı ama test
edilemedi (hesap yok).

---

## 0. [v3] v2'den ne değişti — özet

| # | v2 ne diyordu | Gerçek | Sonuç |
|---|---|---|---|
| 1 | "Paraşüt entegrasyonu sıfırdan yazılacak" | 1184 satırlık, yarı çalışır bir entegrasyon **zaten var** ve içinde üretime sızmış hata ayıklama kodu var | **Faz -1** açıldı (§1) |
| 2 | Tüm tutarlar `DECIMAL(15,4)` | Kod tabanının tamamı **tamsayı kuruş** (`int`), bcmath **hiç yok** | Şema `BIGINT` kuruşa çevrildi (§5) |
| 3 | `customers` tablosu | Tablo adı **`contacts`**; `tax_number` + `tax_office` zaten var | Tüm referanslar düzeltildi |
| 4 | ERP için 2026.5.0 açılsın | Kural: **yayınlanmamış sürüm varken yeni numara açılmaz**; 4.4 hâlâ açık | Faz 0 şeması 2026.4.4 alt adımı (§4) |
| 5 | Yetki: üçlü Yok/Okuma/Yazma | Kod tabanında üçlü ENUM deseni **yok**; sütunlar `ENUM('no','yes')`/`TINYINT` | Okuma/yazma = iki sütun (§8.3) |
| 6 | `job.php` ziyaretçi trafiğiyle tetikleniyor olabilir | **Gerçek cron**; `inline` sözleşmesi doğrulandı | Kuyruk merdiveni tick sayısına göre yeniden yazıldı (§7.5) |
| 7 | Taşıyıcı ünvan + VKN siparişten gelir | Hiçbir tabloda **yok** | Yeni iş kalemi: `shipping_methods` genişletmesi (§6.4) |
| 8 | §4.12 yuvarlama kuralı | Sipariş KDV'si **birim bazında** yuvarlanıyor; kural grup bazında diyor → kabul kriteri sağlanamaz | Kural değişti + mevcut bir hata ortaya çıktı (§5.9) |

---

## 1. [v3] Faz -1 — Mevcut Paraşüt entegrasyonunun acil onarımı

**Bu faz ERP'den bağımsızdır ve ERP'den önce gelir.** Gerekçe: `enable_parasut` açık olan her kurulumda bugün canlı çalışan bir kod var ve içinde geri alınamaz hasar üretebilecek bir bölüm bulunuyor. 2026.4.4 lansmanla birlikte sunuculara inecek; bu hâliyle inmemeli.

### 1.1 Envanter — bugün ne var

| Parça | Yer |
|---|---|
| API katmanı | `includes/fn/parasut.php` (1184 satır, `functions.php:59`'dan yüklenir) |
| Gelen/giden kutusu | `view_parasut_inbox.php` (517 satır, salt okunur, DB'ye yazmıyor) |
| Ayar ekranı | Ayarlar → E-Ticaret → E-Fatura kartı (`includes/settings/commerce.php:397-465`) |
| Config alanları (11) | `enable_parasut`, `parasut_client_id/_secret/_username/_password/_company_id`, `parasut_use_sandbox`, `parasut_tc_in_field`, `parasut_default_product_id`, `parasut_default_warehouse_id` |
| Sabitler (12) | `init.php:634-645` |
| Şema izleri | `orders.parasut_{contact,invoice,shipment}_id` + `orders.parasut_exported`, `contacts.parasut_contact_id`, `products.parasut_product_id` |
| Tetikleme | **Tamamen elle** — `view_order.php`'de üç buton (E-Fatura / E-Arşiv / E-İrsaliye). Sipariş akışında otomasyon yok. |

Bu kurulumda entegrasyon **hiç canlı çalıştırılmamış**: `data/parasut_token.json` yok, `error.log`'da tek `PARASUT_` satırı yok.

### 1.2 Onarılacaklar — öncelik sırasıyla

**P0 — üretime sızmış hata ayıklama kodu**
`parasut_create_shipment()` içinde (`parasut.php:831-903`) **8 adet "SCHEMA PROBE"** var: ilk POST başarısız olursa kod kasıtlı olarak 7 bozuk istek daha gönderiyor ve Paraşüt'ün hata metnindeki alan listesini log'lamaya çalışıyor. Probe 4, 5, 6 ve 7 **başarılı olursa gerçekten belge yaratıp DB'ye kaydediyor** — tek buton tıklamasıyla Paraşüt'te birden fazla irsaliye oluşabilir ve bu geri alınamaz. **Tamamı silinir.**

**P0 — KDV kaybı**
`vat_rate` her kalemde sabit `0` (`parasut.php:570`, `592`; ürün yaratımında `:251`). Pinegrap'in hesapladığı KDV faturaya **hiç taşınmıyor**. Kesilen her belge KDV'siz. Satır bazında gerçek oran gönderilir (§5.9'daki taban kararına göre).

**P0 — düz metin kimlik bilgisi**
`parasut_client_secret` ve `parasut_password` `config`'de `VARCHAR(255)` düz metin; form `type="password"` ama değer HTML kaynağına basılıyor (`commerce.php:422,430`). Şifrelenir — desen §7.1'de.

**P1 — fatura toplamı ≠ sipariş toplamı**
Yalnız `order_items` gönderiliyor. `orders.shipping`, `orders.discount`, `orders.gift_card_discount`, `orders.surcharge` **hiçbiri kalem olarak gitmiyor**. Eski Excel yolu bunları ayrı satır yazıyordu (`edit_orders.php:307-345`) — API yolu bir gerileme. §6.3'teki kalem eşlemesi uygulanır.

**P1 — "gönderildi" yalanı**
`e_invoices`/`e_archives` POST'u asenkron bir `trackable_job` döndürüyor; kod yalnız HTTP 200/201'e bakıyor (`parasut.php:669`). `trackable_job` kelimesi kod tabanının **tamamında geçmiyor**. Belgenin GİB'e gidip gitmediği hiçbir zaman öğrenilmiyor. Ayrıca `orders.parasut_exported = 1` e-belge adımı başarısız olsa **bile** yazılıyor (`:678`). Asgari onarım: e-belge adımı başarısızsa `parasut_exported` yazılmaz ve hata kullanıcıya gösterilir. Tam çözüm Faz 3'te (kuyruk).

**P1 — GİB etiketi gönderilmiyor**
`e_invoices` gövdesinde yalnız `scenario => 'commercial'` sabit (`:641`); alıcı etiketi (`to`) yok. Paraşüt e-faturada bu alanı ister — büyük olasılıkla 422 döner. `parasut_check_einvoice_address()` fonksiyonu **zaten var ama bu akışta çağrılmıyor**; çağrılır ve dönen etiket `to`'ya yazılır. Aynı çağrı e-fatura/e-arşiv kararını da otomatikleştirir (bugün karar **kullanıcının hangi butona bastığı**).

**P2 — kişisel veri log'a düşüyor**
`error_log('PARASUT_INV ...')` ham istek/yanıt gövdesini yazıyor (`:631`, `:633` ve probe satırları) — müşteri VKN, ad, adres düz metin. Maskelenir veya kaldırılır.

**P2 — `parasut_use_sandbox` işlevsiz**
`_parasut_base()` sandbox'ta `https://api.heroku-staging.parasut.com` döndürüyor (`:85-91`) — Paraşüt'ün iç Heroku ortamı, genel müşteriye açık dokümante bir sandbox değil. Anahtar açıkken OAuth da oraya gidiyor, yani **açmak entegrasyonu bozuyor**. Ayar ekranından kaldırılır veya "yalnız Paraşüt desteği verdiyse" notuyla gizlenir. *(v2'deki "resmî sandbox yok" tespiti doğruydu; bu anahtar onu çürütmüyor.)*

**P2 — token cache yarışı**
`data/parasut_token.json`'a `file_put_contents` ile yazılıyor, kilit yok, dönüş kontrol edilmiyor (`:181`). `refresh_token` saklanıyor ama **hiç kullanılmıyor** (`:178`) — süre dolunca her seferinde tam password-grant login. Atomik yazım (`tmp` + `rename`) + refresh akışı.

**P2 — `orders.parasut_exported` iki anlam taşıyor**
Hem eski Excel ihracatı (`edit_orders.php:362`) hem API faturası (`parasut.php:678,683`) `=1` yazıyor; okunduğu tek yer `view_orders.php:2541`'deki rozet. Ayrım ancak `parasut_invoice_id`'nin doluluğundan anlaşılıyor. Rozet mantığı `parasut_invoice_id`'ye bağlanır.

**P3 — ölü kod**
`parasut_get_purchase_bill()` ve `parasut_list_einvoice_inboxes()` hiçbir yerden çağrılmıyor.

**Kayıtlı ama ayrı iş:** `HATA_RAPORU.md:11,42` — `includes/phpexcel`'deki 11 dosyada ölümcül sözdizimi hatası, Excel/Paraşüt ihracatı çağrılınca sistem çöküyor. Bu ERP'nin değil, ihracat yolunun sorunu; Faz -1'de en azından **buton gizlenir**.

### 1.3 Faz -1 sınırı — ne yapılmıyor
Mimari değiştirilmiyor. `parasut.php` yerinde kalıyor, kuyruk kurulmuyor, `includes/erp/` açılmıyor. Amaç yalnız **canlıya güvenle inecek hâle getirmek**. Mimari devir Faz 3'ün işi.

### 1.4 Faz -1 kabul
- Schema probe kodu dosyada yok; `parasut_create_shipment` başarısızlıkta **tek** istek atıp hata döndürüyor
- Kesilen faturada satır KDV oranı sipariştekiyle aynı
- Kargo/indirim/hediye çeki/vade farkı faturada kalem olarak görünüyor, **fatura toplamı = `orders.total`**
- `client_secret` ve `password` DB'de şifreli; HTML kaynağında görünmüyor
- e-belge adımı başarısızken `parasut_exported` yazılmıyor ve kullanıcı hatayı görüyor
- E-Fatura/E-Arşiv kararı `e_invoice_inboxes` sorgusundan geliyor; `to` alanı dolu gidiyor
- `error_log`'da müşteri kimlik/adres verisi yok

### 1.5 Faz -1'in ERP planına etkisi
Faz -1 bittiğinde **Faz 3'ün kapsamı değişir**: "sıfırdan yaz" değil, "çalışır entegrasyonu kuyruk + tek doğru kaynak mimarisine taşı ve `parasut.php`'yi emekliye ayır". `view_parasut_inbox.php` salt okunur görüntüleyici olarak **aynen kalır** — ERP ile çakışmıyor, işe yarıyor.

---

## 2. Konumlandırma — ne yapıyoruz, ne yapmıyoruz

Pinegrap bugün siparişi alıyor, stoğu düşüyor, tahsilatı ödeme sağlayıcısından duyuyor — ama **para nereye gitti, kime ne kadar borçluyuz, bu siparişin faturası kesildi mi** sorularının cevabı sistemde yok. ERP modülünün işi bu boşluğu kapatmak.

**Yapıyoruz (Faz 1–4):** cari hesap (müşteri + tedarikçi), kasa/banka ve hareketleri, satış/alış faturası, irsaliye, Paraşüt üzerinden e-fatura / e-arşiv (e-irsaliye §7.6'daki koşulla).

**Yapmıyoruz:** tekdüzen hesap planı ve muhasebe fişi, beyanname, bordro, üretim/reçete, maliyet muhasebesi. Pinegrap **ön muhasebe** düzeyinde kalıyor, resmî defter iddiası taşımıyor.

---

## 3. Paraşüt sınırı — tek doğru kaynak Pinegrap

```
Pinegrap (kaynak)                         Paraşüt (kanal)
─────────────────                         ───────────────
erp_accounts          ──upsert──────────> contacts
erp_invoices (satış)  ──create──────────> sales_invoices
                      ──convert────────> e_invoices | e_archives
                      <──trackable_job── (asenkron, 15 dk ömür)
                      <──GET e_invoices/{id}── (pencere dolunca)
tahsilat              ──pay()───────────> sales_invoices/{id}/payments
erp_waybills          ──create──────────> shipment_documents  (e-belge: §7.6)
```

**Kurallar:**

1. **Bakiye hiçbir zaman Paraşüt'ten okunmaz.** Cari bakiye Pinegrap'teki hareketlerin toplamıdır. Paraşüt çevrimdışıyken panel tam çalışır, yalnız e-belge kuyruğu bekler.
2. **Yazım tek yönlüdür.** Paraşüt'e belge ve o belgenin ödeme durumu gider; oradan **hiçbir tutar geri okunmaz**.
3. **Geri yön yalnız durum bilgisidir:** e-belge durumu, GİB UUID/numara, PDF adresi.
4. **Paraşüt aboneliği olmayan mağaza ERP'yi tam kullanır**, yalnız e-belge kesemez. `erp_parasut_enabled` ayrı anahtar.

### 3.1 Tahsilat neden Paraşüt'e gidiyor
Tahsilat gönderilmezse mağazanın Paraşüt hesabında **hiç kapanmayan, sürekli büyüyen sahte bir alacak** birikir ve mali müşavir her ay bunu sorar. `sales_invoices` üzerindeki `pay()` ucu tam bu iş için var ve **tek yönlü yazım** olduğu için "tek doğru kaynak" ilkesini bozmuyor.

Yazım başarısız olursa **Pinegrap'teki tahsilat geçerli kalır**; kuyrukta `pay` aksiyonu tekrar denenir.

### 3.2 `purchase_bills` kapsam dışı
Alış faturası için e-belge kesilmez (tedarikçi keser). **Alış faturaları yalnız Pinegrap'te durur.** Mevcut `view_parasut_inbox.php` gelen faturaları Paraşüt'ten canlı listeliyor — bu salt okunur görünüm kalır, ERP'nin alış faturası defteriyle karışmaz.

### 3.3 [v3] `parasut.php`'nin emekliliği
Faz 3 bittiğinde `includes/fn/parasut.php`'nin fatura ve irsaliye yolu `includes/erp/parasut/` altına taşınır. Dosyada yalnız `view_parasut_inbox.php`'nin kullandığı **salt okunur listeleme fonksiyonları** kalır (`parasut_list_purchase_bills`, `parasut_list_sales_invoices`, `parasut_check_einvoice_address`). Ölü fonksiyonlar (`parasut_get_purchase_bill`, `parasut_list_einvoice_inboxes`) Faz -1'de silinir.

**Config geçişi:** mevcut 11 alan korunur ve ERP tarafından okunur; yalnız `client_secret`/`password` şifreli ikizlerine taşınır (Faz -1). İkinci bir config kümesi **açılmaz** — `degisiklikler.md:5765`'teki "ikinci sağlayıcı ikinci kolon kümesi demek" eleştirisi haklı ama onu çözmek ERP'nin işi değil.

---

## 4. [v3] Sürüm ve paketleme

### 4.1 Sürüm numarası — karar verildi
`docs/degisiklikler.md` "Dağıtım durumu": sunucularda **2026.4.3**, hazırlanan **2026.4.4** (yayınlanmamış). Kural: *"Yayınlanmamış sürüm varken yeni numara açılmaz."*

**Karar:**
- **Faz -1** (Paraşüt onarımı) → `upgrade_2026_4_4_parasut_repair()` alt adımı + kod düzeltmeleri. Zaten 4.4'le birlikte çıkması gereken iş.
- **Faz 0** (ERP iskeleti, 11 tablo) → `upgrade_2026_4_4_erp_core()` alt adımı. Tablolar modül kapalıyken de kurulur, boş tablo maliyeti sıfıra yakın.
- **Faz 1+** şema adımları → 2026.4.4 yayınlandıktan sonra açılacak **2026.5.0**'a.

Böylece 4.4'ün yayınını beklemeden başlanır ve kural ihlal edilmez.

> **Dev veritabanı notu:** 2026.4.4 bu makinede zaten koştu. Yeni alt adımın işlemesi için `UPDATE config SET version = '2026.4.3'` çekilip yükseltme yeniden çalıştırılır (CLAUDE.md "Yayınlanmış Sürüm Kapalıdır"). Adımlar idempotent olduğu için uygulanmış olanlar atlanır — bu aynı zamanda çift koşum testidir.

### 4.2 Modül anahtarı
`config.erp_enabled` TINYINT(1) DEFAULT 0 — Ayarlar → **ERP** bölümünde.
Sabit `init.php`'de **savunmacı** biçimde (`init.php:461` deseni, `init.php:634` değil):
```php
define('ERP_ENABLED', isset($row['erp_enabled']) ? (int) $row['erp_enabled'] : 0);
```
Gerekçe: kod şemadan önce iniyor (CLAUDE.md "Yükseltme Köprüsü Kuralı"); sütun yokken özellik kapalı davranmalı, sayfa patlamamalı.

**Migration davranışı:** Tablolar modül kapalıyken **de** kurulur. Migration'ı anahtara bağlamak, sonradan açan mağazada şemayı sürüm dışı bir ana kaydırır ve idempotent adımlar "zaten var" diyerek bozuk şemayı sabitler.

Anahtar yalnız şunları kontrol eder: menü grubu, `erp_*.php` ekranlarının açılması, job kataloğundaki ERP görevleri, sistem durumu widget'ı kontrolü, dış API kaynakları.

**Kapalı modülde ekran davranışı:** 404 değil — kod tabanının deseni (`view_parasut_inbox.php:20-23`) hata mesajı + ilgili ayar sekmesine yönlendirme:
```php
if (!defined('ERP_ENABLED') || !ERP_ENABLED) {
    $liveform->mark_error('_error', lang('The ERP module is not enabled. Please enable it in Settings.'));
    go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . pg_settings_return_url('erp', 'pgset-erp'));
}
```

### 4.3 Dosya yerleşimi
```
pinegrap/
  erp_dashboard.php  erp_accounts.php  erp_invoices.php
  erp_invoice_edit.php  erp_cash.php  erp_waybills.php  erp_settings.php
  erp_action.php                 JSON uç (Desen B, §8.6)
  parasut_webhook.php
  includes/erp/                  PG_ERP_ENTRY kapısı, hepsinde
    accounts.php  cash.php  invoices.php  waybills.php
    numbering.php  money.php  order_bridge.php
    parasut/ client.php  map.php  dispatch.php  inbox.php
  includes/migrations/2026.4.4.php   (Faz -1 + Faz 0 alt adımları)
  includes/migrations/2026.5.0.php   (Faz 1+ , 4.4 yayınlandıktan sonra)
```

**ERP fonksiyonları `functions.php`'ye girmez** — `includes/fn/` bölme işini (2026-09-12) geri alır. **Panel AJAX `api.php`'ye girmez** (13.6k satır); ekranlar kendi POST'larını taşır, canlı sorgular `erp_action.php`'ye gider.

---

## 5. [v3] Veri modeli

> **[v3] Para birimi tipi kararı: tamsayı kuruş, `BIGINT`.**
> Kod tabanının tamamı böyle: `orders.subtotal/tax/total/discount/shipping/surcharge` hepsi `int`, `order_items.price/tax` `int`, `ship_tos.shipping_cost` `int`. `bcmath` kod tabanında **hiç kullanılmıyor** (vendor hariç 0 sonuç). v2'nin `DECIMAL(15,4)` önerisi her sipariş→fatura köprüsüne bir `int↔decimal` dönüşümü, yani **yeni bir yuvarlama noktası** ekliyordu. Toplama ve çıkarma tamsayıda kayıpsız; kayıp yalnız oran çarpımlarında olur ve orası zaten tek noktada (§5.9).
> **Oran alanları DECIMAL kalır:** `tax_rate`, `discount_rate`, `withholding_rate` `DECIMAL(6,3)` (mevcut `products.tax_rate`, `tax_zones.tax_rate` ile aynı), `exchange_rate` `DECIMAL(15,6)`.
> **Adet `DECIMAL(15,4)` kalır** (kg, metre gibi birimler için).

### 5.1 `erp_accounts` — cari kart
| alan | tip | not |
|---|---|---|
| id | INT PK AI | |
| kind | ENUM('customer','supplier','both') | |
| title | VARCHAR(255) | |
| is_person | TINYINT(1) | 1 → TCKN, 0 → VKN |
| tax_number | VARCHAR(11) | INDEX (unique değil) |
| tax_office | VARCHAR(100) | |
| email, phone, address, district, city, country_code, postcode | | e-belge adres alanları |
| currency | CHAR(3) DEFAULT 'TRY' | |
| balance | BIGINT DEFAULT 0 | **[v3] kuruş** · önbellek, hareketlerden türetilir |
| balance_fc | BIGINT DEFAULT 0 | **[v3] kuruş**, orijinal para biriminde |
| balance_updated_at | DATETIME | |
| **contact_id** | INT NULL | **[v3]** INDEX — tablo adı `customers` değil **`contacts`** |
| einvoice_user | TINYINT(1) NULL | panel gösterimi için önbellek |
| einvoice_alias | VARCHAR(100) NULL | seçilen GİB etiketi |
| einvoice_aliases | TEXT NULL | dönen tüm kutular (JSON) |
| einvoice_checked_at | DATETIME NULL | TTL ≤ 24 sa, yalnız gösterim |
| status, notes, created_by, created_at, updated_at | | |

> **[v3] `parasut_contact_id` bu tabloda YOK.** `contacts.parasut_contact_id` (2026.1.17) zaten var ve `parasut_sync_contact()` onu yazıyor. İkinci bir sütun iki doğru kaynak demek. ERP cariyi `contact_id` üzerinden `contacts`'a bağlar ve Paraşüt kimliğini oradan okur. Cari kartın `contact_id`'si yoksa (yalnız tedarikçi) Paraşüt'e gitmez zaten.

> **`opening_balance` sütunu yok.** Açılış **yalnız bir harekettir**; bakiye = saf `SUM(...)`. İki yerde tutmak açılışı iki kez saydırır.

### 5.2 `erp_account_transactions` — cari hareket (append-only)
`id BIGINT PK` · `account_id` (INDEX `account_id, doc_date, id`) · `doc_date DATE` · `kind ENUM('opening','invoice','return','collection','payment','adjustment','writeoff','fx_diff')` · `direction ENUM('debit','credit')` · **`amount BIGINT`** *(kuruş, daima pozitif)* · `currency CHAR(3)` · `exchange_rate DECIMAL(15,6)` · `exchange_rate_date DATE` · `exchange_rate_source VARCHAR(20)` · **`amount_try BIGINT`** *(kuruş)* · `doc_type VARCHAR(20)` · `doc_id INT` (INDEX `doc_type, doc_id`) · `description` · `created_by` · `created_at`

**Kural: UPDATE/DELETE yok.** Düzeltme ters kayıtla yapılır.
`balance = SUM(debit) − SUM(credit)` üzerinden **TL kuruş**, `balance_fc` üzerinden **orijinal para birimi kuruşu**. `erp_accounts` sütunları bu toplamların önbelleğidir, her yazımla aynı transaction içinde tazelenir.

**Dövizli cari:** Kapatma **orijinal para biriminde** yapılır (EUR fatura EUR tahsilatla kapanır). Kur farkından doğan TL artığı `kind='fx_diff'` ayrı hareket olarak yazılır. `exchange_rate_source` varsayılanı **TCMB döviz alış**, `exchange_rate_date` belge tarihi.

### 5.3 `erp_cash_accounts` / `erp_cash_transactions`
**Hesaplar:** id, name, kind ENUM('cash','bank','pos','credit_card'), currency, iban, bank_name, **opening_balance BIGINT**, **balance BIGINT**, is_active, sort_order
**Hareketler:** id, cash_account_id, doc_date, direction ENUM('in','out'), **amount BIGINT**, currency, exchange_rate, exchange_rate_date, **amount_try BIGINT**, account_id (cari, NULL), doc_type, doc_id, payment_method ENUM('cash','transfer','card','other'), transfer_pair_id, description, created_by, created_at

**Tahsilat atomiktir:** 1 kasa (in) + 1 cari (credit), tek DB transaction'ında, aynı `doc_type/doc_id` ile bağlı. İkisi de **yalnız** `erp_post_collection()`'dan geçer.

> **[v3] InnoDB şart.** Transaction olmadan "kasa yazıldı, cari yazılamadı" durumu kaçınılmaz. `install_set_engine()` yardımcısı var; `CREATE TABLE`'larda `ENGINE=InnoDB` açıkça yazılır.

### 5.4 `erp_invoices` — fatura başlık
| alan | tip | not |
|---|---|---|
| id | INT PK AI | |
| direction | ENUM('sales','purchase') | |
| doc_type | ENUM('invoice','return','proforma') | |
| invoice_type | ENUM('SATIS','ISTISNA','TEVKIFAT','IADE','OZELMATRAH','IHRACAT') | UBL-TR fatura tipi |
| series, number, issue_year, full_number | | UNIQUE (direction, series, number, issue_year) |
| supplier_invoice_no / supplier_invoice_date | VARCHAR(32) / DATE NULL | alış faturasının yasal numarası |
| account_id | INT | INDEX |
| order_id | INT NULL | INDEX (UNIQUE **değil** — §5.4.1) |
| parent_invoice_id | INT NULL | iadenin kaynak faturası |
| issue_date, due_date | DATE | |
| currency, exchange_rate, exchange_rate_date | | |
| **subtotal, discount_total, tax_total, withholding_total, grand_total, grand_total_try, paid_total** | **BIGINT** | **[v3] kuruş** |
| **shipping_total, surcharge_total, gift_card_total** | **BIGINT** | **[v3]** §6.3 — sipariş başlık kalemlerinin karşılığı |
| status | ENUM('draft','issued','partially_paid','paid','cancelled') | |
| is_internet_sale | TINYINT(1) | VUK 509 |
| payment_method | VARCHAR(30) | "KREDİ KARTI/BANKA KARTI", "EFT/HAVALE", "KAPIDA ÖDEME" |
| payment_date | DATE | |
| shipment_date | DATE | sevk tarihi |
| carrier_title / carrier_vkn | VARCHAR(255) / VARCHAR(11) | taşıyıcı ünvan + VKN — kaynağı §6.4 |
| web_address | VARCHAR(255) | satışın yapıldığı site adresi |
| edoc_kind | ENUM('none','einvoice','earchive') | |
| edoc_scenario | ENUM('basic','commercial') | |
| edoc_status | ENUM('none','queued','sending','sent','accepted','rejected','error') | INDEX |
| earchive_cancel_deadline | DATE NULL | issue_date + 8 gün |
| parasut_invoice_id, parasut_edoc_id, parasut_job_id | VARCHAR(32) NULL | |
| parasut_job_expires_at | DATETIME NULL | job ID 15 dk ömürlü |
| gib_uuid CHAR(36), gib_number VARCHAR(20) | NULL | |
| edoc_error TEXT, edoc_sent_at DATETIME | NULL | |
| notes, created_by, created_at, updated_at | | |

> **[v3] `orders.parasut_invoice_id` ile ilişki.** Mevcut sütun Paraşüt'ün kimliğini `orders`'ta tutuyor. ERP'de doğru yer `erp_invoices.parasut_invoice_id`. Faz 3'te `orders.parasut_invoice_id` **okunmaya devam eder ama yazılmaz**; `orders.erp_invoice_id` (yeni, INT) ERP faturasını işaret eder ve rozet oradan çizilir. Eski sütun veri taşıdığı için silinmez.

#### 5.4.1 Neden `UNIQUE (order_id, doc_type)` yok
3 kalemlik siparişin önce 1, sonra 1 kalemi iade edilir — bu **iki ayrı iade belgesi** demek.
- `doc_type='invoice'` için sipariş başına **en fazla bir** belge → uygulama katmanında kontrol (MySQL'de kısmi indeks yok),
- `doc_type='return'` serbest, ama satır bazında **`iade edilen adet ≤ faturalanan adet`** kontrolü zorunlu.

#### 5.4.2 Numaratör yalnız satış serisini tüketir
Alış faturasının yasal numarası **tedarikçinindir** (`supplier_invoice_no`). Proforma fatura değildir. Numaratör yalnız `direction='sales' AND doc_type IN ('invoice','return')` için çalışır; proforma ayrı seri kullanır.

### 5.5 `erp_invoice_items` — fatura satır
`id` · `invoice_id` (INDEX) · `line_no` · `product_id NULL` · `description` · `quantity DECIMAL(15,4)` · `unit_code VARCHAR(10)` *(UBL-TR: C62, KGM, MTR)* · **`unit_price BIGINT`** *(kuruş)* · `discount_rate DECIMAL(6,3)` · **`discount_amount BIGINT`** · `tax_rate DECIMAL(6,3)` · **`tax_total BIGINT`** · `vat_exemption_code VARCHAR(10) NULL` *(301, 302…)* · `withholding_rate DECIMAL(6,3)` · `withholding_code VARCHAR(10) NULL` *(601–621)* · **`line_total BIGINT`** · `gtip VARCHAR(20) NULL` · `returned_qty DECIMAL(15,4) DEFAULT 0`

> ⚠ **Sütun adı bilerek `tax_total`.** `order_items.tax` **BİRİM** vergidir — `ecommerce.php:1325` birim fiyattan hesaplıyor, `submit_order.php:825`, `widgets_cart.php:709,2166`, `get_order_preview.php:2321`, `get_order_receipt.php:1883`, `get_view_order_screen_content.php:2041`, `get_express_order.php:4459` hepsi `tax * quantity` ile çarpıyor. Faturada satırın **TOPLAM** vergisi tutuluyor; adın farklı olması okuma anında durduruyor. CLAUDE.md'ye tek satır yazılacak.

> **İstisna/tevkifat kodu neden:** UBL-TR'de KDV %0 ise fatura tipi `ISTISNA` + `TaxExemptionReasonCode` gerekiyor; tevkifatta 601–621 aralığında kod zorunlu. Kod olmadan belge GİB'den döner. `products.tax_rate = 0.000`'ın **sebebini** taşıyacak alan olmadan "sıfır oranlı" ayrımı e-belgede karşılıksız kalır — ürün kartına varsayılan istisna kodu eklenir.

### 5.6 `erp_waybills` / `erp_waybill_items`
Başlık: id, series, number, issue_year, account_id, order_id NULL, invoice_id NULL, issue_date, ship_date, ship_time, carrier_title, carrier_vkn, plate, driver_name, driver_tckn, sevk adresi snapshot alanları, status, parasut_shipment_id, edoc_* *(yalnız §7.6 koşulu sağlanırsa)*
Satır: id, waybill_id, line_no, product_id, description, quantity, unit_code

### 5.7 `erp_document_series` — numaratör
`series` · `doc_kind ENUM('sales_invoice','proforma','waybill','collection','payment')` · `issue_year` · `last_number` · `prefix` · `padding` — UNIQUE (series, doc_kind, issue_year).
Numara `SELECT ... FOR UPDATE` ile alınır; **ancak belge başarıyla kaydedilince tüketilir**, taslakta değil.

### 5.8 `erp_edoc_queue` / `erp_parasut_log`
**Kuyruk:** id · doc_type ENUM('invoice','waybill') · doc_id · action ENUM('create','convert','poll','reconcile','pay','cancel') · status ENUM('pending','sending','done','failed','abandoned') · attempts · next_attempt_at (INDEX) · parasut_job_id · last_error TEXT · created_at · updated_at
**Günlük:** id, doc_type, doc_id, method, path, http_code, duration_ms, request_excerpt, response_excerpt, created_at. **Token, gizli anahtar ve müşteri kimlik/adres verisi maskelenir** (Faz -1 P2 ile aynı kural). 30 gün sonra temizlenir.

### 5.9 [v3] Yuvarlama — v2'nin kuralı çalışmıyor, karar gerekiyor

v2 §4.12 şunu diyordu: *"KDV, her KDV oranı grubunun yuvarlanmış matrahı üzerinden hesaplanır."* Bu **grup bazlı** yuvarlamadır. Ama Pinegrap siparişte **birim bazlı** yuvarlıyor:

```php
// includes/fn/ecommerce.php:1325 — birim vergi
$tax_amount = round($effective_tax_rate / 100 * $order_item['price']);
// submit_order.php:825 — sonra adetle çarpılıyor
$tax += $product['tax'] * $product['quantity'];
```
yani `round(oran × birim) × adet`. Grup bazlı kural ise `round(oran × birim × adet)` verir. **İkisi aynı sonucu vermez** (adet > 1 ve kuruş altı artık varken). Dolayısıyla v2'nin Faz 2 kabul kriteri — *"fatura toplamı siparişin toplamına kuruşu kuruşuna eşit"* — kendi yuvarlama kuralı yürürlükteyken **matematiksel olarak sağlanamıyordu.**

**Dahası: bu tutarsızlık bugün zaten var.** Sepet önizlemesi satır toplamı üzerinden hesaplıyor —
```sql
-- includes/fn/widgets_cart.php:722
ROUND(oi.price * oi.quantity * COALESCE(p.tax_rate, X) / 100)
```
— ödeme ise birim bazında. `widgets_cart.php:2159-2164`'te zaten bunun yol açtığı eski bir "tutar değişti" reddi notu duruyor. Yani ERP bu kararı vermek zorunda değil; **kararı yalnızca görünür kılıyor.**

**Üç yol, biri seçilmeli:**

| Yol | Ne demek | Bedeli |
|---|---|---|
| **A — Fatura devralır (en düşük risk)** | Fatura hiçbir tutarı yeniden hesaplamaz: `unit_price = order_items.price`, `tax_total = order_items.tax × quantity`, başlık toplamları `orders`'tan aynen. | "Kuruşu kuruşuna eşit" **tanım gereği** sağlanır. Ama Paraşüt KDV'yi kendi hesaplar (satır toplamı üzerinden) ve bizimkinden sapabilir → §7.7 |
| **B — Sipariş satır-toplamı tabanına geçirilir (önerilen)** | `ecommerce.php:1325` satır toplamı bazına çevrilir; sepet, ödeme, fatura ve Paraşüt **dördü birden** aynı tabana gelir. | Canlı ticaret koduna dokunuyor; mevcut siparişlerle geriye dönük fark üretir (tarihsel kayıt değişmez, yalnız yeni siparişler). Mevcut sepet/ödeme tutarsızlığını da kapatır. |
| **C — İki taban yan yana** | Sipariş birim bazlı kalır, fatura grup bazlı hesaplar, fark `erp_account_transactions`'a "yuvarlama farkı" hareketi olarak yazılır. | Her faturada açıklanması gereken bir kuruş satırı. Mali müşavir sorar. Reddedilmeli. |

**Öneri: B**, A'ya geri düşme seçeneğiyle. Karar Faz 2 başlamadan verilir ve **önce ölçülür**: mevcut siparişler üzerinde bir tarama betiği "kaç siparişte kaç kuruş fark var" sorusunu cevaplar. Fark sıfıra yakınsa B risksizdir.

**Tek uygulama noktası:** `includes/erp/money.php`. Float yok, tamsayı kuruş; tek çarpım fonksiyonu, tek `round()`.

### 5.10 Mevcut tablolara eklenenler
| Tablo | Sütun | Not |
|---|---|---|
| `orders` | `erp_invoice_id INT NULL` INDEX | ERP faturası. `parasut_invoice_id` (mevcut) silinmez, yazılmaz |
| `orders` | `erp_account_id INT NULL` | |
| **`contacts`** | `erp_account_id INT NULL` INDEX | **[v3]** `customers` değil |
| `shipping_methods` | `carrier_title VARCHAR(255)`, `carrier_vkn VARCHAR(11)` | **[v3]** §6.4 |
| `orders` | `paid_at INT NULL` | **[v3]** §6.4 — VUK 509 ödeme tarihi |
| `products` | `vat_exemption_code VARCHAR(10) NULL` | §5.5 |

`config`: `erp_enabled`, `erp_parasut_enabled`, `erp_default_series`, `erp_auto_invoice_on ENUM('off','order_paid','order_shipped')`, `erp_default_cash_account_id`, `erp_einvoice_scenario`, `erp_web_address`, `erp_round_mode`

### 5.11 [v3] Migration kontrol listesi
- [ ] **2026.4.4** girişine iki alt adım: `upgrade_2026_4_4_parasut_repair()`, `upgrade_2026_4_4_erp_core()` — `upgrade_to_2026_4_4()` gövdesinden çağrılır (`to_` almazlar)
- [ ] `install_create_table` / `install_add_column` / `install_add_index` — ham `db("ALTER TABLE ...")` **yasak**
- [ ] `CREATE TABLE`'larda `ENGINE=InnoDB` açık (§5.3)
- [ ] `install_add_index` tanımı indeks adını **içermeli**: `"INDEX idx_account_date (account_id, doc_date, id)"` — içermezse `InstallUpgradeException`
- [ ] Her adım sonunda `install_note()`
- [ ] **`install/index.php` → `get_tables()` listesine 11 tablonun hepsi** (düz alfabetik string dizisi, `install/index.php:7297`) — atlanırsa temiz kurulumda eski tablolar kalır
- [ ] `install_heavy_tables()` haritasına **hayır** — 11 tablo boş kuruluyor, ağır değil. Faz 1+ veri taşırsa o zaman eklenir
- [ ] **[v3] Tohum dökümü kontrolü:** `install/index.php`'de `CREATE TABLE` yok; temiz kurulum `data/backups/{english,turkish}_default/sql.sql` dökümünü yükleyip **sonra** migration'ları koşuyor. Tablolar migration'dan geldiği için tohum dökümüne dokunmaya gerek yok — ama Faz 0 kabul testi bunu **doğrulamalı**
- [ ] `changelog.txt` → `[YENİ] ERP modülü`, `[ŞEMA] 11 yeni tablo`
- [ ] `docs/degisiklikler.md` → gerekçe (2026.4.4 bölümüne)
- [ ] CLAUDE.md → `tax_total` uyarısı + kuruş kuralı + `contacts` ≠ `customers` notu
- [ ] **[v3] Köprü kuralı:** ERP kodu `pg_require_current_schema()` kapısının **arkasında** çalışır (panel ekranları `validate_user()` çağırıyor), bu yüzden probe gerekmez. `init.php`'deki sabit yine de savunmacı yazılır (§4.2)

---

## 6. İş akışları

### 6.1 Sipariş → cari → fatura
```
Sipariş oluştu
  └─ müşterinin erp_account'ı var mı? yoksa contacts'tan üret
Sipariş ödendi  (config: erp_auto_invoice_on)
  ├─ fatura taslağı üret (order_items → erp_invoice_items)
  │    ⚠ vergi: order_items.tax BİRİM vergidir → tax_total = tax * quantity
  │    ⚠ oran: get_tax_rate_for_address() + get_effective_tax_rate() — İKİSİ DE çağrılır
  │    ⚠ başlık kalemleri: §6.3
  │    internet satışı alanları: is_internet_sale=1, payment_method, payment_date,
  │      web_address, shipment_date, carrier_title, carrier_vkn (§6.4)
  ├─ numaratörden numara al, status='issued'
  ├─ erp_account_transactions: debit, kind='invoice'
  ├─ tahsilat varsa erp_post_collection() → kasa in + cari credit
  └─ erp_parasut_enabled ise kuyruğa 'create'
```

### 6.2 [v3] Vergi oranı — iki fonksiyon, ikinci hesaplama yolu açılmaz
Köprü **kendi oran hesabını yazmaz**, mevcut ikiliyi çağırır:
```php
// includes/fn/forms.php:2910  — bölgede vergi alınır mı, kaç?
$zone_rate = get_tax_rate_for_address($country_code, $state_code);   // '0.000' | rate | false
// includes/fn/ecommerce.php:1088 — hangi oran kazanır?
$rate = get_effective_tax_rate($product_rate, $zone_rate);
```
Davranış (kasten böyle): `products.tax_rate = NULL` → **bölge kazanır** (destination-based); `= 0.000` → **ürün kazanır**, sıfır oranlı; dolu değer → **ürün bölgeyi ezer** (KDV modeli). `0.000` oranlı bölge artık `false` değil `"0.000"` döner (`forms.php:2980-2986`).

`tax_exempt=1` siparişte tüm satırlar `tax=0` (`update_order_item_taxes()`, `ecommerce.php:1204`) — faturada `invoice_type='ISTISNA'` + istisna kodu gerekir.

### 6.3 [v3] Başlık kalemleri faturaya nasıl girer — v2'nin boşluğu

Sipariş toplamının parçaları `order_items`'da **değil**, `orders` başlığında sütun:

| Sipariş | Değer | Faturadaki karşılığı |
|---|---|---|
| `orders.shipping` | `SUM(ship_tos.shipping_cost)` | **Ayrı fatura kalemi** — "Kargo ücreti", kendi KDV oranıyla |
| `orders.surcharge` | vade farkı, `round(ECOMMERCE_SURCHARGE_PERCENTAGE/100*total)` | **Ayrı kalem** — "Vade farkı" |
| `orders.discount` | teklif/kupon indirimi (`special_offer_code`, `discount_offer_id`) | **Satırlara dağıtılır** (aşağıda) |
| `orders.gift_card_discount` | hediye çeki | **Ödeme**, indirim değil → faturada kalem yok, `erp_cash_transactions`'a "hediye çeki" kasa hareketi |

**İndirim dağıtımı — kritik:** `submit_order.php:850` indirimi vergiden **başlık düzeyinde** düşüyor:
```php
$tax = $tax - round(($tax * ($discount / $subtotal)));
```
Bu faturaya doğrudan taşınamaz; UBL-TR satır bazında iskonto ister. Kural: **indirim satırlara `line_total` oranında dağıtılır, artık kuruş en büyük satıra yazılır** (dağıtım toplamı = `orders.discount`, sapma sıfır). Tek yer: `includes/erp/order_bridge.php`.

> ⚠ `submit_order.php:850`'de `$subtotal == 0` iken sıfıra bölme var. Köprü bu değeri okumaz, kendi dağıtımını yapar — ama bu ayrı bir hata olarak kaydedilmeli.

> ⚠ **`add_order.php` (panel/POS siparişi) vergi ve kargo hiç hesaplamıyor** — `total = subtotal` yazıyor (`add_order.php:283-284`). Bu siparişlerden fatura kesilirse KDV'siz çıkar. Faz 2'de ya `add_order.php` düzeltilir ya da panel siparişleri otomatik faturadan muaf tutulup operatöre uyarı gösterilir.
>
> **Çözüldü (2026-09-20):** `add_order.php` tamamlarken satır bazında KDV yazıyor (`local_sale_line_tax()`, `orders.tax`, `total = subtotal + tax`), müşteri/cari seçimi ve sıcak satış carisi (`config.erp_walkin_account_id`, 4.59) var; kargo tezgâh satışında yok. Bkz. `docs/degisiklikler.md` "Yerel satış".

### 6.4 [v3] VUK 509 zorunlu alanlar — üçü sistemde yok

İnternetten yapılan satışın e-Arşiv faturasında **sevk tarihi, ödeme şekli, ödeme tarihi, taşıyıcı ünvanı ve VKN'si, "bu satış internet üzerinden yapılmıştır" ibaresi + web adresi ve iade bölümü** zorunlu. Hedef kitle e-ticaret mağazaları olduğu için bu **istisna değil, varsayılan**.

Repo kontrolü sonucu:

| Alan | Kaynak | Durum |
|---|---|---|
| Sevk tarihi | `ship_tos.ship_date` | ✅ var |
| Web adresi | `config` → `erp_web_address` | ✅ (yeni ayar) |
| İbare + iade bölümü | Belge şablonu | ✅ (üretilir) |
| **Ödeme şekli** | `orders.payment_method` | ⚠ ENUM yalnız 4 değer: `''`, `Credit/Debit Card`, `PayPal Express Checkout`, `Offline Payment`. **iyzico / havale / kapıda ödeme ayrımı yok.** Eşleme tablosu + gerekirse ENUM genişletmesi |
| **Ödeme tarihi** | — | ❌ **Sütun yok.** `orders.order_date` sipariş tarihi, ödeme tarihi değil. Yeni: `orders.paid_at INT NULL` |
| **Taşıyıcı ünvan + VKN** | — | ❌ **Hiçbir tabloda yok.** `orders`'ta carrier alanı yok; `ship_tos` yalnız `shipping_method_id/code/cost/ship_date/packages`; `shipping_methods` yalnız ad/açıklama/kod/oran/transit gün. Yeni: `shipping_methods.carrier_title` + `carrier_vkn`, Ayarlar → Kargo Yöntemleri ekranına iki alan |

Bu üçü **Faz 2'nin iş kalemidir**, "siparişten okunur" varsayımı düşmüştür.

> Not: `orders.tracking_code` kargo takip numarası **değil**, pazarlama referans kodudur. Karıştırılmamalı.

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

### 6.6 Tahsilat / ödeme
Çekmece formu: cari → tutar → kasa/banka → tarih → açıklama → (varsa) kapatılacak fatura(lar). Kısmi tahsilat destekli. Fatura seçilmezse açık hesaba yazılır — **en eski faturadan otomatik kapatma yok** (sürpriz eşleştirme mutabakatı bozar). Dövizli carilerde kapatma orijinal para biriminde, fark `fx_diff`.

### 6.7 Açılış bakiyesi
`kind='opening'` **tek hareket** + tarih. Toplu giriş için CSV içe aktarma, mevcut CSV altyapısı.

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

## 8. [v3] Panel

### 8.1 Tasarım dili
`api_settings.php`'de onaylanan **"satırlar + sağdan çekmece"** dili sürüyor. Doğrulanan iskelet:
- Liste CSS grid: `.api-head/.api-row { display:grid; grid-template-columns:...; min-width:760px }` (`api_settings.php:779-783`)
- Satır: `<div class="api-row" data-app="ID" role="button" tabindex="0">` (`:637-653`)
- Tek çekmece tüm satırlara hizmet eder, kendi `<form>`'unu taşır (`:903-930`)
- Veri PHP'den tek blokta gömülür: `var APPS = ' . json_encode($data, JSON_UNESCAPED_UNICODE) . ';` (`:1123`)
- Sayfa kabuğu: `pg_page_shell(array('title','icon','heading','heading_description'))` — `output.php:345`

**Sohbet balonu (z-index 1080) çekmece açıkken gizlenir** — genel hâli zaten var:
```css
body.pg-side-panel-open .pg-chat-launcher { display: none !important; }  /* backend.src.css:6538 */
```
ERP ekranları **bu genel sınıfı** kullanır, `api_settings.php`'deki özel `api-drawer-open` kopyasını değil.

Bilinen tuzaklar, baştan uygulanacak:
- Dar ekranda liste **yatay kayar** (kap `overflow-x`, satır `min-width`); satırlar bloklara bölünmez (CLAUDE.md "Tablo Widget'larında Dar Ekran")
- Grid öğelerine `min-width: 0`
- Açıklama metinleri somut ve örnekli: "Vade: fatura tarihinden kaç gün sonra (örn. 30 → 16 Eylül faturası 16 Ekim'de vadesinde)"

### 8.2 Ekranlar
| Ekran | İçerik |
|---|---|
| `erp_dashboard.php` | Kasa/banka bakiyeleri, bugünkü tahsilat/ödeme, vadesi geçen alacak, e-belge hataları, son 10 hareket |
| `erp_accounts.php` | Cari liste (tür, bakiye, borçlu/alacaklı renk) + çekmece: **Kart / Ekstre / Belgeler** |
| `erp_invoices.php` | Fatura listesi (yön, durum, e-belge rozeti, **iptal süresi sayacı**) + çekmece: satırlar, e-belge durumu ve hatası, PDF, "Yeniden gönder" |
| `erp_invoice_edit.php` | Satır editörü — **tam sayfa**; ürün arama, satır ekleme, canlı toplam, istisna/tevkifat kodu |
| `erp_cash.php` | Kasa/banka kartları + hareket defteri + virman |
| `erp_waybills.php` | İrsaliye listesi + çekmece |
| `erp_settings.php` | Modül anahtarı, seri/numaratör, varsayılan kasa, otomatik fatura kuralı, senaryo varsayılanı, web adresi, Paraşüt bağlantısı ve durumu |

**Durum (2026-09-22):** §8.2 tamam — panoda e-Belge kartı, bugünkü giriş/çıkış ve son 10 hareket (kasa hakkına bağlı); fatura listesinde `?edoc=` süzgeci ve toplu gönder / GİB'e gönder / durum sor; cari, fatura ve irsaliye listelerinde salt okunur yan çekmece (`includes/erp/drawer.php`, `get_erp_drawer.php`, `assets/js/erp_lists.js`). İptal süresi sayacı yok: İşbaşı sürücüsü iptali desteklemiyor, Paraşüt sürücüsüyle birlikte gelir.

**Devir notu (Erdal, 2026-09-21 akşam):** her tur sonunda ERP devir notu yazılmasına gerek yok — ERP zaten bu ajanın görevi. Not yalnız istenince ya da gerçek bir devir anında güncellenir; `degisiklikler.md` ve `changelog.txt` kuralı aynen sürer.

**Menü:** `includes/fn/output.php:1592` `$menu_items` dizisi. Slot numaraları kalıcıdır (kullanıcı `user.selected_appmenu_items_array`'e pinliyor), **yeniden kullanılmaz**. 0–21 dolu (20 bilerek boş), ERP **22**'yi alır. Ayrıca `switch ($file_name)` (`:2174-2404`) içine `case 'erp_*.php': $active_menu = 22; break;` ve `backend.src.css`'e `.erp-color` (`--erp-color: #8a6d1f`, `--erp-color-highlight: #e6c04f`; 2026-09-21'de #10618c'den bronza çevrildi — eski ton Sayfalar 210° ile Ziyaretçiler 187° arasına sıkışıyordu). Kutucuk glifi `bi-safe2-fill`; açılır menü `bi-columns-gap` · `bi-building` · `bi-receipt` · `bi-hourglass-split` · `bi-clipboard2-check` · `bi-box-arrow-up` · `bi-cash-stack` · `bi-graph-up-arrow` · `bi-sliders`. Favicon seti `erp` (`assets/icons/ico/big/erp.ico` + `png/075|100|150|200/erp.png`), ERP ekranlarında `'icon' => 'erp'`.

### 8.3 [v3] Yetkiler — üçlü ENUM bu kod tabanında yok
v2 "Yok / Okuma / Yazma üçlü seçim" diyordu. Gerçek: yetki sütunları `ENUM('no','yes')` (eski, `user_` önekli) veya `TINYINT` (yeni, öneksiz). Üçlü desen yalnız **dış API scope'larında** var (`includes/api/scopes.php`, `read`/`write`).

**Karar:** okuma/yazma ayrımı gereken yerde **iki sütun** (`erp_accounts` + `erp_accounts_write`), `TINYINT UNSIGNED NOT NULL DEFAULT 0`, **öneksiz** ad. Yazma açıksa okuma kilitli-açık (UI kuralı, `assets/js/backend.src.js:8440-8670` `data-pg-gate-*` mekanizması bunu zaten yapıyor).

Yetki alanları: `erp_accounts` · `erp_invoices` · `erp_cash` · `erp_waybills` · `erp_settings`. **Kasa/banka ayrı satır** — cari görebilen herkesin kasa bakiyesini görmesi gerekmiyor.

**[v3] Yeni yetki 14 dosyaya dokunuyor** (tam liste):

| # | Dosya | Ne |
|---|---|---|
| 1 | `includes/migrations/2026.4.4.php` | `install_add_column('user', 'erp_*', ...)` |
| 2 | `includes/authentication.php:131-160` | SELECT listesine alias'lı sütun |
| 3 | `includes/fn/auth.php:3359+` | `define('USER_MANAGE_ERP', ...)` |
| 4 | `includes/fn/auth.php` ~2116 | `validate_erp_access($user)` guard |
| 5 | `includes/user_permissions.php:188+` | `$groups[]` ERP grubu |
| 6 | `includes/user_permissions.php:439-447` | `pg_user_permission_everything()` **elle tutulan liste** |
| 7 | `add_user.php:66,96,215,355,387,623` | panels, ui, render, INSERT, değer, "en az bir hak" |
| 8 | `edit_user.php:156,214,501,552,1343` | SELECT, değişken, panels, values, UPDATE |
| 9 | `import_users.php:554-560` | INSERT sütun listesi |
| 10 | `reset_password.php:55-59,122` | SELECT + "panel erişimi var mı" |
| 11 | `view_users.php:453,664` | Filtre WHERE + liste SELECT |
| 12 | `includes/fn/output.php:1890+` | Menü grubu koşulu |
| 13 | `includes/api/auth.php:314-331` | API tarafı yetki okuması |
| 14 | `includes/notifications.php:60-80` | Bildirim görünürlük merdiveni (§8.5) |

### 8.4 [v3] Sistem Durumu widget'ı
Kontroller `includes/fn/system_status.php` → `get_system_status_checks()` (`:955-2273`). `welcome.php` hiç kontrol tanımlamaz.

Yeni kontrol: **ERP / e-belge** — ağırlık **6** (webhook ile aynı), **`maintenance`** grubu (`$group = 'maintenance';` — `:1876`).
- Modül kapalıysa **hiç çizilmez** (webhook kontrolündeki kural — çalışmayan kontrol paydaya girmez, `:1191-1194`)
- `abandoned` kuyruk satırı veya `edoc_status='rejected'` fatura → **kırmızı, tam ağırlık**
- 24 saatten eski `pending` satır **veya** `parasut_job_expires_at` geçmiş ama `reconcile` kurulmamış belge → **sarı, yarım**
- Temiz → yeşil
- Glif: `bi-receipt` (`bi-send-fill` webhook'ta, `bi-broadcast` IndexNow'da — aile paylaşılmaz)

**Eklenecek 6 nokta** (hepsi `system_status.php`):
1. `$weights` dizisine `'erp_edoc' => 6` (~`:1046`)
2. `$check_weights` dizisine `'ERP e-Document' => $weights['erp_edoc']` (~`:1073`)
3. `$short_labels` dizisine kısa etiket (~`:1117-1145`)
4. `$job_titles` listesine (cron'a bağlı kontrol olduğu için)
5. `$group = 'maintenance'` bloğunda `$output .= $makeIcon(...)` + `$score -= $weights['erp_edoc'];`
6. ⚠ **Cache şema sürümünü bump et** — `:1010` (`(int)$cached['v'] === 6`) **ve** `:2253` (`'v' => 6`). Yapılmazsa yeni kontrol `data/temp/system_status_cache.json` yüzünden **10 dakika görünmez**.

### 8.5 Bildirimler
Mevcut altı olay (`new_order`, `out_stock`, `form_submited`, `new_comment`, `software_update`, `custom`/sohbet) `create_notification()` ile yazılıyor (`includes/fn/core.php:2384`), görünürlük tek yerde: `pg_notification_visible()` — `includes/notifications.php:60-80`.

ERP iki aday getiriyor: **e-belge reddedildi/hata** ve **vadesi geçen alacak**. İkincisi günlük ve tekrarlayan.
**Karar:** yalnız **e-belge reddedildi/hata** eklensin (nadir, eyleme çağıran, ertelenemez). Vade hatırlatması bildirim değil, panoda satır.

Eklenecek: `pg_notification_display()` (`notifications.php:192+`) yeni `elseif`, `pg_notification_visible()` kuralı, `$rights` dizisine `manage_erp` (her iki sarmalayıcıda: `:85` ve `:97`), tetikleme noktasında `create_notification()`, `assets/images/notification-erp.png` + `-badge.png`. Push istenirse `core.php:2464` koşuluna action eklenir.

### 8.6 [v3] AJAX deseni — `?ajax=` kolu bu tabanda yok
Üç yerleşik desen var, ERP üçünü de kullanır:

| Desen | Nerede | ERP'de |
|---|---|---|
| **A** — merkezi `api.php` + `action` | `welcome.php:2042` istemci, `api.php:571` sunucu, CSRF `validate_token()` (`api.php:14273`) | Yalnız **dashboard widget'ı** |
| **B** — ayrı JSON uç dosyası | `product_attribute_action.php:38-97` (temiz örnek: `ob_end_clean()` + `Content-Type` + HTML basmayan yetki kontrolü + elle token karşılaştırma) | **`erp_action.php`** — çekmece içi canlı sorgular, ürün arama, satır ekleme |
| **C** — klasik form POST | `api_settings.php:45` — `validate_token_field()` + `get_token_field()` | **Liste + çekmece ekranları** |

⚠ Desen A kullanılacaksa `api.php:146-230`'daki rol kapısının muafiyet listesine eklenip kendi kontrolünü yapması gerekir.

### 8.7 [v3] i18n
`includes/local/tr.json` — **düz tek seviyeli JSON**, 10.985 anahtar. **Anahtar = İngilizce cümlenin kendisi.** Nokta ayrılmış kod anahtarı (`erp.invoice.title`) bu tabanın desenine **aykırı** — `lang('Invoice')` yazılır.

`lang()` — `includes/fn/core.php:2715`. Değişkenli kullanım:
```php
lang(array('string' => '{var:1} invoice{suffix:1}', 'vars' => $n, 'suffix' => ($n == 1) ? '' : 's'))
```
Modifier: `{var:1|c}` title case, `|u` upper, `|l` lower. Çeviri yoksa **anahtarın kendisi döner** (güvenli fallback). HTML'e `h(lang(...))`.

> `docs/LANG_USAGE.md` **`pinegrap/` altında değil**, `docs/` klasöründe (repo kökünde). Yeni string yazmadan önce okunur.

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

## 9. Dış API (integration.php) — Faz 5
`includes/api/resources/` altına `accounts.php`, `invoices.php`, `cash.php`.
Kapsamlar `includes/api/scopes.php:27-50`'ye: `'erp_accounts' => array('label'=>'ERP Accounts','read'=>'accounts:read','write'=>'accounts:write')` vb.
Uçlar `includes/api/schema.php:51`'e satır olarak; `bootstrap.php:38-44`'e `require_once`.
**Belgeler ve yetki ekranı şemadan otomatik üretiliyor** — `api_settings.php` ve `api_docs.php`'ye elle dokunmaya gerek yok (`schema.php:24-26`).
Yeni webhook olayları: `invoice.created`, `invoice.edoc_status_changed`, `payment.received`.
Yol deseni sürüm segmentsiz: `integration.php/invoices`.

---

## 10. [v3] Fazlar

### Faz -1 — Paraşüt acil onarımı (§1)
2026.4.4 ile birlikte çıkar. **Kabul:** §1.4.

### Faz 0 — İskelet
Modül anahtarı, `upgrade_2026_4_4_erp_core()` (11 tablo + mevcut tablo sütunları), `get_tables()`, menü slot 22, 5 yetki alanı (14 dosya), boş ekran kabukları, tr.json anahtarları.
**Kabul:**
- Modül kapalıyken site bugünküyle birebir aynı; açıldığında menü geliyor, ekranlar boş açılıyor
- `UPDATE config SET version='2026.4.3'` + yükseltme → 11 tablo doğru şemayla kuruluyor; **ikinci koşumda hepsi "zaten var" diyerek atlanıyor**
- **Temiz kurulum** (boş DB → tohum dökümü → migration) 11 tabloyu kuruyor; aynı DB'ye yeniden kurulumda `get_tables()` hepsini siliyor
- Yetkisiz kullanıcı `erp_*.php`'yi açamıyor; `add_user.php`/`edit_user.php`/`import_users.php` yeni alanları tutarlı yazıyor

### Faz 1 — Cari hesap + kasa/banka
Cari CRUD, `contacts` eşlemesi ve toplu üretim, açılış hareketi, ekstre, kasa/banka, tahsilat/ödeme fişi, virman, dövizli cari + `fx_diff`, CSV içe aktarma.
**Kabul:**
- 100 hareketlik ekstre `SUM()` ile birebir; önbellek ile hesap her zaman eşit
- Tahsilat kısmen başarısız olduğunda (kasa yazıldı, cari yazılamadı) **hiçbir satır kalmıyor** — InnoDB transaction testi
- Açılış bakiyesi **bir kez** sayılıyor
- EUR cariye EUR fatura + farklı kurla EUR tahsilat → cari **her iki para biriminde de sıfır**, fark `fx_diff` satırında
- **[v3]** Tüm tutarlar kuruş; hiçbir yerde `float` yok, `bcmath` çağrısı yok

### Faz 2 — Fatura
**Ön koşul:** §5.9 yuvarlama tabanı kararı (ölçüm + A/B seçimi). Mali müşavir doğrulaması.
Numaratör (yalnız satış), satır editörü, hesaplama (iskonto → matrah → vergi → tevkifat), istisna/tevkifat kodları, VUK 509 alanları **ve eksik üç sütunun eklenmesi** (§6.4), sipariş→fatura köprüsü **başlık kalemi dağıtımıyla** (§6.3), iade akışı (§6.5), iptal, PDF.
**Kabul:**
- Fatura toplamı siparişin toplamına **kuruşu kuruşuna** eşit — test vakası: **3 kalemli, %1 iskontolu, karışık KDV oranlı, birim fiyatı 1 TL'nin altında, kargolu, vade farklı, hediye çeki kullanılmış** bir sipariş
- **[v3]** Kargo, vade farkı faturada kalem; indirim satırlara dağıtılmış ve **dağıtım toplamı = `orders.discount`**; hediye çeki kasa hareketi
- `products.tax_rate` NULL / 0.000 ayrımı korunuyor **ve** 0.000 için istisna kodu zorunlu
- Aynı siparişe ikinci **satış faturası** kesilemiyor; **iki kısmi iade** kesilebiliyor, `returned_qty ≤ invoiced_qty` tutuyor
- Alış faturası satış serisinden numara **tüketmiyor**; numarada delik yok
- **[v3]** `add_order.php` siparişi ya doğru KDV'yle faturalanıyor ya da operatöre uyarı çıkıyor — **✅ 2026-09-20: KDV'yle faturalanıyor** (dev'de #1531 → PGF…008, KDV 134,82)

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

### Faz 4 — İrsaliye (+ e-irsaliye, §7.6 koşuluyla)
**Kabul:** irsaliyeden faturaya dönüşüm miktarları taşıyor; kargolanmış sipariş için irsaliye tek kez üretiliyor. e-İrsaliye ancak API ucu doğrulandıysa kapsamda.

### Faz 5 — Raporlama ve açılım
Yaşlandırma, kasa akışı, cari mutabakat mektubu (PDF), dış API kaynakları, ERP webhook olayları, e-belge hata bildirimi.

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

## 12. [v3] İş sırası

1. **Faz -1** — Paraşüt onarımı. 2026.4.4 ile çıkar, lansmanı bekletmez.
2. **Faz 0** — `upgrade_2026_4_4_erp_core()`. Bir oturumda biter, gerisini risksiz kılar.
3. **Paraşüt API kimlik bilgisi kontrolü/başvurusu** — Faz 1 ile aynı gün, teslimatı beklemeden devam.
4. **§5.9 ölçüm betiği** — mevcut siparişlerde birim-bazlı / satır-bazlı yuvarlama farkı kaç kuruş? Faz 2'nin kararı buna dayanır.
5. **Faz 1** — tek başına değerli: mağaza faturasız da cari ve kasa tutar. (2026.4.4 yayınlandıktan sonra 2026.5.0 açılır.)
6. **Faz 2** — burada ürün "ön muhasebe" olur. Mali müşavir doğrulaması bu fazdan önce.
7. **Faz 3** — kimlik bilgisi geldiğinde ve Faz -1 bittiğinde.
8. Faz 4–5 talebe göre.

---

## 13. [v3] Doğrulanan Pinegrap bilgileri (v2 §13'ün cevapları)

| v2 sorusu | Cevap |
|---|---|
| 1. `config`'te şifreli alan yardımcısı? | Genel bir `pg_encrypt` **yok**. İki desen: `encrypt_string_with_iv()`/`decode_ssl_keys()` (`auth.php:4540`/`:4525`, çift sütun `<alan>`+`<alan>_iv`) ve `mp_credentials_encode()`/`_decode()` (`api/outbound/connectors/base.php:203`/`:216`, tek sütun `"cipher:iv"`). Anahtar `ENCRYPTION_KEY` (`data/config.php`). **`api_settings.php` şifrelemiyor, hash'liyor** (`api/keys.php:83`) |
| 2. `api/outbound/http.php`? | Var (259 satır). `api_http_request/post/check_url/should_retry/address_is_allowed`. Metot serbest. `timeout` 10 sn, `CONNECTTIMEOUT` 5 sn, `max_body` **1000 bayt** (0 = kesme yok). SSRF: şema beyaz listesi + `NO_PRIV_RANGE\|NO_RES_RANGE` + `CURLOPT_RESOLVE` pinning + redirect kapalı + TLS zorunlu |
| 3. Mevcut Paraşüt entegrasyonu? | §1. **Bu plan onun yerine geçiyor**, ama önce Faz -1'de onarılıyor |
| 4. `job.php` tetikleyicisi ve `inline` sözleşmesi? | **Gerçek cron** (`prep.php:1816,1828` komutu basıyor). `inline` = rotasyondan çık (`cron.php:585`), `job.php` her tick doğrudan çağır (`job.php:333-356`), `pg_cron_job_is_enabled()` yine geçerli (`cron.php:544`), `pg_cron_ran()`'ı job.php yazar. Bütçe 10–15 sn. Kilit `config.job_dispatch_lock_until`, **TTL 3600 sn** — `GET_LOCK` değil |
| 5. `process_order_cancellation()`? | `ecommerce.php:6187`. Hook yok; tek duyuru `api_webhook_enqueue('order.cancelled',...)` `:6374`. 5 çağıran |
| 6. `customers`'ta VKN? | **Tablo `contacts`.** `tax_number` VARCHAR(20) + `tax_office` VARCHAR(100) var (2026.1.17). `custom_field_1/2` `contacts`'ta değil `orders`'ta. Fatura adresi `orders.billing_*` |
| 7. Kargo/indirim/kupon faturaya nasıl? | Hepsi **`orders` başlık sütunu**, kalem değil. Kargo gerçek kaynağı `ship_tos.shipping_cost`. Kupon ayrı sütun değil: `special_offer_code` + `discount_offer_id` + tutar `discount`'ta. Tek "hesapla" fonksiyonu yok; toplam `submit_order.php:807-880`'de inline. §6.3 |
| 8. Vergi oranı fonksiyonu? | `get_tax_rate_for_address()` (`forms.php:2910`) + `get_effective_tax_rate()` (`ecommerce.php:1088`). Uygulama `update_order_item_taxes()` (`ecommerce.php:1204`). §6.2 |
| 9. Taşıyıcı ünvan + VKN siparişte? | **Yok.** §6.4 |
| 10. Menü / tr.json? | Menü `output.php:1592`, slot 22 boş. `tr.json` düz İngilizce-cümle anahtar. §8.2, §8.7 |
| 11. bcmath? | **Kullanılmıyor** (vendor hariç 0 sonuç). Para **tamsayı kuruş**, `round()` 216 çağrı ve para hesaplarında **hep 0 haneye**. İstisnalar Paraşüt eşlemesinde 2 hane (`parasut.php:247,554,591`) |

### 13.1 [v3] Yol boyunca ortaya çıkan, ERP'den bağımsız hatalar
Bunlar ERP'nin işi değil ama kaydedilmeli:
1. `submit_order.php:850` — `$subtotal == 0` iken sıfıra bölme
2. Sepet önizlemesi satır-toplamı, ödeme birim bazında vergi hesaplıyor → qty>1'de kuruş farkı (`widgets_cart.php:722` vs `ecommerce.php:1325`); `widgets_cart.php:2159-2164`'te eski bir belirtisi kayıtlı
3. `get_express_order.php:3166,5712` ve `get_order_preview.php:1515,2972` vade farkını **2 haneye**, `submit_order.php:901` **0 haneye** yuvarlıyor → gösterilen ile çekilen tutar farklı olabilir
4. ~~`add_order.php:283-284` — panel siparişinde vergi ve kargo hiç hesaplanmıyor~~ — KDV 2026-09-20'de eklendi; kargo tezgâh satışında bilinçli olarak yok
5. `includes/phpexcel` 11 dosyada ölümcül sözdizimi hatası (`HATA_RAPORU.md:11,42`)
6. `orders.parasut_exported` iki farklı anlam taşıyor
7. `init.php:634-635` — `ENABLE_PARASUT` ve `PARASUT_TC_IN_FIELD` `??` koruması olmadan yazılmış, sütun eksikse notice

---

## 14. Sonraki adım

Onay verilirse **Faz -1** için dosya dosya iş listesi çıkarılır (`parasut.php` düzenlemeleri satır satır, migration alt adımı, ayar ekranı değişikliği), ardından **Faz 0**: migration alt adımının tam SQL'i, `get_tables()` yaması, menü slot 22, 14 dosyalık yetki turu, ekran kabukları.

---

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

### [v3] Kaynaklar — Pinegrap kod tabanı
Tüm satır numaraları 2026-09-16 tarihli `dev` çalışma kopyasından. §13 tablosu ve metin içi referanslar doğrudan okunarak alındı.
