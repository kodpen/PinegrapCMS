# ERP — mali müşavire sorulacaklar

**Başlangıç:** 2026-09-24 · **Kapsam:** Pinegrap ERP (ön muhasebe) · **Kullanım:** her maddenin
"Cevap" satırı müşavirin cevabıyla doldurulur; "Ayar" yazan maddelerde cevap ekrandan seçilir,
kod değişmez. Cevabı gelen madde ilgili ekrana ya da yeni bir işe dönüşür.

Kaynaklar: `docs/_plan_erp.md` (§6.5 iade, §11 mevzuat notu), `docs/degisiklikler.md`,
`docs/_isbasi_api_notlari.md`, `docs/_erp_devir_notu.md` ("Mali müşavire").

---

## 1. Cevabı ayardan seçilenler

### 1.1 Satışta tevkif edilen KDV ve KDV farkı

- **Soru:** Kısmi tevkifatlı satışta (ör. 624 yük taşımacılığı, 2/10) beyannamede hesaplanan
  KDV'yi tam mı gösteriyoruz, yoksa alıcının beyan ettiği tevkif kısmı düşülmüş mü? KDV
  raporundaki "fark" hangisine göre olmalı?
- **Bugün:** Hesaplanan KDV tam kalır, tevkif edilen kısım yanında ayrı satırda durur; fark =
  hesaplanan − indirilecek.
- **Ayar:** ERP Ayarları → Muhasebe kuralları → "Satışta tevkif edilen KDV". İkinci seçenek
  tevkif edilen kısmı hesaplanandan düşer; KDV raporu ekranı, Excel'i ve muhasebeci paketi
  birlikte değişir. Faturalar değişmez.
- **Cevap:** —

### 1.2 %0 KDV'li satırların istisna / muafiyet kodu

- **Soru:** KDV'siz sattığımız mal ve hizmetler hangi GİB koduyla gitmeli (ör. 351 "KDV –
  İstisna Olmayan Diğer"; ihracatta başka kod)? Ürüne göre farklı kod gereken satışımız var mı?
- **Bugün:** Ayarlanan kod, ürünün kendi kodu yoksa her %0 satıra yazılır (siparişten, elle ve
  iade faturası). Dev'de 351 seçildi; İşbaşı test ortamında GİB kabul etti
  (PGF2026000000034 → REE2026000000181, UBL'de `TaxExemptionReasonCode` 351).
- **Ayar:** ERP Ayarları → Muhasebe kuralları → "%0 KDV'li satırların istisna kodu".
- **Not:** Ürün başına kod kolonu (`products.vat_exemption_code`) var ama ürün ekranında alanı
  yok; ürüne göre farklı kod gerekirse ürün ekranına eklenir.
- **Cevap:** —

### 1.3 Hangi giderlerin KDV'si indirilemez

- **Soru:** Gider kategorilerimizden hangilerinin KDV'si indirilemez (binek otomobil giderleri,
  yemek ve temsil-ağırlama, banka masrafları (BSMV), maaş ve SGK…)?
- **Bugün:** Kategori başına "Vergi indirilir" anahtarı; yeni gider formu buradan başlar, her
  gider yine tek tek değiştirilir. Dev'de deneme olarak "Banka masrafları ve komisyonlar"
  kapatıldı, diğerleri açık.
- **Ayar:** Giderler → Kategoriler → "KDV" sütunu.
- **Cevap:** —

### 1.4 Kargo, ek ücret ve taksit masrafının KDV'si

- **Soru:** Müşteriye ayrıca yansıttığımız kargo, ek ücret ve taksit farkı faturada nasıl
  gösterilmeli: tutarın içinden malın oranıyla mı, vergisiz mi?
- **Ayar:** Site Ayarları → Ticaret → ERP → "Nakliye ve ek ücretlerin vergisi" (mağazanın ülkesine
  göre / malın oranıyla tutarın içinden / vergisiz).
- **Cevap:** —

### 1.5 Belge numaraları

- **Soru:** e-Belge kullanmadığımız dönemde (dahili PDF) numaralama yıllık mı, sürekli mi olmalı?
  (e-Belgede GİB biçimi zorunlu.)
- **Ayar:** Site Ayarları → Ticaret → ERP → "Belge numaraları".
- **Cevap:** —

### 1.6 Dönem kilidi

- **Soru:** Beyanname verildikten sonra ayı hangi gün kilitleyelim; geriye dönük düzeltme hangi
  yolla yapılmalı (iptal/iade bugünün tarihiyle mi)?
- **Ayar:** ERP → Muhasebeci paketi → "Dönem kilidi".
- **Cevap:** —

### 1.7 Muhasebeci paketi

- **Soru:** Paket her ay otomatik gitsin mi, hangi gün; belge PDF'lerini istiyor mu?
- **Ayar:** ERP → Muhasebeci paketi (aylık gönderim, PDF'ler, e-posta adresi).
- **Cevap:** —

---

## 2. Ayar olarak çözülemeyenler

Her biri için cevaba göre ya bir ayar ya da yeni bir iş açılır.

### 2.1 Nihai tüketiciye (e-Arşiv) iade

- **Soru:** Tüketiciye kesilmiş e-Arşiv faturada iade nasıl yapılmalı: e-Arşiv iptali mi (kaç
  gün içinde), iade faturası mı, faturanın iade bölümü mü?
- **Bugün:** Resmîleşmemiş fatura iptal edilir; resmîleşmiş olana ERP kendi serisinde iade belgesi
  keser. Logo İşbaşı sürücüsü İADE tipli belgeyi göndermiyor. İşbaşı işlenmemiş e-Arşiv'in
  iptaline izin vermiyor.
- **Cevap:** —

### 2.2 e-Fatura mükellefi alıcıda iade

- **Soru:** Alıcı e-Fatura mükellefiyse iade faturasını alıcı mı keser (bize alış faturası olarak
  mı gelir), biz mi keseriz?
- **Bugün:** ERP satış iadesi keser. Cevap "alıcı keser" ise: sipariş kartında "alıcının iade
  faturasını bekle" yolu ve gelen e-faturadan alış olarak alma (bir ayar ve orta boy iş).
- **Cevap:** —

### 2.3 e-Arşiv iptal süresi

- **Soru:** e-Arşiv faturanın iptal süresi ve yolu nedir (portal / entegratör), süre geçince ne
  yapılır?
- **Bugün:** ERP süre saymıyor; sağlayıcı kabul etmezse yerelde de iptal edilmez.
- **Cevap:** —

### 2.4 "İrsaliye yerine geçer"

- **Soru:** Hangi satışlarda fatura irsaliye yerine geçer, ayrı sevk irsaliyesi ne zaman gerekir?
- **Bugün:** İşbaşı her entegrasyon faturasını `dispatchIncluded: true` ile işliyor (Logo'ya da
  soruldu). Karar: şimdilik bekle.
- **Cevap:** —

### 2.5 Tezgâh satışı, ÖKC fişi ve nihai tüketici kimliği

- **Soru:** Yazar kasa (ÖKC) kullanan mağazada her tezgâh satışına fatura gerekir mi, fişli
  satışa fatura kesilirse ne olur? Nihai tüketiciye 11111111111 ile e-Arşiv kesmenin tutar sınırı
  var mı (alıcının TCKN'si hangi tutardan sonra zorunlu)?
- **Bugün:** ERP açıkken her tezgâh satışı faturalanır ve satışta vergi numarası sorulmaz (Erdal'ın
  kararı).
- **Cevaba göre:** "Tezgâhta faturalama: her satış / istenince" ve "şu tutarın üstünde TCKN iste"
  ayarları.
- **Cevap:** —

### 2.6 e-Fatura senaryosu: temel mi, ticari mi

- **Soru:** e-Fatura mükelleflerine temel mi, ticari mi fatura keselim (ticaride alıcı
  reddedebilir)?
- **Bugün:** İşbaşı gönderilen faturayı TICARIFATURA olarak işliyor (UBL'de görüldü). ERP'de
  seçim yok; `config.erp_einvoice_scenario` kolonu var ama kullanılmıyor. İşbaşı belgesinde
  senaryo cari kartındaki `eInvoiceProfile` ve bir gönderim gövdesindeki `scenario` (sayı)
  alanlarında görünüyor, değerlerin anlamı belgede yok (Logo'ya sorulacak).
- **Cevaba göre:** ayar + sürücü değişikliği.
- **Cevap:** —

### 2.7 Kur farkı faturası

- **Soru:** Dövizli faturanın tahsilatında oluşan kur farkı için kur farkı faturası (KDV'li)
  kesmeli miyiz, kim keser?
- **Bugün:** Yalnız defterde kur farkı kaydı (Site Ayarları → Ticaret → ERP → "Dövizli fatura
  tamamen ödendiğinde kur farkını kendiliğinden kaydet").
- **Cevaba göre:** kur farkı faturası taslağı açan bir adım.
- **Cevap:** —

### 2.8 Vade farkı

- **Soru:** Geç ödemeye vade farkı faturası kesiyor muyuz, oran nasıl belirlenir?
- **Bugün:** Yok (kredi limiti var, vade farkı hesabı yok).
- **Cevap:** —

### 2.9 Hediye kartı

- **Soru:** Hediye kartı satışı faturalanır mı (avans mı), yoksa kart kullanıldığında mı?
- **Bugün:** Hediye kartıyla ödenen kısım ödeme sayılır, faturada indirim değildir. Hediye kartı
  ürününün satışını kod ayrıca ele almıyor: siparişte sıradan bir kalem gibi faturaya girer.
- **Cevap:** —

### 2.10 Stok maliyet yöntemi

- **Soru:** Stok değerlemesi ağırlıklı ortalama ile uygun mu, FIFO mu gerekir?
- **Bugün:** Ağırlıklı ortalama; alış stoğa girer, satış ortalamadan çıkar.
- **Cevap:** —

### 2.11 Tevkifat kodları

- **Soru:** Mağazanın hangi alış ve satışlarında tevkifat uygulanıyor, kodları doğru mu?
- **Bugün:** GİB listesi (UBL-TR Kod Listeleri v1.38, Eylül 2025) ERP'de; kod satırda seçilir.
- **Cevap:** —

### 2.12 Alışta tevkifat ve 2 No.lu KDV beyannamesi

- **Soru:** Sorumlu sıfatıyla beyan edilecek (alışta tevkif edilen) KDV için pakette ayrı bir
  döküm istiyor musunuz?
- **Bugün:** Pakette ve KDV raporunda "Alışlarda tevkif edilen KDV" satırı ve koda göre tablo var.
- **Cevap:** —

### 2.13 Serbest meslek makbuzu

- **Soru:** Mağaza serbest meslek makbuzu düzenliyor ya da alıyor mu?
- **Bugün:** ERP'de yok; İşbaşı'nda uç var (`Payments/smms`).
- **Cevap:** —

### 2.14 İhracat ve mikro ihracat

- **Soru:** Yurt dışı satışlarda fatura türü, istisna kodu ve GTİP nasıl olmalı; mikro ihracatta
  (ETGB) ne gerekiyor?
- **Bugün:** Yurt dışı cariye bölgesiz satış %0; istisna kodu 1.2'deki ayardan (ya da üründen).
- **Cevap:** —

### 2.15 Özel matrah

- **Soru:** Özel matrah uygulanan satışımız var mı (ör. ikinci el)?
- **Bugün:** ERP'de özel matrah yok (Logo'ya da soruldu).
- **Cevap:** —

### 2.16 Fatura tarihi

- **Soru:** Siparişin faturası hangi tarihle kesilmeli: sipariş, teslim ya da kesim günü (VUK
  7 gün kuralı)?
- **Bugün:** Kesim günü (GİB aynı türde tarih sırası istiyor).
- **Cevap:** —

### 2.17 Muhasebe programına aktarım

- **Soru:** Kayıtları hangi programa (Logo, Luca, Mikro, Zirve, ETA…) aktarıyorsunuz, hangi biçim
  işinize yarar (Excel, XML)? Hesap planı kodlarını (ör. gider kategorisinde 770) siz mi
  vereceksiniz?
- **Bugün:** Paket Excel + belge PDF'leri; gider kategorisinde hesap kodu alanı var.
- **Cevap:** —

---

## 3. Logo İşbaşı'na sorulacaklar (müşavire değil)

Liste `docs/_isbasi_api_notlari.md` "Doğrulanmadı / Logo desteğine sorulacak" bölümünde ve
devir notunda. Bu turda eklenen: senaryo alanlarının (`eInvoiceProfile`, `scenario`) değerleri;
`/api/v1.0/master/vatexcepts` belgede var ama 404 dönüyor.
