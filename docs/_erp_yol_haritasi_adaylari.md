# ERP — sonradan eklenebilecek özellikler (yol haritası adayları)

**Tarih:** 2026-09-20 · **Durum:** araştırma notu, karar değil · **Kapsam:** `docs/_plan_erp.md` Faz -1–5 bittikten sonrası

Plandaki bütün fazlar (Faz 3 hariç, Paraşüt anahtarı bekliyor) çalışma ağacında
bitti. Bu not, ERP'nin bundan sonra nereye büyüyebileceğini üç kaynaktan
derler: 2026 mevzuatı (neyin zorunlu olduğu), Türkiye'deki ön muhasebe
programlarının bugün ne verdiği (Paraşüt, Logo İşbaşı, Bizim Hesap, KolayBi,
Mikro) ve Pinegrap'ın elinde zaten olan yapı taşları (mağaza, pazaryeri
bağlayıcıları, ödeme kuruluşu, PWA bildirimi, kur servisi, dış API). Her aday
için değer, iş yükü (K/O/B — küçük / orta / büyük), bağımlılık ve bir karar
notu var. **Erdal seçer;** sıralama önerisi en sonda.

---

## 0. Durum — 2026-09-24

Çalışma ağacında yapılanlar (şema etiketi parantezde; ayrıntı `docs/degisiklikler.md`):

| Aday | Durum |
|---|---|
| A2 sürücü arayüzü | yapıldı (4.61) |
| A4 / C6 / F3 faturayı e-postala | yapıldı (4.97); okundu bilgisi yok |
| B1 banka ekstresi + eşleme | yapıldı (4.102); yalnız CSV, Excel'den CSV kaydedilir |
| B6 çek / senet portföyü | yapıldı (4.101); portföy ve verilen çek kasaları elle açılır |
| B7 tezgâhta karışık ödeme | yapıldı (şema yok) |
| C1 teklif / proforma | yapıldı (4.92); `erp_invoices`'a değil ayrı `erp_quotes` tablosuna, çünkü fatura sorgularının bir kısmı `doc_type`'a bakmıyor |
| C2 tekrarlayan fatura | yapıldı (4.93); yalnız ERP'de yazılmış satış faturası |
| C3 cari fiyat listesi / iskonto | yapıldı (4.94); fatura ve teklif editöründe |
| C4 kredi limiti | yapıldı (4.98); vade farkı hesabı yok |
| D1 alış stoğu ve maliyet | yapıldı (4.77) |
| D3 barkodla stok sayımı | yapıldı (4.100) |
| D5 minimum stok uyarısı | yapıldı (4.99, bildirim 4.91) |
| E1 KDV raporu | yapıldı (şema yok); muhasebeci paketinin KDV özetiyle aynı hesap (`includes/erp/vat_report.php`) |
| E2 kâr/zarar, E3 giderler | yapıldı (4.95, 4.96) |
| E4 muhasebeci paketi, G4 salt okuma rolü | yapıldı (4.78) |
| F2 panel içi bildirim + PWA push | yapıldı (4.91) |
| G1 dönem kilidi | yapıldı (4.79) |
| G2 denetim izi ekranı | yapıldı (4.90) |

Bilerek bekleyenler: A1 gönderim (entegratör kimlikleri), A3 e-İrsaliye, B2 açık
bankacılık, B3 iyzico hakedişi, B4 pazaryeri hakedişi, B5 ödeme linki ve
"Faturalarım" (iyzico + mağaza hesap sayfası), C5 siparişi birden çok faturaya
bölme (büyük iş), F1 SMS/WhatsApp (sağlayıcı sözleşmesi). ERP şema etiketinde sıradaki adım **4.104** (4.103: muhasebe kuralları; açık sorular `docs/erp_malimusavir_sorulacaklar.md`).

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
| A2 | **e-belge sürücü arayüzü** — **yapıldı (2026-09-20 gece, 4.61):** `includes/erp/edoc/registry.php` sözleşmesi, `parasut.php` + `isbasi.php` sürücüleri, ERP Ayarları'nda radyo ile seçim, şifreli kimlik saklama. Kalan: sürücülerin gönderim çağrıları (A1) | Kilitlenmemek | ~~O~~ tamam | — | Erdal'ın kararı: Paraşüt ve Logo İşbaşı birlikte; ileride başka entegratör bir dosya. |
| A3 | **e-İrsaliye** (Faz 4'te bilerek bırakıldı; §7.6 koşulu) | Orta (10M üstü mağaza) | O | A1, A2 | Dahili irsaliye altyapısı hazır; yalnız gönderim ve durum. |
| A4 | **e-Arşiv müşteri teslimi:** fatura PDF + e-Arşiv XML'in müşteriye e-posta ile gitmesi, sipariş ekranından "faturayı gönder" | Yüksek (her satışta) | K | A1 | Bugün PDF var, e-posta yolu `email()` `attachments` ile hazır (mutabakatta kullanıldı). e-belge yokken bile "faturayı e-postala" hemen eklenebilir. |

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
| B1 | **Banka hareketi içe aktarma (CSV/Excel) + eşleme önerisi** — bankadan indirilen ekstre → kasaya hareketler; açıklama/VKN/tutar ile cariye ve açık faturaya eşleme önerisi, operatör onaylar | Yüksek | O | yok | Açık bankacılığa giden yolun ilk, bağımsız adımı. `erp_settlement_suggest()` zaten var (fişe fatura önerisi); eksik olan içe aktarma ve cari eşlemesi. |
| B2 | **Açık bankacılık (API) ile hesap hareketleri** — TCMB/BKM HHS-YÖS standardı; pratikte bir toplayıcı (Kobaküs, Vomsis gibi) ya da banka API mağazası (Garanti BBVA API Store) | Yüksek | B | B1, dış hesap/sözleşme | Toplayıcı ücretli; mağaza sahibinin kendi sözleşmesi. `includes/erp/bank/` sürücü arayüzü (A2 gibi). Önce B1. |
| B3 | **Sanal POS hakedişi** — iyzico (mağazada var) ödemelerinin banka hesabına geçen net tutarı ve komisyonu: tahsilat + komisyon gideri otomatik | Yüksek (kartla satan her mağaza) | O | iyzico rapor API'si | Bugün kartlı sipariş faturalanıyor ama tahsilatı elle girilmiyor; "kart satışı = kasaya otomatik tahsilat, komisyon = gider" kapanışı. |
| B4 | **Pazaryeri hakediş mutabakatı** — Trendyol/N11/Hepsiburada ödeme raporları → pazaryeri carisine tahsilat, komisyon/kargo kesintileri → alış faturası/gider; eksik ödeme tespiti | Yüksek (pazaryeri satıcısı için en büyük dert) | B | mevcut pazaryeri bağlayıcıları (`includes/api/outbound/connectors/`) | Rakipler bunu ayrı modül olarak satıyor (Entegra 7.500 TL). Pinegrap'ta sipariş zaten pazaryerinden geliyor; eksik olan para tarafı. |
| B5 | **Faturaya ödeme linki + müşteri portalı** — cari kişisi mağaza hesabından "Faturalarım": PDF, açık tutar, iyzico ile öde → tahsilat otomatik | Yüksek | O | iyzico (var), kişi↔cari bağı (var) | Pinegrap'ın mağaza olması burada avantaj: müşteri hesabı sayfası zaten var, ayrı portal gerekmez. Paraşüt bunu "müşteri portalı" diye ayrıca sunuyor. |
| B6 | **Çek / senet portföyü** — tahsilat yöntemi "çek" var ama çekin kendisi yok: vade, banka, keşideci, durum (portföyde / bankaya verildi / tahsil / karşılıksız), vadesi gelen çek uyarısı | Orta (toptancı için yüksek) | O | yok | Paraşüt/Logo'da standart. `erp_cheques` tablosu + tahsilat fişine bağ. |
| B7 | **Kısmi ödeme ve karışık ödeme tezgâhta** (yarısı nakit yarısı kart; kalan cariye) | Orta | K | tezgâh tek adımı (var) | Bugün tutar = fatura toplamı. İki satırlı ödeme kutusu yeter. |

### C. Satış belgeleri ve ticari akış

| # | Aday | Değer | İş | Bağımlılık | Not |
|---|---|---|---|---|---|
| C1 | **Teklif / proforma** — `doc_type` ENUM'unda `proforma` zaten var; teklif belgesi (numara, geçerlilik, durum: taslak / gönderildi / kabul / red), tek tıkla faturaya dönüşüm, PDF şablonu | Yüksek (B2B) | O | belge şablonu altyapısı (var) | Paraşüt'ün en çok kullanılan modüllerinden. Satır editörü ve şablon motoru hazır; yeni tablo yerine `erp_invoices` `doc_type='proforma'` + `status` genişletmesi tartışılmalı. |
| C2 | **Tekrarlayan fatura / abonelik** — aylık kira, bakım, hizmet: şablon fatura + periyot, cron ile kesim, e-posta | Orta | O | cron (var), A4 | Hizmet satan mağazalar için; e-ticaret çekirdeğinde daha az. |
| C3 | **Cari bazlı fiyat listesi / B2B iskonto** — cariye özel fiyat/iskonto oranı; elle fatura ve (ileride) B2B sipariş bunu kullanır | Orta-yüksek (toptan) | O | ürün kataloğu (var) | Kampanya iskontosu zaten satırda; cari iskontosu üçüncü katman olur (kampanya → cari → yazılan). |
| C4 | **Cari risk / kredi limiti ve vade uyarısı** — limit aşıldığında fatura kesimi uyarısı/engeli; vade farkı hesaplama | Orta | K–O | yaşlandırma (var) | Kredi limiti tek sütun + kesimde kontrol. Vade farkı: aylık oran ayarı, ekstrede satır. |
| C5 | **Sipariş → kısmi faturalama ve kısmi sevk** — bir siparişi iki faturaya bölmek, irsaliyeleri tek faturaya toplamak (irsaliye→fatura tek yönlü var) | Orta | O | Faz 2/4 | "Çok irsaliye → tek fatura" Paraşüt'te var; toptan sevkiyat senaryosu. |
| C6 | **Fatura e-posta gönderimi ve okundu bilgisi** (A4 ile aynı yol) + fatura üzerinde QR/link | Yüksek | K | A4 | Küçük iş, büyük his. |

### D. Stok ve tedarik

| # | Aday | Değer | İş | Bağımlılık | Not |
|---|---|---|---|---|---|
| D1 | **Alış faturasından stok girişi ve maliyet** — alış faturası kalemi ürüne bağlıysa `inventory_quantity` artsın, **birim maliyet** (son alış / ağırlıklı ortalama) tutulsun | Yüksek (kârlılık için şart) | O | products (var), alış faturası (var) | Bugün satış stoğu düşürüyor (mağaza), alış stoğu yükseltmiyor; maliyet hiç yok → kâr raporu yapılamıyor. |
| D2 | **Satın alma siparişi (tedarikçi) → mal kabul → alış faturası** | Orta | B | D1 | Toptancı/üretici için; e-ticaret mağazası için ikinci sırada. |
| D3 | **Stok sayım** — sayım listesi (barkodla), fark fişi, düzeltme hareketi | Orta | O | barkod (var) | Tezgâh ekranındaki barkod okutma deseni doğrudan kullanılır. |
| D4 | **Depo yönetimi** — çoklu depo, depolar arası transfer, e-ticarette depo seçimi, siparişte çıkış deposu | Orta-yüksek | B | D1 | **Erdal 2026-09-21'de açtı** (aşağıda D4 genişletmesi). Önceki not "talep gelmeden açılmamalı" idi; talep geldi. |
| D5 | **Minimum stok uyarısı** — ERP menü rozeti / e-posta (hatırlatma altyapısı var) | Orta | K | notify (var) | `products.inventory_quantity` + eşik sütunu; `erp_overdue_job` desenine bir iş daha. |

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
| E1 | **KDV raporu** — dönem bazında hesaplanan (satış) / indirilecek (alış) KDV, oran kırılımı, tevkifat | Yüksek (her ay) | K–O | Faz 2 | Veri hazır (`erp_invoice_items.tax_rate/tax_total`); yalnız rapor ekranı + CSV. |
| E2 | **Gelir–gider ve kâr/zarar** — satış faturaları − alış faturaları − giderler, aylık; D1 ile brüt kâr | Yüksek | O | E3, D1 (brüt kâr için) | Paraşüt'ün ana panosu bu. Bizde pano yaşlandırma odaklı; ikinci sekme. |
| E3 | **Gider fişleri** — fatura olmayan masraflar (kira, fatura, yakıt, SGK), kategori, tekrarlayan gider; kasadan ödeme | Yüksek | O | kasa (var) | Alış faturası "tedarikçi + kalem" istiyor; gider fişi tek satır ve kategori. Kâr/zarar için şart. |
| E4 | **Mali müşavir paketi** — ay sonu: satış/alış fatura CSV + KDV raporu + kasa hareketleri tek zip, e-posta ile; "muhasebeci kullanıcı" salt-okuma rolü | Yüksek (her mağazanın müşaviri var) | K–O | dışa aktarım profilleri (var) | Logo/Luca XML formatı ikinci adım; önce CSV paketi. |
| E5 | **Kasa akışı grafiği, cari kârlılığı, ürün kârlılığı** | Orta | K | E2, D1 | Kasa akışı raporuna tek grafik; kârlılık D1'e bağlı. |

### F. İletişim ve bildirim

| # | Aday | Değer | İş | Bağımlılık | Not |
|---|---|---|---|---|---|
| F1 | **SMS / WhatsApp hatırlatma** — vadesi gelen/geçen fatura, ödeme linkiyle (Netgsm/İleti Merkezi/WhatsApp Business API) | Orta-yüksek | O | sağlayıcı sözleşmesi, B5 | E-posta hatırlatması var (4.55/4.56); kanal eklemek. Türkiye'de tahsilatta SMS/WhatsApp e-postadan daha etkili. |
| F2 | **Panel içi ERP bildirimleri PWA push** — tahsilat girildi, fatura ödendi, çek vadesi | Orta | K | PWA push (var) | `erp_event()` zaten olayı üretiyor; push'a bağlamak küçük. |
| F3 | **Müşteriye belge bildirimi** — fatura/irsaliye kesildiğinde e-posta (A4/C6) | Yüksek | K | — | — |

### G. Yönetim ve güvenlik

| # | Aday | Değer | İş | Bağımlılık | Not |
|---|---|---|---|---|---|
| G1 | **Dönem kapanışı / kilit** — belirli tarihe kadar belgelerin değiştirilemez olması (mali müşavir beyanname verdikten sonra) | Yüksek (mevzuat pratiği) | K | — | Tek tarih ayarı + yazma fonksiyonlarında kontrol. Paraşüt'te "kasa kilidi" var. |
| G2 | **Denetim izi ekranı** — kim, ne zaman, hangi belgede ne değiştirdi (log_activity zaten yazıyor; ERP filtresiyle ekran) | Orta | K | — | — |
| G3 | **Çok şirket (tek panel, birden çok VKN)** | Düşük | B | — | Pinegrap tek site = tek şirket; talep yok, açılmamalı. |
| G4 | **Kullanıcı rolü: "muhasebeci" (salt okuma + dışa aktarım)** | Orta | K | E4 | Mevcut üç yetkiye bir "salt okuma" bayrağı. |

---

## 3. Öneri sırası (gerekçesiyle)

1. **A1 + A2 — e-belge (Paraşüt sürücüsü ile)**: mevzuat zorunluluğu; anahtar
   gelir gelmez. Sürücü arayüzünü aynı anda kurmak tek entegratöre kilitlenmeyi
   önler. Yanında **A4/C6** (faturayı e-postala) küçük ve hemen.
2. **B1 — banka ekstresi içe aktarma + eşleme**: dış bağımlılık yok, tahsilat
   girişinin çoğunu ortadan kaldırır; B2'nin (açık bankacılık) yolunu açar.
3. **B4 — pazaryeri hakediş**: Pinegrap'ın pazaryeri satıcısı müşterisi için
   en büyük fark; bağlayıcılar zaten var, yalnız para tarafı eksik.
4. **D1 + E3 + E2 — alış stoğu/maliyet, gider fişi, kâr/zarar**: "ERP" adını
   hak etmenin şartı; üçü bir paket.
5. **C1 — teklif/proforma**: B2B satan mağazaların ilk sorusu; altyapı hazır.
6. **B5 + F1 — ödeme linki, müşteri portalı, SMS/WhatsApp**: tahsilat hızını
   asıl artıranlar; B5 mağaza hesabı sayesinde ucuz.
7. **B6 çek/senet, C3 cari fiyat, C4 risk limiti**: toptancı paketi; talebe göre.
8. **E1, E4, G1, G4**: küçük işler; boş bir günde toplu.

**Açılmaması önerilenler (şimdilik):** G3 çok şirket, D2 satın alma siparişi —
Pinegrap'ın hedef kitlesi (e-ticaret KOBİ) istemeden karmaşıklık getirir.
**D4 (depo yönetimi) bu listeden çıktı:** Erdal 2026-09-21'de istedi; yeri
D1'den sonra, yukarıdaki genişletmedeki fazlamayla.

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
