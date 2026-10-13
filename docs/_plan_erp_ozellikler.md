# Plan — ERP özellikleri: Faz 6–9

Durum: plan (2026-10-13). Satır ve fonksiyon adları `development` @ `47f3740`'a göre.
Kaynak: proje notu `notes/erp-secenekler.md` (madde numaraları aşağıda `#` ile geçer).
Önceki plan `docs/_plan_erp.md` (Faz -1 – 5, bitti); bu plan onun devamıdır.

**Kapsam kararı (Erdal, 2026-10-13):** bu planda yalnız ERP'nin kendi özellikleri var.
Entegrasyonlar (e-Belge sağlayıcıları, kargo firması API'si, pazaryeri, banka, SMS, ödeme
linki) Erdal'ın yerel işidir ve bu planda yoktur.

## 0. Sabit bağlam

- Ürün kodu `pinegrap/`. Önce `CLAUDE.md`; skill'ler (alt ajan görevine yolu yaz):
  `.claude/skills/pinegrap-erp/SKILL.md` (zorunlu), `pinegrap-dis-api`, `pinegrap-sema-adimi`,
  `pinegrap-ceviri`, `pinegrap-ui-deseni`, `pinegrap-veritabani`, `pinegrap-roller-yetki`,
  Faz 8 için `pinegrap-tasarimci-widget`.
- Dal `development`'tan, PR tabanı `development`. Commit ve PR metninde AI izi yok.
- Açık sürüm **2026.4.10**: şema adımı `includes/migrations/2026.4.10.php`,
  `upgrade_2026_4_10_erp_<konu>()`, çağrı `upgrade_to_2026_4_10()` listesinin sonuna;
  ERP etiketleri **10.58–10.69**, dolunca 10.90'dan sonrası. Yeni tablo
  `install/index.php` `get_tables()`'a. Değişiklik `docs/degisiklikler.md` ve
  `pinegrap/changelog.txt` açık sürüm başlığına.
- Ortak dosyalar (`2026.4.10.php`, `tr.json`, `changelog.txt`, `docs/*`, `install/index.php`,
  `includes/fn/output.php`): `git fetch origin development && git merge origin/development`
  sonrası yalnız ekleme.
- **Dokunulmaz:** `includes/erp/edoc/*` (Erdal'ın yerel e-Belge işi), `orders.notes`,
  `orders.tracking_company`, `settings_pane.php`'ye POST.
- Bitiş: `php tools/lint.php`, `check_lang.php`, `check_api_schema.php` (Faz 6, 7),
  `check_bindings.php` (Faz 8), `test.php`; migration sandbox'ta iki kez; `.src.js` +
  `.min.js` ikisi. PR'da "doğrulayamadıklarım".
- Bulut oturumu dev.pinegrap.com'a ulaşamaz; canlı deneme yerel Remote Control oturumuyla
  ya da Erdal'ın Claude bağlayıcısıyla (MCP) yapılır.

## 1. Kapsam ve sıra

| Faz | Madde | İş | Efor | Etiket |
|---|---|---|---|---|
| 6 | #27 | ERP yazma uçları dış API'de ve MCP'de | O | şema yok |
| 7 | #16 | Satın alma siparişi → alış faturası | O | 10.58 |
| 8 | #14, #7'nin hesap kısmı | Müşteri hesabında "Faturalarım" ve cari ekstresi; B2B fiyat ve açık hesap | O–B | 10.59, 10.60 |
| 9 | #11, #12, #29 | Ekstrede xlsx, vade farkı, nakit akışı tahmini | K (her biri) | 10.61 |

Fazlar sırayla; her faz kendi PR'ı (Faz 8 ve 9 parça parça PR olabilir).

**Bu planda olmayanlar:**

- Erdal'ın yerel işi: #1–#6 e-Belge (sağlayıcı sürücüleri, kuyruk, GİB kod listesi,
  e-İrsaliye, e-SMM, UBL yazıcı), #8–#10, #13, #21–#23, #25, #26 ve #7'nin ödeme linki
  parçası. Sürücü arayüzü için bkz. §7.
- Ayrı planı olan: #20 kargo → `docs/_plan_kargo.md` (satır numaraları 2026-10-08'e göre;
  başlamadan yeniden doğrula).
- Talep gelince: #15 siparişi bölme, #17 çoklu depo (yol haritası belgesinde D4 fazlaması),
  #18 set/reçete, #19 lot/SKT, #24 müşavir programı aktarımı (örnek dosya gerekir),
  #28 fişten okuma.

---

## 2. Faz 6 — ERP yazma uçları API'de ve MCP'de (#27)

**Neden:** Bugün API'de ve MCP'de fatura, teklif, irsaliye ve stok yalnız okunuyor;
yazılanlar cari, tahsilat ve gider (`includes/erp/api.php` `erp_api_routes()`). MCP
araçları rotalardan kendiliğinden üretildiği için (`includes/mcp/tools.php`) yeni rota =
yeni araç; MCP tarafında kod yok.

**Yerleşim:** rota tablosu `includes/erp/api.php`, işleyici ve sunucu
`includes/erp/api_resources.php`, nesne şeması `erp_openapi_objects()`, olay listesi
`erp_webhook_events()`. `includes/api/schema.php`'ye dokunulmaz.

**Rotalar** (kapsam parantezde; her işleyici var olan alan fonksiyonunu çağırır, ikinci bir
hesap yolu açmaz):

| Rota | Fonksiyon |
|---|---|
| `POST /erp/invoices` (erp:write) — satış ya da alış **taslağı** | `erp_invoice_draft_save($data, 0)` |
| `POST /erp/invoices/{id}` (+PATCH) — yalnız taslak; kesilmişte 409 | `erp_invoice_draft_save($data, $id)` |
| `DELETE /erp/invoices/{id}` — yalnız taslak | `erp_invoice_draft_delete()` |
| `POST /erp/invoices/{id}/issue` | `erp_invoice_issue()` |
| `POST /erp/invoices/{id}/cancel` | `erp_invoice_cancel()` |
| `POST /erp/invoices/{id}/returns` — kalem (`parent_line_id`) + adet | `erp_return_build()` + `erp_invoice_return()` |
| `POST /erp/invoices/{id}/email` | `erp_mail_invoice_send()` |
| `POST /erp/orders/{order_id}/invoice` (erp:write) | `erp_invoice_from_order()` |
| `GET /erp/invoice-recurrences`, `POST /erp/invoices/{id}/recurrence`, `POST /erp/invoice-recurrences/{id}/stop` | `includes/erp/invoice_recurring.php` |
| `POST /erp/quotes`, `POST /erp/quotes/{id}`, `POST /erp/quotes/{id}/status`, `POST /erp/quotes/{id}/invoice`, `POST /erp/quotes/{id}/email`, `GET /erp/quotes/{id}/document` | `erp_quote_save()`, `erp_quote_set_status()`, `erp_quote_to_invoice()`, `erp_quote_mail_send()`, `erp_quote_html()` |
| `POST /erp/waybills` (`invoice_id` ya da `order_id` ya da satırlar), `POST /erp/waybills/{id}/cancel`, `POST /erp/waybills/{id}/invoice` | `erp_waybill_data_from_invoice()` / `_from_order()` + `erp_waybill_create()`, `erp_waybill_cancel()`, `erp_waybill_invoice()` |
| `POST /erp/stock-counts`, `POST /erp/stock-counts/{id}/items`, `POST /erp/stock-counts/{id}/apply`, `POST /erp/stock-counts/{id}/cancel` | `includes/erp/stock_counts.php` |
| `GET /erp/stock/minimums`, `POST /erp/stock/minimums` | `includes/erp/stock_levels.php` |
| `POST /erp/cash-transfers` (erp_cash:write) | `erp_post_transfer()` |
| `POST /erp/cheques`, `POST /erp/cheques/{id}/step` (erp_cash:write) | `erp_cheque_create()`, `erp_cheque_step()` |
| `GET /erp/reports/profit`, `GET /erp/reports/cashflow`, `GET /erp/dashboard` (erp:read) | `erp_profit_report()`, `includes/erp/cashflow.php`, pano kartlarının hesapları |

**Kurallar:**

- Salt okuma muhasebeci (`manage_erp_readonly`) ve sahibi ERP hakkı olmayan anahtar hiçbir
  yazma rotasından geçemez (`erp_owner_scopes()` tavanı). Kasa yürüten her yol
  `erp_api_expense_cash_gate()` desenindeki kasa kapısından geçer.
- Dönem kilidi reddi 422, kodu `period_locked`; kredi limiti reddi 422, kodu
  `credit_limit` (yalnız `erp_invoice_issue()` sorar, siparişin faturası durmaz).
- Tutarlar kuruş; miktar ondalık sayı (`erp_quantity_in()` makine biçimi). Satırların
  vergisi, iskontosu, tevkifatı yalnız `erp_manual_lines_build()` ile hesaplanır; istemcinin
  gönderdiği toplam kabul edilmez, yanıtta hesaplanan döner.
- `X-Dry-Run` (`includes/api/dry_run.php`) her yazmada çalışır: kurucu fonksiyonlar
  (`erp_manual_lines_build()`, `erp_manual_header_build()`, `erp_waybill_lines_build()`)
  yazmadan önce koşar, aynı 422'yi verir. `Idempotency-Key` (`includes/api/idempotency.php`)
  oluşturma ve kesim rotalarında.
- Olaylar var olan kapıdan (`erp_event()`); yeni olaylar `erp.waybill.cancelled`,
  `erp.stock_count.applied` ve Faz 7'ninkiler. Denetim izi ve bildirim kendiliğinden düşer.
- MCP araç açıklaması rota `summary`/`description`'ından gelir: geri alınamayan adımlarda
  (issue, cancel, returns, apply) açıklama bunu söylesin ("Issues the invoice: it takes a
  number and cannot be edited afterwards").
- e-Belge'ye gönderim bu fazda **yok**: kesim sonrası mağazanın otomatik gönderim ayarı
  bugünkü gibi çalışır, API ayrıca sağlayıcı çağırmaz.

**Kanıt:** `check_api_schema.php` temiz; sandbox'ta curl ile taslak → kes → PDF → iade →
iptal; `X-Dry-Run` ile 422 ve 200; aynı `Idempotency-Key` ile ikinci istek aynı yanıt;
salt okuma anahtarla 403. Yerelde Erdal'ın Claude bağlayıcısıyla "taslak fatura oluştur,
kes, PDF'ini getir" (yeni yazma kapsamları için bağlayıcı yeniden bağlanır).

**Belgeleme:** API sayfası pinegrap.com/api'de, İngilizce, görsel tasarımcıyla (repo'da
değil).

## 3. Faz 7 — Satın alma siparişi (#16)

**Neden:** Stok tutan mağaza tedarikçiye siparişi bugün Pinegrap dışında yazıyor; minimum
stok uyarısı bir eyleme bağlanmıyor.

**Şema (10.58, `upgrade_2026_4_10_erp_purchase_orders()`):**

- `erp_purchase_orders`: `erp_quotes`'un deseni (ayrı tablo, satırlar `line_data`
  JSON'unda): `series`, `number`, `issue_year`, `full_number`, `account_id` (tedarikçi),
  `issue_date`, `expected_date`, `currency`, `exchange_rate`, toplamlar (`subtotal`,
  `discount_total`, `tax_total`, `tax2_total`, `grand_total`, `grand_total_base`),
  `status ENUM('open','sent','partial','received','cancelled')`, `line_data`, `notes`,
  `created_by/at`, `updated_at`, `sent_at`. Anahtar `uniq_number (series, number, issue_year)`,
  `idx_account`, `idx_status`.
- `erp_invoices.purchase_order_id INT UNSIGNED NOT NULL DEFAULT 0` + index;
  `erp_invoice_items.source_line VARCHAR(16) NOT NULL DEFAULT ''` (siparişteki satırın
  anahtarı).
- `erp_document_series.doc_kind` ENUM'una `'purchase_order'`: önce `install_column_info()`
  ile sor, yoksa `install_modify_column()` (4.46'daki desen; ENUM'da olmayan değer sessizce
  numara üretmez).

**Akış:**

1. Sipariş yazılır (ekran `add_erp_purchase_order.php` / `edit_…`, liste
   `erp_purchase_orders.php`); satır editörü `erp_invoice_editor.js` alış yönünde.
   Numara `erp_next_number($seri, 'purchase_order', $yıl)`, seri `ERP_PURCHASE_ORDER_SERIES`
   ya da fatura serisi + `S`.
2. PDF (`includes/erp/templates/purchase_order.html`, `erp_invoice_document_data()` deseni)
   ve tedarikçiye e-posta (teklif e-postasının deseni); gönderilince `sent`.
3. "Alış faturasına dönüştür": kalan miktarlarla alış faturası **taslağı**
   (`erp_invoice_draft_save()`, `purchase_order_id` ve satır başına `source_line` dolu).
   Birden çok kez yapılabilir (parçalı teslim).
4. Durum türetilir: kesilmiş alış faturalarının `source_line` miktarları toplanır;
   hepsi karşılandıysa `received`, bir kısmıysa `partial`. İptal edilen fatura sayılmaz.
5. Stok ve maliyet **bugünkü gibi** yalnız alış faturasıyla değişir (4.77); sipariş ne
   stoğa ne cari defterine ne KDV'ye yazar.

**Minimum stok bağlantısı:** `erp_stock_minimums.php`'de seçilen ürünlerden "Satın alma
siparişi taslağı"; tedarikçiyi operatör seçer, önerilen miktar `minimum − mevcut`
(operatör değiştirir).

**Ayrıca:** menüde ERP altında "Satın alma siparişleri" (`$active_menu = 22`), panoda
"Bekleyen siparişler" kartı, olaylar `erp.purchase_order.created|sent|received|cancelled`,
API `GET/POST /erp/purchase-orders…` (Faz 6'nın kuralları), denetim izi nesne türü.
Muhasebeci paketine ve KDV raporuna girmez.

**Kanıt:** migration iki kez; 3 satırlı sipariş → 2 satırla fatura taslağı → kes →
`partial` → kalan satır → `received`; stok yalnız faturalarla arttı; ENUM genişledi.

## 4. Faz 8 — Müşteri hesabı ve B2B (#14, #7'nin hesap kısmı)

Üç parça, sırayla ve ayrı PR'larla. Ödeme linki (iyzico Link) bu fazda **yok**.

### 8a. "Faturalarım" sistem widget'ı (şema yok)

- Yeni sistem widget türü `account_invoices` ("My Invoices"): `pg_sw_type_labels()` ve
  `assets/js/style_designer.js` `SW_TYPES` birlikte. `account_security` (2026.4.8) örnek
  alınır; dört şablonun Hesabım sayfasına eklenir.
- Oturumdaki kullanıcının kişisi → `erp_accounts.contact_id`. Bağlı cari yoksa ya da ERP
  kapalıysa widget hiçbir şey basmaz.
- Gösterilen: kesilmiş satış faturaları ve iadeler (taslak ve iptal yok), tutar, vade,
  kalan, gecikme rozeti; toplam açık bakiye; "Ekstreyi indir" (mutabakat PDF'i,
  `erp_reconciliation_pdf()`).
- PDF ön yüz ucu ayrı ve dar: `get_erp_invoice_pdf.php` ERP hakkı ister, ona dokunulmaz;
  yeni uç faturanın carisinin oturumdaki kişiye ait olduğunu sorar, değilse 404; dosya
  `erp_archive_file()`'dan. Hız sınırı `waf_rate`.

### 8b. Cariye özel fiyat ön yüzde (10.59)

- Ayar `config.erp_storefront_account_prices` (varsayılan kapalı). Açıkken, oturumdaki
  kişinin carisinde fiyat satırı ya da genel iskonto varsa (`erp_account_price_terms()`)
  ürün listesi, ürün sayfası ve sepet o fiyatı gösterir ve sipariş o fiyatla yazılır.
- Tek giriş noktası: `includes/fn/ecommerce.php` `get_discounted_product_prices()` ve
  `update_order_item_prices()`. Hesap saf bir fonksiyona (`erp_storefront_price($ürün,
  $cari)`) alınır ve `tests/`'te sınanır.
- Karar (varsayılan): cari fiyatı kampanyayla **toplanmaz**; ikisinden düşük olan geçer
  (editördeki "kampanya üstüne eklenmez" kuralının ön yüz karşılığı). Ayar kapalıyken
  hesaplama bit bit bugünkü gibi kalır (golden test).

### 8c. "Cari hesaba yaz" ödeme yolu (10.60)

- Kredi limiti tanımlı ve aktif bir cariye bağlı kişi ödeme adımında "Cari hesabıma yaz"
  seçeneğini görür. Var olan çevrimdışı ödeme yolunun üstüne kurulur (yeni enum değeri
  açılmaz); ayar `config.erp_open_account_payment`.
- Ödemede `erp_credit_refusal($cari, $sipariş_toplamı)` sorulur; limit aşılıyorsa seçenek
  reddedilir ve neden yazılır.
- Sipariş tamamlanınca fatura bugünkü otomatik fatura ayarıyla kesilir; vade cari
  vadesinden (`erp_account_due_date()`).

**Kanıt:** iki kullanıcı (bağlı cari / bağsız) ile widget; başkasının faturası 404; 8b
ayarı kapalıyken sepet tutarı değişmedi (test), açıkken cari fiyatı; 8c limit içi ve
limit dışı sipariş.

## 5. Faz 9 — Küçük işler

### 9a. Banka ekstresinde Excel (#11, şema yok)

`erp_bank_read_csv()` yanına `erp_bank_read_file()`: uzantıya göre CSV ya da xlsx
(gömülü `includes/phpexcel`); sütun eşleme, `line_hash` ve eşleştirme aynı kalır.

### 9b. Vade farkı (#12, 10.61)

- Ayar: aylık oran ve hizmet satırının KDV oranı. Hesap `includes/erp/aging.php`'deki
  gecikme gününden, saf fonksiyon + test.
- Ekstre ve mutabakatta bilgi satırı; "Vade farkı faturası" düğmesi tek satırlı **satış
  faturası taslağı** açar. Kendiliğinden hiçbir şey kesilmez, deftere yazılmaz.

### 9c. Nakit akışı tahmini (#29, şema yok)

Kasa akışı ekranına "Önümüzdeki 30/60/90 gün" sekmesi: açık satış faturaları vadesine göre
giriş; açık alış faturaları ve ödenmemiş giderler çıkış; tekrarlayan fatura ve giderlerin
sonraki tarihleri; portföydeki çekler vadesinde giriş, verilen çekler çıkış. Gerçekleşenle
karışmaz, ayrı tablo. Hesap saf fonksiyon + test.

## 6. Varsayılan kararlar

- API'den yalnız **taslak** oluşturulur; kesim ayrı ve açık bir çağrıdır.
- Satın alma siparişi muhasebe belgesi değildir (teklifin karşılığı): stok, defter, KDV
  yok.
- Ön yüz cari fiyatı ve açık hesap ayarla açılır, varsayılan kapalı.
- Vade farkı otomatik kesilmez.

## 7. e-Belge sürücü arayüzü (Erdal'ın yerel işi için kısa not)

Bulut thread'leri bu bölümdeki dosyalara dokunmaz.

- Sözleşmenin tek doğru kaynağı `includes/erp/edoc/registry.php` başlık yorumu. Yeni
  sağlayıcı = `includes/erp/edoc/<kod>.php` + `erp_edoc_drivers()`'a bir satır; kod bir
  kez belge gönderilince değişmez.
- Fonksiyonlar `erp_edoc_<kod>_`: `info` (yetenekler: `einvoice`, `earchive`, `ewaybill`,
  `inbox`, `accounts`, `return`), `fields`, `ping`, `check_taxpayer`, `send_invoice`,
  `poll`, `cancellable`, `cancel_invoice`, `fetch_document`, `send_waybill`,
  `date_refusal`, `inbox`, `inbox_document`, `accounts`, `account`, `account_save`.
  Tanımlanmayan işlem desteklenmiyor sayılır.
- HTTP yalnız `erp_edoc_http()`, günlük `erp_edoc_log()` (maskeli); kimlikler
  `erp_edoc_providers`'da şifreli; oturum `erp_edoc_session()` / `_save()`.
- Gönderimden önce reddedilecek her kural `party_missing` / `document_missing` listesine
  (`erp_edoc_tax_number_valid()`, `erp_edoc_postcode_valid()`, `erp_edoc_person_name()`).
  Kalıp: `includes/erp/edoc/isbasi.php`.
- Aynı alana düşen açık işler: `erp_edoc_queue` tablosu var, işleyicisi yok (#2); GİB
  UBL-TR kod listeleri v1.43 14 Eylül 2026'da yürürlüğe girdi, istisna ve tevkifat kodları
  gözden geçirilmeli (#3). Sağlayıcı karşılaştırması proje notunda
  (`notes/efatura-saglayici-karsilastirma.md`).

## 8. Elle doğrulanacaklar (Erdal)

- dev.pinegrap.com'da Claude bağlayıcısıyla Faz 6 araçları (yeniden bağlanarak).
- Satın alma siparişi PDF'i ve tedarikçi e-postası (dev'de e-posta gitmez; ekran ve ek).
- Bir şablonda Hesabım → Faturalarım görünümü; cari fiyatlı müşteriyle sepet.

## 9. PR açıklaması

Ne değişti · Etiketler · Golden/sandbox senaryoları · Denetim çıktıları ·
**Doğrulayamadıklarım** · Sorular.
