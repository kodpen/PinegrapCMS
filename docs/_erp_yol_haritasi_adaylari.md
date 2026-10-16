---
name: _erp_yol_haritasi_adaylari
description: ERP roadmap candidates after phases -1 to 5: legislation, competitor features, building blocks (research, not decisions)
---

# ERP — sonradan eklenebilecek özellikler (yol haritası adayları)

**Tarih:** 2026-09-20 · **Durum:** araştırma notu, karar değil · **Kapsam:** ERP Faz -1–5 bittikten sonrası

Plandaki bütün fazlar (Faz 3 hariç, Paraşüt anahtarı bekliyor) çalışma ağacında
bitti. Bu not, ERP'nin bundan sonra nereye büyüyebileceğini üç kaynaktan
derler: 2026 mevzuatı (neyin zorunlu olduğu), Türkiye'deki ön muhasebe
programlarının bugün ne verdiği (Paraşüt, Logo İşbaşı, Bizim Hesap, KolayBi,
Mikro) ve Pinegrap'ın elinde zaten olan yapı taşları (mağaza, pazaryeri
bağlayıcıları, ödeme kuruluşu, PWA bildirimi, kur servisi, dış API). Her aday
için değer, iş yükü (K/O/B — küçük / orta / büyük), bağımlılık ve bir karar
notu var. **Erdal seçer;** sıralama önerisi en sonda.

---

## 0. Durum ve kalanlar (2026-10-14)

Biten adaylar (ayrıntı git geçmişindeki `docs/degisiklikler.md`): A2, A4 / C6 / F3 (faturayı e-postala),
B1 (banka ekstresi; 2026.4.10'da xlsx, xls ve .ods), B6, B7, C1, C2, C3, C4 (kredi limiti
4.98, vade farkı 2026.4.10), D1, D2 (satın alma siparişi, ERP Faz 7), D3, D5, E1, E2, E3,
E4, F2, G1, G2, G4 ve B5'in "Faturalarım" kısmı (ERP Faz 8a). Bunların satırları §2'den
çıkarıldı; 2026-09-24 durum tablosu ve öneri sırasıyla tam metin:
`git show d9fec3a:docs/_erp_yol_haritasi_adaylari.md`. Sonraki seçim 2026-10-13'te proje
notu `notes/erp-secenekler.md` (29 madde) ve ERP Faz 6–9 planıyla yapıldı.

**Kalanlar:**

| Aday | Durum |
|---|---|
| A1 e-Fatura / e-Arşiv gönderimi | İşbaşı zinciri test ortamında çalışıyor; Paraşüt sürücüsü ve canlı anahtar Erdal'ın yerel işi (`docs/memory/plans/erp-edocument-leftovers.md`) |
| A3 e-İrsaliye | başlanmadı; Erdal'ın yerel işi |
| A5 kurumsal fatura alanları | kısmen: hızlı sipariş VKN ve vergi dairesini topluyor; "Kurumsal fatura istiyorum" anahtarı yok. Kural aşağıda |
| B2 açık bankacılık | başlanmadı |
| B3 sanal POS hakedişi | başlanmadı |
| B4 pazaryeri hakediş mutabakatı | başlanmadı |
| B5 faturaya ödeme linki | "Faturalarım" bitti, ödeme linki yok |
| C5 kısmi faturalama ve kısmi sevk | başlanmadı |
| C6 fatura e-postasında okundu bilgisi | e-posta bitti, okundu bilgisi yok |
| D4 depo yönetimi | başlanmadı; dört fazlı plan aşağıda "D4 genişletmesi" (`notes/erp-secenekler.md` #17 buraya bakar) |
| E5 grafikler ve kârlılık | kısmen: ürün ve cari kârlılığı kâr/zarar ekranında (`erp_profit.php`); kasa akışı grafiği doğrulanmadı |
| F1 SMS / WhatsApp hatırlatma | başlanmadı (sağlayıcı sözleşmesi) |
| G3 çok şirket | açılmaması önerildi |

---

## 1. Mevzuat zemini (2026) — neyi zorunlu kılıyor

Bu bölüm kamuya açık kaynaklardan derlendi; mali müşavir doğrulaması planda
hâlâ "bekliyor" ve burada da geçerli.

- **e-Fatura:** genel eşik yıllık brüt satış hasılatı **3 milyon TL**; eşiği
  aşan izleyen yılın **1 Temmuz**'unda geçer. **İnternet üzerinden satış**
  yapanlar için eşik çok daha düşük: 2022 ve sonrası dönemler için
  **500.000 TL** (VUK 509 Tebliği'nin e-ticaret hükmü). Pinegrap'ın hedef
  kitlesi tam bu grup — küçük bir mağaza bile ikinci yılında e-Fatura
  mükellefi olur.
- **e-Arşiv Fatura:** **1 Ocak 2026**'dan itibaren **bilanço esasına göre
  defter tutan** mükelleflerde kâğıt fatura kalmadı; her fatura e-Arşiv ya da
  e-Fatura. e-Fatura mükellefi olmayanlarda **fatura başına 6.900 TL** (2025
  tutarı) ve üzeri satışlar zaten e-Arşiv'di.
- **e-İrsaliye:** genel eşik **10 milyon TL** (önce e-Fatura mükellefi
  olmak şart); bazı sektörlerde ciroya bakılmaz.
- **e-Defter:** 1 Ocak 2026'dan itibaren bilanço esasına göre defter tutan
  tüm mükellefler kapsamda.
- **Ba/Bs formları** **25 Eylül 2024**'te (VUK 565) tamamen kaldırıldı —
  yerini e-belge verisi aldı. Planın §9'undaki "BA/BS" düşüncesi artık konu
  dışı; cari mutabakat mektubu (bizde var) ticari ihtiyaç olarak sürüyor.

**Sonuç:** ERP'nin kestiği fatura, çoğu Pinegrap mağazası için **kâğıt
olamaz**. Faz 3 (e-belge) bir "sonraki faz" değil, modülün gerçek kullanıma
girmesinin ön koşuludur. Aşağıdaki A grubu bu yüzden en başta.

---

## 2. Adaylar

### A. Zorunluluk — e-belge

| # | Aday | Değer | İş | Bağımlılık | Not |
|---|---|---|---|---|---|
| A1 | **Faz 3 — e-Fatura / e-Arşiv gönderimi**: Paraşüt sürücüsünü doldurmak (kuyruk, poll + reconcile, iptal, e-arşiv iptal süresi) **ve** Logo İşbaşı sürücüsünü doldurmak (REST API, anahtar Logo desteğinden) | Zorunluluk | B + O | Paraşüt API kimliği/sandbox; İşbaşı test hesabı + API anahtarı + belgeler (`developers.isbasi.com`, giriş ister) | Plan §7 (yön değişikliği notu); kod `includes/erp/edoc/`. Hangi kimlik önce gelirse o sürücü önce. |
| A3 | **e-İrsaliye** (Faz 4'te bilerek bırakıldı; §7.6 koşulu) | Orta (10M üstü mağaza) | O | A1, A2 | Dahili irsaliye altyapısı hazır; yalnız gönderim ve durum. |

| A5 | **Kurumsal fatura alanları (checkout)** — fatura formunda "Kurumsal fatura istiyorum" anahtarı; açılınca firma adı + VKN + vergi dairesi görünür, kapalıyken bireysel müşteriye VKN sorulmaz. Aynı alanlar **hızlı siparişe** de eklenir | Yüksek (e-Fatura'nın ön şartı) | K–O | — | Bugünkü durum ve iki kural aşağıda (A5 kuralı). |

#### A5 kuralı — zorunluluk layout'a bağlıdır (Erdal, 2026-09-21)

**Bugünkü durum (2026-09-21 ölçümü).** Standart fatura formunda VKN/TCKN ve
vergi dairesi zaten var ve `contacts`'e yazılıyor; **hızlı sipariş
(`express_order.php`) yalnız firma adı topluyor, VKN hiç toplamıyor** → o
yoldan gelen siparişin faturası e-Fatura olamaz (İşbaşı `customer.tcknVkn`
zorunlu tutuyor). Sipariş → cari → fatura eşlemesi sadık çalışıyor
(`erp_account_data_from_contact()`); eksik olan tek şey verinin toplanması.

**Kural 1 — yerleşim layout'un işi.** Alanlar görsel sayfa editöründen
eklenecek; `english_default` ve `turkish_default` düzenleri yazılımla birlikte
güncellenecek, **mevcut sitelerin layout'larını Erdal elle güncelleyecek.**

**Kural 2 — zorunluluk koda gömülmez.** Bir alanı koşulsuz "zorunlu" yapmak,
layout'u güncellenmemiş bir sitede **siparişi tamamen durdurur** (alan formda
yok → doğrulama hiç geçmez). Zorunluluk **alanın formda bulunmasına bağlı**
olacak: anahtar layout'ta yoksa hiçbir şey dayatılmaz, checkout bugünkü gibi
çalışır; anahtar varsa ve müşteri açtıysa firma adı + VKN o zaman zorunlu
olur. `billing_information.php` bu deseni zaten kullanıyor ("Only update tax
fields if they were actually present in the submitted form" —
`field_in_session()`); yeni alanlar da aynı yoldan geçecek. Eksik alanın
bedeli en fazla "o sipariş e-Fatura yerine e-Arşiv gider" olmalı, "sipariş
verilemez" değil.

### B. Para akışı otomasyonu — "tahsilat kendini eşlesin"

| # | Aday | Değer | İş | Bağımlılık | Not |
|---|---|---|---|---|---|
| B2 | **Açık bankacılık (API) ile hesap hareketleri** — TCMB/BKM HHS-YÖS standardı; pratikte bir toplayıcı (Kobaküs, Vomsis gibi) ya da banka API mağazası (Garanti BBVA API Store) | Yüksek | B | B1, dış hesap/sözleşme | Toplayıcı ücretli; mağaza sahibinin kendi sözleşmesi. `includes/erp/bank/` sürücü arayüzü (A2 gibi). Önce B1. |
| B3 | **Sanal POS hakedişi** — iyzico (mağazada var) ödemelerinin banka hesabına geçen net tutarı ve komisyonu: tahsilat + komisyon gideri otomatik | Yüksek (kartla satan her mağaza) | O | iyzico rapor API'si | Bugün kartlı sipariş faturalanıyor ama tahsilatı elle girilmiyor; "kart satışı = kasaya otomatik tahsilat, komisyon = gider" kapanışı. |
| B4 | **Pazaryeri hakediş mutabakatı** — Trendyol/N11/Hepsiburada ödeme raporları → pazaryeri carisine tahsilat, komisyon/kargo kesintileri → alış faturası/gider; eksik ödeme tespiti | Yüksek (pazaryeri satıcısı için en büyük dert) | B | mevcut pazaryeri bağlayıcıları (`includes/api/outbound/connectors/`) | Rakipler bunu ayrı modül olarak satıyor (Entegra 7.500 TL). Pinegrap'ta sipariş zaten pazaryerinden geliyor; eksik olan para tarafı. |
| B5 | **Faturaya ödeme linki + müşteri portalı** — cari kişisi mağaza hesabından "Faturalarım": PDF, açık tutar, iyzico ile öde → tahsilat otomatik | Yüksek | O | iyzico (var), kişi↔cari bağı (var) | Pinegrap'ın mağaza olması burada avantaj: müşteri hesabı sayfası zaten var, ayrı portal gerekmez. Paraşüt bunu "müşteri portalı" diye ayrıca sunuyor. |

### C. Satış belgeleri ve ticari akış

| # | Aday | Değer | İş | Bağımlılık | Not |
|---|---|---|---|---|---|
| C5 | **Sipariş → kısmi faturalama ve kısmi sevk** — bir siparişi iki faturaya bölmek, irsaliyeleri tek faturaya toplamak (irsaliye→fatura tek yönlü var) | Orta | O | Faz 2/4 | "Çok irsaliye → tek fatura" Paraşüt'te var; toptan sevkiyat senaryosu. |
| C6 | **Fatura e-posta gönderimi ve okundu bilgisi** (A4 ile aynı yol) + fatura üzerinde QR/link | Yüksek | K | A4 | Küçük iş, büyük his. |

### D. Stok ve tedarik

| # | Aday | Değer | İş | Bağımlılık | Not |
|---|---|---|---|---|---|
| D4 | **Depo yönetimi** — çoklu depo, depolar arası transfer, e-ticarette depo seçimi, siparişte çıkış deposu | Orta-yüksek | B | D1 | **Erdal 2026-09-21'de açtı** (aşağıda D4 genişletmesi). Önceki not "talep gelmeden açılmamalı" idi; talep geldi. |

#### D4 genişletmesi — depo yönetimi (Erdal'ın isteği, 2026-09-21)

İstenen dört parça: **(a)** birden çok depo, **(b)** depolar arası ürün
sevkiyatı, **(c)** e-ticarette ürün için depo seçimi, **(d)** siparişte çıkış
deposunun görünmesi.

**Neden "büyük" olduğu (dürüst maliyet).** Bugün stok tek sayı:
`products.inventory_quantity`. Çoklu depo, bu sayıyı **türetilmiş** bir
toplama çevirir ve ona dokunan her yer etkilenir: mağaza stok kontrolü ve
"tükendi" rozeti, sepet/ödeme anındaki düşüm, tezgâh satışı, sipariş
düzenleme, ürün içe/dışa aktarımı, API ürün nesnesi, pazaryeri
bağlayıcılarının stok gönderimi, alış faturasından giriş (D1). Tek bir
tabloyla bitmez; **tek doğruluk kaynağını değiştirmek** demektir.

**Şema taslağı** (yalnız sıra geldiğinde):

- `erp_warehouses` — id, code, title, is_default, is_active, address alanları
  (transfer irsaliyesinin çıkış/varış adresi bu karttan gelir), notes.
- `erp_stock` — product_id + warehouse_id (UNIQUE), quantity, reserved,
  updated_at. `products.inventory_quantity` **kaldırılmaz**: depoların
  toplamıyla senkron tutulan bir önbellek olarak kalır, böylece mağaza ve
  pazaryeri kodu değişmeden çalışmaya devam eder (kesme noktası azalır).
- `erp_stock_moves` — append-only hareket defteri: doc_type
  (order|invoice|transfer|count|adjustment), doc_id, product_id, from/to
  warehouse, quantity, unit_cost (D1 ile), created_at, created_by. Cari
  hareket defterinin (`erp_account_transactions`) aynı deseni: düzeltme ikinci
  bir hareket, satır silinmez.
- `erp_transfers` (+ `erp_transfer_items`) — depolar arası sevk; **transfer
  irsaliyesi** olarak basılır (`erp_waybills` deseni ve şablon motoru hazır;
  GİB tarafında sevk irsaliyesidir, alıcı da satıcı da mağazanın kendisi).
- `orders.warehouse_id` — siparişin çıkış deposu; `order_items.warehouse_id`
  parçalı sevkiyata izin verilirse (önce kalem değil sipariş düzeyinde).

**Kararlar (sıra gelince alınacak, şimdi değil).**

1. **Deponun sipariş için nasıl seçileceği:** sabit varsayılan mi, stok
   uygunluğuna göre mi, müşterinin iline göre mi (bölge → depo eşlemesi),
   yoksa operatörün eliyle mi? En basit ve açıklanabilir olan: varsayılan
   depo + stok yetmiyorsa operatöre uyarı. Otomatik bölge eşlemesi üçüncü faz.
2. **Mağaza ürün başına depo mu, depo başına fiyat/teslim süresi mi?**
   "Ürün için depo seçimi" iki farklı şey olabilir: (i) yönetici ürünün hangi
   depodan satılacağını sabitler, (ii) müşteri teslimat süresine göre depo
   görür. (i) ucuz, (ii) kargo entegrasyonu ister.
3. **Rezervasyon:** sipariş verildiği anda mı düşülüyor, sevk anında mı? Bugün
   Pinegrap sipariş anında düşüyor; çoklu depoda "rezerve" sütunu bunu
   bozmadan taşır.
4. e-Belge tarafı: e-İrsaliye İşbaşı API'sinde **yok** (2026-09-21); transfer
   irsaliyesi şimdilik PDF olarak basılır.

**Fazlama önerisi.** (1) Depolar + `erp_stock` + varsayılan depo, her yer
toplamı okumaya devam eder (görünür değişiklik yok, veri hazır). (2) Depo
bazlı stok ekranı, sayım ve düzeltme (D3 ile birlikte). (3) Transfer +
irsaliye. (4) Siparişte çıkış deposu ve mağaza tarafı. **D1 (alış
faturasından giriş/maliyet) bundan önce gelmeli**: depo başına stoğu olan ama
maliyeti olmayan bir sistem, yanlış soruya doğru cevap verir.

### E. Raporlar ve mali müşavir

| # | Aday | Değer | İş | Bağımlılık | Not |
|---|---|---|---|---|---|
| E5 | **Kasa akışı grafiği, cari kârlılığı, ürün kârlılığı** | Orta | K | E2, D1 | Kasa akışı raporuna tek grafik; kârlılık D1'e bağlı. |

### F. İletişim ve bildirim

| # | Aday | Değer | İş | Bağımlılık | Not |
|---|---|---|---|---|---|
| F1 | **SMS / WhatsApp hatırlatma** — vadesi gelen/geçen fatura, ödeme linkiyle (Netgsm/İleti Merkezi/WhatsApp Business API) | Orta-yüksek | O | sağlayıcı sözleşmesi, B5 | E-posta hatırlatması var (4.55/4.56); kanal eklemek. Türkiye'de tahsilatta SMS/WhatsApp e-postadan daha etkili. |

### G. Yönetim ve güvenlik

| # | Aday | Değer | İş | Bağımlılık | Not |
|---|---|---|---|---|---|
| G3 | **Çok şirket (tek panel, birden çok VKN)** | Düşük | B | — | Pinegrap tek site = tek şirket; talep yok, açılmamalı. |

---

## 3. Öneri sırası

2026-09-20'deki sıranın büyük kısmı uygulandı (yukarıda §0). Güncel seçim proje notu
`notes/erp-secenekler.md`'de. Açılmaması önerilen tek aday G3 (çok şirket).

---

## 4. Pinegrap'ın elindeki yapı taşları (adayların "ucuz" olmasının nedeni)

- Mağaza + müşteri hesabı sayfası → müşteri portalı (B5) ayrı ürün değil.
- iyzico ve PayPal ödeme kuruluşu → ödeme linki (B5), POS hakedişi (B3).
- Pazaryeri bağlayıcıları (`includes/api/outbound/connectors/`, N11 ve
  diğerleri) → hakediş (B4) için sipariş eşleşmesi hazır.
- `email()` ek dosya desteği (Faz 5) → belge e-postası (A4, C6).
- PWA push ve gecikme bildirimi işi (`erp_overdue_job.php`) → yeni bildirim
  kanalları (F1, F2, D5).
- Kur servisi (`includes/fn/currency_rates.php`) ve dövizli cari → ithalat/ihracat
  faturaları için zemin.
- Belge şablon motoru (`erp_template_render`) + ayarlardan düzenlenen üç
  şablon → teklif (C1) ve gider fişi çıktısı için dördüncü/beşinci şablon.
- Dış API dikişi (`includes/erp/api.php`) ve webhook olayları → her yeni
  kayıt türü (çek, gider, teklif) API'ye aynı desenle çıkar.
- Barkod okutma (tezgâh + editör) → stok sayım (D3).

---

## Kaynaklar

- [erpburada — e-Fatura, e-Arşiv ve e-İrsaliye 2026 zorunluluk limitleri](https://erpburada.com/blog/e-fatura-e-arsiv-e-irsaliye-2026-zorunluluk-limitleri)
- [ideasoft — E-Ticarette E-Fatura Zorunluluğu 2026](https://www.ideasoft.com.tr/e-ticaret-icin-e-fatura-zorunlulugu/)
- [Paraşüt — 2026'da e-Arşiv ve e-Fatura zorunluluğu](https://www.parasut.com/blog/e-fatura-ve-e-arsiv-zorunlulugu)
- [Faturaport — BA/BS Formu 2026 güncel durum](https://faturaport.com/blog/e-donusum/babs-formu-nedir-2026-guncel-durum-rehberi)
- [TÜRMOB — 2026 yılında tutulacak defterler sirküleri](https://www.turmob.org.tr/ekutuphane/Read/50a121a7-ad97-41ab-ac85-e59f58a250f9)
- [Paraşüt — Paraşüt nedir, özellikleri ve entegrasyonları](https://www.parasut.com/kullanim-kilavuzu/parasut-nedir-parasut-ne-ise-yarar)
- [Kobaküs — muhasebe ve ERP yazılımları için banka entegrasyonu](https://kobakus.com/sektorler/muhasebe-yazilimlari/)
- [TCMB — Ödeme Emri ve Hesap Bilgisi Hizmetleri API Standartları](https://www.tcmb.gov.tr/wps/wcm/connect/8f5e4483-89b0-4eec-acbf-97904cd73d14/%C3%96demeEmriveHesapBilgiHizmetleriAPIStandartlar%C4%B1v1.0.0-accepted.pdf?MOD=AJPERES&CACHEID=ROOTWORKSPACE-8f5e4483-89b0-4eec-acbf-97904cd73d14-nXUjQcG)
- [Garanti BBVA API Store](https://www.garantibbva.com.tr/isim-icin/dijital-bankacilik/garanti-bbva-api-store)
- [Entegra — pazaryerleri hakediş takibi ve mutabakat](https://www.entegrabilisim.com/pazaryerleri-hakedis-takibi-b-MTE1)
- [KolayBi — pazaryeri komisyon faturası muhasebe kaydı](https://www.kolaybi.com/blog/pazaryeri-komisyon-faturasi-muhasebe-kaydi-nasil-yapilir)
- [Tahsildar — e-Fatura sonrası tahsilat otomasyonu rehberi](https://tahsildar.com.tr/blog/e-fatura-sonrasi-tahsilat-otomasyonu)
- [PayTR — linkle ödeme entegrasyonu](https://dev.paytr.com/en/home/link-odeme-entegrasyon-sureci)
