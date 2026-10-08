# Logo İşbaşı API — okunan belgelerden çıkarılan notlar

Kaynak: `https://developers.isbasi.com/` (İşbaşı hesabıyla giriş ister) ve
sayfanın "İndir" bağlantısındaki `swagger.json?v=1.0.9` (Swagger 2.0,
`host: https://isbasimw.isbasi.com`). Okuma tarihi 2026-09-20. Belge elle
düzenlenmiş bir Swagger: yollar sorgu dizesi taşıyor, `security` bir path
gibi listelenmiş, JSON'da `: null,` artıkları var (Redoc yine basıyor);
bu yüzden **tek kaynak sayılmaz** — ilk gerçek çağrı yanıtları burada
"doğrulanmadı" denen her şeyi düzeltir. Sürücü: `includes/erp/edoc/isbasi.php`.

## Ortamlar

Logo 2026-09-21'de **test ortamı** verdi (Erdal'a e-postayla; anahtar ve
kullanıcı bilgileri **hiçbir dosyada tutulmaz**, ayar kartına Erdal girer):

| | Adres |
|---|---|
| Test API | `https://soho-isbasi-mwv2-test.logo-paas.com` (giriş ucu `/api/v1.0/user/integrationLogin`) |
| Test paneli | `https://soho-isbasi-uiv2-test.logo-paas.com/` |
| Canlı API (Swagger `host`) | `https://isbasimw.isbasi.com` |
| Kurye entegrasyonu (başka ürün) | canlı `https://integration.isbasi.com/`, test `https://soho-isbasi-mwv3-stg.logo-paas.com/` |

Sürücüde **Ortam** alanı (`environment`: `live` | `test`, varsayılan `live`)
adresi seçer; "Giriş adresi" kutusu yalnız Logo özel bir adres verirse
doldurulur. **Test ortamında `getplatform`'un döndürdüğü `baseUrl`
yok sayılır** — bir test kurulumu sessizce canlıya taşınamaz. Logo'nun
örneğinde giriş doğrudan test adresine yapılıyor (`ApiKey` başlığı; HTTP
başlıkları büyük/küçük harfe duyarsız, .NET tarafı `apiKey` ile de okur),
bu yüzden `getplatform` başarısız olursa akış durmaz, giriş denenir.

Sorular: `isbasientegrasyon@logo.com.tr`.

## Genel

- İki iş modeli: **Tek Hesaplı Giriş (SSO olmayan)** — bir İşbaşı hesabını
  bağlayan mağaza için; Pinegrap bunu kullanır. SSO modeli çok müşterili
  çözümler için (ssorequesturl / getuserappkey / Login) — yazılmadı.
- API için **ücretli İşbaşı aboneliği** ve Logo'dan alınan **APIKEY** şart
  (bilgi formuyla talep; Erdal başvurdu, 2026-09-20).
- Kota: ayda **3000 okuma / 7000 yazma**; okuma 6000'e çıkarılabilir (ek
  yıllık abonelik bedeli). → Sürücü her belge için en az istek atar; durum
  sorgusu düğmeyle/kuyrukla, döngüde değil. Token bir gün geçerli, önbelleğe
  alınır (`erp_edoc_providers.settings` içinde şifreli `session_enc`).
- İstek `Content-Type: application/json` (ya da `application/vnd.api+json`);
  yeni kayıtta `id = 0`. Her yanıt `{code, message, isError, data}`;
  `code` 0 ya da 200 = başarı, `isError=false`. Hata: `{code:400, message,
  isError:true, data:null}`; HTTP 400/401/403/404/409/429/500.
- Zorunlu başlıklar (uçtan uca değişiyor, sürücü hepsini gönderir):
  `apiKey`, `Authorization: Bearer {accessToken}`, `tenantId`, `Content-Type`,
  `Lang: tr-TR`; bazı uçlar `UserId`, `UserEmail`, `UserName`, `DeviceType:
  WEB` ister (`UserName`/`UserEmail` = giriş e-postası).
- e-Arşiv **internet** faturasında `sendingDate` (gönderim tarihi) ve
  `shipmentAgentItem` (sürücü/kargo: `name`, `surName`, `identifier`
  bireysel TCKN / kurumsal VKN, `firmType` 0 bireysel / 1 kurumsal)
  **zorunlu**. Pinegrap'ta `erp_invoices.carrier_title` / `carrier_vkn` /
  `shipment_date` (4.43) tam bunun için.

## Kimlik doğrulama (tek hesap)

1. **Platform / BASE_URL:** `POST {GİRİŞ}/api/v1.0/user/getplatform`,
   başlık `apiKey`, `Lang`, `Content-Type`; gövde
   `{"isonedayvalidtoken": false, "username", "password"}` →
   `data.baseUrl` (**asıl API adresi**), `data.Token`, `data.Platform`,
   `data.IsTestUser`, `data.isCanary`, `data.description`. Belge "BASE_URL'i
   Login ucundan alırsınız" der; alan burada. `{GİRİŞ}` = swagger `host`
   `https://isbasimw.isbasi.com` (ayarlar kartındaki "Giriş adresi" kutusu,
   varsayılan bu; kurye entegrasyonu bölümü canlı için
   `https://integration.isbasi.com/`, test için
   `https://soho-isbasi-mwv3-stg.logo-paas.com/` yazıyor — o başka bir
   ürün akışı, mağaza için değil).
2. **Giriş:** `POST {baseUrl}/api/v1.0/user/integrationLogin`, başlık
   `apiKey`, `Content-Type: application/json`; gövde
   `{"username": yönetici e-postası, "password"}`. Model
   `IntegrationLoginInfo` ayrıca `suplierTaxNumber`, `childUserName`,
   `appUserKey`, `tenantId` alanlarını tanımlar (tek hesapta boş).
   **Yanıt şeması belgede yok.** Bilinen: `accessToken` (Bearer), `tenantId`
   döner; giriş sonrası `ePortalArchiveResponsible=true` gelirse e-Arşiv
   portal faturası kesilebilir (yanıtta bayraklar var demek). Sürücü
   yanıtta `accessToken|access_token|token|Token` ve
   `tenantId|tenantID|TenantId` arar; `userId|UserId|id`, `email`
   bulursa `UserId`/`UserEmail` başlıklarına koyar; bulamazsa **alan
   adlarını** (değerleri değil) hata mesajında sayar ki ilk testte şema
   öğrenilsin.
3. Token **1 gün** geçerli (GetOutgoingInvoiceDataList notu). 401 → bir kez
   yeniden giriş + tekrar.

## Uçlar (mağazanın işine yarayanlar)

| İş | Uç | Not |
|---|---|---|
| Fatura kaydet | `POST /api/v1.0/invoices/integrationInvoices` | `SimpleInv` gövdesi; `invoiceId=0` yeni. Yanıt `data.invoiceId`. Cari `customer.code` varsa eşleşir, yoksa `name+tcknVkn` (kurumsal) / `firstName+lastName+tcknVkn` (bireysel) aranır, bulunmazsa **yeni cari açılır**. e-Fatura/e-Arşiv ayrımı carinin TCKN/VKN'sine göre **otomatik**. |
| **GİB'e gönder** | `POST /api/v1.0/einvoices/eInvoiceWithJson?invoiceId={id}` | **Gövdesiz.** Taslağı resmîleştiren adım (Logo, 2026-09-21). Başarıda bile `HTTP 400` + `isError: true` + tek kelime ("İşlendi", "İmzalandı") dönebiliyor → sürücü belgenin durumunu okuyup karar veriyor. |
| e-Arşiv portal PDF | `POST /api/v1.0/einvoices/print-eaportal-invoice-pdf?invoiceId={id}` | Gövde: harfi harfine `null`. ETTN'li adres PDF döndürmediğinde yedek. |
| Fatura sorgu | `GET /api/v1.0/invoices/{FaturaId}` | `invoiceNumber`, `totalwotax`, `vatAmount`, `withholdingAmount`, `total`. |
| Giden e-belgeler | `POST /api/v1.0/einvoices/GetOutgoingInvoiceDataList` | `DataListRequest` gövdesi; yanıt satırı: `invoiceId`, `uuId` (**ETTN**), `issueDate`, `amount`, `status`, `statusCode`, `rejectNot`, `salesInvoiceId`, `eGovermentType` ("SATIS"). Belge `Content-Type: application/json-patch+json`, `accept: text/plain` ister. |
| Satış faturaları listesi | `POST /api/v1.0/invoices/invoices` | Satır alanları arasında `eStatus`, `eStatusDescription`, `eInvoiceStatus`, `eArchiveStatus`, `gibCode`, `eReplayText`, `isCancelled`. Filtre `type` IN (1 e-Fatura, 2 e-Arşiv, 3 e-Arşiv internet); operatörler sayısal (2 `<=`, 5 `>=`, 17 IN). |
| Entegrasyon raporu | `/api/v1.0/einvoices/IntegrationInvoiceReport` | Belge hem GET hem POST der; `status` sorgusu: **1, 4, 8 devam eden; 10 GİB işlemi başarıyla tamamlanan**; alanlar `ettn`, `eStatus`, `confirmDate`, `denyDate`, `status10ChangeDate`. Çelişkili → sürücü kullanmıyor. |
| PDF | `GET /api/v1.0/einvoices/DocumentDatawithuuid?uuid={ETTN}&fileFormat=PDF` | `data.content` base64 PDF, `data.name`, `data.type`. Başlık `accept: text/plain`, `Content-Type: application/json-patch+json`. |
| UBL (XML) | `GET /api/v1.0/einvoices/DocumentUblData?invoiceId={id}&type=1` | Yalnız GİB'e gitmiş fatura; `data.content` base64 XML. |
| GİB mükellef | `GET /api/v1.0/user/gibUser?tcknVkn={vkn}` | `data.pkList` / `gbList` (e-Fatura), `dispatchPkList` / `dispatchGbList` (e-İrsaliye); öğe `alias`, `title`, `firstCreationTime`, `type`. 404 = mükellef değil. |
| Fatura sil | `DELETE /api/v1.0/invoices?Ids=1,2` | **Yalnız taslak**; GİB'e gitmiş fatura silinmez. Belgenin curl örneğindeki `/deleteInvoice` yolu **404** veriyor, doğru yol Swagger'daki path (2026-09-23, olmayan kimlikle denendi: `400 "Kayıt Numarası Bulunamadı!"`). e-Arşiv **iptali için API ucu yok** (panelde var) → sürücü `cancel_invoice` taslağı siler, e-Arşiv'de panelde iptal edildiğini doğrular. |
| Cari | `POST firms/firms` (liste), `GET firms/{id}`, `PUT firms` (yol Swagger'daki; curl örneğindeki `/firms/{id}` değil), `DELETE firms` | **Cari eşitleme (2026-09-23, 4.69)** kullanıyor: liste, detay, GET-birleştir-PUT; aşağıdaki bölüm. `DELETE` kullanılmıyor. |
| Ürün | `products/products`, `PUT products`, `GET products/{id}/{type}` | Kullanılmıyor; satırlar `productDetail` ile gider. |
| İrsaliye | `POST dispatch/dispatches?type=1` (liste), `mydispatchlist` | **İrsaliye kaydetme ucu yok** → `send_waybill` desteklenmiyor; yetenekler einvoice/earchive. |
| Kod listeleri | `/api/v1.0/master/vatexcepts`, `/master/withholdings`, `/master/stopajcodes` | Belgede yalnız adı geçiyor (path listesinde yok). |

## `SimpleInv` (fatura gövdesi) — belgeden

```
invoiceId*        int     0 = yeni
customer*         SimpleInvCustomer {code, name, email, tcknVkn, taxOffice,
                  country, city, district, address, isPerson, firstName, lastName}
invoiceDate*      "yyyy-MM-dd HH:mm:ss"
currency*         "TL" | "USD" ...   (belge örneği "TL"; TRY değil)
exchangeRate      number (varsayılan 1)
description, categoryName
deliveryAddressDifferent bool, shippingAddress {country,title,city,district,address,phone}
vatIncluded       bool — true perakende (KDV dahil), false toptan
sendingDate       string — internet e-Arşiv'de zorunlu
shipmentAgentItem {name, surName, identifier, firmType} — internet e-Arşiv'de zorunlu
eGovernmentInvoice {eGovernmentType int, invoiceTypeForEinvoice int (3 = internet
                  satış), eInvoiceProfile int, eArchivePaymentType int,
                  eArchivePaymentDate string, eArchivePaymentAgent string, website}
eArchivePortalInvoice {isEArchive, dispatchIncluded, eGovernmentType}
salesInvoiceDetails[] {quantity, taxRate, name, price, discountRate,
                  discountValue, stoppageRate, vatExemptionCode, description,
                  productDetail {itemCode, itemType 1 ürün / 2 hizmet, name,
                  vat, unit, withholding {code, rateText}}}
```

## Gerçek API ile doğrulananlar (2026-09-21, test ortamı)

Logo test anahtarını verdi; aşağıdakiler **canlı denendi**, tahmin değil.
İki fatura test sistemine geçti (PGF…015 → İşbaşı 1966, PGF…016 → 1967).

- **`getplatform` entegrasyon anahtarıyla 403 verir** (`{"code":403,"message":
  "API yetkilendirilmedi"}`), test ortamında da canlıda da. Base URL onunla
  öğrenilmiyor; Logo'nun verdiği adres **doğrudan base**. Sürücü bu çağrıyı
  yapmıyor.
- **`integrationLogin` yanıtı:** `data { moduleToken, accessToken, tokenType,
  tenantId, userId, licenses[ {code, name, period, expireDate, closureDate,
  packageMessage, isFree, isExpired} ], … }` — belirteç `accessToken`, kiracı
  `tenantId`, kullanıcı `userId`. Yanıtta `baseUrl` **yok**.
- **`user/gibUser?tcknVkn=`** çalışıyor: test ortamında her VKN mükellef
  görünüyor ve takma ad `urn:mail:defaultpk@sertifika1.com` (GİB simülasyonu).
  Dönüş `data.pkList[] {alias, title, …}`, ayrıca `gbList`, `dispatchPkList`,
  `dispatchGbList`.
- **`invoices/integrationInvoices` zorunlu alanları** — her biri ayrı bir
  400 ile öğrenildi: `customer.address`, `customer.tcknVkn` ve
  "Firma tanımında adres, **posta kodu**, şehir ve ilçe alanları dolu
  olmalıdır". Posta kodu `SimpleInvCustomer` modelinde yok ama **`postalCode`
  adıyla gönderilince kabul ediliyor**. Sürücü bunları göndermeden önce
  kendisi denetler (kota harcamamak için).
- **Başarılı yanıt:** `data { invoiceId: 1966, uuid:
  "A7E0F552-4867-4646-A836-601C7E8C466F", no: "REE2026000000172" }` — ETTN ve
  belge numarası **gönderim anında** geliyor, sorguya gerek yok.
- **`GET invoices/{id}`** 158 alanlı bir nesne döndürüyor; e-belge durumu
  `data.eGovernmentInvoice` altında: `{eGovernmentType, invoiceTypeForEinvoice
  (bizim SATIS faturamıza İşbaşı **1** dedi = e-Fatura, çünkü alıcı mükellef),
  eInvoiceProfile: 2, description: "GİB'E GÖNDERİLECEK", eStatus: 0, sendMode,
  isSended: false, gibCode, rejectNote, eArchivePaymentType,
  eArchivePaymentAgent, website, …}`. Ayrıca `invoiceNumber`, `isCancelled`,
  `cancelText`, `accountingStatus.statusText` ("Bekliyor"), `firm {id, code,
  name…}` (İşbaşı'nın açtığı cari), toplamlar (`totalwotax`, `total`,
  `totalAsText`).
- **`GetOutgoingInvoiceDataList`** gönderimden hemen sonra `count: 0` döner —
  belge GİB'e geçmeden listede görünmüyor. Bu yüzden durum sorgusu artık
  fatura detayından okunuyor, liste yalnız ETTN bilinmiyorsa yedek.
- **PDF adresi HTML döndürebiliyor:** `DocumentDatawithuuid?…&fileFormat=PDF`
  → `data.type: "HTML"`, içerik `<!DOCTYPE html …>` (GİB'e geçmemiş belgenin
  görüntüsü). **UBL zip'tir:** `DocumentUblData?invoiceId=…&type=1` →
  `data.type: "zip"`, `data.name: "20260921101032.zip"`. Sürücü türü baytlara
  bakarak belirliyor.
- **e-Fatura mı e-Arşiv mi: alıcının kimlik numarası belirliyor** ve
  numarası belge serisinden okunuyor. Mükellef VKN → `invoiceTypeForEinvoice:
  1`, numara **REE**2026000000172, durum "GİB'E GÖNDERİLECEK". Nihai tüketici
  `11111111111` → `invoiceTypeForEinvoice: 2` (**e-Arşiv**), numara
  **REC**2026000000218, durum "İMZAYA GÖNDERİLMEDİ"; İşbaşı cariyi
  `isPersonalCompany: true`, `taxOrPersonalId: 11111111111` olarak açıyor.
  Yani e-Arşiv yolu test ortamında **denenebiliyor** (sandbox her *gerçek*
  VKN'yi mükellef gösterse de).
- Kimlik doğrulama başlığı: `apiKey` küçük harfle çalışıyor (Logo örneğinde
  `ApiKey`; .NET tarafı büyük/küçük harfe duyarsız).

## GİB'e gönderme — Logo'nun yanıtı ve doğrulaması (2026-09-21)

Logo destek (isbasientegrasyon@logo.com.tr) yazdı: **"Fatura kaydedildiğinde
taslak olarak oluşur, resmileşmesi için gibe gönderilmesi gereklidir."** Yani
`integrationInvoices` bir taslak yaratır; belge ancak ikinci bir çağrıyla
resmîleşir.

| İş | Yöntem ve adres |
| --- | --- |
| **GİB'e gönder** | `POST /api/v1.0/einvoices/eInvoiceWithJson?invoiceId={id}` — **gövdesiz** |
| e-Arşiv portal PDF'i | `POST /api/v1.0/einvoices/print-eaportal-invoice-pdf?invoiceId={id}` — gövde: harfi harfine `null` |
| İade (alış) faturası | `PUT /api/v1.0/invoices/saveorupdatereturnpurchaseinvoice` — tam fatura nesnesi; **satış iadesi değil**, henüz kullanılmıyor |

Başlıklar: `Authorization`, `tenantId`, `ApiKey`, `UserId`, `UserEmail`,
`UserName`, `Lang`, `DeviceType`, `DeviceName` (sürücü hepsini gönderiyor).

**Zorunlu alanlar (Logo'nun kendi listesi):** TCKN/VKN, unvan veya ad-soyad,
adres, il, ilçe. Posta kodu listede yok ama İşbaşı'nın kendi 400'ü istiyor;
sürücü ikisini de gönderiyor ve **çağrıdan önce** denetliyor.

**Kota:** ekranı yok. Gerekirse Logo'dan e-postayla sorulacak.

### Gerçekte ne oluyor (test ortamı, üç belge)

- **Başarılı gönderim `HTTP 400` + `isError: true` ile dönüyor.** Gövde
  `{"code":400,"message":"İşlendi","isError":true,"data":null}` — bir başka
  belgede `"İmzalandı"`. Her iki durumda da belge o çağrıda ilerledi
  ("İMZAYA GÖNDERİLMEDİ" / "GİB'E GÖNDERİLECEK" → "HENÜZ İŞLENMEDİ" /
  "İşlem Bekliyor."). Sürücü bu yüzden **kelimeye bakmıyor**: 400 gelince
  belgenin kendi durumunu bir kez okuyup taslak olmaktan çıkmışsa başarı
  sayıyor. (Logo'ya "neden 400?" diye soruldu.)
- Kelime eşlemesi denenip **bırakıldı**: PCRE Türkçe noktalı büyük İ'yi
  (U+0130) i'ye katlamaz, `/işlendi/iu` "İşlendi" ile eşleşmez. Bir tuzak
  olarak not: bu kod tabanında Türkçe kelime aranacaksa ya ikinci harften
  başlanmalı ya da `mb_strtolower(..., 'UTF-8')` kullanılmalı.
- `eStatus: 0` = **kaydedildi, GİB'e gönderilmedi**. Sürücü artık bunu
  `created` (Sağlayıcıda taslak) olarak okuyor; eskiden "gönderildi"
  diyordu ve operatörün yapması gereken tek adımı gizliyordu.
- Gönderimden sonra akış: `HENÜZ İŞLENMEDİ` → `İşlem Bekliyor.` →
  **`BASARIYLA TAMAMLANDI`** (sürücü: `accepted`). Test ortamı zinciri
  sonuna kadar yürütüyor.
- `GetOutgoingInvoiceDataList` satırları belgeyi **`invoiceId` alanında GİB
  numarasıyla** anıyor (`"invoiceId":"REE2026000000172"`), sayısal kimlikle
  değil; `uuId` ETTN'yi taşıyor. Sürücü her iki adla eşliyor. Liste belge
  GİB'e geçene kadar **boş**.
- ETTN'li PDF adresi belge gönderilmeden önce **HTML**, gönderildikten sonra
  **gerçek PDF** döndürüyor (92 KB, GİB logolu, karekodlu, test ortamında
  "DEMO" filigranlı; ETTN, `Özelleştirme No: TR1.2`, `Senaryo:
  TICARIFATURA`, `Fatura Tipi: SATIS` yazıyor). Yine de e-Arşiv portal PDF'i
  yedek olarak deneniyor: ilk yanıtın baytları `%PDF` değilse.
- "Ödeme Notu:0" ve "İrsaliye yerine geçer" satırları **İşbaşı'nın kendi
  şablonundan** geliyor, bizim gönderdiğimiz alanlardan değil.

## Panelden ve belgeden doğrulananlar (2026-09-23)

Kaynak: `developers.isbasi.com` (Swagger `v=1.0.9`, 2026-09-20'dekiyle aynı
sürüm) ve test panelinin (`soho-isbasi-uiv2-test`) kendi form/lookup
yanıtları (yalnız okuma: sayfa HTML'i, JS dosyaları, `GET` lookup'ları,
listenin yüklenirken attığı `GetGovermentTypes`). Panel aynı `mwv2` ara
katmanını çağırıyor (hata mesajı yolu sızdırıyor), yani kodlar API ile aynı.

- **`eArchivePaymentType` kodları** (satış formu `#eArchivePaymentType`):
  **0** Kredi Kartı/Banka Kartı, **1** EFT/Havale, **2** Kapıda Ödeme,
  **3** Ödeme Aracısı, **4** Diğer — `erp_invoices.payment_method`'daki GİB
  kodlarıyla aynı sırada. Sürücü artık bunları varsayılan olarak gönderiyor
  (`erp_edoc_isbasi_payment_type()`); ayarlardaki `payment_type_codes` hâlâ
  üstün. **İnternet satışı yolu bu yüzden açıldı** (canlı denemesi yok).
- **`eGovernmentType` kodları** (Giden E-Faturalar tip süzgeci): **0** Satış,
  **1** Özel Matrah, **2** İstisna, **4** Tevkifat, **6** İade, **11**
  Konaklama Vergisi, **14** Teknoloji Destek, **15** YTBSatış, **16**
  YTBİstisna, **18** YTBTevkifat. İhracat listede yok. Sürücü İSTİSNA → 2,
  TEVKİFAT → 4 gönderiyor (satırlarda `vatExemptionCode` / `withholding`
  zaten gidiyordu); ÖZEL MATRAH satır başına matrah ister (`specialBaseAmount`,
  entegrasyon modelinde yok) → açık ret; İADE ayrı belge (aşağıda).
- **`eInvoiceProfile`**: **1** Temel, **2** Ticari, **5** Kamu, **8** İlaç ve
  Tıbbi Cihaz, **9** Yatırım Teşvik. İşbaşı cariden kendisi seçiyor
  (gönderdiğimiz faturalarda 2 geldi); sürücü göndermiyor.
- **`invoiceTypeForEinvoice`**: `/Common/GetInvoiceTypes` — e-Fatura
  mükellefine **1** E-Fatura; olmayana **2** E-Arşiv, **3** E-Arşiv İnternet.
- **İptal:** fatura nesnesinde `isCancellable` / `isUndoCancellable` /
  `cancelQuestion` / `cancelText` var. Test kiracısında **gönderilmiş e-Arşiv
  (REC…218, id 1970) `isCancellable: true`**, gönderilmiş **e-Fatura'lar
  (1966, 1980) `false`**. Panel iptali kendi `/Invoice/Sales/UpdateStatus`
  eylemiyle yapıyor (açıklama ister, geri alınabilir); **genel API'de karşılığı
  yok**. GİB e-Arşiv **portal** faturası (5.000/30.000 TL şeması) için panel
  "e-Arşiv İptal İşlemlerinizi GIB Portal sayfasından yapabilirsiniz" diyor.
- **Satış iadesi:** panelde **"Satış İade Faturası"** var (fatura ekranından
  `Edit?id=&type=3|4&createNewReturn=true`; nesne `type:
  RETURN_SALES_INVOICE`, satırlar asıl faturadan kopyalanır, ürün adı
  düzenlenemez; e-devlet alanı `eGovernmentBeforeReturnType`). Entegrasyon
  ucu (`SimpleInv`) iade edilen faturayı gösterecek alan taşımıyor; Logo'nun
  verdiği `saveorupdatereturnpurchaseinvoice` **alış** iadesi (panelde "Alış
  İade Faturası… e-fatura mükellefi olan müşterilere kesilebilir"). → İşbaşı
  sürücüsü `return` yeteneğini **bildirmiyor**; ekran iade faturasını ERP'de
  tutuyor ve "e-Belgesi sağlayıcı ekranında" diyor.
- **Uç varlığı GET ile sınanamıyor:** `/api/v1.0/invoices/<herhangi>` GET'i
  `invoices/{id}` sayılıp `599 "Kayıt Numarası Bulunamadı!"` dönüyor;
  `einvoices` altında yöntem uymayınca 405 değil 404. Yeni uç sormak için
  yazma denemesi gerekir — yapılmadı.

## Uçtan uca deneme (2026-09-23, test ortamı)

- **Tarih sırası reddi:** `400 "Girilen tarihten sonra aynı tipte fatura
  kesilmiş. En son fatura Tarihi :21-09-2026 10:00"` — tarih `dd-mm-yyyy
  HH:MM`; "aynı tip" e-Fatura / e-Arşiv ayrımı. Sürücü mesajı
  `erp_edoc_isbasi_date_refusal()` ile okuyor, kart o tarihi gösteriyor
  (2026-09-23, #27).
- **Test kiracısı paylaşımlı.** Satış listesinde 1353 kayıt var, başka
  entegratörlerin belgeleri (ör. `REB2027…` seri, 13.01.2027 tarihli). GİB'in
  "aynı türden belgeler tarih sırasıyla" kuralı test ortamında bu yüzden
  başkalarının ileri tarihli belgelerine takılabilir.
- **İnternet satışı e-Arşiv reddediliyor:** sipariş #1529 → PGF…023
  (`invoiceTypeForEinvoice 3`, `eArchivePaymentType 0`, taşıyıcı dolu) dört
  kez `599 "Ödeme tarihi e-Arşiv (Online Satış) seçili olan faturalar için
  zorunludur."` döndü. `eGovernmentInvoice.eArchivePaymentDate` şu
  biçimlerde denendi: `Y-m-d H:i:s`, ISO 8601 (`2026-03-20T12:00:00`),
  `d.m.Y` (panelin tarih seçicisinin biçimi); bir denemede tarih faturanın
  üst düzeyine de (`paymentDate`, `eArchivePaymentDate`) kondu. Hiçbiri
  okunmadı. Reddedilen istek İşbaşı'nda kayıt açmıyor (listede yok). Sürücü
  belgedeki ISO biçimine döndü. **Beşinci deneme (2026-09-23, #27, ürün
  düğmesiyle):** belgenin tüm tarih örneklerindeki UTC + milisaniye
  (`2026-09-23T09:00:00.000Z`) — aynı 599; sürücü yine ISO'ya döndü. Panelin
  satış formu (`invoice/sales/edit.min.js`) bu alanı
  `u.eGovernmentInvoice.eArchivePaymentDate = $("#eArchivePaymentDate").val()`
  ile, Kendo tarih seçicisinin `d` biçiminde (tr-TR: `dd.MM.yyyy`) gönderiyor;
  alan adı ve yeri bizimkiyle aynı. Entegrasyon ucu (`SimpleInvEgov`) alanı
  okumuyor gibi görünüyor. **Logo'ya sorulacak.** (Panelin kuralı:
  ödeme türü 2 kapıda / 4 diğer değilse tarih zorunlu; 3 ödeme aracısında
  aracı adı zorunlu.)
- **Gönderilmiş e-Arşiv henüz işlenmemişse iptal edilemiyor:** REC…218 (id
  1970, "HENÜZ İŞLENMEDİ") için panelde İptal Et → "Durumu e-Arşiv Faturası
  Oluşturulacak, e-Arşiv Faturası Oluşturuldu, Sunucuya iletildi - İşlenmeyi
  bekliyor ya da Sunucuda Hata Alındı olan e-Arşiv faturaları iptal durumuna
  alınamaz." (`isCancellable` yine `true` görünüyor). Test ortamı e-Arşiv'i
  işlemediği için panelden iptal yolu burada denenemiyor. Pinegrap'taki
  "Orada iptal edildi, burada da iptal et" düğmesi İşbaşı'ya sorup yerelde
  hiçbir şeyi değiştirmeden reddetti (beklenen).
- **Her entegrasyon faturası "İrsaliye yerine geçer":** 1966, 1970, 1980'de
  `eGovernmentInvoice.dispatchIncluded: true`, `dispatchNumber
  "0000000000000508"`. Pinegrap bu alanı göndermiyor; İşbaşı'nın varsayılanı.
  `SimpleInvEgov` modelinde `dispatchIncluded` yok, gönderilse okunup
  okunmadığı bilinmiyor.
- **GİB mükellef sorgusu** (`user/gibUser`) sürücüde belgeye uygun; cari
  ekranındaki düğme ise `form="form"` ile adı olmayan bir id'ye bağlı olduğu
  için hiç göndermiyordu (düzeltildi, `erp_account_form`). Dev'de 9876543210
  → "e-Fatura mükellefi değil" döndü. 21 Eylül'deki dört deneme İşbaşı'dan
  503 almıştı.

## Firma (tenant) seçimi (2026-09-23)

Test kullanıcısının panelde **altı firması** var ("Firma Değiştir",
`#switchTenantWindow` → `tenantId`): FSDFFFS, SEPET TAXİ (iki kayıt), **TEST
REAL WEBF / TEST REAL WEB** (`4719c95b-216c-49eb-bf62-ca5362dce27f`),
NAKULMA, SB TEST. Sürücünün oturumu da `4719c95b-…` — panelde seçili olanla
**aynı firma**; PGF…015–019 (İşbaşı 1966–1980) bu firmada görünüyor. Yani
#1529'un panelde olmaması firma yüzünden değil, İşbaşı'nın gönderimi
reddetmesinden. Test hesabını Logo birden çok entegratöre veriyor; listede
başkalarının belgeleri bu yüzden var.

Canlıda birden fazla firması olan mağaza için sürücüye isteğe bağlı **Firma
(tenant kimliği)** alanı eklendi: dolu ise `integrationLogin` gövdesine
`tenantId` gider (`IntegrationLoginInfo` modelinde tanımlı); İşbaşı başka bir
firmaya açarsa giriş reddedilir. Bağlantı testi kullanılan firmanın kimliğini
tam yazar. Başka bir firma kimliğiyle giriş **denenmedi** (paylaşımlı test
hesabında varsayılan firmayı değiştirip başkalarını etkileyebilir).

## Sıradaki uçlar (Erdal'ın kararı, 2026-09-23)

Sıra: **1. Gelen faturalar** (yapıldı, 2026-09-23), 2. cari eşitleme (**iki yönlü, onaylı**; yapıldı, 2026-09-23),
sonra gelen irsaliyeler ve serbest meslek makbuzu. Hepsi sürücüden bağımsız
kurulur (Paraşüt `inbox` yeteneğini zaten bildiriyor).

| Uç | Ne için | Not |
|---|---|---|
| `POST einvoices/myInvoicesList` | Gelen e-Faturalar | **Yapıldı (2026-09-23)**, aşağıdaki bölüm. Filtre `issueDate` ISO, `pageSize` 100. |
| `GET einvoices/DocumentUblDatawithuuid?uuid=&type=1` | Gelenin UBL'i | **Yapıldı.** Satışın `DocumentUblData?invoiceId=` ucu değil; ETTN ile. |
| `POST firms/firms`, `GET firms/{id}`, `PUT firms` | Cari eşitleme | **Yapıldı (2026-09-23)**, aşağıdaki bölüm. |
| `POST dispatch/mydispatchlist` | Gelen e-İrsaliyeler | Filtre `issueDate` ISO. |
| `POST Payments/smms`, `PUT Payments/smm` | Serbest meslek makbuzu | Oluşturmadan önce cari `PUT firms` ile açılıp `firm.id` yazılmalı; örnek gövde belgede (`type: vsmm`, brüt/net/KDV/stopaj). |

Gelen faturalar bu taslakla kuruldu (2026-09-23): sözleşmede
`erp_edoc_<kod>_inbox($from, $to, $page)` ve `_inbox_document($item,
$format)`, tablo `erp_edoc_inbox` (4.67), ekranlar `erp_inbox.php` /
`erp_inbox_document.php`. Ayrıntı `docs/degisiklikler.md`.

## Gelen e-faturalar — gerçek yanıtlar (2026-09-23, test ortamı)

Yalnız okuma (liste POST'u da okumadır). Test kiracısında (firma
`4719c95b…`) 2025-01-01'den beri **39 gelen fatura**; tedarikçiler LOGO
ELEKT., CERKE NİSPİ (TCKN'li), TEST E-FATURA FİRMASI, Logo A.Ş., 1903 ROLONG…
— çoğu aynı VKN'yi (4388571876) taşıyor, alıcı VKN'si de o.

- **Liste** `POST /api/v1.0/einvoices/myInvoicesList`, gövde
  `{filters:[{columnName:"issueDate",operator:5,value:"2026-06-25T00:00:00"},
  {columnName:"issueDate",operator:2,value:"2026-09-23T23:59:59.999"}],
  sorting:{issueDate:1}, paging:{currentPage,pageSize:100}, columnNames:null,
  count:true, excel:{export:false,allowedColumns:null,lucaExport:false}}` →
  `{code:200, isError:false, data:{count, data[], extraData:null}}`.
- Satır: `invoiceId` = **GİB numarası** ("TRR2026000000225"), `uuId` = ETTN
  (bazısı küçük harf), `type` 1 "Satınalma Faturası" / 3 "Satış İade
  Faturası", `issueDate` ISO, `amount` = **ödenecek** tutar (tevkifat
  düşülmüş), `totalVatBase`, `currency` **"TL"** (dövizde "USD"),
  `supplier`, `supplierTcknVkn`, `isPersonal`, `invoiceType` "Temel" /
  "Ticari" / "Temel (İade)", `status` "Kabul", `statusCode` 1,
  `purchaseInvoiceId` 0 (İşbaşı'da gider kaydı yok), `accountingStatus
  {statusText:"Bekliyor"}`, `vat<oran>VatTotal/Matrah` sütunları.
  **`eGovermentType` her satırda 0**; tür yalnız `EGovermentTypeDesc`'te
  yazıyla: "Satış", "Tevkifat", "İade", "İstisna", "Özel Matrah".
- **UBL** `GET /api/v1.0/einvoices/DocumentUblDatawithuuid?uuid={ETTN}&type=1`
  → `data {name:"…zip", type:"zip", content: base64}`; zip'te tek XML
  (~330–390 KB: imza + gömülü XSLT). **ETTN büyük/küçük harfe duyarlı** —
  büyük harfe çevrilince "UBL dosyası bulunamadı". Bir belgede (EFT…035)
  `400 "UBL bulunamadı! : UBL not found!"`.
- **PDF** `GET /api/v1.0/einvoices/DocumentDatawithuuid?uuid={ETTN}&fileFormat=PDF`
  gelen fatura için de gerçek PDF (IronPdf, ~88 KB).
- UBL'de görülenler: `ProfileID` TEMELFATURA/TICARIFATURA/KAMU,
  `InvoiceTypeCode` SATIS/TEVKIFAT/IADE/OZELMATRAH; tevkifatta satır
  `TaxTotal/TaxAmount` = KDV − tevkifat, `TaxSubtotal/TaxAmount` tam KDV,
  `WithholdingTaxTotal` ayrı (kod 601, %40); İADE'de `BillingReference`
  ("INR… NOLU FATURANIZ İÇİN"); dövizde `PricingExchangeRate` USD→TRY;
  irsaliyeli faturada `DespatchDocumentReference`; `Item/Name` çoğu zaman
  **ürün kodu**, ürün adı `Item/Description`'da; birim `NIU` (adet);
  birim fiyat 8 haneye kadar (47.85609195).
- Kabul/red (ticari fatura yanıtı) ucu Swagger'da yok.


## Cari kartları — gerçek yanıtlar (2026-09-23, test ortamı)

Cari eşitleme (`includes/erp/edoc/account_sync.php`, sürücüde
`erp_edoc_isbasi_accounts/_account/_account_save`). Yazma yalnız ürün
düğmeleriyle ve yalnız bizim kartlarımıza yapıldı (962: bizim faturalarımızın
açtığı kart; 1004: ürünün açtığı kart).

- **Liste** `POST /api/v1.0/firms/firms`, gövde `{filters:[], sorting:{id:"Asc"},
  paging:{currentPage, pageSize:100}, count:true, excel:{export:false}}` →
  `{code:200, data:{count, data[], extraData}}`. Kiracıda 912 kart. Belgedeki
  operatörler metin (`IsEqualTo`); süzgeç kullanılmadı. `sorting {name:"Asc"}`
  de çalışıyor ama aynı adlı çok kart var, sayfa sınırları kayar → kimlik.
- **Liste satırı:** `id` (sayı), `code` (serbest: e-posta, "0000000000000048",
  "PG-398"…), `isActive`, `isPersonalCompany`, `isForeign`, `name` (kişide "ad
  soyad"), `firstname`/`lastname` (**şirkette görünen adı tutuyor**; kişide ad
  ve soyad), `fullName` (listede boş), `displayName`, `type` (1 kişi, 2 şirket),
  `firmType` (**1 müşteri, 2 tedarikçi, 3 müşteri/tedarikçi** — panel süzgeci
  `#m_form_SellerBuyer`), `country` (ad: "Türkiye"), `address`, `city`, `town`
  (ilçe), `postalCode`, `phone`, `emailAddress`, `vknTckn`, `taxOffice`,
  `balance`, `firmBalance`, `firmCurrency`, `eInvoiceResponsible`,
  `eInvoicePostLabel`, `modificationDate`.
- **Detay** `GET /api/v1.0/firms/{id}`: `taxOrPersonalId`, `district`,
  `countryCode`, `cityCode`, `districtCode`, `firstName`/`lastName`/`fullName`/
  `displayName`, `phoneNumbers[]` (iki eleman), `shippingAddresses`, `banks`,
  `employees`, e-Fatura/e-İrsaliye etiketleri, `beginningBalance`, `balance`,
  `currencyBalance`, `currency: "TL"`. **`modificationDate` yok.**
- **Kaydet** `PUT /api/v1.0/firms`, tam `FirmItemDetails`; zorunlu listede
  bakiye alanları da var → sürücü **önce GET, yalnız kendi alanlarını değiştirip
  gerisini aynen** gönderiyor (962'de bakiye 1236 ve etiketler korundu). Yeni
  kart: **`id: 0`**, yanıt `data` = yeni kimlik (`1004`). Başarıda `code 200`.
- İl/ilçe adı değişince `cityCode`/`districtCode` boş gönderildi; İşbaşı adlardan
  kodu kendisi buldu (Adana/Aladağ → 01/03).
- **Telefon:** yalnız `phone` gönderilince PUT başarı dönüyor ama numara
  **kaydedilmiyor**; `phoneNumbers[0]` ile gönderilince kaydediliyor (ikisi
  birlikte dolu geri geliyor).
- Yeni kartta `eInvoiceResponsible` gönderdiğimizden bağımsız `true` geldi
  (test ortamında her VKN mükellef).
- Faturanın `GET invoices/{id}` yanıtındaki `firm {id, code, name}` faturanın
  konduğu kart; sürücü bunu `poll()`'da `party` olarak veriyor.
- Denenmedi: e-posta alanının yazılması, yabancı ülke, `customer.code` ile
  gerçek fatura gönderimi (İşbaşı kodla eşliyor mu).

## Tevkifatlı satış faturası (2026-09-23, test ortamı)

Ürün düğmesiyle gönderildi (PGF2026000000029 → İşbaşı 2075,
REE2026000000178, taslak): satırda `productDetail.withholding {code: "624",
rateText: "2/10"}`. İşbaşı'nın okuduğu: `total 1279.99`, `withholdingAmount
40`, `vatAmount 180` (**KDV − tevkifat**), `vatInfo[0].amount 220` (tam KDV),
`eGovernmentInvoice.eGovernmentType 4`. Pinegrap'in toplamlarıyla birebir.
`master/withholdings` ve `master/vatexcepts` GET → 404 (uç yok).

## Doğrulanmadı / Logo desteğine sorulacak

- **e-Arşiv (mükellef olmayan alıcı) hiç denenmedi:** test ortamındaki GİB
  simülasyonu her VKN'yi mükellef gösterdiği için her fatura e-Fatura
  (`invoiceTypeForEinvoice: 1`) oldu. e-Arşiv ve internet satışı yolu
  gerçek bir mükellef olmayan alıcı ya da canlı ortam ister.
- ~~VKN'siz nihai tüketici~~ **karara bağlandı (Erdal, 2026-09-21):**
  kimlik numarası olmayan alıcı için e-Arşiv'in `11111111111`'i gönderilir
  (`erp_edoc_final_consumer_tckn()`). Yalnız boşluğun anlamı buysa: **sıcak
  satış carisi** (`ERP_WALKIN_ACCOUNT_ID`) ya da **kişi** kartı; VKN'si
  girilmemiş **firma** kartı hâlâ durduruluyor (eksik veri, anonim alıcı
  değil). Numara cari kartına **yazılmaz**. Adres/posta kodu/şehir/ilçe
  İşbaşı için yine zorunlu — sıcak satış carisine bir kez mağazanın kendi
  adresi girilir.
- ~~İmzalama/gönderme adımı var mı?~~ **Yanıtlandı (Logo, 2026-09-21):**
  var — `einvoices/eInvoiceWithJson`. Bkz. yukarıdaki bölüm.
- ~~Belge GİB'e geçtikten sonra `eStatus`/PDF ne oluyor?~~ **Görüldü:**
  "BASARIYLA TAMAMLANDI" ve PDF adresi gerçek PDF döndürüyor.
- **Neden başarılı gönderim `HTTP 400` + `isError: true` dönüyor?** Sorulacak;
  sürücü şimdilik belgenin durumunu okuyarak karar veriyor.
- ~~`eArchivePaymentType` kodları~~ **panelden okundu (2026-09-23):** 0–4,
  yukarıda. İnternet satışı yolu açık; **gerçek gönderimle denenmedi**.
- ~~`eGovernmentType` / `eInvoiceProfile` sayıları~~ **panelden okundu:**
  yukarıda. İSTİSNA/TEVKİFAT gönderiliyor (**denenmedi**); ÖZEL MATRAH ve
  İHRACAT açık ret.
- **Logo'ya:** (0) internet satışı e-Arşiv'de **ödeme tarihi** hangi alanda,
  hangi biçimde gönderilmeli (beş biçim reddedildi; panel aynı alanı
  `dd.MM.yyyy` gönderiyor); `dispatchIncluded`
  entegrasyonla `false` gönderilebilir mi; (1) e-Arşiv faturasını **API ile
  iptal** eden uç (panelin `UpdateStatus`'u); (2) **satış iadesini API ile kaydetme/gönderme** —
  `RETURN_SALES_INVOICE` için hangi uç ve asıl fatura nasıl bağlanıyor;
  (3) ÖZEL MATRAH için satır matrahı entegrasyon modelinde nasıl gönderilir;
  (4) İhracat faturası entegrasyonla kesilebilir mi.
- ~~Faturayı kaydetmek GİB'e gönderir mi?~~ **Hayır** — taslak kalır
  (Logo, 2026-09-21).
- `price` KDV dahil mi hariç mi (`vatIncluded` ile birlikte) — Pinegrap
  `unit_price` KDV hariç birim fiyat gönderir, `vatIncluded=false`.
- `GetOutgoingInvoiceDataList` filtre sütun adları (`salesInvoiceId` ile
  filtrelenebilir mi) — sürücü tarih aralığıyla çekip **GİB numarası ya da
  `salesInvoiceId`** ile eşler.
- ~~Kota sayacı görünüyor mu?~~ **Hayır** — kontrol ekranı yok, gerekirse
  Logo'dan e-postayla sorulur (Logo, 2026-09-21).
