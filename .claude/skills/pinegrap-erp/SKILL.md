---
name: "pinegrap-erp"
description: "Pinegrap CMS'in ERP (ön muhasebe) modülünde çalışırken kullan: para/vergi/yuvarlama, belge numarası ve taslak, tahsilat-tahsis-iade defter mantığı, cari kart, stok ve maliyet, gider, teklif, tekrarlayan belge, çek/senet, banka ekstresi, denetim izi ve bildirim, e-belge ve ERP ekran desenleri."
---

# Pinegrap — ERP (ön muhasebe) modülü

Bu modüldeki hatalar sessiz muhasebe hatasıdır: aynı para iki kez sayılır,
numara boşa harcanır, KDV iki yerden farklı hesaplanır. Maddeleri atlama.

Plan dosyası `docs/_plan_erp.md` (depo dışında, yalnız geliştirme makinasında).

## Modül kapısı ve kurulum

- ERP ekranlarını `validate_erp_access($user, $area)` ile kapıla; `$area`
  `''`, `'cash'`, `'settings'`, `'read'` (formu yalnız okuyan ekran: dışa
  aktarma), `'accountant'` (muhasebeci paketi) ya da `'write'` (formsuz
  değişiklik: elle çalıştırılan iş) olabilir.
- Muhasebeci yetkisi `manage_erp_readonly` (4.78, rol 3): kapı ERP'yi okumaya
  açar, `settings`/`write` alanını ve alanı `read`/`accountant` olmayan her
  POST'u reddeder. Yeni bir ekran formunu okuma için POST'luyorsa alanını
  söylesin; ekranda değiştiren düğmeyi `USER_ERP_READONLY` ile gizle; ERP
  dışındaki ekranda (sipariş, kişi, tezgâh, dış API kapsamı) yazan her şey
  `empty($user['manage_erp_readonly'])` ister.
- Modül kapalıyken 404 verme; hata mesajı + `pg_settings_return_url('commerce','pgset-erp')`
  bağlantısı bas.
- ERP tablolarını `config.erp_enabled` değerinden **bağımsız**, her zaman kur —
  şemayı anahtara bağlarsan sonradan açan site sürümün söylediğinden farklı
  şemaya düşer.
- Sabitleri (`ERP_ENABLED`, `ERP_SELLER_VKN`, `ERP_FX_ENABLED`,
  `ERP_WALKIN_ACCOUNT_ID`…) savunmacı oku; eski kurulumda tanımsız olabilir.
- Yetki sütunları: `manage_erp`, `manage_erp_cash`, `manage_erp_settings`,
  `manage_erp_readonly` (bkz. `pinegrap-roller-yetki` — rol-3 merdiveninin dördü birden güncellenir).
- ERP menü slotu **22**'dir; yeni ekran açınca `$active_menu = 22` switch'ine
  ekle (`includes/fn/output.php`). Menü başlığı `'Resource planning (ERP)'`
  ("Kaynak Planlama (ERP)"), pano başlığı `'Enterprise resource planning (ERP)'`;
  `lang('ERP')`'nin çevirisini değiştirme (izin satırı, Dosya Yöneticisi kökü).

## Para, kur, yuvarlama

- Para tamsayı **kuruş** `BIGINT`; `bcmath` yok. Oran `DECIMAL(6,3)` /
  `DECIMAL(15,6)`, miktar `DECIMAL(15,4)`.
- Her çarpım ve yuvarlama `includes/erp/money.php` üzerinden; ikinci bir
  yuvarlama noktası açma. `(int)` kırpma yerine `round()`.
- Ev para birimi mağazanın ana para birimidir (`erp_base_currency()`), `'TRY'`
  değil. Dönüştürülmüş sütun adı `*_base`; dönüşüm `erp_to_base($kurus,$kur)`.
- Ekranda `erp_money_out_currency($kurus,$kod)` kullan (ziyaretçi kuru
  uygulanmaz).
- Her döviz dalını `erp_fx_enabled()` ile kapıla.
- Kur farkını yalnız `erp_fx_post_difference()` yazsın: fatura `paid` olunca,
  `erp_post_receipt()` transaction'ı içinde tek `kind='fx_diff'` satırı.
- Siparişten üretilen fatura her zaman ana para birimindedir.
- Yazılan miktar `erp_quantity_in()` ile okunur (tek virgül + üç hane ülkeye
  göre: `erp_decimal_comma()`); `str_replace(',', '.')` ya da
  `erp_fx_rate_in()` miktar için kullanma. JS editörü aynı kuralı
  `data-decimal-comma` ile uygular. Ekranda miktar `erp_quantity_text()`, yüzde
  `erp_percent_text()` (Türkçe %20, diğer 20%); `'%' .` birleştirme yazma. CSV
  ayırıcısı `erp_csv_delimiter()`, PDF kâğıdı `erp_paper_size()` /
  `{{paper}}`.
- Vergi dahil bir tutarı bölmek için `erp_split_gross()`; `/ (1 + oran)`
  yazma.
- Tezgâh ERP açıkken tutarı `local_sale_money()` (`erp_money_out`) ile yazar;
  betik ayırıcıları `pg_sale_config`'teki `decimal`/`thousands`'tan alır.
- Ekranda ve belgede sayı biçimi panel dilinden: `erp_number_separators()`
  (tr 1.234,56 · en 1,234.56); tutar `erp_money_out_currency`, kur
  `erp_fx_rate_text`. `number_format(..., '.', ',')` ile tutar yazma. Form
  alanına giden değer makine biçiminde kalır (`erp_fx_rate_out`, iki ondalık
  noktalı).

## Vergi ve satır hesabı

- KDV tevkifatı (4.68, `includes/erp/withholding.php`): `tax_total` tam KDV,
  `withholding_total` tevkif edilen kısım, **`grand_total` = ara toplam − indirim
  + KDV − tevkifat** (carinin borcu; defter/tahsis/vade buna bakar). Satırda
  `withholding_code`, `withholding_rate` (KDV'nin yüzdesi, 4/10 = 40),
  `withholding_amount`. Listede olan kodun oranı listeninkidir. Yeni bir toplam
  hesabı yazarken tevkifatı düşmeyi unutma; iade parçası tevkifatı KDV gibi
  satırda kalanla sınırlar.
- `erp_invoice_items.tax_total` satırın **toplam** vergisidir;
  `order_items.tax` **birim** vergidir. Ad farkı bilinçlidir.
- `submit_order.php` indirimi toplamdan önce KDV'den düşer ama
  `order_items.tax_total` indirimsiz kalır — sipariş satırlarını olduğu gibi
  faturaya taşımak fazla faturalamaktır.
- `erp_order_lines()` indirimli satırda KDV'yi kalan matrahtan yeniden alır
  (`erp_apply_rate($line_total-$pay,$oran)`); `oran × matrah = KDV` bozulursa
  e-fatura denetimi düşer.
- Satır KDV oranını `erp_line_tax_rate()` ile **yasal oran** olarak taşı,
  yuvarlanmış kuruşun bölümü olarak değil (361/2008 → 18, 17.978 değil).
- Sipariş başlık tutarlarını (`shipping`, `discount`, `surcharge`,
  `installment_charges`, `gift_card_discount`) ayrı ele al; `orders.total`
  taksit masrafını içerir. Kargo, ek ücret ve taksit masrafı satırı yalnız
  `erp_order_charge_lines()` ile: ödeme ekranı bunlara vergi eklemediği için
  vergi tutarın içinden ayrılır (`erp_split_gross()`), malların oranıyla,
  çok oranlıda paylaştırılarak; `erp_shipping_taxed()` (4.76) kapatabilir.
  Sipariş vergisine bağlama yalnız mal satırlarında.
- Satır↔başlık KDV farkı KDV'li satır başına ≤1 kuruşsa dağıt, daha genişse
  reddet; bu kapı `erp_next_number()`'dan **önce** çalışsın.
- İki iskontoyu sırayla uygula: `offer_amount = apply_rate(line_total, offer_rate)`,
  sonra `typed = apply_rate(line_total−offer_amount, discount_rate)`.
- Vergiyi adlandıran her başlık `erp_tax_label($which)` (boşsa KDV çevirileri,
  doluysa `config.erp_tax_name`); `lang('VAT')` yeni ekrana yazma. Tevkifat
  Türkiye'ye özgüdür, adı değişmez.
- Mağazadan satışın bölge oranı `erp_store_zone_rate()` (eyalet yalnız bir
  vergi bölgesindeyse: `erp_store_tax_state()`); çekirdek
  `get_default_tax_rate()`'e dokunma. Cariye satışta kendi oranı olmayan
  ürün `erp_account_zone_rate($account_id)` (alıcının bölgesi; bölgesiz yurt
  dışı 0). `get_tax_rate_for_address()`'e bölgede olmayan bir eyalet verme
  (`erp_zone_state()`): çekirdek onu "bölge yok" okur.
- İkinci vergi (4.75, `erp_tax2_enabled()`): satırın `tax_total`'ı iki
  verginin toplamı, `tax2_amount` ikincinin payı; başlıkta `tax2_total`.
  Yeni bir satır/başlık yazımı `erp_tax2_line_sql()` / `erp_tax2_total_sql()`
  eklesin; KDV'yi ayrı göstermek gereken yer `tax_total - tax2_total` alır.
  Satırın vergi tutarını gösteren sütunun başlığı
  `erp_line_tax_label($which, $has_tax2)` ("KDV + PST"): tutar iki vergiyi
  birlikte taşır, "KDV" demek yanlış olur. Oranı `erp_percent_text()` ile ve
  ikinci oranla yaz ("%5 + %7").
  Tevkifat birinci vergiden; ikinci vergili belge e-Belge'ye gitmez.

## Belge numarası ve taslak

- Numarayı yalnız `erp_next_number($series,$tip,$yil)` ile, transaction içinde al.
  Biçim `erp_number_style()` (gib / year / continuous, 4.72); sürekli biçim
  `issue_year = 0` satırında sayar, yıllık biçimler yılın satırını paylaşır —
  numaranın biçimini başka yerde üretme, `full_number`'dan yıl ya da hane
  çıkarmaya kalkma. Belgenin `issue_year`'ına tarihin yılını değil
  `$numbered['year']`'ı yaz (sürekli biçimde 0): benzersiz anahtar
  `(series, number, issue_year)`, tarihin yılı sürekli 1'i yıllık 1'le
  çakıştırır. Yıl 0'daki belge hiçbir yıla ait değildir.
- Taslak (`status='draft'`) numara almaz, defter kaydı atmaz, seri sayacına
  dokunmaz: sahte seri `_D` (`ERP_DRAFT_SERIES`), `issue_year=0`, `number=id`,
  `full_number=''`. Gerçek bir seriye `_D` adı verilemez.
- Taslağı her kayıtta bütünüyle yeniden yaz (satırlar DELETE + INSERT); taslak
  **silinir**, iptal edilmez.
- Kesimde (`erp_invoice_issue()`) satırları DB'den okuyup
  `erp_manual_lines_build()` ile **yeniden hesapla**; `FOR UPDATE` + numara +
  UPDATE + `erp_account_post()` + `erp_account_refresh_balance()` tek transaction.
- `erp_invoice_create_manual($data)` imzasını ve dönüş dizisini koru.
- Kesilmiş faturayı düzenleme; yalnız `notes` düzenlenebilir.

## İade ve iptal

- İadeyi kendi belgesi ve **kendi serisinde** kes (fatura serisi + `I` ya da
  `ERP_RETURN_SERIES`); `uniq_number` `doc_type` içermez.
- Tam iadede ana faturanın rakamlarını **kopyala**, yeniden hesaplama; kısmi
  iadede satırı orantılı böl, satırı boşaltan parça kalanı aynen alsın.
- İade satırını `parent_line_id` ile ana satıra bağla — aynı ürünün farklı
  fiyatlı iki satırı ürüne göre ayırt edilemez.
- Faturayı yalnız üzerinde tahsis ve iade yokken iptal et; defter kaydını
  **silme, ters kayıtla çevir**, numarayı serbest bırakma.
- Sipariş iptali/kart iadesi ERP'ye kendiliğinden dokunmaz; sipariş kartı
  (`order_flow.php`) "İade veya iptal" ve "Paranın iadesi" adımlarını sunar.
  Resmileşmemiş fatura (`edoc_status` none/error/rejected/created) iptal
  edilir, resmi olana iade kesilir. İptal `orders.erp_invoice_id`'yi sıfırlar;
  siparişin faturasını `erp_order_flow_invoice_row()` ile bul.
- Geri ödenecek parayı cari bakiyesinden okuma (ortak cariler): sipariş başına
  hesapla — `erp_order_flow_money()`, `erp_cash_transactions.order_id` (4.66).
- Kart iadesi ERP açıkken kalem/adetle (`order_refund.php`); tutarı sunucu
  `erp_return_build()` ile hesaplar, sayfadan gelen tutara güvenme. Sonra iade
  formu aynı adetlerle dolu açılır.
- `orders` 2026.4.8'e kadar MyISAM'dı; artık InnoDB (eşik üstü büyük tabloda operatör çevirene kadar MyISAM kalabilir). Akış MyISAM-güvenli kalır: ERP transaction'ının `orders`'ı geri alacağına güvenme. Deneme betiğinde
  `erp_invoice_cancel()` gibi `orders`'a yazan fonksiyonu çağırdıysan bağlantıyı
  elle geri koy.

## Stok ve maliyet (4.77)

- Siparişten kesilen fatura (`order_id > 0`) **stok sayısını değiştirmez**:
  sipariş malı zaten düştü. Satırları yine `sale` hareketi yazar, ama
  `stock_wanted = 0` ile: maliyet kaydı içindir (kâr/zarar). İadesi ana
  hareketten orantılı maliyetle, yine sayısız döner. Sayıyı değiştiren hareket
  yalnız elle kesilen satış, alış ve onların iade/iptalinde
  (`includes/erp/stock.php`). Hareketi olmayan eski satır kâr/zararda bugünkü
  ortalama maliyetle **tahmin** edilir; tahmini ayrı raporla.
- `products` 2026.4.8'e kadar MyISAM'dı; artık InnoDB (aynı şart). Akış değişmedi: stok sayısını ERP transaction'ı içinde değiştirme. Hareketi
  işlem içinde `erp_stock_record()` ile yaz (`stock_wanted`), sayıyı commit
  sonrası `erp_stock_apply_pending()` değiştirsin (derinlik > 0 iken çalışmaz).
  Yeni bir belge akışı eklersen iki çağrıyı da ekle.
- Hareket `UNIQUE (kind, line_id)`, `INSERT IGNORE`: yeniden yazma iki kez
  saymaz. İptal hareketi `kind = 'cancel'`, `reverses_id` ile.
- Ortalama yalnız alış tarafıyla (alış, tedarikçiye iade, bunların iptali)
  oynar; satış ortalamadan çıkar ve ortalamayı değiştirmez. Maliyet = satır
  matrahı (KDV hariç), yabancı parada `erp_to_base`. İlk alıştan önceki
  maliyetsiz stok ilk alışın maliyetini alır. Maliyet ana para biriminde:
  editör son alış fiyatını yalnız ana para birimindeki alışa önerir.
- Sayıya yalnız stok takipli ürün ve tam sayı miktar yansır
  (`erp_stock_whole()`); `config.erp_stock_documents = 0` iken yalnız maliyet.

## Dönem kilidi (4.79)

- `config.erp_lock_date`; `includes/erp/lock.php`. Deftere ya da kasaya yazan
  **her yeni yol** önce `erp_lock_refusal($tarih)` sorsun ve nedeni kullanıcıya
  versin; `erp_account_post()` / `erp_cash_post()` yine sorar ve reddi
  `erp_lock_refused()` ile bırakır, `erp_db_error()` onu döndürür.
- İptal ve iadede sorulan tarih: iadenin kendi tarihi; iptalde asıl belgenin
  (kapalı dönemdeki belge iptal edilemez, iadesi bugün tarihli kesilir).
  Ödemede ödemenin tarihi: kapalı dönemin ödenmemiş gideri/faturası açık
  dönemde ödenebilir.

## Muhasebeci paketi (4.78)

- `includes/erp/accountant.php`: `erp_accountant_collect()` sayfaları kurar,
  `erp_accountant_write_workbook()` çok sayfalı xlsx yazar (hücre
  `['v' => değer, 't' => tür]` ile türünü ezer: money, money_bold, date, int,
  number, bold). Başka bir Excel çıktısı (kâr/zarar, giderler) aynı yazıcıyı
  kullanır; ikinci bir xlsx yazıcısı açma.
- Paket ZIP'i `data/temp/erp_packages/` (`.htaccess` ile kapalı): kayıt değil teslim kopyasıdır; dosyası yoksa ekran yeniden hazırlatır, aylık iş kendisi yeniden kurar. `data/` altına yeni kalıcı klasör açma; yeniden üretilebilen dosya `data/temp/`'e. Bağlantı
  belirtecinin yalnız sha256'sı saklanır; her yeni bağlantı eskisini düşürür;
  e-posta gönderilemezse eski belirteç geri yazılır.
- Sayfaya yeni bir kayıt türü eklerken: kendi sayfası, KDV özetinde satırı
  (`includes/erp/vat_report.php`'deki yardımcı + `erp_vat_sorted()`'ın sıra
  dizisi + `erp_vat_kind_labels()`), Özet'te bloğu (`$kind_labels` +
  `$summary[<tür>]`) ve varsa dosyaları (`belgeler/<klasör>/`).

## KDV raporu (E1)

- Vergi toplamı tek yerden: `includes/erp/vat_report.php`
  (`erp_vat_add_document()`, `erp_vat_add_expense()`, `erp_vat_sorted()`).
  Muhasebeci paketinin KDV sayfası da, `erp_vat_report()` / ekran
  (`erp_vat_report.php`) ve Excel'i (`erp_vat_workbook()`) de bunları
  kullanır; KDV'yi başka bir döngüde yeniden toplama, yeni kaynak buraya girer.
- Tanımlar: hesaplanan = satış − satış iadesi; indirilecek = alış −
  tedarikçiye iade + indirilebilen gider KDV'si; fark
  `erp_vat_difference()` (satış tevkifatı yalnız `erp_vat_net_withholding()`
  açıkken düşülür), başlığı `erp_vat_difference_label()`. İkinci vergi farka
  girmez. Farkı başka yerde elle çıkarma. İptal listede işaretli, toplamda yok.
- Menü adı `pg_erp_tax_report_label()` (menü ERP yüklenmeden kurulur);
  ekranda `erp_tax_label('report')`.

## Muhasebe kuralları (4.103)

- `includes/erp/rules.php`: mali müşavirin belirleyeceği seçimler config'te,
  ERP Ayarları'ndaki "Muhasebe kuralları" kartında (kendi formu, `rules=1`).
  Açık soruların listesi `docs/erp_malimusavir_sorulacaklar.md`; ayarla
  çözülebilen yeni bir soru bu dosyaya ve karta birlikte girer.
- %0 satırın istisna kodu yalnız `erp_vat_line_exemption_code($satır)` ile:
  satırın kendi kodu > ürünün (`products.vat_exemption_code`) > mağazanın
  varsayılanı; KDV'li satıra kod yazılmaz. `erp_invoice_items`'a satır yazan
  yeni bir yol açarsan `vat_exemption_code`'u bununla yaz (bugün sipariş,
  elle fatura + kesim, iade).
- Gider kategorisinin "Vergi indirilir" varsayılanı
  `erp_expense_category_deductible()`: form ve API (gönderilmezse) buradan
  başlar; yazılmış gider kendi değerini korur.

## Giderler (4.95)

- `includes/erp/expenses.php`. Gider bir carinin borcu değildir: cari defterine
  yazılmaz, ödeme `erp_cash_post()` ile `doc_type = 'expense'`,
  `doc_id` gider, `account_id = 0`; iptal `doc_type = 'cancel'`, `doc_id`
  hareket (kasa defteri iptali böyle tanır). Yeni bir ekran kasa hareketini
  türüne göre bağlıyorsa `expense` kolunu ekle (kasa defteri, gösterge).
- Tutar: `erp_expense_figures()` — KDV dahil/hariç, fişteki KDV tutarı oranın
  verebileceği aralıkta olmalı. Ana para: toplam ve KDV ayrı çevrilir, matrah
  farktır.
- `tax_deductible = 0` olan giderin KDV'si maliyettir; pakette indirilecek
  KDV'ye girmez.
- Kategoriler silinmez, kapatılır; ilk kullanımda operatörün dilinde yazılır.
- Fiş dosyası `erp_expense_keep_receipt()` ile (form ve API ortak kapı); türü
  baytlardan oku (`erp_expense_receipt_type()`). Değiştirme yalnız istenince
  (`$replace`) ve `erp_expense_receipt_refusal()` boşken (iptal değil, gider
  tarihi kilitte değil): `erp_archive_store(…, $again = true)` yeni satırı
  eskisinin yanına yazar, belgenin dosyası en yeni satırdır, eskisi silinmez
  (`erp_archive_files()` geçmişi verir). Dosyası olan belgeyi okuyan her yer
  `erp_archive_file()` ile en yeniyi alır; `files` tablosuna doğrudan JOIN
  yazıyorsan `MAX(id)` ile tek satıra indir.
- API (`/erp/expenses…`, `includes/erp/api.php` + `api_resources.php`): para
  hareketi (ödeme, ödenmiş iptal, ödemeli kayıt) `erp_api_expense_cash_gate()`
  ister — anahtarda `erp_cash:write` ve sahibinde kasa hakkı. Olaylar
  `erp.expense.created|paid|cancelled`, `erp_event_expense()` ile yazanın
  işlemi içinde; yeni bir yazma yolu açarsan duyurusunu da koy.
- Tarih kuralı: gider tarihi ve ödeme tarihi bugünden ileri olamaz; ödeme
  gider tarihinden önce olamaz. Ödeme kasası giderin para biriminde olmalı
  (ekran kasaları buna göre süzer).
- Tekrar (4.96) `includes/erp/expense_recurring.php`: gider her zaman
  `erp_expense_save()` ile yazılır (kilit, KDV, kasa aynı kapıdan geçer).
  Tarih `UPDATE … WHERE next_date = <tarih>` ile sahiplenilir (iki koşu aynı
  ayı iki kez yazmaz); kilitli tarih atlanır, başka hata tarihi geri verir;
  sebep `last_error`'da. Koşu günlük iş + gider listesi ziyareti; bir koşuda
  en çok 12 tarih yetişir. Durmuş tekrar yeniden başlatılabilir (yeni satır).

## Olay kapısı, denetim izi, bildirim, e-posta (4.90, 4.91, 4.97)

- `erp_event()` (`includes/erp/events.php`) tek duyuru kapısıdır ve üç şeyi
  besler: webhook kuyruğu, denetim izi (`erp_audit_event()`) ve anlık
  bildirim (`erp_alert_event()`). Yeni bir ERP olayı eklersen `erp_event()`
  üzerinden duyur; ize ve (gerekiyorsa) bildirime kendiliğinden düşer.
  `erp_audit_event()` nesne türünü olay adının ikinci parçasından alır;
  yeni türün etiketi/tutarı için oraya ve `erp_audit_object_types()`'a ekle.
- Denetim izi (`includes/erp/audit.php`, `erp_audit_log`) hiç silinmez.
  `log_activity()` kancası çağıran dosyaya bakar: `includes/erp/` altı ya da
  adında `erp_` geçen dosya. ERP işini başka yerden (ör. `includes/local_sale.php`)
  günlüğe yazan kod ize düşmez; gerekiyorsa işi ERP dosyasına taşı ya da
  olayla duyur. İz yazımı hiçbir işi durdurmaz (hata sessiz).
- Anlık bildirim (`includes/erp/alerts.php`): `create_notification()` +
  `pg_push_enqueue_notification()`; işi yapan kişi `notification_reads`'e
  yazılarak hariç tutulur. Push yalnız `push_subscriptions`'ta cihazı kayıtlı
  (panelde bildirimlere izin vermiş) kullanıcılara kuyruğa girer; tahsilat
  bildirimi onu giren kişiye gitmez, yani tek hesapla denemede telefona bir
  şey düşmez (ikinci kullanıcı ya da sahibi olmayan saatlik stok işi gerekir).
  Yeni eylem eklersen `includes/notifications.php`'de
  hem görünürlüğe hem `pg_notification_display()`'e ekle. Stok düşüşü çok
  yerden olduğu için kanca değil saatlik iş (`erp_stock_alert_job`,
  `erp_stock_minimums.notified_at`).
- Fatura e-postası (`includes/erp/mail.php`): tek kapı `erp_event_invoice()`
  (`created` / `edoc_changed`), karar `erp_mail_auto_target()`, gönderim
  istek sonunda (`erp_mail_defer()`). `email()` doğru çalışır; dev
  makinada e-posta mekanizması yok, o yüzden e-postanın ulaşması denenmez
  (yalnız ekran, PDF eki ve adres doğrulaması). Canlıda gerçek e-posta
  Erdal'ın izni olmadan gönderilmez.
- Kredi limiti (`includes/erp/credit.php`) yalnız `erp_invoice_issue()`'da
  sorulur; siparişin faturası hiç durdurulmaz.

## Teklif, tekrarlayan fatura, cari fiyatı, sayım, çek, ekstre (4.92–4.102)

- Şema etiketleri: 2026.4.4 (son ERP adımı 4.103 muhasebe kuralları) ve
  2026.4.5, 2026.4.6 ve 2026.4.7 yayında ve kapalı. Yeni ERP adımları açık
  sürüm **2026.4.8**'e girer (`2026.4.8.php`, `upgrade_2026_4_8_erp_<konu>()`),
  etiketler 2026.4.7'deki yapıyla **8.58–8.69**, dolunca 8.90'dan sonrası.
  Etiket yalnız addır, çalışma sırası `2026.4.8.php`'deki çağrı listesidir
  (yeni adımı listenin sonuna ekle).
- Teklif (`includes/erp/quotes.php`, `erp_quotes`) `erp_invoices`'a yazılmaz:
  fatura sorgularının bir kısmı `doc_type`'a bakmıyor, teklif oraya girerse
  bakiyeye, KDV'ye ve raporlara sızar. Numara `erp_next_number($seri,
  'proforma', $yıl)`, seri `ERP_QUOTE_SERIES` ya da fatura serisi + `T`.
  Basım fatura şablonuyla `erp_invoice_document_data($id, $source)`;
  faturaya dönüşüm yalnız `erp_invoice_draft_save()` ile (taslak, bugünün
  tarihi). Durum ekranda `erp_quote_state()` ile hesaplanır (süresi doldu).
- Tekrarlayan fatura (`includes/erp/invoice_recurring.php`): yalnız ERP'de
  yazılmış (`order_id = 0`) kesilmiş satış faturası. Koşu gider tekrarıyla
  aynı: tarih koşullu `UPDATE` ile sahiplenilir, günlük iş + fatura listesi
  ziyareti; kesme modunda sonrası (e-belge, e-posta) mağaza ayarına kalır,
  denemede otomatik gönderimin kapalı olduğunu önce kontrol et.
- Cari fiyatı (`includes/erp/price_lists.php`): `erp_product_for_line()`
  sonucu `account_terms` taşır; editör yalnız satış yönünde uygular ve
  kampanya iskontosunu üstüne eklemez. Siparişin fiyatına dokunmaz.
- Stok sayımı (`includes/erp/stock_counts.php`): uygulama tek işlemde
  `products.inventory_quantity` + `out_of_stock` yazar, `erp_stock_moves`'a
  dokunmaz (düzeltmenin maliyeti yok), önceki stok `system_before`'da kalır.
- Çek/senet (`includes/erp/cheques.php`): para yalnız `erp_post_receipt()` /
  `erp_post_transfer()` ile; her adım yeni hareket, önceki hareket yeniden
  yazılmaz (karşılıksız = cariye yeniden borç). Portföy ve verilen çek
  kasaları kullanıcının açtığı ERP kasalarıdır; yoksa ekran söyler.
- Banka ekstresi (`includes/erp/bank_import.php`): yalnız CSV. Satır
  `UNIQUE (cash_account_id, line_hash)` ile bir kez alınır; eşleşme defterde
  aynı kasa + tutar + ±3 gün; kayıt tahsilat/ödeme ya da gider kapısından.
  Sütun seçimi kasaya göre hatırlanır.
- Çeviri tuzakları: `'Quote'` "Alıntı", `'By'` "Şuna Göre", `'From'`/`'To'`
  "Şundan"/"Şuna", `'Count'` "Adet", `'Record'` "Kayıt", `'Due'` "Bitiş"
  demek; ERP metninde yeni anahtar kullan (`'Sales quote'`, `'Sent by'`,
  `'Start date'`, `'Save'`, `'Overdue'`…).

## Kâr/zarar

- `includes/erp/profit.php` — `erp_profit_report()`: satır matrahı (satır −
  indirim), iptal ve taslak hariç; mal maliyeti stok hareketinden; ürünsüz alış
  satırı masraf; giderler maliyet tutarıyla. Yeni bir maliyet ya da gelir
  kaynağı eklersen bu fonksiyona ve Excel'ine (`erp_profit_workbook()`) ekle.
- Faturası kesilmemiş siparişler isteğe bağlı gelir satırıdır
  (`$options['orders']`, çerez `pg_erp_profit_orders`,
  `erp_profit_orders_choice()`); gösterge aynı seçimi kullanır. Faturası
  kesilen sipariş iki kez sayılmaz.
- Eksi marjı `erp_profit_margin_text()` ile yaz (`erp_percent_text()` eksi
  sayıda işareti yüzde işaretinin arkasına koyar).

## Tahsilat, tahsis, bakiye

- Fatura kapatmak için ikinci defter kaydı atma: `erp_settlements` yalnız hangi
  hareketin hangi faturayı kapattığını tutar — aksi hâlde aynı para iki kez
  sayılır.
- `paid_total` ve `status` bu satırlardan **türetilir**
  (`erp_invoice_refresh_paid()`), artırılmaz.
- `UNIQUE(invoice_id, account_txn_id)` + `ON DUPLICATE KEY UPDATE`: aynı çifti
  tekrar yazmak tutarı düzeltir, eklemez.
- Otomatik eşleştirme yazma; FIFO yalnız öneridir (`erp_settlement_suggest()`).
- Tek otomatik tahsis hediye kartıdır (`erp_settle_gift_card()`, kasa hareketi
  yok); geri alım ters kayıtla.
- Tahsilat iptalini ters kayıtla yap (`doc_type='cancel'`, orijinal
  `amount_base`, bugün tarihli).
- Transaction derinliğini `erp_tx_begin/commit/rollback` sayacıyla yönet.
- `erp_accounts.balance` / `erp_tills.balance` önbelleğini rapor kaynağı yapma;
  mutabakat ve kasa akışı defterden toplar.

## Vade, yaşlandırma, hatırlatma

- Tek kaynak `includes/erp/aging.php`; yeni vade görünümü kendi sorgusunu yazmaz.
  Nokta-zaman kuralı: `as_of` sonrası tahsis/iade düşülmez.
- Vade tarihini `erp_account_due_date($account_id,$issue_date)` ile bir kez,
  belge yazılırken hesapla (cari > mağaza > fatura tarihi); yazılmış tarihi ezme.
- Gecikme bildiriminde yeni kanal yazma: panel `create_notification`, cihaz
  `pg_push_enqueue_notification()`, e-posta `email()`.
- Duyurulan belgeyi damgala (`overdue_notified_at`,
  `overdue_second_notified_at`) ve bir daha listeleme.

## Cari kart

- Cari `contacts`'a bağlanır (`erp_accounts.contact_id`); `customers` tablosu
  yoktur. Bağın gerçeği `erp_accounts.contact_id`, `contacts.erp_account_id`
  aynadır; yazmayı iki tarafa `erp_account_link_contact()` yapar.
- `erp_account_save()` bir sütunu yalnız `array_key_exists` ise yazar —
  "gelmediyse 0" yazmak, o alanı göndermeyen her formu sıfırlayıcı yapar.
- ERP dünyanın her yerinde kullanılır: bir ülkenin vergi kuralı (VKN/TCKN hanesi
  ve kontrol hanesi, 11111111111, beş haneli posta kodu, kişide ad + soyad) yalnız
  o ülkenin carisine uygulanır — ülke `erp_account_country()` (carinin, yoksa
  mağazanın). Vergi numarası her kapıda (form, tezgâh, CSV, API, irsaliye)
  `erp_tax_number_check($no, $ülke)` ile denetlenir: TR'de rakam + kontrol hanesi,
  başka ülkede harf/rakam/ayraç ve `erp_tax_number_width()` (tablodan; 4.70'ten
  beri 32). Ekranda ve belgede adı `erp_tax_id_label($ülke)`; PDF'de satıcı
  `label.tax_id` (mağaza), alıcı `label.account_tax_id` (carinin ülkesi).
  `LENGTH(tax_number) IN (10, 11)` gibi TR'ye özgü SQL süzgeci yazma; eşlemeyi
  harf+rakam anahtarıyla yap (`erp_edoc_account_tax_key()`).
- Adres (4.71): `city`, `district`, `state`. Ödeme formu/kişi kartı adresini
  `erp_address_from_checkout($şehir, $eyalet, $ülke)` ile oku (TR'de eyalet
  alanı il, şehir alanı ilçe; başka ülkede olduğu gibi) — `business_state`'i
  doğrudan `city`'ye yazma. Basılı yer satırı `erp_address_locality()` /
  `erp_seller_locality()`; şablonlarda `{{*.locality}}`. TR adresinde `state`
  boş kaydedilir: yeni bir giriş kapısı (form, CSV, API) eyaleti boş şehre
  taşır, saklamaz (`erp_api_account_state_rule()`, içe aktarmadaki kural).
- Türkiye'ye ya da e-Belge'ye özgü bir öğe (tevkifat, internet satışı, GİB
  alanları, e-Belge sütunu/adımı) `erp_turkish_features()` (mağaza TR ya da
  e-Belge kullanımda) veya `erp_edoc_in_use()` (sağlayıcı etkin ya da geçmişte
  gönderilmiş) ile kapılanır.
- Kesimde cari bilgilerini faturaya kopyala
  (`erp_invoice_snapshot_account()`); okuma önce kopyayı, boşsa canlı kartı
  okur. Join alias'ları `live_*`'dır.
- Siparişten cari açarken siparişi de ver:
  `erp_account_for_contact($kişi, $kullanıcı, $order)`. Kişi kartı boşsa
  (ödemede "bilgilerimi güncelle" kaldırılmış) ad, adres, e-posta, telefon
  siparişin fatura bilgisinden dolar (`erp_account_data_with_order()`); yoksa
  cari "#<kişi no>" adıyla adressiz açılır. Var olan cariye dokunulmaz.

## Belge, PDF, şablon

- Belge üretimi `includes/erp/document.php` üzerinden; şablon
  `config.erp_<belge>_template` NULL ise yerleşik dosya.
- Yerleşik şablona literal metin yazma: her etiket `{{label.*}}` ile
  `lang()`'dan gelir.
- Şablon CSS'inde `text-transform: uppercase` kullanma — dompdf noktasız/noktalı
  I'yı bozar.
- Sunucu tarafı PDF yalnız ERP faturası/irsaliye/mutabakat içindir (dompdf);
  `order_invoice_print.php` ve imza makbuzu print-CSS kalır.
- Şablon önizleme uçlarında POST `template` alanını yalnız
  `validate_erp_access($user,'settings')` + CSRF ile kabul et.

## e-Belge

- Yalnız sağlayıcının doldurabileceği bir ekranın menü bağlantısı
  `pg_erp_provider_offers()` ile kapılanır (yeni ekran = oraya bir kol); sağlayıcı
  seçilmemiş mağazada ERP'nin geri kalanı eksiksiz çalışmalı.
- Sağlayıcı adını modülün geri kalanına sızdırma: `erp_edoc_supports()` /
  `erp_edoc_call()` ile sor; yeni sağlayıcı = bir dosya + `erp_edoc_drivers()`'a
  bir satır. Sağlayıcıya özel kolon açma (`settings` JSON veya
  `edoc_external_id`).
- Aynı anda tek aktif sağlayıcı; geçmiş faturanın soruları `edoc_provider`'a
  gider.
- Sürücü kendi curl'ünü yazmaz: `erp_edoc_http()` (TLS doğrulaması açık) +
  `erp_edoc_log()` (maskeli).
- Uç adresi ve alan adı tahmin etme; belgeden yaz, yoksa "Doğrulanmadı"ya geçir.
- Kimlik numarası olmayan alıcıya e-Arşiv'de `erp_edoc_final_consumer_tckn()`
  gider; bu numarayı carinin kartına yazma ve VKN'siz firma kartını durdur.
- Ayrıntı ve sağlayıcıya özgü davranış: `pinegrap-dis-api`.
- Sağlayıcının reddedeceği her kuralı göndermeden önce `party_missing` /
  `document_missing` listesine yaz; biçim kuralları (VKN/TCKN kontrol hanesi,
  TR posta kodu beş hane, kişide ve TCKN'li taşıyıcıda ad + soyad) registry'deki
  `erp_edoc_tax_number_valid()`, `erp_edoc_postcode_valid()`,
  `erp_edoc_person_name()` ile. Etiket nedeni söylesin; aynı liste düzeltme
  formunu ve "e-Belgeye hazır değil" süzgecini besler.
- Siparişten belge zinciri `includes/erp/order_flow.php`'dedir; yeni adım
  kendi fonksiyonunu çağırır, zincir yalnız ekranın sunduğu adımı koşturur.

- İptal/iadede sağlayıcıya özgü karar yazma: ekranlar
  `erp_edoc_invoice_cancellable()` / `erp_edoc_invoice_provider_cancel()` ve
  `erp_edoc_has_capability('return')` sorar; kural sürücüde
  (`erp_edoc_<kod>_cancellable`, `_cancel_invoice`). Yerel iptal, sağlayıcı
  kabul etmeden yapılmaz.
- İşbaşı kodları panelden doğrulandı (`docs/_isbasi_api_notlari.md`):
  `eArchivePaymentType` 0–4, `eGovernmentType` 0/1/2/4/6/…; silme yolu
  `DELETE /api/v1.0/invoices?Ids=` (`/deleteInvoice` değil).
- Gelen e-faturalar (4.67): sağlayıcıya yalnız `erp_edoc_call('inbox' | 'inbox_document')`
  ile gidilir; kayıt `erp_edoc_inbox`, iş `includes/erp/edoc/inbox.php`, UBL okuma
  `includes/erp/edoc/ubl.php` (sağlayıcıdan bağımsız, zip bellekte açılır). Okuma
  mağazanın kararını (`status`, `invoice_id`) ezmez. ERP'ye alma yalnız **taslak**
  alış faturası yazar (`erp_invoice_draft_save()` + `gib_uuid`/`gib_number`/
  `invoice_type`); ETTN'yi olduğu gibi sakla (İşbaşı büyük/küçük harfe duyarlı).
  ERP'nin tutamadığını (KDV dışı vergi, belge geneli indirim/tevkifat) reddet,
  satır satır farkı önceden göster.
- Satışa özgü e-Belge kuralları (tarih sırası, "Tarihi bugüne al", gönderim kartı)
  alış faturasına uygulanmaz; yeni kural yazarken `direction = 'sales'` kapısını koy.
- Cari eşitleme (4.69): sağlayıcının kartlarına yalnız `erp_edoc_call('accounts' |
  'account' | 'account_save')` ile gidilir; kayıt ve bağ `erp_edoc_accounts`'ta
  (`erp_accounts`'a sağlayıcı kolonu açma), iş `includes/erp/edoc/account_sync.php`.
  Okuma mağazanın kararını (`status`, `account_id`, `no_auto_link`) ezmez; elle
  çözülen bağ kendiliğinden yeniden kurulmaz. **Bakiye hiçbir yönde aktarılmaz.**
  Sağlayıcıdaki kartı değiştiren sürücü önce kartı okur, yalnız verilen alanları
  değiştirip gerisini aynen yazar (İşbaşı `PUT firms` bakiye alanlarını zorunlu
  ister). ERP carisini güncellerken `erp_account_save()`'e **tüm satırı** ver
  (`erp_edoc_account_save_data()`): fonksiyon güncellemede bildiği her kolonu
  yazar. Sıcak satış carisi ve 11111111111 kartla bağlanmaz, faturada kod almaz.

## Saklanan belgeler (4.65)

- Kesilen fatura/irsaliye PDF'i, kabul edilmiş e-Belgenin sağlayıcı kopyası ve
  e-postayla giden mutabakat mektubu `includes/erp/archive.php` ile
  `FILE_DIRECTORY_PATH`'e ve `files` tablosuna yazılır: `folder = 0`,
  `erp_doc_type` / `erp_doc_id` dolu, ad `erp-<tür>-<no>-<rastgele>.pdf`.
  Yeni klasör açma; kendi yazma yolunu yazma.
- Saklama olay noktasında `erp_archive_defer()` ile istenir, render istek
  sonunda yapılır (olay transaction içinde atılır). Uçlar önce
  `erp_archive_file()`'a bakar; canlı render yalnız önizleme ve `?html=1`.
- `get_file.php` `erp-` adlı ve `erp_doc_type`'ı dolu dosyayı yalnız ERP
  hakkına verir, başkasına 404; klasörsüz dosya aksi hâlde herkese açıktır.
  Dosya listeleyen yeni sorgu ERP satırlarını `erp_doc_type = ''` ile dışarıda
  bırakır; id ile çalışan dosya işlemi (sil/taşı/yeniden adlandır) onları reddeder.
- Dosya Yöneticisi klasörleri `includes/erp/files.php`'de (`erp_files_folders()`,
  kapsam, sayım, liste, öğe); `view_folders.php` yalnız çizer (`erp_kind`'e göre
  etiket, simge, "… git", önizleme). Yeni klasör/tür eklerken ikisini birlikte
  güncelle; resim türü öğede `picture: true`, `is_image` false kalır.
- `is_writable()` ile dosya klasörünü sorma (Windows'ta yanlış yanıt verir);
  yazmanın sonucuna bak.

## Ekran deseni (ERP'ye özel)

- Genel desen `pinegrap-ui-deseni`'nde. ERP'ye özel eklemeler:
  - Sırası anlam taşıyan listeleri (ekstre, kasa defteri, fatura kalemleri)
    DataTable yapma. Sütun başlığıyla sıralama için `table[data-pg-sort]`
    (`backend.src.js`): tarihi `Ymd`, tutarı kuruş olarak `data-sort`'a yaz;
    açılış/toplam satırı `data-pg-sort-fixed` (`="top"` üstte), alt satırı
    üstündekiyle taşınan `data-pg-sort-with-prev`, sıralanmayan başlık
    `data-pg-sort="none"`. Form ızgaralarına koyma.
  - `assets/js/erp_invoice_editor.js` düz `<script src>` ile servis edilir,
    `.min` ikizi yoktur. JS toplamları PHP'yi birebir taklit eder ve PHP her
    kayıtta yeniden hesaplar — uyuşmazlık hatadır. Cari değişince yalnız
    `data-erp-zone-rate="1"` satırların oranı yenilenir; operatörün yazdığı
    oran bu işareti düşürür.
  - Tarayıcıdan denerken seçim kutusuna `jQuery(...).trigger('change')`
    yetmez (yerel dinleyiciler çalışmaz); `dispatchEvent(new Event('change'))`.
  - Yeni çeviri anahtarı yazmadan önce `tr.json`'daki karşılığa bak
    (`Net` → "KDV Hariç" gibi tuzaklar var).

## Dosya haritası

| İş | Dosya |
|---|---|
| Para / yuvarlama | `includes/erp/money.php` |
| Kur, döviz kapısı | `includes/erp/fx.php` |
| Cari kart, vade, snapshot | `includes/erp/accounts.php` |
| Tahsis, `refresh_paid`, hediye kartı | `includes/erp/settlement.php` |
| İade / iptal | `includes/erp/returns.php` |
| Sipariş kartı, iptal/iade adımları | `includes/erp/order_flow.php` |
| Kalem/adetle kart iadesi | `includes/erp/order_refund.php` |
| Elle fatura mantığı | `includes/erp/invoice_manual.php` |
| Fatura formu + POST okuma | `includes/erp/invoice_form.php` |
| Satır editörü JS | `assets/js/erp_invoice_editor.js` |
| Katalog / kampanya / barkod | `includes/erp/products.php` |
| Stok hareketi, maliyet | `includes/erp/stock.php`, `erp_stock.php` |
| Muhasebeci paketi, bağlantı, aylık iş | `includes/erp/accountant.php`, `erp_accountant*.php` |
| Dönem kilidi | `includes/erp/lock.php` |
| Giderler | `includes/erp/expenses.php`, `erp_expenses.php`, `add_erp_expense.php`, `edit_erp_expense.php`, `erp_expense_categories.php` |
| Tekrarlayan giderler | `includes/erp/expense_recurring.php`, `erp_expense_recurring_job.php` |
| Kâr/zarar | `includes/erp/profit.php`, `erp_profit.php` |
| KDV raporu | `includes/erp/vat_report.php`, `erp_vat_report.php` |
| Muhasebe kuralları | `includes/erp/rules.php`, `erp_settings.php` (kart) |
| Fatura e-postası | `includes/erp/mail.php` |
| Kredi limiti | `includes/erp/credit.php` |
| Minimum stok | `includes/erp/stock_levels.php`, `erp_stock_minimums.php` |
| Denetim izi | `includes/erp/audit.php`, `erp_audit.php` |
| Anlık bildirim | `includes/erp/alerts.php`, `erp_stock_alert_job.php` |
| Teklifler | `includes/erp/quotes.php`, `erp_quotes.php`, `add_erp_quote.php`, `edit_erp_quote.php`, `get_erp_quote_pdf.php` |
| Tekrarlayan faturalar | `includes/erp/invoice_recurring.php`, `erp_invoice_recurrences.php`, `erp_invoice_recurring_job.php` |
| Cari fiyatları | `includes/erp/price_lists.php`, `erp_account_prices.php` |
| Stok sayımı | `includes/erp/stock_counts.php`, `erp_stock_counts.php`, `erp_stock_count.php` |
| Çek ve senetler | `includes/erp/cheques.php`, `erp_cheques.php`, `erp_cheque.php` |
| Banka ekstresi | `includes/erp/bank_import.php`, `erp_bank_statements.php`, `erp_bank_statement.php` |
| Tezgâh satışı (ERP tarafı) | `includes/local_sale.php` (karışık ödeme: `local_sale_split_parts()`) |
| Vade & yaşlandırma | `includes/erp/aging.php` |
| Gecikme bildirimi | `includes/erp/notify.php` |
| Şablon + PDF | `includes/erp/document.php`, `includes/erp/templates/*.html` |
| İrsaliye | `includes/erp/waybills.php` |
| Mutabakat | `includes/erp/reconciliation.php` |
| Kasa akışı | `includes/erp/cashflow.php` |
| CSV içe/dışa | `includes/erp/import.php`, `export.php` |
| API dikişi / olaylar | `includes/erp/api.php`, `events.php` |
| e-Belge | `includes/erp/edoc/registry.php`, `service.php`, `<kod>.php` |
| Gelen e-faturalar, UBL okuma | `includes/erp/edoc/inbox.php`, `ubl.php`, `erp_inbox*.php` |
| Cari eşitleme (sağlayıcı kartları) | `includes/erp/edoc/account_sync.php`, `erp_account_sync*.php` |
| Ekranlar | `erp_*.php`, `add_erp_*.php`, `edit_erp_*.php`, `get_erp_*.php` |

## Bugün bilerek yapılmayanlar

Otomatik tahsis eşleştirme yok (FIFO yalnız öneri) · e-İrsaliye yok
(`erp_waybills` dahili irsaliyedir) · Paraşüt sürücüsü boş · çevrimdışı ödeme
akışı türetilmiş durumdur, yeni enum değeri değil · kâr/zarar resmî gelir tablosu değildir (amortisman,
dönemsellik yok) · siparişi birden çok faturaya bölme yok · faturaya ödeme
linki ve müşterinin "Faturalarım" sayfası yok · iyzico ve pazaryeri hakedişi yok
· banka ekstresi yalnız CSV, bankaya API bağlantısı yok.
