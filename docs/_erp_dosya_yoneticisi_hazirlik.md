# ERP belgeleri → Dosya Yöneticisi: ön hazırlık (2026-09-23, 4.65 ile güncellendi)

Bu not, Dosya Yöneticisi'nde (`view_folders.php`) "ERP" altında belgeleri
birleştirecek ajan içindir. ERP tarafı incelendi ve okuma için tek bir dikiş
hazırlandı: `pinegrap/includes/erp/files.php`. Dosya Yöneticisi ERP'nin iç
tablolarını bilmek zorunda değil; bu dosyanın fonksiyonlarını çağırması yeter.

## 1. Belgeler nerede duruyor (güncel: 2026-09-23, migration 4.65)

Erdal'ın kararıyla ERP belgeleri artık **Dosya Yöneticisi'nin kendi dosya
klasöründe** (`FILE_DIRECTORY_PATH`, varsayılan `pinegrap/data/files`) ve
**`files` tablosunda** saklanıyor. Yeni klasör açılmadı:

- Satırlar **`folder = 0`** — Dosya Yöneticisi ve dosyalar ekranı klasör
  klasör listelediği için bu satırlar bugünkü hiçbir listede görünmez.
- **`files.erp_doc_type`** (`invoice` | `waybill` | `edoc` | `reconciliation`)
  ve **`files.erp_doc_id`** (fatura id, irsaliye id, fatura id, mutabakat log
  id) satırın hangi belge olduğunu söyler; `idx_erp_doc (erp_doc_type,
  erp_doc_id)`. **Dosya Yöneticisi'nin kendi sorguları ERP satırlarını
  `erp_doc_type = ''` ile dışarıda bırakmalı**; ERP alanı bunları
  `includes/erp/files.php` üzerinden listeler.
- Ad: `erp-<tür>-<belge no>-<12 hane rastgele>.pdf`.
- **`get_file.php`** `erp-` ile başlayan ve `erp_doc_type`'ı dolu dosyayı yalnız
  oturumlu ve ERP hakkı olan kullanıcıya verir (`Cache-Control: private,
  no-store`); başkasına 404. Yani `PATH . name` adresi güvenle kullanılabilir.
- **Ne saklanır, ne zaman:** fatura ve irsaliye PDF'i kesim anında (istek
  sonunda); bu özellikten önce kesilmiş belge ilk açılışında. e-Belgenin
  sağlayıcı kopyası GİB kabul ettikten sonra ilk açılışta (yalnız gerçek PDF
  ise). E-postayla gönderilen mutabakat mektubu gönderildiği anda
  (`erp_reconciliation_log` satırı + PDF). Taslak ve iptal edilmiş irsaliye
  saklanmaz (canlı üretilir).
- Saklı kopyası olmayan belge (henüz açılmamış eski belge, taslak) hâlâ uçtan
  canlı üretilir; `files.php` öğesinde `kept: false`, `size: null`.

Diskte başka ERP dosyası: `data/temp` altındaki geçici dışa/içe aktarım
dosyaları (oturuma bağlı, 24 saatte süpürülür) — Dosya Yöneticisi'ne konmaz.

## 2. Envanter

| Belge | Nerede | Açma adresi (panel, oturum ister) | Dosya adı | Üretim | Not |
|---|---|---|---|---|---|
| Satış faturası | `erp_invoices` (`direction='sales'`, `doc_type='invoice'`) + `erp_invoice_items` | `get_erp_invoice_pdf.php?id=N` (`&download=1` indirir) | `{full_number}.pdf` | saklı kopya (yoksa dompdf + saklar) | Şablon `config.erp_invoice_template` (NULL → `includes/erp/templates/invoice_default.html`) |
| İade faturası | `erp_invoices` (`doc_type='return'`, kendi serisi) | aynı | aynı | aynı | `parent_invoice_id` ana faturayı gösterir |
| Alış faturası | `erp_invoices` (`direction='purchase'`) | aynı | aynı | aynı | Tedarikçinin numarası `supplier_invoice_no` |
| Taslak fatura | `erp_invoices.status='draft'`, `full_number=''`, seri `_D` | PDF yok → `edit_erp_invoice_draft.php?id=N` | — | — | Numarası yok; silinir, iptal edilmez |
| e-Fatura / e-Arşiv (resmî kopya) | `erp_invoices.edoc_provider`, `edoc_external_id`, `gib_number`, `gib_uuid` (ETTN), `edoc_status`, `edoc_kind`, `edoc_sent_at` | `get_erp_edoc_document.php?id=N&format=pdf` (`format=xml` = UBL) | sağlayıcının verdiği ad, yoksa `{full_number}.{uzantı}` | saklı kopya (GİB kabulünden sonra), yoksa **sağlayıcıdan canlı** | İşbaşı: GİB'e geçmemiş belgede "PDF" adresi **HTML**, UBL adresi **zip** döndürür (uç baytlara bakıp MIME'ı kendisi seçer). Ağ ve sağlayıcı erişimi gerekir. |
| İrsaliye | `erp_waybills` + `erp_waybill_items` | `get_erp_waybill_pdf.php?id=N` | `{full_number}.pdf` | dompdf | Şablon `config.erp_waybill_template` |
| e-İrsaliye | `erp_waybills.edoc_provider`, `edoc_external_id` (sütunlar var) | — | — | — | **Hiçbir sürücü göndermiyor** (İşbaşı API'sinde yok, Paraşüt sürücüsü boş). Klasör boş kalır. |
| Mutabakat mektubu | E-postayla gidenler: `erp_reconciliation_log` + saklı PDF (`files.erp_doc_type='reconciliation'`). Önizleme istekte üretilir | gönderilen: `PATH . files.name` (get_file.php, ERP hakkı); önizleme: `get_erp_reconciliation_pdf.php?id={cari}&as_of=…` | `{reference}.pdf` | saklı / dompdf | Klasör `reconciliations` |
| Tahsilat / ödeme | `erp_cash_transactions` | yalnız ekran `erp_receipt.php?id=N` (**kasa hakkı**) | — | — | Belgesi (makbuz PDF'i) yok. |
| Cari ekstresi | defterden | yalnız ekran | — | — | PDF'i yok. |
| Dışa aktarım (CSV/XLSX) | **Gerçek dosya:** `data/temp/erp_export_{token}.csv|xlsx` | `erp_export.php?download={token}` | profil adı | — | **Konmamalı:** oturuma bağlı, indirilince silinir, 24 saatte süpürülür (`erp_export_sweep`). `erp_export_log` yalnız hangi belgenin aktarıldığını tutar. |
| İçe aktarım (cari CSV) | `data/temp` altında geçici | — | — | — | **Konmamalı:** geçici. |
| Belge şablonları | `config.erp_invoice_template`, `erp_waybill_template`, `erp_reconciliation_template` (HTML metni) | `erp_settings.php?doc=invoice|waybill|reconciliation` (**ayar hakkı**) | — | — | Belge değil; düzenlemesi ERP Ayarları'nda. |

Paraşüt'ün eski gelen/giden kutusu (`view_parasut_inbox.php`) Paraşüt'ten
canlı liste okur; ERP belgesi değildir, kapsam dışı (ayrıntısı incelenmedi).

Dev'deki sayılar (2026-09-23): 21 satış faturası, 2 iade, 0 alış, 0 taslak,
5 e-Belge (GİB'e geçmiş), 7 irsaliye, 0 e-İrsaliye.

## 3. Hazır dikiş: `includes/erp/files.php`

Salt okur, bütün sorgular burada. Yüklemek için:

```php
require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/files.php');
```

| Fonksiyon | Ne döner |
|---|---|
| `erp_files_allowed($user)` | ERP açık **ve** (`role < 3` ya da `manage_erp`). Yanlışsa ERP klasörünü hiç göstermeyin (hata sayfası basmaz). |
| `erp_files_folders()` | Sanal klasörler, kök `erp` önce: `sales_invoices`, `sales_returns`, `purchase_invoices`, `edocs`, `waybills`, `ewaybills`, `reconciliations`, `drafts`. Her birinde `key, label, parent, icon, count, note`. Sayılar canlı. |
| `erp_files_list($folder, $options)` | `['items' => [...], 'total' => int]`, en yeni önce. `$options`: `search`, `year`, `month` (yıl/ay alt klasörü isterseniz), `limit` (varsayılan 100, en çok 500), `offset`. |
| `erp_files_search($query, $limit = 50)` | Bütün klasörlerde arama; her öğe kendi `kind`'ını taşır. |

**Aranan alanlar.** Fatura / e-Belge: belge no, GİB no, ETTN, alıcı unvanı (kesim anındaki kopya ve canlı kart), alıcı VKN/TCKN, tedarikçi fatura no, sipariş no. İrsaliye: belge no, alıcı, şehir, taşıyıcı, plaka, cari, sipariş no. `LIKE %…%`, `%` ve `_` kaçışlı.

**Öğe biçimi.** Dosya Yöneticisi'nin kendi `pg_explorer_file_payload()` satırına benzetildi (`kind, id, name, type, size, timestamp, modified, username, url, edit_url`), üstüne ERP alanları:

- `kind`: `erp_invoice` · `erp_edoc` · `erp_waybill` · `erp_ewaybill` · `erp_reconciliation`
- `name`: indirilecek dosya adı (`PGF2026000000019.pdf`; e-Belgede `REE2026000000177.pdf` — GİB numarası)
- `title`: "belge no — alıcı"
- `type`: `pdf` (taslakta boş) · `size`: saklı dosyanın boyutu, yoksa **null** · `kept` / `kept_at` · `source`: `kept` | `generated` | `provider` | `draft` (mutabakatta `missing` olabilir)
- `number, doc_class (sales|return|purchase|waybill), date, account_id, account_title, tax_number, order_id, order_number, total, total_label, currency, status, status_label, cancelled`
- faturada: `edoc_status, edoc_status_label, edoc_kind (none|einvoice|earchive), edoc_provider, gib_number, ettn`
- irsaliyede: `ship_date, recipient, carrier, plate, invoice_id, invoice_number, edoc_external_id`
- `url` (satır içi aç), `download_url`, `edit_url` (belgenin ERP ekranı) — hepsi panel adresi (`OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY`)
- `modified`: `get_relative_time()` çıktısı (HTML `<time>`), Dosya Yöneticisi'ndeki gibi

Dev'de denendi: klasörler ve sayılar, `edocs` listesi, "REE2026" / "Kaan" / "Aras" aramaları, Eylül 2026 süzgeci — hepsi birlikte 28 ms.

## 4. Dosya Yöneticisi tarafı için kurallar

1. **Salt okunur.** ERP öğelerinde yeniden adlandırma, taşıma, kopyalama, çoğaltma, kesme, silme, çöp kutusu, zip, izin değiştirme **kapalı**. Kesilmiş belge numarasını ve kaydını korur; iptal edilen de listede kalır (`cancelled: true` → rozet). Açık kalabilecekler: aç, indir, adı kopyala, `edit_url`'ye git. Dosya Yöneticisi'nin id ile çalışan işlemleri (sil, taşı, yeniden adlandır, çöp kutusu, zip, optimize) `files.erp_doc_type <> ''` satırını **reddetmeli** — bugün bu satırlar hiçbir listede görünmediği için bu işlemlere ulaşmıyorlar, ERP alanı eklenince ulaşabilirler.
2. **Herkese açık adres yok.** `url`'ler oturum ve ERP hakkı isteyen panel uçlarıdır; "tam adresi kopyala" / kısa bağlantı / sayfaya gömme uygulanmaz.
3. **Önizlemeyi kendiliğinden yapmayın.** Her fatura/irsaliye açılışı bir dompdf işi, her e-Belge açılışı sağlayıcıya bir çağrıdır. Küçük resim, "quick look" ön yüklemesi ya da toplu indirme ancak kullanıcı tek bir öğeyi açınca olsun.
4. **Boyut.** Saklı kopyada gerçek boyut; `size: null` (henüz saklanmamış) → "—" ya da "açılınca oluşturulur".
5. **Bir fatura iki öğe olabilir.** GİB'e geçmiş satış faturası hem `sales_invoices`'ta (mağazanın PDF'i) hem `edocs`'ta (resmî kopya) görünür. Bilerek böyle: resmî belge sağlayıcıdaki kopyadır, mağazanın PDF'i onun bir görüntüsüdür. Tek öğede birleştirmek isterseniz karar Erdal'ın.
6. **Hak.** Yalnız `erp_files_allowed()`. Listede kasa hakkı gerektiren belge yok (tahsilatın belgesi yok); ayar hakkı gerektiren şablonlar klasöre konmadı.
7. **Arama.** Dosya Yöneticisi'nin kendi aramasına `erp_files_search()` sonuçlarını ekleyin; ERP tablolarına doğrudan sorgu yazmayın (kurallar — taslak, iptal, e-Belge durumu — bu dosyada).
8. Yeni metin gerekirse `lang()` + `tr.json` (yalnız sona ekleme).

## 5. Verilen kararlar (Erdal, 2026-09-23)

- Mağaza PDF'i kesim anında saklanır — **yapıldı** (4.65).
- Sağlayıcı kopyası saklanır — **yapıldı** (kabul edilmiş belgede, ilk açılışta).
- Mutabakat mektupları kayda geçer ve listelenir — **yapıldı**
  (`erp_reconciliation_log`, klasör `reconciliations`).
- Disk yolu Dosya Yöneticisi'nin dosya klasörü; ERP satırları `files`
  tablosunda ERP sütunuyla ayrılır; yeni klasör yok.

## 6. Bu turda ERP tarafında yapılanlar (bu notla ilgili)

- `includes/erp/files.php` (yeni, salt okur).
- `erp_invoices.edoc_kind` artık dolduruluyor: durum sorgusu İşbaşı'nın `invoiceTypeForEinvoice` alanından e-Fatura / e-Arşiv'i yazıyor (sütun ERP'nin ilk şemasından beri vardı, hiç yazılmıyordu). Eski belgelerde bir sonraki "Durumu sor"da dolar; o zamana kadar `none`.

Dosya Yöneticisi hazır olunca başka alan, daha hızlı sayım ya da farklı klasör kırılımı (yıl/ay, e-Fatura/e-Arşiv ayrı) gerekirse bu dosyaya eklenir — notu ERP ajanına iletin.

## 7. Dosya Yöneticisi tarafı yapıldı (2026-09-23)

Tüketici artık var; ERP tarafında bu dikişe dokunan her değişiklik şunları
bozmamalı:

- **Çağrılan:** `erp_files_allowed()`, `erp_files_folders()` (sayılar kenar
  çubuğunda her ERP listelemesinde yeniden okunur), `erp_files_list($klasör,
  ['search', 'limit' => 200, 'offset'])`, `erp_files_search($sorgu, 50)`.
  Yükleme `view_folder_and_files_f.php` → `pg_explorer_erp_ready()`.
- **Dikişte bir satır değişti:** `erp_files_search()` her öğeye `folder`
  yazıyor (doküman bloğu söylüyordu, kod yazmıyordu). Üstteki aramada
  "Bulunduğu klasörü aç" ve Klasör sütunu buna dayanıyor.
- **Okunan öğe alanları:** `kind, id, name, title, type, size, kept, kept_at,
  source, number, doc_class, date, ship_date, account_id, account_title,
  tax_number, order_id, order_number, invoice_id, invoice_number, recipient,
  carrier, plate, sent_to, total_label, balance_label, status,
  status_label, cancelled, edoc_status, edoc_status_label, edoc_kind,
  gib_number, ettn, modified, username, url, download_url, edit_url` ve
  klasörde `key, label, icon, count, note`. Ad değişirse ekran sessizce boş
  alan gösterir.
- **Salt okunur kapı:** `pg_files_include_erp_document()`
  (`includes/fn/files.php`) — Dosya Yöneticisi'nin id ile çalışan 17 alt
  eylemi, `api.php` `delete_file` ve `edit_file.php` `erp_doc_type`'ı dolu
  satırı reddediyor. Yeni bir `erp_doc_type` değeri eklenirse kapı onu da
  kendiliğinden kapsar (boş olmayan her değer).
- **Önizleme:** `kept` doğruysa panel PDF'i kendiliğinden gösterir (dosya
  okuması); değilse "Önizlemeyi göster" düğmesi bekler. `kept`'in anlamı
  değişirse (ör. saklı ama sağlayıcıdan yeniden çekilen) bu karar yeniden
  düşünülmeli.
- Ayrıntı: `docs/degisiklikler.md` → "Dosya Yöneticisi'nde ERP Dosyaları".
