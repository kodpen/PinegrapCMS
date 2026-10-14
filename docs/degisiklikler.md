# Pinegrap — Değişiklik Ayrıntıları

Açık sürümün teknik ayrıntısı ve gerekçesi, yeniden eskiye; kısa özet `pinegrap/changelog.txt`.
Yayınlanmış sürümler `docs/changelog-archive/<sürüm>.md` dosyalarındadır (2026.2.4 öncesi: `install/index.php` içindeki `upgrade_to_*`).
Bu dosya okunmaz: yeni bölüm aşağıdaki ilk `---` çizgisinin hemen altına eklenir.

---

## 2026.4.10 — changelog.txt İngilizce ve yalnız büyük özellikler; yeni başlık biçimi (2026-10-14)

- **Ne değişti:** `pinegrap/changelog.txt` baştan İngilizce yazıldı. Her sürüm artık bir `=` çizgisi, altında sürüm numarası ve tek satırlık girdilerden oluşuyor: plan ile gelen büyük özellikler kısa `[NEW]` satırları, geri kalan her şey sürüm başına tek `[FIX] Miscellaneous bug fixes and new features.`, `[SECURITY] Implemented various security fixes.` ve tek kısa `[SCHEME]` satırı. Konu başlıkları, NOT satırları ve `[HIZ]` etiketi kalktı; dosya ~10.900 satırdan ~275 satıra indi. Eski Türkçe metin git geçmişinde; geliştirici ayrıntısı `docs/degisiklikler-arsiv/` ve bu dosyada.
- **Neden:** dosya müşteriye gidiyor ve panelde "Sürüm Notları" olarak okunuyor; 2026.4.4 ve 2026.4.10'da 380–395 madde vardı, site sahibi için okunmuyordu. Ürün sahibi bu biçimi daha önce de istemişti, zamanla unutuldu; kural artık CLAUDE.md'de (kural 9) ve `docs/memory/release/upgrade-changelog-rules.md`'de.
- **Ayrıştırıcılar:** `pg_changelog_sections()` / `pg_changelog_preamble()` (includes/fn/system_status.php) ve install/index.php'deki kopya sürüm başlığını yalnız "numara, altında çizgi" olarak tanıyordu; yeni biçimde çizgi numaranın üstünde. İkisi de artık iki biçimi de tanıyor (PCRE branch reset `(?|…)`, sürüm hep grup 1'de), "2026.2 / 2026.2.1" gibi ortak başlıkları da sürüm sayıyor (önceden bu bölümlerin maddeleri bir üstteki sürüme yapışıyordu) ve çizgi satırı üstteki girdiyi kapatıyor (önceden dosya sonundaki not son girdinin metnine ekleniyordu). `SCHEME` etiketi `ŞEMA`/`SCHEMA` gibi sarı rozet alıyor ve yükseltme ekranındaki şema notu olarak okunuyor. Eski biçimli dosyada eski ve yeni ayrıştırıcının çıktısı yalnız bu iki düzeltmede ayrılıyor (karşılaştırıldı).
- **Denetim:** `tests/changelog_test.php` iki başlık biçimini, ortak başlığı, girişin kesildiği yeri ve gönderilen dosyanın biçimini (yalnız NEW/FIX/SECURITY/SCHEME, FIX/SECURITY/SCHEME sürüm başına en çok bir kez, açıklama satırında dört etiket) denetliyor; eski ayrıştırıcıyla kırmızı.

## 2026.4.10 — Sepet sayfası hızlı ekleme kapalıyken boş form çizmiyor; sepet ve hızlı sipariş açılışta ve hata sonrası uyarı yazmıyor (2026-10-14)

**Neden.** #447'nin kalanları. Sandbox'ta eski kodla yeniden üretildi;
hepsi 2026.4.9'da da var.

- Sepet sayfasının örnek düzenleri (`data/layouts/248.php`, teklif sepeti
  `497.php`) her açılışta 138–203'te beş uyarı yazıyordu (`label`,
  `recipient`, `quantity`, `amount`, `available_products`) ve hızlı ekleme
  kapalıyken içinde boş bir "Ürün" seçim kutusu olan işlevsiz bir hızlı
  ekleme formu çiziyordu. Neden `get_shopping_cart.php`: 8f062db9
  (`$quick_add['system'] .=` satırının "tanımsız anahtar" uyarısı için)
  diziyi `$quick_add['system'] = ''` ile başlattı; dizi hızlı ekleme
  kapalıyken de dolu olunca düzenin `if ($quick_add):` kapısı açık kaldı.
  Panelden üretilmemiş özel düzen (`includes/templates/shopping_cart.php`)
  aynı kapıyı kullanıyor.
- Ürün formlu bir kalem sepetteyken sepet düzenleri 611/615'te, hızlı
  sipariş düzeni (256) 573'te her alan ve adet için `titles` uyarısı
  yazıyordu. #440'ın "256 hata sonrası çizimde uyarı" kalanı buydu: hata
  sonrası sayfa yeniden çizilirken sepette ürün formlu kalem vardı. #447
  formsuz sepetle denediği için üretemedi. Başlık yalnız referans kodu
  alanı bir başvuruyla eşleşince dolduruluyordu.
- Çok alıcılı sitede hızlı ekleme açıkken (ve sepetin, hızlı siparişin
  alıcı listelerinde) oturumda alıcı yokken `$_SESSION['ecommerce']['recipients']`
  uyarısı.
- Hızlı siparişin hata yolunda: kargo adresi geçersiz, boş ya da ülkesi
  hiçbir kargo bölgesinde değilken `shipping.php` 1571 (`arrival_date_id`,
  zorunlu yöntem denetimi tarih göndermiyor) ve 2247 (hata yanıtında
  `shipping_methods` yok); bilinmeyen hediye çeki kodunda
  `express_order.php` 654 ve `order_preview.php` 104 (`db_item()` satır
  yokken `null`); adet kutusu gönderilmeyen formda `express_order.php` 410.

**Ne değişti.**

- `get_shopping_cart.php`: `$quick_add` yalnız hızlı ekleme ürün grubu
  açık ve ürünlüyken dolduruluyor; `system` anahtarı o blokta `=` ile
  atanıyor. Hızlı ekleme kapalıyken (grup seçilmemiş, kapalı ya da ürünsüz)
  düzenlere boş dizi gidiyor. **Görünen değişiklik:** bu durumda sepet
  sayfasındaki boş hızlı ekleme formu (etiketi "Ürün", seçeneksiz bir
  seçim kutusu, düğmesiz) kalktı; 8f062db9 öncesindeki davranış buydu.
  Hızlı ekleme açıkken HTML aynı.
- `get_shopping_cart.php` ve `get_express_order.php` ürün formunun her
  alanına, her adet için boş başlık (`titles[<adet>] = ['title' => '',
  'title_label' => '']`) veriyor; referans kodu eşleşirse eskisi gibi
  dolduruluyor. Alıcı listesi okumaları `!empty()` ile.
- `shipping.php`: `arrival_date_id` verilmezse 0; zorunlu yöntem denetimi
  hata yanıtını boş liste sayıyor (eskisi de `null` ile aynı sonuca
  varıyordu).
- `express_order.php` / `order_preview.php`: bulunmayan hediye çeki
  `array('id' => '')`; `express_order.php` adet kutusu gönderilmeyen
  kalemi olduğu gibi bırakıyor (eskisi de uyarıdan sonra dokunmuyordu).

Uyarı susturulmadı. Şema, dil anahtarı, JS yok. Test:
`tests/cart_layout_warnings_test.php` (eski kodda üçü de başarısız).

**`cart_action.php`'nin #447'de yalnız kaynakta değişen kolları.**
Sandbox'ta #447 öncesi kodla (66f53d9e, üçüncü port) karşılaştırıldı,
yönetici oturumu ve `define('ECOMMERCE_SAVE_FOR_LATER', true)` ile. 16
istek: çevrimdışı ödeme işareti (1, yok, 0, özellik kapalı, oturumsuz
ziyaretçi), "sonra al" / "sepete geri al", başka siparişin kalemi, kalemsiz
istek, sonra al + ödemeye geç, hepsi saklıyken ödemeye geç. Yönlendirme ve
`orders` / `order_items` satırları iki kodda aynı, iki kodda da uyarı yok.
Tek fark #447'nin bilerek getirdiği: `offline_payment_allowed[]=1` eskide
işareti açıyordu, yenide "verilmedi" (kutu boş) sayılıp kapatıyor. Bu PR
`cart_action.php`'ye dokunmadı.

**Bilerek yapılmayan.** Sistem düzenli (`layout_type = 'system'`) sepet ve
hızlı sipariş sayfalarının eski uyarıları (kurulumla gelen sayfalar özel
düzenli): `get_shopping_cart.php` 574/576 (`$output_ship_to_and_add_name_rows`,
`$output_amount_row`), 632–633; `get_express_order.php` 1075–1095,
2640–2740 (`$contact`), 1669/1672, 2314, 2854, 3277, 3712. Sepet
düzenlerinin yinelenen kalem bölümü (248.php 1363 `$field['title']`)
denenmedi. `submit_order.php` 291/3859'daki aynı hediye çeki okuması (kart
uygulandıktan sonra silinirse) dokunulmadı.

**Doğrulandı (sandbox, MariaDB, PHP 8.3).** Eski kod `origin/development`
worktree'sinden ikinci portta, aynı veritabanı ve aynı oturumla.
Sepet (248), teklif sepeti (497) ve hızlı sipariş (256) sayfaları özel ve
sistem düzende, tek ve çok alıcılı, boş ve dolu (kargolu ürün, ürün formlu
etkinlik, referans kodlu ürün, bağış) sepetle, hızlı ekleme üç durumda
(hediye sepetleri grubu, 25 ürünlü grup içinde bağış, kapalı): 56 açılış.
Hızlı ekleme açıkken ve hızlı siparişte HTML bire bir aynı; kapalıyken
(ayrıca grup kapalı, grup ürünsüz) tek fark boş formun 20 satırının
kalkması. Özel düzenli 28 açılışta eskide 258 uyarı satırı, yenide 0;
sistem düzenli 28 açılışta 160'tan 152'ye (alıcı listesi gitti, kalanlar
yukarıda). Referans kodu eşleşince başlık
("Konu: …") iki kodda da gösteriliyor. Hızlı siparişte 22 hatalı gönderim
(boş form, geçersiz/boş ülke, bölgesiz ABD eyaleti, yöntemsiz / bilinmeyen
/ dizi kargo yöntemi, geçersiz varış tarihi, boş eyalet ve posta kodu,
koşul onayı yok, bozuk kart, değiştirilmiş toplam, bilinmeyen indirim ve
hediye çeki kodu, geçerli hediye çeki, dizi / 0 / 3 adet, boş ürün formu):
yönlendirme ve kalem adetleri iki kodda aynı, eskide 286 uyarı satırı,
yenide 0, hiçbir sipariş tamamlanmadı. Sipariş önizlemede bilinmeyen ve
geçerli hediye çeki: aynı sonuç, eskide 1 uyarı, yenide 0.
`tools/test.php`, lint, dil, kopya, bağlama, API şeması ve
`build_js.php --check` geçti.

---

## 2026.4.10 — InnoDB'deki ziyaretçi tablosunun dosyası temizlikten sonra kendiliğinden küçülüyor (2026-10-14)

**Neden.** Temizlik (10.3) eski satırları siler ama InnoDB dosyası
(`visitors.ibd`) diskte küçülmez; boşalan sayfalar dosyanın içinde kalır.
Yoğun bir sitede ilk temizlikten sonra dosyanın üçte ikisi boş alan olabiliyor
ve müşteri sitelerinde disk dolu kalıyordu. Kart yalnız "Optimize et"
diyordu; kimse basmazsa yer geri gelmiyordu. Plan
`docs/_plan_ziyaretci_temizleme.md`, "Ertelenenler" 5.

**Ne değişti.**
- `includes/fn/visitor_retention.php`: `pg_visitor_shrink_run()` tabloyu
  `ALTER TABLE visitors FORCE, ALGORITHM=INPLACE, LOCK=NONE` ile çevrimiçi
  yeniden kurar. `OPTIMIZE TABLE` değil: o, çevrimiçi yapamayan sunucuda
  (FULLTEXT dizini, eski ayarlar) sessizce yazmaları bekleten kopyaya düşer,
  MariaDB'de `innodb_defragment` açıksa dosyayı küçültmeden birleştirir.
  Algoritma ve kilit açıkça yazıldığı için yapamayan sunucu ifadeyi
  reddeder, tablo olduğu gibi kalır, kart nedenini söyler.
- Genel görev (`job.php`, posta ve webhook geçişlerinden sonra) saatte bir
  bakar (`data/temp/visitor_shrink.json`). Kendiliğinden yeniden kurma
  koşulları (`pg_visitor_shrink_hold_reason()`, sırayla): tablo InnoDB;
  MyISAM → InnoDB taşıması sürmüyor; `innodb_file_per_table` kapalı değil;
  dosyadaki boş alan ≥ 128 MB ve ≥ (veri + dizin) / 2
  (`pg_visitor_shrink_worth()`, kartın eski "Optimize et" notunun eşiği);
  son bir haftada yeniden kurma olmamış (başarılı ya da başarısız); temizliğin
  birikmiş işi yok; veritabanı yerel ve boş disk ≥ 3 × (veri + dizin) + 1 GB
  (InnoDB'de boş alan veri rakamına dahil değil, taşımadaki `veri − boş`
  burada yanlış olurdu); saat, son 14 günün `visitor_stats_hourly` sayfa
  görüntülemelerine göre en sakin saat (veri yoksa 03:00; PHP saati, özetle
  aynı). Saat tutmuyorsa bakış tek küçük okumayla biter, katalog okunmaz.
- Başlamadan önce bu veritabanında 2 sn'den uzun süren bir ifade varsa
  (uzun rapor, yedek) başlamaz. Meta veri kilidi bekleme süresi 2 sn
  (`pg_db_run_table_statement()`'a isteğe bağlı `$lock_wait_seconds`,
  varsayılan 20 aynı); kilidi alamazsa vazgeçer, ertesi gün dener.
- Temizlik ve taşımayla aynı `GET_LOCK`. İsteği yarıda biten yeniden kurma
  (`running` kalan işaret) bir sonraki bakışta dosya boyutundan sonuçlanır.
- Veritabanı Motoru › Ziyaretçi kayıtları: InnoDB tabloda "Optimize et"
  yerine **"Şimdi küçült"** (onayda gereken boş disk; boyut, saat, disk ve
  haftalık bekleme atlanır, uzun ifade denetimi atlanmaz). Boş alan notu
  kendiliğinden yeniden kurmanın saatini ya da neden yapılmadığını söyler
  (disk okunamıyor / yetmiyor, ortak tablespace, temizlik sürüyor, son bir
  haftada yapıldı). Son sonuç: başarılı (30 gün) ya da durdu. MyISAM'da
  düğmeler değişmedi.
- 22 yeni dil anahtarı; kartın eski boş alan cümlesi kısaldı.

Şema ve JS değişikliği yok.

**Ölçüm (sandbox, MariaDB 10.11, 128 MB buffer pool; 1,2 milyon satır,
90 günle ~920 bin satır silindi; aynı anda dört işçi: ziyaret ekleme, sayfa
görüntüleme güncellemesi, tekilleştirme, son bir saat sayımı).**
- Yeniden kurma 2,2–2,7 sn; `visitors.ibd` 620 MB → 124 MB (üç deneme).
  İşten önce p99 1,5–3 ms; yeniden kurma sırasında p99 ~20 ms, en uzun
  407 ms (son andaki kilit ve eski dosyanın silinmesi). Yükün yazdığı
  4.821 ziyaretin hepsi yerinde, hata yok.
- Açık bir işlem tabloyu okumuş halde beklerken: ziyaret sorguları 2 sn
  bekledi, yeniden kurma "kullanımda" deyip vazgeçti (önceki 5 sn denemede
  5 sn). Uzun süren bir sorgu varken: hiç başlamadı, bekleme yok.
- FULLTEXT dizini eklenmiş tabloda: `LOCK=NONE is not supported` (1846),
  tablo değişmedi, kart "durdu" dedi.
- Genel görev: saat tutmayan turda 0,2 sn, aynı saatte ikinci tur bakmadı,
  en sakin saatte tur 2,4 sn'de yeniden kurdu, Site Günlüğü'ne satır düştü.
- **Doğrulanamayan:** MySQL 8 (dev.pinegrap.com, IIS) ve MySQL 5.7; gerçek
  diskte büyük tablo süresi. Önce/sonra boyutları `information_schema`
  istatistiğinden gelir, yaklaşıktır (motor değişiminden hemen sonra
  istatistik eski kalabilir).

---

## 2026.4.10 — ERP'nin kalan 29 formu da dizi gelen tek değerli alanı "Array" diye kaydetmiyor, 1 numaralı kaydı seçmiyor (2026-10-14)

**Neden.** #446'nın ikinci yarısı: testin bekleyenler listesindeki 29 ERP POST
ekranı. Sandbox'ta eski kodla, elle hazırlanmış istekte tek bir alan dizi
gelince (`alan[]=x`): fatura ekranında `settlement_id[]` ve tahsilat
ekranında aynısı 1 numaralı tahsisi kaldırdı, `recurrence_id[]` 1 numaralı
tekrarı durdurdu; gider ödeme `cash_account_id[]` ile 1 numaralı kasadan
ödendi; banka ekstresinde `line_id[]` 1 numaralı satırı işledi ya da kenara
aldı, `category_id[]` gideri 1 numaralı kategoriye yazdı, ekstre yükleme
`cash_account_id[]` ile 1 numaralı kasaya alındı; çekte `bank_till_id[]` ve
`account_id[]` çeki 1 numaralı kasaya yatırdı ya da 1 numaralı cariye
ciro etti; vade farkı `account_id[]` ile 1 numaralı carinin taslağını yazdı;
cari fiyatları `product_id[]` ile 1 numaralı ürünü listeye ekledi;
muhasebeci ekranı `package_id[]` ile 1 numaralı pakete bağlantı açtı;
siparişten fatura ve sipariş belge adımı 1 numaralı siparişi denedi.
Not, alıcı kartı düzeltme, e-posta, imzalayan, gider ve tahsilat iptal
nedeni, yeni gider kategorisi ve fatura şablonu "Array" olarak kaydedildi.
Ayrıca toplu e-belgede `invoice_ids[0][]` ve cari eşitlemede `card_ids[0][]`
gibi işaretli satırın kendisi dizi gelince `(int)` 1 numaralı fatura ya da
kartla çalışıyordu. Hepsi 2026.4.9'da da var.

**Ne değişti.** #446'nın kalıbı: 29 ekran okuduğu tek değerli alanları
`pg_request_single_values()`'e veriyor; dizi gelen alan verilmemiş sayılıyor,
zorunluysa ekranın kendi hatası çıkıyor ("Bir cari seçin.", "Banka hesabını
seçin.", "Bir kategori seçin.", "Sipariş gerekli", "Bu paket artık yok.")
ve bir şey yazılmıyor. Çağrı jeton denetiminden sonra; alanı jeton
denetiminden önce okuyan altı ekranda (`add_erp_late_fee_invoice`,
`erp_order_flow`, `erp_accounts`, `erp_invoices`, `erp_accounts_import`,
`erp_settings`) ve üç PDF önizlemesinde ilk okumadan önce.
`edit_erp_purchase_order` ve `edit_erp_quote` fatura formunun listesini
(`erp_invoice_form_single_fields()`) kullanıyor; `add_erp_return` satır başı
miktar alanlarını (`qty_<satır>`) iade edilebilir satırlardan kuruyor.
`erp_invoices` toplu e-belge ve `erp_account_sync` toplu işlem işaretli
satırın dizi olanını atlıyor (`is_scalar()`).

`tests/erp_request_values_test.php`: bekleyenler listesi kalktı, test bütün
ERP ekranlarını denetliyor (eski kodda 29 ekranın hepsi düşüyor); liveform'da
önekle okunan alanın (`get_field_value('qty_' . …)`) öneki çağrıda olmalı;
yeni test `$_POST`'tan gelen işaretli satır listesinde `is_scalar()`'sız
`(int)` bırakmıyor (eski kodda iki ekran). Gerçek listeler (`lines`,
`choice`, `invoice_ids`, `map`, `categories`, `allocate`, `counted`)
çağrıya verilmiyor. Şema, dil dosyası ve JS değişikliği yok.

**Doğrulandı (sandbox, MariaDB, PHP 8.3, DEBUG açık).** Eski kod
(`origin/development`) 8001'de, yeni 8000'de, aynı veritabanı; her istekten
önce değişen tablolar anlık görüntüye dönüyor, yazılanlar tablo tablo
`mysqldump` farkıyla okunuyor. Sahte İşbaşı ayarı (boş kimlik bilgisi),
sağlayıcıya istek gitmedi; e-posta sandbox'ta gönderilemiyor. 29 ekranda 60 senaryo,
663 istek. 60 normal istek iki kodda aynı durum, yönlendirme, mesaj ve
yazılan satırlar (rastgele dosya adları ve indirme jetonu dışında; işaretli
satırı dizi gönderen iki senaryo bilerek farklı). Her alan dizi verilerek:
eski kodda 181 isteğin 113'ü uyarı yazdı, 22'si "Array" içeren satır kaydetti, 8'i
1 numaralı kaydı değiştirdi; yeni kodda uyarı 0, "Array" 0, 1 numaralı
kayıt 0, 500 yok ve her dizi isteği alanın hiç gönderilmediği istekle aynı
sonucu verdi. `tools/lint.php`, `tools/test.php`, `check_lang`,
`check_bindings`, `check_api_schema`, `check_copies` ve `build_js.php
--check` geçti. Doğrulanamadı: dev.pinegrap.com'daki gerçek veriler, gerçek
e-belge hesabı ve e-posta gönderimi.

**Bilerek yapılmayan.** Listelerin içindeki diziler: satırlar
(`lines[0][description][]`), eşlemenin `choice[x][]`'i, gider
kategorilerinin `categories[x][name][]`'i, içe aktarmanın `map[x][]`'i,
tahsisin `allocate[x][]`'i ve sayımın `counted[x][]`'i. E-belge ayarlarının
Ayarlar ekranındaki kaydı (`includes/settings/commerce.save.php`). Bunlar
devir notunda kalanlar olarak listeli.

---

## 2026.4.10 — Tasarım dosyası ekranı yalnız tasarım dosyasını açıyor; diskte olmayan dosya düzenleyicide uyarısız (2026-10-14)

**Neden.** #449'un kalanları. Tasarım Dosyasını Düzenle (`edit_design_file.php`)
kimlik kapısı satırın varlığını soruyordu, tasarım dosyası olup olmadığını
sormuyordu: Dosya Yöneticisi'ndeki bir dosya elle yazılan adresle açılıp
yeniden adlandırılabiliyor ya da silinebiliyordu. Kaydetme yolu kaydı
gönderilen `id` ile ikinci kez okuyordu. Kayıtlı ama diskte bulunmayan bir
dosya açılınca CSS, JavaScript ve metin düzenleyicileri `file_get_contents()`,
Kaydet `unlink()`, Tasarım Dosyasını Düzenle Kaydet `rename()` uyarısı
yazıyordu. Türkçe varsayılan şablonda `files` satırı `theme__noise.css`,
şablondaki dosya `theme_ noise.css` (boşluklu) idi: Türkçe kurulumda "noise"
temasının diskte dosyası yoktu (2026.4.9'da da).

**Ne değişti.** `edit_design_file.php` kapısı satırı `design = '1'` ile
okuyor ve saklı adın düz dosya adı olduğunu (`basename()` ile aynı, `..` yok)
denetliyor; değilse GET ve POST'ta "Kayıt bulunamadı." 404 (#449'un kalıbı,
tür sınırı yok: ekran her tasarım dosyasını açar). Sil ve yeniden adlandır
kapının okuduğu kimlik ve saklı adla çalışıyor; dosya yalnız adı değiştiyse
ve diskteyse yeniden adlandırılıyor. Üç düzenleyici diskte olmayan dosyayı
boş içerikle ve Dosya Yöneticisi'nin mevcut "Dosya burada kayıtlı ama diskte
yok." notuyla açıyor (normal açılışta HTML aynı); Kaydet eski dosyayı yalnız
varsa siliyor, dosyayı yeniden yazıyor. Şablon dosyası `theme__noise.css`
olarak yeniden adlandırıldı (veritabanı satırının adı; yeni kurulumlar).
Şema adımı, yeni dil anahtarı, JS yok. Test: `tests/panel_id_gate_test.php`
`test_panel_id_gate_file_editors_check_name_and_type` genişletildi.

**Bilerek yapılmayan.** Mevcut Türkçe kurulumlarda `data/files/theme_ noise.css`
elle `theme__noise.css` yapılmalı (şema adımı eklenmedi). Şablon satırındaki
boyut (134322) dosyanın gerçek boyutundan (127718) farklı; yalnız gösterim.
Doğrulama statik (`tools/test.php`, `lint`, `check_lang`, `build_js --check`);
sandbox'ta koşturulmadı.

---

## 2026.4.10 — Zip yedekler kurulum ekranından adım adım geri yükleniyor; geri yükleme yedekteki yöneticiyi ikinci kez eklemiyor (2026-10-14)

**Neden.** Adım adım geri yüklemenin (#441) iki kalanı. (1) Otomatik
yedek `ZipArchive` olan sunucuda haftanın yedeğini tek arşiv olarak yazıyor
(`data/backups/auto_backup_<yıl>-<ay>-<hafta>.zip`), ama kurulum ekranının
"Sunucudaki yedek" listesi yalnız klasörleri gösteriyordu: müşterinin en
güncel yedeği listede yoktu, elle açıp klasöre çevirmek gerekiyordu.
(2) Geri yükleme kurulum olarak çalıştığı için formdaki yönetici yedekten
gelen kullanıcıların üstüne her seferinde yeni satır olarak ekleniyordu.
`user` tablosunda e-posta ve kullanıcı adı tekil değil; `validate_login()`
`LIMIT 1` ile ilk satırı aldığından, yedekte aynı e-posta varsa yeni satıra
hiç giriş yapılamıyor, formda girilen parola çalışmıyordu (yedekteki satırın
parolası geçerliydi) ve kullanıcı listesinde aynı kişi iki kez görünüyordu.

**Ne değişti.**
- `includes/fn/backup_restore.php`: yedek adı `.zip` ile bitiyorsa arşivdir
  (`pg_restore_is_archive()`). `pg_restore_archive_read()` arşivin merkez
  dizinini okur: `sql.sql` en üstte (otomatik yedek, Yedekleme
  Yöneticisi'nin indirdiği arşiv) ya da tek bir klasörün içinde (elle
  sıkıştırılmış yedek klasörü; kurulum ekranının yüklemesi de böyle alıyor).
  Geri yükleme yalnız `sql.sql`'i ve yanındaki `files/` ile `layouts/`'un
  doğrudan altındaki dosyaları alır; alt klasör, `..`, Windows'un aygıt ya da
  akış sayacağı adlar (`:`, `*`, `?` …) ve başka her şey arşivde kalır.
- Arşivin geri yüklemesi `extract` evresiyle başlar
  (`pg_restore_extract_step()`): dosyalar `data/temp/restore_<id>/`'ye en çok
  20 sn'lik adımlarla açılır, ekran adımları #441'deki gibi kendisi gönderir.
  Yarıda kalan dosya, kaydedilen bayttan sürer: sıkıştırılmış bir dosyanın
  ortasına atlanamadığı için arşiv o bayta kadar yeniden okunur (sağlama
  toplamı bu yüzden bütün dosyayı kapsar), okuma adımın yazma süresinden
  sayılmaz, adım ardından en az yarı süre yazar. Her dosya bitince boyutu ve
  CRC-32'si arşivdekiyle karşılaştırılır; uymazsa "Arşiv bozuk" ile durur.
  İlk adım dökümün açılmış boyutu + 64 MB boş disk ister. Açma bitince evre
  `load` olur ve dökümün yüklenmesi #441'in kodundan hiç değişmeden çalışır
  (`pg_restore_step()` dökümü `pg_restore_content_path()`'ten okur).
- Kayıtta `archive`, `source_size`, `source_mtime` (arşivin kendisi; sürdürme
  bunu karşılaştırır), `extract_index`, `extract_written`. #441'in yazdığı,
  bu alanları taşımayan bir kayıt eskisi gibi dökümün boyutuyla eşleşir.
  Çalışma klasörü kayıtla birlikte gider: başarı, hata, **Sil** düğmesi ve
  yeni bir geri yüklemenin başlangıcı (`pg_restore_begin()` artık kalmış
  `restore_*` klasörlerini de siler).
- `install/index.php`: `get_site_backups()` `.zip` dosyalarını da listeler;
  kurulamayan arşiv seçenekte nedeniyle görünür ("Arşivde sql.sql yok …",
  "arşiv açılamadı …", zip desteği yoksa o). Kurulum isteği kaynak adını
  listeye karşı denetler: başlangıç sitesi ya da listedeki kurulabilir yedek
  değilse daha hiçbir şeye dokunmadan alan hatası verir (ad `data/backups`
  altında yol olduğu için `../` gibi değerler de burada durur; dizi gelen
  değer 500 vermez). Açma hatası "Arşiv açılamadı: … Site değişmedi:
  arşivden hiçbir şey yüklenmedi ve açılan dosyalar silindi." der, Site
  Günlüğü'ne #441'in satırı yazılır. Dosyalar ve tasarım şablonları yedek
  klasöründen ya da çalışma klasöründen kopyalanır; yedekte `files/` ya da
  `layouts/` yoksa sitenin kendi klasörü silinmez, konsola uyarı satırı
  düşer (eskiden önce siliniyor, sonra `opendir()` false dönünce istek
  ölüyordu).
- Yönetici: kurulum, formdaki e-postayla (yoksa kullanıcı adıyla) eşleşen bir
  kullanıcı arar. Bulursa yeni satır eklemez; o kullanıcının satırı yedekteki
  gibi kalır, yalnız parolası formdakine (MD5, `user_password_algo` 0; kurulum
  sonunda `pg_password_store()` modern hash'e çevirir) ve rolü yöneticiye
  çekilir. Konsolda ve sonuç kartında tek cümle: "Yedekte <kullanıcı>
  (<e-posta>) kullanıcısı zaten var, bu yüzden yeni yönetici eklenmedi: bu
  kullanıcı yöneticidir ve girdiğiniz parolayla giriş yapar." Eşleşme yoksa
  eskisi gibi yeni yönetici eklenir. Başlangıç sitelerinde kullanıcı yok,
  davranış değişmedi. Yarıda kesilen kurulum sürdürülürken de ikinci satır
  oluşmaz.
- Kaynak kartı "yedek klasörünü ya da .zip arşivini FTP ile yedekler
  klasörüne kopyalayın" diyor. `tests/backup_restore_test.php`: arşiv adı,
  yollar, arşiv okuma (otomatik yedek biçimi, klasörlü arşiv, dışarı çıkan ve
  Windows'a uymayan adlar, `sql.sql`'siz ve bozuk dosya).

**Seçilen varsayılan (yönetici).** Yedekteki kullanıcılar korunur; formdaki
yönetici yalnız yedekte aynı e-posta ya da kullanıcı adı yoksa eklenir.
Varsa o kullanıcıya formdaki parola verilir: geri yüklemeyi yapan kişi
yazdığı bilgilerle girebilsin, yedeğin eski parolasını bilmiyorsa ve posta
sunucusu yoksa siteye (ve kilitli kurulum ekranına) dışarıda kalmasın. Eski
davranışta da formdaki bilgiler girişti (ikinci satır yüzünden çoğu zaman
çalışmasa da), kilidi açan kişi zaten mevcut sitenin yöneticisi.

**Ölçüm (sandbox, MariaDB, PHP 8.3).** 2.002.000 Site Günlüğü satırlı,
301 tablolu site; otomatik yedek 232 MB arşiv (629 MB `sql.sql`, 214 dosya,
90 tasarım şablonu):
- Kurulum ekranı (HTTP, ekranın yaptığı gibi `restore_id` ile yeniden
  gönderim): 3 istek, toplam 41,6 sn (açma 7,6 sn; yükleme 20,1 + 13,9 sn;
  yer değiştirme 3,3 sn). 301 tablo, kullanıcı listesi kaynakla aynı
  (yedekten sonra silinen üye geri geldi, sonra eklenen gitti), yedekten
  sonra silinen dosya geri geldi, `pgr_` tablo, `restore.json` ve
  `restore_*` klasörü kalmadı; Site Günlüğü'nde "… yedeği geri yüklendi:
  600,0 MB, 3 adımda, 41 saniye".
- Aynı içeriğin klasör yedeği: 2 istek, 33,3 sn. Elle sıkıştırılmış,
  `elle/` klasörlü küçük arşiv: 2 istek, 9,1 sn.
- 1-2 sn'lik adımlarla açma: 5-6 adımda `sql.sql`'in SHA-1'i `unzip` ile
  aynı; 2. adımdan sonra öldürülmüş adım taklidi (işaret kaldı, dosyanın
  sonuna çöp eklendi): sonraki adım uyardı, dosyayı kaydedilen bayta kesip
  sürdü, SHA-1 yine aynı.
- Ortasına 16 sıfır bayt yazılmış arşiv: açma "sql.sql sağlama toplamıyla
  uyuşmuyor" ile durdu, site değişmedi, Site Günlüğü'ne satır yazıldı.
  Kesik arşiv "açılamadı", `sql.sql`'siz arşiv listede nedeniyle göründü ve
  reddedildi. Açmanın ortasında bırakılan geri yükleme kurulum ekranında
  kart olarak göründü (%13), **Sil** çalışma klasörünü ve kaydı sildi.
- Yönetici: formdaki e-posta yedekte yönetici → yeni satır yok, formdaki
  parolayla giriş; yedekte olmayan e-posta → yedeğin kullanıcıları + yeni
  yönetici; e-posta yedekte üye → üye yönetici oldu; yalnız kullanıcı adı
  eşleşti → o kullanıcı. Diğer kullanıcılar yedekteki parolalarıyla girdi.

**Bedeli.** Arşivin açılması dökümün boyutu kadar ek disk ister (işlem
bitince silinir). Bir adımda bitmeyen çok büyük dökümde (bu sandbox'ta
~1,5 GB üstü) her açma adımı dökümü baştan yeniden okur; 200 MB/sn'lik açmada 10 GB'a
kadar istek süresi bir dakikanın altında kalır.

**Bilerek yapılmayan.** Şema adımı yok. Kurulum ekranının bilgisayardan
yükleme seçeneği eskisi gibi arşivi klasöre açıyor (dosyayı belleğe alarak);
artık arşivi olduğu gibi yedekler klasörüne koymak yeterli olabilir, ayrı iş.
Geri yüklemenin kurulum olarak yaptığı diğer işler (ayarlardaki, formlardaki,
ürünlerdeki ve kampanya profillerindeki e-posta adreslerinin formdaki
adresle değiştirilmesi, zaman damgalarının şimdiye çekilmesi, yazılım dilinin
formdakine çekilmesi) değişmedi; ayrı iş.

---

## 2026.4.10 — Sipariş ekranları ziyaret kaydı olmadan uyarı yazmıyor; ortaklık kodlu sipariş 500 vermiyor (2026-10-14)

**Neden.** #444'ün kalanı. Ziyaret kaydı (`$_SESSION['software']['visitor_id']`)
yalnız bir site sayfası açılınca oluşuyor; botlar hiç almıyor (#426). On
`UPDATE visitors` kaydı varsayıyordu ve kaydı olmayan istekte "Undefined
array key visitor_id" yazıyordu (`WHERE id = ''` hiçbir satıra
dokunmuyordu): `shopping_cart.php` (ortaklık kodu, ödemeye geçiş),
`billing_information.php`, `submit_order.php`, `express_order.php` (ortaklık
kodu, fatura bilgisi), `retrieve_order.php`, `order_history_retrieve_order.php`,
`order_history_reorder.php`, `custom_form.php`. Sandbox aynı akışlarda üç
sorun daha gösterdi:

- `retrieve_order.php`, `order_history_retrieve_order.php` ve
  `order_history_reorder.php` oturumdaki utm etiketlerini ve
  `http_referer`'ı varsayıyordu; bunlar yalnız adres ya da çerez taşıyınca
  oturuma yazılıyor, yani normal ziyaretçide de uyarı.
- `submit_order.php` ortaklık komisyonunu sipariş kaydının varsayılanı `''`
  üstüne topluyordu (2026.4.3'te eklenen varsayılan). PHP 8 `'' + float`
  ve `round('')`'da TypeError atıyor: geçerli ortaklık koduyla verilen her
  sipariş 500 veriyor, sipariş `incomplete` kalıyordu.
- Özel erişim veren ürünün ilk satın alımında erişim satırı yok,
  `$row['rights']` "array offset on null" yazıyordu.

**Ne değişti.** On `UPDATE visitors` #444'teki gibi yalnız
`!empty($_SESSION['software']['visitor_id'])` iken koşuyor (ortaklık
kodunun oturuma ve çereze yazılması eskisi gibi). utm okumaları
`submit_order.php` / `initialize_order()` ile aynı biçimde (`!empty()`,
`?? ''`). Komisyon, ürün döngüsünden önce `0`'dan başlıyor; ortaklık kodu
olmayan siparişin kaydı değişmedi. Erişim satırı yoksa hak ve bitiş tarihi
`''` (eski `null` ile aynı sonuç). Şema, dil dosyası, JS yok. Testler:
`tests/visitor_record_optional_test.php` (dosya listesi genişledi, utm
testi), yeni `tests/order_submit_affiliate_test.php`; eski kodda hepsi
düşüyor.

**Doğrulandı (sandbox, MariaDB, PHP 8.3).** Eski kod ikinci portta, aynı
veritabanı. Sepet → fatura → ödeme (çevrimdışı), ortaklık kodlu ve kodsuz;
hızlı sipariş, ortaklık kodlu ve kodsuz; `/cart?r=<referans>`; sipariş
geçmişinden yineleme ve kurtarma; sepet sayfasındaki özel form. Her biri
ziyaret kaydı olmadan (curl kullanıcı ajanı) ve normal ziyaretçiyle. Eski
kod: kaydı olmayan istekte 27, normal ziyaretçide 14 uyarı satırı, ortaklık
kodlu sepet ve hızlı siparişte 500; yeni kod 0 uyarı, ortaklık kodlu
siparişler tamamlanıyor (komisyon 449, `commissions` satırı `pending`).
Öteki akışlarda durum kodları, yönlendirmeler, `orders` ve `visitors`
satırları iki kodda aynı. `tools/lint.php`, `tools/test.php`, `check_lang`,
`check_bindings`, `check_api_schema`, `check_copies`,
`build_js.php --check` temiz. Doğrulanamadı: dev.pinegrap.com.

---

## 2026.4.10 — Kalan "Passing null" satırları ve `liveform::prepare()` uyarıları (2026-10-14)

**Neden.** #445'in kalanları. `h()` ve `escape()` düzelince hata ayıklama
açıkken aynı türden kullanım dışı satırını yazan yalnız birkaç yer kaldı:
`escape_csv()` (kampanya profillerinin CSV dışa aktarması; son düzenleyeni ya
da oluşturanı silinmiş profil), `get_page_content.php:4763`
`mb_strtolower()` (yönetici oturumuyla açılan, yorum açık form kayıt sayfası,
örn. `/classified-ads-item`: `get_submitter_email_address()` kayıt yokken
`db_value()`'nun NULL'ını döndürüyordu) ve `import_design.php:104`
`mb_substr()` (URL'siz gönderilen Tasarım İçe Aktar formu).
`encode_url_path()` ve `escape_javascript()` #445'in taramasında
`browse_catalog_menu`, `add_email_campaign` ve `preview_form`'da görünmüştü;
bu ekranlar o arada düzeldi (#437, #442), yardımcılar null'a hâlâ açıktı.
`liveform::prepare()` sayfa düzenindeki `type` özniteliği çift tırnakla
yazılmamış ya da hiç yazılmamış bir alanı (`<input name="email">`) ve `value`'su
olmayan onay kutusunu, alan oturumdayken (hatalı gönderimden sonra form
yeniden çizilirken) "Undefined array key 1" ve `trim(null)` ile karşılıyordu.
Elle yazılmış ya da eski düzenlerde olur. Hepsi 2026.4.9'da da var.

**Ne değişti.** `escape_javascript()`, `escape_csv()` ve `encode_url_path()`
(`includes/fn/core.php`) `null` alınca hemen `''` döndürüyor (#445'in
yaklaşımı: dönen değer aynı, çağıranlar değişmedi; dizi eskisi gibi).
`get_submitter_email_address()` (`includes/fn/mail.php`) her zaman metin
döndürüyor; üç çağıranı da değeri yalnız doğru/yanlış ya da metin olarak
okuyor. `liveform::prepare()` `type`'ı olmayan alanı metin alanı, `value`'su
olmayan kutuyu `''` sayıyor (eski kod da sonuçta böyle davranıyordu).
`import_design.php` alan değerini metin okuyor. Şema, dil dosyası ve JS
değişikliği yok. Test: `tests/core_test.php`
`test_core_other_escape_helpers_take_null`, `tests/liveform_test.php`
`test_liveform_prepare_tag_without_type_or_value` (ikisi eski kodda
`ErrorException`, yenide geçiyor).

**Doğrulandı (sandbox, MariaDB, PHP 8.3, `debug = 1`, development 86d5a130
üstünde; eski kod aynı veritabanında ikinci sunucuda).** #417'nin tarayıcısı
(367 panel ekranı, 6.760 GET): "Passing null" satırı yazan istek 5 → 0
(`import_design` düzeltmesi sonrası etkilenen 7 ekran yeniden: 3 → 0); kalan
682 satırın hepsi aşağıdaki takvim tarihi. 181 site sayfası yönetici
oturumuyla: kullanım dışı satırı 1 → 0. `prepare()`: sandbox'ta yalnız
düzeni değiştirilmiş şifremi unuttum sayfası (`type`'sız e-posta alanı,
`value`'suz kutu) boş gönderilince eski kod 2 uyarı, yeni kod 0; yeniden
çizilen sayfa iki oturumla aynı. HTML: 443 panel açılışı 436 aynı (7'si canlı
veri: veritabanı motoru, oturumlar, performans günlüğü, karşılama özeti ×2,
sunucu yolu gösteren iki ayar ekranı), CSV dışa aktarma bayt bayt aynı;
site sayfaları yöneticide 179/181, oturumsuz 171/181 aynı (fark: CAPTCHA
sorusu, çıkış jetonu, cihaz listesi). `tools/lint.php`, `tools/test.php`,
`check_lang`, `check_bindings`, `check_api_schema`, `check_copies` ve
`build_js.php --check` geçti. Doğrulanamadı: dev.pinegrap.com.

**Bilerek yapılmayan.** `?date[]=x` takvim görünümü olan bir sayfaya (404
hata sayfası da olabilir) gelince `get_calendar_view_screen_content.php:52`
diziyi oturuma yazıyor; o oturumda takvimi çizen her istek
`includes/fn/calendar.php:48` "Array to string conversion" yazıyor (taramada
37 ekranda 682 istek, eski ve yeni kodda aynı). Sepet ve teklif sayfası
düzenleri (`data/backups/*/layouts/248.php` ve `497.php` 138–203, hızlı
ekleme alanı) tanımsız anahtar uyarıları ayrı.

---

## 2026.4.10 — Dosya düzenleyicileri yalnız kendi türündeki tasarım dosyasını açıp kaydediyor (2026-10-14)

**Neden.** #435'in kalanı. CSS düzenleyicisi (`edit_theme_css.php`) kimlik
kapısından (#422) geçen her `files` satırını açıyordu: elle yazılmış
adresle bir resim, yazı tipi ya da tasarım dosyası olmayan bir dosya
baytlarıyla metin kutusunda görünüyor, Kaydet onu silip metinle yeniden
yazıyordu (sandbox'ta `close.png` 280 bayttan 6 baytlık `body{}`'ye döndü).
Kaydetme yolu adı satırdan alıp `..`'yu reddediyordu, açma yolu almıyordu:
`../x.css` adlı bir satır `data/` altındaki dosyayı okumaya çalışıyordu.
Çoğalt yeni dosyayı gönderilen `name` alanının adıyla açıyordu
(`name=evil.png` → `evil.png` adlı, içi CSS olan bir tasarım dosyası).
Kaydetmede dizi gelen `code` dosyayı önce siliyor, sonra `fwrite()`
TypeError'ıyla 500 veriyordu: dosya 0 bayt kalıyordu. JavaScript
düzenleyicisi (`edit_javascript.php`) ve JSON/TXT/SVG/XML düzenleyicisi
(`editor_edit_file.php`) aynı kalıpla yazılmıştı; Temayı Düzenle
(`edit_theme_file.php`) her dosyayı yeniden adlandırıp silebiliyordu.

**Ne değişti.**
- Dört ekranın kimlik kapısı (GET ve POST'tan önce) satırı
  `design = '1'` koşuluyla okuyor ve saklı adın düz bir dosya adı
  olduğunu (`basename()` ile aynı, `..` yok) ve uzantısının ekranın
  kendi türü olduğunu denetliyor: CSS düzenleyicisi ve Temayı Düzenle
  `css`, JavaScript düzenleyicisi `js`, metin düzenleyicisi `js`, `json`,
  `txt`, `svg`, `xml` (kendi kod kipi listesi; Tasarım Dosyasını Düzenle
  ekranı bunlardan JS dışındakileri buraya gönderiyor). Değilse "Kayıt
  bulunamadı." 404, açmada da kaydetmede de. Dosyalar tek klasörde
  (`data/files`) durduğu için "izin verilen klasör" düz ad koşulu,
  "tema/stil dosyası" tasarım dosyası koşulu oluyor.
- Üç metin düzenleyicisi: kaydetme yolu kapının okuduğu adı kullanıyor
  (kendi satır sorgusu ve "Sorry, the file could not be found." çıkışı
  kalktı); Çoğalt kopyayı saklı addan (`prepare_file_name()` +
  `get_unique_name()`) ve satırın klasörü ve açıklamasıyla
  oluşturuyor (CSS düzenleyicisinde tema bayrağı da), gönderilen `name`
  hiç okunmuyor. `code`
  `pg_request_single_values()` ile tek değer; dizi ya da hiç gelmezse
  hiçbir şey yazılmadan düzenleyiciye geri dönülüyor. Yönlendirmeler
  `$id` ve `send_to ?? ''` kullanıyor.
- `edit_theme_css.php` Çoğalt: `from` yalnız `edit_theme_file` ya da
  `edit_design_file` olabiliyor (yeni kopyanın açılacağı betik adı
  ondan kuruluyordu), başka her değer eskiden boş `from`'un aldığı
  `edit_theme_file`. Kaydet yolundaki `from` okumaları `?? ''`.
- `tests/panel_id_gate_test.php`: dört ekranın kapısının tasarım dosyası,
  düz ad ve uzantı koşullarını GET ve POST dallarından önce taşıdığını,
  metin düzenleyicilerinin gönderilen adı okumadığını ve `code`'u dosya
  yazılmadan önce tek değere indirdiğini denetleyen test (eski kodda
  kırmızı).

**Ölçüm (sandbox, eski kod ikinci portta).** 17 normal istek (dört
ekranın gerçek tema, CSS, JS ve SVG dosyalarıyla, `from`/`send_to`
çeşitleri, tema dışa aktarma, `?id=999999`, kimliksiz, `?id[]=`) eski
kodla aynı HTML ve durum kodu. 19 yanlış dosya isteğinde (resim, yazı
tipi, başka türden tasarım dosyası, tasarım dosyası olmayan dosya,
`../x.css`, `sub/x.css`) eski kod 200, yeni kod 404 "Kayıt bulunamadı".
Kaydetme: resme, `../` satırına ve Temayı Düzenle'den resme silme
isteği 404, dosyalar aynı; dizi `code` 302 ile düzenleyiciye dönüyor,
dosya aynı (eski kod 500, dosya 0 bayt). Normal Kaydet ve Kaydet ve Dön
eski kodla aynı yönlendirme ve içerik. `name=evil.png` ile Çoğalt
`sbx-font[1].css` oluşturuyor (eski kod `evil.png`). Yeni kod hata
günlüğüne satır yazmadı.

**Bilerek yapılmayan.** Şema adımı, dil anahtarı, JS yok. Diskte
bulunmayan dosyanın açılışı (`file_get_contents()` uyarısı) değişmedi.
Tasarım Dosyasını Düzenle (`edit_design_file.php`) tasarım dosyası
olmayan bir kimlikle de açılıyor; o ekran dosyanın içeriğine yazmıyor,
dokunulmadı. Resim düzenleyicisi tür listesini zaten denetliyor.

---

## 2026.4.10 — Jetonlu çoğaltma ve gönderme eylemleri kaydı olmayan id'de "Kayıt bulunamadı" diyor; Hesabım sipariş ekranı uyarı yazmıyor (2026-10-14)

**Neden.** #442'nin kalanları.

1. Jetonla çağrılan on eylem ekranı (`duplicate_calendar_event`,
   `duplicate_common_region`, `duplicate_designer_region`,
   `duplicate_email_campaign_profile`, `duplicate_menu`, `duplicate_page`,
   `duplicate_product`, `optimize`, `remove_recipient`,
   `send_email_campaign`) `$_GET['id']`'yi doğrudan `escape()`'e veriyordu:
   `?id[]=x` 500 veriyordu. Sandbox'ta eski kodla bulunan daha ağır durum:
   id'siz ya da var olmayan id'yle (`?id=999999`) beş çoğaltma ekranı boş bir
   satırdan kopya yaratıyordu (adsız takvim etkinliği, ortak ve tasarımcı
   bölgesi, kampanya profili, ürün) ve hata günlüğüne onlarca satır
   yazıyordu; `send_email_campaign` id'siz açılınca boş satır üzerinde
   gönderme döngüsünü koşuyordu. `send_email_campaign` ayrıca jetonu hiç
   doğrulamıyordu: iki çağıran bağlantı (`add_email_campaign`,
   `edit_email_campaign`) ve sonraki 10 alıcı için yenileme jetonu
   taşıyordu, ama ekran bakmıyordu; başka bir siteden açılan bir bağlantı
   oturumu açık yöneticinin hazır kampanyasını gönderebiliyordu.
2. Hesabım'ın sipariş ekranı (`get_view_order_screen_content`) gerçek bir
   siparişin her açılışında uyarı yazıyordu. Özel alan etiketleri bu
   ziyaretin ödeme akışındaki fatura bilgileri sayfasından okunuyor
   (`$_SESSION['ecommerce']['billing_information_page_id']`); siparişine
   geri bakan müşteride o anahtar yok. Kalemsiz bir siparişte
   `$recurring_products_exist`, `$applied_offers` ve
   `$output_recurring_products` hiç atanmıyordu. Özel düzende (sayfanın
   düzen türü "custom") tamamlanmış bir siparişin teslimat adresinde takip
   numarası yoksa `$recipient['shipping_tracking_numbers']` atanmıyor,
   `includes/templates/view_order.php` `count()` ile 500 veriyordu;
   kargosuz kalemlerin alıcısında `offer_id`, kargo adresinde `name`,
   otomatik üyelik yokken `$auto_registration_email_address` /
   `_password` tanımsızdı. Hepsi 2026.4.9'da da var.
3. Sipariş geçmişindeki geri alma, yeniden sipariş ve silme ekranlarının
   "The order could not be found." ve yanındaki üç hata metni `lang()`'sız
   olduğu için Türkçe sitede İngilizce kalıyordu.

**Ne değişti.**

1. On ekranın hepsi jeton denetiminden hemen sonra #424'ün kapısını
   taşıyor: `$id = is_scalar($_GET['id'] ?? null) ? (int) $_GET['id'] : 0`,
   ardından tablosunda `COUNT(*)`; id yok, dizi ya da kayıt yoksa "Kayıt
   bulunamadı." 404. `remove_recipient`'te kapı oturum denetiminden sonra
   (oturumsuz ziyaretçi eskisi gibi girişe yönleniyor). Ekranların kendi
   "bulunamadı" yanıtları (menü, sayfa, dosya, alıcı) yerinde kaldı; var
   olmayan id'de artık önce kapı yanıt veriyor. `send_email_campaign`
   `validate_token_field()` çağırıyor. `duplicate_menu`'nün menü
   öğelerini kopyalayan özyinelemeli fonksiyonu ilk çağrıda üst öğe
   olmadığı için iki "Undefined array key" yazıyordu; varsayılan 0, sorgu
   ve kayıt aynı.
2. Sipariş ekranı üç listeyi fonksiyonun başında boş başlatıyor; oturum
   anahtarlarını ve etiketleri `?? ''` ile okuyor (sorgu ve çıktı aynı:
   eski kodda da boş değer hiçbir satıra uymuyordu). Özel düzende her
   alıcının takip numarası listesi boş dizi olarak başlıyor, `name`
   ve otomatik üyelik değişkenleri boş, kargosuz alıcı `!empty()` ile
   okunuyor. `includes/templates/view_order.php` takip numarası satırını
   yalnız numara varken gösteriyor (sistem düzeniyle aynı). Düzen
   dosyası değiştirilmemiş ("layout_modified" 0) sayfalar şablonu doğrudan
   kullandığı için düzelme hemen geçerli; panelden değiştirilmiş eski
   düzen dosyaları 500 yerine numarasız boş bir "Tracking Number:"
   satırı gösteriyor (PHP 7'deki görünüm).
3. Üç `order_history_*` ekranının dört hata metni `lang()` ile; "The
   order could not be found." ve "You do not have access to this order."
   anahtarları zaten vardı, üç yeni anahtar `tr.json` sonuna eklendi.

Şema adımı ve JS yok. Testler: `tests/panel_id_gate_test.php`
(`test_panel_id_gate_on_token_action_screens`: on ekranda kapı var, id'yi
ondan önce okuyan yok, jeton kapıdan önce), yeni
`tests/order_view_screen_test.php` (varsayılanlar düzenlerden önce, oturum
okumaları, takip satırı, `lang()`'lı mesajlar).

**Doğrulandı (sandbox, MariaDB, PHP 8.3).** Eski kod (`origin/development`)
8001'de, yeni 8000'de, aynı veritabanı. On ekran `?id[]=x`: eski 500, yeni
404 "Kayıt bulunamadı.". id'siz ve `?id=999999`: eski beş çoğaltma ekranı
boş kopya yarattı (yönlendirme yeni kaydın düzenleme ekranına), menü,
sayfa, dosya ve alıcı kendi bulunamadı yanıtını verdi; yeni 404, kayıt
yaratılmadı. Var olan kayıtla (etkinlik 7, bölge 77 ve 193, profil 8, menü
11, sayfa 886, ürün 70, dosya 2019, alıcı 7, kampanya 500) iki sunucuda aynı
durum kodu, aynı yönlendirme biçimi ve aynı mesaj; jetonsuz istek ikisinde
de jeton hatası, yalnız `send_email_campaign` jetonsuz eskide gönderdi,
yenide jeton hatası. 12 alıcılı kampanya yeni kodda iki parti halinde
yenilemeyle sonuna kadar gitti (jeton yenilemede taşınıyor). Hesabım sipariş
ekranı yönetici oturumuyla altı siparişte (kalemsiz, tek ve iki teslimat
adresli, yinelenen ürünlü, kargosuz kalemli): sistem düzeninde eski kod her
açılışta 3, kalemsizde 6 uyarı satırı, yeni kod hiç; HTML aynı (yalnız sayfa
görüntülenme sayacı farklı). Özel düzende eski kod tamamlanmış siparişlerde
500, yeni kod 200, uyarı yok; takip numaralı siparişte numara iki kodda aynı
görünüyor. Sipariş geçmişi hataları yeni kodda Türkçe. `tools/lint.php`,
`tools/test.php` (1012 test), `check_lang`, `check_bindings`,
`check_api_schema`, `check_copies` ve `build_js.php --check` geçti.
Doğrulanamadı: dev.pinegrap.com'daki gerçek veriler, gerçek e-posta
gönderimi.

**Bilerek yapılmayan.** `update_order.php:28`'in API yanıtı ("The order
could not be found.") İngilizce kaldı (API iletileri İngilizce). Sipariş
geçmişi ekranlarının "Query failed." metinleri değişmedi. Panelden
değiştirilmiş özel sipariş düzenlerinde takip numarası olmayan adreste boş
"Tracking Number:" satırı görünüyor; düzen yeniden üretilince gider.

---

## 2026.4.10 — Sipariş akışının son kalanları: editörün sepet widget'ı ve sepete ekle API'si dizi değerde, her siparişteki uyarılar (2026-10-14)

**Neden.** #421, #430 ve #440'ın kalanları. Sandbox'ta eski kodla yeniden
üretildi; hepsi 2026.4.9'da da var.

- Görsel editörün sepet widget'ının betiği (`cart_action.php`) dizi gelen
  değeri sessizce yanlış yazıyordu: `quantities[<kalem>][]=` `(int)` ile 1
  (boş dizi 0, yani kalem siliniyordu); bağış tutarı dizi gelince 0
  okunuyor ve bağış sepetten çıkıyordu; hızlı eklemede
  `quick_add_ship_to[]` / `quick_add_add_name[]` "Array" adlı bir alıcı
  açıp ürünü ona ekliyordu; `quick_add_product_id[]` 1 numaralı ürün
  sayılıyordu; ürün formunun metin alanı dizi gelince elemanları virgülle
  birleştirip yazıyordu, iç içe dizi "Array" yazıyordu. Yinelenen ödeme
  alanları, indirim kodu ve `checkout_to` dizi gelince "Array to string
  conversion" uyarısı yazıyor, indirim kodunda sayfa `"Array" teklif kodu
  geçersiz` diyordu.
- Panelin `add_to_cart` API eylemi (`add_to_cart.php`, ürün düğmesinin
  JSON isteği): `product` metin (`"70"`) ya da `product.id` liste / nesne
  gelince 500 (`TypeError`); `form` ya da `quantity` liste / nesne gelince
  uyarı. Normal istekte de uyarı vardı: vitrin düğmesi `form` göndermezse
  (`request.form` tanımsızken `JSON.stringify` anahtarı atıyor) her
  tıklamada "Undefined array key \"form\"".
- Her siparişte hata günlüğüne yazılan, diziyle ilgisiz uyarılar:
  `get_tax_rate_for_address()` (`forms.php`) eyaleti tanınan ama ülkesinin
  hiçbir vergi bölgesinde olmayan adreste (ör. Teksas dışı bir ABD eyaleti)
  `$valid_tax_zone_id` tanımsız; kargolu hızlı siparişte unvanı ya da
  soyadı boş alıcıda `get_express_order.php` `$recipient['name']`
  (başlamadan okunuyordu; unvan çoğu siparişte boş); hızlı siparişin makbuzu
  hem sayfada hem e-postada `get_order_receipt.php` 2262–2267 ve kargoluysa
  1669 (oturum fatura bilgileri ve kargo adresi sayfasını hiç görmediği
  için anahtar yok, satır yok).

**Ne değişti.** #407 / #421 / #440'ın kuralı: dizi gelen tek değerli alan
"verilmemiş" sayılır, sonrası boş alanla aynı.

- `cart_action.php` indirim kodunu, çevrimdışı ödeme anahtarını, hızlı
  ekleme alanlarını, `save_item_id`'yi ve `checkout_to`'yu siparişe
  dokunmadan önce `pg_request_single_values()`'a veriyor (dizi silinir).
  Adedi ya da bağış tutarı dizi gelen kalem atlanıyor, olduğu gibi kalıyor
  (`shopping_cart.php` #440'ta da böyle). Ürün formu alanı
  `pg_cf_posted_value()`'dan geçiyor (alan `multiple` ile okunuyor): onay
  kutusu ve çoklu seçim listesini tutup virgülle birleştiriyor (eskisi
  gibi), öbür alanlarda dizi boş metin. Yinelenen ödeme alanları dizi
  gelince boş okunuyor: dönem ve başlangıç tarihi ürünün varsayılanına
  düşüyor, ödeme sayısı boş (ClearCommerce / First Data'da "gerekli").
  Hızlı eklemede dizi alıcı boş sayılıyor; çok alıcılı sitede kargolu
  üründe "alıcı seçmelisiniz" uyarısı çıkıyor. Hediye çeki alanları zaten
  diziyi atlıyordu; değişmedi.
- `add_pending_offers()` (`ecommerce.php`; her sepet, hızlı sipariş ve
  widget) bekleyen teklifin alıcı ve isim alanlarını yalnız metinse
  okuyor.
- `add_to_cart.php`: liste / nesne `quantity` eskisi gibi "Lütfen geçerli
  bir miktar girin." (uyarısız, sepet açılmadan); ürün kimliği yalnız
  `product` nesnesindeki metin ya da tam sayı `id`'den okunuyor, başka her
  şey "ürün eklenemedi" yanıtı; `form` ve `notice` yalnız metinse
  kullanılıyor, gelmezse uyarı yok.
- `get_tax_rate_for_address()` `$valid_tax_zone_id`'yi boş başlatıyor;
  `get_express_order.php` her alıcının `name`'ini makbuzdaki
  (`get_order_receipt.php`) gibi boş başlatıyor; `get_order_receipt.php`
  oturumdaki iki sayfa kimliğini `?? ''` ile, özel alan etiketlerini
  satır yoksa boş okuyor. Hepsi eskiden de boş/yanlış değer üretiyordu:
  sayfa ve e-posta çıktısı aynı.

Uyarı susturulmadı. Şema, dil anahtarı, JS yok.

**Bilerek yapılmayan.** Sepet sayfasının örnek düzeni (`data/layouts`
248 ve teklif sepeti 497) her açılışta 138–203 (`label`, `recipient`,
`quantity`, `amount`, `available_products`) ve 611 (`titles`) uyarısı
yazıyor; nedeni `get_shopping_cart.php`: `$quick_add['system'] = ''`
hızlı ekleme kapalıyken de diziyi dolu yapıyor, düzen boş hızlı ekleme
formunu çiziyor. Düzeltmek görünen HTML'i değiştireceği (boş form
kalkacak) ve başka dosyada olduğu için kalanlarda. Hızlı sipariş düzeninde
(256) hata sonrası çizimde uyarı sandbox'ta yeniden üretilemedi (alan
hatası, kart hatası, koşul onayı, dizi alanlar). `cart_action.php`'nin
çevrimdışı ödeme ve "sonra al" kolları yalnız kaynak üzerinden denendi
(yönetici ve `ECOMMERCE_SAVE_FOR_LATER` gerekiyor). Botlarda `visitor_id`
uyarısı ayrı işte. Dizi beklenen yerde tek değer (`quantities=5`) ele
alınmadı.

**Doğrulandı (sandbox, MariaDB, PHP 8.3).** Eski kod `origin/development`
worktree'sinden ikinci portta, aynı veritabanıyla. `cart_action.php`: her
durumda yeni bir sepet (kargolu ürün, ürün formlu etkinlik kaydı, hediye
çeki, yinelenen ve formlu bağış) ve 20 istek; eskide 11'i uyarı yazdı,
yenide 0; normal güncelleme, indirim kodu ve hızlı ekleme iki kodda aynı
satırları yazdı (`order_items`, `form_data`, `order_item_gift_cards`,
`orders`). Farklar yalnız dizi gelen alanda ve yukarıda anlatıldığı gibi
(eskide bağış silindi ve "Array" alıcısı açıldı, yenide kalem olduğu gibi /
"alıcı" uyarısı). `add_to_cart` API'sine 13 istek: eskide 4'ü 500, 7'si
uyarı; yenide 0 / 0, normal üç istek aynı yanıt ve satır. Dört hızlı sipariş
(bağış; kargolu ürün unvansız ve "Mr."; ABD Arizona fatura adresiyle bağış):
hızlı sipariş sayfası, hata sonrası sayfa, makbuz sayfası (rastgele
CAPTCHA ve kimlikler dışında) ve `orders`, `order_items`, `ship_tos`
satırları bire bir aynı; eskide 6–10 uyarı satırı, yenide 0.
`php tools/test.php order_form_array` eski kodda 4 başarısızlık ve 1 hata,
yenide geçiyor. `tools/test.php`, lint, dil, kopya, bağlama, API şeması ve
`build_js.php --check` geçti. Doğrulanamadı: makbuz e-postaları ayrıca
karşılaştırılmadı (aynı fonksiyon, aynı değerler); gerçek ödeme ağ
geçitleri.

---

## 2026.4.10 — ERP formları dizi gelen tek değerli alanı "Array" diye kaydetmiyor, 1 numaralı kaydı seçmiyor (2026-10-14)

**Neden.** #427'nin son kalanı: ERP ekranlarının POST işleyicileri. Formlar
tek değerli alanları `(string)`, `(int)` ya da `trim()` ile okuyordu. Elle
hazırlanmış bir istekte alan dizi gelince (`name[]=x`) "Array to string
conversion" yazılıp değer "Array" olarak kaydediliyor, `(int)` diziyi
uyarısız 1 yaptığı için 1 numaralı kayıt seçiliyordu. Sandbox'ta eski kodla:
kasa düzenlemede Sil `id[]=2` ile 1 numaralı kasayı sildi; cari ve kasa
düzenleme `id[]` ile 1 numaralı kaydın üstüne yazdı; tahsilat
(`account_id[]`, `cash_account_id[]`), virman (`from_id[]`), gider
(`category_id[]`, `cash_account_id[]`), fatura taslağı, satın alma siparişi,
teklif, irsaliye ve çek (`account_id[]`, `till_id[]`) 1 numaralı cariye ya da
kasaya yazıldı; hesap eşlemede `link_account_id[]` kartı 1 numaralı cariye
bağladı; cari, kasa, stok sayımı ve hızlı cari (`erp_account_quick.php`)
"Array" adlı kayıt açtı; e-belge bağlantı testi `edoc_provider[]`'ı "Array"
okuyup "Bilinmeyen e-belge sağlayıcısı" dedi. Hepsi 2026.4.9'da da var.

**Ne değişti.** Serinin kuralı: dizi gelen tek değerli alan "verilmedi"
sayılır; alan zorunluysa ekranın kendi "gerekli" hatası çıkar ve bir şey
kaydedilmez, normal kayıt aynı kalır. 17 ekran jeton denetiminden hemen sonra
(liveform `add_fields_to_session()`'dan önce) #425'in
`pg_request_single_values()`'ini okuduğu tek değerli alanlarla çağırıyor:
`add_erp_account`, `edit_erp_account`, `add_erp_till`, `edit_erp_till`,
`add_erp_receipt`, `add_erp_transfer`, `add_erp_expense`,
`add_erp_manual_invoice`, `edit_erp_invoice_draft`,
`add_erp_purchase_order`, `add_erp_quote`, `add_erp_waybill`, `erp_cheques`,
`erp_stock_counts`, `erp_account_sync_item`, `get_erp_edoc_test` ve
`erp_account_quick` (#437'nin bıraktığı hızlı cari ucu).

- Fatura ve cari formlarının alan listeleri formun yanında:
  `erp_invoice_form_single_fields()` (`includes/erp/invoice_form.php`) ve
  `erp_account_form_single_fields()` (`includes/erp/account_form.php`);
  bunları kullanan ekranlar listeyi yeniden yazmıyor.
- Satırlar (`lines[…]`), hesap eşlemenin `choice[…]` seçimleri ve öbür
  gerçek listeler olduğu gibi.
- `edit_erp_till`: `id` boş ya da kasa yoksa Sil ve Kaydet "Kasa
  bulunamadı." diyor (önceden olmayan kasa için "Kasa silindi." diyordu).
- `get_erp_edoc_test`: sağlayıcının bilgi kutuları adları sürücüden geldiği
  için listeye girmiyor; dizi gelen kutu yazılmamış sayılıyor
  (`is_string()`).
- `tests/erp_request_values_test.php` iki test aldı: 17 ekranın okuduğu her
  `$_POST` alanı ve liveform `get_field_value()` adı çağrının listesinde ve
  çağrı ilk okumadan önce; fatura ve cari formunun okuduğu her alan ortak
  listede. Eski kodda ilk test 17 ekranın hepsinde, ikincisi listeler
  olmadığı için düşüyor; listeden bir ad silinince ya da çağrı kaldırılınca
  da düşüyor. Henüz çağrısı olmayan 29 ERP POST ekranı testte bekleyenler
  listesinde (ikinci yarı).

Şema, dil dosyası ve JS değişikliği yok ("Kasa bulunamadı." çevirisi zaten
var).

**Doğrulandı (sandbox, MariaDB, PHP 8.3, DEBUG açık).** Eski kod
(`origin/development`) 8001'de, yeni 8000'de, aynı veritabanı ve `data/`;
her istekten önce ERP tabloları, ürünler ve kişiler aynı anlık görüntüye
dönüyor, yazılan satırlar `mysqldump` farkıyla okunuyor. Hesap eşleme için
sahte İşbaşı ayarı (`erp_edoc_providers` satırı, boş kimlik bilgisi) ve iki
kart; hiçbir sağlayıcıya istek gitmedi. 27 senaryo (eşlemenin bağla, kart
bağla, kenara al, içe al, eşitle, gönder, bağı çöz eylemleri; fatura kesme
dahil): 27 normal istek iki kodda aynı durum, yönlendirme, mesaj ve yazılan
satırlar (yalnız zaman damgası ve rastgele kimlikler farklı). Her alan tek
tek dizi verilerek 207 istek: eski kodda 150'si uyarı yazdı (86 farklı
satır), 47'si "Array" içeren satır kaydetti; yeni kodda uyarı 0, "Array"
satırı 0, 500 yok. Zorunlu alan dizi gelince yeni kod ekranın kendi
hatasını veriyor ve yazmıyor (ör. "Adı gerekli", "Bir cari seçin.", "İki
hesabı da seçin.", "Kasa bulunamadı.", hızlı caride 422 `{"field_errors":
{"title": "Adı gerekli"}}`); isteğe bağlı alan dizi gelince kayıt o alan
boş olarak yapılıyor. Dokuz istekte (fatura taslağı ve hesap eşleme hata
dönüşü) iki kodda da görülen liveform `h(null)` kullanım dışı satırı #445
ile gitti; bu ekranlar yeniden tarandı, satır yok. `tools/lint.php`,
`tools/test.php`, `check_lang`, `check_bindings`, `check_api_schema`,
`check_copies` ve `build_js.php --check` geçti. Doğrulanamadı:
dev.pinegrap.com'daki gerçek veriler ve gerçek bir e-belge hesabı.

**Bilerek yapılmayan.** Dosya sayısı ~15'i geçtiği için iş ikiye bölündü;
ikinci yarının 29 ekranı (`add_erp_invoice`, `add_erp_late_fee_invoice`,
`add_erp_return`, `edit_erp_expense`, `edit_erp_invoice`,
`edit_erp_purchase_order`, `edit_erp_quote`, `edit_erp_waybill`,
`erp_account_prices`, `erp_account_sync`, `erp_accountant`, `erp_accounts`,
`erp_accounts_import`, `erp_bank_statement`, `erp_bank_statements`,
`erp_cheque`, `erp_expense_categories`, `erp_export`, `erp_inbox`,
`erp_inbox_document`, `erp_invoices`, `erp_order_flow`, `erp_receipt`,
`erp_reconciliation`, `erp_settings`, `erp_stock_count`,
`get_erp_invoice_pdf`, `get_erp_reconciliation_pdf`, `get_erp_waybill_pdf`)
testte bekleyenler listesinde. Satır alanlarının içindeki diziler
(`lines[0][description][]`) ve eşlemenin iç içe `choice[x][]`'i ile e-belge
ayarlarının Ayarlar ekranındaki kaydı (`includes/settings/commerce.save.php`)
değişmedi. Kasa satışında ürün eklemenin ziyaretçi satırı uyarısını (#437'nin
kalanı) #444 giderdi.

---

## 2026.4.10 — Güncelleme ekranının yedek boyutu tahmini satırsız tabloları saymıyor (2026-10-14)

**Neden.** #434 ve #438'in kalanı. Güncelleme ekranının "Başlamadan önce"
kartındaki "Veritabanı yedeği" satırı bütün veritabanının boyutunu
(`information_schema`'daki veri + indeks toplamı) gösteriyor ve boş alan
uyarısını (boş alan < 2 × boyut) da ona göre veriyordu. Yedek artık yedi
tabloyu satırsız döktüğü için yoğun bir sitede tahmin yedeğin birkaç katı
çıkıyor, disk yettiği hâlde "yeterli boş alan olmayabilir" uyarısı
görünebiliyordu.

**Ne değişti.**
- `includes/fn/backup.php`: `pg_backup_estimate_bytes($table_bytes)` (saf;
  tablo → bayt dizisinden `pg_backup_dump_settings()`'in `no-data` ve
  `exclude-tables` listelerindeki tabloları düşer) ve
  `pg_backup_database_size()` (`information_schema`'dan tablo başına
  boyutu okur, `database` ve `backup` döner). Liste kopyalanmadı:
  Mysqldump'a verilen ayarın kendisinden okunuyor, ileride
  `pg_backup_rowless_tables()`'a eklenen tablo tahminden de kendiliğinden
  düşer. `visitors_innodb`/`visitors_myisam` (taşıma kopyaları, hiç
  dökülmez) de sayılmıyor.
- `includes/migrations/runner.php` (`install_preflight_checks()`): satırın
  değeri ve boş alan karşılaştırması yeni tahmini kullanıyor; 2 katsayısı
  aynı. Düşülen kısım 1 MB'ı geçince satırın altında "Yedeğe girmeyen
  ziyaret, görüntüleme ve istek kayıtlarının satırları hariç. Veritabanının
  tamamı … ." yazıyor (Sistem Durumu ve pano veritabanının tamamını
  gösterdiği için iki rakamın neden farklı olduğunu söylüyor). Sonuca
  `backup_bytes` eklendi, `database_bytes` veritabanının tamamı olarak
  kaldı.
- Tahmin yapan başka ekran yok: Yedekleme Yöneticisi, Dosya Yöneticisi ›
  Yedeklemeler ve Ayarlar › Genel › Yedeklemeler yalnız diskteki yedeklerin
  gerçek boyutunu gösteriyor; Sistem Durumu ve panonun depolama kartı
  (`pg_storage_usage()`) yedek değil veritabanı boyutu olduğu için
  değişmedi.
- `tests/backup_test.php`: satırsız ve atlanan her tablonun tahminden
  düştüğü, kalanların (string boyut dahil) sayıldığı test.

**Ölçüm (sandbox, MariaDB 10.11).** Yeni kurulum: veritabanı 17 MB, tahmin
15 MB, gerçek `sql.sql` 1,5 MB. 150.000 ziyaret, 50.000 saatlik sayfa
görüntüleme, 80.000 form öğesi görüntüleme, 40'ar bin güvenlik duvarı,
performans ve API isteği ile 20.000 site günlüğü satırı eklenince:
veritabanı 165 MB (kart önce bunu gösteriyordu), tahmin 20 MB, gerçek
`sql.sql` 4,8 MB. Tahmin diskteki boyuttur (boş InnoDB tablosu da en az
16 KB, indeksler dahil), döküm her zaman ondan küçük; boş alan payı bu
yüzden değiştirilmedi.

**Bilerek yapılmayan.** Şema adımı yok. Tahmin `sql.sql`'in gerçek boyutuna
göre yeniden ölçeklenmedi (katsayılara dokunulmadı).

---

## 2026.4.10 — Ziyaret kaydı olmayan istekte para birimi seçicisi ve sepetin ilk satırı uyarı yazmıyor, para birimsiz istek geri dönüyor (2026-10-14)

**Neden.** #431 ve #437'nin "Bilerek yapılmayan" maddeleri. Ziyaret kaydı
(`visitors` satırı, oturumda `$_SESSION['software']['visitor_id']`) yalnız
bir site sayfası açılınca `get_page.php` `init_tracking()` ile açılıyor;
botlar hiç kayıt almıyor (#426). Kaydı olmayan iki yol o kaydı güncellemeye
çalışıyordu:

- `update_currency.php:48` (para birimi seçicisinin formu): ziyaretçi takibi
  açıkken ziyaret kaydı olmayan istek (bot, önce sayfa açmadan gelen POST)
  "Undefined array key visitor_id" uyarısı yazıyordu. Var olmayan bir
  `currency_id` ayrıca satır 37'de "Trying to access array offset on null"
  yazıyordu.
- `includes/fn/ecommerce.php:4529` `initialize_order()` (sepetin ilk
  satırında siparişi açan fonksiyon): kasa satışında (`add_order.php`)
  site sayfası açmamış personel ilk ürünü ekleyince aynı uyarı. Botun
  sepete ürün eklemesi de aynı yoldan geçiyordu.

İki sorguda da `WHERE id = ''` hiçbir satıra dokunmuyordu; uyarıdan başka
etkisi yoktu. Ayrıca `update_currency.php` para birimi gönderilmeyince
(boş, eksik ya da dizi `currency_id`) yönlendirme `if` bloğunun içinde
kaldığı için boş 200 sayfa veriyordu. Hepsi 2026.4.9'da da var.

**Ne değişti.**

- İki `UPDATE visitors` yalnız `!empty($_SESSION['software']['visitor_id'])`
  iken koşuyor (`VISITOR_TRACKING` koşulunun yanına). Kayıt yoksa kayıt
  açılmıyor, sorgu atlanıyor; sipariş ve para birimi eskisi gibi yazılıyor.
- `update_currency.php` para birimi kodunu `$row['code'] ?? ''` ile okuyor;
  var olmayan para birimi eskisi gibi oturuma yazılıyor (`init.php` onu
  bulamayınca ana para birimini kullanıyor), uyarı yok.
- `update_currency.php`'nin yönlendirmesi bloğun dışına çıktı: para birimi
  olsun olmasın ziyaretçi #431'in `pg_safe_redirect_path()` ile denetlenen
  `send_to`'ya, yoksa ana sayfaya dönüyor. Para birimi olmayan istek
  oturuma ve veritabanına eskisi gibi dokunmuyor.

Yakın çevrede aynı türden başka okuma yok (`ecommerce.php` ve
`update_currency.php`'de başka `visitor_id` okuması yok). Sipariş akışı
ekranlarındaki (`shopping_cart`, `billing_information`, `submit_order`,
`express_order`, `order_history_*`, `retrieve_order`) ve `custom_form`
`visitor_id` okumaları kapsam dışı (devir notu).

Şema, dil dosyası ve JS değişikliği yok. Test:
`tests/visitor_record_optional_test.php` (iki dosyadaki her
`UPDATE visitors` ziyaret kimliğini denetleyen bir `if` altında;
yönlendirme dosyanın en üst düzeyinde; eski kodda iki test de düşüyor).

**Doğrulandı (sandbox, MariaDB, PHP 8.3).** Eski kod (`origin/development`)
ikinci portta, aynı veritabanı. Ziyaret kaydı olmayan oturumla (panel giriş
sayfasından jeton) `update_currency.php`'ye yedi POST: eski kod üç
"visitor_id" ve bir "array offset on null" uyarısı, para birimsiz, boş ve
dizi `currency_id`'de boş 200 sayfa; yeni kod uyarı yok, yedisi de 302
(`send_to=/shop` → `/shop`, `send_to` yok → `/`, `@evil.com/x` → `/`).
Tarayıcı kullanıcı ajanıyla önce ana sayfa açılınca iki kodda da ziyaret
satırının `currency_code`'u GBP oluyor. Kasa satışında site sayfası açmamış
yöneticiyle ürün ekleme: eski kod `ecommerce.php:4529` uyarısı, yeni kod
yok; JSON cevabı (satır numarası dışında) aynı. Önce ana sayfa açılınca iki
kodda da ziyaret satırına `order_id` ve `order_created = 1` yazılıyor.
`tools/lint.php`, `tools/test.php`, `check_lang`, `check_bindings`,
`check_api_schema`, `check_copies`, `build_js.php --check` temiz.
Doğrulanamadı: dev.pinegrap.com.

---

## 2026.4.10 — `h(null)` ve `escape(null)` kullanım dışı satırı yazmıyor (2026-10-14)

**Neden.** #417 panel taramasının K9 maddesi. Hata ayıklama açıkken
`init.php`'nin dışarıda bıraktığı kullanım dışı uyarıları da
`php_errors.log`'a yazılıyor (`pg_error_record()`: "sonraki PHP sürümünün
kıracağı liste"). Taramada en sık satır `includes/fn/core.php`'deki `h()`
idi: `htmlspecialchars()`'a `null` verince PHP 8.1'den beri "Passing null
to parameter #1 ($string) of type string is deprecated" düşüyor. Çağıranların
başında `liveform.class.php` geliyor (`prepare()` 1376 ve `output_field()`
342/567: değeri NULL sütundan gelen alan); 404 "Kayıt bulunamadı" ekranı,
`view_countries`, `view_styles`, `add_container` gibi listeler ve yönetici
oturumuyla açılan site sayfaları da aynı satırı yazıyordu. Günlük dosya:satır
başına istekte bir kez yazdığı için her istekte yalnız ilk çağıran
görünüyordu. `escape()` (`mysqli_real_escape_string()`) aynı durumdaydı
(`add_menu_item`, `preview_form`, `print_packing_slip`, `generate_layout`,
`includes/fn/forms.php` ve `ecommerce.php` üzerinden). PHP 9'da bu çağrılar
`TypeError` olacak. Hepsi 2026.4.9'da da var.

**Ne değişti.** `h()` ve `escape()` `null` alınca hemen `''` döndürüyor.
Dönen değer değişmedi: `htmlspecialchars(null)` ve
`mysqli_real_escape_string(null)` de `''` veriyordu. Dizi eskisi gibi
`TypeError` veriyor (dizi parametre serisinin "verilmedi" kuralı çağıran
ekranda uygulanıyor; burada susturulmadı). Sayı ve mantıksal değer eskisi
gibi metne çevriliyor. `liveform.class.php`'ye dokunmak gerekmedi: oradaki
bütün `h()` çağrıları bu yoldan geçiyor. Şema, dil dosyası ve JS
değişikliği yok. Test: `tests/core_test.php`
`test_core_h_and_escape_take_null` (eski kodda `ErrorException`, yenide
geçiyor).

K9'un ikinci maddesi `includes/fn/custom_form.php` #407 ve #421'de kapanmıştı;
bu turda yeniden denendi, kalan uyarı yok (aşağıda).

**Doğrulandı (sandbox, MariaDB, PHP 8.3, `debug = 1`, development 569eac3c
üstünde).** #417'nin tarayıcısı (367 panel ekranı, 6.760 GET, düz açılış, kendi parametreleri ve her
parametre dizi olarak) eski ve yeni kodla: `h()` / `escape()` kullanım dışı
satırı yazan istek 3.349 → 0 (183 ekranda), herhangi bir satır yazan istek
3.403 → 678, toplam satır 5.564 → 2.121; durum kodu farkı yok, 500 yok. 181
site sayfası oturumsuz ve yönetici oturumuyla (düzenleme kipi): satır yazan
istek 150 → 5 (yöneticide sayfa başına bir `h(null)` satırı gidiyor). Özel
formların 14'ü `custom_form.php`'ye yalnız jetonla, gizli alanlarla ve
bütün alanları boş gönderildi (96 istek, yönetici ve oturumsuz): yeni kodda
hiç satır yok, durum ve yönlendirmeler eskisiyle aynı. HTML: aynı sunucuda
yalnız `core.php` değiştirilerek 1.260 istek (site sayfaları iki oturumla,
panel ekranlarının düz ve parametreli 200 açılışları); sayılar ve göreli
zamanlar ayıklanınca 1.254'ü bire bir aynı, kalan 6'sı canlı veri gösteren
ekranlar (oturumlar, Site Günlüğü, performans günlüğü, veritabanı motoru,
Hesabım'daki cihaz listesi). `tools/lint.php`, `tools/test.php`,
`check_lang`, `check_bindings`, `check_api_schema`, `check_copies` ve
`build_js.php --check` geçti. Doğrulanamadı: dev.pinegrap.com.

**Bilerek yapılmayan.** Aynı türden kalan kullanım dışı satırları başka
yardımcılarda ve hepsi eksik parametreyle açılışta: `encode_url_path()`
(`rawurlencode(null)`; `browse_catalog_menu.php:27`,
`add_email_campaign.php:44`), `escape_javascript()` (`preview_form.php:79`),
`escape_csv()` (`view_email_campaign_profiles.php:229`); çağıran ekran
düzeldiğinde gider. q10 (`liveform::prepare()`'de `type` özniteliği olmayan
input ve `value`'su olmayan onay kutusu) ayrı iş, bu satırlara dokunulmadı.

---

## 2026.4.10 — ERP dışındaki ekranlar dizi gelen `?id`'yi 1 numaralı kayıt saymıyor (2026-10-14)

**Neden.** #436'nın kalanı. ERP dışındaki 19 kök ekranda `(int) $_GET['id']`
okuması var. Dokuzu (#422 / #424'ün kapısından sonra okuyan
`edit_calendar_event`, `edit_product_group`, `edit_submitted_form`,
`edit_user`, `view_menu_items`, `view_order`, `view_visitor_report`; kendi
`is_scalar` / `is_string` denetimi olan `get_account_document`,
`workspace_template`) zaten güvenli. Kalan onunda kapı yoktu ve `(int)`
dizide 1 verdiği için `?id[]=x` 1 numaralı kaydı kullanıyordu: `edit_offer`
1 numaralı teklifi düzenlemeye açıyor, `duplicate_offer` onu çoğaltıyor
(`edit_offer?duplicate=1`), `login_as_user` 1 numaralı kullanıcı olarak
oturum açıyor (rol denetimleri yine geçerli), `get_seo_analysis` 1 numaralı
ürünün / sayfanın SEO panelini veriyor; Hesabım'ın sipariş ekranı
(`get_view_order_screen_content`) ve `order_history_retrieve_order` /
`_reorder` / `_delete_order` müşterinin 1 numaralı siparişini gösteriyor,
sepete alıyor, yeniden sipariş ediyor ya da siliyordu (sandbox'ta
`order_history_delete_order?id[]=x` 1 numaralı siparişi sildi; sahiplik
denetimi yine geçerli). `duplicate_style` ve takvim etkinliği sayfasının
iCalendar indirmesi (`get_page.php`) diziyi önce `escape()`'e verdiği için
500 veriyordu. Hepsi 2026.4.9'da da var.

**Ne değişti.** Serinin kuralı: dizi id "verilmedi" sayılır, normal isteğin
çıktısı değişmez. Kayıt ekranları #424'ün "Kayıt bulunamadı." 404'ünde
duruyor: `duplicate_offer`, `duplicate_style` ve `edit_offer` (yalnız dizi
gelince; `edit_offer` id'siz eskisi gibi yeni teklif açıyor, `?duplicate[]`
verilmemiş sayılıp boş yeni teklif). Kendi "bulunamadı" yanıtı olan
ekranlar id'yi `is_scalar($_GET['id'] ?? null) ? (int) $_GET['id'] : 0`
ile okuyor ve id'siz açılıştaki yanıtı veriyor: `login_as_user` ("Kullanıcı
bulunamadı."), `get_seo_analysis` ("bu kaydı bulamadık"),
`get_view_order_screen_content` ("Sipariş bulunamadı."), üç
`order_history_*` ("The order could not be found."). iCalendar indirmesi
dizi id'de etkinliği aramıyor ("etkinlik bulunamadı" 404). Şema, dil
dosyası, JS değişikliği yok. `tests/panel_id_gate_test.php` genişledi: ERP
dışındaki her kök ekranda `(int)` ile okunan `$_GET['id']`'den önce (aynı
satırda ya da ekranın kapısında) bir `is_scalar` / `is_string` denetimi
olmalı (eski kodda on dosyada 15 okuma yakalıyor; ERP ekranları
`tests/erp_request_values_test.php`'de).

**Doğrulandı (sandbox, MariaDB, PHP 8.3, DEBUG açık).** Eski kod
(`origin/development`) 8001'de, yeni 8000'de, aynı veritabanı; teklif,
kullanıcı, iki sipariş ve ürün için 1 numaralı kayıt kopyalandı. Normal
istekler (yeni / var olan / var olmayan teklif, `duplicate=5`, çoğaltma
yönlendirmesi, `get_seo_analysis` ürün 29, `login_as_user?id=1`, Hesabım
sipariş 1 ve 2, iCalendar yayımlanmamış etkinlik, `reorder?id=1`, var
olmayan sipariş silme) iki sunucuda aynı yanıt. Dizi id: eski kod 1
numaralı teklifi açtı, `duplicate=1`'e yönlendi, "birinci" kullanıcısıyla
oturum açtı, 1 numaralı ürünün SEO panelini, 1 numaralı siparişi gösterdi,
siparişi sepete aldı ve sildi; `duplicate_style` ile iCalendar 500. Yeni kod
hepsinde bulunamadı yanıtı, oturum yönetici olarak kaldı, sipariş yerinde.
Değişen ekranlarda yeni uyarı satırı yok. `tools/lint.php`, `tools/test.php`,
`check_lang`, `check_bindings`, `check_api_schema`, `check_copies` ve
`build_js.php --check` geçti. Doğrulanamadı: dev.pinegrap.com'daki gerçek
veriler.

**Bilerek yapılmayan.** Jetonlu on eylem ekranı `?id[]=` ile hâlâ 500
veriyor (`escape()` diziyi kabul etmiyor; `(int)` okuması yok, 1 numaralı
kaydı kullanmıyorlar): `duplicate_calendar_event`, `duplicate_common_region`,
`duplicate_designer_region`, `duplicate_email_campaign_profile`,
`duplicate_menu`, `duplicate_page`, `duplicate_product`, `optimize`,
`remove_recipient`, `send_email_campaign`; ayrı iş olarak kuyrukta. Hesabım
sipariş ekranı gerçek bir siparişte normal açılışta beş tür uyarı yazıyor
(`$recurring_products_exist`, `$applied_offers`, `$output_recurring_products`,
oturumda `ecommerce` anahtarı); eski kodda da aynı, burada değişmedi.

---

## 2026.4.10 — Büyük yedek kurulum ekranından adım adım geri yükleniyor, yarım kalan geri yükleme sürdürülüyor (2026-10-14)

**Neden.** Bir müşteri sitesinde yanlışlıkla silinen ürün için 950 MB'lık
veritabanı yedeği geri yüklenemedi: kurulum ekranı "sunucu yanıt veremedi"
dedi. Ziyaretçi temizleme planının "Ertelenenler" 8. maddesi
(`docs/_plan_ziyaretci_temizleme.md`).

**Kök neden.** "Sunucudaki yedek" seçeneği bütün `sql.sql`'i tek istekte
çalıştırıyordu (`parse_mysql_dump()`). Önce sitenin tablolarını siliyor,
sonra dökümü yüklüyordu; yükleme web sunucusunun ya da vekil sunucunun
istek süresini (IIS/FastCGI, Cloudflare ~100 sn) aştığında istek kesiliyor,
site yarım veritabanıyla kalıyordu. Sandbox'ta 1,3 GB'lık döküm tek istekte
83 sn sürdü. İkinci bir sorun: `currency_rates` `get_tables()` listesinde
yoktu, Yedekleme Yöneticisi'nin dökümü `DROP TABLE` yazmadığı için güncel
bir sitenin yedeği "Table 'currency_rates' already exists" ile duruyor ve
site yine yarım kalıyordu.

**Nasıl çalışıyor (`includes/fn/backup_restore.php`).**
- Döküm sitenin yanına yüklenir: her tablo ifadesi (`CREATE`, `INSERT`,
  `ALTER`, `LOCK`, `DROP`, `TRUNCATE`, `REPLACE`) `pgr_new_<tablo>`'ya
  yöneltilir. Site yükleme boyunca kendi tablolarıyla çalışır.
- Bir adım en çok 20 sn ifade çalıştırır ve bir ifadenin sonunda durur;
  kaldığı bayt, adım sayısı, o anki tablo ve tablonun `CREATE TABLE`
  ofseti `data/temp/restore.json`'da. Adım sonunda `COMMIT` ve
  `UNLOCK TABLES`; sonraki istek dökümün oturum ayarlarını (`SET ...`,
  en çok 64, son hali) ve tutulan `LOCK TABLES`'ı yeniden kurar. Bağlantının
  oturum değişkenleri her adımın çevresinde saklanıp geri konur (döküm
  `autocommit=0` bırakıyordu; Site Günlüğü satırı geri alınıyordu).
- Ekran aynı formu `restore_id` ile yeniden gönderir; konsol ve ilerleme
  dosyası aynı kalır, önceki adımların satırları korunur.
- Yükleme bitince tek `RENAME TABLE` ile site tabloları `pgr_old_`'a,
  yüklenenler yerine geçer (hepsi ya da hiçbiri); sonra eskiler ve yedekte
  olmayan sistem tabloları (`get_tables()`) silinir. Ardından eskisi gibi
  utf8mb4 dönüşümü ve sürüm yükseltmesi; dönüşüm artık yalnız karakter seti
  farklı tabloları alır (büyük tabloyu boşuna kopyalamıyor).
- Sunucu bir adımı öldürürse (`running` işareti kalmış) sonraki adım
  bilinmeyen yeni tabloları ve o tabloyu silip tabloyu `CREATE TABLE`'ından
  yeniden yükler, adım süresini yarıya indirir (en az 4 sn) ve ekrana uyarı
  satırı yazar.
- Bir veritabanına aynı anda tek geri yükleme (`GET_LOCK`). Kurulum
  ekranının kilit açma süresi her adımda yenilenir.
- Sitenin tablolarına dokunabilecek ifadeler (`UPDATE`, `DELETE`, görünüm,
  tetikleyici, başka veritabanı adı, birden çok tablolu `DROP`/`LOCK`,
  `RENAME`) reddedilir; `USE`, `CREATE DATABASE`, `GLOBAL`/`SQL_LOG_BIN`
  ayarları ve MariaDB'nin sandbox yorumu atlanır. Reddedilen ya da hata veren
  ifadede yüklenen kısım silinir, site değişmez, Site Günlüğü'ne yazılır.
  `config` tablosu olmayan döküm site yedeği sayılmaz.

**Ne değişti.**
- `install/index.php`: tablo silme + `parse_mysql_dump()` bloğu adım adım
  yüklemeyle değişti; `parse_mysql_dump()` kaldırıldı (başka kullanan yok).
  Yarım kalan geri yükleme varsa kaynak adımında kart (yedek, yüzde, boyut,
  son adım) ve **Sil** düğmesi; form o yedekle ve `restore_id` ile açılır,
  kurulumu başlatmak kaldığı yerden sürdürür. `get_tables()`'a
  `currency_rates` eklendi. Başarıda geri yüklenen sitenin günlüğüne
  "<yedek> yedeği geri yüklendi: <boyut>, <n> adımda, <sn> saniye".
- `backups.php`: Yedekleme Yöneticisi yarım kalan geri yüklemeyi uyarı
  olarak gösterir, kurulum ekranına bağlantı verir.
- `tests/backup_restore_test.php`: ifade sınıflandırması (mysqldump.php,
  mysqldump komut satırı, phpMyAdmin biçimleri), reddedilen ifadeler, ad
  değiştirme, oturum takibi, eşleşme ve yüzde.

**Ölçüm (sandbox, MariaDB 10.11, PHP 8.3, 4 çekirdek).** 3.003.221 ziyaret
satırlı, 301 tablolu 1,3 GB döküm (#434'ten önceki biçim, en kötü durum):
- Önce: tek istek 83,1 sn (yükleme 79,6 sn); `currency_rates`'li güncel
  yedekte hata ve yarım site.
- Sonra: 5 istek (4 × 20 sn + 14 sn), toplam 94,6 sn; yükleme sırasında ana
  sayfa ~44 ms'de 200; site tabloları yer değiştirmeye kadar değişmedi;
  yer değiştirme ve eskilerin silinmesi ~3,4 sn.
- mysqldump komut satırı dökümü (1,4 GB): 5 istek, 87,3 sn.
- Chromium: ekran 5 isteği kendisi gönderdi (87,5 sn); 2 adımdan sonra
  kapatılan pencere yeni tarayıcıda karttan sürdürüldü ve bitti.
- Adımın ortasında PHP işçisi öldürüldü: sonraki istek uyardı, `visitors`'ı
  10 sn'lik adımlarla baştan yükledi; 9 istek, 3.003.221 tekil kimlik,
  artık tablo yok.
- `UPDATE` içeren bozuk yedek: reddedildi, site değişmedi, yeni tablolar
  silindi, Site Günlüğü'ne satır yazıldı. **Sil** düğmesi: tablolar silindi,
  günlük satırı ve bildirim.
- Başlangıç sitesi (`turkish_default`) 1 istek 10,1 sn; boş veritabanına
  kurulum ve #434/#438 biçimli yedek (başında not, satırsız tablolar)
  1 istekte geri geldi, 301 tablo.

**Bedeli.** Yükleme boyunca veritabanında sitenin bir kopyası daha kadar boş
yer gerekir. Toplam süre tek isteğe göre ~%10-15 uzun (adım sınırları ve
yer değiştirme).

**Bilerek yapılmayan.** Şema adımı yok. Yedek alma tarafına dokunulmadı
(ziyaret satırları #434/#438'de). Görünüm, tetikleyici ve yabancı anahtar
içeren dökümler desteklenmiyor (ürünün dökümü yazmıyor). Eskiden kalan
davranışlar: geri yükleme kurulum olarak çalıştığı için formdaki yönetici
yeniden ekleniyor, `data/backups`'taki zip arşivleri kurulum ekranında
listelenmiyor, kurulum ekranının saati sunucu saat diliminde.

---

## 2026.4.10 — Sipariş akışı: kargo yöntemi, sepete ekle formları, sepet ve hızlı siparişin ürün alanları dizi gelen değerde 500 veriyordu (2026-10-14)

**Neden.** #421'in taramadığı kalanlar: kargo yöntemi adımı
(`shipping_method.php`), ürün sayfasının ve sipariş formu sayfasının
"Sepete Ekle" formları (`catalog_detail.php`, `order_form.php`), sepet
sayfası (`shopping_cart.php`) ve hızlı siparişte her kalemin yinelenen
ödeme, hediye çeki ve ürün (sipariş kalemi) formu alanları
(`express_order.php`). Sandbox'ta eski kodla yeniden üretildi: kargolu
ürün, bağış (müşterinin düzenlediği yinelenen ödemeli), hediye çeki ve
ürün formlu etkinlik kaydıyla; on akışta formun her alanı tek tek dizi
olarak (`alan[]=…`), toplam 115 istek. 45'inde HTTP 500, 5'inde uyarı.
Hepsi 2026.4.9'da da var.

Görünür hatalar (500, hepsi `TypeError`):

- Değer `escape()`'e dizi olarak gidiyordu: kargo yöntemi adımında
  `shipping_method`, `ship_to_id`, `next_page_id`; ürün sayfasında ve
  sipariş formunda `product_id` ve `page_id`; sepette `page_id` ve
  indirim kodu; sepette ve hızlı siparişte hediye çekinin "kimden" ve
  mesaj alanları ile yinelenen ödemenin dönemi ve ödeme sayısı.
- Doğrulayıcılarda (`preg_match()`): ürün sayfasında, sipariş formunda,
  sepette ve sepetteki hızlı eklemede adet; hediye çekinin alıcı
  e-postası ve teslim tarihi; yinelenen ödemenin başlangıç tarihi; ürün
  formunun e-posta alanı.
- Alıcı adında (`mb_strtolower()`): ürün sayfasının, sipariş formunun ve
  sepetteki hızlı eklemenin `ship_to` / `add_name` alanları.

Görünmeyen uyarılar: dizi gelen bağış tutarı ve sepetteki yinelenen
ödeme dönemi "Array to string conversion" yazıyordu.

**Ne değişti.** #407 ve #421'in kuralı: dizi gelen tek değerli alan
"verilmemiş" sayılır, sonrası boş alanla aynı (zorunluysa sayfa "gerekli"
uyarısıyla geri döner).

- `shipping_method.php` `ship_to_id`, `page_id` ve `next_page_id`'yi ilk
  sorgudan önce yalnız metinse okuyor (dizi alıcı bulunamadı hatasına
  düşüyor); `shipping_method` `pg_liveform_single_values()`'tan geçiyor.
  Yöntem seçilmeden gönderilen formun uyarısı çeviriden geçmiyordu
  (Türkçe sitede İngilizce "Please select a shipping method." çıkıyordu);
  artık `lang()` ile, `tr.json`'daki mevcut anahtarla çıkıyor.
- `catalog_detail.php` formun tüm alanlarını, `order_form.php` sayfa,
  açılır liste düzeninin ürün ve adedi ile alıcı alanlarını, liste
  düzeninde de her ürünün onay kutusu, adet, bağış tutarı ve yinelenen
  ödeme alanlarını `pg_liveform_single_values()`'a veriyor. Açılır liste
  düzeninde ürünü bulunamayan istek (dizi ya da bilinmeyen id) satırı
  olmayan ürünü okumuyor; ürün "şu anda mevcut değil" sayılıyor.
- `shopping_cart.php` indirim kodunu, çevrimdışı ödeme anahtarını ve
  hızlı ekleme alanlarını `pg_liveform_single_values()`'tan geçiriyor;
  `page_id` ve hızlı ekleme alanlarını `express_order.php` gibi yalnız
  metinse okuyor. Adedi ya da bağış tutarı dizi gelen kalem olduğu gibi
  kalıyor, ama yinelenen ödeme, hediye çeki ve ürün formu alanları yine
  denetleniyor. Sepet sayfası satırı olmayan `page_id`'de (dizi ya da
  bilinmeyen id) satırı okumuyor; davranış eskisi gibi (etiketsiz, sonraki
  sayfasız devam).
- `shopping_cart.php` ve `express_order.php` her kalemin yinelenen ödeme
  alanlarını ve her hediye çekinin dört alanını `pg_liveform_single_values()`'a
  veriyor; ürün formu alanları `submit_custom_form()` gibi yeni
  `pg_cf_keep_posted_value()`'dan geçiyor (alanlar `multiple` sütunuyla
  okunuyor): onay kutusu grubu ve çoklu seçim listesini tutuyor, öbür her
  alanda dizi boş metin oluyor. `submit_custom_form()` aynı yardımcıyı
  kullanıyor; davranışı değişmedi.

Uyarı susturulmadı. Ödeme ağ geçidinden dönüş durdurulmuyor (#430'un
kararı); bu değişiklikler yalnız diziyi boşaltıyor. `liveform.class.php`'ye,
başarılı bir siparişin yazdığına, şemaya, dil dosyasına ve JS'e
dokunulmadı.

Davranış farkı (yalnız elle kurulmuş istekte): ürün formunun zorunlu metin
ve tek seçimli liste alanı dizi gelince eskiden her eleman ayrı satır
olarak kaydedilip ödemeye geçiliyordu; artık sayfa "gerekli" uyarısıyla
geri dönüyor (taramada 12 istek). Sipariş formunda onay kutusu ürünü
`product_<id>[]=1` ile eskiden sepete ekleniyordu; artık seçilmemiş
sayılıyor.

**Bilerek yapılmayan.** Görsel editörün sepet widget'ının betiği
(`cart_action.php`) ve panelin `add_to_cart` API eylemi taranmadı. #421'den
kalan iki uyarı (`forms.php`'de her siparişte `$valid_tax_zone_id`,
`get_express_order.php`'de selamlaması ya da soyadı boş alıcıda `name`)
başka dosyalarda olduğu için yerinde. Önceden var olan, diziyle ilgisiz
uyarılar: örnek verinin sepet ve hızlı sipariş düzenleri (`data/layouts`
248 ve 256) bir alan hatasıyla geri dönülen her sayfada; hızlı siparişin
makbuz e-postası hazırlanırken `get_order_receipt.php` 2262–2267;
ziyaretçi kaydı olmayan istekte (bot) `visitor_id` (`ecommerce.php`,
`shopping_cart.php`, `submit_order.php`). Sepet, ürün sayfası ve sipariş
formu bilinmeyen `page_id`'de ayarsız devam ediyor (#430 yalnız üç
sipariş adımını kapattı); dizi `page_id` şimdi aynı yere düşüyor. Dizi
bekleyen yerde tek metin gelmesi (`quantity=5`) ele alınmadı.

**Doğrulandı (sandbox, MariaDB, PHP 8.3).** Eski kod `origin/development`
worktree'sinden ikinci portta sunuldu; iki tarama aynı veritabanında
koştu. 115 isteğin 45'i eskide 500, yenide 500 yok; uyarı yazan istek
eskide 50, yenide 0 (hızlı siparişin her makbuzundaki `get_order_receipt.php`
uyarısı dışında, o normal siparişte de var). Ek olarak sepetteki hızlı
eklemenin dört alanı ve açılır liste düzenli sipariş formunun dört alanı
(yönetici girişiyle) eskide 500, yenide değil. "Gerekli" uyarıları
sayfada görüldü (alıcı e-postası, ürün formu alanı, dönem, başlangıç
tarihi, kargo yöntemi, adet, ürün). Normal akışlar iki kodla aynı
veritabanından tamamlandı: altı sepete ekleme yolu, hızlı sipariş (bağış,
yinelenen bağış, hediye çeki, ürün formlu etkinlik) ve klasik ödeme
(kargolu ürün, ürün formu, hediye çeki, bağış; kargo adresi, kargo
yöntemi, fatura, önizleme). 35 kayıt (sayfalar — rastgele CAPTCHA, `?r=`
kodu, kimlikler ve sipariş numarası dışında —, POST sonuçları, `orders`,
`order_items`, `form_data`, `order_item_gift_cards`, `ship_tos`
satırları) bire bir aynı. `tests/order_form_array_params_test.php` eski
kodda 1 hata ve 1 başarısızlık, yenide geçiyor. `tools/test.php`, lint,
dil, kopya, bağlama, API şeması ve `build_js.php --check` geçti.
Doğrulanamadı: makbuz e-postaları ayrıca karşılaştırılmadı (satırlar
aynı); dev.pinegrap.com'daki gerçek ödeme adımları ve ödeme ağ geçitleri.

---

## 2026.4.10 — ERP'de dizi gelen kayıt numarası, sayfa imleci, ekstre satırı, paket ve gün sayısı 1 sayılmıyor (2026-10-14)

**Neden.** #436'nın ikinci yarısı. ERP ekranlarında `$_GET` / `$_REQUEST`'ten
gelen sayıların kalanı hâlâ çıplak `(int)` ile okunuyordu; dizi gelince PHP
uyarısız 1 veriyor, günlükte iz kalmıyordu. #422'nin testi yalnız
`$_REQUEST['id']`'yi yakaladığı için `$_GET['id']` okuyan yedi ekran
kapısızdı. Hepsi 2026.4.9'da da var.

- `?id[]=x` 1 numaralı kaydı açıyordu: `edit_erp_account` (cari),
  `edit_erp_till` (kasa), `get_erp_drawer` (listelerin kenar çekmecesi),
  `get_erp_quote_pdf`, `get_erp_purchase_order_pdf`, `get_erp_edoc_document`
  (e-belgenin sağlayıcı kopyası), `get_erp_inbox_file` (gelen e-fatura).
- Diğer sayılar: `erp_audit` `id` (iz 1 numaralı kayda süzülüyordu) ve
  `before` (boş sayfa), `erp_bank_statement` `line` (1 numaralı satır açık),
  `erp_accountant` `download` (1 numaralı paket iniyordu), `reply_days`
  (`erp_reconciliation`, `get_erp_reconciliation_pdf`,
  `erp_reconciliation_options()`; mektup "1 gün içinde" diyordu).
- Liste elemanları: minimum stok ekranından açılan satın alma siparişinde
  `products[]` içindeki dizi 1 numaralı ürünü satıra koyuyordu; karttan
  satır seçerek açılan iadede `qty[<satır>][]` miktarı 1 yapıyordu.

**Ne değişti.** Serinin kuralı: dizi gelen tek değer "verilmedi" sayılır,
ekran parametresiz açılışla aynı davranır, normal isteğin çıktısı değişmez.
Okumalar #422/#436'daki satırla aynı:
`is_scalar($_GET['x'] ?? null) ? (int) $_GET['x'] : 0` (`reply_days` için
varsayılan 7, `download` için "indirme yok"). Liste elemanında
`is_scalar()`: satın alma siparişi dizi elemanı atlıyor
(`array_filter(..., 'is_scalar')`, ayrıca
`erp_purchase_order_rows_for_products()`), iade o satırı "iade edilmedi"
sayıyor. Hepsi GET okuması; bir şey yazılmadığı için form durdurulmuyor.
Yeni yardımcı yok.

`tests/erp_request_values_test.php`: çıplak `(int)` testi ad süzgecini
bıraktı, artık ERP ekranlarında `$_GET` / `$_REQUEST`'ten her `(int)` ya da
`(float)` okumasını yakalıyor (eski kodda 13 okuma); adresten alınmış listenin
elemanını çıplak döküm de yakalanıyor (eski kodda `add_erp_return`); dizi
`reply_days` 7; dizi elemanlı ürün listesi satır vermiyor.

Şema, dil dosyası ve JS değişikliği yok. ERP POST işleyicileri (`$_POST`)
ayrı işte; `erp_reconciliation`'ın `$_REQUEST['reply_days']` satırı GET ve
POST'ta ortak olduğu için burada. ERP dışındaki 19 kök ekranın
`(int) $_GET['id']` okumaları kapsam dışı (devir notu).

**Doğrulandı (sandbox, MariaDB, PHP 8.3, `debug = 1`).** Cari, kasa, fatura
(satırlarıyla), teklif, satın alma siparişi, gelen e-fatura, muhasebeci
paketi (dosyasıyla), banka ekstresi satırı, denetim izi ve ürün için 1 ve 2
numaralı kayıtlar; eski kod (`origin/development`) ikinci portta, aynı
veritabanı. 17 durum; her biri iki kodda normal, dizi ve parametresiz,
eski kodda ayrıca 1 numaralı kayıtla açıldı (119 istek): 17 normal ve 17 parametresiz istek eski kodla aynı HTML ve
durum kodu; 17 dizi isteğinin hepsi yeni kodda parametresiz açılışla aynı,
eski kodda hepsi 1 numaralı kaydın / değerin çıktısıyla aynıydı (ör.
`get_erp_drawer?type=account&id[]=x` eski 200 "Birinci Cari", yeni 404;
`erp_accountant?download[]=x` eski paket 1'in zip'i, yeni ekran;
`add_erp_return?invoice_id=2&qty[3][]=x` eski 1 adet, yeni boş). Yeni kodda
uyarı ya da ölümcül satır yok; hata sayfalarında iki kodda da aynı K9
`htmlspecialchars(null)` kullanım dışı satırı var. `tools/lint.php`,
`tools/test.php`, `check_lang`, `check_api_schema`, `check_copies`,
`build_js.php --check` temiz.

---

## 2026.4.10 — Yedeklerde güvenlik duvarı, performans ve API istek kayıtları satırsız; Yedeklemeler sayacı (2026-10-14)

**Neden.** #434'ün kalanı. #434 ziyaret ve görüntüleme kayıtlarını yedekten
çıkarmış, diğer günlük tablolarını bilerek kapsam dışı bırakmıştı. Bunlar da
tekrar üretilebilir, müşteri verisi taşımaz ve yoğun bir sitede dökümün
büyük kısmı olabilir (güvenlik duvarı 20.000 satıra, API ve performans
günlükleri 30 güne kadar tutulur).

**Kapsam (`pg_backup_rowless_tables()`'a eklenen).** Yapısı yedekte kalır,
satırları dökülmez:
- `waf_log`: güvenlik duvarı olayları (Güvenlik Duvarı Kaydı ekranı, pano
  tehdit kartları, panonun karşılama özeti). Otomatik yasaklar burada değil,
  IP listesinde (`banned_ip_addresses`) durur ve satırlarıyla yedeklenir.
- `perf_log`: yavaş istekler (Performans Kaydı ekranı).
- `api_request_log`: API isteği başına bir satır (API Ayarları'nın 24 saatlik
  trafik sayısı ve uygulama çekmecesindeki "Kayıtlar" sekmesi). Faturalama,
  kota ya da hız sınırı bu tabloyu okumuyor; hız sınırı `api_rate_bucket`'ta.

Bilerek satırlarıyla kalan günlükler: `log` (Site Günlüğü: kim neyi ne zaman
yaptı; yanlışlıkla silinen bir kaydın izini bu sürer, API'deki `site_log`
kaynağı da onu okur), `erp_audit_log`, `erp_edoc_log`, `erp_export_log`,
`erp_parasut_log`, `erp_reconciliation_log` (ERP iş kayıtları),
`cron_runs` (iş başına bir satır: son çalışma ve kilit; görevler bunu
filigran olarak okuyor, boşaltmak yer kazandırmadan davranışı değiştirir),
`perf_stats` (saatlik performans özeti, satır sınırlı; #434'te
saatlik ziyaretçi grafiğinin kalmasıyla aynı gerekçe), `ws_mcp_calls` ve
`ws_scheduled_runs` (Çalışma Alanı kayıtları). Hız sınırı sayaçları
(`waf_rate`, `api_rate_bucket`, `api_device_rate`) ve `api_idempotency` saatlik
siliniyor, küçükler; listeye alınmadı.

**Ne değişti.**
- `includes/fn/backup.php`: üç tablo `pg_backup_rowless_tables()`'a eklendi;
  dört yedek yolu, `sql.sql` başındaki not ve `no-data` ayarı bu listeden
  okuduğu için başka kod değişmedi.
- Not metni üç ekranda (Yedekleme Yöneticisi, Ayarlar › Genel ›
  Yedeklemeler, Dosya Yöneticisi › Yedeklemeler) güvenlik duvarı, performans
  ve API istek kayıtlarını da sayıyor; durum çubuğundaki kısa metin "Ziyaret,
  görüntüleme ve istek kayıtları yedeğe girmez". #434'ün iki eski anahtarı
  `tr.json`'da yenileriyle değiştirildi.
- `view_folders.php`: Yedeklemeler alt çubuğu `state.backupEntries`'i sayıyordu;
  bu alan hiçbir yerde doldurulmuyordu (#434 öncesinden), sayaç hep "0 öğe"
  gösteriyordu. Artık `loadBackups()`'ın verdiği listeyi (klasörler +
  dosyalar) sayıyor; ölü alan kaldırıldı. Paylaşılan tablo yardımcısına
  dokunulmadı.
- `tests/backup_test.php`: kapsam testi yeni tabloları ve satırıyla kalması
  gereken günlükleri (site günlüğü, ERP günlükleri, `cron_runs`,
  `perf_stats`, kuyruklar, müşteri tabloları) sayıyor.

**Ölçüm (sandbox, MariaDB 10.11).** 50.000 güvenlik duvarı, 20.000 performans
ve 50.000 API isteği satırı eklenen kurulum: döküm 24,9 MB → 1,5 MB, yedek
kurulum ekranının ayrıştırıcısıyla (`parse_mysql_dump()`) boş veritabanına
geri yükleme 5,4 sn → 2,2 sn. 301 tablonun 301'i geri geldi; 294'ünün
`CHECKSUM TABLE`'ı kaynakla aynı, yedi satırsız tablo boş, `AUTO_INCREMENT`
değerlerinin hepsi aynı. Geri yüklenen veritabanında Güvenlik Duvarı Kaydı,
Performans Kaydı, API Ayarları, pano ve ön yüz hatasız açıldı. Yedeklemeler
alt çubuğu iki yedekli listede önce "0 öğe", sonra "2 öğe".

**Bilerek yapılmayan.** Şema adımı yok. Geri yükleme koduna dokunulmadı.
`seo_issue`/`seo_link` (SEO taramasının sonuçları, tekrar taranarak
üretilebilir) günlük değil, kapsam dışı.

---

## 2026.4.10 — Panel taramasının son uyarıları: şablon ve kasa satışı POST alanları, paketleme fişi, silinmiş ürün grubu (2026-10-14)

**Neden.** #432 ve #433'ün kalanları. #433'ün taraması GET'ti;
`workspace_template` ve `add_order` jetonlu POST alanlarını `(string)` /
`(int)` ile okuyordu. Dizi gelen alan (`name[]=x`) "Array to string
conversion" yazıp değeri "Array" olarak kullanıyor, `(int)` dizide 1
verdiği için 1 numaralı kaydı seçiyordu: sandbox'ta `workspace_template`
`id[]=1` ile 1 numaralı şablonun üstüne kaydetti (açıklaması silindi),
`name[]=x` "Array" adlı şablon açtı; kasa satışında `product_id[]=5` 1
numaralı ürünü aradı, `item_id[]`, `contact_id[]`, `order_id[]` aynı yoldan
1'i kullanıyordu, hızlı cari açmada `title[]` / `phone[]`
`includes/erp/account_quick.php`'de uyarı yazdı. `print_packing_slip`
gerçek bir alıcıda isteğe bağlı satırların değişkenlerini (kurum adres 2 /
ülke, unvan, şirket, adres 2, telefon) yalnız doluysa kuruyordu; boş olanlar
için normal açılışta üç ila beş "Undefined variable". Katalog menüsü
yönlendirmesi silinmiş (ya da boş) ürün grubunda
`get_catalog_item_address_name_from_id()` bulunamayan satırın sütununu
okuyordu (`includes/fn/content.php`, "array offset on null"), ardından
`encode_url_path(null)` kullanım dışı satırı. Hepsi 2026.4.9'da da var.

**Ne değişti.** Serinin kuralı: dizi gelen tek değerli alan "verilmedi"
sayılır, normal isteğin çıktısı değişmez. `workspace_template` jeton
denetiminden hemen sonra, `add_order` eylem okunmadan önce #425'in
`pg_request_single_values()`'ini ekranın okuduğu bütün POST alanlarıyla
çağırıyor (kasa satışında hızlı cari açmanın `$_POST`'tan okuduğu `title`,
`is_person`, `tax_number`, `email`, `phone` dahil). Ekranın kendi formları
ve `add_order.js` bu alanları hep tek değer gönderiyor. `print_packing_slip`
altı değişkeni dallardan önce boş metinle başlatıyor (boş metin, tanımsız
değişkenin bastığıyla aynı). `get_catalog_item_address_name_from_id()` satır
yoksa boş metin dönüyor (önceden `null`; bütün çağıranlar metin olarak
kullanıyor, `get_query_string_for_page_url()` boşu zaten "yok" sayıyor).
Şema, dil dosyası, JS değişikliği yok. Yeni
`tests/panel_scan_leftovers_test.php`: iki ekranın okuduğu her `$_POST`
alanı çağrının listesinde ve çağrıdan sonra okunuyor, fişin varsayılanları
ilk kullanımdan önce, fonksiyon satır yokken sütun okumuyor (eski kodda
dört test de düşüyor).

Altı ekrandaki boş değerli `h()` kullanım dışı satırı (`add_container`,
`add_ship_date_adjustment`, `add_verified_shipping_address`,
`duplicate_products`, `view_countries`, `view_styles`) burada
değiştirilmedi: altısı da `includes/fn/core.php` `h()`'ye giden `null`
(çoğu liveform'un boş alan değeri) ve K9 iş parçacığı `h()` / `escape()`'e
null koruması ekliyor. Sandbox'ta `h()` geçici olarak `(string)` ile
denendi: altı ekranda (106 istek) kullanım dışı satırı 106 → 0, başka satır
çıkmadı. Çağrı yerinde ayrı dönüşüm aynı satırları ikinci kez düzeltirdi.

**Doğrulandı (sandbox, MariaDB, PHP 8.3, DEBUG açık).** Eski kod
(`origin/development`) 8001'de, yeni 8000'de, aynı veritabanı ve `data/`,
istekler sırayla iki sunucuya. Paketleme fişi dört gerçek alıcıda (şirketli
/ şirketsiz, unvanlı, telefonlu) aynı HTML; eskisi istek başına 3–5 uyarı,
yenisi 0. Katalog menüsü: silinmiş ve boş ürün grubu aynı yönlendirme
(`/shop-sidebar/?previous_url_id=…`), eskisi iki satır, yenisi 0; var olan
iki grup ve iki katalog sayfası aynı. `workspace_template`: normal kayıt
ikisinde de şablonu açıp `?id=` sayfasına döndü; dizi ad yenide "ad gerekli"
formunu, dizi kimlik yeni şablonu açtı; bütün alanları dizi gelen kayıt
eskide reddedilip yedi uyarı yazdı, yenide ad ile ve öbür alanlar boş
kaydedildi. Kasa satışı (JSON): ürün kimliği ve barkodla ekleme, geçersiz
eylem, sepeti boşaltma, hızlı cari açma, müşteriyi kaldırma, `state` ve
`recent` istekleri aynı yanıt (yalnız yeni satırın kimliği farklı); dizi
eylem eskide "Geçersiz eylem." JSON'u ve uyarı, yenide eylemsiz POST gibi
ekranın kendisi; dizi ürün kimliği / barkod "barkod okutun ya da ürün adını
yazın", dizi cari adı "Ad gerekli" diyor. `scan.py` dört ekranda (79 GET
isteği): uyarı yok, kalan tek satır hata sayfasındaki liveform `h(null)`
(K9). `tools/lint.php`, `tools/test.php` (985 test), `check_lang`,
`check_bindings`, `check_api_schema`, `check_copies` ve `build_js.php
--check` geçti. Doğrulanamadı: dev.pinegrap.com'daki gerçek veriler.

**Bilerek yapılmayan.** `erp_account_quick.php` (ERP ekranlarının hızlı cari
ucu) aynı `erp_account_quick_create($user, $_POST)` yolunu kullanıyor; ERP
POST uçları ayrı bir iş olarak kuyrukta. Kasa satışında ürün eklemenin
`includes/fn/ecommerce.php` ziyaretçi satırı uyarısı (`visitor_id`, ziyaretçi
kaydı olmayan istek; `update_currency.php:48` ile aynı tür) ve
`escape(null)` kullanım dışı satırı değişmedi (ikincisi K9'un `escape()`
korumasıyla gider).

---

## 2026.4.10 — Tema Tasarımcısı ayarı olmayan CSS teması CSS düzenleyicisinde açılıyor, Kaydet temanın CSS'ini silmiyor (2026-10-14)

**Neden.** #417'nin panel taramasından kalan K8. Tema Tasarımcısı bir
temanın `system_theme_css_rules` satırlarını düzenler ve temanın CSS
dosyasını onlardan yeniden yazar. Panelin üç düğmesi (`edit_theme_file`,
`edit_design_file`, önizleme araç çubuğu) satırı olmayan temayı zaten CSS
düzenleyicisine gönderiyor; tasarımcıya böyle bir dosyayla yalnız adres elle
yazılarak ulaşılıyordu. O zaman ekran istek başına 50'den fazla uyarı
yazıyordu (`theme_designer.php` `preview_properties` hiç kurulmuyor,
`generate_system_theme_css()` `null` ile çağrılıyordu), modül ucu
(`get_theme_designer_module.php`) her alan için bir uyarı daha. Asıl sorun
Kaydet'ti: `system_theme_css_rules` satırı olmayan dosyada da dosyayı
`unlink()` edip yerine üretilmiş CSS yazıyordu. Sandbox'ta eski kodla
88 baytlık elle yazılmış `font-georgia.css` Kaydet'ten sonra 53 KB
üretilmiş CSS'e dönüştü. Tema olmayan bir dosyada da (`.eot`, resim) aynı
yol dosyayı silip CSS yazar. 2026.4.9'da da var.

**Ne değişti.** Varsayılan: tasarımcı yalnız tasarımcı ayarı olan temada
açılıyor. `theme_designer.php` kimlik kapısının hemen ardından (GET ve POST
dallarından önce) temanın satırlarını sayıyor. Satırı yoksa ve dosya `.css`
ise CSS düzenleyicisine (`edit_theme_css.php?id=…`) yönleniyor; `send_to`
metinse taşınıyor, `from` temada `edit_theme_file`, tasarım dosyasında
`edit_design_file` (düğmelerin verdiği değerler, kırıntı yolu aynı). Ekranın
üstünde kısa bir not: "This theme has no Theme Designer settings, so it opens
in the CSS editor." Başka bir dosyada "Kayıt bulunamadı" (404). POST da aynı
kapıdan geçtiği için eski bir sekmeden gelen Kaydet / Çoğalt / Önizle hiçbir
şey yazmadan CSS düzenleyicisine dönüyor. `get_theme_designer_module.php`:
#432'nin "dosya yoksa 404" kapısına tasarımcı satırı koşulu eklendi (satırı
olmayan dosyada da "Kayıt bulunamadı"); tasarımcı bu oturumda açılmadan
çağrılan modülde oturum okuması `?? null` (gerçek temada HTML aynı). Gerçek sistem temasında iki ekranın çıktısı değişmedi. Yeni
`tests/theme_designer_rules_gate_test.php`: kapı GET dalından ve ilk
`unlink()`'ten önce, CSS dosyası CSS düzenleyicisine, ötekiler 404'e gidiyor,
modül ucunda kapı `switch`'ten önce. Şema adımı, JS yok; tek yeni dil anahtarı.

Neden boş kural kümesiyle tasarımcıyı açmak değil: tasarımcının o temada
gösterecek hiçbir ayarı yok ve Kaydet dosyanın CSS'ini üretilmiş kodla
değiştirir; panelin geri kalanı da satırı olmayan temayı CSS teması sayıyor.

**Doğrulandı (sandbox, MariaDB, PHP 8.3, DEBUG açık).** Eski kod
(`origin/development`) 8001'de, yeni 8000'de, aynı `data/`. Satırı olmayan
tema (`theme_modern.css`) ve tasarım CSS'i (`bootstrap.css`): eskisi 200 ve
istek başına 50 uyarı (günlüğün üst sınırı), yenisi 302 → CSS düzenleyicisi,
0 satır; tarayıcıda not görünüyor. Modül ucu satırı olmayan dosyada 8 uyarı
→ 404. `.eot` dosyası ve var olmayan kimlik 404. Kaydet (jetonlu POST) eski
kodda `font-georgia.css`'i değiştirdi, yenide dosyanın md5'i aynı, 0 satır.
`add_theme_file` ile kurulan sistem temasında tasarımcı ekranı ve iki modül
iki sunucuda aynı HTML'i verdi (varlıkların `?v=` sürüm damgası dışında);
Kaydet eskisi gibi `reload_theme.php`'ye yönlendi, 23 satır yerinde.
`tools/lint.php`, `tools/test.php`, `check_lang`, `check_bindings`,
`check_api_schema`, `check_copies` ve `build_js.php --check` geçti.
Doğrulanamadı: dev.pinegrap.com'daki gerçek temalar.

**Bilerek yapılmayan.** Gerçek bir sistem temasında da tasarımcı çalışmıyor:
sayfanın çağırdığı `init_theme_designer_accordion()`,
`resize_theme_designer()`, `highlight_module()`,
`open_theme_designer_accordion_module()` depoda hiçbir JS dosyasında yok,
tarayıcıda "is not defined" (ilk commit'ten beri; "Sistem" tema türü
`add_theme_file`'da zaten kapalı). Seyrek satırlı temada
`generate_system_theme_css()` ve modüllerin `$properties['…']` okumaları
istek başına onlarca uyarı yazıyor. İkisi de bu işin kapsamı dışında kaldı.

---

## 2026.4.10 — ERP ekranları dizi gelen cari, fatura ve kasa numarasını 1 sayıyordu (2026-10-14)

**Neden.** #427'nin kalanı. ERP ekranları kimlik kapısından (#422) geçmeyen
kayıt numaralarını (`account_id`, `contact_id`, `invoice_id`, `order_id`,
`product_id`, `category_id`, `copy`, `back_order`, `amount`, kasa akışının
`till`'i) `(int) ($_GET['x'] ?? 0)` ile okuyordu. Dizi gelince
(`?account_id[]=x`) PHP diziyi uyarısız 1 yapıyor; ekran 1 numaralı carinin,
faturanın ya da kasanın verisini açıyordu. Günlükte iz yoktu. Bir yerde
kayıt da yazıyordu: cari fiyatları ekranında satırda değiştirilen fiyat
(`erp_account_prices.php`, liste hızlı düzenleme) adres dizi `account_id`
taşıyınca 1 numaralı cariye kaydediliyordu. Hepsi 2026.4.9'da da var.

**Ne değişti.** Serinin kuralı: dizi gelen tek değerli parametre "verilmedi"
sayılır, ekran parametresiz açılışla aynı davranır, normal isteğin çıktısı
değişmez. Okuma #422'nin kimlik satırıyla aynı biçimde:
`is_scalar($_GET['x'] ?? null) ? (int) $_GET['x'] : 0`. Yeni yardımcı yok
(#427 gibi satır içi).

- Liste ve rapor süzgeçleri: `erp_invoices` (`account_id`),
  `erp_purchase_orders`, `erp_quotes` (`account_id`), `erp_expenses`
  (`category_id`), `erp_stock` (`product_id`), `get_erp_products`
  (`account_id`; cari bölgesinin vergi oranı ve cari fiyatı).
- Kaydı açan ekranlar: `erp_account_prices` (`account_id`, hızlı düzenleme
  dahil), `erp_account_sync_item` (`account_id`), `add_erp_return`
  (`invoice_id`) dizi gelince parametresiz açılıştaki "bulunamadı" hatasını
  veriyor.
- Formu dolduran ekranlar: `add_erp_account` (`contact_id`),
  `add_erp_manual_invoice`, `add_erp_purchase_order`, `add_erp_quote`
  (`copy`, `account_id`), `add_erp_receipt` (`invoice_id`, `account_id`,
  `amount`, `back_order`), `add_erp_return` (`back_order`), `add_erp_waybill`
  (`order_id`, `invoice_id`) dizi gelince boş form açıyor.
- `erp_cashflow_options()`: dizi `till` "tüm kasalar" (`is_scalar()`); API
  zaten sayı veriyor, davranışı aynı.
- `tests/erp_request_values_test.php` iki test aldı: ERP ekranlarında bu
  adlardan biri `$_GET` / `$_REQUEST`'ten çıplak `(int)` ile okunmuyor
  (eski kodda 15 ekranda 28 okumayı yakalıyor); dizi `till` 0.

Şema, dil dosyası ve JS değişikliği yok. ERP POST işleyicileri (`$_POST`
okumaları) ve ERP'de `$_GET['id']`'yi kapısız okuyan yedi ekran bu işin
dışında; ikinci yarıya kaldı (devir notu).

**Doğrulandı (sandbox, MariaDB, PHP 8.3, `debug = 1`).** Cari, fatura,
teklif, satın alma siparişi, kasa, gider kategorisi, kişi, ürün ve sipariş
için 1 ve 2 numaralı kayıtlar; eski kod (`origin/development`) ikinci
portta, aynı veritabanı. 26 durum × (normal, dizi, parametresiz) × iki kod,
156 istek: 26 normal istek eski kodla aynı HTML ve durum kodu; 26 dizi
isteğinin hepsi yeni kodda parametresiz açılışla aynı (üç hata sayfasında
yalnız sayfanın kendi adresi farklı); 23'ünde eski kod 1 numaralı kaydı
gösteriyordu (ör. `erp_invoices?filter=open&account_id[]=x` "Açık · Birinci
Cari", `add_erp_account?contact_id[]=x` 1 numaralı kişiyle dolu form,
`add_erp_waybill?order_id[]=x` 302, `erp_cashflow?till[]=x` 1 numaralı
kasa). Hızlı düzenleme: eski kod dizi `account_id` ile 1 numaralı cariye
fiyat yazdı, yeni kod 422 "Cari hesap bulunamadı", yazmadı. İki kodda da
uyarı ya da ölümcül satır yok. `tools/lint.php`, `tools/test.php`,
`check_lang`, `check_bindings`, `check_api_schema`, `check_copies`,
`build_js.php --check` temiz.

---

## 2026.4.10 — Veritabanı yedekleri ziyaret kayıtlarını, sayfa ve form öğesi görüntüleme sayılarını almıyor (2026-10-14)

**Neden.** Ziyaretçi temizleme planının (`docs/_plan_ziyaretci_temizleme.md`)
"Ertelenenler" 7. maddesi; ürün sahibi 2026-10-14'te onayladı: ziyaretçi
listeleri, form ve sayfa görüntülemeleri yedeklere girmesin. 90 günlük
saklamadan sonra da yoğun bir sitede bu satırlar dökümün çoğu ve geri
yüklemenin en uzun kısmı; geri yüklenen bir sitenin onlara ihtiyacı yok.

**Kapsam (`pg_backup_rowless_tables()`).** Yapısı (`CREATE TABLE`, mevcut
`AUTO_INCREMENT` ile) yedekte kalır, satırları dökülmez:
- `visitors`: her ziyaret bir satır (ziyaretçi listesi, ziyaretçi raporunun
  ham günleri).
- `visitor_content_hourly`: sayfa ve içerik başına saatlik görüntüleme (pano,
  Sayfalar listesi, sayfa bilgisi "Sayfa Görünümleri", SEO trafik raporu).
- `submitted_form_views`: form öğesi görüntülemesi, görüntüleme başına bir
  satır (2026.4.1 öncesinin tablosu; geçiş sürüyorsa dolu).
- `submitted_form_view_stats`: form öğesi görüntülemesi, gün başına (form
  görünüm dizininin yöneticiye gösterdiği "en çok görüntülenen" listesi).

Hiç yedeklenmeyen (`pg_backup_skipped_tables()`): `visitors_innodb` ve
`visitors_myisam`, #426'nın MyISAM → InnoDB taşımasının çalışma kopyaları;
yalnız `data/temp/visitor_move.json` ile anlamlılar, o dosya yedekte yok.

Satırlarıyla kalan: `visitor_stats_daily` (saklama süresini geçmiş günlerin
özeti; başka kopyası yok), `visitor_stats_hourly` (pano ziyaretçi grafiği,
günde 24 satır), `submitted_form_info.number_of_views` (okuyucuya görünen
sayaç; tablo başka bilgiler de taşıyor), `visitor_reports`.

**Ne değişti.**
- `includes/fn/backup.php`: `pg_backup_dump_database($file)` dört yedek
  yolunun ortak dökümü oldu: otomatik yedek (arşiv ve klasör), Yedekleme
  Yöneticisi ile Dosya Yöneticisi › Yedeklemeler'in adım adım yedeği
  (`includes/panel/software.php`, `create_mysql_dumb`) ve güncelleme öncesi
  yedek (`install_backup_database()`). `mysqldump-php`'ye `no-data` ve
  `exclude-tables` ayarları gidiyor; kütüphaneye dokunulmadı.
- Dosyanın başında (`pg_backup_dump_note()`) hangi tabloların satırsız
  döküldüğünü söyleyen İngilizce SQL yorumu. Kütüphane dosyasını `wb` ile
  açtığı için döküm önce `sql.sql.part`'a yazılır, not ve döküm `sql.sql`'e
  kopyalanır, `.part` silinir. Kopya eksik yazılırsa (dolu disk) iki dosya
  da silinir ve hata her yolun mevcut hata yolundan döner.
- Not üç ekranda: Yedekleme Yöneticisi'nde "Yedekleme Oluştur veya
  Güncelle" kartı, Ayarlar › Genel › Yedeklemeler (saklama açıklamasının
  altında), Dosya Yöneticisi › Yedeklemeler durum çubuğu (kısa metin,
  üstüne gelince tamamı).
- `get_search_results.php`: oturumdaki ziyaretçi satırı yoksa (geri
  yüklenen yedekten sonra açık kalan oturum) "Trying to access array offset
  on null" uyarısı yazıyordu; satır yoksa terim boş başlıyor.

**Geri yükleme.** Geri yükleme koduna dokunulmadı. Satırsız tablolar boş
açılır; ziyaret, sayfa ve form öğesi görüntülemeleri ilk istekten itibaren
yeniden sayılır. Ziyaretçi raporu son saklama dönemini boş gösterir, eski
günler özetten gelir. Aynı sunucuya geri yüklemede açık oturumların
ziyaretçi satırı yok: o oturumların sayfa görüntülemeleri ziyaret satırına
yazılmaz (zaten "satır yoksa geç" yolu vardı), saatlik sayaçlara yazılır.

**Ölçüm (sandbox, MariaDB 10.11).** 206 bin ziyaret satırı, 2.671 saatlik
içerik satırı, 682 + 234 form öğesi görüntüleme satırı, 5.000 + 1.000
satırlık iki taşıma kopyası: döküm 53,4 MB → 1,5 MB. Yedek kurulum
ekranının ayrıştırıcısıyla (`parse_mysql_dump()`) boş bir veritabanına
geri yüklendi (1,7 sn): 297 tablonun `CHECKSUM TABLE`'ı kaynağıyla aynı,
dört tablo boş (yapısı ve `AUTO_INCREMENT` aynı), iki taşıma kopyası yok.
Geri yüklenen veritabanında ön yüz, pano, ziyaretçi raporu, Veritabanı
Motoru, SEO ve Sayfalar hatasız açıldı; ilk sayfa görüntülemeleri
`visitor_content_hourly`'ye yazıldı. Otomatik (arşiv ve klasör), panelden
adım adım ve güncelleme öncesi yedek ayrı ayrı alındı; dördünün de başında
not var, `visitors` satırı yok.

**Bilerek yapılmayan.** `waf_log`, `perf_log`, `api_request_log` gibi diğer
günlük tabloları kapsam dışı (karar yalnız ziyaret ve görüntüleme
kayıtları için). Güncelleme öncesi yedek ekranındaki boyut tahmini hâlâ
bütün veritabanının boyutu (üst sınır olarak doğru).

---

## 2026.4.10 — Zorunlu parametresi olmadan açılan panel ekranları uyarı yazmıyor, kayıt isteyenler "Kayıt bulunamadı" diyor (2026-10-14)

**Neden.** #417'nin panel taramasında K7 olarak kalan iş. Bir kayıt (sayfa,
ürün, menü, tema dosyası) ya da tür üzerinde çalışan ekranlar o parametre
adreste olmadan açılınca (eski sekme, elle yazılmış adres, silinmiş kayıt)
kaydı aramadan okuyordu: php_errors.log'a her açılışta bir ile 38 arası
"Undefined array key" / "Trying to access array offset on null" satırı,
ekranda yarı boş form. #422 ve #424'ün kimlik kapıları listedeki ekranların
çoğunu (`edit_page`, `edit_commission`, `view_order`, `view_email_campaign`,
`view_system_style_source`, `view_menu_items` ...) kapatmıştı; development
üzerinde (#425 ve #428 dahil) 367 panel ekranı parametresiz yeniden tarandı,
uyarı yazan 10 ekran kaldı. Notun K7 listesindeki
`add_email_campaign` (silinmiş sayfayla açılış) da bu işe alındı. Hepsi
2026.4.9'da da var.

**Ne değişti.** Kayıt bekleyen ekran kaydı yoksa #424'ün "Kayıt bulunamadı"
404'ünü veriyor; yalnız bir soruya cevap veren uç eksik değeri boş okuyup
eskiden verdiği cevabı veriyor. Parametre verilmiş normal açılışın çıktısı
değişmedi.

- `add_field.php`: sayfa, ürün grubu ya da ürün yoksa (hiçbir dal çalışmıyor)
  "Kayıt bulunamadı" 404. Ürün ve ürün grubu dallarında `$page_type` boş
  metin (önceden tanımsız; ürün formuna alan eklerken açılışta bir, kaydederken
  iki uyarı yazıyordu).
- `add_menu_item.php`, `preview_form.php`, `generate_layout.php`: menü, ürün
  ya da sayfa yoksa (eksik ya da silinmiş) "Kayıt bulunamadı" 404, kaydın
  ayrıntıları okunmadan önce.
- `get_theme_designer_module.php` (tema tasarımcısının AJAX modülü): tema
  dosyası yoksa 404, boş kimlik için oturuma kayıt da yazılmıyor. Var olan
  ama tasarımcı kuralı olmayan CSS teması (K8) aynen kaldı.
- `browse_catalog_menu.php` (ön yüzdeki katalog menüsünün yönlendirmesi):
  sayfa yoksa bozuk adrese (`//?previous_url_id=`) yönlendirmek yerine
  "Sayfa bulunamadı" 404; ürün grubu ya da dönüş adresi eksikse adresin o
  parçası eskisi gibi boş, uyarısız.
- `image_editor_edit.php`: dosyanın var olup olmadığı satırı okunmadan önce
  denetleniyor (aynı "resim artık yok" ekranı).
- `check_if_file_exists.php`, `get_region_content.php`,
  `print_packing_slip.php`: eksik değer boş okunuyor; cevap ("upload", boş
  içerik, "alıcı bulunamadı") eskisiyle aynı.
- `add_email_campaign.php?page_id=<silinmiş>`: sayfa adı ve klasörü boş
  okunuyor, ekran aynı.
- Dizi gelen parametreler (K3 / K5, #425 / #428) ve kimlik satırları (K1 /
  K2) değişmedi; aynı dosyalarda yalnız parametresiz açılış yolu.
- Yeni `tests/panel_missing_param_test.php`: kapı kaydın ayrıntılarından önce
  geliyor, cevap veren uçlar eksik değeri boş okuyor. Eski kodda üç test de
  düşüyor.

Şema, dil dosyası ve JS değişikliği yok.

**Doğrulandı (sandbox, MariaDB, PHP 8.3, `debug = 1`).** 367 panel ekranı
parametresiz: uyarı yazan istek 10 → 0, ayrı uyarı satırı 76 → 0. Kullanım
dışı uyarıları (yalnız DEBUG'da) `liveform` `h(null)` (K9) dışında 21 → 6
(kalanlar boş değerde `h()`: `add_container`, `add_ship_date_adjustment`,
`add_verified_shipping_address`, `duplicate_products`, `view_countries`,
`view_styles`). 500 yok. Eski kod (`origin/development`, #428 dahil)
ikinci portta, aynı veritabanıyla: değişen 11 ekranın 29 isteği aynı HTML'i
ve aynı durum kodunu verdi; 21'i gerçek kayıtla normal açılış (özel form,
ürün, ürün grubu, menü, sayfa, tema dosyası, resim, alıcı, bölge; katalog
yönlendirmesinin `Location` başlığı), 8'i cevap veren uçların eksik ya da
silinmiş kayıtla açılışı (yalnız günlük satırları gitti). Ürün formuna alan ekleme ve menü öğesi ekleme iki
sunucuda da kaydetti (kayıtlar karşılaştırıldı, sonra silindi). `php -l`,
`tools/lint.php`, `tools/test.php`, `check_lang`, `check_bindings`,
`check_api_schema`, `check_copies` ve `build_js.php --check` geçti.

**Bilerek yapılmayan (kalanlar).** Parametre verilmiş ama kaydı olmayan iki
durum uyarı yazmaya devam ediyor: katalog yönlendirmesinde silinmiş ürün
grubu (`includes/fn/content.php:2521`, ortak fonksiyon) ve tema tasarımcısı
modülünde kuralı olmayan tema (K8). `print_packing_slip.php` var olan alıcıda
da beş tanımsız değişken uyarısı yazıyor (338–354, normal açılış; K6
türünden). Boş değerde `h()` kullanım dışı uyarıları ve `liveform.class.php:1376`
K9'da. K6, K8, K9 notta.

---

## 2026.4.10 — Panelde kalan dizi uyarıları ve sipariş raporunun normal açılış uyarıları (2026-10-14)

**Neden.** #417'nin panel taramasından kalan K6: 500 vermeyen ama hata
günlüğüne uyarı yazan dokuz ekran. Sekizi tek değerli bir parametreyi
`(string)` ya da denetimsiz okuyordu; dizi gelince (`?q[]=x`) "Array to
string conversion" yazıp değeri "Array" olarak kullanıyordu:
`translations` (`language`, `group`, `filter`, `q`; "Array" aranıyordu),
`page_designer` (`type`, `id`, `url`; JS'e "Array" basılıyordu),
`settings_pane` (`pane`), `workspace_template` (`copy`; "şablon
bulunamadı" ekranı), `view_regions` (`filter`; sıralama bağlantılarına
`filter=Array`, `includes/fn/output.php` `pg_list_sort_menu()`'de ikinci
uyarı), `google_taxonomy_action` (`q`), `add_order` (`request`, arama
isteklerinde `q`), `add_system_style` (`start`). `(int)` dizide 1 verdiği
için `workspace_template` `?id[]=` / `?channel[]=` ile 1 numaralı şablonu
ya da kanalı, `translations` `?page_id[]=` ile 1 numaralı sayfayı
seçiyordu. `view_order_report` ise normal açılışta uyarı yazıyordu:
kaydedilmiş rapor sipariş tarihine göre süzülüyorsa `$output_date_changer`
hiç kurulmuyordu, üçten az özet düzeyinde `$sql_summarize_by_2` / `_3`
sorguya tanımsız giriyordu. Hepsi 2026.4.9'da da var.

**Ne değişti.** Serinin kuralı: dizi gelen tek değerli parametre
"verilmedi" sayılır, normal isteğin HTML'i değişmez. Sekiz ekranda okuma
satırı `isset($_GET['x']) && is_string($_GET['x'])` (`calendars.php`,
`view_files.php` kalıbı) oldu; `(int)` okumaları da yalnız metin geldiğinde
yapılıyor. `view_order_report` tarih değiştiriciyi ve üç özet sütununu
dallardan önce boş metinle başlatıyor (boş metin, tanımsız değişkenin
bastığıyla aynı). Şema, dil dosyası, JS değişikliği yok. Yeni
`tests/panel_string_params_test.php`: listedeki parametreler `is_string()`
ile denetleniyor, eski denetimsiz biçimler kalmadı, sipariş raporunun
varsayılanları ilk kullanımdan önce (eski kodda iki test de düşüyor).

**Doğrulandı (sandbox, MariaDB, PHP 8.3, DEBUG açık).**
`panel-tarama-araclari/scan.py` önce bütün panelde (367 ekran, 6.760 istek;
K6 dışındakiler K3, K4, K5, K7, K8'e ait) sonra dokuz ekranda (179 istek):
uyarı yazan istek 14 → 0, ayrı dosya:satır 18 → 0. Kalan tek günlük satırı
`workspace_template?id=1`'deki liveform `h(null)` kullanım dışı satırı
(K9, eskisinde de var). Eski kod (`origin/development`) ikinci portta,
istekler sırayla iki sunucuya: gerçek değerlerle 38 normal istek (dil,
grup, süzgeç, arama, sayfa; beş bölge listesi; Site Ayarları bölmeleri ve
bilinmeyen bölme 404; şablon kimliği, yerleşik şablon kopyası, kanal;
kasa satışı ürün ve müşteri araması; sayfa stili `start=import`
yönlendirmesi; tarih süzgeçli kayıtlı rapor ve tarih aralıklı yeni rapor)
aynı HTML'i verdi. 18 dizi isteğinden 16'sı eski kodda uyarı yazdı, yenide
0; her biri yeni kodda aynı adresin o parametre olmadan açılışıyla aynı
durumu ve HTML'i verdi. `tools/lint.php`, `tools/test.php` (969 test),
`check_lang`, `check_bindings`, `check_api_schema`, `check_copies` ve
`build_js.php --check` geçti. Doğrulanamadı: dev.pinegrap.com'daki gerçek
veriler.

**Bilerek yapılmayan.** Taramanın kapsamı GET; `workspace_template` ve
`add_order`'ın POST yollarındaki `(string) ($_POST['x'] ?? '')` okumaları
(jetonlu kayıt istekleri) değişmedi. `google_taxonomy_action`'da dizi
`action` uyarı yazmadan "bulunamadı" yanıtı veriyor, olduğu gibi kaldı.
K7 (zorunlu parametresiz açılış), K8, K9'a dokunulmadı.

---

## 2026.4.10 — Para birimi seçicisi `send_to`'yu güvenli yönlendirmeden geçiriyor, dizi `currency_id` oturumu düşürmüyor (2026-10-14)

**Neden.** #425'in "Bilerek yapılmayan" maddesi. Para birimi seçicisinin
formu (`update_currency.php`; katalog, sepet, sipariş sayfaları ve sepet
widget'ı) ziyaretçiyi gizli `send_to` alanındaki adrese geri gönderiyor.
Adres `URL_SCHEME . $_SERVER['HTTP_HOST'] . $_REQUEST['send_to']` ile ham
kuruluyordu: `send_to=@evil.com/x` → `Location: https://site.com@evil.com/x`
(tarayıcı `evil.com`'a gider), `.evil.com/x` → `https://site.com.evil.com/x`.
İstek jetonlu POST olduğu için başkasına bağlantıyla yaptırılamıyordu, ama
`send_to` için paneldeki öbür 70'i aşkın `Location`'ın geçtiği
`pg_safe_redirect_path()` kontrolünün dışında kalan tek `Location` buydu.
Aynı dosyada: `currency_id[]=2` diziyi oturuma yazıyordu; çoklu para birimi
açıkken `init.php` her sayfada onu `escape()`'e verdiği için o ziyaretçinin
bu istekten sonraki **her** sayfası 500 veriyordu (oturum bitene kadar).
`send_to` ya da `currency_id` hiç gönderilmeyince "Undefined array key"
uyarısı yazıyordu (K7). Hepsi 2026.4.9'da da var.

**Ne değişti.**

- `update_currency.php:60` öbür ekranların satırının aynısı:
  `URL_SCHEME . HOSTNAME . pg_safe_redirect_path(($_REQUEST['send_to'] ?? ''))`.
  Site içi yol (sorgu dizesi, `#` ve UTF-8 dahil) olduğu gibi kalıyor; dış
  adres, `//`, `/\`, kontrol karakteri, dizi ya da boş `send_to` ana sayfaya
  (`PATH`) dönüyor. `HOSTNAME` tarayıcı isteğinde `$_SERVER['HTTP_HOST']`'un
  kendisi (`init.php`), dönülen alan adı değişmiyor.
- `currency_id` yalnız tek değer olarak (`is_scalar()`) oturuma yazılıyor;
  dizi gelirse istek, para birimi hiç gönderilmemiş gibi boş sayfa veriyor
  (eskisi gibi).
- Taramada `Location`'a ham istek değeri koyan başka dosya yok: `HOSTNAME`
  ya da `HTTP_HOST`'tan sonra değişken koyan altı yer (`logout.php`,
  `registration_entrance.php`, `membership_entrance.php`,
  `catalog_detail.php`, `cancel_order.php`, `add_comment.php`) değeri
  önceden `pg_safe_redirect_path()`'ten geçiriyor; öbürleri `send_to`'yu
  `PATH . SOFTWARE_DIRECTORY . '/…?send_to='` sonrasına koyuyor.

Şema, dil dosyası ve JS değişikliği yok. Test:
`tests/update_currency_redirect_test.php` (5 test; son test hiçbir kök
ekranın `Location`'da host'tan hemen sonra `$_REQUEST` / `$_GET` / `$_POST`
koymadığını tarar, eski kodda `update_currency.php`'yi buluyordu).

**Doğrulandı (sandbox, MariaDB, PHP 8.3).** Eski ve yeni kod aynı oturum
açma akışıyla (panel giriş sayfasından jeton, sonra POST): normal
`send_to=/example-page?previous_url_id=2&a[]=1#software_currency` ve UTF-8
yol iki kodda da aynı `Location`. Eski kodda `@evil.com/x`, `.evil.com/x`,
`https://evil.com/x` sırasıyla `http://127.0.0.1:8000@evil.com/x`,
`…:8000.evil.com/x`, `…:8000https://evil.com/x`; yenide hepsi
`http://127.0.0.1:8000/`. Çoklu para birimi açıkken `currency_id[]=2`
eskide 500 ve sonraki ana sayfa da 500, yenide 200 ve ana sayfa 200.
Jetonsuz POST eskisi gibi yönlenmeden hata sayfasında kalıyor. `tools/lint.php`, `tools/test.php`,
`check_lang`, `check_bindings`, `check_api_schema`, `check_copies` ve
`build_js.php --check` geçti. Doğrulanamadı: dev.pinegrap.com.

**Bilerek yapılmayan.** `update_currency.php:48` ziyaretçi kaydı olmayan
istekte (`$_SESSION['software']['visitor_id']` yok; bot ya da hariç tutulan
IP) "Undefined array key" uyarısı yazıyor. Para birimi gönderilmeyen ya da
dizi gelen istek eskisi gibi boş sayfa veriyor, geri yönlenmiyor.

---

## 2026.4.10 — Panelde kalan 18 ekran tek değerli parametre dizi gelince 500 vermiyor (2026-10-14)

**Neden.** #417'nin panel taramasından kalan K5: K1–K4 dışında, kendi tek
değerli parametresi dizi gelince (`?page_id[]=1`) 500 veren 18 ekran.
Parametre `escape()`, `e()`, `trim()`, `urlencode()`, `preg_match()` ya da
`mb_strtolower()`'a gidiyor, bunlar diziyi reddediyordu: `add_email_campaign`
(`page_id`), `add_field` ve `view_fields` (`page_id`, `product_id`,
`product_group_id`), `add_submitted_form` (`page_id`), `add_menu_item`
(`menu_id`), `add_user` (`contact_id`), `edit_form_item_view`,
`edit_form_list_view`, `generate_layout` (`page_id`), `preview_form`
(`product_id`), `print_packing_slip` (`ship_to_id`), `check_if_file_exists`
(`file_name`), `image_editor_edit` (`file_name`; dosya varken `object_type`,
`object_id`, `column_to_update` da), `editor_select_image`
(`CKEditorFuncNum`, `SingleImage`, `file_input_name`),
`editor_select_page_or_file` (`CKEditorFuncNum`), `get_theme_designer_module`
(`file_id`, `module_id`), `import_design` (`submit_button`) ve
`browse_catalog_menu` (`current_page_id`, `product_group_id`;
`previous_url_id` dizi gelince yönlendirmeye `Array` yazıyordu). Hepsi
2026.4.9'da da var.

**Ne değişti.** Her ekranın başında tek satır:
`pg_request_single_values(array(...))` (#425'in `send_to` / `from` için
`init.php`'de çağırdığı yardımcı). Listedeki parametre dizi gelirse
`$_GET`, `$_POST` ve `$_REQUEST`'ten çıkarılıyor; istek o parametre hiç
verilmemiş gibi işleniyor. Parametreler ekrana özgü olduğu için çağrı
`init.php`'de değil ekranda. Ekranın geri kalanı değişmedi;
`edit_form_item_view` sayfa sorgusunda, liste görünümü eşindeki gibi
`?? ''` aldı (parametresiz açılışta "Undefined array key" yazıyordu, 404
aynı). Normal isteğin çıktısı aynı. Şema, dil dosyası, JS değişikliği yok.

**Doğrulandı (sandbox, MariaDB, PHP 8.3).** `panel-tarama-araclari/scan.py`
18 ekranda (parametresiz, kendi parametreleri `=1`, her kendi ve genel
parametre dizi olarak; 360 istek), #425 birleştikten sonraki development'a
karşı: 500 26 → 0. K5'e ait 30 dizi isteği artık parametresiz açılışla aynı
durum kodunu veriyor ve onun yazmadığı günlük satırı yazmıyor
(`import_design`'da taramanın normal isteğinden kalan liveform `h(null)`
kullanım dışı satırı sonraki parametresiz açılışta da var). Gerçek
kayıtlarla (özel form, form liste ve detay görünümü, ürün, ürün grubu, menü,
kişi, resim, tema dosyası, gönderim) eski kod (`origin/development`) ikinci
portta, istekler sırayla iki sunucuya: 51 normal istek aynı HTML'i verdi; 30
dizi isteğinden 29'u eski kodda 500 verdi (otuzuncusu `previous_url_id[]`,
yönlendirmede `Array`), yenisinde 0; her dizi isteği, yeni kodda aynı
adresin o parametre olmadan açılışıyla aynı durum kodunu, aynı yönlendirmeyi
ve aynı günlük satırlarını verdi. Yeni
`tests/panel_single_value_params_test.php`: listedeki her ekranın çağrısı
parametreyi taşıyor ve ekranın parametreyi `$_GET`, `$_POST` ya da
`$_REQUEST`'ten ilk okuduğu yerden önce geliyor (çağrısı olmayan ekranda
düşüyor). `php -l`, `tools/lint.php`, `tools/test.php` (954 test),
`check_lang`, `check_bindings`, `check_api_schema`, `check_copies` ve
`build_js.php --check` geçti.
Doğrulanamadı: dev.pinegrap.com'daki gerçek veriler.

**Bilerek yapılmayan.** Dizi isteği artık parametresiz açılışla aynı; bu
ekranların bir kısmı parametresiz açılışta zaten uyarı yazıyor (`add_field`,
`add_menu_item`, `generate_layout`, `preview_form`, `print_packing_slip`,
`check_if_file_exists`, `image_editor_edit`, `browse_catalog_menu`,
`get_theme_designer_module`): #417 notunun K7 ve K8'i, ayrı iş. Liveform
`h(null)` (K9) değişmedi; formların POST yolları taranmadı (tarama yalnız
GET), yardımcı onlarda da diziyi çıkarıyor.

---

## 2026.4.10 — Sipariş akışı: bilinmeyen, boş ya da dizi `page_id` siparişi sayfanın ayarları olmadan veriyordu (2026-10-14)

**Neden.** #421'den kalan madde. Hızlı sipariş (`express_order.php`),
fatura bilgileri (`billing_information.php`) ve sipariş önizleme
(`order_preview.php` → `submit_order()`) betikleri sayfanın ayarlarını
formun gönderdiği `page_id` ile okuyor, o kimlikte satır yoksa ayarsız
devam ediyordu. Kimlik bilinmeyen, boş, eksik, dizi (`page_id[]=`) ya da
başka türden bir sayfanın kimliği olunca:

- Hızlı sipariş ve sipariş önizleme siparişi veriyor, ama makbuz
  e-postası gitmiyor, koşullar sayfası onayı, özel alan 1/2 ve özel
  kargo/ödeme formunun zorunlu alanları sorulmuyor, sayfanın kendi
  çevrimdışı ödeme izni, otomatik üyelik ve kaydetme kancaları
  okunmuyor; ziyaretçi makbuz sayfası yerine ana sayfaya gidiyordu.
- Fatura bilgileri, özel alanları ve özel ödeme formunu sormadan
  faturayı tamamlanmış sayıyor, ziyaretçiyi formdaki `next_page_id`'ye
  gönderiyordu.

Kargo adresi adımı (`shipping_address_and_arrival.php`) bunu zaten
yapmıyordu: sayfa o türden değilse "A shipping address &amp; arrival page
could not be found for the id that was passed." hatasıyla duruyordu.
Hepsi 2026.4.9'da da var.

**Ne değişti.** İki yoldan durdurmak seçildi, oturumdaki sayfadan
ayarları almak değil: kargo adresi adımı zaten böyle duruyor ve
oturumdaki kimlik (`$_SESSION['ecommerce']['<adım>_page_id']`) yalnız
ziyaretçinin en son açtığı sayfayı söylüyor, formun geldiği sayfayı
değil. Silinen ya da türü değişen sayfada o da aynı bilinmeyen kimlik
olurdu.

- Yeni `pg_order_page_known($page_id, $page_type)`
  (`includes/fn/ecommerce.php`). Kimlik yalnız rakamdan oluşan sıfırdan
  büyük bir değerse ve o türden bir sayfayı gösteriyorsa doğrudur. Hızlı
  siparişte ayrıca görsel editörün hızlı sipariş widget'ının bulunduğu
  sayfa da sayılır: widget sayfasına bir `express_order_pages` satırı
  açar ve sayfanın türü `standard` kalır. Dizi, boş değer ve 0 sorgusuz
  reddedilir.
- `express_order.php`, `order_preview.php` ve `billing_information.php`
  CSRF denetiminden sonra, `initialize_order()`'dan önce bu denetimi
  yapıyor. Kimlik yanlışsa kargo adresi adımının desenini kullanıyorlar:
  `output_error()` ile "An express order page / An order preview page /
  A billing information page could not be found for the id that was
  passed." ve "Geri git" bağlantısı. Yanlış istek sipariş satırı
  açmıyor, var olanı değiştirmiyor.
- Ödeme ağ geçidinin dönüşü (`paypal_express_checkout_return`,
  `iyzipay_threedsecure_return`, `pay_with_iyzico_return`) durdurulmuyor.
  Dönüş, gönderimde denetlenmiş kimliği adresinde getiriyor; ödeme
  ağ geçidinde alınmış olabileceği için dönüşü kesmek yerine eski
  davranış korunuyor.
- Hızlı sipariş widget'ı (`widgets_cart.php`) sayfasını yalnız
  `$_GET['page']` adından buluyordu. Sitenin köküne açılan ana sayfada ad
  yok, bu yüzden form `page_id` 0 gönderiyordu: sipariş ayarsız
  veriliyordu ve yeni denetim onu durdururdu. Ad bir sayfa bulmazsa
  artık çizilen sayfanın kimliği (`pg_rendered_page()`, özel form
  widget'ının kullandığı kaynak) kullanılıyor; widget o sayfaya kendi
  `express_order_pages` satırını açıyor.

**Doğrulandı (sandbox, MariaDB, PHP 8.3).** Eski kod `origin/development`
worktree'sinden ikinci portta sunuldu; iki taraf da aynı veritabanı
dökümünden başladı. Örnek veriyle (aidat ürünü; klasik yol fatura
bilgileri → sipariş önizleme, hızlı sipariş sayfası) 19 istek denendi:

- Eski kod 10 istekte ayarsız sipariş verdi, makbuz e-postası gitmedi ve
  ziyaretçi ana sayfaya düştü: hızlı siparişte ve sipariş önizlemede
  bilinmeyen, boş, dizi, eksik ve başka türden kimlik. Fatura
  bilgilerindeki 3 istekte (bilinmeyen, dizi, başka tür) faturayı
  tamamlanmış saydı. Yeni kodda bu 13 isteğin hepsi hata sayfasında
  durdu, sipariş verilmedi.
- Bilinmeyen kimlikle 3 ağ geçidi dönüşü iki kodda da aynı sonucu verdi
  ve yeni kod durdurmadı.
- Normal hızlı sipariş, hızlı siparişin "Güncelle" düğmesi ve normal
  klasik sipariş iki kodla tamamlandı. `orders`, `order_items`,
  `ship_tos`, `contacts`, `form_data` ve `user` satırları bire bir aynı
  çıktı; fark yalnız rastgele referans kodu ile otomatik üyeliğin
  rastgele şifresinin özetinde. İki makbuz e-postası da tarih, Message-ID,
  MIME sınırı ve geçici şifre dışında aynı.
- Ana sayfaya konan hızlı sipariş widget'ı eskide `page_id` 0, yenide
  ana sayfanın kimliğini gönderdi. Siparişi iki kod da tamamladı.
- Hata günlüğüne iki tarafta da kayıt düşmedi.

`tests/order_page_known_test.php` eklendi: reddedilen kimlikler ve üç
betiğin denetimi `initialize_order()`'dan önce yapması.
`tools/test.php`, lint, dil, kopya, bağlama, API şeması ve
`build_js.php --check` geçti. Doğrulanamadı: dev.pinegrap.com'daki
gerçek ödeme adımları ve ödeme ağ geçitleri.

---

## 2026.4.10 — Geri Dönüşüm Kutusundaki ürün grubu grup seçicilerinde çıkmıyor, kutudaki grubun ekranı kaydetmiyor (2026-10-14)

**Neden.** #423'ün "Bilerek yapılmayan" maddesindeki iki grup ucu.
(1) Ürün ekranının iki grup seçicisi, sayfa ayarlarındaki beş grup
seçicisi (arama sonuçları, katalog, hızlı sipariş, sipariş formu,
alışveriş sepeti) ve kısa bağlantı ekle/düzenle kutudaki grubu "[DEVRE
DIŞI]" grup gibi sunuyordu ve seçilebiliyordu. #423 bunları bilerek
bırakmıştı: seçili kutu grubu listeden düşerse `<select>` ilk seçeneğe
düşer ve kayıt bağı koparır; tutmak için de ağaç yürüyüşü atalarını
istiyordu (grup kutuya alt ağacıyla gider, alt grubun üstü de kutudadır).
(2) Kutudaki grup `edit_product_group.php?id=…` ile uyarısız açılıp
kaydedilebiliyordu; Etkin işaretli Kaydet grubu kutudayken vitrine geri
koyuyor, `update_product_group_status()` altındakileri de açıyordu.

**Ne değişti.**
- `includes/fn/ecommerce.php`: `pg_catalog_bin_live_sql()`'in `$keep_id`'i
  artık bir liste de alır (çoklu seçim); kimlikleri `pg_catalog_keep_ids()`
  ayıklar (dizi içinde dizi, sıfır, eksi, yazı atılır). Yeni
  `pg_catalog_group_rows_past_bin($rows, $binned)` (saf fonksiyon): üstü
  kutuda olan satırı kutudaki ataların üstünden en yakın listelenen gruba
  asar; yalnız kutudaki ataları atlar (kapalı grup yüzünden listede
  olmayanın altı eskisi gibi erişilmez), `parent_id` döngüsünde satırı
  yerinde bırakır. Yeni `pg_catalog_group_binned()` tek grubu sorar.
  Yeni işaret yok, #416'nın `recycled` sütunu.
- `get_product_group_options()`: `$include_recycled = false` iken seçili
  grup (`$product_group_id`) ve yeni `$keep_ids` kutuda olsa da listede
  kalır, "[GERİ DÖNÜŞÜM KUTUSUNDA]" ekiyle; kutudaki gruplar
  `pg_catalog_group_map(true)`'dan (Katalog Yöneticisi'nin haritası)
  okunup satırlar yukarıdaki fonksiyondan geçer. Bu aynı zamanda kutudaki
  bir grubun altında tek başına geri yüklenmiş grubu seçicilerde yeniden
  görünür kılar. `true` (varsayılan) eskisi gibi; Katalog Yöneticisi'nin
  kısa bağlantı sihirbazı ve vitrin (`get_catalog.php`) değişmedi.
- Çağıranlar `false` verir: sayfa ayarlarının beş seçicisi (seçili ayar
  tutulur), ürün ekranının "Ürünü seçilen gruplara dahil et"i (ürünün
  bulunduğu gruplar `$keep_ids`; kayıt bağları postalanandan yeniden
  yazdığı için) ve varyant grubu üst grubu, kısa bağlantı düzenleme
  (liveform değeri) ve ekleme. Grup ekranının üst grup seçicisi #423'te
  üst grup kutudaysa bütün kutuyu listeliyordu (`$_parent_in_bin`); artık
  yalnız o üst grup işaretli kalır. Kaydetmedeki "üst grup gerekli"
  denetimi de aynı listeyi sayar.
- Grup ekranı: kutudaki grupta üstte uyarı ve "Geri Dönüşüm Kutusunu Aç"
  (`view_product_groups.php?view=bin`), kayıt çubuğunda Kaydet yerine aynı
  bağlantı, Sil yok (#423'teki ürün ekranının deseni). POST, token
  denetiminden hemen sonra hiçbir şey yazmadan aynı ekrana (`send_to`
  korunarak) döner. Çoğalt bağlantısı ve "Seçili ürünleri değiştir"
  (ürünlere gider, grubu yazmaz) kaldı.
- `tr.json`'a 1 anahtar.
- Test (`tests/catalog_bin_test.php`, +5): kimlik ayıklama, satır asma
  (kutudaki iki ata, listede tutulan kutu ebeveyni, kutu dışı nedenle
  eksik ebeveyn, döngü), seçicilerin `false` ve tutulan değerle çağrıldığı,
  grup ekranının yazmadan önce durduğu.

Şema değişikliği yok; dev'de yalnız `git pull` yeter.

**Doğrulandı (sandbox, MariaDB, PHP 8.3, Türkçe başlangıç sitesi).**
Mağaza (37, Tüm Ürün Grupları'nın altı) alt ağacıyla (27, 28, 43, 48, 49)
kutuya atıldı, Ofis Malzemeleri (28, altında 48) tek başına geri yüklendi.
Kısa bağlantı 1 grup 27'ye, katalog sayfası 997 grup 27'ye, katalog
sayfası 985 ve arama sonuçları 216 grup 37'ye bağlı; ürün 43 grup 27, 41,
51'de.
- Kısa bağlantı 1: 27 seçili, "Hediye Sepetleri [GERİ DÖNÜŞÜM
  KUTUSUNDA]" Tüm Ürün Grupları'nın altında; 37, 43, 49 yok; 28 ve 48
  Tüm Ürün Grupları'nın altında. Kaydet: `product_group_id` 27 kaldı.
  Kısa bağlantı ekleme: kutudakilerin hiçbiri yok.
- Sayfa 997: 27 seçili ve işaretli. 985 ve 216: 37 seçili ve işaretli,
  28 onun altında. 266 (sipariş formu), 248 (sepet), 256 (hızlı sipariş):
  kutudakiler yok.
- Ürün 43: 27, 41, 51 seçili, 27 işaretli; varyant grubu üst grubu
  seçicisinde kutudakiler yok. Kaydet: 27 bağı kaldı.
- Grup 28: üst grup seçicisinde yalnız 37 işaretli ve seçili, diğer kutu
  grupları yok; Kaydet: `parent_id` 37 kaldı. Grup 21 ve yeni grup:
  kutudakiler yok.
- Grup 27 ve 37: uyarı, tek düğme "Geri Dönüşüm Kutusunu Aç"; grup 21'de
  uyarı yok, Kaydet ve Sil var. 27'nin formu Etkin işaretli Save ve Delete
  ile postalandı: ikisi de ekrana döndü (302), satır (`enabled 0`,
  `recycled 1`), kutu kaydının tarihi ve ürün bağları değişmedi.
- Bu değişiklikten PHP uyarısı yok; grup kaydında 1278'deki `attributes`
  uyarısı eskiden var.

**Bilerek yapılmayan.** Katalog Yöneticisi'nin kısa bağlantı sihirbazı,
kasa ve stok barkod ekranları ve Dış API listeleri dokunulmadı. Görsel
tasarımcının sistem widget'larındaki grup seçicisi (`list_product_groups`,
`includes/panel/shared_component.php`) kutudaki grupları hâlâ işaretsiz
listeliyor; ayrı bir JSON ucu ve JS seçicisi olduğu için bu PR'a alınmadı.
Ekleme ekranlarında (kısa bağlantı) eski bir formun postaladığı kutudaki
grup reddedilmiyor (#423'teki ürün tarafı gibi).

---

## 2026.4.10 — Dizi gelen `send_to` ve `from` panel ekranlarında ve giriş sayfalarında 500 veriyordu (2026-10-14)

**Neden.** #417 taramasının bıraktığı K3. Kayıttan sonra dönülecek adres
(`send_to`) ve ekranın açıldığı yer (`from`) tek değerli; ekranlar onları
gizli alana ve bağlantılara `h()`, `urlencode()` ile basıyor. Elle yazılmış
`?send_to[]=x` ya da `?from[]=x` ile kişi, kullanıcı, alan, dosya, klasör,
menü öğesi, yönlendirme kaynağı ve tasarım dosyası ekleme, kişi ve kullanıcı
içe aktarma, klasör çoğaltma, reklam ve tasarımcı bölgesi, öne çıkan ve yeni
ürünler, JavaScript, tema CSS'i, tema dosyası, editör dosyası ve sistem
stili kaynağı ekranları 500 veriyordu; gerçek bir kayıtla açılınca sayfa,
kişi, klasör, dosya ve menü düzenleme ile menü öğeleri listesi de. Ön yüzde
giriş ve kayıt giriş sayfaları ile panelin giriş ekranı
(`/pinegrap/index.php?send_to[]=x`) da düşüyordu. Ürün ekleme / düzenleme, ZIP içe aktarma "Array to string"
uyarısı yazıyordu. Hepsi 2026.4.9'da da var.

**Ne değişti.** Serinin kuralı: dizi gelen tek değerli parametre "verilmedi"
sayılır, normal isteğin HTML'i değişmez, 500 yok.

- Yeni `pg_request_single_values($names)` (`includes/fn/core.php`,
  `pg_safe_redirect_path()`'in yanında): verilen adlar `$_GET`, `$_POST`
  ya da `$_REQUEST`'te dizi gelirse o kopyadan çıkarılıyor; metin olduğu
  gibi kalıyor. `get_page.php`'nin #417'deki `unset` döngüsünün aynısı.
- `init.php` onu magic quotes bloğundan hemen sonra `send_to` ve `from`
  için çağırıyor. **Neden tek yerden:** `send_to` ortak bir yerde okunmuyor,
  174 dosya onu doğrudan istekten okuyor (922 kullanım); bulunan ekranları
  tek tek düzeltmek taranmayan ekranları ve POST yollarını
  (`urlencode($_POST['send_to'])` ile kendine dönen kayıt ekranları) ve
  gelecek ekranları açıkta bırakırdı, K1 ve K2'nin değiştirdiği dosyalarla
  da çakışırdı. `init.php` her panel
  ekranından ve her site sayfasından önce çalışıyor ve hiçbir yerde
  `send_to[]` / `from[]` adlı alan yok.
- Yan etki, kural gereği: Çalışma Alanı ekranları
  (`includes/workspace/screen.php:557`) `?from[]=` ile "Array to string"
  uyarısı yazmıyor. ERP raporlarındaki aynı uyarıyı #427 kendi ekranlarında
  kapattı; iki düzeltme birbirine dokunmuyor.
  `update_exchange_rates.php?send_to[]=x` önceden kur listesine
  yönleniyordu, artık `send_to` verilmemiş gibi aynı ekranda kalıyor.

Şema, dil dosyası ve JS değişikliği yok. Test:
`tests/request_single_values_test.php` (3 test).

**Doğrulandı (sandbox, MariaDB, PHP 8.3, DEBUG açık).** #417'nin tarama
aracıyla 367 panel ekranı yönetici oturumuyla: parametresiz, `?send_to[]=1`,
`?from[]=1`, yine parametresiz (1.468 istek). Eski kodda 24 istek 500 (20
ekran), 38 istek parametresiz açılışta olmayan uyarı ya da ölümcül hata
yazdı (8 ayrı dosya:satır); yeni kodda 0 ve 0. Eski kod
(`origin/development`) ikinci portta, istekler sırayla iki sunucuya: 20
ekranın ve ürün ekranlarının metin `send_to` / `from`'lu 31 normal isteği
(ERP `?from=2026-01-01` dahil) ile oturumsuz 8 ön yüz isteği (ana sayfa,
giriş, kayıt girişi, şifremi unuttum, panel girişi; `send_to`'lu ve
`send_to`'suz) aynı HTML'i verdi. Farklı olanlar yalnız dizi istekler:
taramadakiler ve oturumsuz giriş, kayıt girişi, panel girişi 500 → 200.
Tarama kimliksiz açtığı için gerçek kayıtla açılan ekranlara ulaşmıyor;
#422 ve #424 birleştikten sonraki development (`c4db69e`) üstünde gerçek
kimliklerle 37 dizi istek iki sunucuya gönderildi: eski kodda 32'si 500
verdi (25 ekran; taramadakilere ek olarak sayfa, kişi, klasör, dosya ve
menü düzenleme ile menü öğeleri listesi), yenide hepsi 200; kalan 5'i
(ürün ekleme / düzenleme, ZIP içe aktarma, ERP denetim izi, Çalışma Alanı
görevleri) aynı HTML'i verdi ve eskinin "Array to string" uyarısını
yazmadı. `tools/lint.php`, `tools/test.php` (947 test), `check_lang`,
`check_bindings`, `check_api_schema`, `check_copies` ve
`build_js.php --check` geçti.
Doğrulanamadı: dev.pinegrap.com'daki gerçek veriler.

**Bilerek yapılmayan.** `send_to` verilmediğinde zaten uyarı yazan
ekranlar (ör. `update_currency.php`, eksik parametre: K7) dizide de aynı
uyarıyı yazıyor. `update_currency.php` `send_to`'yu `pg_safe_redirect_path()`
olmadan `Location`'a koyuyor (jetonlu POST; ayrı iş). K1, K2, K4 (#422,
#424, #427) dosyalarına ve K5–K9'a dokunulmadı.

---

## 2026.4.10 — ERP ekranları dizi gelen süzgeç, tarih ve aramada "Array to string conversion" uyarısı yazıyordu (2026-10-14)

**Neden.** #417'nin panel taramasında K4 olarak kalan iş. ERP ekranları
adresteki tek değerli parametreleri `(string) ($_GET['x'] ?? '')` ile
okuyordu; değer dizi gelince (`?status[]=x`, `?from[]=x`) PHP "Array" yapıp
her istekte php_errors.log'a "Array to string conversion" yazıyordu. 500
yoktu: değer "Array" metniyle aranıyor ya da beyaz listede bulunmayıp
varsayılana düşüyordu. Sandbox'ta 68 ERP ekranı, `notes/panel-tarama-araclari`
taramasının genişletilmiş hâliyle (kayıt isteyen ekranlar 1 numaralı cariyle,
giderler tarih aralığıyla, kasa akışı tahmin görünümüyle de) 1.406 istek: eski
kodda 28 ekranda 83 istek, 29 dosyada 68 ayrı satır uyarı yazdı. Hepsi
2026.4.9'da da var.

**Ne değişti.** Serinin kuralı: dizi gelen tek değerli parametre "verilmedi"
sayılır, normal isteğin çıktısı değişmez.

- Yeni yardımcı eklenmedi: serinin (#384–#422) satır içi `is_string()`
  deseni kullanıldı. Ortak bir "istekten metin" yardımcısı her okuma yerini
  yine tek tek değiştirmeyi gerektirirdi; tek satırda birçok yeri kurtaran
  yer yalnız aşağıdaki üç kütüphane fonksiyonuydu.
- Değer okuyan satırlar `is_string($_GET['x'] ?? null) ? $_GET['x'] : <eski
  varsayılan>` oldu: `erp_audit`, `erp_cashflow` (hızlı aralık),
  `erp_cheques`, `erp_expenses`, `erp_inbox`, `erp_invoices`, `erp_profit`,
  `erp_purchase_orders`, `erp_quotes`, `erp_stock_minimums`,
  `erp_vat_report`, `erp_waybills`, `erp_account_prices`, `erp_account_sync`,
  `erp_accountant_download`, `get_erp_contacts`, `get_erp_drawer`,
  `get_erp_products`, `get_erp_rate`, `add_erp_manual_invoice`,
  `add_erp_receipt`. `isset($_GET['x']) ? (string) $_GET['x']` biçimindekiler
  (`erp_export` `download` / `entity` / `done`, `erp_profit` `orders`,
  `get_erp_products` `barcode`) koşula `is_string()` aldı; dizi `download`
  artık "dosya artık yok" uyarısıyla yönlenmiyor, dizi `barcode` arama
  sonucuna düşüyor (verilmemiş gibi).
- Yalnız katı karşılaştırmada kullanılan değerlerde (`=== 'csv'`, `===
  'xlsx'`, `=== 'forecast'`, `=== 'xml'`, `in_array(..., true)`) `(string)`
  kaldırıldı: dizi hiçbir metne eşit değil, uyarı da yok (`erp_aging.php`
  zaten böyle okuyordu).
- `erp_account_sync_item.php` `q`'yu beş yerde okuyor; dizi gelirse başta
  `$_GET`'ten çıkarılıyor (#417'deki `get_page.php` gibi).
- `erp_aging_as_of()`, `erp_cashflow_options()` (tarih ve gruplama) ve
  `erp_reconciliation_options()`: dizi değer tarih sayılmıyor
  (`is_scalar()`), varsayılana düşüyor. Yaşlandırma, faturalar, kasa akışı,
  mutabakat ekranları ve API aynı fonksiyonlardan geçiyor; skaler girdide
  davranış birebir aynı. `get_erp_reconciliation_pdf.php`'nin kendi tarih
  okuyucusu da `is_string()` aldı.
- Kimlik (`id`, `K1`, #422) ve `send_to` (K3) satırlarına dokunulmadı.
- Yeni `tests/erp_request_values_test.php`: hiçbir ERP ekranı adresteki
  değeri `(string)` ile okumuyor; üç kütüphane fonksiyonu dizi tarihte
  varsayılanı veriyor. Eski kodda iki test de düşüyor.

Şema, dil dosyası ve JS değişikliği yok.

**Doğrulandı (sandbox, MariaDB, PHP 8.3, `debug = 1`).** Aynı 1.406 istek
yeni kodda: uyarı yazan istek 83 → 0, ayrı satır 68 → 0; durum kodları aynı
(bir istek: `erp_export?download[]=` 302 → 200, verilmemiş sayıldığı için).
Kalan tek günlük satırı `liveform.class.php:1376` `h(null)` kullanım dışı
uyarısı (K9, iki kodda da aynı). Eski kod (`origin/development`) ikinci
portta: değişen 30 dosyanın 71 normal isteği (süzgeçler, tarih aralıkları,
çeyrek / yıl, tahmin ufku, dışa aktarma, çekmece, ürün ve kur uçları, cari
fiyatları, mutabakat ve HTML mektubu, elle fatura, tahsilat) aynı HTML'i
verdi; tek fark `erp_audit?format=csv`'nin, iki sunucunun ortak
veritabanına yazdığı "dışa aktarıldı" satırının sayısı. `php -l`,
`tools/lint.php`, `tools/test.php`, `check_lang`, `check_bindings`,
`check_api_schema`, `check_copies` ve `build_js.php --check` geçti.
Doğrulanamadı: e-belge sağlayıcısı isteyen `erp_account_sync_item.php`
(sandbox'ta sağlayıcı yok) ve dev.pinegrap.com'daki gerçek veriler.

**Bilerek yapılmayan (kalanlar).** ERP ekranlarında `(int) ($_GET['account_id']
?? 0)` gibi kimlik dışı sayı okumaları dizide sessizce 1 oluyor (uyarı yok;
`erp_invoices?account_id[]=x` 1 numaralı carinin faturalarını süzer). POST
işleyicileri taranmadı. K5–K9 notta.

---

## 2026.4.10 — Ziyaretçi tablosu: MyISAM'da kalan tablo kendiliğinden InnoDB'ye taşınıyor, botlar ziyaret olarak yazılmıyor (2026-10-14)

**Neden.** Bir müşteri sitesinde yanlışlıkla silinen bir ürünü geri almak
için 950 MB'lık veritabanı yedeği kurulamadı, sunucu yanıt vermedi; boyutun
çoğu `visitors` tablosuydu. 10.3 saklama süresini ve parça parça silmeyi
getirdi. Mevcut bir sitede kimse Veritabanı Motoru kartına bakmadan iki şey
eksik kalıyordu. Birincisi, tablosu MyISAM'da kalmış siteler (2026.3.6'nın
`ALTER`'i koşmamış ya da bitmemiş olabilir; Veritabanı Motoru'nun taşıma
listesi `visitors`'ı içermiyor). MyISAM silmeden sonra dosyayı küçültmez,
açılan delikler eşzamanlı eklemeyi kapatır ve her yazma tabloyu kilitler;
kart bunu yalnız söylüyordu. İkincisi, güvenlik duvarı kapalı
sitelerde arama motorlarının gezdiği her sayfa ziyaret olarak yazılıyordu.
Plan `docs/_plan_ziyaretci_temizleme.md` ("İkinci parça").

**Yükseltmede 90 gün için yeni şema adımı yok.** 10.3 sütunu `DEFAULT 90`
ile ekliyor; 2026.4.10'a güncellenen her site 90 günle başlar, genel görev
eski kayıtları kimse başlatmadan siler. 0, yalnız birinin elle seçtiği
değerdir (dev'de ziyaretçi satırlarını korumak için bilerek 0); onu 90'a
çeviren bir adım bu seçimi ezerdi. Yedek (`mysqldump-php`) satırları
döktüğü için temizlik biter bitmez yedek de küçülür; dosyanın küçülmesini
beklemez.

**Ne değişti.**
- `includes/fn/visitor_retention.php`: `pg_visitor_move_run()` genel görevde
  (`job.php`), temizliğin işi kalmadığı turda, 15 sn. Durum
  `data/temp/visitor_move.json`'da; taşıma sürmüyorsa tur yalnız bu dosyayı
  okur, saatte bir (tablo MyISAM değilse ya da taşıma bittiyse günde bir)
  birkaç küçük okumayla bakar.
- Kendiliğinden başlama koşulları (`pg_visitor_move_hold_reason()`, sırayla):
  tablo MyISAM; sunucu InnoDB destekliyor (`pg_innodb_capability()`);
  satırlar bir InnoDB sayfasına sığıyor (`pg_innodb_row_estimate()`);
  `visitors_myisam` adında tablo yok; saklama süresi 0 değil; temizlik
  bitmiş, birikmiş iş yok; son başarısız denemenin üstünden bir hafta
  geçmiş; veritabanı sunucusunun boş diski okunabiliyor ve en az
  3 × (veri − boş alan + dizin) + 1 GB. Disk yalnız veritabanı aynı
  makinedeyse (`localhost`, `127.0.0.1`, `::1`, `.`, soket; `p:` öneki ve
  port atılır) `disk_free_space(@@datadir)` ile okunur; okunamıyorsa
  kendiliğinden başlamaz ve kart bunu söyler.
- Taşıma, site çalışırken:
  1. `CREATE TABLE visitors_innodb LIKE visitors`, tablo boşken `ALTER …
     ENGINE=InnoDB, ROW_FORMAT=DYNAMIC` ve 10.3'ün InnoDB'de kaldırdığı iki
     dizin (`ip_address`, `idx_user_agent`) düşer. Sütunlar ad ve tipiyle
     karşılaştırılır; tutmazsa kopya silinip baştan kurulur.
  2. Kopya birincil anahtar pencereleriyle, 2.000 satır:
     `INSERT INTO visitors_innodb SELECT * FROM visitors WHERE id > a AND
     id <= b`; parçalar arasında en az parçanın sürdüğü kadar (≥ 20 ms)
     ara. İmleç işaret dosyasında, her tur kaldığı yerden.
  3. Yetişince değiştirme. Birinci geçiş: kopya başladığından beri değişen
     satırlar (`stop_timestamp >= başlangıç − 60`) `DELETE … WHERE id IN`
     ve `INSERT … SELECT … WHERE id IN` ile yeniden kopyalanır. İkinci
     geçiş: kopyanın `AUTO_INCREMENT`'i `MAX(id) + 1000`, aradaki yeni
     satırlar ve son saniyelerde değişenler. Sonra
     `SET SESSION lock_wait_timeout = 5` ve tek ifadede
     `RENAME TABLE visitors TO visitors_myisam, visitors_innodb TO
     visitors`. Ad değişiminden hemen önce eskiye düşen yeni satırlar
     yeniye eklenir.
  4. Denetim: eski tablodaki saklama süresi içindeki her satır yenide var
     mı (anti-join, `start_timestamp >= sınır`). Eksik varsa eklenir;
     hâlâ eksik varsa taşıma "durdu" olur, eski tablo `visitors_myisam`
     adıyla kalır, kart söyler. Yoksa eski tablo silinir, kart 30 gün
     "önce X, şimdi Y" der, Site Günlüğü'ne bir satır düşer.
  - İfade tabanlı replikasyonda güvenli kalsın diye `INSERT … SELECT …
    LIMIT`, `REPLACE … SELECT` ve `INSERT IGNORE … SELECT` yok; pencere id
    aralığıyla, yeniden kopya DELETE + INSERT ile.
  - Sorgular `db()` yerine `@mysqli_query` ile (`db()` hatada çıkıyor): bir
    hata kopyayı siler, tablo MyISAM'da kalır, kart nedenini söyler.
  - Temizlikle aynı `GET_LOCK`; ikisi aynı anda koşmaz. Taşıma sürerken
    Optimize düğmesi kapalı.
  - Bilinen kayıp: ad değişiminin yapıldığı an eski tablodaki bir satıra
    gelen sayfa görüntüleme güncellemesi yenisine geçmeyebilir (200 bin
    satırlık denemede 3 görüntüleme).
- Veritabanı Motoru › Ziyaretçi kayıtları kartı: uzun MyISAM uyarısı ilk
  cümlesine indi, yanına neden henüz taşınmadığı (disk okunamıyor ya da
  yetmiyor, süre 0, temizlik sürüyor, sunucu ya da satır genişliği izin
  vermiyor, ad dolu) ya da taşımanın ilerlemesi ("yaklaşık N kaydın M
  tanesi kopyalandı") geliyor. Yeni "Şimdi InnoDB'ye taşı" düğmesi (onay
  metninde gereken boş disk) disk koşulunu ve beklemeleri atlar, ilk 20
  sn'yi hemen koşar, kalanını genel görev sürdürür.
- `get_page.php` `init_tracking()`: `IS_BOT` yalnız güvenlik duvarı
  çalışınca tanımlanıyordu; duvar kapalıyken, izinli adreste ya da hariç
  tutulan yolda her tarayıcı sayfası ziyaret yazılıyordu. Artık
  `waf_classify_bot()` insan dışı derse (arama motoru, bilinen bot, araç,
  boş kullanıcı ajanı) ziyaret yazılmıyor; engelleme ya da günlük yok.
- 19 yeni dil anahtarı; bir anahtar ilk cümlesine indi.

Şema ve JS değişikliği yok.

**Ölçüm (sandbox, MariaDB 10.11, 128 MB buffer pool; 3,8 milyon satır,
400 gün, 90 günle ~2,9 milyon satır silinecek; aynı anda siteyi taklit eden
dört işçi: ziyaret ekleme, sayfa görüntüleme güncellemesi, tekilleştirme,
son bir saat sayımı; genel görev gibi 15 sn'lik turlar).**

- MyISAM (3.800.000 satır, 868 MB veri + 211 MB dizin): temizlik 25 tur,
  ardışık turlarla 390 sn; 2.940.590 satır silindi, 310 gün özetlendi.
  Temizliğin bittiği turda taşıma başladı: 518.000 satır 15,0 sn'de,
  383.395 satır 12,6 sn'de, değiştirme + denetim + eski tablonun silinmesi
  4,3 sn; toplam 3 tur, 32 sn. Sonuç InnoDB, ~900 bin satır, 253 MB veri +
  56 MB dizin: 1,1 GB → 295 MB. Dakikada bir koşan genel görevle temizlik
  ~25 dakika, taşıma ~3 dakika sürer (gerçek diskte daha çok tur).
- Yük, MyISAM: işten önce p99 1–2 ms. Temizlik turlarında p99 50–71 ms, en
  uzun 141 ms. Taşıma turlarında p99 34–42 ms, en uzun 271 ms; o da ad
  değişimi değil, 1 GB'lık eski tablonun silindiği an (yavaş sorgu
  günlüğünde `DROP TABLE` 0,66 sn). 500 ms'yi geçen tek sorgu yok.
  Denetimin anti-join'i 2,7 sn sürüyor ama yeni tablo InnoDB (tutarlı okuma)
  ve eski tabloya kimse yazmıyor, siteyi bekletmiyor.
- Bütünlük, MyISAM: koşu sırasında yükün yazdığı 102.621 ziyaretin hiçbiri
  eksik değil; 171.229 sayfa görüntüleme güncellemesinin 6'sı ad değişimi
  anında kayboldu. Aynı deneme önceki kopya koduyla (ORDER BY LIMIT
  pencereleri): 26 + 3 tur, en uzun 273 ms, eksik 0, kayıp 3.
- InnoDB (aynı 3,8 milyon satır): temizlik 15 tur, 205 sn; 2.940.590 satır,
  310 gün. Yük p99 ≤ 15 ms, en uzun 61 ms. Taşıma bakışı `none`, 0,00 sn.
  Eksik ve kayıp 0.
- 200 bin satırlık işlev denemesi (üç işçi): taşıma 6,3 sn, eksik 0, seed
  satırlarının sağlama toplamı aynı.

`tests/visitor_retention_test.php`'ye yerel sunucu, gereken disk ve
başlama koşulları için dört test. `tools/lint.php`, `tools/test.php` (948
test), `check_lang`, `check_bindings`, `check_api_schema`, `check_copies`
ve `build_js.php --check` geçti.

**Doğrulanamayan.** MySQL 8.0'da süreler (sandbox MariaDB); gerçek
sunucunun diski; uzak veritabanı sunucusunda taşıma (kendiliğinden
başlamaz, düğmeyle denenmedi); dev.pinegrap.com'da tablo zaten InnoDB,
taşıma orada çalışmaz.

**Bilerek yapılmayan (plan, "Ertelenenler").** Yedekten ziyaretçi
satırlarını çıkarmak (ürün kararı, madde 7); büyük yedeğin parça parça
geri yüklenmesi (madde 8); InnoDB'de silmeden sonra dosyanın kendiliğinden
küçülmesi (madde 5); uzun metin sütunlarını ayırmak (madde 2), aylık
bölümleme (madde 3), IP'nin erken silinmesi (madde 4).

---

## 2026.4.10 — Kapısı olmayan 37 panel ekranı `?id[]=` ile 500 vermiyor, kimliksiz ve var olmayan kayıtta "Kayıt bulunamadı" (2026-10-14)

**Neden.** #417'nin panel taramasından kalan K2. Bu ekranlarda c651716'daki
kayıt kapısı hiç yoktu: kimliği doğrudan `escape()`, `e()`, `h()` ya da
liveform dizinine (`new liveform('x', $_REQUEST['id'])`) veriyorlardı.
`?id[]=x` 500 veriyordu; kimliksiz ya da silinmiş kayıtla açılınca yarı boş
form basıp 1–21 uyarı yazıyorlardı (`edit_javascript`, `edit_theme_css`
kimliksiz açılışta `data/files` klasörünü dosya diye okumaya çalışıyordu).
Birkaçında kayıt yoksa durduran bir denetim vardı (`edit_comment`,
`edit_custom_style`, `edit_field`, `edit_file`, `edit_menu_item`,
`edit_submitted_form`, `view_order`, `duplicate_folder`, `view_menu_items`)
ama dizi o denetime varmadan `escape()`'te düşüyordu. Hepsi 2026.4.9'da da
var.

**Ne değişti.** 32 ekrana alan erişim denetiminin hemen ardına #417 /
#422'deki kapı: `$id = is_scalar($_REQUEST['id'] ?? null) ? (int)
$_REQUEST['id'] : 0;` ve ekranın tablosunda `COUNT(*)`; kayıt yoksa
`output_error(lang('Record not found.') …, 404)`. Tablolar: `ad_regions`,
`arrival_dates`, `auto_dialogs`, `calendar_event_locations`, `comments`,
`commissions`, `cregion.cregion_id` (ortak ve tasarımcı bölgesi),
`contact_groups`, `style.style_id` (özel stil, görsel editör, kaynak
görünümü), `files` (dosya, tasarım dosyası, JS/CSS/tema/editör),
`dregion.dregion_id`, `email_campaign_profiles`, `form_fields`,
`login_regions`, `menus` (menü, menü öğeleri listesi), `menu_items`,
`recurring_commission_profiles`, `ship_date_adjustments`, `forms`,
`verified_shipping_addresses`, `folder.folder_id`, `product_groups`,
`orders`, `email_campaigns`. Kapı, kimliği kullanan ilk satırdan (rol
denetimi, kişi grubu erişim denetimi, liveform) önce duruyor; `$_REQUEST`
POST'ta gönderilen gizli `id`'yi de okuduğu için kaydet/sil yolu aynı
kapıdan geçiyor (`edit_auto_dialog` formu gizli alan taşımıyor, kimlik
form adresinden geliyor). Özel durumlar:
- `edit_page.php`: kapı, ekranın yetki denetimi için seçtiği
  `$requested_page_id`'yi okuyor (POST'ta `$_POST`, değilse `$_GET`; aynı
  adlı çerez denetimi kandırmasın diye).
- `view_email_campaign.php`: kapı yalnız yönetici yolunda; alıcı
  bağlantısındaki `?r=` dizi gelirse ya da yoksa "verilmedi" sayılıyor
  (`$_GET['r']` boş metne çekiliyor). Bilinmeyen referans kodu, kişisi
  silinmiş alıcı ve geçerli kodla açılışta yazılan `$user` uyarıları da
  gitti (görünen çıktı aynı).
- `view_order_report.php`, `view_visitor_report.php`: kimlik isteğe bağlı
  (kimliksiz = yeni rapor). Dizi kimlik `unset` ediliyor, ekran yeni rapor
  olarak açılıyor; verilen kimlik var olmayan bir rapora aitse "Kayıt
  bulunamadı" 404.
- `add_theme_file.php`: `id` yalnız klasörü önceden seçiyor; dizi gelirse
  verilmedi sayılıyor.
- Normal açılışta uyarı yazan iki yer: `edit_design_file.php` resim ya da
  düzenleyicisi olmayan dosyada `$output_thumbnail`, `$output_image_info`,
  `$output_edit_file_button` tanımsızdı ve sonda hiç kurulmamış `$output`
  basılıyordu (satır kaldırıldı, boş metin basıyordu); `edit_menu_item.php`
  seviyesinin ilk öğesinde üstte öğe yokken `$row['id']` null'dan
  okunuyordu.

Var olan "bulunamadı" denetimleri yerinde bırakıldı (artık kapı önce
çalışıyor; var olmayan kimlikte metin "Kayıt bulunamadı." ve durum 404).
Şema, dil dosyası (`Record not found.` ve `Go back` zaten var), JS değişikliği
yok.

**Doğrulandı (sandbox, MariaDB, PHP 8.3, DEBUG açık).** Boş tablolara
(reklam bölgesi, komisyon, yinelenen komisyon, kargo günü ayarı, doğrulanmış
adres, e-posta kampanyası ve alıcısı, dinamik bölge) birer kayıt eklendi.
`panel-tarama-araclari/scan.py` 37 ekranda (parametresiz, kendi
parametreleri `=1`, her parametre dizi olarak; 680 istek): eski kodda 49
istek 500 verdi (37'si `?id[]=`, 8'i `?send_to[]=`, 3'ü `?from[]=`, 1'i
`?r[]=`; send_to/from olanlar kimliksiz isteklerdi), 578 istek uyarı ya da
ölümcül hata yazdı (176 ayrı satır). Yeni kodda 500 yok; uyarı yazan tek
istek `view_order_report.php?id=1` (aşağıda). Kalan kullanım dışı satırı
yalnız 404 ekranının liveform `h(null)`'ı (K9). Eski kod
(`origin/development`) ikinci portta, istekler sırayla iki sunucuya: var
olan kayıtla 44 normal panel açılışı (her ekran, resim/CSS/JS tasarım
dosyası, seviyenin ilk ve ikinci menü öğesi, kimliksiz yeni rapor) ve 3
alıcı bağlantısı (geçerli kod, bilinmeyen kod, kodsuz) aynı HTML'i verdi;
yeni kodda bunlar uyarı yazmıyor (`view_order_report.php?id=1` dışında). 74 kötü istekte: `?id[]=1`
eskide 37'si 500, yenide 34'ü 404, rapor ekranları ve `add_theme_file` 200;
`?id=999999` eskide 30'u 200 (yarı boş form) ve 6'sı 404, yenide 36'sı 404
(`add_theme_file` iki kodda aynı). 30 ekranda form değiştirilmeden
yeniden gönderildi: hepsi eskisi gibi yönlendirdi, hiçbiri kapıya takılmadı.
`tests/panel_id_gate_test.php`'ye bu ekranların kapıyı taşıdığını denetleyen
test eklendi. `tools/lint.php`, `tools/test.php`, `check_lang`,
`check_bindings`, `check_api_schema`, `check_copies` ve
`build_js.php --check` geçti.
Doğrulanamadı: dev.pinegrap.com'daki gerçek veriler; görsel editörün Ajax
kaydı tarayıcıda denenmedi (form gizli `id`'yi gönderiyor, kod okumayla).

**Bilerek yapılmayan.** K1 (#422) dosyalarına dokunulmadı; çakışan dosya
yoktu. `send_to[]` / `from[]` (K3) kapıdan geçen geçerli kimlikle hâlâ 500
veriyor: taramada yalnız kimliksiz istek oldukları için 404'e düştüler.
`view_order_report.php` normal açılıştaki `$sql_summarize_by_3` ve
`$output_date_changer` uyarıları K6'da. `view_order.php` POST yolunda
liveform'un `id`'si (`(int) $liveform->get('id')`) aynen kaldı: kapı artık
diziyi oraya ulaştırmıyor. Liveform `h(null)` (K9) dokunulmadı.

---

## 2026.4.10 — Geri Dönüşüm Kutusundaki ürün seçicilerde çıkmıyor, grup ekranı kutudakileri saymıyor, ürün ekranı kutudaki ürünü kaydetmiyor (2026-10-14)

**Neden.** #416'nın "Bilerek yapılmayan" maddesindeki üç uç. Kutu bir
bayrak (`recycled = 1`, `enabled = 0`), satır yerinde duruyor; bu yüzden
(1) ürün seçen ekranlar kutudaki ürünü "devre dışı" ürün gibi sunuyordu
ve seçilebiliyordu; (2) ürün grubu ekranı kutudaki alt grubu alt grup
olarak listeleyip sayıyordu (alt grubu yalnız kutuda olan grup "önce alt
grupları silin" diyordu), kutudaki ürünü de grubun ürünleri arasında
gösteriyordu; (3) kutudaki ürün `edit_product.php?id=…` ile uyarısız
açılıp kaydedilebiliyordu. Vitrin yalnız `enabled`'a baktığı için Durum
açık kaydedilen ürün kutudayken yeniden satışa çıkıyordu; Sil de kutu
kaydını yenileyip geri sayımı baştan başlatıyordu.

**Ne değişti.**
- `includes/fn/ecommerce.php`: `pg_catalog_bin_live_sql($table, $keep_id
  = 0)` Katalog Yöneticisi'nin süzgecini (`pg_catalog_live_filter()`)
  `pg_catalog_bin_ready()` kapısıyla verir; kutu yoksa boş. `$keep_id`
  verilirse o satır kutuda olsa da kalır. `pg_catalog_product_binned()`
  tek ürünü sorar. Yeni işaret yok, #416'nın `recycled` sütunu.
- Seçiciler: `get_product_options()` (kısa bağlantı, takvim etkinliği,
  e-posta kampanyası, Katalog Yöneticisi'nin kısa bağlantı sihirbazı) ve
  `select_product()` (ürün ekranının "gerekli ürün"ü) kutudakini
  bırakır; düzenleme ekranları (`edit_short_link.php`,
  `edit_calendar_event.php`, `edit_email_campaign_profile.php`) kaydın
  zaten gösterdiği ürünü `$keep_id` olarak verir ve o seçenek
  "[GERİ DÖNÜŞÜM KUTUSUNDA]" ekiyle kalır. Kalmasaydı `<select>` ilk
  seçeneğe düşer, sonraki kayıt bağlantıyı sessizce başka ürüne ya da
  boşa çevirirdi. Kampanya editörü (`_pg_offer_products()` +
  `offer_editor.js`) listeyi etiketler için tam tutar, ürünlere
  `recycled` ekler; seçici kutudakini yalnız satırın zaten seçtiği ürünse
  gösterir, hızlı başlangıç ilk kutuda olmayan ürünü alır. Ürün grubu
  ekranlarının "seçilmemiş ürünler" listesi ve sayısı (`add_product_group.php`,
  `edit_product_group.php`, kaydırınca yükleyen `get_unselected_products`),
  ERP belge satırı araması (`erp_product_search()`, barkod eşleşmesi ve
  `get_erp_products.php?barcode=` dahil), panel araması (ürün ve ürün
  grubu), Çalışma Alanı ürün referansı araması (grubunki zaten
  `recycled = 0` idi) ve pazaryeri eşleştirme listesi süzülür.
- Grup ekranı (`edit_product_group.php`): alt grup listesi ve
  `$child_product_group_exists` kutudakini saymaz; grubun ürün listesi
  kutudaki ürünü göstermez. Kaydetme `products_groups_xref`'i silip
  postalanandan yeniden yazdığı için kutudaki ürünün satırı silmeden
  korunur (`AND product NOT IN (SELECT id FROM products WHERE recycled =
  '1')`), eski bir formun yine postaladığı kutudaki ürün ikinci kez
  eklenmez (tabloda benzersiz anahtar yok). Üst grup seçicisi
  (`get_product_group_options()`'a `$include_recycled`, yeni grup
  ekranında da) kutudaki grupları göstermez; grubun kendi üst grubu
  kutudaysa (tek başına geri yüklenmiş alt grup) liste eskisi gibi tam
  kalır, yoksa kayıt grubu ilk seçeneğe taşırdı. Diğer grup seçicileri
  (ürün ekranının grupları, sayfa ayarları, kısa bağlantı) değişmedi:
  oradaki seçili kutu grubu tutulmazsa kayıt bağı koparırdı.
- Ürün ekranı: `edit_product.php` kutudaki ürünü gösterir ama POST'u
  hiçbir şey yazmadan aynı ekrana geri gönderir (Kaydet de Sil de).
  `pg_pb_render_product_screen()` `recycled` bağlamıyla üstte uyarı ve
  "Geri Dönüşüm Kutusunu Aç" (`view_product_groups.php?view=bin`)
  gösterir, kayıt çubuğunda Kaydet/Sil yerine aynı bağlantı durur.
  Geri yükleme Katalog Yöneticisi'nin kutusunda; ikinci bir geri yükleme
  kodu yazılmadı. Ürün listesinin satır içi düzenlemesi
  (`pg_pb_quick_update()`) kutudaki ürünü "Ürün bulunamadı." diye
  reddeder.
- `tr.json`'a 3 anahtar.
- Test (`tests/catalog_bin_test.php`, +4): seçicilerin ortak yardımcıyı
  kullandığı, düzenleme ekranlarının mevcut ürünü verdiği, grup
  ekranının kutudakini ne listeleyip ne düşürdüğü, ürün ekranının
  yazmadan önce durduğu. Düzeltme öncesi kodda 4'ü kırmızı.

Şema değişikliği yok; dev'de yalnız `git pull` yeter.

**Doğrulandı (sandbox, MariaDB, PHP 8.3, Türkçe başlangıç sitesi).**
Mega Hediye Sepeti (43, gruplar 27/41/51) ürün ekranının Sil'iyle,
İndirilenler (49, Mağaza'nın alt grubu) grup ekranının Sil'iyle kutuya
atıldı; bir kısa bağlantı 43'e, biri 42'ye bağlıydı.
- Ürün ekranı 43: uyarı ve iki kutu bağlantısı, Kaydet/Sil yok; Durum
  açık Kaydet POST'u ve Sil POST'u ekrana döndü, satır (`enabled 0`,
  `recycled 1`) ve kutu kaydının tarihi değişmedi. 42'de uyarı yok,
  düğmeler var. Satır içi düzenleme 43'e 422 `not_found`.
- Kısa bağlantı ekleme: 43 yok, 42 var. 43'e bağlı bağlantının
  düzenlemesi: 43 seçili, etiket "CGB-078 - Mega Hediye Sepeti (₺79,95)
  [GERİ DÖNÜŞÜM KUTUSUNDA]"; 42'ye bağlı olanda 43 yok.
- Grup 27: ürün listesinde 43 yok, 41 var; seçilmemiş listede 43 yok.
  27, eski formdaki gibi 41, 42 ve 43 postalanarak kaydedildi: 43'ün 27
  bağı tek satır ve sıra numarasıyla kaldı. Mağaza (37) alt grupları 27,
  28, 43 (49 yok); üst grup seçicilerinde 49 yok. Hediye Dükkanı'nın (51)
  tek alt grubu (56) kutuya atılınca Sil düğmesi gerçek silme oldu.
- Kampanya editörü: JSON'da 43 `recycled: 1`; "hediye" hızlı
  başlangıcının iki ürün seçicisinde 43 yok, 42 var.
- Panel araması "Hediye Sepeti" → 41, 42; "ndirilenler" grubu → 23 (49
  yok). Çalışma Alanı ve ERP açılınca ürün referansı ve belge araması →
  41, 42; `?barcode=CGB-078` → `null`, `CGB-077` → 42.
- Katalog Yöneticisi'nden 43 geri yüklendi: `enabled 1`, ürün ekranı
  Kaydet'li, kısa bağlantı eklemede 43 var, grup 27'nin listesinde 43
  yeniden işaretli (bağ korunduğu için).
- Takvim etkinliği, e-posta kampanyası, kampanya, pazaryeri, ERP cari
  fiyatları ekranları 200; bu değişiklikten PHP uyarısı yok
  (`edit_calendar_event.php` 215/455'teki `recurrence_number` uyarısı
  eskiden var).

**Bilerek yapılmayan.** Ürün ekranının grup seçicisi, sayfa ayarlarındaki
ve kısa bağlantıdaki grup seçicileri kutudaki grubu "[DEVRE DIŞI]"
olarak göstermeye devam ediyor (seçili olanı tutmak ağaç yürüyüşünde
atalarını da gerektiriyor). Depo girişi/çıkışı barkod ekranları, kasa
(`local_sale.php` barkod eşleşmesi) ve Dış API listeleri dokunulmadı.
Kutudaki grubun kendi ekranı (`edit_product_group.php?id=…`) uyarısız
açılıyor. Katalog Yöneticisi'nin kısa bağlantı sihirbazı listeyi bir kez
ve kayıttan bağımsız aldığı için kutudaki ürüne giden bir bağlantıyı
orada düzenlemek ürünü yeniden seçtiriyor (klasik
`edit_short_link.php` tutuyor).

---

## 2026.4.10 — Sipariş akışı: hızlı sipariş, kargo adresi, fatura bilgileri ve sipariş önizleme dizi gelen değerde 500 veriyordu (2026-10-14)

**Neden.** #407 site formlarını temizlerken `submit_custom_form()`'u ve
onu çağıran dört sipariş betiğini ayrı işe bıraktı. Sandbox'ta eski kodla
(kargolu ürün ve aidat; klasik ödeme adımları — kargo adresi, fatura
bilgileri, sipariş önizleme — ve hızlı siparişin güncelle, satın al ve
yalnız aidatla satın al yolları; her alan ve her özel kargo/ödeme formu
alanı tek tek dizi olarak, ayrıca formda olmayan ama betiğin okuduğu
alanlar; toplam 347 istek) yeniden üretildi: 180 istekte HTTP 500, 137'sinde
uyarı. Hepsi 2026.4.9'da da var.

Görünür hatalar (500, hepsi `TypeError`):

- `liveform::add_fields_to_session()` `alan[]=x`'i dizi olarak saklıyor,
  betikler değeri `escape()`'e veriyordu: her fatura ve kargo alanı
  (`billing_*`, `shipping_<alıcı>_*`, kargo adresi sayfasının alanları),
  `page_id`, `next_page_id`, `ship_to_id`, indirim kodu, hediye çeki kodu,
  kart sahibi, güvenlik kodu, PO numarası, referans kaynağı, alıcının
  kargo yöntemi; özel formun onay kutusu grubu ve çoklu seçimi iç içe
  dizi (`alan[0][]=x`) gelince (130 istek).
- Özel kargo ve ödeme formunun e-posta, tarih, tarih-saat ve saat alanı
  `validate_email_address()` / `validate_date()`'ten (`preg_match()`,
  `explode()`) geçerken; fatura e-postası da öyle.
- Kart numarası `decrypt_credit_card_number()`'da (`substr()`), son
  kullanma tarihi `explode()`'da, vergi numarası ve vergi dairesi
  `trim()`'de, sipariş toplamı (`total`) `* 100`'de, hızlı siparişte ürün
  adedi (`quantity[<kalem>][]=`) `preg_match()`'te, alıcının posta kodu ve
  adresi `trim()`'de, varış tarihi `isset()`'te.

Görünmeyen uyarılar:

- `submit_custom_form()` 2471: ödeme formu `ship_to_id` vermiyor; özel
  ödeme formu olan fatura bilgileri ve hızlı sipariş sayfasının her
  gönderiminde (normal siparişte de) uyarı.
- "Array to string conversion": özel formun zengin metin alanı
  (`editor.php`), hızlı siparişin bağış tutarı (`ecommerce.php`), hızlı
  siparişin ve sipariş gönderiminin sayfa ve toplam okumaları.

**Ne değişti.** #407'nin kuralı: dizi gelen tek değerli alan "verilmemiş"
sayılır, sonrası boş alanla aynı (zorunluysa sayfa "gerekli" uyarısıyla
geri döner).

- `submit_custom_form()` her alanın değerini yeni
  `pg_cf_posted_value()`'dan geçiriyor; o da #415'in
  `pg_form_list_search_value()`'sunu kullanıyor: onay kutusu grubu ve
  çoklu seçim listesini olduğu gibi tutuyor (içindeki iç içe dizi
  elemanları atılıyor, hiçbiri kalmazsa alan boş), öbür her alanda dizi
  boş metin oluyor. Doğrulayıcılar ve `escape()` artık yalnız metin
  görüyor. Ödeme formunun olmayan `ship_to_id`'si boş okunuyor.
- `shipping_address_and_arrival.php`, `billing_information.php`,
  `express_order.php` (yalnız ödeme ağ geçidinden dönüş dışında; her
  alıcının adres, varış tarihi ve kargo yöntemi alanları dahil) ve
  `submit_order.php` `add_fields_to_session()`'dan sonra tek değerli
  alanlarını `pg_liveform_single_values()`'a veriyor. Doğrudan okunan
  `$_POST`/`$_REQUEST` değerleri (`page_id`, `next_page_id`, `ship_to_id`,
  `arrival_date`, hızlı ekleme alanları) yalnız metinse okunuyor; dizi
  boş sayılıyor. Hızlı siparişte adedi ya da bağış tutarı dizi gelen
  kalem olduğu gibi kalıyor.
- `pg_liveform_single_values()` değeri `get_field()` ile saklandığı gibi
  okuyor (`get_field_value()` kart numarasını önce çözüyordu, dizi orada
  düşüyordu) ve yeni `$empty` parametresi alıyor: sipariş toplamları
  (`total`, `total_with_surcharge`) `null`'a çevriliyor, çünkü `''` de
  `* 100`'de aynı `TypeError`'ı verir; `null` gönderilmemiş toplam gibi
  okunuyor.
- Sayfa ayarı satırı olmayan `page_id`'de (dizi ya da bilinmeyen id)
  fatura bilgileri, hızlı sipariş ve sipariş gönderimi ayar okumalarında
  uyarı yazmıyor; davranış eskisi gibi (ayarsız devam).

Uyarı susturulmadı. `liveform.class.php`'ye, başarılı bir siparişin
yazdığına, şemaya, dil dosyasına ve JS'e dokunulmadı.

Davranış farkı (yalnız elle kurulmuş istekte): zorunlu özel metin alanı
dizi gelince eskiden kabul ediliyordu, şartlar kutusu `terms[]=1` ile
onaylanmış sayılıyordu; artık sayfa "gerekli" uyarısıyla geri dönüyor.
Ödeme yöntemi dizi gelince eskiden "Lütfen bir ödeme yöntemi seçin",
artık "Bir ödeme yöntemi gereklidir" diyor (ikisinde de sipariş
verilmiyor). Taramada 11 istek böyle değişti.

**Bilerek yapılmayan.** Dizi bekleyen yerde tek metin gelmesi
(`quantity=5`) ele alınmadı. Taranmayanlar: kargo yöntemi adımı
(`shipping_method.php`), hızlı siparişin yinelenen ödeme ve hediye çeki
alıcısı alanları, ürün (sipariş kalemi) formları. Önceden var olan,
diziyle ilgisiz uyarılar yerinde: her siparişte
`$valid_tax_zone_id` (`forms.php`), özel kargo formlu hızlı siparişte
`form_name` (`content.php` 1569), hızlı siparişte selamlaması ya da
soyadı boş alıcıda `name` (`get_express_order.php` 4175/4179), ülkesi
boş alıcıda `shipping.php` 1571/2247, ürünsüz hızlı eklemede
`quick_add_product_id` (`express_order.php`). Sipariş akışında bilinmeyen
`page_id` siparişi sayfanın ayarları olmadan (makbuz e-postası ve zorunlu
özel alanlar olmadan) veriyor; bu da önceden vardı, dizi `page_id` şimdi
aynı yere düşüyor.

**Doğrulandı (sandbox, MariaDB, PHP 8.3).** Eski kod `origin/development`
worktree'sinden ikinci portta sunuldu; iki tarama aynı veritabanı
dökümünden başladı. 347 isteğin 180'i eskide 500, yenide 500 yok (ya
sipariş veriliyor ya doğrulama uyarısıyla dönüyor); 125'i aynı sonuç ama
eskideki uyarılar yok; 31'i aynı sonuç, aynı (diziyle ilgisiz) uyarılar;
11'i yukarıdaki davranış farkı. Yeni kodda 5 istekte görülen
`get_express_order.php` ve `shipping.php` uyarıları eskide 500'ün
arkasında kalan, boş alanla eski kodda da çıkan uyarılar (boş
selamlama/soyad/ülke ile eski kodda denendi). Normal siparişler aynı
dökümden iki kodla dörder yoldan (klasik kargolu, eksik zorunlu alan ve
hatalı tarihle dönen fatura, hızlı sipariş kargolu + aidat, yalnız aidat)
tamamlandı: sayfalar (rastgele CAPTCHA dışında), `orders`, `order_items`,
`ship_tos`, `form_data`, `contacts`, `user` satırları (kart numarasının
her şifrelemede değişen şifreli metni dışında) ve üç makbuz e-postası
(MIME sınırı, tarih ve Message-ID dışında) bire bir aynı; hata günlüğünde
yalnız `ship_to_id` uyarısı eksik. `tests/order_form_array_params_test.php`
eski kodda 4 hata ve 1 başarısızlık, yenide geçiyor;
`tests/form_array_params_test.php` güncellendi. `tools/test.php`, lint,
dil, kopya, bağlama, API şeması ve `build_js.php --check` geçti.
Doğrulanamadı: dev.pinegrap.com'daki gerçek ödeme adımları ve ödeme
ağ geçitleri.

---

## 2026.4.10 — Kimlik kapılı 40 panel ekranı `?id[]=` ile 500 vermiyor, ERP ekranları 1 numaralı belgeyi açmıyor (2026-10-14)

**Neden.** #417'nin panel taramasından kalan K1. c651716'daki "Kayıt
bulunamadı" kapısını ve ERP ekranlarının aynı kalıbını taşıyan 40 dosya
kimliği `(int) ($_REQUEST['id'] ?? 0)` ile okuyordu. Dizi için `(int)` 1
veriyor: 1 numaralı kayıt varsa kapı geçiliyordu. c651716'nın 22 ekranı
(`edit_ad`, `edit_calendar`, `edit_calendar_event`, `edit_contact`,
`edit_container`, `edit_country`, `edit_currency`, `edit_email_campaign`,
`edit_folder`, `edit_gift_card`, `edit_key_code`, `edit_product_attribute`,
`edit_product_group`, `edit_referral_source`, `edit_shipping_method`,
`edit_short_link`, `edit_state`, `edit_tax_zone`, `edit_user`, `edit_zone`,
`print_order`, `view_visitor`) kapıdan sonra `$_REQUEST['id']` /
`$_GET['id']`'yi `escape()`, `h()`, `urlencode()` ya da liveform'a verip 500
veriyordu. Kimliği yalnız `$id`'den okuyan 18 ERP ve benzeri ekran
(`add_erp_expense`, `approve_affiliate`, `edit_erp_expense`,
`edit_erp_invoice`, `edit_erp_invoice_draft`, `edit_erp_purchase_order`,
`edit_erp_quote`, `edit_erp_waybill`, `erp_account_sync_item`,
`erp_bank_statement`, `erp_cheque`, `erp_inbox_document`, `erp_receipt`,
`erp_reconciliation`, `erp_stock_count`, `get_erp_invoice_pdf`,
`get_erp_reconciliation_pdf`, `get_erp_waybill_pdf`) düşmüyor ama 1
numaralı kaydı açıyordu (ERP belgesi, PDF'i, ortaklık onayı sorusu, gider
düzenleme formu). Hepsi 2026.4.9'da da var.

**Ne değişti.** Her dosyada tek satır, #417'deki `theme_designer.php`
kalıbı: `$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;`.
Dizi kimlik "verilmedi" sayılıyor: 22 ekran kimliksiz açılış gibi "Kayıt
bulunamadı" 404'ünü, ERP ekranları kendi "bulunamadı" ekranını ya da liste
yönlendirmesini veriyor; `add_erp_expense` boş yeni gider formunu açıyor.
Kapıdan sonraki satırlar değişmedi: kapı diziyi geçirmediği için oralara
ulaşmıyor. Normal isteğin çıktısı aynı. Şema, dil dosyası, JS değişikliği
yok.

**Doğrulandı (sandbox, MariaDB, PHP 8.3).** Kırk tabloda 1 numaralı kayıt
oluşturuldu (kurulum yalnız dokuzunda 1 numara veriyor; gerçek sitede
hepsinde olabilir) ve `panel-tarama-araclari/scan.py` 40 ekranda koşuldu
(parametresiz, kendi parametreleri, her parametre dizi olarak; 736 istek).
Eski kodda 22 istek 500 verdi (hepsi `?id[]=1`), 22 istek ölümcül hata
yazdı; yeni kodda 500 ve uyarı ya da ölümcül hata yazan istek 0. Kalan
tek satır, 404 ekranının kendi `h(null)` kullanım dışı uyarısı (yalnız
DEBUG açıkken; kimliksiz açılışta da yazıyor, liveform, #417'nin K9'u).
Eski kod (`origin/development`) ikinci portta, istekler sırayla iki
sunucuya: 120 normal istek (`?id=1`, kimliksiz, `?id=999999`) aynı HTML'i
verdi; farklı olanlar yalnız `?id[]=1` istekleri (39'u; `erp_receipt`'te
1 numara tahsilat değildi, iki kodda da "bulunamadı"). Yeni
`tests/panel_id_gate_test.php`: hiçbir panel ekranı istek kimliğini
`is_scalar()` olmadan `(int) ($_REQUEST['id'] ?? 0)` ile okumuyor (eski
kodda 40 eşleşme). `php -l`, `tools/lint.php`, `tools/test.php` (934
test), `check_lang`, `check_bindings`, `check_api_schema`, `check_copies` ve
`build_js.php --check` geçti.
Doğrulanamadı: dev.pinegrap.com'daki gerçek veriler.

**Bilerek yapılmayan.** #417 notundaki K2–K9: kapısı olmayan düzenleme
ekranları (`?id[]=` 500 ve kimliksiz açılışta yarı boş form), `?send_to[]=`,
ERP `(string) ($_GET['x'] ?? '')` "Array to string" uyarıları, diğer
500'ler, liveform `h(null)` ve `custom_form.php`. Kapıdan sonraki
`$_REQUEST['id']` okumaları `$id`'ye çevrilmedi: dizi artık kapıda duruyor,
değişiklik dosya başına tek satır kalıyor.

---

## 2026.4.10 — API belgesi: her eklemede yazılan boş sütunların kuralı, Bearer açıklaması üç arayanı anlatıyor (2026-10-14)

**Neden.** #412 ve #405'ten kalan iki iş. (1) Ham sütunu `DEFAULT NULL`
olup belgede boş olamaz yazılı alanlar (ürün ve grup adı, sayfa adı,
başlığı ve açıklaması, dosya adı ve türü) için bir kural yazılı değildi;
sonraki bir tarama bunları sütuna bakıp `?` ile işaretleyebilir ya da yeni
bir ekleme yolu sütunu boş bırakıp belgeyi sessizce yanlış yapabilirdi.
(2) OpenAPI'nin Bearer şeması "a person signed in on a device (POST
/auth/login)" diyordu: OAuth bağlantısı da Bearer jetonuyla geliyor
(`api_devices` satırı) ama anılmıyordu ve hangi arayanın neyi
yapabildiği yazmıyordu.

**Ne değişti.**
- Kural `api_openapi_type_spec()`'in açıklamasında: alanın `?` alıp
  almayacağına sütun değil sunucu fonksiyonunun verebileceği değer karar
  verir. Bir ekleme yolu sütunu yazmıyor ya da NULL yazıyorsa, sunucu
  fonksiyonu kendisi `null` veriyorsa ya da sitenin kendi verisi öyle
  geliyorsa `?`; her ekleme yolunun yazdığı `DEFAULT NULL` sütun ve
  sunucu fonksiyonunun `(string)` ile çevirdiği alan `?` almaz. Ürün,
  grup, sayfa, dosya ve form bildirimlerinin yanında da tek satır not.
- Tarama: bu sütunlara yazan 63 INSERT (ürün 3, grup 7, sayfa 7, dosya 46)
  ve ürün oluşturucusu (`pg_pb_create_product()`, `pg_pb_create_group()`)
  okundu. Hepsi adı, başlığı, açıklamayı ve türü dolu bir değerle yazıyor;
  kopyalama (`duplicate_style.php`) özgün sayfanın değerini taşıyor,
  ürün içe aktarma yalnız adı dolu satırı ekliyor; hiçbir UPDATE bu
  sütunlara NULL yazmıyor; kurulumla gelen iki veri dökümünde (181/180
  sayfa, 27 ürün, 20 grup, 212/206 dosya) NULL yok. Bu yüzden alanlar boş
  olamaz kaldı, belgede şema değişmedi. Önceden `?` taşıyanlar
  (`Product.short_description`, siparişin fatura alanları, `SeoIssue.name`
  ve #412'nin müşteri alanları) kurala uyuyor.
- `components.securitySchemes`: yeni `api_openapi_security_schemes()`.
  Basic (`applicationKey`) uygulama anahtarının sahibinin adına ve verilen
  izinlerle çağırdığını, webhook aboneliklerini yönetebilen tek kimlik
  olduğunu, kişinin kendisinin yapacağı çağrıların 403 forbidden döndüğünü
  söylüyor. Bearer (`deviceToken`) iki jeton türünü ayırıyor: kişinin kendi
  ekip cihazları oturumu (`POST /auth/login`) kişinin kendi sözünü
  değiştirebilen tek kimlik (mesaj düzeltme, silme, zamanlama, oylama
  açma ve değiştirme, sabitleme); OAuth bağlantısı onaylayan kişi adına ve
  verilen izinlerle çağırıyor, okundu işaretleme ve onay yanıtlama gibi
  kişiye ait öbür çağrıları yapabiliyor, kişinin sözünü değiştirmesi 403.
  İkisi de webhook yönetemiyor; jeton bir saat yaşıyor (`POST /auth/refresh`
  ya da OAuth sunucusunun token ucu). `info.description` cümlesi de aynı
  sözcüklerle. Şemanın adı `deviceToken` kaldı: üretilmiş istemciler şemayı
  adıyla anıyor.
- `tests/api_openapi_status_test.php`: 3 yeni test. Sekiz alanın
  bildirimi boş olamaz (kaynaktan okunuyor; resource dosyaları test
  ortamında yüklenmiyor); bu tablolara yazan her INSERT'in sütun listesi
  bu sütunları anıyor, listesi çalışırken kurulan üç ekleme yolu adıyla
  sayılı ve yazmayı güvenceye alan satırları yerinde; iki güvenlik
  şemasının metni üç arayanı anıyor, "signed in on a device", "mobile",
  "phone" demiyor.

Uçların davranışı, şema, dil dosyası ve JS değişmedi. Panelin API belgesi
sayfası yalnız HTTP Basic'i gösteriyor, Bearer cümlesi orada yok (tr.json'a
dokunulmadı).

**Doğrulama.** OpenAPI belgesi CLI'da modüller açık üretildi (380 işlem),
önce ve sonra `openapi-spec-validator` ile geçerli; fark yalnız
`info.description` ve iki güvenlik şemasının açıklaması. Testin kırmızıya
döndüğü denendi (`add_page.php`'den `page_meta_description` çıkarılınca ve
`File.type` `string?` yapılınca). `lint`, `check_lang`, `check_bindings`,
`check_api_schema`, `check_copies`, `build_js --check`, `tools/test.php`
yeşil.

---

## 2026.4.10 — Form listesinin gelişmiş arama düzeni panelin verdiği `{name: '…'}` biçimini okumuyordu, katalog boş terimle RSS bağlantısını göstermiyordu (2026-10-14)

**Neden.** #415'in bıraktığı iki parça, ikisi de 2026.4.9'da var. (1) Form
listesinin gelişmiş arama düzenindeki her `{{…}}` etiketi `json_decode()`
ile okunuyordu. Panelin düzenleme ekranı alanları `{{name: 'subject'}}`
biçiminde listeliyor ve ipucu "Etiketleri Alanlardan kopyalayın" diyor;
örnek verideki gelişmiş araması açık üç liste (`all-conversations`,
`all-support-tickets`, `all-services-projects`) ve araması kapalı diğer
düzenler de bu biçimde (`{name: 'submitter', dynamic: true}`). Bu JSON
değil (anahtar tırnaksız, metin tek tırnaklı), bu yüzden bu etiketler hiç
çözülmüyor, sayfada `{{name: …}}` yazısı olduğu gibi görünüyordu. Yalnız
`{{"name":"subject"}}` gibi sıkı JSON çalışıyordu. (2) Katalog `?query=`
(boş ya da yalnız boşluk) ile açılınca `get_catalog.php` terimi `trim()`
edip terimsiz sayfayı gösteriyor, ama `get_page_content.php`'deki RSS
koşulu terimin metin olmasına baktığı için `<head>`'deki RSS bağlantısı
çıkmıyordu.

**Ne değişti.**
- `includes/fn/forms.php`: `pg_form_list_advanced_field_properties()`. Önce
  sıkı JSON dener; çözülürse sonucu olduğu gibi döner, bu yüzden JSON
  düzenlerin HTML'i değişmez. Çözülmezse tek geçişte tırnaksız anahtarları
  tırnaklar (Türkçe harfli anahtar dahil), tek tırnaklı metni çift tırnağa
  çevirir (içindeki `\'` ve `"` dahil), kapanış parantezinden önceki virgülü
  atar; metinlerin içine dokunmaz. Yine okunamazsa `null` döner.
- `get_form_list_view.php` etiketi bu fonksiyonla okuyor; okunamayan etiket
  atlanıyor ve düzende yazıldığı gibi kalıyor (bilinmeyen alan adındaki
  gibi), uyarı ya da 500 yok.
- Katalog RSS bağlantısı terim boşsa ya da yalnız boşluksa da çıkıyor.
- `tests/form_list_advanced_field_test.php`: beş test (sıkı JSON aynen,
  nesne biçimi, sondaki virgüller, metin içinde tırnak ve kaçış, bozuk girdi
  uyarısız `null`).
- Paneldeki ipucu ve alan etiketleri değişmedi: verdikleri biçim artık
  çalışıyor. Örnek veri değişmedi. Şema, dil dosyası ve JS değişikliği yok.

**Doğrulandı (sandbox, MariaDB, PHP 8.3, Türkçe örnek veri).** Eski kod
(`origin/development`) ve yeni kod aynı veritabanında iki ayrı sunucuda,
opcache kapalı. Üç örnek liste (yönetici olarak): eski kodda gelişmiş arama
bloğunda etiketler düz yazı; yeni kodda dinamik seçim listeleri (Gönderen,
E-posta, Şirket, Telefon, Konu; projelerde Kredi) ve Ara/Temizle düğmeleri
çıkıyor, tarayıcıda bir e-posta seçip Ara ile süzme çalışıyor, sayfa hatası
yok. `/forum` listesine sıkı JSON düzen (standart ve özel alan, dinamik,
operatör, `&quot;`'lu etiket, bilinmeyen alan, JSON olmayan etiket, düğmeler)
kuruldu: oturumsuz dört istekte (terimsiz, alanla arama, gönderen ve tarih,
dizi değer) eski ve yeni HTML birebir aynı (oturum jetonu dışında). Katalog
`/shop-fullwidth`: `?query=`, `?query=%20`, `?1000_query=` yeni kodda RSS
bağlantısı terimsiz sayfadaki gibi, eski kodda yoktu; terimsiz,
`?query=kalem`, `?query[]=x`, `?1000_query=&query=kalem` eski ve yeni
kodda aynı. Hiçbir istek `php_errors.log`'a satır yazmadı.
`tools/lint.php`, `check_*`, `tools/test.php` ve `build_js.php --check`
geçti. Doğrulanamadı: dev.pinegrap.com'daki sayfalar.

**Bilerek yapılmayan.** Katalogda iki uç hâlâ terimsiz sayfadan ayrı (eski
kodda da aynı): basit aramanın Temizle düğmesi terimle gelince
(`?<sayfa>_simple_clear`) ve `?<sayfa>_query=` ile `?query=x` birlikte
gelince katalog terimsiz sayfayı gösteriyor ama RSS bağlantısı gizli.
Gevşek okuyucu JavaScript'in tamamını değil, panel etiketlerinin kullandığı
üç gevşekliği okuyor; onaltılık sayı, yorum ya da `\x41` kaçışı olan etiket
atlanır.

---

## 2026.4.10 — Sayfalar listesinden, sayfa ekranından ve görsel editörden silinen sayfa ve sayfa stili Geri Dönüşüm Kutusuna gidiyor (2026-10-14)

**Neden.** Bir müşteri sitesinde yanlışlıkla silinen kaydı geri almak için
veritabanı yedeğini kurmak gerekti; yedek ziyaretçi tabloları yüzünden
950 MB'tı ve sunucu yanıt veremedi. Dosya yöneticisinden silinen dosya,
klasör ve sayfa ile katalog yöneticisinden silinen ürün ve grup zaten
Geri Dönüşüm Kutusuna düşüyordu; aynı sayfa başka ekrandan silinince
kalıcı gidiyordu. Sandbox'ta eski kodla kaydedildi:

- Sayfalar listesinin toplu silmesi (`edit_pages.php`) ve sayfa
  ekranının Sil düğmesi (`edit_page.php`) satırı, bölgelerini ve
  bağlı kayıtlarını hemen siliyordu.
- Görsel editörde tasarımı silmek (`pg_designer_delete_design()`, tasarım
  listesi, editör araç çubuğu ve `edit_system_style.php`) sayfaları kutuya
  koyuyor ama `style`, `system_style_cells` ve `preview_styles`
  satırlarını siliyor, klasörlerin varsayılan tasarım ayarını
  sıfırlıyordu. Kutudan sayfalar geri gelse de tasarımları yoktu.
- Sayfa Stilleri listesinden (`delete_styles.php`) ve özel stil
  ekranından (`edit_custom_style.php`) silinen stil kalıcı gidiyordu.

Site Günlüğü üç durumda da "silindi" yazıyordu.

**Ne değişti.**

- Yeni `pg_recycle_page()` (`view_folder_and_files_f.php`): dosya
  yöneticisinin silmesinin aynısı. Satır kutu klasörüne taşınır, adı
  park edilir (`pgbin_<id>_<ad>`), kutu kaydı yazılır; bölgeler, formlar,
  yorumlar, kısa linkler ve dil bağlantıları satıra bağlı kaldığı için
  geri yüklemede hepsi döner. Sayfalar listesi ve sayfa ekranı kutu
  kuruluysa bunu çağırır; zaten kutuda olan sayfa eski yoldan kalıcı
  silinir (kutunun kendi Sil'i de öyle). Erişim ve "sayfa silme" yetkisi
  denetimi değişmedi.
- Sayfa stili kutuya **anlık görüntü** olarak girer: `pg_recycle_style()`
  stil satırını (`SELECT *`), hücrelerini ve önizleme stillerini,
  onu varsayılan (ve mobil) tasarım yapan klasörleri, mobil stili o olan
  sayfaları, onu kullanan sayfaları ve birlikte kutuya giren sayfaları
  JSON olarak `recycle_bin.item_data`'ya yazar, sonra satırları siler ve
  atamaları temizler. Kayıt yazılmadan hiçbir şey silinmez (`db()` hata
  verirse istek orada durur).
- `pg_recycle_restore_style()`: satırlar, tablonun bugün sahip olduğu
  sütunlarla (`pg_recycle_row_for_columns()`) aynı kimlikle yazılır; kimlik
  bu arada alınmışsa yeni kimlik alınır ve sayfaların `page_style`'ı ona
  çevrilir. Ad alınmışsa `get_unique_name()` "[1]" ekler; sistem stilinde
  `duplicate_style.php`'deki gibi kod içindeki sınıf adı da değişir.
  Klasör ve mobil atamaları yalnız hâlâ boş (0) olan yerlere geri yazılır;
  bu arada başka tasarım seçilmişse ona dokunulmaz.
- Kutudan stil geri yüklenince birlikte giren sayfaları da gelir; tek bir
  sayfa geri yüklenince (tasarımcı yüklüyorsa) kutudaki tasarımı yalnız
  stil olarak gelir, kardeş sayfalar kutuda kalır.
- Dosya yöneticisi kutusunda stiller "Sayfa Stili" türünde, palet
  simgesiyle, sayfa sayısıyla listelenir; açılmaz, yalnız Geri Yükle ve
  Kalıcı Olarak Sil vardır. Stil satırlarını yalnız tasarımcılar (rol ≤ 1)
  görür, geri yükler ve kalıcı siler; sayıma da öyle girer
  (`pg_recycle_item_types_sql($user)`).
- Kutuyu boşaltmak ve kalıcı silmek stilde kaydı siler (satırlar zaten
  girerken silindi); sayfalarda değişmedi.
- Onay metinleri ve bildirimler "Geri Dönüşüm Kutusuna taşınacak/taşındı"
  diyor; Site Günlüğü satırları kalıyor, metni aynı. Tasarım silme yanıtına
  `recycled` eklendi, mesaj `pg_designer_delete_design_message()`'tan.
- Şema 10.4: `recycle_bin.item_data` LONGTEXT NULL ve `item_type`
  ENUM'una `'style'`. Adım koşmadan (`pg_recycle_style_ready()` yanlış)
  stil eskisi gibi kalıcı silinir.

API (`DELETE /pages/{id}`) ve MCP zaten kutuya koyuyordu (`permanent=true`
dışında); dokunulmadı. Ürün silme bu değişikliğin dışında.

**Neden anlık görüntü.** Sayfa ve dosya gibi stili de gizli bir klasör ya
da bayrakla bırakmak, stil okuyan yaklaşık 80 sorguya "kutudaki hariç"
koşulu eklemek demekti; biri unutulursa kutudaki tasarım seçicilerde ve
sayfalarda görünürdü. Anlık görüntüde stil kutudayken hiçbir tabloda
yoktur. Bedeli: geri yükleme satırları yeniden yazar, kimlik alınmışsa
değişir (sayfalar ona çevrilir).

**Bilerek yapılmayan / sınırlar.**

- Ana sayfa işareti geri gelmiyor; dosya yöneticisi kutusunun kuralı.
- Saklama süresi temizliği günde bir, ilk açan kişiyle koşar; stil
  kayıtlarını yalnız tasarımcının ziyareti temizler (tasarım dosyalarında
  da böyle).
- Gönderilmiş kaydı olan özel form sayfası artık listeden kutuya
  gidebiliyor; kutudan kalıcı silmek onu eskisi gibi reddediyor.
- Stil silen ya da geri yükleyen API ucu yok; sayfa geri yükleyen API ucu
  da yok.

**Doğrulandı (sandbox, MariaDB, PHP 8.3).** Eski kod: dört yolun hepsinde
satırlar gitti, Site Günlüğü "silindi". Yeni kod: dört sayfa bölgeleriyle
kutuda, iki stil kutuda; stil geri yükleme aynı kimlikle kod, ağaç, hücre,
önizleme ve klasör atamasıyla döndü, sayfaları adlarıyla klasörlerine
döndü, mobil stil ayarları geri geldi. Tek sayfa geri yükleme tasarımı
getirdi, kardeş sayfayı getirmedi. Kimlik ve ad alınmışken geri yükleme
"[1]" adlı yeni kimlikle döndü, sınıf adı ve atamalar ona çevrildi.
Kalıcı silme kaydı kaldırdı. Kutu ekranı tarayıcıda: stil satırları palet
simgesiyle, sağ tık menüsünde yalnız Geri Yükle ve Kalıcı Olarak Sil.
Şema adımı iki kez koşturuldu (ikincide atlandı).
`tests/recycle_bin_style_test.php`; `tools/test.php`, lint, dil, kopya,
bağlama, API şeması ve `build_js.php --check` geçti.
Doğrulanamadı: dev.pinegrap.com.

---

## 2026.4.10 — Panel taraması: dizi parametresiyle 500, oturuma yapışan hata sayfaları, her sayfada `?utm_source[]=` (2026-10-14)

**Neden.** Site sayfalarındaki dizi parametresi serisinin (#384, #389, #391,
#394, #397, #403, #406) panel karşılığı. Sandbox'ta yönetici oturumuyla 367
panel ekranı GET ile açıldı: parametresiz, kendi parametreleri `1` ile ve her
parametre (kendi parametreleri ile `id`, `q`, `page`, `sort`, `order`,
`query`, `filter`, `screen`, `search`, `status`, `send_to`, `mode`, `type`,
`view`, `tab`) tek tek dizi olarak (`?x[]=1`), sonunda yine parametresiz
(oturuma yapışanı yakalamak için); 6.760 istek. Eski kodda 149 istek 500
verdi, 440 ayrı dosya:satır uyarı ya da ölümcül hata yazdı. Bu PR bunların
en çok işe yarayan 15 dosyasını düzeltiyor; kalanlar (çoğu `?id[]=` ve
`?send_to[]=` ile 500 veren düzenleme ekranları) aşağıda ve
`notes/panel-php-uyari-taramasi.md`'de. Hepsi 2026.4.9'da da var.

**Ne değişti.** Serinin kuralı: dizi gelen tek değerli parametre "verilmedi"
sayılır, normal isteğin HTML'i değişmez, 500 yok.

- `get_page.php`: sitenin her sayfası `?utm_source[]=x`, `?a[]=x`, `?t[]=x`,
  `?o[]=x`, `?page[]=x` ile 500 veriyordu (`trim()` / `mb_strpos()`), panel
  oturumu olsun olmasın. Dosyanın başında bu dokuz tek değerli parametre
  dizi gelirse `$_GET`'ten çıkarılıyor (`get_shipping_method.php`'deki
  `unset($_GET['ship_to_id'])` gibi). Yalnız `utm_source` taşıyan kampanya
  bağlantısı her ziyarette dört "Undefined array key utm_medium…" uyarısı
  yazıyordu; eksik etiketler eskisi gibi boş saklanıyor (`?? ''`).
- Oturuma yapışan 500'ler: `mass_edit.php` `?tags[]=` değerini oturuma
  yazıyordu, sonraki her açılış `explode()` ile düşüyordu; `theme_designer.php`
  `?page_to_preview_id[]=`, `view_orders_for_contact.php` `?id[]=` için aynı.
  Dizi oturuma yazılmıyor; daha önce yazılmış dizi de okunurken yok sayılıyor.
  `mass_edit.php` bilinmeyen etikette (`?tags=x`) tanımsız `$outputs`'a
  ekliyordu; boş başlıyor (çıktı aynı: yalnız düğmeler).
- `theme_designer.php`: önizlenecek sayfa silinmişse (ya da elle yazılmış bir
  numaraysa) sayfa satırı gelmiyordu, `get_style()` boş klasörle üst klasörü
  hiç bulamıyordu: istek süre sınırına kadar dönüyordu (sandbox'ta 60 saniye
  sonra bile yanıt yok), oturumda kaldığı için o temanın tasarımcısı her
  açılışta. Artık ana sayfaya düşüyor. Ekran ayrıca c651716'daki "Kayıt
  bulunamadı" kapısını aldı: kimliksiz, var olmayan ya da dizi kimlikle açılış
  404.
- `view_orders_for_contact.php`: kişi yoksa (kimlik verilmemiş, silinmiş)
  boş başlık, iki uyarı ve boş kişiye (`contact_id = ''`, yani 0) eşlenen
  sipariş listesi yerine aynı "Kayıt bulunamadı" 404. Taslak siparişin boş
  sipariş numarası `h(null)` kullanım dışı uyarısı yazıyordu; `?? ''`.
- Liste süzgeçleri: `view_pages.php`, `view_contacts.php`, `view_files.php`,
  `view_users.php`, `view_submitted_forms.php`, `get_files_for_picker.php`
  `?filter[]=` ile 500 veriyordu (`strpos()`, `h()`, `trim()`); dizi süzgeç
  verilmemiş sayılıyor, ekran varsayılan görünümle açılıyor. `view_contacts.php`
  `?order[]=` ile `sql_order_direction()` içinde uyarı yazıyordu; dizi yok
  sayılıyor. Çift kayıt listesi boşken `$output_image_header` tanımsızdı
  (yalnız döngüde atanıyordu); döngüden önce boş başlıyor. Silinmiş
  kullanıcının değiştirdiği satırda `h(null)` için `?? ''` (rehber, dosyalar).
- `view_performance_log.php` (`range`, `area`, `slow_sort`, `slow_dir`,
  `mem_sort`, `mem_dir`, `search`) ve `view_waf_log.php` (`range`,
  `category`, `search`): her biri dizi gelince 500 ya da "Array" araması;
  `is_string()` koşulu eklendi, beyaz liste aynı.
- `erp_settings.php`: `?doc[]=` 500 ("Cannot access offset of type array");
  dizi belge fatura sayılıyor.
- `calendars.php`: takvim, görünüm, durum ve tarih oturuma yazılıyor; dizi
  yazılmıyor, bilinmeyen görünüm (`?view=x`) boş (aylık) görünüme dönüyor.
  Önceden bilinmeyen görünüm oturumda kalıyor, takvim boş çiziliyor ve her
  açılış sekiz "Undefined variable" uyarısı yazıyordu.
- `includes/fn/calendar.php` `get_calendar()`: haftalık görünüm
  `$output_calendar`'a tanımsızken ekliyordu (aylık görünüm onu `=` ile
  başlatıyor), panelin yaklaşan etkinlikler görünümü hiç kurulmayan
  `$output_date_selection`'ı basıyordu. İkisi fonksiyon başında boş
  başlıyor; ön yüzdeki haftalık takvim sayfaları da her açılışta uyarı
  yazmıyor. Çıktı aynı.

Şema, dil dosyası (yeni anahtar yok; "Record not found." ve "Go back"
mevcut) ve JS değişikliği yok.

**Doğrulandı (sandbox, MariaDB, PHP 8.3).** Aynı tarama yeni kodda: 15
dosyada 500'ler 53 → 0, uyarı ya da ölümcül hata yazan istek 98 → 0; bütün
panelde 500 149 → 97, ayrı uyarı satırı 440 → 344 (kalanlar bu PR'ın kapsamı
dışı). Eski kod (`origin/development`) ikinci portta, istekler sırayla iki
sunucuya: 15 dosyanın 58 normal isteği (süzgeçler, sıralama, belge türleri,
takvim görünümleri, toplu düzenleme etiketleri, tema tasarımcısı, sipariş
listesi) ve 14 ön yüz isteği (oturumsuz, kampanya / takip / ortaklık /
teklif kodlu) aynı HTML'i verdi (oturum jetonu, saat simgesi, "N dakika
önce", Site Günlüğü sayacı, CAPTCHA sorusu, sayfa görüntülenme sayacı ve
performans günlüğünün sürekli değişen sayıları dışında). Farklı olanlar
yalnız bilerek değişenler: dizi parametreli istekler 500 yerine 200,
kimliksiz / var olmayan kayıtla tema tasarımcısı ve kişi siparişleri 404,
bilinmeyen takvim görünümü aylık. `php -l`, `tools/lint.php`,
`tools/test.php` (919 test), `check_lang`, `check_bindings`,
`check_api_schema`, `check_copies` ve `build_js.php --check` geçti.
Doğrulanamadı: dev.pinegrap.com'daki gerçek veriler.

**Bilerek yapılmayan (kalanlar).** `?id[]=` ile 500 veren 47 düzenleme ve
görüntüleme ekranı: c651716 kapısını almış 22 ekran `(int) $_REQUEST['id']`
ile diziyi 1 sayıyor, kapıdan geçip `escape()`'te düşüyor (tek satırlık
düzeltme: `is_scalar()`); kapısı olmayanlar ayrıca kimliksiz açılışta yarı boş
form ve uyarı gösteriyor. `?send_to[]=` ile 500 veren 21 ekran (`h()` gizli
alanda). Bu iki grup tek tek ekran düzeltmesi mi, `init.php`'de tek değerli
panel parametrelerini süzen merkezi bir kural mı olacağı bir karar. ERP
ekranlarının `(string) ($_GET['x'] ?? '')` kalıbı dizide uyarı yazıp "Array"
değeriyle devam ediyor (500 yok). `h()`, `escape()`, liveform ve
`custom_form.php` dokunulmadı. Ayrıntılı liste notta.

---

## 2026.4.10 — Ürün silme: ürün ekranı, ürün listesi, varyant setleri ve grup ekranı da Geri Dönüşüm Kutusuna gönderiyor (2026-10-14)

**Neden.** Bir müşteri sitesinde ürün yanlışlıkla silindi ve geri
getirmenin tek yolu 950 MB'lık veritabanı yedeğini kurmaktı. Katalog
Yöneticisi (`view_product_groups.php`) ürün ve grubu 2026.4.4'ten beri
Geri Dönüşüm Kutusuna atıyor (`recycled = 1`, `recycled_enabled =
enabled`, `enabled = 0`, `recycle_bin` satırı), API ve Çalışma Alanı da;
ama ürün düzenleme ekranının Sil düğmesi (`edit_product.php` →
`pg_pb_delete_product()`), ürün listesinin "Seçilenleri Sil"i
(`view_products.php` → `edit_products.php` `action=delete`, tabloları
kendisi siliyordu), varyant setleri listesinin silmesi
(`variant_set_action.php` → `pg_pb_delete_variant_set($id, TRUE)`, set
ve içindeki ürünler) ve ürün grubu düzenleme ekranının silmesi
(`edit_product_group.php`) kaydı ve bütün bağlarını kalıcı siliyordu.

**Ne değişti.**
- `includes/fn/ecommerce.php`: `pg_catalog_bin_statements()` kutuya
  atmanın üç cümlesini kurar (bayrak `AND recycled = '0'` korumasıyla,
  eski kutu kaydını silip yenisini yazma); `pg_catalog_bin_ready()`
  kararı Katalog Yöneticisi'ne sorar (`pg_catalog_recycle_ready()`);
  `pg_catalog_bin_product()` ürünü atar, `product.deleted`
  (`recycled: true`, `pg_announce()`) duyurur ve pazaryeri kuyruğuna
  haber verir; `pg_catalog_bin_group()` grubu alt ağacıyla atar (tek
  kayıt, kök ve kutudaki grup reddedilir).
- `product_builder.php`: `pg_pb_bin_variant_set()` seti ve içindeki
  ürünleri atar; ürünlerin kutu kaydı `original_parent_id` olarak setin
  kimliğini taşır.
- Silme yolları: `edit_product.php`, `edit_products.php` (`delete`),
  `variant_set_action.php` (`delete`), `edit_product_group.php` kutu
  varsa kutuya atar, yoksa (yükseltme öncesi kurulum) eskisi gibi kalıcı
  siler. `edit_products.php`'nin kendi silme listesi kaldırıldı, kalıcı
  dal `pg_pb_delete_product()`'a gidiyor (aynı tablo listesi, artık
  `product.deleted` da gidiyor). `edit_product_group.php` grup
  görsellerinin bağını (`product_groups_images_xref`) kutuya atarken
  silmiyor; eskiden hem silmede hem kayıtta en başta siliniyordu.
- Onay metinleri kutu varken "… Geri Dönüşüm Kutusuna taşınacak.
  Katalog Yöneticisi'ndeki Geri Dönüşüm Kutusundan geri yüklenebilir.";
  bildirim ve Site Günlüğü satırları Katalog Yöneticisi'nin cümleleri
  ("Ürün, …, Çöp Kutusuna taşındı.") ya da yeni "{n} ürün Geri Dönüşüm
  Kutusuna taşındı." / "{n} varyant seti ve {m} ürün Geri Dönüşüm
  Kutusuna taşındı.". `tr.json`'a 5 anahtar.
- Kutudakiler listede yok: `view_products.php` ana sorgusu, dışa
  aktarması ve süzgeç sayıları `products.recycled = '0'`; varyant setleri
  listesi kutudaki seti göstermiyor, setin ürün sayısı ve fiyat aralığı
  kutudaki ürünü saymıyor.
- Katalog Yöneticisi: ürünü atma `pg_catalog_bin_product()`'a, grubu
  atma `pg_catalog_bin_statements()`'a geçti (ürün tarafı artık
  `AND recycled = '0'` korumasını ve kayıt değiştirmeyi de yapıyor,
  `product.deleted` de gidiyor). Grup geri yüklenince kutu kaydı
  `original_parent_id`'si o alt ağaçtaki bir grup olan ürünler de geri
  geliyor (`AND recycled = '1'`); tek başına atılan ürünün kaydı `0`
  taşıdığı için etkilenmiyor.
- API (`products.php`, `product_groups.php`) ve Çalışma Alanı
  (`changes.php`) kutu dalları aynı cümleleri kullanıyor; API'nin
  yanıtı, prova (`X-Dry-Run`), 409'ları, günlük satırı ve webhook'u
  aynı. Çalışma Alanı'nın ürün kutusu `pg_catalog_bin_product()`'a
  geçti, artık `product.deleted` (`recycled: true`) gidiyor.
  `product.deleted` olayının açıklamasındaki "through the API" kalktı.
- Test (`tests/catalog_bin_test.php`, 9 test): cümlelerin kendisi, tamsayı
  dönüşümü, boş girdi; API'nin varsayılanının kutu olduğu ve kutu dalının
  kalıcı silmeden önce geldiği; panel ekranlarının, Katalog
  Yöneticisi'nin ve Çalışma Alanı'nın ortak fonksiyonları çağırdığı;
  `products` satırını yalnız `product_builder.php`'nin sildiği (düzeltme
  öncesi kodda `edit_products.php` yüzünden kırmızı).

Şema değişikliği yok (kutu sütunları 2026.4.4'te geldi); dev'de yalnız
`git pull` yeter.

**Doğrulandı (sandbox, MariaDB, PHP 8.3, Türkçe başlangıç sitesi).** Her
yolda silmeden önce ve geri yüklemeden sonra ürünün/grubun satırı ve 14
bağ tablosu (gruplar, bölgeler, görseller, nitelikler, barkod, kısa
bağlantı, kampanya kuralı, etiket bulutu, form alanları…) karşılaştırıldı:
- Ürün ekranı (D10, iki görsel, barkod, kısa bağlantı, bölge, nitelik,
  kampanya kuralı, anahtar kelime ile): kutuda yalnız `enabled`,
  `recycled`, `recycled_enabled` değişti, bağlar yerinde; listede yok,
  bildirim "Ürün, D10, Çöp Kutusuna taşındı."; Katalog Yöneticisi'nden
  geri yükleme sonrası satırlar birebir aynı. `product.deleted`
  (`recycled: true`) kuyrukta.
- Ürün listesi toplu silme (iki ürün): ikisi kutuda, "Tüm Ürünler" sayısı
  27 → 25, geri yüklenince aynı.
- Varyant seti (Ofis Sandalyesi, 3 ürün, biri önceden kapalı): set ve 3
  ürün kutuda (`original_parent_id` = set), liste 5 → 4 set, tek geri
  yüklemeyle set ve 3 ürün döndü, kapalı olan kapalı döndü.
- Ürün grubu ekranı (Hediye Sepetleri, görsel bağı ile): görsel bağı ve
  ürün bağları kutuda da yerinde, geri yüklenince aynı.
- API: prova 200 `would: recycled`, silme 200 `{"deleted":false,
  "recycled":true}`, ikinci silme 409 `already_recycled`; grup silme
  kutuya. Katalog Yöneticisi'nin kendi silmesi, geri yüklemesi ve
  API'nin kutuya attığı ürünün kalıcı silinmesi (boşaltma) çalıştı.
- Kutusuz kurulumdaki kalıcı dal sandbox'ta denenmedi (sütunları
  düşürmek gerekiyor); kod yolu eskisiyle aynı fonksiyon.

**Bilerek yapılmayan.** Diğer panel listeleri (kampanya ürün seçicileri,
sipariş ekranları gibi) kutudaki ürünü kapalı ürün gibi göstermeye devam
ediyor; ürün grubu ekranının alt grup listesi kutudaki alt grubu da
sayıyor. Sayfalar ve sayfa stilleri ayrı iş.

---

## 2026.4.10 — Form listesinin gelişmiş araması dizi değerle 500 veriyordu ve her açılışta uyarı yazıyordu, katalog dizi terimle RSS bağlantısını göstermiyordu (2026-10-14)

**Neden.** #408'in bıraktığı iki parça, ikisi de 2026.4.9'da var. (1) Form
listesi sayfasında (`get_form_list_view.php`) gelişmiş arama açıkken alan
değerleri `liveform`'dan okunup arama SQL'ine gidiyor. Tek değer tutan bir
alana dizi gelince (`?<sayfa>_<alan>[]=x`) standart alanlar (gönderen,
gönderim tarihi, görüntülenme sayısı, kayıt kodu) `prepare_form_data_for_input()`
ya da `escape()`'te 500 veriyordu; özel alanlar diziyi çoklu seçim gibi
okuyup birebir eşleşme süzgeci kuruyor, metin kutusu dizideki ilk değeri
gösterip çoğu zaman "Sonuç bulunamadı" diyordu. İç içe dizi
(`[0][]=x`) her alan tipinde, çoklu seçim listesi ve onay kutusu grubu
dahil, `escape()`'te 500 veriyordu. Sandbox'ta 160 dizi isteğinin 82'si 500.
Ayrıca gelişmiş arama açık her liste, normal açılışta da `php_errors.log`'a
uyarı yazıyordu: düzendeki JSON'da verilmeyen özellikler (`operator`,
`size`, `class`, `help`, `dynamic`, `sort_order`, düğmelerde `label`),
alan sayaçları (`$field_count`, `$field_number`) ve dinamik bir alanda
hiçbir formun seçmediği bir seçeneğin süzgeci. (2) Katalog
sayfası (`get_page_content.php`) `?query[]=x` ya da `?<sayfa>_query[]=x` ile
açılınca arama yapmıyor (#403), ama `<head>`'deki RSS bağlantısı terimin
yalnız `isset()`'ine baktığı için çıkmıyordu.

**Ne değişti.**
- Tek değer tutan gelişmiş arama alanına dizi gelirse alan verilmemiş
  sayılıyor: süzgeç kurulmuyor, alan boş görünüyor. Çoklu seçim listesi ve
  onay kutusu grubu (sayfada `[]` ile yazılan alanlar) diziyi alıyor; içindeki
  dizi değerler atılıyor, hiçbiri kalmazsa alan verilmemiş sayılıyor. Kural
  `includes/fn/forms.php`'de `pg_form_list_search_value()`.
- Gelişmiş aramanın açık/kapalı alanı (`?<sayfa>_advanced[]=x`) dizi gelirse
  verilmemiş sayılıyor (varsayılan açık listede açık).
- Düzende verilmeyen özellikler boş sayılıyor, sayaçlar sıfırdan başlıyor,
  seçeneği seçilmemiş dinamik süzgeç atlanıyor; değerler öncekiyle aynı,
  yalnız uyarılar yok.
- Katalog RSS bağlantısını arama terimi metinse gizliyor; dizi gelen terimde
  bağlantı terimsiz sayfadaki gibi çıkıyor.
- `tests/forms_test.php`: `pg_form_list_search_value()` için üç test.
- Şema, dil dosyası ve JS değişikliği yok.

**Doğrulandı (sandbox, MariaDB, PHP 8.3).** Örnek verideki gelişmiş aramalı
üç liste özel sayfada ve düzenleri eski `{name: '…'}` biçiminde (aşağıya
bakın), bu yüzden blog formuna her tipten alan (tek ve çoklu seçim listesi,
radyo, tek ve iki seçenekli onay kutusu, tarih, tarih ve saat, saat,
e-posta, dosya, metin alanı) eklendi; `/blog`'da varsayılan açık bir düzen
(standart alanlar ve iki uçlu aralıklar dahil), `/blog-cards`'ta dinamik
alanlı varsayılan kapalı bir düzen kuruldu. Eski kod (`origin/development`)
ve yeni kod aynı sunucuda sırayla, opcache kapalı. 160 dizi isteği (`[]=x`,
`[a]=x`, `[0][]=x`, arama düğmesiyle ve düğmesiz): eski kodda 82'si 500,
hepsi uyarılı; yeni kodda hepsi 200, uyarı yok (oturum açmadan ve yönetici
olarak). Dizi istekleri, dizi parametre çıkarılmış eşleriyle karşılaştırıldı:
146'sı aynı (ayrım yalnız adresi taşıyan `send_to`'da); radyo alanında
"[Herhangi]" `checked="checked"` yerine `checked` yazıyor (boş değerle
açılan sayfadaki gibi); arama ve temizle düğmesi dizi gelince basılmış
sayılmaya devam ediyor, yalnız etiketinde gelen değeri değil kendi adını
gösteriyor. 88 normal istek (terimsiz, her alan için gerçek değer ve boş
değer, çoklu seçimde bir ve iki değer, düğmeler, açık/kapalı) eski ve yeni
kodda aynı HTML'i veriyor (oturum jetonu ve rastgele CAPTCHA dışında);
eski kodda hepsi uyarılı, yeni kodda hiçbiri. Katalog (`/shop-sidebar`,
`/shop-sidebar/Office_Chair`, `/shop-fullwidth`): dizi terimli 10 istekte
yeni kodda RSS bağlantısı terimsiz sayfadakiyle aynı, eski kodda yoktu;
metin terimli ve terimsiz istekler eski ve yeni kodda aynı.
`php -l`, `tools/lint.php`, `tools/test.php` (910 test), `check_*` ve
`build_js.php --check` geçti. Doğrulanamadı: dev.pinegrap.com'daki gerçek
sayfalar.

**Bilerek yapılmayan.** Gelişmiş arama düzeni `json_decode()` ile okunuyor;
düzenleme ekranındaki örnek ve örnek verideki üç liste (`all-conversations`,
`all-support-tickets`, `all-services-projects`) JSON olmayan
`{name: 'submitter', dynamic: true}` biçimini kullanıyor. Bu alanlar hiç
çözülmüyor, sayfada `{{name: …}}` yer tutucusu olduğu gibi görünüyor (eski
kodda da aynı). Düzeltmek bu sayfaların HTML'ini değiştirir; ayrı iş.
Katalogda boş terimle (`?query=`) RSS bağlantısı yine çıkmıyor (eski kodda
da aynı); özel düzendeki katalog arama alanı dizi terimle `value=""` yazıyor
(#403'ten). `liveform`, `includes/fn/custom_form.php`,
`get_catalog_detail.php` ve ortak tablo yardımcısına dokunulmadı.

---

## 2026.4.10 — Çalışma Alanı: panelde gönderilemeyen mesajın dosyası artık hiç kaydedilmiyor (2026-10-14)

**Neden.** #402'nin bıraktığı parça. Panelin ataçı (`ws_attach` eylemi) ve
düzenlenmiş kopyayı yanıt olarak gönderme (`ws_message_file_send()`:
metin düzenleyicinin ve resim düzenleyicinin "kopya olarak gönder"i) önce
dosyayı kaydediyor (`ws_store_upload()`), sonra mesajı yazmayı deniyordu.
Mesaj reddedilirse (4000 karakteri aşan metin, dakikada yirmi mesaj
sınırı) #382'den beri dosya `ws_upload_discard()` ile geri alınıyordu,
ama o ana kadar dosya diske yazılmış, `files` satırı eklenmiş (kimliği
harcanmış) ve kanalın ilk dosyasıysa Dosya Yöneticisi'nde kanala klasör
(gerekirse "Workspace" klasörü de) açılmıştı; klasör boş kalıyordu.
Sandbox'ta 4000 karakteri aşan metinle ataç gönderimi: `folder` 27 → 29
("Workspace" ve "genel"), `files` sayısı aynı, `AUTO_INCREMENT` bir
ilerlemiş. API'nin dosya ucu #402'den beri dosyayı ve mesajı kaydetmeden
önce denetliyor; panel buna uyduruldu.

**Ne değişti.**
- Yeni `ws_message_send_file()` (`includes/workspace/files.php`): base64
  dosyayı çözer, önce `ws_upload_check()` (kanala yazma yetkisi, ad, boyut,
  içerik) sonra `ws_message_send_check()` (dosya kimliğinin yerine
  `PHP_INT_MAX`, API'deki gibi: dosyanın yanında metin isteğe bağlı)
  sorar; ikisi de geçerse dosyayı kaydeder ve mesajı yazar. Denetimle
  yazma arasında mesaj yine de reddedilirse (sınır o arada doldu) dosya
  eskisi gibi `ws_upload_discard()` ile geri alınır.
- `ws_attach` ve `ws_message_file_send()` bu fonksiyonu çağırıyor; hata
  metinleri ve sırası (önce dosya, sonra mesaj) aynı.
- Başarılı gönderim değişmedi: dosya kanalın klasöründe aynı adla, aynı
  `files` satırıyla, mesaj aynı `file_id` / `file_name` / `parent_id` ile
  yazılıyor; Site Günlüğü'ne eskisi gibi satır yazılmıyor.
- Test: `test_ws_message_send_file_checks_before_storing` iki denetimin
  dosya kaydedilmeden önce yapıldığını, ataçın ve düzenlenmiş kopyanın bu
  fonksiyondan geçtiğini, API ucunun da mesajı dosyadan önce denetlediğini
  kaynakta denetliyor (test koşucusunda veritabanı yok).

Şema değişikliği yok, izin yok, yeni metin yok, JS değişmedi. API uçlarına
dokunulmadı. Dosyayı yerinde değiştirme (`ws_message_file_replace()`) mesaj
yazmadığı için aynı kaldı.

**Doğrulama.** Sandbox (`tools/setup_sandbox.sh`, `workspace_enabled = 1`),
panel oturumuyla `api.php`'ye `ws_attach`: düzeltmeden önce yukarıdaki
artık yeniden üretildi; sonra klasörü olmayan yeni kanalda 4000 karakteri
aşan metin, `.php` adı ve dakika sınırı reddi `files`, `folder`,
`ws_messages`, `log` sayılarını, `files` `AUTO_INCREMENT`'ını ve
`data/files`'ı değiştirmedi. Başarılı ataç ve düzenlenmiş kopya (metin:
`ws_file_text_save`, resim: `workspace_file_save.php?as=send`) eski kodla
aynı satırları yazdı (kanal klasörü "Workspace" altında, dosya o klasörde,
yanıt `parent_id` ile). `php_errors.log`'da yeni satır yok.

---

## 2026.4.10 — Dış API: kaydedilen ERP tahsilatının zil satırı "system" diyordu ve kaydedenin zilinde okunmamış duruyordu (2026-10-14)

**Neden.** #411'in bıraktığı parça. ERP Ayarları'nda tahsilat bildirimi
açıkken her tahsilat zile bir "Tahsilat alındı" satırı yazıyor
(`erp_alert_event()` → `erp_alert_create()`, `includes/erp/alerts.php`).
Satır, kaydı yapanın adını `USER_USERNAME`'den, kendi zilinde okunmuş
sayılacak kişiyi `USER_ID`'den alıyordu. API ve MCP sunucusu panel
kullanıcısı yüklemediği için ikisi de boş: API'den kaydedilen tahsilatın
satırında ad yerine `system` yazıyordu ve tahsilatı kaydeden kişi kendi
işini zilinde okunmamış bildirim (cihazına da bildirim) olarak alıyordu.
Panelde aynı kişi tahsilatı kaydedince satır onun adını taşıyor ve onun
zilinde okunmuş sayılıyordu.

**Ne değişti.**
- Yeni `erp_alert_actor()` / `erp_alert_current_actor()`: panelde oturum
  açan kişi; panel kullanıcısı yoksa API'yi çağıran uygulamanın sahibi
  (ekip cihazları oturumu ve OAuth'ta oturum açan kişi, uygulama
  anahtarında uygulamanın sahibi; #411'in `ws_bell_user()`'ıyla aynı
  kural). Zamanlanmış işte ikisi de yok, satır eskisi gibi `system`.
- `erp_alert_event()` satırın adını, `erp_alert_create()` okundu satırının
  kişisini buradan alıyor. Tek yerde olduğu için tahsilata varan bütün
  API ve MCP yolları (`POST /erp/receipts`, `erp_receipts_create` ve
  tahsilat kaydeden öbür uçlar) kapsanıyor.
- Öbür kişilerin zilindeki satırın metni, sayısı ve cihaz bildirimi aynı.
  Panelden kaydedilen tahsilat değişmedi. Eski satırlar geriye dönük
  düzeltilmedi.
- Test (`tests/erp_api_test.php`): `test_erp_alert_actor` kişinin nereden
  geldiğini, `test_erp_alert_rows_carry_the_actor` `erp_alert_create()` ve
  `erp_alert_event()`'in kişiyi panel sabitlerinden değil
  `erp_alert_current_actor()`'dan aldığını denetliyor (düzeltme öncesi
  kodda ikisi de kırmızı).

Şema değişikliği yok, izin yok, API belgesi, `tr.json` ve JS değişmedi.

**Doğrulandı (sandbox, MariaDB, PHP 8.3).** ERP ve tahsilat bildirimi
açık, iki yönetici (`admin`, `ayse`). Düzeltme öncesi: `ayse`'nin
uygulama anahtarıyla ve `admin`'in ekip cihazları oturumuyla
`POST /erp/receipts` iki satırı da `system` adıyla, okundu satırı olmadan
yazdı. Düzeltme sonrası: anahtarla `ayse`, cihazla `admin`, MCP'de
(`erp_receipts_create`, anahtarla) `ayse` adıyla; her satır yalnız
kaydedenin zilinde okunmuş, öbür yöneticide okunmamış. Panelden
(`add_erp_receipt.php`, `admin`) kaydedilen tahsilat cihazla aynı
sonucu verdi. OAuth bağlantısıyla denenmedi (cihazla aynı doğrulama
yolundan geçiyor).

**Bilerek yapılmayan.** Öbür ERP zil satırları (düşük stok, süresi dolan
teklif, vadesi geçen alacak) yalnız zamanlanmış işlerden yazılıyor,
API'den tetiklenmiyor; `system` doğru. Ödeme (para çıkışı) zile satır
yazmıyor.

---

## 2026.4.10 — API belgesi: 200 dönen yazmalarda prova cevabı, kişi dışı boş gelebilen alanlar (2026-10-14)

**Neden.** #401'in bıraktığı üç iş. (1) Provası olan ve gerçekte 200 dönen
yazma uçlarında (güncelleme, işlem, silme; 122 rota, takma adlarıyla 151
işlem) prova cevabı (`api_dry_run_stop()`, `{"dry_run": true, ...}`)
OpenAPI'de yalnız kaydın şemasıyla yazılıydı: belgeden üretilen istemci
provanın cevabını kayıt sanıyordu. (2) Nullable taraması kişi alanlarıyla
sınırlıydı. (3) OAuth istemci kaydının 201'i API belgesinde yoktu.

**Ne değişti.**
- `api_openapi_responses()`: provası olan rotanın 200'ü artık
  `anyOf: [DryRun, kaydın şeması]`; açıklaması provanın da 200 döndüğünü
  söylüyor. 201 + 200 dönen üç rotada (`workspace.threads.start`,
  `erp.waybills.invoice`, `erp.accounts.late_fee_invoice`) 200 de böyle.
  Oluşturma ve 204 uçlarının ayrı prova 200'ü (`DryRun`) değişmedi.
- `oneOf` değil `anyOf`: kayıt şemaları hiçbir alanı zorunlu tutmadığı için
  prova cevabı kaydın şemasına da uyuyor; `oneOf` "yalnız biri" ister ve
  sıkı bir doğrulayıcı (`openapi-schema-validator` ile denendi) her prova
  cevabını reddederdi, bütün üyeleri deneyen üretilmiş istemciler de
  (openapi-generator Python) "birden çok eşleşme" hatası verirdi.
  `DryRun` önce geliyor ve artık `dry_run`, `would`, `resource`'u zorunlu
  tutuyor (yeni `api_openapi_dry_run_schema()`); hiçbir kayıtta `dry_run`
  alanı yok, bu yüzden ilk eşleşeni alan istemci her cevabı doğru okur.
- Nullable taraması: her nesnenin sunucu fonksiyonunda `null`,
  `api_time()`, `erp_api_date()` ve `*_ref()` veren alanlar ve ham
  sütun değerleri bildirimle karşılaştırıldı. Gerçekten boş gelebilen ve
  boş olamaz yazılı olanlar:
  - `Customer.first_name`, `last_name`, `email`, `company`, `phone`,
    `address.*`: `contacts` sütunları `DEFAULT NULL`, kişi bilinenle açılıyor
    (e-posta tercihleri yalnız adresle, `POST /customers` yalnız verilen
    alanlarla), `api_customer_phone()` üç numara da boşsa `null`.
  - `Product.short_description`: `DEFAULT NULL` sütun; sütunu olmayan bir
    tabloyla içe aktarılan ürün onu yazmıyor.
  - `Meta.ecommerce.default_tax_rate`: sitenin ülkesi hiçbir vergi
    bölgesinde değilse `null` (kod yorumu da öyle diyordu).
  - `WorkspaceRecord.lines`: yalnız sipariş satırlarıyla okunuyor
    (`ws_ai_record_present()`), öbür kayıtlarda `null`. Satır içi liste
    nullable yazılamadığı için yeni nesne `WorkspaceRecordLine` ve
    `'WorkspaceRecordLine[]?'`.
- #400'ün kanal grubu nesneleri tarandı, düzeltme gerekmedi: boş
  gelebilenler (`parent_id`, `color`, `access`, taşımada `group_id`,
  `group_path`, `previous_group_id`, yetkide `granted_by`, `granted_at`)
  zaten `?` taşıyor, gerisi hep dolu. Test bunu sabitliyor.
- `tests/api_openapi_status_test.php`: 5 yeni test (200 yazmanın iki
  biçimi; `DryRun`'un zorunlu alanları ve hiçbir nesnede `dry_run` alanı
  olmaması; üç tablo + çeviri modülünde provası olan her rotanın 200'ü;
  kişi dışı nullable alanlar; kanal grubu nesneleri), iki test yeni 200'e
  göre güncellendi.

Uçların davranışı, şema, dil dosyası ve JS değişmedi; değişen OpenAPI
üreticisi ve nesne bildirimleri.

**Bilerek yapılmayan.** OAuth istemci kaydı (`POST oauth.php/register`,
201) API'nin OpenAPI belgesine eklenmedi: `integration.php`'nin değil
OAuth yetkilendirme sunucusunun ucu; RFC 7591'in kendi biçimiyle
(`{"error", "error_description"}`) cevap veriyor, API kimliği almıyor,
yalnız OAuth açıkken var ve istemciler onu RFC 8414 belgesinden
(`.well-known/oauth-authorization-server`, `registration_endpoint`)
buluyor. Yanındaki `authorize`, `token` ve `revoke` da belgede değil;
yalnız kaydı eklemek yarım, hepsini eklemek tarayıcı yönlendirmeli bir
akışı REST istemci üreticisine anlatmak olurdu. Ham sütunda `DEFAULT NULL`
olup her ekleme yolunda yazılan alanlar (ürün ve grup adı, sayfa başlığı,
dosya adı ve türü) işaretlenmedi. Bearer şemasının açıklaması (#405
artığı) bu işin dışında.

**Doğrulama.** Sandbox'sız: OpenAPI belgesi CLI'da modüller açık üretildi
(380 işlem), önce ve sonra `openapi-spec-validator` ile geçerli. Fark:
151 işlemin 200'ü `anyOf`, provası olan her işlemin 200'ü ya `DryRun` ya
`anyOf`; değişen bileşenler `DryRun`, `Customer`, `Product`, `Meta`,
`WorkspaceRecord`, yeni `WorkspaceRecordLine`. Örnek cevaplar
`openapi-schema-validator` ile: prova ve ürün cevabı `anyOf`'a uyuyor,
prova cevabı `oneOf`'a uymuyor; adsız müşteri yeni şemaya uyuyor, eskisine
uymuyordu. `lint`, `check_lang`, `check_bindings`, `check_api_schema`,
`check_copies`, `build_js --check`, `tools/test.php` yeşil.

---

## 2026.4.10 — Site formları: dört POST betiği ve özel form fonksiyonları dizi gelen değerde 500 veriyordu (2026-10-14)

**Neden.** #394 sayfaların ekranlarını temizlerken bu ekranların
formlarını işleyen POST betiklerini ve `includes/fn/custom_form.php`'deki
iki `?value_<alan>[]=` okumasını ayrı işe bıraktı. Sandbox'ta eski kodla
(oturum açmış yönetici ve oturumsuz ziyaretçi; ortaklık başvurusu, Hesabım
profil, e-posta tercihleri ve yedi özel form; her alan tek tek dizi olarak,
hem doğrulama hatasıyla geri dönen hem kaydı yazan yoldan; toplam 455
istek) yeniden üretildi: 202 istekte HTTP 500 ya da uyarı. Hepsi
2026.4.9'da da var.

Görünür hatalar (500):

- `liveform::add_fields_to_session()` `alan[]=x`'i dizi olarak saklıyor,
  betikler değeri `escape()`, `validate_email_address()`,
  `validate_date()`, `trim()`, `mb_strtolower()` ya da `urlencode()`'a
  veriyordu (`TypeError`). Ortaklık başvurusunda her alan, profilde her
  sütun (vergi alanları `trim()`'de), e-posta tercihlerinde adres,
  abonelik ve liste kutuları ile `id`, özel formda e-posta, tarih,
  `page_id`, `connect_to_contact`, `add_watcher` ve tek seçimli liste.
- Özel formda dosya alanı `44[]` adıyla gönderilince `$_FILES` dizisinin
  her anahtarı dizi geliyordu; `prepare_file_name()` 500 verdi.
- Hızlı sipariş (`prepare_custom_form()`, özel ödeme ya da kargo formu
  olan sayfa) `?value_<alan>[]=x` ile 500 verdi.
- Doğrulama hatasıyla dönen formun sayfası dizi değeri `h()`'ye verip
  500 veriyordu; bu yol #403'ün `liveform` düzeltmesiyle de kapandı, bu
  değişiklikle dizi oturuma hiç girmiyor.

Görünmeyen uyarılar:

- Görsel editörün özel form widget'ı (`pg_cf_field_initial_value()`,
  `pg_cf_connect_to_contact_off()`) `(string)` ile okuyordu: alana
  "Array" yazıyor, uyarı düşüyordu.
- E-posta tercihlerinde `sig[]` ve tek tıkla abonelikten çıkmada
  `?id[]=` / `?sig[]=`: "Array to string conversion".
- `affiliate_sign_up_form.php` 109: hazır ekran `current_page_id=0`
  gönderir, şartlar sayfası sorgusu satır döndürmez; her gönderimde uyarı.
- `custom_form.php`: dosya alanı istekte hiç yoksa iki uyarı.
- `prepare_custom_form()`: ödeme formu `ship_to_id`, iki çağrı da
  `form_name` vermiyor; özel formu olan hızlı sipariş sayfasının her
  açılışında iki uyarı.

**Ne değişti.** Yeni `pg_liveform_single_values()`
(`includes/fn/forms.php`): betik `add_fields_to_session()`'dan sonra tek
değer tutan alanlarını listeler, dizi gelen değer boş metne çevrilir. Yani
dizi "verilmemiş" sayılıyor, sonrası boş alanla aynı: zorunlu alan
"gerekli" hatasıyla geri döner, zorunlu olmayan boş yazılır. Özel formda
liste onay kutusu grubu ve çoklu seçim dışındaki her alanı, gizli
alanları (`folder_id`, `send_to`, `add_watcher`, `add_watcher_page_id`)
ve CAPTCHA cevabını kapsar; tuzak alan (`body`) dışarıda, içine dizi de
gelse gönderen bot sayılmaya devam ediyor. Profilde saat dilimi listede değil: dizi
orada zaten "geçersiz saat dilimi" hatası veriyordu. Doğrudan okunan
`$_POST` değerleri (`current_page_id`, `next_page_id`, `id`, `sig`,
`connect_to_contact`, özel formun ilk `page_id` sorgusu) ve tek tık
çıkıştaki `$_GET` değerleri yalnız metinse okunuyor; dizi `page_id`
"sayfa bulunamadı" hatasıyla durur. Dizi adlı dosya alanı boş dosya
kontrolünün gönderdiği hâle getiriliyor (`UPLOAD_ERR_NO_FILE`).
`pg_cf_field_initial_value()`, `pg_cf_connect_to_contact_off()` ve
`prepare_custom_form()` `value_<alan>` / `connect_to_contact`'ı yalnız
metinse okuyor, #394'ün `get_form_info()`'daki biçimiyle. Uyarı
susturulmadı. `liveform.class.php`'ye dokunulmadı. Şema, dil dosyası ve
JS değişikliği yok.

Davranış farkı (yalnız elle kurulmuş istekte): zorunlu tek değerli bir
alan dizi gelince eskiden her elemanı ayrı satır olarak kaydediliyordu
(ortaklık şartları kutusunda dizi kabul sayılıyordu); artık form
"gerekli" uyarısıyla geri dönüyor. Taramada 8 istek böyle değişti.

**Bilerek yapılmayan.** `submit_custom_form()` (hızlı sipariş, fatura
bilgileri, kargo adresi ve sipariş gönderiminin özel formları) ve onu
çağıran `express_order.php`, `billing_information.php`,
`shipping_address_and_arrival.php`, `submit_order.php` POST'ları
taranmadı; tarih, saat ve e-posta alanında dizi aynı `TypeError`'ı verir.
Ayrı iş.

**Doğrulandı (sandbox, MariaDB, PHP 8.3).** Eski kod `origin/development`
worktree'sinden ikinci portta sunuldu; iki tarama aynı veritabanı
dökümünden başladı. 455 isteğin 245'i aynı sonuç, 202'si eskide 500 ya
da uyarıyken yenide temiz, 8'i yukarıdaki davranış farkı; yeni kodda
hiç 500 ve uyarı yok (#403 birleştikten sonra da). Ayrıca oturumsuz
e-posta tercihleri `id[]`/`sig[]`, tek tıkla çıkış, dosya alanı `44[]`
(eski 500, yeni kayıt dosyasız), hızlı sipariş `?value_<alan>[]=` (eski
500, yeni 200) ve widget'ı işleyen geçici betikle `?value_1[]=x`
(eski `value="Array"`, yeni boş; metin değer ikisinde de aynı) denendi.
`tests/form_array_params_test.php` eski kodda 4 hata, yenide geçiyor;
`tools/test.php`, lint, dil, kopya, bağlama, API şeması ve
`build_js.php --check` geçti.
Doğrulanamadı: dev.pinegrap.com'daki gerçek formlar.

---

## 2026.4.10 — Dış API: tetiklenen Çalışma Alanı bildiriminin zil satırı adsız yazılıyordu (2026-10-14)

**Neden.** #404'ün bıraktığı parça. Çalışma Alanı'nın her bildirimi
(`ws_notify()`: anma, görev verme, görev bitti, kanala ekleme, onay
isteği ve kararı, not paylaşımı, görev notu, tartışma, okundu isteği ve
benzerleri) kişinin zilinde de bir satır açıyor (`ws_bell_add()`,
`notifications.user`). Satır, işi yapanı panel oturumundaki kullanıcı
adından (`$_SESSION['sessionusername']`) alıyordu; API ve MCP sunucusu
oturum tutmadığı için API'den tetiklenen bildirimlerin zil satırı boş
adla düşüyordu. Yalnız #404'ten beri `ws_api_as_owner()` içinde çağrılan
kanal oluşturmanın "kanala eklendi" satırı adı taşıyordu.

**Ne değişti.**
- `ws_bell_add()` adı yeni `ws_bell_user()`'dan alıyor: panelde oturum
  açan kişi; oturum yoksa API'yi çağıran uygulamanın sahibi (ekip
  cihazları oturumu ve OAuth'ta oturum açan kişi, uygulama anahtarında
  uygulamanın sahibi; `ws_change_log()` ve `ws_api_as_owner()` ile aynı).
  Zamanlanmış işlerde ikisi de yok, satır eskisi gibi adsız. Tek yerde
  olduğu için `ws_notify()`'a varan bütün API ve MCP yolları kapsanıyor.
- Zil satırının metni, sayısı, kime gittiği ve cihaz bildirimi aynı.
- Test: `test_ws_bell_user` adın nereden geldiğini,
  `test_ws_bell_rows_carry_the_owner` `includes/workspace/` altında zile
  satır yazan her fonksiyonun adı `ws_bell_user()`'dan aldığını denetliyor
  (düzeltme öncesi kodda ikisi de kırmızı).

Şema değişikliği yok, izin yok, API belgesi, `tr.json` ve JS değişmedi.

**Doğrulandı (sandbox, MariaDB, PHP 8.3).** Eski kod (`origin/development`)
ikinci bir portta, aynı veritabanında. Uygulama anahtarıyla (sahibi
`admin`) ve `ayse`'nin ekip cihazları oturumuyla kanal açıp karşı tarafı
ekleme, mesajda anma ve görev verme: eski kodda anma ve görev satırları
boş adla, yeni kodda anahtarda `admin`, cihazda `ayse` adıyla yazıldı;
kanala ekleme satırı ikisinde de adlı (#404). Her çalıştırmada üç satır,
metinler aynı; sunucu günlüğünde uyarı yok. OAuth bağlantısıyla
denenmedi (cihazla aynı doğrulama yolundan geçiyor).

**Bilerek yapılmayan.** ERP tahsilat bildirimi (`erp_alert_event()`,
Çalışma Alanı değil) API'den kaydedilen tahsilatta adı `USER_USERNAME`
boş olduğu için `system` yazıyor ve kaydı yapanın kendi zilinde de
okunmamış duruyor (`USER_ID` boş); ayrı iş, kodu okuyarak bulundu,
denenmedi.

---

## 2026.4.10 — Form listesi ve katalog detayı dizi parametreyle 500 veriyordu, form listesi araması tarih ve saat alanlarında uyarı yazıyordu (2026-10-14)

**Neden.** #403'ün bıraktığı parçalar, hepsi 2026.4.9'da var. (1) Form
listesi sayfası (`get_form_list_view.php`) arama terimini `liveform`'dan
okuyor; `?<sayfa kimliği>_query[]=x` ya da eski biçim `?query[]=x` ile gelen
dizi `!= ''` denetiminden geçip arama SQL'inde `escape()`'e gidiyor, sayfa
500 veriyordu (blog, ilan, forum, personel dizini; sistem widget'ının
`sw<kimlik>_query` adı da aynı yoldan). (2) Aynı arama terimi her alan için
`prepare_form_data_for_input()`'tan (`includes/fn/forms.php`) geçiyor. Bu
fonksiyonun "date and time" dalı terimi tarih ve saate bölüyor; tarih ve saat
biçiminde olmayan bir terimde (`blog`, `2016`, saatsiz bir tarih) eksik
parçaları okurken "Undefined array key" uyarısı yazıyordu. Her listede dört
standart "date and time" alanı (gönderim, son değişiklik, en yeni yorum, en
yeni etkinlik) olduğu için form listesindeki her arama `php_errors.log`'a
uyarı yazıyordu. "time" dalı ve bu tipteki özel alanlar da aynı. (3) Katalog
detay sayfası (`get_catalog_detail.php`) `?previous_url_id[]=x` ile "Geri"
düğmesinin `isset($_SESSION['software']['urls'][dizi])` denetiminde 500
veriyordu (oturumda saklı bir katalog adresi varken: hazır düzende iki yer,
özel düzende bir yer). #403 aynısını katalog sayfasında düzeltmişti.

**Ne değişti.**
- Form listesi yeni ve eski biçim arama terimini yalnız metinse kullanıyor;
  dizi gelen terim verilmemiş sayılıyor, liste aramasız açılıyor, arama
  alanı boş. Yeni biçim dizi, eski biçim metinse eski biçim kullanılıyor
  (öncelik sırası aynı).
- `prepare_form_data_for_input()` "date and time" ve "time" dallarında eksik
  parçaları boş sayıyor. Eksik parça önce de `null` okunup boş dizgeye
  dönüşüyordu; dönen değer her girdide aynı, yalnız uyarılar yok.
- Katalog detayı üç yerde `previous_url_id`'yi yalnız metinse okuyor; dizi
  gelince "Geri" düğmesi çıkmıyor (oturumda adres yokmuş gibi).
- `tests/forms_test.php`: tarih ve saat dallarında beş test (eski kodda üçü
  uyarıyla düşüyor).
- Şema, dil dosyası ve JS değişikliği yok.

**Doğrulandı (sandbox, MariaDB, PHP 8.3).** Eski kod (`origin/development`) ve
yeni kod aynı sunucuda sırayla, opcache kapalı. Form listesi: `/blog`,
`/blog-cards`, `/staff-directory`, `/forum` `?<kimlik>_query[]=x`, `/blog`
`?query[]=x`, `?227_query[a]=x`, `?227_query[0][]=x` ve
`?227_query[]=x&query=blog` eski kodda 500, yeni kodda 200. Dizi terimli
sayfanın HTML'i terimsiz sayfayla aynı (ayrım yalnız bağlantılarda taşınan
adres); `?227_query[]=x&query=blog` `?query=blog` ile aynı sonuçları
veriyor. Katalog detayı (`/shop-product-sidebar`, `/shop-product-fullwidth`,
özel düzende ve sayfa hazır düzene alınarak) `?previous_url_id[]=x` ve
`?previous_url_id[a]=x` oturumda katalog adresi varken eski kodda 500, yeni
kodda 200; HTML "Geri" düğmesi olmayan sayfayla aynı. Normal istekler
eski ve yeni kodda aynı HTML'i veriyor (oturum jetonu ve rastgele CAPTCHA
sorusu dışında): aramasız liste; `?227_query=` ile `blog`, `foo`, `2016`,
`27/03/2016`, `03/27/2016 9:30 AM`, `9:30 PM`; `?query=blog`; dizi gelen
browse, sayfa numarası, clear ve başka parametreler; katalog detayı
`previous_url_id=0`, `=9` ve parametresiz. Bu aramalar eski kodda
`php_errors.log`'a uyarı yazıyor, yeni kodda yazmıyor.
`prepare_form_data_for_input()`'un eski ve yeni sürümü 21 girdi, 4 tip ve
iki tarih biçiminde aynı değeri döndürüyor (eski 232 uyarı, yeni 0).
`php -l`, `tools/lint.php`, `tools/test.php` (892 test), `check_*` ve
`build_js.php --check` geçti. Doğrulanamadı: dev.pinegrap.com'daki gerçek
sayfalar.

**Bilerek yapılmayan.** `prepare_form_data_for_input()` tarih ve saat
biçiminde olmayan bir terimi yine `0000-00-blog 00:00` gibi bir değere
çeviriyor (bu alanlarda hiçbir kayıtla eşleşmez). Ham terimi aramak
(`2016` ile o yılın kayıtları) arama sonuçlarını değiştirirdi; bu ayrı bir
karar. `liveform` ve `includes/fn/custom_form.php`'ye dokunulmadı (başka
iş). Katalog sayfasının dizi terimle açılınca RSS bağlantısını göstermemesi
kapsam dışı kaldı.

---

## 2026.4.10 — Çalışma Alanı: kanal grubu, bu arada değişmiş bir listede sessizce en sona gitmiyor (2026-10-14)

**Neden.** #400'ün bıraktığı parça, panel tarafı 2026.4.9'da da var. Bir
kanal grubunu başka bir grubun önüne koymak için panel (`ws_group_move`
eylemi: kenar çubuğunda sürükleme, grup menüsündeki Yukarı / Aşağı taşı) ve
API (`POST|PATCH /workspace/channel-groups/{id}`, `before_id`) aynı
`ws_group_move()`'u çağırıyor. API, `before_id` grubun varacağı seviyede
başka bir grup değilse 422 veriyordu; `ws_group_move()` ise böyle bir
`before_id`'yi "en son" sayıp grubu sessizce sona koyuyordu. Panel
`before_id`'yi her zaman ekranda çizili listeden alır: en son için 0
gönderir, sürüklenen grubu listeden çıkarır. Geçersiz bir `before_id` bu
yüzden ancak liste bu arada başka biri tarafından değiştirildiyse gelir
(önüne bırakılan grup kaldırılmış ya da başka seviyeye taşınmış). O zaman
grup bırakılmadığı bir yere, ekrandaki eski seviyenin sonuna gidiyor, ekran
da eski listeyi göstermeye devam ediyordu. Panelin sona eklemeyi sürdürmesi
için bir sebep yok; bu yüzden panel API'ye uyduruldu.

**Ne değişti.**
- Kural tek yerde, `ws_group_before_fits()` (`includes/workspace/groups.php`):
  `before_id` 0 (ya da altı) en son demek; başka bir değer, grubun varacağı
  seviyede grubun kendisi olmayan bir grup olmalı.
- `ws_group_move()` kurala uymayan `before_id`'yi reddediyor (`field`
  `before_id`), hiçbir şey yazmıyor. Panelde görünen metin "The groups
  changed in the meantime and the list has been loaded again. Try the move
  once more." (`tr.json`'da Türkçesi).
- Panelin `moveGroup()`'u başarısız bir taşımada hatayı gösterip kanal ve
  grup listesini sunucudan yeniden yüklüyor (`reloadChannels(0)`); önceden
  ekranda eski liste kalıyordu. `workspace.min.js` yeniden üretildi.
- API'nin ön denetimi aynı fonksiyonu soruyor; 422 cevabının gövdesi,
  metni ve alanı (`before_id`) aynı. Ön denetim prova cevabından ve ad /
  renk kaydından önce koşuyor; ön denetimle taşıma arasında liste değişirse
  `ws_group_move()`'un reddi de 422 `before_id` olarak dönüyor
  (`ws_api_channel_group_refused()` alanı taşıyor).
- API belgesi: güncelleme ucunun açıklaması ve `before_id` parametresi
  422'yi söylüyor (OpenAPI aynı tanımdan üretiliyor).
- Test: `test_ws_group_before_fits` kuralı (aynı seviye, varılan seviye, 0,
  eksi, olmayan grup, başka seviye, ayrılan seviye, grubun kendisi) ve iki
  çağıranın da fonksiyonu sorduğunu, API'nin prova ve kayıttan önce
  sorduğunu denetliyor.

Şema değişikliği yok, izin yok; bir `tr.json` satırı.

**Bilerek yapılmayan.** Kanal grupları için webhook olayı yok (panelde de
yok). Kanalı bir gruba taşıma (`ws_channel_move_group()`) `before_id`
almıyor, değişmedi.

**Doğrulama.** `test.php` (tam takım), `lint`, `check_lang`,
`check_api_schema`, `check_bindings`, `check_copies`, `build_js --check`,
`node --check workspace.js` temiz. Çalışan örnekte sürüklenerek
denenmedi.

---

## 2026.4.10 — Dış API: altı Çalışma Alanı yazımına prova (2026-10-14)

**Neden.** #393'ün belge taraması, mobil uygulamanın en sık yazdığı altı uçta
`X-Dry-Run` / `?dry_run` olmadığını buldu (belgede bu altısında "Prova
edilebilir" satırı yoktu):
`POST /workspace/channels/{id}/messages`, `POST /workspace/channels/{id}/files`,
`POST /workspace/messages/{id}/reactions`, `POST /workspace/messages/{id}/ack`,
`POST /workspace/approvals/{id}/decide`, `POST /workspace/tasks/{id}/notes`.
Prova isteyen çağrı 400 `dry_run_unsupported` alıyordu; uygulama gövdesini
denemek için gerçek mesaj yazmak, gerçek onay vermek zorundaydı.

**Ne değişti.**
- Altı rota `'dry_run' => true` taşıyor; API belgesi her birinde "Prova
  edilebilir" satırını, OpenAPI `X-Dry-Run` ve `dry_run` parametresini, MCP
  aracı (dosya yükleme dışındakiler) `dry_run` argümanını kendiliğinden
  veriyor.
- Panel fonksiyonlarının yazmadan önceki denetimleri ayrı `*_check()`
  fonksiyonlarına alındı; gerçek fonksiyon önce onu çağırıyor, API ucu da
  provada aynısını çağırıp `api_dry_run_stop()` ile duruyor. Böylece provanın
  reddi gerçeğin reddiyle aynı kod, aynı alan:
  `ws_message_send_check()` (yazma izni, boş mesaj, uzunluk, kişinin dakikada
  yirmi mesaj sınırı), `ws_upload_check()` / `ws_upload_inspect()` (ad, boyut,
  tür, resmin gerçekten resim olması, sayfa/program içeriği),
  `ws_reaction_check()` (emoji, mesaj, yazma izni, mesaj başına yirmi farklı
  emoji), `ws_ack_set_check()` (onay istenmiş mi, kişi sayılanlardan mı),
  `ws_approval_decide_check()` (açık mı, süresi dolmuş mu, kişiye sorulmuş mu,
  cevaplamış mı), `ws_task_note_add_check()` (görünürlük, boş / uzun not).
- Prova cevabı API'nin ortak biçimi (`dry_run`, `would`, `resource`,
  `detail`): mesaj `created` / `workspace_message` (`channel_id`, `kind`,
  `ack`, kişiyi herkese açık kanala katacak mı: `joins`); dosya `created` /
  `workspace_message` (`file`: ad, tür, boyut); tepki `added` / `removed` /
  `workspace_reaction` (`changes`: emoji zaten varsa ya da yoksa false); okundu
  onayı `acknowledged` / `workspace_read_receipt` (`changes`: kişi zaten onay
  verdiyse false); onay kararı `decided` / `workspace_approval` (`outcome`:
  cevaptan sonra istek `open`, `approved` ya da `rejected`); görev notu
  `created` / `workspace_task_note` (`task_id`).
- Dosya provası dosyayı diske yazmıyor: içerik denetimi (resim mi, sayfa ya
  da program gibi mi okunuyor) artık baytlar üzerinde bellekte yapılıyor
  (`getimagesizefromstring()`, `finfo_buffer()`), kanalın klasörü de
  açılmıyor. Form parçası PHP'nin kendi geçici yükleme dosyasıdır, istek
  bitince PHP siler. Gerçek yüklemede de reddedilen dosya artık önce diske
  yazılıp sonra silinmiyor ve kanala klasör açtırmıyor; API'de mesaj metni
  reddedilecekse dosya hiç tutulmuyor (önceden tutulup geri alınıyordu).
- Süresi dolmuş onay isteğine gerçek cevap isteği "süresi doldu" diye
  kapatıyordu, öyle kalıyor; prova kapatmıyor (oylamadaki gibi: sonraki gerçek
  cevaba ya da saatlik işe bırakıyor).
- `Idempotency-Key` provada tüketilmiyor (yönlendirici zaten böyle: prova
  anahtar kaydı yazmaz, aynı anahtarla gelen gerçek çağrı yazılır). Site
  Günlüğü'ne provada satır yazılmıyor; bu altı uç gerçek çağrıda da yazmıyor.

Şema değişikliği yok, izin yok, `tr.json`'da yeni metin yok, JS değişmedi.
Panel davranışı aynı (aynı denetimler, aynı sıra).

**Doğrulama.** Sandbox'ta (`tools/setup_sandbox.sh`) uygulama anahtarı ve iki
ekip cihazı oturumuyla altı ucun her biri: provada mesaj, dosya, klasör,
tepki, okundu onayı, onay cevabı, görev notu, gelen kutusu ve Site Günlüğü
sayıları değişmedi, `data/files`'a dosya düşmedi; boş / uzun metin, `.php`
adı, resim olmayan `.png`, `.txt` adlı HTML, bozuk base64, emoji olmayan
metin, sorulmamış kişi, süresi dolmuş istek, uygulama anahtarıyla okundu
onayı ve onay, dakika sınırı provada gerçek çağrıyla aynı kodu ve alanı
verdi; aynı `Idempotency-Key` ile provadan sonra gerçek çağrı yazıldı.
OpenAPI'de altı işlemde `X-Dry-Run` / `dry_run` var. `lint`, `check_lang`,
`check_api_schema`, `build_js --check`, `test.php` temiz.

---

## 2026.4.10 — Üyelik giriş sayfası özel düzende `$captcha_question` uyarısı yazıyordu (2026-10-14)

**Neden.** #394'ün bıraktığı parça, 2026.4.9'da da var. Hazır şablonlardaki
üyelik giriş/kayıt sayfası (`member-login-register`, düzen `982.php`) kayıt
sekmesinde kayıt girişi (`registration entrance`) düzeninden kopyalanmış
`<?php if ($captcha_question): ?>` bloğunu taşıyor. Üyelik girişi ekranı
(`get_membership_entrance.php`) özel düzeni çizerken bu değişkeni hiç
vermiyordu; sayfa her açılışta, oturum açık olsun olmasın,
`php_errors.log`'a "Undefined variable $captcha_question" yazıyordu. Blok
tanımsız değişkenle zaten gizli kalıyordu: üyelik girişi hazır düzende de
CAPTCHA sormaz, kaydı rehberdeki üye numarası ile soyadının eşleşmesi
geçirir (`pg_member_activate()`), POST işleyicisi de CAPTCHA denetlemez.

**Ne değişti.** `render_layout()` çağrısına `'captcha_question' => ''`
eklendi; düzendeki blok eskisi gibi gizli, uyarı yok. Davranış ve HTML
değişmedi; şema, dil dosyası ve JS değişikliği yok. Hazır şablonlarda
`$captcha_question` geçen öbür düzenlerin hepsi özel form ya da kayıt
girişi sayfası; o iki ekran değişkeni her zaman veriyor (oturum açıkken
boş), aynı sorun onlarda yok.

**Doğrulandı (sandbox, MariaDB, PHP 8.3).** Eski kod (`origin/development`)
ikinci bir portta: `/member-login-register` oturumsuz ve yönetici oturumuyla
açılınca `982.php` 70'te uyarı yazıyor; yeni kodda yazmıyor. İki durumda da
HTML aynı (oturum jetonu, sayfa görüntülenme sayacı, `?v=` sürüm damgaları ve
alt bilgideki bülten formunun rastgele CAPTCHA sorusu dışında). Hazır
şablonun `$captcha_question` geçen öbür 13 sayfası (özel formlar ve
`user-login-register`) yeni kodda uyarısız açılıyor. `php -l`,
`tools/lint.php`, `tools/test.php`, `check_*` ve `build_js.php --check`
geçti. Doğrulanamadı: dev.pinegrap.com'daki gerçek sayfa.

**Bilerek yapılmayan.** Üyelik girişinin kayıt sekmesine gerçek bir CAPTCHA
eklenmedi; bu yeni bir davranış olur (alan görünür, kayıt denetlenir) ve
üye numarası zaten kaydı sınırlıyor. Kurulu sitelerdeki `982.php`
kopyalarına dokunulmadı; düzeltme ekranın kendisinde olduğu için onlar da
uyarısız açılıyor.

---

## 2026.4.10 — Dış API: kişinin kendi sözü olan uçların 403 metni ekip cihazları oturumunu söylüyor (2026-10-14)

**Neden.** #399'un bıraktığı parça. `ws_api_own_words_only()` kapısı yalnız
ekip cihazları oturumunu (`POST /auth/login`) kabul eder; uygulama anahtarı
ve OAuth bağlantısı 403 `forbidden` alır. Kapının altı reddi ise "Only the
person, signed in on a device, can …" diyordu. OAuth bağlantısı da bir
cihaz satırı olduğu (`api_devices`, `oauth_client_id` dolu) ve kişi orada
oturum açtığı için metin, bağlantıyla gelen bir istemciye neden reddedildiğini
söylemiyor, hatta cihazında oturum açmış olduğu için yetmesi gerektiğini
düşündürüyordu. API belgesi bu uçlarda zaten "Only a team devices session
(a Bearer token from POST /auth/login) may: an application key and an OAuth
connection are refused" diyor.

**Ne değişti.**
- Altı metin (kapının varsayılanı: mesaj düzeltme / silme; kendi
  görünümünden silme, zamanlama, oylama açma, oylama değiştirme, sabitleme /
  sabitlemeyi kaldırma / kapatma) "Only the person's own team devices
  session (POST /auth/login) can …; an application key or an OAuth
  connection cannot …" biçiminde. Zamanlamada: "… it is posted later in their
  own name, while an application key or an OAuth connection writes as the
  application." `tr.json`'da altı satırın anahtarı ve Türkçesi yerinde
  değişti ("yalnız kişinin kendi ekip cihazları oturumu (POST /auth/login) …;
  uygulama anahtarı ya da OAuth bağlantısı onun adına …"); eski anahtar
  kalmadı.
- Hata kodu (403 `forbidden`), cevabın yapısı ve kapının kuralı aynı.
- Test: `test_ws_api_own_words_refusals_name_the_team_devices_session`
  kapının varsayılan metnini ve `ws_api_own_words_only(lang('…'))` ile
  verilen her metni okuyup "team devices session (POST /auth/login)" ve
  "OAuth connection" geçtiğini, "signed in on a device" geçmediğini
  denetliyor; eski metinlerle kırmızı.

Şema değişikliği yok, izin yok, JS değişmedi, API belgesi değişmedi (metin
belgede örnek olarak geçmiyordu).

**Bilerek yapılmayan.** `ws_api_person_only()` kapısının metinleri
("Only the person, signed in on a device, can give this answer …" ve
okundu, oy, bildirim düzeyi, kanal sabitleme, tartışmaya katılma / ayrılma,
oylama kapatma metinleri) değişmedi: o kapı OAuth bağlantısını kabul eder,
yalnız uygulama anahtarını reddeder; "cihazında oturum açmış kişi" orada
doğru. `openapi.php`'deki Bearer şemasının "a person signed in on a device
(POST /auth/login)" açıklaması da 403 metni değil, değişmedi.

**Doğrulama.** `test.php` (tam takım), `lint`, `check_lang`,
`check_api_schema`, `check_copies`, `build_js --check` temiz. Çalışan
örnekte istekle denenmedi.

---

## 2026.4.10 — Dış API: kanal oluşturma, mesaj silme ve şablon uygulama Site Günlüğü'ne adsız yazıyordu (2026-10-14)

**Neden.** `ws_channel_create()`, `ws_message_delete()` (başkasının mesajı)
ve `ws_template_apply()` Site Günlüğü satırını panel oturumundaki
kullanıcı adıyla (`$_SESSION['sessionusername']`) yazıyor. API oturum
tutmadığı için `POST /workspace/channels`, personelin başkasının mesajını
sildiği `DELETE /workspace/messages/{id}` (ve `…/delete`) ve
`POST /workspace/channels/{id}/apply-template` satırı boş kullanıcı adıyla
yazıyordu; MCP araçları aynı işleyicilerden geçtiği için onlar da. #400
kanal grubu uçlarında bunu `ws_api_as_owner()` ile çözmüş, kanal
oluşturmayı kapsam dışı bırakmıştı.

**Ne değişti.**
- Üç işleyici panelin fonksiyonunu `ws_api_as_owner()` içinde çağırıyor:
  satır uygulama sahibinin kullanıcı adını taşıyor (ekip cihazları
  oturumu ve OAuth'ta oturum açan kişi, uygulama anahtarında uygulamanın
  sahibi; kanal grubu uçları ve `ws_change_log()` ile aynı). Satırın
  metni, sayısı ve ne zaman yazıldığı aynı; kişinin kendi mesajını
  silmesi panelde olduğu gibi satır yazmıyor.
- `ws_api_as_owner()` dosyanın başına, `ws_api_log()`'un yanına
  taşındı; davranışı aynı.
- Test: `test_ws_api_panel_log_lines_carry_the_owner`
  `includes/workspace/` altında Site Günlüğü'nü oturumdaki adla yazan
  fonksiyonları bulup `api*.php` işleyicilerinin onları yalnız
  `ws_api_as_owner()` içinde çağırdığını denetliyor (düzeltme öncesi
  kodda üç çağrı için kırmızı); `test_ws_api_calls_as_owner_scan`
  taramanın kendisini.

Şema değişikliği yok, izin yok, API belgesi, `tr.json` ve JS değişmedi.

**Bilerek yapılmayan.** Zil satırı (`notifications.user`,
`ws_bell_add()`) API'den tetiklenen bildirimlerde hâlâ boş kullanıcı adı
taşıyor; Site Günlüğü değil, ayrı iş. Panelin oturumla çalışan
fonksiyonları (Pinegrap AI'ın kanal oluşturma eylemi, misafir odası, ilk
kanal) API'den çağrılmadığı için dokunulmadı.

**Doğrulama.** `test.php` (tam takım), `lint`, `check_lang`,
`check_api_schema`, `check_copies`, `build_js --check` temiz. Sandbox'ta
yeni kod ve yanında eski `development` aynı veritabanında: uygulama
anahtarıyla kanal oluşturma ve şablon uygulama, ekip cihazları oturumuyla
başkasının mesajını silme yeni kodda `admin`, eski kodda boş kullanıcı
adıyla birer satır yazdı; kişinin kendi mesajını silmesi satır yazmadı.
OAuth bağlantısıyla denenmedi.

---

## 2026.4.10 — Katalog ve kullanıcı dizini dizi terimle 500 veriyordu, `liveform` dizi değerde formu düşürüyordu (2026-10-14)

**Neden.** #397'nin bilerek bıraktığı parçalar, hepsi 2026.4.9'da var.
(1) Katalog sayfası `?query[]=x` ya da `?<sayfa kimliği>_query[]=x` ile 500
veriyordu: hazır düzende `get_catalog.php` değeri `trim()`'liyordu, özel
düzende `liveform`'dan okunan dizi `get_catalog_search_results()` içinde
`escape()`'e gidiyordu. Aynı sayfa adresteki herhangi bir dizi parametreyle
(`?foo[]=x`, `?currency_id[]=x`) arama formunun gizli alanlarında `h()`'de,
`?previous_url_id[]=x` ile de "Geri" düğmesinin
`isset($_SESSION['software']['urls'][dizi])` denetiminde düşüyordu (oturumda
saklı bir katalog adresi varken). (2) Kullanıcı dizini
(`get_form_view_directory_screen_content.php`) `?<kimlik>_query[]=x`'i arama
alanında `h()`'ye, SQL süzgecinde `escape()`'e veriyordu. Özet bağlantılarını
kuran `build_url()` (`includes/fn/core.php`) adresteki her parametreyi
`urlencode()`'luyordu; bu yüzden dizi parametreli her adres dizini aramasız
da düşürüyordu. Aynı yardımcı form listesinin sayfa bağlantılarını, form
kaydının düzenleme bağlantısını ve kiosk adresini de kurar. (3)
`liveform::prepare()` oturumdaki değeri dizi olan metin, gizli, e-posta
alanına ve textarea'ya değeri `h()` ile yazıyordu. Özel düzende yapılmış bir
forma bir alanı `body[]=x` biçiminde gönderen ziyaretçi form sayfasını her
açılışta 500 olarak görüyordu: değer oturumda kalıyor, sayfa formu oturumdan
silen satıra varmadan düşüyordu. `output_field()` dizi değerde ilk elemanı
alır; `?ad[a]=x` tanımsız anahtar uyarısı yazıyor, `?ad[0][]=x` 500
veriyordu. (4) Hazır düzende sütun sayısı 0 olan fotoğraf galerisi
`get_photo_gallery.php`'de tanımsız `$output` uyarısı yazıyordu.

**Ne değişti.** Katalog: dizi gelen arama terimi yok sayılıyor (#384 ve
#397'nin kuralı: dizi = terim yok); sayfaya özgü terim dizi gelirse genel
`query`'ye düşülüyor. Özel düzende dizi terim, temizle düğmesi gibi alanı
boşaltıyor. İki düzenin arama formunda gizli alanlara yalnız metin
parametreler giriyor; `previous_url_id` yalnız metin gelince okunuyor.
Dizin: arama terimi bir kez okunuyor, dizi gelirse arama yok sayılıyor.
`build_url()` dizi gelen parametreyi düşürmeden köşeli parantezli biçimde
(`q%5B0%5D=x`, `http_build_query()`) taşıyor; metin parametrelerin yazımı
değişmedi. `liveform`: `prepare()` tek değer tutan bir alana (radio ve
checkbox dışındaki `<input>`, `<textarea>`) dizi değer gelince değeri
verilmemiş sayıyor, etiket şablonda yazıldığı değerle kalıyor; radio,
checkbox ve select dizi değeri eskisi gibi okuyor. `output_field()` dizi
değerin ilk elemanını eskisi gibi gösteriyor; ilk elemanı olmayan ya da yine
dizi olan değerde alanı boş yazıyor (yeni `first_value()`). Galeri:
`$output` koşulun dışında başlatıldı. Şema, dil dosyası ve JS değişikliği
yok. Testler: `tests/liveform_test.php` (üç test) ve `tests/core_test.php`'de
`build_url()` testi.

**Doğrulandı (sandbox, MariaDB, PHP 8.3).** Eski kod (`origin/development`)
ikinci bir portta, yeni kod ilkinde; aynı veritabanında 50 adres: katalog
hazır (`shop-fullwidth-2col` hazır düzene alındı) ve özel düzende arama ve
menü açıkken, kullanıcı dizini (`members-home`, özet açık), galeri hazır
düzende 0 ve 3 sütunla, form listesi ve arama sonuçları. Eski kodda 500 veren
20 adres yeni kodda 200; eskiden 200 dönen adreslerin HTML'i (jeton ve
captcha satırları dışında) aynı. Dizi parametreli sayfalar parametresiz
eşleriyle karşılaştırıldı: fark yalnız widget bağlantılarının `send_to`'sunda,
özel düzende arama alanının boş `value=""`'sunda, dizindeki özet
bağlantılarının taşıdığı parametrede ve katalogda dizi terimle `<head>`'e
RSS bağlantısının girmemesinde (`get_page_content.php` terim varsa —
`isset()` — RSS bağlantısı vermiyor; `?query=` için de aynı). Özel form
(`coming-soon`) eski kodda `body[]=x` gönderildikten sonra her açılışta 500
veriyordu, yeni kodda 200 ve textarea şablondaki hâliyle. Galeri 0 sütunla
eski kodda `php_errors.log`'a satır yazıyordu, yeni kodda yazmıyor. Yeni
testler eski kodda hata veriyor, yenide geçiyor. `php -l`, `tools/lint.php`,
`tools/test.php`, `check_*` ve `build_js.php --check` geçti. Doğrulanamadı:
dev.pinegrap.com'daki gerçek sayfalar.

**Bilerek yapılmayan.** Form listesi (blog) `?<kimlik>_query[]=x` ile
`get_form_list_view.php` 2283'te `escape()`'de 500 veriyor; aynı sayfada
metin aramayla (`?227_query=test`) tarih-saat alanı için
`includes/fn/forms.php` 3190–3206 altı tanımsız anahtar uyarısı yazıyor.
Katalog detay sayfası `?previous_url_id[]=x` ile (`get_catalog_detail.php`
906, 964, 1390) aynı `isset()` hatasını veriyor. Özel form POST işleyicisi
(`custom_form.php`) e-posta alanı dizi gelince (`325[]=x`) `preg_match()`'te
500 veriyor. `liveform::prepare()` `type` özniteliği olmayan `<input>`'ta ve
`value`'su olmayan checkbox'ta tanımsız anahtar uyarısı yazar. Katalogda
dizi terimle RSS bağlantısı yine verilmiyor.

---

## 2026.4.10 — API belgesi: oluşturma uçları 201, boş gelebilen kişiler null (2026-10-14)

**Neden.** #393'ün belge taramasının bıraktığı iki bulgu. (1) Kayıt
oluşturan uçlar `api_ok(..., 201)` ile cevap veriyor, ama OpenAPI üreticisi
(`api_openapi_responses()`) yalnız 200 ya da 204 yazabiliyordu: belge her
oluşturmada 200 vaat ediyordu ve durum koduna göre ayrışan üretilmiş
istemciler (oapi-codegen, openapi-generator'ın bazı dilleri) başarılı
cevabı beklenmedik sayıyordu. (2) Çalışma Alanı nesnelerinde kişiyi
`ws_api_person_ref()` / `ws_api_person_brief_ref()` ile veren alanlar
kimse yokken `null` dönüyor, OpenAPI'de ise boş olamaz yazıyordu.

**Ne değişti.**
- Rota tablosunda `'status'` artık 201'i ve listeyi de alıyor: işleyicisi
  201 dönen 49 rotaya (`api/schema.php` 18, `erp/api.php` 17,
  `workspace/api.php` 14, #400'ün kanal grubu oluşturması dahil)
  `'status' => 201`; var olanı geri verebilen üçüne
  (`workspace.threads.start`, `erp.waybills.invoice`,
  `erp.accounts.late_fee_invoice`) `array(201, 200)`. Uçların davranışı
  değişmedi.
- `api_openapi_success_codes()` (`openapi.php`) rotanın başarı kodlarını
  verir; `api_openapi_responses()` 201'i ("Created", kaydın şeması), 200 + 201
  rotada 200'ü ("zaten olanı geri verir") ve 204'ü yazar. Provası olan ve
  200 dönmeyen rotaya 200 olarak prova cevabı eklenir: yeni bileşen
  `DryRun` (`dry_run`, `would`, `resource`, `detail`), çünkü
  `api_dry_run_stop()` 201 / 204 yerine 200 döner. `webhooks.delete` de
  böylece 204'ün yanında prova 200'ünü gösteriyor.
- API belgesi sayfası (`api_console_endpoints_html()`) 200 dışı kodu her
  uçta yazıyor: "Answers 201 Created with the new record.", 200 + 201
  rotada ikisi, 204, provası olan uçta "A rehearsal answers 200". `tr.json`'a
  4 metin.
- `tools/check_api_schema.php` dördüncü sözleşme: 201 dönen işleyicinin
  rotası `'status'` ile 201 bildirmeli, 201 bildiren rotanın işleyicisi 201
  dönmeli (yorum ve string dışındaki `201` sayısı, token ile).
- OpenAPI: `WorkspaceDecision.sender` (`WorkspaceMessageSender?`),
  `marked_by` (`WorkspacePerson?`), `claude_change`, `file`;
  `WorkspaceChange.requested_by` / `decided_by`;
  `WorkspaceAssistantRequest.requested_by`; `WorkspaceNote.owner` /
  `updated_by` / `source`; `WorkspaceNoteVersion.saved_by` null olabilir.
  Yeni nesneler `WorkspaceDecisionChange`, `WorkspaceDecisionFile`,
  `WorkspaceNoteSource` (#393'ün biçimi: satır içi nesne nullable
  yazılamadığı için ayrı nesne + `?`). Sunucu tarafında hep `null`
  dönüyorlardı; değişen yalnız belge.

Şema değişikliği yok, izin yok, JS değişmedi, çalışan kod değişmedi (rota
tablosundaki bildirim, OpenAPI üreticisi, belge sayfası, nesne
bildirimleri).

**Bilerek yapılmayan.** Kişi alanı dışındaki bütün nesnelerin taraması
yapılmadı; aynı nesnede duran boş gelebilen iç içe nesneler (`claude_change`,
`file`, `source`) düzeltildi. 200 dönen yazma uçlarında provanın cevabı
kaydın şemasıyla aynı 200'ün altında kalıyor (önceki durum; 200'ün iki
biçimi `oneOf` ister). `WorkspaceTaskNote.author` ve
`WorkspaceClaudeRequest.requested_by` her zaman dolu döndüğü için
değişmedi; görev sahibi, plan öğesini açan gibi düz sayı kimlikler 0 döner,
`null` değil.

**Doğrulama.** Sandbox'sız: OpenAPI belgesi CLI'da modüller açık üretildi
(345 rota, 380 işlem), önce ve sonra `openapi-spec-validator` ile geçerli;
sonra 201 olan işlem 49, prova 200'ü (`DryRun`) 37 işlemde (provası olan
35 oluşturma ve `webhooks.delete`'in iki fiili), 11 nullable alan `allOf` +
`nullable`. Belge sayfası CLI'da çizildi: 49 uçta 201 satırı, `webhooks.delete`'te
204. Yeni `tests/api_openapi_status_test.php` (6 test); `lint`,
`check_lang`, `check_bindings`, `check_api_schema` (her iki yönde bilerek
bozulunca FAIL), `check_copies`, `build_js --check`, `tools/test.php`
yeşil.

---

## 2026.4.10 — Dış API: zamanlanmış mesaj yalnız ekip cihazları oturumundan, kişinin sözünü değiştiren uçlar MCP aracı değil (2026-10-14)

**Neden.** #393'ün belge taramasının bıraktığı iki kod bulgusu.
(1) `POST /workspace/channels/{id}/scheduled-messages` `ws_api_person_only()`
istiyordu; bu kapı her cihaz oturumunu, OAuth bağlantısını da kabul eder.
Zamanlanmış mesaj ise vakti gelince `ws_scheduled_message_run()` ile
uygulamasız, kişinin adıyla gönderiliyor: bir OAuth bağlantısının (Claude
gibi bir MCP istemcisinin) kurduğu mesaj sonra kişinin kendi sözü olarak
görünüyordu, oysa bağlantının şimdi yazdığı mesaj uygulama adıyla görünür
(`ws_api_writer_app_id()`). 2026.4.9'da OAuth bağlantısı olmadığı için açık
yalnız `development`'ta vardı. (2) `workspace.messages.update`, `.delete`
ve `.hide` `ws_api_own_words_only()` ile OAuth bağlantısını 403 ile
reddediyor, ama MCP aracı olarak sunuluyordu; Claude gibi bir MCP istemcisi
OAuth bağlantısıyla geldiği için araç her çağrıda 403 alıyordu. Kardeş uçlar
(sabitleme, oylama açma / değiştirme) zaten `'mcp' => false`.

**Ne değişti.**
- Zamanlama ucu `ws_api_own_words_only()` istiyor (oylama açma gibi):
  yalnız ekip cihazları oturumu; uygulama anahtarı ve OAuth bağlantısı 403
  `forbidden`, metin aynı (`tr.json` değişmedi). Rota `'mcp' => false`.
  Listeleme ve silme uçları aynı kaldı (uygulama anahtarı ve OAuth okur,
  iptal eder) ve MCP aracı olarak duruyor.
- `workspace.messages.update`, `.delete`, `.delete_post` ve `.hide`
  rotalarına `'mcp' => false`: MCP araç listesinden
  `workspace_messages_update`, `workspace_messages_delete` ve
  `workspace_messages_hide` düşüyor (bağlayıcı yeniden bağlanınca
  görünür). REST uçları ve 403 kuralı aynı.
- API belgesi ve OpenAPI: zamanlama ucunun açıklaması "yalnız ekip
  cihazları oturumu; uygulama anahtarı ve OAuth bağlantısı uygulama adıyla
  yazar ve 403 alır" diyor.
- Test: `test_ws_api_own_words_routes_are_no_tools` işleyicisi (ya da
  çağırdığı `ws_api_*` yardımcısı, ör. `ws_api_pin_target()`)
  `ws_api_own_words_only()` çağıran her rotanın MCP aracı olmadığını
  denetliyor; `test_ws_api_scheduled_message_is_scheduled_by_the_person`
  zamanlamanın kapısını ve listeleme / silmenin araç kaldığını. Önceki iki
  test (düzeltme / silme / gizleme araçtır) tersine çevrildi. Kural
  `pinegrap-dis-api` skill'ine yazıldı.

Şema değişikliği yok, izin yok, `tr.json` ve JS değişmedi.

**Bilerek yapılmayan.** Bağlantının kişi adına yazması plan kararı 11'e
(C5) bağlı; karar gelince bu üç araç ve zamanlama yeniden açılabilir.
`mcp.php` ekip cihazları jetonunu ve uygulama anahtarını da kabul ediyor;
araç listesi tek ve OAuth istemcisine göre kurulu, ekip uygulaması bu
uçları REST'ten çağırır.

**Doğrulama.** `test.php` (tam takım), `lint`, `check_lang`,
`check_api_schema`, `check_copies`, `build_js --check` temiz; yeni testler
düzeltme öncesi kodda kırmızı. Çalışan örnekte MCP istemcisiyle
denenmedi.

---

## 2026.4.10 — Dış API: kanal grubu oluşturma, değiştirme, silme ve grup yetkisi (2026-10-14)

**Neden.** #392 kanalı bir gruba taşımayı ve grupların listesini API'ye
açtı; grupların kendisini düzenlemek (oluşturma, ad, renk, yer, silme) ve
grupta yetki verip geri almak dışarıda kaldı. Bunlar panelde personelin
yaptığı işler; API'den yapılamıyordu.

**Ne değişti.**
- Uçlar, hepsi panelin fonksiyonlarıyla (`includes/workspace/groups.php`):
  - `POST /workspace/channel-groups` {`name`, `color`, `parent_id`} →
    `WorkspaceChannelGroup` (201). `ws_group_save()`.
  - `POST|PATCH /workspace/channel-groups/{id}` {`name`, `color`,
    `parent_id`, `before_id`} → `WorkspaceChannelGroupChange` {`group`,
    `memberships`}. Ad ya da renk gelirse grubun formu gibi
    `ws_group_save()` (Site Günlüğü satırı yazar); yalnız yer gelirse
    kenar çubuğundaki sürükleme / Yukarı / Aşağı gibi `ws_group_move()`
    (panel gibi satır yazmaz). `before_id` aynı seviyedeki başka bir grubun
    önüne koyar; panel yerleştiremediği `before_id`'yi sessizce sona
    atıyor, API 422 (`before_id`) veriyor.
  - `DELETE /workspace/channel-groups/{id}` (IIS için
    `POST /workspace/channel-groups/{id}/delete`) →
    `WorkspaceChannelGroupRemoval`: `ws_group_remove()`; kanallar ve alt
    gruplar bir üst gruba çıkar, gruptaki yetki ve yalnız onun verdiği
    üyelikler biter. Cevap ve prova taşınan kanalları (`channels`,
    görülmeyenler `unseen_channels`), alt grupları (`groups`), yetkisi biten
    kişileri (`access`) ve üyelik değişimini (`memberships`) verir.
  - `GET /workspace/channel-groups/{id}/access` →
    `WorkspaceChannelGroupAccess`: panelin "Grup yetkisi" penceresi
    (`people`, üst gruplardan gelen `above`, `reach`).
  - `POST /workspace/channel-groups/{id}/access` {`user_ids`} (yetki ver,
    `ws_group_access_grant()`) ve `DELETE|POST
    /workspace/channel-groups/{id}/access/{user_id}` (geri al,
    `ws_group_access_revoke()`) → `WorkspaceChannelGroupAccessChange`
    {`access`, `memberships`}. Ekipte olmayan kimlikler düşer, kimse
    kalmazsa 422 (`user_ids`). Yetkisi olmayan kişiden geri alma hata
    değildir ve hiçbir şey yazmaz (panel bu düğmeyi yalnız yetkisi olana
    gösteriyor; panelin fonksiyonu yine de günlüğe satır yazardı).
- Kapı #392 ile aynı: `workspace:read` / `workspace:write`, uygulama
  sahibinin hakları (`ws_can_manage_groups()`: rol 0-2); uygulama anahtarı,
  ekip cihazları oturumu ve OAuth kabul, personel olmayan 403. Olmayan grup
  (yolda) 404, gövdedeki hatalar 422 ve alan adıyla, grup tabloları yoksa
  503. Yazan uçların hepsi prova alır.
- Üyeliği değiştiren yazımlar (başka gruba taşıma, silme, yetki verme /
  geri alma) kimin hangi kanala girip çıktığını hem cevapta hem provada
  verir (`WorkspaceChannelGroupMemberships`: `joining` / `leaving` sayıları,
  kanal kanal adlar, sahibin okumadığı kanallar yalnız `unseen_channels`'ta
  sayı; panel de bu kanalları yalnız sayıyor). Prova
  `ws_group_sync_plan()` ile hesaplanır: `ws_group_sync_channel()`'ın
  hesabı yazmadan, kanalların grubu ve yetki satırları değişmiş sayılarak
  (`ws_group_move_plan()`, `ws_group_remove_plan()`,
  `ws_group_access_plan()`). Cevap tahminden değil eşitlemenin gerçekten
  yaptığından gelir: `ws_group_save()`, `ws_group_move()`,
  `ws_group_remove()`, `ws_group_access_grant()` ve
  `ws_group_access_revoke()` artık `synced` (kanal → eklenen / çıkarılan)
  döner, `ws_group_sync_tree()` `channels` ekledi. Panel bu alanları
  kullanmıyor.
- `ws_group_sync_channel()` ve `ws_channel_move_group()`'un ortak hesabı
  saf iki fonksiyona çıktı (`ws_group_wanted_from()`,
  `ws_group_sync_split()`); davranış aynı.
- Renk, gruptaki `color` gibi paletin renk kodudur (`ws_palette_hexes()`,
  `ws_palette_place_of()`); `null` ya da boş renksiz. OpenAPI ve MCP
  null alabilen bir seçim listesinin listesine `null`'u da ekliyor
  (yoksa sıkı bir doğrulayıcı temizlemeyi reddeder).
- Panelin Site Günlüğü satırları (`… kanal grubu (…) kaydedildi`,
  `… kaldırıldı`, `… yetki verildi / alındı`) API'de de yazılır ve uygulama
  sahibinin kullanıcı adını taşır (`ws_api_as_owner()`: API oturum
  tutmadığı için panelin fonksiyonu adı oturumdan okuyamıyordu).

Şema değişikliği yok, yeni izin kapsamı yok, `tr.json`'da 2 yeni metin, JS
değişmedi. MCP: kanal oluşturma ve değiştirme araç olduğu için altı yeni uç
da araç (`workspace_channel_groups_create`, `_update`, `_delete`,
`_access`, `_access_grant`, `_access_revoke`); `…/delete` takma adı araç
değil.

**Bilerek yapılmayan.** Kanal grubu olay (webhook) duyurmuyor: panel de
duyurmuyor. `GET /workspace/channel-groups/{id}/access` provası yok, okuma.
Grup yetkisi verme cevabı yeni yetki alanları ayrıca listelemiyor;
`access.people` yetkisi olanların tamamı.

**Doğrulama.** `tests/workspace_api_test.php`: altı yazma ucu ve okuma
(yöntem, yol, kapsam #392 ile aynı, cevap nesnesi, prova, PATCH / POST
takma adları, MCP'de altı araç, `delete_post` araç değil, renk seçimi
paletle aynı), `ws_group_wanted_from()` (en yakın grup, ekip dışı
sayılmaz), `ws_group_sync_split()` (kendi katılan ve sahip kalır),
`ws_group_chain_moved()` / `ws_group_chain_removed()`,
`ws_palette_place_of()`, üyelik ve silme sunumları (görülmeyen kanal
sayılır, alanlar şemayla aynı sırada). `tests/mcp_test.php`: null alabilen
seçim listesi `null` içerir. Sandbox'ta uygulama anahtarıyla: oluşturma
(prova yazmadı, 201, iç içe yol "Müşteriler › İstanbul", 5. seviye 422,
bilinmeyen renk / boş ad / olmayan üst grup / fazladan alan 422, personel
olmayan 403); yetki verme provası ve gerçeği (görünen kanalda kişi adıyla,
yöneticinin içinde olmadığı özel kanal `unseen_channels: 1`, `via_group`
yazıldı, kanala satır düştü, günlükte `admin`); alt grubun erişimi
(`above`'da üst gruptaki kişi), personel olmayan 403, olmayan grup 404;
grubu başka gruba taşıma provası ve gerçeği (iki üyelik bitti, günlüğe
satır yok), kendi içine 422, yanlış `before_id` 422, `before_id` ile sıra,
aynı `parent_id` sırayı bozmadı, ad + renk günlüğe yazdı, ad + geri taşıma
üyelikleri geri getirdi; yetkisi olmayandan geri alma (yazmadı, günlük
satırı yok), prova ve `POST` ile geri alma; silme provası (yazmadı) ve
gerçeği (kanal ve alt grup üste çıktı, yetki satırı silindi, `via_group`
üyelikleri bitti), `…/delete` ile silme, ikinci silme 404; MCP
`tools/list`'te altı araç ve renk listesinde `null`, `tools/call` ile
değiştirme provası; OpenAPI'de beş yol ve
beş nesne; API belgesinde uçlar. Sunucu günlüğünde uyarı yok. `lint`,
`check_lang`, `check_api_schema`, `check_bindings`, `test`, `check_copies`,
`build_js --check` temiz. Panelin kendi ekranı sandbox'ta tıklanmadı
(fonksiyonların panel cevabı değişmedi; yalnız yeni anahtarlar eklendi).

---

## 2026.4.10 — Dış API: "Benden sil" kişinin öbür cihazlarına eşitleniyor (2026-10-14)

**Neden.** #393'ün belge taramasının bıraktığı bulgu. Kişi bir mesajı
kendinden gizleyince (`POST /workspace/messages/{id}/hide`, #375; panelde
"Benden sil") yalnız `ws_message_hides`'a satır yazılıyordu. Kanal
mesajlarının değişenleri (`GET /workspace/channels/{id}/messages?changed_since=`,
#371) ve eşitlemenin `changed` listesi (`GET /workspace/sync`, #374) mesajın
`touched_at`'ine bakar; gizleme onu ilerletmediği için kişinin öbür cihazı
gizlemeyi ancak kanalı baştan okuyunca öğreniyordu. Panelde açık duran öbür
ekran da (başka bilgisayar, başka sekme) mesajı sayfa yenilenene kadar
gösteriyordu.

**Ne değişti.**
- `ws_messages_touched()` (`messages.php`) isteğe bağlı okuyucu alır
  (`$user_id`); okuyucu verilince `touched_at` aralığına okuyucunun o andan
  beri gizlediği mesajlar da eklenir. Gizleme anı zaten vardı:
  `ws_message_hides.hidden_at` (2026.4.5, 5.80). Yeni yardımcılar:
  `ws_message_hidden_since()` (kişinin o kanalda o andan beri gizlediği
  mesaj kimlikleri, en düşük `$limit` tanesi) ve
  `ws_messages_touched_where()` (koşul; kimlik yoksa eskisi gibi yalnız
  `touched_at` aralığı, `idx_touched` cevaplar). `ws_channel_news()`
  okuyucuyu `user_id` seçeneğiyle geçirir.
- Kişi bazlı, #390'a simetrik: #390 gelen kutusunda kişinin kendi satırının
  damgasını (`read_at`) ilerletti, çünkü satır tek kişinindir. Mesaj ise
  herkesindir; `touched_at`'i ilerletmek kanaldaki herkesin cihazına boşuna
  "değişti" gönderirdi. Bu yüzden damga kişinin kendi gizleme kaydıdır ve
  değişen mesaj yalnız ona döner. İşaret yeni değil: API'de mesajın
  `hidden` alanı (#375) `true` gelir, mesaj olduğu gibi (silinmiş olarak
  değil); uygulama onu ekrandan kaldırır, `since_id` / `before_id` saymaya
  devam eder.
- API: `GET /workspace/channels/{id}/messages?changed_since=` ve
  `GET /workspace/sync`'in `changed`'i okuyucuyu (`ws_api_viewer()`)
  geçirir. Uygulama anahtarı sahibinin gizlediklerini görür (okumada
  `hidden` da sahibe göre).
- Panel: `ws_sync` okuyucuyu geçirir; `ws_message_payloads()`'a üçüncü
  parametre (`hidden_as_deleted`): ekranda zaten duran mesajların
  değişenlerinde gizlenen mesaj atlanmak yerine silinmiş olarak çizilir, JS
  onu herkesten silinen mesaj gibi kaldırır (JS değişmedi).
- Belge: liste, eşitleme ve gizleme uçlarının açıklamaları (API belgesi,
  OpenAPI, MCP araç açıklamaları).

Şema adımı yok (`hidden_at` 2026.4.5'ten beri yazılıyor), yeni izin kapsamı
yok, yeni metin yok, JS değişmedi. Gizlemeyi geri alma yok (ne panelde ne
API'de), bu yüzden geri almanın eşitlenmesi de yok.

**Bilerek yapılmayan.** `ws_message_hides`'da yalnız `user_id`'yle başlayan
indeks var; kişinin gizleme sayısı az olduğu için `hidden_at` taraması bu
indeksle yeterli, şema adımı açılmadı. Eski bir anla sorulursa gizlemeler
yine gelir (kayıt silinmez); süre sınırı yok.

**Doğrulama.** `tests/workspace_api_test.php`'ye üç test (koşul metni,
okuyucusuz/ansız çağrının veritabanına gitmemesi, üç uç açıklaması).
Sandbox'ta "Ekip cihazları" ile aynı kişinin iki cihazı ve
ikinci bir kişi: birinci cihaz iki mesajı gizleyince ikinci cihazın
`changed_since`'i ikisini `hidden: true` getirdi (`limit=1` + `since_id`
sırayla), eşitlemenin `changed`'i de; sonraki eşitleme tekrarlamadı. İkinci
kişinin `changed_since`'i boş, eşitlemesi aynı imleçle aynı cevap ve
`If-None-Match` ile 304. Panelde (Playwright, iki tarayıcı) yöneticinin
açık kanal ekranı telefondan gizlenen mesajı yaklaşık 3 saniyede kaldırdı,
yeniden yüklemede de yok; ikinci kişinin açık ekranında mesaj duruyor.
Tarayıcı konsolunda ve sunucu günlüğünde hata yok.

---

## 2026.4.10 — Arama sayfası `?query[]=x` ile 500 veriyordu, galeri kanonik adresi klasör numarasını olduğu gibi yazıyordu (2026-10-14)

**Neden.** #391'in bilerek bıraktığı iki parça, ikisi de 2026.4.9'da var.
(1) Arama sonuçları sayfası arama terimi dizi olarak gelince (`?query[]=x`,
`?<sayfa kimliği>_query[]=x`) 500 veriyordu: kanonik adresi kuran
`get_page_content.php` `switch`'i ve `get_search_results.php` aynı değeri
`trim()`'liyordu. İkisi aşılsa da sayfa düşüyordu: `get_search_results.php`
formun gizli alanlarını adresteki her parametreden `h()` ile kuruyor, özel
düzende arama alanının değerini de `liveform` `h()`'den geçiriyordu; adreste
herhangi bir dizi parametre (`?foo[]=x`) aynı hatayı veriyordu. (2) Fotoğraf
galerisi dalı `folder_id`'yi denetimsiz ve `urlencode()`'suz yazıyordu:
`?folder_id=` boşken kanonik adres `?folder_id=` ile, `?folder_id=189%20`'de
boşlukla, `0189` ve `189.0`'da olduğu gibi bitiyordu (galeri bunları 189
diye açıyor); `?folder_id[]=x` "Array" yazıp uyarı düşürüyordu. Dizi ya da
kapsam dışı bir klasör numarası galeride "Erişim reddedildi" verir; bu yolda
`log_activity()` oturumsuz ziyaretçide tanımsız `sessionusername` okuyup bir
uyarı daha yazıyordu.

**Ne değişti.** Arama: dizi gelen arama terimi yok sayılıyor (#384'ün `?q[]=`
için seçtiği gibi: dizi = terim yok); sayfaya özgü terim dizi gelirse genel
`query`'ye düşülüyor, kanonik adres de aynı kuralla kuruluyor. Gizli alanlara
yalnız metin parametreler giriyor; özel düzende arama alanı dizi değerde boş
kalıyor. Gelişmiş aramanın hazır (sistem) düzeninde terimsiz açılış
`$output_featured_items` / `$output_catalog_items` tanımsız diye iki uyarı
yazıyordu; ikisi koşulun dışında başlatıldı. Galeri: kanonik adrese yalnız
pozitif tam sayı (`FILTER_VALIDATE_INT`) yazılıyor, `(int)` ile
normalleştirilerek (`189 ` → `189`); başka her şey yok sayılıyor, adres
yalın kalıyor. Galerideki iki `log_activity()` çağrısı `?? ''` aldı.
Galerinin hangi klasörü açtığı ve "Erişim reddedildi" davranışı değişmedi.
`switch`'in öbür dalları değişmedi. Şema, dil dosyası ve JS değişikliği yok.

**Doğrulandı (sandbox, MariaDB, PHP 8.3).** Eski kod (`origin/development`)
ikinci bir portta, yeni kod ilkinde; aynı veritabanında 17 arama ve 17 galeri
adresi bileşimi, arama sayfası özel ve hazır düzende, gelişmiş ve basit
aramayla, galeri özel ve hazır düzende açıldı. Eski kodda 500 veren 9 arama
adresi artık 200; eskiden çalışan arama adreslerinin HTML'i (jeton ve captcha
satırları dışında) aynı. `?query[]=x` sayfası `/site-search` ile,
`?216_query[]=x&query=test` sayfası `?query=test` ile aynı (yalnız `send_to`
ve arama alanındaki boş `value=""` farklı). Galeride kanonik adres yalnız boş, sayı olmayan ve dizi değerlerde
değişti; `?folder_id=189` ve sayfanın kendi klasörü aynı. Özel düzende eski
kod bu adreslerin 16'sında `php_errors.log`'a satır yazıyordu, yeni kod
hiçbirinde yazmıyor; hazır düzende kalan tek satır aşağıdaki galeri uyarısı.
`php -l`, `tools/lint.php`, `tools/test.php`, `check_*` ve
`build_js.php --check` geçti. Doğrulanamadı: dev.pinegrap.com'daki gerçek
sayfalar.

**Bilerek yapılmayan.** Katalog sayfası (`get_catalog.php` 67–72) aynı
`?query[]=x` değerini `trim()`'liyor; kullanıcı dizini
(`get_form_view_directory_screen_content.php` 424) `?<kimlik>_query[]=x`'i
`h()`'ye veriyor. `liveform::prepare()` metin alanına dizi değer gelince her
formda `h()` hatası verir; burada yalnız arama alanı için önlendi. Hazır
düzende sütun sayısı 0 olan galeri `get_photo_gallery.php` 662'de tanımsız
`$output` uyarısı yazıyor. Dördü yukarıdaki bölümde giderildi.

---

## 2026.4.10 — Dış API: kanala geri alınan kişinin gelen kutusu satırları değişenlerde yeniden geliyor (2026-10-14)

**Neden.** #390'ın bilerek bıraktığı parça. Kişi özel kanaldan çıkınca o
kanalla ilgili satırları `changed_since`'te `deleted: true` ile geliyor,
uygulama onları bırakıyordu. Kişi kanala geri alınınca satırlar panelde ve
ilk sayfada yeniden görünüyor ama damgaları kıpırdamadığı için değişenlerde
gelmiyordu; uygulama onları ancak gelen kutusunu baştan okuyunca
görüyordu.

**Ne değişti.**
- `ws_inbox_reach_regained($channel, $user_ids = null)` (`notify.php`),
  `ws_inbox_reach_lost()`'un simetriği: kanalı artık okuyan kişinin o
  kanalla, okuduğu tartışmalarıyla (hâlâ üyesi olduğu) ve kanalın yalnız
  kanal sayesinde gördüğü görevleriyle ilgili **okunmuş** satırlarının
  `read_at`'ini şimdiye çeker. `changed_since` onları damgasıyla bulur ve
  bu kez görünür oldukları için canlı satır olarak verir; eşitlemenin
  `inbox_changed_at`'i (#380) ilerler. Satırlar okunmuş kalır: zil sayısı
  ve `inbox_unread` değişmez. Okunmamış satıra dokunulmaz: erişim
  dışındayken yazılmış bir satır zaten `created_at`'iyle gelir (görülmeden
  okunduysa `ws_inbox_visible_rows()` onu okunmuş saymıştır, `read_at`'i
  ilerler).
- Ayrı bir sütun ya da kişi-kanal izi yerine #390'ın yolu seçildi:
  kaybedilen satırlar zaten o yolla (`read_at`) işaretleniyor, değişenlerin
  koşulu (`created_at >= T OR read_at >= T`) ve dizini aynı kalır, şema
  adımı gerekmez.
- Görev satırları: kişi görevin sahibi ya da atananı değilse
  (`ws_inbox_task_through_channel()`) ve satır kurala göre görünürse
  geri gelir; kendi görevlerinin satırları kayıpta da kıpırdamamıştı,
  yeniden gönderilmez. Kişi başına en yeni 500 satır
  (`WS_INBOX_REACH_TASK_READ`, kayıptakiyle aynı sınır).
- Çağıran yerler, yalnız erişimi gerçekten geri gelenler için (kanalı
  baştan beri okuyanın her satırı boşuna yeniden gitmesin):
  `ws_channel_add_members()` herkese açık olmayan kanalda (özel kanala
  yeniden ekleme, grup erişiminin geri getirmesi `ws_group_sync_channel()`
  bu yoldan geçer, misafir odası, tartışmaya ekleme);
  `ws_channel_make_public()` (gelen kutusunda o kanal ya da tartışmaları
  hakkında satırı olup kanalda olmayan herkes; seyrek adım, tablo bir kez
  taranır); `ws_thread_join()` (tartışmaya yeniden katılan).
  `ws_channel_join()` yalnız herkese açık kanala katılır, kimse erişim
  kazanmaz, çağırmaz.
- `GET /workspace/inbox` açıklamasına (API belgesi ve `?openapi=1`) "geri
  alınınca o satırlar o andan itibaren değişenlerde olduğu gibi ve okunmuş
  gelir, yeniden tutun" cümlesi; `GET /workspace/sync`'in
  `inbox_changed_at` tarifine "ya da geri verildi". Beceri dosyası
  (`.claude/skills/pinegrap-dis-api/SKILL.md`) erişime geri alan yeni
  kodun bu fonksiyonu çağırmasını söyler.

Şema adımı yok, yeni izin kapsamı yok, yeni metin yok, JS değişmedi; cihaz
oturumu ve izin kuralları aynı.

**Bilerek yapılmayan.** Kişi ekibe yeniden alınırsa (rolü, "Çalışma Alanı"
izni ya da profilde ekipten çıkarılması geri alınırsa) satırlar
değişenlere girmez; #390 da kaybı yalnız kanal adımlarına bağlamıştı.
Yöneticinin özel kanalı inceleme için açması oturuma bağlıdır, satırları
kıpırdatmaz.

**Doğrulama.** `tests/workspace_api_test.php`'ye bir test
(`ws_inbox_task_through_channel()`: sahibi, atananı, kanalsız görev).
Sandbox'ta CLI ile gerçek fonksiyonlar: özel kanalda kişinin dört satırı
(davet, tartışma, anılma, sahibi olmadığı görevin notu) çıkarılınca
`deleted`, yeniden eklenince dördü canlı geldi (yanında yeni davet satırı),
kendisine atanan görevin iki satırı ikisinde de gelmedi, `inbox_unread`
yalnız yeni davet kadar arttı. Herkese açık kanal özel yapılınca dışarıda
kalanın satırı `deleted`, yeniden açılınca canlı geldi; baştan beri üye
olanın satırının `read_at`'i değişmedi. Tartışmadan ayrılıp yeniden
katılan yöneticinin iki satırı önce `deleted`, sonra canlı geldi. Grup
erişimi bitip (`ws_group_sync_channel()` çıkardı) geri verilince kişinin
iki satırı önce `deleted`, sonra canlı geldi. Herkese açık kanala katılmak
hiçbir satırı kıpırdatmadı. Aynı senaryo
değişiklik öncesi `development`'ta: geri alma sonrası yalnız yeni davet
satırı, kanal açılınca ve tartışmaya yeniden katılınca hiçbir satır.

---

## 2026.4.10 — Site sayfaları: beş ekranda daha PHP uyarıları, dizi parametresiyle 500 (2026-10-14)

**Neden.** #389 kendi altı dosyasını temizlerken taramanın bulduğu beş
ekranı kapsam dışında bıraktı: ortaklık başvuru formu, Hesabım profil,
e-posta tercihleri, özel form ve oturumsuz açılan çıkış sayfası. Bu
ekranlar sandbox'ta eski kodla (oturumsuz, yönetici, düzenleme kipinde
yönetici; 14 özel form sayfası, her biri ek parametrelerle, toplam 410
istek) yeniden üretildi: bu dosyalarda 43 farklı satırda 281 uyarı ve
107 istekte HTTP 500. Hepsi 2026.4.9'da da var.

Görünür hatalar (500):

- Özel form sayfası adreste bir değer dizi olarak gelince
  (`?connect_to_contact[]=x`, `?add_watcher[]=x`, `?value_<alan>[]=x`)
  `mb_strtolower()`, `trim()` ya da `h()` diziyi alamadı (`TypeError`).
  Sistem düzenli formda aynı iki değer `get_form_info()`'da
  (`includes/fn/content.php` 354, 569) okunuyor, orada da düzeltildi.
- E-posta tercihleri sayfası `?id[]=x` ya da `?sig[]=x` ile:
  oturumsuz ziyaretçide `trim()`, oturum açmış kişide gizli alanlardaki
  `h()` 500 verdi.

Görünmeyen uyarılar (çıktı aynı):

- `get_affiliate_sign_up_form_screen_content.php` 60–73,
  `get_my_account_profile.php` 57–74, `get_email_preferences.php` 47:
  rehber kaydı olmayan kullanıcı (panelde açılan yönetici) için kişi
  satırı yok, `$row` null. Ortaklık formunda ayrıca `$output .=` ilk
  atamaydı (230) ve şartlar sayfası seçilmemişse `$output_terms` hiç
  kurulmuyordu.
- `get_custom_form_screen_content.php` 47: "Sonrası için Kaydet" açık
  formda `?<sayfa>_save_confirmation` olmadan her açılış; 177 ve 676:
  `?add_watcher=` verilip `add_watcher_page_id` verilmeyince.
- `includes/fn/auth.php` 3233 (`get_token_field()`): oturum açmamış
  ziyaretçi çıkış sayfasını açınca `logout()` oturumu bitirip "Giriş
  yapmadın." hata sayfasını basıyor; o sayfadaki formlar artık
  oturumda olmayan jetonu okuyordu.

**Ne değişti.** Değerler kullanılmadan önce kuruluyor; uyarı
susturulmadı (`@` ya da hata düzeyi yok).
- Özel formda `connect_to_contact`, `add_watcher`, `add_watcher_page_id`
  fonksiyonun başında bir kez, yalnız metinse okunuyor; dizi verilmemiş
  sayılıyor. `value_<alan>` ve `get_form_info()`'daki iki okuma da yalnız
  metni alıyor. `_save_confirmation` `?? ''` ile okunuyor.
- E-posta tercihleri `id` ve `sig`'i bir kez, yalnız metinse okuyor.
  Dizi gelince oturum açmış kişiye sayfa açılıyor; oturumsuz ziyaretçi
  imzasız bağlantı gibi giriş sayfasına gidiyor (önceden 500; `id`
  geçersizken `sig[]` verilince "kimlik geçerli değil" hatası
  çıkıyordu, artık giriş sayfası).
- Kişi satırı yoksa profil ve ortaklık formunda okunan sütunlar null
  başlıyor (eski çıktıyla birebir aynı kalsın diye boş metin değil:
  boş metinde liste kutuları `value=""` özniteliğini kaybediyordu).
  E-posta tercihlerinde abonelik kutusu işaretsiz başlıyor.
- `get_token_field()` jeton yoksa boş değer yazıyor, yanındaki
  `get_token_query_string_field()` gibi.
Şema, dil dosyası ve JS değişikliği yok.

**Bilerek yapılmayan.** Bu sayfaların formlarını işleyen POST betikleri
(`affiliate_sign_up_form.php`, `my_account_profile.php`,
`email_preferences.php`, `custom_form.php`) taranmadı. Aynı `value_<alan>[]=`
okuması `includes/fn/custom_form.php`'de iki yerde daha var: görsel
tasarımcının özel form widget'ı (`pg_cf_field_initial_value()`, 1123;
`(string)` ile okuyor, 500 değil "Array" ve uyarı) ve hızlı sipariş
(`prepare_custom_form()`, 2330; `trim()` dizide 500). Ayrıca üye
giriş/kayıt sayfasının şablondan gelen düzeni (`data/layouts/982.php` 70,
`$captcha_question`) oturumsuz her açılışta uyarı yazıyor. Ayrı işler.

**Doğrulandı (sandbox, MariaDB, PHP 8.3).** Eski kod `origin/development`
worktree'sinden ikinci portta sunuldu, aynı 410 istek iki sunucuda
koşturuldu: yeni kodda bu dosyalardan hiç uyarı ve hiç 500 yok (kalan
tek satır yukarıdaki `982.php`). HTML karşılaştırması (jeton, CAPTCHA,
port, görüntülenme sayacı ve oturum listesi normalize): 500'den
düzelenler ve `?id=x&sig[]=y` dışında bütün sayfalar aynı. Özel formda
`value_<alan>=` ile açılan alanın öznitelik sırası yalnız tarama
sırasında farklı çıktı; sebebi eski kodda bir önceki isteğin 500 verip
form oturumunu temizlememesi, temiz oturumla iki kod aynı. `tools/test.php`,
lint, dil, kopya denetimleri ve `build_js.php --check` geçti.
Doğrulanamadı: dev.pinegrap.com'daki gerçek sayfalar.

---

## 2026.4.10 — API belgesi: mobil uçların belgesi koda uyduruldu (2026-10-14)

**Neden.** #369–#387 ile gelen Çalışma Alanı mobil uçları (kişinin adıyla
yazma, düzeltme / silme / gizleme, okundu, `changed_since`, eşitleme, gelen
kutusu, dosya, bildirim düzeyi, oylama, tartışma, sabitleme, kanal sırası)
kodla, API belgesiyle (`api_docs.php`, `integration.php/docs`) ve OpenAPI
çıktısıyla karşılaştırıldı. Uçların hepsi iki belgede de var; dry_run
bayrağı her uçta işleyicinin duruşuyla eşleşiyor; 304 / 206 / 416 / 409 /
422 cevapları yazılı. Farklar belgede ve OpenAPI üreticisindeydi.

**Ne değişti.**
- API belgesi her uçta prova edilip edilemeyeceğini yazıyor
  (`api_console_endpoints_html()`, rota `dry_run` taşıyorsa): önceden yalnız
  sayfa başındaki genel cümle vardı, hangi yazmanın `X-Dry-Run` aldığı,
  hangisinin 400 `dry_run_unsupported` döndüğü okunamıyordu. Hata
  kataloğunda `dry_run_unsupported` `dry_run` sorgusunu da anıyor.
- OpenAPI: `'nullable' => true` gövde alanı `nullable: true` taşıyor
  (yönlendirici ve MCP aracı JSON `null`'ı zaten temizleme diye alıyordu;
  ör. `PATCH /workspace/polls/{id}` `closes_at: null`); API genelinde her nullable gövde alanı.
- OpenAPI: boş olabilen iç içe nesneler ayrı nesne oldu ve `?` ile yazıldı
  (`WorkspaceMessage.sender` / `file` / `thread`, `WorkspaceThread.started_by`
  / `closed_by`, `WorkspaceSyncPin.pinned_by`, `WorkspaceApproval.record` /
  `requested_by` / `closed_by`): yeni `WorkspacePerson`,
  `WorkspaceMessageSender`, `WorkspaceMessageFile`, `WorkspaceMessageThread`,
  `WorkspaceApprovalRecord`. Üretilen istemci dosyasız mesajda düşüyordu.
- 403 kuralı: `ws_api_person_only()` isteyen on uç (gelen kutusu okundu,
  kanal okundu, bildirim düzeyi, kanal sırası, tartışmaya katılma / ayrılma,
  okundu onayı, oy, oylamayı kapatma, onay kararı) "yalnız POST /auth/login
  jetonu" diyordu; OAuth bağlantısı da kabul ediliyor, açıklama bunu
  söylüyor. Tepki, onay isteği, görev notu, görev kartı ve şablonun
  sabitlenen mesajı OAuth bağlantısıyla uygulama adıyla yazılır; "kendi
  cihazında oturum açmış kişi" yerine ekip cihazları oturumu yazıldı.
- Kanal mesajları açıklaması `file` (ad, `kind`, `size`, `url`, `api_url`) ve
  oylamanın `can_vote` / `can_edit` / `can_close` alanlarını anıyor; dosya
  yükleme açıklaması aynı `Idempotency-Key` başka dosyayla 409
  `idempotency_key_reused` alır diyor; eşitlemenin zil sayısı OAuth
  bağlantısının iznini de anıyor.

Şema değişikliği yok, izin yok, `tr.json`'da 1 yeni metin, JS değişmedi,
çalışan kod değişmedi (yalnız açıklama, nesne bildirimi ve üretici).

**Bilerek yapılmayan (koda dokunuyor, ayrı iş).** Zamanlanmış mesaj OAuth
bağlantısıyla kurulabiliyor ama sonra kişinin adıyla gidiyor; mesaj
düzeltme / silme / gizleme MCP aracı olarak sunuluyor ama her OAuth
bağlantısını ve uygulama anahtarını 403 ile reddediyor (sabitleme ve
oylama açma `'mcp' => false`); mesaj yazma, dosya, tepki, okundu onayı, onay
kararı ve görev notu uçlarında `dry_run` yok; gizleme `touched_at`'i
ilerletmediği için kişinin öbür cihazı `changed_since` / eşitlemeden
öğrenmiyor. API genelinde 201 dönen oluşturma uçları OpenAPI'de 200
yazıyor; Çalışma Alanı'nın öbür nesnelerinde de boş olabilen kişi alanları
var.

**Doğrulama.** Sandbox'ta `integration.php/openapi.json` ve
`/docs/endpoints` önce ve sonra üretildi: iki belge de
`openapi-spec-validator` ile geçerli, eksik `$ref` yok; prova satırı her
`dry_run` rotasında. `lint`, `check_lang`, `check_api_schema`,
`build_js --check` temiz.

---

## 2026.4.10 — Dış API: kanalı bir kanal grubuna taşıma (2026-10-14)

**Neden.** #387 kanalın kişisel tercihlerini (sabitleme, sıra) API'ye
açarken kanalın grubunu bilerek dışarıda bıraktı: gruplar çalışma
alanınındır, herkes için aynıdır, yalnız personel düzenler ve bir kanalı
gruba taşımak o grupta erişimi olanları kanala katar. Bu bir personel
işidir; API'de yoktu, kanal nesnesi de kanalın hangi grupta olduğunu
söylemiyordu.

**Ne değişti.**
- `POST /workspace/channels/{id}/group` {`group_id`}: kanalı bir gruba
  taşır; `0` ya da `null` gruptan çıkarır. Panelin kodunu kullanır
  (`ws_channel_move_group()`): aynı yetki (`ws_can_move_channel_group()`:
  kanalı değiştirebilen personel, özel kanalda yalnız içindeyken; diğerleri
  403), aynı üyelik eşitlemesi ve kanala düşen aynı satır ("… grubundaki
  yetki … kişisini bu kanala ekledi" / "… bu kanaldan çıktı"). Kapı kanal
  değiştirme ucuyla (`POST /workspace/channels/{id}`) aynı: `workspace:write`,
  uygulama sahibinin hakları; uygulama anahtarı, ekip cihazları oturumu ve
  OAuth bağlantısı kabul. Tartışma ve misafirli oda 422, olmayan grup 422
  (`group_id`), grup tabloları yoksa 503.
- Cevap `WorkspaceChannelGroupMove`: `group_id`, `group_path`
  ("Müşteriler › İstanbul"), `previous_group_id`, `changes`, `joining` ve
  `leaving` (`user_id` + `name`) ve kanalın son hali (`channel`). Prova
  (`X-Dry-Run` / `?dry_run`) aynı alanları `detail`'de verir (kanal hariç);
  panelin taşımadan önce sorduğu soru budur. Kanalı zaten bulunduğu gruba
  taşımak hiçbir şey yapmaz (`changes: false`, listeler boş; panel bu durumda
  istek atmıyor).
- `ws_channel_move_group()` ad listelerine ek olarak `joining_ids` ve
  `leaving_ids` döner; gerçek taşımada listeler tahminden değil
  `ws_group_sync_channel()`'ın gerçekten eklediği ve çıkardığı kişilerden
  gelir. Panel gerçek taşımanın cevabını kullanmıyor, önizleme aynı.
- `GET /workspace/channel-groups`: `ws_groups_for()`'un listesi (`id`,
  `name`, `parent_id`, `path`, `color`, `access`, `mine`). Personel bütün
  grupları ve `access`'te grubun kendisinde erişimi olan kişi sayısını
  görür; diğerleri yalnız gördükleri bir kanalı tutan grupları ve
  üstlerini, `access` onlara `null`.
- `WorkspaceChannel`'a `group_id` eklendi (gruptaysa grup, değilse `null`);
  liste ve eşitleme taşır. Eşitlemenin kanal işareti sunulan listenin
  `crc32`'si olduğu için uygulamalar listeyi bir kez yeniden alır.

Şema değişikliği yok (grup tabloları 2026.4.5'ten beri var), yeni izin
kapsamı yok (`workspace:read` / `workspace:write`), `tr.json`'da 1 yeni
metin, JS değişmedi. MCP: kanal oluşturma ve değiştirme MCP aracı olduğu
için iki uç da araç olarak sunulur (`workspace_channels_group`,
`workspace_channel_groups_list`); taşıma aracı provayı (`dry_run`) alır.
Site Günlüğü'ne satır yazılmaz: panel de kanalı gruba taşımayı günlüğe
yazmıyor (grup kaydetme, silme ve grup yetkisi verme / geri alma yazıyor);
iz kanaldaki satırdır.

**Bilerek yapılmayan.** Grup oluşturma, adlandırma, taşıma, silme ve grup
yetkisi verme / geri alma API'de yok; istenirse ayrı bir iş.

**Doğrulama.** `tests/workspace_api_test.php`: iki rota (yöntem, yol,
kapsam kanal değiştirmeyle aynı, cevap, prova, MCP'de kanal değiştirme
gibi, `group_id` zorunlu ve `null` alabilir, en az 0), grup sunumu
(personel olmayana `access` `null`, üst grup ve renk yoksa `null`) ve
taşıma sunumu (yerinde taşıma kimseyi saymaz, provada `channel` yok,
alanlar şemayla aynı sırada). Sandbox'ta: personel (rol 2) cihaz
oturumuyla herkese açık kanalı alt gruba taşıma provası (üst gruptaki
yetkiyle iki kişi `joining`, veritabanı değişmedi), gerçek taşıma (iki kişi
`via_group` ile üye oldu, kanala satır düştü), aynı gruba yeniden
(`changes: false`), özel kanalı başka gruba (yetkili yönetici `joining`),
`null` ve `""` ile gruptan çıkarma (iki kişi `leaving`, çıktı satırı);
personel olmayan kişinin cihazı ve uygulama anahtarı 403, personelin
uygulama anahtarı prova + gerçek taşıma 200; olmayan grup 422 `group_id`,
boş gövde, sayı olmayan, eksi değer ve fazladan alan 422, tartışma 422,
olmayan ve içinde olunmayan özel kanal 404; grup listesi personelde üç grup
ve sayılar, personel olmayanda yalnız gördüğü kanalın grubu ve üstü
(`access: null`, `mine` doğru); kanal listesi ve eşitlemede `group_id`;
OpenAPI'de iki yol ve nesneler; panelin kendi önizleme ve taşıması aynı
cevabı verdi. Site Günlüğü'ne taşıma satırı düşmedi; sunucu günlüğünde
uyarı yok. `lint`, `check_lang`, `check_api_schema`, `check_bindings`,
`test`, `check_copies`, `build_js --check` temiz.

---

## 2026.4.10 — Form listesi sayfasının kanonik adresi `?id=` taşıyordu (2026-10-14)

**Neden.** `get_page_content.php`'de kanonik adresin sorgu kısmını kuran
`switch`'te `form list view` dalı `break`'siz bitiyor, `calendar event
view` dalına düşüyordu. Form listesi `id` ve `recurrence_number`
okumaz (sayfalamayı `<sayfa kimliği>_page_number` ya da `page_number` ile
yapar); düşme bilinçli bir paylaşım değildi. Sonuç: blog listesi gibi bir
form listesi sayfası `?id=5` ya da `?recurrence_number=2` ile açılınca
kanonik adres ve `og:url` bu değeri taşıyordu, `?page_number=2&id=5`'te
sayfa numarası da düşüp `?id=5` kalıyordu (arama motoru ikinci sayfayı
başka bir adrese bağlıyordu). Aynı dalda `?page_number[]=2` `trim()`'e dizi
verip 500 dönüyordu; listenin kendisi (`get_form_list_view.php`) değeri
tam sayıya çevirdiği için hata yalnız buradaydı. İkisi de 2026.4.9'da var.

**Ne değişti.** Dala `break` eklendi; sayfa numarası yalnız metinse
okunuyor (dizi gelirse kanonik adres sayfa numarasız, liste ilk
sayfayı zaten öyle açıyordu). Takvim etkinliği dalı ve öbür dallar
değişmedi; aynı `switch`'te başka eksik `break` yok. Şema, dil dosyası ve
JS değişikliği yok.

**Doğrulandı.** `switch` bloğu eski (`origin/development`) ve yeni dosyadan
çıkarılıp altı sayfa türü × on dört adres parametresi bileşimiyle koşuldu:
84 durumun 76'sı aynı; farklı olan 8'in hepsi form listesinde `id` ya da
`recurrence_number` içeren adresler (yeni kod onları yazmıyor, sayfa
numarasını koruyor). Takvim etkinliği, form kaydı, fotoğraf galerisi ve
arama sonuçlarında çıktı aynı. `php -l`, `tools/lint.php`, `tools/test.php`
ve `build_js.php --check` geçti. Doğrulanamadı: dev.pinegrap.com'daki gerçek
sayfalar.

**Bilerek yapılmayan.** Aynı `switch`'te arama sonuçları `?query[]=x` ile
500 veriyor, ama `get_search_results.php` de aynı değeri `trim()`'liyor;
ikisi birlikte ayrı iş. Fotoğraf galerisi dalı `folder_id`'yi
`urlencode()`'suz ve dizi denetimsiz yazıyor (`?folder_id[]=x` →
"Array" ve uyarı). İkisi yukarıdaki bölümde giderildi.

---

## 2026.4.10 — Dış API: gelen kutusunun değişenlerinde artık görülemeyen satırlar ve baştan okuma işareti (2026-10-14)

**Neden.** #383'ün bilerek bıraktığı iki kenar durumu. (1) Kişinin artık
göremediği şeyle ilgili satır (çıkarıldığı özel kanal, onun tartışmaları,
onsuz özel yapılan kanal) `changed_since`'te gelmiyordu: satır yerinde
durur, damgaları kıpırdamaz, liste kuralı (`ws_inbox_visible_rows()`)
yalnız gizler. Uygulama onu ancak tek satır okuması 404 verince
öğreniyordu. (2) Silinenlerin izi 30 gün tutuluyor; daha eski bir anla
soran uygulamaya sunucu bir şey demiyordu, tarihleri uygulamanın kendisi
karşılaştırmalıydı.

**Ne değişti.**
- Artık görülemeyen satır değişenlerde `deleted: true` ile gelir, #383'ün
  silinen satırıyla aynı biçimde (id, kind, created_at; başlık, metin,
  adres boş, kimlikler `null`, `read: true`). Ayrı bir işaret yerine
  `deleted` seçildi: gelen kutusu satırı tek kişinindir, başkası için
  "duran" bir satır yok; o kişi için silinmiş satırla aynı şeydir ve tek
  satır okuması ikisinde de 404. #371'in kanal mesajlarındaki karşılığı da
  budur: mesaj kişiye görünmez olunca kanal okuması 404 olur, eşitleme
  `gone` der; satır düzeyinde ayrı alan olan `hidden` (#375) kişinin
  kendi seçimidir ve mesajı tam içeriğiyle verir, buraya uymaz.
- `ws_inbox_reach_lost($channel, $user_ids = null)` (`notify.php`): kişi
  kanalın erişiminden çıkınca o kanalla, kanalda açılan tartışmalarla ve
  kanalın görevlerinden kişinin artık göremediğiyle ilgili satırlarının
  `read_at`'ini şimdiye çeker (önceden okunmuşlar dahil). Böylece
  `changed_since` onları damgasıyla bulur, `inbox_changed_at` (#380)
  ilerler, zil sayısı ve `inbox_unread` hemen düşer
  (`ws_bell_sync_read()`). Kanalı hâlâ okuyan (herkese açık kanal, açık
  inceleme) bir şey kaybetmez. Çağıran yerler: `ws_channel_leave()`
  (ayrılma, sahibin çıkarması, tartışmadan ayrılma), grup erişimiyle
  gelen üyeliğin bitmesi (`groups.php`), `ws_channel_make_private()`
  (dışarıda kalan herkes; seyrek adım, gelen kutusu tablosu bir kez
  taranır). Görev satırı kanal taşımadığı için kişi başına en yeni 500
  tanesi tek tek kurala sorulur (`WS_INBOX_REACH_TASK_READ`); görevin
  sahibi ve atananları görmeye devam eder.
- `ws_api_inbox_changed_rows()` (`api_inbox.php`): `changed_since`'in
  bulduğu satırlardan kişinin göremediğini atlamak yerine silinmiş olarak
  verir. Claude'un çalıştığı uygulama, sorulabildiği kanal dışındakileri
  eskisi gibi hiç duymaz (`ws_api_inbox_claude_may_hear()`, silinen
  izlerle ortak).
- Baştan okuma işareti: `GET /workspace/inbox` cevabında `data` ve
  `page`'in yanında `reload` (boolean). `changed_since` izlerin tutulduğu
  30 günden eskiyse `true` (`ws_api_inbox_reload()`); satırlar yine
  gelir, cevap 200 kalır. Ad ve anlam eşitlemenin `reload`'uyla aynı
  (#374: "kanalı yeniden okuyun"). `changed_since` olmadan hep `false`.
- `api_ok_list()`'e beşinci parametre `$extra` (liste zarfına alan),
  `api_openapi_success_schema()` `'returns' => array('list' => …)`
  yanındaki alanları belgeye ekler; rota
  `array('list' => 'WorkspaceInboxItem', 'reload' => 'boolean')` der.
- MCP: `workspace_inbox_list` aracı cevabı ve açıklamayı rotadan alır;
  yeni parametre yok.

Şema adımı yok, yeni izin kapsamı yok (`workspace:read`), yeni metin yok,
JS değişmedi.

**Bilerek yapılmayan.** Erişim başka yoldan kaybolursa (bir toplantı
silinir, inceleme süresi biter, satırın mesajı anılma dışı bir türde
silinir) satır yalnız okunmamışsa, bir liste onu bulup okundu
işaretlediğinde değişenlere girer; okunmuşsa uygulama eskisi gibi 404 ile
öğrenir. Kişi kanala geri alınırsa satırlar listede yeniden görünür ama
değişenlerde canlı olarak dönmez; uygulama ilk sayfayı okuyunca alır.

**Doğrulama.** `tests/workspace_api_test.php`'ye iki test
(`ws_api_inbox_reload()` sınırları; rota `reload`'u bildiriyor ve OpenAPI
200 şeması `data`, `page`, `reload`, yalnız `list` diyen rota eskisi
gibi). Sandbox'ta "Ekip cihazları" oturumuyla: özel kanaldan ayrılan
kişinin o kanaldaki biri okunmuş biri okunmamış iki satırı `changed_since`
ile `deleted: true` geldi, tek okuma 404, eşitlemenin `inbox_unread`'i
1'den 0'a düştü, `inbox_changed_at` ayrılma anına ilerledi; `limit=1` +
`since_id` ikisini sırayla verdi. Panelde kanal özel yapılınca dışarıda
kalan kişinin satırı aynı biçimde geldi, üyelerinki değişmedi. Sahip kişiyi
özel kanaldan çıkarınca kanalın tartışması ve kişinin olmayan görevi
hakkındaki satırlar `deleted: true` geldi, kişiye atanan görevin iki satırı
gelmedi ve listede kaldı. 40 gün önceki anla `reload: true`, 29 günle
`false`; normal listede `reload: false`. OpenAPI belgesinde gelen kutusu
200'ü `data`, `page`, `reload`; diğer listeler değişmedi. Sunucu
günlüğünde hata yok.

---

## 2026.4.10 — Site sayfaları: altı ekranda PHP uyarıları, dizi ve metin parametresiyle 500 (2026-10-14)

**Neden.** #384'ün taraması (sandbox'taki 181 sayfa, oturumsuz ve yönetici
oturumuyla) kendi kapsamı dışında altı dosyada uyarı buldu:
`get_billing_information.php`, `get_order_preview.php`,
`get_form_item_view.php`, `get_calendar_event_view_screen_content.php`,
`get_page_content.php` ve `get_catalog_detail.php`. Hepsinin kökü aynı:
bir değer yalnız bir yolda kuruluyor, öbür yolda (sepet boş, adreste
referans kodu ya da etkinlik kimliği yok, katalog bölgesi olmayan sayfa)
okunuyordu. Aynı satırların yanında üç görünür hata çıktı. Hepsi eski
kodla sandbox'ta üretildi; satırların hepsi 2026.4.9'da da var.

- Form kaydı sayfası (blog yazısı, ilan, rehber kaydı) adreste `?r[]=x`
  ile açılınca `e()` diziyi kaçıramadı (`TypeError`): HTTP 500. 2026.4.9'da
  da var.
- Takvim etkinliği sayfası `?id[]=x` ile (aynı nedenle) ya da tekrar
  numarası sayı olmadan (`?recurrence_number=abc`,
  `?recurrence_number[]=x`) açılınca 500 verdi: tekrar numarası
  `get_calendar_event()`'in tarih hesabına olduğu gibi giriyordu
  (`string + string`). 2026.4.9'da da var.
- Takvim etkinliği takvimden değil doğrudan açılınca (paylaşılan link,
  arama sonucu) "Takvime Geri Git" düğmesi `href=""` ile çiziliyordu,
  tıklayan aynı sayfayı yeniden yüklüyordu (oturumda takvim adresi yok).
  2026.4.9'da da var.

Görünmeyen uyarılar (çıktı aynı, 2026.4.9'da da var):

- `get_billing_information.php` 140–270: sepet boşken sipariş satırı yok,
  `$order` null; her açılışta 19 "array offset on null". Alanlar zaten
  kişinin rehber kaydına düşüyordu.
- `get_order_preview.php` 58–59: aynı nedenle iki uyarı.
- `get_form_item_view.php` 84, 124 ve `get_page_content.php` 3743, 3790,
  4868, 4952: referans kodu olmadan açılan form kaydı sayfası (yönetici
  sayfayı önizlerken yorum bölgesi de çiziliyor, form düzenleyici
  değişkenleri hiç kurulmuyordu).
- `get_calendar_event_view_screen_content.php` 33, 42, 107, 210, 219,
  225–226 ve `get_page_content.php` 3204: kimliksiz ya da tekrar
  numarasız açılan etkinlik; rezervasyonu olmayan etkinlikte
  `$output_reserve_form` hiç kurulmuyordu.
- `get_page_content.php` 3107: özel form onay sayfası `form_id`'siz
  (yönetici önizlemesi).
- `get_page_content.php` 5421 ve `get_catalog_detail.php` 51: ürünsüz
  açılan katalog detay sayfası; `$catalog_product_group_id` yalnız katalog
  bölgesinde kuruluyor, `$item` boş dizi.
- `get_catalog_detail.php` 1312: indirimsiz ürünü olan ürün grubu
  sayfasında (sandbox'ta "Donations") `discounted_price` anahtarı yok.
- `get_page_content.php` 802, 822, 837: kanonik adres dizi parametresini
  "Array" diye yazıyordu.

**Ne değişti.** Değerler kullanıldıkları yerden önce kuruluyor; uyarı
susturulmadı (`@` ya da hata düzeyi yok).
- Fatura sayfasında sipariş satırı yoksa okunan 19 sütun boş başlıyor;
  sipariş önizlemede iki değer boş.
- Form kaydı sayfası referans kodunu bir kez, yalnız metinse okuyor
  (`$reference_code`); dizi gelirse referans kodu yok sayılıyor ("referans
  kodu geçmedi" 404'ü). `get_page_content.php`'de yorumlar ve görsel
  editör kayıt bağlamı da `r`'yi yalnız metinse okuyor; yorum bölgesinde
  form düzenleyici değişkenleri boş başlıyor.
- Takvim etkinliğinde kimlik yalnız metinse okunuyor. Tekrar numarası
  tam sayıya çevriliyor (`max(0, (int) …)`, görsel editörün etkinlik
  widget'ı da böyle okuyor); verilmemişse boş kalıyor, böylece iCal
  bağlantısı ve rezervasyon formu eskisi gibi. Sayı olmayan numara ilk
  tekrarı açıyor. "Geri" düğmesi yalnız oturumda takvim adresi varsa
  çiziliyor (görsel editör widget'ı `has_back_url` ile aynısını yapıyor).
- `$catalog_product_group_id` sistem bölgelerinden önce 0 başlıyor;
  davranış aynı (tanımsız değer zaten `!= 0` değildi). Katalog detayında
  `empty($item['id'])`; indirimsiz üründe `discounted_price` null (dolar
  çevirisi `isset()` ile bakıyor, çıktı aynı).
- Kanonik adreste `r`, `id` ve `recurrence_number` yalnız metinse ve
  `urlencode()` ile yazılıyor (bugünkü referans kodları ve kimlikler için
  çıktı aynı).
Şema, dil dosyası ve JS değişikliği yok.

**Bilerek yapılmayan.** Düzenleme kipindeki yönetici, olmayan bir
referans koduyla (`?r=yanlis`) form kaydı sayfasını açınca sayfa boş bir
kayıtla çiziliyor ve `get_form_item_view.php` ile yorum bölgesi 13 uyarı
yazıyor (2026.4.9'da da var); orada ne gösterileceği ayrı karar. Tarama
başka dosyalarda da uyarı buldu: `get_affiliate_sign_up_form_screen_content.php`
(yönetici, 14), `get_my_account_profile.php` (yönetici, 18),
`get_email_preferences.php` 47, `get_custom_form_screen_content.php` 47,
`includes/fn/auth.php` 3233 (oturumsuz `user-logout`). `get_page_content.php`
790–840'ta `form list view` dalı `break`'siz `calendar event view`'a
düşüyor (form listesinin kanonik adresine `?id=` eklenebiliyor); ayrı iş,
yukarıdaki bölümde giderildi.

**Doğrulandı (sandbox, MariaDB, PHP 8.3).** 181 sayfa oturumsuz ve
yönetici oturumuyla açıldı: eski kodda bu altı dosyadan 33 farklı satırda
uyarı, yeni kodda hiç (kalanlar yukarıdaki başka dosyalarda). Ek girdiler
(`?r[]=x`, `?r=bogus`, geçerli kod, `?id[]=x`, `?id=999999`, yayındaki
tekrarlı etkinlik `?id=7`, `&recurrence_number=2`, `=abc`, `[]=x`, ürünsüz,
`?page[]=x`, ürünlü ve olmayan ürünlü katalog detayı, boş sepetle iki
fatura ve iki önizleme sayfası) eski kodda dört 500 verdi, yeni kodda hiçbiri
uyarı ya da 500 vermedi; düzenleme kipinde yönetici için de aynı (yukarıdaki
bilerek bırakılan durum hariç). Eski kod `origin/development` worktree'sinden
ikinci bir portta sunuldu, 181 sayfanın HTML'i karşılaştırıldı: oturumsuz
181'in 181'i aynı; yönetici oturumunda farklar yalnız sayfa görüntülenme
sayacı ve oturum listesi sırası. Tek görünür fark takvimden gelmeyen
ziyaretçide "Geri" düğmesinin kalkması (takvimden gelince ikisi aynı).
`tools/test.php` 844/844; lint, dil, kopya denetimleri ve
`build_js.php --check` geçti. Doğrulanamadı: dev.pinegrap.com'daki
gerçek sayfalar ve gerçek siparişli sepet.

---

## 2026.4.10 — Dış API: oylama açma, değiştirme ve kapatma (2026-10-14)

**Neden.** #381 (M6, aşağıda) ekip cihazları uygulamasına oylamada oy
vermeyi (`POST /workspace/polls/{id}/vote`) ve mesaj nesnesinde `poll`'u
verdi, ama oylamayı açmak, değiştirmek ve kapatmak yalnız panelde kaldı
(`ws_poll_create`, `ws_poll_edit`, `ws_poll_close` eylemleri ve `/poll`
komutu). Telefondaki kişi oylamasını açamıyor, oy gelmeden düzeltemiyor,
sonucu kanala yazdıramıyordu.

**Ne değişti.**
- `POST /workspace/channels/{id}/polls` {`question`, `options` (metin
  listesi), `multiple`, `anonymous`, `closes_at`}: panelin
  `ws_poll_create()`'i. Soru kişinin mesajıdır (adıyla, dakikada yirmi
  mesaj sınırıyla, herkese açık kanala katarak; andığı kişilere haber
  gider). Cevap oylamanın mesajı, 201. Kimlik bir tartışmanınki de olabilir.
- `POST /workspace/polls/{id}` (PATCH da kabul): panelin
  `ws_poll_edit()`'i, kısmi güncelleme: gönderilmeyen alan olduğu gibi
  kalır, önce oylamanın bugünkü hali kurulur, gelenler üstüne konur.
  `options` gönderilirse bütün liste yerine geçer: `poll.options`'tan bir
  `id` taşıyan öğe o seçeneği (ve oylarını) tutar, `id`'siz öğe yeni
  seçenek ekler, listede olmayan seçenek kalkar. Oy verildikten sonra
  oylu seçenek aynı sözle kalır, `multiple` ve `anonymous` değişmez (422,
  panelin kuralı). `closes_at: null` süreyi kaldırır. Başkasının oylaması
  403, kapanmış oylama 422. Hiç alan yoksa 422.
- `POST /workspace/polls/{id}/close`: panelin `ws_poll_close()`'u. Soran
  kendi oylamasını, kanalı yöneten herhangi birini kapatır (`ws_can_close_poll()`,
  aksi 403). Sonuç panelde olduğu gibi kanala yazılır: tek kazanan
  soranın adıyla karar, beraberlik ya da oysuz oylama sistem satırı.
  Kapanmış oylamayı kapatmak hata değil, aynı cevabı döner.
- `WorkspacePoll`'a `can_edit` ve `can_close` eklendi (panelin
  `ws_polls_map()`'inde zaten vardı): uygulama düğmeleri buna göre
  gösterir.
- Prova (`dry_run`) üçünde de gerçek isteğin retlerini alır: açmanın
  denetimleri `ws_poll_input()`, değiştirmeninkiler `ws_poll_edit_check()`
  olarak `interact.php`'de ortak fonksiyona alındı; panelin
  `ws_poll_create()` ve `ws_poll_edit()`'i de onları çağırıyor. Açmada
  soru uzunluğu artık mesaj yazılmadan önce denetleniyor (aynı metin, aynı
  alan); değiştirmede sorunun yeni hali mesaj düzeltme kurallarına
  (`ws_message_edit_check()`) yazmadan önce tabi. Davranış aynı kalmalı.
- Oy, değiştirme ve kapatma oylamayı aynı yardımcıyla bulur
  (`ws_api_poll_or_404()`: silinmiş mesaj ya da okunamayan kanal 404,
  konuşmanın önceki sürümü 422).
- Açma ve değiştirme yalnız ekip cihazları oturumundan
  (`ws_api_own_words_only()`): uygulama anahtarı ve OAuth bağlantısı
  uygulama adıyla yazar; öyle açılmış oylamayı kişi sonra değiştiremez
  (`ws_poll_edit()` yalnız kişinin kendi mesajını kabul ediyor) ve sonuç
  kararı yine kişinin adıyla yazılırdı. Kapatma kişinin kararıdır, oy gibi
  `ws_api_person_only()` (OAuth kabul). Üçü de `dry_run` alır, hiçbiri MCP
  aracı değil (`'mcp' => false`): panelde hiçbir asistan oylama açmıyor,
  bağlayıcının araç listesi değişmesin.

Şema değişikliği yok, yeni izin kapsamı yok (`workspace:write`), `tr.json`'da
7 yeni metin, JS değişmedi. Site Günlüğü'ne satır yazılmaz: panel de oylama
eylemlerini günlüğe yazmıyor.

**Bilerek yapılmayan.** OAuth bağlantısıyla (claude.ai bağlayıcısı)
oylama açma ve değiştirme, bağlantının yazdığı mesajın nasıl görüneceği
kararını bekliyor (plan kararı 11). Uygulama anahtarıyla, entegrasyon
adına oylama da aynı sebeple yok.

**Doğrulama.** `tests/workspace_api_test.php`: üç rota (yöntem, yol,
kapsam, cevap, `dry_run`, MCP aracı değil, değiştirmede PATCH ve isteğe
bağlı alanlar, `closes_at` nullable), `ws_poll_input()` (boş ve tekrarlı
seçenek atılır, boş soru, tek seçenek, yakın kapanış, on bir seçenek,
uzun soru), `WorkspacePoll`'da `can_edit` / `can_close`. Sandbox'ta iki
ekip cihazı oturumuyla: açma provası (tekrarlı ve boş seçenek atıldı),
uygulama anahtarı 403, tek farklı seçenek, metin olmayan seçenek, bir
dakikadan yakın kapanış, eksik `options`, bilinmeyen alan 422, olmayan
kanal 404; gerçek açma (soru kişinin adıyla, anma çözüldü, `can_edit` /
`can_close` true); tartışmada açma, tartışmada olmayan kişiye 404.
Değiştirme: boş gövde 422, prova (`added: 1`, `removed: 1`), uygulama
anahtarı 403, soran olmayan kişi 403, bilinmeyen seçenek kimliği ve
`label`'sız öğe 422, gerçek PATCH (yeniden adlandırma, ekleme, kaldırma,
`multiple`, `closes_at`), yalnız soru (POST), `closes_at: null`. İki oydan
sonra: oylu seçeneği yeniden adlandırma ve kaldırma 422, `multiple`
değiştirme 422, yeni seçenek ekleme ve oysuz seçeneği kaldırma 200.
Kapatma: soran olmayan ve kanalı yönetmeyen kişi 403, uygulama anahtarı
403, prova kapatmadı, gerçek kapatma sonucu karar olarak yazdı
(`result_message_id`), yeniden kapatma aynı cevap, kapalı oylamayı
değiştirme ve oy 422. Panelde (Playwright, `api.php` eylemleri):
`ws_poll_create`, `ws_poll_edit` (yeni seçenekler, boş soru reddi),
`ws_poll_vote`, `ws_poll_close` ve `/poll` komutu eskisi gibi. Sunucu
günlüğünde uyarı yok; `lint`, `check_lang`, `check_api_schema`, `test`,
`build_js --check`, `check_copies` temiz.

---

## 2026.4.10 — Dış API: kanalı listenin başına sabitleme ve kişinin kendi kanal sırası (2026-10-14)

**Neden.** M6'nın (#381) kapsam dışı bıraktığı parça: telefondaki ekip
uygulaması kanalın bildirim düzeyini seçebiliyordu ama kişinin panelde
kanal listesinde tuttuğu diğer tercihlere (kanalı en üste sabitleme,
sürükleyerek verdiği sıra) API'den ulaşılamıyordu; kanal nesnesi de
bunları taşımıyordu.

**Ne değişti.**
- `POST /workspace/channels/{id}/prefs` {`pinned`, `order`}: yalnız
  gönderilen alan değişir, ikisi de yoksa 422. `pinned` panelin
  `ws_channel_pin()`'i (kanal adının yanındaki raptiye). `order`, kişinin
  içinde olduğu kanallar arasında kanalın gideceği yer (1 en üst), panelde
  sürüklemek gibi: o yerden sonrakiler bir aşağı kayar. Yeni ortak
  fonksiyon `ws_channel_place()` (`channels.php`) listeyi
  `ws_channels_for()`'un sırasıyla okur, kanalı yerine koyar ve panelin
  `ws_channel_order()`'ıyla baştan numaralar. Sabitlenmiş kanallar hep
  önde olduğu için sabitlenmiş kanal sabitlenmişler arasında, diğeri
  onlardan sonra bir yer alır; uçların dışındaki yer en yakın geçerli
  yere çekilir. Her çağrı (yalnız `pinned` gönderilse de) listeyi yeniden
  numaraladığı için her kanaldaki `order` `GET /workspace/channels`'taki
  yeriyle aynıdır.
- `WorkspaceChannel`'a `pinned` ve `order` alanları eklendi (`order`
  kişinin içinde olmadığı kanalda ve liste hiç numaralanmamışken `null`).
  Kanal listesi zaten kişinin sırasıyla geliyordu (`ws_channels_for()`);
  açıklaması bunu söylüyor. Eşitleme aynı nesneyi taşır; liste işareti
  sunulan listenin `crc32`'si olduğu için sabitleme ya da sıra değişince
  liste yeniden gelir.
- `notify` gibi: herkese açık kanalda kişi içinde değilse onu kanala katar
  (panelin `ws_channel_pin()`'i de böyle), içinde olmadığı özel kanal 409
  `not_member`, tartışma 422 (kanal listesinde yer almaz). Yalnız cihaz
  oturumundan (`ws_api_person_only()`; uygulama anahtarı 403, OAuth
  bağlantısı kabul), `dry_run` alır, MCP aracı değil (`'mcp' => false`).

Şema değişikliği yok (`ws_channel_members.sort` ve `pinned` 4.84'ten beri
var), yeni izin kapsamı yok (`workspace:write`), `tr.json`'da 4 yeni metin,
JS ve panelin davranışı değişmedi. Site Günlüğü'ne satır yazılmaz: panel de
sabitleme ve sırayı günlüğe yazmıyor.

**Bilerek yapılmayan.** Kanalın grubu kişisel bir tercih değil: gruplar
çalışma alanınındır, herkes için aynıdır ve yalnız personel düzenler;
kanalı bir gruba taşımak o grupta erişimi olanları kanala katar (panel
taşımadan önce kimin gireceğini önizletiyor). Bu yüzden bu uca girmedi.
Panelde grup açık / kapalı durumu yalnız tarayıcıda (`localStorage`)
tutuluyor, sunucuda kişisel bir grup tercihi yok.

**Doğrulama.** `tests/workspace_api_test.php`: rota (yöntem, yol, kapsam,
cevap, `dry_run`, MCP aracı değil, iki alan da isteğe bağlı, `order` en
az 1) ve kanal sunumu (`pinned`, `order`; numaralanmamış kanalda `null`,
alanlar şemayla aynı sırada). Sandbox'ta ekip cihazları oturumuyla beş
kanalda: yalnız `pinned` (kanal başa geçti, liste 1-5 numaralandı),
sabitlenmemiş kanala `order: 1` (sabitlenmişlerden hemen sonraya),
`pinned` + `order: 1` (en başa), `pinned` + `order: 99` (sabitlenmişlerin
sonuna), sabitlemeyi kaldırma (sabitlenmemişlerin başına), `order: 99`
(en sona); her adımda `order` listedeki yerle aynı. Prova (`?dry_run` ve
`X-Dry-Run`) hiçbir şeyi değiştirmedi; boş gövde, `order: 0`, sayı
olmayan `order`, geçersiz `pinned` ve `group` alanı 422, olmayan kanal
404, uygulama anahtarı 403 (uygulama anahtarıyla liste sahibinin
`pinned` / `order`'ını gösteriyor), içinde olunmayan herkese açık kanal
prova `joins: true`, gerçek istekte kişiyi kattı; eşitlemede alanlar
geliyor; OpenAPI'de yol var. Sunucu günlüğünde uyarı yok; `lint`,
`check_lang`, `check_api_schema`, `test`, `build_js --check`,
`check_copies` temiz.

---

## 2026.4.10 — Dış API: gelen kutusunun değişenlerinde silinen satırlar (2026-10-14)

**Neden.** #379 gelen kutusuna `changed_since` verdi, ama tamamen silinen
satır (silinen mesajdaki anılma, silinen ya da paylaşımı kaldırılan not,
süresi dolup temizlenen tartışma) iz bırakmadığı için değişenlerde
gelmiyordu; uygulama elindeki satırın gittiğini ancak
`GET /workspace/inbox/{id}` 404 verince öğreniyordu. Kanal mesajlarında
silinen mesaj değişenlerde `deleted: true` ile gelir (#371, M2); mesaj
satırı yerinde kalıp `deleted_at` aldığı için. Gelen kutusu satırı ise
gerçekten silinir, bu yüzden aynı deseni kurmak için silinenin kısa süreli
bir izi gerekti.

**Ne değişti.**
- Şema 10.88: `ws_inbox_deleted` tablosu. Silinen her gelen kutusu
  satırının kendi `id`'si (birincil anahtar), kişisi, türü, kanalı,
  yazıldığı ve silindiği an (`idx_user (user_id, deleted_at)`,
  `idx_deleted`).
- `ws_inbox_delete($where)` (`notify.php`): satırları silmeden önce
  izlerini yazar (`REPLACE INTO … SELECT`), 30 günden eski izleri aynı anda
  temizler (`WS_INBOX_DELETED_KEEP`, en çok 500 satır), sonra satırları
  siler. Gelen kutusundan satır silen dört yer artık bunu çağırır: mesaj
  silme (`ws_message_delete()`, anılmalar), not silme (`ws_note_delete()`),
  not paylaşımını kaldırma (`ws_note_unshare()`), tartışma temizliği
  (`ws_threads_purge()`). Tablo yoksa (veritabanı adımı koşmadan) eskisi
  gibi yalnız siler.
- `GET /workspace/inbox?changed_since=` o andan beri silinen satırları da
  verir: `deleted: true`, `id`, `kind`, `created_at`; başlık, metin ve
  adres boş, kimlikler `null`, `read: true` (artık beklemiyor). Silinenler
  yaşayan satırlarla `id` sırasına girer, `since_id` ikisini birlikte
  sayfalar (`ws_api_inbox_changed()` + `ws_api_inbox_deleted()`, koşul
  `ws_api_inbox_deleted_where()`). Canlı satırlar 20 turda bitmeden
  okumayı bıraktıysa, silinenler de son okunan satırın ötesine geçmez; aynı
  `id` hem canlı hem silinmiş görünürse (MySQL 8 öncesi yeniden başlatmada
  kimliği yeniden veren sunucu) canlı olan kazanır. Claude'un çalıştığı
  uygulama yalnız sorulabildiği kanalla ilgili silinenleri görür, canlı
  satırlardaki kuralla aynı.
- `GET /workspace/sync`'in `inbox_changed_at`'i (#380) en yeni izi de
  sayar (`ws_api_inbox_changed_at()`, `MAX(deleted_at)` `idx_user`'ın bir
  ucunu okur): okunmuş bir satır silinince de ilerler, uygulama
  `changed_since` ile sorar. İzler 30 günde temizlendiği için değer geri
  gidebilir; uygulama zaten "değişince sor" kuralıyla çalışır, boş liste
  alır.
- Her satırda yeni `deleted` alanı (`WorkspaceInboxItem`); normal listede
  ve tek satır okumada hep `false`.
- MCP: `workspace_inbox_list` aracı yeni alanı rotadan kendiliğinden alır;
  yeni parametre yok.

Yeni izin kapsamı yok (`workspace:read`), yeni metin yok.

**Bilerek yapılmayan.** 30 günden eski bir anla soran uygulama o tarihten
önceki silinenleri öğrenemez; belgede "önceki okumanız 30 günden eskiyse
gelen kutusunu ilk sayfadan yeniden okuyun" yazıyor, sunucu bunu ayrıca
bildirmez. Kişinin artık göremediği şeyle ilgili satır (çıkarıldığı özel
kanal, silinen görev) silinmiş sayılmaz ve değişenlerde gelmez: satır
yerinde durur, liste kuralı onu gizler; uygulama onu 404 ile öğrenmeye
devam eder. Zildeki (`GET /notifications`) satırı silinen gelen kutusu
satırına bakan bildirim olduğu gibi kalır.

**Doğrulama.** `tests/workspace_api_test.php`'ye iki test
(`ws_api_inbox_deleted_where()`; silinen satırın alanları şemayla aynı,
`deleted` ve `read` doğru). Sandbox'ta (taze kurulum tabloyu oluşturdu)
"Ekip cihazları" oturumuyla: iki anılmanın mesajı silinince
`changed_since` ikisini `deleted: true` ile, araya okunan canlı satırla
birlikte `id` sırasında verdi; `limit=1` + `since_id` üç sayfada üçünü
verip boş döndü; normal listede silinenler yok, kalan satır
`deleted: false`; silinen satırın tek okuması 404. 31 günlük iz bir
sonraki silmede temizlendi. Aynı istek `If-None-Match` ile 304. Tablo
yokken mesaj silme ve `changed_since` hatasız, eskisi gibi. Okunmuş bir
satırın mesajı silinince eşitlemenin `inbox_changed_at`'i silme anına
ilerledi, `inbox_unread` değişmedi. Sunucu günlüğünde hata yok.

---

## 2026.4.10 — Dış API: mesajı kanalın en üstüne sabitleme, sabitlemeyi kaldırma ve kişinin kendisi için kapatması (2026-10-14)

**Neden.** #380 açık kanalın sabitlenmiş mesajını `GET /workspace/sync`
cevabına (`channel_state.pin`) koydu, ama API'de mesaj sabitleyen ya da
sabitlemeyi kaldıran bir uç yoktu: ekip cihazları uygulaması panelin
sabitleme şeridini gösterebiliyor, mesaj menüsündeki "En üste sabitle",
"Sabitlemeyi kaldır" ve şeridin kapatma düğmesini yapamıyordu.

**Ne değişti.**
- `POST /workspace/messages/{id}/pin` (`ws_api_messages_pin()`): mesajı,
  notu, kararı ya da görev kartını kanalın en üstüne sabitler, şu an
  sabitli olanın yerine; `on: false` sabitlemeyi kaldırır. Panelin
  `ws_pin` eylemiyle aynı fonksiyondan yazar
  (`ws_channel_pin_message()`): aynı izin (`ws_can_pin()`: kanala
  yazabilen; arşivlenmiş kanal 403, tartışma 422), kanala aynı sistem
  satırı ("… kanalın en üstüne bir mesaj sabitledi" / "… kaldırdı"), aynı
  `pinned_by` / `pinned_at`. Böylece `channel_state`'in işareti ve pano
  işareti (`ws_channel_board_stamp()` `pinned_message_id`'yi okur)
  değişir; uygulamanın sonraki eşitlemesi ve paneldeki açık ekranların
  `ws_sync`'i değişikliği görür. Sabitli mesajı yeniden sabitlemek ya da
  sabitli olmayan bir mesajdan sabitlemeyi kaldırmak hiçbir şey yazmaz:
  yinelenen çağrı kanala ikinci satır yazmaz, yeni bir sabitlemeyi
  görmemiş uygulama onu kaldırmaz (panel `message_id: 0` ile "ne sabitliyse"
  kaldırır; API mesaja bağlı olduğu için bunu yapmaz).
- `POST /workspace/messages/{id}/pin/close` (`ws_api_messages_pin_close()`):
  şeridin çarpısı gibi sabitli mesajı kişi için kapatır
  (`ws_channel_pin_hide()`, `ws_channel_members.pin_hidden`); başka bir
  mesaj sabitlenene kadar panelde de kapalı kalır. Yalnız şu an sabitli
  mesaj için yazar, başka mesajda hiçbir şey olmaz. Kanalın üyesi olmayan
  409 `not_member` alır (panel onu yalnız tarayıcıda tutar; uygulama da
  kendi ekranında tutar).
- İkisi de yalnız ekip cihazları oturumundan (`ws_api_own_words_only()`,
  ortak `ws_api_pin_target()`): kanal satırı kişinin adını taşıyor ve
  sabitleme şeridini çizen `channel_state` yalnız o oturuma gidiyor.
  Uygulama anahtarı ve OAuth bağlantısı 403 alır. `X-Dry-Run` /
  `?dry_run=true` ile prova (`would`: `pinned`, `unpinned`, `closed`;
  `detail.replaces` yerini alacağı mesaj, `detail.changes` bir şey
  değişecek mi). Cevap, `GET /workspace/sync`'in `channel_state`'iyle aynı
  nesne (`WorkspaceSyncChannelState`).
- Rotalar `'mcp' => false`: MCP istemcisi uygulama ya da OAuth bağlantısı
  olarak bağlanır, ikisi de reddedilir; modelin çizeceği bir şerit de yok.

Şema değişikliği yok, yeni izin kapsamı yok (`workspace:write`), JS
değişmedi, MCP aracı yok. Dört yeni metin `tr.json`'da.

**Bilerek yapılmayan.** Site Günlüğü'ne satır yazılmaz: panelin sabitlemesi
de yazmıyor, kaydı kanalın kendi satırı ("… sabitledi"). Ayrı bir hız sınırı
yok (panelde de yok; API'nin uygulama başına kovası geçerli). Uygulama
anahtarı ve OAuth bağlantısıyla sabitleme açılmadı: OAuth bağlantısının
yazdıklarının nasıl görüneceği plandaki karar 11'e bağlı. Şablon uygulama
ucunun (`POST /workspace/channels/{id}/apply-template`) şablonun mesajını
sabitlemesi eskisi gibi her kimlikle çalışıyor.

**Doğrulama.** `tests/workspace_api_test.php`: iki rota POST,
`workspace:write`, `dry_run`, `WorkspaceSyncChannelState` döner, MCP aracı
değil; `on` varsayılanı `true`. Sandbox'ta ekip cihazları oturumuyla:
prova `would: pinned`, `changes: true` döndü, kanal ve sistem satırı
değişmedi; sabitleme cevabında `pin` (mesaj 1, `pinned_by`, `pinned_at`),
kanala bir sistem satırı düştü; aynı çağrı yinelenince satır sayısı
değişmedi; ikinci mesajın provası `replaces: 1` verdi, sabitlenince pano
işareti değişti; sabitli olmayan mesajda `on: false` ve kapatma hiçbir şey
yazmadı; kapatma provası `pin_hidden`'i yazmadı, gerçek kapatma
`hidden: true` verdi ve `pin_hidden` mesajın kimliği oldu; `on: false`
sabitlemeyi kaldırdı, "kaldırdı" satırı düştü, `pin: null`. Sistem satırı
422, arşivlenmiş kanal 403, olmayan mesaj 404, bilinmeyen alan 422,
tartışmadaki mesaj 422, üye olmadığı herkese açık kanalda kapatma 409;
uygulama anahtarıyla iki uç da 403. Sabitlemeden sonra
`GET /workspace/sync?channel_id=` `channel_state.pin`'i o mesajla verdi.
OpenAPI'de iki uç `X-Dry-Run`, `dry_run` ve `on` gövde alanıyla
görünüyor. Sunucu günlüğünde uyarı yok; `lint`, `check_lang`,
`check_api_schema`, `test`, `build_js --check` temiz.

---

## 2026.4.10 — Ziyaretçi takibi: gelen site uyarısız okunuyor, arama terimi bozulmuyor, dizi parametresi sayfayı düşürmüyor (2026-10-14)

**Neden.** #380'in sandbox'ta gördüğü, kapsam dışı bıraktığı uyarı:
personel ana sayfası (`staff-home`) açılınca `get_page.php`'de "Undefined
variable $delimiter". Değişken `init_tracking()`'de, yeni ziyaretçi kaydı
açılırken gelen sitenin arama motoru olup olmadığına bakan `if/elseif`
zincirinde yalnız eşleşen bir motor için kuruluyordu. Arama motoru
olmayan her siteden (Facebook, DuckDuckGo, bir e-posta uygulaması) gelen
yeni ziyaretçi bu yüzden günlüğe bir uyarı yazıyordu. Sandbox'ta
personel ana sayfasında görünmesinin nedeni ayrı bir hataydı: site
`127.0.0.1:8000` gibi portlu bir adreste açılınca `HTTP_HOST` portu
taşıyor, `parse_url()`'in verdiği referer host'u taşımıyor; karşılaştırma
tutmadığı için sitenin kendi sayfası "gelen site" sayılıyordu. Uyarının
kendisi görünen bir şeyi değiştirmiyordu: tanımsız değişken boş sayıldı,
arama terimi zaten okunmayacaktı; sayfa çıktısı aynı (`init.php`'deki
global `$delimiter` yol ayracıdır ve fonksiyonun içinde görünmez).

Aynı blokta aynı kalıp ve iki görünür hata daha çıktı, hepsi sandbox'ta
eski kodla üretildi; 2026.4.9'da da var:

- Google bugün arama terimini göndermiyor (referer
  `https://www.google.com/`); `query` anahtarı ve `q` değeri olmadığı için
  her Google ziyaretçisi iki uyarı yazıyordu. Host'u olmayan referer
  (`foo`, `/yol`, `https:///`) iki uyarı daha ekliyordu.
- Arama terimi parametresi dizi olarak gelince (`?q[]=x`) `trim()` PHP 8'de
  `TypeError` verdi: ziyaretçi ilk sayfada hata sayfası (HTTP 500) gördü.
  Motor adı adresin tamamında aranıyordu; `https://baska-site/www.google/?q[]=x`
  gibi bir sayfadaki bağlantıya tıklayan herkes buna düşüyordu.
- Arama terimi iki kez çözülüyordu (`parse_str()` zaten çözüyor, ardından
  `urldecode()`): Ziyaretçi raporunda "c++ 100%" araması "c   100%" oldu.
- Motor adı adresin tamamında, kalıplar da çapasız arandığı için
  `www.flask.com` "Ask.com", adresinde "www.google" geçen her site "Google"
  sayılıyordu.
- Referer oturuma ilk sayfada yazılıyor, ama host ve terim o isteğin
  `$_SERVER['HTTP_REFERER']`'ından okunuyordu. İlk sayfa kaydı açmadan
  bitince (yukarıdaki 500) sonraki istekte "Undefined variable
  $http_referer_parsed_url" uyarısı çıktı ve host boş yazıldı.

**Ne değişti.** `$delimiter` zincirden önce boş başlıyor. Host ve sorgu
oturumdaki referer'dan, `parse_url()` ile okunuyor; host'u olmayan
referer boş host veriyor. Motor kalıpları adresin tamamına değil host'a
uygulanıyor, `ask.com` kalıbı alan adının sonuna çapalandı. Sorgu yoksa
terim aranmıyor; değer metin değilse terim boş kalıyor; `urldecode()`
kaldırıldı. Sitenin kendi adresiyle karşılaştırmada `HTTP_HOST`'tan port
düşülüyor. Yalnız `get_page.php`'deki `init_tracking()` değişti; şema, dil
dosyası ve JS değişikliği yok. Dosyanın geri kalanında aynı kalıp
görülmedi: sandbox'taki 181 sayfa oturumsuz ve yönetici oturumuyla
açıldı, `get_page.php` uyarı yazmadı (başka dosyalardaki uyarılar ayrı).

**Doğrulandı (sandbox, MariaDB, PHP 8.3).** Her istekte yeni ziyaretçi
kaydı açılsın diye son yarım saatin kayıtları eskitildi; her referer'la
yeni bir oturumda personel ana sayfası açıldı (yeni kodda ardından aynı
oturumda ana sayfa da). Eski kod
(15 referer: boş, Facebook, DuckDuckGo, `android-app://`, Google terimli
ve terimsiz, Bing, Yahoo, `foo`, `/yol`, `https:///`, sitenin kendi portlu
adresi, `baska-site/www.google/?q[]=x`, `flask.com`, "c%2B%2B 100%25"):
16 uyarı ve bir 500, 500'ün ardından gelen istekte 5 uyarı daha; terim
"c   100%", `flask.com` Ask.com, `baska-site` Google, kendi adres gelen
site. Yeni kod (aynı 15 + `google.com.tr` terimli, Bing terimli,
`www.google.com/search?q[]=x`): uyarı ve hata yok, her istek 302/200
(oturumsuz personel ana sayfası girişe yönlendiriyor); terim
"c++ 100%", Google/Bing/Yahoo terimleri doğru, `flask.com` ve `baska-site`
motor değil, kendi adres kaydedilmedi.
Yönetici oturumuyla personel ana sayfası DuckDuckGo referer'ıyla 200,
uyarısız. `tools/test.php` 835/835; lint, dil, bağlama, API şeması, kopya
denetimleri ve `build_js.php --check` geçti. Doğrulanamadı:
dev.pinegrap.com'daki gerçek ziyaretçiler.

---

## 2026.4.10 — Dış API: kanal görselinin küçük kopyası, dosyada Range; panelde yazılamayan mesajın dosyası geri alınıyor (2026-10-14)

**Neden.** #378 (mobil API planının M5 dilimi) kanal dosyasını jetonla
indirme ucunu açtı ve dört parçayı kapsam dışında bıraktı: görselde küçük
önizleme yoktu (liste gösteren telefon her fotoğrafın aslını indiriyordu),
`Range` yoktu (iOS AVPlayer adresten oynatırken parça ister; uygulama videoyu
önce bütün indirmek zorundaydı), panelin ataç yolu mesaj yazılamadığında
dosyayı Dosya Yöneticisi'nde sahipsiz bırakıyordu (API yolu geri alıyordu)
ve multipart yüklemede `Idempotency-Key` parmak izi dosyayı değil yalnız
`name` ve `text`'i kapsıyordu.

**Ne değişti.**
- `GET /workspace/messages/{id}/file?size=N` (1–4096): görselin küçük
  kopyası; uzun kenarı 160, 320, 640, 1280 ya da 1920'nin N'ye eşit ya da
  büyük ilki (en çok 1920, `ws_file_preview_edge()`). Sabit basamaklar
  diskte görsel başına basamak kadar kopya tutar ve çağıranın her ekran
  genişliği için yeniden çizdirmesini önler. Çizim dosya yöneticisinin
  optimize / boyutlandır motoru (`pg_process_image_file()`, `write =>
  false`, kalite 80): görselin kendi biçiminde, EXIF dönüşü uygulanmış,
  metadata'sız. Kopya `data/temp/workspace_previews/` altında, adı
  saklanan dosyanın adı + boyutu + değişme anı + basamağın SHA-1'i; aynı
  adla değiştirilen dosya yeni kopya alır. Ara dosyaya yazılıp taşınır
  (yarım kopya okunmaz); yeni kopya yazılırken 50'de bir 30 günden eski
  kopyalar süpürülür (`ws_file_preview_sweep()`). Görsel zaten küçükse,
  küçültülemiyorsa (hareketli GIF, kütüphanenin okumadığı biçim, bellek
  yetmezse) ya da kopya yazılamazsa asıl gider. Görsel olmayan dosyada
  `size` 422. Erişim kuralı indirmeyle aynı (mesajı okuyabilen).
- `api_send_file()` (`includes/api/response.php`) artık `Range`'i
  karşılıyor: tek aralık 206 + `Content-Range`, aralık dosya sonundan
  sonra başlıyorsa 416 + `Content-Range: bytes */boyut`; her cevapta
  `Accept-Ranges: bytes`. `If-Range` ETag'i tutmuyorsa (ya da tarihse;
  bu çıkış `Last-Modified` göndermiyor) dosyanın tamamı 200 ile gider.
  Birden çok aralık, başka birim ya da okunamayan başlık yok sayılır ve
  dosyanın tamamı gider (RFC 9110 izin veriyor). `If-None-Match` önce
  bakılır (304). Parça 1 MB'lık dilimlerle okunur, bellekte bütün
  tutulmaz (`api_send_file_part()`). Ayrıştırma saf fonksiyon:
  `pg_http_byte_range()` (`includes/fn/files.php`). OpenAPI'de `binary`
  rotalara `Range` / `If-Range` başlıkları ve 206 / 416 cevapları eklendi;
  `api_status_text()` 206 ve 416'yı tanıyor.
- Panelin `ws_attach`'ı ve düzenlenmiş kopyayı yanıt olarak gönderen
  `ws_message_file_send()` mesaj yazılamazsa (hız sınırı, 4000 karakteri
  aşan metin) dosyayı `ws_upload_discard()` ile geri alıyor; API yolu
  zaten böyleydi.
- `Idempotency-Key` parmak izi `'form_file'` rotasında form parçasının
  adını, boyutunu ve SHA-256'sını da kapsıyor (`api_idempotency_input()`,
  `includes/api/idempotency.php`); aynı anahtar, aynı ad ve metinle başka
  dosya artık ilk mesajla cevaplanmıyor, 409 `idempotency_key_reused`.
  Aynı dosyanın tekrarı yine ilk cevabı alıyor. Özet yalnız anahtar
  gönderildiğinde hesaplanır.

Şema değişikliği yok, yeni izin kapsamı yok, MCP aracı yok (iki rota
`'mcp' => false` kalıyor). Yeni metin `tr.json`'da (1 anahtar).

**Bilerek yapılmayan.** Kopyalar her istekte değil yalnız yeni kopya
yazılırken süpürülüyor; silinen mesajın kopyası en geç 30 gün sonra
gidiyor (API onu zaten vermiyor: dosya satırı ve dosya yoksa 404). BMP'nin
kopyası BMP kalıyor (biçim dönüştürülmüyor). `HEAD` isteği API'de
yönlendirilmiyor (405, eskisi gibi); oynatıcılar `GET` + `Range` kullanıyor. Panelin
`get_file.php` yolu `Range` desteklemiyor; bu dilim yalnız API çıkışına
dokundu.

**Doğrulama.** `tests/http_byte_range_test.php` (yeni: aralık biçimleri,
dosyaya kırpma, sonu aşan aralık, yok sayılan başlıklar) ve
`tests/workspace_api_test.php`'ye üç test (`size` parametresi, basamaklar,
küçük ya da görsel olmayan dosyada asıl). Sandbox'ta uygulama anahtarıyla:
2400×1600 JPEG `size=300` → 320×213 JPEG, ikinci istek aynı ETag, ETag ile
304; `size=3000` → 1920×1280; `size=0` ve `size=99999` 422; videoda
`size` 422; asıl dosya bayt bayt aynı. 1,8 MB video: tamamı 200 +
`Accept-Ranges`, `bytes=0-1` ve `bytes=1000000-` 206 doğru `Content-Range`
ve bayt bayt aynı, sonu aşan 416, `If-Range` tutarken 206, tutmazken 200,
jetonsuz 401. Aynı anahtarla aynı dosya iki kez 201 ve aynı mesaj, başka
dosya 409; tabloda tek mesaj ve tek dosya. Panelde (`api.php`,
`ws_attach`): başarılı ek 1 dosya + 1 mesaj; 20000 karakterlik metin ve
hız sınırı hataları sonrası dosya tablosu ve diskte değişiklik yok. JPEG,
PNG, WebP ve GIF kopyaları ayrıca CLI'da çizildi. Sunucu günlüğünde hata
yok.

---

## 2026.4.10 — Dış API: kanal bildirim düzeyi, oylamaya oy ve tartışmaya katılma / ayrılma (2026-10-14)

**Neden.** Mobil API planının M6 dilimi
(`docs/_plan_mobil_api_claude_kanali.md`). Kanalı sessize almak, oylamaya
oy vermek ve bir mesajın tartışmasına katılmak ya da ayrılmak yalnız
panelde vardı (`ws_channel_notify`, `ws_poll_vote`, `ws_thread_join` /
`ws_thread_leave` eylemleri); API'nin mesaj nesnesinde oylama hiç
görünmüyordu, kanal nesnesinde de kişinin seçtiği düzey yoktu.

**Ne değişti.**
- `POST /workspace/channels/{id}/notify` {`notify`: `all` | `mentions` |
  `none`}: panelin `ws_channel_set_notify()`'ı. Gövde alanının adı plandaki
  `level` değil, kanal nesnesindeki yeni `notify` alanıyla aynı: uygulama
  okuduğu alanı geri yazar. Kişinin içinde olmadığı herkese açık kanalda
  onu kanala katar (panelin yardımcısı böyle); özel kanal ya da içinde
  olmadığı tartışma 409 `not_member`. `WorkspaceChannel`'a `notify` alanı
  eklendi; kanal listesi ve eşitleme de taşıyor (eşitlemenin liste işareti
  sunulan listenin `crc32`'si olduğu için düzey değişince liste yeniden
  gelir; eski imleçle de bir kez).
- `POST /workspace/polls/{id}/vote` {`option_ids`}: panelin
  `ws_poll_vote()`'u. Boş liste oyu geri alır. Bilinmeyen seçenek ya da tek
  seçimli oylamada birden çok seçenek 422 (panel sessizce süzüyordu; API
  istemcisi hatasını görsün diye reddediyor), kapalı ya da süresi geçmiş
  oylama ve konuşmanın önceki sürümündeki oylama 422, yalnız okuyabilen
  403. Süresi geçmiş oylamaya gelen gerçek oy, panelde olduğu gibi onu
  kapatır (sonuç kanala yazılır); prova kapatmaz. Cevap oylamanın mesajı.
  Denetimler `ws_poll_vote_check()`'e alındı (`interact.php`), panelin
  `ws_poll_vote()`'u da onu kullanıyor; prova aynı retleri alır.
- Mesaj nesnesinde `poll` (`WorkspacePoll?`, `ws_api_poll_present()`,
  panelin `ws_polls_map()`'inden): `id`, `multiple`, `anonymous`, `closed`,
  `closes_at`, `voters`, `voted`, `can_vote`, `options` (`id`, `label`,
  `count`, `mine`, `leading`, `voters`; gizli oylamada `voters` boş,
  misafirin `user_id`'si `null`), `result_message_id`. Liste ve eşitleme
  oylamaları bütün sayfa için tek seferde okur.
- `POST /workspace/threads/{id}/join` ve `/leave`: panelin
  `ws_thread_join()`'i ve ayrılma mantığı. Ayrılma `actions.php`'de satır
  içindeydi; `ws_thread_leave()` olarak `threads.php`'ye alındı, panel de
  onu çağırıyor (son ayrılan tartışmayı sonuçlandırır). Tartışma, içinde
  olunmasa da üst kanalı okunabiliyorsa bulunur
  (`ws_api_thread_room_or_404()`); zaten içindeyken katılma ve içinde
  değilken ayrılma hata değil, tartışmayı döndürür. Katılamama sebebi
  ayrı ayrı söylenir: sonuçlanmış tartışma ve arşivlenmiş kanal 422,
  Kullanıcı rolü 403. Cevap `WorkspaceThread`.
- Dördü de yalnız cihaz oturumundan (`ws_api_person_only()`; uygulama
  anahtarı 403, OAuth bağlantısı kabul: okundu işareti ve okundu onayı
  gibi kişinin kendi cevabı), dördü de `dry_run` alır, hiçbiri MCP aracı
  değil (`'mcp' => false`): panelde hiçbir asistan bunları yapmıyor ve
  bağlayıcının araç listesi değişmesin.

Şema değişikliği yok, yeni izin kapsamı yok (`workspace:write`), `tr.json`'da
6 yeni metin, JS değişmedi. Site Günlüğü'ne satır yazılmaz: panel de bu
dört eylemi günlüğe yazmıyor.

**Bilerek yapılmayan.** Oylama açma, değiştirme ve kapatma API'de yok
(plan yalnız oyu istiyordu). Kanalın panel menüsündeki diğer kişisel
tercihleri (sabitleme, sıra, grup) bu dilime girmedi.

**Doğrulama.** `tests/workspace_api_test.php`: dört rota (yöntem, yol,
kapsam, cevap, `dry_run`, MCP aracı değil), `WorkspacePoll` kayıtlı,
oylama sunumu (gizli oylamada isim yok, misafirin `user_id`'si `null`,
süresiz oylamada `closes_at` `null`, alanlar şemayla aynı sırada).
`tools/check_api_schema.php` `ws_api_poll_present()`'ı da karşılaştırıyor.
Sandbox'ta ekip cihazları oturumuyla: `notify` prova (`joins: false`),
gerçek (`notify: none` liste ve eşitlemede), geçersiz değer ve eksik alan
422, uygulama anahtarı 403, olmayan kanal 404; kanaldan çıkınca `notify`
kişiyi yeniden kattı (`joins: true`). Oylama: prova, oy (`mine`,
`voters`), iki kişinin oyu (sayı 2, iki isim), tek seçimlide iki seçenek
422, başka oylamanın seçeneği ve sayı olmayan öğe 422, çok seçimli gizli
oylamada iki seçenek (isimsiz), boş listeyle geri alma, uygulama anahtarı
403, olmayan oylama 404; süresi geçince prova 422 ve oylama açık kaldı,
gerçek oy 422 ve oylama kapandı (sonuç kararı kanala yazıldı,
`result_message_id` doldu, `can_vote: false`). Tartışma: ikinci personel
prova, katılma (iki kişi, "tartışmaya katıldı" satırı), yeniden katılma
aynı cevap, tartışmanın kimliğiyle `notify: none`, uygulama anahtarı
403, düz kanal kimliği 404, ayrılma (`can_join: true`), yeniden ayrılma
aynı cevap; son kişinin provası `concludes: true`, gerçek ayrılma
tartışmayı sonuçlandırdı (`closed`, `kept_until` 30 gün sonra), sonra
katılma 422. OpenAPI'de dört yol ve `WorkspacePoll` var. Sunucu
günlüğünde uyarı yok; `lint`, `check_lang`, `check_api_schema`, `test`,
`build_js --check`, `check_copies` temiz.

---

## 2026.4.10 — Dış API: eşitlemede zil sayısı, gelen kutusunun değişim anı, sabitlenmiş mesaj, sekme sayıları ve pano işareti (2026-10-14)

**Neden.** #374 (`GET /workspace/sync`, aşağıda) panelin `ws_sync`'indeki
kanal ekranına özgü parçaları ve zil sayısını bilerek dışarıda bıraktı:
telefon uygulaması sabitlenen mesajı, sekmelerin yanındaki sayıları ve
panonun değiştiğini öğrenmek için ayrı çağrı yapacak bir uç bulamıyordu,
zil sayısı için de her yoklamada `GET /notifications/unread-count`'u ayrıca
çağırmak zorundaydı. #379 gelen kutusuna `changed_since` ekledi, ama
eşitleme cevabı kutunun ne zaman değiştiğini söylemiyordu.

**Ne değişti.**
- `notifications_unread` (zil sayısı, `api_notifications_unread_count()`,
  `GET /notifications/unread-count`'un sayısı): yalnız arayan "Kendi hesabı"
  okuma iznini (`account:read`) taşıyorsa, yoksa `null`
  (`ws_api_sync_bell()`; `GET /auth/me`'nin `unread`'i gibi reddetmez,
  boş bırakır). Yeni izin kapsamı açılmadı: zilin izni zaten `account:read`.
  Ekip cihazları oturumunda hep vardır (`api_device_scopes()`); uygulama
  anahtarı ve OAuth bağlantısında yalnız operatör ya da kişi verdiyse.
  Bu izni taşımayan mevcut uygulamalara kendiliğinden verilmez.
- `inbox_changed_at`: gelen kutusunda bir satırın en son yazıldığı,
  yeniden gönderildiği ya da okunduğu an (`ws_api_inbox_changed_at()`,
  `api_inbox.php`; boş kutuda `null`). #379'un `changed_since`'i ile
  eşleşir: değişince uygulama `GET /workspace/inbox?changed_since=<önceki
  değer>` çağırır. Okunmamış satırların en yeni `created_at`'i ile bütün
  satırların en yeni `read_at`'i (yalnız okunmamış satır yeniden
  gönderilir, okunan satır okunmadan önce yazılmıştır), ikisi de
  `idx_user` üzerinden; `MAX(created_at)` bütün kutuyu taramaz.
  `workspace:read` yeter, `inbox_unread` gibi herkese.
- `channel_state` (`ws_api_sync_channel_state()`): yalnız ekip cihazları
  oturumundan, `channel_id` verilince ve kanal okunabiliyorsa; içindekiler
  önceki cevaptan beri değiştiyse gönderilir, yoksa `null`. Alanlar:
  `channel_id`; `pin` (sabitlenen mesaj `WorkspaceMessage` olarak,
  `pinned_by`, `pinned_at`, `hidden` — kişi panelde kapattıysa);
  `tab_counts` (`ws_channel_tab_counts()`: kararlar ve notlar, açık
  görevler, özet yazılı mı; tartışmada `null`); `board`
  (`ws_channel_board_stamp()`: kanalın görevleri, kararları, dosyaları,
  sabitlenen mesajı ya da özeti değişince değişen işaret; tartışmada ve
  misafir odasında `null`). Panelin okuduğu yerden okunur:
  sabitlenen mesaj için `ws_channel_pinned_message()` ve
  `ws_channel_pin_hidden()` `pins.php`'ye alındı, panelin
  `ws_channel_pin_present()`'ı da onları kullanıyor.
- İmleç: kanal durumunun işareti (`p`, `crc32`, kanal kimliği işaretin
  içinde, böylece iki boş kanal aynı sayılmaz) yalnız durum gönderilince
  yazılır. Durum taşımayan çağrının imleci eskisiyle bayt bayt aynı; eski
  imleç (`p` yok) okunur ve durum bir kez gönderilir. Hiçbir şey
  değişmediyse cevap aynı kalır, ETag ile 304 eskisi gibi. Zil sayısı
  imlece girmez (`inbox_unread` gibi): değişince cevap değişir, imleç aynı
  kalır.
- OpenAPI: `WorkspaceSyncChannelState`, `WorkspaceSyncPin`,
  `WorkspaceSyncTabCounts`; rota açıklaması iki alanı anlatıyor. Rota
  `'mcp' => false` kalıyor.

Şema değişikliği yok, yeni izin kapsamı yok, yeni metin yok, JS değişmedi.

**Bilerek yapılmayan.** Kanal durumu uygulama anahtarına ve OAuth
bağlantısına verilmez: ekranı olmayan bir entegrasyonun yoklaması bu
okumaların (iki sayım, iki pano okuması, sabitlenen mesaj) bedelini
ödemesin; "ekranda sayılma" kuralıyla aynı çizgi. Panelin `ws_sync`'indeki
görev kartı HTML'i, misafir şeridi, paylaşım durumu, kişinin programlanmış
mesaj sayısı ve `scheduled_due` API'ye alınmadı (HTML ve panelin kendi
tetikleyicisi). Sabitleme ve sabitlenmiş mesajı kapatma uçları yok.

**Doğrulama.** `tests/workspace_api_test.php`: imleç gidiş-dönüşü `p` ile
ve `p`'siz, eski imlecin okunması ve aynı baytlarla yeniden yazılması,
bozuk `p`'nin reddi, kanal durumu sunumu (alanlar şemayla aynı, tartışmada
`null`'lar, iki kanalın işareti farklı, `hidden` işareti değiştiriyor),
`account:read`'siz zil `null`, boş alanların sunumu `null`. Sandbox'ta ekip cihazları oturumuyla: ilk
çağrıda `channel_state` (pin `null`, sayılar 0, pano işareti) ve
`notifications_unread: 0`; ikinci çağrıda `channel_state: null`, aynı
imleç, ETag ile 304. Mesaj sabitlenince `pin` (mesaj 2, `pinned_by`,
`pinned_at`) geldi ve pano işareti değişti; kanala görev açılınca
`tab_counts.tasks: 1`, pano işareti yine değişti; `pin_hidden` yazılınca
`hidden: true`. Bildirim satırı eklenince `notifications_unread: 1`, imleç
aynı kaldı. Uygulama anahtarıyla `notifications_unread: null`,
`channel_state: null` ve imlecinde `p` yok. `p`'si çıkarılmış imleçle
durum bir kez geldi. Gelen kutusuna satır eklenince `inbox_changed_at`
satırın anı oldu ve `changed_since` o değerle satırı (`read: false`)
verdi; `POST /workspace/inbox/read` sonrası an okuma anına geçti,
önceki değerle `changed_since` satırı `read: true` verdi; değişiklik
yokken 304 yine geldi. Tartışmanın kimliğiyle durum `pin`, `tab_counts`,
`board` `null`. Panelin Çalışma Alanı ekranı Chromium'da açıkken
`ws_sync` cevabında `pin` (id 2, `hidden: true`) eskisi gibi geldi,
konsolda hata yok. Sunucu günlüğünde bu değişiklikten uyarı yok;
`lint`, `check_lang`, `check_api_schema`, `test`, `build_js --check`
temiz.

---

## 2026.4.10 — Dış API: gelen kutusunda sonradan değişen satırlar, changed_since (2026-10-14)

**Neden.** #377 (mobil API planının M4 dilimi) gelen kutusunu API'ye açtı,
ama uygulamanın elindeki satırlarda sonradan ne değiştiğini (yeni gelen
satır, panelde, zilde ya da başka bir cihazda okundu işaretlenen satır,
değişen notun yeniden gelen bildirimi) tek istekle almanın yolu yoktu:
uygulama `GET /workspace/sync`'in `inbox_unread` sayısı değişince ilk
sayfayı baştan okuyordu. Kanal mesajlarında aynı iş için #371 (M2)
`changed_since` + `since_id` vermişti; uygulama iki yerde aynı deseni
kullansın diye gelen kutusuna da aynısı eklendi.

**Ne değişti.**
- `GET /workspace/inbox?changed_since=<an>`: o andan beri yazılan, yeniden
  gelen ya da okunan satırlar, eskiden yeniye (`id` sırası), her biri
  şimdiki haliyle (`read` güncel). `since_id` sayfalar: `limit` kadar
  satır dönerse son satırın kimliğiyle yeniden sorulur (M2'nin kuralı).
  `unread` ve `cursor` bu kipte yok sayılır; `changed_since` olmadan
  `since_id` bir şey değiştirmez. An ISO-8601 ya da Unix zamanı; sitenin
  saatiyle, önceki cevabın `Date` başlığı.
- Şema adımı yok. `ws_inbox` satırı yalnız yazılırken (`created_at`),
  değişen notun bildirimi okunmamışken yeniden gelirken (`notes.php`,
  `created_at` şimdiye taşınır) ve okunurken (`read_at`) değişir; satırı
  yerinde değiştiren her yer bu iki damgadan birini yazıyor (gelen kutusu
  okundu, zil `pg_notification_mark_read()`, kanal okundu
  `ws_channel_mark_read()`, görevi açma, okundu bilgisi, görünürlük
  süzgeci). Koşul `ws_api_inbox_changed_where()`: `created_at >= an OR
  read_at >= an`.
- Kişinin artık göremediği şeyle ilgili satır atlanır ve sayfa arkasındaki
  satırlarla doldurulur (`ws_api_inbox_changed()`), böylece "limit kadar
  satır döndü, devam et" kuralı bozulmaz. İlk okuma sayfa kadar satır
  alır; gizli satıra rastlarsa 100'erli adımlarla, en çok 20 tur sürer.
- Görünürlük ve Claude süzgeci `ws_api_inbox_reachable_rows()`'a ayrıldı;
  `ws_api_inbox_present_rows()` onu kullanır. Değişenlerde yalnız sayfaya
  giren satırlar anlatılır.
- MCP: `workspace_inbox_list` aracı `changed_since` ve `since_id`
  alanlarını rotadan kendiliğinden aldı.

Şema değişikliği yok, yeni izin kapsamı yok, yeni metin yok.

**Bilerek yapılmayan.** Tamamen silinen satır (silinen mesajdaki anılma,
silinen ya da paylaşımı kaldırılan not, süresi dolup temizlenen tartışma)
iz bırakmadığı için değişenlerde gelmez; kişinin artık göremediği şeyle
ilgili satır da gelmez (liste kuralı). Uygulama elindeki satırı
`GET /workspace/inbox/{id}` 404 verince atar. Silineni bildirmek için
panelin silmelerini işarete çevirmek ya da ayrı bir silinenler tablosu
gerekirdi; ikisi de bu dilimin dışında. `GET /workspace/sync` hâlâ
yalnız `inbox_unread` taşır: aynı aralıkta bir satır okunup bir yenisi
gelirse sayı değişmez, uygulama `changed_since`'i kendi aralığıyla da
çağırabilir (değişiklik yoksa ETag ile 304). Eşitleme cevabına gelen
kutusunun değişim işaretini eklemek ayrı küçük iş.

**Doğrulama.** `tests/workspace_api_test.php`'ye iki test (rota
parametreleri ve MCP aracı; `ws_api_inbox_changed_where()`). Sandbox'ta
"Ekip cihazları" oturumuyla: `changed_since` yalnız yeni ve yeniden gelen
satırları verdi, değişmemiş okunmuş ve okunmamış satırları vermedi;
`POST /workspace/inbox/read` sonrası okunan satır `read: true` ile geldi;
`limit=1` + `since_id` üç sayfada üç satırı verip boş döndü; `unread=true`
yok sayıldı; Unix zamanı da kabul edildi; bozuk an 422 `changed_since`;
aynı istek `If-None-Match` ile 304. Arka arkaya 150 gizli satırın
ardındaki satır `limit=1` ve `limit=2` ile tek çağrıda geldi, gizli
satırlar okundu sayıldı, `sync` `inbox_unread` doğru kaldı. Sunucu
günlüğünde hata yok.

---

## 2026.4.10 — Dış API: Çalışma Alanı kanalına dosya gönderme ve dosyayı jetonla indirme (2026-10-14)

**Neden.** Mobil API ve Claude kanalı planının M5 dilimi
(`docs/_plan_mobil_api_claude_kanali.md`). Panel kanala ataç düğmesiyle
dosya koyuyor (`ws_attach`); API'de kanala yükleme ucu yoktu (`POST /files`
yalnız görsel / PDF alıyor ve operatörün klasörüne yazıyor). Mesajdaki
`file.url` da `get_file.php`'ye gidiyor; o yalnız tarayıcı oturumuna
bakıyor, Bearer jetonunu tanımıyor, bu yüzden telefon uygulaması kanal
dosyasını açamıyordu.

**Ne değişti.**
- `POST /workspace/channels/{id}/files` (`workspace:write`): dosya
  `multipart/form-data` içinde `file` adlı parçayla (büyük dosyanın yolu)
  ya da JSON gövdede `content_base64` + `name` ile; isteğe bağlı `text`.
  Panelin yolu: `ws_store_upload()` + `ws_message_send()`; aynı tür
  kuralı (program, betik, SVG / HTML / XML / JS yok, görsel gerçekten
  görsel olmalı), en çok 20 MB ya da sunucunun daha düşük sınırı, dosya
  kanalın Dosya Yöneticisi klasörüne (tartışmanınki kanalınkine).
  Kim olarak yazıldığı mesaj yazma ucunun kuralı: uygulama anahtarı ve
  OAuth uygulama adına, "Ekip cihazları" oturumu kişinin adına ve onun
  dakikada yirmi mesaj sınırıyla. Sınır dosya kaydedilmeden önce
  denetleniyor; mesaj yine de yazılamazsa dosya geri alınıyor
  (`ws_upload_discard()`), artık dosya kalmıyor. Cevap 201 ile mesaj.
  Panelin `ws_attach`'ı mesajı yazamazsa dosyayı bırakıyor; ona
  dokunulmadı.
- `GET /workspace/messages/{id}/file` (`workspace:read`): dosyayı JSON
  içinde değil kendi baytlarıyla verir; telefon görseli çözmeden
  gösterir, 20 MB'lık dosya bellekte üç kez tutulmaz. Erişim kuralı
  mesajdan gider: mesajı okuyabilen (`ws_can_read_channel()`, Claude'un
  uygulamasında ayrıca `ws_api_channel_or_404()`) dosyasını da alır;
  herkesten silinmiş mesajın dosyası yok (404). Planın önerdiği
  `get_file.php` kuralını ortak fonksiyona almak gerekmedi: o kural
  dosya adından kanala gidiyor, bu uç ise mesajı zaten tanıyor; kopya
  oluşmadı. Görsel, ses, video ve PDF kendi türüyle `inline`, gerisi
  `application/octet-stream` ve `attachment`; tür kuralı misafir
  odasıyla ortak `ws_file_type()`'a alındı (`ws_guest_file_send()` onu
  kullanıyor, davranışı aynı). ETag dosyanın adresi, boyutu ve
  değişme anından; `If-None-Match` 304.
- `WorkspaceMessage.file`'a `kind` (image, video, audio, pdf, file),
  `size` (bayt) ve `api_url` (bu indirme ucu) eklendi; `url` olduğu gibi
  kalıyor. Mesaj okuyan her uç (liste, eşitleme, yazma cevapları) yeni
  alanları veriyor.
- API çekirdeğinde genel yetenekler: `api_send_file()`
  (`includes/api/response.php`, `api_send()`'in dosya için ikizi: bütün
  tamponları boşaltır, aynı günlük satırını yazar, `Cache-Control:
  private, no-store`, `nosniff`, RFC 6266 dosya adı); rota bayrağı
  `'binary' => true` (OpenAPI 200'ü `application/octet-stream` yazıyor,
  `fields` parametresini koymuyor; `tools/check_api_schema.php`
  `returns` istemiyor) ve `'form_file' => 'file'` (OpenAPI isteğe
  `multipart/form-data` ekliyor). `api_request_input()`: `post_max_size`'ı
  aşan form PHP'ce atılınca ham gövde url-encoded diye okunup sınır
  satırı bilinmeyen alan sanılıyordu (422 "... adında bir alan kabul
  etmiyor"); artık form boş sayılıyor ve uç "Dosya N MB'tan büyük"
  diyor.
- `ws_store_upload()` base64'ü çözüp yeni `ws_store_upload_bytes()`'a
  veriyor; multipart dosya doğrudan ona gidiyor. Panelin davranışı aynı.
- İki rota `'mcp' => false`: dosya baytları bir modelin işine yaramaz;
  MCP araç listesi değişmedi, bağlayıcıyı yeniden bağlamak gerekmez.

Şema değişikliği yok, yeni izin kapsamı yok. Yeni metin `tr.json`'da
(3 anahtar).

**Bilerek yapılmayan.** Görselde `?size=` küçük önizleme ve video için
`Range` (iOS AVPlayer adresten oynatırken ister; uygulama dosyayı önce
indirip yerelden oynatabilir) sonraki küçük dilim. Panelin `ws_attach`'ı
başarısız mesajda dosyayı bırakmaya devam ediyor. Multipart istekte
`Idempotency-Key` parmak izi dosyayı değil `name` ve `text`'i kapsıyor.

**Doğrulama.** `tests/workspace_api_test.php`'ye iki test (rotalar,
kapsamlar, MCP dışında oluşları, indirmenin `binary` oluşu;
`ws_file_type()`). Sandbox'ta: "Ekip cihazları" ile multipart PNG
(Türkçe adla) 201, kişinin adıyla, dosya kanalın klasöründe; uygulama
anahtarıyla JSON base64 PDF (satır sonlu) 201, uygulama adıyla;
url-encoded base64 (`+` boşluğa dönmüş) 201. İndirme: PNG ve PDF bayt
bayt aynı, `Content-Type`, `inline`, iki biçimli dosya adı, ETag ile
304, kimliksiz 401, dosyasız / olmayan / silinmiş mesaj 404, üyesi
olunmayan özel kanalın dosyası 404 ve oraya yükleme 404. Ret: `.php`,
SVG, görsel olmayan `.png`, HTML içerikli `.txt`, bozuk base64, adsız
base64, ikisi birden, yalnız metinli form 422; 3 MB form
(`upload_max_filesize` 2M) ve 9 MB form (`post_max_size` 8M) "Dosya 2
MB'tan büyük" 422; hız sınırında 422 ve dosya tablosu değişmedi;
uygulama anahtarı sınıra girmiyor. Eşitleme (`GET /workspace/sync`)
mesajlarda yeni alanları verdi; OpenAPI belgesinde indirme 200'ü binary,
yükleme isteği JSON ve multipart. Sunucu günlüğünde yalnız PHP'nin
post_max_size uyarısı (beklenen).

---

## 2026.4.10 — Dış API: Çalışma Alanı gelen kutusu, GET /workspace/inbox (2026-10-14)

**Neden.** Mobil API ve Claude kanalı planının M4 dilimi
(`docs/_plan_mobil_api_claude_kanali.md`). Çalışma Alanı'nda bir şey
söylenen kişinin zil satırı (`GET /notifications`) konu olarak
`workspace_inbox` ve gelen kutusu satırının kimliğini veriyordu, ama API'de
bu kimliği okuyan uç yoktu: telefon uygulaması bildirime dokunulunca hangi
kanalı, mesajı ya da görevi açacağını bilemiyor, gelen kutusunu
listeleyemiyor ve okundu işaretleyemiyordu. Ayrıca zil satırlarının
`url`'si Çalışma Alanı satırlarında yazılım klasörünü iki kez taşıyordu.

**Ne değişti.**
- `GET /workspace/inbox` (`workspace:read`, yeni dosya
  `includes/workspace/api_inbox.php`): en yeniden eskiye; `unread=true`
  yalnız okunmayanlar, `limit` 1–100 (varsayılan 30), `cursor`
  (`api_cursor_encode()`, satır kimliğiyle). Her satır `WorkspaceInboxItem`:
  `kind`, panelin cümlesi (`title`, `body`, `ws_inbox_describe()`), tam
  panel adresi `url`, `read`, `actor_id` ve satırın neyle ilgili olduğu:
  `channel_id`, `parent_channel_id` (kanal bir tartışmaysa başladığı kanal,
  `ws_thread()`), `message_id`, `task_id`, `note_id`, `plan_item_id`
  (`ws_inbox.event_id`), `meeting_url` (başlamak üzere olan toplantının
  bağlantısı), `created_at`. `thread` türünde `message_id` tartışmanın
  başladığı mesajdır (üst kanalda); zamanlanmış eylemin bildirimi
  (`scheduled_notice`) eylemin yazıldığı kanalı ve mesajı, kişi o kanalı
  okuyabiliyorsa verir (panel satırının kuralı).
- `GET /workspace/inbox/{id}`: tek satır. Başkasının satırı ya da kişinin
  artık göremediği bir şeyle ilgili satır 404 ("Inbox item").
- `POST /workspace/inbox/read` {`ids` | `all`}: `ws_inbox_mark_read()`;
  zildeki karşılıkları da okunur (`ws_bell_sync_read()`), bekleyen push
  gönderilmez (`ws_push_still_relevant()`). Cevap `updated` + `unread`,
  `POST /notifications/read` gibi; başkasının kimliği ve zaten okunmuş
  satır atlanır. Yalnız cihaz oturumundan (`ws_api_person_only()`, kanal
  okundu işareti gibi): uygulama anahtarı 403. `dry_run` alır
  (`would: marked_read`).
- Görünürlük panelinkiyle aynı: `ws_inbox_list()`'in süzgeci
  `ws_inbox_visible_rows()`'a (`notify.php`) alındı, panel ve API onu
  kullanır; panelin listesi aynı. Kişinin artık göremediği kanal, görev,
  mesaj ya da notla ilgili satır gösterilmez ve okundu sayılır; sayfa bu
  yüzden `limit`'ten kısa dönebilir, imleç okunan satırları izler.
- Claude'un çalıştığı uygulama yalnız Claude'a sorulabilen kanallarla
  ilgili satırları görür (`ws_claude_channel_allowed()`).
- `GET /workspace/sync` açıklamasına gelen kutusunun nasıl okunacağı
  eklendi.
- [DÜZELTME] `GET /notifications`: Çalışma Alanı satırlarının `url`'si
  `https://site/pinegrap/pinegrap/workspace.php…` oluyordu.
  `ws_inbox_describe()` site kökünden başlayan bir yol (`/pinegrap/…`)
  veriyor, `api_notification_present()` ise kökten başlamayan her adresin
  önüne yazılım klasörünü ekliyordu. Kökten başlayan yolun önüne artık
  yalnız şema ve alan adı geliyor; diğer satırların göreli adresleri
  (`view_order.php?id=…`) eskisi gibi.
- MCP: üç yeni araç, rotalardan kendiliğinden: `workspace_inbox_list`,
  `workspace_inbox_get`, `workspace_inbox_read`.

Şema değişikliği yok, yeni izin kapsamı yok. Yeni metin `tr.json`'da
(1 anahtar).

**Bilerek yapılmayan.** Gelen kutusunda `changed_since` yok: uygulama
`GET /workspace/sync`'in `inbox_unread` sayısı değişince ilk sayfayı
yeniden okur. Satırlar panelde olduğu gibi tek tek anlatılır (satır başına
birkaç okuma), bu yüzden sayfa 100 satırla sınırlı. OAuth bağlantısının
okundu işaretlemesi kanal okundu işareti gibi serbest (cihaz satırı).

**Doğrulama.** `tests/workspace_api_test.php`'ye iki test (üç rota, kapsam
ve araç; `ws_api_inbox_url()`). Sandbox'ta: uygulama anahtarıyla kanal,
anılma, görev ve tartışma açılınca kişinin "Ekip cihazları" oturumu
`GET /workspace/inbox` ile dört satırı (`thread`, `assigned`, `mention`,
`invited`) doğru kimliklerle aldı (`thread`'de `channel_id` tartışma,
`parent_channel_id` kanal); `limit=2` iki sayfa, bozuk imleç 400
`invalid_cursor`; `GET …/inbox/{id}` 200, olmayan ve başkasının satırı
404. Okundu: uygulama anahtarı 403, boş gövde 422 `ids`, bilinmeyen alan
422, `X-Dry-Run` ve `?dry_run=true` 200 `would: marked_read` (hiçbir satır
değişmedi), `ids [2, 99]` → `updated 1, unread 3`, aynısı yeniden
`updated 0`, zilin okunmayanlarından da düştü, `sync` `inbox_unread 3`;
`all` → `updated 3, unread 0`, zil 0. Özel kanaldan çıkarılan kişinin
satırları listede yok, okundu işaretlendi, tek okuma 404. `GET
/notifications`'ın adresi düzeltmeden önce `/pinegrap/pinegrap/…`, sonra
`/pinegrap/workspace.php?channel=1&thread=2`. Panelin `ws_inbox_list()`'i
aynı satırları verdi; OpenAPI üç yolu ve `WorkspaceInboxItem`'ı, okundu
ucunda `X-Dry-Run`'ı gösteriyor; sunucu günlüğünde uyarı yok.

---

## 2026.4.10 — Dış API: açık uygulamanın eşitlemesi Pinegrap AI ve MCP ajanı isteğini sürdürüyor, IIS'te de (2026-10-14)

**Neden.** #373 ekip cihazından yazılan mesajda `@ai` ya da ajan olan bir
MCP sunucusunun takma adı anılınca isteği kuyruğa koydu, ama kuyruğu
yalnız sunucu yanıtı önce gönderebiliyorsa (PHP-FPM,
`fastcgi_finish_request()`) yanıttan sonra sürdürdü. IIS'te (dev ve müşteri
siteleri) ve mod_php'de istek, biri kanalı panelde açana ya da saatlik iş
çalışana kadar bekliyordu; telefonda kanalı açık tutan kişi yanıt
alamıyordu. Panelde işi açık ekran sürdürür: ekran kanaldaki bir mesajın
altında bekleyen Pinegrap AI ya da MCP ajanı isteği görünce `ws_ai_kick` ve
`ws_mcp_agent_kick` eylemlerini ayrı bir istekle çağırır
(`workspace.js`, `schedule`).

**Ne değişti.**
- `includes/workspace/api_sync.php`, `GET /workspace/sync`: ekip
  cihazları oturumundan (`ws_api_device_writes_as_person()`, `ws_sync_seen()`
  ile aynı koşul) `channel_id` ile çağrılınca ve kişi kanalı okuyabiliyorsa,
  `ws_api_sync_waiting()` kanalda `queued`, `sent` ya da `running` duran
  `ai` ve `mcp` isteklerine bakar (panel ekranının işareti) ve
  `ws_api_assistants_carry_on(…, true)` ile sürdürür. Kanal artık bir kez
  okunuyor; `$now` işten sonra alınıyor, böylece çağrının içinde yazılan
  yanıt ve değişen mesajlar aynı cevaba giriyor, imleç ondan sonrasını
  arıyor.
- `includes/workspace/api_resources.php`: `ws_api_carry_on_where()` (saf
  fonksiyon) yeri söyler: yanıtı önce gönderebilen sunucuda `after`
  (#373'teki kapanış fonksiyonu, panelin bütçesiyle), gönderemeyen sunucuda
  yalnız eşitlemede `here`, mesaj yazma ucunda hiç. `here`'de bir ajan
  çağrısı (`ws_mcp_agent_kick()` varsayılan bütçe 1 sn) ve bir model
  çağrısı (`ws_ai_kick(1)`) yapılır; eşitleme en çok o kadar tutulur, bir
  sonraki eşitleme sonraki adımı alır.
- İzinler ve sınır panelle aynı: istek, soranın yetkisiyle çalışır;
  Pinegrap AI site genelinde tek çağrıyı `GET_LOCK('pg_ws_ai_run')` ile
  tutar, işi başka yerde süren bir çağrı hemen döner; ajan istekleri tek
  tek sahiplenilir, iki çağrı aynı isteği göndermez. Uygulama anahtarının
  ve OAuth bağlantısının yoklaması kimse ekranda değil sayılır, işi
  sürdürmez. Eşitlemenin hız sınırı API'nin cihaz başı sayılan dakika
  sınırıdır (`api_rate_limit_check()`).
- API açıklaması (`includes/workspace/api.php`): `workspace.sync` bunu
  söylüyor; `workspace.messages.create` IIS'te işi "uygulamanın sonraki
  `GET /workspace/sync`'i" sürdürür diyor.
- `.claude/skills/pinegrap-dis-api/SKILL.md`: uzun iş başlatan uçlar için
  kural.

Şema değişikliği yok, yeni izin kapsamı yok, yeni metin yok, MCP aracı yok
(eşitleme `'mcp' => false`), JS değişmedi.

**Bilerek yapılmayan.** Ayrı bir "kuyruğu sürdür" ucu açılmadı: API mesaj
nesnesi isteğin durumunu taşımadığı için uygulama ne zaman çağıracağını
bilemezdi; eşitleme zaten birkaç saniyede bir geliyor. IIS'te iş sürerken o
eşitleme çağrısı model ya da ajan çağrısı kadar sürer (Pinegrap AI'da en
çok `WS_AI_CALL_TIMEOUT`, 55 sn); istemci beklemeden bırakırsa iş
`ignore_user_abort()` ile sürer, sonraki eşitleme sonucu getirir.
Claude'un kuyrukta bekleyen isteği (`ws_claude_tick()`) eşitlemede yeniden
denenmez; panelde de ekran açılışındaki `ws_tick`'e aittir. Kanal açık
değilken (yalnız kanal listesi) iş sürmez, panelde olduğu gibi.

**Doğrulama.** `tests/workspace_api_test.php`'ye bir test
(`ws_api_carry_on_where()`: FPM'de iki uçta `after`, IIS'te eşitlemede
`here`, mesaj yazmada boş). Sandbox'ta (PHP'nin kendi sunucusu,
`fastcgi_finish_request()` yok, yani IIS yolu) ekip cihazı oturumuyla,
kanala bayat bir `running` Pinegrap AI isteği ve olmayan sunucuya `queued`
bir MCP ajanı isteği yazılarak: `channel_id`'siz eşitleme ve development'ın
kodu ikisine de dokunmadı; yeni kodla `channel_id=1` eşitlemesi aynı
çağrıda AI isteğini bekçiyle "zamanında yanıt vermedi", ajan isteğini
sahiplenip "Bu MCP sunucusu bulunamadı" ile kapattı (iki kick de çalıştı).
Uygulama anahtarıyla (Basic) aynı çağrı isteğe dokunmadı; başka kanalda
bekleyen istek `channel_id=1` ile beklemede kaldı, `channel_id=2` ile
sürdü. İmleç, ETag ile 304 ve olmayan kanalda `gone: true` değişmedi.
Sunucu günlüğünde uyarı yok. Gerçek bir model ya da ajan çağrısı
denenmedi (anahtar ve ajan sunucusu yok); çağrılan kod panelin eylemleriyle
aynı.

---

## 2026.4.10 — Dış API: Çalışma Alanı mesajını yalnız kendi görünümünden silme ve mesaj uçlarında dry_run (2026-10-14)

**Neden.** #371 (aşağıda, "Çalışma Alanı mesajını düzeltme, silme, kanalı
okundu işaretleme…") iki parçayı bilerek dışarıda bıraktı: panelin
"Benden sil"i (`ws_delete` + `scope: me` → `ws_message_hide()`) API'de
yoktu, telefon uygulaması bir mesajı yalnız kendi görünümünden
kaldıramıyordu; ve düzeltme, silme, okundu işareti `X-Dry-Run` /
`?dry_run=true` ile denenemiyordu (400 `dry_run_unsupported`). Plan
(`docs/_plan_mobil_api_claude_kanali.md`) ikisini ayrıca tanımlamıyor;
aşağıdaki yorum panelin davranışına ve API'nin genel dry_run kuralına
(`includes/api/dry_run.php`) dayanıyor.

**Ne değişti.**
- `POST /workspace/messages/{id}/hide`: `ws_message_hide()` ile, panelin
  `can_hide` kuralı (kim yazmış olursa olsun okunabilen her mesaj, önceki
  sürümdeki mesaj da; çalışma alanının kendi satırı ve silinmiş mesaj 404).
  Diğerleri mesajı olduğu gibi görür, içindeki anılma zilden düşer. Geri
  alınamaz. Yalnız "Ekip cihazları" oturumundan (`ws_api_own_words_only()`,
  silme gibi); uygulama anahtarı ve OAuth bağlantısı kendi metniyle 403.
  `ws_api_own_words_only()`'e isteğe bağlı `$refusal` eklendi.
- `WorkspaceMessage`'a `hidden` (boolean): okuyan kişi mesajı kendisinden
  sildiyse true. Liste mesajı dışarıda bırakmıyor, işaretliyor: panel gibi
  süzseydi `limit` kadar dönmeyen sayfa `since_id` / `before_id` ile
  sayfalayan uygulamaya "bitti" derdi. Uygulama `hidden: true` olanı
  göstermez. Bayrak çağrının adına okuduğu kişiye göredir (uygulama
  anahtarında sahibi); içerik boşaltılmıyor, var olan entegrasyonların
  okuduğu değişmiyor. Gizleme mesajı `touch` etmiyor (kişiye özel; diğer
  okuyucuların eşitlemesi tetiklenmesin), bu yüzden `changed_since` onu
  getirmez; aynı kişinin başka cihazı bir sonraki tam okumada görür.
- dry_run: `workspace.messages.update`, `.delete`, `.delete_post`, `.hide`
  ve `workspace.channels.read` rotalarına `'dry_run' => true`; her biri
  ilk yazmanın hemen üstünde `api_dry_run_stop()` çağırıyor (`would`:
  updated, deleted, hidden, read). Denetimlerin aynı kodla yapılması için
  `ws_message_edit_check()`, `ws_message_delete_check()` ve
  `ws_message_hide_check()` ayrıldı (`ws_approval_input()` gibi);
  `ws_message_edit()`, `ws_message_delete()` ve `ws_message_hide()` önce
  bunları çağırıyor, panelin `ws_edit` / `ws_delete` davranışı aynı.
  Mesaj yazma ve tepki uçları bu PR'da dry_run almadı.
- MCP: yeni araç `workspace_messages_hide`; `workspace_messages_update`,
  `workspace_messages_delete` ve `workspace_channels_read` araçlarına
  `dry_run` alanı rotadan kendiliğinden geliyor. OpenAPI'de dört uçta
  `X-Dry-Run` ve `dry_run` parametresi.

Şema değişikliği yok, yeni izin kapsamı yok (`workspace:write`). Yeni
metin `tr.json`'da (1 anahtar).

**Bilerek yapılmayan.** Gizlemeyi geri alma yok (panelde de yok).
Gizlenen mesaj listeden çıkarılmıyor (yukarıdaki sayfalama nedeni). OAuth
bağlantısının kişi adına gizlemesi, düzeltme ve silme gibi karar 11'e (C5)
bağlı.

**Doğrulama.** `tests/workspace_api_test.php`'ye bir test (gizleme bir
araç, beş rota dry_run alıyor). Sandbox'ta "Ekip cihazları" girişiyle:
PATCH ve POST düzeltme `X-Dry-Run` ile ve `?dry_run=true` ile 200
`would: updated`, boş metin dry run'da da gerçekte de aynı 422 `text`,
uygulama mesajını düzeltme dry run'da 403, uygulama anahtarıyla dry run
403; DELETE ve POST `.../delete` dry run 200 `would: deleted`, uygulama
mesajı 403; okundu dry run 200 `would: read`, başka mesaj 422
`message_id`, uygulama anahtarı 403; gizleme dry run 200
`would: hidden`. Bunlardan sonra `ws_messages`, `last_read_id` ve
`ws_message_hides` değişmemişti. Gerçek gizleme: uygulama anahtarı 403,
kendi mesajı ve uygulamanın mesajı 200 `hidden: true`, ikinci kez 200,
sistem satırı, olmayan ve silinmiş mesaj 404; liste gizlenenleri
`hidden: true` ile döndürdü, diğerleri false. Mesaj yazma dry run'ı hâlâ
400 `dry_run_unsupported`. Panelde `ws_edit` (değişti, boş metin ve
uygulama mesajı reddedildi), `ws_delete` `scope: me` ve herkesten silme
eskisi gibi; sunucu günlüğünde uyarı yok.

---

## 2026.4.10 — Dış API: tek çağrıda eşitleme, GET /workspace/sync (2026-10-14)

**Neden.** Mobil API ve Claude kanalı planının M3 dilimi
(`docs/_plan_mobil_api_claude_kanali.md`). Panel açık bir kanalı birkaç
saniyede bir `ws_sync` ile tek istekte güncel tutuyor; bir telefon
uygulaması aynı işi kanal listesi, mesajlar ve zil için ayrı ayrı
çağrılarla yapmak zorundaydı. API çağrısı kişiyi "ekranda" da saymıyordu,
bu yüzden uygulamada okuyan kişinin telefonu anılma için ayrıca push ile
uyandırılıyordu.

**Ne değişti.**
- `GET /workspace/sync?cursor=…&channel_id=…` (`workspace:read`, yeni
  dosya `includes/workspace/api_sync.php`). Cevap: `channels` (bütün
  liste, `GET /workspace/channels`'ın verdiği nesneler) ve `threads`
  (kişinin içinde olduğu açık tartışmalar, kenar çubuğunun
  `ws_threads_for()` listesi) yalnız imleçten beri değiştiyse, yoksa
  `null`; `inbox_unread`; `channel_id` verilince o kanalın imleçten sonra
  yazılan mesajları (`messages`) ve elde olan mesajlardan değişenler
  (`changed`: düzeltme, silme, tepki, işaret, oy, okundu onayı).
  İmleçte bu kanal açık değilse en yeni 50 mesaj gelir; geriye
  `GET …/messages?before_id=` ile gidilir. `more` 200 yeni mesajda,
  `reload` 200 değişen mesajda (bir cevaba sığmadı, kanalı yeniden oku),
  `gone` kanal artık okunamıyorsa.
- İmleç (base64url JSON: an, açık kanal, gönderilen son mesaj, kanal ve
  tartışma listesinin `crc32` işareti) önceki cevabın bıraktığı durumu
  taşır. Hiçbir şey değişmediyse imleç aynen döner, cevap bayt bayt aynı
  olur ve API'nin ETag'i `If-None-Match` ile 304 verir. Bir şey
  gönderildiyse an bu çağrının anına geçer. Okunamayan imleç 400
  `invalid_cursor`. İmleç imzalı değil: kanal her çağrıda yeniden
  denetlenir, mesaj kimliği yalnız o kanal içindeki okumayı sınırlar.
- Ekip cihazları oturumundan çağrı (`ws_api_device_writes_as_person()`)
  kişiyi ekranda sayar (`ws_touch_seen()`) ve vakti gelen push'ları
  gönderir (`ws_push_due_run()`), panelin açık ekranı gibi: anılmanın
  push'u bir dakika bekler, kişi okursa düşer. Uygulama anahtarının ve
  OAuth bağlantısının yoklaması kimseyi ekranda saymaz. Çağrı hiçbir şeyi
  okundu işaretlemez (`POST /workspace/channels/{id}/read`, M2).
- Ortak kod: panelin `ws_sync`'i ve yeni uç açık kanalı aynı
  `ws_channel_news()` (`messages.php`: süresi dolan anket ve onayları
  kapatır, yeni ve değişen mesajları okur) ve `ws_sync_seen()`
  (`notify.php`) ile okur. Panelin davranışı aynı. API'nin mesaj
  sunumu `ws_api_messages_present()`'a alındı, `GET …/messages` da onu
  kullanır.
- Claude'un çalıştığı uygulama yalnız Claude'a sorulabilen kanalları ve
  tartışmaları görür (`GET /workspace/channels` kuralı).
- MCP: rota `'mcp' => false` taşır ve araç olmaz
  (`pg_mcp_route_is_offered()` bu bayrağı tanıyor); imleçle yoklama bir
  modelin işine yaramaz.

Şema değişikliği yok, yeni izin kapsamı yok, yeni metin yok.

**Bilerek yapılmayan.** WebSocket ya da SSE yok (paylaşımlı barındırmada
ve IIS'te her açık bağlantı bir PHP işçisi tutar). Panelin `ws_sync`'indeki
ekrana özgü parçalar (sabitlenen mesaj, sekme sayıları, pano işareti,
görev kartı HTML'i, misafir durumu, programlanmış işin tetiklenmesi)
API'de yok. Zil sayısı ayrı izin istediği için yok
(`GET /notifications/unread-count`). Kanalın önceki sürümleri (`era`)
okunmaz, `GET …/messages` gibi.

**Doğrulama.** `tests/workspace_api_test.php`'ye üç test (rota GET ve
`workspace:read`, MCP aracı değil, bayraksız olsa araç olurdu; imleç
gidiş-dönüşü ve bozuk imleçlerin reddi; liste işareti). Sandbox'ta "Ekip
cihazları" ile: ilk çağrı listeleri verdi; kanal açılınca `channels`
geldi, `threads` `null`; `channel_id` ile ilk sayfa; değişiklik yokken
imleç aynı kaldı ve ETag ile 304; iki yeni mesaj `messages`'da; tepki ve
düzeltme `changed`'de (`edited: true`, 👍 1); bozuk imleç 400
`invalid_cursor`; olmayan kanal `gone: true`; `user_online_timestamp`
güncellendi. Panelin Çalışma Alanı ekranı Chromium'da açıkken API'den
yapılan düzeltme ve yeni mesaj `ws_sync` cevabında `changed: 1`,
`messages: 1` olarak geldi, konsolda hata yok. Sunucu günlüğünde uyarı
yok; `check_api_schema`, `lint`, `check_lang`, `build_js --check` temiz.

---

## 2026.4.10 — API: cihazdan yazılan mesajda @Claude, @ai ve MCP ajanı anması isteği başlatıyor (2026-10-14)

**Neden.** #369 (aşağıda, "cihazdan yazılan Çalışma Alanı mesajı
kişinin adıyla") bu parçayı bilerek dışarıda bıraktı: ekip
cihazından (`POST /auth/login`) yazılan mesaj `@Claude`, `@ai` ya da kendisi
ajan olan bir MCP sunucusunun takma adını (`@dev`) ansa da istek
başlamıyordu. İsteği kuyruğa koyan çağrılar panelin `ws_send` eyleminde
duruyordu, API'nin kanal yazma ucu (`ws_api_messages_create()`) onları hiç
çağırmıyordu.

**Ne değişti.**
- `includes/workspace/messages.php`: `ws_message_ask_assistants()`.
  Panelin `ws_send` eylemindeki sıra buraya taşındı: önce
  `ws_claude_after_send()`, sonra `ws_mcp_agent_after_send()`, ajan
  sunucusunun mesajı değilse `ws_ai_after_send()` (takma adı Pinegrap AI
  ile yanıtlanan sunucu ve #362'nin `@ai` taslak kartı da bu yoldan
  çıkar), tartışmada sorulduysa `ws_thread_assistant_asked()`. Dönüş
  `claude`, `agent`, `ai`. İzin denetimleri aynı yardımcıların içinde
  olduğu için iki yolda da aynı: Claude'a ya da AI'a kapalı kanal,
  misafir odası, dışa paylaşılan kanal, kişi başı bekleyen istek sınırı;
  sorulamayan yerde kanala nedenini söyleyen sistem satırı düşer.
- `includes/workspace/actions.php`, `ws_send`: aynı yardımcıyı çağırıyor;
  ekrana dönen `claude`, `ai`, `agent` alanları değişmedi.
- `includes/workspace/api_resources.php`, `ws_api_messages_create()`:
  `ws_api_message_asks_assistants()` doğruysa (yazan kişinin kendi
  cihazı, yani `ws_api_writer_app_id()` 0, ve `kind` `message`) aynı
  yardımcıyı çağırıyor. Uygulama anahtarıyla ya da OAuth bağlantısıyla
  yazılan mesaj hiçbir asistanı sormaz (bir ajanın yanıtında geçen takma
  ad kendini yeniden sormasın; görev notundaki `$app_id <= 0` kuralıyla
  aynı). Karar ve not türündeki mesaj sormaz; panel de yalnız düz mesajda
  sorar.
- Kuyruğu ilerletmek: `ws_api_assistants_carry_on()`. Claude'un rutini
  hemen ateşlenir (`ws_claude_dispatch()`, API'de göreve verme
  `ws_api_tasks_delegate()` ile aynı). Pinegrap AI ve ajan sunucusu bir
  model çağrısı ya da ajanın yanıtı kadar sürdüğü için telefonun yanıtını
  bekletmez: `fastcgi_finish_request()` varsa (PHP-FPM) yanıt gittikten
  sonra kapanış fonksiyonunda `ws_mcp_agent_kick()` ve `ws_ai_kick()`
  çalışır (`erp_mail_defer()` deseni). Yoksa (IIS, mod_php) isteği
  kanalı açık panel ekranı ve saatlik iş sürdürür; göreve verilen işte
  de böyle.
- API açıklaması (`includes/workspace/api.php`,
  `workspace.messages.create`; OpenAPI ve MCP aracı metni buradan gelir)
  bunu söylüyor.

Şema değişikliği yok, yeni metin yok, JS değişmedi.

**Bilerek yapılmayan.** API'de "kuyruğu sürdür" ucu yok: IIS'te
telefondan sorulan Pinegrap AI ya da ajan isteği, biri kanalı panelde
açana ya da saatlik iş çalışana kadar bekler. Uygulamanın ekranı gibi
sürdürmesi için bir uç ya da eşitleme çağrısının (plandaki M3,
`GET /workspace/sync`) taşıması gerekir; ayrı dilim. `/claude …` ve
`/ai …` komutları API'de etiketle yeniden yazılmaz (metin yazıldığı gibi
kalır), ama `ws_claude_asked()` ve `ws_ai_asked()` bu yazımı zaten
tanıdığı için istek başlar. Diğer eğik çizgi komutları (`ws_command_run()`)
API'de hâlâ çalışmaz. Mesaj ekli dosya (`ws_attach`) panelde de asistan
sormadığı için değişmedi.

**Doğrulama.** `tests/workspace_api_test.php`'ye bir test (kişi + mesaj
sorar; uygulama, karar ve not sormaz). Sandbox'ta ekip cihazı oturumuyla
(`POST /auth/login`): Claude ve AI bağlı değilken `@Claude` ve `@ai`
mesajının ardından kanala kurulum ve Premium satırları düştü; karar
türündeki `@ai` mesajı hiçbir şey yazmadı. Claude bağlı gösterilince
`@Claude` isteği `ws_ai_requests`'e kişinin adıyla `queued` düştü ve
`ws_claude_dispatch()` aynı istekte çağrıldı; özel kanalda "Bu kanalda
Claude çağrılamaz" satırı düştü, istek yazılmadı; tartışmada sorulunca
istek yazıldı ve tartışmanın `assistants` alanı `claude` oldu. Sunucu
uygulama anahtarıyla yazılan `@Claude … @ai` mesajı ne istek ne satır
bıraktı. Panelin `ws_send` eylemi aynı yanıtı verdi (`claude: true`,
`@ai` için Premium satırı, düz mesajda hiçbir şey). MCP ajanı yolu
sandbox'ta denenmedi (ajan sunucusu gerekir); kod panelle aynı çağrıdır.

---

## 2026.4.10 — Listeden hızlı güncelleme: anahtar kod durumu ve hediye kartı notları (2026-10-14)

**Neden.** #364'ün hızlı güncelleme kalıbının ikinci aday grubu (#370'in
"Kapsam dışı / sonraki grup" satırı). Bir anahtar kodu geçici olarak
kapatmak ya da yeniden açmak için kodun ekranını açıp formu baştan kaydetmek
gerekiyordu; hediye kartına kısa bir not düşmek de öyle.

**Anahtar kodlar** (`view_key_codes.php`). Etkin hücresi tıklanınca Etkin /
Devredışı listesi açılır (tür `select`, ürün listesinin Etkin sütunu gibi),
seçim hemen kaydedilir. İzin listenin izni (rol < 3 ya da e-ticaret
yönetimi). Kayıt `pg_key_code_quick_update()` (`includes/fn/ecommerce.php`):
`edit_key_code.php` ile aynı yazma (`enabled`, `user`, `timestamp`); Site
Günlüğü'ne "anahtar kod (KOD) anahtar kodlar listesinde değiştirildi: Etkin
"Etkin" iken "Devredışı" oldu" yazılır. Aynı değer yazmaz ve günlüğe düşmez.
Cevap Anahtar Kod hücresinin rengini (`data-pg-cell="code"`; yeşil yalnız kod
bugün ödemede çalışıyorsa: etkin, süresi geçmemiş, teklifi açık ve
tarihleri içinde) ve "Son Değişiklik" hücresini de yeniler. Bu kural
`pg_key_code_active()`'e taşındı; listenin döngüsündeki eski hesap ve
şablondaki `status_enabled` dalı yerine hücreler `pg_key_code_list_cells()`
ile çizilir. Webhook ya da Çalışma Alanı olayı yok; anahtar kod ekranı da
duyurmuyor.

**Hediye kartları** (`view_gift_cards.php`). Notlar hücresi tıklanınca tek
satırlık kutu açılır (tür `text`, boşaltılabilir, uzunluk sınırı yok;
sütun `longtext` ve kartın ekranı da sınır koymuyor). İzin listenin izni.
Kayıt `pg_gift_card_quick_update()`: `edit_gift_card.php` ile aynı yazma
(`notes`, `last_modified_user_id`, `last_modified_timestamp`); Site Günlüğü'ne
"hediye kartı (KOD) hediye kartları listesinde değiştirildi: Notlar "eski"
iken "yeni" oldu". Bakiye ve son kullanma tarihi listede değişmez (bakiye
kendi izini istiyor, #370'te kapsam dışı bırakılmıştı).

Birden çok satırlı not listede değişmez: kutu tek satırdır ve satır sonunu
boşluğa çevirir (`pg_list_edit_parse()`), kaydetmek satırları birleştirirdi.
Bu notlar kartın ekranında, satırları korunarak değişir; hücre düz kalır
(`pg_gift_card_notes_fit_list()`) ve doğrudan istek "Bu notlar birden çok
satırdan oluşuyor…" ile reddedilir. Sipariş iptal edilince kartın notuna
bir satır eklendiği için (`process_order_cancellation()`, `CONCAT`) yazma okunan
nota koşulludur (`AND notes = '…'`); arada not değiştiyse "Hediye kartı bu
arada değişti…" ile reddedilir ve iptal satırı ezilmez.

Betiğe (`backend.src.js`) ve ortak yardımcıya (`includes/fn/output.php`)
dokunulmadı. Şema değişikliği yok.

**Testler.** `tests/list_edit_lists_test.php`'ye 5 test: anahtar kod
sütununun yalnız 1/0 kabul etmesi; `pg_key_code_active()`'in etkin, süre,
teklif durumu ve tarihleri ile teklifi olmayan kodu ayırması; anahtar kod
hücrelerinin rengi, işareti ve kaçışlı kullanıcı adı; not sütununun boş ve
tek satırı kabul edip çok satırı listede açmaması; not hücresinin kaçışı.

---

## 2026.4.10 — Dış API: Çalışma Alanı mesajını düzeltme, silme, kanalı okundu işaretleme ve değişen mesajlar (2026-10-14)

**Neden.** Mobil API ve Claude kanalı planının M2 dilimi
(`docs/_plan_mobil_api_claude_kanali.md`). Bir telefon uygulaması kendi
mesajını düzeltemiyor, silemiyor, kanalı okundu işaretleyemiyordu (yalnız
okunmamış sayısını görüyordu) ve elindeki mesajlardaki değişikliği
(düzenleme, tepki, silme, oy) almanın yolu yoktu; panel bunları `ws_edit`,
`ws_delete` ve `ws_sync` ile yapıyor.

**Ne değişti.**
- `PATCH|POST /workspace/messages/{id}` {text}: `ws_message_edit()` ile,
  panelin kuralı (yalnız yazanın kendi mesajı, not ya da kararı; kayıt
  değişikliğini kaydeden kilitli karar ve onay isteği değişmez, yalnız
  yeni anılanlara bildirim gider).
- `DELETE /workspace/messages/{id}` ve IIS için
  `POST /workspace/messages/{id}/delete`: `ws_message_delete()` ile (kişi
  kendi mesajını, yönetici herkesinkini ve misafirinkini; uygulama mesajı
  silinmez). Yanıt `deleted: true` mesajdır. Silinmiş mesaja 404.
- `POST /workspace/channels/{id}/read` {message_id?}:
  `ws_channel_mark_read()`; `message_id` yoksa kanalın en yeni mesajı.
  Mesaj başka kanalınsa 422, kanalın üyesi olmayana 409 `not_member`.
  Yanıt güncel `unread` ile kanal nesnesi. İşaret geri gitmez.
- `GET /workspace/channels/{id}/messages?changed_since=<zaman>`: yazılmış
  mesajlardan o andan sonra değişenler (`ws_messages_touched()`), id
  sırasıyla; `since_id` ile sayfalanır, kanalın önceki sürümleri dışarıda.
  `ws_messages_touched()`'a isteğe bağlı `$after_id` ve `$limit` eklendi;
  panel çağrısı aynı. Veritabanı yükseltilmemişse (`touched_at` yok) 503.
- Düzeltme ve silme yalnız "Ekip cihazları" oturumundan
  (`ws_api_own_words_only()`, #369'un `ws_api_device_writes_as_person()`
  kuralı): uygulama anahtarı ve OAuth bağlantısı uygulama adıyla yazdığı
  için 403 alır, entegrasyon kişinin sözünü değiştirmez. Bağlantının kişi
  adına yazması karar 11'e (C5) bağlı; karar gelince bu kural da genişler.
  Okundu işareti okundu onayı gibi her cihaz oturumundan
  (`ws_api_person_only()`, OAuth dahil); uygulama anahtarı 403. Önceki bir
  sürümdeki mesaj (`ws_message_in_past()`) değişmez ve silinmez (422).
- MCP araçları: `workspace_messages_update`, `workspace_messages_delete`,
  `workspace_channels_read`; POST takma adı ayrı araç değil.

Şema değişikliği yok, yeni izin kapsamı yok (`workspace:write`). Yeni
metinler `tr.json`'da (2 anahtar).

**Bilerek yapılmayan.** "Yalnız benden sil" (`ws_message_hide()`) API'de
yok; dry_run yok (mesaj yazma ve tepki gibi). #369'dan önce cihazdan
yazılmış mesajlar uygulamanın adıyla durduğu için düzeltilemez; #369'dan
sonra cihazdan ve panelden yazılanlar düzeltilir.

**Doğrulama.** `tests/workspace_api_test.php`'ye iki test (üç uç birer
araç, POST takma adı araç değil; `changed_since` bir tarih). Sandbox'ta
"Ekip cihazları" ile giriş: uygulama anahtarıyla düzeltme, silme ve
okundu 403; cihazla düzeltme 200 (`edited: true`, metindeki
`<@user:118>` "@admin"), uygulama mesajını düzeltme ve silme 403, olmayan
mesaj 404; iki uygulama mesajıyla `unread` 2 → `message_id` ile 1 → boşla
0, eski mesaja geri işaret 0'da kaldı, başka kanalın mesajı 422, üye
olmayan 409 `not_member`; POST takma adıyla silme `deleted: true`, ikinci
silme 404; `changed_since` düzeltilen ve silinen mesajı döndürdü,
`since_id` + `limit=1` ile sayfalandı, bozuk değer 422. Sunucu günlüğünde
uyarı yok.

---

## 2026.4.10 — API: cihazdan yazılan Çalışma Alanı mesajı kişinin adıyla (2026-10-14)

**Neden.** Mobil API planının M1 dilimi (`docs/_plan_mobil_api_claude_kanali.md`).
Ekip cihazları oturumu (`POST /auth/login`, `pgat_…` jetonu) kişinin kendi
haklarıyla çalışıyor ama Çalışma Alanı'na yazdığı her şey
`ws_api_app_id()` ile uygulama adına düşüyordu: telefonundan yazan kişinin
mesajı kanalda "Ekip cihazları (uygulama)" olarak görünüyordu. Hız sınırı
(`ws_rate_limited()`), genel kanala yazınca katılma ve kendi mesajıyla
kanalın okunmuş sayılması da kişiye uygulanmıyordu.

**Ne değişti.**
- `includes/workspace/api_resources.php`: `ws_api_device_writes_as_person()`
  (cihaz satırı var ve `oauth_client_id` boşsa doğru) ve
  `ws_api_writer_app_id()` (kişi cihazdan yazıyorsa 0, yoksa uygulama
  kimliği). Mesaj yazma, tepki, onay isteği, kanala görev kartı, görev notu
  ve şablon uygulamanın sabitlenen mesajı (`api_templates.php`) artık bunu
  kullanıyor; böylece `ws_message_send()`, `ws_reaction_set()`,
  `ws_task_note_add()`, `ws_approval_create()` ve `ws_template_apply()`
  panelin yolundan geçiyor: `sender_kind = 'user'`, hız sınırı (aşılınca
  422), genel kanala katılma, okundu işareti, anılma bildiriminde yazan
  kişi (kendini anan kendine bildirim almaz), webhook'ta `sender_id`.
- Cihazdan yazılan görev notu bir yardımcıyı adıyla anarsa görevi ona
  sorar (`ws_task_delegation_from_note()`), paneldeki gibi; uygulamanın
  notu bunu hiç yapmaz.
- Mesajla birlikte okundu isteği (`ack: true`): yazılacak mesaj kişinin
  sayıldığı için "yazan ya da personel isteyebilir" kuralı uygulama yerine
  kişiye bakıyor; personel olmayan kişi kendi mesajında okundu isteyebiliyor.
- API açıklamaları (`includes/workspace/api.php`; OpenAPI ve MCP aracı
  metinleri buradan gelir): mesaj, tepki, onay isteği, görev notu ve şablon
  uçları kimin adıyla yazıldığını söylüyor.

Şema değişikliği yok, yeni metin yok.

**Bilerek yapılmayan.** OAuth bağlantısı (claude.ai bağlayıcısı, MCP
istemcileri) da bir `api_devices` satırıdır ama eskisi gibi uygulama adına
yazar: kişi adına "… ile" rozetiyle yazması plandaki 11. karara (C5)
bağlı. Okundu verme ve onay kararı (`ws_api_person_only()`) zaten
cihazdan kişi olarak veriliyordu; değişmedi. Claude ve Pinegrap AI
uygulama anahtarıyla yazdığı için etkilenmez. `@Claude`, `@ai` ya da bir
MCP ajanını anan mesaj cihazdan yazılınca istek başlamaz: panelin `ws_send`
eylemi `ws_claude_after_send()`, `ws_mcp_agent_after_send()` ve
`ws_ai_after_send()` çağırıyor, API ucu çağırmıyor; kuyruğu ekranın sonraki
çağrısı ilerlettiği için bu ayrı bir dilim.

**Doğrulama.** `tests/workspace_api_test.php`'ye bir test (uygulama
anahtarı, ekip cihazı, istemcisi boş cihaz satırı, OAuth bağlantısı).

---

## 2026.4.10 — Listeden hızlı güncelleme: ERP cari fiyatları ve nakliye ücretleri (2026-10-14)

**Neden.** #364'ün açtığı hızlı güncelleme kalıbının ilk iki aday listesi.
ERP'de bir carinin ürün fiyatı ya da iskontosu bütün tabloyu gönderen tek
bir formla kaydediliyordu; Kaydet'e basmadan çıkan kişi değişikliği
kaybediyordu. Nakliye yöntemlerinde taşıyıcının fiyatı değişince her
yöntemi açıp formu baştan kaydetmek gerekiyordu.

**ERP Cari fiyatları** (`erp_account_prices.php`). Cari fiyatı ve İskonto %
hücreye tıklanarak değişir (tür `money`, ikisi de boş bırakılabilir). İstek
aynı ekrana gider; carinin numarası tablonun adresindedir
(`erp_account_prices.php?account_id=N`). İzin Minimum stok ekranınınki: ERP
açık, rol < 3 ya da ERP yönetimi, muhasebecinin salt okunur hakkı değil
(salt okunur kullanıcıda ve şema yokken hücreler düz kalır). Kayıt
`erp_account_price_quick_save()` → mevcut `erp_account_prices_save()`: diğer
koşul saklandığı gibi geçirilir, yalnız yazılan değişir; okuma ekranın kendi
okuyucularıyla (`erp_kurus()`, `erp_fx_rate_in()`), iskonto 0–100 denetimi
kaydetme fonksiyonununki. Fiyat ürün fiyatının sınırını (INT) aşamaz. Aynı
değer yazmaz ve günlüğe düşmez.

Boşaltma kuralı ekranın kendi kuralıdır: ürün ne fiyat ne iskonto taşırsa
listeden çıkar (`erp_account_prices_save()` satırı siler). Hızlı güncellemede
bu, son koşulu boşaltmak demektir; satır sayfa yenilenene kadar yerinde
kalır, ürün hücresinde "Listeden çıkarıldı" rozeti çıkar ve yeniden fiyat ya
da iskonto yazmak ürünü listeye geri koyar (kayıt `ON DUPLICATE KEY` ile
yeniden açılır). Yanlışlıkla boşaltılan bir ürün böylece aynı ekranda geri
alınır; ayrı bir silme düğmesi eklenmedi. Site Günlüğü'ne "erp: ürünün (KOD)
carideki (Cari) fiyatı değiştirildi: Alan "eski" iken "yeni" oldu" ya da
"erp: ürün (KOD) carinin (Cari) fiyat listesinden çıkarıldı" yazılır.

Tablonun içindeki giriş kutuları ve sayfanın altındaki yapışkan Kaydet
düğmesi kaldırıldı. Form artık yalnız "Diğer her şeyde iskonto (%)"
kutusunu taşır, Kaydet düğmesi o kartın içindedir; bildirimi "Carinin
iskontosu kaydedildi." Hücrelerin çizimi tek yerde
(`erp_account_price_cells()`): fiyat liste fiyatı gibi para biçiminde,
iskonto sondaki sıfırlar atılarak; boş koşul "—". Kutunun açıldığı değer
`erp_account_price_input()` / `erp_account_rate_input()` (sitenin ondalık
işaretiyle, gruplamasız); eski satır içi kapanışların yerini aldılar.

**Nakliye yöntemleri** (`view_shipping_methods.php`). Ana Oran (temel ücret)
ve Öğe Oranı (kalem ücreti) hücreye tıklanarak değişir; boş kutu ücretsiz
demektir, yöntemin kendi ekranının boş kutuyu kaydettiği gibi. İzin listenin
izni (rol < 3 ya da e-ticaret yönetimi). Kayıt
`pg_shipping_method_quick_update()` (`includes/fn/ecommerce.php`):
`edit_shipping_method.php` ile aynı okuyucu (`pg_normalize_amount()` →
kuruş, `round`), aynı damga (`user`, `timestamp`); sütun `INT UNSIGNED`
olduğu için üst sınır onunki. Site Günlüğü'ne "nakliye yöntemi (Ad) nakliye
yöntemleri listesinde değiştirildi: Alan "eski" iken "yeni" oldu" yazılır.
Cevap "Son Değişiklik" hücresini de yeniler (`data-pg-cell="last_modified"`).
Değişken temel ücret açık olan yöntemde her ara toplam için ayrı bir ücret
olduğundan hücre düz kalır; doğrudan istek gelirse "Bu nakliye yönteminde
her sipariş ara toplamı için ayrı bir temel ücret var…" ile reddedilir.
Hücrelerin çizimi `pg_shipping_method_list_cells()`'e taşındı (değişken
temel ücretin basamakları dahil); liste ve cevap aynı biçimi kullanır.

Betiğe (`backend.src.js`) ve ortak yardımcıya (`includes/fn/output.php`)
dokunulmadı. Şema değişikliği yok.

**Kapsam dışı / sonraki grup.** Anahtar kodlar (Etkin) ve Hediye kartları
(Notlar; bakiye ayrı iz istediği için hariç). Nakliye yöntemlerinde ağırlık
ücretleri ve değişken temel ücretin basamakları listede değişmiyor.

**Testler.** `tests/list_edit_lists_test.php` (3 test): iki ekranın
sütunlarının boş kutuyu ve rakamı kabul edip eksi işaretini ve simgeyi
reddetmesi; nakliye hücrelerinin tek ve değişken temel ücreti, kalem ücretini
ve kaçışlı kullanıcı adını çizmesi.

---

## 2026.4.10 — Siparişler listesinde durum satırda değişiyor (2026-10-14)

**Neden.** Buglar listesinin 5. maddesi ("Tüm tablolar listede hızlı
güncelleme özelliği alacak") #364'le ürünlere ve ERP Minimum stoğa geldi;
sıradaki liste Siparişler'di. Bir siparişin durumunu değiştirmek için
siparişi açmak gerekiyordu, üstelik sipariş ekranında durum için bir alan
yok: Dışa Aktarılmış yalnız dışa aktarmayla, Tamamlanmış'a dönüş yalnız
API'yle yapılabiliyordu. Dışa aktarılmış bir siparişi yeniden dışa aktarmak
isteyen operatörün panelde yolu yoktu.

**Ne değişti.** `view_orders.php`'de Durum hücresi #364'ün seçim kalıbıyla
açılır; seçenekler Tamamlanmış ve Dışa Aktarılmış
(`pg_order_quick_edit_columns()`, `includes/fn/ecommerce.php`). Yalnız bu iki
durumdaki siparişin hücresi düzenlenebilir; Tamamlanmamış (ödemesi bitmemiş
sepet) ve İptal Edildi düz kalır. İptal bilerek seçeneklere konmadı:
`process_order_cancellation()` neden sorar, ödemeyi iade etmeyi dener ve
müşteriye e-posta atar; geri alınamaz. Bu yüzden satırdaki onaylı "Siparişi
İptal Et" düğmesinde kalır. İptal edilmiş siparişi geri almak da sipariş
ekranının onaylı "Siparişi Geri Al" düğmesinde kalır (iptal kayıtlarını
temizler, hediye kartlarını yeniden açar). Doğrudan bir istekle bu
durumlardan birine ya da birinden geçilmek istenirse 422 ile ve bu
düğmeleri gösteren bir iletiyle reddedilir.

İstek, ekran sayfayı çizmeden ve istek değerlerini listenin oturum
filtrelerine kopyalamadan önce yakalanır (`view_orders.php` `$_REQUEST`'i
oturuma yazıyor; yakalama bu döngünün üstünde). İzin listeninkidir
(`validate_ecommerce_access()` ile aynı: rol < 3 ya da e-ticaret yönetimi).
Kayıt `pg_order_quick_update()`'tedir ve API'nin ve Çalışma Alanı'nın
iptal dışı durum değişikliğiyle aynı şeyi yapar: `orders.status` yazılır,
`last_modified_timestamp` güncellenir (ShipWorks gibi değişen siparişleri
çeken entegrasyonlar görsün diye; sipariş ekranının kaydı da bunu yapar),
Site Günlüğü'ne "sipariş (NUMARA) sipariş listesinde değiştirildi: Durum
"eski" iken "yeni" oldu" yazılır ve `pg_announce('order.status_changed')`
Çalışma Alanı olaylarına ve webhook kuyruğuna gider (sipariş ekranının Geri
Al'ı da aynı olayı aynı biçimde duyurur). Bu geçişte hiçbir ekran müşteriye
e-posta atmadığı için burada da atılmaz. Yazma `WHERE status = <okunan>`
koşuluyla yapılır: arada başka bir yol siparişi iptal ettiyse üzerine
yazılmaz, cevap "Sipariş bu arada değişti…" olur. Aynı durum yeniden
seçilirse hiçbir şey yazılmaz.

Durum hücresinin çizimi (durum rozeti, "Ödeme bekleniyor" rozeti, Paraşüt
işareti) listeden `pg_order_list_status_html()`'e taşındı; liste ve hızlı
güncellemenin cevabı aynı fonksiyonu kullanır, rozetler değişmedi.
Kullanılmayan `$parasut_exported` / `$parasut_invoice_id` değişkenleri
kaldırıldı. Betiğe (`backend.src.js`) ve ortak yardımcıya
(`includes/fn/output.php`) dokunulmadı. Şema değişikliği yok.

**Testler.** `tests/order_quick_edit_test.php` (6 test): sunulan seçenekler
(yalnız complete ve exported), iptal / tamamlanmamış / büyük harfli değerin
reddi, her durumun rozeti (eski `canceled` yazımı dahil), "Ödeme bekleniyor"
rozetinin `paid_at` sütunu yokken çıkmaması ve dışa aktarılmış siparişte
de çıkması, Paraşüt işaretinin iki ipucu, hücrenin taşıdığı değer.

**Doğrulandı (sandbox, MariaDB, Chromium).** Durum: Herhangi listesinde tamamlanmış ve dışa
aktarılmış beş siparişin hücresi düzenlenebilir, iptal edilmiş sipariş düz
çıktı; tablo yalnız iki seçeneği taşıdı. Tamamlanmış → Dışa Aktarılmış
seçilince 200 döndü, veritabanında durum ve `last_modified_timestamp`
yazıldı, hücre yeşil rozetle yeniden çizildi ve yeşil yanıp söndü; geri
Tamamlanmış'a da döndü. Havale bekleyen siparişte "Ödeme bekleniyor", Paraşüt
işaretli siparişte işaret değişiklikten sonra yerinde kaldı. Escape ve aynı
değer istek göndermedi. Site Günlüğü'ne dört satır, Çalışma Alanı açıkken
`ws_events_in`'e her değişiklik için `order.status_changed` ({id,
order_number, status, previous}) düştü. Doğrudan isteklerde iptal edilmiş ve
tamamlanmamış sipariş 422 `status`, `cancelled` değeri 422, başka alan 422,
olmayan sipariş 422 `not_found`, yanlış jeton 403; listenin Durum filtresi
isteklerden sonra değişmedi. 390 piksel dokunmatik ekranda dokunuş seçimi
açtı ve kaydetti. Doğrulanamadı: Safari / iOS, gerçek webhook aboneliği,
dev.pinegrap.com'daki gerçek siparişler.

---

## 2026.4.10 — Çalışma Alanı: siz yokken olanlar ve sabah özeti (2026-10-14)

**Neden.** Çalışma Alanı araştırmasının P2 2.5 maddesi ("Kaçırdıkların ve
sabah özeti"). Kanal karşılaması (`ws_channel_briefing()`) son gelişten
bu yana yalnız mesaj sayısını ve en çok yazanı söylüyordu; karar, görev
devri, müşterinin yazdığı ve bekleyen onay ayrı satırlarda ya da hiç
görünmüyordu. Sabah e-postası için de görev e-postası
(`task_digest`) yalnız görev listesi gönderiyordu.

**Ne değişti.**
- `includes/workspace/briefing.php` yeni bölüm "What was missed".
  `ws_recap_channel()` bir kanalda son görülen mesajdan sonrasını sayar:
  başkalarının mesajları (tek gruplu okuma: sayı, en çok yazan, misafirler),
  o mesajın zamanından sonra yazılan kararlar (`marked_at`), okuyana verilen
  açık görevler (`ws_task_assignees.assigned_at`), biten görevler, okuyanın
  kararını bekleyen onay istekleri. Karar ve görevler en fazla
  `WS_RECAP_LOOKBACK` (30) gün geriye bakar; aylarca açılmamış kanal
  geçmişini saymasın diye. `ws_recap_parts()` / `ws_recap_sentence()`
  bunları "Siz yokken: 4 yeni mesaj (çoğunu Ayşe yazdı), bir karar
  yazıldı, size bir görev verildi, Mehmet 2 mesaj yazdı." cümlesine
  çevirir; misafirin mesajları sayıdan düşülür, adıyla ayrıca söylenir.
- Karşılama: sayıdan fazlası varsa (`ws_recap_says_more()`) bu cümle eski
  "N yeni mesaj" satırının yerine geçer ve anılmadan sonra ilk satır olur.
  Yalnız mesaj varsa eski satır aynen kalır.
- Pinegrap AI özeti (karşılama): kanalda Pinegrap AI'a izin varsa
  (`ws_ai_channel_state()['available']`: misafir odası ve dışarıyla
  paylaşılan kanal hiçbir zaman) ve en az `WS_RECAP_AI_MIN` (3) yeni mesaj
  varsa karşılama `recap.since_id` taşır; ekranda "Kaçırdıklarımı özetle"
  düğmesi çıkar. Yeni panel eylemi `ws_brief_recap` (`includes/panel/
  actions.php` listesine eklendi) → `ws_recap_ai()`: son görülen mesajdan
  sonraki en fazla 80 mesaj, 9.000 karakter (yenileri tutulur), etiketler
  okuyanın haklarıyla ada çevrilip `<@...>` biçimi atılır, tek
  `pg_ai_chat()` çağrısı. Cevaptan etiket, `**` ve başlık işaretleri
  temizlenir, 1.500 karakterle sınırlanır. Kanal açılınca her şey okundu
  işaretlendiği için `since_id` karşılamayla birlikte gelir. Özet,
  kanal açık kaldıkça sekme değişse de karşılamada durur.
- Sabah özeti: `task_digest` eylemine iki seçenek, `missed` ve `ai`
  (`ai` yalnız `missed` ile). `ws_recap_person()` kişinin katıldığı,
  bildirimi kapalı olmayan, okunmamışı olan kanalları (en çok okunmamış
  önce, en fazla 8) ve kişiye bekleyenleri (okunmamış anılma, açılmamış
  görev devri, onay istekleri) toplar; `ws_recap_mail_html()` e-postaya
  "Kaçırdıklarınız" bölümünü yazar. Görevi olmayan ama bir şey kaçırmış
  kişiye de e-posta gider (önceden görevsize hiç gitmiyordu; seçenek
  kapalıyken davranış aynı). `ai` açıkken `ws_recap_person_ai()` yalnız
  Pinegrap AI'ın okuyabildiği kanalları modele gönderir; öbür kanalların
  adı bile gitmez, listede sayılarla kalır.
- AI özeti yalnız arka plan çalışmasında yazılır
  (`pg_cron_is_background_run()`: genel iş ya da crontab). Ekranın
  tetiklediği çalışmada ve "Şimdi çalıştır"da model çağrılmaz; Pinegrap
  AI'ın kendi kuralı ("model çağrısı ekrana giden yolda yapılmaz") ile
  aynı. Bir çalışma modele en fazla `WS_RECAP_MAIL_AI_BUDGET` (150 sn)
  harcar, kalan kişiler sayılı listeyi alır; programlanmış işlemin kilidi
  (`WS_SCHEDULED_LOCK`, 600 sn) içinde kalır.
- Form: "Herkesin kaçırdıklarını ekle" ve (Pinegrap AI hazırsa,
  `CFG.scheduled.recap_ai`) "Pinegrap AI birkaç cümleyle özetlesin"
  anahtarları. Yeni hazır işlem "E-postayla sabah özeti": her iş günü
  08:30, kişinin kendisine, bu hafta vadesi gelen görevler + kaçırdıkları.
  Özet satırı "(…, kaçırdıklarıyla birlikte (Pinegrap AI özetiyle))".
- Cümleden kurma (`scheduled_draft.php`, #360) `missed` ve `ai`'yi tanır.
- Mevcut çeviri düzeltildi: "Optional. Nobody gets an e-mail with nothing
  in it." artık "İçinde hiçbir şey olmayan…" (eskisi "görev olmayan"
  diyordu; kaçırdıklar seçeneğiyle görevsiz e-posta da gidebiliyor).

Şema değişikliği yok. Yeni metinler `tr.json`'da (36 anahtar).

**Bilerek yapılmayan.** Özet önbelleğe alınmaz (her tıklama bir model
çağrısı; düğme beklerken kapalı). Kişiye özel ayrı bir "sabah özeti
ayarı" yok: programlanmış işlemle kurulur. Kanal karşılaması kapatılmışsa
(o gün o kanalda) düğme de görünmez. Tartışmalar (thread kanalları)
e-postadaki listeye girmez; kenar çubuğu da onları saymaz.

**Doğrulama.** `tests/workspace_recap_test.php` yedi test (ifadeler ve
sırası, tekil biçimler, e-postada kişiye ait olanların çıkması, cümle,
"daha fazlası var mı", etiket temizliği, modele giden olgular);
`tests/workspace_scheduled_draft_test.php`'ye bir test. Sandbox'ta
ai.pinegrap.com yerine yerel sahte bir model ile (uç adresi yalnız
denemede değiştirildi): #genel'de "Siz yokken: 4 yeni mesaj (çoğunu ayse
yazdı), bir karar yazıldı, size bir görev verildi." satırı ve
"Kaçırdıklarımı özetle" düğmesi; tıklayınca "Pinegrap AI okuyor…", cevap
karşılamada (etiket ve `**` temizlenmiş), modele giden istemde yalnız
son görülenden sonraki dört mesaj. Misafir odasında "Siz yokken: Mehmet
Yılmaz 2 mesaj yazdı." ve düğme yok. Görev e-postası CLI'da (arka plan)
`missed`+`ai` ile iki kişiye gitti: "Kaçırdıklarınız", "Sizi
bekleyenler: size bir görev verildi.", kanal listesi, AI paragrafı;
misafir odasının mesajları modele gitmedi. Formdan "E-postayla sabah
özeti" şablonu iki anahtar açık kaydedildi (`missed:1, ai:1`); ekrandan
"Şimdi çalıştır" model çağırmadı. Sandbox'ta posta sunucusu olmadığı için
web'den gönderim "gönderilemedi" döndü; e-posta gövdesi CLI'da sahte
sendmail ile okundu. dev.pinegrap.com'da Pinegrap AI anahtarı olmadığı
için orada yalnız sayılı satır ve liste denenebilir.

---

## 2026.4.10 — Çalışma Alanı: kanal durumu, check-in ve eskiyen iş süzgeci (2026-10-14)

**Neden.** Çalışma Alanı araştırmasının P2 2.4 maddesi
(`notes/workspace-arastirma.md` §4): projelerde "işiniz ne durumda"
sorusunun tek bir cevabı yoktu; haftalık durum, düzenli soru (check-in) ve
eskiyen işin hatırlatması eksikti (Asana smart status, Linear Pulse,
Basecamp check-in). Madde, anket ve programlanmış işlem altyapısının
üstüne kurulacak biçimde yazılmıştı; yeni ekran açılmadı.

**Ne değişti.**

- Şema adımı 10.87 (`upgrade_2026_4_10_workspace_status()`): üç tablo.
  `ws_status_updates` (mesaj başına bir satır, `uk_message`; `health`
  ENUM on_track/at_risk/off_track; `idx_channel (channel_id, id)` başlıktaki
  son durum için), `ws_checkins` (mesaj, kanal, soru) ve
  `ws_checkin_answers` (birincil anahtar `(checkin_id, user_id)`: kişi başı
  tek yanıt, yerinde değişir, boş yanıt satırı siler). 10.85 olay
  otomasyonuna, 10.86 toplantıya ayrılmıştı; olay otomasyonu şema adımı
  almadı, 10.85 boş kaldı.
- Yeni modül `includes/workspace/status.php`:
  - Durum güncellemesinin metni mesajın kendisidir; ilk satır kalın
    "**Durum: Riskte**" olur. Böylece bildirimde, e-postada ve API'de
    durum ayrı bir alana bakmadan okunur; ekran mesajın sol kenarını duruma
    göre renklendirir. Başlıktaki etiket, mesajı silinmemiş en yeni
    satırdan gelir.
  - `ws_status_facts()`: açık, geciken, son yedi günde biten görev,
    `WS_STATUS_STALE_DAYS` (7) gündür değişmeyen açık görev (`GREATEST
    (updated_at, created_at)`: notlar `ws_task_notes_recount()` ile
    `updated_at`'i ilerletiyor, hiç değişmemiş görevde `updated_at` boş
    olabilir), haftanın kararları ve açık onay istekleri. Kanalın görevleri
    kanalı okuyan herkese açık olduğu için (`ws_can_see_task()`) ayrı yetki
    süzgeci yok. `ws_status_suggest()` (saf, test var) bunlardan bir durum
    önerir: en az üç ya da açıkların yarısı gecikmişse Gecikti, herhangi
    bir geciken ya da eskiyen görev varsa Riskte.
  - `ws_status_draft()`: anahtar olmadan da çalışan kural tabanlı taslak.
    `ws_status_ai_draft()`: Pinegrap AI'a tek senkron çağrı (toplu metin
    yazarının, `bulk.php`, deseni), rakamlar, görev ve karar başlıkları ile
    son check-in'in yanıtları JSON olarak gider. Yalnız
    `ws_ai_ready()` ve `ws_ai_channel_allowed()` doğruysa sunulur; değilse
    düğme hiç çıkmaz (misafirli kanal ve izin verilmemiş özel kanal dahil).
  - Check-in yanıtını kanalda yazabilen herkes verir
    (`ws_can_post_channel()`); kart yanıtları ve kanalın şu anki üyelerinden
    henüz yanıtlamayanları gösterir. Eski bir sürümdeki (era) check-in'e
    yanıt verilmez.
  - Durum güncellemesi ve check-in tartışmada (discussion) yazılmaz: durum
    kanalındır ve tartışma temizlenirken (`ws_threads_purge()`) bu satırlar
    geride kalırdı. Sunucu ikisini de reddeder, + menüsü tartışmada
    göstermez.
- Programlanmış işlem: yeni `checkin` adımı (kanal + soru; boş soru
  varsayılan soruyla kaydedilir), haftalık tekrarla düzenli soru olur.
  "Kişilere görevlerini e-postayla gönder" adımına `stale` süzgeci: eskiyen
  işlerin hatırlatması ayrı bir motor yazılmadan bununla programlanır.
  Cümleden kurma (`scheduled_draft.php`) iki yeniliği de tanıyor.
- Ekran: `assets/js/workspace_status.js` (`workspace.min.js` paketinde),
  + menüsünde "Durum güncellemesi" ve "Check-in sor", başlıkta durum
  etiketi, mesaj kartı ve programlanmış işlem kutusu. Panel API izin
  listesine (`includes/panel/actions.php`) beş eylem eklendi.

**Ödünler.** Durum ve check-in için dış API ucu, MCP aracı ve webhook olayı
yazılmadı (madde istemiyordu); gerekirse ayrı PR. Check-in kapanmaz ve
yanıtlayanlara ayrıca bildirim gitmez: soru bir mesaj olduğu için kanalın
olağan okunmamış sayacı ve bildirimi yeterli görüldü. Misafir ekranı
(`workspace_guest.js`) durum mesajını metniyle okur, renk ve check-in kartı
yalnız ekibin ekranında.

**Doğrulama.** Sandbox'ta: şema adımı iki kez koştu (ilkinde üç tablo,
ikincide üçü de atlandı); kanalda tohum görevlerle rakamlar, önerilen durum
(Riskte), taslak, paylaşma, başlık etiketi ve renkli kenar; check-in sorma,
yanıtlama ve yanıtı değiştirme; programlanmış işlem kutusu ve
`ws_scheduled_do()` ile çalışan `checkin` adımı; `stale` süzgecinin yalnız
eskiyen görevi seçmesi; 390 px telefonda yatay kaydırma yok. Pinegrap AI
taslağı anahtar olmadığı için denenemedi.

---

## 2026.4.10 — Tablolarda listeden hızlı güncelleme: ürünlerde stok, fiyat, durum ve kısa açıklama, ERP'de minimum stok (2026-10-14)

**Neden.** Ürün sahibinin Buglar listesinin 5. maddesi: "Tüm tablolar listede
hızlı güncelleme özelliği alacak." Stok, fiyat ya da satış durumu gibi sık
değişen bir alan için ürünü açıp formu kaydedip listeye dönmek gerekiyordu;
50 ürünün stoğunu düzeltmek 50 form demekti. ERP'nin Minimum stok ekranı ise
bütün tabloyu tek bir formla gönderiyordu: bir minimumu değiştirip Kaydet'e
basmadan sayfadan çıkan kişi değişikliği kaybediyordu.

**Ne değişti — ortak yardımcı.** Hızlı güncelleme bir sütun tanımıyla açılır
(`includes/fn/output.php`): alan adı → `type` (`text` | `integer` | `money` |
`select`), `label`, `max`, `min`, `empty` (sayı ya da tutar boş
bırakılabilir), `required` (metin boş bırakılamaz), `options` (değer →
etiket). Tabloya `pg_list_edit_table($url, $sütunlar)` adresi ve tanımları
(`data-pg-edit-url`, `data-pg-edit-columns`; tarayıcıya yalnız tür, etiket,
sınırlar ve seçenekler gider, `required` gibi kurallar sunucuda kalır),
düzenlenebilen hücreye `pg_list_edit_cell($sütunlar, $alan, $id, $değer)`
alanı, kaydı ve düzenlenecek ham değeri (`data-pg-edit*`, `tabindex="0"`)
yazar. Tanımı olmayan alan ya da kaydı olmayan satır düz kalır.

İstek aynı ekrana gider (`pg_list_edit=1`, `token`, `field`, `id`, `value`)
ve ekran sayfayı çizmeden önce `pg_list_edit_requested()` ile onu yakalar.
`pg_list_edit_request($sütunlar, $izin)` sırasıyla POST, oturum,
`validate_user()` + ekranın izin fonksiyonu, CSRF jetonu (`hash_equals`),
alanın tanımda olması, kaydın sayı olması ve değerin okunmasına bakar; biri
tutmazsa JSON'la (405, 401, 403, 422) cevap verip çıkar. `validate_*`
fonksiyonları HTML sayfasıyla cevap verdiği için bu kapı JSON'a özel yazıldı.
Değeri `pg_list_edit_parse()` okur: metin tek satıra indirilir ve kırpılır,
uzunluk karakterle sayılır (`mb_strlen`); tam sayı yalnız rakamdır, sınırların
içinde olmalıdır; tutar yalnız rakam ve ayraçtır (eksi işareti ya da para
simgesi reddedilir, okuması ekranın kendi tutar okuyucusuna kalır); seçim
yalnız sunulan değerlerden biridir; bozuk UTF-8 ve tanımsız tür reddedilir.
Ekran kaydı kendi kaydetme fonksiyonuyla yapar ve cevabı
`pg_list_edit_reply()` ile verir: hücrenin yeni HTML'i, sınıfı, sıralama
değeri, gerekirse aynı satırdaki başka hücreler (`cells`, `data-pg-cell` ile
bulunur).

Tarayıcı tarafı `backend.src.js`'in sonundaki modül: hücreye tıklamak (ya da
klavyeyle odaklanıp Enter / F2) küçük bir kutu açar (seçimde açılır liste,
sayıda telefonun rakam klavyesi, tutarda ondalıklı klavye). Enter ya da
kutudan çıkmak kaydeder, Escape vazgeçer, Tab kaydedip alttaki satırın aynı
hücresini açar, Ctrl+S sayfanın formunu göndermeden yalnız hücreyi kaydeder.
Değişmeyen değer istek göndermez. Kayıt sürerken hücrede yazılan değer ve bir
dönen simge görünür; hata olursa hücre eski hâline döner, kırmızı yanıp söner
ve sunucunun nedeni bildirim olarak çıkar (bağlantı koparsa "Değişiklik
kaydedilemedi…"). Başarıda hücre yeşil yanıp söner, DataTables'ın arama
dizini yenilenir. Kalem simgesi fareyle üzerine gelince, dokunmatik ekranda
hep soluk görünür; hücreye `title` konmadı, çünkü `pgBindTitlePopovers` her
`title`'ı açılır kutuya çevirir ve listede yüzlerce açılır kutu olurdu.
Düğme metinlerine (#327) dokunulmadı.

**Ürünler.** `view_products.php`'de Kısa Açıklama (metin, en çok 100 karakter
— sütunun boyu), Etkin (Etkin / Devre dışı), Fiyat (tutar) ve stok
izlenen üründe Stok Miktarı (0–9 999 999) değişir; izin ürün listesininki
(rol < 3 ya da e-ticaret yönetimi). Kayıt `pg_pb_quick_update()`'tedir ve
ürün formunun kaydettiği yolla aynı yardımcıları kullanır: fiyat
`pg_pb_price_to_cents()` ile kuruşa çevrilir (INT sınırını aşan reddedilir),
stok `pg_pb_apply_stock_flag()` ile stokta yok bayrağını ve tarihini ayarlar,
satır `pg_pb_update_row()` ile yazılır, durum değişince
`pg_pb_sync_tag_cloud_keywords()` etiket bulutunu düzeltir, fiyat, stok ve
durum değişince `pg_marketplace_product_changed()` pazaryerlerine haber
verir. Kısa açıklama ve durum SEO analizini eskimiş işaretler (analiz bu
ikisini okur, fiyatı ve stoğu okumaz). Değişiklik zamanı ve kullanıcısı
yazılır; Site Günlüğü'ne "ürün (KOD) ürün listesinde değiştirildi: Alan
"eski" iken "yeni" oldu" satırı düşer. Stok izlemeyen üründe stok hücresi
düz kalır; doğrudan istek gelirse "Bu ürün stok takibi yapmıyor…" ile
reddedilir. Hücrelerin çizimi tek yerde toplandı (`pg_pb_list_cells()`):
liste ve hızlı güncellemenin cevabı aynı sınıfları ve renkleri kullanır
(stok 0 ve altı kırmızı, 10'un altı sarı; ürün devre dışı kalınca adı ve
kısa açıklaması kırmızıya döner). Fiyat kutusu sitenin ondalık ayracıyla
açılır (`pg_format_input_amount()`, Türkçe sitede 299,95).

**ERP Minimum stok.** `erp_stock_minimums.php`'de minimum hücreye tıklanarak
değişir, boş bırakmak minimumu kaldırır (0–1 000 000). Kayıt
`erp_stock_minimum_quick_save()` → mevcut `erp_stock_minimums_save()`;
cevap minimumla birlikte stok hücresini de getirir, ürün "Düşük"e girer ya da
çıkar. İzin ekranınki: ERP açık, rol < 3 ya da ERP yönetimi, muhasebecinin
salt okunur hakkı değil (salt okunur kullanıcıda hücreler düz kalır). Site
Günlüğü'ne "erp: ürünün (KOD) minimum stoğu X iken Y oldu" yazılır. Ekranın
bütün tabloyu gönderen formu, Kaydet düğmesi ve "yalnız değişenleri gönder"
betiği kaldırıldı; işaretlenen ürünlerden satın alma siparişi açan kutular ve
düğme olduğu gibi kaldı. Minimum başlığı sayılarla aynı hizaya (sağa) alındı.

**Kapsam dışı / sıradakiler.** Bu PR'da yalnız iki ekran bağlandı. Yeni bir
listeye eklemek için betiğe ya da yardımcıya dokunmak gerekmiyor: ekran bir
sütun tanımı, kendi kayıt fonksiyonunu çağıran bir izin + kayıt bloğu ve
hücrelere `pg_list_edit_cell()` ekler. Adaylar: ERP Cari fiyatları
(`erp_account_prices.php`; cari fiyatı ile iskonto ikisi boşalınca satır
silindiği için kuralı ayrıca düşünülmeli), Nakliye yöntemleri (temel ve
kalem ücretleri), Anahtar kodlar (Etkin), Hediye kartları (Notlar; bakiye
ayrı iz istediği için hariç). Stokta Olmayan Ürünler filtresinde stok sütunu
yok, orada stok değişmez (fiyat, durum ve kısa açıklama değişir). ERP stok
ekranının stoğu sayım ve hareketle değiştiği için hızlı güncellemeye
açılmadı. Şema değişikliği yok.

**Testler.** `tests/list_edit_test.php` (12 test): metnin kırpılması ve tek
satıra inmesi, karakterle sayılan uzunluk, zorunlu metin, tam sayı aralığı ve
boş değer, tutar biçimi (eksi, simge, yarım ayraç reddi), seçim, tanımsız tür
/ dizi / bozuk UTF-8, tablo tanımlarının sırası ve sunucu kurallarının
tarayıcıya gitmemesi, boş özellikler, hücre değerinin kaçışı.

**Doğrulandı (sandbox, MariaDB, Chromium).** Ürün listesinde stok 5 → 0
Enter'la kaydedildi, hücre kırmızıya döndü, veritabanında stokta yok bayrağı,
tarihi ve kullanıcı yazıldı, odak hücreye döndü; fiyat 19,90 → 1990 kuruş;
Tab alttaki satırın fiyatını açtı, Escape istek göndermeden kapattı; -5
fiyatı 422 ile reddedildi, bildirim çıktı, hücre eski hâline döndü; Etkin →
Devre dışı SEO analizini eskimiş işaretledi, ad ve kısa açıklama kırmızıya
döndü; kısa açıklama kutudan çıkınca kırpılarak kaydedildi; Ctrl+S sayfayı
göndermeden kaydetti; aynı değer istek göndermedi; DataTables araması yeni
metni buldu. Doğrudan isteklerde yanlış jeton 403, ad alanı 422, stok
izlemeyen ürün 422 `not_tracked`, olmayan ürün 422 `not_found`. Site
Günlüğü satırları yazıldı. Minimum stokta boş değer minimumu kaldırdı ve
"Düşük" kalktı, 2 000 000 reddedildi, 4 kaydedildi, sıralama değeri
güncellendi ve stok yeniden düşük göründü. 390 piksel dokunmatik ekranda
dokunuş kutuyu açtı, 11 kaydedildi. Doğrulanamadı:
Safari / iOS ve gerçek bir telefon, dev.pinegrap.com'daki gerçek ürünler.

---

## 2026.4.10 — Siparişler listesi ortak tablo yardımcısında: çoklu sıralama; bozuk tarih değeri listeyi çökertmiyor (2026-10-14)

**Neden.** Eski liste grupları birer PR'la `pg_list_state()` yardımcısına
taşınıyordu (#331'den #354'e); Siparişler (`view_orders.php`) bilerek ayrı
bırakılmıştı. Liste sıralamayı oturumda çevrilmiş başlık adıyla
(`['ecommerce']['view_orders']['sort'/'order']`) tutup kendi `switch`'iyle
tek sütunluk ORDER BY kuruyordu. Bu PR'la sunucuda sıralanan panel
listelerinden eski sıralama kodunu kullanan kalmadı (taramada
`get_column_heading()`/`asc_or_desc()` çağırıp `pg_list_state()`
kullanmayan ekran yok).

Aynı ekranda #350 ve #351'de kapatılan tarih açığı da vardı. İstekteki her
değer oturuma kopyalanıyor, varsayılan aralık ise yalnız `start_month`
oturumda yokken kuruluyordu. `?start_month=x` tek başına gelince
`start_year` hiç yazılmadığı için yıl hesabı (`'' - 1`) TypeError veriyordu;
`?stop_year=abc` `mktime()`'da TypeError veriyordu (aynı değerle yıl
seçimi döngüsü de bitmezdi, Rehber'deki gibi). Değer oturumda kaldığı için
liste oturum kapanana ya da `?reset=true` ile açılana kadar hata
sayfasında kalıyordu. 2026.4.9'da da var.

**Ne değişti.** Sıralanabilen sütunlar kodla tanımlandı: `first_name`,
`last_name`, `status`, `order_number`, `total`, `user`, `tracking_code`,
`member_id` (başlığı `MEMBER_ID_LABEL`), `affiliate_code` (yalnız ortaklık
programı açıkken) ve `order_date`. Varsayılan eskisi gibi sipariş tarihi
azalan; yeni seçilen sütun artan başlar. Sıra
`pg_list_state('view_orders', …)` ile okunur, ORDER BY
`pg_list_order_by()` ile yalnız ekranın kod → SQL eşlemesinden yazılır
(SQL ifadeleri eskisiyle aynı). Başlıklar `get_column_heading()`'e düzey
listesi verir; Shift+tık ve düzey numarası ürünlerdeki gibi çalışır. Araç
çubuğunda `pg-toolbar-grow`'dan sonra, durum ve tür seçiminin soluna
`pg_list_sort_menu()` eklendi. Durum, tür, gelişmiş filtreler ve tarih
aralığı eskisi gibi ekranın kendi oturum anahtarında durduğu için
sıralama değişince korunuyor. `?reset=true` (Kargo Raporu, pano ve
karşılama özeti bağlantıları) artık `['list_state']['view_orders']`'ı da
siliyor, yani taze görünüm varsayılan sırayla açılıyor; eskiden de
sıralama bu sıfırlamayla siliniyordu.

Tarih için #351'deki kalıp: altı parçanın (`start_`/`stop_` `month`,
`day`, `year`) her biri 1–4 haneli bir sayı değilse kendi varsayılanına
(bir ay önce → bugün) dönüyor. Kontrol varsayılan bloğunun yerine,
istekteki değerler oturuma kopyalandıktan hemen sonra koşuyor; Kargo
Raporu'nun `start_year=2000`/`stop_year=2030` bağlantıları ve satış
haritasının bağlantısı geçerli değer taşıdığı için etkilenmiyor.

Davranış farkları: sıra panel dili değişince kaybolmuyor. Türkçe panelde
varsayılan sıradayken Sipariş Tarihi başlığı ok göstermiyordu (oturuma
İngilizce 'Order Date' yazılıyordu), artık gösteriyor. Eski
`sort=<başlık>&order=desc` bağlantıları `pg_list_sort_parse()` ile tek
düzey olarak okunur. Eski oturum anahtarı
(`['ecommerce']['view_orders']['sort'/'order']`) artık okunmuyor; ilk
açılışta liste varsayılanla başlar. Sütunlar, Tür sütununun sıralanmaması,
Dışa Aktar düğmeleri (sabit sıralarıyla), Paraşüt dışa aktarımı, toplu
işlemler, satır düğmeleri, istatistik şeridi, dönem gezgini, tarayıcıdaki
arama ve sayfa başına kayıt seçimi değişmedi. Ortak yardımcıya,
`backend.src.js`'e ve `tr.json`'a dokunulmadı; kullanılan metinler zaten
vardı. Satır içi hızlı güncelleme bu PR'ın kapsamında değil (yardımcıya
henüz birleşmedi). Şema değişikliği yok.

**Doğrulandı (sandbox, MariaDB, Chromium; son 31 günde 8 sipariş, 3
kullanıcı, 3 üye kimlikli kişi, ortaklık programı açık, Türkçe panel).**
On sütunun her biri iki yönde, sütunların her sıralı ikilisi (ilk azalan,
ikinci artan) ve üç üç düzeyli birleşim, aynı ORDER BY'lı SQL'in sıralama
değerleri dizisini verdi (113 denetim, hepsi geçti; doğrulayıcı ters
beklentiyle hata verdi). Parametresiz yeniden açılışta iki düzeyli sıra
korundu; `?reset=true` varsayılana döndü ve Sipariş Tarihi'nde aşağı ok
çıktı; eski Türkçe bağlantı `sort=Toplam&order=desc` sıraladı; bozuk değer
(`id;drop table x:desc`) varsayılana düştü. Durum filtresi (Dışa
Aktarılmış) ve gelişmiş filtre (soyad Kaya) sıralama değişince korundu.
Eski kodla `?start_month=x` ve `?stop_year=abc` 500 verdi (günlükte
`Unsupported operand types: string - int` ve `mktime(): Argument #6
($year) must be of type ?int`), sonraki parametresiz açılış da 500
verdi; yeni kodla bunlar, `stop_month=1e9`, `start_year=99999` ve boş
`start_day` 200 döndü, varsayılan aralıkla 8 sipariş listelendi ve PHP
günlüğüne bu ekrandan satır düşmedi. Tarayıcıda 1400 pikselde başlığa tık
tek sütuna, Shift+tık aynı sekmede ikinci düzeye çevirdi; menü iki düzeyi,
"Ardından şuna göre sırala" seçimini ve varsayılana dönüşü gösterdi; 390
pikselde menü araç çubuğunda satıra sarıldı, yatay kaydırma yok.
`tools/lint.php`, `check_lang.php`, `test.php` (775 test),
`check_copies.php` ve `build_js.php --check` temiz.

**Doğrulanamayan.** Gerçek sipariş hacmiyle (binlerce satır) hız ve
dev.pinegrap.com'daki görünüm denenmedi.

---

## 2026.4.10 — Kanalda @ai programlanmış işlemin taslağını hazırlıyor (2026-10-14)

**Neden.** Bir önceki değişiklik (aşağıda, "bir cümleyle kuruluyor")
cümleden taslağı formun üstündeki kutuya koydu ve kanalda `@ai` ile
yazılan isteği bilerek dışarıda bıraktı. Araştırma notunun örneği tam
bu yoldu: kanalda "şu olunca şunu yap" yazan kişi formu açmayı bilmek
zorunda kalmamalı.

**Ne değişti.**
- Kanaldan gelen `@ai` isteğinde (not, görev ve MCP takma adı dışında)
  Pinegrap AI'a yeni bir araç sunulur: `propose_scheduled_action`
  (`sentence`). Yalnız `ws_scheduled_draft_ai_offered()` doğruysa:
  isteyen programlayabilen personel ve `ws_scheduled_draft_ready()`
  doğru. Sistem istemine "sonra, tekrar tekrar ya da bir şey olunca
  yapılacak iş görev değil programlanmış işlemdir" satırı eklenir; en çok
  2 taslak (`WS_SCHEDULED_DRAFT_PER_ANSWER`).
- Taslak #360'ın yolundan çıkar: `ws_scheduled_draft()` isteyenin
  haklarıyla, isteğin kanalı formun kanalı olarak. Bu çağrı kendi
  model adımıdır (`ws_scheduled_draft_ai_step()`): araç cümleyi
  `scheduled_todo`'ya koyar, bir sonraki `ws_ai_step()` sohbet çağrısı
  yerine taslak çağrısını yapar ve sonucu (adımların özeti, açık kalan)
  sohbete yazar. Böylece bir web isteğinde iki model çağrısı üst üste
  binmez; site çapındaki tek-çağrı kilidi ve süre sınırı aynen geçerli.
  Aynı turda `answer` çağrılırsa "önce taslak" hatasıyla geri döner.
- Taslak, yanıtın altındaki öneri kartına `ws_ai_changes` satırı olarak
  gider: `record_type 'scheduled'`, `action 'draft'`, taslak
  `snapshot`'ta (`ws_scheduled_draft_ai_store()`). `mcp_call` gibi
  `ws_change_types()`'ta değildir; uygulanmaz
  (`ws_change_apply()` "formdan kaydedilir" der, API'deki
  `workspace_changes_apply` de). Kart `ws_scheduled_draft_change_entry()`
  ile çizilir: isteyen adı, adımları (`ws_scheduled_preview()` metni),
  açık kalanı ve "Formda aç" düğmesini görür; öbürleri yalnız bir
  taslağın beklediğini görür, çünkü taslak isteyenin okuduğu başka bir
  kanalın adını taşıyabilir. "Kenara ayır" her öneride olduğu gibi.
- "Formda aç" formu #360'ın `preset` yoluyla (cümle kutusu, notlar) açar;
  kayıt her zamanki `ws_scheduled_save()`'den geçer. Kayıtta
  `draft_change_id` gelirse `ws_scheduled_draft_saved()` kartı
  "kaydedildi" yapar: `record_id` kaydedilen işlem, kanaldaki işlem
  kartının mesajı snapshot'ta (`decision_message_id` boş kalır; karar
  değildir). Kart "X kaydetti · saat · Programlanmış işleme git" der.
- Yalnız taslak öneren yanıt boşsa ya da "ayarladım" diyorsa metin
  "Taslağını aşağıya hazırladım. Formda açıp kaydedene kadar hiçbir şey
  programlanmaz." olur (`ws_ai_proposed_line()`); taslak varken "görev
  iste" ve "değiştirdim deme" geri göndermeleri çalışmaz.
- API: öneri listesinde taslak `record.type = scheduled`, alanlar kartın
  adımlarıdır (yalnız isteyene), `can_apply` her zaman false.

Gizlilik #360'taki gibi: kanal adları taslak çağrısına yalnız
`ws_ai_channel_allowed()` doğruysa gider; AI'a kapalı özel kanal hiçbir
çağrıda geçmez. Şema değişikliği yok. Yeni metinler `tr.json`'da
(8 anahtar).

**Bilerek yapılmayan.** Yeni olay türü eklenmedi. Taslak kendiliğinden
kaydedilmez. Not, görev ve MCP takma adı isteklerinde araç sunulmaz (kartın
duracağı kanal yok). Kartta şablon seçilip form değiştirilirse kart
bağlantısı düşer; cümle kutusundan yeniden doldurmak bağlantıyı korur.

**Doğrulama.** `tests/workspace_scheduled_draft_test.php`'ye üç test
(bekleyen cümle ve sınırlar, aracın yalnız kanalda sunulması, yanıt
metni). Sandbox'ta yerel sahte model ile: kanalda `@ai her pazartesi
09:00'da herkese geciken görevlerini e-postayla gönder` → araç sunuldu,
aynı turdaki `answer` geri döndü, taslak ayrı adımda yapıldı, kart
"Ne zaman: her hafta Pazartesi 09:00 · Yap: geciken görevler e-postası ·
Sonraki çalışmalar" gösterdi; ikinci kullanıcı adımları ve adı görmedi;
`ws_change_apply` reddetti. Playwright: "Formda aç" formu dolu açtı (cümle
kutusu ve notla), "Programla" kaydetti, kart "admin kaydetti · Programlanmış
işleme git" oldu; Pinegrap AI kapalıyken kart formu kutusuz açtı, konsolda
hata yok. AI'a kapalı özel kanal (`yonetim`) hiçbir çağrıda geçmedi, AI'a
açık özel kanal yalnız taslak çağrısında geçti.

---

## 2026.4.10 — Uzak ajan: konuşma sürüyor, yanıt takma adın kendi adıyla görünüyor (2026-10-14)

**Neden.** Uzak ajanın beşinci ve son dilimi (`docs/_plan_tersine_oauth.md` →
"4.6 Uzak ajan"). İki eksik kalmıştı. Birincisi: ajanın yanıtına verilen
cevap ajanın önceki yanıtını alıntılayarak gidiyordu; kendi konuşmasını
tutan bir ajan (başka bir Pinegrap sitesi, bir şirketin asistanı) her
cevapta sıfırdan başlıyor ve kendi yanıtını alıntıdan okuyordu. İkincisi:
yanıt soranın adıyla kilitli bir mesaj olarak yazıldığı için kanalda
soranın kendi sözü gibi görünüyordu (yalnız ilk satırı "@x, `araç`
aracıyla yanıtladı" diyordu) ve soran, yanıt kendi adıyla geldiği için
hiçbir bildirim almıyordu.

**Ne değişti.**

- Şema adımı 10.111 (`upgrade_2026_4_10_workspace_mcp_conversations()`):
  `ws_ai_requests.mcp_conversation` VARCHAR(255) '' (`mcp_task_note`'tan
  sonra) ve `idx_reply (reply_message_id)`. Dizin, kanal ekranının her
  mesaj sayfasında "bu mesajlardan hangisi bir ajanın yanıtı" sorusu için;
  `ws_mcp_ask_server()`'ın cevap sorgusu da ondan yararlanır.
- `includes/workspace/mcp_agent.php`, konuşma:
  - `ws_mcp_agent_conversation_argument()` (saf): aracın, mesajın gittiği
    argümandan başka, zorunlu olmayan, adı konuşma diyen bir metin
    argümanı (`conversation_id`, `conversationId`, `context_id`,
    `contextId`, `thread_id`, `threadId`, `session_id`, `sessionId`; ilk
    bulunan). `ws_mcp_agent_tool()` bunu `conversation` olarak döner.
  - `ws_mcp_agent_conversation_of()` (saf): yanıtın `structuredContent`'inde
    o adın (yoksa öteki adların) altındaki metin ya da sayı; 255 baytı aşan
    ya da denetim karakteri taşıyan kimlik alınmaz. Aracın konuşma
    argümanı yoksa hiçbir kimlik saklanmaz: geri verilemeyecek kimlik
    tutulmaz.
  - Kimlik, yanıt yazılırken isteğe saklanır (`ws_mcp_agent_post()`;
    hemen gelen yanıtta, arka planda biten işte ve onaylı çağrıda).
  - `ws_mcp_agent_earlier()`: bu isteğin izlediği yanıtın isteği (kanalda
    cevaplanan mesajı aynı sunucunun ajanı yanıtlamışsa o; görevde aynı
    ajanın son yanıtlanmış çalışması). `ws_mcp_agent_conversation_carried()`
    (saf) onun kimliğini, aynı hesap konuşuyorsa verir: oturumsuz
    (taşıyıcı, başlık, temel) sunucuda herkes sitenin tek kimlik bilgisiyle
    gittiği için herkes; OAuth'lu sunucuda yalnız konuşmayı başlatan kişi.
  - Kimlik giderse istek yalnız yeni mesajı taşır: kanalda cevaplanan
    yanıt, görevde "Bu görevdeki son yanıtınız" alıntılanmaz. Görevin
    başlığı ve açıklaması yine gider (görev değişmiş olabilir).
- `mcp_agent.php`, kimlik: `ws_mcp_agent_answers_map()` bir mesaj sayfası
  için tek sorguyla yanıt mesajı → istek; `ws_mcp_agent_answer_split()`
  (saf) yanıtın ilk satırındaki aracı (kod aralığından, dilden bağımsız)
  ve satırdan sonraki yanıtı ayırır; `ws_mcp_agent_answer_view()` ekranın
  göstereceği göndereni kurar: ad `@takmaad`, unvan "MCP ajanı", baş
  harfli simge, `about` "araç aracıyla, Ad için yanıtladı", `app` ve
  `mcp` işaretleri.
- `messages.php` (`ws_message_payloads()` / `ws_message_payload()`): yanıt
  mesajı ajanın adıyla, `sender_kind = 'app'` ve `mine = false` olarak
  çizilir, gövdesi ilk satırı olmadan. Kayıt değişmez: mesaj veritabanında
  soranın adıyla ve ilk satırıyla kalır.
- Bildirim: yanıt yazılınca soran `agent_answer` türünde bildirim alır
  (gelen kutusu, zil, telefon; ekrana bakarken sohbet gecikmesiyle).
  Başlığı "@takmaad, #kanal kanalında size yanıt verdi", gövdesi yanıtın
  ilk satırsız özeti (`ws_mcp_agent_inbox_describe()`). Kanal okununca
  bahsetmeler gibi okunmuş sayılır (`ws_channel_mark_read()`). Görevdeki
  yanıtın bildirimi zaten vardı (`task_assistant`).
- `assets/js/workspace.js` (+ `.min.js`): ajan yanıtının adının yanında
  "MCP ajanı" rozeti (üstüne gelince araç ve soran); ajanın yanıtına cevap
  yazılırken "Bunu @x yazmadan da @x yanıtlar." ipucu.
- `includes/local/tr.json`: 5 yeni metin.

**Ödün.** Kayıt soranın adıyla kaldığı için ajanın adı yalnız mesajı
çizen yolda (`ws_message_payloads()`: kanal, tartışma, kanal içi arama,
kararlar listesi) görünür; genel arama (komut paleti ve API araması),
sabitlenen mesaj, Genel Bakış, zaman çizelgesi, bildirim özetleri ve API
mesajı soranın adıyla ve "@x, `araç` aracıyla yanıtladı" satırıyla
gösterir (satır bu yüzden kayıtta duruyor). Yeni bir gönderen türü (`sender_kind`) 27 dosyada 160'tan çok
dalı değiştirecekti; tek küçük PR'a sığmadı. Konuşma kimliği yalnız
yapısal yanıtın en üst düzeyinde aranır; `_meta`'da ya da iç içe nesnede
verilen kimlik okunmaz. Dev'in kendi MCP sunucusu konuşma kimliği
vermediği için konuşma dev'de denenemez; yalnız gerileme denenir.

**Denendi.** Birim testleri (`tests/workspace_mcp_test.php`: konuşma
argümanının seçimi, yanıttan kimlik okuma ve reddedilenler, OAuth'lu
sunucuda başkasının konuşmayı sürdürmemesi, yanıtın ilk satırının
ayrılması). Sandbox'ta şema adımı 10.110'dan sonra iki kez koşturuldu
(ikincisinde "zaten var"). `192.0.2.2:8001`'de konuşma kimliği veren
sahte bir 2026-07-28 ajanıyla: kanalda `@helpdesk` sorusunun yanıtı kimliği
sakladı, yanıta verilen iki cevap `conversation_id` ile ve alıntısız gitti;
konuşma argümanı olmayan araçta cevap eskisi gibi alıntılı gitti, kimlik
saklanmadı; görevde ikinci çalışma kimlikle ve "son yanıtınız" alıntısı
olmadan gitti; soran `agent_answer` bildirimi aldı, kanal okununca okundu
oldu. Tarayıcıda (Playwright) yanıtlar `@helpdesk` adı, "MCP ajanı" rozeti
ve rozet açıklamasıyla, ilk satırsız çizildi; konsolda hata yok. Onaylı
(OAuth) yolda konuşma kimliği uçtan uca denenmedi (aynı yardımcıları
kullanıyor). `tools/` denetimleri, `tests/` (766) ve `build_js.php --check`
geçti.

---

## 2026.4.10 — Programlanmış işlem bir cümleyle kuruluyor (2026-10-14)

**Neden.** Çalışma Alanı araştırmasının P2 2.2 maddesi. Programlanmış
işlemin formu güçlü ama uzun: dört adım, her birinde birkaç seçim. "Her
pazartesi 09:00'da herkese geciken görevlerini e-postayla gönder" gibi bir
isteği forma dökmek, formu tanımayan için zordu.

**Ne değişti.**
- Yeni `includes/workspace/scheduled_draft.php`. Formun üstünde
  "Bir cümleyle anlatın" kutusu; "Formu doldur" (ya da Ctrl+Enter)
  `ws_scheduled_draft` eylemini çağırır. `ws_scheduled_draft()` cümleyi
  Pinegrap AI'a tek bir `pg_ai_chat()` çağrısıyla gönderir, dönen JSON'u
  `ws_scheduled_draft_parse()` ile okur (kod bloğu, önünde söz, gevşek
  JSON), `ws_scheduled_draft_tidy()` ile formun aldığı biçime getirir.
  Form, hazır işlemlerin açıldığı yoldan (`preset`) taslakla yeniden açılır.
  **Hiçbir şey kaydedilmez**; kişi adımlara ve özete
  (`ws_scheduled_preview()`) bakar, eksik kalanı tamamlar, kaydeder.
  Kayıt `ws_scheduled_save()`'in her zamanki denetiminden geçer.
- Modele giden: cümle; bugün, yarın, haftanın sonraki günlerinin tarihleri
  ve gelecek ayın ilk günü (model gün saymasın diye); bu sitede formun
  sunduğu "ne zaman", koşul ve eylem türleri (5.85 zincirleri yoksa yalnız
  kanala yaz ve e-posta, tek "sonra"); kişinin görebildiği olaylar
  (#359'un `ws_events_js_config()` listesi ve süzgeç değerleri) ve sayaçlar;
  takım adlarıyla; okuduğu kanallar. Özel kanalın adı yalnız Pinegrap AI'a
  açıksa gider (`ws_ai_channel_allowed()`); misafirle paylaşılan kanal hiç
  gitmez. Formun açıldığı kanal her zaman sunulur, adı gidemiyorsa
  "this channel" olarak.
- Toparlama (`ws_scheduled_draft_tidy()` ve küçük okuyucular): bilinmeyen
  tür ve anahtar atılır; sunulmayan kanal ve kişi kimliği boşalır (kanala
  yaz ve onay isteğinde formun açıldığı kanala düşer); geçersiz e-posta
  adresi atılır; tekrar, süzgeç, öncelik, biçim formun listesinden;
  dakika tekrarı en az 5; para sayacı kuruşa çevrilir (form öyle okur).
  Kayıt koşulu ve kayıt değişikliği (`change`) gibi elle kayıt seçmeyi
  isteyen kısımlar alınmaz. Anlaşılamayan zaman yarın 09:00'a kurulur.
  Dışarıda kalan her şey ve modelin "eksik" cümlesi kutunun altında
  yazılır.
- Kutu yalnız `ws_scheduled_draft_ready()` doğruysa (programlayabilen
  personel, Pinegrap AI hazır) görünür (`CFG.scheduled.draft`). AI hazır
  değilken form eskisi gibidir; eylem doğrudan çağrılırsa "Bu sitede
  Pinegrap AI hazır değil" der.
- Kutuya yazmak formun özetini tetiklemez (olaylar kutuda durdurulur). Boş
  form açılınca imleç kutudadır.

Şema değişikliği yok. Yeni metinler `tr.json`'da (13 anahtar). Yeni eylem
`ws_scheduled_draft` (`includes/panel/actions.php`).

**Bilerek yapılmayan.** Kanalda `@ai` ile yazılan istekten taslak kurma
(araştırma notunun örneğindeki yol) bu değişikliğe girmedi; giriş noktası
formun kendisi. (Sonraki değişiklikte eklendi: yukarıda, "Kanalda @ai
programlanmış işlemin taslağını hazırlıyor".) Kayıt koşulu, kayıt değişikliği ve başka bir işlemi
başlatma (`trigger` eylemi) elle seçilir. İstek sayısına ayrı bir sınır
konmadı: eylem yalnız personele açık, Pinegrap AI'ın kendi bekletmesi
(`hold_until`) geçerli.

**Doğrulama.** `tests/workspace_scheduled_draft_test.php` dokuz test
(cevabı okuma, haftalık görev e-postası, sunulmayan kimliklerin atılması,
dışarıda kalanın notu, zincirsiz veritabanı, olay kuralı, sayaç koşulu,
zaman sınırları, istemin içeriği). Sandbox'ta ai.pinegrap.com yerine
yerel sahte bir model ile Playwright: cümle haftalık pazartesi 09:00,
"yalnız iş günü", geciken görev e-postası ve başarılı olursa kanala yaz
olarak doldu, sunulmayan kişi (999) atıldı, kaydedilen satır doğru; olay
cümlesi "Görev durumu Bekliyor olduğunda, kanalda" + 09:00–18:00 koşulu
olarak doldu, kayıt koşulu notla dışarıda kaldı; okunamayan cevapta hata
kutunun altında, düğme yeniden açık; AI'a kapalı özel kanalın adı isteme
hiç girmedi, form o kanalda açılınca yalnız "this channel" olarak gitti;
Pinegrap AI kapalıyken kutu yok, eylem hata veriyor. dev.pinegrap.com'da
Pinegrap AI anahtarı olmadığı için orada yalnız formun değişmediği
denenebilir.

---

## 2026.4.10 — Çalışma Alanı olayları programlanmış işlemi başlatıyor (2026-10-14)

**Neden.** Çalışma Alanı araştırmasının P2 2.1 maddesi. Programlanmış
işlemin olay kuralı (8.85) sitenin olaylarını (sipariş, stok, kişi, form)
dinliyordu ama Çalışma Alanı'nın kendi olaylarına kördü:
`pg_event_workspace_events()` `workspace.*`'ı bilerek dışarıda bırakıyordu,
çünkü `pg_announce()` her açık kanal mesajını da duyurur. "Tasarım
onaylanınca 'Siteyi yayına al' görevini aç ve müşteriye e-posta at" gibi
bir akış kurulamıyordu.

**Ne değişti.**
- `includes/workspace/watch.php`: `ws_event($event, $payload)` Çalışma
  Alanı olaylarının tek yayın noktası. Yalnız `ws_events_own()`'daki beş
  olayı ve yalnız etkin bir programlanmış işlem o olayı beklerken
  (`ws_events_awaited()`, istek başına bir okuma) `ws_events_in`'e tek satır
  yazar; otomasyon kurmayan site hiçbir şey yazmaz. `pg_announce()` yolu
  (webhook) değişmedi ve `workspace.*`'ı hâlâ `ws_events_in`'e yazmaz, aynı
  olay iki kez tutulmaz. Olaylar:
  `workspace.task.completed`, `workspace.task.status_changed`
  (`ws_task_set_status()`, kimin yaptığı `actor_id`),
  `workspace.task.overdue` (`ws_task_overdue_run()`; mevcut `cron_runs`
  damgasıyla, görevde kimse olmasa da),
  `workspace.approval.decided` (`ws_approval_settle()`; özel kanalda da,
  `status` = sonuç), `workspace.guest.message` (`ws_guest_send()`; misafirin
  adı ve yazdığı).
- `ws_events_catalog()` beş olayı `right => member` ile listeler; filtreleri
  `task_status` (görev durumları), `outcome` (onaylandı, reddedildi, süresi
  doldu, kararsız kapandı) ve hepsinde `channel`. Kural `status` ve
  `channel_id` taşır; `ws_events_rule_matches()` kanalı da karşılaştırır.
  `ws_scheduled_rule_input()` durumu ve kanalı doğrular (kanal okunabilir
  olmalı, tartışma olamaz).
- Görünürlük: `ws_events_start()` Çalışma Alanı olayını işlemi yazan kişinin
  gözüyle süzer (`ws_events_creator_sees()`: görev için `ws_can_see_task()`,
  onay ve misafir için kanalın okunabilirliği). Okuyamadığı özel kanaldaki
  görev, yazanın işlemini başlatmaz ve iz bırakmaz (atlandı satırı yazılmaz).
- Zincir derinliği: `ws_scheduled_execute()` çalışırken `ws_event_depth()`
  derinliğini söyler; bu sırada doğan Çalışma Alanı olayı `_depth` taşır ve
  başlattığı işlem kuyruğa bir alt basamaktan girer,
  `WS_SCHEDULED_CHAIN_DEPTH`'te durur. Bugün programlanmış işlem görev
  durumunu değiştiremediği (`task` değişiklik türü atlanır) için doğrudan
  döngü kurulamıyor; koruma ileride eklenecek eylemler için.
- Yer tutucular: `{{actor}}` (tamamlayan, durumu değiştiren ya da isteği
  sonuçlandıran kişi, etiketiyle) ve `{{message}}` (misafirin yazdığı).
  `{{record}}` görevi, görev hakkında değilse onay isteğinin kanalını ya da
  misafir odasını etiketler; `{{record_title}}` onay isteğinde isteğin
  başlığıdır; `{{record_link}}` onay ve misafir mesajında mesajın kendisini
  açar; `{{record_status}}` görev durumu ya da onay sonucudur. Ad ve bağlantı
  yalnız yazan kişi kaydı ya da kanalı okuyabiliyorsa dolar
  (`ws_events_own_values()`).
- Form (`assets/js/workspace_events.js` + `workspace.min.js`): olay listesinde
  beş yeni olay, Çalışma Alanı olayında "Hangi kanalda" seçimi ve olayın kendi
  filtresi (sunucudan `options`, `filter_label`, `any`). İki hazır işlem:
  "Geciken görevler kanala" ve "Onaylanınca sonraki görev".
- Kural metni: "Görev durumu “Bekliyor” olduğunda, #tasarim kanalında",
  "Bir onay isteği sonuçlandığında: Onaylı" gibi.

Şema değişikliği yok. Yeni metinler `tr.json`'da (28 anahtar).

**Bilerek yapılmayan.** Cümleden otomasyon kurma (P2 2.2) bu değişikliğe
girmedi. Kanal izleme satırı (`ws_watch_write()`) Çalışma Alanı olaylarını
yazmaz; kanal zaten "X tamamladı" satırını alıyor. Webhook kataloğu
değişmedi; özel kanalın olayları dışarı gitmez.

**Doğrulama.** `tests/watch_test.php` dört yeni test (olayların ayrı
tutulması, kayıt eşleme, kanal ve durum filtresi, derinlik). Sandbox'ta
PHP ile uçtan uca: beş olay da işlemi başlattı ve metinler doldu
(`{{actor}}`, `{{record_title}}`, `{{record_link}}`, `{{record_status}}`,
`{{customer}}`, `{{message}}`); "Bekliyor" kuralı yalnız #tasarim'da çalıştı;
onaylanan istek "Sonraki adım: Tasarım onayı" görevini açtı; yönetici
(rol 2) üyesi olmadığı özel kanaldaki görevle başlamadı; derinlik 6'daki
çalışmanın olayı işlemi derinlik 7'de başlattı, derinlik 7'deki hiç
başlatmadı; olay beklenmezken `ws_events_in`'e satır yazılmadı. Okuyamadığı
kanala ve geçersiz duruma kural kaydı reddedildi. Playwright ile formda
olay, durum ve kanal seçimleri göründü; iki hazır işlem kaydedildi
(kural ve eylem veritabanında doğru). dev.pinegrap.com'da denenmedi.

---

## 2026.4.10 — Uzak ajan: yazan ajan aracı, her mesaj soranın onayıyla ve kendi hesabıyla gidiyor (2026-10-14)

**Neden.** Uzak ajanın dördüncü dilimi (`docs/_plan_tersine_oauth.md` →
"4.6 Uzak ajan"). #347'den beri ajanın mesajlarını alan araç yalnız okuyan
olarak açık olmak zorundaydı: başka bir şirketin asistanı gibi hizmette iş
yapan (bilet açan, kayıt değiştiren) bir ajan seçilemiyordu, çünkü yazan
araç her çağrıdan önce onay ister ve takma ada yazılan mesajın onay yolu
yoktu. Şema yok: onayı bekleyen çağrı zaten `ws_mcp_calls`'ta (`via =
'agent'`, `status = 'pending'`), kartı `ws_ai_changes`'te (`record_type =
'mcp_call'`); isteğin bekleyişi `ws_ai_requests.status`'un var olan `sent`
değeriyle yazılır (ajan isteklerinde başka bir anlamda kullanılmıyordu).

**Ne değişti.**

- `includes/workspace/mcp_agent.php`:
  - Kişilerin kendi hesabıyla bağlandığı (OAuth'lu) sunucuda mesajları alan
    araç "onay ister" kurallı bir araç da olabilir (`ws_mcp_agent_candidates()`,
    `ws_mcp_agent_tool()` → `approve`). Taşıyıcı (bearer) ya da temel
    kimlikli sunucuda kural eskisi gibi: yalnız okuyan.
  - Böyle bir araçta istek gönderilmez: çağrı `pending` olarak saklanır,
    soranın mesajının altına (görevde çalışmaya) `mcp_call` kartı olarak
    düşer (`ws_mcp_agent_propose()` → `ws_mcp_calls_propose()`), istek `sent`
    olur. Mesajın altındaki satır "@takmaad: Ad Soyad onaylayınca
    gönderilecek" der (durum `approval`; ekranlar bu durumda hızlanmaz ve
    `ws_mcp_agent_kick` çağırmaz). "Durdur" bekleyen isteği de kapatır.
  - Kart onaylanınca (`ws_mcp_call_run()` → `ws_mcp_agent_approved()`) istek
    yeniden `running` alınır ve araç yalnız soranın kendi hesabıyla çağrılır
    (`ws_mcp_connection(..., true)`), sitenin hesabıyla asla. Yanıt karar
    mesajı yerine ajanın yanıtı olarak düşer: kanalda mesajın altına
    kilitli, görevde Asistan bölümüne (#356'nın yolu); kartın
    `decision_message_id`'si o yanıtı gösterir. Arka planda süren iş (#353)
    aynı yoldan izlenir; görevin soruları ve iptali de soranın hesabıyla
    gider (`ws_mcp_agent_call_approved()`). Hizmetin reddettiği jeton kartı
    geri verir, istek beklemeye devam eder; kişi hesabını yeniden bağlayınca
    aynı kart çalışır. Başka her hata kartı, çağrıyı ve isteği birlikte
    kapatır.
  - Kartı bir kenara bırakmak isteği "bir kenara bırakıldı" olarak kapatır
    (`ws_mcp_agent_set_aside()`); istek başka yoldan kapanırsa (durdurma,
    kuralın bozulması, saatlik iş) bekleyen çağrı `dismissed`, kartı
    `dismissed` ya da `failed` olur (`ws_mcp_agent_drop_pending()`).
  - Saatlik iş, bağlamı gitmiş bekleyen istekleri kapatır
    (`ws_mcp_agent_approval_gone()`: sunucu silinmiş, soran kişi yok, görev
    silinmiş ya da görülemiyor, soran mesaj silinmiş). Bekçi takılan
    istekleri yalnız `running`'de arar.
  - Kişi başı sınır (3) bekleyenleri de sayar; bunlardan biri onay
    bekliyorsa ileti "onaylayın ya da bir kenara bırakın" der
    (`ws_mcp_agent_limit_problem()`, kanal ve görev ortak).
  - Ayarlardaki seçici böyle bir aracın yanına "her mesaj onay bekler"
    yazar; yardım metni OAuth'lu sunucuda yazan aracın onayla çalıştığını
    söyler; kayıtta kural denetimi `approve`'u da kabul eder.
- `includes/workspace/mcp_ask.php`: kart ajan çağrısında "Bu, mesajı sizin
  hesabınızla … aracı üzerinden @takmaad hizmetine gönderir; oradaki ajan
  onun üzerine iş yapar." uyarısını (silebilen araçta ek cümleyle) ve
  `agent` işaretini taşır; bırakma `ws_mcp_agent_set_aside()`'ı çağırır.
  `changes.php`: bırakan kişi geçer.
- `includes/workspace/delegation.php`: onay bekleyen ajan çalışması
  çekmecede "devam ediyor" sayılmaz (`open_agent` boş), ekran onu
  hızlandırmaz; sınır iletisi ortak yardımcıdan.
- `assets/js/workspace.js`, `workspace_task_delegate.js` (+ `.min.js`):
  ajan kartının başlığı "Ajana gitmeden önce onay bekliyor", onay sonrası
  bildirim "Sizin hesabınızla gönderildi. Ajanın yanıtı verir vermez
  gelir."
- `mcp.php`: sunucu kartında "her mesaj önce onaylanır". API:
  `workspace.changes.list`, `workspace.assistant_requests.list`
  (`agent = mcp`, `status = sent` onay bekler) ve `workspace.tasks.delegate`
  açıklamaları; değişikliğin `agent` alanı `mcp` de olabilir.
- `includes/local/tr.json`: 15 yeni metin; ajan aracı yardım metninin son
  cümlesi ayrı anahtara alındı.

**Ödün.** Onay yalnız soran kişide: bir yönetici başkasının bekleyen
mesajını onaylayamaz (durdurabilir). Onay, sitenin hesabıyla gitmez; soran
kendi hesabını bağlamamışsa kart "hesabınızı bağlayın" der ve bekler. Her
mesaj ayrı kart: aynı ajanla art arda yazışmada her biri onay ister. Onay
bekleyen istek kendiliğinden süresi dolmaz; saatlik iş yalnız bağlamı
gitmişleri kapatır.

**Denendi.** Birim testleri (`tests/workspace_mcp_test.php`: OAuth'lu
sunucuda yazan aracın aday olması ve `approve` işareti, taşıyıcı kimlikli
sunucuda olmaması; onaylı çağrının soranın hesabıyla gitmesi). Sandbox'ta
`192.0.2.2:8001`'de yalnız kişinin jetonunu kabul eden sahte bir
2026-07-28 ajanıyla (`send_message`, yazan araç): kanalda `@helpdesk`
mesajı kart oldu, onaydan önce sunucuya hiçbir çağrı gitmedi; onay mesajı
kişinin jetonuyla gönderdi, yanıt mesajın altına kilitli düştü (`<@user:1>`
etiketi bozuldu), kart yanıtı gösterdi; bırakma isteği kapattı; "Durdur"
kartı kapattı, sonra onay reddedildi; aracın hatası kartı, isteği ve
çağrıyı kapattı; uzun iş arka planda sürdü, soru yanıtı getirdi; yalnız
sitenin hesabı varken kart "bağlayın" dedi ve bekledi, hesap bağlanınca
aynı kart çalıştı; soran mesaj silinince saatlik iş isteği kapattı; görevde
devir kart oldu, çekmece ikinci çalışmayı reddetti, onay yanıtı Asistan
bölümüne yazdı. Tarayıcıda (Playwright) kartın başlığı ve onay satırı.
`tools/` denetimleri, `tests/` (758) ve `build_js.php --check` geçti.

---

## 2026.4.10 — Ürün çoğaltma: "Assortment" seçimindeki SQL enjeksiyonu kapandı (2026-10-14)

**Neden.** #355'in kapsam dışı notu. Ürünleri toplu çoğaltma ekranının
(`duplicate_products.php`) gönderiminde "Assortment" seçiminin değeri
(`option_id`) `duplicate_products()`'a ham geçiyor ve kopyanın
`products_attributes_xref` satırını yazan `INSERT`'e kaçışsız, tırnak
içinde ekleniyordu. Ekrana `validate_ecommerce_access()` ile e-ticaret
yetkisi olan her kullanıcı (Kullanıcı rolü dahil) giriyor; form CSRF
jetonu istiyor ama jetonu kendi oturumu veriyor. Sandbox'ta eski kodla
`SLEEP` içeren bir değer iki özellikli bir üründe isteği 2,0 s
bekletti (özellik başına bir kez); değer sorguya alt sorgu da sokabiliyor.
2026.4.9'da da var.

Aynı satırlarda iki eski hata daha vardı. Döngü seçimi aynı değişkene
geri yazdığı için "-Do not Change-" seçiliyken birden çok özelliği olan
üründe kopyanın bütün özellikleri ilk özelliğin seçeneğini alıyordu
(sandbox: Renk Mocha + Beden 40 olan ürünün kopyası Renk Mocha + Beden
"Mocha"). Bir seçenek seçilince de o seçenek ürünün **her** özelliğine
yazılıyordu (Beden seçeneği Renk'e de). Ayrıca "Silinecek Harf Sayısı"
boşken temizleme koşulu (`!isset(...) && !is_numeric(...)`) hiç
tutmuyordu; `-''` PHP 8'de `TypeError` verdiği için gönderim 500 ile
duruyordu.

**Ne değişti.** Seçim, tek bir ürün kopyalanmadan önce bir kez
`product_attribute_options`'ta aranıyor: yalnız rakamlardan oluşan ve
var olan bir seçeneğin kimliği kabul ediliyor, `duplicate_products()`'a
satırın kendisi (`id`, `product_attribute_id`) geçiyor. Başka her değer
(olmayan kimlik, metin, dizi) hiçbir şey yazmadan ekrana "Seçilen
Assortment seçeneği bulunamadı, bu yüzden hiçbir ürün çoğaltılmadı."
hatasıyla dönüyor (yeni `tr.json` anahtarı). Seçenek yalnız ait olduğu
özelliğin satırında kullanılıyor, diğer özellikler aslın seçeneğini
koruyor; döngü her satırda kendi değişkeniyle başlıyor. Bu `INSERT`'in üç
değeri `(int)` ile yazılıyor. "Silinecek Harf Sayısı" sayı değilse ya da
1'den küçükse alanın varsayılanı olan 1 kullanılıyor. Form, açılır liste
ve liste ekranı aynı; şema değişikliği yok.

Kapsam dışı, not edildi: açılır listedeki on seçenek (`21`, `134`, `23` …
"Assortment A–Y") tek bir mağazanın kimlikleriyle koda yazılmış; başka
sitelerde bu kimlikler yok ya da başka özelliklere ait. Düzeltmeden sonra
bu sitelerde seçim artık çöp satır yazmıyor, hata veriyor. Listenin
sitenin kendi özelliklerinden kurulması ayrı bir karar.

**Doğrulandı (sandbox, MariaDB, PHP 8.3).** Örnek ürüne ikinci özellik
(Beden 40) eklenip eski ve yeni kod aynı yedi gönderimle karşılaştırıldı.
Eski kod: `SLEEP` değeri 2,04 s bekletti ve ürün yazdı; olmayan kimlik
(999) ve dizi çöp seçenekli kopya yazdı; "Değiştirme"de Beden ilk
özelliğin seçeneğini aldı; geçerli Beden seçeneği Renk'e de yazıldı; boş
harf sayısı 500 verdi. Yeni kod: `SLEEP` değeri, 999 ve dizi 0,02 s'de
`duplicate_products.php`'ye döndü, ürün yazılmadı, hata bir kez Türkçe
göründü; "Değiştirme"de Renk Mocha + Beden 40; Beden 38 seçilince yalnız
Beden değişti; iki ürün + Renk Yeşil seçiminde iki kopya doğru; boş harf
sayısında kopya 1 harf silinerek yazıldı. Sekiz filtrede ekran HTML'i eski
kodla aynı (yalnız betik adına bağlı yardım bağlantısı farklı); sunucu
günlüğüne uyarı düşmedi. `tools/` denetimleri ve `build_js.php --check`
geçti. Doğrulanamadı: dev.pinegrap.com'daki gerçek kayıtlar.

---

## 2026.4.10 — Uzak ajan: göreve verilen ya da görev notunda yazılan takma ada ajan kendisi yanıt veriyor (2026-10-14)

**Neden.** Uzak ajanın üçüncü dilimi (`docs/_plan_tersine_oauth.md` → "4.6 Uzak
ajan"). #347'den beri kendisi ajan olan bir MCP sunucusu (ayarlarda "ajan
aracı" seçili) kanalda takma adını kendi yanıtlıyordu, görevde ise hâlâ
Pinegrap AI çalışıyordu: görevi takma ada vermek (#333) ya da görev notunda
`@takmaad` yazmak (#345) Pinegrap AI'ı o sunucunun araçlarıyla
çalıştırıyordu. Pinegrap AI bağlı değilse takma ad görevde hiç
seçilemiyordu. Şema yok: görevin çalışması zaten `ws_ai_requests`'te
(`task_id`, `note_text`, `note_reply`, `mcp_server_id`); ajanın çalışması
aynı satıra `agent = 'mcp'` ile yazılır.

**Ne değişti.**

- `includes/workspace/delegation.php`:
  - Takma adın kuyruğu sunucuya göre seçilir (`ws_assistant_agent_of()`,
    saf): ajan modundaki sunucu `mcp`, kalanı eskisi gibi `ai`. Çalışma
    `agent = 'mcp'` ve `mcp_server_id` ile açılır; `ws_task_run_key()` onu
    da `mcp:<id>` okur.
  - `ws_assistants()`: ajan modundaki sunucu, Pinegrap AI bağlı olmasa da
    listelenir; kuralı kanaldakiyle aynı (`ws_mcp_agent_problem()`: sunucu
    açık, ajan aracı açık ve yalnız okuyan, OAuth'lu sunucuda kişinin
    hesabı). Çekmecenin seçicisi, görev notu ve takma adın bildirimi
    (`ws_task_delegation_alias_problem()`) aynı kuralı okur.
  - Ajana yalnız Pinegrap AI'a sorulabilen yerde sorulur: görevin kanalı
    varsa `ws_ai_channel_allowed()` (özel kanalda yapay zekâ anahtarı).
    Kişi başı sınır MCP ajanlarınınki (3, kanaldaki isteklerle ortak).
  - Çekmecedeki "Durdur", süren ajan çalışmasının sunucudaki görevini de
    iptal eder (`ws_mcp_agent_task_stop()`, `tasks/cancel`).
- `includes/workspace/mcp_agent.php`:
  - Ajana giden metin `ws_mcp_agent_task_compose()` (saf) ile yazılır:
    görevin numarası ve başlığı, "Açıklama:", aynı ajanın bu görevdeki son
    yanıtı ("Bu görevdeki son yanıtınız:", alıntı olarak) ve "Talimat:"
    (devrin talimatı ya da notun metni; baştaki `@takmaad` atılır, notta
    yalnız takma ad yazıyorsa talimat bölümü yok). Diğer notlar, kişiler ve
    tarihler gitmez. Metinler kanaldaki sınırlarla kesilir.
  - Yanıt mesaj yerine görevin Asistan bölümüne yazılır
    (`ws_task_delegation_answer()`: notun yanıtı, devredenin bildirimi,
    çekmecede "@takmaad yanıtladı"). `<@` / `<#` etiketleri bozulur, yanıt
    kimseyi anmaz. Hata da devrin yolundan bildirilir
    (`ws_task_delegation_told()`).
  - Kurallar her gönderimde ve her soruda yeniden denetlenir: soran görevi
    göremiyorsa, kanalın izni kalktıysa, sunucu ya da araç kapatıldıysa
    istek kapanır (süren görev sunucuda da iptal edilir).
  - Arka planda süren iş (#353) görevde de aynı yoldan: çekmecede ilerleme
    sözü, bitince yanıt.
- `assets/js/workspace_task_delegate.js` (+ `.min.js`): çekmece ajanın
  çalışmasını beklerken `ws_mcp_agent_kick` çağırır (Pinegrap AI'da
  `ws_ai_kick` gibi).
- API'de `workspace.tasks.delegate` açıklaması ajanın kendi yanıtladığını ve
  neyi okuduğunu yazar; ayarlardaki ajan aracı yardım metnine görev cümlesi
  eklendi.

**Ödün.** Ajan görevin notlarını, tarihlerini ve atananlarını okumaz;
isterse kendi araçlarıyla okur. Yazan çağrı için onay kartı (`mcp_call`)
yok: kanaldaki gibi ajan kendi kuralıyla çalışır. Ekranı açık kimse yoksa
istek saatlik işte gönderilir. Pinegrap'ın kendi `workspace_search` aracı
`q`'yu 200 karakterle sınırlar: dev'de bu araç ajan seçilirse kısa başlıklı,
açıklamasız bir görevle denenir.

**Denendi.** Birim testleri (`tests/workspace_delegation_test.php`: takma
adın kuyruğu, çalışmanın anahtarı; `tests/workspace_mcp_test.php`: görevin
metni, son yanıtın alıntısı, talimattaki takma ad, yanıtta etiketlerin
bozulması). Sandbox'ta `192.0.2.2:8001`'de sahte bir 2026-07-28 ajanıyla
(`ask` aracı), Pinegrap AI bağlı değilken: takma ad listede ve çekmecede
seçildi; devir başlık, açıklama ve talimatla gitti, yanıt Asistan bölümüne
düştü, devreden bildirim aldı; notta `@helpdesk` son yanıtı alıntıladı;
yalnız takma ad yazan not yalnız görevi gönderdi; uzun iş görevle döndü,
çekmecede "Adım 2/2", sonra yanıt; çekmeceden durdurma sunucuya
`tasks/cancel` gönderdi; aracın hatası (devreden bildirim aldı), kişi başı
sınır, yapay zekâ izni olmayan özel kanaldaki görev, tüm araçlar kapalıyken
takma adın listeden çıkması ve notun bildirimi. Kanaldaki `@helpdesk`
değişmedi. Tarayıcıda (Playwright) çekmecenin seçicisi, "@helpdesk
bekleniyor" ve ekranın `ws_mcp_agent_kick` çağırması.

---

## 2026.4.10 — Gönderilen Formlar'da sayfa bağlantıları görünüyor, ürün çoğaltma ekranı hata günlüğüne yazmıyor (2026-10-14)

**Neden.** #354 kapsam dışı bıraktığı iki eski uyarıyı not etmişti; ikisi de
2026.4.9'da var ve sandbox'ta eski kodla üretildi.

Gönderilen Formlar (`view_submitted_forms.php`) 100'den çok formda sayfa
bağlantılarını `$output_screen_links`'e hazırlıyor, her bağlantıya hiç
tanımlanmamış `$output_filter_for_links`'i ekliyordu (açılış başına üç
uyarı). Asıl sorun daha büyüktü: `$output_screen_links` sayfaya hiç
basılmıyordu. Liste sunucuda 100'er satır kesildiği için 101. formdan
sonrası ancak adrese elle `?screen=2` yazılarak ya da tarih aralığı / form
seçimi daraltılarak görülebiliyordu; tablonun istemci tarafı arama ve
sayfalama da yalnız o 100 satırda çalışıyor. Gelişmiş filtreler açıkken her
form alanı için `form_field_<id>` oturum anahtarı okunuyor (her alan bir
uyarı, örnekte açılış başına 100'ü aşkın) ve Durum seçimindeki üç
`$status_*_selected` değişkeni tanımsız kalıyordu.

Ürünleri toplu çoğaltma ekranı (`duplicate_products.php`) `view_products.php`
kopyasıdır ama onun uyarı düzeltmelerini almamıştı: satır döngüsü yalnız
bazı filtrelerin seçtiği sütunları (`selection_type`, `recurring_start`,
`email_page` …) ve sorguda hiç seçilmeyen `required_product_name` ile
`commissionable`'ı her satırda okuyordu (filtreye göre satır başına 14–22
uyarı); `$where`, `$sql_join`, `$filter_specific_columns`,
`$filter_specific_join` ve sekiz `*_filter_selected` değişkeni de her
açılışta tanımsızdı (10–12 uyarı).

**Ne değişti.** Gönderilen Formlar'da bağlantılar `$output_filter_for_links`
olmadan kuruluyor; filtre, form seçimi, tarih aralığı ve sıra zaten oturumda
olduğu için bağlantının taşıması gereken başka değer yok. Bağlantılar kartın
altına basılıyor (Komisyonlar'da ve #350'den beri Yinelenen Komisyon
Profilleri'nde de orada); etkin sayfanın sınıfındaki fazladan tırnak (`active"`) kaldırıldı.
Gelişmiş filtrede alan değeri `?? ''` ile okunuyor, üç durum seçimi
değişkeni boş başlıyor.

Ürün çoğaltmada `view_products.php`'deki kalıp uygulandı: koşullu
değişkenler dosyanın başında boş başlıyor, filtreye özgü satır değerleri
`?? ''` ile okunuyor. Hiçbir yerde kullanılmayan `$required_product` ve
`$commissionable` satırları kaldırıldı. NULL dönen birleştirme sütunları
(`email_page`, `start_page` …) artık boş metin; ekrandaki karşılaştırmalar
ve `h()` çıktısı aynı. Ortak tablo yardımcısına, JS'e ve dil dosyasına
dokunulmadı; şema değişikliği yok.

Kapsam dışı, not edildi: Gönderilen Formlar'da "Dışa Aktar" adresi elle
`[Tümü]` form seçiliyken açılırsa bir uyarı yazıyor (düğme bu durumda
uyarı penceresiyle durduğu için arayüzden ulaşılmıyor). Ürün çoğaltmanın
gönderiminde "Assortment" seçimindeki `option_id` ürün özelliklerini
kopyalayan `INSERT`'e kaçışsız giriyor; ayrı iş olarak ele alınmalı.

**Doğrulandı (sandbox, MariaDB, PHP 8.3).** Ürün çoğaltmanın sekiz
filtresinde (27 ürün) eski kod sunucu günlüğüne sekiz açılışta 1272 uyarı
yazdı, yeni kod hiç yazmadı; sekiz filtrede tablo HTML'i eski kodla satır
satır aynı (yalnız betik adına bağlı yardım bağlantısı ve menü vurgusu
farklı, çünkü eski kod başka adla koştu); bir ürünü çoğaltma gönderimi
uyarısız 302. Gönderilen Formlar'da 160 formla eski kod 1. ve 2. sayfada
`$output_filter_for_links` uyarısı, gelişmiş filtre açıkken 100'ü aşkın
`form_field_*` ve üç `$status_*_selected` uyarısı yazdı ve sayfa
bağlantısı basmadı; yeni kod uyarısız, 1. sayfada 100, 2. sayfada 60 satır
ve «, 1, 2, » bağlantıları (etkin sayfa doğru) gösterdi. Chromium: 1400 ve
390 px'te bağlantılar kartın altında, yatay kaydırma yok, sayfa hatası
yok. `tools/test.php` 754/754; lint, dil, bağlama, API şeması, kopya
denetimleri ve `build_js.php --check` geçti. Doğrulanamadı:
dev.pinegrap.com'daki gerçek kayıtlar.

---

## 2026.4.10 — Dosyalar, Gönderilen Formlar, bölgeler, Site Günlüğü, ürün çoğaltma ve editörün bağlantı seçicisi ortak tablo yardımcısında: çoklu sıralama (son eski liste grubu) (2026-10-14)

**Neden.** Eski liste grupları birer PR'la `pg_list_state()` yardımcısına
taşınıyordu (#331'den #352'ye). #352'nin "Sonraki gruplar" dökümündeki son
grup bu PR'da: Tüm Dosyalarım (`view_files.php`), Gönderilen Formlar
(`view_submitted_forms.php`), beş bölge listesi (`view_regions.php`: Tüm
Reklam / Giriş / Dinamik / Tasarımcı / Ortak Bölgeler), Site Günlüğü
(`view_log.php`), ürünleri toplu çoğaltma ekranı (`duplicate_products.php`)
ve sayfa editörünün bağlantı seçicisi Öğelere Gözat
(`editor_select_page_or_file.php`: Sayfalar, Dosyalar, Kısa Linkler
sekmeleri). Bu PR'la, ayrı çalışmada taşınan Siparişler listesi dışında
panelde sıralamayı çevrilmiş başlık adıyla tutan liste kalmadı.

Altısı da sırayı başlık adıyla tutuyordu: Dosyalar, bölgeler ve ürün
çoğaltma oturumda, Gönderilen Formlar istekteki her değeri oturuma kopyalayan
döngüyle, Site Günlüğü yalnız adreste (hızlı aralık düğmeleri ve gelişmiş
filtre formu `sort`/`order`'ı taşıyordu), bağlantı seçici üç sekmenin
paylaştığı tek oturum anahtarında. Ürün çoğaltma kendi sırasını tutmuyor,
ürün listesinin eski anahtarını (`['ecommerce']['view_products']`)
okuyordu; #331'den beri ürün listesi bu anahtarı yalnız bu ekran için
yazıyordu. Hepsi tek sütunluk ORDER BY (bağlantı seçicide tek dizili
`array_multisort()`) üretiyordu.

Sıralama yönü altı ekranda da `sql_order_direction()`'dan ya da sabit
`asc`/`desc`'ten geçiyor, sütun `switch`'ten seçiliyordu. Sandbox'ta eski
kodla `?order=asc, (SELECT SLEEP(0.3))` ve `sort` içine konan `SLEEP` hiçbir
ekranda koşmadı (sonraki açılış dahil); bu grupta 2026.4.9'da açık yok,
`[GÜVENLİK]` satırı yazılmadı.

**Ne değişti.** Her listenin sıralanabilen sütunları kodla tanımlandı:
Dosyalar `name`, `type`, `size`, `optimized`, `folder`, `description`,
`last_modified`; Gönderilen Formlar `custom_form`, `reference_code`,
`status`, `contact`, `user`, `submitted`, `last_modified` (Kişi eskisi gibi
soyadı, sonra ad: iki ORDER BY düzeyi, ikisi de seçilen yönde); Reklam
Bölgeleri `name`, `display_type`, `created`, `last_modified`; Giriş
Bölgeleri `name`, `not_logged_in_header`, `login_form`,
`not_logged_in_footer`, `logged_in_header`, `logged_in_footer`, `created`,
`last_modified`; Dinamik Bölgeler `name`, `code`, `last_modified`;
Tasarımcı ve Ortak Bölgeler `name`, `content`, `last_modified`; Site
Günlüğü `time`, `user`, `description`, `ip_address`; ürün çoğaltma her
filtrede ekrandaki başlıklar (ürün listesinin kodlarıyla: `id`,
`short_description`, `price`, …, stokta olmayanlarda `out_of_stock_date`);
bağlantı seçici sayfalarda `url`, `folder`, `page_type`, `last_modified`,
dosyalarda `url`, `folder`, `size`, `last_modified`, kısa linklerde `url`,
`destination_url`, `folder`, `last_modified`. Varsayılanlar eskisi gibi:
Site Günlüğü'nde zaman azalan, stokta olmayan ürünlerde stoktan çıkış
tarihi azalan, kalanlarda son değişiklik azalan.

Sıra `pg_list_state('<liste>', …)` ile okunur; beş bölge listesi, bağlantı
seçicinin üç sekmesi ayrı anahtar kullanır (`view_regions_all_ad_regions`,
`editor_select_page_or_file_page` gibi), ürün çoğaltma tek anahtarla her
filtrenin kendi sütunlarını kabul eder. ORDER BY `pg_list_order_by()` ile
yalnız ekranın kod → SQL eşlemesinden yazılır (SQL ifadeleri eskisiyle
aynı). Site Günlüğü'nde eşit satırlar eskisi gibi `log_id` ile ilk düzeyin
yönünde ayrılır. Hata günlükleri gösterilirken (`error_log` kayıtları
veritabanında olmadığı için liste PHP'de birleştirilir) `usort()` artık
seçilen düzeyleri sırayla uygular: zaman sayı olarak, metin veritabanı gibi
büyük/küçük harf ayırmadan (`strcasecmp()`), eşitlikte `log_id` doğal sırayla
(`strnatcmp()`; eskisi `strcmp()` ile 10'u 9'dan önce koyuyordu). Bağlantı
seçici öğeleri PHP'de topladığı için düzeyler `array_multisort()`'a sırayla
verilir; her düzeyde eşit kalan öğeler eskisi gibi öğenin diğer
değerleriyle ayrılır. Başlıklar `get_column_heading()`'e düzey listesi
verir; Shift+tık ve düzey numarası ürünlerdeki gibi çalışır.
`pg_list_sort_menu()` araç çubuğunun sağına (`pg-toolbar-grow` boşluğuyla)
eklendi: Dosyalar'da arama formunun önüne, bölgelerde ekleme düğmesinin
yanına, Site Günlüğü'nde hata günlüğü düğmelerinin yanına, bağlantı
seçicide sekme düğmelerinin yanına; araç çubuğu olmayan ürün çoğaltmaya
yalnız menüyü taşıyan bir `#button_bar` eklendi. Menü ve başlık
bağlantıları ekranın parametrelerini taşır (Dosyalar ve bölgelerde
`filter`, Site Günlüğü'nde tarih aralığı, bağlantı seçicide
`CKEditorFuncNum`). Gönderilen Formlar'daki oturumdaki yönü süzen blok ve
ürün listesindeki eski anahtarı yazan köprü (`view_products.php`)
kaldırıldı; köprüyü okuyan başka bir şey yoktu. Ortak yardımcıya,
`backend.src.js`'e ve `tr.json`'a dokunulmadı; kullanılan metinler zaten
vardı.

Aynı akışta giderilen eski hatalar:

- Gönderilen Formlar'da `switch` İngilizce başlıkla (`'Custom Form'`,
  `'Contact'` …) karşılaştırıyor, başlık bağlantısı ise `lang()` ile
  çevrilmiş adı gönderiyordu; Türkçe panelde hiçbir başlık listeyi
  sıralamıyor, liste hep son değişiklik azalan kalıyordu.
- Site Günlüğü'nde "Hata Günlüklerini Göster" açıkken birleştirilmiş liste
  seçilen sütunu yok sayıp hep zamana göre sıralanıyordu. Türü başlığı
  sıralama bağlantısı gibi görünüyor ama sıralamıyordu; artık düz başlık.
- Bağlantı seçicinin Dosyalar ve Kısa Linkler sekmeleri erişim türü
  süzülmediğinde her satır için tanımsız `access_control_type` uyarısı
  yazıyordu; ürün çoğaltma her açılışta tanımsız `$_GET['filter']`
  uyarısı yazıyordu. İkisi de düzeldi.

Davranış farkları: sıra panel dili değişince kaybolmuyor. Eski
`sort=<başlık>&order=desc` bağlantıları `pg_list_sort_parse()` ile tek
düzey olarak okunur. Site Günlüğü'nün sırası artık oturumda kalıyor; tarih
aralığı düğmeleri ve gelişmiş filtre sırayı adreste taşımıyor. Bağlantı
seçicide her sekmenin kendi sırası var (eskiden Dosyalar'da Boyut seçmek
Sayfalar'a geçince sırayı varsayılana sıfırlıyordu). Ürün çoğaltma ürün
listesinin sırasını devralmıyor, kendi sırasını tutuyor. Yönsüz seçilen
yeni bir sütun artan başlar, varsayılan sütun azalan (diğer listelerle
aynı). Eski oturum anahtarları (`['files']`,
`['forms']['view_submitted_forms']['sort'/'order']`,
`['design']['view_regions'][<filtre>]`,
`['ecommerce']['view_products']`,
`['editor_select_page_or_file']['sort'/'order']`) artık okunmuyor; ilk
açılışta liste varsayılanla başlar. Sütunlar, filtreler, düğmeler, satır
eylemleri, dışa aktarımlar, tarayıcıdaki arama ve sayfa başına kayıt seçimi
değişmedi. Şema değişikliği yok.

**Doğrulandı (sandbox, MariaDB, Chromium; 211 dosya, gönderilmiş formlar,
beş bölge türü, 41 günlük kaydı + örnek `error_log`, 27 ürün, 181 sayfa,
120 dosya, 6 kısa link).** Eski ve yeni kod yan yana koştu. SQL ile
sıralanan dokuz listede (Dosyalar, Gönderilen Formlar, beş bölge, Site
Günlüğü, ürün çoğaltmanın Tüm Ürünler ve Ürün Hareketleri görünümleri) her
sütun iki yönde eskisiyle aynı satırları aynı sıralama değerleri dizisiyle
verdi ve aynı ORDER BY'lı SQL'le örtüştü; sütunların her ikili birleşimi
(ilk azalan, ikinci artan) SQL'in dizisini verdi ve parametresiz yeniden
açılışta korundu; bozuk değer varsayılana düştü (1162 denetim, hepsi geçti). Bağlantı
seçicinin üç sekmesinde her sütun iki yönde eskisiyle satır satır aynı
sırayı verdi; ikili birleşimler iki yön karışımıyla PHP'nin karşılaştırma
kurallarına göre sıralı geldi, sayfa aynı sırayı gösterdi ve yeniden
açılışta korudu (246 denetim). Site Günlüğü'nde hata günlükleri açıkken
her tek sütun ve her ikili birleşim birleştirilmiş listede sıralı geldi,
`error_log` kayıtları listedeydi (81 denetim). Yeni kodla `order`/`sort`
içine konan `SLEEP` hiç koşmadı, sonraki açılış da beklemedi. Eski kodla
Türkçe panelde Gönderilen Formlar Türkçe başlıkla sıralanmadı, yenisiyle
sıralandı. Tarayıcıda 1400 pikselde başlığa tık tek sütuna, Shift+tık
ikinci düzeye çevirdi, menü rozeti 2 gösterdi; 390 pikselde altı ekranda
yatay kaydırma yok, sayfa hatası yok. Sunucu günlüğünde değişiklikten
sonra bu dosyalardan yeni uyarı yok; ürün çoğaltmanın satır başına yazdığı
tanımsız anahtar ve değişken uyarıları (`required_product_name`,
`$filter_specific_columns` …) eski kodda da aynı, bu PR'ın kapsamı dışında
kaldı. Doğrulanamadı: dev.pinegrap.com'daki gerçek kayıtlar ve gerçek bir
telefonda dokunuş.

---

## 2026.4.10 — Uzak ajan: arka planda süren iş (MCP görevleri) ve isteği durdurma (10.110) (2026-10-14)

**Neden.** Uzak ajanın ikinci dilimi (`docs/_plan_tersine_oauth.md` → "4.6 Uzak
ajan"). Bir ajanın işi bir çağrıya sığmayabilir: bir rapor hazırlamak, başka
bir sistemde iş başlatmak, bir insanın onayını beklemek. MCP'nin güncel
neslinde (2026-07-28) sunucu böyle bir çağrıya sonucu değil bir görev tutamacı
döner (`io.modelcontextprotocol/tasks` eki, `resultType: "task"`); istemci
görevi `tasks/get` ile sorar, görev bitince sonuç aynı yanıtta gelir. İstemci
bu yanıtı bugüne dek "desteklenmiyor" hatası sayıyordu: ajan işi kabul ettiği
hâlde mesajın altında "yanıtlayamadı" yazıyordu; 45 saniyeyi aşan bir yanıt da
zaman aşımına düşüyordu.

**Ne değişti.**

- İstemci (`includes/mcp/client.php`):
  - Güncel nesilde her istek `_meta` içinde
    `io.modelcontextprotocol/clientCapabilities` taşır. Belge bunu zorunlu
    sayıyor, önceden hiç gönderilmiyordu: izlenen ek yoksa boş nesne, varsa
    `extensions` altında adı. Bağlantı dizisine `extensions` eklendi,
    varsayılanı boş. Görevi izleyemeyen çağıran (Pinegrap AI'ın araç döngüsü,
    form) görev bildirmez; sunucu ona görev dönmemeli, dönerse eskisi gibi
    hata. Eski nesil (2025-11-25) görevi el sıkışmadaki yetenekle açıyordu;
    site orada görev bildirmez, o sunucular eskisi gibi bekletilerek
    yanıtlanır.
  - `pg_mcp_client_send()`: görevi bildiren çağırana `resultType: "task"` hata
    değil, `task` alanıyla başarılı yanıttır. `input_required` (çok turlu
    istek) kendi sözüyle hata: "daha fazla bilgi istedi, buradan verilemiyor".
  - Yeni: `pg_mcp_client_task()` (saf: kimlik en çok 255 bayt, denetim
    karakteri yok; bilinmeyen durum `working` sayılır; `statusMessage` tek
    satır, 200 karakter; `failed`'da hatanın mesajı), `pg_mcp_client_get_task()`
    (yanıttaki kimlik sorulanla aynı olmalı), `pg_mcp_client_cancel_task()`.
    `tasks/*` isteklerinde yalnız `Mcp-Method` başlığı gider, `Mcp-Name` yalnız
    `tools/call`'ın.
  - Plandan sapma: 2026-07-28 ekinde `tasks/result` yok (plan bunu yazıyordu),
    sonuç `tasks/get`'in `completed` yanıtında; `tasks/list` de kalktı.
    Kaynak: MCP 2026-07-28 değişiklik notu madde 6 ve `ext-tasks` deposunun
    `schema/2026-07-28/schema.ts`'i.
- Şema 10.110 (`upgrade_2026_4_10_workspace_mcp_tasks()`): `ws_mcp_calls.status`
  ENUM'una `running` (önce tip okunur, yalnız genişletilir), ardından
  `ws_ai_requests.mcp_task` VARCHAR(255) '' (sunucunun verdiği görev kimliği),
  `mcp_task_poll` INT UNSIGNED (sıradaki sorunun zamanı), `mcp_task_note`
  VARCHAR(255) (sunucunun son ilerleme sözü). Çalışma Alanı'nın 10.80–10.89
  aralığında yalnız iki etiket kaldığı ve başka sohbetler onları alabileceği
  için dosyanın kendi yorumundaki ikinci Çalışma Alanı aralığından 10.110
  seçildi.
- `includes/workspace/mcp_agent.php`:
  - Ajanın çağrısı görevi izleyen bağlantıyla gider (`ws_mcp_agent_connection()`;
    şema adımından önce izlemez). Görev dönerse istek `running` kalır, kimlik
    saklanır, çağrının kaydı `running` olur (`finished_at` 0); görev bitince
    süresi çağrıdan itibaren yazılır.
  - `ws_mcp_agent_poll()`: sırası gelen görevi `tasks/get` ile sorar.
    `completed` → sonuç, bekletilmeden gelen yanıtla aynı yoldan
    (`ws_mcp_agent_result()`, `ws_mcp_agent_post()`: `isError`, boş yanıt,
    kilitli mesaj, bozulan etiketler). `failed` → "İş bir hatayla sona erdi:
    …". `cancelled` → istek iptal, sunucunun sözüyle. `input_required` →
    görev sunucuda iptal edilir, istek "daha fazla bilgi istedi" ile kapanır
    (bilgi vermek, `tasks/update`, sonraki bir iş). `working` → sunucunun
    istediği aralıkla (2 sn–10 dk; söylemezse 5 sn) yeniden sorulur, ilerleme
    sözü mesajın altındaki satıra yazılır. Ulaşılamama, 429 ya da 5xx'te 60 sn
    sonra yeniden sorulur; sunucu görevi tanımıyor ya da reddediyorsa istek
    kapanır.
  - Kurallar her soruda yeniden denetlenir (`ws_mcp_agent_context()`,
    bekletilmeden gelen yanıtın denetimiyle aynı): sunucu ya da araç
    kapatıldıysa, kanalın izni kalktıysa, soran üye değilse ya da mesaj
    silindiyse görev sunucuda da iptal edilir (`ws_mcp_agent_task_stop()`;
    kapatılmış sunucu durdurmak için bile çağrılmaz).
  - Bir soru, zamanı koşullu güncellemeyle ileri alınarak sahiplenilir: iki
    ekran aynı görevi aynı anda sormaz; soran ekran ölürse soru 50 sn sonra
    yeniden sorulur.
  - `ws_mcp_agent_kick()` önce sırası gelen en çok 5 görevi sorar, sonra
    bekleyen isteği gönderir. 5 dakikalık "zamanında yanıt vermedi" kuralı
    görevli isteğe uygulanmaz; bir günü aşan görev sunucuda iptal edilir ve
    istek "işi bir gün içinde bitirmedi" ile kapanır (bekçi her turda en çok 5).
  - Durdurma: soran ya da bir yönetici bekleyen veya süren isteği mesajın
    altındaki satırdan "Durdur" ile durdurur (`ws_mcp_agent_cancel()`, eylem
    `ws_mcp_agent_cancel`, panel eylem listesine eklendi). İstek önce koşullu
    güncellemeyle iptal olur, görev varsa ardından `tasks/cancel` gider; yolda
    olan bir yanıt yazılmaz (yazmadan önce durum okunur). Görevle dönen çağrı
    gelmeden istek durdurulmuşsa görev hemen iptal edilir.
- `claude.php`: satırın durumu kişiye göre (`cancel`). `assets/js/workspace.js`
  (+ `.min.js`): satırda onay sorulu "Durdur" bağlantısı; ekran ajan isteği
  beklerken de sürerken de `ws_mcp_agent_kick` çağırır (görev soruları
  ekranlar ve saatlik işle taşınır).

**Ödün.** Ekranı açık kimse yoksa biten görev saatlik işte yazılır (bekleyen
istek gibi). Ajanın ek bilgi istemesi (`input_required`, `tasks/update`) henüz
desteklenmiyor: görev iptal edilir, satır nedenini söyler. Sunucunun anlık
bildirimleri (`notifications/tasks`, `subscriptions/listen`) kullanılmaz; PHP'nin
istek başına modeline yoklama uyar. Pinegrap'ın kendi MCP sunucusu görev
dönmez: dev'de görev yolu denenemez, yalnız bekletilmeden gelen yanıtın
değişmediği ve durdurma denenir.

**Denendi.** Birim testleri: istemci (`tests/mcp_client_test.php`: her istekte
yetenek bildirimi, görevle yanıt ve `tasks/get` ile sonuca kadar, `tasks/cancel`,
başka görevin yanıtı reddedilir, görev biçimleri, `input_required`), Çalışma
Alanı (`tests/workspace_mcp_test.php`: soru aralığı, kimin durdurabileceği).
Sandbox'ta `192.0.2.2:8443`'te https konuşan sahte bir 2026-07-28 ajanıyla
(`ask` aracı, görev eki) uçtan uca: bekletilmeden yanıt değişmedi; uzun iş
görevle döndü, aralığa uyularak soruldu, satırda "Adım 2/3", bitince yanıt
kilitli düştü (etiket bozuldu, çağrı kaydı 7000 ms); `failed`, `input_required`
(sunucuya `tasks/cancel` gitti), sunucunun iptali, kişinin durdurması (kuyrukta
ve sürerken), bir günü aşan görev, araç kapatılınca durma, sunucu düşünce 60 sn
sonra yeniden sorma, sunucu görevi unutunca kapanma. Tarayıcıda satırdaki
"Durdur", onay sorusu ve ekranın görev sürerken `ws_mcp_agent_kick` çağırması.
Şema adımı sandbox'ta 10.89'dan yükseltmede ve iki kez koşturuldu (ikincide
atlandı).

---

## 2026.4.10 — Yönlendirme kaynağı, kişinin siparişleri, ziyaretçi raporu, otomatik diyalog ve anahtar kod listeleri ortak tablo yardımcısında: çoklu sıralama, sıralama yönündeki SQL enjeksiyonu kapandı (2026-10-14)

**Neden.** Eski liste grupları birer PR'la `pg_list_state()` yardımcısına
taşınıyor (#331'den #349'a). #349'un "Sonraki gruplar" dökümünde ilk sıraya
konan beş liste bu PR'da: Tüm Yönlendirme Kaynakları
(`view_referral_sources.php`), Kişinin Tüm Siparişleri
(`view_orders_for_contact.php`), Tüm Ziyaretçi Raporları
(`view_visitor_reports.php`), Otomatik Diyaloglar (`view_auto_dialogs.php`
+ `includes/templates/view_auto_dialogs.php`) ve Tüm Anahtar Kodlar
(`view_key_codes.php` + `includes/templates/view_key_codes.php`).
Yönlendirme Kaynakları sıralamayı yalnız adreste `asc_or_desc()`
başlıklarıyla, Kişinin Siparişleri oturumda ama yine `asc_or_desc()`
başlıklarıyla, kalan üçü istekteki her değeri oturuma kopyalayan bir
döngüyle tutuyordu; beşi de çevrilmiş başlık adını kendi `switch`'iyle
tek sütunluk ORDER BY'a çeviriyordu.

Otomatik Diyaloglar ve Anahtar Kodlar'da yön (`order`) ORDER BY'a yalnız
`e()` (`escape()`) ile yazılıyordu; tırnak içermeyen bir değer sorguya
olduğu gibi giriyordu (#341, #344, #348'deki açığın aynısı; #348'in
dökümü bu iki ekranı `escape()` kalıbı dışında saymıştı çünkü çağrı `e()`
adıyla yazılmış). Sandbox'ta eski kodla `?order=asc, (SELECT SLEEP(0.3))`
8 diyalogda ve 8 anahtar kodda 2,4 s bekletti; değer oturumda kaldığı için
sonraki her açılışta ve anahtar kodların CSV dışa aktarımında da koştu.
Otomatik Diyaloglar'a Yönetici ve üstü, Anahtar Kodlar'a e-ticaret
yetkisi olan Kullanıcı rolü dahil girebiliyor. 2026.4.9'da da var. Kalan
üç listede sütun `switch`'ten geçiyor, yön `sql_order_direction()` ile
süzülüyordu; `order` ve `sort` içindeki `SLEEP` koşmadı.

**Ne değişti.** Her listenin sıralanabilen sütunları kodla tanımlandı:
Yönlendirme Kaynakları `name`, `code`, `last_modified`; Kişinin Siparişleri
`order_number`, `total`, `order_date`; Ziyaretçi Raporları `name`,
`created`, `last_modified`; Otomatik Diyaloglar `name`, `enabled`, `url`,
`width`, `height`, `delay`, `frequency`, `page`, `created`,
`last_modified`; Anahtar Kodlar `code`, `offer_code`, `offer_message`,
`enabled`, `expiration_date`, `notes`, `single_use`, `report`,
`last_modified`. Varsayılan eskisi gibi: Kişinin Siparişleri'nde sipariş
tarihi azalan, dördünde son değişiklik azalan. Sıra
`pg_list_state('<liste>', …)` ile okunur, ORDER BY `pg_list_order_by()`
ile yalnız ekranın kod → SQL eşlemesinden yazılır (SQL ifadeleri eskisiyle
aynı). Başlıklar `get_column_heading()`'e düzey listesi verir; Shift+tık
ve düzey numarası ürünlerdeki gibi çalışır. Araç çubuğunun sağına
`pg_list_sort_menu()` eklendi (`pg-toolbar-grow` boşluğuyla); araç çubuğu
olmayan Kişinin Siparişleri'ne yalnız menüyü taşıyan bir `#button_bar`
eklendi, Yönlendirme Kaynakları'nda araç çubuğu sütunu #346'daki gibi
tam genişliğe açıldı. İstekteki her değeri oturuma kopyalayan döngü üç
ekrandan kaldırıldı; sıralama dışında onu okuyan bir şey yoktu. Ortak
yardımcıya, `backend.src.js`'e ve `tr.json`'a dokunulmadı; kullanılan
metinler zaten vardı. Siparişler listesine dokunulmadı (ayrı çalışmada).

Aynı akışta giderilen üç eski hata:

- Ziyaretçi Raporları'nda `switch` İngilizce başlıkla (`'Name'`,
  `'Created'`) karşılaştırıyor, başlık bağlantısı ise `lang()` ile
  çevrilmiş adı gönderiyordu; Türkçe panelde Adı ve Oluşturulma
  tıklanınca liste hep son düzenleme azalan kalıyordu.
- Kişinin Siparişleri'nde başlık bağlantıları kişi kimliğini (`id`)
  taşımıyordu. Sayfanın başındaki `validate_contact_access($user,
  $_GET['id'])` kimliksiz istekte rolü Kullanıcı olan rehber yetkilisine
  "Erişim reddedildi" veriyordu; Yönetici ve üstü oturumdaki kişiyle
  devam ediyordu. Sıralama bağlantıları ve menü formu artık `id`'yi
  taşıyor. Aynı dosyada tanımsız `$row_style` ve `$keys_and_values`
  satır başına PHP uyarısı yazıyordu, başlık hücreleri `</td>` ile
  kapanıyordu; ikisi de düzeldi.
- Anahtar Kodlar'ın CSV dışa aktarımı `offers` tablosunu birleştirmediği
  için Teklif Mesajı'na göre sıralıyken `Unknown column
  'offers.description'` hatası veriyordu (dosyada başlık satırı ve hata
  metni). Dışa aktarım bu sütun için teklif açıklamasını alt sorguyla
  okuyor; aynı kodu paylaşan iki teklif olsa da her anahtar kod bir satır.

Davranış farkları: sıra panel dili değişince kaybolmuyor. Eski
`sort=<başlık>&order=desc` bağlantıları `pg_list_sort_parse()` ile tek
düzey olarak okunur. Yönlendirme Kaynakları'nın sırası artık oturumda
kalıyor. Kişinin Siparişleri'nde yeni bir sütun eskiden azalan, artık
artan başlıyor (diğer listelerle aynı). Eski oturum anahtarları
(`['ecommerce']['view_orders_for_contact']['sort'/'order']`,
`['stats']['view_visitor_reports']`, `['view_auto_dialogs']`,
`['ecommerce']['view_key_codes']`) artık okunmuyor; ilk açılışta liste
varsayılanla başlar. Sütunlar, Oluştur / Web Sitesi Analitiği / İçe Aktar /
Dışa Aktar / Tüm Anahtar Kodlarını Sil düğmeleri, satır düğmeleri,
tarayıcıdaki arama ve sayfa başına kayıt seçimi değişmedi. Satır içi
hızlı güncelleme bu PR'ın kapsamında değil. Şema değişikliği yok.

**Doğrulandı (sandbox, MariaDB, Chromium; 8 yönlendirme kaynağı, bir
kişinin 5 siparişi, 7 ziyaretçi raporu, 8 otomatik diyalog, 8 anahtar
kod).** Beş listede her sütun iki yönde ve sütunların her ikili birleşimi
(ilk azalan, ikinci artan), aynı ORDER BY'lı SQL'in sıralama değerleri
dizisini verdi; varsayılan eskisi gibi; parametresiz yeniden açılışta iki
düzeyli sıra korundu; yönsüz kod artan, varsayılan sütun azalan başladı;
bozuk değer (`id;drop table x:desc`) varsayılana düştü; Türkçe başlıklı
eski bağlantı (ör. `sort=Adı&order=desc`) sıraladı; Kişinin
Siparişleri'nde her sıralama bağlantısı ve menü formu `id`'yi taşıdı;
anahtar kod CSV'si dört farklı sırada (Teklif Mesajı dahil) listedeki
sırayla ve her kod bir kez geldi (936 denetim, hepsi geçti; doğrulayıcı
ters beklentiyle hata verdi). Yeni kodla `order`/`sort` içine konan
`SLEEP` hiç koşmadı, sonraki açılış da beklemedi. Eski kodla Türkçe
panelde Ziyaretçi Raporları `sort=Adı` ile sıralanmadı, yenisiyle
sıralandı. Rolü Kullanıcı olan, kişinin rehber grubuna yetkili bir
hesapla eski kimliksiz başlık bağlantısı "Erişim reddedildi" verdi, yeni
bağlantı 5 siparişi sıraladı. Tarayıcıda 1400 pikselde başlığa tık tek
sütuna, Shift+tık aynı sekmede ikinci düzeye çevirdi; menü iki düzeyi ve
rozeti gösterdi; 390 pikselde menü dokunuşla açıldı, yatay kaydırma yok;
beş sayfada da sayfa hatası yok. Sunucu günlüğünde değişiklikten sonra
bu dosyalardan uyarı yok; tek istisna, eski kimliksiz adresin bilerek
denenmesinde değişmeyen `$_GET['id']` satırının yazdığı tanımsız anahtar
uyarısı (yeni bağlantılar bu adresi üretmiyor). Doğrulanamadı: dev.pinegrap.com'daki gerçek
kayıtlar ve gerçek bir telefonda dokunuş.

---

## 2026.4.10 — Gönderilen Formlar, Rehber ve Site Günlüğü: bozuk tarih değeri listeyi çökertmiyor (2026-10-14)

**Neden.** #350, Komisyonlar'da kapattığı çökmenin Gönderilen Formlar
(`view_submitted_forms.php`) ve Siparişler'de de olduğunu not etmişti.
Gönderilen Formlar istekteki her değeri oturuma yazıyor, tarih
varsayılanlarını ise yalnız `start_month` hiç yokken kuruyordu.
`?start_month=x` (ilk açılışta tek başına bile; o zaman öbür beş parça boş
kalıyor) yıl hesabında ve `mktime()`'da PHP 8'de `TypeError` veriyordu
("Beklenmeyen bir hata oluştu", HTTP 500). Değer oturumda kaldığı için
sonraki her açılış da çöküyordu; böyle bir bağlantıya tıklayan yönetici
listeyi oturumu kapanana kadar açamıyordu.

Aynı kalıbı taşıyan iki liste daha çıktı. Rehber (`view_contacts.php`)
parçaları `mktime()`'a `(int)` ile verdiği için orada çökmüyordu, ama
gelişmiş filtrelerin yıl seçimi oturumdaki bitiş yılına kadar sayıyor. PHP
8'de sayı ile sayı olmayan metin metin olarak karşılaştırıldığı için
`?stop_year=abc` (liste bir kez açıldıktan sonra) döngüyü hiç bitirmiyordu:
128 MB sınırında istek "Allowed memory size exhausted" ile düşüyor, sınırsız
bellekte süre sınırına kadar dönüyordu. Değer oturumda kaldığı için Rehber,
yinelenen kişiler görünümü dahil, oturum boyunca açılmıyordu. Site Günlüğü
(`view_log.php`) tarihi oturuma yazmıyor ama adresteki parçaları doğrudan
`mktime()`'a veriyordu; bozuk bir parça o isteği hata sayfasına düşürüyordu
(sonraki düz açılış düzeliyor). Üçü de eski kodla sandbox'ta üretildi;
2026.4.9'da da var.

**Ne değişti.** #350'deki kalıp: altı tarih parçasının her biri ayrı
denetleniyor; oturumda yoksa ya da 1–4 haneli bir sayı değilse o parça
varsayılana dönüyor (Gönderilen Formlar'da bir ay önce → bugün, Rehber'de en
eski kişinin tarihi → bugün). Geçerli parçalar korunuyor, zaten bozulmuş bir
oturum sonraki açılışta kendini düzeltiyor; bitiş yılı en çok dört hane
olabildiği için yıl döngüsü de sınırlı kalıyor. Rehber'de en eski kişi
tarihinin sorgusu ve denetim, yinelenen kişiler görünümünü de kapsasın diye
filtre koşulunun dışına alındı (`contacts.timestamp` indeksli, sorgu
ucuz). Site Günlüğü'nde adresten gelen her parça aynı denetimden geçiyor
(dizi değeri de); geçmeyen parça varsayılanı (yaklaşık 31 gün önce → bugün)
alıyor, `start_month` boşken eskisi gibi bütün aralık varsayılan.

Siparişler'e (`view_orders.php`) dokunulmadı: kendi taşıma PR'ı bu
düzeltmeyi alıyor. Orada aynı yıl döngüsü de var; aynı denetim onu da
kapatır. Şema, dil dosyası ve JS değişikliği yok.

**Doğrulandı (sandbox, MariaDB, PHP 8.3).** Eski kodla: Gönderilen
Formlar'da `?start_month=x` ve ardından düz açılış 500; Rehber'de bir
açılıştan sonra `?stop_year=abc` ve ardından düz açılış 500 (128 MB, bellek
tükendi), yinelenen kişiler görünümünde de aynı; sınırsız bellekte istek
yanıt vermedi ve PHP sunucusu süre sınırında kapandı; Site Günlüğü'nde
`start_month=x` ve `start_day=x` 500. Yeni kodla: Gönderilen Formlar'da yedi
bozuk istek (`start_month=x`, tek başına `start_month=01`, 20 haneli yıl,
`stop_year=abc`, boş gün, dizi değeri, `start_year=x`) ve her birinin
ardından düz açılış 200; eski koddan bozuk kalmış oturum ilk açılışta
düzeldi; geçerli aralık (5 Mart – 6 Nisan 2025) başlıkta ve tarih
değiştirici bağlantılarında doğru; `start_month=x&start_year=2024`'te ay
varsayılana döndü, yıl korundu. Rehber'de 20 haneli başlangıç yılı,
`stop_year=abc`, yinelenen kişiler görünümünde `stop_year=abc` ve
`start_month=x` 200 (128 MB sınırında da). Site Günlüğü'nde dört bozuk istek
200, geçerli aralık bağlantılarda korundu. `tools/test.php` 747/747; lint,
dil, bağlama, API şeması, kopya denetimleri ve `build_js.php --check`
geçti. Doğrulanamadı: dev.pinegrap.com'daki gerçek kayıtlar.

---

## 2026.4.10 — Komisyonlar'da gelişmiş filtre düğmesi, Yinelenen Komisyon Profilleri'nde sayfalama (2026-10-14)

**Neden.** #348 iki listeyi ortak tablo yardımcısına taşırken iki eski
eksiği not edip dokunmamıştı. Tüm Komisyonlar'da (`view_commissions.php`)
gelişmiş filtre paneli (tarih aralığı) vardı ama onu açan düğme yoktu;
panel yalnız adrese `?advanced_filters=true` yazılarak açılıyordu. Kod
düğmenin değişkenlerini (`$output_advanced_filters_*`, Material ikon
adları) hazırlıyor ama hiçbir yere basmıyordu; panel açıkken sayfa da
başlıksız bir kabukla (`pg_page_shell(array('extra classes' =>
'filters_active'))`) çiziliyordu. Tüm Yinelenen Komisyon Profilleri
(`view_recurring_commission_profiles.php`) sayfa sayısını ve
bağlantılarını hesaplıyor ama göstermiyordu, sorguda da LIMIT yoktu: her
açılışta bütün profiller yükleniyor, DataTables onları tarayıcıda 100'erli
bölüyordu. Etkin sayfa sınıfına fazladan bir tırnak (`'active"'`) da
karışmıştı.

Sandbox'ta denerken Komisyonlar'da eski bir çökme daha çıktı: tarih
parçaları istekten oturuma tek tek yazılıyor, varsayılanlar ise yalnız
`start_month` hiç yokken kuruluyordu. Eksik (`?start_month=01` tek başına),
sayı olmayan (`?start_month=x`) ya da çok uzun bir parça `mktime()`'a
string olarak gidiyor ve PHP 8'de `TypeError` ("Beklenmeyen bir hata
oluştu") veriyordu. Değer oturumda kaldığı için liste o oturumun sonraki
her açılışında da çöküyordu; böyle bir bağlantıya tıklayan yönetici
Komisyonlar'ı oturumu kapanana kadar açamıyordu. Eski kodla sandbox'ta
üretildi; 2026.4.9'da da var.

**Ne değişti.** Komisyonlar: araç çubuğuna, sıralama menüsünün sağına
Siparişler ve Rehber'deki (#335) düğme aynı kalıpla eklendi (`btn-ghost`;
kapalıyken `bi-funnel`, açıkken `bi-funnel-fill` ve `active`; başlığı ve
`aria-label`'ı "Gelişmiş Filtreler Ekle" / "Gelişmiş Filtreleri Kaldır").
Sayfa kabuğu iki durumda da aynı (başlık ve açıklama yerinde); panel
Siparişler'deki gibi `<main>`'in içinde çiziliyor. Altı tarih parçasının
her biri ayrı denetleniyor: oturumda yoksa ya da 1–4 haneli bir sayı
değilse o parça varsayılana (bir ay önce → bugün) dönüyor. Bozuk bir
değer listeyi artık çökertmiyor, zaten bozulmuş bir oturum da sonraki
açılışta kendini düzeltiyor. Durum varsayılanı (`[Tümü]`) yalnız oturumda
yokken kuruluyor.

Yinelenen Profiller: sayfa numarası ortak yardımcının (`pg_list_state()`)
döndürdüğü `screen` değerinden okunuyor (Ürünler'deki gibi; elle yazılmış
denetim kaldırıldı, kural aynı: pozitif tam sayı, yoksa 1). Sorgu `LIMIT`
ile 100 kayıt getiriyor, bağlantılar tablonun altında Komisyonlar'daki
gibi çıkıyor. Sıralama oturumda tutulduğu için bağlantılar yalnız
`screen` taşıyor ve çoklu sıra sayfalar arasında korunuyor. Fazladan
tırnak düzeltildi.

Ödün: Yinelenen Profiller'de tarayıcıdaki arama ve sayfa başına kayıt
seçimi artık açık sayfanın 100 kaydı içinde çalışıyor (Komisyonlar ve
Rehber'deki gibi). Sütunlar, varsayılan sıralar ve çoklu sıralama
değişmedi. Ortak yardımcıya, `backend.src.js`'e ve `tr.json`'a
dokunulmadı; kullanılan metinler zaten vardı. Şema değişikliği yok.
Hâlâ dokunulmayan: Komisyonlar'daki durum filtresi (`$sql_status`) hiç
uygulanmıyor (#348'in notu). Siparişler (`view_orders.php`) ve Gönderilen
Formlar (`view_submitted_forms.php`) tarih parçalarını aynı biçimde
`mktime()`'a veriyor; bu PR'da bakılmadı.

**Doğrulandı (sandbox, MariaDB, Chromium; 250 yinelenen profil, 12
komisyon; 47 denetim, hepsi geçti).** Yinelenen Profiller varsayılan
sırayla 100 / 100 / 50 satırlık üç sayfa verdi, satırlar aynı ORDER BY'lı
SQL'in sırasıyla eşleşti; `amount:asc` ikinci sayfada korundu; iki
düzeyli sıra (`frequency:asc,amount:desc`) ikinci sayfada doğru 100
satırı verdi; `screen` için `abc`, `0`, `-2`, `1.5`, `1;select` ilk
sayfaya düştü, sondan sonraki sayfa boş geldi (sorgu hatası yok); tek
etkin sayfa öğesi, fazladan tırnak yok; tarayıcıda 2'ye tıklayınca ikinci
sayfa açıldı ve etkin oldu. Komisyonlar'da düğme kapalıyken `bi-funnel`,
panel yok; tıklayınca panel açıldı, düğme `bi-funnel-fill` + `active`,
başlık aynı kaldı; panelden Güncelle'de panel açık kaldı; araç
çubuğundaki düğme ve panelin X'i paneli kapattı. Altı bozuk ya da eksik
tarih isteğinin her biri ve ardından gelen düz açılış listeyi gösterdi
(eski kodla `?start_month=x` ve sonraki açılış hata sayfası verdi). 390
pikselde iki listede de yatay kaydırma yok; konsolda hata yok.
Doğrulanamadı: dev.pinegrap.com'daki gerçek kayıtlar.

---

## 2026.4.10 — Uzak ajan: kendisi ajan olan MCP sunucusu takma adını kendi yanıtlıyor (10.89) (2026-10-14)

**Neden.** Faz F'nin son parçası, seviye C (`notes/mcp-arastirma.md` §3): bazı MCP
sunucuları yalnız araç sunmaz, kendisi bir ajandır (başka bir Pinegrap sitesi, bir şirketin
asistanı); mesajı alır ve yanıtlar. Bugüne dek takma ada yazılan her mesajı Pinegrap AI o
sunucunun araçlarıyla yanıtlıyordu; Pinegrap AI'ı olmayan site böyle bir ajana kanaldan hiç
soramıyordu. Tam hâli (uzun süren iş, görev ve not, yazan araç onayı, konuşmanın sürmesi) tek
PR'a sığmadığı için dilimlere bölündü (`docs/_plan_tersine_oauth.md` → "4.6 Uzak ajan"); bu
ilk dilim, kanalda uçtan uca çalışan en küçük parçadır.

**Ne değişti.**

- Şema 10.89 (`upgrade_2026_4_10_workspace_mcp_agent()`): `ws_mcp_servers.agent_tool`
  VARCHAR(128) '' — mesajları alan aracın adı, '' iken takma adı Pinegrap AI yanıtlar;
  `ws_mcp_calls.via` ENUM'una `agent` (önce tip okunur, yalnız genişletilir; sütun ondan sonra
  eklendiği için sütunun varlığı ENUM'un da genişlediğini söyler). İstek `ws_ai_requests`'te
  `agent = 'mcp'` ile bekler; sütun VARCHAR, değişiklik yok.
- Yeni modül `includes/workspace/mcp_agent.php`:
  - `ws_mcp_agent_argument($tool)` (saf): mesajın gideceği argüman — aracın tek zorunlu metin
    argümanı; zorunlu yoksa adı mesaj aldığını söyleyen metin (`message`, `prompt`, `query`…).
    Başka bir şey de zorunluysa ya da böyle bir metin yoksa ''. Değer listesi (`enum`,
    `const`) ya da biçimi (`format`: tarih, adres) olan argüman metin sayılmaz; sayfalama
    imleci ya da süzgeç gibi ilk metne düşülmez, seçim listesi uzamaz.
  - `ws_mcp_agent_candidates()` / `ws_mcp_agent_tool()`: seçilebilen araç tek metinle
    çağrılabilen ve yalnız okuyan kurala açılabilen araçtır (sunucu salt okur dedi ya da bir şey
    demedi); kullanılabilmesi için seçili, sunucunun listesinde ve `auto` / `auto_declared`
    kuralıyla açık olmalı. Yazan (`approve`) araç bu dilimde seçilemez: her mesajın önce onay
    kartı gerekir (dilim 4).
  - `ws_mcp_agent_after_send()`: takma ada yazılmış mesaj ya da ajanın yanıtına cevap
    (`ws_mcp_ask_server()`, cevap eşlemesi artık `agent IN ('ai', 'mcp')`) ajan kipindeki
    sunucunun ise istek kuyruğa girer ve Pinegrap AI sorulmaz. Kapılar: kanal Pinegrap AI'ın
    sorulabildiği kanal olmalı (`ws_ai_channel_allowed()`: özel kanalın sözü yalnız üyeleri
    izin verdiyse siteden çıkar; misafir kanalı hiç), kişi başı 3 bekleyen istek,
    `ws_mcp_ask_problem()` (kapalı, aracı yok, OAuth'ta hesap yok) ve aracın hâlâ kullanılabilir
    olması. Engelde kanala nedenini söyleyen sistem mesajı düşer.
  - `ws_mcp_agent_kick()`: en eski bekleyen isteği koşullu güncellemeyle sahiplenir (iki
    ekran aynı isteği göndermez), araca gönderir, yanıtı soranın mesajının altına, soranın
    adıyla, kilitli (`locked = 1`) yazar; başında "@x, `araç` aracıyla yanıtladı:". Yanıt
    Markdown kalır, `<@` / `<#` etiketleri `‹` ile bozulur (kimseyi anmaz; F1c ve onay kartı
    kararının deseni). Hata (`isError`), boş yanıt ya da ulaşılamama isteği `failed` yapar,
    mesajın altındaki satır nedenini söyler. Her çağrı `ws_mcp_calls`'a `via = 'agent'` ile.
  - Ajan yalnız mesajı ve cevapsa cevaplanan mesajı okur (Markdown alıntısı, yazanın adıyla;
    etiketler sorana görünen adlarına çevrilir, sitenin `<@user:N>` biçimi gitmez), kanalın
    geri kalanını okumaz. Mesajın başındaki `@takmaad` atılır (takma ad sitenin adıdır, ajanın
    değil; `ws_mcp_agent_unaddressed()`); ajanın kendi yanıtı adsız ve "yanıtladı" satırı
    olmadan alıntılanır (soranın adıyla yazılmıştır ama soran yazmadı). Mesaj 4000, alıntı
    2000 karakterle kesilir.
  - Zaman: ekrandan çağrı 45 sn, saatlik işte 90 sn; 5 dakikadır `running` kalan istek
    "zamanında yanıt vermedi", bir gün gönderilemeyen `cancelled` (`ws_mcp_agent_watchdog()`).
- `includes/workspace/actions.php`: mesaj gönderiminde önce ajan, ajanın değilse Pinegrap AI;
  yanıtta `agent: true`. Yeni eylem `ws_mcp_agent_kick` (panel eylem listesine de eklendi).
  `scheduled_messages.php` programlanmış mesajda aynı sırayı izler. `ai.php`
  `ws_ai_after_send()` ajan kipindeki sunucuya yazılan mesajı almaz (ikinci kapı).
- `workspace_recurring_job.php`: `ws_mcp_agent_job()` ekranların taşımadığı istekleri gönderir.
- `claude.php` `ws_claude_request_state()`: `agent = 'mcp'` satırını `ws_mcp_agent_request_state()`
  yazar ("@x bekleniyor / üzerinde çalışıyor / yanıtladı / yanıtlayamadı: …").
- Ayarlar kartı (`mcp.php`): "Araçlar ve ayarlar"ın araç formunda "@x için kim yanıtlar"
  seçimi (Pinegrap AI ya da uygun bir araç, mesajın gideceği argümanla). Seçilen araç aynı
  kayıtta yalnız okuyan kuralla açık değilse kaydedilmez, hata söylenir, takma ad Pinegrap AI'da
  kalır. Kart satırında "`araç` aracıyla kendi yanıtlıyor"; Pinegrap AI bağlı değilken uyarı
  ajan sunucularının sorulabildiğini söyler.
- `assets/js/workspace.js` (+ `.min.js`): ekran ajan isteği bekliyorken `ws_mcp_agent_kick`
  çağırır (`kickAgent`, Pinegrap AI'ınki gibi en çok 4 sn'de bir); `@` menüsünde ajan
  sunucusunun açıklaması "Kendi ajanı yanıtlar" (`BOOT.mcp[].agent`).
- Dış API: `GET /workspace/assistant-requests` `agent` değeri ve süzgeci `mcp`'yi de taşır
  (önceden böyle bir satır `claude` görünürdü).

**Ödün.** Yanıt takma adın kendi üye kimliğiyle değil soranın adıyla, kilitli olarak yazılır:
uygulama dışı bir gönderen (`ws_app_sender()`, `refs.php`) bugün yalnız Claude ve Pinegrap AI'ı
tanıyor; genelleştirmesi dilim 5. Ajan kipindeki sunucuya görev devri ve görev notunda
`@takmaad` bu dilimde değişmedi: Pinegrap AI ajan aracını sıradan bir araç olarak kullanır
(dilim 3).

**Denendi.** Birim testi (`tests/workspace_mcp_test.php`: argüman seçimi, aracın kuralları,
alıntı, baştaki takma ad, yanıtın etiketleri). Sandbox'ta `192.0.2.2:8001`'de sahte bir ajan sunucusuyla
(2026-07-28, `ask` aracı) uçtan uca: ayar kartında seçim (kuralsız seçim reddedildi), kanalda
`@ajan` → yanıt mesajın altında kilitli, ajanın yanıtına cevap aynı ajana alıntıyla gitti,
`isError` satırda göründü, araç kapatılınca kanal nedenini söyledi, Pinegrap AI sorulmadı;
tarayıcıda `@` menüsü ve ekranın `ws_mcp_agent_kick` çağrısı. Şema adımı sandbox'ta iki kez
koşturuldu.

---

## 2026.4.10 — Sayfa stili, tema, tasarım dosyası, konteyner ve yorum listeleri ortak tablo yardımcısında: çoklu sıralama (2026-10-14)

**Neden.** Eski liste grupları birer PR'la `pg_list_state()` yardımcısına
taşınıyor (#331 ürünler, #335 Rehber, #336 Sayfalar, #338 Kullanıcılar,
#340 e-ticaret ayar listeleri, #341 e-posta kampanyaları, #344 takvim ve
menü listeleri, #346 ülke ve nakliye listeleri, #348 reklam, komisyon ve
rehber grubu listeleri). #348'in "Sonraki gruplar" dökümünde ilk sıraya
konan beş liste bu PR'da: Tüm Sayfa Stilleri (`view_styles.php`), Tüm
Temalar (`view_themes.php`), Tasarım Dosyaları (`view_design_files.php`),
Tüm Konteynerler (`view_containers.php`) ve Yorumlar (`view_comments.php`).
Beşi de sıralamayı çevrilmiş başlık adıyla oturumda tutup kendi
`switch`'iyle tek sütunluk ORDER BY kuruyordu; panel dili değişince seçim
kayboluyordu.

Sıralama açığı yok: beşinde de sütun `switch`'ten geçiyor, yön istekten
okunurken `sql_order_direction()` ile süzülüyordu (Konteynerler'deki `escape()`
zaten süzülmüş değere uygulanıyordu). Sandbox'ta eski kodla `order` ve
`sort` içine konan `SLEEP(0.3)` beş listede de koşmadı; changelog'a
[GÜVENLİK] satırı gerekmedi.

**Ne değişti.** Her listenin sıralanabilen sütunları kodla tanımlandı:
Sayfa Stilleri `name`, `theme`, `collection`, `layout_type`,
`last_modified`; Temalar `name`, `description`, `folder`,
`activated_desktop`, `activated_mobile`, `last_modified`; Tasarım
Dosyaları `name`, `type`, `size`, `optimized`, `folder`, `description`,
`last_modified`; Konteynerler `name`, `enabled`, `length`, `width`, `height`,
`weight`, `cost`, `created`, `last_modified`; Yorumlar `name`,
`published`, `featured`, `publish_cancel`, `submitted`. Varsayılan eskisi
gibi: dördünde son değişiklik azalan, Yorumlar'da gönderilme azalan. Sıra
`pg_list_state('<liste>', …)` ile okunur, ORDER BY `pg_list_order_by()`
ile yalnız ekranın kod → SQL eşlemesinden yazılır (SQL ifadeleri eskisiyle
aynı). Başlıklar `get_column_heading()`'e düzey listesi verir; Shift+tık
ve düzey numarası ürünlerdeki gibi çalışır. Araç çubuğunun sağına
`pg_list_sort_menu()` eklendi (`pg-toolbar-grow` boşluğuyla); araç
çubuğu olmayan Yorumlar'a yalnız menüyü taşıyan bir `#button_bar`
eklendi. Ortak yardımcıya, `backend.src.js`'e ve `tr.json`'a
dokunulmadı; kullanılan metinler zaten vardı. Siparişler listesine
dokunulmadı (ayrı çalışmada).

Davranış farkları: sıra panel dili değişince kaybolmuyor. Eski
`sort=<başlık>&order=desc` bağlantıları `pg_list_sort_parse()` ile tek
düzey olarak okunur. Eski oturum anahtarları
(`$_SESSION['software']['design'][...]`,
`['ecommerce']['view_containers']`, `['comments']['sort'/'order']`)
artık okunmuyor; ilk açılışta liste varsayılanla başlar. Sütunlar,
Oluştur / Dosya Yükle / Görsel Sayfa Editörü düğmeleri, toplu silme ve
düzenleme, disk kullanımı satırı, satır düğmeleri, tarayıcıdaki arama ve
sayfa başına kayıt seçimi değişmedi. Satır içi hızlı güncelleme bu PR'ın
kapsamında değil. Şema değişikliği yok.

Fark edilen, dokunulmayan: Temalar ve Yorumlar oturumdaki `query`
değerine göre arama süzgeci kuruyor ama bu değeri yazan bir yer yok
(süzgeç hiç devreye girmiyor). Eskiden beri böyle.

**Doğrulandı (sandbox, MariaDB, Chromium; 50 sayfa stili, 4 tema, 79
tasarım dosyası, 8 konteyner, 8 yorum).** Beş listede her sütun iki yönde ve
sütunların her ikili birleşimi (ilk azalan, ikinci artan), aynı ORDER
BY'lı SQL'in sıralama değerleri dizisini verdi; varsayılan eskisi gibi;
parametresiz yeniden açılışta iki düzeyli sıra korundu; yönsüz kod artan,
varsayılan sütun azalan başladı; bozuk değer (`id;drop table x:desc`)
varsayılana düştü; Türkçe başlıklı eski bağlantı (ör.
`sort=Adı&order=desc`) sıraladı (2160 denetim, hepsi geçti; doğrulayıcı
ters beklentiyle hata verdi). Yeni kodla da `order`/`sort` içindeki
`SLEEP` koşmadı. Tarayıcıda 1400 pikselde başlığa tık tek sütuna,
Shift+tık aynı sekmede ikinci düzeye çevirdi; menü iki düzeyi ve rozeti
gösterdi; 390 pikselde menü dokunuşla açıldı, yatay kaydırma yok; sayfa
hatası yok. Sunucu günlüğünde bu beş dosyadan uyarı yok. Doğrulanamadı:
dev.pinegrap.com'daki gerçek kayıtlar ve gerçek bir telefonda dokunuş.

---

## 2026.4.10 — Reklam, komisyon, rehber grubu, hediye kartı, sipariş raporu ve yinelenen komisyon profili listeleri ortak tablo yardımcısında: çoklu sıralama, sıralama yönündeki SQL enjeksiyonu kapandı (2026-10-14)

**Neden.** Eski liste grupları birer PR'la `pg_list_state()` yardımcısına
taşınıyor (#331 ürünler, #335 Rehber, #336 Sayfalar, #338 Kullanıcılar,
#340 e-ticaret ayar listeleri, #341 e-posta kampanyaları, #344 takvim ve
menü listeleri, #346 ülke ve nakliye listeleri). #346'nın "Sonraki gruplar"
dökümünde ilk sıraya konan altı liste bu PR'da: Tüm Reklamlarım
(`view_ads.php`), Tüm Komisyonlar (`view_commissions.php`), Tüm Rehber
Grupları (`view_contact_groups.php`), Tüm Hediye Kartları
(`view_gift_cards.php`), Tüm Sipariş Raporları (`view_order_reports.php`)
ve Tüm Yinelenen Komisyon Profilleri
(`view_recurring_commission_profiles.php`). Altısı da istekteki her değeri
oturuma kopyalayan bir döngüyle sıralamayı çevrilmiş başlık adıyla tutup
kendi `switch`'iyle tek sütunluk ORDER BY kuruyordu.

Altısında da yön (`order`) ORDER BY'a yalnız `escape()` ile yazılıyordu;
tırnak içermeyen bir değer sorguya olduğu gibi giriyordu (#341 ve #344'teki
açığın aynısı). Sandbox'ta eski kodla `?order=asc, (SELECT SLEEP(0.3))`
satır başına 0,3 saniye bekletti: 5 reklamda 1,5 s, ay içindeki 6
komisyonda 1,8 s, 42 rehber grubunda 12,6 s, 4 hediye kartında 1,2 s, 5
sipariş raporunda 1,5 s, 4 yinelenen profilde 1,2 s. Değer oturumda kaldığı
için listenin sonraki her açılışında da koştu; hediye kartlarının CSV dışa
aktarımı da aynı değeri kullanıyordu. Rolü Kullanıcı olanlar dahil, ilgili
yetkisi olan oturum açmış her kullanıcı girebiliyor: Reklamlar'a en az bir
reklam bölgesini düzenleyebilen, Rehber Grupları'na rehber yetkisi ve en az
bir grubu olan, Komisyonlar, Hediye Kartları ve Yinelenen Profiller'e
e-ticaret yetkisi olan, Sipariş Raporları'na e-ticaret rapor yetkisi olan.
2026.4.9'da da var.

**Ne değişti.** Her listenin sıralanabilen sütunları kodla tanımlandı:
Reklamlar `name`, `ad_region`, `display_type`, `created`, `last_modified`;
Komisyonlar `affiliate_name`, `affiliate_code`, `reference_code`, `status`,
`amount`, `frequency`, `order_number`, `product`, `created`,
`last_modified`; Rehber Grupları `name`, `subscription`, `type`,
`description`, `created`, `last_modified`; Hediye Kartları `code`,
`balance`, `original_amount`, `expiration_date`, `notes`, `from`,
`recipient`, `delivery_date`, `created`, `last_modified`; Sipariş Raporları
`name`, `created`, `last_modified`; Yinelenen Profiller `affiliate_name`,
`affiliate_code`, `enabled`, `amount`, `start_date`, `frequency`,
`commissions`, `order_number`, `product`, `created`, `last_modified`.
Altısında da varsayılan eskisi gibi son değişiklik azalan. Sıra
`pg_list_state('<liste>', …)` ile okunur, ORDER BY `pg_list_order_by()`
ile yalnız ekranın kod → SQL eşlemesinden yazılır (sütunlar eskisiyle
aynı). Başlıklar `get_column_heading()`'e düzey listesi verir; Shift+tık
ve düzey numarası ürünlerdeki gibi çalışır. Araç çubuğunun sağına
`pg_list_sort_menu()` eklendi (`pg-toolbar-grow` boşluğuyla). Araç çubuğu
olmayan Komisyonlar ve Yinelenen Profiller'e yalnız menüyü taşıyan bir
`#button_bar` eklendi; Rehber Grupları'nda araç çubuğu artık rolü
Kullanıcı olana da çıkıyor (Oluştur düğmesi eskisi gibi yalnız Yönetici ve
üstüne). Hediye kartlarının CSV dışa aktarımı listedeki sırayla geliyor.

İstekteki her değeri oturuma kopyalayan döngü kaldırıldı. Döngünün
sıralama dışında taşıdığı değerler artık adıyla oturuma yazılıyor:
Reklamlar'da reklam bölgesi seçimi (`ad_region_id`), Komisyonlar'da
gelişmiş filtrelerin tarih aralığı (`start_*`, `stop_*`) ve `status`
(`view_verified_shipping_addresses.php`'deki #340 kalıbı). Rehber
Grupları, Hediye Kartları, Sipariş Raporları ve Yinelenen Profiller'de
döngüyü okuyan başka bir şey yoktu. Ortak yardımcıya, `backend.src.js`'e
ve `tr.json`'a dokunulmadı; kullanılan metinler zaten vardı.

Davranış farkları: sıra panel dili değişince kaybolmuyor. Eski
`sort=<başlık>&order=desc` bağlantıları `pg_list_sort_parse()` ile tek
düzey olarak okunur. Komisyonlar ve Yinelenen Profiller'de Sipariş
başlığı eskiden bir bağlantıydı ama switch'te karşılığı olmadığı için
tıklayınca listeyi varsayılana döndürüyordu; artık sipariş numarasına
(`orders.order_number`) göre sıralıyor. Sütunlar, Oluştur ve Dışa Aktar
düğmeleri, reklam bölgesi seçimi, komisyon tarih aralığı ve özet
kutuları, sayfalama, satır düğmeleri, tarayıcıdaki arama ve sayfa başına
kayıt seçimi değişmedi. Satır içi hızlı güncelleme bu PR'ın kapsamında
değil. Şema değişikliği yok.

Fark edilen, dokunulmayan: Komisyonlar'da gelişmiş filtreler kapalıyken
onları açan bir düğme çıkmıyor (yalnız `?advanced_filters=true` ile
açılıyor) ve durum filtresi (`$sql_status`) hiç uygulanmıyor; Yinelenen
Profiller sayfalama bağlantılarını hesaplıyor ama göstermiyor ve sorguda
LIMIT yok (hepsi listeleniyor). Üçü de eskiden beri böyle.

**Doğrulandı (sandbox, MariaDB, Chromium; 5 reklam, 22 komisyon, 42
rehber grubu, 4 hediye kartı, 5 sipariş raporu, 4 yinelenen profil).**
Altı listede her sütun iki yönde ve sütunların her ikili birleşimi (ilk
azalan, ikinci artan), aynı ORDER BY'lı SQL'in sıralama değerleri
dizisini verdi; varsayılan son değişiklik azalan; parametresiz yeniden
açılışta iki düzeyli sıra korundu; yönsüz kod artan, varsayılan sütun
azalan başladı; bozuk değer (`id;drop table x:desc`) varsayılana düştü;
Türkçe başlıklı eski bağlantı (ör. `sort=Adı&order=desc`) sıraladı;
reklam bölgesi seçimi ve komisyon tarih aralığı sıralama değişince
korundu (593 denetim, hepsi geçti; doğrulayıcı ters beklentiyle altı
listede de hata verdi). Yeni kodla `order`/`sort` içine konan `SLEEP` hiç
koşmadı, sonraki açılış da beklemedi. Hediye kartı CSV'si listedeki
iki düzeyli sırayla geldi. Tarayıcıda 1400 pikselde başlığa tık tek
sütuna, Shift+tık aynı sekmede ikinci düzeye çevirdi; menü iki düzeyi ve
rozeti gösterdi; 390 pikselde menü açıldı, yatay kaydırma yok; konsolda
yalnız sandbox'ın erişemediği DataTables çeviri dosyası (dokunulmayan
listelerde de aynı). Sunucu günlüğünde bu altı dosyadan uyarı yok.
Doğrulanamadı: dev.pinegrap.com'daki gerçek kayıtlar, rolü Kullanıcı olan
yetkili hesaplar ve gerçek bir telefonda dokunuş.

---

## 2026.4.10 — Ülke, eyalet/il, nakliye bölgesi, nakliye yöntemi ve varış tarihi listeleri ortak tablo yardımcısında: çoklu sıralama (2026-10-14)

**Neden.** Eski liste grupları birer PR'la `pg_list_state()` yardımcısına
taşınıyor (#331 ürünler, #335 Rehber, #336 Sayfalar, #338 Kullanıcılar,
#340 e-ticaret ayar listeleri, #341 e-posta kampanyaları, #344 takvim,
menü, kısa link ve ürün özelliği listeleri). Bu PR, #331'in dökümünde
`asc_or_desc()` kullanan sekiz ekrandan aynı yapıdaki beşini taşıdı:
Tüm Ülkeler (`view_countries.php`), Tüm Eyaletler/İller
(`view_states.php`), Tüm Nakliye Bölgeleri (`view_zones.php`), Tüm
Nakliye Yöntemleri (`view_shipping_methods.php`) ve Tüm Nakliye Varış
Tarihleri (`view_arrival_dates.php`). Beşi de filtresiz, araç çubuğunda
yalnız Oluştur düğmesi olan e-ticaret ayar listeleri. Sıralamayı
oturumda değil yalnız adreste, çevrilmiş başlık adıyla (`?sort=Adı`)
taşıyıp kendi `switch`'iyle tek sütunluk ORDER BY kuruyorlardı; listeden
çıkıp dönünce sıra varsayılana dönüyordu.

Güvenlik açısından eski kod sorunsuzdu: sütun `switch`'ten sabit bir
değer olarak geliyor, yön `sql_order_direction()` ile süzülüyordu
(#341 ve #344'teki `escape()` açığı bu beş listede yok). Changelog'a
[GÜVENLİK] satırı gerekmedi.

**Ne değişti.** Her listenin sıralanabilen sütunları kodla tanımlandı:
Ülkeler `name`, `code`, `zip_code_required`, `transit_adjustment`,
`default`, `last_modified`; Eyaletler/İller `name`, `code`, `country`,
`last_modified`; Nakliye Bölgeleri `name`, `base_rate`,
`primary_weight_rate`, `secondary_weight_rate`, `item_rate`,
`last_modified`; Nakliye Yöntemleri `name`, `message`, `code`,
`service`, `realtime_rate`, `base_rate`, `primary_weight_rate`,
`secondary_weight_rate`, `item_rate`, `handling`, `transit`, `street`,
`po_box`, `protected`, `last_modified`; Varış Tarihleri `name`,
`arrival_date`, `status`, `start_date`, `end_date`, `last_modified`.
Varsayılanlar eskisiyle aynı: Ülkeler ve Eyaletler/İller ad artan, diğer
üçü son değişiklik azalan. Sıra `pg_list_state('<liste>', …)` ile okunur,
ORDER BY `pg_list_order_by()` ile yalnız ekranın kod → SQL eşlemesinden
yazılır; eşlemedeki sütunlar eskisiyle aynı, yalnız tablo adıyla
nitelendi (Eyaletler/İller'de Ülke sütunu eskisi gibi `countries.name`).
Başlıklar `asc_or_desc()` yerine `get_column_heading()`'e düzey listesi
verir; Shift+tık ve düzey numarası ürünlerdeki gibi çalışır. Araç
çubuğunun sağına `pg_list_sort_menu()` eklendi (`pg-toolbar-grow`
boşluğuyla). Ülkeler ve Varış Tarihleri'nde araç çubuğu sütunu
`col-md-6 col-xl-9` yerine `col-12` oldu, menü öbür listelerdeki gibi
sağ kenara oturuyor. `asc_or_desc()` hâlâ `view_log.php`,
`view_orders_for_contact.php` ve `view_referral_sources.php`'de
kullanıldığı için yerinde kaldı. Ortak yardımcıya, `backend.src.js`'e ve
`tr.json`'a dokunulmadı; kullanılan metinler zaten vardı.

Aynı dosyalarda iki küçük düzeltme: Eyaletler/İller'de Ülke hücresi
HTML için kaçışlanmadan yazılıyordu, artık `h()` ile yazılıyor;
Nakliye Bölgeleri ve Varış Tarihleri'nde `<th>` başlıkları `</td>` ile
kapanıyordu, `</th>` oldu.

Davranış farkları: sıra artık oturumda kalıyor (listeden çıkıp dönünce
ve panel dili değişince kaybolmuyor). Eski `sort=<başlık>&order=desc`
bağlantıları `pg_list_sort_parse()` ile tek düzey olarak okunur.
Başlığa ilk tıklama öbür ortak tablo listelerindeki gibi artan
başlıyor; eskiden Son Düzenleme başlığı (Eyaletler/İller dışında)
yönsüz bağlantıyla azalan başlıyordu. Sütunlar, Oluştur düğmesi, satır düğmeleri, durum renkleri,
Nakliye Yöntemleri'ndeki sıralanmayan Nakliye Bölgesi sütunu,
tarayıcıdaki arama ve sayfa başına kayıt seçimi değişmedi. Satır içi
hızlı güncelleme bu PR'ın kapsamında değil. Şema değişikliği yok.

**Doğrulandı (sandbox, MariaDB, Chromium; 242 ülke, 143 eyalet/il, 6
bölge, 8 nakliye yöntemi, 5 varış tarihi).** Beş listede her sütun iki
yönde ve sütunların her ikili birleşimi (ilk azalan, ikinci artan), aynı
ORDER BY'lı SQL'in sıralama değerleri dizisini verdi; parametresiz
açılışta varsayılan geldi; parametresiz yeniden açılışta iki düzeyli sıra
korundu; yönsüz kod artan, varsayılan sütun varsayılan yönde başladı;
bozuk değer (`id;drop table x:desc`) varsayılana düştü; Türkçe başlıklı
eski bağlantı (`sort=Adı&order=desc`) sıraladı; `sort`/`order` içine
konan `SLEEP(0,3)` hiç koşmadı (456 denetim, hepsi geçti; doğrulayıcı
ters beklentiyle beş listede de hata verdi). Tarayıcıda başlığa
Shift+tık aynı sekmede ikinci düzeyi ekledi, menü iki düzeyi ve rozeti
gösterdi; 390 piksel genişlikte menü dokunuşla açıldı, yatay kaydırma
yok. Sunucu günlüğünde bu beş dosyadan uyarı yok.
Doğrulanamadı: dev.pinegrap.com'daki gerçek kayıtlar ve gerçek bir
telefonda dokunuş.

---

## 2026.4.10 — Görev notunda @takmaad: MCP takma adına görev hakkında soru (2026-10-14)

**Neden.** Faz F'nin isteğe bağlı kalanı (`docs/_plan_oauth.md`). #333 görevi
bir MCP takma adına verdirdi; görev notu ise yalnız `@ai` / `@Claude`
çağırıyordu. Kanalda `@github` yazmak Pinegrap AI'ı o sunucunun araçlarıyla
çalıştırırken görev notunda aynı yazı sıradan bir not olarak kalıyordu.
Şema yok: not, devrin kullandığı isteğin aynısını açar
(`ws_ai_requests`, `agent = 'ai'`, `mcp_server_id`, `task_id`).

**Ne değişti.**

- `includes/workspace/mcp_ask.php`: takma adı metinde bulan eşleme saf bir
  fonksiyona ayrıldı, `ws_mcp_alias_named($body, $servers)` (kanaldaki
  `ws_mcp_ask_server()` de onu kullanır; kural aynı: kelime başında `@`,
  büyük/küçük harf fark etmez, e-posta adresi ya da takma adla başlayan
  daha uzun bir kelime sayılmaz, ilk yazılan kazanır).
- `includes/workspace/delegation.php`: `ws_assistant_called()` sırası
  Claude → takma ad → Pinegrap AI. Takma ad Pinegrap AI'ın adından önce
  gelir, kanalda olduğu gibi (aynı notta `@ai` ve `@github` varsa
  `@github`). Takma ad yalnız `ws_assistants()` listesindeyse çağrılır
  (Pinegrap AI bağlı, sunucu açık, en az bir aracı açık). Not talimattır,
  görev devredilmez; model #333'teki gibi görevin başlığını, açıklamasını,
  durumunu, tarihlerini, bu notu ve aynı takma adın görevdeki önceki
  yanıtlarını okur, diğer notları okumaz. Yazan araç çağrısı görevde
  `mcp_call` kartında bekler; uygulayan, notu yazan kişidir.
- Çağrılamayan takma ad sessiz kalmaz: `ws_task_delegation_alias_problem()`
  notun bildirimine nedenini yazar (sunucu kapalı, açık aracı yok ya da
  Pinegrap AI bağlı değil). OAuth'lu sunucuda yazanın hesabı yoksa
  bildirim, çalışmayı başlatan yolun cümlesidir ("Görevin Asistan
  bölümünden kendi hesabınızı bağlayın"). Karar notları
  (`ws_task_note_add(…, 0, false)`) ve uygulamaların notları bugünkü gibi
  hiçbir asistanı çağırmaz; not düzenlemek de çağırmaz.
- `assets/js/workspace.js`: görevin yeni not kutusunda `@` menüsü kişilerden
  önce Claude, Pinegrap AI ve takma adları (`BOOT.mcp`) gösterir. Kanal ve
  Notlar menüleriyle aynı öğeler; iki ortak yardımcıya ayrıldı. Not
  düzenlerken menü yalnız kişileri gösterir.
- Çekmecedeki "henüz asistan çalışmadı" cümlesi, takma ad varken ilkini
  örnek verir ("@ai, @Claude ya da @github gibi bir MCP sunucusu takma adı
  yazan bir not da asistana sorar").

**Denendi.** Birim testi (`tests/workspace_mcp_test.php`:
`ws_mcp_alias_named()` eşlemesi). Çalışmanın kendisi #333'ün yolu
(`ws_task_delegation_run()`, anahtar `mcp:<id>`), orada sandbox'ta
denenmişti; nottan başlaması dev.pinegrap.com kontrol listesinde.

**Kapsam dışı.** Uzak ajan (seviye C) Faz F'nin tek kalanı.

---

## 2026.4.10 — Takvim, etkinlik konumu, menü, kısa link ve ürün özelliği listeleri ortak tablo yardımcısında: çoklu sıralama (2026-10-14)

**Neden.** Eski liste grupları birer PR'la `pg_list_state()` yardımcısına
taşınıyor (#331 ürünler, #335 Rehber, #336 Sayfalar, #338 Kullanıcılar,
#340 e-ticaret ayar listeleri, #341 e-posta kampanyaları). Filtresi
olmayan beş liste tek PR'da taşındı: Takvimler (`view_calendars.php`),
Etkinlik Konumları (`view_calendar_event_locations.php`), Tüm Menüler
(`view_menus.php`), Kısa Linkler (`view_short_links.php`) ve Tüm Ürün
Özellikleri (`view_product_attributes.php`). Beşi de istekteki her değeri
oturuma kopyalayan bir döngüyle sıralamayı çevrilmiş başlık adıyla tutup
kendi `switch`'iyle tek sütunluk ORDER BY kuruyordu.

Takvimler, Etkinlik Konumları ve Menüler'de yön (`order`) ORDER BY'a yalnız
`escape()` ile yazılıyordu; tırnak içermeyen bir değer sorguya olduğu gibi
giriyordu. Sandbox'ta eski kodla `?order=asc, (SELECT SLEEP(0.3))` satır
başına 0,3 saniye bekletti (6 takvimde 1,8 s, 5 konumda ve 5 menüde 1,5 s)
ve değer oturumda kaldığı için sonraki açılışlarda da koştu. Takvimler'e
takvim yetkisi olan her kullanıcı (rolü Kullanıcı olanlar dahil),
Etkinlik Konumları'na Yönetici ve üstü, Menüler'e Tasarımcı ve üstü
girebiliyor. 2026.4.9'da da var. Kısa Linkler ve Ürün Özellikleri
yönü zaten `sql_order_direction()` ile süzüyordu; aynı yük orada koşmadı.

**Ne değişti.** Her listenin sıralanabilen sütunları kodla tanımlandı:
Takvimler ve Etkinlik Konumları `name`, `created`, `last_modified`; Menüler
`name`, `effect`, `created`, `last_modified`; Kısa Linkler `name`,
`destination_type`, `created`, `last_modified`; Ürün Özellikleri `name`,
`label`, `created`, `last_modified`. Beşinde de varsayılan eskisi gibi
son değişiklik azalan. Sıra `pg_list_state('<liste>', …)` ile okunur,
ORDER BY `pg_list_order_by()` ile yalnız ekranın kod → SQL eşlemesinden
yazılır (sütunlar eskisiyle aynı; Hedef Türü ve Efekt eskisi gibi
veritabanındaki değere göre). Başlıklar `get_column_heading()`'e düzey
listesi verir; Shift+tık ve düzey numarası ürünlerdeki gibi çalışır. Araç
çubuğunun sağına `pg_list_sort_menu()` eklendi (`pg-toolbar-grow`
boşluğuyla). İstekteki her değeri oturuma kopyalayan döngü yalnız
sıralama için vardı (başka okuyanı yok), beş dosyadan da kaldırıldı;
Kısa Linkler'deki, yalnız o döngü için oturum dizisini hazırlayan
blok da gitti. Ortak yardımcıya, `backend.src.js`'e ve `tr.json`'a
dokunulmadı; kullanılan metinler zaten vardı.

Davranış farkları: sıra panel dili değişince kaybolmuyor. Eski
`sort=<başlık>&order=desc` bağlantıları `pg_list_sort_parse()` ile tek
düzey olarak okunur. Sütunlar, Oluştur düğmesi (Takvimler'de yalnız
Yönetici ve üstüne), satır düğmeleri (Genişlet, Düzenle, Ziyaret Et,
özellik seçenekleri açılır listesi), tarayıcıdaki arama ve sayfa başına
kayıt seçimi değişmedi; Takvimler hâlâ yalnız kullanıcının erişebildiği
takvimleri gösteriyor. Satır içi hızlı güncelleme bu PR'ın kapsamında
değil. Şema değişikliği yok.

**Doğrulandı (sandbox, MariaDB, Chromium; 6 takvim, 5 etkinlik konumu, 5
menü, 4 kısa link, 5 ürün özelliği).** Beş listede her sütun iki
yönde ve sütunların her ikili birleşimi (ilk azalan, ikinci artan), aynı
ORDER BY'lı SQL'in sıralama değerleri dizisini verdi; varsayılan son
değişiklik azalan; parametresiz yeniden açılışta iki düzeyli sıra
korundu; yönsüz kod (`sort=name`) artan, varsayılan sütun azalan başladı;
bozuk değer (`id;drop table x:desc`) varsayılana düştü; Türkçe başlıklı
eski bağlantı (`sort=Adı&order=desc`) sıraladı (240 denetim, hepsi
geçti). Yeni kodla `order`/`sort` içine konan `SLEEP` hiç koşmadı.
Tarayıcıda başlığa tık tek sütuna, Shift+tık aynı sekmede ikinci düzeye
çevirdi; menü iki düzeyi gösterdi; 390 piksel genişlikte menü açıkken de
yatay kaydırma yok. Sunucu günlüğünde bu beş dosyadan uyarı yok.
Doğrulanamadı: dev.pinegrap.com'daki gerçek kayıtlar, rolü Kullanıcı olan
bir takvim yetkilisiyle Takvimler ve gerçek bir telefonda dokunuş.

---

## 2026.4.10 — Ziyaretçi kayıtları: saklama süresi, günlük özet, parça parça silme (10.3) (2026-10-14)

**Neden.** `visitors` tablosuna her ziyaret bir satır yazılıyor, hiçbiri
silinmiyordu. Yoğun sitelerde 3,8 milyon satıra ve tek başına 800 MB'ın
üstüne çıkıyor, siteyi kilitliyordu. Bu büyüklükteki bir tablo 2026.4.8'in
InnoDB taşımasında sınırı (250.000 satır / 128 MB) aştığı için büyük
olasılıkla MyISAM'da kaldı: MyISAM'da her yazma, ve o tabloyu okuyan her
sorgu, tablonun tamamını kilitler. Plan ve ertelenenler
`docs/_plan_ziyaretci_temizleme.md`.

**Ne değişti.**
- `config.visitor_retention_days` (varsayılan 90, 0 sınırsız, 7–3650) ve
  `config.visitor_summary` (varsayılan 1). Ayarlar › SEO › Ziyaretçi
  Takibi; geçersiz değer alan hatasıyla döner, değişiklik koşu işaretini
  siler (`pg_visitor_cleanup_reset()`), sonraki genel görev turu hemen
  bakar.
- Modül `includes/fn/visitor_retention.php`. `pg_visitor_cleanup_run()`
  genel görevde (`job.php`, 15 sn): birikmiş iş varken her turda, yoksa
  saatte bir (`data/temp/visitor_cleanup.json`). Sınır, sitenin saatiyle
  bugünden N gün önceki gece yarısı (`strtotime`, yaz saatine dayanıklı).
  Sıra: saatlik grafik tablolarının geçmiş doldurması bitmediyse önce ona
  pay, imlecinin üstündeki satırlara dokunulmaz; sonra özet (koşunun en
  çok %60'ı); sonra silme. Silme: son silinecek satır `start_timestamp`
  dizininden bir kez bulunur (yalnız zamana göre sıralı, sonra o saniyenin
  en büyük id'si), sonra birincil anahtardan bir pencere
  (`SELECT MAX(id) FROM (SELECT id … WHERE id > imleç ORDER BY id LIMIT
  5000)`) ve `DELETE … WHERE id > imleç AND id <= üst AND start_timestamp
  < sınır`. Her parça kendi kısa işlemi, aralarında 20 ms. MyISAM'da
  (tablo kilidi) parça 1.000 satır, ara en az parçanın sürdüğü kadar.
  `DELETE … ORDER BY id LIMIT n` kullanılmadı: MyISAM'da sunucu
  `start_timestamp` dizinini seçip her parçada eşleşen bütün satırları
  sıralıyordu (EXPLAIN: Using filesort, parça başına ~0,5 sn). Kilit
  beklemesi ya da deadlock olursa koşu bırakır, sonraki tur devam eder.
  İki koşu `GET_LOCK('pg_visitor_cleanup.<veritabanı>')` ile ayrılır.
- `visitor_stats_daily`: gün × boyut × değer; boyut başına günün ilk 100
  değeri (`PG_VISITOR_SUMMARY_TOP`) ve kalanların toplamı tek `is_rest`
  satırında; toplam satırı (`dimension = ''`) en son yazılır,
  `MAX(stat_date) WHERE dimension = ''` özetin vardığı gün. Gün önce
  silinip yeniden yazılır, yarıda kalan gün baştan yazılır. Boyutlar raporun
  gruplama anahtarları; tam yönlendiren adres ve posta kodu yok (neredeyse
  her ziyarette ayrı değer). Bir gün birincil anahtar aralığından okunur
  (id'ler zamanla artıyor; aralık günün satır sayısının iki katını
  aşarsa `start_timestamp` dizininden).
- Veritabanı Motoru › "Ziyaretçi kayıtları" kartı (`#visitor-records`):
  satır, boyut, boş alan, en eski kayıt, saklama süresi, özetin vardığı
  gün, son temizlik; silinecek satır ve tahmini boyut
  (`pg_visitor_cleanup_preview()`, ortalama satır boyutundan). "Ziyaretçi
  kayıtlarını şimdi temizle" onay ister; `action=visitor_cleanup&ajax=1`
  20 sn'lik adımlar, JSON, sayaç kartta; son adımın bildirimi sayfa
  yenilenince. JS yoksa tek adım ve yönlendirme. "Optimize et" kartta ayrı
  ve uyarılı.
- Uyarı: 2.000.000 satır ya da 512 MB (veri + dizin,
  `pg_visitor_health_thresholds()`) kartta ve Sistem Durumu'nda
  ("Visitor Records", puansız iş satırı). Boş alan 128 MB'ı ve dosyanın
  yarısını geçince kartta Optimize bilgisi.
- Ziyaretçi Raporu (`view_visitor_report.php`): tarih seçicili ve filtresiz
  raporda, yıl/ay/gün + en çok bir boyutla, ilk ham günden öncesi özetten
  (`pg_visitor_summary_report_rows()`), sonrası satırlardan; satır sorgusu
  `start_timestamp >= pg_visitor_summary_raw_from()` ile sınırlanır (özetin
  saydığı gün iki kez sayılmasın). Gruplar MySQL sırasına PHP'de dizilir.
  Karşılayamadığı raporda o günlerin girmediğini söyleyen not. Yeni
  gruplama "Cihaz" (`pg_visitor_device_sql()`; `visitor_reports`
  ENUM'larına `'device'`).
- Dış API `GET /reports/visitors`: aynı birleştirme, yanıtta `summary_to`
  (özetten gelen son gün ya da null), `by=device`. Özetteki "diğer"
  satırları toplamlarda var, `rows`'ta yok.

**Şema (10.3).** `config.visitor_retention_days` SMALLINT UNSIGNED 90,
`config.visitor_summary` TINYINT(1) UNSIGNED 1; `visitor_stats_daily`
(`idx_dimension_date`, `idx_date`); `visitor_reports.summarize_by_1..3`
ENUM'una `'device'` (önce tipi okunur, yalnız genişletilir). Yalnız
InnoDB'de: `visitors.ip_address` dizini (`idx_dedup` aynı sütunla
başlıyor) ve `idx_user_agent` (gruplama önek dizinini kullanamaz, hiçbir
sorgu okumuyor) kaldırılır; InnoDB'de dizin kaldırmak tabloyu kopyalamaz.
Sandbox'taki 3,8 milyon satırda ikisi ~350 MB. MyISAM'da bırakılır;
tablo InnoDB'ye taşındıktan sonra adımları yeniden koşturmak onları da
kaldırır. Yükseltme satır silmez. `tests/innodb_test.php` stok `config`
sayıları 433 sütun / 8739 / 64154 bayt.

**Ölçüm (sandbox, MariaDB 10.11, 128 MB buffer pool, ~3,8 milyon satır,
400 gün; 90 günde ~2,94 milyon satır silinecek; aynı anda siteyi taklit
eden yük: ziyaret ekleme, sayfa görüntüleme güncellemesi, tekilleştirme,
son bir saat sayımı).**
- InnoDB, genel görev gibi 15 sn'lik koşular: 11 koşu, 151 sn; 310 gün
  özetlendi, 2.945.272 satır silindi. Yük: ortanca değişmedi (ekleme
  0,8 ms), p99 ekleme ~4,5 ms, güncelleme ~3 ms, tekilleştirme ~1,5 ms; en
  uzun 28 ms, hata yok.
- InnoDB, tek `DELETE … WHERE start_timestamp < sınır`: 49,5 sn tek işlem;
  yeni satırlara dokunmadığı için bu yük beklemedi, ama yarıda kesilemez,
  kesilirse aynı süre geri alır ve 2,9 milyon satırlık geri alma kaydı
  tutar.
- MyISAM (3.794.000 satır, 1,03 GB; 2.939.369 silinecek), 15 sn'lik
  koşular: 20 koşu, 277 sn; 310 gün özetlendi, koşu başına ~152.000 satır
  (MyISAM'da özet gün başına ~0,55 sn, koşuyu o sınırlıyor). Aynı yük:
  hata yok, en uzun bekleme 83 ms; p99 ekleme 0,5 → ~43 ms, güncelleme
  0,4 → ~33 ms, tekilleştirme 0,9 → ~15 ms; 10 sn'de 468 yerine 290–360
  döngü. İlk deneme `ORDER BY id LIMIT` ile parça başına 0,3–0,5 sn sürüp
  sorguları 300–870 ms bekletti; id pencereleri bunun için.
- MyISAM, tek `DELETE`: 24,5 sn; bu sürede tabloya giden her sorgu bekledi
  (tekilleştirme sorgusu 24,4 sn). Bildirilen "tüm sistemi kilitliyor"
  belirtisi bu; canlı trafikte ve soğuk diskte dakikalar sürer. Silmeden
  sonra MyISAM dosyası da küçülmez (872 MB veri, 675 MB'ı boş).
- Ön izleme sayımı 0,7 sn. Silmeden sonra InnoDB dosyası küçülmez
  (1,34 GB); `OPTIMIZE TABLE` 854 bin satırda 5,5 sn, 1,34 GB → 310 MB,
  bu sırada tek sorgu 0,85 sn bekledi.
- Rapor ve API'nin özet + ham toplamı ham kopyayla birebir aynı (3.794.000
  ziyaret, 18.969.998 sayfa görüntüleme; ay ay, ülke, kanal, cihaz).
  "Şimdi temizle" tarayıcıda 570.297 satırı iki adımda, 28 sn'de sildi.

**Doğrulanamayan.** Gerçek sitelerdeki veri dağılımı (gün başına satır,
değer çeşitliliği); MySQL 8.0'da süreler (sandbox MariaDB).

---

## 2026.4.10 — Belgeler: biten plan dosyaları temizlendi (2026-10-14)

Biten üç plan silindi (`_plan_csrf_2fa`, `_panel_api_eylemler`, `_erp_dosya_yoneticisi_hazirlik`); kısmen biten yedisinde biten bölümler çıkarıldı ve başa "Kalanlar" kondu (`_plan_erp`, `_plan_erp_ozellikler`, `_plan_oauth`, `_plan_tersine_oauth`, `_plan_claude_kanal`, `_plan_api_mobil`, `_erp_yol_haritasi_adaylari`); tam metinler git geçmişinde (`d9fec3a`).

---

## 2026.4.10 — E-posta kampanya listeleri ortak tablo yardımcısında: çoklu sıralama (2026-10-14)

**Neden.** Eski liste grupları birer PR'la `pg_list_state()` yardımcısına
taşınıyor (#331 ürünler, #335 Rehber, #336 Sayfalar, #338 Kullanıcılar,
#340 e-ticaret ayar listeleri). Aynı yapıdaki üç e-posta kampanyası
listesi tek PR'da taşındı: Kampanyalarım (`view_email_campaigns.php`),
Kampanya Geçmişim (`view_email_campaign_history.php`) ve Kampanya
Profillerim (`view_email_campaign_profiles.php`). Üçü de istekteki her
değeri `$_SESSION['software'][<liste>]` altına kopyalayan bir döngüyle
sıralamayı çevrilmiş başlık adıyla tutup kendi `switch`'iyle tek sütunluk
ORDER BY kuruyordu.

Yön (`order`) ORDER BY'a yalnız `escape()` ile yazılıyordu; tırnak
içermeyen bir değer sorguya olduğu gibi giriyordu. Sandbox'ta eski kodla
`?order=asc, (SELECT SLEEP(0.5))` üç listede de satır başına yarım saniye
bekletti (4 satırda 2 s, 6 satırda 3 s) ve değer oturumda kaldığı için
sonraki açılışlarda da koştu. E-posta yetkisi olan, oturum açmış her
kullanıcı (rolü Kullanıcı olanlar dahil) sorguya kendi SQL'ini
ekleyebiliyordu. 2026.4.9'da da var.

**Ne değişti.** Her listenin sıralanabilen sütunları kodla tanımlandı:
Kampanyalarım ve Kampanya Geçmişim `profile`, `subject`, `page_to_send`,
`scheduled_time` (yalnız kampanya işi açıkken, sütun da yalnız o zaman
görünüyor), `status`, `purpose`, `created`, `last_modified`; Kampanya
Profillerim `name`, `enabled`, `action`, `subject`, `page_to_send`,
`purpose`, `created`, `last_modified`. Varsayılanlar eskisi gibi:
kampanya işi açıkken iki kampanya listesi zamanlanmış saate göre artan,
kapalıyken son değişiklik azalan; profiller son değişiklik azalan. Sıra
`pg_list_state('<liste>', …)` ile okunur, ORDER BY `pg_list_order_by()`
ile yalnız ekranın kod → SQL eşlemesinden yazılır (sütunlar eskisiyle
aynı). Başlıklar `get_column_heading()`'e düzey listesi verir; Shift+tık
ve düzey numarası ürünlerdeki gibi çalışır. Araç çubuğunun sağına
`pg_list_sort_menu()` eklendi (`pg-toolbar-grow` boşluğuyla). İstekteki
her değeri oturuma kopyalayan döngü yalnız sıralama için vardı (başka
okuyanı yok), üç dosyadan da kaldırıldı. Profillerin CSV dışa aktarımı
listeyle aynı sırayı kullanmaya devam ediyor. Ortak yardımcıya,
`backend.src.js`'e ve `tr.json`'a dokunulmadı; kullanılan metinler zaten
vardı.

Davranış farkları: sıra panel dili değişince kaybolmuyor; kampanya işi
kapalıyken oturumda kalmış "Zamanlanmış Saat" sırası artık görünmeyen
sütuna göre dizmiyor, varsayılana düşüyor. Eski `sort=<başlık>&order=desc`
bağlantıları `pg_list_sort_parse()` ile tek düzey olarak okunur. Sütunlar,
Oluştur, Kampanya Geçmişim / Kampanyalarım geçişi, İçe/Dışa Aktar,
tarayıcıdaki arama ve sayfa başına kayıt seçimi, Seçilenleri Sil ve
düzenleme düğmeleri değişmedi; bu listelerde durum filtresi yok (durumlar
sorguda sabit). Satır içi hızlı güncelleme bu PR'ın kapsamında değil.
Şema değişikliği yok.

**Doğrulandı (sandbox, MariaDB, Chromium; 4 hazır/duraklatılmış, 4
tamamlanmış/iptal kampanya, 6 profil).** Kampanya işi kapalı ve açıkken
üç listede her sütun iki yönde ve birer iki düzeyli sıra, aynı ORDER BY'lı
SQL'in satır sırasını verdi; varsayılanlar yukarıdaki gibi; sayfa
yenilenince sıra korundu; bozuk değer (`id;drop table x:desc`)
varsayılana düştü; Türkçe başlıklı eski bağlantı (`sort=Konu&order=desc`)
sıraladı; iş kapalıyken `scheduled_time` reddedildi ve menüde çıkmadı;
profil CSV'si iki düzeyli sırayla geldi (222 denetim, hepsi geçti). Yeni
kodla `order`/`sort` içine konan `SLEEP` hiç koşmadı. Tarayıcıda başlığa
tık tek sütuna, Shift+tık aynı sekmede ikinci düzeye çevirdi; menü iki
düzeyi gösterdi; 390 piksel genişlikte yatay kaydırma yok. Sunucu
günlüğünde bu üç dosyadan uyarı yok. Doğrulanamadı: dev.pinegrap.com'daki
gerçek kampanyalar ve gerçek bir telefonda dokunuş.

---

## 2026.4.10 — E-ticaret ayar listeleri ortak tablo yardımcısında: çoklu sıralama (2026-10-13)

**Neden.** Eski liste grupları birer PR'la `pg_list_state()` yardımcısına
taşınıyor (#331 ürünler, #335 Rehber, #336 Sayfalar, #338 Kullanıcılar).
Görünüm filtresi olmayan dört kısa e-ticaret ayar listesi aynı kalıba
uyduğu için tek PR'da taşındı: Vergi Bölgeleri (`view_tax_zones.php`),
Para Birimleri (`view_currencies.php`), Kargo Tarihi Düzeltmeleri
(`view_ship_date_adjustments.php`) ve Doğrulanmış Teslimat Adresleri
(`view_verified_shipping_addresses.php`). Dördü de sıralamayı
`$_SESSION['software']['ecommerce'][<liste>]` altında başlık adıyla tutup
kendi `switch`'iyle tek sütunluk ORDER BY kuruyordu.

Üçünde başlık tıklaması hiç çalışmıyordu (sandbox'ta eski kodla
denendi): Vergi Bölgeleri ve Kargo Tarihi Düzeltmeleri istekteki `sort`
ve `order`'ı oturuma hiç yazmıyordu, liste hep son değişikliğe göre
kalıyordu; Para Birimleri istekteki değeri yazıyor ama İngilizce adla
(`'Name'`, `'Code'` …) karşılaştırıyordu, Türkçe panelde başlık `Adı`
geldiği için hep varsayılana düşüyordu. Doğrulanmış Teslimat Adresleri
çalışıyordu.

**Ne değişti.** Her listenin sıralanabilen sütunları kodla tanımlandı:
Vergi Bölgeleri `name`, `tax_rate`, `last_modified`; Para Birimleri
`name`, `base`, `code`, `symbol`, `exchange_rate`, `created`,
`last_modified`; Kargo Tarihi Düzeltmeleri `zip_code_prefix`,
`shipping_method`, `adjustment`, `created`, `last_modified`; Doğrulanmış
Teslimat Adresleri `company`, `address_1`, `address_2`, `city`, `state`,
`zip_code`, `country`, `created`, `last_modified`. Varsayılan dördünde de
eskisi gibi son değişiklik azalan. Sıra `pg_list_state('<liste>', …)` ile
okunur, ORDER BY `pg_list_order_by()` ile yalnız ekranın kod → SQL
eşlemesinden yazılır (sütunlar eskisiyle aynı). Başlıklar
`get_column_heading()`'e düzey listesi verir; Shift+tık ve düzey numarası
ürünlerdeki gibi çalışır. Araç çubuğunun sağına `pg_list_sort_menu()`
eklendi (Kullanıcılar'daki gibi `pg-toolbar-grow` boşluğuyla). Ortak
yardımcıya, `backend.src.js`'e ve `tr.json`'a dokunulmadı; kullanılan
metinler zaten vardı.

Doğrulanmış Teslimat Adresleri'nde il/eyalet filtresi eskisi gibi oturumda
tutulur; listeye gelen her istek değerini oturuma yazan döngü yalnız
`state_id`'yi yazacak şekilde daraltıldı (döngünün başka okuyanı yoktu).
Para Birimleri'ndeki aynı döngü ve iki listedeki `order` temizleme
blokları yalnız sıralama için vardı, kaldırıldı. Oluştur bağlantısındaki
`state_id` HTML'e SQL kaçışıyla (`escape()`) yazılıyordu; adresle gelen
tırnaklı bir değer bağlantının dışına taşabiliyordu. Artık
`h(urlencode())` ile yazılıyor.

Davranış farkları: üç listede başlığa tıklamak artık sıralıyor; sıra
panel dili değişince kaybolmuyor. Eski `sort=<başlık>&order=desc`
bağlantıları `pg_list_sort_parse()` ile tek düzey olarak okunur. Sütunlar,
tarayıcıdaki arama ve sayfa başına kayıt seçimi, Döviz Kurlarını Güncelle,
il/eyalet filtresi ve düzenleme düğmeleri değişmedi; bu listelerde toplu
işlem yok. Satır içi hızlı güncelleme bu PR'ın kapsamında değil. Şema
değişikliği yok.

**Doğrulandı (sandbox, MariaDB, Chromium; 8 vergi bölgesi, 7 para birimi,
8 kargo tarihi düzeltmesi, iki ülkeden 8 adres).** Dört listede her sütun
iki yönde ve birer iki düzeyli sıra, aynı ORDER BY'lı SQL'in satır
sırasını verdi; varsayılan son değişiklik azalan; sayfa yenilenince sıra
korundu; bozuk değer (`id;drop table x:desc`) varsayılana düştü; Türkçe
başlıklı eski bağlantılar (`sort=Adı&order=desc`) sıraladı (80 denetim,
hepsi geçti). Adreslerde il seçimi sırayı, sıra değişikliği il seçimini
korudu; Oluştur bağlantısı ili taşıdı; `state_id` olarak verilen
`x"><script>` sayfaya kaçışsız düşmedi. Tarayıcıda başlığa tık tek sütuna,
Shift+tık aynı sekmede ikinci düzeye çevirdi; menü düzeyleri, yön
çevirmeyi, kaldırmayı ve varsayılana dönmeyi gösterdi; 390 piksel
genişlikte yatay kaydırma yok. Sunucu günlüğünde bu dört dosyadan uyarı
yok. Doğrulanamadı: dev.pinegrap.com'daki gerçek kayıtlar ve gerçek bir
telefonda dokunuş.

---

## 2026.4.10 — Çalışma Alanı: toplantı bağlantısı ve toplantı notu (2026-10-13)

**Neden.** Workspace araştırması (`workspace-arastirma.md` §4, P2 2.6 a ve
b): Notion, Teams, Slack, Odoo ve Bitrix24'ün ortak özelliği. Yerleşik
görüntülü görüşme (WebRTC) paylaşımlı hostingte sunucu ister; bağlantı
taşımak yeter. Ses kaydı ve yazıya dökme (2.6 c) kapsam dışı.

**Ne değişti.**
- Plan öğesi (`ws_events`) toplantı bağlantısı taşıyor:
  `meeting_url` VARCHAR(500). Form "Jitsi odası oluştur" ile
  `https://meet.jit.si/<başlıktan birkaç kelime>-<10 rastgele karakter>`
  yazıyor (`crypto.getRandomValues`), ya da Meet/Teams/Zoom bağlantısı
  yapıştırılıyor. Doğrulama `ws_meeting_url_clean()`: http(s), boşluksuz,
  `FILTER_VALIDATE_URL`, en çok 500 karakter. Site bu servislerle hiç
  konuşmaz; adres yalnız yeni sekmede açılır (`noopener`).
- "Katıl": plan öğesi formunda, takvimin gün listesinde, gelen kutusunda
  ve planlama panosundaki öğenin sağ tık menüsünde. Takvim ızgarasında ve
  panoda bağlantılı öğe kamera simgesi taşıyor. Takvim `?event=<id>` ile
  açılınca öğenin formu açılıyor (bildirimin adresi).
- Başlamadan bildirim: zamanlı ve bağlantılı öğe, başlamadan
  `WS_MEETING_REMIND_MINUTES` (10) dakika önce katılanlara (kişiler,
  departmanın üyeleri ya da bütün ekip — `ws_event_people_ids()`) gelen
  kutusu satırı `meeting_soon` yazar; satır zil ve push kuyruğuna gider.
  An `ws_events.remind_at`'te kayıt sırasında hesaplanır, `reminded_at`
  koşullu güncellemeyle sahiplenilir (görev hatırlatmasıyla aynı desen,
  aynı koşular: `ws_task_reminders_waiting()` / `_run()` içinden çağrılır,
  böylece `job.php`, ekranların yoklaması ve saatlik iş değişmedi). Saat
  ya da bağlantı değişince yeniden kurulur; toplantı bittiyse atılır.
  E-posta yok: on dakika önceden haber, bağlantı açılan ekranda bir
  dokunuş uzakta. Push adresi dış bağlantı olamaz (`sw.js`
  `safeTarget()` kapsam dışını panele çevirir), bu yüzden satırın adresi
  takvimdeki öğe; gelen kutusu listesi ayrıca bağlantının kendisini
  `join` olarak taşır.
- `ws_inbox.event_id`: satırın hangi plan öğesine ait olduğu. Yalnız
  `task_id` / `channel_id` / `message_id` olsaydı iki toplantının
  bildirimi `ws_notify()`'ın "aynı şey iki kez" kontrolünde tek satıra
  düşerdi.
- Toplantı notu (`includes/workspace/meetings.php`): plan öğesinden
  "Toplantı notu" sıradan bir not açar — öğenin etiketi
  (`<#plan:id>`), saati ve bağlantısı; Katılımcılar (işaret listesi,
  gelenler işaretlenir; departman toplantısında departman etiketi, şirket
  toplantısında blok yok), Gündem, Kararlar, Aksiyonlar (`::: Başlık`lı
  tablolar, `ws_template_note_table()`). Not açan kişinindir ve her not
  gibi paylaşılır; yeniden basınca kişinin okuyabildiği, öğeyi etiketleyen
  en yeni not açılır (önce kendi notu). Öğeye not sütunu eklenmedi: tek
  bir not kimliği, notu herkesle paylaşmadan başkalarına bir şey
  söylemezdi.
- "Kanala aktar" (not başlığında toplantı notunda, "Daha fazla" menüsünde
  düzenlenebilen her notta): Kararlar ve Aksiyonlar bloklarını (tablo ya
  da işaret listesi; başlık ekran dilinde ya da İngilizce) okur, önce
  liste gösterir. Karar kanala `decision` mesajı olur (Kararlar sekmesi);
  kanalda kelimesi kelimesine aynı karar varsa "zaten var". Aksiyon satırı
  `/task` gibi okunur (`ws_command_parse_task()`: @kişi, gün, ~süre,
  !öncelik), kimse yoksa aktaran kişinin olur, görev kartı kanala düşer;
  görev olan satıra notta `<#task:id>` eklenir ve bir daha aktarılmaz.
  İzin kontrolü `ws_can_assign_to()`; izin dışı satır listede kırmızı ve
  seçilemez. Bir seferde en çok 15 (kanalın dakikada 20 mesaj sınırı).
  İzin çakışması (`ws_assignment_check()`) burada sorulmuyor; `/task`
  önizlemesindeki uyarılar görev çekmecesinde görünür.
- Dış API / MCP: plan öğesi `meeting_url` alanı (yazma ve okuma). iCal
  aboneliği bağlantıyı açıklamaya ve `LOCATION`'a yazıyor.

**Şema (10.86).** `ws_events.meeting_url`, `remind_at`, `reminded_at`,
`idx_remind (reminded_at, remind_at)`; `ws_inbox.event_id`. 10.85 olaylarla
otomasyon işine bırakıldı.

**Doğrulanamayan.** Bildirimin telefondaki push görünümü (sandbox'ta push
servisi yok); Teams ve Zoom bağlantılarının açılışı (yalnız yeni sekme
açılıyor, servisle konuşulmuyor).

---

## 2026.4.10 — Kullanıcılar listesi ortak tablo yardımcısında: çoklu sıralama (2026-10-13)

**Neden.** Eski liste grupları birer PR'la `pg_list_state()` yardımcısına
taşınıyor (#331 ürünler, #335 Rehber, #336 Sayfalar). `view_users.php`
sıralamayı `$_SESSION['software']['view_users']` altında çevrilmiş başlık
adıyla tutup kendi `switch`'iyle tek sütunluk ORDER BY kuruyordu. Bu PR
yalnız Kullanıcılar'ı taşır.

**Ne değişti.** Sıralanabilen sütunlar kodla tanımlandı: her görünümde
`username` ve `last_online`; varsayılan görünümde (Tüm Kullanıcılarım,
filtresiz) `role`, `member_user`, `site_manager`, `site_designer`; diğer
görünümlerde `email`, `start_page`, `last_modified`. Varsayılan eskisi
gibi son değişiklik azalan; varsayılan görünüm Son Düzenleme sütununu
göstermediği halde satırları ona göre dizdiği için `last_modified`
o görünümün listesinde de var (başlığı yok, menüde en sonda). Sıra
`pg_list_state('view_users', …)` ile okunur, ORDER BY `pg_list_order_by()`
ile yalnız ekranın kod → SQL eşlemesinden yazılır (Üye Kullanıcı artık
`contacts.member_id`, eskiden aynı sütunun `user_member_id` takma adıydı;
Site Yöneticisi ve Site Tasarımcısı eskisi gibi role göre). Başlıklar
`get_column_heading()`'e düzey listesi verir; Shift+tık ve düzey numarası
ürünlerdeki gibi çalışır. Araç çubuğunda görünüm seçiminin soluna
`pg_list_sort_menu()` eklendi; görünüm (`filter`) #335'teki `$query`
parametresiyle bağlantılarda ve formda taşınır. Ortak yardımcıya,
`backend.src.js`'e ve `tr.json`'a dokunulmadı; kullanılan metinler zaten
vardı.

Rol tabanlı görünürlük değişmedi: WHERE (görünümün filtresi ve
`user_role` koşulu — yönetici hepsini, diğerleri yalnız kendinden düşük
rolleri görür) sıralamadan ayrı kuruluyor, ona dokunulmadı. Bir görünümün
göstermediği sütundaki düzey düşer, hiç düzey kalmazsa varsayılan döner.
Eski kod varsayılan görünümde E-posta, Başlangıç Sayfası ve Son Düzenleme
sıralarını İngilizce adla karşılaştırıyordu; Türkçe panelde eşleşmediği
için başka görünümde e-postaya göre sıralayıp Tüm Kullanıcılarım'a dönünce
satırlar görünmeyen e-posta sütununa göre diziliyordu, başlıkta ok
yoktu. Artık o düzey düşüyor. Varsayılan görünüm dışındaki görünümlerde
tanımsız kalan sütun başlığı değişkenleri (rol, yönetici sütunları vb.)
başta boş tanımlandı; her açılışta PHP günlüğüne düşen "Undefined
variable" uyarıları kalktı, çıktı aynı.

Davranış farkları: sıra panel dili değişince kaybolmuyor. Eski
`sort=<başlık>&order=desc` bağlantıları `pg_list_sort_parse()` ile tek
düzey olarak okunur. Sütunlar, görünüm seçimi, tarayıcıdaki arama ve
sayfalama, Seçilenleri Sil, oturum kilidi ve giriş sağlayıcısı rozetleri
değişmedi. Satır içi hızlı güncelleme bu PR'ın kapsamında değil. Şema
değişikliği yok.

**Doğrulandı (sandbox, MariaDB, Chromium, 21 kullanıcı, dört rol).** Eski
ve yeni kod aynı 26 adreste (her görünüm, her sıralanabilir sütun, Türkçe
başlıklı eski bağlantılar, yönetici ve menajer hesabı) aynı satır sırasını
ve aynı başlıkları verdi; tek fark iki ölçüm arasında oturum açılınca
değişen "son çevrimiçi" zamanıydı. Çok düzeyli sıralar (rol + ad, site
tasarımcısı + üye + ad, başlangıç sayfası + e-posta, DISTINCT kullanan Özel
Kullanıcılarım'da e-posta + başlangıç sayfası) aynı ORDER BY'lı SQL ile
karşılaştırıldı. Sayfa yenilenince sıra korundu; Tüm Kullanıcılarım'dan
başka görünüme geçince rol düzeyi düştü, ad kaldı; panel dili İngilizceye
alınıp geri getirilince sıra korundu; bozuk düzey (`user.user_id;drop`,
`role:sideways`) varsayılana düştü. Menajer hesabı çok düzeyli sırayla
yine yalnız Kullanıcı rolündekileri gördü. Tarayıcıda başlığa Shift+tık
aynı sekmede düzey ekledi, Shift'siz tık tek sütuna çevirdi; menünün formu
görünümü taşıdı; 390 piksel genişlikte menü ekrana sığdı, yatay kaydırma
yok; sunucu günlüğünde `view_users.php` uyarısı kalmadı. Doğrulanamadı:
dev.pinegrap.com'daki gerçek hesaplar ve gerçek bir telefonda dokunuş.

---

## 2026.4.10 — Akrilik efekt hafifledi: Site Ayarları penceresi ve pano (2026-10-13)

**Neden.** Şeffaf Akrilik Efekt (`.advanced-visuals`) açıkken Site
Ayarları penceresi aşırı takılıyordu, en çok pano (`welcome.php`)
üstünde. `backdrop-filter` bir kez çizilmez: kutunun altında ya da
içinde bir şey değişen her karede yeniden çalışır. Pencerenin tamamı
(1180×860) bulanıktı ve ayar bölmesi kaydırılan bir alan; her kaydırma
karesi bütün pencereyi yeniden bulanıklaştırıyordu. Panoda hiç durmayan
animasyonlar (Sistem Durumu hücreleri, canlı noktalar) da pencere boşta
dururken aynı işi her karede yaptırıyordu. Cam balon temasında her kartın
(`blur(16px) saturate(130%)`) ve karşılama kartının (`blur(6px)`) kendi
filtresi vardı; sabit (`background-attachment: fixed`) renk geçişinin
üstünde her kaydırma karesinde hepsi yeniden hesaplanıyordu.

**Ölçüm (headless Chromium 141, yazılım çizimi, 1600×1000, cam tema,
koyu).** Bulanık katman sayısı, pencere açıkken: önce 17 (pencere + 15
kart + karşılama kartı), sonra 1 (sol bölüm listesi, 240×860). Pencere
açık, boşta: 9–10 → 60 kare/sn; ayar bölmesi kaydırılırken 10–11 → 58–60.
Pano kaydırılırken (pencere kapalı): 53 → 60. Açık temada aynı: pencere
12 → 60. Seçenekler tek tek denendi: yarıçap maliyeti değiştirmiyor
(pencere üstünde 8/12/24 px aynı ~21 kare/sn, yalnız listede 24 px ile
100 px aynı ~58–60), belirleyen bulanık alanın büyüklüğü ve ne sıklıkla
yeniden çizildiği. Arkadaki animasyonları pencere açıkken durdurmak
(`animation-play-state`) yalnız listede bulanıklık kalınca küçük fark
yaptığı için eklenmedi. `background-attachment: fixed` ölçülebilir fark
yaratmadı, dokunulmadı.

**Ne değişti (`assets/css/backend.src.css`).**
- Site Ayarları penceresi: `.modal-content.pg-sm` saydam ve filtresiz;
  filtre yalnız `.pg-sm-side`'da (aynı değerler: açıkta
  `brightness(1) saturate(1.5) blur(20px)`, koyuda
  `brightness(0.7) saturate(1.7) blur(100px)`), bölmenin dolgusu
  (`%55 secondary-bg`) aynı. `.pg-sm-main` panelin kendi arka planıyla
  düz. %90 saydam denendi: filtre olmadan panonun rakamları ve başlıkları
  formun altından okunuyordu. Pencerenin üstüne açılan küçük diyaloglar
  (`#pg_settings_modal_extras`) eskisi gibi tümüyle buzlu; küçükler ve
  kaydırılmıyorlar. Perde (`--bs-backdrop-opacity: 0.25`) aynı.
- Cam balon teması kartlarından ve `.pg-brief`'ten `backdrop-filter`
  kaldırıldı; yarı saydam dolgular aynı. Arkalarında yalnız panonun yumuşak
  radyal geçişleri var, bulanıklık görünür bir fark yaratmıyordu (önce/sonra
  ekran görüntüsü açık ve koyu temada aynı).

**Etkilenen ekranlar.** Pencere kuralı Site Ayarları'nın açıldığı her
panel ekranını etkiler, yalnız akrilik efekt açıkken. Kart ve karşılama
kartı kuralları yalnız panoda (cam tema). Menü ve açılır pencerelerin
(`.dropdown-menu.backdrop`, `.popover.backdrop`) filtresine dokunulmadı.
`will-change` bu kurallarda hiç yoktu; geçişler Bootstrap'in (modal kayma
animasyonu `prefers-reduced-motion: reduce` altında zaten kapalı).

**Not.** Bu Chromium'da koyu temanın 100 px'lik `backdrop-filter`'ı
arkadaki yazıyı bulanıklaştırmadan, keskin ve soluk gösteriyor (40 px ve
üstü aynı; 24 px ve altı gerçekten buzlu). Gerçek ekran kartında aynı
olup olmadığı dev.pinegrap.com'da bakılacak; değerler bu PR'da
değişmedi.

**Doğrulandı.** Sandbox (MariaDB, PHP 8.3), Chromium: pano ve pencere
açık/koyu temada 1600 px, pencere 800 px (tam ekran) ve 390 px (çekmece
açık) genişlikte ekran görüntüsüyle; `php tools/lint.php` temiz. JS
değişmedi. Doğrulanamadı: Erdal'ın ekran kartında gerçek kare hızı.

---

## 2026.4.10 — Sayfalar listesi ortak tablo yardımcısında: çoklu sıralama (2026-10-13)

**Neden.** Eski liste grupları birer PR'la `pg_list_state()` yardımcısına
taşınıyor (#331 ürünler, #335 Rehber). `view_pages.php` sıralamayı
`$_SESSION['software']['pages']` altında çevrilmiş başlık adıyla tutup
kendi `switch`'iyle tek sütunluk ORDER BY kuruyordu. Bu PR yalnız
Sayfalar'ı taşır.

**Ne değişti.** Sıralanabilen sütunlar kodla tanımlandı (`name`, yalnız
Özel Form Sayfalarım görünümünde `form_name` ve `form_enabled`, `folder`,
`style`, `page_type`, `searchable`, `comments`, `sitemap`, `seo`,
`impact`, `last_modified`). Sıra `pg_list_state('view_pages', …)` ile
okunur, ORDER BY `pg_list_order_by()` ile yalnız ekranın kod → SQL
eşlemesinden yazılır (sütunlar artık tablo adıyla nitelenmiş,
`seo_impact` sorgudaki takma ad). Varsayılan eskisi gibi son değişiklik
azalan. Başlıklar `get_column_heading()`'e düzey listesi verir; Shift+tık
ve düzey numarası ürünlerdeki gibi çalışır. Görünüm seçiminin yanına
`pg_list_sort_menu()` eklendi; görünüm (`filter`) adresten okunduğu için
#335'teki `$query` parametresiyle bağlantılarda ve formda taşınır. Ortak
yardımcıya, `backend.src.js`'e ve `tr.json`'a dokunulmadı; kullanılan
metinler zaten vardı.

Stil sütunu masaüstü ve mobil için tek kod (`style`) oldu: eski kod
cihaz geçişinde oturumdaki başlığı "Desktop Page Style" ↔ "Mobile Page
Style" diye İngilizce adla çeviriyordu, Türkçe panelde eşleşmediği için
sıra varsayılana düşüyordu. Etkin olmayan bir tema önizlenirken stil
sütunu eskisi gibi sıralanamaz (stil adı satır satır `preview_styles`'tan
okunuyor); o zaman yalnız o düzey düşer, eskiden bütün sıra silinirdi.
Form sütunları diğer görünümlerde yok; o düzey düşer, hiç düzey kalmazsa
varsayılan döner (eski kod da oturumdaki sırayı boşaltıyordu). Kullanılmayan
`$screen` okuması kaldırıldı (liste sayfalamayı tarayıcıda yapıyor).

Davranış farkları: sıra panel dili değişince kaybolmuyor. Eski
`sort=<başlık>&order=desc` bağlantıları `pg_list_sort_parse()` ile tek
düzey olarak okunur. Toplu işlemler (Seçilenleri Değiştir, Seçilenleri
Sil), SEO özet şeridi, SEO yenileme ve HTML çözümleme düğmeleri
değişmedi. Satır içi hızlı güncelleme bu PR'ın kapsamında değil. Şema
değişikliği yok.

**Doğrulandı (sandbox, MariaDB, Chromium, 181 sayfa).** Sunucunun
döndürdüğü satır sırası aynı ORDER BY'lı SQL ile karşılaştırıldı:
varsayılan (son değişiklik azalan), `folder:asc,name:desc` ve sayfa
yenilenince korunması, Standart Sayfalarım'da tür + ad, Özel Form
Sayfalarım'da form etkin + form adı + ad (başka görünüme geçince form
düzeyleri düştü, ad kaldı), masaüstü ve mobil stil (cihaz geçişinde sıra
ve başlık oku korundu), eski `sort=Klasör&order=desc` bağlantısı, bozuk
düzeyin düşmesi. RSS görünümü (GROUP BY) etki + klasör + ad ve SEO
görünümü dört düzeyle hatasız açıldı. Tarayıcıda başlığa Shift+tık aynı
sekmede düzey ekledi, Shift'siz tık önde olan sütunu tek başına çevirdi;
menünün bağlantıları ve formu görünümü taşıdı; 390 piksel genişlikte menü
açıldı, yatay kaydırma yok; konsolda ve sunucu günlüğünde `view_pages.php`
uyarısı yok. Doğrulanamadı: dev.pinegrap.com'daki gerçek sayfalar, tema
önizlemesi açıkken stil sütunu ve gerçek bir telefonda dokunuş.

---

## 2026.4.10 — Rehber (kişi listesi) ortak tablo yardımcısında: çoklu sıralama (2026-10-13)

**Neden.** #331 çoklu sıralamayı `pg_list_state()` kullanan tek sunucu
listesine, ürünlere getirmişti; `view_contacts.php` sıralamayı oturumda
çevrilmiş başlık adıyla tutup kendi `switch`'iyle ORDER BY kuruyordu ve
tek sütunla kalıyordu. Eski liste grupları birer PR'la ortak yardımcıya
taşınıyor; bu PR yalnız Rehber'i taşır.

**Ne değişti.** Sıralanabilen sütunlar kodla tanımlandı (`first_name`,
`last_name`, `company`, `email`, `user`, `opt_in`, `last_modified`; iş ve
ev adresi görünümlerinde e-posta/kullanıcı/opt-in yerine `city`, `state`,
`zip_code`, `country`, adres öneki görünüme göre `contacts.business_` ya
da `contacts.home_`). Sıra `pg_list_state('view_contacts', …)` ile okunur,
ORDER BY `pg_list_order_by()` ile yalnız ekranın kod → SQL eşlemesinden
yazılır. Varsayılan eskisi gibi son değişiklik azalan. Başlıklar
`get_column_heading()`'e düzey listesi verir; Shift+tık ve düzey numarası
ürünlerdeki gibi çalışır, `backend.src.js`'e dokunulmadı. Araç çubuğuna
`pg_list_sort_menu()` eklendi (yinelenen kişiler görünümünde yok).

Rehber görünümü (`filter`) oturumdan değil adresten okunur. Menünün
bağlantıları ve "Ardından şuna göre sırala" formu görünümü taşımazsa liste
"Tüm Kişilerim"e dönerdi; bu yüzden `pg_list_sort_menu()`'ye isteğe bağlı
beşinci parametre `$query` eklendi: verilen ad → değer çiftleri her
bağlantının sonuna kodlanarak eklenir ve forma gizli alan olarak girer.
Parametresiz çağrı (ürünler) aynı HTML'i üretir. Başlık bağlantıları
görünümü zaten `$extras` ile taşıyordu.

Davranış farkları: oturumda tutulan sıra artık başlık değil kod olduğu
için panel dili değişince kaybolmuyor (eski kodda saklanan başlık yeni
dilde eşleşmeyince sıra varsayılana dönüyordu). Bir görünümün göstermediği sütun düzeyi
(örneğin iş adresi görünümüne geçerken e-posta) düşer, hiç düzey kalmazsa
varsayılan döner; eski kod adres sütunlarında aynısını yapıyordu, e-posta
ve kullanıcıda gizli sütuna göre sıralamaya devam ediyordu. Yinelenen
kişiler görünümü her zaman e-posta artan sıralanır ve diğer görünümler
için saklanan sıraya dokunmaz. Eski `sort=<başlık>&order=desc`
bağlantıları `pg_list_sort_parse()` ile tek düzey olarak okunur. Dışa
Aktar, toplu silme ve birleştirme kendi sabit sıralarını kullanıyordu,
değişmedi.

Testler: `tests/list_state_test.php`'ye menü testi (bağlantılar ve form
görünümü taşıyor, değer bağlantıda kodlanıyor, formda kaçışlanıyor,
parametresiz çağrı değişmedi). Şema değişikliği yok.

**Doğrulandı (sandbox, MariaDB, Chromium).** `sort=company:asc,first_name:desc`
şirketi boş olanlar önde, aynı şirkette ad azalan geldi; başlıkta
Shift+tık aynı sekmede düzey ekledi ve sayfa yenilenince kaldı; iş adresi
görünümünde Şehir tık + Posta kodu Shift+tık ve menüden düzey ekleme
görünümü korudu; yinelenen kişilerde menü yok, sıra e-posta; eski başlıklı
bağlantı tek sütunla sıraladı; grup filtresi ve gelişmiş filtre açıkken
sıralama çalıştı; adresten gelen bozuk düzey düştü; Dışa Aktar 200 döndü;
390 piksel genişlikte menü açıldı, yatay kaydırma yok; konsolda hata yok.
Sunucu günlüğündeki `view_contacts.php` uyarıları (`$output_user_row`,
gelişmiş filtre seçim değişkenleri, `$oldest_timestamp`) bu değişiklikten
önce de vardı, dokunulmadı. Doğrulanamadı: dev.pinegrap.com'daki gerçek
rehber ve gerçek bir telefonda dokunuş.

---

## 2026.4.10 — Dış API: iletişim grubu oluşturma, değiştirme, silme ve üyelik olayı (2026-10-13)

**Sorun.** #317 iletişim gruplarını okuyup üyelerini değiştirebiliyordu ama
grubun kendisini değil: bir bülten aracı ya da CRM yeni bir liste açmak, adını
değiştirmek ya da kaldırmak için panele gitmek zorundaydı. Bir kişinin gruba
girdiğini ya da çıktığını duymanın yolu da yoktu; entegrasyon üyeleri
zamanlayıcıyla yeniden okuyordu.

**Değişiklik.** `includes/api/resources/contact_groups.php`'ye üç uç, mevcut
`contact_groups:write` izniyle (yeni kapsam yok):

- `POST /contact-groups`: `name` (zorunlu, en çok 100; kolon `varchar(100)`),
  `description` (255), `email_subscription` (yoksa kapalı),
  `subscription_type` (`open`/`closed`, yoksa `open`). Boş grup, uygulamanın
  sahibi oluşturan olarak yazılır; 201. Ekleme ekranı gibi yalnız rol < 3
  (`api_contact_group_may_create()`); rol 3 + kişi yetkisi 403 `forbidden`
  alır, çünkü oluşturduğu grup kimseye verilmemiş olurdu.
- `POST /contact-groups/{id}` (`PATCH` de): yalnız gönderilen alan değişir
  (`api_contact_group_fields()` mevcut satırın üstüne koyar); hiçbir alan
  yoksa 422. Abonelik kapatılınca tür ve üyelerin `opt_in` satırları kalır,
  ekran da silmiyor.
- `DELETE /contact-groups/{id}` ve `POST /contact-groups/{id}/delete`: grup
  ekranının kuralı: içinde kişi olan grup silinmez (409
  `contact_group_has_members`, üyeleri `remove` ile çıkarma yolunu söyler).
  Silinince ekrandaki gibi `contacts_contact_groups_xref`,
  `users_contact_groups_xref`, `opt_in` ve
  `contact_groups_email_campaigns_xref` satırları da gider; gönderilmiş
  kampanya gönderilmiş kalır. Ekrandan tek fark: üyeler `members` alanının
  saydığı gibi sayılır (kişisi silinmiş bağ sayılmaz), yoksa API'de 0 üyeli
  görünen grup silinemezdi; o artık bağ grupla birlikte gider.

Ad, ekranlar gibi büyük/küçük harf ayırmadan tekil: başka grupta varsa 409
`name_taken` (sahibinin id'siyle). Rol 3 hesap, grup düzenleme ekranındaki gibi
kendisine verilen grubun adını değiştirebilir ve boşsa silebilir. Üç uç da
`dry_run` destekler ve site günlüğüne uygulama + anahtarla yazar.

**Webhook olayı `contact_group.members_changed`.** Veri: `id`, `name`,
`added` ve `removed` (kişi kimlik listeleri); kişi başına değil değişiklik
başına bir olay, kimse gerçekten yer değiştirmediyse olay yok
(`pg_contact_group_members_event()`, `pg_announce_contact_group_members()`,
`includes/fn/events.php`). Duyuran yerler: `POST /contact-groups/{id}/members`,
panelde kişi ekleme, kişi düzenleme (yalnız gerçekten eklendiği ya da
çıkarıldığı gruplar; çıkarmada `mysqli_affected_rows()`) ve seçili kişileri
gruplara düzenleme. Yalnız `opt_in` yazılan üye olay üretmez. Bilerek sessiz:
ödeme, özel formlar, e-posta tercihleri ekranı, üyelik/ortaklık kayıtları,
içe aktarmalar ve kişinin silinmesi; katalog metni bunu söylüyor.

OpenAPI ve MCP araçları rotalardan gelir: `contact_groups_create`,
`contact_groups_update`, `contact_groups_delete` (`_post` takma adı araç
olmaz). İzin açıklaması (Uygulama Erişimi) artık grup yazmayı da anıyor. Şema
değişikliği yok.

Testler: `tests/api_contact_groups_test.php` (oluşturma kapısı, yeni grubun
varsayılanları, değişiklikte gönderilmeyenin kalması ve kapalı abonelikte
türün korunması, olayın verisi: tekilleştirme, yalnız çıkarma, kimse
değişmediyse `null`; rota sayısı 8).

---

## 2026.4.10 — Çalışma Alanı MCP: "MCP bağlantılarım" ekranı ve okuyan araçlar için form (2026-10-13)

OAuth Faz F'nin isteğe bağlı iki kalanı (`docs/_plan_oauth.md` Faz F,
`notes/oauth-faz-f-devir.md`): kişinin kendi bağlantıları ekranı ve F1c
(araç formu, `notes/mcp-arastirma.md` §3 seviye A).

**Sorun.** #324'ten sonra sıradan bir üye OAuth'lu sunucudaki kendi
hesabını yalnız onay kartından ve "hesabınız bağlı değil" mesajındaki
bağlantıdan kurabiliyor, kesmek için ise o kartı bulmak zorundaydı;
Çalışma Alanı Ayarları'ndaki MCP kartı yalnız ayar görüntüleyicilerine
açık. Pinegrap AI lisansı olmayan sitede takma ad hiç çalışmıyordu: kanalda
`@takmaad` yazınca yalnız "Premium lisans gerekir" mesajı geliyordu.

**Değişiklik.**

- Yeni ekran `workspace_mcp.php` ("MCP bağlantılarım"; Çalışma Alanı
  çekmecesinde "Çalışma ve planlama" altında, yalnız açık bir sunucu varken;
  ayarlar kartının başlığında da bağlantısı var). Her üye açık sunucuları
  görür. OAuth'lu sunucuda kendi hesabının durumu (bağlı, son kullanım, sona
  ermiş, bağlı değil; site hesabı varsa okuyan araçların onu kullandığı) ve
  Bağla / Yeniden bağla / Bağlantıyı kes düğmeleri. Bağlama
  `ws_mcp_person_connect_start()` ile, dönüş `workspace_mcp.php?mcp_connected=ID`
  (`pg_connect_flash_take()`); kesme `ws_mcp_person_disconnect()`. Kimlik
  bilgisiyle çalışan sunucuda "herkes sitenin bağlantısını kullanır" yazar.
- F1c, yeni modül `includes/workspace/mcp_form.php`: açık ve onay istemeyen
  araçlar (`ws_mcp_form_tools()`: `ws_mcp_tools_on()` eksi `approve`)
  ekranda listelenir; seçilen aracın formu `inputSchema`'dan çıkar
  (`ws_mcp_form_fields()`): string metin kutusu, `enum` seçim listesi,
  integer/number sayı (virgül ondalık), boolean Evet/Hayır/boş, düz değer
  dizisi satır başına bir değer, nesne ve diğer her şey JSON. `type` dizisi
  ya da `anyOf`/`oneOf` ise `null` olmayan ilk tür alınır; adı düz kelime
  olmayan özellik atlanır; en çok 40 alan. `ws_mcp_form_arguments()` boş
  alanı göndermez (zorunluysa hata), her değeri türüne çevirir, JSON
  nesnesini nesne bırakır (`{}` → `{}`).
- Çalıştırma `ws_mcp_form_run()`: kişinin kendi hesabı varsa onunla, yoksa
  sitenin (Pinegrap AI'ın okuyan araç kuralıyla aynı), 401'de bir kez
  yenileme. Çağrı `ws_mcp_calls`'a `via = 'form'`, `request_id 0` ile
  yazılır (sütun 10.82'de hazırdı; şema yok). Kişi başına sunucu başına
  dakikada en çok 10 form çağrısı. Yanıt ekranda (20.000 karakter) gösterilir;
  kişi yazabildiği bir kanal seçtiyse yanıt (3000 karakter) kendi adıyla,
  `locked = 1` mesaj olarak gider (`ws_mcp_form_message_body()`: yanıt kod
  bloğunda, içindeki ``` ve `<@`/`<#` etiketleri etkisizleştirilir, mesaj
  `WS_MESSAGE_MAX`'a sığar). Mesaj Pinegrap AI'ı tetiklemez
  (`ws_message_send()` `ws_ai_after_send()` çağırmaz).
- Onay isteyen (`approve`) araç formla **çalışmaz**: yazan çağrı yalnız
  yanıt altındaki onay kartından, soranın kendi hesabıyla çalışır.
- Pinegrap AI hazır değilken takma ad yazılınca gelen sistem mesajına
  (`ws_ai_after_send()`), sunucunun formla çalışan aracı varsa
  `ws_mcp_form_hint()` eklenir: "@x sunucusunun okuyan araçları onsuz da
  formla çalıştırılabilir: [@x araçlarından birini çalıştır](…)". Bağlantı
  kanalı seçili getirir (`?server=&channel=`; `#` parçası yok, mesajda kanal
  etiketi sayılmasın diye).

Yetki: Çalışma Alanı üyesi (`ws_screen_gate()`); kanal seçimi
`ws_can_post_channel()`. Kanala gönderim için kanalın Pinegrap AI izni
aranmaz: modele bir şey okutulmuyor, kişi zaten görebildiği yanıtı kendi
adıyla gönderiyor.

Testler: `tests/workspace_mcp_test.php`'ye form araçları, sorgu adresi,
şemadan alanlar, argüman türleri, sayı okuma ve kanal mesajı (etiket ve
kod bloğu etkisizleştirme, uzunluk) testleri. Sandbox'ta sahte bir MCP
sunucusuyla (`initialize`, `tools/list`, `tools/call`) uçtan uca denendi:
ekran, boş zorunlu alan hatası, enum/liste/boolean/JSON alanlarının
gönderdiği argümanlar, kanala kilitli mesaj ve `ws_mcp_calls` satırı
(`via form`, `message_id`), OAuth'lu sunucuda kartın "Bağlı değil" hali ve
giriş kurulmamışken Bağla'nın hatası, onaylı aracın formunun açılmaması,
Pinegrap AI kapalıyken sistem mesajı. Gerçek OAuth hizmetine kişi bağlantısı
sandbox'ta denenmedi (yol #324'teki `ws_mcp_person_connect_start()`).

---

## 2026.4.10 — Dış API: para alanlarında en az / en çok sınırı yönlendiricide (2026-10-13)

**Sorun.** Yönlendirici `int` ve `decimal` türlerinde şemadaki `min`/`max`'ı
uyguluyordu ama `money` türünde uygulamıyordu (`api-genisletme-devir.md` 6.
madde). Bu yüzden para alanının sınırı her işleyicide ayrı ayrı ve farklı
sözlerle denetleniyordu: ürün fiyatı "price cannot be negative.", hediye
kartı kendi `api_gift_card_money_or_fail()` yardımcısı, ERP tahsilat, virman
ve çek "Enter an amount greater than zero.", ERP cari kartında
`credit_limit` ise eksi değer **sessizce 0'a çekiliyordu**. Sınır OpenAPI
belgesinde ve MCP araç tanımında da görünmüyordu; entegrasyon kuralı ancak
422 alınca öğreniyordu. Bazı alanlarda hiç üst sınır yoktu: ürün fiyatı
`products.price` (imzalı INT) kolonunun taşıyabileceğinden büyük gelince
MySQL'e kadar gidiyordu.

**Değişiklik.**

- `api_validate_value()` `money` türünde de `min`/`max`'ı uygular. Üç sayı
  türü (`int`, `decimal`, `money`) tek yardımcıdan geçer
  (`api_validate_bounds()`), ret sözü ve alanı hepsinde aynı: 422
  `validation_failed`, "{alan} is below the smallest accepted value." /
  "... above the largest accepted value.", `field` alanın adı.
- `api_openapi_type()` `money` için `minimum`/`maximum` yazar; MCP araçları
  aynı fonksiyondan kurulduğu için `inputSchema`'da da görünür, API
  konsolu (`/docs`) zaten her türde `min–max` notu gösteriyordu.
- Bütün para parametrelerine sınır yazıldı:

  | Uç | Alan | min | max |
  |---|---|---|---|
  | `POST /products`, `POST /products/{id}` | `price` | 0 | 2147483647 (`products.price` imzalı INT) |
  | `POST /gift-cards` | `amount` | 1 | 4294967295 (INT UNSIGNED) |
  | `POST /gift-cards/{id}` | `balance` | 0 | 4294967295 |
  | `POST /erp/accounts`, `POST /erp/accounts/{id}` | `credit_limit` | 0 | — (BIGINT) |
  | `POST /erp/accounts` | `opening_balance` | — | — (eksi: mağaza cariye borçlu) |
  | `POST /erp/receipts` | `amount` | 1 | — |
  | `POST /erp/cash-transfers` | `amount` | 1 | — |
  | `POST /erp/cheques` | `amount` | 1 | — |
  | `POST /erp/expenses`, `POST /erp/expenses/{id}` | `amount` | 1 | — |
  | `POST /erp/expenses`, `POST /erp/expenses/{id}` | `tax_amount` | 0 | — |

- İşleyicilerdeki tekrar denetimler kaldırıldı: ürün oluşturma/güncellemede
  eksi fiyat denetimi, `api_gift_card_money_or_fail()`, ERP tahsilat,
  virman ve çekte `amount <= 0` denetimi, cari kartta `max(0, ...)`.
  Panelle ortak kayıt fonksiyonlarındaki denetimler (`erp_expense_figures()`,
  `erp_account_save()` içindeki `max(0, ...)`) yerinde: panel de onlardan
  geçiyor. Toplu fiyat ucundaki satır denetimi ("price cannot be
  negative.") da yerinde; `list` türünün öğeleri yönlendiricide türlenmiyor.

**Davranışı değişen uçlar.** Hepsi yine 422, alan adı aynı; değişen ret
sözü ve sırası:

- `credit_limit` eksi gelince artık 422 (önceden 0 olarak yazılıp 200/201
  dönüyordu).
- Ürün fiyatı 2147483647'den, hediye kartı tutarı/bakiyesi 4294967295'ten
  büyükse 422 (ürün fiyatında önceden sınır yoktu).
- Ürün fiyatı eksi: "price cannot be negative." yerine "price is below the
  smallest accepted value.".
- ERP tahsilat, virman, çek ve gider `amount` 0 ya da eksi: "Enter an
  amount greater than zero." yerine "amount is below the smallest accepted
  value.". Gider `tax_amount` eksi: önceden `field: amount` ile "The tax
  amount cannot be negative." dönüyordu, artık `field: tax_amount`.
- Sınır dışı tutar artık uygulama sahibinin ERP kasa hakkı, kasa/cari
  varlığı gibi işleyici denetimlerinden **önce** reddedilir (diğer tür
  hataları gibi); kasa hakkı olmayan sahibin `amount: 0` isteği 403 yerine
  422 alır.

Şema değişikliği yok; yeni izin yok.

Testler: `tests/api_money_bounds_test.php` (her para alanının alt sınırı
olması, bilerek eksi alan `opening_balance`'ın adıyla listelenmesi,
yukarıdaki tablonun rotalarla aynı olması, OpenAPI'de `minimum`/`maximum`).
Yönlendirici gerçek `api_validate_value()` ile ayrıca denendi: eksi fiyat,
2147483648 fiyat, 0 hediye kartı, 4294967296 hediye kartı, eksi
`credit_limit`, 0 tahsilat, eksi `tax_amount` 422 ve doğru `field`; 0
fiyat, eksi `opening_balance`, `"500"` kabul; `int`/`decimal` sınırları
eskisi gibi. HTTP katmanı ve MCP sandbox'ta denenmedi.

---

## 2026.4.10 — Dış API ve MCP: ERP vade farkı ve nakit tahmini (ERP Faz 9'un API parçası) (2026-10-13)

**Neden.** ERP Faz 9 (#319) vade farkını ve nakit tahminini yalnız panele
koydu; ikisi de API'de ve MCP'de yoktu (Faz 9 devir notunun 1. ve 2.
maddesi). Bir muhasebe entegrasyonu ya da asistan, carinin işleyen vade
farkını okuyamıyor, faturasını hazırlatamıyor, önümüzdeki günlerin nakit
durumunu soramıyordu.

**Ne değişti.** Üç yeni rota `erp_api_routes()`'ta; MCP'de üç yeni araç
(`erp_accounts_late_fee`, `erp_accounts_late_fee_invoice`,
`erp_reports_cash_forecast`, rotadan kendiliğinden). İşleyiciler
`includes/erp/api_reports.php`'de, panelin çağırdığı fonksiyonları
çağırır. Yeni kapsam, şema adımı ve web kancası olayı yok.

| Rota | Kapsam | Fonksiyon |
|---|---|---|
| `GET /erp/accounts/{id}/late-fee` | `erp:read` | `erp_late_fee_account()`, `_charged_through()`, `_open_draft()` |
| `POST /erp/accounts/{id}/late-fee-invoice` | `erp:write` | `erp_late_fee_draft()` |
| `GET /erp/reports/cash-forecast` | `erp_cash:read` | `erp_cash_forecast()` |

Ayrıntılar:

- **Vade farkı okuma.** `ErpLateFee`: oran (`rate`, aylık yüzde; 0 =
  kapalı), KDV oranı (`tax_rate`, `null` = carinin kendi oranı), dönem
  (`from`, `to`), en son kesilen gün (`charged_through`), toplam ve fatura
  fatura döküm (gün, ortalama borç, vade farkı), bekleyen vade farkı
  taslağı (`draft_invoice_id`). `from` verilmezse `as_of` yılının 1 Ocak'ı
  (mutabakat mektubu gibi). Kesilmiş bir dönem yeniden sayılmaz:
  `charged_through` istenen başlangıçtan sonraysa `from` ertesi güne
  kayar ve `to`'yu geçebilir, toplam 0 olur (açıklamada yazıyor).
- **Vade farkı faturası.** Panel düğmesinin kuralı aynen: tek satırlı
  satış faturası taslağı, bugünün tarihi, mağaza para birimi, ayardaki KDV
  oranı, notlarda fatura fatura döküm; taslak `late_fee_through` taşır,
  kesilince sonraki vade farkı ertesi günden başlar. Hesabın bekleyen vade
  farkı taslağı varsa ikincisi yazılmaz, o taslak 200 ile döner (panel de
  onu açar); bu yolda hiçbir şey yazılmadığı için prova da aynı cevabı
  verir. Oran 0 iken ve dönemde yansıtılacak tutar yokken 422. Prova
  (`X-Dry-Run`) dönemi, tutarı ve fatura sayısını söyler. Günlüğe panelin
  satırı ("erp late payment fee draft …") uygulamanın adıyla yazılır.
- **Tarih kuralı.** İki uçta da `as_of` bugünü geçemez (422): panelin
  `erp_aging_as_of()`'u ileri bir günü kabul ediyor, ama API'de bu
  henüz borçlanılmamış günlere vade farkı yazmak olurdu. `from > as_of`
  422. 10.61 koşmamış sitede iki uç da 503 `service_unavailable`.
- **Nakit tahmini.** `ErpCashForecast`: `horizon` 30/60/90 (başka değer
  422), bugünkü kasa (`cash`, çek kasaları hariç), toplam giriş/çıkış/net
  ve beklenen bakiye; `columns` (vadesi geçmiş + otuzar gün, her birinin
  giriş/çıkış/net ve sonrasındaki bakiyesi), `kinds` (yedi tür, her
  sütun için bir tutar, `columns` sırasıyla), `items` (tarih sırasıyla,
  `limit` kadar, varsayılan 100, en çok 500; `items_count` toplamı).
  Kalemlere kaynak kaydı eklendi: `erp_cash_forecast_items()` artık
  `source` (`invoice`, `expense`, `invoice_recurrence`,
  `expense_recurrence`, `cheque`), `source_id` ve `account_id` de
  döndürüyor; ekran bunları kullanmıyor. Panelde kasa hakkı isteyen ekran
  olduğu için kasa akışı gibi `erp_cash:read` ve sahibin kasa hakkı
  istenir.
- **ErpInvoice.** `late_fee_through` alanı (vade farkı faturasının
  kestiği son gün, diğer faturalarda `null`): panelde 10.61 ile gelen bağ
  API nesnesine de yansıdı.
- `tools/check_api_schema.php` iki yeni nesnenin sunucu–şema eşlerini de
  karşılaştırıyor; `tests/erp_api_test.php`'ye rota kapsamı ve nakit
  tahmini sunumu testleri eklendi.

**Doğrulama.** Sandbox'ta curl: üç faturalı (biri kısmen tahsil edilmiş)
cari; vade farkı okuma (varsayılan dönem, `from`/`as_of`, ileri `as_of`
422, ters tarih 422, olmayan cari 404, yalnız okuma anahtarı 200), elle
hesapla aynı tutar (1.200,00 ₺ × 46 gün + 600,00 ₺ × 29 gün, aylık %3 →
72,60 ₺);
fatura: yalnız okuma anahtarı 403, tanınmayan alan 422, prova (başlık ve
`?dry_run=true`) yazmadı, gerçek 201 taslak (satır, %20 KDV, notlar,
`late_fee_through`), ikinci çağrı ve provası aynı taslak 200, okumada
`draft_invoice_id`; taslak kesilince `charged_through` ve `from` kaydı,
aynı güne ikinci fatura 422; oran 0'da okuma `rate: 0`, yazma 422.
Nakit tahmini: 30/60/90, `horizon=45` ve `limit=0` 422, yalnız okuma
anahtarı ve kasa hakkı olmayan sahip 403; alış faturası, gider, alınan
çek (çek kasası `cheque_tills`), tekrarlayan fatura kalemleri kaynaklarıyla.
OpenAPI'de üç rota ve iki nesne; MCP `tools/list`'te üç araç,
`tools/call` ile okuma, tahmin (`horizon` tamsayıyla) ve fatura provası.
Panelde `erp_cashflow.php?view=forecast` ve cari kartı aynı rakamlarla
açıldı. `php tools/lint.php`, `check_lang.php`, `check_api_schema.php`,
`test.php` temiz.

**Doğrulanamayan.** IIS (dev.pinegrap.com); döviz açık sitede tahmin
(kod değişmedi, yalnız kalemlere alan eklendi).

---

## 2026.4.10 — Tablolarda çoklu sıralama: ürün listesi ve tarayıcıda sıralanan tablolar (2026-10-13)

**Neden.** Buglar listesinin 4. maddesi: "Tüm tablolar çoklu sıralama
desteği alacak. Örneğin stok alçalan fiyat yükselen aynı anda." Panelde
iki ortak sıralama yolu var; ikisi de yalnız tek sütunla sıralıyordu.

**Sunucuda sıralanan liste: ürünler.** `pg_list_state()` artık sıralama
düzeylerini de okur. Adres düzeyleri sırayla `kod:yön` çiftleri olarak
taşır (`sort=inventory_quantity:desc,price:asc`, `pg_list_sort_value()`
yazar). Okuma sırası: değerin tamamı bir kod ya da eski bağlantıdaki bir
başlıksa tek düzeydir (eski davranış), değilse virgülle bölünür
(`pg_list_sort_parse()`). Her düzey `pg_list_sort_clean()`'den geçer:
görünümün sunmadığı sütun ve tekrar düşer, yön `asc`/`desc` dışındaysa
tek sıralamadaki kuralla başlar (varsayılan sütun kendi yönüyle, diğerleri
artan), hiçbir düzey kalmazsa varsayılan döner. ORDER BY yalnız
`pg_list_order_by()` ile, ekranın kod → SQL eşlemesinden yazılır; adresten
gelen hiçbir şey sorguya girmez. Oturumda ilk düzey eskisi gibi
`sort`/`order` olarak durur, sonrakiler yalnız varsa `then` altına yazılır;
böylece daha önce saklanmış tek sıralama aynen okunur ve tek `order`
parametresi taşıyan eski bağlantı ilk düzeyin yönünü çevirir. Dönüşe
`sorts` (bütün düzeyler) eklendi, `sort`/`order` ilk düzeydir.

`get_column_heading()` `$sort` olarak düzey listesi alırsa
`pg_list_sort_heading()`'e devreder; dizge alan 35 ekran eskisi gibi
çalışır. Başlık bağlantısı: düz tık o sütunla tek başına sıralar (öndeki
düzeyse yönü çevirir, DataTables'ın alışılmış davranışı);
`data-pg-sort-add` Shift+tıkın gideceği adresi taşır
(`pg_list_sort_toggle()`: yeni sütun sona artan eklenir, artan düzey
azalan olur, azalan düzey çıkar, tek düzeyse artana döner). Birden çok
düzey varken sıralı başlıkta düzey numarası çıkar. Ok ve numara metin
değil ikon (`bi-N-circle-fill`), çünkü sütun görünürlüğü listesi başlığın
metnini okur; ikon başlığın adını kirletmez. Shift+tık tarayıcıda yeni
pencere açtığı için `backend.src.js` tıklamayı belgeye devredilmiş tek
dinleyiciyle yakalar (DataTables'ın sabit başlığı başlıkları kopyalar)
ve Shift+mousedown'da metin seçimini engeller.

Shift dokunmatikte yok; araç çubuğundaki "Sıralama" menüsü
(`pg_list_sort_menu()`) aynı işi bağlantı ve GET formuyla yapar: düzeyler
sırayla, her birinin yönünü çevirme ve kaldırma bağlantısı, "Ardından şuna
göre sırala" listesi (her sütun artan ve azalan, değer düzeylerin tamamı)
ve varsayılana dönüş. Listenin boş ilk seçeneği `required` ile formun boş
`sort` göndermesini, yani listeyi sıfırlamasını engeller. Dışa Aktar aynı
düzeyleri kullanır; dışa aktarma sorgusunda etki puanı olmadığı için o
düzey eskisi gibi son değişikliğe göre sıralar.

**Tarayıcıda sıralanan tablolar (`data-pg-sort`).** `backend.src.js`
sıralayıcısı düzey listesiyle çalışır: Shift+tık sunucudakiyle aynı
adımları uygular, düz tık eskisi gibi tek sütunu artan → azalan → sunucu
sırası döndürür. Her düzeyin sayı mı metin mi olduğu ayrı belirlenir; boş
hücreler her düzeyde sona gider, eşitlikte sunucunun sırası korunur.
`aria-sort` yalnız öndeki başlıkta (bir anda bir başlık), sıralı her
başlık `data-pg-sort-level` taşır. Saklanan seçim `3:desc,1:asc`
biçiminde; eski tek `3:asc` bir düzey olarak okunur. Bu yolun Shift'siz
düzey ekleme yolu yok: telefonda bu tablolar tek sütunla sıralanır.

**Hangi tablolar kazandı.** Sunucu yolu: yalnız `view_products.php`
(`pg_list_state()`'i kullanan tek ekran). Tarayıcı yolu, değişmeden: 27
ekran (`edit_erp_account`, `edit_erp_invoice`, `edit_erp_till`,
`edit_erp_waybill`, `erp_account_prices`, `erp_accountant`,
`erp_accounts_import`, `erp_audit`, `erp_bank_statement`,
`erp_bank_statements`, `erp_cashflow`, `erp_cheques`, `erp_dashboard`,
`erp_expenses`, `erp_export`, `erp_inbox_document`,
`erp_invoice_recurrences`, `erp_profit`, `erp_purchase_orders`,
`erp_quotes`, `erp_receipt`, `erp_stock`, `erp_stock_count`,
`erp_stock_counts`, `erp_stock_minimums`, `erp_vat_report`,
`search_console`). Dokunulmayanlar: sıralamayı oturumda başlık adıyla
tutup kendi `switch`'iyle ORDER BY kuran 35 ekran (`view_orders`,
`view_contacts`, `view_pages`, `view_users`, `view_files`,
`duplicate_products` gibi; `get_column_heading()`'e dizge veriyorlar),
`asc_or_desc()` kullanan 8 ekran (`view_countries`, `view_log`,
`view_zones` gibi) ve hiç sıralaması olmayan DataTables listeleri
(`erp_invoices`, `erp_accounts`, `erp_waybills` gibi). Bunların her biri
`pg_list_state()`'e taşınınca aynı yardımcılarla kazanır.

Testler: `tests/list_state_test.php` (adresten düzeyler ve oturumdaki
`then`, bilinmeyen/tekrar düzeyin düşmesi, saklanan düzeylerin tek
sıralamayla yer değiştirmesi, Shift+tık adımları, ORDER BY'ın yalnız
eşlemeden yazılması, başlık bağlantıları ve eski tek sıralama
bağlantısı). Mevcut altı test değişmeden geçiyor. Şema değişikliği yok.

**Doğrulandı (sandbox, MariaDB, Chromium).** Ürünler
`sort=inventory_quantity:desc,price:asc` ile stoğu 10 olanlar fiyat artan
geldi; başlıkta Shift+tık aynı sekmede üçüncü düzeyi ekledi ve oturumda
kaldı; menüde kaldırma, ekleme formu ve varsayılana dönüş çalıştı; eski
`sort=price&order=desc` bağlantısı tek sütunla sıraladı; çok düzeyli
(etki dahil) Dışa Aktar 200 döndü. 390 piksel genişlikte menü dokunuşla
açıldı. `erp_stock_minimums.php`'de Stok azalan + Ürün artan Shift+tıkla
kuruldu, sayfa yenilenince geri geldi, ikinci Shift+tık düzeyi kaldırdı,
eski `2:desc` saklı değeri okundu, Shift+tık metin seçmedi; konsolda hata
yok. Doğrulanamadı: Safari/iOS'ta menü ve gerçek bir telefonda dokunuş.

---

## 2026.4.10 — Banka ekstresi `.ods` dosyasını da alıyor (2026-10-13)

**Neden.** ERP Faz 9 (10.61) banka ekstresinde `.ods`'yi adla reddediyordu,
çünkü PHPExcel'in OpenDocument okuyucusu PHP 8'de ölüyordu. Okuyucu ürün içe
aktarımı düzeltmesiyle (`implode()` sırası, `Europe/London` çevirisi, saat
hücresi) onarıldı; ekstreyi LibreOffice'te açıp yeniden kaydeden operatörün
dosyası artık geri çevrilmiyor.

**Değişiklik.** `erp_bank_read_file()` `.ods`'yi çalışma kitabı sayar; ret
listesinde `.numbers` ve `.pdf` kaldı, ret metni `.ods`'yi de sayar.
`erp_bank_read_spreadsheet()`'in PHPExcel kolu `.xls` ile `.ods`'yi birlikte
okur; okuyucu adı ürün içe aktarımının `pg_products_import_reader()`'ından
gelir (`OOCalc`), ayrı bir okuyucu ya da kopya yok. `.xls` gibi
**biçimleriyle** okunur (`setReadDataOnly` yok): OOCalc tarih hücresine
tarih biçimi koyar, `erp_bank_xls_cell()` onu `Y-m-d` (saatliyse `Y-m-d H:i`)
yazar; kuruşlu ve para birimli (`currency`) hücre aynı makine biçimine döner.
Dosya OOCalc'a verilmeden önce zip olarak açılıp `content.xml` ve `meta.xml`
aranır: okuyucu açılamayan zip'te hata vermeden devam edip uyarılarla
düşüyordu, şimdi bozuk `.xlsx` gibi "Dosya okunamadı" der. Başlık bloğunun
atlanması (`erp_bank_rows_from_heading()`), sütun ekranı, satır karması ve
eşleştirme değişmedi; satırlar `raw_rows`'a `.xlsx`'tekiyle aynı yazılır.
Yükleme formu `.ods`'yi kabul eder; alan adı ve yardım metni onu da sayar
(`tr.json`'da üç anahtar yenilendi). Şema değişikliği yok.

**Bilinen.** PHPExcel `getLibXmlLoaderOptions()` içinde
`libxml_disable_entity_loader(true)` çağırır ve geri açmaz: aynı istekte
PHPExcel'den sonra `XMLReader::open()` ile dosya açılamaz. Ekstre yüklemesi
istek başına bir dosya okuduğu için etkisiz; `.xls`/`.ods` ile `.xlsx`'i aynı
istekte okuyacak bir kod (toplu içe aktarım, test) `.xlsx`'i önce okumalı.

**Doğrulama.** `tests/erp_bank_import_test.php`: elle yazılmış `.ods`
(başlık bloğu, boş sütun, `span`'lı metin, tarih ve tarih-saat, `currency` ve
eksi kuruşlu `float`, LibreOffice'in tekrarlı boş satır/sütunları) UTC,
Europe/Istanbul ve America/New_York'ta aynı satırları verir; zip olmayan ve
`content.xml`'siz `.ods` okunamaz der; `.pdf`/`.numbers` ret metni `.ods`'yi
sayar. Sandbox'ta örnek ekstre (`ornek-banka-ekstresi.xlsx`) LibreOffice ile
`.ods`'ye çevrildi; `erp_bank_read_file()` dört saat diliminde (Kiritimati
dahil) `.xlsx` ile birebir aynı sonucu verdi (7 satır: başlık + 6 hareket).
Panelden yüklenen `.ods`'de sütun ekranı "İşlem Tarihi" başlığıyla açıldı;
tarih, açıklama ve tutar seçilince 6 satır geldi (+10.000, −15.000, −3.000,
+2.450,75, −12,50, +1.141). Bozuk `.ods` "Dosya okunamadı", `.pdf` yeni ret
metnini verdi.

---

## 2026.4.10 — Çalışma Alanı: seçili mesajları kopyalama, telefonda mesaj kopyalama, ayırt edilen kanal listesi düğmesi (2026-10-13)

**Neden.** Ürün sahibi telefonda mesaj kopyalayamadı. Asıl eksik: "Mesajları
seç" ile birden fazla mesaj seçildiğinde alttaki çubukta Yanıtla / İlet /
Nota ekle vardı, kopyalama yoktu. Tek mesajın "Metni kopyala"sı da
mesaj menüsünün alt bölümündeydi: telefonda ya mesaja dokunup araç
satırındaki "…" ile ya da basılı tutarak açılan uzun menünün sonundan
bulunuyordu. Basılı tutma menüyü açtığı için (`onContext()`, Android'in
`contextmenu` olayı iptal edilir) tarayıcının kendi metin seçimi de
çalışmıyordu. Kopyalanan metin `plain(html, true)` ile tek satıra
iniyordu (`\s+` → boşluk), satır sonları ve liste maddeleri birleşiyordu.
Aynı ekran görüntüsünde ikinci sorun: telefonda Çalışma Alanı başlığındaki
kanal listesi düğmesi `bi-list` (üç çizgi) simgesiydi ve panelin ana menü
düğmesinin tam altında duruyordu; ikisi karışıyordu.

**Ne değişti.** `assets/js/workspace.js` (+ `workspace.min.js`),
`assets/css/backend.src.css`, `includes/workspace/screen.php` (metinler ve
`timezone`), `includes/workspace/forward.php` (seçim çubuğu metinleri).
- Seçim çubuğuna "Kopyala" (`copySelected()`): seçili mesajlar
  `selectedMessages()` sırasıyla (eskiden yeniye) `[gg.aa.yyyy SS:dd] Ad:`
  başlığı altında, aralarında boş satırla panoya gider; dosyalı mesajda
  "Dosya: ad" satırı eklenir. Tarih ekrandaki "Dün 21:18" gibi göreli
  etiket değil, tam tarih-saattir ve sitenin saat diliminde yazılır:
  `ws_screen_config()` artık `timezone` (`date_default_timezone_get()`,
  Site Ayarları'ndaki dilim) taşır; tarayıcının bilmediği dilimde
  kendi saatine düşer. Kopyaladıktan sonra seçim kapanır.
- "Metni kopyala" (`bi-copy`) `tool: true` ile araç satırına çıktı ve
  mesaj menüsünde Yanıtla / Görev yap'ın hemen altına taşındı; metni
  olmayan (yalnız dosya) mesajda görünmez. Telefonda araç satırı mesaja
  dokununca açılır; satır sığmazsa artık alta kayar (`flex-wrap`).
- Yeni `readableText()`: mesajın HTML'i bir an ekran dışına yerleştirilip
  `innerText` ile okunur; satırlar, liste maddeleri ve tablo satırları
  kendi satırlarında kalır, form kartı (`.ws-form-card`) çıkarılır.
- Dokunmatik ekranda (`touchScreen()`) menüye "Metni seç" eklendi:
  `textSheet()` metni salt okunur bir `textarea` ile kipte açar; basılı
  tutup istenen kısım seçilir, "Tümünü kopyala" da vardır. Alan 16px
  (iOS yakınlaştırmasın).
- `copyText()`: pano API'si reddederse (`catch`) ya da güvenli bağlam
  yoksa `copyFromField()` denenir; `execCommand('copy')`'nin dönüş değeri
  artık okunur (önceden false dönse de "Kopyalandı" yazıyordu), iOS için
  `setSelectionRange()` ve `readonly`, açık bir kip ya da çekmece varsa
  geçici alan onun içine konur (Bootstrap odak tuzağı seçimi geri
  alıyordu). İkisi de reddedilirse metin `textSheet()` kipinde seçilmeye
  hazır açılır. Bu iyileştirme bağlantı/numara kopyalayan bütün menülere
  de gelir.
- `plain()`'in yalnız kopyalama için duran `full` bayrağı kaldırıldı.
- Kanal listesi düğmesi üç yerde (`drawCenter`, ana sayfa, zamanlanmış
  mesajlar) aynı satırdı; tek `channelsToggle()` yardımcısına indi.
  Görünüş: `bi-chevron-left` + "Kanallar" yazılı, Çalışma Alanı vurgu
  renginde yuvarlak düğme (`.ws-channels-toggle`, yalnız `md` altında);
  360px'ten dar ekranda yalnız ok kalır. `title`/`aria-label` "Kanal
  listesini göster".

Yeni metinler (tr.json): `File: {var:1}`, `Show the channel list`, `Copy all`,
`The browser did not let the text be copied. Select it and copy it from
here.`, `Press and hold to select the part you need, then copy it.`,
`Select the text`.

**Bilerek yapılmayan.** Basılı tutma yine menüyü açar, tarayıcının kendi
seçimine dönülmedi (menü telefonda tepki ve diğer eylemlerin tek yolu);
kısmi kopyalama "Metni seç" kutusundan yapılır. Misafir odası
(`workspace_guest.js`) mesaj menüsü taşımadığı için oradaki seçim zaten
tarayıcınındır, dokunulmadı.

**Doğrulama.** Sandbox'ta Playwright ile 412×915 dokunmatik görünüm:
basılı tutma → "Mesajları seç", üç mesaj seçilip çubuktaki "Kopyala"ya
basıldı; pano üç mesajı sırasıyla, `[14.10.2026 00:36] admin:`
başlıklarıyla (sitenin İstanbul saati; tarayıcı UTC'deydi), çok satırlı
metin ve liste maddeleriyle aldı; seçim kapandı, çubuk telefonda iki satıra
sığdı.
başlıkta "‹ Kanallar" görünür, ana menü düğmesinden ayrı; dokununca liste
açılır. Çok satırlı mesajda araç satırındaki kopyalama düğmesi panoya
satır sonlarıyla yazdı; basılı tutma menüsünde "Metni kopyala" ve "Metni
seç" üst sırada; "Metni seç" kutusu aynı metni gösterdi; pano API'si
kaldırılıp `execCommand` reddettirildiğinde kutu "Tarayıcı … izin
vermedi" notuyla açıldı. 1366px masaüstünde düğme gizli, üzerine gelince
çıkan araç çubuğunda kopyalama düğmesi var, sağ tık menüsünde "Metni seç"
yok. `php tools/lint.php`, `check_lang.php`, `tools/test.php`,
`build_js.php --check` temiz.

---

## 2026.4.10 — Düğmeler "Kaydediliyor…" yazıp bekletmiyor; çift gönderim formda önleniyor (2026-10-13)

**Sorun.** Ürün sahibinin hata kanalından: panel düğmeleri basılınca
"Oluşturuluyor", "Kaydediliyor", "Yükleniyor" gibi yazılara dönüyor ve Ajax
ile çalışan ekranlarda kullanıcıyı boşuna bekletiyordu. İstek: özellik
kaldırılsın, düğmeler buna göre sadeleşsin.

**Kök sebep.** `backend.src.js`'teki ortak tıklama işleyicisi
`data-loading-content` taşıyan her bağlantı ve düğmede etiketi bir
spinner'la o metne çeviriyor, `disabled` sınıfı ekliyor ve **sabit 10
saniye** sonra geri alıyordu. İstekle hiç bağı yoktu: Ajax ile kaydeden
ekran (Site Ayarları penceresinin Kaydet'i, `type="button"` düğmeler) cevap
200 ms'de gelse de 10 saniye "Kaydediliyor" gösteriyordu; tarayıcının
doğrulaması (`required`) formu durdursa da düğme kilitleniyordu; dosya
indiren form 10 saniye yeniden gönderilemiyordu. Öznitelik 211 dosyada 530
öğedeydi (kaydet/oluştur düğmeleri, liste satırı düzenle/sil düğmeleri,
breadcrumb ve "Oluştur" bağlantıları, toplu işlem düğmeleri). Ayrıca birkaç
ekran aynı işi kendi koduyla yapıyordu.

**Ne değişti.**
- `data-loading-content` 530 öğeden kaldırıldı; özniteliği bir değişkende
  tutan `erp_inbox_document.php` ve `erp_account_sync_item.php`'deki `$wait`
  ile kurulumdaki `$output_submit_button_process_label` de gitti.
- `backend.src.js` tıklama işleyicisi yalnız onay (`data-confirm-content`,
  `data-confirm-action`) ve toplu işlem (`value="Delete Selected"` vb.)
  mantığını taşıyor; `loading_triggered()` silindi. Onaylı ve toplu
  işlemlerin akışı aynı.
- Çift gönderim artık düğmede değil, form ve bağlantıda önleniyor:
  `window` üzerinde `submit` ve `click` dinleyicileri aynı formun ya da
  bağlantının 3 saniye içindeki ikinci gönderimini sessizce yutar. Ekranda
  hiçbir şey değişmez; gönderen düğme `disabled` olmadığı için adı ve değeri
  (`submit_save=Save` gibi) istekte kalır. Dinleyiciler `window`'da olduğu
  için formdaki ve `document`'taki her işleyiciden sonra koşar: Ajax ile
  gönderen ya da bağlantıyı kendisi işleyen ekran olayı zaten iptal etmiştir
  ve hiç bekletilmez. Hedefi başka sekme/çerçeve olan form ve bağlantı,
  `#` ve `javascript:` bağlantıları, `download` bağlantıları ve değiştirici
  tuşlu tıklamalar korunmaz. Tarayıcı geçmişinden (bfcache) dönülünce koruma
  sıfırlanır. Süre kısa tutuldu: cevabı indirme olan istek sayfayı yerinde
  bırakır ve hemen tekrarlanabilmeli. Dinleyiciler pencere başına bir kez
  kurulur (`window.pgSubmitGuard`); ikinci kopya, ilkinin az önce işaretlediği
  formu görüp gönderimi iptal ederdi.
- Kendi kopyasını taşıyan ekranlar: sayfa tasarımcısının Kaydet'i
  (`page_designer.js`) "Kaydediliyor" yazmıyor, kayıt bitince "Kaydedildi"
  hemen çıkıyor (önceden kayıt hata verirse düğme "Kaydediliyor"da takılı
  kalıyordu); kullanılmayan `Saving`/`Creating` etiketleri
  `page_designer.php`'den çıktı. Barkod şablonu kaydet (`backend.src.js`) ve
  menü sıralama kaydet (`view_menu_items.php`) istek sürdüğü kadar
  `disabled`, etiket değişmiyor. Dosya yükleme (`add_file.php`,
  `add_design_file.php`) yükleme sürerken `disabled` kalıyor, "Yükleniyor..."
  yazmıyor (Dropzone ilerlemeyi dosyaların üstünde gösteriyor); yükleme hata
  verirse düğme yeniden açılıyor, önceden kalıcı kilitliydi.

**Bilerek yapılmayan.** Etiketi değiştirmeden istek sürdüğü kadar düğmeye
spinner koyan ve her yolda geri alan ekranlar olduğu gibi kaldı
(`style_designer.js` şablon oluştur, `view_folders.php` döndür,
`search_console.php`). Yazılım Güncelleme ekranı (`software_update.php`)
kendi ilerleme çubuğu ve günlüğüyle çok adımlı bir sunucu işi yürütüyor;
düğmesi değişmedi. Çalışma Alanı notlarının otomatik kayıt durumu
("Kaydediliyor…") ve kampanya/ön yüz bildirimleri düğme değil durum
satırıdır, kapsam dışı. Artık kullanılmayan `tr.json` anahtarları
(`Creating`, `Duplicating` vb.) ortak dosyada çakışma çıkarmasın diye
silinmedi. Düğme tasarımı/CSS değişmedi.

**Doğrulama.** `tools/lint.php`, `tools/check_lang.php`,
`tools/check_bindings.php`, `tools/check_api_schema.php`, `tools/test.php`,
`tools/check_copies.php`, `tools/build_js.php --check`; `node --check`
`backend.src.js` ve `page_designer.js`. Başsız Chromium'da gerçek
`backend.src.js` + jQuery + Bootstrap ile: yavaş cevaplı formda ikinci
tıklama ikinci POST'u göndermiyor ve istek `submit_save=Save`'i taşıyor
(eski kodla iki POST); Ajax formunun işleyicisi üç tıklamada üç kez
çalışıyor, düğme etiketi ve durumu değişmiyor; `required` alanı boş form
hiç gönderilmiyor ve korumayı kurmuyor; indirme formu aynı anda iki
tıklamada bir, 3,2 saniye sonra yeniden gönderiliyor; GET bağlantısına
çift tıklama tek istek (eski kodla iki). Panel ekranları dev ortamda
elle denenmedi.

---

## 2026.4.10 — Ürün kaydı bildirimi indiği ekranda; ürün listelerinde görseller büyük açılıyor (2026-10-13)

**Sorun.** Ürün sahibinin hata kanalından iki madde. (1) Ürün kaydedilince
bildirim ürün listesinde çıkmıyor, başka bir sayfaya gidilince beliriyordu.
(2) Ürün listelerinde görsel yalnız 50 piksellik küçük resimdi; müşteriler
ürün güncellerken benzer ürünleri ayırt etmekte zorlanıyordu.

**Kök sebep (1).** liveform bildirimleri oturumda form adına göre durur ve
yalnız o adı basan ekranda gösterilip silinir. `edit_product.php` "ürün
güncellendi"yi kendi formuna (`edit_product`) yazıp `send_to`'ya (çoğunlukla
`view_products.php`) gidiyordu: liste bildirimi görmüyor, bir sonraki ürün
açılınca ürün ekranı eski ürünün bildirimini basıyordu. `add_product.php`
varyantlı üründe bildirimi `view_products`'a yazıp grup ekranına
(`edit_product_group.php`) gidiyordu; ürün formu istenince `view_fields.php`'ye
hiç bildirim gitmiyordu. Aynı hata 2026.4.9'da da var.

**Ne değişti.**
- `edit_product.php`: bildirim, kaydın indiği ekranın formuna yazılır.
  "Kaydet ve kal" ürün ekranına, dönüş ürün listesiyse `view_products`'a.
  `send_to` başka bir yere (ör. sitedeki satır içi düzenleme düğmesinin
  sayfası) gidiyorsa bildirim kuyruğa girmez; o sayfa panel bildirimi basmaz
  ve kuyruktaki not sonradan listede yanlış anda çıkardı. Silme bildirimi de
  aynı kuralla.
- `add_product.php`: varyantlı ürün bildirimi `edit_product_group`'a, ürün
  formu istenince `view_fields`'e (tek ürün ve varyant seti) yazılır.
- Yeni `pg_product_image_cell()` ve `pg_product_more_images()`
  (`includes/fn/ecommerce.php`). Hücre küçük resmi bir düğmeye sarar ve
  panelin mevcut resim görüntüleyicisini (`assets/js/pg_lightbox.js`,
  `_pg_lightbox_assets_once()`) kullanır: aynı `data-pg-lb-gallery` değerini
  taşıyan öğeler tek galeri olur; kapak düğmenin kendisi, ek görseller
  (`products_images_xref`) aynı hücrede boş gizli `span`'lar. Sıra ürün
  ekranındakiyle aynı (kapak önce, ek görseller tabloya yazıldıkları sırada).
  Altyazı "SKU - kısa açıklama". Düğmede `no-submit` var: `backend.js`'in
  düğme işleyicisi `type=button` düğmelerin etiketini "yükleniyor" metnine
  çevirir. Ek görseller listenin tamamı için tek sorguyla okunur;
  `view_products.php`'de kimlikler sonucu ilk geçişte toplanıp
  `mysqli_data_seek()` ile başa sarılır.
- Kullanan listeler: `view_products.php` (bütün filtreler),
  `edit_product_group.php` (gruptaki ürünler ve alt gruplar — alt grubun
  yalnız kapağı — ve eklenebilecek ürünler), `add_product_group.php`, "Daha
  Fazla Ürün Yükle"nin satırları (`pg_panel_get_unselected_products`,
  `includes/panel/commerce.php`; görüntüleyiciyi çağıran ekran yükler) ve
  `duplicate_products.php`. Görüntüleyici yalnız "Tablolarda Ürün Görsellerini
  Göster" açıkken yüklenir.
- Küçük resim adresi artık `encode_url_path()`'ten geçiyor; boşluklu ya da
  Türkçe karakterli dosya adı da açılıyor.

**Bilerek yapılmayan.** Varyant setleri görünümü (`pg_pb_variant_sets_screen()`)
ve ERP satır tablolarındaki küçük resimler değişmedi: ilki grubun görseli,
ikincisi ürün listesi değil. Ürün grubu kaydı (`edit_product_group.php`)
hiç bildirim yazmıyor; bu iş kapsamında değil.

**Doğrulama.** `php -l`; `tools/lint.php`, `tools/check_lang.php`,
`tools/test.php`. Hücre çıktısı, sayfanın kendi `pg_lightbox.js`'iyle
başsız tarayıcıda denendi: kapak + iki ek görsel 1/3, 2/3 sırasıyla, kapakla
aynı ve boş ek görsel atlanıyor, tırnak ve `<` içeren altyazı metin olarak
çıkıyor, tek görselde küçük resim şeridi yok.

---

## 2026.4.10 — Görev bir MCP takma adına verilebiliyor (OAuth Faz F3) (2026-10-13)

**Neden.** `docs/_plan_tersine_oauth.md` §4.6 (senaryo 5), Faz F'nin son
adımı. Görev devri (#265/#269/#271) yalnız Pinegrap AI ve Claude'u tanıyordu;
`ws_assistants()` başka bir asistanın aynı listeye satır ekleyerek katılması
için kurulmuştu. #307 kanalda `@takmaad` akışını, #324 kişi başı bağlantıyı
ve yazan araçlar için onay kartını getirdi; bu parça ikisini göreve taşır.
Şema yok: `ws_tasks.delegate` VARCHAR(32) `mcp:<server_id>`'yi alır,
`ws_ai_requests.mcp_server_id` (10.82) ve `ws_ai_changes.task_id` (10.80)
zaten var. 10.83 etiketi kullanılmadı, boş kaldı.

**Ne değişti.**

- `includes/workspace/delegation.php`: anahtar `mcp:<server_id>`
  (`ws_assistant_mcp_id()`). `ws_assistants()` Pinegrap AI açıkken, açık ve en
  az bir aracı açık her sunucuyu `@takmaad` adıyla ekler (uygulaması Pinegrap
  AI'ınki). Çalışma Pinegrap AI'ın isteğidir: `agent = 'ai'`,
  `mcp_server_id` dolu (`ws_assistant_agent()`); kişi başı sınır
  `WS_AI_PER_PERSON`, kanal anahtarı Pinegrap AI'ınki. `ws_task_run_key()`
  isteği yeniden anahtara çevirir: çekmecedeki çalışma adı, önceki
  çalışmalar, açık çalışma haritası (API `assistant_working`),
  `workspace.task.assistant_answered` olayının `agent`'ı ve "asistan
  cevap verdi" bildirimi `@takmaad` der. `ws_assistant_task_problem()`
  OAuth sunucusunda soranın (kendi ya da sitenin) hesabını ister; yoksa
  düz bir cümle döner (bağlantı değil). Çekmece verisinde takma ad satırı
  `title` (sunucu adı) ve hesap yoksa `connect` taşır. Sorgular
  `mcp_server_id`'yi yalnız 10.82 koşmuşsa okur
  (`ws_delegation_server_column()`).
- `includes/workspace/mcp_ask.php`: `ws_mcp_ask_start()` görev isteğini
  `ws_mcp_ask_task_start()`'a verir. Model kanal mesajında olduğu gibi yalnız
  işin gerektirdiğini okur: görevin başlığı, açıklaması, durumu, önceliği,
  tarihleri, talimat ve **aynı sunucunun** bu görevdeki önceki soru/yanıtları.
  Notlar, kişiler ve kanal gitmez (modelin okuduğu her şey araç argümanıyla
  diğer hizmete gidebilir). Sistem metni "görev @x'e verildi, yanıtın göreve
  yazılır" der. `ws_mcp_ask_propose()` artık görevde de öneri kurar (kanal
  ya da görev şart); `ws_ai_deliver()`'ın görev dalı
  `ws_mcp_calls_propose($row, 0, …)` ile `mcp_call` kartlarını görevin
  üstüne yazar (`task_id`, kanal 0). `ws_mcp_call_run()` görevde karar
  mesajı yerine göreve not yazar (uygulayan kişi adına,
  `ws_task_note_add(…, 0, false)`: not hiçbir asistanı çağırmaz).
- Sunucu kaldırılınca ona verilmiş görevlerin `delegate`'i boşalır
  (`mcp_delete`), önerileri karar için kalır.
- Çekmece (`assets/js/workspace_task_delegate.js`): seçicide
  "@takmaad · Sunucu adı"; hesabı olmayan takma ad seçilebilir ama "Asistana
  ver" yerine "Hesabımı bağla" çıkar. Görevdeki `mcp_call` kartı kanal
  kartıyla aynı: "Hesabımı bağla" / engel cümlesi / "Hesabımın bağlantısını
  kes". Karar verilince görev yeniden çizilir (karar notu görünsün). Satır
  etiketi takma ad için `bi-plug`.
- Bağlanma dönüşü: `ws_mcp_connect` eylemi `task_id` alır, dönüş
  `workspace_tasks.php?task=X&mcp_connected=Y`; görevler ekranı bildirimi
  gösterir (`workspace_tasks.php` artık `mcp` yapılandırmasını verir).
- API: `POST /workspace/tasks/{id}/delegate` `agent`'ı ve
  `GET /workspace/tasks` `delegate` süzgeci enum yerine metin
  (`ai`, `claude`, `mcp:<id>`; süzgeçte `any`/`none`), geçersiz değer 422.

**Denendi.** Birim testleri (`tests/workspace_delegation_test.php`: anahtar
ayrıştırma, `ws_assistant_agent()`, `ws_task_run_key()`). Sandbox'ta
(`tools/setup_sandbox.sh`, sahte sunucu satırı, model elle sürüldü) 51
kontrol: takma ad listede, devir `delegate = mcp:id` ve `agent ai` +
`mcp_server_id` yazar, aynı anda tek çalışma, ilk mesajda görev ve talimat
var ve görev notu gitmiyor, araçlar yalnız sunucununki, yazan araç görevde
`pending` + `mcp_call` kartı (task_id, kanal 0), bekleyen sayacı, çekmece adı
ve kartı, OAuth değilse kart engelli, reddetme çağrıyı `dismissed` yapar,
karar notu çalışma başlatmaz, ikinci çalışmaya önceki yanıt gider, OAuth'ta
hesap yoksa seçicide "bağla" ve devir reddi, kendi hesabı olmadan Uygula
reddedilir ve kart bekler. Tarayıcıda çekmece (Playwright): seçici, bağlan
ipucu, görevdeki kart. Gerçek bir OAuth MCP sunucusunda uygulama (karar
notu) denenmedi; dev.pinegrap.com kontrol listesinde.

**Kapsam dışı (devir notunda).** Görev notunda `@takmaad` yazınca sorma
(bugün yalnız `@ai` / `@Claude`) ve uzak ajan (seviye C). "MCP bağlantılarım"
ekranı ve okuyan araçlar için form ayrı geldi (#334).

---

## 2026.4.10 — Ürün içe aktarımı: `.ods` PHP 8'de okunuyor; tablo tarihleri saat diliminden bağımsız (2026-10-13)

**Sorun.** Ürünleri İçe Aktar'a verilen OpenDocument (`.ods`) dosyası PHP
8'de ölümcül hatayla duruyordu (ERP Faz 9'un "bilinen sorun" kaydı).
`includes/phpexcel/PHPExcel/Reader/OOCalc.php:538` `implode($dataArray, "\n")`
yazıyordu; ters argüman sırası PHP 7.4'te kullanımdan kalktı, PHP 8'de
`TypeError`. `pg_products_import_to_csv()` yalnız `Exception` yakaladığı için
hata, "Dosya okunamadı" yerine boş sayfa oldu.

Düzeltme sandbox'ta denenince aynı yolda iki tarih hatası çıktı:

- OOCalc tarih hücresini UTC'den `Europe/London`'a çeviriyordu; İngiltere yaz
  saatinde (mart sonu–ekim sonu) her tarihe bir saat ekleniyor, `2026-07-15`
  hücresi İstanbul'da `2026-07-15 04:00:00` olarak aktarılırdı.
- `pg_products_import_cell()` ve `pg_products_import_xlsx_date()` UTC'ye göre
  hesaplanmış damgayı `date()` ile, yani sitenin saat dilimiyle yazıyordu:
  saat içeren hücre o kadar kayıyor (İstanbul'da 13:45 → 16:45), UTC'nin
  gerisindeki saat dilimlerinde (Amerika) tarih bir gün geri gidiyordu. Bu
  `.xlsx` yolunda da vardı. Banka ekstresi (`erp_bank_sheet_value()`) zaten
  `gmdate()` kullanıyor.

**Değişiklik.** Vendored `OOCalc.php`'de üç satır: `implode("\n", $dataArray)`;
tarih hücresi UTC'de kalır (`Europe/London` çevirisi ve kullanılmayan
`$timezoneObj` kalktı); saat hücresi `strtotime()` yerine `gmmktime()` ile
kurulur, çünkü `PHPToExcel()` damgayı UTC okur ve `strtotime()` sunucu saat
dilimini uygular (eskiden iki kayma `date()` ile birbirini götürüyordu).
`import_products_f.php`'de iki `date()` → `gmdate()`. `OOCalc.php`'de başka PHP
8 kırığı yok; PHPExcel'in dinamik özellik ve "zorunludan önce isteğe bağlı
parametre" bildirimleri yalnız kullanımdan kalkma uyarısı, davranışa dokunmaz.
Banka ekstresinde `.ods` reddi (`erp_bank_read_file()`) yerinde kaldı; açılması
ayrı iş. Şema değişikliği yok.

**Doğrulama.** `tests/import_products_test.php`: elle yazılmış `.ods` (çok
paragraflı ve `span`'lı metin, sayı, mantıksal, yaz ve kış tarihi, tarih-saat,
saat) UTC, Europe/Istanbul ve America/New_York'ta aynı CSV'yi verir;
`pg_products_import_xlsx_date()` üç saat diliminde aynı günü ve saati verir.
Düzeltmeden önce test `TypeError` ve kayan tarihlerle düşüyor. Sandbox'ta
LibreOffice'in yazdığı `.ods`, `.xlsx` ve `.xls` aynı üç saat diliminde
`pg_products_import_to_csv()`'den geçti; yalnız tarih taşıyan `.xlsx` ve `.xls`
İstanbul ve UTC'de eskisiyle aynı çıktıyı verdi (`.xls` tarih hücresi eskisi
gibi gün sayısı: okuyucu `setReadDataOnly(true)`). Dokunulmayan: formüllü
hücrede PHPExcel `Calculation.php:3349` "array offset on null" uyarısı verir
(değer doğru, uyarı `php_errors.log`'a düşer).

---

## 2026.4.10 — Dış API: anahtar kodlar ve ürün nitelikleri (2026-10-13)

**Sorun.** Anahtar kodlar (teklif kodunun yerine yazılan, kişiye, karta ya da
gönderime özel kodlar) ve ürün nitelikleri (beden, renk ve seçenekleri) API'de
yoktu. Bir CRM ya da bülten aracı kişiye özel indirim kodu üretemiyor, hangi
kodun kullanıldığını okuyamıyordu. Ürün yazan bir entegrasyon `POST /products`
`attributes` alanına `attribute_id`/`option_id` vermek zorundaydı ama bu
kimlikleri okuyacağı ya da yeni bir seçenek (XL) açacağı bir uç yoktu.

**Değişiklik: anahtar kodlar.** Yeni `includes/api/resources/key_codes.php` ve
yeni izin grubu `key_codes` (`key_codes:read`, `key_codes:write`); anahtar kod
ekranlarının kapısı `validate_ecommerce_access()` olduğu için teklifler ve
hediye kartlarıyla aynı koşulla (mağaza açık, rol < 3 ya da mağaza yetkisi)
verilir. Ayrı izin, çünkü anahtar kod ödemede teklifin kendi kodu gibi indirim
alır: kod üretmek indirim dağıtmaktır, okumak kullanılabilir kodları tutmaktır;
`offers:write` bugün yalnız teklifi açıp kapatıyor, tarihini değiştiriyor ya da
siliyor.

- `GET /key-codes` (`offer_code`, `status`, `single_use`, `q` kod/not; imleçli,
  yeniden eskiye) ve `GET /key-codes/{id}`: kod, `offer_code`, `status`,
  `enabled`, `single_use`, `report`, `expires_on`, `notes`, değiştiren.
  `status` bugün ödemede ne olacağını söyler: `disabled` (kapalı; tek
  kullanımlık kod onu kullanan siparişte `submit_order.php` tarafından
  kapatılır), `expired`, `offer_inactive` (bu teklif koduyla bugün çalışan
  teklif yok; birden çok teklif aynı kodu paylaşabildiği için `EXISTS`/alt
  sorgu, ekrandaki `LEFT JOIN` gibi satırı çoğaltmaz) ya da `active`. Süzgeç
  aynı dört durumu aynı öncelikle SQL'e çevirir
  (`api_key_code_status_clause()`).
- `POST /key-codes`: var olan bir teklif kodu için tek bir kendi kodu (`code`)
  ya da `quantity` kadar (en çok 250) yeni on karakterlik kod, ekrandaki gibi
  `0-9A-Z` (`random_int`). `enabled` varsayılan açık, `single_use` varsayılan
  kapalı (anahtar kod ekranı gibi), `report` varsayılan `key_code`. Cevap tek
  kodda bile `data` listesidir, 201.
- `POST /key-codes/{id}` (PATCH da): yalnız gönderilen alan yazılır;
  `enabled=true` kullanılmış tek kullanımlık kodu yeniden açar.
- `DELETE /key-codes/{id}` ve `POST /key-codes/{id}/delete`: ekrandaki gibi
  siler; kodu kullanmış siparişler kaydettiklerini korur. Toplu silme
  (ekrandaki "Tüm anahtar kodları sil") API'de yok.

Ekrandan farklı bir denetim: **bir teklifin kendi koduyla aynı kod reddedilir**
(422, `code`). `get_offer_code_for_special_offer_code()` yazılan kodu önce
tekliflerde arar, anahtar kodlara ancak teklif yoksa bakar; teklif koduyla aynı
anahtar kod hiç okunmaz, `single_use` ve `report` işlemez. Üretilen kodlar da
hem anahtar kodlarda hem tekliflerde boş olanlardan seçilir. Güncellemede kodun
kendisi geri gönderilirse denetlenmez (kural öncesi kayıt). Kod
karşılaştırması veritabanınınki gibi büyük/küçük harf duyarsızdır.

**Değişiklik: ürün nitelikleri.** Yeni `includes/api/resources/product_attributes.php`;
izin `products:read` / `products:write` (yeni izin yok): nitelikler katalogdur
ve `products:write` ürünün hangi seçeneği taşıdığını zaten yazıyor; klasörlerin
ve menülerin `pages:*` altına girmesiyle aynı karar.

- `GET /product-attributes` (`q` ad/etiket; imleçli, id sırasıyla) ve
  `GET /product-attributes/{id}`: ad (benzersiz kod), etiket, seçenekler
  sırasıyla (`no_value` "istemiyorum" seçeneği), `products` ve
  `product_groups` kullanım sayıları (Geri Dönüşüm Kutusu dahil). Seçenekler
  ve sayılar sayfa başına üç sorguda.
- `POST /product-attributes`: ad, etiket, en az bir seçenek; alınmış ad 409
  `name_taken` (ürün grubu gibi, hangi niteliğin aldığını söyler).
- `POST /product-attributes/{id}` (PATCH da): yalnız gönderilen yazılır.
  `options` kimliği verilen seçeneğin adını ya da `no_value`'sunu değiştirir,
  kimliksiz olanı sona ekler; **gönderilmeyen seçenek kalır**. Ekran seçenek
  listesini bütünüyle değiştirir ve eksik olanı siler; API'de bir seçenek ancak
  kimliği `remove_option_ids`'te ise gider, ekrandaki gibi ürünlerdeki bağıyla
  (`products_attributes_xref`). Ek olarak, kaldırılan seçenek bir ürün grubunun
  ön seçimiyse (`product_groups_attributes_xref.default_option_id`) 0'a çekilir;
  ekran bunu yapmıyordu, grup var olmayan bir seçeneği gösteriyordu. Nitelikte
  en az bir seçenek kalır; iki seçenek aynı okunamaz (büyük/küçük harf
  duyarsız, `product_attribute_action.php`'nin kuralı). Sıra değiştirme ekranda
  kalır.
- `DELETE /product-attributes/{id}` ve `POST …/delete`: nitelik, seçenekleri,
  ürün ve ürün grubu bağları (ekrandaki dört DELETE). Bir ürün ya da grupta
  kullanılıyorsa `force=true` olmadan 409 `attribute_in_use`; tekliflerin
  `offer_active` kuralıyla aynı gerekçe: ekranda silen kişi mağazaya bakıyor,
  uygulama bakmıyor.

`POST /products` `attributes` açıklaması yeni uca yönlendiriyor. Yazmalar
`dry_run` destekler, günlüğe uygulama ve anahtarla yazılır. OpenAPI'de
`KeyCode`, `ProductAttribute`, `ProductAttributeOption`; MCP araçları
rotalardan kendiliğinden gelir (`key_codes_*`, `product_attributes_*`). Yeni
`key_codes` izni mevcut uygulamalara verilmez, operatör Uygulama Erişimi'nde
işaretler. Şema değişikliği yok.

Testler: `tests/api_key_codes_test.php` (durum ve önceliği, süzgecin dört
durumu, son gün, üretilen kodun biçimi, rotalar ve kapsamları, yöntem+yol
çakışması yok, iznin teklif/hediye kartından türememesi ve mağaza kapalıyken
verilmemesi), `tests/api_product_attributes_test.php` (seçeneklerin denetimi
ve düzeltilmesi, reddedilen biçimler, seçenek değişikliği planı: ekleme,
yeniden adlandırma, yalnız adı verilen kaldırılır, başka niteliğin seçeneği,
hem değişen hem kaldırılan, seçeneksiz kalma, aynı okunan seçenek; seçenek
nesnesi ve şeması; rotalar).

**Doğrulandı (sandbox, MariaDB, varsayılan site dökümü, HTTP üzerinden
`integration.php`).** Anahtar kodlar: liste, imleç, okunamayan imleç 400,
`offer_code`/`single_use`/`q` süzgeçleri, `status=active/disabled/expired`;
`dry_run` hiçbir şey yazmadı; üç kod üretildi (tek kullanımlık, son gün, not),
kendi kodu; aynı kod küçük harfle, teklif kodu, `quantity` ile kendi kodu,
olmayan teklif, eksik `offer_code`, tanınmayan alan, `quantity=251` 422;
güncelleme (kapatma, son gün, `report`, not, son günü silme, kodu yeniden
gönderme, alınmış koda ve boş koda değiştirme, boş istek, PATCH), 404, POST
ile silme; günlük satırları. Ürün nitelikleri: oluşturma, `dry_run`, alınmış ad
409, seçeneksiz, `id`li, aynı okunan, boş etiketli ve dize seçenekler 422;
seçenek ekleme, yeniden adlandırma (`no_value` korundu), başka niteliğin
seçeneği, hem değişen hem kaldırılan, var olanla aynı okunan, hepsini
kaldırma 422, alınmış ad 409; ürünün taşıdığı seçenek kaldırılınca ürün bağı
silindi ve grubun ön seçimi 0'a çekildi; kullanılan nitelik `force`suz 409,
`force` ile silindi, bağlar gitti; `q`, imleç, PATCH, 404; OpenAPI'de altı yol
ve üç nesne. MCP araç tanımları CLI'da üretildi; MCP üzerinden çağrı ve panel
ekranları (Uygulama Erişimi izin listesi) ayrıca denenmedi.

---

## 2026.4.10 — Çalışma Alanı: müşteri onayı, ekip dışından e-postayla onay (10.84) (2026-10-13)

**Neden.** Çalışma Alanı araştırma notunun P1 1.5 maddesi: onay
istekleri yalnız ekipten kişilere soruluyordu; ajans/serbest çalışan
senaryosunda asıl onaylayan müşteridir ve onay e-posta yazışmasında
kayboluyordu. Hedef: görev ya da plan maddesi için müşteriye hesap açtırmadan,
tek kullanımlık, süreli, imzalı bir bağlantıyla sormak; yanıtın kim tarafından,
hangi bağlantıyla verildiği denetim izine yazılsın.

**Ne değişti.** Yeni `includes/workspace/approval_guests.php` ve herkese açık
`workspace_approval.php`.
- `ws_approval_create()` `guests` (ad + e-posta, en çok `WS_APPROVAL_MAX_GUESTS`
  = 5) alır; müşteri varken kanaldan onaylayan seçmek zorunlu değildir.
  Yalnız personel (rol ≤ 2, misafir olmayan) müşteri sorabilir
  (`ws_approval_can_ask_guests()`). Her kişiye `pg_short_link_new_token()`
  ile 43 karakterlik bir jeton üretilir; tabloda yalnız SHA-256'sı ve ilk 6
  karakteri (`token_hint`, site günlüğünde bağlantıyı ayırt etmek için) durur.
  Bağlantı isteğin son tarihine kadar, son tarih yoksa 30 gün geçerli; en
  fazla 90 gün (`ws_approval_guest_expiry()`).
- E-posta düz metin, `email(... 'queue' => true)` ile kuyruğa gider (dev'de
  `mail_outbox`), Reply-To isteyen kişinin adresi. İçerikteki ilk `**başlık**`
  satırı çıkarılır, etiketler okunur metne çevrilir.
- `workspace_approval.php` oturum açmaz (`PG_NO_SESSION`); `noindex`,
  `no-referrer`, `no-store`, `X-Frame-Options: DENY`. GET hiçbir şey
  değiştirmez; POST (`approve` / `reject` + not) koşullu `UPDATE ... WHERE
  decision = 'pending' AND token_hash = ... AND expires_at > now` ile tek
  yanıt alır, 303 ile adrese döner. Ret not ister; not tek satıra, 500
  karaktere indirilir, `<@...>`/`<#...>` etiketleri ve HTML işaretleri
  ayıklanır. IP (`waf_client_ip()`), tarayıcı ve bağlantı ipucu yanıtla
  saklanır. Tabloda olmayan jeton `ws_guest_offence()` ile güvenlik duvarına
  sayılır (misafir odası bağlantılarıyla aynı kural).
- Yanıt `ws_approval_evaluate()`'e girer: kural "herhangi biri"/"hepsi"
  müşterileri de sayar; sonuç mesajı müşteriyi "Ad (müşteri)" diye yazar.
  İstek açık kalırsa isteyene yeni `approval_answered` bildirimi gider;
  kapanınca mevcut `approval_decided`.
- Görev: `ws_task_approval_create()` isteği görevin kanalında
  `record_type = 'task'` ile açar; `ws_task_approvals_detail()` görev
  çekmecesindeki "Müşteri onayı" bölümünü (`assets/js/workspace_task_approval.js`)
  besler. İstek kapanınca sonuç cümlesi `ws_task_note_add(..., $ask = false)`
  ile göreve not olarak yazılır. Plan maddesi için kanaldaki formda `#plan`
  etiketi yeter: `ws_approval_record_of()` önce site kaydını, yoksa ilk
  `#task`/`#plan` etiketini kayıt sayar.
- Kart: müşteri satırları "müşteri" rozetiyle; yöneticiye e-posta ve
  denetim satırı (gönderim/geçerlilik ya da "… bağlantısıyla … adresinden
  yanıtlandı") görünür, "Yeni bağlantı gönder" (`ws_approval_guest_resend`)
  hash'i değiştirir, eski bağlantı açılmaz olur.
- Dış API: `approvals.create` `guests` alır, `approvers` artık zorunlu
  değil; yanıtta `guests` dizisi (`ws_api_approval_guest_present()`, e-posta
  yalnız personele); `workspace.approval.*` webhook'ları `guests` taşır.
- Panel eylemleri `ws_task_approval_create` ve `ws_approval_guest_resend`
  `includes/panel/actions.php` tablosuna eklendi.

Şema 10.84: `ws_approval_guests` (approval_id, name, email, token_hash
`CHAR(64) ascii_bin` UNIQUE, token_hint, expires_at, sent_at, sent_by,
decision, note, decided_at, decided_ip, decided_agent, decided_hint);
`install/index.php` tablo listesine eklendi.

**Güvenlik.** Müşterinin notu göreve ve karta yalnız metin olarak gider:
`ws_task_note_add()` `$ask = false` ile asistanı çağırmaz (notta "@claude"
yazsa da). Sayfa misafir odasının güvenli işleyicisini (`ws_guest_render()`)
kullanır.

**Bilerek yapılmayan.** Müşteriye hatırlatma e-postası yok (hatırlatma
yalnız ekip içi). Müşteri isteğe yanıtını değiştiremez, sayfada yazışamaz.
Jeton adres satırında durduğu için sunucu erişim günlüğüne düşer; bağlantı
kişiye özel, tek yanıtlık ve süreli olduğu için kabul edildi.

**Doğrulama.** `tests/approval_guests_test.php` (18 test). Sandbox'ta 10.84
iki kez koşturuldu, ikincisi "zaten var". Görev çekmecesinden ve kanal
formundan tarayıcıyla istek gönderildi; `mail_outbox` satırı, sayfanın
açık / yanıtlanmış / süresi dolmuş / kapanmış / geçersiz (404) halleri,
notsuz ret uyarısı, ikinci yanıtın reddi, iki müşterili "hepsi" kuralı,
yeni bağlantı (eskisi geçersiz), bildirim ve görev notu denendi.

---

## 2026.4.10 — MCP sunucularında kişi başı bağlantı ve yazan araçlar için onay kartı (2026-10-13)

**Neden.** `docs/_plan_tersine_oauth.md` §4.6 (senaryo 5), F2'nin kalanı.
#316 OAuth ile bağlanan MCP sunucusuna yalnız sitenin bağlantısını
getirmişti: herkes aynı hesapla okuyordu, yazan araçlar hiç açılamıyordu
(kimin adına yazdığı belli olmayan bir çağrı açılmamalı). Bu parça herkesin
kendi hesabını bağlamasını ve yazan araçların, soranın onayıyla ve onun
hesabıyla çalışmasını getirir. Şema yok.

**Ne değişti.**

- `includes/connect/purposes/mcp.php`: `owner => 'any'` (site ya da kişi).
  `connect.php` POST'u çalışma anında kurulan sağlayıcıyı hâlâ başlatmaz;
  kişi bağlantısı Çalışma Alanı'ndan, `ws_mcp_person_connect_start()` →
  `pg_connect_start(..., 'person', $viewer, ...)` ile başlar (sunucu OAuth ve
  açık, uygulama kayıtlı olmalı).
- `includes/workspace/mcp.php`: `ws_mcp_connection($server, $timeout,
  $user_id, $person_only)`: kişinin etkin hesabı önce, yoksa sitenin
  (`$person_only` ise hiçbiri). Kişi bağlantısı oturum kimliğini saklamaz
  (`ws_mcp_remember()` atlar, el sıkışma her seferinde). 401'de
  `ws_mcp_reauthorize()` kullanılan hesabı (`$conn['account_id']`) yeniler;
  hata metni kişi için ayrı ("@x ile kendi bağlantınız sona erdi").
  `ws_mcp_person_account()`, `ws_mcp_oauth_account()`,
  `ws_mcp_account_headers()`, `ws_mcp_connect_link()` (kanaldan bağlanma
  adresi `workspace.php?channel=X&mcp_connect=Y`), `ws_mcp_person_disconnect()`
  (bekleyen kartların mesajlarını tazeler).
- Araç kuralı `approve` (yalnız OAuth sunucusunda, `ws_mcp_can_approve()`):
  yazan araç yalnız böyle açılır, okuyup okumadığını söylemeyen araçta
  yönetici "Yalnızca okur" (`auto_declared`) ile "Her çağrıda onay iste"
  arasında seçer (`ws_mcp_tool_rule_for()`). Kartta "Önce sorar" rozeti,
  "Hesabımı bağla / yeniden bağla / bağlantısını kes" satırı ve kendi
  hesabıyla bağlı kişi sayısı. `mcp_connect_me` / `mcp_disconnect_me`
  yönetici denetiminden önce koşar (her ayar görüntüleyicisi kendi hesabını
  yönetir).
- `includes/workspace/mcp_ask.php`: onay isteyen araç Pinegrap AI'a
  "[needs approval]" işaretiyle verilir. Çağrısı çalışmaz:
  `ws_mcp_ask_propose()` `ws_mcp_calls`'a `pending` satır yazar (yalnız
  kanalda, yanıt başına en çok `WS_MCP_ASK_MAX_PROPOSED` = 3), yanıt
  teslim edilince `ws_mcp_calls_propose()` her biri için `ws_ai_changes`
  satırı (`record_type 'mcp_call'`, `action 'run'`, `record_id` çağrı,
  alanlar argümanlar) yazar. İstek başarısız olursa
  `ws_mcp_ask_drop_pending()` bekleyenleri siler. Okuyan araçlar soranın
  bağlantısıyla (`requested_by`) çalışır. Bağlı değil mesajı soranın kendi
  hesabını bağlayacağı bağlantıyı içerir.
- `includes/workspace/changes.php`: `mcp_call` `ws_change_types()`'ta yok
  (Claude ve API yazamaz). Kart girişi `ws_mcp_change_entry()`: yalnız soran
  uygulayabilir; hesabı bağlı değilse kart "Bağla" düğmesi gösterir
  (`blocked`). `ws_change_apply()` → `ws_mcp_call_run()`: yalnız soranın
  kendi hesabıyla (sitenin bağlantısı yazan çağrıda hiç kullanılmaz);
  başlık yoksa ya da hizmet 401 verirse öneri `pending`'e döner; sonuç
  `ws_mcp_calls`'a (done/failed, `approved_by`, süre) ve kanala kilitli
  karar mesajı olarak yazılır. Araç çıktısı mesajda kimseyi anmaz (`<@`,
  `<#` etkisizleştirilir) ve `WS_MCP_DECISION_RESULT_LIMIT` (600) ile
  kısalır. Reddedilen kartın çağrısı `dismissed` olur.
- API: `GET /workspace/changes?type=mcp_call` onay kartlarını listeler
  (kayıt adı "@takma ad araç", alanlar argümanlar). Uygula/reddet API'den
  `ws_change_apply()` kuralıyla gelir: yalnız soran.
- Ekran: `workspace.php?mcp_connect=<id>` sorup bağlanmaya gönderir, dönüşte
  (`mcp_connected`) bildirim; kartta "Bağla" ve "Hesabımın bağlantısını
  kes" (`ws_mcp_connect`, `ws_mcp_disconnect_me` eylemleri; `ws_mcp_connect`
  oturumu kapatmaz, bekleyen bağlantı oturumda tutulur).

**Denendi.** Birim testleri (`tests/workspace_mcp_test.php`: onay yalnız
OAuth'ta, kural matrisi, argüman kısaltma, kişi hata metni). Sandbox'ta sahte
bir MCP + OAuth sunucusuna karşı uçtan uca (60 kontrol): kişi bağlantısı,
kişinin jetonuyla araç listesi, başka kişiye bağlanma bağlantısı, okuyan
araç çalışır / yazan bekler / en çok 3, kartı yalnız soran uygular, kilitli
ve kimseyi anmayan karar mesajı, iki kez uygulanmaz, araç hatasında
`failed`, bağlantı kesilince kart "Bağla" der ve bekler, site bağlantısı
yazan çağrıda kullanılmaz, 401'de öneri geri döner, reddetme, başarısız
istekte bekleyenler silinir, API listesi, ayar kartı.

**Kapsam dışı (devir notunda).** F3 (göreve takma ad: `ws_tasks.delegate`
= `mcp:<server_id>`; görevde kanal olmadığı için onay kartının yeri
ayrıca kurulmalı, `ws_mcp_ask_propose()` şimdilik kanal dışında reddeder).

---

## 2026.4.10 — Dış API: ortaklık komisyonları (salt okuma) ve `?dry_run=true` (2026-10-13)

**Sorun.** İki ayrı eksik (`api-genisletme-devir.md` 5. ve 7. madde).
Ortaklık programının komisyonları API'de yoktu: bir ödeme ya da muhasebe
aracı "hangi ortağa ne kadar ödenecek" sorusunu soramıyor, Komisyonlar
ekranını elle okumak gerekiyordu. İkincisi, yazma provası yalnız
`X-Dry-Run` başlığıyla isteniyordu; sorgu dizesi denetlenmediği için
başlık gönderemeyen bir araç (webhook test aracı, kodsuz otomasyon,
tarayıcıdan denenen adres) `?dry_run=true` eklediğinde parametre sessizce
yok sayılıyor ve yazma **gerçekten yapılıyordu**.

**Değişiklik 1: komisyonlar.** Yeni `includes/api/resources/commissions.php`
ve yeni izin grubu `commissions` (yalnız `commissions:read`, yazma yok).
Komisyonlar ekranının kapısı `validate_ecommerce_access()` olduğu için izin
mağaza açıkken rol < 3 ya da mağaza yetkisi (`manage_ecommerce`) taşıyan
sahibe açılır (`gift_cards:*` ile aynı koşul). Ortaklık programının açık
olması aranmaz: ekran da aramıyor ve program kapatılsa da geçmiş
komisyonlar ödenmeyi bekleyebilir.

- `GET /commissions` (`status`, `affiliate_code`, `reference_code`,
  `order_id`, `created_since`, `created_before`, `updated_since`;
  imleçli): son değişme anı + id sırasıyla, böylece `updated_since` ile
  eşitleme durumu değişen komisyonu yeniden yakalar (ürün listesinin
  deseni). Bütün yazarlar (sipariş, yinelenen profil, düzenleme ekranı)
  `last_modified_timestamp`'i günceller.
- `GET /commissions/{id}`: aynı alanlar, tek komisyon.
- Alanlar: referans kodu, durum (`pending`/`payable`/`ineligible`/`paid`),
  tutar (kuruş), ortak kodu, ortağın adı ve kişi kimliği
  (`affiliate_contact_id`, `GET /customers/{id}` için), sipariş kimliği ve
  numarası, yinelenen komisyonda profil kimliği, dönem (`monthly`/`yearly`),
  ürün adı ve açıklaması, oluşturan/değiştiren.
- Ortak, liste başına tek sorguyla koddan bulunur
  (`api_commission_affiliates()`); ekranın `LEFT JOIN contacts`'ı aynı
  kodu taşıyan iki kişi olduğunda satırı çiftlediği için birleştirme
  yerine ayrı sorgu, en eski kişi ortak sayılır. Kolon büyük-küçük harf
  ayırmadan karşılaştırdığı için eşleme de ayırmaz.
- Yinelenen komisyonların vadesi gelen taksitleri ekran ya da Hesabım
  açılınca, günde en çok bir kez yazılıyor (`update_recurring_commissions()`).
  API de okumadan önce aynı fonksiyonu çağırır; yoksa API aynı anda
  ekranın göstereceğinden az komisyon bildirebilirdi.

**Değişiklik 2: `?dry_run=true`.** `api_dry_run_requested()` artık başlığa
ek olarak sorgu dizesindeki `dry_run`'ı okur, aynı değerlerle (`true/1/yes/on`,
`false/0/no/off`); ikisinden biri prova isterse prova yapılır. Geçersiz
değer 400 `invalid_dry_run` (`field: dry_run`), dizi de geçersiz. Değer
`$_GET`'ten değil `api_request_input()`'tan okunur ve gövde anahtarıysa
sayılmaz: MCP aracı süreç içinde çalışırken kendi girdisini kurar ve
`dry_run`'ı başlık olarak verir, `mcp.php` isteğinin sorgu dizesi araca
geçmez (sandbox'ta denendi). Gövdede `dry_run` alanı eskisi gibi 422
(tanınmayan alan). Provası olmayan uca `?dry_run=true` 400
`dry_run_unsupported` alır; mesaj iki yolu da anıyor. OpenAPI provalı her
yazma ucunda `X-Dry-Run` başlığının yanına `dry_run` sorgu parametresini
yazar. Bilinmeyen sorgu parametresine 422 vermek seçilmedi: önbellek
kırıcı, analiz etiketi gibi uçla ilgisiz değerleri reddetmek çağıranları
boşuna kırardı (`api_request_body_keys()` notu).

Yeni izin mevcut uygulamalara verilmez; operatör Uygulama Erişimi'nde
"Komisyonlar"ı işaretler. MCP araçları (`commissions_list`,
`commissions_get`) rotalardan kendiliğinden gelir. Şema değişikliği yok.

Testler: `tests/api_commissions_test.php` (sipariş komisyonu ve yinelenen
komisyon alanları, ortak/sipariş yokken `null`, şemaya uyma, kod
eşlemesinin büyük-küçük harfi yok sayması, süzgeç koşulları, rotalar ve
kapsamları, durum listesinin ekranla aynı olması, kapsamın salt okuma
olması, mağaza yetkisi kapısı ve mevcut anahtarlara verilmemesi).

**Doğrulandı (sandbox, MariaDB, gerçek HTTP).** `GET /commissions`
imleçle iki sayfa, `status`/`affiliate_code` süzgeci, okunamayan imleç
400, bilinmeyen `status` 422, `GET /commissions/{id}` ve 404; izni olmayan
anahtar 403 `insufficient_scope`. Yinelenen profil (iki ay önce başlayan,
aylık) okumada üç taksit yazdı; aynı kodu küçük harfle taşıyan ikinci
kişi ortak sayılmadı. `?dry_run=true`, `?dry_run=1`, `?dry_run=on` hediye
kartı oluşturma/güncelleme/silmede hiçbir şey yazmadı; başlık `false` +
sorgu `true` prova yaptı; `?dry_run=maybe` ve `?dry_run[]=1` 400;
`?dry_run=false` gerçekten yazdı; gövdede `dry_run` 422; provasız uçta
400 `dry_run_unsupported`. OpenAPI'de `/commissions` yolları, `Commission`
nesnesi ve `dry_run` sorgu parametresi; MCP `tools/list`'te iki komisyon
aracı (salt okuma ipucuyla), `commissions_get` çağrısı ve `dry_run`
argümanıyla prova çalıştı, `mcp.php?dry_run=true` araca geçmedi.

---

## 2026.4.10 — Banka ekstresi Excel'den, vade farkı, nakit tahmini (ERP Faz 9, 10.61) (2026-10-13)

**Neden.** `docs/_plan_erp_ozellikler.md` Faz 9: bankalar ekstreyi çoğunlukla
Excel olarak veriyor, CSV'ye çevirmek operatöre kalıyordu; geç ödeyen müşteriye
vade farkı elle hesaplanıyordu; nakit akışı ekranı yalnız olanı gösteriyor,
önümüzdeki günleri göstermiyordu.

**9a. Ekstre Excel'den.** `erp_bank_statement_upload()` dosyayı artık
`erp_bank_read_file()` ile okur: `.xlsx`/`.xlsm` ilk sayfası ürün
içe aktarıcısının zip yardımcılarıyla satır satır (`erp_bank_read_spreadsheet()`,
kütüphane yok), eski `.xls` PHPExcel ile **biçimleriyle** okunur; ürün
içe aktarıcısı `setReadDataOnly(true)` kullandığı için tarih hücresi orada
gün sayısı olarak gelir, burada gelmez. Sayı hücresi `erp_bank_sheet_value()`
ile CSV'nin taşıyacağı metne döner: tarih biçimli hücre `Y-m-d` (UTC ile, batı
saat diliminde gün kaymasın), tam sayı olduğu gibi, gerisi iki ondalık ve
noktayla (1234.5599999999999 → 1234.56). Bankanın başlık bloğu
(`erp_bank_rows_from_heading()`): ilk 60 satırda bir hücresi tarih, başka bir
hücresi tutar (rakam, en çok üç harf, sıfır olmayan) olan ilk satır bulunur,
üstündeki başlık satırı kalır, gerisi atılır; bulunamazsa satırlar olduğu gibi
kalır. CSV de aynı süzgeçten geçer. `.ods`, `.numbers`, `.pdf` adla reddedilir
("Excel veya CSV olarak kaydedip yeniden yükleyin"): PHPExcel'in OpenDocument
okuyucusu PHP 8'de `implode()` argüman sırasından ölüyor (bkz. bilinen sorun).
Sütun seçimi, satır özeti ve eşleştirme değişmedi.

**9b. Vade farkı.** Yeni `includes/erp/late_fee.php`. Ayar ERP Ayarları'nda
yeni kart: `config.erp_late_fee_rate` (aylık %, 0 = kapalı, en çok 50) ve
`config.erp_late_fee_tax_rate` (faturadaki KDV oranı; boşsa carinin satış
bölgesi oranı, `erp_account_zone_rate()`). Hesap `erp_late_fee_for_invoice()`:
vadeden (ya da son faturalanan günden, hangisi sonraysa) itibaren gün gün,
tahsilat (`erp_settlements.amount_base`) ve iadeler (iade faturası
`grand_total_base`) kendi günlerinde borcu düşürür; ay 30 gün, yuvarlama bir
kez sonda. Geç ödenmiş fatura da günlerini kazanır. `erp_late_fee_account()`
carinin satış faturalarını (kesilmiş, kısmen ödenmiş, ödenmiş; vadesi as-of'tan
önce; vade farkı faturası olmayan) dönemden önce kapananları eleyerek toplar.
Dönem varsayılanı mutabakat mektubundaki gibi as-of yılının 1 Ocak'ı.
Cari kartının bakiye kartı ve mutabakat mektubu ekranı (`erp_late_fee_panel()`,
yalnız ekranda, mektupta değil) tutarı söyler; "Vade farkı faturası" düğmesi
(`add_erp_late_fee_invoice.php`, salt okunur kullanıcıda yok) tek satırlı satış
faturası **taslağı** yazar: açıklama dönem, notlar fatura fatura gün ve tutar,
seri satış serisi, para birimi ana para birimi. Açık taslak varken düğme
yerine taslağa bağlantı çıkar, ikinci taslak yazılmaz.

Şema 10.61: `config.erp_late_fee_rate DECIMAL(6,3)`, `erp_late_fee_tax_rate
DECIMAL(6,3) NULL` (config VARCHAR almaz) ve `erp_invoices.late_fee_through
DATE NULL` + `idx_late_fee (account_id, late_fee_through)`. Taslak dönemin
son gününü `late_fee_through`'a yazar (`erp_invoice_draft_save()` yalnız
çağıran anahtarı verirse yazar; taslağı düzenlemek onu silmez). Fatura
kesilince sayılır: sonraki vade farkı ertesi günden başlar, aynı gün iki kez
faturalanmaz; silinen taslak hiçbir şey saymaz.

**9c. Nakit tahmini.** Yeni `includes/erp/cash_forecast.php`. Nakit akışı
ekranında "Gerçekleşen / Beklenen" sekmesi (`erp_cashflow.php?view=forecast`,
`horizon` 30/60/90, varsayılan 90). Tablo: vadesi geçmiş + 30'ar günlük
sütunlar; satırlar açık satış ve alış faturaları (`erp_aging_invoices()`,
açık tutar etkin vadede), ödenmemiş giderler, tekrarlayan fatura ve giderler
(sonraki tarihten adım adım, `erp_cash_forecast_repeats()`; geçmiş tarih bugün
sayılır), portföydeki/tahsildeki alınan ve verilen çek-senetler vadelerinde.
Altında para girişi, çıkışı, net ve beklenen bakiye (bugünkü kasa + birikimli
net). Bugünkü nakit: ana para birimindeki kasalar, çek-senet portföy kasaları
hariç (çek hem portföyde hem satırda sayılmasın). Kurusu bilinmeyen yabancı
paralı tekrarlayan belge ve yabancı para kasaları notta sayılır, toplama
girmez. Hesaplama saf `erp_cash_forecast_build()`; liste en çok 300 hareket.

**Bilerek yapılmayan.** API/MCP uçları (vade farkı ve tahmin) yok; ekranlarla
sınırlı tutuldu. Vade farkı otomatik kesilmez, e-posta atmaz; yalnız taslak.
Tedarikçiye vade farkı yok. Tahmin geçmiş tahsilat alışkanlığından gecikme
tahmini yapmaz; vadeyi olduğu gibi alır.

**Bilinen sorun (bu PR dışında).** Ürün içe aktarımı da `.ods` dosyasında
PHP 8'de ölümcül hata verir: `includes/phpexcel/PHPExcel/Reader/OOCalc.php:538`
`implode($dataArray, "\n")`.

**Doğrulama.** Birim testleri: `tests/erp_late_fee_test.php`,
`tests/erp_cash_forecast_test.php`, `tests/erp_bank_import_test.php` (elle
yazılmış .xlsx dahil); `innodb_test` config sütun sayısı 431. Sandbox'ta 10.61
iki kez koşturuldu, ikincisinde dördü de "zaten var". Ayar kaydı (3,5 / 20),
cari kartında "₺950,83" (74 gün × ₺10.000 + 15 gün × ₺5.000, %3,5) ve taslak
(₺950,83 + %20 KDV = ₺1.141,00), taslağın kesilmesi (`late_fee_through`
korundu), kesilen faturadan sonra yeni dönemin ertesi günden başlaması
(₺151,67), tahmin tablosu 90 ve 60 gün, örnek .xlsx ve .xls ekstrenin
yüklenip sütun eşlenmesi gerçek POST'larla denendi.

---

## 2026.4.10 — Google ile Giriş signin.php akışında; listelerde sağlayıcı rozetleri (2026-10-13, şema 10.75)

**Sorun.** #306 Microsoft ve Apple ile girişi `signin.php` /
`includes/fn/signin.php` akışına ve `user_identities` tablosuna kurdu; Google
eski yolunda kaldı (`google_auth.php`'de kendi kopyası, kimlik
`user.user_google_id`'de). Aynı kurallar iki yerde yazılıydı, her ekran
Google'ı ayrı, ötekileri ayrı okuyordu. Kullanıcılar listesi ve Oturumlar
ekranı yalnız Google'ı gösteriyordu: Microsoft ya da Apple ile bağlı hesap,
hatta tek giriş yolu Apple olan hesap orada "bağlı değil" görünüyordu.

**Değişiklik.**

- `providers/google.php`'ye `signin` bloğu (sıra 10, `openid email
  profile`, PKCE). Blokta iki yeni anahtar: `return` (dönüş kök betiği;
  Google'da `google_auth.php`) ve `authorize_params` (girişe özel istek
  parametreleri; Google'da `access_type=online`, `prompt=select_account` —
  bağlı hesap tarafındaki `offline` + `consent` girişte istenmez).
  `pg_signin_return_file()`, `pg_signin_authorize_params()`,
  `pg_signin_redirect_uri($key)`.
- Google'ın uygulaması Güvenlik ayarlarındaki istemci (`config.oauth_google_*`,
  `pg_signin_google_app()`; `pg_connect_app()`'in Google yedeği de artık
  bunu çağırıyor), anahtarı `config.oauth_google_enabled` (`pg_signin_switched_on()`
  Google'ı ekler). Bağlı hesaplar'da ayrı bir Google uygulaması kayıtlıysa
  bile giriş Güvenlik'teki istemciyle yapılır: o istemcide `google_auth.php`
  kayıtlı, ötekinde olmayabilir.
- `google_auth.php` yalnız `PG_SIGNIN_PROVIDER = 'google'` tanımlayıp
  `signin.php`'yi çalıştırıyor. Eski bağlantılar (`google_auth.php?create=0`,
  özel düzenler, `__google_signin_url`) çalışmaya devam ediyor.
  `pg_google_signin_url()` artık `signin.php?provider=google` döndürüyor.
- **Geçiş kararı: şema adımı.** 10.75 (`upgrade_2026_4_10_signin_google()`)
  `user.user_google_id` değerlerini `user_identities`'e (`provider =
  'google'`, `subject` = Google `sub`, `email` = hesabın adresi) taşıyor ve
  taşınan her satırın eski kolonunu NULL yapıyor; ikinci koşuda taşınacak bir
  şey kalmıyor ("yeniden koş" güvenli). Kolon silinmedi. Adım koşmadan önce
  (dev'de 10.74 elle yeniden koşturulmuşsa) giriş yine çalışsın diye
  `pg_signin_identity_user('google', …)` kayıt bulamazsa eski kolona bakıyor
  ve bulduğu bağı o anda taşıyor (`pg_signin_google_legacy_user()`); e-posta
  eşleştirmesi eski kolonda başka bir `sub` taşıyan hesabı devralmıyor;
  Google bağlantısı kesilirken eski kolon da temizleniyor.
  Seçilmeyen yol (eski kolonu okumaya devam): her ekran iki kaynağı
  birleştirmek zorunda kalırdı, listeler için tek sorgu olmazdı.
- Ekranlar tek kaynaktan: Hesabım bölümü (`pg_account_security_section()`),
  `account_security.php` (`unlink_google` artık genel `unlink_<sağlayıcı>`
  dalında), hesap güvenliği widget'ı (`has_google`), `pg_google_account_notice()`,
  kullanıcı düzenleme ekranı (Google'a özel rozet, satır ve panel kalktı;
  hiç bağı olmayan hesapta "Yalnız parolayla giriyor" satırı;
  `pg_unlink_google` formu `pg_unlink_identity=google` olarak hâlâ kabul),
  üyelik formu (bekleyen Google kaydı da `pg_signin_identity_add()` ile).
- Listeler: `pg_signin_identities_of($user_ids)` tek sorguda hesap başına
  sağlayıcıları düğme sırasıyla verir (`pg_signin_group_identities()`, saf),
  `pg_signin_identity_badges()` rozetleri çizer: sağlayıcı başına bir rozet,
  parolasız hesapta tek sağlayıcı varsa "Yalnız X" uyarısı.
  `view_users.php` adın yanında, `view_sessions.php` oturum satırında ve Bağlı
  hesaplar tablosunda (artık `user_identities`'teki her hesap).
  `pg_connected_account_label()` sağlayıcı listesi alıyor.
- Ayarlar › Güvenlik: üç kart tek kart (`pgset-signin`, "Google, Microsoft ve
  Apple ile Giriş"), her sağlayıcının anahtarı ve alanları aynı; kayıt
  (`security.save.php`) değişmedi. Ayar arama kayıtlarında
  `pgset-signin-microsoft` ve `pgset-signin-apple` bölümleri kalktı,
  anahtar kelimeleri `pgset-signin`'e eklendi.
- Görsel editör: `siteFlags.signin` (herhangi bir sağlayıcı açık) giriş
  düğmeleri bölümünü, `siteFlags.google` yalnız Google bağlantı
  token'larını soluklaştırıyor; önceden yalnız Microsoft açıkken de bölüm
  "kapalı" görünüyordu.

**Davranış farkları (Google).** Google girişi PKCE gönderiyor ve id_token'da
`exp` de denetleniyor. Daha önce bağlı bir Google hesabı, Google adresini
doğrulanmamış bildirse de girebiliyor (kimlik `sub` ile tanınıyor); bilinmeyen
hesapta doğrulanmamış adres eskisi gibi reddediliyor. Uygulamanın gizli
anahtarı boşsa düğme artık hiç çıkmıyor (önceden çıkıp dönüşte hata
veriyordu). Günlük satırları sağlayıcı adlı genel metinler.

**Google Cloud Console.** Değişiklik gerekmez: yönlendirme adresi
`<site>/pinegrap/google_auth.php` olarak kaldı.

**Doğrulanamayan.** Gerçek Google ile uçtan uca giriş (sandbox dışarı
çıkamıyor). Sandbox'ta taklit token sunucusuyla denendi: taşınmış bağ,
eski kolonda kalmış bağ (taşındı), aynı doğrulanmış adresle personel
hesabına bağlanma, bilinmeyen hesap reddi, doğrulanmamış adres reddi,
kayıtla üye açma, üyelik formuna devir ve üyelik tamamlanınca kimlik,
vazgeçme, tekrar kullanılan state, token ucunun `invalid_grant` cevabı.

---

## 2026.4.10 — Dış API: iletişim (kişi) grupları (2026-10-13)

**Sorun.** Kampanyaların gönderildiği iletişim grupları API'de yoktu. Bir
bülten aracı, CRM ya da form entegrasyonu "bu kişi hangi listede" sorusunu
soramıyor, yeni aboneyi listeye ekleyemiyor, dışarıda abonelikten çıkanı
sitede işaretleyemiyordu; bunun için kişi ekranında grup işaretlemek
gerekiyordu. #315'ten sonra kampanyaların `contact_group_ids` alanı da bir
grup adına bağlanamıyordu.

**Değişiklik.** Yeni `includes/api/resources/contact_groups.php` ve yeni
izin grubu `contact_groups` (`contact_groups:read`, `contact_groups:write`);
kişi ekranlarının kapısı `validate_contacts_access()` olduğu için rol < 3 ya
da kişi yetkisi (`manage_contacts`, `customers:*` ile aynı koşul). Rol 3
hesabın uygulaması, ekranlardaki gibi yalnız kendisine verilen gruplara
ulaşır (`users_contact_groups_xref`, `api_contact_group_owner_filter()`;
liste, okuma, üyeler ve yazmada).

- `GET /contact-groups` (`email_subscription`, `q` ad/açıklama; imleçli,
  id sırasıyla): ad, açıklama, e-posta aboneliği, `subscription_type`
  (open: e-posta tercihleri sayfasında herkese, closed: yalnız üyelerine;
  abonelik kapalıysa `null`), üye sayısı (tek sorguda `GROUP BY`; silinmiş
  kişiden kalan bağ sayılmaz), oluşturan/değiştiren.
- `GET /contact-groups/{id}`: aynı alanlar, tek grup.
- `GET /contact-groups/{id}/members` (`email`; imleçli, kişi id sırasıyla):
  kişi, ad, adres, şirket, `email_opt_in` (kişinin bütün kampanyalar için
  anahtarı, `contacts.opt_in`) ve abonelikli grupta `opt_in` (bu grubun
  e-postası için tercihi, `opt_in` tablosu; satırı yoksa kampanya ekranı
  gibi abone sayılır; aboneliksiz grupta `null`).
- `POST /contact-groups/{id}/members`: `add` ve `remove` listeleri (en çok
  250'şer kişi kimliği) tek istekte, kişi ekranında grup işaretler gibi.
  Zaten üye olanı eklemek ya da üye olmayanı çıkarmak hata değil; cevaptaki
  `added`/`removed` gerçekten değişeni sayar. Eklenecek kişi var olmalı
  (yoksa 422). `opt_in` gönderilirse `add`'deki her kişinin bu grup için
  tercihi yazılır (üye olanlar dahil; dışarıdaki abonelikten çıkma
  `add=[id], opt_in=false`); aboneliksiz grupta yok sayılır. Gönderilmezse
  hiçbir tercih yazılmaz: yeni üye abone sayılır, daha önce çıkmış ve geri
  eklenen kişi çıkmış kalır. Gruptan çıkarmak tercih satırını silmez, kişi
  ekranı da silmiyor.

Grup oluşturma, adını değiştirme ve silme bilerek yok: grup kendi
ekranında yapılır. Kişi kaydına dokunulmaz; tam kayıt `GET /customers/{id}`,
adresle arama `GET /customers?email=`. Yazma `dry_run` destekler, günlüğe
uygulama ve anahtarla yazılır. Yeni izin mevcut uygulamalara verilmez
(`customers:write` taşıyan anahtar dahil); operatör Uygulama Erişimi'nde
işaretler. Şema değişikliği yok.

Testler: `tests/api_contact_groups_test.php` (rol 3 süzgeci, üyenin
tercihi ve satırsız üye, üye alanları ve şemaya uyması, kimlik listesi,
gerçek değişiklik sayımı, rotalar ve kapsamları, aynı yöntem+yol çakışması
yok, kapsamın kişi ekranı kapısı ve mevcut anahtarlara verilmemesi).

**Doğrulandı (sandbox, MariaDB 10.11, varsayılan site dökümü).** İşleyiciler
API yardımcıları taklit edilerek gerçek tablolarda koşturuldu: liste,
`q`/`email_subscription` süzgeci, imleç ve okunamayan imleç 400; üye
sayısı silinmiş kişinin bağını saymadı; tek okuma, abonelikli ve
aboneliksiz grup, 404; üyeler, imleç, `email` süzgeci, `opt_in` satırlı,
satırsız ve aboneliksiz; boş istek, aynı kişi iki listede, olmayan kişi
422; `dry_run` hiçbir şey yazmadı; ekle + çıkar, zaten üye olan sayılmadı;
`opt_in=false` üye kalırken tercihi yazdı; çıkmış kişi `opt_in`
gönderilmeden geri eklenince çıkmış kaldı; aboneliksiz grupta `opt_in`
yok sayıldı; rol 3 yalnız kendi grubunu gördü, başkasına 404. HTTP
katmanı (kimlik doğrulama, yönlendirici, OpenAPI) diğer uçlarla ortak,
ayrıca denenmedi.

---

## 2026.4.10 — Dış API: e-posta kampanyaları (2026-10-13)

**Sorun.** E-posta kampanyaları API'de yoktu. Bir bülten ya da CRM
entegrasyonu, Claude veya bir izleme aracı "hangi kampanya bekliyor, kaçı
gitti, kime gidemedi" sorusunu soramıyor; bekleyen bir kampanyayı durdurmak,
saatini kaydırmak ya da konusundaki yazım hatasını düzeltmek için Kampanyalarım
ekranına girmek gerekiyordu.

**Değişiklik.** Yeni `includes/api/resources/campaigns.php` ve yeni izin
grubu `campaigns` (`campaigns:read`, `campaigns:write`); ekranın kapısı
`validate_email_access()` olduğu için rol < 3 ya da e-posta yetkisi. Rol 3
hesabın uygulaması, ekranlardaki gibi yalnız kendi oluşturduğu kampanyalara
ulaşır (`api_campaign_owner_filter()`, liste, okuma, yazma ve silmede).

- `GET /campaigns` (`status`, `type` manual/automatic, `purpose`,
  `profile_id`, `q` konu, `created_since`; imleçli, yeniden eskiye): her
  kampanya alıcı toplamlarıyla (`total`, `sent`, `failed`, `pending`; tek
  sorguda, `GROUP BY`). İş vazgeçtiği alıcıyı `complete = 1` ile de
  işaretlediği için `failed` gönderilmiş sayılmaz; 2026.4.8 tekrar
  kolonları yoksa (`pg_email_retry_ready()`) hiçbir alıcı başarısız değil.
- `GET /campaigns/{id}`: mesaj (`body`: düz metinde metin, HTML'de
  kampanya oluşturulurken işlenmiş sayfa) ve elle kampanyada kişi grupları:
  `contact_group_ids` (dahil) ile `excluded_contact_group_ids` (hariç;
  `contact_groups_email_campaigns_xref.type`).
- `GET /campaigns/{id}/recipients` (`status` sent/pending/failed, imleçli,
  gönderim sırasıyla): adres, kişi, deneme sayısı, son hata.
- `POST|PATCH /campaigns/{id}`: ekranın değiştirdikleri: `status`
  ready/paused/cancelled, `subject`, düz metinde `body`, `from_name`,
  `from_email`, `reply_to`, yalnız otomatikte `bcc`, `send_at` (boş dize:
  hemen; sütunun varsayılanı `1000-01-01 00:00:00` yazılır), `purpose`.
  Gönderilmiş (`complete`) kampanya 409 `campaign_complete`; HTML
  kampanyanın gövdesi 409 `campaign_body_is_a_page`; elle kampanyada bcc
  409 `campaign_bcc_automatic_only`. Adresler `validate_email_address()`,
  boş adres ekrandaki gibi kabul.
- `DELETE /campaigns/{id}` ve `POST /campaigns/{id}/delete`: ekrandaki gibi
  kampanya, grup bağları ve alıcılar birlikte silinir; gönderilmiş olan
  gönderilmiş kalır.

API hiçbir e-posta göndermez: `ready` kampanyayı zamanlanmış işin
kuyruğuna bırakır; iş kapalıysa ekrandaki "Kampanyayı Gönder" düğmesini
bekler. Kampanya oluşturma (kişi gruplarından alıcı seçimi) bilerek yok:
dışarıya toplu e-posta açar, ayrı karar. Yazmalar `dry_run` destekler,
günlüğe uygulama ve anahtarla yazılır. Yeni izin mevcut uygulamalara
verilmez (kayıtlı kapsamlar olduğu gibi saklanır); operatör Uygulama
Erişimi'nde işaretler. Şema değişikliği yok.

Testler: `tests/api_campaigns_test.php` (gönderim saati yazma/okuma ve
temizleme, toplamlar, alıcı durumu ve süzgeci, rol 3 süzgeci, rotalar ve
kapsamları, aynı yöntem+yol çakışması yok, kapsamın ekran kapısı ve
mevcut anahtarlara verilmemesi).

**Doğrulandı (sandbox).** Liste, `status`/`type`/`purpose`/`profile_id`/`q`
süzgeçleri, imleç ve okunamayan imleç 400; dört kampanyanın toplamları
(6 alıcı: 3 gönderildi, 1 başarısız, 2 bekliyor); tek okuma, dahil/hariç
gruplar, HTML gövde; alıcılar ve üç durum süzgeci, imleç; `send_at`
İstanbul saatine yazıldı (07:00Z → 10:00), `null` sütunun varsayılanını
yazdı; geçersiz adres, boş konu, boş istek, tanınmayan alan, `complete`
durumu 422; üç 409; otomatikte bcc; `X-Dry-Run` güncelleme ve silmede
hiçbir şey yazmadı; iki silme yolu kampanyayı, alıcıları ve grup
bağlarını birlikte sildi, ikinci silme 404; rol 3 + e-posta yetkili
uygulama yalnız kendi kampanyasını gördü ve değiştirdi, başkasınınkine
404; izni olmayan ve e-posta yetkisi olmayan rol 3 uygulamaya 403
`insufficient_scope`; site günlüğü satırları; OpenAPI iki nesne, dört yol.

**Doğrulanamayan.** Zamanlanmış işin `ready`'ye alınan kampanyayı gerçekten
göndermesi denenmedi (sandbox'ta posta sunucusu yok); işin seçtiği
koşullar (`status`, `start_time <= NOW()`) ekranın yazdığıyla aynı.

---

## 2026.4.10 — Ödemede "Cari hesabıma yaz" (ERP Faz 8c, 10.60) (2026-10-13)

**Neden.** `docs/_plan_erp_ozellikler.md` Faz 8c: kredi limiti tanımlı bir
cariye bağlı müşteri mağazadan ödeme yapmadan, cari hesabına yazdırarak
sipariş verebilsin.

**Ne değişti.** Ayar `config.erp_open_account_payment` (varsayılan kapalı,
Ayarlar › E-Ticaret › ERP). Seçenek ancak şu üyeye çıkar:
`pg_erp_open_account()`. Üyenin kişisine bağlı cari aktif ve müşteri ya da
her ikisi türünde olmalı, `credit_limit > 0` olmalı ve oturum ghost
olmamalı. Tasarımcının ödeme widget'ı (`_eo_render_payment_methods()`,
`_eo_render_payment_methods_only()`) ve eski yerleşik ekranlar
(`get_express_order.php`, `get_order_preview.php`) "Cari hesabıma yaz"
radyosunu `value="On Account"` ile, altında "Kullanılabilir limit" yazarak
basar. `submit_order.php` değeri `Offline Payment`'a çevirir; yeni enum
değeri açılmadı. Ödeme denetiminde limit sorulur: `pg_erp_open_account_refusal()`
→ `erp_storefront_on_account()`. Reddedilirse alan hatası nedeni ve
kullanılabilir tutarı söyler, sayfa "Cari hesabıma yaz" seçili döner.
Kabulde `orders.erp_on_account = 1` ve `erp_account_id` yazılır, makbuzda
ödeme yöntemi "Cari hesabıma yaz" görünür. Genel çevrimdışı ödeme
kapalıyken de çalışır. Abonelik (recurring) siparişi cariye yazılmaz.

**Okumalar.** Plan `erp_credit_refusal()` diyordu; o yalnız engelleme
modunda (`block`) reddeder, varsayılan uyarı modunda boş döner ve cümlesi
operatöre fatura dilinde konuşur. Mağazada uyarıyı okuyacak kimse yok. Bu
yüzden aynı kural (`erp_credit_state()`: sonrası > limit ise aşım) moddan
bağımsız uygulanır. Defter siparişi ancak faturalanınca tutar, bu yüzden
faturalanmamış cari siparişleri de sayılır (`erp_invoice_id = 0`, durum
complete/exported). Limitin tam dolması kabul edilir.

"Otomatik fatura ayarı" için kod yok: `ERP_AUTO_INVOICE_ON` tanımlı ama hiçbir
yerde okunmuyor. Fatura her sipariş gibi sipariş ekranındaki akıştan kesilir.
`erp_invoice_from_order()` `orders.erp_account_id`'yi okur, vade
`erp_account_due_date()`'ten gelir. Fatura ödenince `settlement.php` siparişi
ödendi işaretler; bu var olan yol.

**Aynı akışta.** `job.php` havale iptali `erp_on_account = 0` ister: cari
siparişine havale gelmez. `view_order.php` "Havale bekleniyor" yerine "Cari
hesapta" rozeti, ödeme yöntemi satırında "Cari hesaba yazıldı" gösterir.
Sipariş görüntüleme widget'ı `^^__payment_method_pretty^^` da aynı.
e-Arşiv ödeme şekli `erp_order_payment_method_code()` ile `DIGER`; havale
yapılmadığı için `EFT/HAVALE` değil.

**Bilerek yapılmayan.** Kendi yerleşim dosyası olan eski ödeme sayfaları
(`data/layouts/*.php`) radyoyu kendiliğinden göstermez. Yerleşime
`<input type="radio" name="payment_method" value="On Account">` eklenirse
sunucu tarafı aynı çalışır. Aynı anda verilen iki siparişin limit kontrolü
kilitsizdir. Toplamı 0 olan siparişte ödeme adımı yoktur, cariye bir şey
yazılmaz.

**Doğrulama.** Sandbox'ta adım 10.60 iki kez koşturuldu. İlkinde üç işlem
yapıldı, ikincisinde üçü de "zaten var" diye atlandı. Eski ödeme sayfası
(`/express-order`) üzerinden gerçek POST'larla denendi; limit 600, bakiye 372.

- 2 × 122,55 reddedildi: "limitin ₺228,00 kadarı kullanılabilir".
- 1 × 122,55 kabul edildi: Offline Payment, `erp_on_account` 1, cari 1. Genel
  çevrimdışı ödeme kapalıydı.
- İkinci 122,55 bekleyen siparişle reddedildi (₺105,45).
- Düz `Offline Payment` "mevcut değil" dedi.
- "Mevcut değil" dönenler: limitsiz Bob, carisiz Carol, ghost oturum, ayar
  kapalı.
- Tasarımcı widget'ı seçeneği yalnız Alice'e gösterdi ("Kullanılabilir
  limit: ₺105,45").
- `job.php` 30 günlük düz havaleyi iptal etti, cari siparişini bıraktı.
- Fatura PGF2026000000007 cari 1'e, vade +30 gün, `DIGER` ile kesildi.
  Bakiye 49.455'e çıktı, kullanılabilir tutar aynı kaldı (çift sayım yok).
  Tahsilatla fatura ödendi, sipariş `paid_at` aldı.
- Panelde "Cari hesapta" rozeti ve "Cari hesaba yazıldı" göründü.
- `tests/erp_storefront_test.php`'de limit kuralı ve ayar kapalı golden
  testi var.

---

## 2026.4.10 — USPS fiyat, teslim tarihi ve adres doğrulaması REST API ve OAuth ile (2026-10-13)

**Neden.** USPS Web Tools'u (`production.shippingapis.com`, XML, `USERID`
ile giriş) kapattı. USPS API'leri artık yalnız USPS Developer Portal
uygulamasının tüketici anahtarı ve gizli anahtarıyla alınan OAuth 2.0
jetonunu kabul ediyor. Bu yüzden USPS yöntemlerinin anlık fiyatı ve teslim
tarihi gelmiyor, "ABD Adreslerini Doğrulama" çalışmıyordu. UPS (#302) ve
FedEx (#308) ile aynı iş, aynı modülde.

**Nasıl.** `includes/fn/carrier_api.php`'ye USPS eklendi:

- Uygulama `connect_apps` tablosunda `shipping_usps` anahtarıyla, jeton
  `pg_connect_service_put('shipping_usps')` ile; önbellek, kilit ve 401'de
  bir kez yenileme UPS/FedEx'teki gibi. Hostlar: üretim
  `https://apis.usps.com`, test `https://apis-tem.usps.com`.
- Jeton: `POST {host}/oauth2/v3/token`, `client_id` ve `client_secret` form
  gövdesinde.
- `pg_carrier_api_call()` artık GET de yapıyor: `$payload` `null` ise gövde
  ve `Content-Type` gönderilmez (başlıklar `pg_carrier_api_request_headers()`
  ile). `pg_carrier_api_error_text()` USPS'in `error.errors[]` ayrıntılarını
  da satıra ekliyor, tekrar eden satırı bir kez yazıyor.
- Fiyat: paket başına `POST /prices/v3/total-rates/search`, `RETAIL`,
  bugünün tarihi, yalnız bu hizmetin ve etkin yöntemlerin sınıfları
  (`PRIORITY_MAIL_EXPRESS`, `PRIORITY_MAIL`, `USPS_GROUND_ADVANTAGE`).
  `pg_usps_rate_parse()`: Flat Rate, Cubic ve varış tesisi (DDU/DSCF/SCF/NDC)
  fiyatları elenir; `rateIndicator` SP/PA/DR/DN olanlar, yoksa göstergesiz
  ama açıklaması bunlardan olmayanlar kalır; paketin işleme sınıfına
  (eski machinable testi, `pg_usps_machinable()`) uyanlar seçilir ve
  kalanların en yükseği alınır. Fiyat `totalPrice`, yoksa `totalBasePrice`.
- Teslim tarihi: `GET /service-standards/v3/estimates`, `destinationType`
  `STREET`, kabul tarihi gönderim günü. Eski istek tek seferde tüm
  sınıfları alıyordu; yeni API sınıf başına yanıt verdiği için yalnız
  istenen hizmetin tarihi `shipping_delivery_dates`'e yazılır.
- Adres: `GET /addresses/v3/address` (sokak, daire, şehir, eyalet, ilk 5
  hane). `pg_usps_address_parse()`: `DPVConfirmation` D/S ya da düzeltme
  kodu 32 → "daire/suite eksik"; N ya da 22 → "bulunamadı"; 4xx metninde
  "address not found"/"multiple" → bulunamadı, "invalid city/state/zip" →
  ilgili alan, 404 → bulunamadı; ağ hatası, 401/403, 5xx ve boş adres
  müşteriye hata göstermez, günlüğe yazılır. Fatura adresinde PO Box ve
  büyük-küçük harf biçimi, gönderim adresinde ad/şirketin büyük harfe
  çevrilmesi, oturum hash'i ve mevcut mesajlar aynen kaldı.
- `shipping.php` başındaki `shipping_mask_credentials()` silindi: kimlik
  taşıyan XML isteği kalmadı.

**Ne değişti.**

- Ayarlar › E-Ticaret › Nakliye: "USPS Web Araçları Kullanıcı Kimliği"
  kutusu kalktı. Yerine USPS başlığı altında Tüketici Anahtarı, Tüketici
  Gizli Anahtarı (kayıtlıysa "Kayıtlı. Korumak için boş bırakın."), Ortam
  ve "Kaydet ve bağlantıyı test et". USPS'in açma/kapama anahtarı yok,
  eskisi gibi kimlik girilince açılır.
- Eski kullanıcı kimliği girili, yenisi yoksa kartta uyarı.
  `config.usps_user_id` silinmedi; artık kayıtta yazılmıyor ve yalnız bu
  uyarı için okunuyor. `USPS_USER_ID` sabiti `init.php`'de kaldı: yöntem
  ekranındaki USPS hizmet listesi eski kimlik ya da yeni uygulama varken
  görünür, kayıtlı yöntemlerin hizmeti kaybolmaz.
- Adres doğrulama uyarısı "Onaylı bir USPS Web Araçları hesabı gerektirir"
  yerine "Yukarıda girilen USPS tüketici anahtarı ve gizli anahtarıyla
  USPS Addresses API'sini kullanır."
- Kimlik alanlarının yardım metni taşıyıcının kendi alan adlarını
  kullanıyor ("… şu API'leri içeren bir uygulama oluşturun: …. Ardından
  uygulamanın {kimlik} ve {gizli} değerlerini buraya girin."); UPS ve
  FedEx kartları da bunu kullanıyor.
- `usps_ground` hizmetinin adı "USPS Retail Ground" yerine "USPS Ground
  Advantage" (USPS 2023'te değiştirdi; hizmet anahtarı aynı).
- Günlük satırları `lang()` ile Türkçe.

**Bilerek yapılmayan.** Ticari (`COMMERCIAL`) fiyat ve hesap numarası
yok; eski kod da perakende fiyat alıyordu. Kabul saati gönderilmiyor (eski
istek mesai bitiş saatini ekliyordu). Etiket, takip ve iade API'leri bu
işin dışında.

**Doğrulanan.** Uçlar, gövdeler ve yanıt alanları devir notundaki
araştırmaya göre (USPS api-examples, karrio-usps, usps_v3). Yerel bir
taklit sunucuyla jeton (form gövdesi), fiyat (POST, JSON), teslim tarihi ve
adres (GET, gövdesiz, `Content-Type` yok) uçtan uca denendi.
`tests/carrier_api_test.php`'ye 17 USPS testi eklendi. lint, check_lang,
test.php, check_bindings, check_api_schema, check_copies, build_js --check
temiz.

**Doğrulanamayan.** Gerçek bir USPS hesabına istek atılmadı. USPS'in
README'sine göre bazı uygulamalarda jeton için CRID/MID gerekebilir.
Dev'de: USPS Developer Portal uygulamasının anahtarlarıyla Test ortamında
"Kaydet ve bağlantıyı test et"; USPS hizmetli yöntemde fiyat ve teslim
tarihi; "ABD Adreslerini Doğrulama" açıkken ABD adresli ödeme. 10.72 adımı
ve `ENCRYPTION_KEY` gerekir.

---

## 2026.4.10 — MCP sunucusuna OAuth ile bağlanma (OAuth Faz F2, ilk parça) (2026-10-13)

**Neden.** `docs/_plan_tersine_oauth.md` §4.6 (senaryo 5), `docs/_plan_oauth.md`
Faz F: Çalışma Alanı'nın MCP sunucuları kartı (#297) yalnız elle girilen
jeton, başlık ya da kullanıcı adı/parolayla bağlanabiliyordu. Uzak MCP
sunucularının çoğu (GitHub, Linear, Notion, Atlassian, Sentry ve başka bir
Pinegrap sitesi) MCP yetkilendirme belgesine göre OAuth ister. Ortak altyapı
#295 ve #309'la hazırdı; bu parça kartı ona bağlar.

**Ne değişti.**

- `includes/mcp/client.php`: giriş keşfi. Kimliksiz bir `initialize`
  isteğine gelen 401'in `WWW-Authenticate: Bearer resource_metadata=...`
  başlığı (`pg_mcp_client_bearer_challenge()`) → korumalı kaynak belgesi
  (RFC 9728; başlıktaki adres, sonra yol ekli ve kökteki well-known adı) →
  yetkilendirme sunucusu belgesi (RFC 8414 yol ekli, OIDC yol ekli, OIDC yol
  sonu). Kaynak belgesi yoksa (2025-03-26 sunucusu) sunucunun kendi kökü
  yetkilendirme sunucusu sayılır. Denetimler: belgenin `resource`'u sunucunun
  adresini kapsamalı, AS belgesi istenen `issuer`'ı söylemeli, uçlar https,
  `code_challenge_methods_supported`'ta S256 olmalı (yoksa MCP belgesi gereği
  vazgeçilir). Kapsam sırası: meydan okumanın `scope`'u, yoksa
  `scopes_supported`. `pg_mcp_client_register()` RFC 7591 dinamik kaydı
  (`token_endpoint_auth_method none`), `pg_mcp_client_client_metadata()`
  sitenin istemci tanımı. `pg_mcp_client_list_tools()` ve
  `pg_mcp_client_call_tool()` artık HTTP `status` da döndürüyor.
- Yeni uç `connect_client.php`: sitenin istemci kimlik belgesi (CIMD,
  draft-ietf-oauth-client-id-metadata-document). Oturumsuz, salt okunur;
  `client_id` kendi adresi, tek yönlendirme adresi `connect.php`. Adres
  `pg_connect_client_metadata_url()`. Sitenin kendi OAuth sunucusu sorgu
  dizgili `client_id`'yi reddettiği için ayrı dosya (sorgu yerine yol).
- `includes/workspace/mcp.php`: `auth_kind = 'oauth'` (ENUM'da #297'den beri
  hazırdı, şema yok). Sağlayıcı `mcp_<id>`, kurucusu
  `ws_mcp_connect_provider()` dosya yüklenirken kaydedilir; tanım
  `ws_mcp_connect_definition()`: `identity => false`,
  `refresh_optional`, `default_ttl` 5 yıl (süresiz GitHub jetonu; bittiğini
  sunucunun 401'i söyler), `nonce => false`, `authorize_params` ve
  `token_params` = `resource` (RFC 8707), `iss_required` AS
  `authorization_response_iss_parameter_supported` derse. İstemci olma sırası
  (`ws_mcp_oauth_registration()`): yöneticinin girdiği istemci kimliği → site
  https'teyse ve AS okuyorsa CIMD → dinamik kayıt → hiçbiri yoksa "geliştirici
  ayarlarında uygulama kaydedin, yönlendirme adresi …" hatası. Bulunanlar
  `connect_apps.options`'ta (`registered`, uçlar, `issuer`, `resource`,
  `scopes`, `client_auth`). Dinamik kayıt aynı token adresi için tekrar
  edilmez. İstemci kimliği değişince eski bağlantılar biter.
- Bağlantı yalnız sitenin (`user_id = 0`, amaç `mcp`, `owner => 'site'`).
  `ws_mcp_connection()` OAuth sunucusunda başlığı
  `pg_connect_access_token()`'dan alır; 401'de `ws_mcp_reauthorize()` bir kez
  `force` yeniler ve isteği tekrarlar (araç listesi, el sıkışma, Pinegrap AI'ın
  araç çağrısı). Yenileme jetonu yoksa bağlantı `reauth` olur, kart
  "Yeniden bağla" der; hata metni `ws_mcp_error_text()`.
- Kart: OAuth sunucusunda durum rozeti (Bağlı değil / Yeniden bağla),
  "Bağla" (yalnız yönetici; `mcp_connect` eylemi gerekirse keşfi yeniden
  koşar, sonra `pg_connect_start()` ile hizmete yönlendirir) ve "Bağlantıyı
  kes" (`mcp_disconnect`, `pg_connect_disconnect()`). Dönüş
  `workspace_settings.php?mcp_connected=<id>#ws-mcp-<id>`;
  `ws_mcp_settings_returned()` mesajı gösterir ve araçları hemen okur.
  Formda isteğe bağlı istemci kimliği / gizli anahtar ve yönlendirme adresi.
  Sunucu silinince ya da adresi veya giriş türü değişince
  `ws_mcp_oauth_forget()` bağlantıları (hizmette iptal dahil) ve uygulamayı
  siler. `connect.php` sağlayıcının kurucusu için `includes/workspace/mcp.php`'yi
  yükler; genel bağlantı formu (POST) çalışma anında kurulan sağlayıcıyı
  başlatmaz: o, özelliğin kendi ekranından ve onun kuralıyla (Çalışma Alanı'nda
  yalnız yönetici) başlar, yoksa bir müdür sitenin MCP bağlantısını kendi
  hesabıyla değiştirebilirdi.
- `includes/fn/connect.php`: boş `scope` parametresi gönderilmez; yenileme
  jetonu olmayan bağlantı iptalde erişim jetonuyla iptal edilir (RFC 7009);
  sağlayıcıda `revoke_client_auth` iptal isteğine istemci kimliğini (gizliyse
  token ucundaki gibi kimlik doğrulamayı) ekler.

**Denendi.** Birim testleri (`tests/mcp_client_test.php`,
`tests/workspace_mcp_test.php`, `tests/connect_test.php`). Sandbox'ta sitenin
kendi OAuth sunucusu ve MCP ucuna karşı uçtan uca: keşif, gerçek kayıt ucu,
yetkilendirme adresi (resource, S256, kapsamlar), yanlış `state` ve eksik
`iss` reddi, kod → jeton, 12 araç okundu, bozuk jetonda 401 → yenileme
jetonuyla yenileme → tekrar, `products_list` çağrısı, bağlantıyı kesince
sunucudaki iznin silinmesi. CIMD yolu sandbox'ta denenemedi (https gerekiyor).

**Kapsam dışı (devir notunda).** Kişi başı bağlantı, yazan araçların onay
kartı (`ws_ai_changes` `record_type 'mcp_call'`), F3 (göreve takma ad).

---

## 2026.4.10 — Yedekler Google Drive, OneDrive ve Dropbox'a (2026-10-13)

**Neden.** `docs/_plan_tersine_oauth.md` §4.4: otomatik yedeğin uzak kopyası
yalnız FTP ve S3'e gidiyordu; KOBİ'lerin çoğunda ikisi de yok, ama Google,
Microsoft ya da Dropbox hesabı var. Sunucudan bağımsız, müşterinin kendi
bulutunda duran bir kopya barındırma işinin parçası.

**Ne değişti.**

- `pg_backup_remote_type()` beş tür tanıyor: `ftp`, `s3`, `gdrive`,
  `onedrive`, `dropbox` (`pg_backup_remote_types()`, ayar JSON'undaki sıra).
  Bulut türünün ayarı yalnız `connection_id` ve `folder`; izin bağlı hesapta
  (`connect_accounts`) kalıyor, ikinci bir belirteç deposu yok. Yeni şema
  adımı yok: ayar mevcut `config.backup_remote_settings` şifreli JSON'unda.
- Yeni amaç `includes/connect/purposes/backup.php` (sahibi site). Kapsamlar
  dar: Drive `drive.file` (hassas değil, Google doğrulaması istemiyor;
  uygulama yalnız kendi açtığı dosyaları görür), OneDrive
  `Files.ReadWrite.AppFolder`, Dropbox `files.metadata.read` +
  `files.content.write` (uygulama klasörü).
- Yeni sağlayıcı `includes/connect/providers/dropbox.php`: #295'in
  `'nonce' => false` ve `'revoke_auth' => 'bearer'` bayraklarını kullanıyor,
  `token_access_type=offline` ile süresiz refresh token alıyor. `account`
  amacı Dropbox'u da listeliyor, böylece Bağlı hesaplar ekranındaki
  "Yeniden bağla" Dropbox hesabında da çalışıyor.
- `includes/fn/backup_cloud.php` (her istekte yüklü; `pg_backup_remote_normalize()`
  klasör kuralını oradan okuyor): arşiv 10 MiB parçalar hâlinde gider
  (Drive'ın 256 KiB, Graph'ın 320 KiB katı). Drive: klasör bulunur ya da
  açılır, sürdürülebilir yükleme, 308 cevabındaki `Range`'e göre kalan
  yerden devam; yeni kopya yerleşince aynı adlı eski kopyalar silinir (Drive
  aynı adı yan yana tutar). OneDrive: uygulama klasörü ve alt klasörler
  açılır, yükleme oturumu `conflictBehavior=replace`; parçalar oturum
  adresine erişim belirteci olmadan gider (Graph'ın istediği bu). Dropbox:
  tek parçalık dosya `files/upload`, büyüğü `upload_session` start /
  append_v2 / finish, `mode=overwrite`. Her parça `pg_connect_api()` ile
  gittiği için bir saatlik belirteç uzun yüklemenin ortasında yenilenir.
- Saklama sayısı buluttaki klasöre de uygulanır (`pg_backup_cloud_prune()`,
  yalnız başarılı otomatik yedekten sonra): klasör listelenir,
  `pg_backup_prune_list()` ve silme anında yeniden `pg_backup_is_auto_name()`.
  Elle konan dosyalar silinmez.
- Ayarlar › Genel › Yedeklemeler: üç yeni seçenek; her biri hesap seçimi,
  klasör (boşsa sitenin alan adı) ve "X hesabı bağla" düğmesi. Düğme
  ayar formunun dışındaki gizli bir formu gönderir (`$pg_settings_modals`).
  Bağlantıdan `?backup_connect=<tür>` ile dönülür: tür ve yeni hesap
  seçili gösterilir, kaydetmek kullanıcıda. Kaydederken seçili bulut
  türünün hesabı (doğru sağlayıcı, sitenin kendi hesabı, `backup` amacı)
  ve klasör adı denetlenir.

**Bilerek yapılmayan.** Drive'da elle açılmış klasör kullanılmıyor:
`drive.file` onu göstermiyor, daha geniş kapsam Google doğrulaması ister.
Yedekten geri yükleme buluttan yapılmıyor; arşiv indirilip Yedekleme
Yöneticisi'nden yüklenir. OneDrive iş hesaplarında uygulama klasörü
kiracının ayarına bağlı olabilir.

---

## 2026.4.10 — Google Search Console: arama verileri ve tek tıkla doğrulama (10.73) (2026-10-13)

**Neden.** Tersine OAuth planının ikinci senaryosu (`docs/_plan_tersine_oauth.md`
§4.2). Sitenin kendi sayacı gelen ziyaretçiyi sayar ama hangi aramayla
geldiğini, sayfanın Google'da kaç kez ve kaçıncı sırada göründüğünü bilmez.
Ürün sahibi "ilk 5 hemen" dedi; model A (her site kendi Google uygulaması).

**Ne değişti.** Ziyaretçiler menüsünde `search_console.php`. Bağlı hesaplar
altyapısı (#282) üzerinde `search_console` amacı: Google kapsamları
`webmasters` ve `siteverification.verify_only`. Bağlama
(`pg_gsc_link()`, `includes/fn/search_console.php`):

1. `sites.list` → `pg_gsc_property_pick()`: önce bu adresin URL öneki
   mülkü (yazımı fark etmez), yoksa ana makinenin ya da üst alan adının
   alan mülkü, en yakını; aynı eşleşmede yüksek yetki. Doğrulanmamış mülk
   atlanır.
2. Mülk yoksa Site Verification `token` (META) → kod
   `config.google_site_verification`'a yazılır, `get_page_content()` her
   sayfaya `<meta name="google-site-verification">` basar (yer tutucu
   yoksa `</head>` önüne) → `webResource.insert` → Search Console'a
   `sites.add`.
3. Sahip ya da tam yetkiliyse site haritası (`sitemaps.submit`).
4. İlk indirme (60 sn bütçe), `seo_search_job` iş dağıtıcısı açıksa
   listesine eklenir.

Hesap bağlanıp ekrana `?connected=1` ile dönülünce ve seçilecek tek hesap
varken bağlama kendiliğinden başlar; bu yüzden kurulum tek tık. Kendiliğinden
başlama yalnız `connect.php`'nin bıraktığı bildirimle olur, adres tek başına
hiçbir şey yapmaz.

Günlük iş (`seo_search_job.php`) Search Analytics'ten üç düzey okur: `site`
(tarih), `page` (tarih + sayfa), `query` (tarih + sorgu), 5000'lik
sayfalarla en çok 50 000 satır. İlk seferde 90 gün, sonra son okunan günden
üç gün geriden (Google son günleri düzeltir). 16 aydan eski satırlar silinir.
Alan mülkünde yalnız bu sitenin adresleri sayılsın diye `includingRegex`
sayfa süzgeci.

Ekran: 7/28/90 gün; tıklama, gösterim, tıklama oranı, ortalama sıra (gösterimle
ağırlıklı) ve önceki döneme göre değişim; Chart.js günlük grafik; en çok
tıklanan 50 arama ve sayfa; durum kartında mülk, yetki, hesap, son indirme,
site haritası, hatalar ve Şimdi indir / Site haritasını yeniden gönder /
Bağı kaldır (yönetici ve üstü). Pano kartı 31 "Google Arama": son 28 gün,
haftalık çubuk, ilk beş arama; site bağlıyken ve ziyaretçi yetkisi olana.

**Bilerek yapılmayan.** Toplamlar sorgu satırlarından toplanmaz, `site`
satırından okunur: Google anonim aramaları sorgu satırlarına koymaz, toplam
Search Console'dakinden az görünürdü. Bağ kaldırılınca doğrulama etiketi
sayfalarda kalır: silinirse Google doğrulamayı düşürür, bu sahibin Search
Console'daki kararıdır. URL Denetimi (sayfa başına "dizinde mi") kotası
mülk başına günlük olduğundan bu PR'da yok. Modül `functions.php`'ye
eklenmedi; ekran, iş ve pano kartı kendisi yükler.

**Şema 10.73** (`upgrade_2026_4_10_search_console()`): `seo_search_daily`,
`config.search_console`, `config.google_site_verification`. dev.pinegrap.com
gibi 2026.4.10'da olan site Yazılımı Onar › "Veritabanı yükseltme adımlarını
yeniden koş" ile alır.

---

## 2026.4.10 — Kafe şablonu: menü, şubeler, kurumsal catering, masa rezervasyonu, etkinlikler, kariyer ve hesaplar; altı yeni blok (2026-10-13)

**Neden.** Ürün sahibi klinik şablonuyla birlikte "kurumsal bir cafe sitesi
olsun, menü özellikleri vs. olan" ve "bu 2 şablon için yeni bootstrap 5
bileşenleri eklensin, tam donanımlı tasarımlar olacak" dedi. Klinik (#292)
ve 18 blok (#276) birleşti; bu PR kafe şablonu ve onun için eksik kalan
altı blok.

**Nasıl.** `includes/design_templates/pinegrap-cafe.php` klinik şablonunun
yapısını izler: aynı düğüm kurucuları, ortak parçalar, sistem widget'ları ve
kurulumda editörün ürettiği başlangıç düzenleri (`'tree' => 'starter'`).

- Menü bir özel formdur ("Menü"): her yemek bir kayıt — kategori, ad,
  açıklama, fiyat, beslenme etiketleri (onay kutusu grubu, bir yemek
  birkaçını taşır), rozet, "Ana sayfada göster" ve fotoğraf. Menü
  sayfasının sekmeleri (Bootstrap `nav-pills`) kategori başına süzülmüş
  birer liste widget'ı taşır; ana sayfadaki favoriler "Ana sayfada göster"
  = Evet süzgeciyle en fazla dört yemek. Personel "Menüye Ekle" sayfasından
  yemek ekler; kayıtları panelde düzenler ya da siler. Şablon 24 örnek
  yemekle kurulur.
- Masa rezervasyonu ve catering teklifi klinikteki randevu düzenindedir:
  form kaydı kendi sayfasını açar (`form_item_view`, yalnız gönderen ve
  personel), altına yazışma gelir, otomatik kayıt misafire hesap açar.
  Durum yalnız personelin gördüğü bir seçim listesidir (`office_use_only`;
  rezervasyon: Talep edildi / Onaylandı / Tamamlandı / İptal; catering:
  Talep alındı / Teklif gönderildi / Onaylandı / Tamamlandı / İptal).
  Rezervasyonlarım ve Catering Taleplerim gönderen / izleyen / düzenleyen
  süzgeciyle gelir; Personel Alanı bugünden itibaren rezervasyonları tarih
  ve saat sırasıyla, en yeni beş catering talebini listeler.
- Etkinlikler bir formdur ("Etkinlik Takvimi") ve `event_date` ≥ `current
  date` süzgeciyle en yakını önce listelenir. Altı örnek etkinliğin tarihi
  kurulum gününe göre hesaplanır (3–20 gün sonrası): şablon hangi gün
  kurulursa kurulsun etkinlik sayfası ve ana sayfa dolu açılır.
- Kariyer: "İş Başvuruları" formu; özgeçmiş özel klasöre yüklenir, kayıt
  sayfası yoktur, durum Yeni / Görüşme / İşe alındı / Bu sefer olmadı.
  Açık pozisyonlar bir akordeondur, her biri site aramasında kendi
  sonucudur.
- Görünüm `organic`, palet `cocoa`; küçük resim `_pg_design_thumb_cafe()`
  (`includes/fn/design_themes.php`). Varsayılan olarak güzel adresler
  kapalı.

**Ne değişti.** 46 sayfa ve 52 widget:

- Ana sayfa (saatler kartlı hero, hızlı eylemler, favoriler, bu mevsimin
  kahvesi, rakamlar, şubeler, kahve kulübü damga kartı, catering tanıtımı,
  yaklaşan etkinlikler, yorumlar, son yazılar), Menü (beslenme anahtarı,
  beş sekme, kahve kökenleri), Şubelerimiz (üç kafe, saat tablosu, yol
  tarifi bağlantısı), Kurumsal ve Catering (hizmetler, kişi başı fiyatlı
  üç paket, nasıl çalışır, teklif formu, SSS), Etkinlikler ve Atölyeler
  (özel etkinlik bölümüyle), Hakkımızda (hikâye, kavurma atölyesi, ekip,
  sürdürülebilirlik, kariyer bandı), Kariyer, İletişim (konu seçimli
  görüşme), Bülten, Blog (dört örnek yazı), Masa Ayırt, rezervasyon ve
  catering talebi sayfaları, Rezervasyonlarım, Catering Taleplerim,
  Yardım Merkezi, 404.
- Hesap: giriş, kayıt, şifremi unuttum, şifre belirle, çıkış, Hesabım,
  profil, şifre değiştir, e-posta tercihleri, adres defteri (yalnız
  e-ticaret açıksa), gelen kutusu, görüşme, personel rehberi.
- Personel (özel klasör): Personel Alanı (yaklaşan rezervasyonlar, yeni
  catering talepleri, sekiz kısayol), Menüye Ekle, Yeni Etkinlik, Yeni Blog
  Yazısı, Personel Rehberine Ekle.
- On e-posta sayfası: rezervasyon, catering talebi ve iş başvurusu için
  birer "yeni" (personele) ve "alındı" (gönderene); yeni mesaj, mesajınız
  alındı, yeni yanıt, bülten hoş geldin.
- `tr.json`'a 716 anahtar; Türkçe örnek fiyatlar TL'dir (ör. `$4.80` →
  `₺170`), sayfa adları Türkçe (`menumuz`, `subelerimiz`,
  `kurumsal-catering`, `masa-ayirt`, `rezervasyonlarim`,
  `catering-taleplerim`). Şablonda geçen ama kafede başka anlama gelen
  mevcut çeviriler (Time → Zaman, Apply → Uygula, Service → Servis,
  Events → Olaylar…) için yeni, bağlama uygun anahtarlar kullanıldı.

**Yeni bloklar (`assets/js/style_designer.js`).** Paletin Bloklar
sekmesine altı blok: Menü: Beslenme Anahtarı, Menü: Kahve Kökenleri
(kavrum derecesi çubuğuyla), Catering: Paketler (öne çıkan paketle),
Sadakat: Damga Kartı (on damga, onuncusu hediye), Etkinlikler: Kartlar
(tarih yaprağıyla) ve Bildirim: Duyuru (kapatılabilir şerit). Metinler
`_sdT()` ile çevrilir.

**Düzeltmeler (çekirdek).**

- `_pg_cf_seed_given_records()` (`includes/fn/custom_form.php`) bir
  şablonun örnek kayıtlarında değer listesini atlıyordu; onay kutusu
  grubu (beslenme etiketleri) boş kalıyordu. Liste artık değer başına bir
  `form_data` satırı yazar — süzgeçler ve kayıt düzenleme ekranı alanı
  böyle okur. Sınır 12'den 24 kayda çıktı (kafe menüsü). Değerleri seçen
  kısım `_pg_cf_seed_record_values()` oldu;
  `tests/custom_form_seed_test.php`.
- `pg_design_templates()` (`includes/fn/designer.php`) şablon dosyalarını
  kendi değişkenleriyle aynı kapsamda `include` ediyordu: sayfalarını
  kurarken `$id` adlı bir değişken kullanan şablon listenin anahtarını
  eziyor, galeride görünmüyordu ("Şablon bulunamadı"). Dosya artık kendi
  kapsamında yüklenir (`_pg_tpl_include()`).

**Bilerek yapılmayan.** Masa doluluğu denetlenmez: misafir bir saat ister,
personel e-postayla onaylar ve durumu günceller. Menü bir e-ticaret
kataloğu değildir; sepet ve çevrimiçi sipariş yok. Damga kartı bir
tanıtım bloğudur, hesaba bağlı sayaç tutmaz. Catering teklifi ERP
teklifine bağlanmadı; personel isterse ERP'de ayrıca teklif hazırlar.

**Doğrulama.** `tools/lint.php`, `check_lang.php`, `check_bindings.php`,
`check_api_schema.php`, `check_copies.php`, `test.php`, `build_js.php
--check` temiz. Şablon deneme sitesine (`tools/setup_sandbox.sh`, Türkçe)
editörün başlangıç düzenleriyle kuruldu, uyarı yok; 24 yemek beş sekmeye
doğru dağıldı (6 / 4 / 5 / 4 / 5), beslenme etiketleri değer başına satır
olarak yazıldı, altı etkinlik ve dört blog yazısı geldi. Sayfalar
Bootstrap 5.3.8 ile 1280 ve 390 piksel genişlikte çekildi, yatay kaydırma
ve JS hatası yok. Ziyaretçi olarak masa ayırtıldı: hesap açıldı, kayıt
sayfası "Talep edildi" ile açıldı; aynı oturumda catering teklifi istendi
(kişi bilgileri dolu geldi), kayıt "Talep alındı" ile açıldı; ikisi
Rezervasyonlarım'da, Catering Taleplerim'de ve Personel Alanı'nda
listelendi. İş başvurusu özgeçmişle gönderildi: kayıt "Yeni" durumunda,
dosya özel klasörde. Yeni rezervasyon ve catering talebiniz alındı
e-postaları kayıtla doldu.

**Doğrulanamayan.** E-postaların gerçekten gönderilmesi (deneme sitesinde
posta yok) ve görsellerin yüklenmesi (dış adresler kapalı) denenmedi.

---

## 2026.4.10 — Alış faturası kendi serisinde (2026-10-13)

**Kaynak.** Satın alma siparişlerinin dev.pinegrap.com testi (koordinatör
üzerinden): alış faturası PGF2026000000001 ile satış faturası
PGF2026000000001 aynı numarayı taşıyor. `erp_invoice_issue()` iki yön için
de `ERP_DEFAULT_SERIES`'ten numara alıyordu; `erp_document_series` satırı
`doc_kind`'e göre ayrı olduğundan iki sayaç da 1'den başlıyordu.
`erp_invoices.uniq_number` `direction` içerdiğinden veritabanı ikisini de
kabul ediyordu.

**Ne değişti.** Yeni `erp_invoice_series($direction)`
(`includes/erp/invoice_manual.php`): satış faturası fatura serisinde kalır,
alış faturası `config.php`'deki `ERP_PURCHASE_INVOICE_SERIES` ya da fatura
serisi + `A` (PGF → PGFA) alır; irsaliyenin `S`, iadenin `I`, teklifin `T`,
satın alma siparişinin `P` eki gibi. Alış faturasının iadesi ana faturanın
serisine `I` ekler, artık PGFAI. Ayarlar › E-Ticaret › ERP'deki seri
açıklaması "alış faturaları A" diyor (yeni dil anahtarı).

**Bilerek yapılmayan.** Şema adımı yok. İstenen biçim
`erp_document_series.prefix`'i güncelleyen bir adımdı, ama `prefix` yalnız
GİB biçiminde kullanılıyor; "yıl" ve "sürekli" biçimleri seriyi doğrudan
yazdığından yalnız önek değişse PGF-2026-0001 yine iki belgede olurdu.
Kesilmiş belgelerin numaraları değişmedi; eski `PGF / purchase_invoice`
sayaç satırı yerinde kalır, kullanılmaz. Yeni seri 1'den başlar. Seri 10
karakter sınırına göre kesilmiyor (`I`, `S`, `T`, `P` eklerinde de böyle).

**Doğrulama.** Sandbox: alış PGFA2026000000001, satış PGF2026000000006,
alış PGFA2026000000002; `erp_document_series`'te `PGFA / purchase_invoice`
satırı açıldı. `tests/erp_purchase_order_test.php`'de seri testi.

---

## 2026.4.10 — Cari fiyatları mağazada (ERP Faz 8b, 10.59) (2026-10-13)

**Neden.** `docs/_plan_erp_ozellikler.md` Faz 8b (#14): bayi ya da
sözleşmeli müşteri için caride tanımlı fiyat ve iskonto (`price_lists.php`)
yalnız ERP'de elle kesilen faturada uygulanıyordu; aynı müşteri mağazadan
sipariş verince liste fiyatını ödüyordu.

**Ne değişti.** Şema 10.59 `config.erp_storefront_account_prices`
(TINYINT, varsayılan 0; `ERP_STOREFRONT_ACCOUNT_PRICES`), ayar Ayarlar ›
E-Ticaret › ERP'de "Cari fiyatları mağazada". Kural tek saf fonksiyonda,
`erp_storefront_price($liste, $koşullar, $kampanya)`
(`includes/erp/storefront.php`): cari fiyatı ürünün kendi satırındaki fiyat,
yoksa liste fiyatından (satırın ya da carinin) iskonto
(`erp_storefront_account_price()`, yuvarlama `erp_apply_rate()`);
kampanyayla **toplanmaz**, ikisinden düşük olan geçer, eşitlikte kampanya
kalır; liste fiyatının altına inmeyen cari fiyatı fiyat sayılmaz. Mağaza
tarafında `pg_erp_member_prices()` (`includes/fn/ecommerce.php`) oturumdaki
üyenin carisi için listeden ucuzlayan ürünleri istek başına bir kez çıkarır
(ERP yalnız koşulu olan üyede yüklenir) ve üç yer onu okur:

- `get_discounted_product_prices()`: bütün vitrin (katalog, ürün sayfası,
  arama, çapraz satış, sepet ve hızlı sipariş ekranları, panel API'nin
  ürün okuması) fiyatı buradan aldığı için üstü çizili liste fiyatı ve cari
  fiyatı görünür. Yeni `$for_member` parametresi; ürün RSS beslemeleri
  (`get_page.php`, `_pg_build_catalog_*_rss_parts()`) `false` geçer.
- `update_order_item_prices()`: teklifsiz satırın fiyatı cari fiyatı.
- `apply_offers_to_cart()` ve `get_best_offer_id()`: tekliften arındırılan
  satır cari fiyatına döner; bir teklif satırı ancak cari fiyatının altına
  indiriyorsa alır, indirim tutarı cari fiyatına göre sayılır (sonraki
  tekliflerin alt toplam koşulu doğru kalsın diye).

Boş döndüğü durumlar: ayar kapalı, oturum yok, kişinin carisi yok ya da
aktif müşteri carisi değil (`kind` customer/both, `status` active), personel
başkası adına sipariş veriyor (ghost oturum). Hediye kartı (para, mal değil)
ve bağış hiç almaz.

**Golden.** Ayar kapalıyken `pg_erp_member_prices()` boş dizi, üç yer
bugünkü değeri yazar (`tests/erp_storefront_test.php`). Sandbox'ta aynı
sepet (beş ürün, biri kampanyalı) geliştirme dalının koduyla ve bu kodla
ayar kapalıyken koşturuldu: satır satır aynı fiyat, teklif ve ara toplam
(124.976).

**Bilerek yapılmayan.** Katalogda fiyata göre sıralama ve fiyat aralığı
süzgeci SQL'de liste fiyatına bakar. Sipariş, sepetin en son
hesaplandığı fiyatla yazılır (`submit_order.php` fiyatı yeniden hesaplamaz;
kampanyada da böyle). Cari fiyatı vergi ve döviz bakımından liste
fiyatıyla aynı tabanda tutulur, dönüştürme yok.

---

## 2026.4.10 — Bağlı hesaplar: Dropbox'a hazırlık (2026-10-13)

**Neden.** Buluta yedek senaryosu (`docs/_plan_tersine_oauth.md` §4.4)
Dropbox'u getiriyor; Dropbox ortak altyapıda iki küçük boşluğa çarpıyordu.
Altyapı ayrı thread'lerin ortak dosyası olduğu için bu ek kendi küçük
PR'ında.

**Ne değişti.** Sağlayıcı dosyası iki yeni bayrak alabilir:
`'nonce' => false` (kimlik belirtecine nonce taşımayan sağlayıcı; nonce ne
gönderilir ne beklenir, belirteç yine doğrudan token ucundan, PKCE
doğrulayıcısı ve istemci sırrıyla gelir) ve `'revoke_auth' => 'bearer'`
(Dropbox: iptal, bağlantının erişim belirteciyle ve JSON `null` gövdeyle
yapılır, refresh token'ı da sonlandırır; öteki sağlayıcılar eskisi gibi
form gövdesinde refresh token gönderir). Google Drive'ın sürdürülebilir
yüklemesinin istediği cevap başlıkları (`api_http_request()`
`'response_headers'`) #297 ile aynı biçimde geldiği için bu PR'dan çıktı.

**Bilerek yapılmayan.** Davranışı değişen mevcut sağlayıcı yok; Google ve
Microsoft dosyalarına dokunulmadı.

---

## 2026.4.10 — Bağlı hesaplar: çalışma anında kurulan sağlayıcı (MCP sunucuları için hazırlık) (2026-10-13)

**Neden.** Çalışma Alanı'ndaki MCP istemcisinin OAuth'lu sunuculara
bağlanabilmesi (`_plan_oauth.md` Faz F, F2) için jetonların bağlı hesaplar
deposunda (`connect_accounts`) durması kararlaştırıldı. Oradaki sağlayıcılar
dosyadan okunuyordu (`includes/connect/providers/<ad>.php`, uçlar sabit);
bir MCP sunucusunun uçları ise keşifle bulunur ve sunucu başına değişir. Bu
değişiklik yalnız altyapıdır, ekranda bir şey değişmez; MCP tarafı ayrı
değişiklikle gelir. Kullanıcıya görünen bir etkisi olmadığı için
`changelog.txt`'ye yazılmadı.

**Ne geldi** (`includes/fn/connect.php`):

- `pg_connect_provider_builders($prefix, $builder)`: bir özellik bir anahtar
  öneki (`mcp_`) için kurucu kaydeder; `pg_connect_provider('mcp_7')`
  dosyada yoksa kurucuya sorar (`pg_connect_built_provider()`), tanım
  dosyadakiyle aynı biçimdedir ve `built => true` taşır. Aynı adlı dosya her
  zaman önce gelir. `pg_connect_providers()` yalnız dosyaları listeler, bu
  yüzden Bağlı hesaplar ekranı kurulan sağlayıcıları göstermez; özellik kendi
  ekranından bağlar.
- Amaç dosyası sağlayıcı yerine önek adlayabilir (`provider_prefix`);
  kapsamlar o zaman sağlayıcının `identity_scopes`'udur, sahiplik kuralı
  (`owner`) aynen geçerli.
- Genel istemci: `connect_apps.options.public = true` olan uygulama gizlisiz
  hazır sayılır (`pg_connect_app_public()`), jeton isteğinde yalnız
  `client_id` ve PKCE doğrulayıcısı gider (istemci kimlik belgesi ya da
  `token_endpoint_auth_method: none` ile dinamik kayıt).
- Sağlayıcı tanımına yeni anahtarlar (hepsi isteğe bağlı, var olan Google ve
  Microsoft davranışı değişmez): `token_params` (her jeton isteğine, kod
  değişimine de yenilemeye de eklenir: RFC 8707 `resource`), `identity =>
  false` (hesap adı yok: kimlik jetonu ve userinfo okunmaz, sahip başına tek
  bağlantı `external_id = 'account'`, etiket `account_label` ya da
  sağlayıcının adı), `refresh_optional => true` (yenileme jetonu vermeyen
  sunucu kabul edilir; erişim jetonu bitince bağlantı "Yeniden bağla"ya
  düşer), `default_ttl` (yanıtta `expires_in` yoksa erişim jetonunun ömrü;
  `pg_connect_token_response()`'a üçüncü parametre), `iss_required => true`
  (yetkilendirme sunucusu `authorization_response_iss_parameter_supported`
  diyorsa dönüşte `iss` zorunlu, RFC 9207).

**Doğrulama.** `tests/connect_test.php`'ye üç test ve bir ek: önekle kurulan
sağlayıcı (dosya önce gelir, liste dışı, geçersiz önek ve anahtar
reddedilir), önekli amaç ve sahiplik, genel istemcinin hazır sayılması,
varsayılan ömür. Jeton isteği ve `pg_connect_finish()` ağ ve veritabanı
istediği için birim testte yok; Google ve Microsoft yolunda değişen tek şey
`token_params`/`public`/`identity`/`refresh_optional` anahtarlarının
yokluğunda eski dalın çalışmasıdır.

**Doğrulanamayan.** Gerçek bir OAuth'lu MCP sunucusuyla bağlantı; bu altyapıyı
kullanan MCP tarafı henüz yok.

---

## 2026.4.10 — Microsoft ile Giriş ve Apple ile Giriş (2026-10-13)

**Neden.** Tersine OAuth planının (`docs/_plan_tersine_oauth.md`) dördüncü
senaryosu, model A (her site kendi Microsoft ve Apple uygulamasını kaydeder).
Google ile Giriş zaten vardı; iş ve okul hesapları Microsoft'ta, iPhone
kullanıcılarının çoğu Apple'da. App Store kuralları (4.8) üçüncü taraf
girişi sunan bir uygulamadan gizlilik odaklı bir seçenek de ister; Apple ile
Giriş bunu karşılar.

**Nasıl.** Bağlı hesaplar altyapısının (`includes/fn/connect.php`) sağlayıcı
dosyaları ve uygulama deposu (`connect_apps`) kullanılır; ikinci bir
belirteç deposu yok, çünkü giriş belirteç saklamaz: yalnız id_token okunur.
Sağlayıcı dosyasındaki `signin` bloğu girişi tanımlar (sıra, kapsamlar,
PKCE, kimliği oluşturan talepler, doğrulanmış sayılan kiracılar ve
talepler, istemci sırrının türü). Apple yalnız giriş içindir
(`'accounts' => false`, PR #293): Bağlı hesaplar ekranında görünmez,
onunla hesap bağlanamaz. `includes/fn/signin.php` akışı yürütür, dönüş
adresi `signin.php` (kök betik; `connect.php` oturum açmış kullanıcı
istediği için ayrı). Durum, nonce ve PKCE oturumda (en çok beş bekleyen
deneme, 15 dakika), RFC 9207 `iss`, id_token'da `iss`/`aud`/`exp`/`nonce`/
`sub` denetimi (`pg_connect_claims_problem()`). Kimlik kalıcı tabloda
(`user_identities`: sağlayıcı + konu tekil, kullanıcı + sağlayıcı tekil).
Microsoft'ta konu `tid:oid` (uygulama yeniden kaydedilse de değişmez); adres
yalnız kişisel hesap kiracısında (`9188040d-…`) ya da isteğe bağlı
`xms_edov` talebi doğruysa doğrulanmış sayılır (nOAuth: bir kiracı
yöneticisi başkasının adresini yazabilir). Apple'da `response_mode=form_post`
siteler arası bir POST olduğundan başlangıçta oturum çerezi
`SameSite=None` yapılır (ödeme dönüşleriyle aynı çare); istemci sırrı her
token isteğinde .p8 anahtarıyla imzalanan ES256 JWT'dir (DER imza JOSE
biçimine çevrilir); ad yalnız ilk girişte POST'un `user` alanında gelir.

**Ne değişti.** Ayarlar › Güvenlik'te iki kart: Microsoft ile Giriş (anahtar,
Bağlı hesaplar'daki uygulamanın durumu, yönlendirme adresi, `xms_edov`
açıklaması) ve Apple ile Giriş (anahtar, kurulum adımları, dönüş adresi,
Services ID, Team ID, Key ID, .p8 anahtarı; anahtar geri gösterilmez).
Apple alanları kaydetmeden önce denetlenir; hatalıysa ekrandaki hiçbir
ayar yazılmaz. Anahtarlar `config.signin_providers`'da, uygulama girilene
kadar düğme gizli. Düğmeler `pg_signin_buttons()` ile Google'ınkinin
altında: panel girişi, eski giriş/kayıt/üyelik ekranları (özel düzenlerde
`$google_signin` değişkeni) ve görsel editörün giriş, kayıt ve üyelik
widget'larındaki "Google ile Giriş" bölümü. Kurallar Google'ınki: doğrulanmış
aynı adresli hesaba bağlanır (o sağlayıcıya başka kimlikle bağlı hesap
devralınmaz), personel girişinde bilinmeyen hesap reddedilir, kayıttan
gelene üye açılır (parolasız, algo 3), üyelikte kimlik üyelik formuna
devredilir (`google_membership_pending` kaydı sağlayıcıyı taşır), ardından
`pg_mfa_gate()` ve `pg_device_limit_gate()`. Hesabım › Giriş ve cihazlar,
görsel editörün hesap güvenliği widget'ı (`has_microsoft`,
`microsoft_removable`, `microsoft_only`, Apple için aynıları) ve
`edit_user.php` bağlantıyı gösterir ve keser; tek giriş yolu olan
bağlantı kesilmez (`pg_signin_can_remove()`), kullanıcı silinince kimlikler
de silinir. Şema 10.74: `user_identities`, `config.signin_providers`.
`tr.json`'da "Sign in with Apple" "Apple ile giriş yap" yerine "Apple ile
Giriş" oldu (düğmeler alt alta "Google ile Giriş" ile aynı biçimde).

**Bilerek yapılmayan.** Google ile Giriş kendi yolunda kaldı
(`google_auth.php`, `user.user_google_id`); aynı akışa taşınması ayrı PR.
Görsel editördeki `__google_signin_url` bağlantı belirteci yalnız
Google'ındır; bölüm üç düğmeyi de taşır. Oturum açmış kişinin Microsoft ya
da Apple hesabını sonradan bağlama düğmesi yok (Google'da da yok): bağlantı
doğrulanmış adresle ya da o sağlayıcıyla açılan hesapla kurulur.
id_token imzası doğrulanmaz (OpenID Connect Core 3.1.3.7, belirteç TLS ile
doğrudan token ucundan gelir).

---

## 2026.4.10 — Klinik testi: başkası adına doldurulan form oturumdaki kişiyi ezmiyor; giriş sayfası tasarımın kendisinden; kayıt düzenleme ekranı sayfaya dönüyor; İptal düğmesinde send_to kaçışı (2026-10-13)

Klinik şablonunun (#292) dev testinden.

### 1. Giriş isteyen sayfa, kendi tasarımının giriş sayfasına gönderiyor

**Neden.** Klinik şablonu var olan bir tasarımın yanına kuruldu; aynı adlı
sayfalar "-2" eki aldı (`/giris-2`). Çıkış yapmış ziyaretçi Klinik'in
kayıt gerektiren bir sayfasını (randevu sayfası, `registration` klasörü)
açınca eski tasarımın `/giris` sayfasına gidiyordu. Sebep bir site ayarı
değil: `pg_sw_sign_in_url()` "giriş widget'ı taşıyan, ziyaretçinin
açabildiği ilk sayfayı" seçiyor, `pg_sw_widget_pages()` sayfaları başlık
ve ada göre sıraladığı için `giris` `giris-2`'den önce geliyordu.

**Nasıl.** `pg_sw_sign_in_url()` beşinci parametre olarak istenen sayfanın
id'sini alır; `_pg_sw_open_widget_page_name($type, $style_id)` önce o
sayfanın tasarımındaki (`page.page_style`) açık sayfayı, yoksa eskisi gibi
ilk açık sayfayı döndürür. Çağıranlar: `get_page.php` (private ve
registration klasörü yönlendirmesi) ve `get_page_content.php` (yorum için
giriş bağlantısı, yorumun sayfası).

**Bilerek yapılmayan.** Kurulumda site ayarını güncellemek: böyle bir ayar
yok, olsa da var olan sitenin giriş sayfasını yeni (belki taslak)
tasarıma çevirirdi. Widget'ların kendi bağlantıları (`login_page_id`,
`register_page_id`, `account_page_id`) şablonda zaten kendi sekmelerine
çözülüyor (`{{tab:login}}`); değişmedi. Bağlamı olmayan ekranlar
(`index.php`, `forgot_password.php` → `pg_sw_screen_page_url()`) eskisi
gibi ilk açık sayfayı kullanır.

### 2. Kayıt düzenleme ekranı send_to'ya dönüyor

**Neden.** Kayıt sayfasındaki Düzenle düğmesi (`__edit_url`,
`pg_sw_backend_edit_url()`) `edit_submitted_form.php?id=…&send_to=<sayfa>`
açıyordu, ama ekran `send_to`'yu forma koymuyordu; Kaydet panelin form
listesine (`view_submitted_forms.php`) dönüyordu. Klinikte "Durumu
Değiştir" bu düğme.

**Nasıl.** GET'te `send_to` (`pg_send_to_url()` + `pg_safe_redirect_path()`,
yalnız bu sitenin yolu) gizli alan olarak forma girer ve İptal oraya döner.
Kayıt başarılıysa mevcut `send_to` dalı çalışır. Hata olursa: eski
ön yüz düzenleme formu (`form_item_view_page_id` gönderir) eskisi gibi
`send_to`'ya, panel ekranı kendine döner ve `send_to`'yu korur (önceden
`send_to` varsa hatayı gösteremeden sayfaya giderdi).

### 3. İptal düğmesinde `send_to` betik olarak çalışmıyor

**Neden.** `output_header()` İptal adresini
`OnClick="pgSettingsGo('…')"` içine yalnız `htmlspecialchars()` ile
koyuyordu. Öznitelik çözülünce `&#039;` yeniden `'` olur; adresten gelen
bir `send_to` (`edit_product_group.php` zaten `pg_send_to_url()`
veriyordu; bu iş `edit_submitted_form.php`'yi de ekliyor) dizeyi kapatıp
betik çalıştırabiliyordu (İptal'e tıklayınca).

**Nasıl.** `pg_output_go_script($url)`: adres önce JSON dizesi
(`JSON_HEX_TAG|APOS|QUOT|AMP`), sonra HTML olarak kaçırılır. Test
`tests/output_test.php`.

### 4. Başkası adına doldurulan form oturumdaki kişiyi ezmiyor

**Neden.** Yönetici oturumuyla randevu formu başka bir hasta adına (ad,
e-posta, telefon farklı) gönderildi; formun kişi alanları (`contact_field`)
oturumdaki kişinin kişi kaydını (ad, soyad, e-posta, cep telefonu) formdaki
değerlerle ezdi. `custom_form.php` giriş yapmış kişide `$contact_id`'yi
`user.user_contact`'tan alıp her zaman onu güncelliyordu; kişi kaydı yoksa
yenisini açıp kullanıcıya bağlıyordu. Bütün özel formlar (eski sayfa türü
ve tasarım widget'ı) bu tek işleyiciden geçer.

**Nasıl.** `pg_cf_email_is_someone_else($typed, $known)` ve
`pg_cf_contact_is_someone_else()` (`includes/fn/custom_form.php`): giriş
yapmış kişide, formun kişi e-postası alanının değeri (`email_address`'e
bağlı alan) kişinin hiçbir adresi değilse (`user.user_email` ve kişi
kaydının `email_address`'i; büyük/küçük harf ve boşluk fark etmez) gönderim
başkası içindir. O zaman `$contact_id` boşalır: gönderim, anonim gönderim
gibi kendi kişi kaydını alır, bu kayıt kullanıcıya (`user_contact`)
bağlanmaz, oturumdaki kişinin kaydına dokunulmaz. Karar "sonra kaydet"
dalından önce verilir. Test `tests/custom_form_contact_test.php`.

**Ödün.** Kişi e-postası alanı olmayan formda iki durum ayırt edilemez;
eskisi gibi oturumdaki kişi güncellenir. Kendi e-postasını formda başka bir
adresle değiştiren kişi de artık "başkası" sayılır (yeni kişi kaydı açılır);
hesap e-postası zaten profil ekranından değişir.

**Bilerek yapılmayan.** E-postaya göre var olan bir kişi kaydını bulup
güncellemek: giriş yapmış herhangi bir üye, bir e-posta yazarak başkasının
kişi kaydını değiştirebilirdi. Personel adına doldurulan gönderim personelin
kullanıcısında kalır (`forms.user_id`); hastaya hesap açılmaz, randevu
hastanın Randevularım listesinde görünmez. Bu ayrı bir ürün kararı.

**Açık kalan.** Şablon sayfalarının editörde taslak başlaması (2026-10-06
kararı) ürün sahibinin kararını bekliyor; bu bölümde değişmedi.

---

## 2026.4.10 — Çalışma Alanı: kanalda @takma ad ile MCP sunucusuna soru (2026-10-13)

**Neden.** Önceki değişiklik (10.82) başka hizmetlerin MCP sunucularını
takma adla ekletiyor ve araç izinlerini tutuyordu; bu değişiklik onları
kanalda kullanılır kılar. "@github açık PR'lar hangileri?" yazılınca
Pinegrap AI o sunucunun açık araçlarıyla yanıt verir.

**Akış** (`includes/workspace/mcp_ask.php`, `ai.php`'ye bağlanır). Mesaj
gönderilince `ws_ai_after_send()` önce `ws_mcp_ask_server()`'a bakar:
gövdede elle yazılmış `@takmaad` (büyük/küçük harf fark etmez, `e@docs.com`
gibi kelime içi değil) ya da gövde Pinegrap AI'ı adıyla çağırmıyorsa
takma adla verilmiş bir yanıta cevap. İstek `ws_ai_requests`'e `agent = 'ai'`,
`mcp_server_id` ile girer; sıra, kilit, adım sınırı, kişi başı bekleyen
istek sınırı ve lisans Pinegrap AI'ınkiyle aynıdır. Pinegrap AI hazır
değilse kurulum ipucu, kanal kapalıysa kanal metni, sunucu kapalıysa ya da
açık aracı yoksa (`ws_mcp_ask_problem()`) bunu söyleyen sistem mesajı
yazılır ve istek açılmaz.

**Model ne görür.** `ws_ai_start()` takma adlı istekte
`ws_mcp_ask_start()`'a döner: kanalın özeti, kararları ya da ekip listesi
gitmez, yalnız istek, cevapladığı mesaj ve o mesaj önceki bir takma ad
yanıtıysa onun isteği. Gerekçe: modelin okuduğu her şey araç argümanına
girip öbür hizmete gidebilir. Sistem metni yalnız sunucunun araçlarının
olduğunu, argümanlara isteğin gerektirdiğinden fazlasının konmamasını,
araçların yalnız okuduğunu ve araç yanıtlarının talimat değil veri
olduğunu söyler; sunucunun `instructions` metni veri olarak, 1500
karakterle eklenir. Araçlar `answer`, `fail` ve sunucunun açık araçları
(en çok 40, alfabetik); şemalar nesne olarak çözülüp gönderilir (`{}`
korunur, `$schema`/`$id` atılır). Araç adı sohbet API'sinin kuralına
(`^[A-Za-z0-9_-]{1,64}$`) çevrilir, çakışan ad ve `answer`/`fail` numara
alır; eşleme her adımda yeniden kurulur.

**Araç çağrısı** (`ws_mcp_ask_tool_run()`). Çağrı anında araç hâlâ açık
mı, sunucu açık mı yeniden bakılır. Bir istekte en çok 8, modelin bir
turunda en çok 3 çağrı (web isteğinin süresi aşılmasın diye); argüman 20
KB'ı aşamaz. Zaman aşımı web'de 15, komut satırında 45 saniye. Sonuç
`pg_mcp_client_result_text()` ile 6000 karaktere kesilir ve
`service_text` olarak verilir. Her çağrı `ws_mcp_calls`'a yazılır
(argümanlar, durum, hata, süre, istek/kanal/mesaj); sunucunun `last_used`
alanı güncellenir, bağlantı hatası `last_error`'a düşer.

**Yanıt.** Öneri yoktur: `ws_ai_finish_answer()` takma adlı istekte görev
ve "yaptım" dürtmelerini atlar, metni doğrudan yazar. `ws_ai_deliver()`
yanıtın sonuna "@docs üzerinden: `products_list`, `pages_list`" satırını
ekler (en çok beş araç, kalanlar +N; araç çağrılmadıysa ayrı cümle),
uzunluk sınırı bu satırla birlikte hesaplanır.

**Ekran.** Kanalın @ menüsü açık ve araçlı sunucuları takma adıyla gösterir
(`BOOT.mcp`, `ws_mcp_boot()`); seçilince `@takmaad ` metni girer. Ayarlar
kartında takma adın kanalda nasıl kullanıldığı yazar, Pinegrap AI bağlı
değilse uyarı çıkar, 40'tan fazla araç açıksa ilk 40'ın sunulduğu söylenir.
`pg_mcp_client_call_tool()` argümanları nesne olarak da alır.

**Şema yok.** 10.82'nin `ws_mcp_calls` tablosu ve
`ws_ai_requests.mcp_server_id` kolonu kullanılır.

**Doğrulama.** `tests/workspace_mcp_test.php` (7 test): araç açılabilirliği,
kuralların yalnız çalışabilen araçlarda geçmesi, fonksiyon adları, 40
sınırı, takma ad biçimi, kimlik başlıkları, başlık adları. Sandbox'ta,
sitenin kendi `mcp.php`'sine yerel ağ dışı bir adresten bağlanan uzak
sunucu yerine geçen bir sunucuyla: takma ad tespiti, istek açma, model
bağlamı, araç listesi, gerçek HTTP araç çağrısı ve kaydı, kapalı araç ve
araç olmayan ad, yanıtın imza satırıyla kanala düşmesi, yanıta cevabın aynı
sunucuyla sürmesi, @ai ile cevabın Pinegrap AI'ın kendisine gitmesi,
kapalı sunucuda sistem mesajı, @ menüsü (ekran görüntüsü).

**Doğrulanamayan.** Modelin kendisi (ai.pinegrap.com sandbox'tan
çözülemiyor); model çağrısı dışındaki her adım denendi.
dev.pinegrap.com'da gerçek bir sunucuyla denenmeli.

---

## 2026.4.10 — FedEx teslim tarihleri REST API ve OAuth ile (2026-10-13)

**Neden.** FedEx eski SOAP web servislerini (`ws.fedex.com`, Rate v20;
anahtar, parola, hesap ve sayaç numarasıyla giriş) kapattı. FedEx
API'leri artık yalnız FedEx Developer Portal projesinin API anahtarı ve
gizli anahtarıyla alınan OAuth 2.0 jetonunu kabul ediyor. Bu yüzden FedEx
yöntemlerinin teslim tarihi gelmiyordu. UPS ile aynı iş (#302), aynı
modülde.

**Nasıl.** `includes/fn/carrier_api.php`'ye FedEx eklendi:

- Uygulama `connect_apps` tablosunda `shipping_fedex` anahtarıyla, jeton
  `pg_connect_service_put('shipping_fedex')` ile; önbellek, kilit ve
  401'de bir kez yenileme UPS'teki gibi. Hostlar: üretim
  `https://apis.fedex.com`, test `https://apis-sandbox.fedex.com`.
- Jeton: `POST {host}/oauth/token`, `client_id` ve `client_secret`
  form gövdesinde (FedEx'in belgelediği biçim).
- Tarih: `POST {host}/rate/v1/rates/quotes`, `X-locale: en_US`,
  `rateRequestControlParameters.returnTransitTimes = true`, hesap numarası
  `accountNumber.value`'da, `rateRequestType` `ACCOUNT`, paket ağırlığı
  LB (sıfırsa 1 lb, eskisi gibi). Eskisi gibi tek istekle tüm hizmetler
  gelir ve hepsi `shipping_delivery_dates` önbelleğine yazılır.
- Yanıt okuyucu `pg_fedex_delivery_parse()`: tarih sırayla
  `commit.dateDetail.dayFormat`, `operationalDetail.deliveryDate`,
  `commitDate`; transit gün sayısı `commit.transitDays.minimumTransitTime`
  ya da `operationalDetail.transitTime` (`ONE_DAY` … `TWENTY_DAYS`).
  FedEx Ground için eskisi gibi tarih transit gün sayısından, yöntemin
  hafta sonu ayarları ve hariç tutulan tarihlerle hesaplanır.
- `pg_carrier_api_http()` sıkıştırılmış yanıtı açar (FedEx gzip
  gönderebiliyor).

**Ne değişti.**

- Ayarlar › E-Ticaret › Nakliye › FedEx: Anahtar, Parola ve Sayaç Numarası
  kutuları kalktı. Yerine API Anahtarı, Gizli Anahtar (kayıtlıysa
  "Kayıtlı. Korumak için boş bırakın."), Ortam (Üretim / Test ortamı) ve
  "Kaydet ve bağlantıyı test et". Hesap Numarası aynı ayar anahtarında
  (`config.fedex_account`) kalır ve gereklidir.
- Eski bilgiler girili, yenileri yoksa kartta uyarı. Uyarı metni
  taşıyıcıdan bağımsız hale getirildi ("{taşıyıcı} anlık bilgileri"),
  UPS kartı da aynı metni kullanıyor.
- Kaydetme ve test bildirimleri alan adlarından bağımsız: "{taşıyıcı}
  kimlik bilgilerini kabul etti." / "{taşıyıcı} kimlik bilgileri
  kaydedilmedi: …".
- `config.fedex_key`, `fedex_password`, `fedex_meter` silinmedi; artık
  okunmuyor ve kayıtta yazılmıyor.
- Günlük satırları `lang()` ile Türkçe.

**Bilerek yapılmayan.** FedEx fiyatı istenmiyor; eski kod da yalnız teslim
tarihi alıyordu (FedEx yöntemlerinde anlık fiyat sıfır döner, yöntemin
kendi ücreti geçerli). SmartPost eskisi gibi desteklenmiyor. Gönderim
saati gönderilmiyor: eski istek mesai bitiş saatini ekliyordu, yeni
istekte yalnız tarih (`shipDateStamp`) var.

**Doğrulanan.** Uç, gövde ve yanıt alanları karrio-fedex 2026.1.32 ve
ShippingRates kaynaklarıyla karşılaştırıldı (developer.fedex.com bulut
ortamından açılmıyor). lint, check_lang, test.php, check_bindings,
check_api_schema, check_copies, build_js --check temiz.

**Doğrulanamayan.** Gerçek bir FedEx hesabına istek atılmadı. Dev'de:
FedEx Developer Portal'da "Rates and Transit Times" API'si eklenmiş bir
proje, test anahtarları ve test hesap numarasıyla Test ortamında
"Kaydet ve bağlantıyı test et"; sonra FedEx hizmetli bir nakliye
yönteminde teslim tarihi ve Ayarlar › Günlük'te hata satırı olmaması.
10.72 adımı ve `ENCRYPTION_KEY` gerekir.

---

## 2026.4.10 — Dış API: ziyaretçi raporları ve site günlüğü (2026-10-13)

**Sorun.** Trafik ve denetim kaydı API'de yoktu. Bir raporlama aracı ya da
Claude "geçen hafta kaç ziyaret geldi, hangi kampanya sipariş getirdi, bu
sayfayı kim değiştirdi" sorularını panelden başka yerde soramıyordu.

**Değişiklik.** İki yeni dosya, iki yeni izin grubu, yazma yok:

- `includes/api/resources/visitors.php`, izin `visitors:read`
  (`validate_visitors_access()`: rol < 3 ya da ziyaretçi yetkisi):
  - `GET /reports/traffic` (`interval` day/hour): `visitor_stats_hourly`
    toplayıcısından (`pg_visitor_stats_range()`), boş günler/saatler sıfır
    olarak; en çok 400 gün (saatlikte 31). `visitor_tracking` sitenin ziyaret
    sayıp saymadığını söyler.
  - `GET /reports/top-content`: `pg_visitor_top_content()`, başlık ve
    adresiyle (`pg_visitor_label_content_rows()`).
  - `GET /reports/visitors?by=…`: `visitors` tablosu, ziyaretçi raporu
    ekranının özet toplamlarıyla (ziyaret, sayfa, form, sipariş oluşturuldu /
    ödemeye geçti / tamamlandı, ciro kuruş). Boyutlar: referrer_host,
    search_engine, search_terms, channel (ücretli: izleme kodunda SEO
    ayarlarındaki pay-per-click işareti; işaret boşsa hiçbir ziyaret ücretli
    sayılmaz, ekranın boş `LIKE '%%'` davranışı alınmadı), landing_page,
    tracking_code, utm_*, country, state, city, form, site_search_terms.
    `totals` penceredeki bütün ziyaretleri kapsar.
  - Üç rapor da pencereyi sitenin saatine göre tam gün olarak sayar
    (`api_visitors_window()`, yaz saati geçişinde de); varsayılan son 30 gün.
    Tek ziyaret satırı (IP, şehir, user agent) bilerek verilmez.
- `includes/api/resources/site_log.php`, izin `site_log:read` (Site Günlüğü
  ekranı gibi rol ≤ 2): `GET /site-log` (`from`, `to`, `user`, `q`, imleç).
  Açıklama sitenin dilinde kayıtlı olduğu için `q` o dilde aranır. PHP hata
  günlükleri dosya olduğu için dışarıda kaldı.

Testler: `tests/api_reports_test.php` (varsayılan pencere, Mart 2026
Berlin yaz saati geçişinde 31 gün, işaretsiz kanal ifadesi). Şema
değişikliği yok.

**Doğrulandı (sandbox).** Haziran 2019 trafiği tablo toplamıyla aynı
(10 ziyaret / 539 sayfa), saatlik 48 dönem, 31 / 400 gün sınırları, from >
to 422; en çok görüntülenen üç sayfa; kaynak, kanal, giriş sayfası, ülke,
UTM dağılımları ve toplamlar SQL ile aynı (2158 ziyaret, 43 sipariş);
`ppc_` işaretli test ziyareti `pay_per_click`; site günlüğü süzgeçleri ve
imleç; izni olmayan uygulamaya 403; OpenAPI dört nesne.

---

## 2026.4.10 — UPS anlık fiyatları REST API ve OAuth ile (2026-10-13)

**Neden.** UPS erişim anahtarı (Access Key), kullanıcı adı ve parolayla
girişi kaldırdı; API'leri artık yalnız OAuth 2.0 client credentials ile
alınan erişim jetonunu kabul ediyor. `shipping.php` hâlâ
`onlinetools.ups.com/ups.app/xml/Rate` XML ucuna eski bilgilerle
gidiyordu, bu yüzden UPS anlık fiyatı gelmiyordu. Tersine OAuth planının
yan bulgusu (`docs/_plan_tersine_oauth.md`), ürün sahibinin "sorunları
çöz" kararıyla. FedEx (SOAP) ve USPS (Web Tools) ayrı PR'larla gelir.

**Nasıl.** Yeni modül `includes/fn/carrier_api.php` (`functions.php`
manifestinde `connect.php`'den sonra):

- Uygulama: UPS Developer Portal'da açılan uygulamanın istemci kimliği ve
  gizli anahtarı `connect_apps` tablosunda `shipping_ups` anahtarıyla
  (`pg_connect_app_save()`; gizli anahtar sitenin şifreleme anahtarıyla
  mühürlü, forma geri basılmaz), ortam (`production` / `test` = UPS CIE)
  `options.environment`'ta. `includes/connect/providers/` altında dosyası
  olmadığı için Bağlı hesaplar ekranında görünmez; `shipping_` öneki, ileride
  UPS'in kendi girişi için eklenecek bir sağlayıcıyla çakışmasın diye.
  Yeni şema adımı yok, #282'nin tabloları (10.72) kullanılır.
- Jeton: `POST {host}/security/v1/oauth/token`, Basic auth,
  `grant_type=client_credentials`. `pg_connect_service_put('shipping_ups')`
  ile mühürlü önbellekte; bitimine iki dakika kala yenilenir, ortam +
  kimlik + gizli anahtar parmak izi değişince eski jeton verilmez, ayar
  değişince silinir. Aynı anda iki ödeme iki jeton almasın diye
  `GET_LOCK` (Paraşüt'teki gibi). API 401 verirse jeton bir kez yeniden
  alınır.
- Fiyat: `POST {host}/api/rating/v2409/Shop`, başlıklar `transId`
  (32 karakter) ve `transactionSrc`. Eski davranış aynen: paket başına bir
  istek, tüm hizmetler tek yanıtta, hesap numarası varsa `ShipperNumber`
  + `NegotiatedRatesIndicator` ve anlaşmalı fiyat önce
  (`NegotiatedRateCharges.TotalCharge`, yoksa `TotalCharges`), hizmet
  kodları (01, 14, 13, 02, 59, 12, 03), ölçüler en çok altı karakter,
  `shipping_rates` önbelleği ve istek/yanıt günlük satırları. İstek kurucu
  ve yanıt okuyucu saf fonksiyonlar: `pg_ups_rate_request()`,
  `pg_ups_rate_parse()`.
- HTTP: `pg_carrier_api_http()`, `pg_curl_tls()` ile (kargo çağrıları
  `ALLOW_INSECURE_UPDATE_TLS` kapsamında), 10 sn zaman aşımı, yönlendirme
  izlenmez. Hata satırı UPS, FedEx ve OAuth hata biçimlerini okur
  (`pg_carrier_api_error_text()`).

**Ne değişti.**

- Ayarlar › E-Ticaret › Nakliye › UPS: Erişim Anahtarı, Kullanıcı Kimliği
  ve Parola kutuları kalktı. Yerine İstemci Kimliği, İstemci Gizli Anahtarı
  (kayıtlıysa "Kayıtlı. Korumak için boş bırakın."), Ortam (Üretim / Test
  ortamı) ve "Kaydet ve bağlantıyı test et" (jeton ister, sonucu
  bildirimde söyler). Hesap Numarası aynı ayar anahtarında
  (`config.ups_account`) kalır. Alanlar `pg_carrier_api_settings_fields()`
  ile çizilir, `pg_carrier_api_settings_save()` ile kaydedilir; alanları
  formda olmayan bir bölme onlara dokunmaz.
- Eski bilgiler girili, yenileri yoksa kartta uyarı: eski giriş kaldırıldı,
  anlık fiyat yeni bilgiler girilene kadar kapalı. Şema adımı koşmamışsa ya
  da şifreleme anahtarı yoksa kart onu söyler.
- `config.ups_key`, `ups_user_id`, `ups_password` silinmedi; artık
  okunmuyor ve kayıtta yazılmıyor.
- Boyutu sıfır olan paket boyutsuz, yalnız ağırlıkla soruluyor; REST API
  sıfır boyutu reddediyor.
- `tests/carrier_api_test.php`: jeton isteği, önbellek, hata satırı, UPS
  istek ve yanıtı.

**Bilerek yapılmayan.** Eski kolonlar silinmedi ve taşınmadı (yeni API
eski bilgileri kabul etmiyor). ShipTo için sokak adresi ve `ShipFrom`,
`PaymentDetails` gönderilmiyor; eski istek de göndermiyordu, önbellek
posta koduna göre. UPS teslim tarihi eskisi gibi hesaplanıyor.

**Doğrulanan.** Uç, başlık, gövde ve yanıt alanları UPS'in resmi OpenAPI
dosyalarıyla (github.com/UPS-API/api-documentation: `Rating.yaml`,
`OAuthClientCredentials.yaml`) ve karrio-ups ile ShippingRates
kaynaklarıyla karşılaştırıldı. lint, check_lang, test.php, check_bindings,
check_api_schema, check_copies, build_js --check temiz.

**Doğrulanamayan.** Gerçek bir UPS hesabına istek atılmadı (bulut
ortamında taşıyıcı hesabı yok). Dev'de: Rating API'si eklenmiş bir UPS
uygulamasıyla Test ortamında "Kaydet ve bağlantıyı test et", sonra UPS
hizmetli bir nakliye yöntemiyle sepette fiyat ve günlükte "UPS fiyat
isteği / yanıtı" satırları. 10.72 adımı ve `ENCRYPTION_KEY` gerekir.

---

## 2026.4.10 — Dış API: hediye kartları (2026-10-13)

**Sorun.** Hediye kartları API'de yoktu. Ödül, iade ya da mağaza dışında
satılan kart için kod üretmek, bir kartın kalan bakiyesine bakmak ya da
sadakat sisteminden bakiye düzeltmek için Hediye Kartları ekranına girmek
gerekiyordu.

**Değişiklik.** Yeni `includes/api/resources/gift_cards.php` ve yeni izin
grubu `gift_cards` (`gift_cards:read`, `gift_cards:write`); ekranın kapısı
`validate_ecommerce_access()` olduğu için e-ticaret açıkken rol < 3 ya da
e-ticaret yetkisi. Kod harcanabilir para olduğu için `orders:read`'e
katılmadı: satış raporlayan bir entegrasyon kodları görmemeli.

- `GET /gift-cards` (`status` active/used/expired, `order_id`, `q` kod
  tireli ya da tiresiz, not, gönderen, alıcı e-postası; `created_since`;
  imleçli, yeniden eskiye) ve `GET /gift-cards/{id}` (`redemptions`:
  `applied_gift_cards`'tan sipariş, tutar, önceki ve sonraki bakiye,
  sipariş tarihi).
- `POST /gift-cards`: tek kart, `generate_gift_card_code()`, bakiye =
  tutar, isteğe bağlı `expires_on` ve `notes`; e-posta gönderilmez.
- `POST|PATCH /gift-cards/{id}`: `balance`, `expires_on` (boş dize süreyi
  kaldırır), `notes`; tutar ve kod değişmez.
- `DELETE /gift-cards/{id}` ve `POST /gift-cards/{id}/delete`: ekrandaki
  gibi satırı siler; siparişlerdeki harcama kaydı kalır.

Durum ekranın mantığıyla hesaplanır: son kullanma tarihi geçmişse
`expired` (bakiye ne olursa olsun), bakiye 0 ise `used`, değilse `active`.
Router `money` türüne sınır uygulamadığı için tutar (> 0) ve bakiye (≥ 0)
ile sütunun üst sınırı işleyicide denetlenir. Yazmalar `dry_run`
destekler, günlüğe uygulama ve anahtarla yazılır. Şema değişikliği yok.

**Doğrulandı (sandbox).** Liste, durum/sipariş/arama süzgeçleri, imleç;
oluşturma (201), 0 / ondalık / çok büyük tutar 422; bakiye ve süre
değiştirme, süreyi kaldırma, geçmiş tarih `expired`; -1 bakiye ve boş
istek 422; `X-Dry-Run` üç yazmada; iki silme yolu, 404; harcama satırı
tek kartta; izni olmayan uygulamaya 403 `insufficient_scope`; OpenAPI.

---

## 2026.4.10 — Çalışma Alanı: başka hizmetlerin MCP sunucuları takma adla, MCP istemcisi (10.82) (2026-10-13)

**Neden.** Site bugüne kadar yalnız MCP *sunucusuydu* (`mcp.php`): Claude
ve diğer istemciler siteye bağlanıyordu. Bu değişiklikle site bir MCP
*istemcisi* de oluyor: yönetici başka bir hizmetin uzak MCP sunucusunu
(GitHub, bir proje takip aracı, bir dokümantasyon sitesi) `@takma ad` ile
ekliyor; kanalda bu takma ada yazılan istek Pinegrap AI tarafından o
sunucunun izin verilen araçlarıyla yanıtlanacak (kanal tarafı ayrı
değişiklikle gelir). Bu parça istemciyi, sunucu dizinini ve araç izinlerini
getirir.

**İstemci** (`includes/mcp/client.php`). Streamable HTTP; stdio sunucuları
web sitesinden çağrılamaz. Önce güncel nesil (2026-07-28) denenir:
oturum yok, `server/discover`, her istekte `MCP-Protocol-Version`,
`Mcp-Method`, `Mcp-Name` başlıkları ve `_meta` içinde sürüm ile istemci
bilgisi. Sunucu bunu tanımazsa eski el sıkışmaya düşülür: `initialize`
(2025-11-25 önerilir; 2025-06-18 ve 2025-03-26 kabul), `Mcp-Session-Id`,
`notifications/initialized`. 401/403 ya da ağ hatasında düşülmez (kimlik
bilgisi ya da adres yanlıştır, eski sürüm denemek bir şey değiştirmez).
Yanıt JSON ya da SSE (`text/event-stream`) olabilir; SSE'de isteğin
kimliğine eşleşen mesaj aranır. Oturum 404 ya da -32022 ile düşerse bir kez
yeniden el sıkışılır. `tools/list` sayfalanır (en çok 10 sayfa, 200 araç);
araç şemaları nesne olarak çözülür ki boş `{}` diziye dönüşmesin. Arka
planda süren iş (`resultType` ≠ `complete`) şimdilik hata sayılır.
Bütün istekler `api_http_request()` üstünden gider: yalnız https, adres
DNS'te çözülüp iç ağ adresleri reddedilir, curl o adrese sabitlenir,
yönlendirme izlenmez. `api_http_request()`'e `response_headers` seçeneği
eklendi (oturum ve içerik türü başlıkları için).

**Dizin ve ekran** (`includes/workspace/mcp.php`, Çalışma Alanı Ayarları ›
"MCP sunucuları" kartı; yalnız yönetici). Takma ad: harfle başlar, 2–32
karakter `a-z0-9_-`, benzersiz; bir kullanıcı adıyla ya da ayrılmış adlarla
(`ai`, `claude`, `pinegrap`, `herkes`, `kanal` …) çakışamaz, böylece `@`
tek anlama gelir. Kimlik bilgisi: yok, Bearer jetonu, hizmetin kendi başlığı
(`Host`, `Content-Type`, `Accept`, `Cookie`, `Mcp-*` gibi bağlantının
kendi başlıkları reddedilir) ya da kullanıcı adı ve parola; JSON olarak site anahtarıyla
şifrelenir, ekrana geri yazılmaz (boş bırakılan alan saklananı korur),
satır sonu içeremez. En çok 20 sunucu. Kaydedince el sıkışılır ve araçlar
okunur; hata metni kaydedilir ve satırda görünür. Sunucu kapatılabilir,
araçları yeniden okunabilir, kaldırılabilir; her işlem etkinlik kaydına
yazılır.

**Araç izinleri.** Her araç kapalı başlar. Herkes tek bağlantıyla
(yöneticinin girdiği kimlik bilgisiyle) çalıştığı için yazan araç, yetkisi
olmayan birinin o hesapla iş yapması demektir (confused deputy); bu yüzden
yalnız sunucunun `readOnlyHint: true` dediği araçlar (`auto`) açılabilir.
Sunucu hiçbir ipucu vermiyorsa yönetici "yalnız okuyor" diyerek açabilir
(`auto_declared`); `destructiveHint`/`idempotentHint` gibi bir ipucu verip
`readOnlyHint` vermeyen araç yazan sayılır ve kapalı kalır. Yazan araçlar
kişi başı bağlantı ve onay ile ayrı değişiklikte gelecek. Araç adı
`^[A-Za-z0-9_./-]{1,128}$` dışındaysa liste dışı kalır. Listede yeniden
okununca kaybolan aracın izni silinir.

**Şema 10.82** (`upgrade_2026_4_10_workspace_mcp()`): `ws_mcp_servers`,
`ws_mcp_calls` (çağrı kaydı; bu parçada henüz yazılmıyor),
`ws_ai_requests.mcp_server_id`. Ayrıntı `CLAUDE-tam.md` sürüm tablosunda.
**2026.4.10'a zaten geçmiş siteler (dev dahil) tabloları almak için
Yazılımı Onar › 2026.4.9'dan yeniden koşturmalı.**

**Doğrulama.** `tests/mcp_client_test.php` (12 test): adres kuralları, SSE
okuma, kimlik eşleşmesi, güncel nesil el sıkışma ve iki sayfalık araç
listesi, `initialize`'a düşüş ve unutulan oturum, 401'de düşmeme,
2024-11-05'i reddetme, arka plan işi, araç normalleştirme, sonuç metni,
oturum kimliği, başlıkta CR/LF temizliği. Sandbox'ta: sitenin kendi
`mcp.php`'sine karşı iki nesilde de el sıkışma ve araç listesi; ekle,
kaydet, jetonun şifreli saklanması, boş alanın jetonu koruması, araç
izinlerinde yazan aracın reddedilmesi, ipucusuz aracın `auto_declared`
olması, kapat/aç/kaldır, etkinlik kaydı. Şema adımı üç kez koşturuldu.

**Doğrulanamayan.** Sandbox dış adresleri çözemediği için gerçek bir uzak
sunucu (GitHub MCP gibi) denenemedi; dev.pinegrap.com'da denenmeli.

---

## 2026.4.10 — Yayınla: yalnız düzenlenen sayfanın zamanı ve kaydı (2026-10-13)

**Kaynak.** Erdal'ın yerel testinin ikinci raporu (koordinatör üzerinden): tek
Yayınla tasarımdaki bütün sayfaları "düzenlendi" diye kaydediyor. Ortak
başlıktaki tek değişiklik log tablosuna 1 stil ve 31 sayfa satırı yazdı, 35
sayfanın değişiklik zamanı aynı ana çekildi. Welcome'daki Aktivite kartının
İçerik filtresi değişmemiş sayfalarla doluyor, sitemap lastmod her sayfada
değişiyor.

**Neden.** Editör, gösterdiği ortak bileşen değişen sayfayı da gönderiyor
(`_pgPageUnchanged()`): sunucu o sayfayı yeniden puanlıyor ve çeviri işine
alıyor, bu doğru. Ama `pg_designer_save_page()` gönderilen her sayfada
`page_timestamp` ve `page_user`'ı güncelliyor ve "sayfa düzenlendi" yazıyordu.

**Düzeltme.** Sunucu, güncellenen sayfanın kendi kolonlarını (ad, tasarım,
klasör, başlık, açıklama, arama, site haritası, noindex/nofollow, ana sayfa,
yorum ayarları ve `page_tree_json`) kayıtlı satırla karşılaştırıyor. Hepsi
aynıysa sayfa yalnız ortak bileşen yüzünden gelmiştir: satır yine yazılıyor ama
değişiklik zamanına ve yazarına dokunulmuyor, log satırı yazılmıyor. Karar
istekteki bir bayraktan değil, kayıtlı satırdan çıkıyor; elle hazırlanmış bir
istek gerçek bir düzenlemeyi kayıttan saklayamaz. Ortak bileşen sayfa ağacında
yalnız kimliğiyle durduğu için başlık değişikliği sayfa ağacını değiştirmiyor.
Yeniden puanlama (`$written`) ve çeviri işi aynen sürüyor. Stil satırı ("stil
düzenlendi") yerinde duruyor: ortak bileşen tasarımın parçası. Editör
değişmedi.

**Doğrulama (sandbox, Playwright).** Hakkımızda sekmesinde ortak başlıktaki
marka linkinin metni değişip Yayınla'ya basıldı. Öncesi: 29 sayfanın zamanı
değişti, 29 "sayfa düzenlendi" satırı yazıldı. Sonrası: hiçbir sayfanın zamanı
değişmedi, yalnız "stil düzenlendi" satırı yazıldı, yeni metin sayfada
görünüyor. Aynı sekmede h1 değişince yalnız `hakkimizda`'nın zamanı ve satırı
yazıldı. Sandbox metinleri eski hâline getirildi. dev.pinegrap.com'da
denenmedi.

---

## 2026.4.10 — Referrer-Policy sunucu kuralından çıktı; görev çekmecesinde hazır olmayan asistan seçilemiyor (2026-10-13)

### 1. `no-referrer` sayfaları IIS'te de referrer göndermiyor

**Neden.** Yerel testte IIS yanıtlarında `Referrer-Policy` iki kez
görüldü. Kök web.config `<customHeaders>` içinde
`strict-origin-when-cross-origin` ekliyordu. `waf_send_security_headers()`
bunu görüp kendi kopyasını göndermiyordu, ama `no-referrer` isteyen
betikler (`includes/oauth/authorize.php`, `includes/api/response.php`,
`router.php` süreli kısa link kapısı, `workspace_guest.php`,
`short_link_gone.php`, `includes/workspace/calendar_feed.php`) kendi
başlıklarını yazıyor, IIS de kendi değerini ona ekliyordu. Referrer-Policy
spec'i son geçerli değeri aldığı için bu sayfalar fiilen
`strict-origin-when-cross-origin` ile gidiyordu. Bir önceki düzeltmede
"açık kalan" olarak bırakılmıştı (aşağıda "Yerel test bulguları", madde 2).

**Nasıl.** Referrer-Policy artık yalnız PHP'nin: kök `web.config`'ten ve
yazılımın yazdığı kurallardan (`includes/server_config.php`: IIS bloğu,
Apache `.htaccess` bloğu, nginx örneği) ekleme satırı çıktı. IIS bloğunda
`<remove name="Referrer-Policy" />` kaldı: sunucu düzeyinden miras gelen bir
kopya aynı şeyi yapmasın. Artık `waf_server_adds_header('Referrer-Policy')`
false döner, `waf_send_security_headers()` her PHP yanıtına
`strict-origin-when-cross-origin` yazar ve `no-referrer` betikleri
`header()` ile onu değiştirir (hepsi `init.php` / router başlıklarından
sonra koşuyor). Testler: `tests/waf_headers_test.php` kök web.config'in ve
üç sunucu şablonunun Referrer-Policy eklemediğini denetler.

**Ödün.** Statik dosyalar (CSS, JS, görsel) başlıksız kalır. Tarayıcıların
varsayılanı aynı politika (`strict-origin-when-cross-origin`) olduğu için
pratikte fark yok. `<location>` ile yol başına kural denenmedi: router'dan
geçen kısa linkleri kapsamaz.

**Bilerek yapılmayan.** "Sunucu kurallarını onar" yalnız ekler, operatörün
dosyasından satır silmez; eski kurulumların web.config / .htaccess
dosyasındaki Referrer-Policy satırı kendiliğinden gitmez. Changelog
operatöre satırı silmesini söylüyor. O satır kaldıkça PHP normal
sayfalarda kendi kopyasını göndermemeye devam eder (çift başlık yok).

### 2. Görev çekmecesi: hazır olmayan asistan varsayılan değil

**Neden.** Çekmecenin Asistan bölümündeki seçim listesi ilk asistanla
açılıyordu; Pinegrap AI kurulu değilken "Pinegrap AI (hazır değil)" seçili
geliyor, "Asistana ver" sunucudan kurulum uyarısıyla dönüyordu.

**Nasıl.** `assets/js/workspace_task_delegate.js` (`composer()`): hazır
olmayan asistan listede kalır ama `disabled`; varsayılan ilk hazır
asistandır. Hazır asistan yoksa listenin başına "— Seçilmedi —" (`''`)
eklenir, o seçili açılır ve "Asistana ver" düğmesi pasif kalır. Görev zaten
bir asistandaysa liste hiç çizilmez, kayıtlı atama korunur (değişmedi).
Metin `dg_not_chosen` (`ws_delegation_js_strings()`, mevcut
"— Not selected —" anahtarı). `workspace.min.js` yeniden üretildi.

---

## 2026.4.10 — Mağazada "Faturalarım" (ERP Faz 8a) (2026-10-13)

**Neden.** `docs/_plan_erp_ozellikler.md` Faz 8 (#14), ilk parça: müşteri
ERP'de kesilen faturasını ancak e-postayla gelirse görüyordu; hesabında
faturaları, ne kadar borcu kaldığı ve ekstresi yoktu.

**Ne değişti.** Yeni sistem widget türü `account_invoices` ("My
Invoices"; `pg_sw_type_labels()`, `SW_TYPES`, oluşturucu
`_render_system_widget_account_invoices()`, `widgets_account.php`).
Oturumdaki üyenin kişisine bağlı cari `pg_erp_member_account()` ile
okunur (`ecommerce.php`; `erp_accounts.contact_id`, istek başına bir kez,
ERP yüklenmeden). Cari yoksa ya da ERP kapalıysa widget hiçbir şey
basmaz; bu yüzden ayrı bir ayar yok ve şema adımı yok. Okumalar yeni
`includes/erp/storefront.php`'de:

- `erp_storefront_documents()`: carinin kesilmiş satış faturaları ve
  iadeleri (taslak, iptal ve numarasız yok), en yeni önce, ayardaki sayı
  kadar (varsayılan 50, en çok 500). Kalan ve gecikme günü yaşlandırma
  modülünden (`erp_aging_invoices()`): alacak ekranlarıyla aynı rakam.
- `erp_storefront_document_state()`: iade / ödendi / gecikti / kısmen
  ödendi / açık (saf, `tests/erp_storefront_test.php`).
- `erp_storefront_account_figures()`: bakiye defterden
  (`erp_reconciliation_balance()`, cari dövizliyse kendi para biriminde,
  mutabakat mektubu nasıl yazıyorsa), vadesi geçen toplam yaşlandırmadan.
- `erp_storefront_document()`: belge bu carinin kesilmiş satış belgesi
  mi; değilse null.

Belgeler yeni `get_account_document.php`'den açılır: `?id=` faturanın
kesildiğinde saklanan PDF'i (`erp_archive_invoice()`; saklanmamışsa şimdi
üretip saklar, panel gibi), `?statement=1` mutabakat PDF'i
(`erp_reconciliation_pdf()`, varsayılan yılbaşından bugüne).
Panelin `get_erp_invoice_pdf.php`'si ERP hakkı ister ve değişmedi; yeni
uç yalnız belgenin oturumdaki üyenin carisine ait olup olmadığını sorar.
Başkasının belgesi, taslak, iptal ve carisi olmayan üye aynı 404'ü alır;
adres başına dakikada 30 istek (`waf_rate_exceeded()`, kapsam
`erp_doc`). Oturum yoksa giriş sayfasına yönlenir.

Widget'ın jetonları, bayrakları ve başlangıç ağacı (`style_designer.js`):
bakiye, vadesi geçen toplam, belge sayısı, "Ekstreyi indir"; satırda
numara, tür, tarih, vade, tutar, kalan, durum ("12 gün gecikmiş"),
gecikme günü, sipariş numarası ve PDF bağlantısı. Satır durum bayrakları
birbirini dışlar (`invoice_is_paid`, `_unpaid`, `_partial`, `_overdue`;
iade `invoice_is_return`), başlangıç ağacındaki rozetler bunlara bire
bir bağlı. Hiçbir şey göndermediği için Mesajlar düğümü kendi adıyla
damgalanır ve JS'te `_SW_NO_MESSAGES`'tadır. Altı hazır şablonun Hesabım
sayfasına hesap özeti ile güvenlik kartı arasına eklendi (şablon
sürümleri birer arttı).

**Bilerek yapılmayan.** Müşteri faturayı ödeyemez (ödeme linki bu fazda
yok, plan §4). Belge listesinde sayfalama yok; ayardaki sayıdan eskisi
ekstrede görünür. Bir kişiye birden çok cari bağlıysa ilk açılan
(en küçük id) okunur; panel de kişi başına bir cari varsayar.

---

## 2026.4.10 — Klinik şablonu: randevu, hekimler, hasta rehberi, blog ve hesaplar (2026-10-13)

**Neden.** Ürün sahibi "yerel hekim sitesi olsun, hesap sayfaları, randevu
vs. hepsi dahil, blog da var, hizmetler vs." diye tam donanımlı bir şablon
istedi. Bir önceki PR (#276) şablonun kullanacağı Bootstrap 5.3 bloklarını
paletin Bloklar sekmesine ekledi; bu PR şablonun kendisi.

**Nasıl.** `includes/design_templates/pinegrap-clinic.php`, Kitapçı
şablonunun yapısını izler: aynı düğüm kurucuları, ortak parçalar, sistem
widget'ları ve kurulumda editörün ürettiği başlangıç düzenleri
(`'tree' => 'starter'`). Randevu, ek modül istemeyen bir özel formdur:
form kaydı kendi sayfasını açar (`form_item_view`, yalnız gönderen ve
personel), altına yorum olarak yazışma gelir (iletişim sayfasındaki
görüşme düzeni), otomatik kayıt hastaya hesap açar. Durum (Talep edildi /
Onaylandı / Tamamlandı / İptal) yalnız personelin gördüğü bir seçim
listesidir (`office_use_only`); personel randevuyu açıp "Durumu Değiştir"
ile kaydı panelde düzenler. Randevularım listesi gönderen / izleyen /
düzenleyen süzgeciyle, Personel Alanı'ndaki liste bugünden itibaren
(`preferred_date` ≥ `current date`) tarih sırasıyla gelir. Görünüm
`modern-soft`, palet `ocean`; küçük resim `_pg_design_thumb_clinic()`.
Varsayılan olarak güzel adresler kapalı.

**Ne değişti.** 37 sayfa ve 33 widget:

- Ana sayfa (saatler kartlı hero, hızlı eylemler, hizmetler, rakamlar,
  hekimler, üç adımda randevu, yorumlar, son yazılar), Hizmetlerimiz
  (bölüm başına kapsam, süre, ücret; site aramasında bölüm başına sonuç),
  Hekimlerimiz (dört profil), Check-up (üç paket, hazırlık, SSS), Randevu
  Al (bölüm, hekim — "Fark etmez" seçili —, görüşme şekli, tarih, saat
  aralığı, hasta bilgileri, sigorta, neden, açık rıza, doğrulama), randevu
  sayfası, Randevularım, Hasta Rehberi (altı başlık + SSS), Hakkımızda,
  İletişim (konu seçimli görüşme), Bülten, Blog (dört örnek yazı, YZ
  özeti, yorumlar, tıbbi uyarı), Yardım Merkezi, 404.
- Hesap: giriş, kayıt, şifremi unuttum, şifre belirle, çıkış, Hesabım
  (randevu kartı; adres defteri yalnız e-ticaret açıksa), profil, şifre
  değiştir, e-posta tercihleri, adres defteri, gelen kutusu, görüşme.
- Personel (özel klasör): Personel Alanı (yaklaşan randevular, kısayollar),
  yeni blog yazısı, personel rehberine ekleme, personel rehberi.
- Altı e-posta sayfası: yeni randevu (personele), randevu talebiniz
  alındı (hastaya), yeni mesaj, mesajınız alındı, yeni yanıt, bülten
  hoş geldin.
- Her sayfada acil durum satırı (telefonda da görünür), dil seçici ve
  giriş bölgesi; başlık menüsü `xl` altında katlanır, telefon numarası
  `xxl`'de görünür. Hastanın telefonu kişinin cep telefonu alanına
  bağlıdır, sonraki randevuda ad, e-posta ve telefon dolu gelir.
- `tr.json`'a 403 anahtar; Türkçe örnek ücretler TL'dir (ör. `$90` →
  `₺2.800`), sayfa adları Türkçe (`randevu-al`, `randevularim`,
  `hasta-rehberi`, `hekimlerimiz`).

**Düzeltmeler (çekirdek).**

- `_pg_cf_controls()` (`includes/fn/custom_form.php`) tek seçimli bir
  listenin varsayılanını `selected` işaretli seçenekten okumuyordu;
  ziyaretçinin görmediği personel alanı (randevu durumu) boş kaydediliyordu.
  Artık seçili seçeneğin değeri (değer yoksa metni) varsayılan; çoklu
  listeye dokunulmaz. `tests/custom_form_default_test.php`.
- `_pg_sw_resolve_visibility()` (`includes/fn/widgets.php`) iç içe görünürlük
  aralıklarını çözmüyordu: "kayıt bulundu" aralığının içindeki "düzenleyebilir"
  ya da "bu oturumda gönderildi" aralıkları işaretleriyle sayfaya düşüyor,
  düzenleme düğmesi (boş adresle) ve yeni hesap kutusu herkese görünüyordu.
  Bu, Kitapçı şablonunun blog yazısı sayfasındaki Düzenle düğmesini de
  etkiliyordu. Tutulan aralığın içi artık kendi bayraklarıyla çözülür.
  `tests/widget_visibility_test.php`.
- Kitapçı (1.0.7) ve klinik şablonunun sayfa yolunda "Home" "Ev" diye
  çıkıyordu; ikisi de "Homepage" (Ana Sayfa) anahtarını kullanıyor.

**Bilerek yapılmayan.** Takvimde dolu saat denetimi yok: hasta bir saat
aralığı ister, personel saati e-postayla netleştirir ve durumu günceller.
Çalışma Alanı ya da takvim modülüne bağlanmadı; şablon yalnız mevcut form,
yorum ve hesap özelliklerini kullanır. Personel Alanı'ndaki "bugünden
itibaren" süzgeci tarih alanı boş kayıtları göstermez.

**Doğrulama.** `tools/lint.php`, `check_lang.php`, `check_bindings.php`,
`check_api_schema.php`, `check_copies.php`, `test.php`, `build_js.php
--check` temiz. Şablon deneme sitesine (`tools/setup_sandbox.sh`, Türkçe)
editörün başlangıç düzenleriyle kuruldu, uyarı yok; sayfalar Bootstrap 5.3.8
ile 1440, 1280, 1024 ve 390 piksel genişlikte çekildi, yatay kaydırma ve
JS hatası yok. Ziyaretçi olarak randevu gönderildi: hesap açıldı, kayıt
sayfası açıldı, durum "Talep edildi" kaydedildi; hasta düzenleme düğmesini
görmüyor, yönetici görüyor ve düğme kaydın panel ekranına gidiyor; kayıt
Randevularım'da ve Personel Alanı'nda listeleniyor; iki randevu e-postası
kayıtla doldu.

**Doğrulanamayan.** E-postaların gerçekten gönderilmesi (deneme sitesinde
posta yok) ve görsellerin yüklenmesi (dış adresler kapalı) denenmedi;
dev.pinegrap.com'da yerel oturum dener.

---

## 2026.4.10 — E-posta bağlı Google ya da Microsoft hesabından (2026-10-13)

**Neden.** Tersine OAuth planının birinci senaryosu (§4.1). Microsoft,
Exchange Online'da parolayla SMTP girişini (Basic auth) 2026 sonunda
kapatıyor; Gmail parolayla SMTP'ye yalnız uygulama parolasıyla izin
veriyor. Müşteri sitelerinin çoğu e-postayı bu iki hesaptan gönderiyor.

**Nasıl.** Yeni amaç dosyası `includes/connect/purposes/mail.php`
(`owner = site`; Google `gmail.send`, Microsoft Graph `Mail.Send`) her
sağlayıcı için iletinin nasıl teslim edileceğini de taşır (`send`: adres,
içerik türü, `raw`/`base64`, en büyük boyut, belirteç kapsamları).
`email()` (`includes/fn/mail.php`) ileti türü için bir bağlı hesap
seçiliyse (`SYSTEM_MAIL_CONNECTION` / `CAMPAIGN_MAIL_CONNECTION`,
`data/config.php`) iletiyi SMTP'deki gibi PHPMailer'la kurar (`isSMTP()`:
CRLF satır sonları; ekler, DKIM, List-Unsubscribe aynı), `preSend()` ile
bitirir ve sunucuya bağlanmak yerine sağlayıcının API'sine verir: Gmail
`upload/gmail/v1/users/me/messages/send?uploadType=media`
(`message/rfc822`, 35 MB'a kadar), Graph `me/sendMail` (MIME base64
`text/plain`, istek 4 MB'a kadar). SMTP'de yalnız zarfta giden Bcc
alıcıları sağlayıcının okuması için başlık olarak öne eklenir (imzadan
sonra; DKIM Bcc'yi kapsamaz). Posta kutusu kendi adına gönderdiği için
From bağlı hesabın adresi olur, ad korunur; Reply-To yoksa asıl gönderen
adresi Reply-To olur (`pg_mail_connection_from()`). Hata `email()`'in
mevcut yoluna düşer: günlük, `pg_mail_last_error()`, gönderene bildirim.
Kuyruk aynı `email()`'i çağırdığı için kampanya işi de bu yoldan gider.

**Ne değişti.** SMTP Ayarları'nda Sistem ve Kampanya kartlarında
"Gönderim yolu" seçimi (bu sunucu ya da `mail` amacıyla bağlanmış site
hesapları) ve uygulaması kurulu sağlayıcılar için "… hesabı bağla"
düğmeleri (formlar iç içe olamadığı için düğmeler gizli formları `form`
özniteliğiyle gönderir; dönüş yine SMTP Ayarları). Bir hesap seçiliyse
"Test Gönder" düğmesi SMTP bilgisi olmadan da görünür. Kayıtta yalnız
`mail` amacı taşıyan site hesabı seçilebilir.

**Bilerek yapılmayan.** Bağlantı başarısız olunca SMTP'ye ya da `mail()`'e
geri düşülmez: hata görünür kalır, hesap "Yeniden bağla" durumundaysa
günlükte yazar. Seçili hesap silinmişse ya da artık posta amacı
taşımıyorsa ileti eskisi gibi SMTP / `mail()` ile gider. Graph'ın 4 MB
sınırını aşan ekler için yükleme oturumu yok; ileti boyutu açıkça
reddedilir. Microsoft'un SMTP XOAUTH2 yolu bilerek seçilmedi: posta
kutusunda SMTP AUTH'un açık olmasını ister, Graph istemez.

---

## 2026.4.10 — Dış API: mağaza kurulumu (nakliye, bölge, vergi, ülke, para birimi) (2026-10-13)

**Sorun.** Siparişin hesaplandığı tanımlar API'de yoktu. Bir kargo ya da
pazaryeri entegrasyonu nakliye yöntemlerini, bölgeleri, vergi oranlarını,
ülke/il kodlarını ve kurları okuyamıyor, nakliye ücretini tahmin ediyordu.

**Değişiklik.** Yeni `includes/api/resources/store.php`, yalnız okuma, izin
`orders:read` (e-ticaret açık ve rol < 3 ya da e-ticaret yetkisi). Hepsi tek
sayfa döner (küçük kümeler):

- `GET /store/shipping-methods` (`enabled` süzgeci) ve
  `GET /store/shipping-methods/{id}`: taban ücret kademeleri
  (`base_rates`: `from_subtotal` → `rate`, `variable_base_rate` açıksa
  2.–4. kademe), birincil/ikincil ağırlık ve ürün başı ücret, ilk ürün
  hariç bayrakları, gerçek zamanlı taşıyıcı servisi, sunulduğu günler ve
  kesim saatleri (`offered_on`), sokak adresi / posta kutusu, geçerlilik
  aralığı (tablonun 1000-01-01 / 9999-12-31 "her zaman" değerleri null),
  hazırlık ve taşıma günleri, korumalı (yalnız personel), taşıyıcı unvanı
  ve VKN, `zone_ids`. Rota açıklaması ücretin `shipping.php`'deki
  hesaplanışını anlatır.
- `GET /store/zones`: bölgenin ek ücretleri, ülke kodları ve illeri.
- `GET /store/tax-zones`: oran (yüzde), ülke kodları ve illeri.
- `GET /store/countries` (`code` süzgeci): varsayılan ülke, posta kodu
  zorunluluğu, iller.
- `GET /store/currencies`: sembol HTML varlığından çözülür (`&euro;` → €),
  kur tabana göre, taban önce.

Para kuruş cinsinden (`api_money()`). Şema değişikliği yok.

**Doğrulandı (sandbox).** Sekiz nakliye yöntemi ve bölgeleri, 404, bölgeler
(International 205 ülke, Türkiye 81 il), vergi bölgeleri (Teksas 8.25, TR 18),
242 ülke / 143 il, TR süzgeci, yedi para birimi (₺ taban); MCP `tools/list`
altı araç, OpenAPI.

---

## 2026.4.10 — Paraşüt jetonu şifreli depoda (2026-10-13)

**Neden.** Tersine OAuth planının yan bulgusu (§4.14): Paraşüt'ün erişim ve
yenileme jetonu `data/parasut_token.json`'da düz metindi. Erişim jetonu
süresi boyunca Paraşüt API'sine doğrudan girer; dosya `data/` yedeğine de
giriyordu. Ürün sahibi "sorunları çöz" dedi.

**Nasıl.** `includes/fn/parasut.php`'de yalnız önbelleğin iki fonksiyonu
değişti: `_parasut_token_cache_read()` / `_parasut_token_cache_write()`
artık `pg_connect_service_get/put('parasut')` kullanıyor (bağlı hesaplar
tablosunda `provider = 'service'` satırı, site anahtarıyla şifreli). Eski
dosya ilk okumada şifreli kayda taşınıp silinir; şifreli kayıt zaten varsa
dosya artık kullanılmaz ve silinir. Şema 10.72 henüz koşmamış sitede dosya
eskisi gibi okunur ve yazılır (atomik yeniden adlandırma korundu). Kayıt
firma, istemci ve kullanıcı adının özetini (`owner`) taşır; Paraşüt
ayarları başka bir hesaba çevrilince eski jeton sunulmaz. Kilit ve yenileme
akışı (`parasut_get_token()`) değişmedi.

**Bilerek yapılmayan.** `includes/erp/edoc/` sürücüleri ve Paraşüt kimlik
bilgilerinin saklanışı (`config.parasut_credentials_enc`, zaten şifreli)
değişmedi. `PARASUT_TOKEN_CACHE` sabiti eski dosyayı bulmak için kaldı.

---

## 2026.4.10 — ERP: satın alma siparişleri dış API'de ve MCP'de (2026-10-13)

**Neden.** Faz 7'nin son parçası (`docs/_plan_erp_ozellikler.md` §3, "API
`GET/POST /erp/purchase-orders…` (Faz 6'nın kuralları)" ve olaylar). Sipariş
panelde yazılıp faturaya dönüşüyordu ama dışarıdan (muhasebe ya da depo
entegrasyonu, Claude bağlayıcısı) görünmüyordu. Şema yok.

**Rotalar** (`includes/erp/api.php` tablosu; işleyiciler ve sunucu yeni
`includes/erp/api_purchase_orders.php`'de, Faz 6'nın `api_stock.php` deseni):

| Rota | Kapsam | Alan fonksiyonu |
|---|---|---|
| `GET /erp/purchase-orders` (durum, `waiting`, `late`, tedarikçi, numara, tarih, `updated_since`, imleç) | erp:read | sorgu, `erp_purchase_order_progress()` |
| `GET /erp/purchase-orders/{id}`, `…/{id}/document` | erp:read | `erp_purchase_order()`, `erp_purchase_order_html()` |
| `POST /erp/purchase-orders`, `POST /erp/purchase-orders/{id}` (PATCH da) | erp:write | `erp_purchase_order_save()` |
| `POST /erp/purchase-orders/{id}/status` (`sent`, `cancelled`, `open`) | erp:write | `erp_purchase_order_mark_sent()`, `erp_purchase_order_set_status()` |
| `POST /erp/purchase-orders/{id}/invoice` | erp:write | `erp_purchase_order_to_invoice()` |
| `POST /erp/purchase-orders/{id}/email` | erp:write | `erp_purchase_order_mail_send()` |

Her yazma `X-Dry-Run` ile durur (`check_api_schema.php` denetler); 409 kodları
`purchase_order_not_editable`, `purchase_order_status_not_allowed`,
`purchase_order_not_invoiceable`, `purchase_order_not_sendable`. MCP araçları
rotalardan kendiliğinden çıkar (`erp_purchase_orders_list` …).

**Nesne `ErpPurchaseOrder`.** Başlık, toplamlar, `late` (pano kartının
okuması), satırlar ve her satırda `key`, `received_quantity`,
`drafted_quantity`, `outstanding_quantity`; siparişten yapılan fatura ve
taslaklar `invoices`'ta. Liste sayfası tek sorguda miktarları, faturaları ve
ürün kodlarını okur (`erp_api_purchase_order_context()`), satır başına sorgu
yok.

**Satır anahtarı API'de.** Panel formu anahtarı gizli alanla geri gönderiyor;
API'de karşılığı yoktu, bir istemci taslağın satırlarını yeniden yazınca
anahtar düşer, sipariş o satırı hiç sayamazdı. `erp_api_lines_in()` üçüncü
parametre aldı: sipariş güncellemesinde `lines[].key`, siparişten yapılmış
alış taslağının güncellemesinde `lines[].purchase_order_line`. Siparişten
yapılmamış taslakta anahtar 422, yeni siparişte ve teklifte bilinmeyen alan
422. `ErpInvoice` artık `purchase_order_id` ve satırlarda
`purchase_order_line` taşıyor; `GET /erp/invoices?purchase_order_id=` eklendi.

**Olaylar.** `erp_webhook_events()`'e `erp.purchase_order.created|sent|
received|cancelled|reopened` (paneldeki 1. ve 2. parçadan beri yayılıyordu,
abone olunamıyordu).

**Alan tarafında küçük değişiklikler.** Durum ve dönüşüm reddi tek yerde:
`erp_purchase_order_status_refusal()` (gönderildi, iptal, yeniden aç) ve
`erp_purchase_order_invoice_refusal()`; ekranlar ve API aynı cümleyi verir.
`erp_purchase_order_refresh()` durum değişmese de `updated_at`'i ilerletir
(alınan miktar değişti), `erp_purchase_order_to_invoice()` de; böylece
`updated_since` ile izleyen istemci siparişi yeniden görür.

**Doğrulanan.** Sandbox'ta curl ile: X-Dry-Run (200, `would`), aynı
Idempotency-Key ile ikinci istek aynı sipariş (`Idempotent-Replay: true`),
yeni siparişte `key` 422, beklenen tarih sipariş tarihinden önce 422,
anahtarlı güncelleme satırları korudu ve yeni satıra anahtar verdi, `sent`,
faturaya dönüşüm (201, taslak satırları anahtarlı), ikinci dönüşüm 409,
taslak API'den 2 satıra indirilip kesildi → `partial`, kalan taslak kesildi →
`received` (olay bir kez), iptal → dönüşüm ve e-posta 409, yeniden açma →
`partial`, kısmi siparişi değiştirme 409, e-posta X-Dry-Run, PDF, `late`,
`waiting`, imleç ve `updated_since`, OpenAPI'de 6 yol ve nesne.
`tests/erp_purchase_order_test.php`'ye 5 test (18 test).

**Doğrulanamayan.** dev.pinegrap.com'da Claude bağlayıcısıyla (yeni araçlar
için yeniden bağlanmak gerekir); gerçek e-posta gönderimi.

---

## 2026.4.10 — Dış API: kısa linkler; Geri Dönüşüm Kutusu'ndaki kısa link yönlendirmiyor (2026-10-13)

**Sorun.** Kısa linkler yalnız panelden (Kısa Linkler ekranı, dosya
yöneticisi) yönetiliyordu; bir kampanya aracı ya da asistan link açamıyor,
hedefini değiştiremiyordu. Ayrıca Geri Dönüşüm Kutusu'na taşınan kısa link
`router.php`'de hâlâ çözülüyor ve 301 ile yönlendiriyordu (sorgu `recycled`
sütununa bakmıyordu).

**Değişiklik.** Yeni `includes/api/resources/short_links.php`; izin
`pages:read` / `pages:write` (rol ≤ 2, Kısa Linkler ekranının kapısı).
Yardımcılar dosya yöneticisininkiler (`pg_short_link_read_request`,
`pg_short_link_clean_name`, `pg_short_link_state`, `check_name_availability`).

- `GET /short-links`, `GET /short-links/{id}`: en yeni önce; süzgeçler
  hedef türü, sayfa, tür (`mode`: permanent / timed / once), metin,
  `recycled`. Yanıtta linkin kendi adresi (`address`), gittiği yer
  (`target`), durum (open / used / expired), `expires_at`, `use_count`.
  Tek kullanımlık linkin adresi saklanmaz, yalnız `token_hint`.
- `POST /short-links`: ad (boşluk `_` olur; sayfa, dosya, klasör ya da
  başka kısa linkle çakışırsa 409 `name_taken`), hedef (`page_id`;
  ürün grubu ve ürün için katalog sayfası + `product_group_id` /
  `product_id`; `url` yalnız http(s) ya da site yolu; `file_id`),
  `tracking_code`. `mode=timed` için `duration` panelin seçenekleri
  (3600 … 7776000 sn). `mode=once` ad almaz; üretilen adres
  (`token_url`) yalnız bu yanıtta bir kez verilir, saklanan hash'tir.
- `POST`/`PATCH /short-links/{id}`: ad (tek kullanımlıkta 422), hedef
  alanlarından biri gelince hedef saklı olanla birlikte yeniden okunur ve
  türün kullanmadığı sütunlar boşaltılır (dosya yöneticisi gibi),
  `tracking_code`, süreli linke `renew` ile yeni süre. Geri Dönüşüm
  Kutusu'ndaki link 409 `already_recycled`.
- `DELETE /short-links/{id}` (ve `POST …/delete`): Geri Dönüşüm Kutusu'na
  (`recycled = 1` + `recycle_bin` satırı, dosya yöneticisi gibi);
  `permanent=true` doğrudan siler.

Çalışma Alanı misafir linkleri (`workspace_guest`) API'de görünmez (404).
`router.php`: bulunan kısa link `recycled` ise kısa link sayılmaz, ad sayfa
aramasına düşer (404); sütun olmayan eski şemada davranış aynı. Şema
değişikliği yok.

**Doğrulandı (sandbox).** Sayfa, ürün grubu, ürün, dosya ve adres hedefli
oluşturma; ad çakışması (kısa link ve sayfa) 409; `javascript:` ve `//`
adresleri 422; tek kullanımlık link: `token_url` bir kez, tarayıcıyla ilk
açılış 302 hedefe, ikincisi "kullanıldı" sayfasına, `used_at` /
`use_count` güncellendi; süreli link süre yok 422, `renew` ile uzatma;
hedef türü değişince eski sütunlar boşaldı; Geri Dönüşüm Kutusu'na taşıma,
adres 301'den 404'e döndü, ikinci silme ve değiştirme 409, `recycled=true`
listesi, `permanent=true` ile kutu satırı da silindi; misafir linki 404;
MCP `tools/list` ve OpenAPI.

---

## 2026.4.10 — Bağlı hesaplar: giden OAuth altyapısı (2026-10-13)

**Neden.** Ürün sahibi tersine OAuth planından (`docs/_plan_tersine_oauth.md`)
model A'yı seçti ve ilk beş senaryonun hemen başlamasını istedi. Beşi de
(Microsoft 365/Gmail ile posta, Search Console, buluta yedek, Microsoft ile
giriş, Çalışma Alanı'nda MCP istemcisi) aynı şeye dayanıyor: sitenin bir
dış hesaba bir kez bağlanıp belirteçlerini güvenle saklaması ve yenilemesi.
Microsoft parolalı SMTP'yi 2026 sonunda kapatıyor; posta senaryosu bu
altyapıyı bekliyordu.

**Nasıl.** `includes/fn/connect.php` her istekte yüklü bir modül: sağlayıcı
ve amaç tanımları klasörden okunur (`includes/connect/providers/*.php`,
`includes/connect/purposes/*.php`; yeni sağlayıcı ya da özellik bir dosya),
akış yetkilendirme kodu + PKCE S256, durum ve nonce oturumda, RFC 9207
`iss` denetimi. id_token imzası doğrulanmaz (OpenID Connect Core 3.1.3.7:
belirteç TLS üzerinden doğrudan token ucundan, istemci sırrıyla alınan
yanıtta gelir), ama `iss`, `aud`, `exp`, `nonce` ve `sub` denetlenir;
id_token yoksa userinfo ucu okunur. Refresh token ve kapsam kümesi başına
erişim belirteçleri tek bir şifreli alanda (`encrypt_string_with_iv()`,
site anahtarı) durur. Yenileme `GET_LOCK` altında, satır yeniden okunarak
yapılır; `invalid_grant` hesabı `reauth` durumuna alır ve günlüğe yazar,
geçici hatalar yalnız `last_error`'a düşer. Microsoft belirteci kaynak
başına verdiği için (`token_per_scope`) önbellek anahtarı kapsamların
özetidir ve yenilemede kapsam gönderilir; Google `access_type=offline` +
`prompt=consent` ile her bağlantıda refresh token verir. Google
uygulaması girilmemişse Google ile giriş ayarlarındaki uygulama kullanılır.

**Ne değişti.** Ayarlar › Bağlantılar › Bağlı hesaplar (`connections.php`):
sağlayıcı başına kart, bağlı hesaplar (amaç, bağlanma ve son kullanım,
son hata, durum), Denetle / Yeniden bağla / Bağlantıyı kes (Google'da izin
de geri alınır; Microsoft'un iptal ucu yok), uygulama formu (kurulum adımları, kopyalanabilir yönlendirme
adresi, istemci kimliği, gizli anahtar — kayıtlıysa geri gösterilmez —,
Microsoft dizini). Geri dönüş adresi `connect.php`: POST bağlantıyı
başlatır (site hesabı yönetici yetkisi ister), GET kodu alır ve ekrana
iletiyle döner; dönüş adresi yalnız panel betiği olabilir. Komut paletinde
"Bağlı hesaplar". Şema 10.72: `connect_apps`, `connect_accounts`.
Özelliklerin kullanacağı yüz: `pg_connect_start_fields()`,
`pg_connect_access_token()`, `pg_connect_api()`,
`pg_connect_service_get/put/delete()` (sunucudan sunucuya belirteçler).
`tr.json`'da "Client ID" "Müşteri Kimliği" yerine "İstemci Kimliği",
"Client Secret" "İstemci Gizli Anahtarı" oldu (Paraşüt ve ClearCommerce
alanları da aynı anahtarı kullanır).

**Bilerek yapılmayan.** Hiçbir özellik henüz bu bağlantıyı kullanmıyor;
posta gönderimi, Paraşüt belirteci ve kargo API'leri ayrı PR'lar. Kişinin
kendi hesabını bağlaması (`owner = person`) kütüphanede var, ekranı onu
kullanacak özellikle gelecek. Arka planda belirteç tazeleme işi yok:
belirteç kullanılırken yenilenir, Google'ın altı ay kullanılmayan izni
düşürmesi `reauth` olarak görünür.

---

## 2026.4.10 — Dış API ve MCP: ERP virmanı, çek/senet, kâr, kasa akışı ve pano (Faz 6, üçüncü parça) (2026-10-13)

**Neden.** `docs/_plan_erp_ozellikler.md` Faz 6 (#27), son parça: kasalar
arası para aktarma ve çek/senet API'de yoktu, çekler yalnız okunuyordu;
kâr raporu, kasa akışı ve ERP panosunun rakamları API'den alınamıyordu.

**Ne değişti.** Altı yeni rota `erp_api_routes()`'ta; MCP'de altı yeni
araç. Yazma işleyicileri yeni `includes/erp/api_cash.php`'de, raporlar
yeni `includes/erp/api_reports.php`'de; ikisi de panelin kullandığı
fonksiyonları çağırır. Yeni web kancası olayı yok.

| Rota | Kapsam | Fonksiyon |
|---|---|---|
| `POST /erp/cash-transfers` | `erp_cash:write` | `erp_post_transfer()` |
| `POST /erp/cheques` | `erp_cash:write` | `erp_cheque_create()` |
| `POST /erp/cheques/{id}/step` | `erp_cash:write` | `erp_cheque_step()` |
| `GET /erp/reports/profit` | `erp:read` | `erp_profit_report()` |
| `GET /erp/reports/cashflow` | `erp_cash:read` | `erp_cashflow_report()`, `_by_till()`, `_by_method()` |
| `GET /erp/dashboard` | `erp:read` | `erp_aging_summary()`, `erp_profit_report()`, pano kartlarının sayımları |

Ayrıntılar:

- **Kasa kapısı.** Kasayı yürüten üç yazma `erp_api_expense_cash_gate()`
  ile anahtarın `erp_cash:write` kapsamını ve sahibinin kasa hakkını
  sorar; üçü de `X-Dry-Run` ile denenebilir. Dönem kilidi 422
  `period_locked` (`date` alanıyla).
- **Virman.** Ekrandaki gibi: iki kasa da var ve aktif olmalı, aynı olamaz,
  döviz açıkken aynı para biriminde olmalı. `erp_post_transfer()` kayıtlı
  kur yoksa sessizce 1 kullanıyordu; API'de kayıtlı kur yoksa ve
  `exchange_rate` gönderilmediyse 422 `exchange_rate`, kayıtlı kur varsa o
  kullanılır. Yanıt iki hareketin numarası (`out_id`, `in_id`) ve iki
  kasanın güncel bakiyesi. Panelde de API'de de virman geri alınmaz; ters
  virmanla düzeltilir (açıklamada yazıyor).
- **Çek ve senet.** Kayıt ekrandaki `erp_cheque_create()`: alınan çek
  portföy kasasına tahsilat, verilen çek verilen çekler kasasından ödeme;
  kur ve para birimi denetimi provadan önce yapılır. Adımlar
  (`deposit`, `collect`, `endorse`, `bounce`, `pay`) ekrandaki
  `erp_cheque_step()`. Hangi adımın hangi durumdan atılabileceği
  fonksiyonun içindeydi; yeni `erp_cheque_step_from()`'a alındı,
  `erp_cheque_step()` ve API onu okur. Atılamayan adım 409
  `cheque_step_not_allowed` (mesajda şu anki durum). Ekranın kısıtları API'de
  de var: banka hesabı portföy kasası olamaz, ciro edilen cari aktif ve
  çekin geldiği cariden başka olmalı. Her adım yalnız kullandığı alanı
  alır (`cash_account_id` yalnız deposit/collect/pay, `account_id` yalnız
  endorse, `reason` deposit dışında); başka alan 422. Ciro ve karşılıksız
  `erp_post_receipt()` üzerinden geçtiği için `erp.receipt.created`
  yayılır (panelde de öyle).
- **Kâr raporu.** Dönem KDV raporuyla aynı okunur (`month` ya da `from` +
  `to`, en çok iki yıl): bunun için `erp_api_reports_vat()`'taki okuma
  `erp_api_report_period()`'a ayrıldı. Ekran siparişleri sayma seçimini
  çerezde tutuyor; API'de `orders` parametresi. Ürün ve cari satırları
  `limit` (varsayılan 50, en çok 500) kadar, toplam sayılarıyla.
- **Kasa akışı.** Panelde kasa hakkı isteyen ekran olduğu için
  `erp_cash:read` kapsamı ve sahibin kasa hakkı istenir (plan `erp:read`
  diyordu). Ekran bugünden sonrasını ve 1100 günden uzununu sessizce
  kırpıyor; API 422 ile söyler, rakamlar istenenden kısa dönemi kapsamasın.
  Satır etiketleri (yerelleştirilmiş tarih) yerine `start`/`end`.
- **Pano.** Panelin kartları: alacak ve borç (toplam, vadesi geçen, yedi
  gün içinde vadesi gelen, yaşlandırma, en eski borçlu cariler), bu ayın
  satış/kâr/gideri, ödenecek giderler, minimum stoğun altındaki ürün
  sayısı, kredi limitini aşan cariler, açık teklifler, bekleyen satın alma
  siparişleri. Ödenecek giderlerin sorgusu `erp_dashboard.php`'den
  `erp_expense_unpaid_summary()`'ye alındı, ekran da onu çağırır. Kasa
  kartı (`cash`: bakiyeler ve bugünün giriş/çıkışı) yalnız `erp_cash:read`
  tutan ve sahibi kasa hakkına sahip anahtara dolu gelir, ötekine `null`
  (panelin kasa kartını gizlemesi gibi). e-Belge kartı eklenmedi
  (`includes/erp/edoc/*` Erdal'ın yerel işi).
- `tools/check_api_schema.php` yeni nesnelerin sunucu–şema eşlerini de
  karşılaştırıyor.

**Doğrulama.** Sandbox'ta curl: virman (prova, aynı kasa, aktif olmayan,
olmayan, sıfır tutar, yalnız okuma anahtarı 403, gerçek 201 ve bakiyeler
100000 → 75000 / 500000 → 525000, aynı `Idempotency-Key` ile
`Idempotent-Replay: true`); döviz açıkken TRY → USD 422, kayıtsız kurda
422 `exchange_rate`, gönderilen kurla prova, kayıtlı kurun gönderilene
üstün gelmesi (41,2); kilitli dönem 422 `period_locked`. Çek: due_date
eksik, olmayan cari, aktif olmayan kasa, kilitli tarih, geçersiz kind,
prova, yalnız okuma 403; dört alınan ve bir verilen çek, tekrar istek
yeni çek açmadı; adımlar: geçersiz adım, 404, alınana pay 409, banka
yok/portföy kasası/aktif olmayan 422, adımın almadığı alan 422; bankaya
ver, tahsil (portföy → banka virmanı), aynı adım tekrar 409, ciro (aynı
cari ve cari yok 422, pasif cari 422), karşılıksız, verilen çekin ödenmesi;
dört adımın provası hareket yazmadı (15 → 15); döviz açıkken TRY portföy
→ USD banka 422. Raporlar: kâr (ay, `orders`, `limit`, hatalı ay, iki
yıldan uzun, `limit=0`), kasa akışı (gün, tek kasa + ay, bugünden sonrası,
ters tarih, 1100 günden uzun, olmayan kasa, geçersiz group 422; yalnız
`erp:read` anahtarı 403), pano (tam anahtar `cash` dolu, döviz açıkken USD
kasalar toplama girmedi; okuma anahtarı `cash: null`; bekleyen ve geciken
satın alma siparişi 2/1). Sahibinin kasa hakkı yokken kasa akışı ve virman
403, pano `cash: null`. OpenAPI'de altı rota ve beş nesne; MCP
`tools/list`'te altı araç (`erp_cheques_step` destructiveHint true),
`tools/call` ile pano, adım ve virman provası. `php tools/lint.php`,
`check_lang.php`, `check_api_schema.php`, `test.php` temiz;
`tests/erp_api_test.php` genişletildi.

**Doğrulanamayan.** IIS (dev.pinegrap.com); panel ekranlarının
(`erp_dashboard.php`, çek ekranı) tarayıcıda görünümü — değişiklik
ekranın kullandığı fonksiyonu taşımaktan ibaret, CLI'dan aynı değerler
okundu.

---

## 2026.4.10 — Tek dilli sitede sayfanın dili: büyük harfte Türkçe İ (2026-10-13)

**Kaynak.** Erdal'ın yerel test raporunun 6. maddesi: "Tasarımın dili `en`
olarak ayarlı ama metinler Türkçe; büyük harfe çevrilen metin MÜŞTERI
görünüyor."

**Neden.** `generate_style_code_from_tree()` kaydedilen gövdeye sabit
`<html lang="en">` yazıyor. Bunu sitenin diline çeviren adım
(`pg_tr_render_finalize()`) yalnız sitede hedef dil varken çalışıyordu; tek
dilli sitede sayfa "en" kalıyordu. Tarayıcı `text-transform: uppercase`'i
(Bootstrap `.text-uppercase`) sayfanın diline göre uyguluyor: "en"de
i → I, "tr"de i → İ.

**Düzeltme** (`includes/fn/translate.php`, `pg_tr_finalize()`). Hedef dilde
çizilmeyen her sayfada, kaynak dil (`pg_tr_source_language()`: Diller ve
Çeviri'deki kaynak dil, boşsa panel dili) "en" değilse, `<html>`'deki `lang`
eksikse ya da "en" ise kaynak dile çevriliyor. Stile elle yazılmış başka bir
dil (`lang="de"`) korunuyor. Dili olan sitelerde mevcut dil adımı her
sayfanın dilini zaten yazıyor; sonuç değişmiyor.

**Doğrulama (sandbox, kaynak dil boş, panel dili tr, hedef dil yok).**
Öncesi `/hakkimizda` `<html lang="en">`, Chromium'da `text-transform:
uppercase` "Müşteri Hizmetleri, İletişim, ılık" → "MÜŞTERI HIZMETLERI,
İLETIŞIM, ILIK". Sonrası `<html lang="tr">` → "MÜŞTERİ HİZMETLERİ, İLETİŞİM,
ILIK". `/anasayfa`, `/magaza` de `lang="tr"`. `php tools/test.php` geçti.
dev.pinegrap.com'da denenmedi.

---

## 2026.4.10 — Görsel editör: özellikler panelindeki değişiklik kendi geri alma adımını açıyor (2026-10-13)

**Kaynak.** Erdal'ın yerel test raporunun 1. maddesi: link adresi seçiciyle ya
da URL kutusuyla değişince ayrı geri alma adımı açılmıyor. Başlık metni →
link URL'si → Ctrl+Z yapılınca başlık metni de geri dönüyor.

**Neden.** Geçmiş, değişiklikten ÖNCEKİ hâlin anlık görüntüsüyle çalışıyor
(`saveState()`). Özellikler panelinin ortak `[data-prop]` işleyicisi metin
kutularında hiç anlık görüntü almıyordu: URL kutusuna yazılan ya da seçicinin
doldurduğu adres, bir önceki adımın içine düşüyordu. Açılır liste ve onay
kutusunda görüntüyü değişiklikten SONRA alıyordu: ilk Ctrl+Z aynı hâle
dönüp hiçbir şey yapmıyor, ikincisi iki düzenlemeyi birden geri alıyordu.
Erişilebilirlik alanlarının `[data-nodeattr]` işleyicisi de görüntüyü
değişiklikten sonra (`change`) alıyordu.

**Düzeltme** (`style_designer.js`). İki işleyici de görüntüyü değişiklikten
önce alıyor. Metin kutusunda yazma bir adım sayılıyor:
`saveState(_sdEditRun('prop:' + prop))` aynı alandaki kesintisiz yazmayı tek
adımda topluyor (`SAVE_STATE_RUN_MS`), başka alana ya da başka öğeye geçince
yeni adım açılıyor. Seçici `input` + `change` olaylarını birlikte gönderiyor;
işleyici yalnız `input`'u dinlediği için tek adım oluşuyor.

**Doğrulama (sandbox, Playwright).** Hakkımızda sekmesinde h1 satır içi
"BASLIK DEGISTI" yapıldı, ardından "Bize Yazın" linkinin adresi değişti.
Öncesi (yazarak da, seçicinin olaylarıyla da): 1. geri al başlığı ve adresi
birlikte geri aldı, 2. geri al kapalıydı. Sonrası: 1. geri al yalnız adresi
(`/iletisim`), 2. geri al başlığı geri aldı; yinele başlığı getirdi. Hedef
açılır listesi (`_blank`) için de 1. geri al yalnız hedefi geri aldı. Ortak
başlıktaki linkte (marka linki) metin ve adres ayrı adımlarda geri alındı.
dev.pinegrap.com'da denenmedi.

---

## 2026.4.10 — Dış API: takvim etkinliği ekleme, değiştirme, silme (2026-10-13)

**Sorun.** Takvimler API'de yalnız okunuyordu. Bir rezervasyon aracı ya da
asistan etkinlik açamıyor, saatini kaydıramıyor, yayına alamıyordu; Çalışma
Alanı aynı işi öneri olarak yapabildiği hâlde API yapamıyordu.

**Değişiklik.** `calendars` izin grubuna `calendars:write` eklendi; `calendars:read`
ile aynı kişilere verilir (rol < 3 ya da takvim yetkisi). Yeni rotalar
(`includes/api/resources/calendars.php`):

- `POST /calendars/events`: `name`, `start`, `end`, `calendar_ids` zorunlu;
  `all_day`, `short_description`, `description` (HTML, `pg_sanitize_rich_text`
  ile temizlenir), `location`, `show_start_time`, `show_end_time`, `published`,
  `unpublish_days`. Tüm gün etkinlik ilk günün 00:00'ından son günün 23:59'una
  yazılır; diğerinde bitiş başlangıçtan sonra olmalı (etkinlik ekranının
  kuralı). Geri kalan sütunlar ekranın başlangıç değerleriyle (yayında değil,
  45 gün, tekrar ve rezervasyon kapalı).
- `POST`/`PATCH /calendars/events/{id}`: yalnız gönderilen değişir; `start`,
  `end` ya da `all_day` gelince saatler saklı olanla birlikte yeniden
  hesaplanır. Salon ayıran tek seferlik etkinlik `check_calendar_event_location_availability`
  ile denetlenir (409 `room_taken`); salon ayıran tekrarlanan etkinliğin saati
  buradan değiştirilmez (409 `repeating_event_books_room`), çünkü ekran her
  tekrarı ayrı denetler. `calendar_ids` sahibin açabildiği takvimleri
  değiştirir, göremediği takvimler etkinliği tutar.
- `DELETE /calendars/events/{id}` (ve `POST …/delete`): ekrandaki gibi
  istisnalar, takvim ve salon bağları, kalan rezervasyon yerleriyle birlikte.

Yetki ekranlarla aynı: rol 3 sahibi yalnız atanmış takvimlerine yazar;
yayın yetkisi (`user_publish_calendar_events`) yoksa `published` /
`unpublish_days` 403, yayındaki etkinliği değiştiremez ve silemez (403).
Tekrar kuralı, rezervasyon, ürün, salon seçimi ve sonraki sayfa etkinlik
ekranında kalır. Şema değişikliği yok. Var olan uygulamalar yeni izni
kendiliğinden almaz; API ayarlarından verilmesi gerekir.

**Doğrulandı (sandbox).** Oluşturma, dry-run, tüm gün (00:00–23:59), bitiş
başlangıçtan önce 422, olmayan ya da boş takvim 422; açıklamadaki `script`,
`onclick` ve `javascript:` temizlendi; tüm gün aç/kapat; takvim değiştirme;
salon çakışması 409 ve bitişik saat kabul; tekrarlanan + salonlu etkinlikte
saat 409, ad değişikliği geçer; silme bağları temizledi, sonra 404. Rol 3
(takvim 1 atanmış, yayın yetkisi yok): başka takvim 422, yayınlama 403,
yayındaki etkinlik 403, göremediği takvim korunur, görünmeyen etkinlik 404.
`tests/api_calendars_test.php` saat kuralı için yeni test.

---

## 2026.4.10 — Görsel editör: olmayan kayıt, ürün ve kategoride 404 + noindex (2026-10-13)

**Kaynak.** Erdal'ın yerel test raporunun 2. maddesi:
`/blog-yazisi/olmayan-kayit-xyz` 200 dönüyor, "Yazı bulunamadı" gösteriyor,
noindex yok, kanonik `/blog-yazisi`. Klasik form detay sayfası (`/blog/olmayan`)
bu durumda zaten 404 veriyordu; görsel editörün widget'ları vermiyordu.

**Nasıl.** `pg_sw_item_missing()` (`widgets.php`): adresin istediği öğe yoksa
widget onu işaretler; yanıt 404 olur, `X-Robots-Tag: noindex` gider,
`get_page_content.php` sayfaya `<meta name="robots" content="noindex, follow">`
yazar ve kanonik bağlantıyı çıkarır. Bayrak sayfanın widget geçişinden önce
sıfırlanır. E-posta gövdesi ve SEO geçişi (başka bir isteğin içinde çizilen
sayfa) hiç işaretlenmez. İşaretleyen üç yer:

- **Form detay widget'ı** (`_render_system_widget_form_item_view`): `?r=` dolu
  ya da form güzel URL'liyken sayfa adının ardında bir ad var, ama kayıt yok
  veya ziyaretçi göremiyor. Erişimi olmayan kayıt da 404 olur; widget onu
  zaten "bulunamadı" gibi gösteriyordu (kaydın varlığı sızmasın diye). Aynı
  sayfada katalog widget'ı varsa ad onundur, işaretlenmez.
- **Ürün detay widget'ı**: adres ya da `?pid=` bir ürün ya da seçim tipi grup
  bulmadı. Aynı sayfada katalog listesi varsa ad önce onundur, işaretlenmez.
- **Katalog listesi**: adresteki kategori yok. Aynı sayfada ürün detay widget'ı
  varsa işaretlenmez (ürünü o gösterebilir).

Çıplak detay sayfası (`/blog-yazisi`, `/urun-detay`) 200 kalır. Liste sayfasının
ardındaki olmayan ad (`/blog-2/olmayan`) bu PR'da değişmedi: her sayfanın
ardındaki fazladan parça gibi 200 dönüyor.

**Doğrulama (sandbox).** Form 1109'da güzel URL geçici açıldı:
`/blog-yazisi/olmayan-kayit-xyz` ve `/blog-yazisi?r=NOPE123` → 404,
`X-Robots-Tag: noindex`, robots meta, kanonik yok, gövdede "bulunamadı"
mesajı. `/blog-yazisi/ilk-yazi`, `/blog-yazisi`, `/blog-yazisi/` → 200 ve
kanonik. `/urun-detay/olmayan-urun`, `/urun-detay?pid=999999`,
`/magaza/olmayan-grup` → 404 + noindex; `/urun-detay/Recurring_Donation`,
`/magaza/Donations`, `/magaza` → 200. `php tools/test.php` 405 geçti.
dev.pinegrap.com'da denenmedi. IIS'te sunucunun kendi hata sayfası 404 gövdesini
değiştirirse (httpErrors), hata sayfası widget'ının 404'ünde de aynısı olur.

---

## 2026.4.10 — Dış API: yorum moderasyonu (2026-10-13)

**Sorun.** Ziyaretçi yorumları yalnız yorumlar ekranından yönetiliyordu; bir
entegrasyon ya da asistan bekleyen yorumları göremiyor, yayınlayamıyordu.

**Değişiklik.** Yeni `includes/api/resources/comments.php` ve yeni izin
grubu `comments` (`comments:read`, `comments:write`; rol ≤ 2, yorumlar
ekranının kapısı). İzin ayrı tutuldu: SEO için `pages:read` verilmiş bir
uygulama bekleyen yorumları ve ziyaretçi adlarını okumamalı.

- `GET /comments`, `GET /comments/{id}`: en yeni önce; süzgeçler sayfa,
  konu (`item_type`: page, submitted_form, calendar_event, product_group,
  product; `item_id`), durum (published / pending / scheduled), öne çıkan,
  metin, tarih. Zamanlanmış yorumda `publish_at` ve `cancel_if_newer`.
- `POST /comments/{id}`: `published`, `featured`. İki `published` değeri de
  zamanlamayı bitirir. Yayında olmayan bir yorum yayınlanınca yorumlar
  ekranının gönderdiği e-postalar gider (sayfayı izleyenler; form kaydında
  kaydı gönderen); dry-run `emails` ile söyler. Form kaydı sayfasında
  `submitted_form_info` sayacı yorumlar ekranındaki gibi yeniden yazılır.
- `DELETE /comments/{id}` (ve `POST …/delete`): ekli dosya başka yorum
  paylaşmıyorsa onunla, paneldeki bildirimiyle birlikte.

Ziyaretçinin adı ve metni API'den değiştirilmez (alan gönderilirse 422).

**Doğrulandı (sandbox).** Liste süzgeçleri ve imleç; bekleyeni yayınlama ve
öne çıkarma, zamanlanmışı bekletme (`publish_at` temizlendi), boş istek ve
`message` 422; form kaydındaki yorumu bekletince sayaç 1→0, yayınlayınca
0→1 (dry-run `emails: true`); silme ve sonra 404; günlük satırları.
Denenmeyen: gönderilen e-postanın kendisi (sandbox'ta posta sunucusu yok;
örnek verideki form kaydı silinmiş olduğundan eski e-posta fonksiyonu uyarı
yazdı, yorumlar ekranında da aynısı olur).

---

## 2026.4.10 — Yerel test bulguları: ülke kısıtında panel girişi, IIS'te çift başlık, MCP 401 metni, Bağlı uygulamalar, iki PHP uyarısı, çekmecede not başlığı (2026-10-13)

**Neden.** dev.pinegrap.com'daki yerel test turunun ikinci raporu (OAuth/MCP,
Welcome, Workspace, dil yönlendirmesi, WAF). Bir önemli bulgu, dört küçük.

### 1. Ülke kısıtı açıkken yurt dışındaki personel panele giremiyordu

**Belirti.** "Listedekileri reddet" + listede AQ, "Yönetim paneli" muaf.
AQ'dan `/pinegrap/` → 302 `/giris` → 403 (`geo-country`). Ayar metni "panel
ekranları ve giriş sayfası her ülkeden açık kalır" diyor.

**Kök sebep.** `waf_country_is_panel_request()` yalnız yazılım dizinindeki
betikleri panel sayar; doğru. Ama `index.php` → `get_login_screen()` girişi
sitenin kendi sayfasına gönderir: görsel editörle kurulmuş sitede giriş
widget'ını taşıyan sayfaya (`pg_sw_screen_page_url('login')`), eski sitede
`login` türündeki sayfayı panelin adresinde çizer (sayfanın teması ve
görselleri yine router/get_file'dan, kısıtlı). Şifremi unuttum
(`forgot_password.php`) ve sıfırlama bağlantısının açtığı yeni şifre sayfası
da sitenin sayfalarıdır. `/pinegrap/login.php` diye bir betik yoktur; o adres
router'da site sayfası sayılır, 403 orada doğrudur.

**Seçilen yol: panel kendi ekranlarında kalır.** WAF'a "bu yol sitenin giriş
sayfası mı" diye sayfa çözdürmek düşünüldü, alınmadı: `waf.php`
functions.php'den bağımsızdır, tasarım sayfasının widget'ı taşıyıp taşımadığı
`pg_sw_widget_pages()`'in `shared_components` + sayfa ağacı LIKE taramasıdır
(kopyası WAF'ta ikinci bir tanım olurdu), ve sayfa açılsa bile teması ve
görselleri dosya yolundan reddedilirdi — ayar metni "sitenin sayfaları ve
dosyaları kısıtlı kalır" diyor. Bunun yerine:

- `waf_country_keeps_to_panel()` (waf.php): güvenlik duvarı engelliyor
  (`block`), ülke kısıtı açık, panel muaf, adres izin listesinde değil ve
  ziyaretçinin ülkesi reddediliyor. Karar saf bir fonksiyonda
  (`waf_country_keeps_to_panel_decision()`), testli. Belirleyemezse `false`:
  panel eskisi gibi davranır. İzleme modunda kimse reddedilmediği için
  `false`.
- `pg_sign_in_kept_to_panel()` / `pg_sign_in_screen_url()` (forms.php).
  `get_login_screen()` ve `get_forgot_password_screen()` bu ziyaretçiye
  sitenin sayfasını (eski ya da tasarım) değil yerleşik formu çizer; yerleşik
  ekranlar yalnız panelin statik dosyalarını yükler.
- `forgot_password.php`: bu ziyaretçinin yönlendirmeleri `forgot_password.php`
  üstünde kalır, sıfırlama bağlantısı `set_password.php?k=…`'yı açar.
  Diğer ziyaretçiler için bağlantı sitenin yeni şifre sayfasıdır, sayfa
  yoksa eskisi gibi durur.
- `set_password.php` GET'te yerleşik formu çizer (`get_set_password()`,
  `page_type` artık geçiliyor; `page_id` 0 ile sistem şablonu). Önceden GET
  CSRF kontrolüne düşüp "oturum süresi doldu" diyordu. Hata ve onay dönüşleri
  ile "yeni e-posta iste" linkleri `pg_sign_in_screen_url()`'den.
- 2FA (`mfa.php`), cihaz sınırı ve giriş sonrası `welcome.php` zaten panel
  betikleri, muaf.

### 2. IIS'te `X-Content-Type-Options` ve `Referrer-Policy` iki kez

Önceden "bilinen kozmetik" olarak bırakılmıştı (2026.4.x güvenlik başlıkları
bölümü): web.config `<customHeaders>` ile ekliyor, PHP de gönderiyor, IIS
ikisini birleştiriyor. Sunucu kuralı statik dosyalar için kalmalı, PHP kopyası
kuralsız sunucular için gerekli. Şimdi PHP, IIS altında
(`SERVER_SOFTWARE` Microsoft-IIS) sitenin kökündeki web.config'in
`<customHeaders>` içinde bu başlığı `<add>` ettiğini görürse kendi kopyasını
göndermez (`waf_server_adds_header()`, `waf_iis_custom_headers()`; yorumlar
atılır). `nosniff` gönderen her yer `waf_send_nosniff()` /
`pg_send_nosniff()` üzerinden geçer (API, MCP, OAuth uçları, Workspace
misafir dosyası, ERP muhasebeci paketi, gelen kutusu dosyası, get_file'ın
belge türleri). Apache'nin `Header always set`'i PHP'nin kopyasını zaten
değiştirir; nginx'in `add_header`'ı buradan okunamaz, orada çift kalır.

**Açık kalan:** `no-referrer` isteyen ekranlar (OAuth onay ekranı, API
dokümanı, misafir sayfası, kısa link) IIS'te sunucunun
`strict-origin-when-cross-origin` kopyasıyla birleşir; tarayıcı son geçerli
değeri alır. PHP sunucunun eklediğini kaldıramaz; düzeltmesi web.config'te
o yollar için `<location>` kuralıdır, bu iş kapsamında değil. → Kapandı: en üstteki "Referrer-Policy
sunucu kuralından çıktı" bölümü.

### 3. MCP 401 metni

`api_authenticate()` kimliksiz istekte "API anahtarı ve gizli anahtar
gereklidir" diyordu; `/mcp.php` OAuth'la çalışır. `PG_MCP_ENTRY` tanımlıyken
metin uygulamanın siteye bağlanıp jeton alması gerektiğini söyler. Kod
(`credentials_missing`) ve `WWW-Authenticate` meydan okuması aynı.

### 4. Bağlı uygulamalar

- Kişinin ekranında (`connected_apps.php`) her kartta bağlantının adresi
  (`/mcp.php`, `/pinegrap/mcp.php`, `/pinegrap/integration.php`). MCP iki
  adreste yanıt verir; aynı Claude'un iki bağlantısı iki aynı kart gibi
  görünüyordu. Birleştirilmedi: ikisi ayrı jetondur, biri sonlanınca öteki
  çalışır.
- `oauth_clients.last_used_timestamp` yalnız jeton verilirken
  güncelleniyordu; `api_device_authenticate()` cihaz satırının son kullanımını
  yazarken (dakikada bir) istemcininkini de yazar.

### 5. PHP uyarıları

- `edit_user.php`: son yönetici kendini düzenlerken rol kartları kapalıdır,
  `role` gönderilmez; kaydetme `$_POST['role']`'u okuyordu. Artık kaydedilen
  rol (`$saved_role`) kullanılır; rol gönderilmemişse `user_role` hiç
  yazılmaz (boş değer gevşek sunucuda 0 olurdu).
- `seo.php` `update_visitor_page_data()`: `init_tracking()` bot, başka
  Pinegrap kurulumu ve izleme botu için ziyaretçi kaydı açmaz; fonksiyon
  `visitor_id` yokken çıkar. Saatlik özet sayacı (`pg_record_visitor_page_view()`)
  yine önce çalışır, davranışı değişmedi.

### 6. Görev çekmecesinde "Notlarım" başlığı

`ws_notes_js_strings()` ekranın dizgeleriyle (`ws_js_strings()`)
`array_merge` ile birleşir ve ikisi de `'notes'` anahtarını tanımlıyordu:
not ekranınınki ("My Notes") çekmecenin "Notes" başlığını eziyordu. Not
ekranının anahtarı `my_notes` oldu; JS'te `t('notes')`'u yalnız çekmece
başlığı okuyor, `my_notes`'u okuyan yok (menü `nav_notes`'u kullanır). Diğer
Çalışma Alanı dizge tablolarında farklı metinle tekrarlanan anahtar yok
(misafir sayfasınınki ayrı tablodur).

---

## 2026.4.10 — Dış API: menüler ve menü öğeleri (2026-10-13)

**Sorun.** Siteyi API ya da MCP üzerinden kuran bir asistan sayfa açıp
içeriğini önerebiliyordu ama sitenin menüsüne bağlantı ekleyemiyordu; menü
yalnız panelden düzenleniyordu.

**Değişiklik.** Yeni `includes/api/resources/menus.php`, kapsam
`pages:read` / `pages:write` (menü sitenin kendisidir, sayfalarla aynı
karar: rol ≤ 2):

- `GET /menus`, `GET /menus/{id}`: menü ayarları (efekt, açılır konumlar,
  sınıflar) ve öğeler, sitenin çizdiği sırayla düz liste (`parent_id`,
  `level`, `order`); `url` sayfanın adresi ya da `link_url`. Üstü silinmiş
  öğe sitede çizilmediği için listede de yok.
- `POST /menus`, `POST /menus/{id}`: menü açma ve görünüş ayarları; panelde
  olduğu gibi (`view_menus.php` designer kapısı) sahibin rolü 0–1 olmalı,
  yoksa 403. Ad kuralı menü ekranınınki (harf, rakam, tire; benzersiz, 409
  `name_taken`).
- `POST /menus/{id}/items`, `POST /menus/{id}/items/{item_id}`, `DELETE`
  (ve `POST …/delete`): öğe ekleme, değiştirme, taşıma (`parent_id`,
  `after`: 0 ilk, kardeş kimliği ardına; kendi altına taşıma 422), alt
  öğeleriyle silme (`removed` sayısı, dry-run ile önce görülebilir). Sıra
  her yazımda o seviye için 1, 2, 3… diye yeniden yazılır.

**Kararlar.** Menünün adı tasarımlarda menüyü yerleştiren addır
(`<menu>ad</menu>`, editörün menü öğesi); API'den değiştirilmez ve menü
API'den silinmez, ikisi de menüyü kullanan her tasarımda boş bir yer
bırakırdı. Bağlantı ya sayfa ya adres: sayfa verilince adres, adres
verilince sayfa temizlenir (menü öğesi ekranı gibi). Geri Dönüşüm
Kutusu'ndaki ve taslak sayfaya bağlantı verilmez; adres yalnız http, https,
mailto, tel ya da bu sitede bir yol (`//` ile başlayan ve `\` içeren
reddedilir). Günlüğe uygulama adıyla yazılır.

**Doğrulandı (sandbox).** Liste ve tekil; menü açma (geçersiz ad, ad
çakışması, dry-run), ayar değiştirme, `name` gönderilince 422, rolü 2 olan
sahibe 403 (öğe yazımı açık); öğe ekleme (sayfa, adres, mailto, yeni
pencere, güvenlik, `after: 0`), taslak sayfa, `javascript:`, `//evil.com`,
sayfa ve adres birlikte reddi; iç içe ekleme, döngü reddi, üst değiştirme
ve sıra, başka üstün öğesinin `after` olarak reddi, alt öğeleriyle silme
(dry-run sayısı), olmayan menü ve öğe 404; MCP araçları ve OpenAPI
(`Menu`, `MenuItem`). Denenmeyen: sitede menünün çizimi (veri panelin
yazdığıyla aynı sütunlara yazılıyor).

---

## 2026.4.10 — Dış API ve MCP: ERP irsaliyeleri, stok sayımları ve minimum stok yazılıyor (Faz 6, ikinci parça) (2026-10-13)

**Neden.** `docs/_plan_erp_ozellikler.md` Faz 6 (#27), ikinci parça:
irsaliye ve stok API'de ve MCP'de yalnız okunuyordu. Virman, çek/senet ve
raporlar üçüncü parçada.

**Ne değişti.** Sekiz yeni rota `erp_api_routes()`'ta; MCP'de sekiz yeni
araç. İşleyiciler yeni `includes/erp/api_stock.php`'de ve panelin alan
fonksiyonlarını çağırır. Yeni iki olay `erp_webhook_events()`'ta.

| Rota | Fonksiyon |
|---|---|
| `POST /erp/waybills` (`invoice_id`, `order_id` ya da `account_id` + `lines`) | `erp_waybill_data_from_invoice()` / `_from_order()` + `erp_waybill_create()` |
| `POST /erp/waybills/{id}/cancel` | `erp_waybill_cancel()` |
| `POST /erp/waybills/{id}/invoice` | `erp_waybill_invoice()` |
| `POST /erp/stock-counts`, `POST …/{id}/items`, `POST …/{id}/apply`, `POST …/{id}/cancel` | `erp_stock_count_create()`, `erp_stock_count_add()` / `erp_stock_count_save()`, `erp_stock_count_apply()`, `erp_stock_count_cancel()` |
| `POST /erp/stock/minimums` | `erp_stock_minimums_save()` |

Ayrıntılar:

- **İrsaliye.** Panel formu nasıl doluyorsa öyle: faturadan (siparişten
  kesilmiş fatura o siparişin irsaliyesi olur, fatura bağlı), siparişten
  ya da cari + satırlarla. Gönderilen alanlar faturanın ya da siparişin
  verdiğinin yerine geçer; gönderilen `lines` kısmi sevkiyattır. Boş
  alıcı carinin kartındaki adrestir — panel formundaki bu kural yeni
  `erp_waybill_ship_to_default()`'a alındı, form da onu çağırır. Sipariş
  ya da faturanın ikinci irsaliyesi 409 `order_already_shipped` /
  `invoice_already_shipped` (panel bu durumda var olana yönlendiriyordu;
  `erp_waybill_create()` yalnız siparişi sorduğu için fatura kontrolü
  API'de). Taslak, iptal ya da alış faturası 409 `invoice_not_shippable`.
  Başlık reddi alan adıyla döner (`erp_api_waybill_field()`): `ship_time`,
  `carrier_tax_number`, `driver_id_number`. Şoför TCKN yazılır ama
  API'den geri okunmaz (var olan sunucu kuralı).
- **Cari açılması ve prova.** Carisi olmayan müşterinin siparişinde
  `erp_waybill_data_from_order()` müşteri kaydından cari açıyordu; prova
  bunu yapmamalı. Fonksiyona `$open_account` (varsayılan true) eklendi:
  false iken yazmadan okur, cari yoksa `account_id` 0 döner;
  `erp_waybill_header_build()`'e de `$account_required` (varsayılan true).
  İşleyici önce yazmadan denetler, prova orada durur, gerçek çağrıda cari
  açılıp irsaliye yazılır. Panel çağrıları değişmedi.
- **Faturalama.** Siparişin irsaliyesi siparişin faturasıyla kesilir;
  `POST /erp/orders/{order_id}/invoice` ile aynı ön denetimler
  (`erp_api_order_invoice_check()`, PR #270'teki işleyiciden ayrıldı) ve
  aynı 422 `period_locked`. Sipariş zaten faturalıysa irsaliye ona bağlanır
  ve 200 döner. Elle yazılmış irsaliye katalog fiyatlarıyla taslak olur.
- **İptal.** Faturalanmış irsaliye 409 `waybill_invoiced`, iptal edilmiş
  409 `waybill_cancelled`. `erp_waybill_cancel()` artık
  `erp.waybill.cancelled` yayar (`erp_event_waybill()` olay adını
  parametre alır; arşiv yalnız kesimde); panelden iptal de yayar.
- **Sayım.** `items` sırayla ve hepsi ya da hiçbiri (tek işlem): ürün
  `product_id` ya da `code` (barkod ya da ürün kodu) ile; `mode` add
  (varsayılan, `quantity` yoksa 1 — bir okutma), set ya da remove. Barkod
  okutmanın ürün bulunduktan sonraki kısmı yeni `erp_stock_count_add()`'e
  ayrıldı, `erp_stock_count_scan()` onu çağırır. Uygulamanın provası kaç
  ürünün stokunun değişeceğini söyler; boş sayım 409 `stock_count_empty`,
  açık olmayan 409 `stock_count_not_open`. `erp_stock_count_apply()` yeni
  `erp.stock_count.applied` olayını yayar (`erp_event_stock_count()`;
  panelden de). Denetim izinde yeni nesne türü `stock_count`.
- **Minimum stok.** `minimums: [{product_id, minimum}]`, en çok 500; 0
  temizler. Alan fonksiyonu stok takip etmeyen ürünü sessizce atlıyordu;
  API 422 ile söyler. Yanıt `changed` ve ürünlerin güncel seviyeleri.
  Plandaki `GET /erp/stock/minimums` açılmadı: `GET /erp/stock/levels`
  minimumu ve `low=true` süzgecini zaten veriyor, ikinci bir okuma rotası
  aynısı olurdu.
- Yanıt yardımcıları `erp_api_waybill_answer()` ve
  `erp_api_stock_count_answer()` `api_resources.php`'de; GET rotaları da
  onları kullanır.

**Doğrulama.** Sandbox'ta curl: sayım başlat (prova, aynı
`Idempotency-Key` ile tekrar `Idempotent-Replay: true`), ürün numarası ve
barkodla say, set, remove, tekrar isteğin ikinci kez saymadığı, uygula
(prova "changes": 1; stok 9986 → 9990, 0 sayılan ürün stok dışı), uygulanmış
sayımda değiştirme/uygulama/iptal 409, boş sayım 409, iptal; yalnız okuma
anahtarı 403. Minimum: doğrulamalar (eksi, tekrar eden ürün, stok takip
etmeyen, olmayan ürün, 1000000 üstü, bilinmeyen alan), kaydet, temizle,
`GET /erp/stock/levels?low=true`. İrsaliye: on yedi ret durumu; faturadan
yaz (plaka büyük harf, saat 09:30), aynı fatura 409; carisi olmayan
siparişte prova cari açmadı (2 → 2), gerçek çağrı cariyi açıp yazdı;
siparişin faturasından kısmi satırla (1,5 KGM); elle yazılan irsaliyeyi
faturala (taslak) → taslağı sil → irsaliyeyi iptal (olay denetim izinde);
siparişin irsaliyesini faturala (kesildi); sonradan faturalanan siparişin
irsaliyesi bağlandı (200). OpenAPI'de rotalar ve iki olay; MCP
`tools/list`'te sekiz araç, `tools/call` ile sayım başlatma ve prova.
Panelin kullandığı `erp_stock_count_scan()` ve `erp_waybill_ship_to_default()`
CLI'dan sandbox veritabanında. `tests/erp_api_test.php` genişletildi.

**Doğrulanamayan.** IIS (dev.pinegrap.com); web kancasının teslimi
(sandbox'ta DNS olmadığı için abonelik açılamadı; olaylar denetim izinde
görüldü); barkod tablosu olan bir mağaza (kod ürün koduyla denendi).

---

## 2026.4.10 — Dış API: görsel tasarımlar listesi ve tasarıma taslak sayfa ekleme (2026-10-13)

**Sorun.** API ve MCP görsel tasarımın sayfalarını okuyup onlara değişiklik
önerebiliyordu ama yeni sayfa açamıyordu; hangi tasarımların olduğu da
yalnız sayfaların üzerinden görülebiliyordu. Siteyi MCP üzerinden kuran bir
asistan "Hizmetler sayfasını ekle" diyemiyordu.

**Değişiklik.** `includes/api/resources/design.php`:

- `GET /design/designs` (`design:read`): Görsel Sayfa Düzenleyici'nin
  tasarımları (`pg_designer_list_designs()` ile aynı koşul), sayfa ve taslak
  sayısı, ana sayfanın tasarımda olup olmadığı, `state` (empty / live /
  draft / mixed, `pg_designer_design_state()`), başladığı şablon. Kimlikle
  sayfalanır.
- `POST /design/pages` (`design:write`, dry-run): tasarıma yeni sayfa. Kayıt
  düzenleyicinin kendi kaydıyla (`pg_designer_save_page()`, `page_draft` 1)
  yapılır; sayfa her zaman taslak klasörüne gider, `folder_id` yayınlanınca
  gideceği klasördür (verilmezse en üst klasör; klasör uçlarının hedef
  denetimi: kutu ve taslak klasörü olmaz, sahibin klasöre yazma hakkı
  gerekir). `copy_from` aynı tasarımın bir sayfasının yerleşimini kopyalar
  (düzenleyicideki "Sayfayı çoğalt" gibi; başlık, açıklama ve ayarlar
  kopyalanmaz). Kendi formu olan sayfa kopyalanmaz (409 `page_has_form`):
  formun alanları ve kayıtları sayfaya aittir, kopyanın formunu düzenleyici
  kaydederken kurar. Ad, Sayfalar ekranındaki gibi boşluk ve `&` → `_`;
  `? # % \ < > "` reddedilir; ad bir sayfa, dosya, kısa bağlantı ya da kök
  klasörle çakışırsa 409 `name_taken`. Kayıt `page.created` olayını yayar
  ve günlüğe uygulama adıyla yazılır.
- Tasarım sayfası cevabına `draft` eklendi.
- Düzeltme: `GET /design/pages` imleci `$cursor['id']` okuyordu, oysa
  `api_cursor_decode()` `v` ve `i` verir; ikinci sayfa yine ilk sayfayı
  döndürüyor, sayfalayan istemci döngüye giriyordu. `i` okunuyor.

**Neden taslak.** `design:write` "yazmak önermektir" kuralıyla verilir:
uygulama sitede görünen hiçbir şeyi kendi başına değiştirmez. Taslak sayfa
siteye çıkmaz; içeriği öneriyle yazılır, bir tasarımcı uygular ve yayınlar.
Düzenleyicinin kaydı yalnız açık sekmelerini yazar, API'nin açtığı sayfa
açık bir düzenleyicinin kaydıyla silinmez; sayfayı yeniden yükleyince sekme
olarak gelir.

**Doğrulandı (sandbox).** Tasarım listesi ve sayfalama; dry-run; boş ve
`copy_from` ile sayfa (ağaç kopyalandı, `page_tree_code` üretildi,
`page_drafts` satırı yayın klasörüyle yazıldı); ad çakışması, geçersiz
karakter, olmayan tasarım, görsel olmayan tasarım, olmayan klasör, başka
tasarımın sayfası, formlu sayfa reddi; yeni sayfaya `page` önerisi; MCP
`tools/list` ve OpenAPI (`Design`, `/design/designs`, `/design/pages`).
Denenmeyen: gerçek düzenleyicide sekmenin açılması (bulut oturumunda tarayıcı
yok).

---

## 2026.4.10 — ERP: satın alma siparişinden alış faturası, teslim durumu, minimum stoktan sipariş (2026-10-13)

**Neden.** Faz 7'nin ikinci parçası (`docs/_plan_erp_ozellikler.md` §3, akışın
3–4. adımı, minimum stok bağlantısı ve pano kartı). İlk parça (10.58) siparişi
yazdırıp gönderiyordu; mal geldiğinde siparişle fatura arasında bağ yoktu.
Şema yok: kolonlar 10.58'de açılmıştı.

**Faturaya dönüşüm.** `erp_purchase_order_to_invoice()` siparişin her satırı
için *kalan* miktarı (sipariş − kesilmiş faturalarda alınan − taslaklarda
olan) alış faturası **taslağına** yazar: siparişin fiyatı, iskonto oranı,
vergi ve tevkifatı; bugünün tarihi, vade carinin ödeme vadesi, döviz
siparişte yabancıysa bugünün kayıtlı kuru (yoksa taslak kursuz kaydedilir,
kesilirken sorar). Not siparişin notu + "Satın alma siparişi <no>".
`erp_invoice_draft_save()` artık `purchase_order_id`'yi (yalnız
`array_key_exists` ise, `quote_id` gibi; formdan yeniden kaydetmek bağı
korur) ve satır başına `source_line`'ı yazar (`erp_invoice_lines_have_source()`
ile kolon varsa). Taslak editörü anahtarı gizli alanda taşır; kesme adımı
satırları `UPDATE` ettiği için anahtar korunur. İptal edilmiş ya da tamamı
gelmiş siparişten ve kalanı olmayan siparişten taslak açılmaz. Fatura
kopyalanırken (`add_erp_manual_invoice.php?copy=`) anahtarlar boşaltılır:
kopya siparişe bağlı değildir.

**Türetilen durum.** `erp_purchase_order_quantities()` siparişe bağlı
(`purchase_order_id`), alış yönlü, `doc_type = 'invoice'` faturaların
satırlarını `source_line`'a göre toplar: taslak dışı ve iptal dışı olanlar
"alınan", taslaklar "taslakta". İade faturası sayılmaz (iade ayrı belge,
siparişe bağlanmaz). `erp_purchase_order_progress()` /
`erp_purchase_order_receipt_state()` saf fonksiyonlar: her satır tamamen
geldiyse `received`, bir şey geldiyse `partial`, yoksa `sent_at`'e göre
`sent`/`open`; fazla gelen tam sayılır. `erp_purchase_order_refresh()` bunu
`erp_event_invoice()`'tan çağrılır: alış faturası kesilince
(`erp.invoice.created`) ya da iptal edilince (`erp.invoice.cancelled`),
işlem commit'inden sonra. Güncelleme okunan duruma koşullu `UPDATE`; son
mal geldiği anda `erp.purchase_order.received` bir kez çıkar. İptal edilmiş
sipariş yenilenmez; "Yeniden aç" yeniler (iptal → yeniden aç → `partial`
sandbox'ta denendi).

**Ekranlar.** Sipariş ekranında "Teslim alınanlar" kartı (satır başına
sipariş edilen / alınan / taslakta / gelecek; bağlı faturalar ve taslaklar
durumlarıyla) ve "Alış faturası taslağı oluştur" (açık, gönderilmiş ya da
kısmi siparişte, kalan varsa). Fatura ekranında ve taslak ekranında
"<no> numaralı satın alma siparişinden" bağlantısı. `erp_stock_minimums.php`'de
işaret kutuları ve "İşaretlenenler için satın alma siparişi" (ayrı GET formu,
`form=` özniteliğiyle; minimum kaydetme formuna karışmaz):
`add_erp_purchase_order.php?products[]=` satırları
`erp_purchase_order_rows_for_products()` ile doldurur — miktar
`max(1, ⌈minimum − mevcut⌉)` (`erp_purchase_order_suggested_quantity()`),
fiyat son alış maliyeti (yoksa 0), vergi ürünün oranı ya da varsayılan oran;
tedarikçiyi operatör seçer. ERP panosunda "Bekleyen satın alma siparişleri"
kartı (açık + gönderilmiş + kısmi; teslim günü geçen varsa kırmızı ve
sayısı), `erp_purchase_orders.php?status=waiting`'e gider.

**Doğrulama.** Sandbox'ta planın kanıtı: minimum stoktan iki ürün (2 adet,
minimum 10 ve 5 → 8 ve 3 öneriliyor) + kargo satırıyla 3 satırlı sipariş →
taslak (3 satır, anahtarlar dolu) → ikinci dönüşüm "her satır zaten bir
faturada ya da taslakta" → taslak 2 satıra indirilip kesildi → sipariş
`partial`, stok 2→7 → kalan (A 3, B 3) yeni taslak → kesildi → `received`,
olay bir kez, stok 7→10 ve 2→5 → ikinci fatura iptal → `partial`, stok geri
→ sipariş iptal, dönüşüm reddi, yeniden aç → `partial`; taslak silinince
kalan geri geliyor; fatura kopyasında anahtarlar boş; pano kartı ve geç
teslim sayısı. `tests/erp_purchase_order_test.php`'ye 5 test (13 test).

**Doğrulanamayan.** IIS (dev.pinegrap.com) ve e-Belge sağlayıcısı açık
mağazada alış faturası kesme.

---

## 2026.4.10 — Dış API: klasörler ve sayfa güncellemesinde yorum ayarları, meta anahtar kelimeler, klasör taşıma (2026-10-13)

**Sorun.** Dış API sayfanın `folder_id`'sini veriyordu ama klasörün kendisini
okumanın, açmanın ya da sayfayı bir klasöre taşımanın yolu yoktu; siteyi MCP
üzerinden kuran bir asistan bölüm açamıyor, hangi klasörün kime açık
olduğunu göremiyordu. Sayfa güncellemesi yorum ayarlarını ve meta anahtar
kelimeleri yazmıyordu (Çalışma Alanı'nın Claude önerileri bunları panel
yoluyla zaten yazıyordu).

**Çözüm** (yeni `includes/api/resources/folders.php`, kapsam `pages:read` /
`pages:write`; şema değişikliği yok).

- `GET /folders` (parent_id, q, archived; id imleci), `GET /folders/{id}`:
  ad, üst klasör, seviye, sıra, **etkin erişim** (`access`: kendi türü ya da
  üstten gelen; `access_inherited`), arşiv, `system` (`drafts` |
  `recycle_bin`), `recycled`, doğrudan içindeki klasör/sayfa/dosya sayısı
  (sayfa başına üç `GROUP BY` sorgusu). Geri Dönüşüm Kutusu ve içindekiler
  listede yok, kimlikle okunur.
- `POST /folders` (name, parent_id — verilmezse en üst klasör, access —
  varsayılan `inherit`, order, archived). Stil sütunları 0 (miras):
  `add_folder.php`'de stil seçemeyen kişinin aldığı değer.
- `POST|PATCH /folders/{id}`: ad, sıra, arşiv, üst klasör, erişim. Taşımada
  döngü (klasörün kendi dalına taşınması) reddedilir, alt dalın seviyeleri
  tek geçişte düzeltilir (`api_folder_relevel()`).
- `DELETE /folders/{id}` + `POST …/delete`: yalnız boş klasör (klasör ekranının
  kuralı, 409 `folder_not_empty`); `aclfolder` satırları da silinir. En üst
  klasör, taslaklar klasörü ve kutu 409 `system_folder_protected`.
- **Erişim genişletme API'de yok.** Klasörün ya da sayfanın sunulduğu erişim
  yalnız aynı kalabilir ya da daralabilir (public < guest < registration <
  membership < private); daha çok kişiye açacak yazım (tür değişikliği, özel
  bir üstten çıkaran taşıma, sayfayı daha açık klasöre taşıma) 409
  `access_widening_refused`. Gerekçe: satır yazıldığı an içindekiler herkese
  sunulur ve bunu içeriği gören bir kişi yapmalı. Kural saf fonksiyonlarda
  (`api_folder_access_walk()`, `api_folder_access_widens()`,
  `api_folder_within()`), `tests/api_folders_test.php`.
- Taslaklar klasörü ve kutu hedef olarak kullanılamaz (422): ikisini kendi
  ekranları doldurur.
- `POST /pages/{id}`: `meta_keywords`, `comments`, `comments_open`,
  `comments_publish`, `comments_login` (Çalışma Alanı'nın `page` değişiklik
  türüyle aynı sütunlar) ve `folder_id`. Ana sayfa (409
  `home_page_protected`), sistem sayfaları (409 `system_page_protected`),
  taslak (409 `page_is_draft`) ve kutudaki sayfa (409 `already_recycled`)
  taşınmaz.
- Sayfa cevabına `meta_keywords`, `comments` nesnesi (`enabled`, `open`,
  `auto_publish`, `login_required`), `draft`, `recycled` eklendi.
- Panel: API ayarlarında Sayfalar satırının açıklaması, konsolda Klasörler
  grubu. MCP araçları kendiliğinden geldi (`folders_list` … `folders_delete`).

**Doğrulama.** Sandbox'ta (`tools/setup_sandbox.sh`) sunucu uygulaması
anahtarıyla gerçek isteklerle: liste/tekil, oluşturma (dry-run dahil),
yeniden adlandırma, arşiv, daraltma, genişletme reddi (tür ve taşıma), döngü
reddi, en üst klasör, boş olmayan klasör, kutu ve kutudaki sayfa yolları;
sayfada meta anahtar kelime ve yorum ayarları, daraltan taşıma, genişleten
taşıma reddi, ana sayfa ve giriş sayfası reddi veritabanından okundu. MCP
`tools/list` yeni araçları ve silmenin `destructiveHint`'ini, OpenAPI yeni
yolları ve `Folder` nesnesini veriyor. Denenmeyen: rolü 2 (yönetici) olan
sahibin klasör hakkı (pages:write rol ≤ 2'ye verilir, `pg_folder_edit_access()`
onlara her klasörde evet der).

---

## 2026.4.10 — Görsel editör: panel ipuçlarında ^^token^^ yerine bağlama seçenekleri, form detayında alan etiketleri (2026-10-13)

**Kaynak.** Koordinatörün notu: #248'de detay sayfası ipuçları bağlama
seçeneğinin adına çevrilmişti, panelde ^^token^^ gösteren ipuçları
kalmıştı. Kural: sayfa içeriği ve tasarımcının gördüğü yönlendirme
^^__x^^ yer tutucusu kullanmaz.

- **Widget ayarlarındaki ipuçları token adı söylüyordu.** 37 ipucu
  (giriş formu, giriş bölgesi, sepet bağlantısı, dil seçici, şifremi
  unuttum, kayıt, üyelik, çıkış, şifre belirleme, profil, e-posta
  tercihleri, adres defteri, hesap güvenliği, Hesabım, sepet, sipariş
  özeti, takvim, ödeme, katalog filtresi, form listesi döngüsü, form
  alanı adı) artık Veri Bağla listesindeki seçeneğin adını söylüyor:
  `_swBindLabel(tür, token)` seçeneği widget'ın kendi token paletinden
  okur; `_swLinkHint()`, `_swTextHint()`, `_swStaffHint()` ortak
  cümleleri kurar. Bağlı öğenin araç çubuğu ipucu da (`Veri bağlı: href
  → …`) seçenek adını gösterir. Veri Bağla panelinin giriş cümleleri
  "tokenler" yerine "alanlar" der. Yerel test raporu (madde 3): href'i
  bağlı linkteki not `form_item_view` yerine "Detay sayfası adresi"
  yazıyor.
- **Giriş bölgesi ve sepet bağlantısında Veri Bağla paneli yanlış
  widget'ı görüyordu.** `_getSystemWidgetRegionType()` bu iki türü
  tanımıyor, `form_list_view` dönüyordu: içlerindeki bir öğe seçilince
  panel "önce widget'a bir form seçin" diyor, kendi paletini
  (`SW_LOGIN_REGION_TOKEN_GROUPS`, `SW_CART_LINK_TOKEN_GROUPS`) ve
  görünürlük koşullarını (`SW_VISIBILITY_FLAGS`) hiç sunmuyordu. Kendi
  paleti olan her tür artık kendi adıyla döner. Bağlı link alanındaki
  not da ("URL ... bağlamasından geliyor") token yerine seçenek adını
  söyler.
- **"Field label tokens" ayarı işe yaramıyordu.** Form detayında açılınca
  sunucu `<alan>__label` değerini dolduruyordu ama Veri Bağla listesinde
  bunu seçmenin yolu yoktu (yalnız "Özel…" ile elle yazılabiliyordu).
  Ayarın adı "Alan etiketleri"; açıkken liste "Alan etiketleri" başlığı
  altında her alanın etiketini sunar, canvas etiketin kendisini gösterir.
- **E-posta tercihleri kutusunun adı token taşıyordu.** Hazır ağaç liste
  kutusunu `contact_group_^^__group_id^^` adıyla kuruyordu. Kutunun adı
  artık `contact_group`; sunucu her satırda `contact_group_<liste id>`
  yapar (`_pg_email_preferences_name_box()`). Eski ağaçlardaki ad da aynı
  yoldan okunur. Ödeme widget'ının alıcı başlığı `^^__recipient_name^^`
  metni yerine örnek metinle gelir (bağlama aynı).

Sandbox'ta doğrulandı: 15 widget panelindeki ipuçlarında ^^ kalmadı,
Türkçe metinler seçenek adlarıyla okunuyor. Sepet bağlantısının linki
seçilince panel öncesinde "önce widget'a bir form seçin" diyordu;
sonrasında sepet alanlarını ve "Sepette ürün var / Sepet boş"
koşullarını sunuyor, araç çubuğu ipucu "href → Sepet sayfası adresi …".
Form detayında (blog yazısı) ayar açıkken listede `title__label=Başlık`
vb. var; başlığa bağlanınca canvas ve yayındaki sayfa `Başlık` yazıyor.
E-posta tercihleri
sayfası eski ve yeni adla aynı kutuları (`contact_group_11` …) çiziyor;
yeni adla kaydedilen seçim sayfa yeniden açılınca korunuyor.
dev.pinegrap.com'da denenmedi.

---

## 2026.4.10 — Görsel editör: hizmet, randevu, saat, şube, menü ve etkinlik blokları (2026-10-13)

**Neden.** Ürün sahibi bir yerel hekim ve bir kurumsal kafe şablonu istedi
ve "bu iki şablon için yeni Bootstrap 5 bileşenleri eklensin" dedi. Paletin
Bloklar sekmesinde hizmet kartı, fiyat listesi, çalışma saatleri, şube,
menü ve etkinlik gibi yerel işletmenin her sayfada kullandığı parçalar
yoktu; operatör bunları başlık, paragraf ve kartlardan elle kuruyordu.
Bloklar şablonlardan önce geldi ki her tasarım (şablonla kurulmamış olanlar
da) aynı parçaları kullanabilsin.

**Nasıl.** `assets/js/style_designer.js`'te `createFromPalette()`'in bileşen
dalına tek bir aile eklendi: tür öneki `services-`, `steps-`, `booking-`,
`hours-`, `locations-`, `menu-`, `events-`, `notice-` olan ya da
`team-profiles` / `testimonial-ratings` olan bloklar ortak yardımcıları
(`_bkSection`, `_bkRow`, `_bkCol`, `_bkMenuItem`…) bir kez tanımlayan bir
`if` içinde üretilir. Palet öğeleri `_sdBlockPaletteItems()`'a, gruplar
`_sdBlockPaletteGroups()`'a eklendi (Hizmetler ve Randevu, Saatler ve
Şubeler, Yiyecek ve İçecek; `events-` İçerik Listeleri'ne, `notice-` Durum
ve Yardımcılar'a). Tam genişlikte bölüm oldukları için hepsi kökte kaba
sarılmayanlar listesinde (`_noWrapCtypes`). Görselli üç blok mevcut oran
kurallarını (`pg-ratio-16x9`, `pg-ratio-1x1`) `_SD_BLOCK_CSS_NEEDS` üzerinden
Stiller paneline yazar; yeni CSS kuralı yok, gerisi Bootstrap 5.3
yardımcıları.

**Ne değişti.** On sekiz blok:

- Hizmetler: Simgeli Kartlar (altı kart, kartın tamamı link —
  `stretched-link`), Görselli Kartlar (süre rozeti, fiyat ve düğme), Fiyat
  Listesi (iki sütun `list-group-flush`).
- Adımlar: Numaralı (dört kart, numara rozeti ve simge).
- Randevu: Eylem Çağrısı (renkli panel, randevu ve `tel:` düğmesi),
  İkiye Bölünmüş (görsel + kontrol listesi + iki düğme).
- Çalışma Saatleri: Kart (gün başına satır, kapalı gün soluk), Konum
  (16:9 harita yeri, adres, saat, telefon, yol tarifi).
- Şubeler: Kartlar (görsel, adres, saat, telefon).
- Menü: Sekmeler (Bootstrap `nav-pills` + `tab-pane`, her bölmede iki
  sütun), İki Sütun, Görselli Kartlar (kare görsel, rozet), Pano
  (`data-bs-theme="dark"` bölüm, boy fiyatlı tablo), Günün Önerileri.
  Menü satırı ad, fiyata uzanan kılavuz çizgi (`border-bottom` taşıyan boş
  `flex-grow-1`), açıklama ve diyet rozetlerinden oluşur.
- Etkinlikler: Liste (`<time datetime>` tarih yaprağı).
- Duyuru: Şerit (sayfa üstü acil durum satırı, `tel:` bağlantısı).
- Ekip: Profiller (yatay kart, uzmanlık, dil rozetleri, randevu düğmesi).
- Referans: Puanlar (ortalama puan ve yıldızlar + üç yorum kartı).

Bütün görünen metinler `_sdT()` ile; 340 yeni anahtar `tr.json`'a eklendi.
Türkçe fiyatlar gerçekçi TL tutarlarına çevrildi (ör. `$2.80` → `₺95`).
Örnek görseller picsum.photos, örnek adlar mevcut kanondan (Jane Cooper →
Ayşe Yılmaz).

**Bilerek yapılmayan.** Randevu ve rezervasyon blokları form içermiyor.
Paletteki statik `<form>` blokları (Contact: Form gibi) gönderilmez; çalışan
form Pinegrap'ta özel form widget'ıdır. Bloklar bu yüzden düzen ve eylem
çağrısı verir, form şablonların kendi sayfalarında widget olarak gelir.
Harita için bir bileşen yok; konum blokları yer tutucu taşır.

**Doğrulama.** `node --check`, `tools/check_lang.php` temiz. Blok ağaçları
editörün dışında küçük bir Node betiğiyle HTML'e çevrildi ve Bootstrap
5.3.8 ile 1280 ve 390 piksel genişlikte, İngilizce ve Türkçe çekildi:
sütunlar, kılavuz çizgiler, koyu pano ve kartlar beklenen gibi.

**Doğrulanamayan.** Bloklar canlı editörde sürüklenip bırakılarak
denenmedi; dev.pinegrap.com'da yerel oturum dener.

---

## 2026.4.10 — Çalışma Alanı: hatırlatıcı, gecikme ve görev notu bildirimleri; başlıkta hızlı not (2026-10-13)

**Neden.** Ürün sahibinin üç isteği: panelin service worker'lı bildirim
sistemine Çalışma Alanı'nın eksik uçlarını tamamlamak, bir "hızlı not"
düğmesi ve Çalışma Alanı açıksa Notlarım'a bir yol.

**Tarama.** `sw.js` yükte metin taşımaz; uyanınca `push_pending`'e sorar,
o da zilin satırlarını okur. Çalışma Alanı'ndan cihaza giden tek yol
`ws_notify()` → `ws_inbox` → zil (`notifications.target_user_id`, 4.83) →
`push_queue` (`source = 'ws'`). Bu yola zaten bağlı olanlar: anma, görev
verme, görevin bitmesi (açana), görev notunda anma, engelin kalkması, kanala
davet, not paylaşımı ve değişikliği, Claude/Pinegrap AI'ın not cevabı, onay
isteği/kararı/hatırlatması, okundu isteği hatırlatması, misafir mesajı,
zamanlanmış eylem bildirimi ve hatası, tartışma, asistanın görev cevabı.
Bağlı olmayan dört şey bulundu: (1) görev hatırlatıcısı yalnız e-postaydı,
adresi olmayan kişiye hiç ulaşmıyordu; (2) gecikmiş görev için hiçbir şey
yoktu; (3) görevdeki kişiler, anılmadıkları sürece göreve yazılan notu
duymuyordu; (4) asistanın cevap bildirimi onay bekleyen önerileri
söylemiyordu. Bir de: görevi açmak o görevin gelen kutusu satırlarını okunmuş
saymıyordu, zil ve cihaz kuyruğu okunmamış kalıyordu. Push uçları
(`push_config`, `push_subscribe`, `push_unsubscribe`, `push_test`,
`push_pending`) ve dış API'nin `/notifications` uçları eksiksiz; yeni uç
gerekmedi. Kişi başı, tür bazında bildirim tercihi sistemde yok; kanalın
"Hiçbir zaman" ayarı anmaları susturuyor ve öyle kalıyor.

**Ne değişti.**

- `reminders.php`: hatırlatıcı her kişiye `task_reminder` gelen kutusu satırı
  yazar (zil + cihaz), e-posta adresi olanlara ayrıca e-posta gider.
  Kime: `ws_task_notice_user_ids()` (görevdeki ekip üyeleri, kimse yoksa
  açan); `ws_task_reminder_people()` bunun adresi olanları. Dönüşte `sent`
  artık giden hatırlatma, `failed` e-postası kimseye gitmeyen hatırlatma.
  Çekmecedeki metinler "Saat ve hatırlatma", "Hatırlat" ve "bildirimlerinde
  ve e-postayla" oldu (eski anahtarlar tr.json'da duruyor).
- Gecikme: `ws_task_overdue_run()`. Açık görev bitiş anını geçince
  görevdekilere bir kez `task_overdue`. An: saatli görevde saati, saatsizde
  ertesi gün `WS_TASK_REMIND_DAY_TIME` (09:00) — listelerin "gecikti" demeye
  başladığı gün. Görevde bir şey saklanmaz, şema adımı yok: `cron_runs`'ta
  `workspace_overdue_notices` satırı bir su çizgisi tutar, her koşu
  çizgiyi bulduğu yerden şimdiye korumalı UPDATE ile taşıyarak kendi
  aralığını alır (iki koşu aynı anda gelirse biri çıkar). İlk koşu yalnız
  çizgiyi kurar (eski gecikmeler haber değildir); geride kalan koşu en çok iki
  gün geriye bakar (`WS_TASK_OVERDUE_LOOKBACK`). Bitiş ileri alınan görev
  yeni an geçince yeniden bildirilir. Koşanlar: genel iş (`job.php`, beş
  dakikada bir; Çalışma Alanı yalnız gerektiğinde yüklenir), saatlik
  tekrarlayan görev işi, açık Çalışma Alanı ekranının `ws_tick`'i
  (`ws_sync` cevabındaki `scheduled_due` artık bunu da söyler).
- `task_work.php`: `ws_task_note_tell()` — göreve not yazılınca görevdekiler
  ve açan `task_note` alır; yazan ve notta anılanlar (kendi `note`
  satırları var) hariç, görevi görebilenlere. Okunmamış satır sonraki notları
  da toplar; satır en yeni notu (okuyanın kendi notları hariç) yazanın adı
  ve metniyle anlatır, uygulama notunda uygulamanın adıyla.
- `notify.php`: üç yeni tür (`task_note`, `task_reminder`, `task_overdue`)
  ve tanımları; `task_assistant` gövdesi onay bekleyen öneri sayısını ekler
  (`ws_task_delegation_waiting_map()`). `ws_notify()`'a `batch` bayrağı:
  cihaz kuyruğa girer ama burada uyandırılmaz; hatırlatma ve gecikme koşuları
  bitince kuyruğu bir kez işler (`ws_push_batch_done()`). Her satırda
  kuyruğu ayrı işlemek, çok kişili bir koşuyu ekranın `ws_tick` isteğinde
  uzatırdı.
- `ws_inbox_mark_task_read()`: görev çekmecesi (`ws_task_get`) açılınca o
  görevin okuyana ait satırları okunur, zil eşitlenir; kuyrukta bekleyen
  banner gönderim anında düşer (`ws_push_still_relevant()`). Dış API'nin
  görev okuması okundu saymaz.
- Hızlı not: `ws_notes_quick_markup()` (`notes.php`), `output_header()`
  zilin solunda basar — Çalışma Alanı açık, kişi ekip üyesi ve notlar
  kuruluysa; araç çubuğu kipinde değil (ayarlar düğmesiyle aynı gerekçe).
  Kutu `ws_note_save` eylemiyle kişinin kendi notuna yazar (başlık boş,
  ilk satır başlık olur), Ctrl+Enter kaydeder, kayıttan sonra "Notu aç"
  bağlantısı çıkar; kutunun başında Notlarım bağlantısı. Betik
  `backend.src.js` (`pg_quick_note_init()`), metinler ve adresler formun
  `data-*` özniteliklerinde, stil `backend.src.css`'te zilinkiyle aynı
  yerleşim.

**Bilerek yapılmayan.**

- Kanalın "Her mesajı bildir" ayarı cihaz bildirimi göndermiyor; yalnız
  rozeti etkiliyor. Varsayılan `all` olduğundan her mesajı bildirime çevirmek
  herkesi gürültüye boğardı. Gerekirse ayrı bir karar.
- Başkasının planladığı toplantı/izin (plan kaydı) için bildirim yok:
  `ws_inbox` plan kaydına işaret edecek kolon taşımıyor, şema adımı ister.
- Tür bazında kişisel bildirim tercihi ekranı yok.
- Gecikme ve hatırlatma için webhook olayı eklenmedi.

**Doğrulama.** Sandbox'ta (MariaDB, üç ekip üyesi, biri e-postasız) CLI ile:
hatırlatma iki kişiye `task_reminder` + zil satırı, e-postasız kişiye de;
görev notu yazan hariç görevdekilere ve açana `task_note`, ikinci not aynı
satırda, anılan kişiye yalnız `note`; görevi açınca satır ve zil okundu;
gecikmede ilk koşu yalnız çizgiyi kurdu, saatli geçmiş görev ve dünkü
saatsiz görev (kimse yokken açana) bildirildi, bugünkü saatsiz ve bitmiş
görev bildirilmedi, aynı çizgiyle ikinci koşu hiçbir şey yapmadı;
`batch` satırı kuyrukta bekleyip `ws_push_batch_done()` ile işlendi;
zilin cihaz için verdiği metin doğru. `php job.php` ve
`workspace_recurring_job.php` hatasız koştu, çizgi ilerledi. Chromium'da
hızlı not: masaüstü ve 375 px'te açıldı, kutu odak aldı, Ctrl+Enter kaydetti,
"Notu aç" doğru notu açtı (ilk satır başlık), Notlarım bağlantısı doğru.

**Doğrulanamayan.** Gerçek bir cihaza giden push (sandbox'ta abonelik yok);
dev.pinegrap.com'da telefonla denenmeli.

---

## 2026.4.10 — ERP: satın alma siparişi (10.58) (2026-10-13)

**Neden.** ERP planının Faz 7'si (`docs/_plan_erp_ozellikler.md` §3): mağaza
tedarikçiye ne istediğini sistemde tutamıyordu; alış faturası ancak mal ve
fatura gelince yazılıyordu. Bu PR siparişin kendisini getirir (yaz, düzelt,
yazdır, e-postala, gönderildi işaretle, iptal et, yeniden aç, kopyala).
Alış faturasına dönüşüm, kısmi teslimden okunan durum, minimum stoktan
sipariş ve pano kartı sonraki PR'da; API/MCP uçları ve webhook olay listesi
Faz 6 birleşince gelir.

**Model.** Teklif gibi kendi tablosu (`erp_purchase_orders`): para, stok ve
vergi hareketi yaratmadığı için hiçbir raporun onu dışarıda bırakmayı
öğrenmesi gerekmez. Satırlar alış faturası editörüyle yazılır
(`erp_invoice_form_cards()`'a `purchase_order` seçeneği: gizli
`direction=purchase`, "Tedarikçi" etiketi, vade yerine **beklenen teslim**,
tedarikçi fatura no/tarihi bloğu yok) ve `erp_manual_lines_build()` ile
hesaplanıp `line_data`'ya JSON yazılır (teklifteki gibi). Beklenen teslim
boş bırakılabilir: `erp_invoice_form_read()` boş vadeyi carinin ödeme
vadesiyle doldurduğu için ekran yazılanı önce okur, boşsa sıfırlar (teklif
ekranının yaptığı). Tarih sipariş tarihinden önce olamaz.

**Satır anahtarı (`source_line`).** Her sipariş satırı 16 karaktere kadar
harf/rakamdan bir anahtar taşır; satır düzenlenirken anahtarı değişmez
(editörde gizli `lines[i][source_line]`). Sonraki PR'da siparişten yapılan
alış faturası satırları bu anahtarı `erp_invoice_items.source_line`'a yazar;
sipariş "ne geldi"yi buradan okur. `erp_source_line_key()` anahtarı temizler
ve yalnız sıfırdan oluşanı boş sayar (editör son satırı silerken gizli
alanlara `0` yazar). `erp_purchase_order_line_keys()` boş ya da daha önce
kullanılmış (büyük/küçük harf fark etmeksizin) anahtara yenisini verir; bir
anahtar iki satıra verilmez, kopyalanan satır kendi anahtarını alır.
Siparişi kopyalamak anahtarları boşaltır.

**Numara serisi: P, plandaki S değil.** Plan fatura serisinin sonuna S
eklemeyi yazıyordu; `erp_waybill_series()` irsaliyeler için zaten S
kullanıyor ve iki belgenin aynı numarayla basılması numaralamanın yapmaması
gereken tek şeydir. Sipariş serisi `ERP_DEFAULT_SERIES . 'P'` (varsayılan
`PGFP`), `config.php`'de `ERP_PURCHASE_ORDER_SERIES` ile değiştirilebilir.
Numara ilk kayıtta `erp_next_number(…, 'purchase_order', yıl)` ile aynı
işlemin içinde alınır (`erp_document_series.doc_kind` ENUM'una
`purchase_order` eklendi).

**Durumlar.** `open` ve `sent` düzenlenebilir; `partial` ve `received`
sonraki PR'da alış faturalarından okunur (düzenlenemez); `cancelled`
düzenlenemez, "Yeniden aç" `sent_at` varsa `sent`'e, yoksa `open`'a döner.
Gönderildi: e-posta başarıyla gidince ya da elle (telefon, kâğıt);
`sent_at` ilk seferde yazılır, `erp.purchase_order.sent` yalnız o zaman
çıkar. İptal `received` dışında her açık durumdan yapılır; gelmiş mal alış
faturalarında kalır.

**Olaylar ve denetim izi.** `erp_event_purchase_order()`:
`erp.purchase_order.created` (kayıt işleminin içinde, kayıtla birlikte geri
alınır), `.sent`, `.cancelled`, `.reopened`; `.received` sonraki PR'da.
`erp_audit_object_types()`'a `purchase_order` (ekrana bağlantılı), olay
metinleri. Webhook olay listesine (`erp_webhook_events()`) Faz 6 ile
çakışmamak için API PR'ında eklenecek.

**Ekranlar.** `erp_purchase_orders.php` (durum süzgeci, "Mal bekleyen"
sayaçlı, numara/tedarikçi araması, cari süzgeci; beklenen teslimi geçmiş
bekleyen sipariş kırmızı "Gecikti"), `add_erp_purchase_order.php`
(`?copy=`, `?account_id=`), `edit_erp_purchase_order.php` (düzenle,
gönderildi işaretle, iptal, yeniden aç, e-posta ve geçmişi, kopyala, denetim
izi; düzenlenemezken salt-okunur satır tablosu),
`get_erp_purchase_order_pdf.php` (`&download=1`, `&html=1`). Şablon
`includes/erp/templates/purchase_order.html`: fatura şablonunun verisiyle
okunur, etiketler veride (Sipariş No, Sipariş Tarihi, Beklenen teslim,
TEDARİKÇİ), sabit metin taşımaz; carinin kopyası tutulmadığı için tedarikçi
bugünkü kartıyla basılır. E-posta `erp_document_mails`'e `purchase_order`
türüyle yazılır, alıcı önce carinin fatura e-postası. ERP menüsünde
Teklifler'in altında; tedarikçi (ve "ikisi de") carinin kartında iki düğme.

**Şema 10.58** (`upgrade_2026_4_10_erp_purchase_orders()`):
`erp_purchase_orders` tablosu (`uniq_number`, `idx_account`, `idx_status`),
`erp_invoices.purchase_order_id` + `idx_purchase_order`,
`erp_invoice_items.source_line` VARCHAR(16), `doc_kind` ENUM'una
`purchase_order` (mevcut değerler okunup korunarak). Son iki kolon bu PR'da
yalnız açılır, sonraki PR yazar. **2026.4.10'a zaten geçmiş siteler (dev
dahil) adımı almak için Yazılımı Onar › "Veritabanı yükseltme adımlarını
yeniden koş" › 2026.4.9'dan yeniden koşturmalı.**

**Doğrulama.** Sandbox'ta adım iki kez koşturuldu (ilkinde 5 ifade
uygulandı, ikincide 31/31 "zaten yerindeydi"). Güncelleme öncesi ekranlar
"güncellemeyi çalıştırın" diyor. Sipariş yazma (numara `PGFP2026000000001`,
seri satırı `purchase_order`), düzenleme (anahtarlar korunuyor, kopya
anahtar yenileniyor, boş tarih boş kalıyor), PDF (pdftotext ile Türkçe
karakterler, sayılar), gönderildi (iki kez, olay bir kez), iptal, iptalken
gönder/iptal reddi, yeniden aç, kopyala, liste süzgeçleri, cari kartı
(tedarikçide düğmeler, müşteride yok), denetim izi ekranı, salt-okunur
görünüm, başarısız e-postanın kaydı ve taslağın korunması; fatura, teklif
ve fatura taslağı ekranları değişmeden çalışıyor.
`tests/erp_purchase_order_test.php` (8 test).

**Doğrulanamayan.** Başarılı e-posta gönderimi (sandbox'ta posta sunucusu
yok); dev.pinegrap.com'da denenmeli.

---

## 2026.4.10 — Çalışma Alanı API ve MCP: görevi asistana verme ve önerileri onaylama (2026-10-13)

**Neden.** Görevi asistana verme işinin üçüncü ve son PR'ı (araştırmanın P1
1.2 maddesi). İlk iki PR ekranı kurdu; dış API ve MCP görevin asistanda
olduğunu, asistanın cevabını ve önerilerini göremiyor, öneri de
uygulanamıyordu. Mobil API planına (`docs/_plan_api_mobil.md`) uygun olarak
uygulamaya özel uç yazılmadı: mevcut uçlara alan ve süzgeç eklendi, yeni uçlar
genel eylemler.

**Ne değişti.**

- `WorkspaceTask`: `delegate` (ai / claude / null), `delegated_by`,
  `delegated_at`, `assistant_working` (şu an çalışan asistan),
  `waiting_changes` (onay bekleyen öneri sayısı). Liste tek sorguda okur
  (`ws_api_tasks_known()`, yeni `ws_task_delegation_open_map()`).
- `GET /workspace/tasks?delegate=any|none|ai|claude` (`ws_tasks_list()`
  süzgeci `delegate`).
- `POST /workspace/tasks/{id}/delegate` {agent, instruction}: ekrandaki
  `ws_task_delegate()`; cevap çalışmanın kendisi (`WorkspaceAssistantRequest`,
  201). Çalışma sürerken 409 `assistant_busy`. `POST
  /workspace/tasks/{id}/delegate/stop` {take_back}: `ws_task_delegation_stop()`.
  Asistanın çalıştığı uygulamadan (Claude, Pinegrap AI) ikisi de 403:
  asistan görev devretmez, geri almaz.
- `GET /workspace/assistant-requests`: görevdeki çalışmalar (origin `task`,
  `task_id`), görevi görebilen herkes için; bu çalışmalarda `instruction` ve
  `answer` düz metin olarak gelir. `task_id` süzgeci.
- `GET /workspace/changes`: görevdeki öneriler de (`task_id`, kanal ve mesaj
  0), `task_id` süzgeci; her öneride `can_apply`, `can_dismiss`.
  Okunabilirlik tek SQL'de: kanallar + görülebilen görevler
  (`ws_api_task_seen_sql()`, `ws_can_see_task()` kuralının SQL'i; sandbox'ta
  beş kullanıcı × yedi görev düzeninde PHP kuralıyla birebir aynı çıktı).
- `POST /workspace/changes/{id}/apply` ve `/dismiss`: kartın düğmeleriyle
  aynı fonksiyonlar (`ws_change_apply()`, `ws_change_dismiss()`), yalnız
  cihaz oturumu ya da kişinin bağladığı OAuth uygulaması
  (`ws_api_person_only()`); uygulama anahtarı 403, yani asistan kendi
  önerisini uygulayamaz. Eskimiş öneri 409 `stale`, karar verilmiş öneri 409
  `already_decided`.
- `GET /workspace/my-day`: `proposed_changes` (kişinin istediği, onu
  bekleyen öneriler; kanal ve görev, en çok 20).
- API'den uygulanan değişikliğin etkinlik kaydı kişinin kullanıcı adıyla
  yazılır (`ws_change_log()`, oturum yoksa API sahibinin adı).
- `workspace.task.assistant_answered` olayının açıklaması cevabın ve
  önerilerin nereden okunacağını söylüyor.
- MCP araçları uçlardan kendiliğinden geliyor: `workspace_tasks_delegate`,
  `workspace_tasks_delegate_stop`, `workspace_changes_apply`,
  `workspace_changes_dismiss`.

**Bilerek yapılmayan.** Panelin "Benim günüm" ekranına öneri listesi
eklenmedi (yalnız API). Pinegrap AI'ı API'den başlatmak bekleyen bir ekran
ya da saatlik iş ister; uç bunu açıklamasında söylüyor.

**Doğrulama.** Sandbox'ta cihaz oturumuyla (`POST /auth/login`): görevi
Claude'a verme, ikinci istekte 409, Claude uygulamasıyla talep alma ve
cevaplama (iki öneri) → `my-day` iki öneri → Claude anahtarıyla apply 403 →
cihazla apply (görev güncellendi, etkinlik kaydı kişinin adıyla) → aynı
öneriye tekrar 409 `already_decided` → dismiss → kayıt arada değişince 409
`stale` → durdur + geri al (delegate null, çalışma iptal). OpenAPI belgesinde
dört yeni yol ve yeni alanlar var.

**Doğrulanamayan.** Gerçek mobil uygulama ve MCP istemcisiyle deneme;
dev.pinegrap.com'da yapılmalı.

---

## 2026.4.10 — Dış API ve MCP: ERP faturaları ve teklifleri yazılıyor (Faz 6, birinci parça) (2026-10-13)

**Neden.** `docs/_plan_erp_ozellikler.md` Faz 6 (#27): API'de ve MCP'de
fatura ve teklif yalnız okunuyordu; yazılanlar cari, tahsilat ve giderdi.
Bu parça faturaları, tekrarlayan faturaları, siparişin faturasını ve
teklifleri açar. İrsaliye, sayım, stok alt sınırları, virman, çek/senet ve
raporlar sonraki iki parçada.

**Ne değişti.** On sekiz yeni rota `erp_api_routes()`'ta
(`includes/erp/api.php`); MCP'de her biri kendiliğinden araç olur
(`delete_post` hariç). İşleyiciler yeni `includes/erp/api_documents.php`'de
(`api_resources.php` 3500 satırı geçtiği için ayrıldı) ve panelin kendi alan
fonksiyonlarını çağırır; ikinci bir hesap yolu yok.

| Rota | Fonksiyon |
|---|---|
| `POST /erp/invoices`, `POST`/`PATCH /erp/invoices/{id}` | `erp_invoice_draft_save()`; yalnız taslak, kesilmişte 409 `invoice_not_draft` |
| `DELETE /erp/invoices/{id}`, `POST …/{id}/delete` | `erp_invoice_draft_delete()` |
| `POST /erp/invoices/{id}/issue` | `erp_invoice_issue()` |
| `POST /erp/invoices/{id}/cancel` | `erp_invoice_cancel()` |
| `POST /erp/invoices/{id}/returns` | `erp_returnable_lines()` + `erp_return_build()` + `erp_invoice_return()` |
| `POST /erp/invoices/{id}/email` | `erp_mail_invoice_send()` |
| `POST /erp/orders/{order_id}/invoice` | `erp_invoice_from_order()` |
| `GET /erp/invoice-recurrences`, `POST /erp/invoices/{id}/recurrence`, `POST /erp/invoice-recurrences/{id}/stop` | `invoice_recurring.php` |
| `POST /erp/quotes`, `POST`/`PATCH /erp/quotes/{id}`, `POST …/status`, `POST …/invoice`, `POST …/email`, `GET …/document` | `erp_quote_save()`, `erp_quote_set_status()`, `erp_quote_to_invoice()`, `erp_quote_mail_send()`, `erp_quote_html()` |

Ayrıntılar:

- **Satırlar.** `erp_api_lines_in()` her satırı denetler: nesne olmalı,
  bilinmeyen alan 422 (`lines[0].total` gibi alan adıyla), `unit_price`
  zorunlu ve kuruş (ondalık 422), oranlar 0–100, ürün var olmalı. Ürüne
  bağlı satırda ad ve KDV oranı gönderilmezse ürünün adı ve satır
  editörünün vereceği oran (`erp_product_for_line()`) kullanılır; ürünsüz
  satırda ikisi zorunlu. Mağazada ikinci vergi yokken `tax2_rate` 422.
  Vergi, iskonto, tevkifat ve toplamlar yalnız `erp_manual_lines_build()`
  ile hesaplanır, istemci toplam gönderemez. ERP'nin `lines[0][unit_code]`
  biçimli alan adları API'de `lines[0].unit` olarak döner
  (`erp_api_document_field()`).
- **Taslak görünürlüğü.** `GET /erp/invoices` taslakları yalnız
  `status=draft` ile listeler; `GET /erp/invoices/{id}` taslağı da döndürür
  (`erp_api_invoice_row($id, true)`), PDF'i taslakta 404. Taslağın
  `totals.open` değeri 0.
- **Prova (X-Dry-Run).** Her yazma rotası prova edilebilir; işleyici alan
  fonksiyonunun reddedeceği her şeyi yazmadan önce sorar: kesimde cari
  pasif, e-Belge alıcı eksikleri, ikinci vergi + e-Belge, dönem kilidi
  (422 `period_locked`), kurun bulunmaması, satışta kredi limiti (422
  `credit_limit`); sipariş faturasında kalem yokluğu, kişi kaydının
  silinmesi ve toplam uyuşmazlığı. Bu iki kod `api_error_catalogue()`'a
  eklendi (`includes/api/schema.php`'de yalnız bu iki satır; plan dosyası
  schema.php'yi dokunulmaz sayıyordu, ama 422 kodları katalogda olmadan
  `check_api_schema.php` geçmiyor).
- **İptal.** Taslak 409 `invoice_is_draft` (silinir), tahsilat bağlıysa 409
  `invoice_has_payments`, iade varsa 409 `invoice_has_returns`. e-Belge
  sağlayıcısının elindeki belge 409 `invoice_at_provider`: sağlayıcıdan
  geri alma bir sürücü çağrısı ve operatör onayı (panelde
  `erp_edoc_invoice_provider_cancel()`), API sağlayıcıya gitmez.
- **İade.** `lines` (`{line_id, quantity}`) ya da `all: true`; ikisi
  birden ya da hiçbiri 422. Faturaya ait olmayan satır, aynı satırın iki
  kez geçmesi 422.
- **Teklif.** Kur ve tarih kuralları taslakla aynı (`erp_api_document_header()`);
  `valid_until` boş gönderilirse tarihten 30 gün. Durum geçişleri yeni saf
  `erp_quote_status_refusal()`'dan okunur (panelin düğmeleri de aynı
  kuralı `erp_quote_set_status()` içinden sorar); izin verilmeyen geçiş
  409 `quote_status_not_allowed`.
- **E-posta.** Fatura ve teklif e-postası prova edilince alıcı listesi
  döner, gönderilmez. Posta sunucusunun reddi 503 ve gönderim kaydında
  başarısız görünür.
- **Kur.** Yabancı para biriminde gönderilen kur `exchange_rate_source =
  'api'` ile yazılır (giderdeki gibi); gönderilmezse tarihin kayıtlı kuru,
  o da yoksa kesimde bakılır. Para birimi, kur ve tarih gönderilmeyen
  güncellemede taslağın kuru korunur.
- Yeni nesne `ErpInvoiceRecurrence`. Etkinlik günlüğüne her yazma
  `erp_api_log()` ile uygulama adıyla düşer; olaylar alan
  fonksiyonlarından (`erp.invoice.created`, `erp.quote.*`) bugünkü gibi.
- Kesim sonrası mağazanın otomatik e-posta ayarı paneldeki gibi çalışır
  (`erp_event_invoice()` → `erp_mail_auto_consider()`); e-Belge'ye
  gönderim panelde fatura ekranında bir adım olarak kalır.

**Doğrulama.** Sandbox'ta iki uygulamayla (tam ERP ve yalnız `erp:read`)
curl: taslak yaz → değiştir (PATCH ve POST) → kes → PDF → kısmi iade →
iadeli faturayı iptal 409 → iadeyi iptal → faturayı iptal; aynı
`Idempotency-Key` ile ikinci istek `Idempotent-Replay: true` ve tek kayıt;
yalnız okuma anahtarı 403 `insufficient_scope`; dönem kilidi 422
`period_locked`, kredi limiti (block) 422 `credit_limit`; taslakta iptal,
iade, e-posta, tekrar reddi; tekrarlayan fatura başlat → listele → durdur;
teklif yaz → değiştir → reddet → yeniden aç → kabul → faturaya dönüştür →
taslağı sil (teklif yeniden kabul); tamamlanmış siparişin faturası ve
ikinci deneme 409. MCP `tools/list`'te yeni 17 araç (`delete_post` yok),
`tools/call` ile `erp_invoices_issue` (dry_run) ve `erp_quotes_create`.
`tests/erp_api_test.php` (rota–işleyici, yöntem+yol çakışması, prova,
satır yardımcıları) ve `erp_quote_test.php`'ye durum geçişi testi.

**Doğrulanamayan.** IIS (dev.pinegrap.com), gerçek e-posta gönderimi ve
e-Belge sağlayıcısı olan bir mağaza.

---

## 2026.4.10 — Çalışma Alanı: asistanın görevdeki önerileri için onay kuyruğu (2026-10-13)

**Neden.** Görevi asistana verme işinin ikinci PR'ı (araştırmanın P1 1.2
maddesi). İlk PR'da asistan görevde yalnız metinle cevap veriyordu.
İstenen: asistanın yazdıkları (yorum, durum değişikliği, not) "onay
bekliyor" olarak düşsün, sorumlu kişi tek tıkla onaylasın ya da reddetsin.

**Nasıl.** Yeni bir kuyruk yazılmadı; kanalda zaten çalışan önerilen
değişiklikler (`ws_ai_changes`, `changes.php`) kullanıldı. Görev
çalışmasının önerileri mesaja değil göreve bağlı durur (`task_id`, kanal
0, mesaj 0; kolon 10.80'de eklenmişti). Aynı kontroller geçerli: öneri
yapılırken ve uygulanırken isteyen kişinin hakları sorulur, kayıt bu arada
değiştiyse öneri "eskimiş" olur, yalnız isteyen kişi uygular (kenara ayırmayı
yetkililer de yapabilir). Görevdeki önerinin kanala kararı yok; çekmecedeki
kart ve etkinlik kaydı izi tutar.

**Ne değişti.**

- `ws_change_types()`'a üç tür: `task` (güncelleme: başlık, açıklama, durum,
  öncelik, başlangıç ve bitiş tarihi; görevi değiştirebilen kişi,
  `ws_task_validate()` ile), `task_note` (oluşturma: metin ve `task_id`;
  görev çalışmasında `task_id` verilmezse o görev; uygulanınca önerenin
  uygulaması adıyla yazılır, uygulama bağlantısı yoksa uygulayan kişinin
  adıyla), `note` (oluşturma: başlık, metin; uygulayan kişinin Çalışma Alanı
  notu). Üçü kanalda da önerilebilir; kanalda uygulanınca karar mesajı
  düşer.
- `ws_changes_input()` dördüncü parametre `$task_id`: görev bağlamında toplu
  değişiklik ve kanal özeti reddedilir. `ws_changes_store()` `task_id`
  yazar. `ws_changes_map()` istek kimliğine göre de okur (`$by =
  'request_id'`); görev bağlamında işlem hakkı kanal yerine görevi görme
  hakkına bakar. `ws_change_apply()` / `ws_change_dismiss()` görev
  bağlamında kanal kararı yazmaz.
- Pinegrap AI: görev çalışmasında `propose_change` açık (görev istemi ve
  değişiklik türleri rehberi eklendi; "değiştirdim" diyen cevaba bir kez geri
  dönüş görevde de çalışır); `ws_ai_deliver()` görev önerilerini cevapla
  birlikte saklar.
- Claude: görev çalışmasının cevabı `changes` alır (`tasks` 422); cevap
  ucunun açıklaması ve rutin istemi güncellendi — **claude.ai'deki rutinin
  istemi Ayarlar › Çalışma Alanı › Claude kartından yeniden
  kopyalanmalı.** Eski istemle de çalışır, yalnız öneri yapmaz.
- Ekran: görev çekmecesinin Asistan bölümünde her cevabın altında öneri
  kartları (kanaldakiyle aynı görünüm: Uygula / Kenara ayır, uygulandı /
  eskimiş / reddedildi satırları), bölüm başında "Önerilen N değişiklik onay
  bekliyor", görev satırında "N onay bekliyor" etiketi (görev brief'inde
  `waiting_changes`). Görev ya da görev notu uygulanınca çekmece yeniden
  çizilir.
- Zamanlanmış eylemler yeni türleri almaz (`ws_scheduled_change_skipped()`).
- Bağlantısı kapatılmış asistanın çalışmaları boş resim yerine varsayılan
  avatarla görünür (`ws_assistant_avatar()`).

**Bilerek yapılmayan.** Görev çalışmasında görev önerisi (`ws_ai_drafts`)
ve toplu değişiklik yok. Pinegrap AI'ın kanalda doğrudan not yazan
`add_task_note` aracı olduğu gibi kaldı. API/MCP uçları (görev başına öneri
listesi, uygula/reddet) üçüncü PR'da.

**Doğrulama.** Sandbox'ta Claude uygulamasıyla: görev çalışmasına üç öneri
(durum/öncelik/tarih, göreve not, Çalışma Alanı notu) → çekmecede kartlar
→ uygula (görev güncellendi, not Claude adına, kişinin notu oluştu,
etkinlik kaydı "Claude'un önerisiyle") → kenara ayır → kayıt arada
değişince "eskimiş"; toplu değişiklik, görev önerisi, geçersiz durum ve ters
tarihler 422. Kanalda aynı türler: karar mesajları. Pinegrap AI akışı CLI'da
(`propose_change` → `ws_ai_deliver`) ve uygulanınca "Pinegrap AI'ın
önerisiyle" kaydı. Chromium'da kartlar ve düğmeler.

**Doğrulanamayan.** Gerçek Pinegrap AI model çağrısı ve gerçek Claude
rutini; dev.pinegrap.com'da denenmeli.

---

## 2026.4.10 — Görsel editör: sayfa seçicilerinde Geri Dönüşüm Kutusu, link seçicide adres kodlaması (2026-10-13)

**Kaynak.** Bir önceki taramada (aşağıda, PR #257) bilerek bırakılan iki
nokta. Seçicideki karar koordinatörün varsayılanıdır: kutudaki sayfa
listelenmez, ama ayarın hâlâ gösterdiği sayfa "(silinmiş)" etiketiyle
seçili kalır ki "seçilmedi" gibi görünmesin.

- **Sayfa seçicileri kutudaki sayfaları listeliyordu.** `shared_component`
  liste uçları (`list_all_pages`, sepet, katalog listesi ve detayı, form
  detayı, takvim etkinliği, katalog sayfaları) çöp kutusuna bakmıyordu;
  kutudaki sayfa park edilmiş adıyla (`pgbin_<id>_<ad>`) seçilebiliyordu,
  oysa sunulmaz. Türe göre süzülen listeler artık kutudaki sayfayı
  almıyor (`pg_designer_not_binned_sql()`; `pg_sw_widget_pages()` zaten
  almıyordu). `list_all_pages` ve form listesi (`list_custom_forms`)
  kutudakini `binned: 1` işaretiyle, park edilmeden önceki adıyla
  döndürüyor (`_sc_flag_binned_pages()`; ad `pg_recycle_parked_original()`
  ile, `pg_shared_component_usage()` ile aynı yol).
- **Seçili sayfa listeden düşünce seçici "seçilmedi" gösteriyordu.**
  `_swPageOptions()` kutudaki sayfayı yalnız seçili olansa gösterir,
  "(silinmiş)" ekiyle. Hiçbir listede olmayan seçili sayfa için
  `_swMissingPageOption()` bir seçenek yazar: adı tam listeden
  (`list_all_pages`) okunur; kutudaysa ya da hiç yoksa "(silinmiş)",
  canlı ama seçicinin istediği widget'ı taşımıyorsa "(uyumsuz — yeniden
  seçin)". Detay sayfası seçicisinin kendi "uyumsuz" seçeneği bu yola
  katıldı (önceden `#<id>` gösteriyordu). Form seçicileri (form listesi
  ve form detayı, Hesabım filtresi, özel form widget'ının "Hangi form"u)
  aynı kuralı `_swFormOptions()` ile uygular. Panel birkaç seçici
  çizdiğinde `list_all_pages` bir kez istenir (`_fetchAllPages()` bekleyen
  geri çağrıları toplar).
- **Link seçici adresi kodlamıyordu.** `editor_select_page_or_file.php`
  (CKEditor ve editörün link/dosya alanları) `PATH . ad`'ı olduğu gibi
  veriyordu; adında boşluk, `#` ya da `?` olan sayfanın linki yarıda
  kesiliyordu (`/şartlar ve koşullar#1` → `/şartlar ve koşullar` + parça,
  404). Eklenen adres artık `encode_url_path()`'ten geçiyor (sayfa,
  dosya, kısa link); liste okunur adı göstermeye devam ediyor.

Sandbox'ta doğrulandı (hello-pinegrap tasarımı yayımlandı, iki sayfası
kutuya taşındı; editör ve link seçici Playwright ile): öncesinde katalog
detay seçicisi `pgbin_1091_urun-detay — Ürün`'ü seçili, giriş formunun
yönlendirme seçicisi kutudaki iki sayfayı seçenek olarak gösteriyordu;
sonrasında `urun-detay (silinmiş)` ve `hakkimizda — Hakkımızda (silinmiş)`
seçili, kutudaki öteki sayfa listede yok. Canlı ama uyumsuz sayfa
`site-search (uyumsuz — yeniden seçin)`, var olmayan id `#99999
(silinmiş)` gösteriyor. Link seçici `/%C5%9Fartlar%20ve%20ko%C5%9Fullar%231`
ekliyor, adres 200 dönüyor. dev.pinegrap.com'da denenmedi.

---

## 2026.4.10 — Çalışma Alanı: takvim aboneliği (iCal) (10.81) (2026-10-13)

**Neden.** Çalışma Alanı araştırmasının P1 1.4 maddesi
(`/mnt/project-files/notes/workspace-arastirma.md`): görevler ve plan
kayıtları yalnız panelde görünüyordu. Her üyeye kendi gizli takvim adresi;
Google, Apple ve Outlook bu adresi kendisi okuyup günceller. OpenAPI/MCP
değişikliği yok.

**Takvimde ne var.** Dört tür, ekrandan seçilir (varsayılan dördü de):

- Görevler (`tasks`): kişiye atanmış, tarihli görevler (açık olanlar ve son
  60 gün içinde bitenler, iptal hariç). Gün `ws_task_window()`'un bitişi;
  `due_time` varsa o saatte bir an (DTEND yok, RFC 5545 §3.6.1), yoksa tam
  gün. Biten görevin başlığı "✓ " ile başlar. Tekrarlayan görevlerin
  önümüzdeki 90 günlük kopyaları `ws_recurrence_projections()`'tan
  `STATUS:TENTATIVE` olarak gelir (tatile denk gelen kopya kaydırılmış
  günüyle), UID `ws-series-<id>-<gün>`.
- Plan (`plan`): `ws_event_applies()` ile kişiyi ilgilendiren toplantı,
  ziyaret, izin vb. (kişiye, departmanına ya da herkese). Meşgul (OPAQUE)
  yazılır.
- Tatil günleri (`holidays`): resmî tatiller ve şirket/departman kapalı
  günleri, boş (TRANSPARENT). Yıllık kayıtlar `RRULE:FREQ=YEARLY` ile tek
  olay; 29 Şubat `BYMONTH=2;BYMONTHDAY=-1`.
- Onaylar (`approvals`): kişinin cevabını bekleyen, kapanış saati olan onay
  istekleri, kapanış anında (`ws_can_post_channel()` ile kanal hakkı
  yeniden kontrol edilir).

Görevler takvimde VEVENT'tir: Google, Outlook ve Apple abone olunan
takvimde VTODO'yu göstermez. Pencere geçmiş 60, ileri 400 gün; en çok 2000
olay. "Açıklamaları ve notları ekle" varsayılan kapalı: kapalıyken yalnız
başlık, tarih, durum/öncelik, kanal ve panel bağlantısı gider (takvim
servisi içeriğin kopyasını tutar).

**Adres ve uç.** `workspace_ical.php?token=<43 karakter base64url>`:

- Oturumsuz (`PG_NO_SESSION`); aynı tarayıcıdaki panel çerezi adresin
  gösterdiğini değiştiremez. Jeton `pg_short_link_new_token()` (256 bit),
  `token_hash` (SHA-256) ile aranır. Ekran adresi yeniden gösterebilsin diye
  jeton ayrıca site anahtarıyla şifreli saklanır (`token_secret`); anahtarı
  olmayan sitede adres yalnız oluşturulduğu anda bir kez gösterilir.
- Açmayan her şey 404 (bilinmeyen/yenilenmiş/sonlandırılmış jeton, modül
  kapalı, kişi silinmiş ya da artık ekipte değil); suç sayılmaz. Silinmiş
  kişinin aboneliği o an silinir.
- `ETag` (gövdenin sha1'i) ve `If-None-Match` ile 304 (zayıf etiket ve
  liste dahil), `HEAD`, diğer yöntemlere 405. `Cache-Control: private,
  max-age=300`, `X-Robots-Tag: noindex`, `Referrer-Policy: no-referrer`.
- Çıktı RFC 5545: CRLF, 75 oktette UTF-8 sınırında katlama, TEXT kaçışları,
  saatler UTC, tam gün DATE (DTEND son günden sonraki gün), `X-WR-CALNAME`,
  `X-WR-TIMEZONE`, `REFRESH-INTERVAL`/`X-PUBLISHED-TTL` PT1H.
- Son okuma (`fetched_at/ip/agent`) dakikada en çok bir kez yazılır; ekran
  okuyanı adıyla gösterir (Google Takvim, Apple Takvim, Outlook…).

**Güvenlik duvarı.** `workspace_ical.php`, `waf_is_api_request()`
listesinde: takvim sunucuları kendi kullanıcı ajanıyla, kendi ülkelerinden
okur, bu yüzden bot politikası, boş/genel kullanıcı ajanı engeli ve hassas
hız sınırı uygulanmaz; ülke kısıtlamasının "API, MCP, OAuth" muafiyeti bu
adresi de kapsar (ayar etiketi güncellendi). Saldırı aracı kontrolü, IP
kara listesi ve kurallar yine işler.

**Ekran.** `workspace_calendar_feed.php` (Hesap menüsü › Takvim aboneliği;
Çalışma Alanı çekmecesinin İş grubu; İş Takvimi'nin altındaki bağlantı):
adres oluştur, kopyala, Google'a ekle (`calendar.google.com/calendar/render
?cid=webcal://…`, yalnız https sitede), Apple/Outlook'ta aç (`webcal://`),
türleri ve açıklama anahtarını kaydet, yeni adres oluştur (eskisi hemen
404), aboneliği sonlandır, Google/Outlook/Apple için ekleme yardımı. Her
işlem kişinin etkinlik kaydına yazılır. Menü bağlantısı yalnız modül açık
ve kişi Çalışma Alanı üyesiyken görünür.

**Şema 10.81** (`upgrade_2026_4_10_workspace_calendar_feeds()`):
`ws_calendar_feeds`, kişi başı bir satır (`uk_user`), `uk_token`. Görev
tablosuna dokunulmadı. Adımı 10.80'den (görev devri) sonra koşar.
**2026.4.10'a zaten geçmiş siteler (dev dahil) tabloyu almak için Yazılımı
Onar › 2026.4.9'dan yeniden koşturmalı.**

**Bilerek yapılmayan.** VTIMEZONE yazılmıyor (saatler UTC, takvim kendi
saat dilimine çevirir). Görevlerin tekrar kuralı RRULE olarak değil, kopya
olaylar olarak gider: kopyalar tatil kaydırması ve kural sonu bilgisini
taşır. Ekip ya da departman takvimi (kişi dışı adres) yok; adres kişinin
kendi görünümü.

**Doğrulama.** Sandbox'ta uçtan uca: oluştur, kaydet, boş tür hatası,
yenile (eski 404, yeni 200), sonlandır (404, satır silindi, etkinlik
kaydı); ICS içeriği (iptal, başkasının ve tarihsiz görev yok; 17:00 görevi
14:00Z; ✓; TENTATIVE kopyalar ve tatil kaydırması; yıllık tatil RRULE; 29
Şubat; izin OPAQUE; toplantı UTC; onay anı; açıklama açık/kapalı); 304,
HEAD, 405, bilinmeyen/dizi jeton 404; modül kapalı ve ekipten çıkarılmış
kişi 404; son okuma "Google Calendar" olarak; WAF engelleme modunda ve
bilinmeyen botlar kapalıyken boş ve genel kullanıcı ajanıyla adres açılıyor,
panel betiği engelleniyor. Şema adımı iki kez koşturuldu (ikincide "zaten
var"). `tests/workspace_calendar_feed_test.php` (9 test).

**Doğrulanamayan.** Gerçek Google/Apple/Outlook aboneliği ve Cloudflare'in
bu sunucuları geçirmesi; dev.pinegrap.com'da denenmeli.

---

## 2026.4.10 — Çalışma Alanı: görevi asistana verme (Pinegrap AI, Claude) (10.80) (2026-10-13)

**Neden.** Çalışma Alanı araştırmasının P1 1.2 maddesi
(`/mnt/project-files/notes/workspace-arastirma.md`): asistanlar kanalda ve
notta sorulabiliyordu ama bir görev onlara verilemiyordu. Model Linear'ınki:
görevin sorumlusu insan kalır, asistan vekildir (delegate). Bu PR veri
modelini, asistan üyeyi ve devri getirir; asistanın önerdiği değişikliklerin
tek tıkla onayı (onay kuyruğu) ve API/MCP uçları sonraki iki PR'dır.

**Görev asistana nasıl ulaşır.** Yeni taşıma yolu yok. Her çalışma, iki
asistanın zaten çalıştığı kuyrukta (`ws_ai_requests`) bir istektir; görevle
`task_id` üzerinden bağlanır, kanal ve mesaj 0'dır (nottan ve sayfa
editöründen gelen istekler gibi): talimat `note_text`'te, cevap
`note_reply`'da. Pinegrap AI'ı site kendisi koşturur (`ws_ai_kick`, ekran
bekledikçe); Claude'un rutini yükü taşımayan tetikle uyandırılır ve kuyruğu
API'den çeker (`GET /workspace/claude/requests`), bu yüzden kişi başı sınır,
nöbetçi, günlük hak ve tetik aynı kalır. `docs/_plan_api_mobil.md` (mobil
uygulama aynı API'yi okur) ve `docs/_plan_claude_kanal.md` (Claude kanal
istekleri, yükte talimat yok) ile uyumlu: Claude isteğin içeriğini yine
kimliği doğrulanmış API'den okur. Dış sistemler için iki webhook olayı.

**Ne değişti.**

- Şema 10.80 (`upgrade_2026_4_10_workspace_delegation()`):
  `ws_tasks.delegate/delegated_by/delegated_at` (+ `idx_delegate`),
  `ws_ai_requests.task_id`, `ws_ai_changes.task_id` (onay kuyruğu için
  şimdiden).
- `includes/workspace/delegation.php`: `ws_assistants()` görevin
  verilebileceği asistanların tek listesi (bağlantısı açık olanlar; ileride
  Faz F'nin MCP sunucusu takma adı buraya bir satırla katılır),
  `ws_task_delegate()` (görevi değiştirebilen kişi, açık görev; görevde kimse
  yoksa ve bir departman havuzunda değilse devreden kişi göreve eklenir),
  `ws_task_delegation_run()` (görev başına tek açık çalışma, kişi başı
  sınır), `ws_task_delegation_stop()` (durdur / geri al),
  `ws_task_delegation_from_note()` (notta @ai/@Claude görevi devretmeden
  sorar; uygulamanın yazdığı not sormaz), `ws_task_delegation_context()`
  (asistanın okuduğu görev: notlar, önceki üç çalışma, isteyen kişinin
  haklarıyla).
- Pinegrap AI (`ai.php`): görev çalışması için ayrı istem (görev JSON'u,
  isteyen kişi okuyorsa kanal bağlamı, önceki çalışmalar, talimat); öneri
  araçları (`propose_*`, `add_task_note`) ve tasarım araçları görev
  çalışmasında kapalı; `read_channel` yalnız görevin kanalı ve yalnız isteyen
  okuyabiliyorsa (`ws_ai_request_channel()`). Kanal bağlamında okuma hakkı
  kontrolü yoktu; özel kanal görevinde kanal üyesi olmayan biri sorarsa kanal
  içeriği sızmasın diye eklendi.
- Claude (`api_claude.php`, `claude.php`): istek nesnesine `task` bloğu
  (`WorkspaceClaudeRequest.task`: başlık, açıklama, tarihler, durum,
  sorumlular, notlar, önceki çalışmalar, `handed_over`), `channel.kind`
  `task`; görev çalışmasına cevap yalnız metin (görev/değişiklik 422);
  `fail` artık kapanmış isteği yeniden yazmıyor. Rutin istemine görev
  paragrafı eklendi — **claude.ai'deki rutinin istemi Ayarlar › Çalışma
  Alanı › Claude kartından yeniden kopyalanmalı.**
- Bildirim: `task_assistant` gelen kutusu satırı ("Claude, G-12 görevinde
  cevap verdi" / "… istediğinizi yapamadı"); webhook
  `workspace.task.delegated`, `workspace.task.assistant_answered`.
- Panel: `ws_task_delegate`, `ws_task_delegation` (çalışma sürerken okuma),
  `ws_task_delegation_stop`; `ws_task_note_add` artık `notice` döndürüyor
  (not kaydedildi ama asistan sorulamadı: neden).
- Ekran: `assets/js/workspace_task_delegate.js` (workspace.min.js
  paketinde) görev çekmecesinin Asistan bölümü (asistan seçimi + talimat,
  "Asistan: Claude · Devreden" rozeti, Durdur, Görevi geri al, çalışmalar ve
  cevaplar, aynı asistana yeni talimat) ve görev satırındaki asistan
  etiketi. Çalışma sürerken bölüm 4 sn (AI) / 8 sn (Claude) arayla okunur,
  çekmece kapanınca durur.

**Faz F için.** Asistan üyenin görünüşü (ad, avatar, simge), devir, not
içinde çağırma ve etkinlik kaydı (çalışma satırları) `ws_assistants()`
listesinden okunuyor; MCP istemcisi olarak Çalışma Alanı aynı kuyruğa
cevap veren yeni bir anahtar ekleyerek katılabilir. Faz F yapılmadı.

**Bilerek yapılmayan.** Görev çalışmasında asistan taslak (`ws_ai_drafts`)
yazamaz: taslaklar kanala bağlı. Asistanın görevi kendisi değiştirmesi ya
da not yazması yok; bunlar onay kuyruğu PR'ında "öneri" olarak gelir.

**Doğrulama.** Sandbox'ta adım iki kez koşturuldu; Claude uygulamasıyla
uçtan uca: devret → kuyrukta `task` bloğu → claim → görev/değişiklikli
cevap 422 → metin cevabı → çekmecede cevap ve gelen kutusu satırı; notta
@Claude (açık çalışma varken ikinci not uyarı veriyor), önceki çalışmalar
bloğu, Durdur (geç gelen cevap/fail 409 `closed`), geri al, başarısız
çalışma bildirimi. Chromium'da çekmece ve satır etiketi.
`tests/workspace_delegation_test.php`.

**Doğrulanamayan.** Pinegrap AI'ın gerçek model çağrısı (ağ geçidi
sandbox'ta yok) ve gerçek Claude rutini; dev.pinegrap.com'da denenmeli.

---

## 2026.4.10 — Pano: dört Çalışma Alanı kartı (Görevlerim, Beni Bekleyenler, Haftam, Ekip Nabzı) (2026-10-13)

**İstenen.** Erdal: Workspace hakkında daha fazla takip widget'ı.

**Kapı.** `pg_dashboard_workspace_rights($user)` (`includes/dashboard/widgets.php`):
modül açık, hesap kullanabiliyor (rol < 3 ya da `manage_workspace`) ve
`ws_rights()` üye sayıyor; karşılamanın Çalışma Alanı şeridiyle aynı kapı.
Evetse modülü yükleyip haklarla döner. `welcome.php` kayıt defterinde dört
kartın `show`'u buna bağlı; Ekip Nabzı ayrıca plan panosu hakkı ya da
yönetilen bölüm ister (`ws_led_people()`).

**Şeritli kart yardımcısı.** `pg_widget_lanes($name, $label, $lanes,
$default)`: Operasyon Masası'nın düzeni (üstte sayılı şerit karoları, altta
basılı şeridin listesi) ortak fonksiyon oldu; widget 9 da bunu kullanıyor.
Her şeridin kendi gövdesi olduğundan bir öğe iki şeritte birden durabilir.
Açılan şerit: verilmezse içi dolu ilk acil şerit, sonra içi dolu ilk şerit.

**Kartlar.**
- 27 Görevlerim: `ws_tasks_list()` (scope mine, açık, 60) tek okumayla üç
  şerit: Gecikmiş, Bugün, Açık (ilk 12 ve "N açık daha"). Açık şeridin alt
  satırı pazartesiden beri biten görev sayısı. Karo rengi önceliği taşır.
- 28 Beni Bekleyenler: `ws_approvals_waiting()`, `ws_acks_waiting()`,
  `ws_home_talk()` (Çalışma Alanı ana ekranının okuyucuları). Anılmalar
  şeridinin sayısı okunmamışlar; okunmamış satırın adından sonra nokta var.
- 29 Haftam: bugünden altı gün sonrasına `ws_events_between()` ile
  kişiyi ilgilendiren etkinlikler (`ws_event_applies()`), bitmiş olanlar
  hariç; birkaç gün süren olay görünen ilk günde bir kez, bitiş günüyle.
  Aynı aralıkta teslimi olan açık görevler. Gün başlıkları Bugün, Yarın,
  sonra `ws_day_label()`. En çok 24 satır.
- 30 Ekip Nabzı: `ws_board()` ile bugünün tek günü. Dışarıda (izin ya da
  ziyaret), Yoğun (planlanan dakika kapasiteyi aşıyor, yüzdeye göre
  sıralı), Çalışıyor (`ws_task_time`'da açık sayaç; süre
  `data-pg-elapsed` ile dakikada bir ilerler; görülemeyen görevin adı
  "Bir görev").

Yenileme listesine 27, 28 ve 30 girdi (görünürken). 29 sayfa açılışında
gelir. Renkler açık ve koyu temada tanımlandı.

**Doğrulama.**
- `php tools/test.php`: kimlik testleri 30'a çıktı (`31` geçersiz);
  `pg_widget_lanes()` açılış seçimi ve işaretlemesi; modül kapalıyken dört
  kart da "Access denied".
- Sandbox + Playwright (iki ekip üyesi; biri izinde, birinin sayacı
  açık; gecikmiş, bugünkü ve haftalık görevler; onay isteği, okundu isteği
  ve anılma): dört kart 313×313, şerit seçimi yenilemeden sonra korundu,
  koyu tema. Çalışma Alanı kapatılınca kartlar listeden çıktı, hata yok.

Rol 3 bir kullanıcıyla ve canlı veriyle bakılmadı.

---

## 2026.4.10 — Güvenlik duvarı: ülke kısıtlaması (10.2) (2026-10-13)

**İstek.** Erdal: "bunu yaparken ayrıca waf altına ülke kısıtlama ekleyelim
(varsayılan kapalı.)" Ülkeyi dil yönlendirmesinin konum katmanı
(`pg_geo_country()`, bir önceki bölüm) verir; ikinci bir kaynak yazılmadı.

**Yer (`waf.php`).** `waf_run()`'da bot sınıflandırmasından sonra, hız
sınırından önce, kendi mandalıyla (`$done['country']`). Router aşamasında
koştuğu için `get_file.php`, `robots.txt` ve `sitemap.xml` de kapsanır.
İzin listesindeki adres ve `waf_is_excluded()` yolu bu noktaya hiç gelmez.
Bot sınıflandırmasından sonra olmasının sebebi `waf_verify_bot()`'un
"verified" kararıdır: `waf_handle_bot()` onu `waf_bot_verified(true)` ile
işaretler, kısıtlama okur.

**Karar (`waf_country_refused()`).** `block` kipi listedekileri, `allow` kipi
listede olmayanları reddeder. Ülkesi bilinmeyen istek `waf_country_unknown`
açık değilse içeri alınır: veritabanı eksik ya da indirilemiyorsa site
kapanmaz. `T1` (Cloudflare'in Tor kodu) listeye ayrıca yazılabilir.
Liste `waf_country_codes()` ile okunur (büyük harf, sıra korunur, tekrar ve
geçersiz kod atılır); kayıtta kodlar `pg_sales_map_iso_countries()` + T1'e
karşı denetlenir.

**Muafiyetler (`waf_country_request_exempt()`).**
- `robots.txt` ve `.well-known/` her zaman (`waf_path_is_always_served()`):
  sertifika yenilemesi birkaç ülkeden denetlenir.
- API, MCP, OAuth (`waf_country_exempt_api`, varsayılan açık): çağıran kimlik
  sunar ve sağlayıcısının bulunduğu yerden gelir. `api.php` bu sınıfa girmez,
  sitenin kendi sayfalarının (mağaza, sepet, canlı sohbet) konuştuğu uçtur.
- Yönetim paneli (`waf_country_exempt_panel`, varsayılan açık): yazılım
  dizini altındaki her adres; `get_file.php` ve `router.php` hariç (yeniden
  yazma kuralları sitenin her adresini `router.php` ile koşar). `api.php` yalnız
  oturumu personele (rolü 3'ten küçük) aitse panel sayılır; panelin kendi
  XHR'ları böylece çalışır, ziyaretçi mağaza API'sine kısıtlanmış ülkeden
  ulaşamaz.
- Adını adresiyle doğrulayan arama motoru botu (`waf_country_exempt_bots`,
  varsayılan açık); "Arama Motoru Botlarını Doğrula" açık olmalıdır.

Sitenin sayfaları ve dosyaları panel muafiyetiyle açılmaz: router aşamasında
oturum yoktur. Yurt dışındaki personelin bunlara da ulaşması için adresi
izin listesine eklenir; kart bunu söyler. Oturum çerezi taşıyan isteği
init aşamasına bırakıp personeli orada tanımak düşünüldü, çerezin kendisi
bir şey kanıtlamadığı ve dosya yolu init'e uğramadığı için yapılmadı.

**Kayıt ve yanıt.** `waf_log_event('block' | 'would-block', 'geo-country',
'country', 10, 'COUNTRY', 'DE (cloudflare)')`; engelleme sayfası `waf_deny(403)`.
İzleme kipinde yalnız kayıt. Suç sayılmaz (`waf_register_offence()` yok):
ülkesi kısıtlı ziyaretçi saldırmadı, otomatik yasak onu ikinci kez cezalandırır.
Güvenlik Duvarı Kaydı ve pano kartı 22'de kategori "Ülke Kısıtlaması".

**Panel.** Ayarlar › Güvenlik Duvarı'nda `pgset-countries` kartı (arama
kayıt defterinde de): kip, ülke kodları (tagin; altında ülke adları),
bilinmeyen ülke, üç muafiyet, ekranı açanın ülkesine ne olduğu (izin
listesindeyse onu da söyler), konum ve veritabanı satırı
(`pg_geo_settings_note()`, Diller ekranıyla ortak) ve "şimdi indir" düğmesi.
Kayıt kuralları: `allow` kipinde boş liste reddedilir; panel muaf değilken
kaydeden kişinin ülkesi reddedilecekse, gönderilen izin listesi adresini
kapsamıyorsa kayıt reddedilir (kendini kilitleme).

**Konum veritabanı.** `pg_geo_db_needed()` güvenlik duvarı açık ve kısıtlama
`allow`/`block` iken de doğru döner; genel iş veritabanını bu durumda da
güncel tutar. Ayarlar ekranının kendiliğinden indirmesi
(`pg_geo_db_refresh_for_screen()`, iki ekranda ortak) artık 12 saatlik
deneme bekleyişine uyar: indiremeyen sunucuda her kayıt beklemez.

**Şema (10.2).** `config.waf_country_mode` ENUM('off','allow','block') 'off',
`waf_country_list` TEXT NULL, `waf_country_unknown` TINYINT(1) 0,
`waf_country_exempt_panel` / `_api` / `_bots` TINYINT(1) 1.
`tests/innodb_test.php`: 424 kolon, TEXT dönüşümünden önce MySQL 8.0'da 8602
bayt, sunucu satırı 64.110 bayt.

**Doğrulama.** `tests/waf_country_test.php` (liste okuma, kip kararı,
bilinmeyen ülke, her zaman açık yollar). Sandbox'ta migration iki kez koştu;
kart ve kayıt kuralları panelden denendi. Engelleme kipinde, 127.0.0.1
güvenilen vekil, CF-IPCountry ile:
- `allow TR,US`: US 200, DE ve T1 403, başlıksız ve XX 200; tema dosyası
  DE 403, US 200; `sitemap.xml` DE 403; `robots.txt` DE 200;
- panel giriş sayfası ve giriş DE'den çalışır, panel ekranları 200;
  personel oturumuyla `api.php` 200, ziyaretçiyle 403; giriş sonrası ön
  yüz sayfası 403;
- `integration.php` ve `mcp.php` DE'den kısıtlanmaz, API muafiyeti
  kapalıyken `integration.php` 403;
- panel muafiyeti kapalıyken panel DE 403, FR 200;
- `waf_country_unknown=1`: başlıksız ve XX 403;
- `block DE,T1`: DE ve T1 403, FR ve başlıksız 200;
- izleme kipinde DE 200 ve "would-block" kaydı; izin listesindeki adres DE'den
  200;
- sahte Googlebot DE'den bot kuralıyla 403.

**Doğrulanmayan.** Gerçek Cloudflare arkasında ve IIS'te deneme, gerçek arama
motoru botunun ters DNS doğrulamasıyla geçişi.

---

## 2026.4.10 — Pano üst alanı: karşılama ve saat tek satırda, sıradaki toplantı geri sayımı, "Başka bir şey söyle" (2026-10-13)

**İstenen.** Erdal: ekranın üst kısmındaki saat ve brief alanları
geliştirilsin.

**Brief ayrı dosyada.** `welcome.php`'deki yaklaşık 1.700 satırlık
karşılama kodu `includes/dashboard/brief.php` → `pg_dashboard_brief($user,
$changelog_sections)` oldu. Davranış aynı; tek fark sistem durumu önbellek
yolunun `dirname(__FILE__)` yerine `PG_FUNCTIONS_DIR` ile kurulması. Dönen
dizi: `icon`, `greeting`, `line`, `strip`, `next`, `ws`. Sayfa da yeni
`get_dashboard_brief` paneli eylemi de bu fonksiyondan geçer.

**"Başka bir şey söyle".** Kartın sağında yuvarlak düğme.
`get_dashboard_brief`'i çağırır (muaf, jetonsuz, yazmaz; oturum açık
kalır çünkü cümle seçimi son söyleneni oturumda tutuyor). Gelen selam,
cümle, şerit ve saatin altındaki satır yerine konur, giriş animasyonu
yeniden oynar. İstek sürerken düğme döner ve kilitlenir.

**Saatin altında sıradaki etkinlik.**
- `ws_dashboard_signals()` artık `events_next` da döner: bugünün bitmemiş,
  kişiyi ilgilendiren toplantı ve ziyaretlerinden ilk üçü (`event_first`
  alanları + `id`, `ends_at`).
- `pg_dashboard_brief_next()` hepsini yazar, CSS ilkini gösterir.
  Saatli olanlar tüm gün olanlardan önce gelir.
- `pg_dashboard_brief_until()` kalan süreyi yazar: "25 dk sonra", "2 sa
  30 dk sonra", başladıysa "Şimdi". Dakika yukarı yuvarlanır. Sayfa
  betiği (`pg_clock_next()`) aynı hesabı saatin dakika tikinde yapar;
  biten etkinliği siler, sıradaki kendiliğinden öne çıkar, istek yok.

**Şeritte iki yeni öğe.** Çalışan görev sayacı (`ws_task_time_running()`,
en başta, yeşil nefes alan nokta, süre `data-pg-elapsed`'den dakikada bir
ilerler) ve onayını bekleyen istekler (`ws_approvals_waiting()`, bağlantı
en yenisinin kanalına). Cümle havuzuna da onay bekleyen istek cümlesi
girdi.

**Düzen.** 992 px ve üstünde `.pg-clockbar` satır: kart solda, saat
sağda sağa yaslı; altında tarih ve sıradaki etkinlik. Daha dar ekranda
eskisi gibi alt alta.

**Doğrulama.**
- `php tools/test.php`: `tests/dashboard_brief_test.php` (geri sayım
  yuvarlaması, durumlar, işaretleme, sıralama, kaçış).
- Sandbox + Playwright (Çalışma Alanı açık, bir görev sayacı, iki toplantı,
  bir tüm gün ziyaret): 1440, 1100 ve 390 px, koyu tema. Düğmeye üç kez
  basıldı; her seferinde farklı cümle geldi, tek `.pg-brief-line`, tek
  şerit, tek etkinlik satırı kaldı. Sayfada zaman ileri alınınca toplantı
  "Şimdi" oldu, bitince sıradaki geldi, hepsi bitince satır kalktı.

Onay isteği şeridi sandbox'ta denenmedi (kanal ve mesaj kurmak gerekiyordu);
`ws_approvals_waiting()` Çalışma Alanı ekranının kullandığı fonksiyon.

---

## 2026.4.10 — Pano: Operasyon Masası; en çok satanlar E-ticaret Özeti'nde, en çok ziyaret edilenler Ziyaretçi Özetleri'nde; panoda CodeMirror yok (2026-10-13)

**İstenen.** Erdal: bekleyen sevkiyat, bekleyen iade ve stokta tükenen
kartları güzel bir başlık ve kurguyla tek geniş karta insin. En çok
satanlar E-ticaret Özeti'ne, en çok ziyaret edilen sayfalar Ziyaretçi
Özetleri'ne girsin.

**Operasyon Masası (widget 9, eski Bekleyen Sevkiyatlar).**
- Üç şerit:
  - `pg_dashboard_ops_shipping()`: eski 9'un sorgusu aynen. 30 günlük
    pencere, `ship_tos.complete`, `saved_for_later` yoklaması. Özet
    satırı sipariş, paket, tutar ve en eski tarih; liste en çok 10 alıcı.
  - `pg_dashboard_ops_refunds()`: eski 26. `refund_status` kolonu yoksa
    şerit yok. Para birimine göre gruplu toplam: tek para biriminde
    tutar, birden çoksa "Birden çok para biriminde".
  - `pg_dashboard_ops_stock()`: eski 12'nin 8 satırı ve toplam sayı.
    `ECOMMERCE_LOW_STOCK_THRESHOLD` elle ayarlıysa eşiğin altına inen
    stoklu, ön siparişsiz ürünler "Azalıyor" başlığıyla. Eşik yoksa
    uydurma bir eşik konmaz.
- Şerit düğmeleri Aktivite'nin süzgeç düğmesi (`pg_widget_filter()`).
  Gövde `data-pg-filterable="ops"`, şerit gövdeleri `data-kind`. Seçim
  aynı mekanizmayla hatırlanır. `data-pg-default` en acil şerittir:
  - Ödenmemiş iade varsa iade.
  - Yoksa iki günü geçmiş sevkiyat.
  - Yoksa sayısı sıfırdan büyük ilk şerit.
- Sıfır olan şerit yeşil onay simgesi ve "Hepsi gönderildi" / "Bekleyen
  iade yok" / "Hepsi stokta" alt satırıyla durur. İki günü geçen sevkiyat
  ya da iade sayıyı kırmızı yapar.
- Kart artık `ECOMMERCE_SHIPPING`'e bağlı değil. Kargo kapalıysa yalnız
  gönderim şeridi çıkmaz. 12 ve 26 emekli (boş başarı, yeniden
  kullanılmaz).

**En çok satanlar (widget 3).** `pg_dashboard_best_sellers($since,
$limit)`: `complete` / `exported` siparişlerin satırlarından ürün başına
adet ve tutar. Eşit adette ürün kimliği sıralar. Eski Trend İçerik tüm
zamanları okuyordu. Şimdi son 30 gün okunur, o ay satış yoksa tüm
zamanlar. Başlık hangisi olduğunu söyler. Kart geniş oldu: solda özet,
sağda liste.

**En çok ziyaret edilenler (widget 5).** Şeridin dördüncü hücresi ("En Çok
Ziyaret Edilen") düğme oldu (`data-vs5="p"`). Tıklanınca grafiğin
yerinde `#vs5_pages` açılır:
- Son 7 günün en çok görüntülenen 8 içeriği
  (`pg_visitor_top_content()`).
- Bugünün en çok görüntüleneni listede alevle işaretli. Listede değilse
  "Bugün Yükselen" başlığıyla altta.
- Aynı hücreye ya da başka bir döneme tıklayınca grafik döner.
- Kart boyu değişmez.

Trend İçerik (6) emekli.

**CodeMirror.** `pg_page_shell()`'e `'code_editor' => false` (#254'teki
seçenek). Hiçbir pano kartı kod alanı çizmiyor. Site Ayarları diyaloğu
CodeMirror'ı gerektiğinde `window.pgCodeEditorReady()` ile yükler.
Sandbox'ta panoda yüklenen betik dosyası 47'den 14'e, betik baytı
1,48 MB'tan 0,68 MB'a indi (sıkıştırmasız).

**Doğrulama.** Sandbox + Playwright:
- 1440 px'te Operasyon Masası 658×316. Tükenen şeridi varsayılan açıldı,
  iki ürün listelendi. Seçim yenilemeden sonra korundu.
- 390 px'te şeritler ikinci satırsız sığdı.
- E-ticaret Özeti'nde en çok satanlar "Tüm Zamanlar"la geldi (sandbox
  siparişleri eski).
- Ziyaretçi Özetleri'nde liste grafiğin yerinde açıldı (veri yok
  durumu).

Bekleyen sevkiyat ve iade satırları sandbox'ta yoktu. Sorguları eski
kartlardan aynen taşındı, canlıda bakılmalı.

---

## 2026.4.10 — Çalışma Alanı API ve MCP: not yazma, plan kayıtları, arama, "benim günüm" (2026-10-13)

**Neden.** Çalışma Alanı araştırmasının P1 1.3 maddesi: dış API (ve ondan
türeyen MCP araçları) notları ve planlama panosunu yalnız okuyabiliyordu,
Ctrl+K araması ve kişinin "bugün neyim var" görünümü hiç yoktu. Claude ya da
ChatGPT'den "bu toplantının notunu Çalışma Alanı'na yaz", "perşembe 14:00'e
müşteri görüşmesi koy", "bugün neyim var" istekleri bu yüzden karşılıksızdı.

**Ne değişti.** On yeni rota; hepsi `ws_api_routes()`'ta
(`includes/workspace/api.php`) ve MCP'de kendiliğinden araç olur
(`pg_mcp_tool_from_route()`). İşleyiciler panelin kendi fonksiyonlarını
çağırır; kim neyi yazabilir sorusu orada cevaplanır.

| Rota | Kapsam | Ne yapar |
|---|---|---|
| `POST /workspace/notes` | workspace:write | Sahibin yeni kişisel notu (`ws_note_save()`); paylaşılana kadar kimse görmez |
| `POST`/`PATCH /workspace/notes/{id}` | workspace:write | Yalnız gönderilen alanlar; `base` ile çakışma 409 `changed_meanwhile` |
| `GET /workspace/plan-items`, `/{id}` | tasks:read | Toplantı, ziyaret, izin, tatil (`ws_events_between()`); `user_id`, `kind`, `from`, `days` |
| `POST /workspace/plan-items`, `POST`/`PATCH /{id}` | tasks:write | `ws_event_save()` üzerinden; kimin için plan yapılabileceğini o söyler |
| `DELETE /workspace/plan-items/{id}`, `POST …/{id}/delete` | tasks:write | `dry_run` destekli; POST takma adı MCP'de ayrı araç değil |
| `GET /workspace/search` | workspace:read | `ws_palette_search()`; `#` kayıt, `@` kişi; `type`, `channel_id`, `limit` |
| `GET /workspace/my-day` | workspace:read | Bugün biten/geciken görevler, sayaç, bugünkü plan, onaylar, okundu onayları, kişiye yazılanlar |

Ayrıntılar:

- **Notlar.** Boş not (başlık ve metin boş) 422. Okumak için paylaşılmış
  not 403; `pinned` yalnız sahibinin, başkası gönderirse 422 (ekran onu
  sessizce yok sayıyordu, API'de sessiz yok sayma yanıltıcı olurdu). Eski
  hâl ekrandaki gibi sürümlere girer, notu tutan diğerlerine haber gider.
  `ws_api_note_or_404()`'ün Claude kanalı süzgeci `ws_api_note_reachable()`
  olarak ayrıldı; arama da onu kullanıyor.
- **Plan kayıtları.** Kısmi güncelleme kaydın tam hâlini kurup gelen
  alanları üstüne koyar. Yalnız `start_date` gönderilirse kayıt gün
  uzunluğunu korur (iki günlük izin taşınınca bitişi de kayar). Saat
  `HH:MM` değilse 422 (ekranın sessizce 09:00'a düşmesi API'de yok).
  `ws_event_save()`'in tarih hataları 422 `start_date`/`end_date`, geri
  kalan reddi (şirket geneli, başkası için plan, kayıt sahibi değil) 403.
  Takvim adresinden okunan tatil (`from_calendar`) değiştirilebilir ama
  takvimin sonraki okunuşunda yeniden yazılır; açıklamada söyleniyor.
- **Arama.** Görevler yalnız `tasks:read` ile aranır (`type=tasks` onsuz
  403 `insufficient_scope`). Panel kayıtları türüne göre süzülür:
  `ws_api_search_record_scopes()` her türü okuyan kapsama eşler (sipariş
  `orders:read`, kişi `customers:read`, fatura `erp:read`, makbuz
  `erp_cash:read` …); kullanıcı hesabını okuyan kapsam olmadığı için o tür
  aranmaz. Bunun için `ws_palette_records()` ve `ws_palette_search()`
  isteğe bağlı tür listesi aldı (`record_kinds`); panel çağrıları değişmedi.
  `/` komut araması API'de boş döner. Adresler tam adres olarak verilir.
- **Benim günüm.** Görev, sayaç ve plan parçaları `tasks:read` ister;
  yoksa boş gelir ve `left_out` kapsamı adlandırır. Onaylar
  `WorkspaceApproval`, görevler `WorkspaceTask`, sayaç `WorkspaceTaskTime`
  nesnesiyle döner. Claude'un uygulaması yalnız sorulabildiği kanalları
  görür (onay, okundu, bahsetme).
- Yeni nesneler: `WorkspacePlanItem`, `WorkspaceSearchHit`,
  `WorkspaceMyDay` (`includes/workspace/api_day.php`);
  `tools/check_api_schema.php` sunucu–bildirim çiftlerini tanıyor.

**Bilerek yapılmayan.** Not silme ve paylaşma API'ye açılmadı: kanal
paylaşımı kanala kişinin adıyla kart yazar (API'nin "uygulamanın yazdığı
uygulama adıyla görünür" kuralına aykırı), silme geri alınamaz. Not ve plan
yazmada `dry_run` yok (doğrulama `ws_note_save()`/`ws_event_save()` içinde,
yazmadan ayrılmıyor; görev yazma da böyle); plan silmede var.

**Doğrulama.** Sandbox'ta iki kullanıcı ve üç uygulamayla (tam, yalnız
`workspace:read`, ikinci kişi) bütün yollar: oluşturma, kısmi güncelleme,
taşıma, 403/404/409/422 dalları, `dry_run`, `left_out`, kapsamla süzülen
kayıt araması; OAuth jetonuyla `mcp.php` `tools/list` (yeni dokuz araç
listede, `delete_post` yok) ve `tools/call`; panelin Ctrl+K araması
değişmeden çalışıyor (Chromium). `tests/workspace_api_test.php`: her
rotanın işleyicisi var, iki rota aynı yöntem+yolu paylaşmıyor, silme tek
araç, arama kapsam eşlemesi bilinen türleri adlandırıyor.

**Doğrulanamayan.** IIS (dev.pinegrap.com) ve gerçek bir Claude bağlantısı.

---

## 2026.4.10 — Görsel editör: klasik blog adresi, yeni sekmeye verilen sayfa, katalog sepete ekle ve hedef sayfa, sayfa adı çakışması (2026-10-13)

**Kaynak.** Güzel URL düzeltmesinden (aşağıda, PR #251) sonra görsel
editörün komşu alanlarında yapılan hata taraması. Beş hata, her biri ayrı
commit.

- **Klasik blog adresleri 404 (2026.4.9'da da var).** `layout_type`
  klasik sayfanın da varsayılanı `'system'`. `get_page.php`'nin system
  bloğu `/X/<segment>`'i X'e çevirip segmenti atıyordu; düzeni `system`
  olan klasik form liste görünümünde `/blog/<adres adı>` bu yüzden
  kaydı bulamıyor, adres 404 dönüyordu (düzeni `custom` olan sayfa
  etkilenmiyordu). System bloğu artık `page_type = 'form list view'`
  sayfayı kendi durumuna bırakıyor, o da segmentten adı okuyor.
- **Yeni sekmeye verilen detay sayfası kaydedilmiyordu.** Widget ayarı
  (`_patchSysCfg`) sunucuya hemen yazılıyor, değişmeyen sayfa ise kayıtta
  ağacı olmadan (`unchanged: 1`) gidiyor. `pg_designer_resolve_tab_refs()`
  `tab:<anahtar>`'ı yalnız gönderilen ağaçlardaki widget'larda çözüyordu;
  liste sayfası değişmemişse ayar `tab:…` olarak kalıyor, link hiçbir
  sayfaya gitmiyordu. Değişmeyen sayfanın ağacı artık veritabanından
  okunuyor; ayarında `tab:` geçen widget yoksa hiç okunmuyor.
- **Katalog listesinde kart üzerindeki "Sepete Ekle" ana sayfaya atıyordu.**
  Sepet sayfası seçilmemişse form `next_page_id = 0` gönderiyor,
  `catalog_detail.php` sonraki sayfayı `catalog_detail_pages`'te arıyordu;
  editör sayfasının orada satırı yok, ziyaretçi ana sayfaya gidiyor, logda
  PHP uyarısı kalıyordu. Kart artık ürün detayı widget'ı gibi listeye
  bildirimle (`?cart_added=`) dönüyor; `catalog_detail.php` satır yoksa
  sonraki sayfa yok sayıyor.
- **Katalogda "Grup gezinmesi → Hedef sayfa" hiç çalışmıyordu.** Ayar
  kaydediliyor ama okunmuyordu: içerik haritası, grup ağacı ve grup kartları
  yalnız `drill_down` için kuruluyordu; bu kipte grup görünmüyor, kökünde
  ürün olmayan katalog boş görünüyordu. Grup linkleri artık
  `/<hedef sayfa>/<grup>`'a gidiyor; editör kataloğu da klasik katalog
  sayfası da grubu bu adresten alıyor. Hedef seçilmemiş ya da silinmişse
  drill-down'a düşülür. Hedef seçici yalnız klasik katalog sayfalarını
  listeliyordu; artık katalog listesi widget'ı olan editör sayfalarını da
  listeliyor (ürün detayı widget'ının katalog seçicisiyle aynı liste,
  `list_catalog_listing_pages`; JS'teki `_fetchCatalogPages()` kalktı).
- **Sayfa adı dosya, kısa link ya da kök klasörle çakışabiliyordu.** Editör
  kaydı yalnız diğer sayfalara bakıyordu; klasik sayfa ekranları
  (`check_name_availability()`) dosyalara, kısa linklere, site kökündeki
  dosya ve klasörlere, `sitemap.xml` / `robots.txt`'ye de bakar. Böyle
  adlandırılan editör sayfası hiç görünmüyordu (`/pinegrap` yönetim
  klasörünü açar). Yeni `pg_designer_page_name_taken_elsewhere()` ad
  verilirken ya da değişirken çalışır; bu adı zaten taşıyan sayfa
  kaydedilmeye devam eder. `check_name_availability()` kullanılmadı: site
  kökünü çalışma dizininden `'../'` diye okur, MCP sunucusundan gelen
  kayıtta çalışma dizini yazılım klasörü değildir.

**Yapılmayanlar.** Taramada çıkan iki küçük nokta bırakıldı: CKEditor'ün
link seçicisi sayfa adresini kodlamadan basıyor (Türkçe karakter
tarayıcıda çalışıyor; `#`/`?` içeren ad nadir) ve tasarımcı sayfa
seçicileri (`list_all_pages` ve diğerleri) Geri Dönüşüm Kutusu'ndaki
sayfaları da listeliyor (seçili sayfa listeden düşerse seçici "seçilmedi"
gösterir, ayrı karar ister). Sandbox'ta doğrulandı (hello-pinegrap
tasarımı, editör için Playwright); dev.pinegrap.com'da denenmedi.

---

## 2026.4.10 — Çok dilli site: otomatik dil yönlendirmesi ve ziyaretçi konumu (10.1) (2026-10-13)

**İstek.** Erdal: "kullanıcı diline veya konumuna göre otomatik uygun dile
yönlendirme mekanizması ayarlıyalım. isabetli olsun." Aynı istekteki WAF
ülke kısıtlaması ayrı PR'da gelir ve buradaki konum katmanını kullanır.

**Konum katmanı (`includes/geo.php`).** `waf.php` gibi bağımsızdır
(functions.php, oturum, `lang()` yok) ve `waf.php` onu yükler; böylece
router'ın WAF aşaması da ülkeyi okuyabilir. `pg_geo_country()` istek başına
bir kez `code` ve `source` döner. Kaynak sırası:

1. Kenarın başlığı (`CF-IPCountry`, `CloudFront-Viewer-Country`), yalnız
   `waf_client_ip()`'nin adres başlığına inandığı eşte (Cloudflare aralığı
   ya da `waf_trusted_proxies`, Cloudflare Tunnel'da 127.0.0.1). Ülke ile
   adres aynı yerden gelir; sunucuya doğrudan gelen sahte başlık okunmaz.
   `XX` bilinmiyor sayılır, `T1` (Tor) korunur.
2. Sunucu modülü (`GEOIP_COUNTRY_CODE`, `MM_COUNTRY_CODE`), yalnız arada
   vekil yokken (modül bağlanan adresi bulur).
3. Sitenin kendi veritabanı, `data/geo/`: DB-IP "IP to Country Lite" (CC BY
   4.0, aylık). Sıralı, sabit boyutlu kayıtlar (IPv4 4 bayt, IPv6 /64'ün 8
   baytı, ardından 2 harf); disk üzerinde ikili arama yaklaşık yirmi kayıt
   okur. İstek hiçbir ağ çağrısı yapmaz. Özel ve ayrılmış adres sorulmaz.

**Veritabanı (`includes/fn/geo.php`).**
- `pg_geo_db_refresh()` bu ayın listesini, henüz yayınlanmadıysa geçen ayınkini
  indirir (cURL, 60 sn, `pinegrap_user_agent()`, `pg_curl_tls()`).
- Satırlar aralık kayıtlarına çevrilir (`pg_geo_db_records()`): komşu aynı
  ülke birleşir, delikler ve uçlar `--` olur, sırası bozuk liste reddedilir.
  Aile başına 1000 kaydın altı "eksik" sayılır ve eski veritabanı kalır.
- Dosyalar derlemenin adını taşır; `meta.json` en son, rename ile yazılır.
  Windows açık dosyayı yeniden adlandırmadığı için okunmakta olan çift hiç
  değiştirilmez, eski çiftler sonradan silinir. Tek derleme `flock` ile;
  başarısız deneme 12 saat beklenir (`attempt.txt`).
- Genel iş (`job.php`) `pg_geo_db_due()` ile yalnız konumu okuyan bir ayar
  açıkken ve veritabanı bu ayın değilse indirir. Ayarlar ekranı ilk kayıtta
  ve "şimdi indir" düğmesiyle indirir.

**Yönlendirme (`includes/fn/language_redirect.php`).** Kanca `get_page.php`'de,
sayfa bulunup arşiv denetimi geçtikten sonra, sayfa türlerinden önce.
Karar sırası (`pg_lang_redirect_decide()`):

1. `pg_language` çerezi: ziyaretçinin kendi seçimi.
2. Accept-Language: q'ya göre, eşitte yazılış sırasıyla. `de-AT` → `de`;
   Çince yazı ya da bölgeyle zh-CN / zh-TW, Portekizce Brezilya'da pt-BR;
   `iw`, `in`, `nb`, `nn`, `fil` eş adları; en son aynı dilin başka çeşidi.
3. Ülke: `pg_lang_country_languages()` resmi dilleri en yaygını önde verir.
4. Yedek dil: yalnız ziyaretçi bir işaret verdiyse ve site onun dillerinin
   hiçbirinde yoksa. İşaret yoksa sayfa olduğu gibi kalır.

Kip `language`, `country` ya da `both`; ülke yalnız gerekince sorulur.

**Kim yönlendirilmez.** GET olmayan istek; `Sec-Fetch-Dest` belge olmayan
(çerçeve, editör önizlemesi); XHR; `IS_BOT` ya da `waf_classify_bot()`
insan demeyen istemci; oturum açmış personel; dil dizinindeki adres
(`/en/...` istendiği gibi açılır); aynı siteden gelen istek (referer bu
host). Yani yalnız ziyaretin ilk sayfası yönlenir. API, MCP, OAuth ve
integration uçları `get_page.php`'ye hiç uğramaz.

**Kapsam.** `home` (varsayılan) yalnız sitenin kökü, `all` görsel tasarımın
her sayfası. Her iki durumda sayfanın çevrilmiş gövdesi olmalıdır:
`layout_type = 'system'` ve ağaç (`pg_page_tree_sql_expr()`, çeviri
çizicisinin kullandığı test). `layout_type` tek başına yetmez, eski sistem
düzenleri de 'system' taşır.

**Seçimin hatırlanması.** Aynı siteden gelen istekte referer'ın dili bu
sayfanınkinden farklıysa (dil değiştirici ya da dizinler arası link), bu
sayfanın dili `pg_language`'a yazılır (bir yıl, HttpOnly, SameSite=Lax).
Tarayıcıların varsayılanı ve WAF güvenlik başlıklarındaki
`Referrer-Policy: strict-origin-when-cross-origin` aynı kökende tam yolu
gönderir.

**Yanıt.** 302, `Cache-Control: private, no-store`, `Vary: Accept-Language,
Cookie`; sorgu dizesi korunur. Arama motorunun dizinlediği adres sayfanın
kendisidir; `hreflang` x-default kaynak adresi göstermeye devam eder.

**Panel.** Ayarlar › Diller ve Çeviri'de `pgset-language-redirect` kartı:
- anahtar, kip, kapsam ve yedek dil (yalnız yayındaki hedef diller);
- ekranı açanın tarayıcısının nereye gönderileceği (`pg_lang_redirect_decide()`
  kayıtlı ayarla);
- bu isteğin konumu ve kaynağı, veritabanının durumu;
- lisansın istediği DB-IP atfı ve "Konum veritabanını şimdi indir" düğmesi.

Hedef dil yokken uyarı, kolonlar yokken not gösterir.

**Şema (10.1).** `config.language_redirect` TINYINT(1) 0,
`language_redirect_mode` ENUM('language','country','both') 'both',
`language_redirect_scope` ENUM('home','all') 'home',
`language_redirect_fallback` TEXT NULL. Sabitler `LANGUAGE_REDIRECT*`
(`init.php`). `tests/innodb_test.php` sayıları güncellendi: 418 kolon, TEXT
dönüşümünden önce MySQL 8.0'da 8556 bayt, sunucu satırı 64.095 bayt.

**Ödün / sonraya kalan.** Ülke → dil haritası kodda sabittir; bölgesel diller
(Katalonya gibi) ayrı ele alınmaz. `both` kipinde tarayıcı ile konum farklı
dil derse tarayıcı kazanır.

**Doğrulama.** Birim testleri: `tests/language_redirect_test.php` ve
`tests/geo_test.php` (uydurma bir listeden derleme ve arama dahil).
Sandbox'ta hello-pinegrap şablonu kuruldu (kaynak tr, hedef en), ayar panelden
kaydedildi ve ön yüzde 30 durum denendi:
- en tarayıcı 302 ile /en/'e gider, tr tarayıcı kalır;
- aynı sitenin referer'ı kalır, Google referer'ı yönlenir;
- /en/'e geçiş çerezi yazar; çerez tr iken en tarayıcı kalır;
- Googlebot, iframe, HEAD, oturumlu personel ve eski düzen sayfası kalır;
- home ve all kapsamları, yedek dil;
- güvenilen vekilden gelen CF-IPCountry (both, country, T1) okunur,
  güvenilmeyen eşten gelen sahte başlık okunmaz;
- kapalıyken hiçbir istek yönlenmez.

Migration taze kurulumda koştu, kolonlar silinip iki kez daha koşturuldu.

**Doğrulanmayan.** DB-IP'den gerçek indirme: sandbox'ın ağı
download.db-ip.com'a kapalıdır ("CONNECT tunnel failed, response 403"),
derleme uydurma listeyle sınandı. IIS'te ve Cloudflare arkasında deneme.

---

## 2026.4.10 — Pano: Aktivite kartı, altı liste kartı tek süzgeçli akışta (2026-10-13)

**İstenen.** Erdal: son form, rehber, kullanıcı, site günlüğü ve kampanya
kartları aktivite özetiyle birleşsin. Akış filtreli olsun, başka aktivite
türleri de süzülebilsin.

**Değişiklik.** Widget 7 (eski adı Son Güncellemeler) Aktivite oldu.
Geniş bir kart: solda türler, sağda zamana göre akış.
- Türler ve kaynakları (`pg_dashboard_activity_kinds()`):
  - İçerik: eski 7.
  - Formlar: eski 13.
  - Siparişler: yeni.
  - Rehber: eski 10.
  - Yorumlar: yeni.
  - Kullanıcılar: eski 11.
  - Kampanyalar: eski 19.
  - Site Günlükleri: eski 17.
- Her tür eski kartının kapısını ve kapsamını taşır:
  - Formlar: rol 3 yalnız erişebildiği klasörlerdeki form sayfaları,
    `complete = '1'`.
  - Rehber: rol 3 düzenleyebildiği gruplar.
  - Kullanıcılar: yönetici kendi rolünün altını görür.
  - Siparişler: `manage_ecommerce`, sepette kalanlar hariç.
  - Yorumlar: rol < 3. Rol 3'ün klasör kapsamı Yorum Moderasyonu
    kartında.
- Her tür en çok 12 satır getirir. "Tüm Aktivite" aynı satırları zamana göre
  birleştirir. Aynı saniyedeki satırları kimlik sıralar, yenilemede yer
  değiştirmezler.
- Tür düğmesi bugünkü sayıyı ve yedi günlük çubukları gösterir
  (`pg_activity_daily()`, tür başına tek sorgu). İçerikte günlük seri yok:
  bir düzine tabloya yayılıyor. Bugünkü sayısı getirilen satırlardan
  çıkar; bugün 12'den az değişiklik varsa kesindir, yoksa alt sınırdır.
- Seçilen türün tam listesine bağlantı listenin altına çıkar (gönderilen
  formlar, siparişler, rehber, yorumlar, kullanıcılar, kampanyalar,
  günlük). Tür yoksa "Son zamanlarda bu türden bir şey yok." yazar.
- Ortak parçalar (`includes/dashboard/widgets.php`):
  - `pg_widget_row()` `data` özelliğiyle `data-*` öznitelikleri yazar.
    Anahtar `[a-z0-9-]`, değer kaçışlı.
  - `pg_widget_bars()`: yedi günlük CSS çubukları.
  - `pg_widget_filter()`: süzgeç düğmesi. Renk düğmede `--pg-k`
    olarak durur.
- Sayfa betiği (`welcome.php`) genel bir süzgeç taşır:
  `[data-pg-filterable]` gövde, `[data-pg-filter]` düğme, `[data-kind]`
  satır, `.pg-filter-more`, `.pg-filter-none`. Seçim tarayıcıda
  `localStorage`'da kart başına durur. `try/catch` içinde, yoksa
  varsayılan. Her yeniden çizimden sonra (`pg:widget-drawn`) geri konur.
  Operasyon Masası da aynı mekanizmayı kullanacak.
- Dar kartta (telefon) türler akışın üstünde çip satırı olur. Çubuklar ve
  ikinci satır gizlenir.

**Hız.** Eski Son Güncellemeler page, files, folder, short_links ve menus
tablolarını her dakika `LIMIT`'siz okuyordu (staff için her satır). Şimdi
her tablo en yeniden sınırlı okunur. Rol 3'ün satırları okunduktan sonra
klasöre göre süzüldüğü için onun tabloları 200 satıra kadar okunur.
Kampanyalarda ilerleme, listelenen kampanyaların alıcıları üzerinden tek
`GROUP BY` sorgusuyla gelir. Eskiden kampanya başına iki sorgu vardı:
yirmi satır için kırk sorgu. Altı kart altı istek yerine tek kart tek
istek.

**Emekli kimlikler.** 10, 11, 13, 17 ve 19 boş başarı döndüren dosyalar
olarak kalır. Yükseltme sırasında açık kalan sekme bunları istemeye devam
eder; boş başarı iskeleti temizler. Kimlikler yeniden kullanılmaz. Kayıtlı
dizilişte bu kimlikler yok sayılır; 7 yerinde kalır.

**Ödün.** Formlar kartının başlığındaki "bugün N form" çizgisi ve form
başına sayım yok; sayı tür düğmesinde. Eski Site Günlükleri satırı
mesajdaki adresi link yapıyordu. Satırın kendisi link olduğu için
(iç içe `<a>` geçersiz) adres artık düz metin. Rol 3 için form ve rehber
günlük sayıları eskisi gibi site geneli sayılır, kapsamlı değildir;
içerik değil yalnız sayı.

**Doğrulama.** `php tools/test.php`: satır `data` öznitelikleri, çubuk
ölçeği, süzgeç düğmesi. Sandbox + Playwright:
- 1440 px'te kart 658×316.
- "Formlar" seçilince 50 satırdan 12'si görünür kaldı.
- Sayfa yenilenince seçim korundu.
- 390 px'te çip düzeni ve koyu tema denendi.

---

## 2026.4.10 — Dış API: OAuth'la bağlanan uygulamalar panelde (operatör kapatır/kaldırır, kişi kendi bağlantılarını sonlandırır) (2026-10-13)

**Neden.** Faz A'dan beri kişi bir bağlantıyı ancak parolasını
değiştirerek ya da her yerden çıkış yaparak sonlandırabiliyordu; bu, bütün
oturumlarını ve bütün bağlantılarını birlikte kapatıyordu. Operatör
bağlantıları OAuth uygulamasının Bağlantılar sekmesinde tek tek
sonlandırabiliyordu, ama kendini kaydetmiş istemcileri göremiyor, birini
durduramıyordu. `oauth_clients.status` alanı Faz A'dan beri vardı, onay
ekranı ve token ucu `disabled`'ı reddediyordu, ama onu yazan bir ekran
yoktu (`docs/_plan_oauth.md` Kapsam, Faz B).

**Tasarım.**

- **Kişinin ekranı: `connected_apps.php` (Bağlı uygulamalar).** Panel
  hakkı olan herkes yalnız kendi OAuth bağlantılarını görür
  (`pg_oauth_connections_of_user()`: `api_devices` satırları,
  `oauth_client_id > 0`). Her kartta uygulamanın adı, kimlik adresi
  (kimlik belgesiyle gelen istemcide), bağlandığı adres (MCP sunucusu ya da
  API, `pg_oauth_resource_key()`), "Yapabilecekleri" listesi
  (`pg_oauth_scope_lines()`: onaylanan kapsamlar, `api_scope_groups()`
  sırasıyla "Salt okunur / Okuma ve yazma / Yönetim"), bağlanma tarihi, son
  kullanım ve adres var. "Bağlantıyı sonlandır" yalnız o bağlantıyı
  `api_device_revoke()` ile kapatır. "Tüm bağlantıları sonlandır" kişinin
  diğer oturumlarına dokunmaz. Kimlik formdan gelir, bu yüzden
  `pg_oauth_connection_end()` satırın kişiye ait ve OAuth bağlantısı
  olduğunu sorguda denetler. Operatörün kapattığı uygulamanın kartında
  "Site tarafından kapatıldı" yazar. Ekran kullanıcı menüsünde Hesabım'ın
  altında, yalnız etkin bir OAuth uygulaması varken çıkar
  (`output_header()`; sorgu `@mysqli_query` ile, db yardımcıları sorgu
  hatasında ölümcül olduğu ve 10.70 öncesi şemada `kind` sütunu olmadığı
  için). Yardım bağlantısı `api#mcp`.
- **Operatörün kartı: Uygulama Erişimi → "Bağlı uygulamalar".** Bir OAuth
  uygulaması varken ya da kayıtta istemci kaldıkça çıkar
  (`pg_oauth_clients_overview()`: bağlantısı olanlar önce, sonra son
  kullanıma göre). Her istemcide ad, kaynağı ("Kimliği: alan adı",
  "Kendini kaydetti" ya da "Site ekledi"), durum, yanıtın gittiği yerler
  (loopback adresler tek "Bu bilgisayardaki bir program" etiketi, diğerleri
  `pg_oauth_redirect_destination()` metni), bağlantı sayısı, son kullanım,
  kayıt tarihi ve yazılım sürümü var.
  - **Kapat / Aç** (`pg_oauth_client_set_status()`): kapalı istemci yeni
    bağlantı başlatamaz (onay ekranında ayrı bir "bu sitede kapatıldı"
    mesajı), yenileme jetonu token ucunda `invalid_client`, erişim jetonu
    her istekte 401 alır (`api_device_oauth_check()`, Faz A'dan beri).
    Bağlantılar silinmez; açılınca kimseye yeniden sorulmadan geri gelir.
  - **Kaldır** (`pg_oauth_client_delete()`): istemcinin bütün
    bağlantılarını `api_device_revoke()` ile kapatır, bekleyen kodlarını ve
    satırını siler. Kendini kaydeden istemci (açık dinamik kayıt) ve kimlik
    belgesiyle gelen istemci tekrar gelebilir; kişiye onay ekranı yeniden
    sorulur. Kartın notu bunu ve kalıcı durdurmanın "Kapat" olduğunu söyler.
- **Kapatma kararı korunuyor.**
  - `pg_oauth_clients_sweep()` kapalı istemciyi silmiyor. Satır kararın
    kaydıdır; süpürme kimlik belgeli kapalı istemciyi silseydi istemci bir
    sonraki gelişinde belgeden açık olarak yeniden okunurdu.
  - `pg_oauth_client_resolve()` kapalı satırın belgesini süresi geçse de
    yeniden çekmiyor; istemci belgeyi değiştirse de kapalı kalır.
- Onay ekranının alt notu artık bağlantının "panelde hesap menünüzdeki Bağlı
  uygulamalar ekranından" sonlandırılabileceğini söylüyor (önce parola ya da
  her yerden çıkış diyordu).
- Kapatma, açma, kaldırma ve sonlandırma etkinlik günlüğüne yazılıyor.
  Türkçe metinler `tr.json`'da (34 anahtar).

**Bilinen sınır.** İzin verilen istemci listesi (allowlist) yok: dinamik
kayıt açık kalır, operatör istemciyi geldikten sonra kapatır. Onaylanmamış
istemciyi baştan bekleten bir kip istenirse ayrı iş.

**Doğrulama.** Sandbox'ta (PHP yerleşik sunucu, IIS kuralını taklit eden
yönlendirici) Claude ve Cursor kayıt gövdeleriyle iki istemci bağlandı:
- Kişinin ekranı iki kartı, kapsam satırlarını ve son kullanımı gösteriyor.
  Masaüstü ve 390 px genişlikte görüntüsü alındı.
- Kapat: aynı jetonla `mcp.php` 401, yenileme `invalid_client`, authorize
  "kapatıldı" sayfası, kişinin kartında rozet. Aç: aynı jeton 200.
- Kaldır: istemci, bağlantıları ve kodları silindi, jeton 401.
- Başka kişinin `connection_id`'si yok sayıldı. Tek ve tümünü sonlandırma
  çalıştı. Günlük satırları yazıldı.
- `pg_oauth_clients_sweep()` kapalı ve eski istemciyi silmedi, açık ve eski
  bağlantısızı sildi.
- Menü bağlantısı yalnız etkin OAuth uygulaması varken çıktı. Yardım
  bağlantısı `pinegrap.com/tr/api#mcp`. Sunucu logunda uyarı yok.

**Doğrulanmayan.** Gerçek bir istemciyle (Claude, Cursor) kapatıp açma
canlı denenmedi; dev.pinegrap.com'da denenmesi gerekiyor.

---

## 2026.4.10 — Çalışma Alanı: betikler küçültülmüş tek dosyada, kayıt ekranlarında ilk tıkta yükleniyor, kod editörü gerektiğinde geliyor (2026-10-13)

**Neden.** Çalışma Alanı araştırmasının (P1 1.1) ölçümü: her Çalışma Alanı
ekranı küçültülmemiş ~1 MB Workspace betiği yüklüyordu (12 dosya). Sandbox'ta
Chromium ile ölçülen tablo daha ağırdı: kanal ekranı **59 betik, ~5,0 MB**.
Bunun 2,27 MB'ı panel başlığının her ekrana koyduğu CodeMirror ve linterleri
(jshint tek başına 1,2 MB), Çalışma Alanı ekranlarında hiç kullanılmıyordu.
Sipariş, ürün, kişi, kullanıcı ve ERP ekranlarındaki Çalışma Alanı düğmesi
(`ws_record_button()`) ise yalnız bir çekmece için ~1 MB betik yüklüyor ve
her açılışta `ws_bootstrap` + `ws_tick` istekleri gönderiyordu.

**Ne değişti.**

- **Derleme aracı:** `tools/build_js.php`. Neyin neyden derlendiği
  `pg_js_bundles()`'tadır (`includes/fn/output.php`); ekranlar da aynı
  listeyi okur. terser sürümü sabittir (`terser@5.51.2`, `npx` ile gelir),
  bayraklar eski reçeteyle aynıdır (`--compress --mangle --format
  ascii_only=true`), lisans yorumları kalır. `--check` geçici klasöre
  derleyip karşılaştırır; CI'da yeni adım olarak koşar, kaynağı değişip
  yeniden derlenmemiş `.min.js` PR'ı kırmızıya çeker. Satır sonları
  karşılaştırmaya girmez (Windows checkout'u CRLF'e çevirse de geçer).
- **Paketler:**
  - `workspace.min.js`: editör, tekrar, olaylar, şablonlar, süre,
    bağımlılık, onay, okundu ve `workspace.js`, sayfanın eskiden yüklediği
    sırayla (487 KB; kaynakları 1.017 KB).
  - `workspace_palette.min.js`, `workspace_board_channel.min.js`,
    `workspace_tour.min.js`, `workspace_guest.min.js`: bire bir ikizler.
  - Hangi ekranın neyi yüklediği değişmedi: arama kutusu kayıt çekmecesi
    dışında her ekranda, kanal panosu ve tur yalnız kanal ekranında.
- **Sunuş:** `pg_js_bundle_files()` / `pg_js_bundle_urls()` /
  `pg_js_bundle_tags()`. `ENVIRONMENT_SUFFIX` `min` ise (varsayılan)
  `.min.js`, `src` ise (ENVIRONMENT `development`) kaynaklar tek tek
  gelir. `.min.js` dosyası yoksa da kaynaklar gelir; eksik güncelleme ekranı
  bozmaz.
- **Kayıt ekranları:** `ws_screen_assets($viewer, 'record')` artık betik
  yüklemiyor, `ws_record_loader()`'ın küçük satır içi betiğini basıyor.
  Çalışma Alanı düğmesine ilk tıklama tutuluyor
  (`window.PGWsPendingRecord`), paket dosya dosya sırayla yükleniyor,
  `workspace.js` başlayınca `PGWsRecordReady`'yi yazıp tutulan çekmeceyi
  açıyor. Ondan sonra `workspace.js`'in kendi dinleyicisi cevap veriyor.
  Yüklenemeyen dosya sonraki tıklamada yeniden deneniyor.
- **Kod editörü:** `output_header()`'a `'code_editor' => false` özelliği
  (`output_control_panel_header_includes()`'un üçüncü parametresi). Açık
  olmadığında CodeMirror etiketleri `<template id="pg-code-editor-assets">`
  içinde bekliyor. `window.pgCodeEditorReady(markup, done)`
  (`backend.src.js`): CodeMirror sayfada yoksa ve gelen işaretlemede
  "CodeMirror" geçiyorsa dosyaları başlıktaki sırayla yükleyip `done`'ı
  çağırıyor; başka her durumda `done` hemen, aynı çağrıda koşuyor. Site
  Ayarları penceresi (`load()`) bölmeyi bununla boyuyor: kod alanlı bölme
  (Genel'deki cron satırları, SEO) Çalışma Alanı ekranında editör yüklenince
  çiziliyor, pencere o sırada "yükleniyor" kalıyor. Çalışma Alanı'nın sekiz
  ekranı (`workspace*.php`) bu özelliği kapatıyor.

**Ölçüm (sandbox, Chromium, `ENVIRONMENT_SUFFIX` min).**

| Ekran | Önce | Sonra |
|---|---|---|
| Kanallar | 59 betik, 4.996 KB (Workspace 1.057 KB, CodeMirror 33 dosya) | 18 betik, 2.240 KB (Workspace 510 KB, CodeMirror yok) |
| Görevler, Pano, Takvim, Zaman çizelgesi, Notlar, Şablon | 57 betik, 4.953 KB | 16 betik, 2.217 KB |
| Kullanıcı düzenle (kayıt ekranı) | 57 betik, 4.973 KB, `ws_bootstrap` + `ws_tick` | 48 betik, 3.983 KB, Çalışma Alanı isteği yok |

**Bilerek yapılmayan.**

- **`workspace.js`'i ekran başına bölmek.** Ekran sabitini terser'a
  `--define` ile verip ölü kodu attırarak ölçüldü: ekran başına yalnız
  %10–17 kazanç (367 KB → 305–330 KB). Kanal ekranının `app` nesnesi
  (8.000 satır) paylaşılan kodda da kullanılıyor (etiket alanları onun
  yöntemlerini ödünç alıyor, kanal formu ve programlanmış işlem formu ona
  bakıyor), terser nesne özelliklerini atamıyor. Ekran başına ayrı dosya
  ayrıca ekranlar arasında önbelleği bozardı: tek dosya bir kez inip bütün
  ekranlarda kullanılıyor. Gerçek bölme `app`'i parçalara ayıran ayrı bir
  refactor'dür.
- **DataTables, select2, Inputmask, jQuery UI'ı Çalışma Alanı ekranından
  çıkarmak.** `backend.src.js`'in hazır olunca koşan bölümleri bunları
  korumasız çağırıyor (`.inputmask()`, `.sortable()`); eksik eklenti
  ekranın geri kalan başlatmasını durdurur. CodeMirror farklı: yalnız
  ekranların kendi satır içi betikleri ve ayarlar bölmeleri kullanıyor.
- **Kod editörünü bütün panelde ertelemek.** Kendi kod alanı olan ekranların
  bir kısmı başlıktaki CodeMirror'a güveniyor; ekran ekran denetlenmeden
  açılamaz. Pano için `'code_editor' => false` aynı biçimde kullanılabilir.

**Davranış farkı.** Kayıt ekranları artık `ws_tick` göndermiyor. Zamanlayıcı
olmayan sitelerde tekrarlayan görevler, bekleyen Claude istekleri ve
programlanmış işlemler Çalışma Alanı ekranları açıldıkça ve genel iş (cron)
koştukça yürür; sipariş ya da ürün ekranını açmak artık bunları tetiklemez.

**Doğrulama.** `php tools/build_js.php --check` temiz (kaynağa tek karakter
değişikliği STALE ile yakalanıyor). Yeni `tests/js_bundles_test.php`: her
paket ve kaynak dosyası var, kaynak bir pakette bir kez geçiyor, her
`assets/js/workspace*.js` bir pakette, `workspace.js` en sonda, min kipte
yalnız `.min.js` seçiliyor. lint, check_lang, check_bindings,
check_api_schema, check_copies, test.php (338 test) temiz. Sandbox'ta
Playwright ile min ve src kipinde: yedi Çalışma Alanı ekranı ve ayarları
konsol hatası vermeden açılıyor; kullanıcı ekranında Çalışma Alanı düğmesi
paketi yükleyip çekmeceyi açıyor, ikinci tıklama `workspace.js`'in kendi
dinleyicisiyle açılıyor; Görevler ekranında Site Ayarları › Genel bölmesi
CodeMirror'u yükleyip 1 editör, SEO 3 editör çiziyor; yeni görev çekmecesi,
Ctrl+K arama kutusu, kanalda tur ve mesaj gönderme çalışıyor. Kanal
ekranında mesaj gönderildikten sonra görülen iki "jQuery is not defined"
hatası ve çözülemeyen dış adresler değişiklik öncesinde de aynen var
(sandbox dış ağa çıkamıyor).

**Doğrulanamayan.** IIS (dev.pinegrap.com) ve gerçek veriyle büyük kanal
geçmişi denenmedi; ölçüm sandbox'ın boş veritabanında.

---

## 2026.4.10 — Pano: kartlar tek istekte, yalnız görünürken yenileniyor; saat tarayıcıda (2026-10-13)

**Belirti.** Erdal: "Welcome ekranı çok kasmaya başladı." Pano yavaş
açılıyor, açık bırakıldığında tarayıcıyı yoruyordu.

**Kök sebep (koddan).** İlk açılışta her kart kendi `get_widget_data`
isteğini atıyordu: yirmi küsur istek, her biri `init.php`, WAF, oturum ve
veritabanı bağlantısını baştan kuruyor. Az PHP işçisi olan sunucuda istekler
birbirini bekliyordu. Sonra her dakika on üç kart daha isteniyordu. Bu,
sekme arka plandayken de, kart ekranın dışındayken de oluyordu. Gelen içerik
öncekiyle aynı olsa bile kart silinip yeniden çiziliyordu. Grafikler baştan
kuruluyor, liste en üste dönüyordu. Saat de dakikada bir ayrı istekti ve
istek zamanına göre bir dakikaya kadar geride kalıyordu.

**Değişiklik.**
- Yeni panel eylemi `get_widgets_data` (`includes/panel/dashboard.php`,
  `actions.php` tablosunda `get_widget_data` ile aynı satır: muaf, token
  yok, yazmaz). `pg_dashboard_widgets_run_many()`
  (`includes/dashboard/widgets.php`) her kimliği
  `pg_dashboard_widget_run()`'dan geçirir. Kartın erişim denetimi ve
  cevabı tek istektekiyle aynıdır. Tekrar eden kimlik bir kez, en çok 40
  kimlik.
- `welcome.php` sayfa betiği yeniden yazıldı:
  - Hafif kartlar tek toplu istekte gelir. Yavaşlar (1, 2, 5, 21, 23) ayrı
    istekle yanında gelir, ötekileri bekletmez.
  - Toplu istek bütünüyle düşerse kartlar eski yoldan tek tek istenir.
    Ölen bir kart yalnız kendini götürür. Yükseltmenin ortasında, eylemi
    bilmeyen bir kurulumda da pano çalışır.
  - Yenileme sekme gizliyken (`document.visibilityState`) atlanır. Sekmeye
    dönünce kaçan yenileme hemen koşar.
  - Ekranın dışındaki kart (`IntersectionObserver`, 200 px pay) bayat
    işaretlenir. Görünüme girince istenir. Art arda girenler 250 ms içinde
    tek isteğe toplanır.
  - Kartın HTML'i öncekiyle aynıysa DOM'a dokunulmaz. Değiştiyse
    grafikler yok edilir (`destroy_widget_charts`) ve kaydırma yerleri
    (sınıf adı ve aynı sınıftaki sırası) geri konur. Çizilen karta
    `pg:widget-drawn` olayı gönderilir.
  - Eski ilk koşu dalındaki `#system_status_widget` isteği kaldırıldı.
    Böyle bir öğe sayfada yoktu.
- Saat (`widget_clock.php`) cevabına `epoch`, `offset` (sitenin UTC farkı,
  saniye) ve `date` (uzun tarih satırı) eklendi. Sayfa sunucu saatini ve
  farkı bir kez alır, dakikayı kendi zamanlayıcısıyla tam vaktinde
  değiştirir. 12 saat biçiminde ÖÖ/ÖS etiketlerini `get_absolute_time()`
  verir. Her yenileme saati de getirir. Bilgisayar saatinin kayması ve yaz
  saati geçişi böyle düzelir. Gün dönünce tarih satırı sunucudan istenir.

**Ölçüm (sandbox, Playwright).** İlk açılışta 23 kart için 23 widget
isteği yerine 6 istek gitti (bir toplu, beş ayrı). Sayfanın üstündeyken
dakikalık yenileme yalnız görünen üç yenilenen kartı ve saati istedi.
Sekme gizliyken hiç widget isteği gitmedi. Dönüşte bir istek gitti.
Aşağı kaydırınca bayat kartlar iki istekte geldi. Kartlar ve iskelet
yerleşimi değişmedi. Sandbox verisi az olduğu için süre ölçümü anlamlı
değil; canlıda (dev.pinegrap.com) bakılmalı.

**Ödün.** Toplu istek en yavaş hafif kart kadar sürer. Bu yüzden ağır
olduğu bilinen beş kart ayrıdır. Liste kodda sabittir; yeni ağır bir kart
eklenirse oraya da yazılmalı. Görünürlük `IntersectionObserver` ister. Yoksa
(çok eski tarayıcı) her yenilemede tüm yenilenen kartlar istenir, yani eski
davranış.

**Belgeler.** Biten iki plan silindi:
- `docs/_plan_panel_api_widget.md`: panel API taşıması bitti (son adım
  #239). `_panel_api_eylemler.md`'deki gönderme not edildi.
  `CLAUDE.md`'deki plan satırı kalan planlara (`_plan_kargo`,
  `_plan_csrf_2fa`) indirildi.
- `docs/calisma-alani-kanal-pano-plani.md`: kanal panosunun 1–4.
  aşamaları 2026.4.8'de yapıldı (aşağıda "Çalışma Alanı: tek arama
  kutusu (Ctrl+K) ve kanal panosu"). Yapılmayan tek madde isteğe bağlı 5.
  aşama: elle sıralama (`ws_tasks.board_rank`, şema ister) ve kişiye
  özel varsayılan görünüm.

---

## 2026.4.10 — Dış API: OAuth ve MCP, Claude dışındaki istemcilerle de bağlanıyor (Cursor, VS Code, Gemini CLI, Codex, Windsurf, ChatGPT, mcp-remote) (2026-10-13)

**Neden.** OAuth sunucusu ve `mcp.php` yalnız Claude'la denenmişti. Diğer
MCP istemcilerinin kayıt gövdeleri, dönüş adresleri ve keşif sırası
kaynak kodlarından okundu: VS Code (`extHostMcp`, `oauth.ts`), MCP SDK,
mcp-remote, gemini-cli-core, Codex. Kodla karşılaştırınca şunlar bağlantıyı
kesiyordu:

- **Cursor** kayıtta `cursor://anysphere.cursor-mcp/oauth/callback`
  gönderiyor. Uygulama şeması için ters alan adı (noktalı şema) şartı
  vardı, kayıt `invalid_redirect_uri` ile reddediliyordu. Windsurf ve
  Kiro gibi tek kelimelik şemalı istemciler de aynı durumdaydı.
- **VS Code** `grant_types`'a `urn:ietf:params:oauth:grant-type:device_code`
  ekleyebiliyor. Bazı istemciler `response_types`'a fazladan değer
  koyuyor. Kayıt yalnız `authorization_code`/`refresh_token` ve yalnız
  `code` kabul ettiği için `invalid_client_metadata` dönüyordu.
- Loopback adresi kayıtta `http://127.0.0.1/` iken istemci
  `http://localhost:PORT/` ile gelebiliyor (ya da tersi). Yalnız port
  serbestti, ad farkı ve sondaki eğik çizgi farkı eşleşmiyordu.
- **Gemini CLI** ve SDK tabanlı istemciler `scope`'a `openid` ve
  `offline_access` koyuyor. Authorize'da yalnız bunlar istendiğinde
  `invalid_scope` dönüyordu. Yenilemede aynı dize tekrar gelince
  `invalid_scope` dönüyordu.
- Tek dönüş adresli istemci authorize'da `redirect_uri` göndermezse token
  ucu boş adresi kayıttakiyle karşılaştırıp reddediyordu.
- **Gemini CLI** önce MCP adresine HEAD atıp 401'deki
  `WWW-Authenticate`'ten keşif adresini arıyor. GET/HEAD doğrudan 405
  dönüyordu, meydan okuma yoktu.
- Eski SDK'lar `initialize`'da `2024-11-05` istiyor. Sürüm listesinde
  yoktu, istemci bilinmeyen sürüm cevabıyla vazgeçiyordu.
- Bazı istemciler yetenekten bağımsız `resources/list` ve `prompts/list`
  soruyor. `-32601` dönüyordu ve sunucu hatalı görünüyordu.
- Tarayıcı içi istemciler keşif belgelerini `MCP-Protocol-Version`
  başlığıyla okuyor. OAuth uçlarının CORS önuçuşunda bu başlık yoktu.

**Düzeltme.**

- `rules.php`
  - `pg_oauth_redirect_uri_is_acceptable()`: uygulama şeması artık
    noktasız da olabiliyor (`cursor://…`, `vscode://…`, `myapp:/cb`).
    Reddedilenler `pg_oauth_forbidden_redirect_schemes()`'de: javascript,
    vbscript, data, file, blob, about, filesystem, view-source, jar, ftp,
    ws, wss, mailto, tel, sms, intent. Kullanıcı adı/parola taşıyan ve
    iki noktadan sonrası boş adres (`cursor:`, `cursor://`) de
    reddediliyor. Kodu çalınan bir adresi PKCE koruyor (doğrulayıcı
    istemcide). RFC 8252 7.1 de ters alan adını öneri olarak bırakıyor.
  - `pg_oauth_loopback_rest()` ve `pg_oauth_redirect_uri_matches()`:
    loopback http adresi (127.0.0.1, localhost, ::1) yalnız yol ve
    sorgusuyla karşılaştırılıyor. Port ve loopback adı yok sayılıyor, boş
    yol `/` sayılıyor.
  - `pg_oauth_scope_requested()`: tanınmayan değerler atlanıyor. Tanınan
    hiçbir şey istenmediyse istemciye açık olan her şey öneriliyor
    (kapsamsız istek gibi). Kişi yine onay ekranında daraltıyor.
  - `pg_oauth_redirect_destination()`: onay ekranı için hedefin türü.
    `web` https sitesi, `device` bu bilgisayardaki program (loopback),
    `app` bu cihazdaki uygulama (şema).
- `endpoints.php`
  - Kayıt: `grant_types` `authorization_code` **içermeli**, `response_types`
    `code` **içermeli**. Fazlası reddedilmiyor, kayıtta ve cevapta her
    zaman `["authorization_code","refresh_token"]` / `["code"]` yazıyor
    (RFC 7591 2: sunucu desteklemediğini değiştirebilir, cevap neyin
    kaydedildiğini söyler).
  - Token: dönüş adresi authorize'daki gibi eşleştiriliyor. Authorize'da
    adres gönderilmediyse token'da da gönderilmeyebilir, gönderilirse
    istemcinin kayıtlı adreslerinden biri olmalı.
  - Yenileme: `scope`'taki tanınmayan değerler atlanıyor.
  - Önuçuş `MCP-Protocol-Version` başlığına izin veriyor.
- `authorize.php`: etkin dönüş adresi istemcinin gönderdiği (yoksa tek
  kayıtlı adres). Kod gönderilen adresle saklanıyor. Onay ekranı "Yanıtın
  gideceği yer" altında hedefin türünü ve adını gösteriyor. Bilinmeyen
  istemci sayfası (ör. sitede silinmiş dinamik istemci) kişiye
  uygulamadan yeniden başlamasını, olmazsa sunucuyu orada kaldırıp
  eklemesini söylüyor.
- `protocol.php`
  - `2024-11-05` sürüm listesine girdi. Bu sunucunun kullandığı
    `tools/list` ve `tools/call` o sürümde de aynı.
  - GET/HEAD önce kimlik soruyor: kimliksizse 401 ve meydan okuma, kimlikli
    ise 405 `Allow: POST, OPTIONS`.
  - `resources/list`, `resources/templates/list`, `prompts/list` boş liste
    dönüyor.
- `api_settings.php` (OAuth uygulamasının rehberi, çekmece ve sayfa):
  "Uygulamaya göre adımlar" kutusu eklendi. Claude (web/masaüstü/mobil),
  Claude Code, ChatGPT, VS Code (Copilot), Cursor, Windsurf, Gemini CLI,
  Codex ve diğerleri (mcp-remote) için açılır bölümler var. Her birinde
  kısa adımlar ve kopyalanabilir ayar ya da komut bulunuyor: `.vscode/mcp.json`
  `servers…type: http`, Cursor `mcpServers…url`, Windsurf `serverUrl`,
  Gemini `httpUrl` + `/mcp auth`, `codex mcp add … --url` + `codex mcp
  login`, `npx -y mcp-remote`. Rehberdeki adres `pinegrap/mcp.php`. 1. adım
  adresin aynen yazılmasını söylüyor: `www`'li/`www`'siz ya da sorgu dizeli
  adres, korumalı kaynak belgesindeki `resource` ile uyuşmuyor ve VS Code
  gibi istemciler bağlanmıyor. "Uygulama ne yapabilir" kutusuna araç
  sınırı eklendi: VS Code ve Cursor gibi uygulamalar aynı anda sınırlı
  sayıda araç kullanıyor (VS Code'da 128). Uyarı çıkarsa kişi onay
  ekranında gereksiz alanları kaldırmalı ya da araçları uygulamada
  kapatmalı. Türkçe metinler `tr.json`'da.

**Bilinen sınırlar (düzeltilmedi).**

- Alt klasöre kurulumda (`PATH !== '/'`) istemcinin bulabildiği tek ad
  `openid-configuration`. MCP SDK bu belgeyi OIDC şemasıyla doğruluyor ve
  `jwks_uri` gibi alanlar arıyor. Bu, Faz E (OIDC) ile çözülür.
- Kök `/.well-known/oauth-protected-resource` (yolsuz) `integration`
  kaynağını tanıtıyor. VS Code'un yolsuz yedek araması MCP adresiyle
  uyuşmuyor. VS Code önce 401'deki `resource_metadata`'yı ve yollu adresi
  denediği için bu yedeğe düşmemesi beklenir (VS Code kaynağından okundu,
  canlı denenmedi).
- Yöneticinin istemci listesi / izin verilen istemciler (allowlist) bu PR'da
  yok, Faz B'ye kaldı.

**Doğrulama.** `tests/oauth_test.php` (16 test, 114 doğrulama) ve
`tests/mcp_test.php` geçiyor. Yeni testler: VS Code, Cursor, Gemini ve
çıplak kayıt gövdeleri, reddedilen kayıtlar, VS Code ve uygulama şeması
eşleşmesi, hedef türü, kapsam seçimi. Sandbox'ta (PHP yerleşik sunucu,
IIS kuralını taklit eden yönlendirici) uçtan uca denendi:
- Keşif belgeleri ve 204 önuçuşu.
- `HEAD`/`GET` `/mcp.php` ve `/pinegrap/mcp.php` için 401 ve doğru
  `resource_metadata`, `DELETE` için 405.
- VS Code (device_code'lu, dört adresli), Cursor (`cursor://`) ve Gemini
  (`openid offline_access`) kayıtları 201. `JavaScript://` ve `implicit`
  400.
- VS Code: `127.0.0.1:54321` ile onay ("Bu bilgisayardaki bir program"),
  `localhost:54321` ile token 200, `offline_access`'lı yenileme 200.
- Cursor: kapsamsız ve kaynaksız onay ("Bu cihazdaki bir uygulama
  cursor://anysphere.cursor-mcp"), `cursor://…?code=…&iss=…` dönüşü,
  token 200.
- Gemini: `redirect_uri`'siz authorize ve token 200. Yanlış adresle token
  `invalid_grant`, kayıtsız adresle authorize 400. Bilinmeyen istemci
  sayfası Türkçe.
- Jetonla `initialize` (`2024-11-05`), `resources/list`,
  `resources/templates/list`, `prompts/list` ve `tools/list` (yalnız
  products:read verilince 4 araç).
- Panel rehberinin dokuz bölümü ve kopyalanacak ayarlar render ediliyor.
  Sunucu logunda uyarı yok.

**Doğrulanmayan.** Gerçek istemcilerle (Cursor, VS Code, Gemini CLI, Codex,
ChatGPT geliştirici modu) canlı bağlantı denenmedi. Cloud oturumu
dev.pinegrap.com'a ulaşamıyor. Erdal'ın dev.pinegrap.com'da denemesi
gerekiyor.

---

## 2026.4.10 — Görsel editör: formlarda güzel URL, Adres Adı'na bağlı linkler, link seçicide Geri Dönüşüm Kutusu (2026-10-13)

**Kaynak.** Ürün sahibinin görsel editörde bulduğu üç sorun: formlarda güzel
URL hiç etki etmiyor; güzel URL linkleri kaydı referansla değil, blog
sayfasının adının sonuna eklenen bir adresle açmaya çalışıyor; özel form
widget'ında klasik sayfa eklemedeki "Güzel URL'leri Etkinleştir" seçeneği
yok, var olan kayıtların adresleri de açılamıyor.

**Kök neden.** Klasik akışta güzel URL'yi form liste görünümü sayfası
çözüyordu: `/blog/<adres adı>` gelince `get_page.php` listenin detay
sayfasına geçip kaydı adından buluyordu. Görsel editör sayfaları
`page_type = 'standard'`, `layout_type = 'system'`; `get_page.php` bu
sayfalarda `/X/<her şey>`'i X'e çevirip segmenti atıyordu, yani
`/blog/ilk-yazim` blog listesini yeniden açıyordu. Editör widget'ları
(form listesi, form detayı, Hesabım gönderimleri, form RSS'i, site haritası,
kanonik adres) `custom_form_pages.pretty_urls`'ü hiç okumuyor, her zaman
`?r=` basıyordu. Linkinin adresini "Adres adı" alanına bağlayan tasarım
göreli bir slug basıyordu; tarayıcı onu bulunduğu sayfanın adresine
ekliyordu (`/blog/ilk-yazim` gibi), o adres de listeyi açıyordu. Editörde
ayar yoktu, `pg_cf_write_form_settings()` da `pretty_urls`'ü yazmıyordu.

**Adres biçimi.** Kaydın güzel adresi onu gösteren detay sayfası + adı:
`/blog-yazisi/ilk-yazim`, katalog widget'larının ürün adresiyle aynı biçim.
Klasik biçim (`/blog/ilk-yazim`, liste sayfası + ad) kaybolmasın diye kaydın
adresine 301 ile yönlenir; sorgu dizesi korunur. `?r=` her yerde çalışmaya
devam eder; adı olmayan kayıt (gönderildiğinde Başlık rolünde alan yoktu)
`?r=` ile basılır. Liste + ad yerine detay + ad seçildi çünkü görsel
editörde bir formun birden çok listesi olabilir: adres kaydı gösteren
sayfaya bağlanınca bütün listeler aynı kanonik adrese gider.

- Yönlendirme: `pg_sw_route_record_address()` (`includes/fn/widgets.php`),
  `get_page.php`'nin system bloğundan. Sayfada güzel URL'si açık bir formun
  `form_item_view`'ı varsa segment `forms.address_name`'de aranır ve
  `$_GET['r']` kurulur (bulunamazsa boş kalır, widget bulunamadı halini
  basar). Sayfada liste widget'ı varsa ve listenin detay sayfası aynı formu
  gösteriyorsa kaydın adresine 301. `?r=` verilmişse ya da sayfada katalog
  widget'ı varsa dokunulmaz (katalog kendi segmentini okur).
- Link üretimi: tek kaynak `pg_sw_record_url()`. `pg_sw_list_detail_target()`
  listenin detay sayfasının klasik mi editör sayfası mı olduğunu ayırır:
  klasik detay sayfası `/x/<ad>`'ı çözmez, `?r=` alır. Kullananlar: form
  listesi, form detayı (`self_url`: yorum ve paylaşım linkleri), Hesabım
  gönderim listesi, form RSS'i (`pg_sw_form_feed_parts()`), site haritası,
  kanonik adres (`pg_sw_page_item()`, `seo.php`).
- Adres adına bağlı link: render öncesi `pg_sw_record_href_bindings()`
  `_bindings.href = 'address_name'`'i `form_item_view`'a çevirir; link
  kaydın tam adresine gider (güzel URL kapalıysa `?r=`). Metin olarak basılan
  adres adı değişmez. Bağlama paneli bunu söyler.

**Ayar.** Özel form widget'ının ayarlarında (formun kendi sayfasında)
"Kayıt adresleri → Güzel URL'leri Etkinleştir". `pretty_urls` diğer form
ayarlarıyla kaydedilir (`pg_cf_load_page_form_settings()`,
`pg_cf_write_form_settings()`). Anahtar açıkken Başlık rolünde alan yoksa
panel uyarır. Başka bir sayfanın formunu gösteren widget, güzel URL'nin açık
ya da kapalı olduğunu ve formun kendi sayfasından açıldığını söyler.

**Var olan kayıtlar.** Anahtar açılınca, ya da açıkken Başlık alanı
değişince, `pg_cf_refresh_address_names()` formun bütün kayıtlarını gönderim
sırasıyla adlandırır (`update_submitted_form_address_name()`; aynı ada sayı
eklenir, klasik "Sayfayı Düzenle"deki `update_multiple_submitted_form_address_names()`
ile aynı kural). Zaten açıkken yalnız adı boş kayıtlar adlandırılır. Kayıt
ekranı kaç kaydın kendi adresini aldığını söyler. Klasikteki gibi kapatmak
adları silmez; linkler `?r=`'ye döner.

**Komşu düzeltmeler.**

- Dış API (`POST|PATCH /forms/{id}/submissions/{submission_id}`) ve Çalışma
  Alanı form değişikliği (`ws_change_write_form()`) cevapları değiştirince,
  formun güzel URL'si açıksa kaydın adı yeniden üretilir. Önceden başlığı
  değişen kayıt eski adıyla kalıyordu; panelin gönderim ekranı bunu zaten
  yapıyordu.
- Link seçici (`editor_select_page_or_file.php`, editörün ve CKEditor'ün
  sayfa/dosya seçicisi) Geri Dönüşüm Kutusu'ndaki sayfaları listelemiyor
  (`pg_designer_not_binned_sql()`); seçilen link silinmiş sayfaya gidiyordu.

**Yapılmayanlar.** Hazır tasarım şablonlarının formlarında güzel URL
varsayılan açılmadı: kurulu sitelerin adresleri kendiliğinden değişmesin.
Site içi arama dizini (`update_search_index.php`) yalnız klasik liste
sayfalarını tarıyor, editör kayıtları dizine girmiyor; bu ayrı bir iş.
Ziyaretçi raporlarındaki içerik linkleri (`pg_visitor_label_content_rows()`)
editör kayıtları için `?r=` kalır (çalışır, yalnız kanonik değil). Bulut
oturumu dev.pinegrap.com'a erişemediği için canlıda denenmedi.

- `tests/designer_pretty_url_test.php`.

---

## 2026.4.10 — Görsel editör: ortak bileşende yapılan düzenleme kayıtta kaybolmuyor (2026-10-13)

**Belirti.** kodpen.com'da Erdal, menüdeki bir linkin adresini beş kez
değiştirdi. URL kutusunu da dosya/sayfa seçicisini de denedi. Editör her
seferinde "kaydedildi" dedi ama sitede link eski adreste kaldı.

**Kök sebep.** Ortak bileşenin (başlık, altlık, menü) ağacı editörde
`_sharedCache[sid].tree` içinde durur. Kayıt yalnız `_sharedDirty`
işaretli bileşenleri yazar (`_flushDirtyShared()`). Özellik panelleri
seçili düğümü değiştirir ama bileşeni işaretlemez: işareti seçim koyar
(`_sdTrackSharedEditing()`). Bu işaret iki durumda yoktu:

1. Seçim `render()` yerine `renderSelectionOnly()` ile yapıldıysa.
   Bu yol yalnız `_editingSharedSid`'i yazıyordu. Sağ tık, form kontrolüne
   mousedown, Bootstrap'a bağlı öğeye tıklama (`data-bs-toggle`, açılır
   menü, akordeon), Ctrl/Shift ile çoklu seçim, "Üst öğeyi seç" ve
   tasarım asistanının `select()`'i bu yoldan seçiyordu.
2. Bir kayıttan sonra. `_flushDirtyShared()` yazdığı bileşenin işaretini ve
   snapshot'ını siliyordu. Seçim bileşenin içinde kaldıkça yeni bir seçim
   olayı gelmediği için sonraki düzenlemeler hiç işaretlenmedi. Sayfa da
   değişmemiş sayıldı (`_pgPageUnchanged()`) ve hiçbir şey yazılmadı.

Sandbox'ta yeniden üretildi (hello-pinegrap şablonu, "Site Üst Bölümü"
başlığındaki marka linki). Düzeltmeden önce kayıt sonrası 2. ve 3.
düzenleme ile sağ tıkla seçilip yapılan düzenleme DB'ye gitmiyordu.
Düzeltmeden sonra hepsi gidiyor.

**Düzeltme (`style_designer.js`).**
- `renderSelectionOnly()` `_sdTrackSharedEditing()`'i çağırıyor. Her seçim
  yolu bileşeni işaretliyor ve ilk girişte snapshot alıyor.
  `_sdSelectRender()`'daki ayrı çağrı kalktı.
- `_flushDirtyShared()` başarılı yazımdan sonra gönderdiği JSON'u
  snapshot olarak tutuyor. Değişmemiş dalda da snapshot'ı siliyor değil,
  tutuyor. Yazım reddedilirse ya da yanıt gelmezse snapshot yok olur ve
  bir sonraki kayıt bileşeni koşulsuz gönderir.
- Yeni `_sdSharedCatchEdits()` kayıttan önce (`_saveAjaxImpl()` ve
  `prepareFormData()` içinde, `_pgTabsSerialize()`'dan önce) snapshot'ı
  olan her bileşeni bugünkü ağaçla karşılaştırıyor. Farklıysa işaretliyor.
  Böylece seçimsiz yapılan bir düzenleme de bulunuyor. Değişmemiş bileşen
  yine gönderilmiyor.
- Önbellekteki ağacı sunucudaki satırla değiştiren `prefetchShared()`, o
  bileşenin eski snapshot'ını siliyor.

**Ödün.** Bir bileşen, kayıttan sonra oturum boyunca karşılaştırılmaya devam
ediyor. Kayıt başına bileşen başına bir `JSON.stringify` maliyeti var.
`_pgPageUnchanged()`'e dokunulmadı: işaretli bileşeni gösteren sayfalar
eskisi gibi yeniden yazılıyor. Widget ayarları sekme referanslarını sayfa
kaydında çözdüğü için bu işarete güveniyor.

**Doğrulama.** Sandbox'ta Playwright ile şu senaryolar denendi:
- Kayıt, düzenleme, kayıt, düzenleme, kayıt.
- Sağ tıkla seçip düzenleme.
- Düzenlemeden kayıt (bileşen gönderilmedi).
- Sayfa linki.
- Başlığa geri dönüp düzenleme.

Her senaryoda hem DB hem ön yüz doğrulandı. kodpen.com'daki ağaç
görülemedi; linkin hangi yoldan seçildiği bilinmiyor. Yukarıdaki iki
yoldan biri olduğu düşünülüyor.

---

## 2026.4.10 — Görsel editör: detay sayfası denetimi, sayfaya özel dosyalar, tek ikon sürümü, dar pencere (2026-10-13)

**Kaynak.** kodpen.com'da bir ajanın bulduğu hatalardan 10, 11 ve 12.
maddeler.

**Denetim detay sayfalarını kayıtla ölçüyor (madde 10).** Görsel editörün
detay sayfaları (`page_type = 'standard'`, içinde `form_item_view` ya da
`calendar_event_view` widget'ı) yapı denetiminde `?r=` / `?id=` olmadan
çiziliyordu; widget boş halini basınca her detay sayfası "H1 yok", "H2 ile
başlıyor", "ince içerik" alıyordu. `pg_seo_render_context()` artık bu
sayfalar için bir `query` taşır (`pg_seo_widget_record_query()`): formun en
yeni gönderiminin `reference_code`'u (`r`) ya da widget'ın takvimlerindeki
en yeni yayımlanmış etkinlik (`id`). Kaydı olmayan, ya da erişimi herkese
açık olmayan (`submitter_only` gibi) bir detay sayfası denetlenmez, eski
yanlış bulgu yazılmaz. `pg_seo_analyze_page()` `$_GET`'i ödünç alır ve
`finally`'de geri koyar. Sandbox'ta hello-pinegrap'in blog yazısı sayfası:
önce `h1_missing`, `heading_starts_at_h2`, `thin_content 154`; sonra H1
bulguları yok, sayfa kayıtla çiziliyor.

Her sayfanın H5 ile başlaması tasarım tarafında: kodpen.com'un ortak üst
bölümündeki çekmece başlığı (`<h5 id="navbarDarkDrawerLabel">Menü</h5>`)
sayfanın H1'inden önce geliyor. Paletin offcanvas bileşeni bundan sonra
başlığı `<p class="offcanvas-title h5">` olarak yazar (görünüm aynı);
mevcut tasarımlardaki etiketi operatör değiştirir.

**Tek Bootstrap Icons sürümü (madde 11).** Site, tuval ve önizleme
1.11.3'ü, editörün ikon seçicisi panelin yerel 1.13.1 kopyasını
kullanıyordu; seçicide olup 1.11.3'te olmayan ikon sitede görünmüyordu.
Tek kaynak `pg_design_icons_url()` (1.13.1, jsDelivr) ve JS'te
`_sdIconsUrl()` (`sdDesign.iconsUrl`). Kendi `bootstrap-icons` sayfasını
bağlayan bir tasarıma (`pg_design_is_icons_url()`) sabit satır ikinci kez
eklenmez: `get_page_content.php`, tuval ve önizleme aynı kuralı uygular.

**HTML görünümü (madde 11).** Editörün HTML ağacı `<head>`'de sabit yazılmış
iki satır (göreli `bootstrap-icons@1.11.3/...` ve `bootstrap@5.3.8`) ve
"Kullanıcı Stilleri.css" adlı var olmayan bir dosya gösteriyordu. Ağaç artık
sayfanın gerçekte yüklediklerini sırasıyla gösterir: Google Fonts, açık
harici CSS satırları tam adresleriyle, tema, satır içi dosyalar için tek
`<style id="pg-page-css">`, kullanıcı stilleri için `<style
id="pg-user-styles">`; gövdenin sonunda harici betikler (`type="module"`
dahil) ve satır içi betikler için tek `<script id="pg-page-js">`.

**Sayfaya özel dosyalar (madde 11).** Tasarımın CSS/JS satırları
(`style_custom_css` / `style_custom_js`) isteğe bağlı bir `pages` listesi
taşır: sayfa kimlikleri, kaydedilmemiş sekme için `tab:<anahtar>`. Liste
yoksa ya da boşsa dosya her sayfada yüklenir (eski tasarımlar aynen
çalışır). `pg_design_asset_on_page()` sunucu tarafı, `_sdAssetsInPlay()`
editör tarafıdır.

- Editör: dosya satırının sağ tık menüsünde "Yüklendiği Sayfalar…"
  (kilitli çerçeve satırlarında yok); "Her sayfa" ya da sekmelerden seçim.
  Satırda sayfa sayısı rozeti, üzerine gelince sayfa adları. Tuval ve HTML
  görünümü sekme değişince o sayfanın dosyalarına geçer.
- Kayıt: `tab:<anahtar>` kaydın verdiği kimliğe çevrilir
  (`pg_designer_resolve_asset_page_refs()`, sunucuda ve `_pgTabsAfterSave`).
- ZIP içe aktarma: her dosya onu kullanan sayfaları hatırlar
  (`page_names`); bütün sayfaların kullandığı dosya her sayfada kalır
  (`pg_di_scope_assets()`), yalnız bazılarının kullandığı o sayfalarla
  sınırlanır. Editöre aktarımda sekmeler, doğrudan tasarım oluşturmada
  (`import_design_zip.php`) sayfa kimlikleri yazılır.
- Şablon: tasarımdan yapılan şablon sayfaları şablon anahtarıyla
  (`key:<anahtar>`) adlandırır (`pg_dtc_asset_page_keys()`); şablonda
  olmayan sayfa düşer, hiç sayfası kalmayan dosya hiçbir sayfayla
  eşleşmeyen `key:` alır. Şablondan açılan editör `key:` → `tab:` çevirir,
  sunucu kurulumu `key:` → kimlik.
- Tasarım kopyalama (`duplicate_style.php`) eski kimlikleri kopyaların
  kimliklerine çevirir. Tasarımdan ayrılan sayfa (`page_detach`) yüklediğini
  götürür: başka sayfalara ait dosyalar geride kalır, kendi dosyaları yeni
  tasarımda her sayfada yüklenir (`pg_design_assets_for_page()`).
- Sandbox: `harita.js` iki sayfayla sınırlandı, yalnız o sayfaların
  HTML'inde; yeni sekmeyle sınırlanan dosya kayıtta sekmenin kimliğini
  aldı; iki sayfalık ZIP'te `ana.js` yalnız ana sayfada, ortak `site.js`
  her sayfada.

jQuery ve jQuery UI değişmedi: tasarımın jQuery satırı tasarımın kendi
jQuery'sidir; yazılımın `frontend.js`'i (formlar, takvim, tarih seçici,
iletişim kutuları) jQuery ve jQuery UI üzerine yazılı ve `get_page_content`
onları bu yüzden her sayfaya ekliyor. Kaldırmak `frontend.js`'in yeniden
yazılmasını gerektirir.

**Dar pencere (madde 12).** Development sürümünde araç çubuğu ve durum
çubuğu 420px'e kadar sığıyordu; kodpen.com'daki taşma burada tekrar
edilemedi. Yine de taşma yolları kapatıldı: 992px altında sekme şeridi
sıfıra kadar daralır (`min-width: 0`), Kaydet / Yayınla yerinden oynamaz;
durum çubuğundaki "son değişiklik" satırı uzun bir kullanıcı adında üç
noktayla kısalır, uyarı düğmesini itmez; editör yüksekliği `100dvh` ile
ölçülür (telefon ve tablette tarayıcı çubukları durum çubuğunu
örtmüyor). Şerit taşınca yanında "Tüm sayfalar" düğmesi çıkar: sayfa
sayısı, arama kutusu, gruplar renkleriyle, taslak ve kaydedilmemiş
işaretleri; Enter ilk eşleşeni açar. Fare tekerleği şeridi yatay kaydırır.
Sandbox'ta 34 sekmeyle 360–1400px arası ölçüldü.

- `tests/designer_asset_scope_test.php`.

## 2026.4.10 — Görsel editör: bağlanan metin linkini koruyor, kayıt yokken düzen gizleniyor, pg-hide-if-empty, boş liste etiketi, stretched-link, detay sayfası başlığı (2026-10-13)

**Neden.** kodpen.com görsel editörle kurulurken çıkan sorun listesinin
1–9 ve 13. maddeleri. Listedeki yollar canlı siteye aittir; sorunlar yerel
kodda yeniden üretilip düzeltildi. Sayfa içinde `^^token^^` yer tutucusu
kullanılmaz kuralı korunur: aşağıdaki takma adlar eski tasarımlar bozulmasın
diye vardır, panel artık token adı göstermez.

### 1. Bağlanan metin içindeki HTML (`includes/fn/designer.php`, `style_designer.js`)

`_apply_bindings($props, $rich_text)`: metnini olduğu gibi basan beş içerik
türünde (heading, link, paragraph, span, text — `_render_content_html()`)
`text` bağlaması `pg_binding_text_slot()` ile yazılır: metnin işaretlemesi tek
bir kelime grubunu sarıyorsa token o grubun yerine girer
(`<a href="/blog-detay">Başlık</a>` → `<a href="/blog-detay">^^title^^</a>`),
grubun çevresindeki boşluk kalır. İşaretleme yoksa, hiç kelime yoksa ya da
birden çok grup varsa (hangisinin değer olduğu bilinmez) eskisi gibi tüm
metin token olur. Bileşenler (metni kaçışlı basar) etkilenmez. Tuval aynı
kuralı `_sdBindTextSlot()` ile uygular. Bağlama paneli (`_sdBindingTextNote()`)
metinde işaretleme varken ne olacağını söyler (birden çok grup ve bağlama
varsa uyarı rengiyle); metin tek bir `<a>` ise "Linki ayrı bir öğe yap"
(`_sdSplitTextLink()`) öğeyi aynı etiketli bir semantic öğe + içinde content
link'e çevirir: `text` bağlaması ve tarih biçimi linke geçer, diğer
bağlamalar (`eo_visible_if`), sınıflar (align → `text-*`, lead), id ve
özellikler dışta kalır; fontFamily ve inlineStyle semantic öğede alan
olmadığı için `style` özelliğine katlanır. Linkin href, target, rel, sınıf ve
diğer özellikleri korunur. Böylece linkin adresi de detay adresine
bağlanabilir.

### 2. Form listesinde `^^__detail_url^^` (`includes/fn/widgets.php`)

Panel bu adı söylüyordu ama form listesi yalnız `form_item_view`'ı
dolduruyordu. Form listesi satırına ve Hesabım gönderim satırına
`__detail_url` aynı değerle eklendi. Panel artık token adı göstermez: detay
sayfası seçicisinin altında `_swDetailPageHint()` bağlama listesindeki
seçeneğin adını söyler. `form_item_view` seçeneği bölgeye göre adlanır:
listede "Detay sayfası adresi", detayda "Bu kaydın adresi"; eski adla
kaydedilmiş bağlama açılır listede bu seçenek olarak görünür.

### 3. `^^__not_found_message^^` ve kayıt yokken düzen (`widgets.php`)

Form detay widget'ı `__not_found_message`'ı `not_found` ile aynı değerle
doldurur (katalog ve takvim bu adı kullanıyor). Yeni ayar
`hide_when_not_found` (varsayılan açık): kayıt yoksa ya da ziyaretçi
göremiyorsa `pg_sw_prune_record_layout()` loop_area'nın çocuklarını (loop_area
yoksa kökün çocuklarını) `_pg_sw_keep_for_not_found()` ile keser: "kayıt
yokken göster" (`record_not_found`) öğeleri ve bulunamadı mesajına bağlı
öğeler bütün, Mesajlar düğümü ve bunları saran öğeler kalır; başka bir
duruma bağlı öğeler ve kayıt alanları gider. Ağaçta bu duruma ait hiçbir şey
yoksa renderer mesajı `pg_sw_state_message_html()` ile kendisi yazar. Kapalı
ayar eski davranıştır (düzen boş alanlarla). Panelde "Kayıt yoksa düzeni
gizle" anahtarı.

### 4. `pg-hide-if-empty` ve boş öğe (`widgets.php`, `designer.php`)

`pg_sw_hide_empty_fields()` yeniden yazıldı: sınıfı taşıyan her öğe, aynı
etiketin iç içe geçmesini sayan `pg_sw_closing_tag()` ile kapanışına kadar
bulunur, sondan başa işlenir (içteki boşalınca dıştaki de gider). Değer,
varsa `pg-field-value` öğesinin içidir (`pg_sw_field_value()`), yoksa öğenin
tamamı; `pg_sw_has_content()` metin, adresli img, video/iframe/svg, link ve
form denetimlerini içerik sayar. Önceki düzenli ifade iç içe div'de erken
kapanıyor ve hiçbir şeyi silmiyordu. Çocuğu olmayan semantic öğe artık tek
satırda kapanır (`<div class="x"></div>`), bağlı değeri boş kalan öğe gerçekten
boştur ve `:empty` eşleşir.

### 5. Boş liste mesajı (`widgets.php`, `widgets_catalog.php`, `designer.php`)

`_split_widget_tree()` kayıtların basıldığı öğeyi `loop_parent` olarak döner
(`ul`, `tbody`, grid için `row`, üst seviyede `''`). `pg_sw_state_message_html()`
mesajı buna uygun yazar: li, tüm kolonları kaplayan tr/td
(`pg_sw_row_cells()`), dd, `div.col-12`, satır içi öğede span, aksi halde div.
Sınıf `text-body-secondary py-3` (Bootstrap 5.3/6; `text-muted` kullanımdan
kalkıyor). `$outside_markers` ile liste/tablo gibi yalnız satır tutabilen
ebeveynin yanına `<!--pg-loop-before-->` / `<!--pg-loop-after-->` konur; form
listesi arama kutusunu öncesine, sayfalamayı sonrasına yazar.

### 6. Stretched link (`designer.php`)

`_has_stretched_link_child` yerine ataya bakan kural: `_render_tree_node()`
bir yığın (`_pg_stretch_stack()`) tutar; bir öğe kendisi konumlanmışsa
(`_pg_node_is_positioned()`: position sınıfları, card, list-group-item,
btn-group, input-group, dropdown, modal-content, navbar, ratio, sticky/fixed
ve satır içi `position:`) ya da bir atası öyleyse `position-relative`
eklenmez. Değilse, stretched link'e yalnız metin öğeleri ve sınıfsız/id'siz
yalın kutular üzerinden ulaşan ilk öğe (`_pg_stretch_claims()`) sınıfı alır.
Sonuç: `.card > h2 > a.stretched-link`'te h2 artık position-relative almaz,
kart tıklanır. Bilinen sınır: yalnız kendi CSS'iyle konumlanan bir kutu
tanınmaz; panel Stretch satırında `position-relative` sınıfını eklemeyi
söyler.

### 7. Detay sayfası seçicisi (`style_designer.js`)

`_swPageOptions(..., { otherTabs })`: tasarımın detay widget'ı henüz olmayan
sekmeleri ayrı grupta listeler (form listesi, katalog ve Hesabım detay
seçicileri). `_swDetailPageHint()` seçilen sekmede widget yoksa uyarır, hiç
detay sayfası yoksa ne yapılacağını söyler.

### 8. Widget adı (`style_designer.js`)

`_swAutoName(type, page, where)`: paylaşılan bir parçaya (`_sdSharedOwnerOf()`
— başlık, altlık) bırakılan ya da birden çok sayfada duran widget tasarımın
adını alır (`#sd-style-name`, boşsa "tasarım"); tek sayfadakiler sayfanın
adını almaya devam eder. Tür seçilince yeniden adlandırma da aynı kuralla.
"Bu sayfa için kopya" (`_swForkForThisPage`) sayfa adında kalır.

### 9. Detay sayfasının başlığı (`get_page_content.php`, `widgets.php`)

Form detay widget'ı ayarları: `title_field` (formun bir alanı ya da
`reference_code`; boşsa formun RSS başlık alanları) ve `title_suffix`
("| Gelen Kutusu", çevrilen sayfada `pg_tr_text(..., 'seo')` ile).
`pg_sw_record_title()` başlığı kurar; `get_page_content.php` widget'lı sayfada
alan değerlerini `form_data`'dan düz metin olarak (html alanı
`convert_html_to_text`) okur. Formda RSS başlık alanı yoksa panel uyarır.

### 13. Küçükler

Tuvalde açıklama/özet biçimli alanın örnek metni yalnız katalog, sepet,
sipariş bölgelerinde ürün metnidir, diğerlerinde nötr. `shared_component`
panel eylemlerinden `list_form_fields` ve `list_form_item_view_pages` formu
`form_id` ya da `page_id` ile kabul eder (`_sc_custom_form_page_id()`);
eksikse "Geçersiz ID." yerine beklenen parametreyi söyler.

**Doğrulama.** `tests/designer_widget_fixes_test.php` (23 test): metin
yuvası, hide-if-empty (iç içe, alan değeri, resim), boş öğe, durum mesajı
etiketleri, `loop_parent` ve dış işaretler, stretched-link (kart, yalın kutu,
satır içi position), kayıt yokken budama, kayıt başlığı. Tuval yuvası ve
link ayırma headless Chromium'da sunucu durumlarıyla karşılaştırıldı.
`tools/test.php`, `lint.php`, `check_lang.php`, `check_bindings.php`,
`check_api_schema.php` temiz.

---

## 2026.4.10 — Dış API: ürün güncellemede grup, varyant, görsel ve barkod; yazımdan sonra SEO puanı (2026-10-13)

**Neden.** Erdal, Claude bağlayıcısıyla bir ürünün "tüm alanlarını" doldurttu
(ürün 227, dev.pinegrap.com). Claude'un raporundaki eksikler:

- `group_ids` ve `attributes` yalnız oluştururken veriliyordu; güncellemede
  yoktu. Grup üyeliği `POST /product-groups/{id}/products` ile zaten
  değişebiliyordu ama ürün güncelleme ucunun tarifi bunu anmıyordu ve model onu
  bulamadı.
- Görsel bağlayan bir alan hiçbir uçta yoktu.
- `barcode` cevapta dönüyordu ama yazılamıyordu.
- Güncelleme cevabındaki SEO bloğu `stale.meta: true` ve eski puanla
  dönüyordu: ürün güncelleme `seo_analysis_current = 0` yazıp bırakıyordu,
  sayfa ve ürün grubu güncellemesi ise `pg_seo_recalculate()` çağırıyor.

**Ne değişti.**

- `POST /products/{id}`: `group_ids`, `attributes`, `images` ve `barcode`
  eklendi. İlk üçü gönderildiğinde ürünün mevcut bağlarının **yerine geçer**,
  boş liste hepsini kaldırır (ürün ekranı da bu bağları baştan yazar). Tarif
  grup üyeliğinin grup tarafından da değişebileceğini anıyor.
- `POST /products`: `images` ve `barcode` eklendi. Görseller
  `pg_pb_create_product()`'ın mevcut `images` ilişkisiyle yazılır.
- `images`: Dosya kitaplığındaki dosya adları ya da API'nin verdiği adresler
  (sitenin kendi kökündeyse ad olarak okunur). Dosya `files` tablosunda olmalı
  ve uzantısı görsel olmalı (jpg, jpeg, png, gif, webp, avif, svg). İlki
  `products.image_name`, kalanı sırayla `products_images_xref`.
- `barcode`: `BARCODE_ENABLED` kapalıysa 422. Biçim
  `pg_barcode_matches_format()` ile, başka üründeki kod 422. Ürünün zaten
  barkodu varsa: aynısıysa yazma yok, farklıysa 422. Gerekçe: ürün ekranı da
  (`pg_assign_product_barcode()`) yalnız ekler, çünkü basılmış etiket aynı
  ürünü okutmaya devam etmeli.
- Ekleme ve güncellemeden sonra `api_products_rescore()` meta yarısını
  `pg_seo_recalculate('product', ...)` ile hemen hesaplar; cevap güncel puanı
  ve `stale.meta: false` taşır. Yapı yarısı gece işinde kalır.
- Doğrulama yazmadan önce yapılır; `X-Dry-Run` her şeyi denetler, hiçbir şey
  yazmaz.

**Doğrulama.** Sandbox'ta sunucu anahtarıyla `integration.php` üzerinden:

- İki PNG yüklendi. Ürün görsel, barkod (EAN-13) ve grupla oluşturuldu.
- Güncellemede görsel (biri adres, biri adla), iki grup ve bir varyant
  özelliği yazıldı; SEO puanı 46'dan 62'ye çıktı ve `stale.meta` false döndü.
- Hata yolları: farklı barkod, olmayan dosya ve olmayan grup 422 döndü. Aynı
  barkod 200 döndü. Boş listeler görselleri ve grupları temizledi.
- Dry run hiçbir şey yazmadı.

MCP aracı şemadan kendiliğinden genişler, ayrıca denenmedi. Barkod
değiştirme ve sürüm/varyant matrisi kurmak kapsam dışında kaldı.

---

## 2026.4.10 — Uygulama Erişimi: OAuth uygulamasında MCP adresleri ve bağlantı rehberi (2026-10-13)

**Neden.** Erdal Claude'u `https://dev.pinegrap.com/pinegrap/mcp.php` ile
bağladı; ekran ise yalnız kök adresi (`site.com/mcp.php`) gösteriyordu ve
"OAuth ile uygulama bağlama açıldı" penceresiyle çekmecenin OAuth bölümü
neredeyse boştu. Hangi adresin verileceği ve bağlantının nasıl yapılacağı
ekrandan anlaşılmıyordu.

**Ne değişti.** `api_settings.php` içinde ekrana özgü
`api_settings_oauth_guide($id_prefix, $button_class)` eklendi; hem oluşturma
sonrası pencere hem çekmecenin Genel sekmesi onu basıyor:

- **MCP sunucu adresi:** yazılım klasöründeki dosya
  (`pg_oauth_resource_addresses()['mcp'][1]`, ör. `site.com/pinegrap/mcp.php`).
  Gerçek dosya olduğu için her sunucuda, yeniden yazma kuralına bakmadan
  çalışır; bu yüzden birinci sırada.
- **Kısa adres:** sitenin kendi adresi (`['mcp'][0]`, `site.com/mcp.php`);
  sayfa adresleri nerede çalışıyorsa orada çalışır. Sunucu tarafında iki adres
  de aynı kaynak sayılır (#246), hangisiyle bağlanıldığı fark etmez.
- **Keşif adresi:** kontrol için; uygulamalar onu MCP adresinden kendileri
  bulur.
- **Nasıl bağlanır:** Claude (Ayarlar › Bağlayıcılar › Özel bağlayıcı ekle),
  ChatGPT (Uygulamalar ve Bağlayıcılar, geliştirici modu, OAuth), giriş ve onay
  adımları, Bağlantılar sekmesi.
- **Uygulama neler yapabilir:** her izin alanı bir araç grubudur (Okuma →
  listeleme/görüntüleme, Yazma → oluşturma/değiştirme/silme); kişi kendi panel
  hesabının yetkisini aşamaz; daraltma hemen geçerli, genişletmeden sonra
  uygulamada yeniden bağlanmak gerekir (onaylanan kapsamlar saklanıyor,
  `pg_oauth_effective_scopes()` yalnız kesişimi alır).

Pencereden "Site adresi" (issuer) kutusu kaldırıldı: istemciye verilmesi
gereken bir değer değil, MCP adresinin yanında karışıklık yaratıyordu.
Sunucu tarafı (`pg_oauth_resources()` sırası, PRM, token kontrolü)
değişmedi. Eski metinlerin `tr.json` karşılıkları başka yerlerde
kullanılmadığı hâlde silinmedi (ortak dosyada yalnız ekleme).

**Doğrulama.** Sandbox'ta OAuth uygulaması oluşturulup pencere ve çekmece
ekran görüntüsüyle kontrol edildi: adresler `http://127.0.0.1:8000/pinegrap/mcp.php`,
`/mcp.php` ve `/.well-known/oauth-authorization-server`, Kopyala düğmeleri her
kutuda. Claude ve ChatGPT menü adları üçüncü taraf arayüzlerinden; değişirlerse
yalnız metin güncellenir.

---

## 2026.4.10 — WAF: bot politikası kimlikle girilen uçlarda uygulanmaz (Claude-User engeli) (2026-10-13)

**Neden.** dev.pinegrap.com'da Claude bağlayıcısı OAuth onayını ve token'ı
aldı (kod 11:27:24'te kullanıldı, `api_devices` satırı açıldı) ama hemen
ardından "yetkilendirme başarısız" dedi. `api_request_log`'da token'lı tek
istek yoktu; `waf_log`'da ise 11:27:25'ten itibaren Claude'un her
`POST /pinegrap/mcp.php` isteği `bot-spoof` kuralıyla engellenmişti. Claude'un
MCP istemcisi `Claude-User` adıyla Anthropic'in API çıkışından (160.79.106.160
–.186) geliyor; `waf_ai_bots()` bu adı `https://claude.com/crawling/bots.json`
listesine göre doğruluyor ve liste yalnız tarayıcı adreslerini
(160.79.106.16/28, .128/28) içeriyor. Adres listede olmadığı için istek
"sahte bot" sayıldı, 403 aldı ve her engelleme bir ihlal olarak otomatik
yasağa sayıldı.

**Ne değişti.** `waf_handle_bot()`: saldırı aracı kontrolünden hemen sonra
`waf_is_api_request() && !waf_is_site_api_request()` ise döner. Bu uçlar
(`integration.php`, `mcp.php`, `oauth.php`) çağıranı sunduğu kimlik bilgisiyle
kabul eder; kullanıcı ajanının bir tarayıcı adı taşıması orada hiçbir şey
kazandırmaz. Dönüş iyi-bot dalından önce olduğu için `IS_BOT` tanımlanmaz ve
hız sınırları aynen işler. Saldırı araçları (`sqlmap` vb.) bu uçlarda da
engellenir. `api.php` kapsam dışında: sitenin kendi sayfalarına kimliksiz
cevap verdiği için bot politikası orada sürer; sayfalar da değişmedi.

**Neden aralık eklemek değil.** Anthropic'in API çıkış adreslerini
`anthropic-bots` satırına elle eklemek, yayımlanan liste her yenilendiğinde
ezilir ve aynı sorun ChatGPT ve diğer MCP istemcilerinde de tekrar eder.
Kimlik isteyen uçta kullanıcı ajanına bakmak zaten bir koruma sağlamıyordu.

**Doğrulama.** Sandbox'ta WAF engelleme kipinde, `anthropic-bots` satırı
yayımlanan listeyle doldurulup `CF-Connecting-IP` güvenilir vekilden
verilerek: düzeltmeden önce `mcp.php` ve `oauth.php/token` için Claude-User +
160.79.106.167 → 403 (`bot-spoof`); sonra 401 (MCP/OAuth kodu cevaplıyor).
`sqlmap` ile `mcp.php` hâlâ 403, aynı sahte Claude-User ile ana sayfa ve
`api.php` hâlâ 403 `bot-spoof`.

**Sahada.** Engellemeler ihlal olarak sayılsa da dev.pinegrap.com'da
160.79.x için otomatik yasak oluşmamıştı; başka bir sitede oluşmuşsa Güvenlik
Duvarı ekranından kaldırılır.

---

## 2026.4.10 — Dış API: MCP sunucusu sitenin kök adresinde (`site.com/mcp.php`) (2026-10-13)

**Neden.** Erdal dev.pinegrap.com'da Claude'a `https://dev.pinegrap.com/mcp.php`
verdi ve 404 aldı. Dosya yazılım klasöründe (`pinegrap/mcp.php`) durduğu için
gerçek adres `.../pinegrap/mcp.php` idi; kökteki `mcp.php` dosya olmadığından
yönlendiriciye düşüyor, yönlendirici de onu sayfa adı sanıp sitenin 404
sayfasını veriyordu (sandbox'ta yeniden üretildi). Bir kişinin yapay zekâ
uygulamasına yazacağı adres `site.com/mcp.php`'dir.

**Ne değişti.**
- `router.php`: `.well-known` bloğunun hemen ardından `mcp.php` adı
  `pinegrap/mcp.php`'ye devredilir (oauth.php'nin keşif belgeleriyle aynı yol).
  Apache ve IIS'te ek kural gerekmez; mevcut "dosya değilse yönlendiriciye"
  kuralı yeter.
- `pg_oauth_resource_addresses()`: her kaynağın cevap verdiği adresler; ilki
  sitenin verdiği adres. MCP için kök adres önce, yazılım klasöründeki adres
  ikinci. `pg_oauth_resources()` ilk adresleri döndürür, panel artık kök adresi
  gösterir.
- İstemci (Claude, MCP SDK'ları) korumalı kaynak belgesindeki `resource`'un
  bağlandığı adresle aynı olduğunu denetler. Bu yüzden iki adres de kendi
  belgesini alır: 401'deki `resource_metadata` isteğin geldiği adrese göre
  kurulur (`pg_oauth_request_resource()`, IIS için `HTTP_X_ORIGINAL_URL`
  önce okunur), `/.well-known/oauth-protected-resource/<yol>` de istenen
  adresi `resource` olarak döndürür.
- Jeton ucu kaynağı yazımla değil anahtarla karşılaştırır: bir adres için
  verilmiş jeton diğerinde de geçer, `integration.php` ile karşılıklı ret
  sürer.

**Doğrulama.** Sandbox'ta (`php -S` + Apache benzeri yönlendirme): düzeltmeden
önce kök `POST /mcp.php` → sitenin 404 sayfası; sonra iki adreste 401 ve doğru
`resource_metadata`, iki belge de kendi `resource`'u ile; kök adres için ve
klasör adresi için alınan jetonlar iki adreste `tools/list` / `tools/call`
çalıştırdı; MCP jetonu `integration.php`'de, entegrasyon jetonu kök MCP'de
401; `GET /mcp.php` 405; bilinmeyen sayfa hâlâ 404. IIS'te ve gerçek bir
Claude bağlayıcısıyla denenmedi.

---

## 2026.4.10 — Dış API: MCP sunucusu (`mcp.php`) ve istemci kimlik belgesi (10.71) (2026-10-13)

**Neden.** OAuth Faz A'nın amacı buydu: Claude, ChatGPT ya da bir editörün
asistanı siteye adresinden bağlanıp kişi adına çalışabilsin. Erdal
araştırmadan sonra "önce MCP sunucusu" dedi (Çalışma Alanı'nda dış MCP
sunucularını `@takma ad` ile çağırma sonraya kaldı; araştırma notu
`/mnt/project-files/notes/mcp-arastirma.md`). MCP'nin 2026-07-28 sürümü
oturumu ve el sıkışmayı kaldırdı, dinamik istemci kaydını eskimiş saydı;
istemciler artık önce istemci kimlik belgesini (CIMD) deniyor. Plan:
`docs/_plan_oauth.md` → "Faz C + D".

**Tasarım.**

- **Araçlar = dış API rotaları.** `api_schema()`'daki (modüllerinkiler
  dahil) her rota bir araç: ad rota kimliğinden (`products.list` →
  `products_list`; model API'lerinin araç adı kuralı nokta kabul etmiyor),
  açıklama özet + açıklama, `inputSchema` parametrelerden
  (`api_openapi_type()`; `nullable` → `["tip","null"]`), ipuçları: GET
  `readOnlyHint`, `create` dışındaki yazmalar `destructiveHint`. Dry-run
  destekleyen yazmalara `dry_run` parametresi (API'nin `X-Dry-Run`'ı).
  Liste çağıranın kapsamlarına göre süzülür. Dışarıda: `auth.*`, `push.*`,
  `docs.*`, IIS için açılmış `*_post` takma adları. Ayrı bir araç katmanı
  yazılmadı: her yeni uç kendiliğinden araç olur, iki tanım ayrışmaz.
- **Süreç içinde çalıştırma.** Siteye kendi kendine HTTP isteği atmak
  paylaşımlı hostingte loopback/sertifika sorunu ve iki PHP işçisi demekti.
  `tools/call` rotayı `api_run()`'ın sırasıyla çalıştırır (kapsam,
  `api_validate_input()`, işleyici). Bunun için `router.php`'deki
  `api_request_method/path/input()` ve `api_header()` bir değer de alır,
  `response.php`'ye `api_send_hook()` eklendi: işleyici yine `api_send()`
  ile çıkar, kanca cevabı `CallToolResult`'a çevirir (2xx → `content` +
  `structuredContent`, diğerleri → `isError: true` + API'nin hata gövdesi).
  Günlüğe yol `/mcp/<rota yolu>` olarak düşer, hız sınırı aynı.
- **İki protokol kuşağı.** 2026-07-28: `MCP-Protocol-Version` başlığı ya da
  `_meta`, `Mcp-Method`/`Mcp-Name` gövdeyle karşılaştırılır (uyuşmazsa
  400/-32020), sonuçlarda `resultType: complete` ve `_meta` içinde
  `serverInfo`, `server/discover`, `tools/list`'te `ttlMs` + `cacheScope:
  private`. 2025-03-26…2025-11-25: `initialize` ile açılır; oturum kimliği
  verilmez (izinli), her istek tek başına. Desteklenmeyen sürüm 400/-32022.
  GET/DELETE 405, bildirimler 202, toplu istek reddedilir. Akış (SSE) yok.
- **Kimlik.** `api_authenticate()` olduğu gibi: OAuth jetonu (kaynağı
  `mcp` olmalı — `PG_OAUTH_RESOURCE`), cihaz jetonu ya da uygulama
  anahtarı. İlk istek de (`initialize` dahil) kimlik ister; jetonsuz
  istemci 401 + `WWW-Authenticate: Bearer resource_metadata=".../mcp.php"`
  alır ve OAuth'a geçer. CORS her kaynağa açık (çerez/oturum yok, sayfa
  ancak elindeki jetonla iş yapabilir); bozuk `Origin` 403.
- **WAF.** `mcp.php` ve `oauth.php` `waf_is_api_request()` listesine girdi.
  Her POST "hassas" sayıldığından dakikada 30 adres sınırına takılıyordu;
  bir yapay zeka uygulamasının arka ucu çok sayıda siteye aynı adreslerden
  bağlanır. Sayım uygulama/jeton başına (API) ve kayıt için adres başına.
- **CIMD (Faz D).** `client_id` https adresiyse (`pg_oauth_client_id_is_url()`:
  yol var; parça, sorgu, kullanıcı adı, nokta bölüm yok) belge o adresten
  çekilir (`api_http_request()`: iç ağ adresi yok, yönlendirme yok, 5 KB),
  `pg_oauth_metadata_document_client()` doğrular (belge kendi adresini
  `client_id` olarak taşımalı, gizli yok, yöntem yalnız `none`, dönüş
  adresleri Faz A kurallarıyla) ve `oauth_clients`'a
  `registered_by = 'metadata'` olarak 1 gün önbelleğe alır; yenileme
  başarısızsa 7 güne kadar eski kopya. Çekme yalnız yetkilendirme
  ekranında, **kişi giriş yaptıktan sonra** (girişsiz biri siteyi
  istediği adrese istek attıramasın) ve adres başına saatte 30. Token ucu
  belge çekmez. Onay ekranı "Kimlik adresi" satırında belgenin alan adını
  gösterir; https dönüş adresi başka bir alan adındaysa uyarır. Sunucu
  belgesinde `client_id_metadata_document_supported`. Kullanılmayan kopyalar
  dinamik istemcilerle birlikte 30 günde silinir.
- **Panel.** OAuth uygulamasının çekmecesinde ve açılış sayfasında "MCP
  sunucu adresi" (Claude'da özel bağlayıcı adresi).

**Şema 10.71.** `oauth_clients.client_id` `VARCHAR(255) ascii_bin`,
`registered_by` + `metadata`, `metadata_expires`.

**Doğrulama.** Lint, `check_lang`, `check_api_schema`, `check_copies`,
`check_bindings`, `test.php` (298; yeni `tests/mcp_test.php`, CIMD
testleri `tests/oauth_test.php`'de). Sandbox (`setup_sandbox.sh` +
`.htaccess` taklidi): jetonsuz 401 ve challenge, kaynak belgesi,
`initialize`, bildirim 202, `tools/list` (yalnız verilen kapsamların
araçları), `tools/call` ile okuma, dry-run, oluşturma, doğrulama hatası
(422 → `isError`), bilinmeyen alan, olmayan kayıt, kalıcı silme, verilmemiş
araç (-32602), 2026-07-28 ile `server/discover`/`tools/list`/`tools/call`,
eksik/uyuşmayan başlık (-32020), desteklenmeyen sürüm (-32022), bilinmeyen
yöntem 404, ayrıştırma hatası, toplu istek, GET 405, OPTIONS, Origin;
`integration.php` jetonu `mcp.php`'de ve tersi 401; CIMD girişsiz →
giriş sayfası, ağ yok/iç adres → hata sayfası ve gerekçe; kayıtlı kopyayla
onay ekranı (kimlik adresi, başka alan adı uyarısı, kaçış), kod → jeton →
`tools/list` → yenileme, 7 günlük eski kopya sınırı. Migration 10.70
şemasından ve ikinci kez koşturuldu. **Doğrulanmayan:** gerçek bir MCP
istemcisi (Claude özel bağlayıcı) — https'li, internetten erişilen bir
site ister; CIMD belgesinin gerçekten çekilmesi (sandbox'ta DNS yok);
IIS.

---

## 2026.4.10 — Dış API: OAuth 2.1 yetkilendirme sunucusu, MCP hazırlığı (10.70) (2026-10-13)

**Neden.** Pinegrap'a MCP sunucusu eklendiğinde Claude gibi dış
uygulamalar siteye **kişi adına** bağlanacak; MCP'nin yetkilendirme belgesi
bunun için OAuth 2.1 ister (keşif, dinamik kayıt, PKCE'li yetkilendirme
kodu, kaynak göstergesi). Mevcut iki kimlik — uygulama anahtarı+gizli ve
cihaz girişi (kullanıcı adı+parola) — buna uymuyordu: dış uygulama
parolayı görmemeli, kişi neyi verdiğini ekranda seçmeli. Plan:
`docs/_plan_oauth.md` (bu PR Faz A).

**Tasarım.**

- Operatörün anahtarı `api_apps.kind = 'oauth'` uygulaması (cihaz
  uygulamasının eşi): gizlisi yok, durumu OAuth'u açar/kapatır, kapsamları
  tavandır. Etkin bir tane yokken `.well-known` belgeleri ve uç noktalar 404
  döner. Birden fazla varsa en eskisi (`pg_oauth_app()`).
- **Jeton = `api_devices` satırı.** Bağlantı, kişinin API'ye bir başka
  girişidir; `oauth_client_id`, `scopes` (onaylanan, JSON) ve `resource`
  kolonlarıyla işaretlenir. Erişim/yenileme jetonları, 60 sn'lik yeniden
  deneme penceresi, çalıntı yenilemenin bağlantıyı kapatması, kişi başına
  20 sınırı, cihaz başına dakika sınırı ve **parola değişince / her yerden
  çıkışta / hesap silinince kapanma** olduğu gibi gelir — ayrı bir jeton
  tablosu bunların hepsini ikinci kez yazdırırdı.
- `api_device_authenticate()` satırın `oauth_client_id`'sine bakar: uygulama
  türü `'oauth'` aranır, istemci kapalıysa 401, jetonun kaynağı bu giriş
  noktası değilse 401 (RFC 8707; `PG_OAUTH_RESOURCE`, varsayılan
  `integration`). Kapsam = onaylanan ∩ tavan ∩ kişinin bugünkü hakları,
  her istekte (`pg_oauth_effective_scopes()`); cihazdaki gibi `account:*`
  kendiliğinden eklenmez, `webhooks:manage` OAuth ile hiç verilmez.
- Yenileme ortak çekirdekte: `api_device_rotate_pair()` hata kodu döner,
  `api_device_rotate()` onu API biçiminde, token ucu OAuth biçiminde
  (`invalid_grant`) cevaplar. `POST /auth/refresh` OAuth satırının jetonunu
  **döndürmeden önce** reddeder (yanlış adrese gelen istemci jetonunu
  kaybetmesin).
- `oauth.php` tek giriş: `/authorize` oturumlu (onay ekranı), `/token`,
  `/register`, `/revoke` oturumsuz (`PG_NO_SESSION`, integration.php'nin
  gerekçesi). API bootstrap'ını yükler (`PG_API_ENTRY`); cevaplar OAuth'un
  kendi biçiminde (`{"error": …}`), `error_description` RFC gereği ASCII
  olduğu için İngilizce ve çevrilmez.
- Keşif: `router.php` `.well-known/oauth-authorization-server`,
  `openid-configuration` (alt klasöre kurulumda istemcinin ulaşabildiği tek
  ad; OIDC alanı iddia etmez) ve `oauth-protected-resource/<yol>` adlarını
  `oauth.php`'ye verir. `integration.php`'nin 401'leri OAuth açıkken
  `WWW-Authenticate: Bearer resource_metadata="…"` taşır; Basic kimlik
  gönderen istek Basic meydan okumasını almaya devam eder (`api_fail()`).
- Yetkilendirme: istemci ve dönüş adresi doğrulanmadan hiçbir yere
  yönlendirilmez (açık yönlendirme yok); sonra her hata istemciye `error`,
  `state`, `iss` (RFC 9207) ile döner. PKCE zorunlu, yalnız S256. Dönüş
  adresi birebir eşleşir, yalnız loopback http'de port serbest (RFC 8252).
  Giriş yoksa panel girişine (`send_to` ile, iki adımlı doğrulama dahil)
  gider. Onay ekranı çerçeveye alınamaz (`X-Frame-Options: DENY`,
  `frame-ancestors 'none'`), CSRF jetonu ister; kişi kutuları kaldırarak
  daraltır, genişletemez. Kod 60 sn yaşar, kullanılınca işaretlenir;
  ikinci kez gelirse ondan çıkan bağlantı kapatılır (RFC 6749 4.1.2).
- Dinamik kayıt (RFC 7591) açık, adres başına saatte 10 (`waf_rate`).
  Gizli istemci (`client_secret_basic/post`) için `pgcs_…` gizli bir kez
  verilir, HMAC'i saklanır; yanlış gizli `api_offence()`. Hiç bağlanmamış ya
  da 30 gündür kullanılmayan dinamik istemciler ve eski kodlar günlük API
  bakımında silinir.
- Panel (`api_settings.php`): yeni uygulama penceresinde üçüncü tür,
  satırda `bi-plug` ve bağlantı sayısı, çekmecede keşif adresi; Cihazlar
  sekmesi bu türde "Bağlantılar" adıyla bağlantıları listeler ve tek tek
  kapatır (mevcut `device_revoke`).

**Ödün / sonraya kalan.** Kayıtlı istemci listesi (kapatma, silme) panelde
yok — bağlantı kapatılabiliyor ama istemci satırı yalnız temizlikle gidiyor.
Kişinin kendi "bağlı uygulamalarım" ekranı yok. İstemci kimlik belgesi
(CIMD), `id_token`/OIDC ve MCP sunucusunun kendisi planın sonraki
fazları. Yenilemede daha dar `scope` istenebilir ama saklanmaz (bağlantı
onaylananı korur).

**Doğrulama.** Sandbox'ta (PHP yerleşik sunucusu + `.htaccess` taklidi
yönlendirici) uçtan uca: panelden OAuth uygulaması açma, keşif belgeleri,
kaynak belgesi, 401'deki Bearer başlığı, kayıt (geçerli/geçersiz dönüş),
girişsiz yönlendirme, yanlış dönüş adresi hata sayfası, plain PKCE reddi,
onay ekranı (başlıklar, kaçışlı ad, kutular), izin/ret, yanlış doğrulayıcı,
jeton, API'de kapsam (verilmeyen 403), kod tekrarında bağlantının
kapanması, cihaz yenileme ucunun reddi, yenileme, daha geniş kapsam reddi,
başka istemcinin jetonu, geri alma, gizli istemcide Basic/yanlış gizli,
preflight. Migration boş şemada ve iki kez koşturuldu (ikincisinde "zaten
var"). `tests/oauth_test.php` saf kuralları sınar. **Doğrulanmayan:**
gerçek bir MCP istemcisiyle (Claude özel bağlayıcı) bağlantı, https'li
sunucuda deneme, IIS'te `.well-known` yolunun yönlendiriciye düşmesi.

---

## 2026.4.10 — Dış API: ham kalan çekirdek uçlar olgunlaştı (webhook yönetimi, ürün/grup/müşteri silme, grup oluşturma, dosya ve kampanya güncelleme, form gönderimi yazma, takvimler) (2026-10-13)

**Sorun.** Modül uçları (ERP, Çalışma Alanı, çeviri) olgunken çekirdek
kaynakların çoğu yalnız okunuyor ya da yarım kalmıştı. Webhook "failing"
durumuna düşünce API'den geri açılamıyordu (panelde Durdur/Sürdür/Yeniden
dene vardı); ürün, ürün grubu ve müşteri silinemiyor, grup
oluşturulamıyordu; dosya ve kampanya güncellenemiyor, form gönderimi tek
okunamıyor ve yazılamıyordu; takvimler API'de hiç yoktu. Çalışma Alanı'nın
Claude önerileri (`includes/workspace/changes.php`) bunların çoğunu panel
yoluyla zaten yapıyordu; dış API geride kalmıştı.

**Çözüm** (kimlik doğrulama ve token tasarımına dokunulmadı; OAuth ayrı
iş).

- **Webhook** (`resources/webhooks.php`): `GET /webhooks/{id}`,
  `PATCH /webhooks/{id}` + `POST /webhooks/{id}/update` (url, events,
  status `active|disabled`), `POST /webhooks/{id}/retry` (panelin
  `webhook_retry`'ı: durum active, kuyruk satırlarının denemesi sıfır),
  `POST /webhooks/{id}/test` (yalnız bu aboneliğe `webhook.test` kuyruk
  satırı; istek akışında ağ çağrısı yok, kapalı abonelikte 409
  `webhook_disabled`), `POST /webhooks/{id}/rotate-secret`.
  **Tuzak:** `POST /webhooks/{id}` ilk sürümden beri *silme*dir
  (`webhooks.delete` `also_accepts POST`); güncelleme bu yüzden ayrı
  adreste. Başka uygulamanın aboneliği 404.
- **Ürün silme** (`products.php`): `DELETE /products/{id}` +
  `POST /products/{id}/delete`. Varsayılan katalog kutusu
  (`explorer_catalog_recycle`'ın aynısı: `recycled=1`,
  `recycled_enabled=enabled`, `enabled=0`, `recycle_bin` satırı);
  `permanent=true` → `pg_pb_delete_product()`. İkisi de `product.deleted`
  duyurur, `recycled` alanıyla. Kutu yoksa 409 `recycle_bin_unavailable`.
- **Ürün grupları** (`product_groups.php`): `POST /product-groups`
  (`add_product_group.php`'nin satırı: browse, attributes 1, adres adı
  kısa açıklama → ad; üst grup verilmezse kök; varsayılan kapalı; alınmış
  ad 409 `name_taken`), `POST /product-groups/{id}/products` (`add` /
  `remove` tek istekte; zaten olan/olmayan hata değil),
  `DELETE /product-groups/{id}` + `POST …/delete` (kutu ya da kalıcı;
  kök 409 `root_group_protected`, içinde grup olan 409
  `group_not_empty` — katalog ekranı alt ağacı birlikte kutuya atar, API
  dal silmez). Ağaç (üst grup değiştirme, sıralama) hâlâ yazılmaz.
  Etiket bulutu yeniden kurulumu `api_product_group_clouds()`'a alındı,
  güncelleme de onu kullanıyor.
- **Liste davranışı değişti:** `GET /products` ve `GET /product-groups`
  kutudaki kayıtları listelemiyor (önceden kapalı kayıt gibi
  listeleniyordu); kimlikle okumada `recycled: true`. Grubun
  `child_ids`'i de kutudakileri saymıyor. Sütun yoksa (2026.4.9 öncesi)
  süzgeç eklenmez (`api_catalog_bin_columns()`).
- **Müşteri silme** (`customers.php`): `DELETE /customers/{id}` +
  `POST …/delete`; kişi ekranının üç silmesi (contacts, grup bağları,
  opt_in). Kullanıcı hesabına bağlı kişi 409 `customer_has_account`,
  ERP carisine bağlı kişi 409 `customer_has_erp_account` (panel bu iki
  reddi yapmaz). `customer.deleted` olayı.
- **Dosya** (`files.php`): `POST /files/{id}` yalnız `description`
  (alternatif metin). Ad (adres) ve klasör yazılmaz; yalnız okunabilen
  (herkese açık klasördeki) dosya.
- **Kampanya** (`offers.php`): `POST /offers/{id}` — `enabled`,
  `description`, `start_date`, `end_date` (boş/null = bitişsiz,
  `PG_OFFER_OPEN_END_DATE`). Koşullar ve sonuçlar editörde; eksik
  kampanya açılsa da eksik kalır.
- **Form gönderimi** (`forms.php`): `GET /forms/{id}/submissions/{submission_id}`,
  `POST`/`PATCH` aynı adres — `complete` ve `values: [{name, value}]`;
  yalnız text box / text area / email address (Çalışma Alanı'nın
  kuralı), e-posta doğrulanır, 10000 karakter. Yeni kapsam
  `forms:write` (rol < 3 ya da manage_forms). Değer okuma
  `api_form_submission_values()`'a alındı.
- **Takvimler** (yeni `resources/calendars.php`): `GET /calendars`,
  `GET /calendars/events` (calendar_id, published, upcoming,
  starts_after/before, updated_since, search; başlangıç sırasıyla imleç),
  `GET /calendars/events/{id}`. Tekrar eden etkinlik bir kez, kuralıyla
  (`unit`, `times`, `days`, `month_by`) ve iptal edilen tekrarlarla
  (`skipped_occurrences`); tekrarlar açılmaz. Saatler sitenin duvar saati
  + ofset (`date('c')`). Yeni kapsam `calendars:read` (rol < 3 ya da
  manage_calendars; rol 3 yalnız `users_calendars_xref`'teki takvimleri
  görür); takvimler kapalıysa 503.
- Olay kataloğu: `customer.deleted`, `product_group.created`,
  `product_group.deleted`; `product.deleted` açıklaması kutuyu da anıyor.
- Panel: API ayarlarındaki kaynak açıklamaları güncellendi, `calendars`
  satırı eklendi; konsolda Takvimler grubu.
- `tools/check_api_schema.php` sunucu eşlemesine takvim etkinliği ve form
  gönderimi eklendi; `tests/api_calendars_test.php`.

**Doğrulama.** Sandbox'ta (`tools/setup_sandbox.sh`) sunucu uygulaması
anahtarıyla gerçek isteklerle: her yeni uç başarı, dry-run, 404, 409 ve
422 yollarıyla; webhook kuyruk satırları ve olay yükleri, katalog kutusu
ve `recycle_bin` satırları, form_data satırları veritabanından okundu;
takvimde rol 3 + manage_calendars sahibiyle yalnız atanmış takvimin
göründüğü ve hak kaldırılınca 403 döndüğü denendi. Webhook oluşturma ve
adres değiştirme sandbox'ta DNS çözülmediği için yalnız ret yolundan
denendi; `/test` ile kuyruğa giren satırın gerçek alıcıya gönderimi
denenmedi.

## 2026.4.10 — Ürün listesi sıralamayı kolon koduyla tutuyor: `pg_list_state()` (2026-10-13)

**Sorun** (Plan 1 Faz 5). `view_products.php` sıralamayı oturumda
çevrilmiş başlıkla ("Fiyat") tutuyor, her filtrede 10 kopya blokta uzun
bir `!=` zinciriyle doğruluyordu. Panel dili değişince kayıtlı başlık
tanınmıyor, sıralama "Son Değişiklik"e dönüyordu.

**Çözüm.**

- `pg_list_state($screen, $columns, $default)` (`includes/fn/output.php`):
  `$_SESSION['software']['list_state'][$screen]` altında kod + yön; yalnız
  `$columns` anahtarı olan kod geçer, gerisi varsayılana düşer; `desc`
  dışındaki yön `asc`; yönsüz seçilen kolon varsayılan kolonsa varsayılan
  yönde, değilse artan açılır; etiket taşıyan eski bağlantı koda eşlenir;
  `screen` pozitif tam sayı değilse 1.
- `view_products.php`: 10 blok birer kod listesi (`$sort_codes`),
  etiket `switch`'i kod → SQL kolonu haritası, 39 başlık kod taşıyor.
  Etiketi boş özel alan sıralanamaz.
- `get_column_heading()` isteğe bağlı `$key` alır (bağlantı değeri ve
  karşılaştırma); vermeyen ekranlar eskisi gibi.
- `duplicate_products.php` başlık tabanlı eski anahtarı okumaya devam eder;
  ürün listesi o anahtarı her istekte doldurur.
- SEO filtresi şema hazır değilken tüm ürünlere düşünce sıralama da artık
  o listenin kolonlarıyla doğrulanır.
- **Davranış farkı:** `order`'sız elle yazılmış adreste Tükenen Ürünler'de
  "Stokta Tükenme Tarihi" azalan açılır (eskiden artan). Başlık
  bağlantıları her zaman yön taşır.
- `tests/list_state_test.php`.

**Doğrulama.** Sandbox'ta eski kopyayla 105 istek (10 filtre, kolonlar,
yönler, geçersiz değerler): HTML ve sıra aynı; "Etki" ve "Başlangıç
Sayfası"ndaki fark eşit değerlerden, eski kod da iki çalıştırmada farklı
sıra veriyor. Dil `tr` → `en`: "Fiyat ↓" → "Price ↓" korundu.

## 2026.4.10 — Sayfa Ekle ve Sayfa Düzenle tek sayfa türü formunu paylaşıyor (2026-10-13)

Site sahibine görünen bir değişiklik yok; `changelog.txt`'ye madde girmez.

**Sorun** (Plan 1 Faz 6). `add_page.php` (2.781 satır) ile `edit_page.php`
(5.082 satır) sayfa türü seçicisini, Sistem Seçenekleri kartını, POST'tan
tür özelliklerinin okunmasını ve türe bağlı yan kayıtları paralel
kopyalarda tutuyordu (1.759 ortak satır). Bir türe alan eklemek iki yerde
aynı değişikliği gerektiriyordu.

**Çözüm.** `includes/forms/page_form.php` (ERP `invoice_form.php` deseni,
`PG_FUNCTIONS_DIR` kapısı); kök dosyalar ince sarmalayıcı kaldı
(1.773 ve ~550 satır).

- `pg_page_form_type_fields($page_type, $layout_type, $properties, $user, $page_id)`:
  seçici, Sistem Seçenekleri kartı, WYSIWYG kodu. Düzenleme düğmeleri ve
  kayıtlı özelliklerin yüklenmesi `edit_page.php`'de kaldı.
- `pg_page_form_new_page_properties()`: yeni sayfanın varsayılanları.
- `pg_page_form_type_properties_from_post($page_type, $page_id)`: her tür
  için POST → özellik dizisi. Kişi grubu, özel klasör ve özel form id'leri
  ham gelir; erişim denetimi ve teklif alanları çağıranda.
- `pg_page_form_save_type_links($page_type, $page_id, $user)`: arama
  sonuçları etiket bulutu, form görünüm dizini liste görünümleri, takvim
  bağları. Tür tablosuna yazılmadan **önce** çağrılır: etiket bulutu
  yardımcısı eski değerleri tablodan okur.
- Etiket bulutu yardımcısına işaretsiz "ürünlerde ara" kutusu `0` olarak
  geçer, `''` değil: yardımcı `== 0` ile bakar ve PHP 8'de `'' == 0`
  yanlıştır.

**Görünür farklar (yalnız Sayfa Ekle; artık Düzenle ile aynı çiziliyor):**
takvim varsayılan görünümünde "Aylık" açıkça seçili; arama sonuçları ürün
grubu listesi select türü grupları göstermiyor; `interactive_page_feature`
çapası, üç açılır kutuda `w-100`, birkaç etiket sınıfı.

**Doğrulama.** Sandbox'ta değişiklik öncesi kopya ikinci sunucuda: 39
sayfanın düzenleme ekranı fark 0; 40 türün her biri Sayfa Ekle ve Sayfa
Düzenle'de varsayılan ve ters çevrilmiş gövdeyle gönderildi, veritabanı
dökümü fark 0. Rol 3 kullanıcıyla ve tür değiştirerek düzenleme
denenmedi.

## 2026.4.10 — Panel eylem tablosu tamamlandı: `api.php`'de `switch` ve muafiyet zinciri kalmadı (2026-10-13)

Kullanıcıya görünen değişiklik yok; `changelog.txt`'ye madde girmez.

**Sorun** (Plan 1 Faz 3'ün kalanı; ilk parça yukarıda "2026.4.8 — Panel
eylem tablosu"). İlk parçadan sonra `api.php`'de 70 satır içi case ile
`chat_*` (11), `ws_*` (150) ve `site_chat_*` (6) devreden case'leri ve
51 tam ad + 3 önekten oluşan `and ($action != '…')` muafiyet zinciri
duruyordu. Tablonun `exempt` sütunu yalnız bilgi amaçlıydı; zincirle
uyuşmasını bir test ayrıştırarak denetliyordu.

**Çözüm.**

- **Kalan 70 eylem grup dosyalarına taşındı** (`includes/panel/`):
  `dashboard.php` (8), `notifications.php` (9), `commerce.php` (23),
  `content.php` (14), `designer.php` (16), `shared_component.php` (1). Envanter
  önerisinden iki sapma: `get_common_regions` ve `test` `content.php`'de
  (ikisi de LiveSite göç aracının uçları, `get_pages`/`get_folders` ile
  aynı yerde), `test.php` diye tek fonksiyonluk dosya açılmadı.
- **Devreden aileler** tek handler'a bağlandı: `pg_panel_chat`,
  `pg_panel_site_chat` (`chat.php`), `pg_panel_ws` (`workspace.php`).
  Tabloda her ad ayrı satırdır (adlar `pg_panel_actions()` içinde döngüyle
  eklenir), handler eylem adını modüle aynen geçirir.
- **Muafiyet zinciri tablodan üretilir.** `pg_panel_gate($action)`:
  `exempt` → genel kapı yok; `session` → oturum ister, rol istemez
  (`any_role`, yalnız `get_form`/`get_forms`); `role` → oturum + rol ≤ 1
  (tabloda olmayan her ad dahil). `pg_panel_exempt_prefixes()` (`chat_`,
  `site_chat_`, `ws_`) tabloda olmayan aile adını da muaf tutar; eskiden de
  öyleydi, bilinmeyen `ws_x` "Invalid login." değil "Invalid action." alır.
  Zincirdeki gerekçe yorumları tablo satırlarının üstüne taşındı.
- **`api.php`** artık önsöz + kapı + `pg_panel_dispatch()` + "Invalid
  action." + `respond()` / `validate_token()`. 5.287 → 245 satır.
  `_sc_unique_name()` yalnız `shared_component` kullandığı için
  `shared_component.php`'ye taşındı.
- **Yeni isteğe bağlı sütunlar:** `any_role`; `public` (oturumsuz ziyaretçi
  ucu: sepet, ürün, taksit, kargo, çapraz satış, site sohbeti — "muaf ve
  yazıyorsa token ister" kuralının bilinçli istisnası); `token_by`
  (`offer_editor`: token'ı `pg_offer_editor_handle()` denetler).
- **`global $user`:** oturum kullanıcısını yükleyen her handler önce
  `global $user;` der (`designer` ayrıca `$folders_that_user_has_access_to`).
  Gövdeler eskiden global kapsamda çalışıyordu; `check_folder_access_in_array()`,
  `select_folder()`, `get_page_options()`, `pg_tour_ready()`, sayfa/widget
  çizicileri `$user`'ı `global` ile okur. Yalnız kanıtlanmış zincirlere değil
  `$user` atayan her handler'a konuldu: hangi yardımcının hangi alt eylemden
  çağrıldığını tek tek izlemek yerine eski anlamı aynen korur.
- **Yol düzeltmeleri:** `dirname(__FILE__)` → `PG_FUNCTIONS_DIR` (37 include satırı).
  Gövdelerde göreli yol, `__DIR__`, case düzeyinde `return`/`break` (sondaki
  hariç) ve gövdenin atamadan okuduğu dış kapsam değişkeni yok (tokenizer ile
  tarandı); include edilen dosyaların hiçbiri üst düzeyde değişken tutmuyor.
- **Davranış farkı (tek):** `action` dizi gelirse eski zincirdeki
  `strpos()` PHP 8'de TypeError → HTTP 500 "Beklenmeyen bir hata oluştu."
  veriyordu; şimdi genel kapıdan geçip `{"status":"error","message":"Invalid
  action."}` döner.
- **`tests/panel_actions_test.php`:** zinciri ayrıştıran test yerine muaf
  adların **sabit listesi** (51 ad; muafiyet eklemek iki yerde bilerek
  yapılır), aile satırlarının muaf ve doğru handler'da olduğu,
  `pg_panel_gate()` yanıtları (bilinmeyen, dizi, tamsayı, `Null`), `public`
  satırların muaf olduğu, `token_by` fonksiyonunun `validate_token()`
  çağırdığı ve `api.php`'de `switch`/`case` kalmadığı.

**Doğrulama.**

- **Statik:** 74 blok (70 case + 3 aile + `default` hariç) için eski case
  gövdesi ile yeni handler gövdesi **token düzeyinde** karşılaştırıldı
  (yorum ve boşluk hariç; dize içerikleri dahil): `global` satırı, sondaki
  `break;` ve `dirname(__FILE__)` → `PG_FUNCTIONS_DIR` dışında fark 0.
  Çok satırlı dizelerin içi dedent edilmedi (SQL girintisi aynen).
- **Sandbox, eski/yeni yan yana:** `development` bir worktree'de `:8001`'de,
  yeni ağaç `:8000`'de, aynı veritabanı ve oturum. 238 eylem + 4 bilinmeyen
  ad × {oturumsuz, admin tokensız, admin, rol 2, rol 3, rol 3 +
  `manage_ecommerce`} ve 30 tasarımcı/ortak bileşen/pano widget'ı alt
  eylemi: 1.604 istek. Port ve varlık sürüm damgası (`?v=`) normalize
  edildikten sonra tek fark yukarıdaki dizi `action`. PHP uyarı sayıları
  aynı (yalnız sepet oturumunun ilk istek uyarısı sıraya bağlı yer
  değiştiriyor); Fatal yok.
- **Doğrulanamayan:** sandbox'ta Çalışma Alanı ve sohbet kapalı (kapı ve
  token yolları denendi, modül içi değil); ağ isteyen ya da ağır işler
  (`software_update`, `software_backup`, `ca_bundle_*`, `push_test`,
  `sitemap_check`, Iyzipay, kargo) yalnız oturumsuz ve tokensız durumda
  karşılaştırıldı.
